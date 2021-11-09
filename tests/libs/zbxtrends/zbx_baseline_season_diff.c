/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

static zbx_time_unit_t	mock_get_time_unit(const char *path)
{
	const char	*unit;

	unit = zbx_mock_get_parameter_string(path);

	switch (*unit)
	{
		case 'w':
			return ZBX_TIME_UNIT_WEEK;
		case 'Y':
			return ZBX_TIME_UNIT_ISOYEAR;
		case 'y':
			return ZBX_TIME_UNIT_YEAR;
		case 'm':
			return ZBX_TIME_UNIT_MONTH;
		case 'd':
			return ZBX_TIME_UNIT_DAY;
		case 'h':
			return ZBX_TIME_UNIT_HOUR;
		default:
			return ZBX_TIME_UNIT_UNKNOWN;
	}
}

static int	mock_get_int(const char *path)
{
	const char		*value;
	zbx_mock_handle_t	handle;

	if (ZBX_MOCK_SUCCESS != zbx_mock_parameter(path, &handle))
		return 0;

	if (ZBX_MOCK_SUCCESS != zbx_mock_string(handle, &value))
		fail_msg("invalid value at %s", path);

	return atoi(value);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_timespec_t	ts;
	struct tm	tm_season, tm_period;
	time_t		time_tmp;
	zbx_time_unit_t	season_unit;
	zbx_tm_diff_t	diff;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.season.start"), &ts))
		fail_msg("Invalid input time format");

	time_tmp = (time_t)ts.sec;
	tm_season = *localtime(&time_tmp);
	season_unit = mock_get_time_unit("in.season.unit");

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.period.start"), &ts))
		fail_msg("Invalid input time format");

	time_tmp = (time_t)ts.sec;
	tm_period = *localtime(&time_tmp);

	zbx_baseline_season_diff(&tm_season, season_unit, &tm_period, &diff);

	zbx_mock_assert_int_eq("weeks from season start", mock_get_int("out.weeks"), diff.weeks);
	zbx_mock_assert_int_eq("months from season start", mock_get_int("out.months"), diff.months);
	zbx_mock_assert_int_eq("days from season start", mock_get_int("out.days"), diff.days);
	zbx_mock_assert_int_eq("hours from season start", mock_get_int("out.hours"), diff.hours);
}
