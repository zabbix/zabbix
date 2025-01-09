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
#include "../../../../src/libs/zbxsysinfo/linux/proc.c"

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
	const char	*expected_str;
	zbx_uint64_t	expected_result, actual_result;

	ZBX_UNUSED(state);

	get_test_param("expected", &expected_str);

	if (NULL == expected_str)
		fail_msg("Invalid test case data: must have \"expected\" parameter");

	expected_result = (zbx_uint64_t)strtoul(expected_str, NULL, 0);
	get_pid_mem_stats("1", &actual_result);

	if (expected_result != actual_result)
		fail_msg("Unexpected result from get_pid_mem_stats: expected %lu, got %lu",
				(unsigned long)expected_result, (unsigned long)actual_result);
}
