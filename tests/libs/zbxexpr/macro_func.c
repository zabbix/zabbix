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
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxexpr.h"

#define MACROFUNC_INPUT_SIZE	1024

void	zbx_mock_test_entry(void **state)
{
	const size_t		macro_pos = 1, macro_pos_end = 6, func_pos = 8;
	int			expected_ret, returned_ret;
	char			*value = NULL, macro_expr[MAX_STRING_LEN], *func = NULL, *params = NULL;
	zbx_token_func_macro_t	token;

	ZBX_UNUSED(state);
	func = zbx_malloc(func, MACROFUNC_INPUT_SIZE);
	params = zbx_malloc(params, MACROFUNC_INPUT_SIZE);

	zbx_snprintf(func, MACROFUNC_INPUT_SIZE, "%s", zbx_mock_get_parameter_string("in.function"));
	zbx_snprintf(params, MACROFUNC_INPUT_SIZE, "%s", zbx_mock_get_parameter_string("in.params"));
	zbx_snprintf(macro_expr, MAX_STRING_LEN, "{{TIME}.%s(%s)}", func, params);

	token = (zbx_token_func_macro_t)
		{
			.macro		= { macro_pos, macro_pos_end },
			.func		= { func_pos, func_pos + strlen(func) },
			.func_param	= { func_pos + strlen(func), strlen(macro_expr) - 2 }
		};

	value = zbx_malloc(value, MACROFUNC_INPUT_SIZE);

	zbx_snprintf(value, MACROFUNC_INPUT_SIZE, "%s", zbx_mock_get_parameter_string("in.data"));
	returned_ret = zbx_calculate_macro_function(macro_expr, &token, &value);
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		zbx_mock_assert_str_eq("fmttime result", zbx_mock_get_parameter_string("out.value"), value);
	}

	zbx_free(value);
	zbx_free(func);
	zbx_free(params);
}
