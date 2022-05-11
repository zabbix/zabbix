/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxalgo.h"
#include "zbxjson.h"
#include "zbxregexp.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockjson.h"

#define _FAIL(file, line, prefix, message, ...)						\
											\
do 											\
{											\
	cm_print_error("%s%s" message "\n", (NULL != prefix_msg ? prefix_msg : ""),	\
			(NULL != prefix_msg && '\0' != *prefix_msg ? ": " : ""),	\
			__VA_ARGS__);							\
	_fail(file, line);								\
}											\
while(0)

void cm_print_error(const char * const format, ...);

static void	json_flatten_contents(struct zbx_json_parse *jp, const char *prefix, zbx_vector_ptr_pair_t *props);

static void	json_append_prop(zbx_vector_ptr_pair_t *props, const char *key, const char *value)
{
	zbx_ptr_pair_t	pair;

	pair.first = zbx_strdup(NULL, key);
	pair.second = zbx_strdup(NULL, value);
	zbx_vector_ptr_pair_append_ptr(props, &pair);
}

static int	json_compare_props(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t	*p1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t	*p2 = (const zbx_ptr_pair_t *)d2;

	return strcmp((const char *)p1->first, (const char *)p2->first);
}

static void	json_flatten_value(const char *ptr, const char *path, zbx_vector_ptr_pair_t *props)
{
	struct zbx_json_parse	jp_value;
	char			*value = NULL;
	size_t			value_alloc = 0;
	zbx_json_type_t		type;

	if (FAIL == zbx_json_brackets_open(ptr, &jp_value))
	{
		zbx_json_decodevalue_dyn(ptr, &value, &value_alloc, &type);
		json_append_prop(props, path, (ZBX_JSON_TYPE_NULL != type ? value : "null"));
	}
	else
		json_flatten_contents(&jp_value, path, props);

	zbx_free(value);
}

static int	json_quote_key(char *key, size_t size)
{
	char	*ptr, *out;
	int	quotes = 0, dot = 1;

	for (ptr = key; '\0' != *ptr; ptr++)
	{
		if ('\'' == *ptr || '\\' == *ptr)
			quotes++;

		if (0 == isalnum((unsigned char)*ptr) && '_' != *ptr && '-' != *ptr)
			dot = 0;
	}

	if (1 == dot)
		return FAIL;

	if (0 == quotes)
		return SUCCEED;

	if (ptr - key + quotes + 1 > (int)size)
		fail_msg("The hardcoded 2k limit exceeded by JSON key: %s", key);

	for (out = ptr + quotes; ptr != key; ptr--)
	{
		*out-- = *ptr;
		if ('\'' == *ptr || '\\' == *ptr)
			*out-- = '\\';
	}

	return SUCCEED;
}

static void	json_flatten_object(struct zbx_json_parse *jp, const char *prefix, zbx_vector_ptr_pair_t *props)
{
	const char	*pnext = NULL;
	char		*path = NULL, key[MAX_STRING_LEN];
	size_t		path_alloc = 0, path_offset;

	while (NULL != (pnext = zbx_json_pair_next(jp, pnext, key, sizeof(key))))
	{
		path_offset = 0;
		if (SUCCEED == json_quote_key(key, sizeof(key)))
			zbx_snprintf_alloc(&path, &path_alloc, &path_offset, "%s['%s']", prefix, key);
		else
			zbx_snprintf_alloc(&path, &path_alloc, &path_offset, "%s.%s", prefix, key);

		json_flatten_value(pnext, path, props);
	}
	zbx_free(path);
}

static void	json_flatten_array(struct zbx_json_parse *jp, const char *parent, zbx_vector_ptr_pair_t *props)
{
	const char	*pnext;
	char		*path, *value = NULL;
	int		index = 0;

	for (pnext = NULL; NULL != (pnext = zbx_json_next(jp, pnext));)
	{
		path = zbx_dsprintf(NULL, "%s[%d]", parent, index++);
		json_flatten_value(pnext, path, props);
		zbx_free(path);
	}

	zbx_free(value);
}

static void	json_flatten_contents(struct zbx_json_parse *jp, const char *prefix, zbx_vector_ptr_pair_t *props)
{
	if ('{' == *jp->start)
		json_flatten_object(jp, prefix, props);
	else if ('[' == *jp->start)
		json_flatten_array(jp, prefix, props);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flattens json into vector of key (json path), value pairs, sorted *
 *          by keys                                                           *
 *                                                                            *
 ******************************************************************************/
static void	json_flatten(struct zbx_json_parse *jp, zbx_vector_ptr_pair_t *props)
{
	json_flatten_contents(jp, "$", props);
	zbx_vector_ptr_pair_sort(props, json_compare_props);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares returned json with expected                              *
 *                                                                            *
 * Comments: The comparison is done by first flattening both jsons into       *
 *           key(jsonpath)-value pairs, sorting by keys and then comparing    *
 *           the resulting lists.                                             *
 *           If expected value starts with / then regular expression match is *
 *           performed.                                                       *
 *                                                                            *
 ******************************************************************************/
void	__zbx_mock_assert_json_eq(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *returned_value)
{
	struct zbx_json_parse	jp_expected, jp_returned;
	zbx_vector_ptr_pair_t	props_expected, props_returned;
	int			i, props_num;

	if (FAIL == zbx_json_open(expected_value, &jp_expected))
		_FAIL(file, line, prefix_msg, "Expected value is not a valid JSON object: %s", zbx_json_strerror());

	if (FAIL == zbx_json_open(returned_value, &jp_returned))
		_FAIL(file, line, prefix_msg, "Returned value is not a valid JSON object: %s", zbx_json_strerror());

	zbx_vector_ptr_pair_create(&props_expected);
	zbx_vector_ptr_pair_create(&props_returned);

	json_flatten(&jp_expected, &props_expected);
	json_flatten(&jp_returned, &props_returned);

	props_num = MIN(props_expected.values_num, props_returned.values_num);

	for (i = 0; i < props_num; i++)
	{
		zbx_ptr_pair_t	*pair_expected = &props_expected.values[i];
		zbx_ptr_pair_t	*pair_returned = &props_returned.values[i];

		if (0 != strcmp(pair_expected->first, pair_returned->first))
		{
			_FAIL(file, line, prefix_msg, "Expected key \"%s\" while got \"%s\"", pair_expected->first,
					pair_returned->first);
		}

		if ('/' == *(char *)pair_expected->second)
		{
			char	*pattern = (char *)pair_expected->second + 1;
			if (NULL == zbx_regexp_match(pair_returned->second, pattern, NULL))
			{
				_FAIL(file, line, prefix_msg, "Key \"%s\" value \"%s\" does not match pattern \"%s\"",
						pair_returned->first, pair_returned->second, pattern);
			}
		}
		else
		{
			if (0 != strcmp(pair_expected->second, pair_returned->second))
			{
				_FAIL(file, line, prefix_msg, "Expected key \"%s\" value \"%s\" while got \"%s\"",
						pair_expected->first, pair_expected->second, pair_returned->second);

			}
		}
	}

	if (i < props_expected.values_num)
	{
		zbx_ptr_pair_t	*pair_expected = &props_expected.values[i];
		_FAIL(file, line, prefix_msg, "Expected key \"%s\" while got nothing", pair_expected->first);
	}

	if (i < props_returned.values_num)
	{
		zbx_ptr_pair_t	*pair_returned = &props_returned.values[i];
		_FAIL(file, line, prefix_msg, "Did not expect key \"%s\"", pair_returned->first);
	}

	for (i = 0; i < props_expected.values_num; i++)
	{
		zbx_free(props_expected.values[i].first);
		zbx_free(props_expected.values[i].second);
	}
	zbx_vector_ptr_pair_destroy(&props_expected);

	for (i = 0; i < props_returned.values_num; i++)
	{
		zbx_free(props_returned.values[i].first);
		zbx_free(props_returned.values[i].second);
	}
	zbx_vector_ptr_pair_destroy(&props_returned);
}
