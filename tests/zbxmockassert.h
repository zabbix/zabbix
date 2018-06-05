/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU Ge_neral Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU Ge_neral Public License for more details.
**
** You should have received a copy of the GNU Ge_neral Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_MOCK_ASSERT_H
#define ZABBIX_MOCK_ASSERT_H

#include "common.h"

void	__zbx_mock_assert_str_eq(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *return_value);

void	__zbx_mock_assert_str__ne(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *return_value);

void	__zbx_mock_assert_uint64_eq(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t return_value);

void	__zbx_mock_assert_uint64__ne(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t return_value);

void	__zbx_mock_assert_int_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value);

void	__zbx_mock_assert_int_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value);

void	__zbx_mock_assert_result_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value);

void	__zbx_mock_assert_result_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value);

void	__zbx_mock_assert_sysinfo_ret_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value);

void	__zbx_mock_assert_sysinfo_ret_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value);

#define zbx_mock_assert_str_eq(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_str_eq(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_str_ne(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_str_ne(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_uint64_eq(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_uint64_eq(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_uint64_ne(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_uint64_ne(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_int_eq(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_int_eq(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_int_ne(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_int_ne(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_result_eq(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_result_eq(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_result_ne(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_result_ne(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_sysinfo_ret_eq(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_sysinfo_ret_eq(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define zbx_mock_assert_sysinfo_ret_ne(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_sysinfo_ret_ne(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#endif
