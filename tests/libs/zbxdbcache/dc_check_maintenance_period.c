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
#include "mutexs.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "log.h"

#include "dbconfig.h"
#include "dbconfig_maintenance_test.h"

static zbx_timeperiod_type_t	get_period_type(const char *path)
{
	const char		*data;

	data = zbx_mock_get_parameter_string(path);

	if (0 == strcmp(data, "onetime"))
		return TIMEPERIOD_TYPE_ONETIME;
	if (0 == strcmp(data, "daily"))
		return TIMEPERIOD_TYPE_DAILY;
	if (0 == strcmp(data, "weekly"))
		return TIMEPERIOD_TYPE_WEEKLY;
	if (0 == strcmp(data, "monthly"))
		return TIMEPERIOD_TYPE_MONTHLY;

	return TIMEPERIOD_TYPE_ONETIME;
}

static void	get_period(zbx_dc_maintenance_period_t *period)
{
	zbx_timespec_t	ts;

	period->type = get_period_type("in.period.type");
	period->every = zbx_mock_get_parameter_uint64("in.period.every");
	period->dayofweek = zbx_mock_get_parameter_uint64("in.period.dayofweek");
	period->day = zbx_mock_get_parameter_uint64("in.period.day");
	period->month = zbx_mock_get_parameter_uint64("in.period.month");
	period->period = zbx_mock_get_parameter_uint64("in.period.period");
	period->start_time = zbx_mock_get_parameter_uint64("in.period.start_time");

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.period.start_date"), &ts))
		period->start_date = 0;
	else
		period->start_date = ts.sec;
}

static void	get_maintenance(zbx_dc_maintenance_t *maintenance)
{
	zbx_timespec_t	ts;

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.maintenance.active_since"),
			&ts))
	{
		fail_msg("Invalid 'active_since' format");
	}
	maintenance->active_since = (int)ts.sec;

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.maintenance.active_until"),
				&ts))
	{
		fail_msg("Invalid 'active_until' format");
	}
	maintenance->active_until = (int)ts.sec;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_dc_maintenance_period_t	period;
	zbx_dc_maintenance_t		maintenance;
	int				expected_ret, step;
	int				actual_rets[128];
	time_t				running_since, running_until;
	zbx_timespec_t			ts;
	zbx_mock_handle_t		times, returns, handle;
	zbx_mock_error_t		mock_err;
	const char			*now, *return_str;
	char				msg[4096];

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	get_maintenance(&maintenance);
	get_period(&period);

	times = zbx_mock_get_parameter_handle("in.times");

	for(step = 1; ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(times, &handle))); step++)
	{
		if (ZBX_MOCK_SUCCESS != mock_err)
		{
			fail_msg("Cannot read 'times' element #%d: %s", step, zbx_mock_error_string(mock_err));
			continue;
		}

		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_string(handle, &now)))
		{
			fail_msg("Cannot read 'times' element #%d value: %s", step, zbx_mock_error_string(mock_err));
			continue;
		}

		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_strtime_to_timespec(now, &ts)))
		{
			fail_msg("Cannot convert 'times' element #%d value to time: %s", step,
					zbx_mock_error_string(mock_err));
			continue;
		}

		actual_rets[step-1] = dc_check_maintenance_period_test(&maintenance, &period, ts.sec, &running_since,
				&running_until);
	}

	step = 1;
	returns = zbx_mock_get_parameter_handle("out.returns");

	for(step = 1; ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(returns, &handle))); step++)
	{
		if (ZBX_MOCK_SUCCESS != mock_err)
		{
			fail_msg("Cannot read 'returns' element #%d: %s", step, zbx_mock_error_string(mock_err));
			continue;
		}

		if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_string(handle, &return_str)))
		{
			fail_msg("Cannot read 'returns' element #%d value: %s", step, zbx_mock_error_string(mock_err));
			continue;
		}

		expected_ret = zbx_mock_str_to_return_code(return_str);
		zbx_snprintf(msg, sizeof(msg), "Invalid return code step %d", step);

		zbx_mock_assert_int_eq("dc_check_maintenance_period return value", expected_ret, actual_rets[step-1]);
	}
}
