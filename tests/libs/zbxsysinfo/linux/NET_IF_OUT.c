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

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST		request;
	AGENT_RESULT 		param_result;
	zbx_mock_error_t	error;
	zbx_mock_handle_t	init_param_handle;
	const char		*init_param;
	zbx_mock_handle_t	param_handle;
	const char		*expected_param_value_string, *expected_return_string;
	zbx_uint64_t 		expected_param_value = 0;
	int			expected_result = FAIL, actual_result = FAIL;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle,&expected_return_string)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}
	else
	{
		if (0 == strcmp("SYSINFO_RET_OK", expected_return_string))
			expected_result = SYSINFO_RET_OK;
		else if (0 == strcmp("SYSINFO_RET_FAIL", expected_return_string))
			expected_result = SYSINFO_RET_FAIL;
		else
			fail_msg("Get unexpected 'return' parameter from test case data: %s", expected_return_string);
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("param", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &init_param)))
	{
		fail_msg("Cannot get input 'param' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("result", &param_handle)) ||
		ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_param_value_string)))
	{
		fail_msg("Cannot get expected 'result' parameters from test case data: %s",
				zbx_mock_error_string(error));
	}
	else
	{
		if (FAIL == is_uint64(expected_param_value_string, &expected_param_value) &&
			SYSINFO_RET_OK == expected_result)
		{
			fail_msg("Cannot get expected numeric parameter from test case data: %s",
					expected_param_value_string);
		}
	}

	init_request(&request);
	init_result(&param_result);
	if (SUCCEED != parse_item_key(init_param, &request))
		fail_msg("Cannot parse item key: %s", init_param);

	if (expected_result != (actual_result = NET_IF_OUT(&request,&param_result)))
	{
		fail_msg("Got %s instead of %s as a result.", zbx_sysinfo_ret_string(actual_result),
			zbx_sysinfo_ret_string(expected_result));
	}

	if (SYSINFO_RET_OK == expected_result)
	{
		if (NULL == GET_UI64_RESULT(&param_result) || expected_param_value != *GET_UI64_RESULT(&param_result))
		{
			if (NULL != GET_UI64_RESULT(&param_result))
			{
				fail_msg("Got '" ZBX_FS_UI64 "' instead of '%s' as a value.",
						*GET_UI64_RESULT(&param_result), expected_param_value_string);
			}
			else
				fail_msg("Got 'NULL' instead of '%s' as a value.", expected_param_value_string);
		}
	}
	else /* SYSINFO_RET_FAIL == expected_result */
	{
		if (NULL == GET_MSG_RESULT(&param_result) ||
			0 != strcmp(expected_param_value_string, *GET_MSG_RESULT(&param_result)))
		{
				fail_msg("Got '%s' instead of '%s' as a value.",
					(NULL != GET_MSG_RESULT(&param_result) ?
						*GET_MSG_RESULT(&param_result) : "NULL"),
					expected_param_value_string);
		}
	}

	free_request(&request);
	free_result(&param_result);
}
