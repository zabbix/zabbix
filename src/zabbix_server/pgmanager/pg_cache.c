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

#include "pg_cache.h"
#include "zbxcacheconfig.h"

void	pg_group_clear(zbx_pg_group_t *group)
{
	for (int i = 0; i < group->proxies.values_num; i++)
		group->proxies.values[i]->group = NULL;

	zbx_vector_pg_proxy_ptr_destroy(&group->proxies);
	zbx_vector_uint64_destroy(&group->hostids);
	zbx_vector_uint64_destroy(&group->new_hostids);
}

void	pg_proxy_clear(zbx_pg_proxy_t *proxy)
{
	zbx_vector_uint64_destroy(&proxy->hostids);
}

void	pg_cache_init(zbx_pg_cache_t *cache, zbx_uint64_t map_revision)
{
	zbx_hashset_create(&cache->groups, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	cache->group_revision = 0;

	zbx_vector_pg_group_ptr_create(&cache->group_updates);

	zbx_hashset_create(&cache->proxies, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create(&cache->map, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&cache->map_updates, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	pthread_mutex_init(&cache->lock, NULL);

	cache->startup_time = time(NULL);
	cache->map_revision = map_revision;
}

void	pg_cache_destroy(zbx_pg_cache_t *cache)
{
	pthread_mutex_destroy(&cache->lock);

	zbx_hashset_destroy(&cache->map_updates);
	zbx_hashset_destroy(&cache->map);

	zbx_vector_pg_group_ptr_destroy(&cache->group_updates);

	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*group;

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
		pg_group_clear(group);

	zbx_hashset_destroy(&cache->groups);

	zbx_pg_proxy_t	*proxy;


	zbx_hashset_iter_reset(&cache->proxies, &iter);
	while (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_iter_next(&iter)))
		pg_proxy_clear(proxy);

	zbx_hashset_destroy(&cache->proxies);
}


void	pg_cache_queue_update(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		if (cache->group_updates.values[i] == group)
			return;
	}

	zbx_vector_pg_group_ptr_append(&cache->group_updates, group);
}

static void	pg_cache_queue_mapping(zbx_pg_cache_t *cache, zbx_uint64_t hostid, zbx_uint64_t proxyid)
{
	zbx_pg_host_t	*host;

	if (NULL == (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->map_updates, &hostid)))
	{
		zbx_pg_host_t	host_local = {.hostid = hostid, .proxyid = proxyid};

		zbx_hashset_insert(&cache->map_updates, &host_local, sizeof(host_local));
	}
	else
		host->proxyid = proxyid;
}

void	pg_cache_process_updates(zbx_pg_cache_t *cache)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groups:%d", __func__, cache->group_updates.values_num);

	/* WDN: debug */
	pg_cache_dump(cache);

	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		/* TODO: host reassignment implementation */
	}

	zbx_vector_pg_group_ptr_clear(&cache->group_updates);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

zbx_pg_proxy_t	*pg_cache_group_add_proxy(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t proxyid, int clock)
{
	zbx_pg_proxy_t	*proxy, proxy_local = {.proxyid = proxyid, .group = group, .firstaccess = clock};

	proxy = (zbx_pg_proxy_t *)zbx_hashset_insert(&cache->proxies, &proxy_local, sizeof(proxy_local));

	zbx_vector_uint64_create(&proxy->hostids);

	zbx_vector_pg_proxy_ptr_append(&group->proxies, proxy);

	/* non zero clock will be during initial setup loading from db, */
	/* which will queue all groups for update anyway                */
	if (0 == clock)
		pg_cache_queue_update(cache, group);

	return proxy;
}

void	pg_cache_group_remove_proxy(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t proxyid)
{
	zbx_pg_proxy_t	*proxy;
	int		i;

	if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &proxyid)))
		return;

	for (i = 0; i < proxy->hostids.values_num; i++)
	{
		zbx_vector_uint64_append(&group->new_hostids, proxy->hostids.values[i]);
		pg_cache_queue_mapping(cache, proxy->hostids.values[i], 0);
	}

	if (NULL != proxy->group)
	{
		if (FAIL != (i = zbx_vector_pg_proxy_ptr_search(&proxy->group->proxies, proxy,
				ZBX_DEFAULT_PTR_COMPARE_FUNC)))
		{
			zbx_vector_pg_proxy_ptr_remove_noorder(&proxy->group->proxies, i);
		}
	}

	pg_proxy_clear(proxy);
	zbx_hashset_remove_direct(&cache->proxies, proxy);

	pg_cache_queue_update(cache, group);
}

void	pg_cache_group_remove_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid)
{
	int		i;
	zbx_pg_host_t	*host;

	if (FAIL != (i = zbx_vector_uint64_search(&group->hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(&group->hostids, i);

	if (FAIL != (i = zbx_vector_uint64_search(&group->new_hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(&group->new_hostids, i);

	if (NULL != (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->map, &hostid)))
	{
		zbx_pg_proxy_t	*proxy;

		if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)))
		{
			if (FAIL != (i = zbx_vector_uint64_search(&proxy->hostids, hostid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_vector_uint64_remove_noorder(&proxy->hostids, i);
			}
		}

		pg_cache_queue_mapping(cache, hostid, 0);
	}

	pg_cache_queue_update(cache, group);
}

void	pg_cache_group_add_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid)
{
	int	i;

	if (FAIL == (i = zbx_vector_uint64_search(&group->hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		zbx_vector_uint64_append(&group->hostids, hostid);
		zbx_vector_uint64_append(&group->new_hostids, hostid);
	}

	pg_cache_queue_update(cache, group);
}

void	pg_cache_lock(zbx_pg_cache_t *cache)
{
	pthread_mutex_lock(&cache->lock);
}

void	pg_cache_unlock(zbx_pg_cache_t *cache)
{
	pthread_mutex_unlock(&cache->lock);
}


// WDN: debug
void	pg_cache_dump_group(zbx_pg_group_t *group)
{
	zabbix_log(LOG_LEVEL_DEBUG, "proxy group:" ZBX_FS_UI64, group->proxy_groupid);
	zabbix_log(LOG_LEVEL_DEBUG, "    status:%d failover_delay:%d min_online:%d revision:" ZBX_FS_UI64,
			group->status, group->failover_delay, group->min_online, group->revision);

	zabbix_log(LOG_LEVEL_DEBUG, "    hostids:");
	for (int i = 0; i < group->hostids.values_num; i++)
		zabbix_log(LOG_LEVEL_DEBUG, "        " ZBX_FS_UI64, group->hostids.values[i]);

	zabbix_log(LOG_LEVEL_DEBUG, "    new hostids:");
	for (int i = 0; i < group->new_hostids.values_num; i++)
		zabbix_log(LOG_LEVEL_DEBUG, "        " ZBX_FS_UI64, group->new_hostids.values[i]);

	zabbix_log(LOG_LEVEL_DEBUG, "    proxies:");
	for (int i = 0; i < group->proxies.values_num; i++)
		zabbix_log(LOG_LEVEL_DEBUG, "        " ZBX_FS_UI64, group->proxies.values[i]->proxyid);
}

void	pg_cache_dump_proxy(zbx_pg_proxy_t *proxy)
{
	zbx_uint64_t	groupid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "proxy:" ZBX_FS_UI64, proxy->proxyid);

	if (NULL != proxy->group)
		groupid = proxy->group->proxy_groupid;

	zabbix_log(LOG_LEVEL_DEBUG, "    status:%d firstaccess:%d groupid:" ZBX_FS_UI64,
			proxy->status, proxy->firstaccess, groupid);

	zabbix_log(LOG_LEVEL_DEBUG, "    hostids:");
	for (int i = 0; i < proxy->hostids.values_num; i++)
		zabbix_log(LOG_LEVEL_DEBUG, "        " ZBX_FS_UI64, proxy->hostids.values[i]);

}

void	pg_cache_dump_host(zbx_pg_host_t *host)
{
	zabbix_log(LOG_LEVEL_DEBUG, ZBX_FS_UI64 " -> " ZBX_FS_UI64 " :" ZBX_FS_UI64,
			host->hostid, host->proxyid, host->revision);
}

void	pg_cache_dump(zbx_pg_cache_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*group;
	zbx_pg_proxy_t		*proxy;
	zbx_pg_host_t		*host;

	zabbix_log(LOG_LEVEL_DEBUG, "GROUPS:");

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_group(group);

	zabbix_log(LOG_LEVEL_DEBUG, "PROXIES:");

	zbx_hashset_iter_reset(&cache->proxies, &iter);
	while (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_proxy(proxy);

	zabbix_log(LOG_LEVEL_DEBUG, "MAP:");

	zbx_hashset_iter_reset(&cache->map, &iter);
	while (NULL != (host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_host(host);

	zabbix_log(LOG_LEVEL_DEBUG, "MAP UPDATES:");

	zbx_hashset_iter_reset(&cache->map_updates, &iter);
	while (NULL != (host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_host(host);

}

