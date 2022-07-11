/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_DIAG_H
#define ZABBIX_DIAG_H

#include "zbxjson.h"
#include "zbxalgo.h"
#include "memalloc.h"

#define ZBX_DIAG_SECTION_MAX	64
#define ZBX_DIAG_FIELD_MAX	64

#define ZBX_DIAG_HISTORYCACHE_ITEMS		0x00000001
#define ZBX_DIAG_HISTORYCACHE_VALUES		0x00000002
#define ZBX_DIAG_HISTORYCACHE_MEMORY_DATA	0x00000004
#define ZBX_DIAG_HISTORYCACHE_MEMORY_INDEX	0x00000008

#define ZBX_DIAG_HISTORYCACHE_SIMPLE	(ZBX_DIAG_HISTORYCACHE_ITEMS | \
					ZBX_DIAG_HISTORYCACHE_VALUES)

#define ZBX_DIAG_HISTORYCACHE_MEMORY	(ZBX_DIAG_HISTORYCACHE_MEMORY_DATA | \
					ZBX_DIAG_HISTORYCACHE_MEMORY_INDEX)

#define ZBX_DIAG_VALUECACHE_ITEMS		0x00000001
#define ZBX_DIAG_VALUECACHE_VALUES		0x00000002
#define ZBX_DIAG_VALUECACHE_MODE		0x00000004
#define ZBX_DIAG_VALUECACHE_MEMORY		0x00000008

#define ZBX_DIAG_VALUECACHE_SIMPLE	(ZBX_DIAG_VALUECACHE_ITEMS | \
					ZBX_DIAG_VALUECACHE_VALUES | \
					ZBX_DIAG_VALUECACHE_MODE)

#define ZBX_DIAG_PREPROC_VALUES			0x00000001
#define ZBX_DIAG_PREPROC_VALUES_PREPROC		0x00000002

#define ZBX_DIAG_PREPROC_SIMPLE		(ZBX_DIAG_PREPROC_VALUES | \
					ZBX_DIAG_PREPROC_VALUES_PREPROC)

#define ZBX_DIAG_LLD_RULES		0x00000001
#define ZBX_DIAG_LLD_VALUES		0x00000002

#define ZBX_DIAG_LLD_SIMPLE		(ZBX_DIAG_LLD_RULES | \
					ZBX_DIAG_LLD_VALUES)

#define ZBX_DIAG_ALERTING_ALERTS	0x00000001

#define ZBX_DIAG_ALERTING_SIMPLE	(ZBX_DIAG_ALERTING_ALERTS)

typedef struct
{
	char		*name;
	zbx_uint64_t	value;
}
zbx_diag_map_t;

void	diag_map_free(zbx_diag_map_t *map);

int	diag_parse_request(const struct zbx_json_parse *jp, const zbx_diag_map_t *field_map, zbx_uint64_t *field_mask,
		zbx_vector_ptr_t *top_views, char **error);

void	diag_add_mem_stats(struct zbx_json *json, const char *name, const zbx_mem_stats_t *stats);

int	diag_add_section_info(const char *section, const struct zbx_json_parse *jp, struct zbx_json *json, char **error);

int	diag_add_historycache_info(const struct zbx_json_parse *jp, struct zbx_json *json, char **error);

int	diag_add_preproc_info(const struct zbx_json_parse *jp, struct zbx_json *json, char **error);
void	diag_add_locks_info(struct zbx_json *json);

#endif
