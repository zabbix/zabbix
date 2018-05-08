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
	int 		r;
	char		*filename;
	int 		extraparams;
	char		*expected_msg;
	char		*expected_retval;
	int 		file_exists;

	filename	= (char *)zbx_mock_get_parameter_string("in.filename");
	extraparams	= (int)zbx_mock_get_parameter_uint64("in.extraparams");
	expected_retval	= (char *)zbx_mock_get_parameter_string("out.retval");

	request.key		= NULL;
	request.nparam		= 1 + extraparams;
	request.params		= (char **)zbx_malloc(NULL, request.nparam * sizeof(char *));
	request.params[0]	= filename;
	request.lastlogsize	= 0;
	request.mtime		= 0;

	result.msg		= NULL;

	r = VFS_FILE_EXISTS(&request, &result);

	if (SYSINFO_RET_OK == r)
	{
		zbx_mock_assert_str_eq(TEST_NAME, expected_retval, "ok");

		file_exists = (int)zbx_mock_get_parameter_uint64("out.file_exists");
		zbx_mock_assert_int_eq(TEST_NAME, file_exists, result.ui64);
	}
	else
	{
		zbx_mock_assert_int_eq("Bad "TEST_NAME" return code!", SYSINFO_RET_FAIL, r);

		zbx_mock_assert_str_eq(TEST_NAME, expected_retval, "fail");

		expected_msg = (char *)zbx_mock_get_parameter_string("out.msg");
		assert_non_null(result.msg);
		zbx_mock_assert_str_eq("Bad "TEST_NAME" result message", expected_msg, result.msg);
	}

	request.params[0] = NULL;
	zbx_free(request.params);

	if(result.msg)
		zbx_free(result.msg);
}
