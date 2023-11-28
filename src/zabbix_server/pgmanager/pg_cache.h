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

#define ZBX_PG_PROXY_STATUS_UNKNOWN	0
#define ZBX_PG_PROXY_STATUS_OFFLINE	1
#define ZBX_PG_PROXY_STATUS_ONLINE	2

#define ZBX_PG_GROUP_STATUS_UNKNOWN	0
#define ZBX_PG_GROUP_STATUS_OFFLINE	1
#define ZBX_PG_GROUP_STATUS_RECOVERY	2
#define ZBX_PG_GROUP_STATUS_ONLINE	3
#define ZBX_PG_GROUP_STATUS_DECAY	4

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxyid;
	zbx_uint64_t	revision;
}
zbx_pg_host_t;

typedef struct
{
	int				startup_time;
	zbx_hashset_t			groups;
	zbx_hashset_t			map;
	zbx_hashset_t			map_updates;
	zbx_hashset_t			proxies;
	zbx_uint64_t			group_revision;
	zbx_vector_pg_group_ptr_t	group_updates;
	pthread_mutex_t			lock;
	zbx_uint64_t			map_revision;
}
zbx_pg_cache_t;

void	pg_group_clear(zbx_pg_group_t *group);

void	pg_cache_init(zbx_pg_cache_t *cache, zbx_uint64_t map_revision);
void	pg_cache_destroy(zbx_pg_cache_t *cache);

void	pg_cache_queue_update(zbx_pg_cache_t *cache, zbx_pg_group_t *group);
void	pg_cache_process_updates(zbx_pg_cache_t *cache);
zbx_pg_proxy_t	*pg_cache_group_add_proxy(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t proxyid, int clock);
void	pg_cache_group_remove_proxy(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t proxyid);

void	pg_cache_group_remove_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid);
void	pg_cache_group_add_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid);

void	pg_cache_lock(zbx_pg_cache_t *cache);
void	pg_cache_unlock(zbx_pg_cache_t *cache);

/* WDN: debug */
void	pg_cache_dump_group(zbx_pg_group_t *group);
void	pg_cache_dump(zbx_pg_cache_t *cache);

#endif
