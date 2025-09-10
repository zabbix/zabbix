/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxmocktest.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxexpr.h"

void	zbx_mock_test_entry(void **state)
{
	int		got_result, got_length;
	char		*got_name = NULL, *got_context = NULL;
	unsigned char	got_context_op;

	const char	*in_macro = zbx_mock_get_parameter_string("in.macro");
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	ZBX_UNUSED(state);

	got_result = zbx_user_macro_parse_dyn(in_macro, &got_name, &got_context, &got_length, &got_context_op);

	zbx_mock_assert_int_eq("return value", exp_result, got_result);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.name"))
	{
		const char	*exp_name = zbx_mock_get_parameter_string("out.name");

		zbx_mock_assert_str_eq("expected name", exp_name, got_name);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.context"))
	{
		const char	*exp_context = zbx_mock_get_parameter_string("out.context");

		zbx_mock_assert_str_eq("expected context", exp_context, got_context);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.length"))
	{
		int	exp_length = zbx_mock_get_parameter_int("out.length");

		zbx_mock_assert_int_eq("expected length", exp_length, got_length);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.context_op"))
	{
		int	exp_context_op = zbx_mock_get_parameter_int("out.context_op");

		zbx_mock_assert_int_eq("expected context op", exp_context_op, got_context_op);
	}

	zbx_free(got_name);
	zbx_free(got_context);
}
