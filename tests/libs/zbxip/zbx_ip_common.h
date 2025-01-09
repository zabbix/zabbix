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

#ifndef ZBX_IP_COMMON
#define ZBX_IP_COMMON

#include "zbxmockdata.h"

int	compare_str_vectors(const zbx_vector_str_t *v1, const zbx_vector_str_t *v2);
void	extract_yaml_values(const char *path, zbx_vector_str_t *values);

#endif
