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

int	__wrap_DBis_null(const char *field);
DB_ROW	__wrap_DBfetch(DB_RESULT result);
DB_RESULT	__wrap_DBselect(const char *fmt, ...);
zbx_trend_state_t	__wrap_zbx_trends_get_avg(const char *table, zbx_uint64_t itemid, int start, int end,
		double *value);

int	__wrap_DBis_null(const char *field)
{
	ZBX_UNUSED(field);
	return SUCCEED;
}

DB_ROW	__wrap_DBfetch(DB_RESULT result)
{
	ZBX_UNUSED(result);
	return NULL;
}

DB_RESULT	__wrap_DBselect(const char *fmt, ...)
{
	ZBX_UNUSED(fmt);
	return NULL;
}

static	zbx_mock_handle_t	hout;
static int			iteration;

zbx_trend_state_t	__wrap_zbx_trends_get_avg(const char *table, zbx_uint64_t itemid, int start, int end, double *value)
{
	zbx_mock_handle_t	htime;
	zbx_timespec_t		start_exp, end_exp, start_ret = {start, 0}, end_ret = {end, 0};

	ZBX_UNUSED(table);
	ZBX_UNUSED(itemid);

	*value = 0;

	printf("iteration: %d\n", ++iteration);

	if (ZBX_MOCK_SUCCESS != zbx_mock_vector_element(hout, &htime))
		fail_msg("got more data than expected");

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_object_member_string(htime, "start"), &start_exp))
		fail_msg("invalid start time format");

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_object_member_string(htime, "end"), &end_exp))
		fail_msg("invalid end time format");

	zbx_mock_assert_timespec_eq("start time", &start_exp, &start_ret);
	zbx_mock_assert_timespec_eq("end time", &end_exp, &end_ret);

	return ZBX_TREND_STATE_NORMAL;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_timespec_t		ts;
	const char		*period;
	char			*error = NULL;
	int			skip, season_num;
	zbx_time_unit_t		season_unit;
	zbx_vector_dbl_t	values;
	zbx_vector_uint64_t	index;
	zbx_mock_handle_t	handle;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.time"), &ts))
		fail_msg("Invalid input time format");

	period = zbx_mock_get_parameter_string("in.period");
	season_num = atoi(zbx_mock_get_parameter_string("in.season_num"));
	season_unit = zbx_tm_str_to_unit(zbx_mock_get_parameter_string("in.season"));

	skip = atoi(zbx_mock_get_parameter_string("in.skip"));

	zbx_vector_dbl_create(&values);
	zbx_vector_uint64_create(&index);

	hout = zbx_mock_get_parameter_handle("out.data");

	if (FAIL == zbx_baseline_get_data(0, ITEM_VALUE_TYPE_FLOAT, ts.sec, period, season_num, season_unit, skip,
			&values, &index, &error))
	{
		fail_msg("failed to get baseline data: %s", error);
	}

	zbx_vector_uint64_destroy(&index);
	zbx_vector_dbl_destroy(&values);

	if (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hout, &handle))
		fail_msg("expected more values");
}
