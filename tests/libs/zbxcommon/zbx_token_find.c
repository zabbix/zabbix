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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"

static void	compare_token(const char *prefix, const char *path, const char *expression, zbx_strloc_t strloc)
{
	char	*end, c;

	end = (char *)&expression[strloc.r + 1];
	c = *end;
	*end = '\0';
	zbx_mock_assert_str_eq(prefix, zbx_mock_get_parameter_string(path), expression + strloc.l);
	*end = c;
}

static void	get_exp_value_and_compare(const char *param, size_t found_value)
{
	zbx_uint32_t	expected_value;

	/* get expected values */
	if (FAIL == is_uint32(zbx_mock_get_parameter_string(param), &expected_value))
			fail_msg("Invalid %s value", param);

	/* compare expected token vaues to values found */
	if (expected_value != found_value)
	{
		fail_msg("Position "ZBX_FS_SIZE_T" of '%s' not equal to expected "ZBX_FS_SIZE_T,
				(zbx_fs_size_t)found_value, param, (zbx_fs_size_t)expected_value);
	}
}

static void	compare_token_user_macro(const char *expression, zbx_token_t *token)
{
	zbx_mock_handle_t	handle;
	const char		*parameter;

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.token", &handle) &&
				ZBX_MOCK_SUCCESS == zbx_mock_string(handle, &parameter))
	{
		compare_token("Invalid token", "out.token", expression, token->loc);

		compare_token("Invalid name", "out.name", expression, token->data.user_macro.name);

		compare_token("Invalid context", "out.context", expression, token->data.user_macro.context);

	}
	else
	{
		get_exp_value_and_compare("out.token_l", token->loc.l);
		get_exp_value_and_compare("out.token_r", token->loc.r);

		get_exp_value_and_compare("out.name_l", token->data.user_macro.name.l);
		get_exp_value_and_compare("out.name_r", token->data.user_macro.name.r);

		get_exp_value_and_compare("out.context_l", token->data.user_macro.context.l);
		get_exp_value_and_compare("out.context_r", token->data.user_macro.context.r);
	}
}

static void	compare_token_func_macro_values(const char *expression, zbx_token_t *token)
{
	zbx_mock_handle_t	handle;
	const char		*parameter;

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.token", &handle) &&
			ZBX_MOCK_SUCCESS == zbx_mock_string(handle, &parameter))
	{
		compare_token("Invalid token", "out.token", expression, token->loc);

		compare_token("Invalid macro", "out.macro", expression, token->data.func_macro.macro);

		compare_token("Invalid func", "out.func", expression, token->data.func_macro.func);

		compare_token("Invalid param", "out.param", expression, token->data.func_macro.func_param);
	}
	else
	{
		get_exp_value_and_compare("out.token_l", token->loc.l);
		get_exp_value_and_compare("out.token_r", token->loc.r);

		get_exp_value_and_compare("out.macro_l", token->data.func_macro.macro.l);
		get_exp_value_and_compare("out.macro_r", token->data.func_macro.macro.r);

		get_exp_value_and_compare("out.func_l", token->data.func_macro.func.l);
		get_exp_value_and_compare("out.func_r", token->data.func_macro.func.r);

		get_exp_value_and_compare("out.func_param_l", token->data.func_macro.func_param.l);
		get_exp_value_and_compare("out.func_param_r", token->data.func_macro.func_param.r);
	}
}

static void	compare_token_lld_macro_values(const char *expression, zbx_token_t *token)
{
	zbx_mock_handle_t	handle;
	const char		*parameter;

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.token", &handle) &&
			ZBX_MOCK_SUCCESS == zbx_mock_string(handle, &parameter))
	{
		compare_token("Invalid token", "out.token", expression, token->loc);

		compare_token("Invalid macro", "out.name", expression, token->data.lld_macro.name);
	}
	else
	{
		get_exp_value_and_compare("out.token_l", token->loc.l);
		get_exp_value_and_compare("out.token_r", token->loc.r);

		get_exp_value_and_compare("out.name_l", token->data.lld_macro.name.l);
		get_exp_value_and_compare("out.name_r", token->data.lld_macro.name.r);
	}
}

static void	compare_token_lld_func_macro_values(const char *expression, zbx_token_t *token)
{
	zbx_mock_handle_t	handle;
	const char		*parameter;

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.token", &handle) &&
			ZBX_MOCK_SUCCESS == zbx_mock_string(handle, &parameter))
	{
		compare_token("Invalid token", "out.token", expression, token->loc);

		compare_token("Invalid macro", "out.macro", expression, token->data.lld_func_macro.macro);

		compare_token("Invalid func", "out.func", expression, token->data.lld_func_macro.func);

		compare_token("Invalid param", "out.param", expression, token->data.lld_func_macro.func_param);
	}
	else
	{
		get_exp_value_and_compare("out.token_l", token->loc.l);
		get_exp_value_and_compare("out.token_r", token->loc.r);

		get_exp_value_and_compare("out.macro_l", token->data.lld_func_macro.macro.l);
		get_exp_value_and_compare("out.macro_r", token->data.lld_func_macro.macro.r);

		get_exp_value_and_compare("out.func_l", token->data.lld_func_macro.func.l);
		get_exp_value_and_compare("out.func_r", token->data.lld_func_macro.func.r);

		get_exp_value_and_compare("out.func_param_l", token->data.lld_func_macro.func_param.l);
		get_exp_value_and_compare("out.func_param_r", token->data.lld_func_macro.func_param.r);
	}
}

static void	compare_token_macro_values(const char *expression, zbx_token_t *token)
{
	compare_token("Invalid token", "out.token", expression, token->loc);
	compare_token("Invalid macro", "out.macro", expression, token->data.macro.name);
}

static void	compare_token_expression_macro_values(const char *expression, zbx_token_t *token)
{
	compare_token("Invalid token", "out.expression", expression, token->data.expression_macro.expression);
}

void	zbx_mock_test_entry(void **state)
{
	const char		*expression;
	int			expected_ret, expected_token_type;
	zbx_token_t		token;

	ZBX_UNUSED(state);

	expression = zbx_mock_get_parameter_string("in.expression");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	zbx_mock_assert_result_eq("zbx_token_find() return code", expected_ret,
			zbx_token_find(expression, 0, &token, ZBX_TOKEN_SEARCH_EXPRESSION_MACRO |
			ZBX_TOKEN_SEARCH_SIMPLE_MACRO));

	if (SUCCEED == expected_ret)
	{
		zbx_mock_str_to_token_type(zbx_mock_get_parameter_string("out.token_type"), &expected_token_type);

		if (expected_token_type != token.type)
		{
			fail_msg("Expected token type 0x%02X does not match type found 0x%02X", expected_token_type,
					token.type);
		}

		switch (expected_token_type)
		{
			case ZBX_TOKEN_LLD_FUNC_MACRO:
				compare_token_lld_func_macro_values(expression, &token);
				break;
			case ZBX_TOKEN_FUNC_MACRO:
				compare_token_func_macro_values(expression, &token);
				break;
			case ZBX_TOKEN_USER_MACRO:
				compare_token_user_macro(expression, &token);
				break;
			case ZBX_TOKEN_LLD_MACRO:
				compare_token_lld_macro_values(expression, &token);
				break;
			case ZBX_TOKEN_MACRO:
				compare_token_macro_values(expression, &token);
				break;
			case ZBX_TOKEN_EXPRESSION_MACRO:
				compare_token_expression_macro_values(expression, &token);
				break;
		}
	}
}
