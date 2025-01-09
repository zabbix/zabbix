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

#define FAIL_PARAM(NAME, MOCK_ERR)	fail_msg("Cannot get \"%s\": %s", NAME, zbx_mock_error_string(MOCK_ERR))

static void	get_out_parameter(const char *name, const char **value);

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT	result;
	const char	*expected_json, *expected_error, *expected_string, *actual_string;
	char		**p_result;
	int		expected_ret, actual_ret;

	ZBX_UNUSED(state);

	get_out_parameter("json", &expected_json);
	get_out_parameter("error", &expected_error);

	if (NULL == expected_json)
	{
		if (NULL == expected_error)
			fail_msg("Invalid test case data: expected \"json\" or \"error\" out parameter");

		expected_ret = SYSINFO_RET_FAIL;
		expected_string = expected_error;
	}
	else
	{
		if (NULL != expected_error)
			fail_msg("Invalid test case data: expected only one of \"json\" and \"error\" out parameters");

		expected_ret = SYSINFO_RET_OK;
		expected_string = expected_json;
	}

	zbx_init_agent_request(&request);
	zbx_init_agent_result(&result);

	zbx_init_library_sysinfo(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

	if (SUCCEED != zbx_parse_item_key("net.if.discovery", &request))
		fail_msg("Unexpected return code from zbx_parse_item_key()");

	actual_ret = net_if_discovery(&request, &result);

	zbx_free_agent_request(&request);

	if (actual_ret != expected_ret)
	{
		fail_msg("Unexpected return code from net_if_discovery(): expected %s, got %s",
				zbx_sysinfo_ret_string(expected_ret), zbx_sysinfo_ret_string(actual_ret));
	}

	/* we know the return code is one of these */
	if (SYSINFO_RET_OK == actual_ret)
		p_result = ZBX_GET_STR_RESULT(&result);
	else
		p_result = ZBX_GET_MSG_RESULT(&result);

	if (NULL == p_result)
		fail_msg("NULL result in AGENT_RESULT while expected \"%s\"", expected_string);

	actual_string = *p_result;

	if (0 != strcmp(expected_string, actual_string))
		fail_msg("Unexpected result string: expected \"%s\", got \"%s\"", expected_string, actual_string);

	zbx_free_agent_result(&result);
}

/* fails on error, sets *value to NULL if parameter not found */
static void	get_out_parameter(const char *name, const char **value)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;

	*value = NULL;

	if (ZBX_MOCK_NO_PARAMETER != (error = zbx_mock_out_parameter(name, &handle)) && ZBX_MOCK_SUCCESS != error)
		FAIL_PARAM(name, error);
	else if (ZBX_MOCK_SUCCESS == error && ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, value)))
		FAIL_PARAM(name, error);
}
