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

#include "zbxlog.h"
#include "zbxexpr.h"

static unsigned char	mock_get_cycle(const char *path)
{
	const char	*value;

	value = zbx_mock_get_parameter_string(path);

	if (0 == strcmp(value, "ZBX_REPORT_CYCLE_DAILY"))
		return ZBX_REPORT_CYCLE_DAILY;
	if (0 == strcmp(value, "ZBX_REPORT_CYCLE_WEEKLY"))
		return ZBX_REPORT_CYCLE_WEEKLY;
	if (0 == strcmp(value, "ZBX_REPORT_CYCLE_MONTHLY"))
		return ZBX_REPORT_CYCLE_MONTHLY;
	if (0 == strcmp(value, "ZBX_REPORT_CYCLE_YEARLY"))
		return ZBX_REPORT_CYCLE_YEARLY;

	fail_msg("Unsupported report cycle: %s", value);
	return 0;
}

static unsigned char	mock_get_weekdays(const char *path)
{
	zbx_mock_handle_t	hweekdays, hvalue;
	zbx_mock_error_t	err;
	unsigned char		weekdays = 0;

	hweekdays = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hweekdays, &hvalue))))
	{
		const char	*value;

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hvalue, &value)))
			fail_msg("Cannot read weekday flag");

		weekdays |= (1 << atoi(value));
	}

	return weekdays;
}

void	zbx_mock_test_entry(void **state)
{
	unsigned char		cycle, weekdays;
	zbx_timespec_t		ts;
	time_t			now, nextcheck;
	int			start_time, step = 1;
	zbx_mock_handle_t	htimes, htime;
	zbx_mock_error_t	err;
	char			buf[MAX_STRING_LEN];
	const char		*report_tz;

	ZBX_UNUSED(state);

	cycle = mock_get_cycle("in.cycle");
	weekdays = mock_get_weekdays("in.weekdays");

	report_tz = zbx_mock_get_parameter_string("in.timezone");
	if (0 != setenv("TZ", report_tz, 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.now"), &ts))
		fail_msg("Invalid time format for 'now' parameter");
	now = ts.sec;

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.start_time"), &ts))
		fail_msg("Invalid time format for 'start_time' parameter");
	start_time = ts.sec;

	htimes = zbx_mock_get_parameter_handle("out.reports");
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(htimes, &htime))))
	{
		const char	*value;

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(htime, &value)))
			fail_msg("[%d] cannot read nextcheck timestamp", step);

		if (ZBX_MOCK_SUCCESS != (err = zbx_strtime_to_timespec(value, &ts)))
			fail_msg("[%d] cannot parse nextcheck timestamp: %s", step, zbx_mock_error_string(err));

		if (FAIL == (nextcheck = zbx_get_report_nextcheck(now, cycle, weekdays, start_time)))
			fail_msg("[%d] cannot calculate report nextcheck", step);

		zbx_snprintf(buf, sizeof(buf), "[%d] invalid report nextchek value", step++);
		zbx_mock_assert_time_eq(buf, ts.sec, nextcheck);

		now = nextcheck;
	}
}
