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

#include "zbx_expr_common.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*period = zbx_mock_get_parameter_string("in.period"),
			*time_str = zbx_mock_get_parameter_string("in.time"),
			*tz = zbx_mock_get_parameter_string("in.time_zone");
	int		out = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	ZBX_UNUSED(state);

	int	res;
	time_t	time = human_time_to_unix_time(time_str);
	int	result = zbx_check_time_period(period, time, tz, &res);

	zbx_mock_assert_int_eq("return value function", out, result);

	if (SUCCEED == zbx_mock_parameter_exists("out.res"))
	{
		int	out_res = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.res"));
		zbx_mock_assert_int_eq("return value res", out_res, res);
	}
}
