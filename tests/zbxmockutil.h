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

#ifndef ZABBIX_MOCK_UTIL_H
#define ZABBIX_MOCK_UTIL_H

#include "zbxmockdata.h"
#include "zbxalgo.h"

const char	*zbx_mock_get_parameter_string(const char *path);
const char	*zbx_mock_get_optional_parameter_string(const char *path);
const char	*zbx_mock_get_object_member_string(zbx_mock_handle_t object, const char *name);

zbx_mock_handle_t	zbx_mock_get_parameter_handle(const char *path);
zbx_mock_handle_t	zbx_mock_get_object_member_handle(zbx_mock_handle_t object, const char *name);

zbx_uint64_t	zbx_mock_get_parameter_uint64(const char *path);
int		zbx_mock_get_parameter_int(const char *path);
zbx_uint64_t	zbx_mock_get_object_member_uint64(zbx_mock_handle_t object, const char *name);
zbx_uint32_t	zbx_mock_get_parameter_uint32(const char *path);

double	zbx_mock_get_parameter_float(const char *path);
double	zbx_mock_get_object_member_float(zbx_mock_handle_t object, const char *name);
int	zbx_mock_get_object_member_int(zbx_mock_handle_t object, const char *name);

int	zbx_mock_str_to_return_code(const char *str);
unsigned char	zbx_mock_str_to_value_type(const char *str);
unsigned char	zbx_mock_str_to_variant(const char *str);
void	zbx_mock_str_to_token_type(const char *str, int *out);
void	zbx_mock_str_to_token_search(const char *str, int *out);
int	zbx_mock_str_to_item_type(const char *str);
int	zbx_mock_str_to_family(const char *str);

void	zbx_mock_extract_yaml_values_str(const char *path, zbx_vector_str_t *values);
void	zbx_mock_extract_yaml_values_ptr (zbx_mock_handle_t hdata, zbx_vector_ptr_t *values);

#endif
