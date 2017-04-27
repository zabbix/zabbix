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
			ZBX_CU_ASSERT_INT_EQ_FATAL(strlen(context), context_r - context_l + 1);

		}
		else
			ZBX_CU_ASSERT_INT_EQ_FATAL(context_l, 0);
	}
}


static void	test_zbx_user_macro_parse()
{
	ZBX_CU_LEAK_CHECK_START();

	/* check if user macro parse fails due to incorrect macro format */
	cu_test_macro_expresssion("", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{", NULL, NULL, FAIL);
	cu_test_macro_expresssion("}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$A }", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$ A}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{ $A}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$A", NULL, NULL, FAIL);
	cu_test_macro_expresssion("$A}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$a}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$Ab}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("B{$A}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("B{$A}B", NULL, NULL, FAIL);

	/* check if user macro parse fails due to incorrect macro context format */
	cu_test_macro_expresssion("{$A:", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$A: \"", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$A: \"}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$A:\"1}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$A:\"1\"2}", NULL, NULL, FAIL);
	cu_test_macro_expresssion("{$A:\"1 }", NULL, NULL, FAIL);

	/* check if user macro parse succeeds */
	cu_test_macro_expresssion("{$A}", "A", NULL, SUCCEED);
	cu_test_macro_expresssion("{$ABCD}", "ABCD", NULL, SUCCEED);
	cu_test_macro_expresssion("{$A}B", "A", NULL, SUCCEED);
	cu_test_macro_expresssion("{$A:1}", "A", "1", SUCCEED);
	cu_test_macro_expresssion("{$A:1234}", "A", "1234", SUCCEED);
	cu_test_macro_expresssion("{$A:1 }", "A", "1 ", SUCCEED);
	cu_test_macro_expresssion("{$A: 1}", "A", "1", SUCCEED);
	cu_test_macro_expresssion("{$A: 1 }", "A", "1 ", SUCCEED);
	cu_test_macro_expresssion("{$A:  \"1\"}", "A", "\"1\"", SUCCEED);
	cu_test_macro_expresssion("{$A:  \"1\"  }", "A", "\"1\"", SUCCEED);
	cu_test_macro_expresssion("{$A:  \"\\\"1\\\"\"}", "A", "\"\\\"1\\\"\"", SUCCEED);
	cu_test_macro_expresssion("{$A:  \"\\\"1\\\"\"  }", "A", "\"\\\"1\\\"\"", SUCCEED);
	cu_test_macro_expresssion("{$A: \"{$B}\" }", "A", "\"{$B}\"", SUCCEED);

	ZBX_CU_LEAK_CHECK_END();
}

int	ZBX_CU_SUITE(str_test)
{
	CU_pSuite	suite = NULL;

	/* test suite: str.c */
	if (NULL == (suite = CU_add_suite("Parsers", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, "user macro parser", test_zbx_user_macro_parse);

	return CUE_SUCCESS;
}
