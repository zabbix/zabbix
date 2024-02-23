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

#define ZBX_PG_PROXY_STATE_UNKNOWN	0
#define ZBX_PG_PROXY_STATE_OFFLINE	1
#define ZBX_PG_PROXY_STATE_ONLINE	2

#define ZBX_PG_GROUP_STATE_UNKNOWN	0
#define ZBX_PG_GROUP_STATE_OFFLINE	1
#define ZBX_PG_GROUP_STATE_RECOVERY	2
#define ZBX_PG_GROUP_STATE_ONLINE	3
#define ZBX_PG_GROUP_STATE_DECAY	4
#define ZBX_PG_GROUP_STATE_DISABLED	5

typedef struct
{
	int				startup_time;

	zbx_hashset_t			groups;
	zbx_vector_pg_group_ptr_t	group_updates;
	zbx_uint64_t			group_revision;
	zbx_uint64_t			proxy_revision;

	zbx_hashset_t			hostmap;
	zbx_hashset_t			hostmap_updates;
	zbx_uint64_t			hostmap_revision;

	zbx_hashset_t			proxies;

	pthread_mutex_t			lock;
}
zbx_pg_cache_t;

typedef struct
{
	zbx_uint64_t	objectid;
	int		state;
	zbx_uint32_t	flags;
}
zbx_pg_update_t;

ZBX_VECTOR_DECL(pg_update, zbx_pg_update_t)

void	pg_proxy_clear(zbx_pg_proxy_t *proxy);
void	pg_group_clear(zbx_pg_group_t *group);

void	pg_cache_init(zbx_pg_cache_t *cache, zbx_uint64_t map_revision);
void	pg_cache_destroy(zbx_pg_cache_t *cache);

void	pg_cache_queue_group_update(zbx_pg_cache_t *cache, zbx_pg_group_t *group);
void	pg_cache_get_updates(zbx_pg_cache_t *cache, zbx_vector_pg_update_t *groups, zbx_vector_pg_update_t *proxies,
		zbx_vector_pg_host_t *hosts_new, zbx_vector_pg_host_t *hosts_mod, zbx_vector_pg_host_t *hosts_del,
		zbx_vector_uint64_t *groupids);
void	pg_cache_group_remove_proxy(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_pg_proxy_t *proxy);
void	pg_cache_proxy_free(zbx_pg_cache_t *cache, zbx_pg_proxy_t *proxy);

void	pg_cache_group_remove_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid);
void	pg_cache_group_add_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid);
void	pg_cache_set_host_proxy(zbx_pg_cache_t *cache, zbx_uint64_t hostid, zbx_uint64_t proxyid);

void	pg_cache_update_groups(zbx_pg_cache_t *cache);
void	pg_cache_update_proxies(zbx_pg_cache_t *cache);
void	pg_cache_update_hostmap_revision(zbx_pg_cache_t *cache, zbx_vector_uint64_t *groupids);

void	pg_cache_lock(zbx_pg_cache_t *cache);
void	pg_cache_unlock(zbx_pg_cache_t *cache);

void	pg_cache_dump(zbx_pg_cache_t *cache);

#endif
