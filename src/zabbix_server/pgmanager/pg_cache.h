/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_PG_CACHE_H
#define ZABBIX_PG_CACHE_H

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"

typedef struct
{
	zbx_hashset_t			groups;
	zbx_uint64_t			group_revision;
	zbx_vector_pg_group_ptr_t	updates;
	pthread_mutex_t			lock;
}
zbx_pg_cache_t;

void	pg_group_clear(zbx_pg_group_t *group);

void	pg_cache_init(zbx_pg_cache_t *cache);
void	pg_cache_destroy(zbx_pg_cache_t *cache);

void	pg_cache_queue_update(zbx_pg_cache_t *cache, zbx_pg_group_t *group);
void	pg_cache_process_updates(zbx_pg_cache_t *cache);

/* WDN: debug */
void	pg_cache_dump_group(zbx_pg_group_t *group);

#endif
