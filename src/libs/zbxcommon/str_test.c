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

	ret = zbx_user_macro_parse(expression, &macro_r, &context_l, &context_r);
	ZBX_CU_ASSERT_INT_EQ_FATAL(ret, success);

	if (FAIL != ret)
	{
		ZBX_CU_ASSERT_CHAR_EQ_FATAL(expression[macro_r], '}');

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

		ZBX_CU_ASSERT_STRING_N_EQ_FATAL(expression + 2, macro,  mloc.r - mloc.l + 1);
		ZBX_CU_ASSERT_INT_EQ_FATAL(strlen(macro), mloc.r - mloc.l + 1);

		if (NULL != context)
		{
			ZBX_CU_ASSERT_INT_NE_FATAL(context_l, 0);
			ZBX_CU_ASSERT_STRING_N_EQ_FATAL(expression + context_l, context, context_r - context_l + 1);
			ZBX_CU_ASSERT_INT_EQ_FATAL(strlen(context), (size_t)context_r - context_l + 1);

		}
		else
			ZBX_CU_ASSERT_INT_EQ_FATAL(context_l, 0);
	}
}

static void	test_zbx_user_macro_parse_fail1()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail2()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail3()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail4()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A }", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail5()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$ A}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail6()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{ $A}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail7()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail8()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("$A}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail9()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail10()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$a}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail11()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$Ab}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail12()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("B{$A}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_fail13()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("B{$A}B", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_fail1()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A:", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_fail2()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A: \"", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_fail3()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A: \"}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_fail4()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A:\"1}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_fail5()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A:\"1\"2}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_fail6()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A:\"1\"2}", NULL, NULL, FAIL);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_succeed1()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A}", "A", NULL, SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_succeed2()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$ABCD}", "ABCD", NULL, SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_succeed3()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A}B", "A", NULL, SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_succeed1()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A: 1}", "A", "1", SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_succeed2()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A: 1 }", "A", "1 ", SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_succeed3()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A:  \"1\"}", "A", "\"1\"", SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_succeed4()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A:  \"1\"  }", "A", "\"1\"", SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_succeed5()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A:  \"\\\"1\\\"\"}", "A", "\"\\\"1\\\"\"", SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_succeed6()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A:  \"\\\"1\\\"\"  }", "A", "\"\\\"1\\\"\"", SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

static void	test_zbx_user_macro_parse_context_succeed7()
{
	ZBX_CU_LEAK_CHECK_START();
	cu_test_macro_expresssion("{$A: \"{$B}\" }", "A", "\"{$B}\"", SUCCEED);
	ZBX_CU_LEAK_CHECK_END();
}

int	ZBX_CU_DECLARE(str_test)
{
	CU_pSuite	suite = NULL;

	/* test suite: str.c */
	if (NULL == (suite = CU_add_suite("zbx_user_macro_parse", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail1);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail2);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail3);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail4);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail5);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail6);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail7);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail8);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail9);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail10);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail11);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail12);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_fail13);

	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_fail1);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_fail2);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_fail3);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_fail4);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_fail5);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_fail6);

	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_succeed1);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_succeed2);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_succeed3);

	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_succeed1);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_succeed2);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_succeed3);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_succeed4);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_succeed5);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_succeed6);
	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse_context_succeed7);

	return CUE_SUCCESS;
}
