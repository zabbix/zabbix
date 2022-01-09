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
#include "zbxmockutil.h"
#include "zbxmockhelper.h"

#include "common.h"
#include "sysinfo.h"

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

	init_request(&request);
	init_result(&result);

	if (SUCCEED != parse_item_key(param, &request))
		fail_msg("Cannot parse item key: %s", param);

	if (expected_result != (actual_result = SYSTEM_HW_CHASSIS(&request, &result)))
	{
		fail_msg("Unexpected return code from SYSTEM_HW_CHASSIS(): expected %s, got %s",
				zbx_sysinfo_ret_string(expected_result), zbx_sysinfo_ret_string(actual_result));
	}

	if (SYSINFO_RET_OK == expected_result && 0 == strcmp(expected_value, *GET_STR_RESULT(&result)))
	{
		if (NULL == GET_STR_RESULT(&result))
			fail_msg("Got 'NULL' instead of '%s' as a value.", expected_value);
	}
	else /* SYSINFO_RET_FAIL == expected_result */
	{
		if (NULL == GET_MSG_RESULT(&result) || 0 != strcmp(expected_value, *GET_MSG_RESULT(&result)))
		{
				fail_msg("Got '%s' instead of '%s' as a value.",
					(NULL != GET_MSG_RESULT(&result) ? *GET_MSG_RESULT(&result) : "NULL"),
					expected_value);
		}
	}

	free_request(&request);
	free_result(&result);
}
