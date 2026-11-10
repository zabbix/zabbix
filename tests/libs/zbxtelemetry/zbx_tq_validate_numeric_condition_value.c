/*
** Copyright (C) 2001-2026 Zabbix SIA
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
#include "zbxtelemetry.h"


void	zbx_mock_test_entry(void **state)
{
	int	expected_ret, returned_ret;

	ZBX_UNUSED(state);

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	returned_ret = zbx_tq_validate_numeric_condition_value(zbx_mock_get_parameter_string("in.value"));

	zbx_mock_assert_int_eq("zbx_tq_validate_numeric_condition_value return value", expected_ret, returned_ret);
}
