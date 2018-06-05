/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "../zbxcunit/zbxcunit.h"

typedef struct
{
	int	type;
	char	*component;
}
zbx_jsonpath_parse_t;

typedef struct
{
	const char		*path;
	zbx_jsonpath_parse_t	parse[10];
}
zbx_jsonpath_data_t;

static int	cu_init_empty()
{
	return CUE_SUCCESS;
}

static int	cu_clean_empty()
{
	return CUE_SUCCESS;
}

static void	cu_test_json_path(const char *path, zbx_jsonpath_parse_t *parse)
{
	char		description[MAX_STRING_LEN];
	const char	*next = NULL;
	zbx_strloc_t	loc;
	int		num = 0, ret, type;
	size_t		len;

	zbx_snprintf(description, sizeof(description), "jsonpath '%s'", path);

	do
	{
		ret = zbx_jsonpath_next(path, &next, &loc, &type);

		if (FAIL == parse[num].type)
		{
			ZBX_CU_ASSERT_INT_EQ_FATAL(description, ret, FAIL);
			return;
		}

		ZBX_CU_ASSERT_INT_EQ_FATAL(description, ret, SUCCEED);
		ZBX_CU_ASSERT_INT_EQ_FATAL(description, type, parse[num].type);

		len = loc.r - loc.l + 1;

		ZBX_CU_ASSERT_INT_EQ_FATAL(description, len, strlen(parse[num].component));
		ZBX_CU_ASSERT_STRINGN_EQ_FATAL(description, path + loc.l, parse[num].component, len);

		num++;
	}
	while ('\0' != *next);
}

static void	test_zbx_jsonpath_next()
{
	size_t			i;
	zbx_jsonpath_data_t	data[] = {
			{"", {{-1}}},
			{"$", {{-1}}},
			{"$.", {{-1}}},
			{"$.a", {{0, "a"}}},
			{"$['a']", {{1, "a"}}},
			{"$[ 'a' ]", {{1, "a"}}},
			{"$[\"a\"]", {{1, "a"}}},
			{"$.a.", {{0, "a"}, {-1}}},
			{"$.a.b", {{0, "a"}, {0, "b"}}},
			{"$['a'].b", {{1, "a"}, {0, "b"}}},
			{"$['a']['b']", {{1, "a"}, {1, "b"}}},
			{"$.a['b']", {{0, "a"}, {1, "b"}}},
			{"$['a'", {{-1}}},
			{"$[a']", {{-1}}},
			{"$['a'", {{-1}}},
			{"$['']", {{-1}}},
			{"$.['a']", {{-1}}},
			{"$.a[0]", {{0, "a"}, {2, "0"}}},
			{"$.a[0].b[1]", {{0, "a"}, {2, "0"}, {0, "b"}, {2, "1"}}},
			{"$.a[1000]", {{0, "a"}, {2, "1000"}}},
			{"$.a[ 1 ]", {{0, "a"}, {2, "1"}}},
			{"$['a'][2]", {{1, "a"}, {2, "2"}}},
			{"$['a'][2]['b'][3]", {{1, "a"}, {2, "2"}, {1, "b"}, {2, "3"}}},
			{"$.a[]", {{0, "a"}, {-1}}},
			{"$.a[1", {{0, "a"}, {-1}}},
			{"$['a'][]", {{1, "a"}, {-1}}},
			{"$['a'][1", {{1, "a"}, {-1}}},
			{"$[1][2]", {{2, "1"}, {2, "2"}}},
			};

	ZBX_CU_LEAK_CHECK_START();

	for (i = 0; ARRSIZE(data) > i; i++)
		cu_test_json_path(data[i].path, data[i].parse);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cu_test_zbx_json_path_open(const char *json, const char *jpath, const char *result, int retcode)
{
	struct zbx_json_parse	jp, jp_out;

	char		description[MAX_STRING_LEN];
	const char	*next = NULL;
	zbx_strloc_t	loc;
	int		num = 0, ret, type;
	size_t		len;

	zbx_snprintf(description, sizeof(description), "json '%s', path '%s'", json, jpath);

	ZBX_CU_ASSERT_INT_EQ_FATAL(description, zbx_json_open(json, &jp), SUCCEED);

	ret = zbx_json_path_open(&jp, jpath, &jp_out);

	ZBX_CU_ASSERT_INT_EQ_FATAL(description, ret, retcode);
	if (FAIL == ret)
		return;

	len = jp_out.end - jp_out.start + 1;
	ZBX_CU_ASSERT_INT_EQ_FATAL(description, len, strlen(result));
	ZBX_CU_ASSERT_STRINGN_EQ_FATAL(description, jp_out.start, result, len);
}

static void	test_zbx_json_path_open()
{
	typedef struct
	{
		const char	*json;
		const char	*jpath;
		const char	*result;
		int		retcode;
	}
	zbx_json_data_t;

	size_t			i;
	zbx_json_data_t		data[] = {
			{"[1, 2, 3]", "$[0]", "1", SUCCEED},
			{"[1, 2, 3]", "$[1]", "2", SUCCEED},
			{"[1, 2, 3]", "$[2]", "3", SUCCEED},
			{"[1, 2, 3]", "$[3]", NULL, FAIL},
			{"[1,[\"a\",\"b\",\"c\"],3]", "$[1][0]", "\"a\"", SUCCEED},
			{"{\"x\":[1, [\"a\", \"b\", \"c\"], 3]}", "$.x[1][2]", "\"c\"", SUCCEED},
			{"{\"a\":{\"b\": [{\"x\":10}, 2, 3] }}", "$.a", "{\"b\": [{\"x\":10}, 2, 3] }", SUCCEED},
			{"{\"a\" : {\"b\": [{\"x\":10}, 2, 3] }}", "$.a", "{\"b\": [{\"x\":10}, 2, 3] }", SUCCEED},
			{"{\"a\":{\"b\": [{\"x\":10}, 2, 3] }}", "$.a.b", "[{\"x\":10}, 2, 3]", SUCCEED},
			{"{\"a\":{\"b\": [{\"x\":10}, 2, 3] }}", "$.a.b[0]", "{\"x\":10}", SUCCEED},
			{"{\"a\":{\"b\": [{\"x\":10}, 2, 3] }}", "$.a.b[1]", "2", SUCCEED},
			{"{\"a\":{\"b\": [{\"x\":10}, 2, 3] }}", "$.a.b[2]", "3", SUCCEED},
			{"{\"a\":{\"b\": [{\"x\":10}, 2, 3] }}", "$.a.x", NULL, FAIL},
			{"{\"a\":1}", "", NULL, FAIL},
			{"{\"a\":1}", "$a", NULL, FAIL},
			};

	ZBX_CU_LEAK_CHECK_START();

	for (i = 0; ARRSIZE(data) > i; i++)
		cu_test_zbx_json_path_open(data[i].json, data[i].jpath, data[i].result, data[i].retcode);

	ZBX_CU_LEAK_CHECK_END();

}


int	ZBX_CU_DECLARE(json)
{
	CU_pSuite	suite = NULL;

	/* test suite: zbx_user_macro_parse() */
	if (NULL == (suite = CU_add_suite("zbx_jsonpath_next", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, test_zbx_jsonpath_next);

	/* test suite: zbx_user_macro_parse() */
	if (NULL == (suite = CU_add_suite("zbx_json_path_open", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, test_zbx_json_path_open);

	return CUE_SUCCESS;
}
