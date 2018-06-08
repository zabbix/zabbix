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
	char		description[ZBX_KIBIBYTE * 64];

	zbx_snprintf(description, sizeof(description), "expression '%s'", expression);

	ret = zbx_user_macro_parse(expression, &macro_r, &context_l, &context_r);
	ZBX_CU_ASSERT_INT_EQ_FATAL(description, ret, success);

	if (FAIL != ret)
	{
		ZBX_CU_ASSERT_CHAR_EQ_FATAL(description, expression[macro_r], '}');

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

		ZBX_CU_ASSERT_STRINGN_EQ_FATAL(description, expression + 2, macro,  mloc.r - mloc.l + 1);
		ZBX_CU_ASSERT_INT_EQ_FATAL(description, strlen(macro), mloc.r - mloc.l + 1);

		if (NULL != context)
		{
			ZBX_CU_ASSERT_INT_NE_FATAL(description, context_l, 0);
			ZBX_CU_ASSERT_STRINGN_EQ_FATAL(description, expression + context_l, context,
					context_r - context_l + 1);
			ZBX_CU_ASSERT_INT_EQ_FATAL(description, strlen(context), (size_t)context_r - context_l + 1);

		}
		else
			ZBX_CU_ASSERT_INT_EQ_FATAL("context", context_l, 0);
	}
}

static void	test_zbx_user_macro_parse()
{
	struct zbx_user_macrotest_data_t
	{
		const char	*expression;
		const char	*macro;
		const char	*context;
		int		success;	/* expected return value */
	};

	size_t				i;
	struct zbx_user_macrotest_data_t	user_macro_parse_test_cases[] = {
			{"", NULL, NULL, FAIL},
			{"{", NULL, NULL, FAIL},
			{"}", NULL, NULL, FAIL},
			{"{$A }", NULL, NULL, FAIL},
			{"{$ A}", NULL, NULL, FAIL},
			{"{ $A}", NULL, NULL, FAIL},
			{"{$A", NULL, NULL, FAIL},
			{"$A}", NULL, NULL, FAIL},
			{"{$}", NULL, NULL, FAIL},
			{"{$a}", NULL, NULL, FAIL},
			{"{$Ab}", NULL, NULL, FAIL},
			{"B{$A}", NULL, NULL, FAIL},
			{"B{$A}B", NULL, NULL, FAIL},
			{"{$A:", NULL, NULL, FAIL},
			{"{$A: \"", NULL, NULL, FAIL},
			{"{$A: \"}", NULL, NULL, FAIL},
			{"{$A:\"1}", NULL, NULL, FAIL},
			{"{$A:\"1\"2}", NULL, NULL, FAIL},
			{"{$A:\"1 }", NULL, NULL, FAIL},
			{"{$A}", "A", NULL, SUCCEED},
			{"{$ABCD}", "ABCD", NULL, SUCCEED},
			{"{$A}B", "A", NULL, SUCCEED},
			{"{$A:1}", "A", "1", SUCCEED},
			{"{$A:1234}", "A", "1234", SUCCEED},
			{"{$A:1 }", "A", "1 ", SUCCEED},
			{"{$A: 1}", "A", "1", SUCCEED},
			{"{$A: 1 }", "A", "1 ", SUCCEED},
			{"{$A:  \"1\"}", "A", "\"1\"", SUCCEED},
			{"{$A:  \"1\"  }", "A", "\"1\"", SUCCEED},
			{"{$A:  \"\\\"1\\\"\"}", "A", "\"\\\"1\\\"\"", SUCCEED},
			{"{$A:  \"\\\"1\\\"\"  }", "A", "\"\\\"1\\\"\"", SUCCEED},
			{"{$A: \"{$B}\" }", "A", "\"{$B}\"", SUCCEED}
			};

	ZBX_CU_LEAK_CHECK_START();

	for (i = 0; ARRSIZE(user_macro_parse_test_cases) > i; i++)
	{
		cu_test_macro_expresssion(user_macro_parse_test_cases[i].expression,
				user_macro_parse_test_cases[i].macro,user_macro_parse_test_cases[i].context,
				user_macro_parse_test_cases[i].success);
	}

	ZBX_CU_LEAK_CHECK_END();
}

int	ZBX_CU_DECLARE(str_test)
{
	CU_pSuite	suite = NULL;

	/* test suite: zbx_user_macro_parse() */
	if (NULL == (suite = CU_add_suite("zbx_user_macro_parse", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, test_zbx_user_macro_parse);

	return CUE_SUCCESS;
}
