/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "zbxalgo.h"
#include "zbxregexp.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxprometheus.h"

/* Defines maximum row length to be written in error message in the case of parsing failure */
#define ZBX_PROMEHTEUS_ERROR_MAX_ROW_LENGTH	50

#define ZBX_PROMETHEUS_HINT_HELP	0
#define ZBX_PROMETHEUS_HINT_TYPE	1

#define ZBX_PROMETHEUS_TYPE_UNTYPED	"untyped"

#define ZBX_PROMETHEUS_ERROR_ROW_NUM	10

typedef enum
{
	ZBX_PROMETHEUS_CONDITION_OP_EQUAL,
	ZBX_PROMETHEUS_CONDITION_OP_REGEX,
	ZBX_PROMETHEUS_CONDITION_OP_EQUAL_VALUE,
}
zbx_prometheus_condition_op_t;

/* key-value matching data */
typedef struct
{
	/* the key to match, optional - can be NULL */
	char				*key;
	/* the pattern to match */
	char				*pattern;
	/* the condition operations */
	zbx_prometheus_condition_op_t	op;
}
zbx_prometheus_condition_t;

/* the prometheus pattern filter */
typedef struct
{
	/* metric filter, optional - can be NULL */
	zbx_prometheus_condition_t	*metric;
	/* value filter, optional - can be NULL */
	zbx_prometheus_condition_t	*value;
	/* label filters */
	zbx_vector_ptr_t		labels;
}
zbx_prometheus_filter_t;

/* the prometheus label */
typedef struct
{
	char	*name;
	char	*value;
}
zbx_prometheus_label_t;

/* the prometheus data row */
typedef struct
{
	char			*metric;
	char			*value;
	zbx_vector_ptr_t	labels;
	char			*raw;
}
zbx_prometheus_row_t;

/* the prometheus metric HELP, TYPE hints in comments */
typedef struct
{
	char	*metric;
	char	*type;
	char	*help;
}
zbx_prometheus_hint_t;

/* TYPE, HELP hint hashset support */

static zbx_hash_t	prometheus_hint_hash(const void *d)
{
	const zbx_prometheus_hint_t	*hint = (zbx_prometheus_hint_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(hint->metric);
}

static int	prometheus_hint_compare(const void *d1, const void *d2)
{
	const zbx_prometheus_hint_t	*hint1 = (zbx_prometheus_hint_t *)d1;
	const zbx_prometheus_hint_t	*hint2 = (zbx_prometheus_hint_t *)d2;

	return strcmp(hint1->metric, hint2->metric);
}

/******************************************************************************
 *                                                                            *
 * Function: str_loc_dup                                                      *
 *                                                                            *
 * Purpose: allocates and copies substring at the specified location          *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             loc - [IN] the substring location                              *
 *                                                                            *
 * Return value: The copied substring.                                        *
 *                                                                            *
 ******************************************************************************/
static char	*str_loc_dup(const char *src, const zbx_strloc_t *loc)
{
	char	*str;
	size_t	len;

	len = loc->r - loc->l + 1;
	str = zbx_malloc(NULL, len + 1);
	memcpy(str, src + loc->l, len);
	str[len] = '\0';

	return str;
}

/******************************************************************************
 *                                                                            *
 * Function: str_loc_unquote_dyn                                              *
 *                                                                            *
 * Purpose: unquotes substring at the specified location                      *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             loc - [IN] the substring location                              *
 *                                                                            *
 * Return value: The unquoted and copied substring.                           *
 *                                                                            *
 ******************************************************************************/
static char	*str_loc_unquote_dyn(const char *src, const zbx_strloc_t *loc)
{
	char		*str, *ptr;

	src += loc->l + 1;

	str = ptr = zbx_malloc(NULL, loc->r - loc->l);

	while ('"' != *src)
	{
		if ('\\' == *src)
		{
			switch (*(++src))
			{
				case '\\':
					*ptr++ = '\\';
					break;
				case 'n':
					*ptr++ = '\n';
					break;
				case '"':
					*ptr++ = '"';
					break;
			}
		}
		else
			*ptr++ = *src;
		src++;
	}
	*ptr = '\0';

	return str;
}

/******************************************************************************
 *                                                                            *
 * Function: str_loc_unescape_hint_dyn                                        *
 *                                                                            *
 * Purpose: unescapes HELP hint                                               *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             loc - [IN] the substring location                              *
 *                                                                            *
 * Return value: The unescaped and copied HELP string.                        *
 *                                                                            *
 ******************************************************************************/
static char	*str_loc_unescape_hint_dyn(const char *src, const zbx_strloc_t *loc)
{
	char		*str, *pout;
	const char	*pin;
	size_t		len;

	len = loc->r - loc->l + 1;
	str = zbx_malloc(NULL, len + 1);

	for (pout = str, pin = src + loc->l; pin <= src + loc->r; pin++)
	{
		if ('\\' == *pin)
		{
			pin++;
			switch (*pin)
			{
				case '\\':
					*pout++ = '\\';
					break;
				case 'n':
					*pout++ = '\n';
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					*pout++ = '?';
			}
		}
		else
			*pout++ = *pin;
	}

	*pout++  ='\0';

	return str;
}

/******************************************************************************
 *                                                                            *
 * Function: str_loc_cmp                                                      *
 *                                                                            *
 * Purpose: compares substring at the specified location with the specified   *
 *          text                                                              *
 *                                                                            *
 * Parameters: src      - [IN] the source string                              *
 *             loc      - [IN] the substring location                         *
 *             text     - [IN] the text to compare with                       *
 *             text_len - [IN] the text length                                *
 *                                                                            *
 * Return value: -1 - the substring is less than the specified text           *
 *                0 - the substring is equal to the specified text            *
 *                1 - the substring is greater than the specified text        *
 *                                                                            *
 ******************************************************************************/
static int	str_loc_cmp(const char *src, const zbx_strloc_t *loc, const char *text, size_t text_len)
{
	ZBX_RETURN_IF_NOT_EQUAL(loc->r - loc->l + 1, text_len);
	return memcmp(src + loc->l, text, text_len);
}

/******************************************************************************
 *                                                                            *
 * Function: str_loc_op                                                       *
 *                                                                            *
 * Purpose: parses condition operation at the specified location              *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             loc - [IN] the substring location                              *
 *                                                                            *
 * Return value: The condition operation.                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_prometheus_condition_op_t	str_loc_op(const char *data, const zbx_strloc_t *loc)
{
	/* the operation has been already validated during parsing, */
	/*so there are only three possibilities:                    */
	/*   '=' - the only sinle character operation               */
	/*   '==' - ends with '='                                   */
	/*   '=~' - ends with '~'                                   */

	if (loc->l == loc->r)
		return ZBX_PROMETHEUS_CONDITION_OP_EQUAL;

	if ('~' == data[loc->r])
		return ZBX_PROMETHEUS_CONDITION_OP_REGEX;

	return ZBX_PROMETHEUS_CONDITION_OP_EQUAL_VALUE;
}

/******************************************************************************
 *                                                                            *
 * Function: skip_spaces                                                      *
 *                                                                            *
 * Purpose: skips spaces                                                      *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             pos - [IN] the starting position                               *
 *                                                                            *
 * Return value: The position of the next non space character.                *
 *                                                                            *
 ******************************************************************************/
static size_t	skip_spaces(const char *data, size_t pos)
{
	while (' ' == data[pos] || '\t' == data[pos])
		pos++;

	return pos;
}

/******************************************************************************
 *                                                                            *
 * Function: skip_row                                                         *
 *                                                                            *
 * Purpose: skips until beginning of the next row                             *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             pos - [IN] the starting position                               *
 *                                                                            *
 * Return value: The position of the next row space character.                *
 *                                                                            *
 ******************************************************************************/
static size_t	skip_row(const char *data, size_t pos)
{
	const char	*ptr;

	if (NULL == (ptr = strchr(data + pos, '\n')))
		return strlen(data + pos) + pos;

	return ptr - data + 1;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_metric                                                     *
 *                                                                            *
 * Purpose: parses metric name                                                *
 *                                                                            *
 * Parameters: data - [IN] the source string                                  *
 *             pos  - [IN] the starting position                              *
 *             loc  - [OUT] the metric location in the source string          *
 *                                                                            *
 * Return value: SUCCEED - the metric name was parsed out successfully        *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	parse_metric(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr = data + pos;

	if (0 == isalpha(*ptr) && ':' != *ptr && '_' != *ptr)
		return FAIL;

	while ('\0' != *(++ptr))
	{
		if (0 == isalnum(*ptr) && ':' != *ptr && '_' != *ptr)
			break;
	}

	loc->l = pos;
	loc->r = ptr - data - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_label                                                      *
 *                                                                            *
 * Purpose: parses label name                                                 *
 *                                                                            *
 * Parameters: data - [IN] the source string                                  *
 *             pos  - [IN] the starting position                              *
 *             loc  - [OUT] the label location in the source string           *
 *                                                                            *
 * Return value: SUCCEED - the label name was parsed out successfully         *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	parse_label(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr = data + pos;

	if (0 == isalpha(*ptr) && '_' != *ptr)
		return FAIL;

	while ('\0' != *(++ptr))
	{
		if (0 == isalnum(*ptr) && '_' != *ptr)
			break;
	}

	loc->l = pos;
	loc->r = ptr - data - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_label_op                                                   *
 *                                                                            *
 * Purpose: parses label operation                                            *
 *                                                                            *
 * Parameters: data - [IN] the source string                                  *
 *             pos  - [IN] the starting position                              *
 *             loc  - [OUT] the operation location in the source string       *
 *                                                                            *
 * Return value: SUCCEED - the label operation was parsed out successfully    *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	parse_label_op(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr = data + pos;

	if ('=' != *ptr)
		return FAIL;

	loc->l = loc->r = pos;

	if ('~' == ptr[1])
		loc->r++;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_label_value                                                *
 *                                                                            *
 * Purpose: parses label value                                                *
 *                                                                            *
 * Parameters: data - [IN] the source string                                  *
 *             pos  - [IN] the starting position                              *
 *             loc  - [OUT] the value location in the source string           *
 *                                                                            *
 * Return value: SUCCEED - the label value was parsed out successfully        *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	parse_label_value(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr;

	ptr = data + pos;

	if ('"' != *ptr)
		return FAIL;

	loc->l = pos;

	while ('"' != *(++ptr))
	{
		if ('\\' == *ptr)
		{
			ptr++;

			if ('\\' != *ptr && 'n' != *ptr && '"' != *ptr)
				return FAIL;
			continue;
		}
		if ('\0' == *ptr)
			return FAIL;
	}

	loc->r = ptr - data;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_metric_op                                                  *
 *                                                                            *
 * Purpose: parses metric operation                                           *
 *                                                                            *
 * Parameters: data - [IN] the source string                                  *
 *             pos  - [IN] the starting position                              *
 *             loc  - [OUT] the operation location in the source string       *
 *                                                                            *
 * Return value: SUCCEED - the metric operation was parsed out successfully   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	parse_metric_op(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr = data + pos;

	if ('=' != *ptr)
		return FAIL;

	if ('=' != ptr[1])
		return FAIL;

	loc->l = pos;
	loc->r = pos + 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: str_copy_lowercase                                               *
 *                                                                            *
 * Purpose: copies lowercase converted string to a buffer                     *
 *                                                                            *
 * Parameters: dst  - [OUT] the output buffer                                 *
 *             size - [IN] the output buffer size                             *
 *             src  - [IN] the source string to copy                          *
 *             len  - [IN] the length of the source string                    *
 *                                                                            *
 * Return value: The number of bytes copied.                                  *
 *                                                                            *
 ******************************************************************************/
static int	str_copy_lowercase(char *dst, int size, const char *src, int len)
{
	int	i;

	if (0 == size)
		return 0;

	if (size > len + 1)
		size = len + 1;

	for (i = 0; i < size - 1 && '\0' != *src; i++)
		*dst++ = tolower(*src++);

	*dst = '\0';

	return i;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_metric_value                                               *
 *                                                                            *
 * Purpose: parses metric value                                               *
 *                                                                            *
 * Parameters: data - [IN] the source string                                  *
 *             pos  - [IN] the starting position                              *
 *             loc  - [OUT] the value location in the source string           *
 *                                                                            *
 * Return value: SUCCEED - the metric value was parsed out successfully       *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	parse_metric_value(const char *data, size_t pos, zbx_strloc_t *loc)
{
	const char	*ptr = data + pos;
	int		len;
	char		buffer[4];

	loc->l = pos;

	len = ZBX_CONST_STRLEN("nan");
	if (len == str_copy_lowercase(buffer, sizeof(buffer), ptr, len) && 0 == memcmp(buffer, "nan", len))
	{
		loc->r = pos + 2;
		return SUCCEED;
	}

	if ('-' == *ptr || '+' == *ptr)
		ptr++;

	len = ZBX_CONST_STRLEN("inf");
	if (len == str_copy_lowercase(buffer, sizeof(buffer), ptr, len) && 0 == memcmp(buffer, "inf", len))
	{
		loc->r = ptr - data + 2;
		return SUCCEED;
	}

	if (FAIL == zbx_number_parse(ptr, &len))
		return FAIL;

	ptr += len;

	if ('e' == *ptr || 'E' == *ptr)
	{
		ptr++;

		if ('-' == *ptr || '+' == *ptr)
			ptr++;

		if (0 == isdigit(*ptr))
			return FAIL;

		while (0 != isdigit(*ptr))
			ptr++;
	}

	loc->r = ptr - data - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_condition_free                                        *
 *                                                                            *
 ******************************************************************************/
static void	prometheus_condition_free(zbx_prometheus_condition_t *condition)
{
	zbx_free(condition->key);
	zbx_free(condition->pattern);
	zbx_free(condition);
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_condition_create                                      *
 *                                                                            *
 * Purpose: allocates and initializes conditionect                            *
 *                                                                            *
 * Parameters: key     - [IN] the key to match                                *
 *             pattern - [IN] the matching pattern                            *
 *             op      - [IN] the matching operation                          *
 *                                                                            *
 * Return value: the created condition object                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_prometheus_condition_t	*prometheus_condition_create(char *key, char *pattern,
		zbx_prometheus_condition_op_t op)
{
	zbx_prometheus_condition_t	*condition;

	condition = (zbx_prometheus_condition_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_condition_t));
	condition->key = key;
	condition->pattern = pattern;
	condition->op = op;

	return condition;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_filter_clear                                          *
 *                                                                            *
 * Purpose: clears resources allocated by prometheus filter                   *
 *                                                                            *
 * Parameters: filter - [IN] the filter to clear                              *
 *                                                                            *
 ******************************************************************************/
static void	prometheus_filter_clear(zbx_prometheus_filter_t *filter)
{
	if (NULL != filter->metric)
		prometheus_condition_free(filter->metric);

	if (NULL != filter->value)
		prometheus_condition_free(filter->value);

	zbx_vector_ptr_clear_ext(&filter->labels, (zbx_clean_func_t)prometheus_condition_free);
	zbx_vector_ptr_destroy(&filter->labels);
}

/******************************************************************************
 *                                                                            *
 * Function: parse_condition                                                  *
 *                                                                            *
 * Purpose: parses condition data - key, pattern and operation                *
 *                                                                            *
 * Parameters: data        - [IN] the filter data                             *
 *             pos         - [IN] the starting position in filter data        *
 *             loc_key     - [IN] the condition key location                  *
 *             loc_op      - [IN] the condition operation location            *
 *             loc_pattern - [IN] the condition pattern location              *
 *                                                                            *
 * Return value: SUCCEED - the condition data was parsed successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	parse_condition(const char *data, size_t pos, zbx_strloc_t *loc_key, zbx_strloc_t *loc_op,
		zbx_strloc_t *loc_pattern)
{
	if (SUCCEED != parse_label(data, pos, loc_key))
		return FAIL;

	pos = skip_spaces(data, loc_key->r + 1);

	if (SUCCEED != parse_label_op(data, pos, loc_op))
		return FAIL;

	pos = skip_spaces(data, loc_op->r + 1);

	if (SUCCEED != parse_label_value(data, pos, loc_pattern))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_filter_parse_labels                                   *
 *                                                                            *
 * Purpose: parses label conditions                                           *
 *                                                                            *
 * Parameters: filter - [IN/OUT] the filter                                   *
 *             data   - [IN] the filter data                                  *
 *             pos    - [IN] the starting position in filter data             *
 *             loc    - [IN] the location of label conditions                 *
 *             error  - [IN] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the label conditions were parsed successfully      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_filter_parse_labels(zbx_prometheus_filter_t *filter, const char *data, size_t pos,
		zbx_strloc_t *loc, char **error)
{
	zbx_strloc_t	loc_key, loc_value, loc_op;

	loc->l = pos;
	pos = skip_spaces(data, pos + 1);

	while ('}' != data[pos])
	{
		if (FAIL == parse_condition(data, pos, &loc_key, &loc_op, &loc_value))
		{
			*error = zbx_dsprintf(*error, "cannot parse label condition at \"%s\"", data + pos);
			return FAIL;
		}

		if (0 == str_loc_cmp(data, &loc_key, "__name__", ZBX_CONST_STRLEN("__name__")))
		{
			if (NULL != filter->metric)
			{
				*error = zbx_strdup(*error, "duplicate metric condition specified");
				return FAIL;
			}

			filter->metric = prometheus_condition_create(NULL,
					str_loc_unquote_dyn(data, &loc_value), str_loc_op(data, &loc_op));
		}
		else
		{
			zbx_prometheus_condition_t	*condition;

			condition = prometheus_condition_create(str_loc_dup(data, &loc_key),
					str_loc_unquote_dyn(data, &loc_value), str_loc_op(data, &loc_op));
			zbx_vector_ptr_append(&filter->labels, condition);
		}

		pos = skip_spaces(data, loc_value.r + 1);

		if (',' != data[pos])
		{
			if ('}' == data[pos])
				break;

			*error = zbx_strdup(*error, "missing label condition list terminating character \"}\"");
			return FAIL;
		}

		pos = skip_spaces(data, pos + 1);
	}

	loc->r = pos;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_filter_init                                           *
 *                                                                            *
 * Purpose: intializes prometheus pattern filter from the specified data      *
 *                                                                            *
 * Parameters: filter - [IN/OUT] the filter                                   *
 *             data   - [IN] the filter data                                  *
 *             error  - [IN] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the filter was initialized successfully            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_filter_init(zbx_prometheus_filter_t *filter, const char *data, char **error)
{
	int		ret = FAIL;
	size_t		pos = 0;
	zbx_strloc_t	loc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memset(filter, 0, sizeof(zbx_prometheus_filter_t));
	zbx_vector_ptr_create(&filter->labels);

	pos = skip_spaces(data, pos);

	if (SUCCEED == parse_metric(data, pos, &loc))
	{
		filter->metric = prometheus_condition_create(NULL, str_loc_dup(data, &loc),
				ZBX_PROMETHEUS_CONDITION_OP_EQUAL);

		pos = skip_spaces(data, loc.r + 1);
	}

	if ('{' == data[pos])
	{
		if (SUCCEED != prometheus_filter_parse_labels(filter, data, pos, &loc, error))
			goto out;

		pos = loc.r + 1;
	}

	pos = skip_spaces(data, pos);

	/* parse metric value condition */
	if ('\0' != data[pos])
	{
		zbx_strloc_t	loc_op, loc_value;

		if (SUCCEED != parse_metric_op(data, pos, &loc_op))
		{
			*error = zbx_dsprintf(*error, "cannot parse metric comparison operator at \"%s\"", data + pos);
			goto out;
		}

		pos = skip_spaces(data, loc_op.r + 1);

		if (SUCCEED != parse_metric_value(data, pos, &loc_value))
		{
			*error = zbx_dsprintf(*error, "cannot parse metric comparison value at \"%s\"", data + pos);
			goto out;
		}

		pos = skip_spaces(data, loc_value.r + 1);
		if ('\0' != data[pos])
		{
			*error = zbx_dsprintf(*error, "unexpected data after metric comparison value at \"%s\"",
					data + pos);
			goto out;
		}

		filter->value = prometheus_condition_create(NULL, str_loc_dup(data, &loc_value),
				ZBX_PROMETHEUS_CONDITION_OP_EQUAL_VALUE);
		zbx_strlower(filter->value->pattern);
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		prometheus_filter_clear(filter);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() Prometheus pattern error: %s", __func__, *error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_label_free                                            *
 *                                                                            *
 ******************************************************************************/
static void	prometheus_label_free(zbx_prometheus_label_t *label)
{
	zbx_free(label->name);
	zbx_free(label->value);
	zbx_free(label);
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_row_free                                              *
 *                                                                            *
 ******************************************************************************/
static void	prometheus_row_free(zbx_prometheus_row_t *row)
{
	zbx_free(row->metric);
	zbx_free(row->value);
	zbx_free(row->raw);
	zbx_vector_ptr_clear_ext(&row->labels, (zbx_clean_func_t)prometheus_label_free);
	zbx_vector_ptr_destroy(&row->labels);
	zbx_free(row);
}

/******************************************************************************
 *                                                                            *
 * Function: condition_match_key_value                                        *
 *                                                                            *
 * Purpose: matches key,value against filter condition                        *
 *                                                                            *
 * Parameters: condition - [IN] the condition                                 *
 *             key       - [IN] the key (optional, can be NULL)               *
 *             value     - [IN] the value                                     *
 *                                                                            *
 * Return value: SUCCEED - the key,value pair matches condition               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	condition_match_key_value(const zbx_prometheus_condition_t *condition, const char *key,
		const char *value)
{
	/* perform key match, succeeds if key is not defined in filter */
	if (NULL != condition->key && (NULL == key || 0 != strcmp(key, condition->key)))
		return FAIL;

	/* match value */
	switch (condition->op)
	{
		case ZBX_PROMETHEUS_CONDITION_OP_EQUAL:
		case ZBX_PROMETHEUS_CONDITION_OP_EQUAL_VALUE:
			if (0 != strcmp(value, condition->pattern))
				return FAIL;
			break;
		case ZBX_PROMETHEUS_CONDITION_OP_REGEX:
			if (NULL == zbx_regexp_match(value, condition->pattern, NULL))
				return FAIL;
			break;
		default:
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: condition_match_metric_value                                     *
 *                                                                            *
 * Purpose: matches metric value against filter condition                     *
 *                                                                            *
 * Parameters: condition - [IN] the condition                                 *
 *             key       - [IN] the key (optional, can be NULL)               *
 *             value     - [IN] the value                                     *
 *                                                                            *
 * Return value: SUCCEED - the key,value pair matches condition               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	condition_match_metric_value(const char *pattern, const char *value)
{
	char	buffer[5];

	if (SUCCEED != is_double(pattern))
	{
		if ('+' == *pattern)
			pattern++;

		if ('+' == *value)
			value++;

		zbx_strlcpy(buffer, value, sizeof(buffer));
		zbx_strlower(buffer);
		return (0 == strcmp(pattern, buffer) ? SUCCEED : FAIL);
	}

	if (SUCCEED != is_double(value))
		return FAIL;

	if (ZBX_DOUBLE_EPSILON <= fabs(atof(pattern) - atof(value)))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_metric_parse_labels                                   *
 *                                                                            *
 * Purpose: parses metric labels                                              *
 *                                                                            *
 * Parameters: data   - [IN] the metric data                                  *
 *             pos    - [IN] the starting position in metric data             *
 *             labels - [OUT] the parsed labels                               *
 *             loc    - [OUT] the location of label block                     *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the labels were parsed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_metric_parse_labels(const char *data, size_t pos, zbx_vector_ptr_t *labels,
		zbx_strloc_t *loc, char **error)
{
	zbx_strloc_t		loc_key, loc_value, loc_op;
	zbx_prometheus_label_t	*label;

	pos = skip_spaces(data, pos + 1);
	loc->l = pos;

	while ('}' != data[pos])
	{
		zbx_prometheus_condition_op_t	op;

		if (FAIL == parse_condition(data, pos, &loc_key, &loc_op, &loc_value))
		{
			*error = zbx_strdup(*error, "cannot parse label");
			return FAIL;
		}

		op = str_loc_op(data, &loc_op);
		if (ZBX_PROMETHEUS_CONDITION_OP_EQUAL != op)
		{
			*error = zbx_strdup(*error, "invalid label assignment operator");
			return FAIL;
		}

		label = (zbx_prometheus_label_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_label_t));
		label->name = str_loc_dup(data, &loc_key);
		label->value = str_loc_unquote_dyn(data, &loc_value);
		zbx_vector_ptr_append(labels, label);

		pos = skip_spaces(data, loc_value.r + 1);

		if (',' != data[pos])
		{
			if ('}' == data[pos])
				break;

			*error = zbx_strdup(*error, "missing label list terminating character \"}\"");
			return FAIL;
		}

		pos = skip_spaces(data, pos + 1);
	}

	loc->r = pos;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_parse_row                                             *
 *                                                                            *
 * Purpose: parses metric row                                                 *
 *                                                                            *
 * Parameters: filter  - [IN] the prometheus filter                           *
 *             data    - [IN] the metric data                                 *
 *             pos     - [IN] the starting position in metric data            *
 *             prow    - [OUT] the parsed row (NULL if did not match filter)  *
 *             loc_row - [OUT] the location of row in prometheus data         *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the row was parsed successfully                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: If there was no parsing errors, but the row does not match filter*
 *           conditions then success with NULL prow is be returned.           *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_parse_row(zbx_prometheus_filter_t *filter, const char *data, size_t pos,
		zbx_prometheus_row_t **prow, zbx_strloc_t *loc_row, char **error)
{
	zbx_strloc_t		loc;
	zbx_prometheus_row_t	*row;
	int			ret = FAIL, match = SUCCEED, i, j;

	loc_row->l = pos;

	row = (zbx_prometheus_row_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_row_t));
	memset(row, 0, sizeof(zbx_prometheus_row_t));
	zbx_vector_ptr_create(&row->labels);

	/* parse metric and check against the filter */

	if (SUCCEED != parse_metric(data, pos, &loc))
	{
		*error = zbx_strdup(*error, "cannot parse metric name");
		goto out;
	}

	row->metric = str_loc_dup(data, &loc);

	if (NULL != filter->metric)
	{
		if (FAIL == (match = condition_match_key_value(filter->metric, NULL, row->metric)))
			goto out;
	}

	/* parse labels and check against the filter */

	pos = skip_spaces(data, loc.r + 1);

	if ('{' == data[pos])
	{
		if (SUCCEED != prometheus_metric_parse_labels(data, pos, &row->labels, &loc, error))
			goto out;

		for (i = 0; i < filter->labels.values_num; i++)
		{
			zbx_prometheus_condition_t	*condition = filter->labels.values[i];

			for (j = 0; j < row->labels.values_num; j++)
			{
				zbx_prometheus_label_t	*label = row->labels.values[j];

				if (SUCCEED == condition_match_key_value(condition, label->name, label->value))
					break;
			}

			if (j == row->labels.values_num)
			{
				/* no matching labels */
				match = FAIL;
				goto out;
			}
		}

		pos = skip_spaces(data, loc.r + 1);
	}

	/* check if there was a whitespace before metric value */
	if (pos == loc.r + 1)
	{
		const char	*ptr;
		int		len;

		if (NULL == (ptr = strchr(data + pos, '\n')))
			len = strlen(data + pos);
		else
			len = ptr - data + pos;

		*error = zbx_dsprintf(*error, "cannot parse text at: %.*s", len, data + pos);
		goto out;
	}

	/* parse value and check against the filter */

	if (FAIL == parse_metric_value(data, pos, &loc))
	{
		*error = zbx_strdup(*error, "cannot parse metric value");
		goto out;
	}
	row->value = str_loc_dup(data, &loc);

	if (NULL != filter->value)
	{
		if (SUCCEED != (match = condition_match_metric_value(filter->value->pattern, row->value)))
			goto out;
	}

	pos = loc.r + 1;

	if (' ' != data[pos] && '\t' != data[pos] && '\n' != data[pos] && '\0' != data[pos])
	{
		*error = zbx_dsprintf(*error, "invalid character '%c' following metric value", data[pos]);
		goto out;
	}

	/* row was successfully parsed and matched all filter conditions */
	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		prometheus_row_free(row);
		*prow = NULL;

		/* match failure, return success with NULL row */
		if (FAIL == match)
			ret = SUCCEED;
	}
	else
		*prow = row;

	if (SUCCEED == ret)
	{
		/* find the row location */

		pos = skip_row(data, pos);
		if ('\n' == data[--pos])
			pos--;

		loc_row->r = pos;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_help                                                       *
 *                                                                            *
 * Purpose: parses HELP comment metric and help text                          *
 *                                                                            *
 * Parameters: data       - [IN] the prometheus data                          *
 *             pos        - [IN] the starting position in metric data         *
 *             loc_metric - [OUT] the metric location in data                 *
 *             loc_help   - [OUT] the help location in data                   *
 *                                                                            *
 * Return value: SUCCEED - the help hint was parsed successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	parse_help(const char *data, size_t pos, zbx_strloc_t *loc_metric, zbx_strloc_t *loc_help)
{
	const char	*ptr;

	if (SUCCEED != parse_metric(data, pos, loc_metric))
		return FAIL;

	pos = skip_spaces(data, loc_metric->r + 1);
	loc_help->l = pos;

	for (ptr = data + pos; '\0' != *ptr && '\n' != *ptr;)
	{
		if ('\\' == *ptr++)
		{
			if ('\\' != *ptr && 'n' != *ptr)
				return FAIL;
			ptr++;
		}
	}

	loc_help->r = ptr - data - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_type                                                       *
 *                                                                            *
 * Purpose: parses TYPE comment metric and the type                           *
 *                                                                            *
 * Parameters: data       - [IN] the prometheus data                          *
 *             pos        - [IN] the starting position in metric data         *
 *             loc_metric - [OUT] the metric location in data                 *
 *             loc_type   - [OUT] the type location in data                   *
 *                                                                            *
 * Return value: SUCCEED - the type hint was parsed successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	parse_type(const char *data, size_t pos, zbx_strloc_t *loc_metric, zbx_strloc_t *loc_type)
{
	const char	*ptr;

	if (SUCCEED != parse_metric(data, pos, loc_metric))
		return FAIL;

	pos = skip_spaces(data, loc_metric->r + 1);
	loc_type->l = pos;
	ptr = data + pos;
	while (0 != isalpha(*ptr))
		ptr++;

	/* invalid metric type */
	if (pos == (loc_type->r = ptr - data))
		return FAIL;

	loc_type->r--;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_register_hint                                         *
 *                                                                            *
 * Purpose: registers TYPE/HELP comment hint to the specified metric          *
 *                                                                            *
 * Parameters: hints      - [IN/OUT] the hint registry                        *
 *             data       - [IN] the prometheus data                          *
 *             metric     - [IN] the metric                                   *
 *             loc_hint   - [IN] the hint location in prometheus data         *
 *             hint_type  - [IN] the hint type                                *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the hint was registered successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_register_hint(zbx_hashset_t *hints, const char *data, char *metric,
		const zbx_strloc_t *loc_hint, int hint_type, char **error)
{
	zbx_prometheus_hint_t	*hint, hint_local;
	zbx_strloc_t		loc = *loc_hint;

	hint_local.metric = metric;

	if (NULL == (hint = (zbx_prometheus_hint_t *)zbx_hashset_search(hints, &hint_local)))
	{
		hint = zbx_hashset_insert(hints, &hint_local, sizeof(hint_local));
		hint->type = NULL;
		hint->help = NULL;
	}
	else
		zbx_free(metric);

	while ((' ' == data[loc.r] || '\t' == data[loc.r]) && loc.r > loc.l)
		loc.r--;

	if (ZBX_PROMETHEUS_HINT_HELP == hint_type)
	{
		if (NULL != hint->help)
		{
			*error = zbx_dsprintf(*error, "multiple HELP comments found for metric \"%s\"", hint->metric);
			return FAIL;
		}
		hint->help = str_loc_unescape_hint_dyn(data, &loc);
	}
	else /* ZBX_PROMETHEUS_HINT_TYPE */
	{
		if (NULL != hint->type)
		{
			*error = zbx_dsprintf(*error, "multiple TYPE comments found for metric \"%s\"", hint->metric);
			return FAIL;
		}
		hint->type = str_loc_dup(data, &loc);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_parse_hint                                            *
 *                                                                            *
 * Purpose: parses TYPE/HELP comment hint and registers it                    *
 *                                                                            *
 * Parameters: filter     - [IN] the prometheus filter                        *
 *             data       - [IN] the prometheus data                          *
 *             pso        - [IN] the position of comments in prometheus data  *
 *             hints      - [IN/OUT] the hint registry                        *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the hint was registered successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_parse_hint(zbx_prometheus_filter_t *filter, const char *data, size_t pos,
		zbx_hashset_t *hints, zbx_strloc_t *loc, char **error)
{
	int		ret, hint_type;
	zbx_strloc_t	loc_metric, loc_hint;
	char		*metric;

	loc->l = pos;
	pos = skip_spaces(data, pos + 1);

	if ('\0' == data[pos])
	{
		loc->r = pos - 1;
		return SUCCEED;
	}

	if (0 == strncmp(data + pos, "HELP", 4))
	{
		pos = skip_spaces(data, pos + 4);
		ret = parse_help(data, pos, &loc_metric, &loc_hint);
		hint_type = ZBX_PROMETHEUS_HINT_HELP;
	}
	else if (0 == strncmp(data + pos, "TYPE", 4))
	{
		pos = skip_spaces(data, pos + 4);
		ret = parse_type(data, pos, &loc_metric, &loc_hint);
		hint_type = ZBX_PROMETHEUS_HINT_TYPE;
	}
	else
	{
		/* skip the comment */
		const char	*ptr;

		if (NULL != (ptr = strchr(data + pos, '\n')))
			loc->r = ptr - data - 1;
		else
			loc->r = strlen(data + pos) + pos - 1;

		return SUCCEED;
	}

	if (SUCCEED != ret)
	{
		*error = zbx_strdup(*error, "cannot parse comment");
		return FAIL;
	}

	loc->r = loc_hint.r;
	metric = str_loc_dup(data, &loc_metric);

	/* skip hints of metrics not matching filter */
	if (NULL != filter->metric && SUCCEED != condition_match_key_value(filter->metric, NULL, metric))
	{
		zbx_free(metric);
		return SUCCEED;
	}

	return prometheus_register_hint(hints, data, metric, &loc_hint, hint_type, error);
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_parse_rows                                            *
 *                                                                            *
 * Purpose: parses rows with metrics from prometheus data                     *
 *                                                                            *
 * Parameters: filter  - [IN] the prometheus filter                           *
 *             data    - [IN] the metric data                                 *
 *             rows    - [OUT] the parsed rows                                *
 *             hints   - [OUT] the TYPE/HELP hint registry (optional)         *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the rows were parsed successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_parse_rows(zbx_prometheus_filter_t *filter, const char *data, zbx_vector_ptr_t *rows,
		zbx_hashset_t *hints, char **error)
{
	size_t			pos = 0;
	int			row_num = 1, ret = FAIL;
	zbx_prometheus_row_t	*row;
	char			*errmsg = NULL;
	zbx_strloc_t		loc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (pos = 0; '\0' != data[pos]; pos = skip_row(data, pos), row_num++)
	{
		pos = skip_spaces(data, pos);

		/* skip empty strings */
		if ('\n' == data[pos])
			continue;

		if ('#' == data[pos])
		{
			if (NULL != hints)
			{
				if (SUCCEED != prometheus_parse_hint(filter, data, pos, hints, &loc, &errmsg))
					goto out;
				pos = loc.r + 1;
			}
			continue;
		}

		if (SUCCEED != prometheus_parse_row(filter, data, pos, &row, &loc, &errmsg))
			goto out;

		if (NULL != row)
		{
			row->raw = str_loc_dup(data, &loc);
			zbx_vector_ptr_append(rows, row);
		}

		pos = loc.r + 1;
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		const char	*ptr, *suffix = "";
		int		len;

		if (NULL != (ptr = strchr(data + pos, '\n')))
			len = ptr - data - pos;
		else
			len = strlen(data + pos);

		if (ZBX_PROMEHTEUS_ERROR_MAX_ROW_LENGTH < len)
		{
			len = ZBX_PROMEHTEUS_ERROR_MAX_ROW_LENGTH;
			suffix = "...";
		}
		*error = zbx_dsprintf(*error, "data parsing error at row %d \"%.*s%s\": %s", row_num, len, data + pos,
				suffix, errmsg);
		zbx_free(errmsg);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s rows:%d hints:%d", __func__, zbx_result_string(ret),
			rows->values_num, (NULL == hints ? 0 : hints->num_data));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_extract_value                                         *
 *                                                                            *
 * Purpose: extracts value from filtered rows according to output template    *
 *                                                                            *
 * Parameters: filter  - [IN] the prometheus filter                           *
 *             output      - [IN] the output template                         *
 *             value       - [OUT] the extracted value                        *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Return value: SUCCEED - the value was extracted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_extract_value(zbx_vector_ptr_t *rows, const char *output, char **value, char **error)
{
	zbx_prometheus_row_t	*row;

	if (0 == rows->values_num)
	{
		*error = zbx_strdup(*error, "no matching metrics found");
		return FAIL;
	}

	if (1 < rows->values_num)
	{
		int	i, rows_num = ZBX_PROMETHEUS_ERROR_ROW_NUM;
		size_t	error_alloc, error_offset = 0;

		error_alloc = (NULL == *error ? 0 : strlen(*error) + 1);

		zbx_strcpy_alloc(error, &error_alloc, &error_offset, "multiple matching metrics found:\n\n");

		if (rows->values_num < rows_num)
			rows_num = rows->values_num;

		for (i = 0; i < rows_num; i++)
		{
			row = (zbx_prometheus_row_t *)rows->values[i];
			zbx_strcpy_alloc(error, &error_alloc, &error_offset, row->raw);
			zbx_chrcpy_alloc(error, &error_alloc, &error_offset, '\n');
		}

		if (rows->values_num > rows_num)
			zbx_strcpy_alloc(error, &error_alloc, &error_offset, "...");
		else
			(*error)[error_offset - 1] = '\0';

		return FAIL;
	}

	row = (zbx_prometheus_row_t *)rows->values[0];

	if ('\0' != *output)
	{
		int	i;

		for (i = 0; i < row->labels.values_num; i++)
		{
			const zbx_prometheus_label_t	*label = (const zbx_prometheus_label_t *)row->labels.values[i];

			if (0 == strcmp(label->name, output))
			{
				*value = zbx_strdup(NULL, label->value);
				break;
			}
		}

		if (i == row->labels.values_num)
		{
			*error = zbx_strdup(*error, "no label matches the specified output");
			return FAIL;
		}
	}
	else
		*value = zbx_strdup(NULL, row->value);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_prometheus_pattern                                           *
 *                                                                            *
 * Purpose: extracts value from prometheus data by the specified filter       *
 *                                                                            *
 * Parameters: data        - [IN] the prometheus data                         *
 *             fitler_data - [IN] the filter in text format                   *
 *             output      - [IN] the output template                         *
 *             value       - [OUT] the extracted value                        *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Return value: SUCCEED - the value was extracted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_prometheus_pattern(const char *data, const char *filter_data, const char *output, char **value,
		char **error)
{
	zbx_prometheus_filter_t	filter;
	char			*errmsg = NULL;
	int			ret = FAIL;
	zbx_vector_ptr_t	rows;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == prometheus_filter_init(&filter, filter_data, &errmsg))
	{
		*error = zbx_dsprintf(*error, "pattern error: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	zbx_vector_ptr_create(&rows);

	if (FAIL == prometheus_parse_rows(&filter, data, &rows, NULL, error))
		goto cleanup;

	if (FAIL == prometheus_extract_value(&rows, output, value, &errmsg))
	{
		*error = zbx_dsprintf(*error, "data extraction error: %s", errmsg);
		zbx_free(errmsg);
		goto cleanup;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): output:%s", __func__, *value);
	ret = SUCCEED;
cleanup:
	zbx_vector_ptr_clear_ext(&rows, (zbx_clean_func_t)prometheus_row_free);
	zbx_vector_ptr_destroy(&rows);
	prometheus_filter_clear(&filter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_prometheus_to_json                                           *
 *                                                                            *
 * Purpose: converts filtered prometheus data to json to be used with LLD     *
 *                                                                            *
 * Parameters: data        - [IN] the prometheus data                         *
 *             fitler_data - [IN] the filter in text format                   *
 *             value       - [OUT] the converted data                         *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Return value: SUCCEED - the data was converted successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_prometheus_to_json(const char *data, const char *filter_data, char **value, char **error)
{
	zbx_prometheus_filter_t	filter;
	char			*errmsg = NULL;
	int			ret = FAIL, i, j;
	zbx_vector_ptr_t	rows;
	zbx_hashset_t		hints;
	zbx_prometheus_hint_t	*hint, hint_local;
	zbx_hashset_iter_t	iter;
	struct zbx_json		json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == prometheus_filter_init(&filter, filter_data, &errmsg))
	{
		*error = zbx_dsprintf(*error, "pattern error: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	zbx_vector_ptr_create(&rows);
	zbx_hashset_create(&hints, 100, prometheus_hint_hash, prometheus_hint_compare);

	if (FAIL == prometheus_parse_rows(&filter, data, &rows, &hints, error))
		goto cleanup;

	zbx_json_initarray(&json, rows.values_num * 100);

	for (i = 0; i < rows.values_num; i++)
	{
		zbx_prometheus_row_t	*row = (zbx_prometheus_row_t *)rows.values[i];
		char			*hint_type;

		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, row->metric, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, row->value, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_LINE_RAW, row->raw, ZBX_JSON_TYPE_STRING);

		if (0 != row->labels.values_num)
		{
			zbx_json_addobject(&json, ZBX_PROTO_TAG_LABELS);

			for (j = 0; j < row->labels.values_num; j++)
			{
				zbx_prometheus_label_t	*label = (zbx_prometheus_label_t *)row->labels.values[j];
				zbx_json_addstring(&json, label->name, label->value, ZBX_JSON_TYPE_STRING);
			}

			zbx_json_close(&json);
		}

		hint_local.metric = row->metric;
		hint = (zbx_prometheus_hint_t *)zbx_hashset_search(&hints, &hint_local);

		hint_type = (NULL != hint && NULL != hint->type ? hint->type : ZBX_PROMETHEUS_TYPE_UNTYPED);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_TYPE, hint_type, ZBX_JSON_TYPE_STRING);

		if (NULL != hint && NULL != hint->help)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_HELP, hint->help, ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);
	}

	*value = zbx_strdup(NULL, json.buffer);
	zbx_json_free(&json);
	zabbix_log(LOG_LEVEL_DEBUG, "%s(): output:%s", __func__, *value);
	ret = SUCCEED;
cleanup:
	zbx_hashset_iter_reset(&hints, &iter);
	while (NULL != (hint = (zbx_prometheus_hint_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_free(hint->metric);
		zbx_free(hint->help);
		zbx_free(hint->type);
	}
	zbx_hashset_destroy(&hints);

	zbx_vector_ptr_clear_ext(&rows, (zbx_clean_func_t)prometheus_row_free);
	zbx_vector_ptr_destroy(&rows);
	prometheus_filter_clear(&filter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
	return ret;
}

int	zbx_prometheus_validate_filter(const char *pattern, char **error)
{
	zbx_prometheus_filter_t	filter;

	if (FAIL == prometheus_filter_init(&filter, pattern, error))
		return FAIL;

	prometheus_filter_clear(&filter);
	return SUCCEED;
}

int	zbx_prometheus_validate_label(const char *label)
{
	zbx_strloc_t	loc;
	size_t		pos;

	if ('\0' == *label)
		return SUCCEED;

	if (SUCCEED != parse_label(label, 0, &loc))
		return FAIL;

	pos = skip_spaces(label, loc.r + 1);
	if ('\0' != label[pos])
		return FAIL;

	return SUCCEED;
}


#ifdef HAVE_TESTS
#	include "../../../tests/libs/zbxprometheus/prometheus_test.c"
#endif
