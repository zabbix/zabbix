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

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	handle;
	const char		*expected_result = NULL, *expected_error = NULL;
	char			**actual_result, **actual_error;
	AGENT_REQUEST		request;
	AGENT_RESULT		result;
	int			ret;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter("result", &handle)))
	{
		if (ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter("error", &handle)))
			fail_msg("Either \"result\" or \"error\" must be present in test case data.");

		if (ZBX_MOCK_SUCCESS != error || ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &expected_error)))
			fail_msg("Cannot get \"error\" parameter from test case data: %s", zbx_mock_error_string(error));
	}
	else
	{
		if (ZBX_MOCK_SUCCESS != error || ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &expected_result)))
			fail_msg("Cannot get \"result\" parameter from test case data: %s", zbx_mock_error_string(error));

		if (ZBX_MOCK_SUCCESS == zbx_mock_out_parameter("error", &handle))
			fail_msg("Parameters \"result\" and \"error\" cannot be both present in test case data.");
	}

	zbx_init_agent_request(&request);
	zbx_init_agent_result(&result);

	zbx_init_library_sysinfo(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

	if (SUCCEED != zbx_parse_item_key("system.boottime", &request))
		fail_msg("Parsing of \"system.boottime\" key failed.");

	switch (ret = system_boottime(&request, &result))
	{
		case SYSINFO_RET_OK:
			if (NULL == expected_result)
				fail_msg("system_boottime() was not expected to succeed.");
			if (NULL == (actual_result = ZBX_GET_TEXT_RESULT(&result)))
				fail_msg("Result is not set.");
			if (0 != strcmp(*actual_result, expected_result))
				fail_msg("Expected result \"%s\" instead of \"%s\".", expected_result, *actual_result);
			break;
		case SYSINFO_RET_FAIL:
			if (NULL == expected_error)
				fail_msg("system_boottime() was not expected to fail.");
			if (NULL == (actual_error = ZBX_GET_MSG_RESULT(&result)))
				fail_msg("Error message is not set.");
			if (0 != strcmp(*actual_error, expected_error))
				fail_msg("Expected error \"%s\" instead of \"%s\".", expected_error, *actual_error);
			break;
		default:
			fail_msg("Unexpected return of system_boottime(): %d (%s).", ret, zbx_sysinfo_ret_string(ret));
	}

	zbx_free_agent_request(&request);
	zbx_free_agent_result(&result);
}
