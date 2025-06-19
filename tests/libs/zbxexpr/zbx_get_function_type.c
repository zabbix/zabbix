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
	const char	*func_return, *func = zbx_mock_get_parameter_string("in.func"),
			*func_out = zbx_mock_get_parameter_string("out.func");

	ZBX_UNUSED(state);

	zbx_function_type_t	function_type = zbx_get_function_type(func);

	if (ZBX_FUNCTION_TYPE_TRENDS == function_type)
		func_return = "trends";

	if (ZBX_FUNCTION_TYPE_TIMER == function_type)
		func_return = "timer";

	if (ZBX_FUNCTION_TYPE_HISTORY == function_type)
		func_return = "history";

	zbx_mock_assert_str_eq("return value:", func_out, func_return);
}
