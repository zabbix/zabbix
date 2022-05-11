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
		const char *returned_value)
{
	if (0 == strcmp(returned_value, expected_value))
		return;

	_FAIL(file, line, prefix_msg, "Expected value \"%s\" while got \"%s\"", expected_value, returned_value);
}

void	__zbx_mock_assert_str_ne(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *returned_value)
{
	if (0 != strcmp(returned_value, expected_value))
		return;

	_FAIL(file, line, prefix_msg, "Did not expect value \"%s\"", returned_value);
}

void	__zbx_mock_assert_uint64_eq(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t returned_value)
{
	if (returned_value == expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected value \"" ZBX_FS_UI64 "\" while got \"" ZBX_FS_UI64 "\"", expected_value,
			returned_value);
}

void	__zbx_mock_assert_uint64_ne(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t returned_value)
{
	if (returned_value != expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect value \"" ZBX_FS_UI64 "\"", returned_value);
}

void	__zbx_mock_assert_int_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value)
{
	if (returned_value == expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected value \"%d\" while got \"%d\"", expected_value, returned_value);
}

void	__zbx_mock_assert_int_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value)
{
	if (returned_value != expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect value \"%d\"", returned_value);
}

void	__zbx_mock_assert_double_eq(const char *file, double line, const char *prefix_msg, double expected_value,
		double returned_value)
{
	if (ZBX_DOUBLE_EPSILON >= fabs(returned_value - expected_value))
		return;

	_FAIL(file, line, prefix_msg, "Expected value \"%f\" while got \"%f\"", expected_value, returned_value);
}

void	__zbx_mock_assert_double_ne(const char *file, double line, const char *prefix_msg, double expected_value,
		double returned_value)
{
	if (ZBX_DOUBLE_EPSILON < fabs(returned_value - expected_value))
		return;

	_FAIL(file, line, prefix_msg, "Did not expect value \"%f\"", returned_value);
}

void	__zbx_mock_assert_result_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value)
{
	if (expected_value == returned_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected result \"%s\" while got \"%s\"",
			zbx_result_string(expected_value), zbx_result_string(returned_value));
}

void	__zbx_mock_assert_result_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value)
{
	if (expected_value != returned_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect result \"%s\"", zbx_result_string(returned_value));
}

void	__zbx_mock_assert_sysinfo_ret_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value)
{
	if (expected_value == returned_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected sysinfo result \"%s\" while got \"%s\"",
			zbx_sysinfo_ret_string(expected_value), zbx_sysinfo_ret_string(returned_value));
}

void	__zbx_mock_assert_sysinfo_ret_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value)
{
	if (expected_value != returned_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect sysinfo result \"%s\"", zbx_sysinfo_ret_string(returned_value));
}

void	__zbx_mock_assert_ptr_eq(const char *file, int line, const char *prefix_msg, const void *expected_value,
		const void *returned_value)
{
	if (returned_value == expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Expected value \"0x%p\" while got \"0x%p\"", expected_value,
			returned_value);
}

void	__zbx_mock_assert_ptr_ne(const char *file, int line, const char *prefix_msg, const void *expected_value,
		const void *returned_value)
{
	if (returned_value != expected_value)
		return;

	_FAIL(file, line, prefix_msg, "Did not expect value \"0x%p\"", returned_value);
}

void	__zbx_mock_assert_timespec_eq(const char *file, int line, const char *prefix_msg,
		const zbx_timespec_t *expected_value, const zbx_timespec_t *returned_value)
{
	char	expected_str[ZBX_MOCK_TIMESTAMP_MAX_LEN], returned_str[ZBX_MOCK_TIMESTAMP_MAX_LEN];
	int	err;

	if (expected_value->sec == returned_value->sec && expected_value->ns == returned_value->ns)
		return;

	if (ZBX_MOCK_SUCCESS != (err = zbx_timespec_to_strtime(expected_value, expected_str, sizeof(expected_str))))
		_FAIL(file, line, NULL, "Cannot convert timestamp to string format: %s", zbx_mock_error_string(err));

	if (ZBX_MOCK_SUCCESS != (err = zbx_timespec_to_strtime(returned_value, returned_str, sizeof(returned_str))))
		_FAIL(file, line, NULL, "Cannot convert timestamp to string format: %s", zbx_mock_error_string(err));

	_FAIL(file, line, prefix_msg, "Expected timestamp \"%s\" while got \"%s\"", expected_str, returned_str);
}

void	__zbx_mock_assert_timespec_ne(const char *file, int line, const char *prefix_msg,
		const zbx_timespec_t *expected_value, const zbx_timespec_t *returned_value)
{
	char	returned_str[ZBX_MOCK_TIMESTAMP_MAX_LEN];
	int	err;

	if (expected_value->sec != returned_value->sec || expected_value->ns != returned_value->ns)
		return;

	if (ZBX_MOCK_SUCCESS != (err = zbx_timespec_to_strtime(returned_value, returned_str, sizeof(returned_str))))
		_FAIL(file, line, NULL, "Cannot convert timestamp to string format: %s", zbx_mock_error_string(err));

	_FAIL(file, line, prefix_msg, "Did not expect timestamp \"%s\"", returned_str);
}

void	__zbx_mock_assert_time_eq(const char *file, int line, const char *prefix_msg, time_t expected_value,
		time_t returned_value)
{
	char	expected_str[ZBX_MOCK_TIMESTAMP_MAX_LEN], returned_str[ZBX_MOCK_TIMESTAMP_MAX_LEN];
	int	err;

	if (expected_value == returned_value)
		return;

	if (ZBX_MOCK_SUCCESS != (err = zbx_time_to_strtime(expected_value, expected_str, sizeof(expected_str))))
		_FAIL(file, line, NULL, "Cannot convert timestamp to string format: %s", zbx_mock_error_string(err));

	if (ZBX_MOCK_SUCCESS != (err = zbx_time_to_strtime(returned_value, returned_str, sizeof(returned_str))))
		_FAIL(file, line, NULL, "Cannot convert timestamp to string format: %s", zbx_mock_error_string(err));

	_FAIL(file, line, prefix_msg, "Expected timestamp \"%s\" while got \"%s\"", expected_str, returned_str);
}

void	__zbx_mock_assert_time_ne(const char *file, int line, const char *prefix_msg, time_t expected_value,
		time_t returned_value)
{
	char	returned_str[ZBX_MOCK_TIMESTAMP_MAX_LEN];
	int	err;

	if (expected_value != returned_value)
		return;

	if (ZBX_MOCK_SUCCESS != (err = zbx_time_to_strtime(returned_value, returned_str, sizeof(returned_str))))
		_FAIL(file, line, NULL, "Cannot convert timestamp to string format: %s", zbx_mock_error_string(err));

	_FAIL(file, line, prefix_msg, "Did not expect timestamp \"%s\"", returned_str);
}
