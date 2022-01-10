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
#include "log.h"

void	zbx_mock_test_entry(void **state)
{
	int			simple_interval, nextcheck, step = 1;
	zbx_custom_interval_t	*custom_intervals = NULL;
	char			*error = NULL, msg[4096];
	const char		*delay, *nextcheck_expected;
	zbx_mock_handle_t	checks, handle;
	zbx_mock_error_t	mock_err;
	time_t			now;
	zbx_timespec_t		ts;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	delay = zbx_mock_get_parameter_string("in.delay");

	if (SUCCEED != zbx_interval_preproc(delay, &simple_interval, &custom_intervals, &error))
		fail_msg("Value of 'delay' is not a valid update interval: %s.", error);

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.disabled_until"), &ts))
		fail_msg("Invalid 'at' format");

	now = ts.sec;
	checks = zbx_mock_get_parameter_handle("out.checks");

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(checks, &handle))))
	{
		if (ZBX_MOCK_SUCCESS != mock_err)
			fail_msg("Cannot read 'checks' element #%d: %s", step, zbx_mock_error_string(mock_err));

		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_string(handle, &nextcheck_expected)))
			fail_msg("Cannot read 'checks' element #%d value: %s", step, zbx_mock_error_string(mock_err));

		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_strtime_to_timespec(nextcheck_expected, &ts)))
		{
			fail_msg("Cannot convert 'checks' element #%d value to time: %s", step,
					zbx_mock_error_string(mock_err));
		}

		nextcheck = calculate_item_nextcheck_unreachable(simple_interval, custom_intervals, now);

		zbx_snprintf(msg, sizeof(msg), "Invalid nextcheck calculation step %d", step++);
		zbx_mock_assert_time_eq(msg, ts.sec, nextcheck);

		now = nextcheck;
	}

	zbx_custom_interval_free(custom_intervals);
}
