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
#include "zbxdbhigh.h"

int	__wrap_zbx_db_is_null(const char *field);
void	__wrap_zbx_recalc_time_period(time_t *tm_start, int table_group);

int	__wrap_zbx_db_is_null(const char *field)
{
	ZBX_UNUSED(field);
	return SUCCEED;
}

void	__wrap_zbx_recalc_time_period(time_t *tm_start, int table_group)
{
	ZBX_UNUSED(tm_start);
	ZBX_UNUSED(table_group);
}

void	zbx_mock_test_entry(void **state)
{
	time_t		start, end;
	const char	*param;
	int		expected_ret, returned_ret;
	char		*error = NULL;
	zbx_timespec_t	ts_from, ts_start, ts_end, ts;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.time"), &ts_from))
		fail_msg("Invalid input time format");

	param = zbx_mock_get_parameter_string("in.param");

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	returned_ret = zbx_trends_parse_range(ts_from.sec, param, &start, &end, &error);

	if (FAIL == returned_ret)
	{
		printf("zbx_trends_parse_range() error: %s\n", error);
		zbx_free(error);
	}
	zbx_mock_assert_result_eq("zbx_trends_parse_range()", expected_ret, returned_ret);

	if (SUCCEED == returned_ret)
	{
		if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("out.start"), &ts_start))
			fail_msg("Invalid start time format");

		if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("out.end"), &ts_end))
			fail_msg("Invalid end time format");

		ts.ns = 0;
		ts.sec = start;
		zbx_mock_assert_timespec_eq("start time", &ts_start, &ts);

		ts.sec = end;
		zbx_mock_assert_timespec_eq("end time", &ts_end, &ts);
	}
}
