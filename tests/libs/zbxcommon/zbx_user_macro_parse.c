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
#include "zbxmockutil.h"
#include "zbxmockassert.h"
#include "common.h"

void	zbx_mock_test_entry(void **state)
{
	int			exp_result, act_result;
	int			exp_macro_r, exp_context_l, exp_context_r;
	int			act_macro_r, act_context_l, act_context_r;
	unsigned char		*match, context_op;
	const char		*macro;
	zbx_mock_handle_t	hmatch;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.match", &hmatch))
		match = &context_op;
	else
		match = NULL;

	macro = zbx_mock_get_parameter_string("in.macro");


	exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	if (SUCCEED == exp_result)
	{
		exp_macro_r	= (int)zbx_mock_get_parameter_uint64("out.macro_r");
		exp_context_l	= (int)zbx_mock_get_parameter_uint64("out.context_l");
		exp_context_r	= (int)zbx_mock_get_parameter_uint64("out.context_r");
	}

	act_result = zbx_user_macro_parse(macro, &act_macro_r, &act_context_l, &act_context_r, match);

	zbx_mock_assert_int_eq("return value", exp_result, act_result);

	if (SUCCEED == exp_result)
	{
		if (exp_macro_r != act_macro_r)
			fail_msg("Got %d instead of %d for macro_r output parameter", act_macro_r, exp_macro_r);
		if (exp_context_l != act_context_l)
			fail_msg("Got %d instead of %d for context_l output parameter", act_context_l, exp_context_l);
		if (exp_context_r != act_context_r)
			fail_msg("Got %d instead of %d for context_r output parameter", act_context_r, exp_context_r);
	}
}
