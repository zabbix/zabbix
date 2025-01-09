/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxprometheus.h"

#include "zbxalgo.h"
#include "zbxeval.h"
#include "zbxexpr.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxregexp.h"
#include "zbxstr.h"
#include "zbxtypes.h"

#define ZBX_PROMETHEUS_HINT_HELP	0
#define ZBX_PROMETHEUS_HINT_TYPE	1

typedef enum
{
	ZBX_PROMETHEUS_CONDITION_OP_EQUAL,
	ZBX_PROMETHEUS_CONDITION_OP_REGEX,
	ZBX_PROMETHEUS_CONDITION_OP_EQUAL_VALUE,
	ZBX_PROMETHEUS_CONDITION_OP_NOT_EQUAL,
	ZBX_PROMETHEUS_CONDITION_OP_REGEX_NOT_MATCHED
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

ZBX_PTR_VECTOR_DECL(prometheus_condition, zbx_prometheus_condition_t *)

/* the prometheus pattern filter */
typedef struct
{
	/* metric filter, optional - can be NULL */
	zbx_prometheus_condition_t		*metric;
	/* value filter, optional - can be NULL */
	zbx_prometheus_condition_t		*value;
	/* label filters */
	zbx_vector_prometheus_condition_t	labels;
}
zbx_prometheus_filter_t;

/* the prometheus metric HELP, TYPE hints in comments */
typedef struct
{
	char	*metric;
	char	*type;
	char	*help;
}
zbx_prometheus_hint_t;

/* indexing support */

typedef struct
{
	char				*value;
	zbx_vector_prometheus_row_t	rows;
}
zbx_prometheus_index_t;

/* TYPE, HELP hint hashset support */

static zbx_hash_t	prometheus_hint_hash(const void *d)
{
	const zbx_prometheus_hint_t	*hint = (const zbx_prometheus_hint_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(hint->metric);
}

static int	prometheus_hint_compare(const void *d1, const void *d2)
{
	const zbx_prometheus_hint_t	*hint1 = (const zbx_prometheus_hint_t *)d1;
	const zbx_prometheus_hint_t	*hint2 = (const zbx_prometheus_hint_t *)d2;

	return strcmp(hint1->metric, hint2->metric);
}

ZBX_PTR_VECTOR_IMPL(prometheus_label, zbx_prometheus_label_t *)
ZBX_PTR_VECTOR_IMPL(prometheus_row, zbx_prometheus_row_t *)
ZBX_PTR_VECTOR_IMPL(prometheus_label_index, zbx_prometheus_label_index_t *)

ZBX_PTR_VECTOR_IMPL(prometheus_condition, zbx_prometheus_condition_t *)

/******************************************************************************
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
 * Purpose: parses condition operation at the specified location              *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             loc - [IN] the substring location                              *
 *                                                                            *
 * Return value: The condition operation.                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_prometheus_condition_op_t	str_loc_op(const char *src, const zbx_strloc_t *loc)
{
	if ('=' == src[loc->l])
	{
		if ('~' == src[loc->r])
			return ZBX_PROMETHEUS_CONDITION_OP_REGEX;
		else
			return ZBX_PROMETHEUS_CONDITION_OP_EQUAL;
	}
	else if ('!' == src[loc->l])
	{
		if ('~' == src[loc->r])
			return ZBX_PROMETHEUS_CONDITION_OP_REGEX_NOT_MATCHED;
		else
			return ZBX_PROMETHEUS_CONDITION_OP_NOT_EQUAL;
	}

	return ZBX_PROMETHEUS_CONDITION_OP_EQUAL_VALUE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: skips spaces                                                      *
 *                                                                            *
 * Parameters: data - [IN] the source string                                  *
 *             pos  - [IN] the starting position                              *
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
 * Purpose: skips until beginning of the next row                             *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             pos - [IN] the starting position                               *
 *                                                                            *
 * Return value: The position of the next row space character.                *
 *                                                                            *
 ******************************************************************************/
static size_t	skip_row(const char *src, size_t pos)
{
	const char	*ptr;

	if (NULL == (ptr = strchr(src + pos, '\n')))
		return strlen(src + pos) + pos;

	return (size_t)(ptr - src + 1);
}

/******************************************************************************
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
	loc->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}

/******************************************************************************
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
	loc->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}

/******************************************************************************
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
	if ('=' == data[pos])
	{
		loc->l = pos;

		if ('~' == data[pos + 1])
			loc->r = pos + 1; /* =~ */
		else
			loc->r = pos; /* = */

		return SUCCEED;
	}
	else if ('!' == data[pos] && ('=' == data[pos + 1] || '~' == data[pos + 1]))
	{
		/* != or !~ */
		loc->l = pos;
		loc->r = pos + 1;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
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

	loc->r = (size_t)(ptr - data);

	return SUCCEED;
}

/******************************************************************************
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
	if (len == str_copy_lowercase(buffer, sizeof(buffer), ptr, len) && 0 == memcmp(buffer, "nan", (size_t)len))
	{
		loc->r = pos + 2;
		return SUCCEED;
	}

	if ('-' == *ptr || '+' == *ptr)
		ptr++;

	len = ZBX_CONST_STRLEN("inf");
	if (len == str_copy_lowercase(buffer, sizeof(buffer), ptr, len) && 0 == memcmp(buffer, "inf", (size_t)len))
	{
		loc->r = (size_t)(ptr - data) + 2;
		return SUCCEED;
	}

	if (FAIL == zbx_number_parse(ptr, &len))
		return FAIL;

	loc->r = (size_t)(ptr + len - data) - 1;

	return SUCCEED;
}

static void	prometheus_condition_free(zbx_prometheus_condition_t *condition)
{
	zbx_free(condition->key);
	zbx_free(condition->pattern);
	zbx_free(condition);
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocates and initializes condition                               *
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

	zbx_vector_prometheus_condition_clear_ext(&filter->labels, prometheus_condition_free);
	zbx_vector_prometheus_condition_destroy(&filter->labels);
}

/******************************************************************************
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

		if (0 == zbx_strloc_cmp(data, &loc_key, "__name__", ZBX_CONST_STRLEN("__name__")))
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
			zbx_vector_prometheus_condition_append(&filter->labels, condition);
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
 * Purpose: initializes prometheus pattern filter from the specified data     *
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
	zbx_vector_prometheus_condition_create(&filter->labels);

	if (NULL == data)
		return SUCCEED;

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

static void	prometheus_label_free(zbx_prometheus_label_t *label)
{
	zbx_free(label->name);
	zbx_free(label->value);
	zbx_free(label);
}

static void	prometheus_row_free(zbx_prometheus_row_t *row)
{
	zbx_free(row->metric);
	zbx_free(row->value);
	zbx_free(row->raw);
	zbx_vector_prometheus_label_clear_ext(&row->labels, prometheus_label_free);
	zbx_vector_prometheus_label_destroy(&row->labels);
	zbx_free(row);
}

/******************************************************************************
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
		case ZBX_PROMETHEUS_CONDITION_OP_NOT_EQUAL:
			if (0 == strcmp(value, condition->pattern))
				return FAIL;
			break;
		case ZBX_PROMETHEUS_CONDITION_OP_REGEX_NOT_MATCHED:
			if (NULL != zbx_regexp_match(value, condition->pattern, NULL))
				return FAIL;
			break;
		default:
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches metric value against filter condition                     *
 *                                                                            *
 * Parameters: pattern   - [IN] the condition                                 *
 *             value     - [IN] the value                                     *
 *                                                                            *
 * Return value: SUCCEED - the 'value' matches 'condition'                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	condition_match_metric_value(const char *pattern, const char *value)
{
	double	pattern_dbl, value_dbl;
	char	buffer[5];

	if (SUCCEED != zbx_is_double(pattern, &pattern_dbl))
	{
		if ('+' == *pattern)
			pattern++;

		if ('+' == *value)
			value++;

		zbx_strlcpy(buffer, value, sizeof(buffer));
		zbx_strlower(buffer);
		return (0 == strcmp(pattern, buffer) ? SUCCEED : FAIL);
	}

	if (SUCCEED != zbx_is_double(value, &value_dbl))
		return FAIL;

	if (zbx_get_double_epsilon() <= fabs(pattern_dbl - value_dbl))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
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
static int	prometheus_metric_parse_labels(const char *data, size_t pos, zbx_vector_prometheus_label_t *labels,
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
		zbx_vector_prometheus_label_append(labels, label);

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
 * Comments: If there were no parsing errors, but the row does not match      *
 *           filter conditions then success with NULL prow is returned.       *
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
	zbx_vector_prometheus_label_create(&row->labels);

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
	else /* no labels in row */
	{
		if (0 < filter->labels.values_num) /* got labels in filter */
		{
			match = FAIL;
			goto out;
		}
	}

	/* check if there was a whitespace before metric value */
	if (pos == loc.r + 1)
	{
		*error = zbx_strdup(*error, "no space before metric value");
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

	loc_help->r = (size_t)(ptr - data) - 1;

	return SUCCEED;
}

/******************************************************************************
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
	if (pos == (loc_type->r = (size_t)(ptr - data)))
		return FAIL;

	loc_type->r--;

	return SUCCEED;
}

/******************************************************************************
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
		hint_local.type = NULL;
		hint_local.help = NULL;
		hint = zbx_hashset_insert(hints, &hint_local, sizeof(hint_local));
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
 * Purpose: parses TYPE/HELP comment hint and registers it                    *
 *                                                                            *
 * Parameters: filter     - [IN] the prometheus filter                        *
 *             data       - [IN] the prometheus data                          *
 *             pos        - [IN] the position of comments in prometheus data  *
 *             hints      - [IN/OUT] the hint registry                        *
 *             loc        - [OUT] the location of hint
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
			loc->r = (size_t)(ptr - data) - 1;
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
static int	prometheus_parse_rows(zbx_prometheus_filter_t *filter, const char *data,
		zbx_vector_prometheus_row_t *rows, zbx_hashset_t *hints, char **error)
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
			zbx_vector_prometheus_row_append(rows, row);
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
			len = (size_t)(ptr - data) - pos;
		else
			len = strlen(data + pos);

/* Defines maximum row length to be written in error message in the case of parsing failure */
#define ZBX_PROMEHTEUS_ERROR_MAX_ROW_LENGTH	50

		if (ZBX_PROMEHTEUS_ERROR_MAX_ROW_LENGTH < len)
		{
			len = ZBX_PROMEHTEUS_ERROR_MAX_ROW_LENGTH;
			suffix = "...";
		}
		*error = zbx_dsprintf(*error, "data parsing error at row %d \"%.*s%s\": %s", row_num, len, data + pos,
				suffix, errmsg);
		zbx_free(errmsg);

#undef ZBX_PROMEHTEUS_ERROR_MAX_ROW_LENGTH
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s rows:%d hints:%d", __func__, zbx_result_string(ret),
			rows->values_num, (NULL == hints ? 0 : hints->num_data));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts value from row                                           *
 *                                                                            *
 * Parameters: rows    - [IN] the source rows                                 *
 *             output  - [IN] the output template                             *
 *             value   - [OUT] the extracted value                            *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the value was extracted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int prometheus_extract_value(const zbx_vector_prometheus_row_t *rows, const char *output, char **value,
		char **error)
{
	const zbx_prometheus_row_t	*row;

	if (0 == rows->values_num)
	{
		*error = zbx_strdup(*error, "no matching metrics found");
		return FAIL;
	}

	if (1 < rows->values_num)
	{
#define ZBX_PROMETHEUS_ERROR_ROW_NUM	10

		int	i, rows_num = ZBX_PROMETHEUS_ERROR_ROW_NUM;
		size_t	error_alloc, error_offset = 0;

		error_alloc = (NULL == *error ? 0 : strlen(*error) + 1);

		zbx_strcpy_alloc(error, &error_alloc, &error_offset, "multiple matching metrics found:\n\n");

		if (rows->values_num < rows_num)
			rows_num = rows->values_num;

		for (i = 0; i < rows_num; i++)
		{
			row = rows->values[i];
			zbx_strcpy_alloc(error, &error_alloc, &error_offset, row->raw);
			zbx_chrcpy_alloc(error, &error_alloc, &error_offset, '\n');
		}

		if (rows->values_num > rows_num)
			zbx_strcpy_alloc(error, &error_alloc, &error_offset, "...");
		else
			(*error)[error_offset - 1] = '\0';

		return FAIL;

#undef ZBX_PROMETHEUS_ERROR_ROW_NUM
	}

	row = rows->values[0];

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
 * Purpose: aggregates row values                                             *
 *                                                                            *
 * Parameters: rows     - [IN] the source rows                                *
 *             function - [IN] the aggregation function                       *
 *             value    - [OUT] the aggregated value                          *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the values were aggregated successfully            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_aggregate_values(const zbx_vector_prometheus_row_t *rows, const char *function,
		char **value, char **error)
{
	zbx_vector_dbl_t		values;
	int				i, ret;
	double				value_dbl;
	const zbx_prometheus_row_t	*row;

	zbx_vector_dbl_create(&values);

	for (i = 0; i < rows->values_num; i++)
	{
		row = rows->values[i];

		value_dbl = atof(row->value);

		if (0 == isnan(value_dbl))
			zbx_vector_dbl_append(&values, value_dbl);
	}

	if (0 == strcmp(function, "avg"))
	{
		ret = zbx_eval_calc_avg(&values, &value_dbl, error);
	}
	else if (0 == strcmp(function, "min"))
	{
		ret = zbx_eval_calc_min(&values, &value_dbl, error);
	}
	else if (0 == strcmp(function, "max"))
	{
		ret = zbx_eval_calc_max(&values, &value_dbl, error);
	}
	else if (0 == strcmp(function, "sum"))
	{
		ret = zbx_eval_calc_sum(&values, &value_dbl, error);
	}
	else if (0 == strcmp(function, "count"))
	{
		value_dbl = (double)values.values_num;
		ret = SUCCEED;
	}
	else
	{
		*error = zbx_dsprintf(NULL, "unsupported aggregation function \"%s\"", function);
		ret = FAIL;
	}

	zbx_vector_dbl_destroy(&values);

	if (SUCCEED == ret)
	{
		char	buffer[32];

		zbx_print_double(buffer, sizeof(buffer), value_dbl);
		*value = zbx_strdup(NULL, buffer);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: performs the specified request on rows                            *
 *                                                                            *
 * Parameters: rows    - [IN] the source rows                                 *
 *             request - [IN] the request  (value, label, function)           *
 *             output  - [IN] the output template/function name               *
 *             value   - [OUT] the result value                               *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the request was performed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_query_rows(const zbx_vector_prometheus_row_t *rows, const char *request, const char *output,
		char **value, char **error)
{
	if (0 == strcmp(request, "function"))
		return prometheus_aggregate_values(rows, output, value, error);

	return prometheus_extract_value(rows, output, value, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get rows matching the filter criteria                             *
 *                                                                            *
 * Parameters: rows     - [IN] the rows to filter                             *
 *             filter   - [IN] the prometheus filt                            *
 *             rows_out - [OUT] the filtered rows                             *
 *                                                                            *
 ******************************************************************************/
static void	prometheus_filter_rows(zbx_vector_prometheus_row_t *rows, zbx_prometheus_filter_t *filter,
		zbx_vector_prometheus_row_t *rows_out)
{
	int			i, j, k;
	zbx_prometheus_row_t	*row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < rows->values_num; i++)
	{
		row = rows->values[i];

		if (NULL != filter->metric)
		{
			if (SUCCEED != condition_match_key_value(filter->metric, NULL, row->metric))
				continue;
		}

		if (0 == row->labels.values_num && 0 != filter->labels.values_num)
			continue;

		for (j = 0; j < filter->labels.values_num; j++)
		{
			zbx_prometheus_condition_t	*condition = filter->labels.values[j];

			for (k = 0; k < row->labels.values_num; k++)
			{
				zbx_prometheus_label_t	*label = row->labels.values[k];

				if (SUCCEED == condition_match_key_value(condition, label->name, label->value))
					break;
			}

			if (k == row->labels.values_num)
				break;
		}

		if (j != filter->labels.values_num)
			continue;

		if (NULL != filter->value)
		{
			if (SUCCEED != condition_match_metric_value(filter->value->pattern, row->value))
				continue;
		}

		zbx_vector_prometheus_row_append(rows_out, row);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows:%d", __func__, rows_out->values_num);
}

static void	prometheus_hint_clear(void *d)
{
	zbx_prometheus_hint_t	*hint = (zbx_prometheus_hint_t *)d;

	zbx_free(hint->metric);
	zbx_free(hint->help);
	zbx_free(hint->type);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse prometheus input and initialize cache                       *
 *                                                                            *
 * Parameters: prom  - [IN] the prometheus cache                              *
 *             data  - [IN] the prometheus data                               *
 *             error - [OUT] the error message rows                           *
 *                                                                            *
 * Return value: SUCCEED - the prometheus data were parsed successfully       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_prometheus_init(zbx_prometheus_t *prom, const char *data, char **error)
{
	zbx_prometheus_filter_t	filter = {0};
	int			ret = FAIL;

	zbx_vector_prometheus_row_create(&prom->rows);
	zbx_vector_prometheus_label_index_create(&prom->indexes);

	zbx_hashset_create_ext(&prom->hints, 100, prometheus_hint_hash, prometheus_hint_compare, prometheus_hint_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	if (0 != pthread_mutex_init(&prom->index_lock, NULL))
	{
		*error = zbx_dsprintf(NULL, "Cannot initialize prometheus cache: %s", zbx_strerror(errno));
		goto out;
	}

	if (SUCCEED != prometheus_filter_init(&filter, NULL, error))
		goto out;

	if (FAIL == prometheus_parse_rows(&filter, data, &prom->rows, &prom->hints, error))
		goto out;

	ret = SUCCEED;
out:
	prometheus_filter_clear(&filter);

	if (SUCCEED != ret)
		zbx_prometheus_clear(prom);

	return ret;
}

static void	prometheus_label_index_free(zbx_prometheus_label_index_t *label_index)
{
	zbx_hashset_iter_t	iter;
	zbx_prometheus_index_t	*index;

	zbx_free(label_index->label);

	zbx_hashset_iter_reset(&label_index->index, &iter);
	while (NULL != (index = (zbx_prometheus_index_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_prometheus_row_destroy(&index->rows);

	zbx_hashset_destroy(&label_index->index);
	zbx_free(label_index);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by prometheus cache                      *
 *                                                                            *
 * Parameters: prom  - [IN] the prometheus cache                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_prometheus_clear(zbx_prometheus_t *prom)
{
	zbx_hashset_destroy(&prom->hints);

	zbx_vector_prometheus_label_index_clear_ext(&prom->indexes, prometheus_label_index_free);
	zbx_vector_prometheus_label_index_destroy(&prom->indexes);

	zbx_vector_prometheus_row_clear_ext(&prom->rows, prometheus_row_free);
	zbx_vector_prometheus_row_destroy(&prom->rows);

	pthread_mutex_destroy(&prom->index_lock);
}

static void	prometheus_lock(zbx_prometheus_t *prom)
{
	if (0 != pthread_mutex_lock(&prom->index_lock))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot lock prometheus cache: %s", zbx_strerror(errno));
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
}

static void	prometheus_unlock(zbx_prometheus_t *prom)
{
	if (0 != pthread_mutex_unlock(&prom->index_lock))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot unlock prometheus cache: %s", zbx_strerror(errno));
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * row indexing support                                                       *
 *                                                                            *
 ******************************************************************************/

static	zbx_prometheus_label_index_t	*prometheus_get_index(zbx_prometheus_t *prom, const char *label)
{
	int				i;
	zbx_prometheus_label_index_t	*label_index = NULL;

	prometheus_lock(prom);

	for (i = 0; i < prom->indexes.values_num; i++)
	{
		if (0 == strcmp(prom->indexes.values[i]->label, label))
		{
			label_index = prom->indexes.values[i];
			break;
		}
	}

	prometheus_unlock(prom);

	return label_index;
}

static void	prometheus_add_index(zbx_prometheus_t *prom, zbx_prometheus_label_index_t *index)
{
	prometheus_lock(prom);
	zbx_vector_prometheus_label_index_append(&prom->indexes, index);
	prometheus_unlock(prom);
}

static zbx_hash_t	prometheus_index_hash_func(const void *d)
{
	const zbx_prometheus_index_t	*index = (const zbx_prometheus_index_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(index->value);
}

static int	prometheus_index_compare_func(const void *d1, const void *d2)
{
	const zbx_prometheus_index_t	*i1 = (const zbx_prometheus_index_t *)d1;
	const zbx_prometheus_index_t	*i2 = (const zbx_prometheus_index_t *)d2;

	return strcmp(i1->value, i2->value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get label from row by the specified name                          *
 *                                                                            *
 * Parameters: row  - [IN] the prometheus row                                 *
 *             name - [IN] the label name                                     *
 *                                                                            *
 * Return value: The prometheus row label or NULL if no labels matched the    *
 *               specified name.                                              *
 *                                                                            *
 ******************************************************************************/
static zbx_prometheus_label_t	*prometheus_get_row_label(zbx_prometheus_row_t *row, const char *name)
{
	int	i;

	for (i = 0; i < row->labels.values_num; i++)
	{
		zbx_prometheus_label_t	*label = row->labels.values[i];

		if (0 == strcmp(label->name, name))
			return label;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get rows matching one filter label                                *
 *                                                                            *
 * Parameters: prom   - [IN] the prometheus cache                             *
 *             filter - [IN] the filter                                       *
 *             rows   - [OUT] the rows matching filter label or NULL if there *
 *                           are now matching rows                            *
 *                                                                            *
 * Return value: SUCCEED - the matched rows were returned successfully        *
 *               FAIL    - filter does not contain conditions that can be     *
 *                         indexed.                                           *
 *                                                                            *
 * Comments: The rows are indexed by first filter 'label equals' condition.   *
 *           The index is created automatically when rows for unindexed       *
 *           label are requested.                                             *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_get_indexed_rows_by_label(zbx_prometheus_t *prom, zbx_prometheus_filter_t *filter,
		zbx_vector_prometheus_row_t **rows)
{
	int				i;
	zbx_prometheus_condition_t	*condition;
	zbx_prometheus_label_index_t	*label_index;
	zbx_prometheus_index_t		*index, index_local;

	for (i = 0; i < filter->labels.values_num; i++)
	{
		condition = filter->labels.values[i];

		if (ZBX_PROMETHEUS_CONDITION_OP_EQUAL == condition->op)
			break;
	}

	if (i == filter->labels.values_num)
		return FAIL;

	if (NULL == (label_index = prometheus_get_index(prom, condition->key)))
	{
		label_index = (zbx_prometheus_label_index_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_label_index_t));

		label_index->label = zbx_strdup(NULL, condition->key);
		zbx_hashset_create(&label_index->index, 0, prometheus_index_hash_func, prometheus_index_compare_func);

		for (i = 0; i < prom->rows.values_num; i++)
		{
			zbx_prometheus_row_t	*row = prom->rows.values[i];
			zbx_prometheus_label_t	*label;

			if (NULL == (label = prometheus_get_row_label(row, label_index->label)))
				continue;

			index_local.value = label->value;

			if (NULL == (index = (zbx_prometheus_index_t *)zbx_hashset_search(&label_index->index,
					&index_local)))
			{
				index = (zbx_prometheus_index_t *)zbx_hashset_insert(&label_index->index, &index_local,
						sizeof(index_local));
				zbx_vector_prometheus_row_create(&index->rows);
			}

			zbx_vector_prometheus_row_append(&index->rows, row);
		}

		prometheus_add_index(prom, label_index);
	}

	index_local.value = condition->pattern;

	if (NULL != (index = (zbx_prometheus_index_t *)zbx_hashset_search(&label_index->index, &index_local)))
		*rows = &index->rows;
	else
		*rows = NULL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate prometheus pattern request and output                    *
 *                                                                            *
 * Parameters: request - [IN] the prometheus request                          *
 *             output  - [IN] the prometheus output                           *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - valid request and output combination               *
 *               FAIL    - invalid request and output combination             *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_validate_request(const char *request, const char *output, char **error)
{
	if (0 == strcmp(request, "value"))
	{
		if ('\0' != *output)
		{
			*error = zbx_strdup(NULL, "invalid third parameter");
			return FAIL;
		}
		return SUCCEED;
	}

	if ('\0' == *output)
	{
		*error = zbx_strdup(NULL, "missing third parameter");
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extract value from prometheus cache by the specified filter       *
 *                                                                            *
 * Parameters: prom        - [IN] the prometheus cache                        *
 *             filter_data - [IN] the filter in text format                   *
 *             request     - [IN] the data request - value, label, function   *
 *             output      - [IN] the output template/function name           *
 *             value       - [OUT] the extracted value                        *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Return value: SUCCEED - the value was extracted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_prometheus_pattern_ex(zbx_prometheus_t *prom, const char *filter_data, const char *request,
		const char *output, char **value, char **error)
{
	zbx_prometheus_filter_t		filter;
	int				ret = FAIL;
	char				*errmsg = NULL;
	zbx_vector_prometheus_row_t	rows, *prows;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == prometheus_filter_init(&filter, filter_data, &errmsg))
	{
		*error = zbx_dsprintf(*error, "pattern error: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	zbx_vector_prometheus_row_create(&rows);

	if (SUCCEED != prometheus_validate_request(request, output, error))
		return FAIL;

	if (SUCCEED != prometheus_get_indexed_rows_by_label(prom, &filter, &prows) || NULL == prows)
		prows = &prom->rows;

	prometheus_filter_rows(prows, &filter, &rows);

	if (FAIL == (ret = prometheus_query_rows(&rows, request, output, value, &errmsg)))
	{
		*error = zbx_dsprintf(*error, "data extraction error: %s", errmsg);
		zbx_free(errmsg);
	}

	prometheus_filter_clear(&filter);
	zbx_vector_prometheus_row_destroy(&rows);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts value from prometheus data by the specified filter       *
 *                                                                            *
 * Parameters: data        - [IN] the prometheus data                         *
 *             filter_data - [IN] the filter in text format                   *
 *             request     - [IN] the data request - value, label, function   *
 *             output      - [IN] the output template/function name           *
 *             value       - [OUT] the extracted value                        *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Return value: SUCCEED - the value was extracted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_prometheus_pattern(const char *data, const char *filter_data, const char *request, const char *output,
		char **value, char **error)
{
	zbx_prometheus_filter_t		filter;
	char				*errmsg = NULL;
	int				ret = FAIL;
	zbx_vector_prometheus_row_t	rows;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == prometheus_filter_init(&filter, filter_data, &errmsg))
	{
		*error = zbx_dsprintf(*error, "pattern error: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	zbx_vector_prometheus_row_create(&rows);

	if (SUCCEED != prometheus_validate_request(request, output, error))
		return FAIL;

	if (FAIL == prometheus_parse_rows(&filter, data, &rows, NULL, error))
		goto cleanup;

	if (FAIL == prometheus_query_rows(&rows, request, output, value, &errmsg))
	{
		*error = zbx_dsprintf(*error, "data extraction error: %s", errmsg);
		zbx_free(errmsg);
		goto cleanup;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): output:%s", __func__, *value);
	ret = SUCCEED;
cleanup:
	zbx_vector_prometheus_row_clear_ext(&rows, prometheus_row_free);
	zbx_vector_prometheus_row_destroy(&rows);
	prometheus_filter_clear(&filter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts filtered prometheus rows to json to be used with LLD     *
 *                                                                            *
 * Parameters: rows  - [IN] filtered prometheus rows                          *
 *             hints - [IN] prometheus hints                                  *
 *             value - [OUT] the converted data                               *
 *                                                                            *
 * Return value: SUCCEED - the data was converted successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static void	prometheus_to_json(zbx_vector_prometheus_row_t *rows, zbx_hashset_t *hints, char **value)
{
	int			i, j;
	struct zbx_json		json;
	zbx_prometheus_hint_t	*hint, hint_local;

	zbx_json_initarray(&json, (size_t)rows->values_num * 100);

	for (i = 0; i < rows->values_num; i++)
	{
		zbx_prometheus_row_t	*row =rows->values[i];
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
				zbx_prometheus_label_t	*label = row->labels.values[j];

				zbx_json_addstring(&json, label->name, label->value, ZBX_JSON_TYPE_STRING);
			}

			zbx_json_close(&json);
		}

		hint_local.metric = row->metric;
		hint = (zbx_prometheus_hint_t *)zbx_hashset_search(hints, &hint_local);

#define ZBX_PROMETHEUS_TYPE_UNTYPED	"untyped"

		hint_type = (NULL != hint && NULL != hint->type ? hint->type : ZBX_PROMETHEUS_TYPE_UNTYPED);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_TYPE, hint_type, ZBX_JSON_TYPE_STRING);

#undef ZBX_PROMETHEUS_TYPE_UNTYPED

		if (NULL != hint && NULL != hint->help)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_HELP, hint->help, ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);
	}

	*value = zbx_strdup(NULL, json.buffer);
	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts cached prometheus data to json to be used with LLD       *
 *                                                                            *
 * Parameters: prom        - [IN] the prometheus cache                        *
 *             filter_data - [IN] the filter in text format                   *
 *             value       - [OUT] the converted data                         *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Return value: SUCCEED - the data was converted successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_prometheus_to_json_ex(zbx_prometheus_t *prom, const char *filter_data, char **value, char **error)
{
	zbx_vector_prometheus_row_t	rows;
	zbx_prometheus_filter_t		filter;
	char				*errmsg = NULL;
	int				ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == prometheus_filter_init(&filter, filter_data, &errmsg))
	{
		*error = zbx_dsprintf(*error, "pattern error: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	zbx_vector_prometheus_row_create(&rows);

	prometheus_filter_rows(&prom->rows, &filter, &rows);

	prometheus_to_json(&rows, &prom->hints, value);
	zbx_vector_prometheus_row_destroy(&rows);
	prometheus_filter_clear(&filter);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts filtered prometheus data to json to be used with LLD     *
 *                                                                            *
 * Parameters: data        - [IN] the prometheus data                         *
 *             filter_data - [IN] the filter in text format                   *
 *             value       - [OUT] the converted data                         *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Return value: SUCCEED - the data was converted successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_prometheus_to_json(const char *data, const char *filter_data, char **value, char **error)
{
	zbx_prometheus_filter_t		filter;
	char				*errmsg = NULL;
	int				ret = FAIL;
	zbx_vector_prometheus_row_t	rows;
	zbx_hashset_t			hints;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == prometheus_filter_init(&filter, filter_data, &errmsg))
	{
		*error = zbx_dsprintf(*error, "pattern error: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	zbx_vector_prometheus_row_create(&rows);
	zbx_hashset_create_ext(&hints, 100, prometheus_hint_hash, prometheus_hint_compare, prometheus_hint_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	if (FAIL != (ret = prometheus_parse_rows(&filter, data, &rows, &hints, error)))
		prometheus_to_json(&rows, &hints, value);

	zbx_hashset_destroy(&hints);

	zbx_vector_prometheus_row_clear_ext(&rows, prometheus_row_free);
	zbx_vector_prometheus_row_destroy(&rows);
	prometheus_filter_clear(&filter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*value));
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
