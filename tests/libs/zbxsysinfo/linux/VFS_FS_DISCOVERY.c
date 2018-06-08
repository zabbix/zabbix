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

#define GET_TEST_PARAM_FAIL(NAME, MOCK_ERR)	fail_msg("Cannot get \"%s\": %s", NAME, zbx_mock_error_string(MOCK_ERR))

static void	get_test_param(const char *name, const char **value)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;

	*value = NULL;

	if (ZBX_MOCK_NO_PARAMETER != (error = zbx_mock_out_parameter(name, &handle)) && ZBX_MOCK_SUCCESS != error)
		GET_TEST_PARAM_FAIL(name, error);
	else if (ZBX_MOCK_SUCCESS == error && ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, value)))
		GET_TEST_PARAM_FAIL(name, error);
}

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT	result;
	const char	*expected_json, *expected_error, *expected_string, *actual_string;
	char		**p_result;
	int		expected_result, actual_result;

	ZBX_UNUSED(state);

	get_test_param("json", &expected_json);
	get_test_param("error", &expected_error);

	if (NULL == expected_json)
	{
		if (NULL == expected_error)
			fail_msg("Invalid test case data: must have one - \"json\" or \"error\" parameter");

		expected_result = SYSINFO_RET_FAIL;
		expected_string = expected_error;
	}
	else
	{
		if (NULL != expected_error)
			fail_msg("Invalid test case data: only one parameter \"json\" or \"error\" must exist");

		expected_result = SYSINFO_RET_OK;
		expected_string = expected_json;
	}

	init_request(&request);
	init_result(&result);

	if (expected_result != (actual_result = VFS_FS_DISCOVERY(&request, &result)))
	{
		fail_msg("Unexpected return code from VFS_FS_DISCOVERY(): expected %s, got %s",
				zbx_sysinfo_ret_string(expected_result), zbx_sysinfo_ret_string(actual_result));
	}

	if (SYSINFO_RET_OK == actual_result)
		p_result = GET_STR_RESULT(&result);
	else
		p_result = GET_MSG_RESULT(&result);

	if (NULL == p_result)
		fail_msg("NULL result in AGENT_RESULT while expected \"%s\"", expected_string);

	actual_string = *p_result;

	if (0 != strcmp(expected_string, actual_string))
		fail_msg("Unexpected result string: expected \"%s\", got \"%s\"", expected_string, actual_string);

	free_request(&request);
	free_result(&result);
}
