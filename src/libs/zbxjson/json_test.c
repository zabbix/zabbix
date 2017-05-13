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
	int		index = 0, ret;
	size_t		len;

	zbx_snprintf(description, sizeof(description), "jsonpath '%s'", path);

	do
	{
		ret = zbx_jsonpath_next(path, &next, &loc);
		ZBX_CU_ASSERT_INT_EQ_FATAL(description, ret, parse[index].retcode);

		if (FAIL == ret)
			return;

		len = loc.r - loc.l + 1;

		ZBX_CU_ASSERT_INT_EQ_FATAL(description, len, strlen(parse[index].component));
		ZBX_CU_ASSERT_STRINGN_EQ_FATAL(description, path + loc.l, parse[index].component, len);

		index++;
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
			{"$.a", {{SUCCEED, "a"}}},
			{"$['a']", {{SUCCEED, "a"}}},
			{"$[ 'a' ]", {{SUCCEED, "a"}}},
			{"$[\"a\"]", {{SUCCEED, "a"}}},
			{"$.a.", {{SUCCEED, "a"}, {FAIL}}},
			{"$.a.b", {{SUCCEED, "a"}, {SUCCEED, "b"}}},
			{"$['a'].b", {{SUCCEED, "a"}, {SUCCEED, "b"}}},
			{"$['a']['b']", {{SUCCEED, "a"}, {SUCCEED, "b"}}},
			{"$.a['b']", {{SUCCEED, "a"}, {SUCCEED, "b"}}},
			{"$['a'", {{FAIL}}},
			{"$[a']", {{FAIL}}},
			{"$['a'", {{FAIL}}},
			{"$['']", {{FAIL}}},
			{"$.['a']", {{FAIL}}},
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
