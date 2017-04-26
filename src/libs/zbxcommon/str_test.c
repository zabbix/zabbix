/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

static int	cu_init_empty()
{
	return CUE_SUCCESS;
}

static int	cu_clean_empty()
{
	return CUE_SUCCESS;
}

static void	cu_test_macro_expresssion(const char *expression, const char *macro, const char *context, int success)
{
	int		macro_r, context_l, context_r, ret;
	zbx_strloc_t    mloc;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT_FATAL(success == (ret = zbx_user_macro_parse(expression, &macro_r, &context_l, &context_r)));

	if (FAIL != ret)
	{
		CU_ASSERT_FATAL('}' == expression[macro_r]);

		mloc.l = 2;

		if (0 != context_l)
		{
			const char    *ptr = expression + context_l;

			while (' ' == *(--ptr))
			;

			mloc.r = ptr - expression - 1;
		}
		else
			mloc.r = macro_r - 1;

		CU_ASSERT_FATAL(strlen(macro) == mloc.r - mloc.l + 1);
		CU_ASSERT_FATAL(0 == strncmp(expression + 2, macro, mloc.r - mloc.l + 1));

		if (NULL != context)
		{
			CU_ASSERT_FATAL(0 != context_l);
			CU_ASSERT_FATAL(strlen(context) == (size_t)context_r - context_l + 1);
			CU_ASSERT_FATAL(0 == strncmp(expression + context_l, context, context_r - context_l + 1));
		}
		else
			CU_ASSERT_FATAL(0 == context_l);

		CU_ASSERT_FATAL(0 == strncmp(expression + 2, macro, mloc.r - mloc.l + 1));
	}

	ZBX_CU_LEAK_CHECK_END();
}

static void	user_macro_parse_test1()
{
	cu_test_macro_expresssion("{$A}", "A", NULL, SUCCEED);
}

static void	user_macro_parse_test2()
{
	cu_test_macro_expresssion("{$A}B", "A", NULL, SUCCEED);
}

static void	user_macro_parse_test3()
{
	cu_test_macro_expresssion("{$A:1}", "A", "1", SUCCEED);
}

static void	user_macro_parse_test4()
{
	cu_test_macro_expresssion("{$A:1 }", "A", "1 ", SUCCEED);
}

static void	user_macro_parse_test5()
{
	cu_test_macro_expresssion("{$A: 1}", "A", "1", SUCCEED);
}

static void	user_macro_parse_test6()
{
	cu_test_macro_expresssion("{$A: 1 }", "A", "1 ", SUCCEED);
}

static void	user_macro_parse_test7()
{
	cu_test_macro_expresssion("{$A:  \"1\"}", "A", "\"1\"", SUCCEED);
}

static void	user_macro_parse_test8()
{
	cu_test_macro_expresssion("{$A:  \"1\"  }", "A", "\"1\"", SUCCEED);
}

static void	user_macro_parse_test9()
{
	cu_test_macro_expresssion("{$A:  \"\\\"1\\\"\"}", "A", "\"\\\"1\\\"\"", SUCCEED);
}

static void	user_macro_parse_test10()
{
	cu_test_macro_expresssion("{$A:  \"\\\"1\\\"\"  }", "A", "\"\\\"1\\\"\"", SUCCEED);
}

static void	user_macro_parse_test11()
{
	cu_test_macro_expresssion("{", NULL, NULL, FAIL);
}

static void	user_macro_parse_test12()
{
	cu_test_macro_expresssion("}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test13()
{
	cu_test_macro_expresssion("{$A }", NULL, NULL, FAIL);
}

static void	user_macro_parse_test14()
{
	cu_test_macro_expresssion("{$A", NULL, NULL, FAIL);
}

static void	user_macro_parse_test15()
{
	cu_test_macro_expresssion("$A}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test16()
{
	cu_test_macro_expresssion("{$}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test17()
{
	cu_test_macro_expresssion("{$a}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test18()
{
	cu_test_macro_expresssion("{$Ab}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test19()
{
	cu_test_macro_expresssion("B{$A}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test20()
{
	cu_test_macro_expresssion("B{$A}B", NULL, NULL, FAIL);
}

static void	user_macro_parse_test21()
{
	cu_test_macro_expresssion("{$A:", NULL, NULL, FAIL);
}

static void	user_macro_parse_test22()
{
	cu_test_macro_expresssion("{$A: \"}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test23()
{
	cu_test_macro_expresssion("{$A:\"1}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test24()
{
	cu_test_macro_expresssion("{$A:\"1\"2}", NULL, NULL, FAIL);
}

static void	user_macro_parse_test25()
{
	cu_test_macro_expresssion("{$A:\"1 }", NULL, NULL, FAIL);
}

int	ZBX_CU_SUITE(str_test)
{
	CU_pSuite	suite = NULL;

	/* test suite: str.c */
	/* check if user macro parse succeeds */
	if (NULL == (suite = CU_add_suite("user macro parse test", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, "{$A}", user_macro_parse_test1);
	ZBX_CU_ADD_TEST(suite, "{$A}b", user_macro_parse_test2);
	ZBX_CU_ADD_TEST(suite, "{$A:1}", user_macro_parse_test3);
	ZBX_CU_ADD_TEST(suite, "{$A:1 }", user_macro_parse_test4);
	ZBX_CU_ADD_TEST(suite, "{$A: 1}", user_macro_parse_test5);
	ZBX_CU_ADD_TEST(suite, "{$A: 1 }", user_macro_parse_test6);
	ZBX_CU_ADD_TEST(suite, "{$A:  \"1\"}", user_macro_parse_test7);
	ZBX_CU_ADD_TEST(suite, "{$A:  \"1\"  }", user_macro_parse_test8);
	ZBX_CU_ADD_TEST(suite, "{$A:  \"\\\"1\\\"\"}", user_macro_parse_test9);
	ZBX_CU_ADD_TEST(suite, "{$A:  \"\\\"1\\\"\"  }", user_macro_parse_test10);

	/* check if user macro parse fails due to incorrect macro format */
	ZBX_CU_ADD_TEST(suite, "{", user_macro_parse_test11);
	ZBX_CU_ADD_TEST(suite, "}", user_macro_parse_test12);
	ZBX_CU_ADD_TEST(suite, "{$A }", user_macro_parse_test13);
	ZBX_CU_ADD_TEST(suite, "{$A", user_macro_parse_test14);
	ZBX_CU_ADD_TEST(suite, "$A}", user_macro_parse_test15);
	ZBX_CU_ADD_TEST(suite, "{$}", user_macro_parse_test16);
	ZBX_CU_ADD_TEST(suite, "{$a}", user_macro_parse_test17);
	ZBX_CU_ADD_TEST(suite, "{$Ab}", user_macro_parse_test18);
	ZBX_CU_ADD_TEST(suite, "B{$A}", user_macro_parse_test19);
	ZBX_CU_ADD_TEST(suite, "B{$A}B", user_macro_parse_test20);

	/* check if user macro parse fails due to incorrect macro context format */
	ZBX_CU_ADD_TEST(suite, "{$A:", user_macro_parse_test21);
	ZBX_CU_ADD_TEST(suite, "{$A: \"}", user_macro_parse_test22);
	ZBX_CU_ADD_TEST(suite, "{$A:\"1}", user_macro_parse_test23);
	ZBX_CU_ADD_TEST(suite, "{$A:\"1\"2}", user_macro_parse_test24);
	ZBX_CU_ADD_TEST(suite, "{$A:\"1 }", user_macro_parse_test25);

	return CUE_SUCCESS;
}
