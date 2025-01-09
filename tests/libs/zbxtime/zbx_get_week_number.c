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

#include "zbxcommon.h"
#include "zbxtrends.h"
#include "zbxlog.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_timespec_t	ts;
	struct tm	tm;
	time_t		time_tmp;
	int		week_ret, week_exp;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.time"), &ts))
		fail_msg("Invalid input time format");

	time_tmp = (time_t)ts.sec;
	tm = *localtime(&time_tmp);

	week_ret = zbx_get_week_number(&tm);
	week_exp = atoi(zbx_mock_get_parameter_string("out.week"));

	zbx_mock_assert_int_eq("week number", week_exp, week_ret);
}
