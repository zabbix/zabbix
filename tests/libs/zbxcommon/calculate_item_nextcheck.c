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

#include "common.h"

#define ZBX_DATETIME_FORMAT	"%04d-%02d-%02d %02d:%02d:%02d"
#define ZBX_TIMEZONE_FORMAT	"%+03d:%02d"

#define ZBX_DATETIME_LEN	(4 + 1 + 2 + 1 + 2 + 1 + 2 + 1 + 2 + 1 + 2)
#define ZBX_TIMEZONE_LEN	(1 + 2 + 1 + 2)
#define ZBX_FULLTIME_LEN	(ZBX_DATETIME_LEN + ZBX_TIMEZONE_LEN)

/******************************************************************************
 *                                                                            *
 * Function: strtime_tz_sec                                                   *
 *                                                                            *
 * Purpose: gets timezone offset in seconds from date in RFC 3339 format      *
 *          (for example 2017-10-09 14:26:43+03:00)                           *
 *                                                                            *
 * Parameters: strtime - [IN] the time in RFC 3339 format                     *
 *             tz_sec  - [OUT] the timezone offset in seconds                 *
 *                                                                            *
 * Return value: SUCCEED - the timezone offset was parsed successfully        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_strtime_tz_sec(const char *strtime, int *tz_sec)
{
	int	tz_hour, tz_min;

	if (strlen(strtime) < ZBX_FULLTIME_LEN)
		return FAIL;

	if (2 != sscanf(strtime + ZBX_DATETIME_LEN, "%d:%d", &tz_hour, &tz_min))
		return FAIL;

	if (tz_hour < 0)
		tz_min = -tz_min;

	*tz_sec = (tz_hour * 60 + tz_min) * 60;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: time_to_strtime                                                  *
 *                                                                            *
 * Purpose: converts time  from seconds since the Epoch to RFC 3339 format    *
 *          (for example 2017-10-09 14:26:43+03:00)                           *
 *                                                                            *
 * Parameters: timestamp - [IN] the number of seconds since the Epoch         *
 *             tz_sec    - [IN] the timezone offset in seconds                *
 *             buffer    - [OUT] the output buffer                            *
 *                               (at least ZBX_FULLTIME_LEN + 1 characters)   *
 *             size      - [IN] the output buffer size                        *
 *                                                                            *
 * Return value: SUCCEED - the was converted successfully                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_time_to_strtime(time_t timestamp, int tz_sec, char *buffer, char size)
{
	struct tm	*tm;
	int		tz_hour, tz_min;

	if (size < ZBX_FULLTIME_LEN + 1)
		return -1;

	timestamp += tz_sec;
	tz_hour = tz_sec / 60;
	tz_min = tz_hour % 60;
	tz_hour /= 60;

	tm = gmtime(&timestamp);

	zbx_snprintf(buffer, size, ZBX_DATETIME_FORMAT ZBX_TIMEZONE_FORMAT,
			tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday,
			tm->tm_hour, tm->tm_min, tm->tm_sec, tz_hour, tz_min);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: time_to_strtime                                                  *
 *                                                                            *
 * Purpose: converts time from RFC 3339 format to seconds since the Epoch     *
 *                                                                            *
 * Parameters: strtime   - [IN] the time in RFC 3339 format                   *
 *             timestamp - [OUT] the number of seconds since the Epoch        *
 *                                                                            *
 * Return value: SUCCEED - the was converted successfully                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_strtime_to_time(const char *strtime, time_t *timestamp)
{
	struct tm	tm;
	int		tz_sec;
	time_t		time_gm;

	if (6 != sscanf(strtime, ZBX_DATETIME_FORMAT, &tm.tm_year, &tm.tm_mon, &tm.tm_mday,
			&tm.tm_hour, &tm.tm_min, &tm.tm_sec))
	{
		return FAIL;
	}

	if (FAIL == zbx_strtime_tz_sec(strtime, &tz_sec))
		return FAIL;

	tm.tm_year -= 1900;
	tm.tm_mon--;

	if (-1 == (time_gm = timegm(&tm)))
		return FAIL;

	*timestamp = time_gm - tz_sec;

	return SUCCEED;
}

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
	char			*error = NULL, nextcheck_result[32], msg[4096];
	const char		*delay, *nextcheck_expected;
	zbx_mock_handle_t	checks, handle;
	unsigned char		item_type;
	zbx_mock_error_t	mock_err;
	time_t			now;

	ZBX_UNUSED(state);

	delay = zbx_mock_get_parameter_string("in.delay");
	err = zbx_interval_preproc(delay, &simple_interval, &custom_intervals, &error);
	zbx_mock_assert_result_eq("zbx_interval_preproc() return value", SUCCEED, err);

	item_type = get_item_type(zbx_mock_get_parameter_string("in['item type']"));

	if (SUCCEED != zbx_strtime_to_time(zbx_mock_get_parameter_string("in['start time']"), &now))
		fail_msg("Invalid 'start time' format");

	checks = zbx_mock_get_parameter_handle("out.checks");

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(checks, &handle))))
	{
		if (ZBX_MOCK_SUCCESS != mock_err)
			fail_msg("Cannot read checks element", zbx_mock_error_string(mock_err));

		if (ZBX_MOCK_SUCCESS != zbx_mock_string(handle, &nextcheck_expected))
			fail_msg("Cannot read checks value", zbx_mock_error_string(mock_err));

		if (FAIL == zbx_strtime_tz_sec(nextcheck_expected, &tz_sec))
			fail_msg("Invalid nextcheck time format");

		nextcheck = calculate_item_nextcheck(0, item_type, simple_interval, custom_intervals, now);

		if (SUCCEED != zbx_time_to_strtime(nextcheck, tz_sec, nextcheck_result, sizeof(nextcheck_result)))
			fail_msg("Cannot convert nextcheck to string format");

		zbx_snprintf(msg, sizeof(msg), "Invalid nextcheck calculation step %d", step++);
		zbx_mock_assert_str_eq(msg, nextcheck_expected, nextcheck_result);

		now = nextcheck;
	}

	zbx_custom_interval_free(custom_intervals);
}
