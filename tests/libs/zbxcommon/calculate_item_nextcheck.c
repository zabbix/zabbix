/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
#include "zbxmocktime.h"

#include "common.h"

/******************************************************************************
 *                                                                            *
 * Function: get_item_type                                                    *
 *                                                                            *
 * Purpose: get item type from its string representation                      *
 *                                                                            *
 * Parameters: item_type - [IN] the item type                                 *
 *                                                                            *
 * Return value: Corresponding ITEM_TYPE_* define value                       *
 *                                                                            *
 * Comments: This function will fail test case if unknown item_type is given. *
 *                                                                            *
 ******************************************************************************/
static unsigned char	get_item_type(const char *item_type)
{
	const char	*item_types[] = {
			"ZABBIX",
			"SNMPv1",
			"TRAPPER",
			"SIMPLE",
			"SNMPv2c",
			"INTERNAL",
			"SNMPv3",
			"ZABBIX_ACTIVE",
			"AGGREGATE",
			"HTTPTEST",
			"EXTERNAL",
			"DB_MONITOR",
			"IPMI",
			"SSH",
			"TELNET",
			"CALCULATED",
			"JMX",
			"SNMPTRAP",
			"DEPENDENT",
			NULL
	};
	int		i;

	for (i = 0; NULL != item_types[i]; i++)
	{
		if (0 == strcmp(item_types[i], item_type))
			return i;
	}

	fail_msg("Unknown item type: %s", item_type);

	return 0;
}

void	zbx_mock_test_entry(void **state)
{
	int			err, simple_interval, nextcheck, tz_sec, step = 1;
	zbx_custom_interval_t	*custom_intervals = NULL;
	char			*error = NULL, nextcheck_result[64], msg[4096];
	const char		*delay, *nextcheck_expected;
	zbx_mock_handle_t	checks, handle;
	unsigned char		item_type;
	zbx_mock_error_t	mock_err;
	time_t			now;
	zbx_timespec_t		ts;

	ZBX_UNUSED(state);

	setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1);

	delay = zbx_mock_get_parameter_string("in.delay");
	err = zbx_interval_preproc(delay, &simple_interval, &custom_intervals, &error);
	zbx_mock_assert_result_eq("zbx_interval_preproc() return value", SUCCEED, err);

	item_type = get_item_type(zbx_mock_get_parameter_string("in['item type']"));

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in['start time']"), &ts))
		fail_msg("Invalid 'start time' format");

	now = ts.sec;
	checks = zbx_mock_get_parameter_handle("out.checks");

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(checks, &handle))))
	{
		if (ZBX_MOCK_SUCCESS != mock_err)
			fail_msg("Cannot read checks element: %s", zbx_mock_error_string(mock_err));

		if (ZBX_MOCK_SUCCESS != zbx_mock_string(handle, &nextcheck_expected))
			fail_msg("Cannot read checks value: %s", zbx_mock_error_string(mock_err));

		if (ZBX_MOCK_SUCCESS != zbx_strtime_tz_sec(nextcheck_expected, &tz_sec))
			fail_msg("Invalid nextcheck time format");

		nextcheck = calculate_item_nextcheck(0, item_type, simple_interval, custom_intervals, now);

		if (ZBX_MOCK_SUCCESS != zbx_time_to_strtime(nextcheck, tz_sec, nextcheck_result,
				sizeof(nextcheck_result)))
		{
			fail_msg("Cannot convert nextcheck to string format");
		}

		zbx_snprintf(msg, sizeof(msg), "Invalid nextcheck calculation step %d", step++);
		zbx_mock_assert_str_eq(msg, nextcheck_expected, nextcheck_result);

		now = nextcheck;
	}

	zbx_custom_interval_free(custom_intervals);
}
