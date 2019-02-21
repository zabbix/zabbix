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

/* the prometheus patttern filter */
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
 * Purpose: parses match operation at the specified location                  *
 *                                                                            *
 * Parameters: src - [IN] the source string                                   *
 *             loc - [IN] the substring location                              *
 *                                                                            *
 * Return value: The match operation.                                         *
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
 * Function: prometheus_match_free                                            *
 *                                                                            *
 ******************************************************************************/
static void	prometheus_match_free(zbx_prometheus_condition_t *match)
{
	zbx_free(match->key);
	zbx_free(match->pattern);
	zbx_free(match);
}

/******************************************************************************
 *                                                                            *
 * Function: prometheus_match_create                                          *
 *                                                                            *
 * Purpose: allocates and initializes match object                            *
 *                                                                            *
 * Parameters: key     - [IN] the key to match                                *
 *             pattern - [IN] the matching pattern                            *
 *             op      - [IN] the matching operation                          *
 *                                                                            *
 * Return value: the created match object                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_prometheus_condition_t	*prometheus_match_create(char *key, char *pattern,
		zbx_prometheus_condition_op_t op)
{
	zbx_prometheus_condition_t	*match;

	match = (zbx_prometheus_condition_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_condition_t));
	match->key = key;
	match->pattern = pattern;
	match->op = op;

	return match;
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
		prometheus_match_free(filter->metric);

	if (NULL != filter->value)
		prometheus_match_free(filter->value);

	zbx_vector_ptr_clear_ext(&filter->labels, (zbx_clean_func_t)prometheus_match_free);
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

			filter->metric = prometheus_match_create(NULL, str_loc_unquote_dyn(data, &loc_value), op);
		}
		else
		{
			zbx_prometheus_condition_t	*match;

			match = prometheus_match_create(str_loc_dup(data, &loc_key),
					str_loc_unquote_dyn(data, &loc_value), op);
			zbx_vector_ptr_append(&filter->labels, match);
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
		filter->metric = prometheus_match_create(NULL, str_loc_dup(data, &loc),
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

	/* parse metric value match */
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

		filter->value = prometheus_match_create(NULL, str_loc_dup(data, &loc_value), op);
	}

	if (NULL == filter->metric && 0 == filter->labels.values_num)
		return FAIL;

	ret = SUCCEED;
out:
	if (FAIL == ret)
		prometheus_filter_clear(filter);

	return ret;
}

static int	perform_match(zbx_prometheus_condition_t *match, const char *key, const char *value)
{
	/* match optional key */
	if (NULL != match->key && (NULL == key || 0 != strcmp(match->key, key)))
		return FAIL;

	/* match value */
	switch (match->op)
	{
		case ZBX_PROMETHEUS_CONDITION_OP_EQUAL:
			if (0 == strcmp(match->pattern, value))
				return SUCCEED;
			break;
		case ZBX_PROMETHEUS_CONDITION_OP_REGEX:
			if (NULL != zbx_regexp_match(value, match->pattern, NULL))
				return SUCCEED;
			break;
		default:
			return FAIL;
	}
	return FAIL;
}

int	zbx_prometheus_pattern(const char *data, const char *filter, const char *output, char **value, char **error)
{
	*error = zbx_strdup(*error, "Not implemented");
	return FAIL;
}

int	zbx_prometheus_to_json(const char *data, const char *filter, char **value, char **error)
{
	*error = zbx_strdup(*error, "Not implemented");
	return FAIL;
}

#ifdef HAVE_TESTS
#	include "../../../tests/libs/zbxprometheus/prometheus_test.c"
#endif
