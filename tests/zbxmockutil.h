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

#ifndef ZABBIX_MOCK_UTIL_H
#define ZABBIX_MOCK_UTIL_H

#include "zbxmockdata.h"

const char	*zbx_mock_get_parameter_string(const char *path);
const char	*zbx_mock_get_object_member_string(zbx_mock_handle_t object, const char *name);

zbx_mock_handle_t	zbx_mock_get_parameter_handle(const char *path);
zbx_mock_handle_t	zbx_mock_get_object_member_handle(zbx_mock_handle_t object, const char *name);

zbx_uint64_t	zbx_mock_get_parameter_uint64(const char *path);
zbx_uint64_t	zbx_mock_get_object_member_uint64(zbx_mock_handle_t object, const char *name);

int	zbx_mock_str_to_return_code(const char *str);
unsigned char	zbx_mock_str_to_value_type(const char *str);
int	zbx_mock_str_to_return_code(const char *str);

#endif
