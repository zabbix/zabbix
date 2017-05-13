/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
	int	retcode;
	char	*component;
	int	index;
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
	int		num = 0, ret, index;
	size_t		len;

	zbx_snprintf(description, sizeof(description), "jsonpath '%s'", path);

	do
	{
		ret = zbx_jsonpath_next(path, &next, &loc, &index);
		ZBX_CU_ASSERT_INT_EQ_FATAL(description, ret, parse[num].retcode);

		if (FAIL == ret)
			return;

		ZBX_CU_ASSERT_INT_EQ_FATAL(description, index, parse[num].index);

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
			{"", {{FAIL}}},
			{"$", {{FAIL}}},
			{"$.", {{FAIL}}},
			{"$.a", {{SUCCEED, "a", -1}}},
			{"$['a']", {{SUCCEED, "a", -1}}},
			{"$[ 'a' ]", {{SUCCEED, "a", -1}}},
			{"$[\"a\"]", {{SUCCEED, "a", -1}}},
			{"$.a.", {{SUCCEED, "a", -1}, {FAIL}}},
			{"$.a.b", {{SUCCEED, "a", -1}, {SUCCEED, "b", -1}}},
			{"$['a'].b", {{SUCCEED, "a", -1}, {SUCCEED, "b", -1}}},
			{"$['a']['b']", {{SUCCEED, "a", -1}, {SUCCEED, "b", -1}}},
			{"$.a['b']", {{SUCCEED, "a", -1}, {SUCCEED, "b", -1}}},
			{"$['a'", {{FAIL}}},
			{"$[a']", {{FAIL}}},
			{"$['a'", {{FAIL}}},
			{"$['']", {{FAIL}}},
			{"$.['a']", {{FAIL}}},
			{"$.a[0]", {{SUCCEED, "a", 0}}},
			{"$.a[0].b[1]", {{SUCCEED, "a", 0}, {SUCCEED, "b", 1}}},
			{"$.a[1000]", {{SUCCEED, "a", 1000}}},
			{"$.a[ 1 ]", {{SUCCEED, "a", 1}}},
			{"$['a'][2]", {{SUCCEED, "a", 2}}},
			{"$['a'][2]['b'][3]", {{SUCCEED, "a", 2}, {SUCCEED, "b", 3}}},
			{"$.a[]", {{SUCCEED, "a", -1}, {FAIL}}},
			{"$.a[1", {{FAIL}}},
			{"$['a'][]", {{SUCCEED, "a", -1}, {FAIL}}},
			{"$['a'][1", {{FAIL}}},
			};

	ZBX_CU_LEAK_CHECK_START();

	for (i = 0; ARRSIZE(data) > i; i++)
	{
		cu_test_json_path(data[i].path, data[i].parse);
	}

	ZBX_CU_LEAK_CHECK_END();
}

int	ZBX_CU_DECLARE(json)
{
	CU_pSuite	suite = NULL;

	/* test suite: zbx_user_macro_parse() */
	if (NULL == (suite = CU_add_suite("zbx_jsonpath_next", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, test_zbx_jsonpath_next);

	return CUE_SUCCESS;
}
