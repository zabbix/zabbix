/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	const char	*param;
	char		*error = NULL;
	zbx_timespec_t	ts_in, ts_out, ts;
	struct tm	tm;
	size_t		len;
	int		multiplier;
	zbx_time_unit_t	base;
	time_t		time_tmp;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.time"), &ts_in))
		fail_msg("Invalid input time format");

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("out.time"), &ts_out))
		fail_msg("Invalid output time format");

	param = zbx_mock_get_parameter_string("in.param");
	if (SUCCEED != zbx_tm_parse_period(param, &len, &multiplier, &base, &error))
	{
		fail_msg("Invalid time period: %s", error);
		zbx_free(error);
	}

	time_tmp = ts_in.sec;
	tm = *localtime(&time_tmp);

	zbx_tm_sub(&tm, multiplier, base);
	if (-1 == (time_tmp = mktime(&tm)))
		fail_msg("invalid time structure");

	ts.ns = 0;
	ts.sec = time_tmp;

	zbx_mock_assert_timespec_eq("Time subtraction result", &ts_out, &ts);
}
