/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "zbxtrends.h"
#include "log.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_timespec_t	ts_in, ts_out, ts;
	struct tm	tm;
	zbx_time_unit_t	base;
	time_t		time_tmp;
	const char	*unit;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.time"), &ts_in))
		fail_msg("Invalid input time format");

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("out.time"), &ts_out))
		fail_msg("Invalid output time format");

	if ('i' == *(unit = zbx_mock_get_parameter_string("in.base")))
	{
		base = ZBX_TIME_UNIT_ISOYEAR;
	}
	else
	{
		if (ZBX_TIME_UNIT_UNKNOWN == (base = zbx_tm_str_to_unit(unit)))
			fail_msg("Invalid time unit");
	}

	time_tmp = ts_in.sec;
	tm = *localtime(&time_tmp);

	zbx_tm_round_down(&tm, base);

	if (0 > tm.tm_hour || 23 < tm.tm_hour)
		fail_msg("invalid tm_hour:%d", tm.tm_hour);

	if (1 > tm.tm_mday || 31 < tm.tm_mday)
		fail_msg("invalid tm.tm_mday:%d", tm.tm_mday);

	if (0 > tm.tm_mon || 11 < tm.tm_mon)
		fail_msg("invalid tm.tm_mon:%d", tm.tm_mon);

	if (0 > tm.tm_wday || 6 < tm.tm_wday)
		fail_msg("invalid tm.tm_wday:%d", tm.tm_wday);

	if (0 > tm.tm_yday || 365 < tm.tm_yday)
		fail_msg("invalid tm.tm_yday:%d", tm.tm_yday);

	if (-1 == (time_tmp = mktime(&tm)))
		fail_msg("invalid time structure");

	ts.ns = 0;
	ts.sec = time_tmp;

	zbx_mock_assert_timespec_eq("Time rounding result", &ts_out, &ts);
}
