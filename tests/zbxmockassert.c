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

#include "common.h"

void cm_print_error(const char * const format, ...);

#define _FAIL(file, line, prefix, message, ...)						\
											\
do 											\
{											\
	cm_print_error("%s%s" message "\n", (NULL != prefix_msg ? prefix_msg : ""),	\
			(NULL != prefix_msg && '\0' != *prefix_msg ? ": " : ""),	\
			__VA_ARGS__);							\
	_fail(file, line);								\
}											\
while(0)

void	__zbx_mock_assert_str_eq(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *return_value)
{
	if (0 == strcmp(return_value, expected_value))
		return;

	_FAIL(file, line, prefix_msg, "Expected value \"%s\" while got \"%s\"", expected_value, return_value);
}

void	__zbx_mock_assert_str_ne(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *return_value)
{
	if (0 != strcmp(return_value, expected_value))
		return;

	_FAIL(file, line, prefix_msg, "Did not expect value \"%s\"", return_value);
}

void	__zbx_mock_assert_uint64_eq(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t return_value)
{
	if (return_value == expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected value \"" ZBX_FS_UI64 "\" while got \"" ZBX_FS_UI64 "\"", expected_value,
			return_value);
}

void	__zbx_mock_assert_uint64_ne(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t return_value)
{
	if (return_value != expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect value \"" ZBX_FS_UI64 "\"", return_value);
}

void	__zbx_mock_assert_int_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value)
{
	if (return_value == expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected value \"%d\" while got \"%d\"", expected_value, return_value);
}

void	__zbx_mock_assert_int_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value)
{
	if (return_value != expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect value \"%d\"", return_value);
}

void	__zbx_mock_assert_result_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value)
{
	if (expected_value == return_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected result \"%s\" while got \"%s\"",
			zbx_result_string(expected_value), zbx_result_string(return_value));
}

void	__zbx_mock_assert_result_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value)
{
	if (expected_value != return_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect result \"%s\"", zbx_result_string(return_value));
}

void	__zbx_mock_assert_sysinfo_ret_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value)
{
	if (expected_value == return_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected sysinfo result \"%s\" while got \"%s\"",
			zbx_sysinfo_ret_string(expected_value), zbx_sysinfo_ret_string(return_value));
}

void	__zbx_mock_assert_sysinfo_ret_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value)
{
	if (expected_value != return_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect sysinfo result \"%s\"", zbx_sysinfo_ret_string(return_value));
}

