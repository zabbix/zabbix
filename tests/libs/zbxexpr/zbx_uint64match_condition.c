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
	zbx_uint64_t	value = zbx_mock_get_parameter_uint64("in.value"),
			pattern = zbx_mock_get_parameter_uint64("in.pattern");
	unsigned char	op = (unsigned char)zbx_mock_get_parameter_uint64("in.op");
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	ZBX_UNUSED(state);

	int	result = zbx_uint64match_condition(value, pattern, op);

	zbx_mock_assert_int_eq("return value", exp_result, result);
}
