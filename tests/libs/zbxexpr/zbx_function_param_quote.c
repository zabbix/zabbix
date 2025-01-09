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
	int		ret, forced, escape, expected_ret;
	const char	*param;
	char		*result = NULL;

	ZBX_UNUSED(state);

	param = zbx_mock_get_parameter_string("in.param");
	forced = zbx_mock_get_parameter_int("in.forced");
	escape = zbx_mock_get_parameter_int("in.escape");

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	result = zbx_strdup(result, param);

	ret = zbx_function_param_quote(&result, forced, escape);

	zbx_mock_assert_int_eq("zbx_function_param_quote() return", expected_ret, ret);

	zbx_free(result);
}
