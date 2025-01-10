/*
** Copyright (C) 2001-2024 Zabbix SIA
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

static time_t	parse_time_from_string(const char *time_str)
{
	struct tm tm = {0};

	if (sscanf(time_str, "%d-%d-%dT%d:%d:%d", &tm.tm_year, &tm.tm_mon, &tm.tm_mday, &tm.tm_hour, &tm.tm_min,
			&tm.tm_sec) != 6)
		fail_msg("Cannot read time: %s", time_str);

	tm.tm_year -= 1900;
	tm.tm_mon -= 1;

	return mktime(&tm);
}

void	zbx_mock_test_entry(void **state)
{
	const char	*period = zbx_mock_get_parameter_string("in.period");
	const char	*time_str = zbx_mock_get_parameter_string("in.time");
	const char	*tz = zbx_mock_get_parameter_string("in.time_zone");
	int		out = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	ZBX_UNUSED(state);

	int	res;
	time_t	time = parse_time_from_string(time_str);
	int	result = zbx_check_time_period(period, time, tz, &res);

	zbx_mock_assert_int_eq("return value function", out, result);

	if (SUCCEED == zbx_mock_parameter_exists("out.res"))
	{
		int	out_res = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.res"));
		zbx_mock_assert_int_eq("return value res", out_res, res);
	}
}
