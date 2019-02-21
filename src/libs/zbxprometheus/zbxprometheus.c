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

#define ZBX_PROMETHEUS_PARSE_OUTPUT_DEFAULT	0
#define ZBX_PROMETHEUS_PARSE_OUTPUT_RAW		1

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
	while (' ' == data[pos])
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
			ptr++;

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
	int		digit = 0;

	if ('\0' == *ptr)
		return FAIL;

	loc->l = pos;

	if (0 == strncmp(ptr, "Nan", 3))
	{
		loc->r = pos + 2;
		return SUCCEED;
	}

	if ('-' == *ptr || '+' == *ptr)
		ptr++;

	if (0 == strncmp(ptr, "Inf", 3))
	{
		loc->r = ptr - data + 2;
		return SUCCEED;
	}

	while (0 != isdigit(*ptr))
	{
		digit = 1;
		ptr++;
	}

	if ('.' == *ptr)
		ptr++;

	while (0 != isdigit(*ptr))
	{
		digit = 1;
		ptr++;
	}

	if (0 == digit)
		return FAIL;

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
		zbx_prometheus_condition_op_t	op;

		if (FAIL == parse_condition(data, pos, &loc_key, &loc_op, &loc_value))
		{
			*error = zbx_dsprintf(*error, "cannot parse label condition pair at %s", data + pos);
			return FAIL;
		}

		op = str_loc_op(data, &loc_op);
		if (0 == strncmp(data + loc_key.l, "__name__", loc_key.r - loc_key.l + 1))
		{
			if (NULL != filter->metric)
			{
				*error = zbx_strdup(*error, "duplicate metric name specified");
				return FAIL;
			}

			filter->metric = prometheus_condition_create(NULL, str_loc_unquote_dyn(data, &loc_value), op);
		}
		else
		{
			zbx_prometheus_condition_t	*condition;

			condition = prometheus_condition_create(str_loc_dup(data, &loc_key),
					str_loc_unquote_dyn(data, &loc_value), op);
			zbx_vector_ptr_append(&filter->labels, condition);
		}

		pos = skip_spaces(data, loc_value.r + 1);

		if (',' == data[pos])
		{
			pos = skip_spaces(data, pos + 1);
			continue;
		}

		if ('\0' == data[pos])
		{
			*error = zbx_strdup(*error, "label list was not properly terminated");
			return FAIL;
		}
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

	memset(filter, 0, sizeof(zbx_prometheus_filter_t));
	zbx_vector_ptr_create(&filter->labels);

	if (SUCCEED == parse_metric(data, pos, &loc))
	{
		filter->metric = prometheus_condition_create(NULL, str_loc_dup(data, &loc),
				ZBX_PROMETHEUS_CONDITION_OP_EQUAL);

		pos = loc.r + 1;
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
		zbx_strloc_t			loc_op, loc_value;
		zbx_prometheus_condition_op_t	op;

		if (SUCCEED != parse_metric_op(data, pos, &loc_op))
		{
			*error = zbx_dsprintf(*error, "cannot parse metric comparison operator at %s", data + pos);
			goto out;
		}

		op = str_loc_op(data, &loc_op);
		pos = skip_spaces(data, loc_op.r + 1);

		if (SUCCEED != parse_metric_value(data, pos, &loc_value))
		{
			*error = zbx_dsprintf(*error, "cannot parse metric comparison value at %s", data + pos);
			goto out;
		}

		pos = skip_spaces(data, loc_value.r + 1);
		if ('\0' != data[pos])
		{
			*error = zbx_dsprintf(*error, "unexpected data after metric comparison value at %s",
					data + pos);
			goto out;
		}

		filter->value = prometheus_condition_create(NULL, str_loc_dup(data, &loc_value), op);
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		prometheus_filter_clear(filter);

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
			*error = zbx_strdup(*error, "invalid label syntax");
			return FAIL;
		}

		label = (zbx_prometheus_label_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_label_t));
		label->name = str_loc_dup(data, &loc_key);
		label->value = str_loc_unquote_dyn(data, &loc_value);
		zbx_vector_ptr_append(labels, label);

		pos = skip_spaces(data, loc_value.r + 1);
		if (',' == data[pos])
		{
			pos = skip_spaces(data, pos + 1);
			continue;
		}

		if ('\0' == data[pos])
		{
			*error = zbx_strdup(*error, "label list was not properly terminated");
			return FAIL;
		}
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
		*error = zbx_strdup(*error, "failed to parse metric name");
		goto out;
	}

	row->metric = str_loc_dup(data, &loc);

	if (NULL != filter->metric)
	{
		if (FAIL == (match = condition_match_key_value(filter->metric, NULL, row->metric)))
			goto out;
	}

	/* parse labels and check against the filter */

	pos = loc.r + 1;
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

		pos = loc.r + 1;
	}

	/* parse value and check against the filter */

	pos = skip_spaces(data, pos);
	if (FAIL == parse_metric_value(data, pos, &loc))
	{
		*error = zbx_strdup(*error, "failed to parse metric value");
		goto out;
	}
	row->value = str_loc_dup(data, &loc);

	if (NULL != filter->value)
	{
		if (SUCCEED != (match = condition_match_key_value(filter->value, NULL, row->value)))
			goto out;
	}

	/* row was successfully parsed and matched all filter conditions */
	pos = loc.r + 1;
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

		pos = skip_row(data, loc.r + 1);
		if ('\0' == data[pos])
			loc_row->r = pos - 1;
		else
			loc_row->r = pos - 2;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_parse_rows                                            *
 *                                                                            *
 * Purpose: parses rows with metrics from prometheus data                     *
 *                                                                            *
 * Parameters: filter  - [IN] the prometheus filter                           *
 *             data    - [IN] the metric data                                 *
 *             output  - [IN] the flag specifying if raw row value must be    *
 *                            included, see ZBX_PROMETHEUS_PARSE_OUTPUT_*     *
 *                            defines.                                        *
 *             rows    - [OUT] the parsed rows                                *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the rows were parsed successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	prometheus_parse_rows(zbx_prometheus_filter_t *filter, const char *data, int output,
		zbx_vector_ptr_t *rows, char **error)
{
	const char	*__function_name = "prometheus_parse_rows";

	size_t			pos = 0;
	int			row_num = 1, ret = FAIL;
	zbx_prometheus_row_t	*row;
	char			*errmsg = NULL;
	zbx_strloc_t		loc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (pos = 0; '\0' != data[pos]; pos = skip_row(data, pos))
	{
		pos = skip_spaces(data, pos);
		if ('#' == data[pos] || '\n' == data[pos])
			continue;

		if (SUCCEED != prometheus_parse_row(filter, data, pos, &row, &loc, &errmsg))
		{
			*error = zbx_dsprintf(*error, "failed to parse row %d: %s", row_num, errmsg);
			zbx_free(errmsg);
			goto out;
		}

		if (NULL != row)
		{
			if (ZBX_PROMETHEUS_PARSE_OUTPUT_RAW == output)
				row->raw = str_loc_dup(data, &loc);
			zbx_vector_ptr_append(rows, row);
		}

		pos = loc.r + 1;
		row_num++;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s rows:%d", __function_name, zbx_result_string(ret),
			rows->values_num);
	return ret;
}

int	zbx_prometheus_pattern(const char *data, const char *filter_data, const char *output, char **value,
		char **error)
{
	const char	*__function_name = "zbx_prometheus_pattern";

	zbx_prometheus_filter_t	filter;
	char			*errmsg = NULL;
	int			ret = FAIL;
	zbx_vector_ptr_t	rows;
	zbx_prometheus_row_t	*row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&rows);

	if (FAIL == prometheus_filter_init(&filter, filter_data, &errmsg))
	{
		*error = zbx_dsprintf(*error, "Cannot parse filter: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	if (FAIL == prometheus_parse_rows(&filter, data, ZBX_PROMETHEUS_PARSE_OUTPUT_DEFAULT, &rows, &errmsg))
	{
		*error = zbx_dsprintf(*error, "Cannot parse rows: %s", errmsg);
		zbx_free(errmsg);
		goto cleanup;
	}

	if (0 == rows.values_num)
	{
		*error = zbx_strdup(*error, "No row matches the specified filter");
		goto cleanup;
	}

	if (1 < rows.values_num)
	{
		*error = zbx_strdup(*error, "Multiple rows match the specified filter");
		goto cleanup;
	}

	row = (zbx_prometheus_row_t *)rows.values[0];

	if ('\0' != *output)
	{
		int			i;
		zbx_prometheus_label_t	*label;

		for (i = 0; i < row->labels.values_num; i++)
		{
			label = (zbx_prometheus_label_t *)row->labels.values[i];

			if (0 == strcmp(label->name, output))
				break;
		}

		if (i == row->labels.values_num)
		{
			*error = zbx_strdup(*error, "No label matches the specified output");
			goto cleanup;
		}
		*value = zbx_strdup(NULL, label->value);
	}
	else
		*value = zbx_strdup(NULL, row->value);

	ret = SUCCEED;
cleanup:
	zbx_vector_ptr_clear_ext(&rows, (zbx_clean_func_t)prometheus_row_free);
	zbx_vector_ptr_destroy(&rows);
	prometheus_filter_clear(&filter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
	return ret;
}

int	zbx_prometheus_to_json(const char *data, const char *filter, char **value, char **error)
{
	*error = zbx_strdup(*error, "Not implemented");
	return FAIL;
}

#ifdef HAVE_TESTS
#	include "../../../tests/libs/zbxprometheus/prometheus_test.c"
#endif
