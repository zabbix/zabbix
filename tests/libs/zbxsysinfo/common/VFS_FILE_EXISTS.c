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
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "file.h"

#define TEST_NAME "VFS_FILE_EXISTS"

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT	result;
	char		*key;
	char		*expected_error;
	char		*expected_value;
	int 		file_exists;
	int 		ret;

	key = (char *)zbx_mock_get_parameter_string("in.key");
	expected_value = (char *)zbx_mock_get_parameter_string("out.result");

	init_request(&request);
	init_result(&result);

	if (SUCCEED != parse_item_key(key, &request))
		fail_msg("Cannot parse item key: %s", key);

	ret = VFS_FILE_EXISTS(&request, &result);

	if (SYSINFO_RET_OK == ret)
	{
		file_exists = (int)zbx_mock_get_parameter_uint64("out.file_exists");

		zbx_mock_assert_str_eq(TEST_NAME, expected_value, "ok");
		zbx_mock_assert_int_eq(TEST_NAME, file_exists, result.ui64);
	}
	else
	{
		expected_error = (char *)zbx_mock_get_parameter_string("out.error");

		zbx_mock_assert_str_eq(TEST_NAME, expected_value, "fail");
		zbx_mock_assert_int_eq("Bad "TEST_NAME" return code!", SYSINFO_RET_FAIL, ret);
		assert_non_null(result.msg);
		zbx_mock_assert_str_eq("Bad "TEST_NAME" result message", expected_error, result.msg);
	}

	free_result(&result);
	free_request(&request);
}
