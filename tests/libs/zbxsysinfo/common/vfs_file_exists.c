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
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxsysinfo.h"
#include "../../../../src/libs/zbxsysinfo/sysinfo.h"
#include "../../../../src/libs/zbxsysinfo/common/vfs_file.h"

#define TEST_NAME "VFS_FILE_EXISTS"

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT	result;
	const char	*key;
	int 		expected_ret, ret;

	ZBX_UNUSED(state);

	key = zbx_mock_get_parameter_string("in.key");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	zbx_init_agent_request(&request);
	zbx_init_agent_result(&result);
	zbx_init_library_sysinfo(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

	if (SUCCEED != zbx_parse_item_key(key, &request))
		fail_msg("Cannot parse item key: %s", key);

	ret = vfs_file_exists(&request, &result);
	zbx_mock_assert_sysinfo_ret_eq("Invalid "TEST_NAME" return value", expected_ret, ret);

	if (SYSINFO_RET_OK == ret)
	{
		zbx_mock_assert_uint64_eq(TEST_NAME, zbx_mock_get_parameter_uint64("out.file_exists"), result.ui64);
	}
	else
	{
		zbx_mock_assert_ptr_ne("Invalid "TEST_NAME" error message", NULL, result.msg);
		zbx_mock_assert_str_eq("Invalid "TEST_NAME" error message",
			zbx_mock_get_parameter_string("out.error"), result.msg);
	}

	zbx_free_agent_result(&result);
	zbx_free_agent_request(&request);
}
