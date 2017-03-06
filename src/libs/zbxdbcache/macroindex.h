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

#ifndef ZABBIX_MACROINDEX_H
#define ZABBIX_MACROINDEX_H

#include "common.h"
#include "zbxalgo.h"

typedef struct
{
	zbx_hashset_t	macros;
	zbx_hashset_t	htmpls;

	zbx_mem_malloc_func_t	malloc_func;
	zbx_mem_realloc_func_t	realloc_func;
	zbx_mem_free_func_t	free_func;
}
zbx_macro_index_t;

typedef struct zbx_dc_macro zbx_dc_macro_t;

void	zbx_mi_init(zbx_macro_index_t *index);
void	zbx_mi_init_ext(zbx_macro_index_t *index, zbx_mem_malloc_func_t malloc_func,
		zbx_mem_realloc_func_t realloc_func, zbx_mem_free_func_t free_func);
void	zbx_mi_clear(zbx_macro_index_t *index);

void	zbx_mi_add_host_template(zbx_macro_index_t *index, zbx_uint64_t hostid, zbx_uint64_t templateid,
		zbx_vector_ptr_t *sort);
void	zbx_mi_remove_host_template(zbx_macro_index_t *index, zbx_uint64_t hostid, zbx_uint64_t templateid,
		zbx_vector_ptr_t *sort);
void	zbx_mi_sort_host_templates(zbx_macro_index_t *index, zbx_vector_ptr_t *sort);
void	zbx_mi_get_host_templates(zbx_macro_index_t *index, zbx_hashset_t *htmpls);

void	zbx_mi_add_macro(zbx_macro_index_t *index, zbx_dc_macro_t *macro);
void	zbx_mi_remove_macro(zbx_macro_index_t *index, zbx_dc_macro_t *macro);

void	zbx_mi_get_macro_value(zbx_macro_index_t *index, const zbx_uint64_t *hostids, int hostids_num,
		const char *name, const char *context, char **replace_to);


#endif /* ZABBIX_USERMACRO_H */
