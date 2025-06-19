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

#ifndef ZBX_ALGO_COMMON
#define ZBX_ALGO_COMMON

#include "zbxmockdata.h"

void	vector_to_list(zbx_list_t *list, zbx_vector_ptr_t values);
void	extract_yaml_values_uint64(const char *path, zbx_vector_uint64_t *values);
int	binary_heap_elem_compare(const void *d1, const void *d2);
void	dump_binary_heap(const char *name, const zbx_binary_heap_t *heap_in);
int	binary_heaps_are_same(const zbx_binary_heap_t *heap1_in, const zbx_binary_heap_t *heap2_in);
void	extract_binary_heap_from_yaml_int(zbx_binary_heap_t *heap, zbx_mock_handle_t *handle);

#endif
