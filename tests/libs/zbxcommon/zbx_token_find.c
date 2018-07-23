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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"

static void	get_exp_value_and_compare(const char *param, size_t found_value)
{
	size_t	expected_value;

	/* get expected values */
	if (FAIL == is_uint32(zbx_mock_get_parameter_string(param), &expected_value))
			fail_msg("Invalid %s value", param);

	/* compare expected token vaues to values found */
	if (expected_value != found_value)
	{
		fail_msg("Position "ZBX_FS_SIZE_T" of '%s' not equal to expected "ZBX_FS_SIZE_T,
				found_value, param, expected_value);
	}
}

static void	compare_token_func_macro_values(zbx_token_t *token)
{
	get_exp_value_and_compare("out.token_l", token->token.l);
	get_exp_value_and_compare("out.token_r", token->token.r);

	get_exp_value_and_compare("out.macro_l", token->data.func_macro.macro.l);
	get_exp_value_and_compare("out.macro_r", token->data.func_macro.macro.r);

	get_exp_value_and_compare("out.func_l", token->data.func_macro.func.l);
	get_exp_value_and_compare("out.func_r", token->data.func_macro.func.r);

	get_exp_value_and_compare("out.func_param_l", token->data.func_macro.func_param.l);
	get_exp_value_and_compare("out.func_param_r", token->data.func_macro.func_param.r);
}

static void	compare_token_lld_func_macro_values(zbx_token_t *token)
{
	get_exp_value_and_compare("out.token_l", token->token.l);
	get_exp_value_and_compare("out.token_r", token->token.r);

	get_exp_value_and_compare("out.macro_l", token->data.lld_func_macro.macro.l);
	get_exp_value_and_compare("out.macro_r", token->data.lld_func_macro.macro.r);

	get_exp_value_and_compare("out.func_l", token->data.lld_func_macro.func.l);
	get_exp_value_and_compare("out.func_r", token->data.lld_func_macro.func.r);

	get_exp_value_and_compare("out.func_param_l", token->data.lld_func_macro.func_param.l);
	get_exp_value_and_compare("out.func_param_r", token->data.lld_func_macro.func_param.r);
}

void	zbx_mock_test_entry(void **state)
{
	const char		*expression;
	int			expected_ret, expected_token_type;
	zbx_token_t		token;

	ZBX_UNUSED(state);

	expression = zbx_mock_get_parameter_string("in.expression");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_str_to_token_type(zbx_mock_get_parameter_string("out.token_type"),
			&expected_token_type);

	zbx_mock_assert_result_eq("zbx_token_find() return code", expected_ret,
			zbx_token_find(expression, 0, &token, ZBX_TOKEN_SEARCH_BASIC));

	if (SUCCEED == expected_ret)
	{
		if (expected_token_type != token.type)
			fail_msg("Expected token type does not match type found");

		switch (expected_token_type)
		{
			case ZBX_TOKEN_FUNC_MACRO:
				compare_token_func_macro_values(&token);
				break;
			case ZBX_TOKEN_LLD_FUNC_MACRO:
				compare_token_lld_func_macro_values(&token);
				break;
		}
	}
}
