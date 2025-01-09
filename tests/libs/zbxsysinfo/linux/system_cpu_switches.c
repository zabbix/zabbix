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

#include "zbxsysinfo.h"
#include "../../../../src/libs/zbxsysinfo/sysinfo.h"
#include "zbxnum.h"

void	zbx_mock_test_entry(void **state)
{
	/* variables for test infrastructure */
	zbx_mock_error_t	mock_ret_code;		/* return code from zbx_mock_*() functions */
	zbx_mock_handle_t	mock_handle;
	int			expected_ret = 0;
	const	char		*expected_error_msg = NULL, *expected_ret_str = NULL,
				*expected_context_switches_str = NULL;
	zbx_uint64_t		expected_context_switches_count;

	/* variables for calling function-under-test */
	AGENT_REQUEST	zbx_agent_request;
	AGENT_RESULT	zbx_agent_result;
	int		actual_ret;

	ZBX_UNUSED(state);

	/* set up test case values from an external source */
	/* system_cpu_switches() does not use AGENT_REQUEST argument, only output parameters have to be set up */

	if (ZBX_MOCK_SUCCESS != (mock_ret_code = zbx_mock_out_parameter("expected_ret", &mock_handle)) ||
			ZBX_MOCK_SUCCESS != (mock_ret_code = zbx_mock_string(mock_handle, &expected_ret_str)))
	{
		fail_msg("Cannot get \"expected_ret\" parameter from test case data: %s",
				zbx_mock_error_string(mock_ret_code));
	}

	if (0 == strcmp("SYSINFO_RET_OK", expected_ret_str))
		expected_ret = SYSINFO_RET_OK;
	else if (0 == strcmp("SYSINFO_RET_FAIL", expected_ret_str))
		expected_ret = SYSINFO_RET_FAIL;
	else
		fail_msg("Invalid \"expected_ret\" parameter in test case data: %s", expected_ret_str);

	if (SYSINFO_RET_OK == expected_ret)
	{
		if (ZBX_MOCK_SUCCESS != (mock_ret_code = zbx_mock_out_parameter("ctxt", &mock_handle)) ||
				ZBX_MOCK_SUCCESS != (mock_ret_code = zbx_mock_string(mock_handle,
				&expected_context_switches_str)))
		{
			fail_msg("Cannot get \"ctxt\" parameter from test case data: %s",
					zbx_mock_error_string(mock_ret_code));
		}

		if (SUCCEED != zbx_is_uint64(expected_context_switches_str, &expected_context_switches_count))
			fail_msg("Invalid \"ctxt\" parameter in test case data: %s", expected_context_switches_str);
	}
	else	/* SYSINFO_RET_FAIL */
	{
		if (ZBX_MOCK_SUCCESS != (mock_ret_code = zbx_mock_out_parameter("error_msg", &mock_handle)) ||
				ZBX_MOCK_SUCCESS != (mock_ret_code = zbx_mock_string(mock_handle, &expected_error_msg)))
		{
			fail_msg("Cannot get \"error_msg\" parameter from test case data: %s",
					zbx_mock_error_string(mock_ret_code));
		}
	}

	zbx_init_agent_request(&zbx_agent_request);
	zbx_init_agent_result(&zbx_agent_result);

	zbx_init_library_sysinfo(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

	/* call the function-under-test */
	actual_ret = system_cpu_switches(&zbx_agent_request, &zbx_agent_result);

	/* check test results */

	if (expected_ret != actual_ret)
	{
		fail_msg("Unexpected return code from system_cpu_switches(): expected %s, got %s",
				zbx_sysinfo_ret_string(expected_ret), zbx_sysinfo_ret_string(actual_ret));
	}

	if (SYSINFO_RET_OK == actual_ret)
	{
		zbx_uint64_t	*actual_context_switches_count;

		if (NULL == (actual_context_switches_count = ZBX_GET_UI64_RESULT(&zbx_agent_result)))
			fail_msg("system_cpu_switches() returned no valid number of context switches in AGENT_RESULT.");

		if (expected_context_switches_count != *actual_context_switches_count)
		{
			fail_msg("system_cpu_switches() context switches: expected " ZBX_FS_UI64 ", got " ZBX_FS_UI64
					".", expected_context_switches_count, *actual_context_switches_count);
		}
	}
	else	/* SYSINFO_RET_FAIL */
	{
		char	**actual_error_msg;

		if (NULL == (actual_error_msg = ZBX_GET_MSG_RESULT(&zbx_agent_result)) || NULL == *actual_error_msg)
			fail_msg("system_cpu_switches() returned no valid error message in AGENT_RESULT.");

		if (0 != strcmp(expected_error_msg, *actual_error_msg))
		{
			fail_msg("system_cpu_switches() error message: expected \"%s\", got \"%s\"",
					expected_error_msg, *actual_error_msg);
		}
	}

	zbx_free_agent_request(&zbx_agent_request);
	zbx_free_agent_result(&zbx_agent_result);
}
