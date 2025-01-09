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

#ifndef ZABBIX_MOCK_ASSERT_H
#define ZABBIX_MOCK_ASSERT_H

#include "zbxtime.h"
#include "zbxalgo.h"

void	__zbx_mock_assert_str_eq(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *returned_value);

void	__zbx_mock_assert_str_ne(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *returned_value);

void	__zbx_mock_assert_uint64_eq(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t returned_value);

void	__zbx_mock_assert_uint64_ne(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t returned_value);

void	__zbx_mock_assert_vector_uint64_eq(const char *file, int line, const char *prefix_msg,
		zbx_vector_uint64_t *expected_value, zbx_vector_uint64_t *returned_value);

void	__zbx_mock_assert_vector_uint64_ne(const char *file, int line, const char *prefix_msg,
		zbx_vector_uint64_t *expected_value, zbx_vector_uint64_t *returned_value);

void	__zbx_mock_assert_int_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value);

void	__zbx_mock_assert_int_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value);

void	__zbx_mock_assert_double_eq(const char *file, int line, const char *prefix_msg, double expected_value,
		double returned_value);

void	__zbx_mock_assert_double_ne(const char *file, int line, const char *prefix_msg, double expected_value,
		double returned_value);

void	__zbx_mock_assert_result_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value);

void	__zbx_mock_assert_result_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value);

void	__zbx_mock_assert_sysinfo_ret_eq(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value);

void	__zbx_mock_assert_sysinfo_ret_ne(const char *file, int line, const char *prefix_msg, int expected_value,
		int returned_value);

void	__zbx_mock_assert_ptr_eq(const char *file, int line, const char *prefix_msg, const void *expected_value,
		const void *returned_value);

void	__zbx_mock_assert_ptr_ne(const char *file, int line, const char *prefix_msg, const void *expected_value,
		const void *returned_value);

void	__zbx_mock_assert_timespec_eq(const char *file, int line, const char *prefix_msg,
		const zbx_timespec_t *expected_value, const zbx_timespec_t *returned_value);

void	__zbx_mock_assert_timespec_ne(const char *file, int line, const char *prefix_msg,
		const zbx_timespec_t *expected_value, const zbx_timespec_t *returned_value);

void	__zbx_mock_assert_time_eq(const char *file, int line, const char *prefix_msg, time_t expected_value,
		time_t returned_value);

void	__zbx_mock_assert_time_ne(const char *file, int line, const char *prefix_msg, time_t expected_value,
		time_t returned_value);

#define zbx_mock_assert_str_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_str_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_str_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_str_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_uint64_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_uint64_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_uint64_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_uint64_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_int_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_int_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_int_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_int_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_double_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_double_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_double_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_double_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_result_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_result_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_result_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_result_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_sysinfo_ret_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_sysinfo_ret_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_sysinfo_ret_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_sysinfo_ret_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_ptr_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_ptr_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_ptr_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_ptr_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_timespec_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_timespec_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_timespec_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_timespec_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_time_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_time_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_time_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_time_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_vector_uint64_eq(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_vector_uint64_eq(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)

#define zbx_mock_assert_vector_uint64_ne(prefix_msg, expected_value, returned_value) \
	__zbx_mock_assert_vector_uint64_ne(__FILE__, __LINE__, prefix_msg, expected_value, returned_value)
#endif
