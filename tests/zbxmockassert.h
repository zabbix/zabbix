/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#ifndef ZABBIX_MOCK_ASSERT_H
#define ZABBIX_MOCK_ASSERT_H

#include "common.h"

void	__zbx_mock_assert_streq(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *return_value);

void	__zbx_mock_assert_strne(const char *file, int line, const char *prefix_msg, const char *expected_value,
		const char *return_value);

void	__zbx_mock_assert_uint64eq(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t return_value);

void	__zbx_mock_assert_uint64ne(const char *file, int line, const char *prefix_msg, zbx_uint64_t expected_value,
		zbx_uint64_t return_value);

void	__zbx_mock_assert_inteq(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value);

void	__zbx_mock_assert_intne(const char *file, int line, const char *prefix_msg, int expected_value,
		int return_value);

#define	zbx_mock_assert_streq(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_streq(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define	zbx_mock_assert_strne(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_strne(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define	zbx_mock_assert_uint64eq(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_uint64eq(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define	zbx_mock_assert_uint64ne(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_uint64ne(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define	zbx_mock_assert_inteq(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_inteq(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#define	zbx_mock_assert_intne(prefix_msg, expected_value, return_value) \
	__zbx_mock_assert_intne(__FILE__, __LINE__, prefix_msg, expected_value, return_value)

#endif
