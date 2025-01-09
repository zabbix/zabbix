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

void	zbx_mock_test_entry(void **state)
{
	int		expected_ret, returned_ret, pos = 0;
	char		*value = NULL, macro_expr[MAX_STRING_LEN];
	zbx_token_t	token;

	ZBX_UNUSED(state);

	zbx_snprintf(macro_expr, MAX_STRING_LEN, "{{TIME}.regrepl(%s)}", zbx_mock_get_parameter_string("in.params"));

	if (SUCCEED != zbx_token_find(macro_expr, pos, &token, ZBX_TOKEN_SEARCH_BASIC))
		fail_msg("cannot find token");

	if (ZBX_TOKEN_FUNC_MACRO != token.type)
		fail_msg("invalid token type");

#define FMTTIME_INPUT_SIZE	1024

	value = zbx_malloc(value, FMTTIME_INPUT_SIZE);

	zbx_snprintf(value, FMTTIME_INPUT_SIZE, "%s", zbx_mock_get_parameter_string("in.data"));
	returned_ret = zbx_calculate_macro_function(macro_expr, &token.data.func_macro, &value);
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		zbx_mock_assert_str_eq("fmttime result", zbx_mock_get_parameter_string("out.value"), value);
	}

	zbx_free(value);
#undef FMTTIME_INPUT_SIZE
}
