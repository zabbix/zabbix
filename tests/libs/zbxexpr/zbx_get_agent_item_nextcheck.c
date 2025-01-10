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

static int	parse_time_from_string(const char *time_str)
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
	uint64_t	itemid = zbx_mock_get_parameter_uint64("in.itemid");
	const char	*delay = zbx_mock_get_parameter_string("in.delay");
	const char	*now = zbx_mock_get_parameter_string("in.now");
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_uint64_t	exp_nextcheck = zbx_mock_get_parameter_uint64("out.nextcheck");

	ZBX_UNUSED(state);

	char	*error = NULL;
	int	nextcheck, scheduling;

	int	time = parse_time_from_string(now);
	int	result = zbx_get_agent_item_nextcheck(itemid, delay, time, &nextcheck, &scheduling, &error);
	zbx_mock_assert_int_eq("return value", exp_result, result);
	zbx_mock_assert_uint64_eq("return value", exp_nextcheck, (uint64_t)nextcheck);
}
