/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

	init_request(&request);
	init_result(&result);

	if (SUCCEED != parse_item_key("system.boottime", &request))
		fail_msg("Parsing of \"system.boottime\" key failed.");

	switch (ret = SYSTEM_BOOTTIME(&request, &result))
	{
		case SYSINFO_RET_OK:
			if (NULL == expected_result)
				fail_msg("SYSTEM_BOOTTIME() was not expected to succeed.");
			if (NULL == (actual_result = GET_TEXT_RESULT(&result)))
				fail_msg("Result is not set.");
			if (0 != strcmp(*actual_result, expected_result))
				fail_msg("Expected result \"%s\" instead of \"%s\".", expected_result, *actual_result);
			break;
		case SYSINFO_RET_FAIL:
			if (NULL == expected_error)
				fail_msg("SYSTEM_BOOTTIME() was not expected to fail.");
			if (NULL == (actual_error = GET_MSG_RESULT(&result)))
				fail_msg("Error message is not set.");
			if (0 != strcmp(*actual_error, expected_error))
				fail_msg("Expected error \"%s\" instead of \"%s\".", expected_error, *actual_error);
			break;
		default:
			fail_msg("Unexpected return of SYSTEM_BOOTTIME(): %d (%s).", ret, zbx_sysinfo_ret_string(ret));
	}

	free_request(&request);
	free_result(&result);
}
