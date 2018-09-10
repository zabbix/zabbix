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

#ifndef ZABBIX_MOCK_DATA_H
#define ZABBIX_MOCK_DATA_H

#include "common.h"

int	zbx_mock_data_init(void **state);
int	zbx_mock_data_free(void **state);

typedef int	zbx_mock_handle_t;

typedef enum
{
	ZBX_MOCK_SUCCESS,
	ZBX_MOCK_INVALID_HANDLE,
	ZBX_MOCK_NO_PARAMETER,
	ZBX_MOCK_NO_EXIT_CODE,
	ZBX_MOCK_NOT_AN_OBJECT,
	ZBX_MOCK_NO_SUCH_MEMBER,
	ZBX_MOCK_NOT_A_VECTOR,
	ZBX_MOCK_END_OF_VECTOR,
	ZBX_MOCK_NOT_A_STRING,
	ZBX_MOCK_INTERNAL_ERROR,
	ZBX_MOCK_INVALID_YAML_PATH,
	ZBX_MOCK_NOT_A_TIMESTAMP,
	ZBX_MOCK_NOT_ENOUGH_MEMORY,
	ZBX_MOCK_NOT_A_BINARY,
	ZBX_MOCK_NOT_AN_UINT64
}
zbx_mock_error_t;

const char	*zbx_mock_error_string(zbx_mock_error_t error);

zbx_mock_error_t	zbx_mock_in_parameter(const char *name, zbx_mock_handle_t *parameter);
zbx_mock_error_t	zbx_mock_out_parameter(const char *name, zbx_mock_handle_t *parameter);
zbx_mock_error_t	zbx_mock_db_rows(const char *data_source, zbx_mock_handle_t *rows);
zbx_mock_error_t	zbx_mock_file(const char *path, zbx_mock_handle_t *file);
zbx_mock_error_t	zbx_mock_exit_code(int *status);
zbx_mock_error_t	zbx_mock_object_member(zbx_mock_handle_t object, const char *name, zbx_mock_handle_t *member);
zbx_mock_error_t	zbx_mock_vector_element(zbx_mock_handle_t vector, zbx_mock_handle_t *element);
zbx_mock_error_t	zbx_mock_string(zbx_mock_handle_t string, const char **value);
zbx_mock_error_t	zbx_mock_binary(zbx_mock_handle_t binary, const char **value, size_t *length);
zbx_mock_error_t	zbx_mock_parameter(const char *path, zbx_mock_handle_t *parameter);
zbx_mock_error_t	zbx_mock_uint64(zbx_mock_handle_t object, zbx_uint64_t *value);

/* date/time support */
#define ZBX_MOCK_TIMESTAMP_MAX_LEN	37

zbx_mock_error_t	zbx_strtime_to_timespec(const char *strtime, zbx_timespec_t *ts);
zbx_mock_error_t	zbx_time_to_strtime(time_t timestamp, char *buffer, size_t size);
zbx_mock_error_t	zbx_timespec_to_strtime(const zbx_timespec_t *ts, char *buffer, size_t size);

#endif	/* ZABBIX_MOCK_DATA_H */
