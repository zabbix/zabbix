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

#include "common.h"
#include "sysinfo.h"
#include "../../../../src/libs/zbxsysinfo/common/system.h"

static time_t	expected_timestamp_value = 0;

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST		request;
	AGENT_RESULT 		param_result;
	zbx_mock_error_t	error;
	zbx_mock_handle_t	param_handle;
	const char		*expected_value_string, *expected_return_string, *key_string, *type,
				*expected_timestamp_string;
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
		fail_msg("Get unexpected 'return' parameter from test case data: %s", expected_return_string);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("result", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_value_string)))
	{
		fail_msg("Cannot get expected 'result' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}

	if (SYSINFO_RET_OK == expected_result)
	{
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("timestamp", &param_handle)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_timestamp_string)))
		{
			fail_msg("Cannot get expected 'timestamp' parameter from test case data: %s",
					zbx_mock_error_string(error));
		}

		if (FAIL == is_uint32(expected_timestamp_string, &expected_timestamp_value)
				&& SYSINFO_RET_OK == expected_result)
		{
			fail_msg("Cannot get expected timestamp from test case data: %s", expected_timestamp_value);
		}
	}

	init_request(&request);
	init_result(&param_result);

	if (ZBX_MOCK_SUCCESS == (error = zbx_mock_in_parameter("key", &param_handle)) &&
			ZBX_MOCK_SUCCESS == zbx_mock_string(param_handle, &key_string) &&
			SUCCEED != parse_item_key(key_string, &request))
	{
		fail_msg("Failed to parse item key from string '%s'", key_string);
	}

	if (expected_result != (actual_result = SYSTEM_LOCALTIME(&request, &param_result)))
	{
		fail_msg("Got '%s' instead of '%s' as a result.", zbx_sysinfo_ret_string(actual_result),
				zbx_sysinfo_ret_string(expected_result));
	}

	type = get_rparam(&request, 0);

	if (SYSINFO_RET_OK == expected_result)
	{
		if (NULL == type || 0 != strcmp(type, "local"))
		{
			if (NULL != GET_UI64_RESULT(&param_result))
				value = zbx_dsprintf(value, ZBX_FS_UI64, *GET_UI64_RESULT(&param_result));
		}
		else
			value = *GET_STR_RESULT(&param_result);
	}
	else
		value = *GET_MSG_RESULT(&param_result);

	if (NULL == value || 0 != strcmp(expected_value_string, value))
	{
		fail_msg("Got '%s' instead of '%s' as a value.", (NULL != value ? value : "NULL"),
				expected_value_string);
	}
	else
	{
		if (SYSINFO_RET_OK == expected_result && (NULL == type || 0 != strcmp(type, "local")))
			zbx_free(value);
	}

	free_request(&request);
	free_result(&param_result);
}

time_t	__wrap_time(time_t *seconds)
{
	ZBX_UNUSED(seconds);

	return expected_timestamp_value;
}

void	__wrap_zbx_get_time(struct tm *tm, long *milliseconds, zbx_timezone_t *tz)
{
	localtime_r(&expected_timestamp_value, tm);

	if (NULL != tz)
	{
		tz->tz_sign = '+';
		tz->tz_hour = 2;
		tz->tz_min = 30;
	}

	*milliseconds = 123;
}
