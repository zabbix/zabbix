/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "zbxcunit.h"

#define ZBX_CU_ASSERT_ARGS_LENGTH	1024
#define ZBX_CU_ASSERT_NAME_LENGTH	128
#define ZBX_CU_ASSERT_BUFFER_SIZE	(ZBX_CU_ASSERT_ARGS_LENGTH * 2 + ZBX_CU_ASSERT_NAME_LENGTH + 16)

struct mallinfo	zbx_cu_minfo;

static char	zbx_cu_assert_args_buffer[ZBX_CU_ASSERT_BUFFER_SIZE];

const char	*zbx_cu_assert_args_str(const char *assert_name, const char *expression1, const char *actual,
		const char *expression2, const char *expected)
{
	size_t	offset = 0;

	offset = zbx_snprintf(zbx_cu_assert_args_buffer, ZBX_CU_ASSERT_NAME_LENGTH + 1, "%s(", assert_name);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 2, "%s=\"%s\", ",
			expression1, actual);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 1, "%s=\"%s\")",
			expression2, expected);

	return zbx_cu_assert_args_buffer;
}

const char	*zbx_cu_assert_args_ui64(const char *assert_name, const char *expression1, zbx_uint64_t actual,
		const char *expression2, zbx_uint64_t expected)
{
	size_t	offset = 0;

	offset = zbx_snprintf(zbx_cu_assert_args_buffer, ZBX_CU_ASSERT_NAME_LENGTH + 1, "%s(", assert_name);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 2, "%s=" ZBX_FS_UI64 ", ",
			expression1, actual);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 1, "%s=" ZBX_FS_UI64  ")",
			expression2, expected);

	return zbx_cu_assert_args_buffer;
}

const char	*zbx_cu_assert_args_dbl(const char *assert_name, const char *expression1, double actual,
		const char *expression2, double expected)
{
	size_t	offset = 0;

	offset = zbx_snprintf(zbx_cu_assert_args_buffer, ZBX_CU_ASSERT_NAME_LENGTH + 1, "%s(", assert_name);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 2, "%s=" ZBX_FS_DBL ", ",
			expression1, actual);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 1, "%s=" ZBX_FS_DBL  ")",
			expression2, expected);

	return zbx_cu_assert_args_buffer;
}

const char	*zbx_cu_assert_args_int(const char *assert_name, const char *expression1, int actual,
		const char *expression2, int expected)
{
	size_t	offset = 0;

	offset = zbx_snprintf(zbx_cu_assert_args_buffer, ZBX_CU_ASSERT_NAME_LENGTH + 1, "%s(", assert_name);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 2, "%s=%d, ",
			expression1, actual);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 1, "%s=%d)",
			expression2, expected);

	return zbx_cu_assert_args_buffer;
}
