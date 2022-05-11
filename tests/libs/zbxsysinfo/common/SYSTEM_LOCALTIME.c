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

#include "common.h"
#include "sysinfo.h"
#include "../../../../src/libs/zbxsysinfo/common/system.h"

#define LONG_DATETIME_LENGTH	30	/* Length of datetime like "2017-12-18,14:06:09.123,+02:30" */
#define SHORT_DATETIME_LENGTH	19	/* Length of datetime like "2017-12-18,14:06:09" */

static zbx_timespec_t	timespec;

time_t	__wrap_time(time_t *seconds);
int	__wrap_gettimeofday(struct timeval *__restrict tv, void *tz);

static void	zbx_mock_time(void)
{
	static int		time_parsed = 0;
	zbx_mock_error_t	error;
	zbx_mock_handle_t	param_handle;
	const char		*timestamp;
	struct tm		tm;
	int			ms, hour, min;
	char			sign, tmp[16];
	size_t			length;

	if (0 != time_parsed)
		return;	/* timestamp param was already parsed */

	memset(&tm, 0, sizeof(tm));
	memset(&timespec, 0, sizeof(timespec));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("timestamp", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &timestamp)))
	{
		fail_msg("Cannot get expected 'timestamp' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}

	length = strlen(timestamp);
	if (SHORT_DATETIME_LENGTH == length || LONG_DATETIME_LENGTH == length)
	{
		if (6 != sscanf(timestamp, "%04d-%02d-%02d,%02d:%02d:%02d", &tm.tm_year, &tm.tm_mon, &tm.tm_mday,
				&tm.tm_hour, &tm.tm_min, &tm.tm_sec) || 1900 > tm.tm_year || 1 > tm.tm_mon ||
				12 < tm.tm_mon || 1 > tm.tm_mday || 31 < tm.tm_mday || 0 > tm.tm_hour ||
				23 < tm.tm_hour || 0 > tm.tm_min || 59 < tm.tm_min || 0 > tm.tm_sec || 59 < tm.tm_sec)
		{
			fail_msg("Cannot parse date and time part of 'timestamp' parameter: %s", timestamp);
		}

		tm.tm_year -= 1900;
		tm.tm_mon--;

		if (LONG_DATETIME_LENGTH == length)
		{
			if (4 != sscanf(timestamp + SHORT_DATETIME_LENGTH, ".%d,%c%d:%d", &ms, &sign, &hour, &min) ||
					0 > ms || 1000 <= ms || ('-' != sign && '+' != sign) || 0 > hour || 23 < hour ||
					0 > min || 59 < min)
			{
				fail_msg("Cannot parse ms and timezone part of 'timestamp' parameter: %s", timestamp);
			}

			timespec.ns = ms * 1000000;

			zbx_snprintf(tmp, sizeof(tmp), "ZBX%c%02d:%02d", (sign == '+' ? '-' : '+'), hour, min);
			if (0 != setenv("TZ", tmp, 1))
				fail_msg("Cannot set timezone value: %s", tmp);

			tzset();
		}

		timespec.sec = mktime(&tm);
	}
	else
	{
		/* Fallback to numeric timestamp format */
		if (FAIL == is_uint32(timestamp, &timespec.sec))
			fail_msg("Cannot convert 'timestamp' parameter value to numeric: %s", timestamp);
	}

	time_parsed = 1;
}

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST		request;
	AGENT_RESULT 		param_result;
	zbx_mock_error_t	error;
	zbx_mock_handle_t	param_handle;
	const char		*expected_value_string, *expected_return_string, *key_string;
	char			*value = NULL;
	int			expected_result = SYSINFO_RET_FAIL, actual_result;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_return_string)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}

	if (0 == strcmp("SYSINFO_RET_OK", expected_return_string))
		expected_result = SYSINFO_RET_OK;
	else if (0 == strcmp("SYSINFO_RET_FAIL", expected_return_string))
		expected_result = SYSINFO_RET_FAIL;
	else
		fail_msg("Got unexpected 'return' parameter from test case data: %s", expected_return_string);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("result", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_value_string)))
	{
		fail_msg("Cannot get expected 'result' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("key", &param_handle)) ||
			ZBX_MOCK_SUCCESS != zbx_mock_string(param_handle, &key_string))
	{
		fail_msg("Cannot get expected 'key' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}

	init_request(&request);
	init_result(&param_result);

	if (SUCCEED != parse_item_key(key_string, &request))
		fail_msg("Cannot parse item key from string '%s'", key_string);

	if (0 != strcmp(request.key, "system.localtime"))
		fail_msg("Got unexpected item key parameter from test case data: %s", key_string);

	if (expected_result != (actual_result = SYSTEM_LOCALTIME(&request, &param_result)))
	{
		fail_msg("Got '%s' instead of '%s' as a result.", zbx_sysinfo_ret_string(actual_result),
				zbx_sysinfo_ret_string(expected_result));
	}

	if (SYSINFO_RET_OK == expected_result)
		value = (NULL != GET_TEXT_RESULT(&param_result)) ? *GET_TEXT_RESULT(&param_result) : NULL;
	else
		value = (NULL != GET_MSG_RESULT(&param_result)) ? *GET_MSG_RESULT(&param_result) : NULL;

	if (NULL == value || 0 != strcmp(expected_value_string, value))
	{
		fail_msg("Got '%s' instead of '%s' as a value.", (NULL != value ? value : "NULL"),
				expected_value_string);
	}

	free_request(&request);
	free_result(&param_result);
}

time_t	__wrap_time(time_t *seconds)
{
	zbx_mock_time();

	if (NULL != seconds)
		*seconds = timespec.sec;

	return timespec.sec;
}

int	__wrap_gettimeofday(struct timeval *__restrict tv, void *tz)
{
	if (NULL != tv)
	{
		zbx_mock_time();

		tv->tv_sec = timespec.sec;
		tv->tv_usec = timespec.ns / 1000;
	}

	if (NULL != tz)
		fail_msg("Timezone param in gettimeofday() call is not set to null");

	return 0;
}
