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

#include "net_if_common.h"
#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "zbxsysinfo.h"
#include "../../../../src/libs/zbxsysinfo/sysinfo.h"
#include "zbxnum.h"

void	zbx_mock_test_entry_net_if_common(void **state, int net_if_func)
{
	AGENT_REQUEST		request;
	AGENT_RESULT 		param_result;
	zbx_mock_error_t	error;
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
		if (FAIL == zbx_is_uint64(expected_param_value_string, &expected_param_value) &&
			SYSINFO_RET_OK == expected_result)
		{
			fail_msg("Cannot get expected numeric parameter from test case data: %s",
					expected_param_value_string);
		}
	}

	zbx_init_agent_request(&request);
	zbx_init_agent_result(&param_result);

	zbx_init_library_sysinfo(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

	if (SUCCEED != zbx_parse_item_key(init_param, &request))
		fail_msg("Cannot parse item key: %s", init_param);

	if (ZABBIX_MOCK_NET_IF_IN == net_if_func)
		actual_result = net_if_in(&request, &param_result);
	else if (ZABBIX_MOCK_NET_IF_OUT == net_if_func)
		actual_result = net_if_out(&request, &param_result);
	else if (ZABBIX_MOCK_NET_IF_TOTAL == net_if_func)
		actual_result = net_if_total(&request, &param_result);
	else
		fail_msg("invalid net_if_func");

	if (expected_result != actual_result)
	{
		fail_msg("Got %s instead of %s as a result.", zbx_sysinfo_ret_string(actual_result),
			zbx_sysinfo_ret_string(expected_result));
	}

	if (SYSINFO_RET_OK == expected_result)
	{
		if (NULL == ZBX_GET_UI64_RESULT(&param_result) || expected_param_value !=
				*ZBX_GET_UI64_RESULT(&param_result))
		{
			if (NULL != ZBX_GET_UI64_RESULT(&param_result))
			{
				fail_msg("Got '" ZBX_FS_UI64 "' instead of '%s' as a value.",
						*ZBX_GET_UI64_RESULT(&param_result), expected_param_value_string);
			}
			else
				fail_msg("Got 'NULL' instead of '%s' as a value.", expected_param_value_string);
		}
	}
	else /* SYSINFO_RET_FAIL == expected_result */
	{
		if (NULL == ZBX_GET_MSG_RESULT(&param_result) ||
			0 != strcmp(expected_param_value_string, *ZBX_GET_MSG_RESULT(&param_result)))
		{
				fail_msg("Got '%s' instead of '%s' as a value.",
					(NULL != ZBX_GET_MSG_RESULT(&param_result) ?
						*ZBX_GET_MSG_RESULT(&param_result) : "NULL"),
					expected_param_value_string);
		}
	}

	zbx_free_agent_request(&request);
	zbx_free_agent_result(&param_result);
}
