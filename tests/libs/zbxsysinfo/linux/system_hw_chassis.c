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
#include "zbxmockutil.h"
#include "zbxmockhelper.h"

#include "zbxsysinfo.h"
#include "../../../../src/libs/zbxsysinfo/sysinfo.h"

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST		request;
	AGENT_RESULT		result;
	const char		*param, *expected_value;
	int			expected_result, actual_result;

	ZBX_UNUSED(state);

	param = zbx_mock_get_parameter_string("in.param");
	expected_value = zbx_mock_get_parameter_string("out.value");
	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	zbx_init_agent_request(&request);
	zbx_init_agent_result(&result);

	zbx_init_library_sysinfo(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

	if (SUCCEED != zbx_parse_item_key(param, &request))
		fail_msg("Cannot parse item key: %s", param);

	if (expected_result != (actual_result = system_hw_chassis(&request, &result)))
	{
		fail_msg("Unexpected return code from system_hw_chassis(): expected %s, got %s",
				zbx_sysinfo_ret_string(expected_result), zbx_sysinfo_ret_string(actual_result));
	}

	if (SYSINFO_RET_OK == expected_result && 0 == strcmp(expected_value, *ZBX_GET_STR_RESULT(&result)))
	{
		if (NULL == ZBX_GET_STR_RESULT(&result))
			fail_msg("Got 'NULL' instead of '%s' as a value.", expected_value);
	}
	else /* SYSINFO_RET_FAIL == expected_result */
	{
		if (NULL == ZBX_GET_MSG_RESULT(&result) || 0 != strcmp(expected_value, *ZBX_GET_MSG_RESULT(&result)))
		{
				fail_msg("Got '%s' instead of '%s' as a value.",
					(NULL != ZBX_GET_MSG_RESULT(&result) ? *ZBX_GET_MSG_RESULT(&result) : "NULL"),
					expected_value);
		}
	}

	zbx_free_agent_request(&request);
	zbx_free_agent_result(&result);
}
