/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_LLD_H
#define ZABBIX_LLD_H

#include "common.h"
#include "zbxjson.h"
#include "zbxalgo.h"

typedef struct
{
	zbx_uint64_t	parent_itemid;
	zbx_uint64_t	itemid;		/* the item, created by the item prototype */
}
zbx_lld_item_link_t;

typedef struct
{
	struct zbx_json_parse	jp_row;
	zbx_vector_ptr_t	item_links;	/* the list of item prototypes */
	zbx_vector_ptr_t	overrides;
}
zbx_lld_row_t;

void	lld_field_str_rollback(char **field, char **field_orig, zbx_uint64_t *flags, zbx_uint64_t flag);
void	lld_field_uint64_rollback(zbx_uint64_t *field, zbx_uint64_t *field_orig, zbx_uint64_t *flags,
		zbx_uint64_t flag);

void	lld_override_item(const zbx_vector_ptr_t *overrides, const char *name, const char **delay,
		const char **history, const char **trends, unsigned char *status, unsigned char *discover);
void	lld_override_trigger(const zbx_vector_ptr_t *overrides, const char *name, unsigned char *severity,
		zbx_vector_ptr_pair_t *override_tags, unsigned char *status, unsigned char *discover);
void	lld_override_host(const zbx_vector_ptr_t *overrides, const char *name, zbx_vector_uint64_t *lnk_templateids,
		signed char *inventory_mode, unsigned char *status, unsigned char *discover);
void	lld_override_graph(const zbx_vector_ptr_t *overrides, const char *name, unsigned char *discover);

int	lld_validate_item_override_no_discover(const zbx_vector_ptr_t *overrides, const char *name,
		unsigned char override_default);

int	lld_update_items(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *lld_rows,
		const zbx_vector_ptr_t *lld_macro_paths, char **error, int lifetime, int lastcheck);

void	lld_item_links_sort(zbx_vector_ptr_t *lld_rows);

int	lld_update_triggers(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, const zbx_vector_ptr_t *lld_rows,
		const zbx_vector_ptr_t *lld_macro_paths, char **error, int lifetime, int lastcheck);

int	lld_update_graphs(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, const zbx_vector_ptr_t *lld_rows,
		const zbx_vector_ptr_t *lld_macro_paths, char **error, int lifetime, int lastcheck);

void	lld_update_hosts(zbx_uint64_t lld_ruleid, const zbx_vector_ptr_t *lld_rows,
		const zbx_vector_ptr_t *lld_macro_paths, char **error, int lifetime, int lastcheck);

int	lld_end_of_life(int lastcheck, int lifetime);

typedef void	(*delete_ids_f)(zbx_vector_uint64_t *ids);
typedef void	(*get_object_info_f)(const void *object, zbx_uint64_t *id, int *discovered, int *lastcheck,
		int *ts_delete);
void	lld_remove_lost_objects(const char *table, const char *id_name, const zbx_vector_ptr_t *objects,
		int lifetime, int lastcheck, delete_ids_f cb, get_object_info_f cb_info);

#endif
