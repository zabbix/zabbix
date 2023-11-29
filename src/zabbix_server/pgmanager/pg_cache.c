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

ZBX_VECTOR_IMPL(pg_update, zbx_pg_update_t)

static int	pg_host_compare_by_hostid(const void *d1, const void *d2)
{
	const zbx_pg_host_t	*h1 = *(const zbx_pg_host_t * const *)d1;
	const zbx_pg_host_t	*h2 = *(const zbx_pg_host_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->hostid, h2->hostid);

	return 0;
}

static int	pg_host_compare_by_revision(const void *d1, const void *d2)
{
	const zbx_pg_host_t	*h1 = *(const zbx_pg_host_t * const *)d1;
	const zbx_pg_host_t	*h2 = *(const zbx_pg_host_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->revision, h2->revision);

	return 0;
}

void	pg_cache_lock(zbx_pg_cache_t *cache)
{
	pthread_mutex_lock(&cache->lock);
}

void	pg_cache_unlock(zbx_pg_cache_t *cache)
{
	pthread_mutex_unlock(&cache->lock);
}

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
	zbx_vector_pg_host_ptr_destroy(&proxy->hosts);
}

void	pg_cache_init(zbx_pg_cache_t *cache, zbx_uint64_t map_revision)
{
	zbx_hashset_create(&cache->groups, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	cache->groups_revision = 0;

	zbx_vector_pg_group_ptr_create(&cache->updates);

	zbx_hashset_create(&cache->proxies, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&cache->links, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create(&cache->new_links, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	pthread_mutex_init(&cache->lock, NULL);

	cache->startup_time = time(NULL);
	cache->links_revision = map_revision;
}

void	pg_cache_destroy(zbx_pg_cache_t *cache)
{
	pthread_mutex_destroy(&cache->lock);

	zbx_hashset_destroy(&cache->new_links);
	zbx_hashset_destroy(&cache->links);

	zbx_vector_pg_group_ptr_destroy(&cache->updates);

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


void	pg_cache_queue_group_update(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
	for (int i = 0; i < cache->updates.values_num; i++)
	{
		if (cache->updates.values[i] == group)
			return;
	}

	zbx_vector_pg_group_ptr_append(&cache->updates, group);
}

static void	pg_cache_add_link(zbx_pg_cache_t *cache, zbx_uint64_t hostid, zbx_uint64_t proxyid)
{
	zbx_pg_host_t	*host;

	if (NULL == (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->new_links, &hostid)))
	{
		zbx_pg_host_t	host_local = {.hostid = hostid, .proxyid = proxyid};

		zbx_hashset_insert(&cache->new_links, &host_local, sizeof(host_local));
	}
	else
		host->proxyid = proxyid;
}

zbx_pg_proxy_t	*pg_cache_group_add_proxy(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t proxyid, int clock)
{
	zbx_pg_proxy_t	*proxy, proxy_local = {.proxyid = proxyid, .group = group, .firstaccess = clock};

	proxy = (zbx_pg_proxy_t *)zbx_hashset_insert(&cache->proxies, &proxy_local, sizeof(proxy_local));
	zbx_vector_pg_host_ptr_create(&proxy->hosts);

	zbx_vector_pg_proxy_ptr_append(&group->proxies, proxy);

	/* non zero clock will be during initial setup loading from db, */
	/* which will queue all groups for update anyway                */
	if (0 == clock)
		pg_cache_queue_group_update(cache, group);

	return proxy;
}

void	pg_cache_group_remove_proxy(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t proxyid)
{
	zbx_pg_proxy_t	*proxy;
	int		i;

	if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &proxyid)))
		return;

	for (i = 0; i < proxy->hosts.values_num; i++)
	{
		zbx_vector_uint64_append(&group->new_hostids, proxy->hosts.values[i]->hostid);
		pg_cache_add_link(cache, proxy->hosts.values[i]->hostid, 0);
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

	pg_cache_queue_group_update(cache, group);
}

void	pg_cache_group_remove_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid)
{
	int		i;
	zbx_pg_host_t	*host;

	if (FAIL != (i = zbx_vector_uint64_search(&group->hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(&group->hostids, i);

	if (FAIL != (i = zbx_vector_uint64_search(&group->new_hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(&group->new_hostids, i);

	if (NULL != (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->links, &hostid)))
	{
		zbx_pg_proxy_t	*proxy;

		if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)))
		{
			zbx_pg_host_t	host_local = {.hostid = hostid};

			if (FAIL != (i = zbx_vector_pg_host_ptr_search(&proxy->hosts, &host_local,
					pg_host_compare_by_hostid)))
			{
				zbx_vector_pg_host_ptr_remove_noorder(&proxy->hosts, i);
			}
		}

		pg_cache_add_link(cache, hostid, 0);
	}

	pg_cache_queue_group_update(cache, group);
}

void	pg_cache_group_add_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid)
{
	int	i;

	if (FAIL == (i = zbx_vector_uint64_search(&group->hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		zbx_vector_uint64_append(&group->hostids, hostid);
		zbx_vector_uint64_append(&group->new_hostids, hostid);
	}

	pg_cache_queue_group_update(cache, group);
}

static void	pg_cache_reassign_hosts(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
#define PG_HOSTS_GAP_LIMIT	1

	int			min_hosts = INT32_MAX, max_hosts = 0, online_num = 0, hosts_num = 0;
	zbx_vector_uint64_t	hostids;

	zbx_vector_uint64_create(&hostids);

	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATUS_ONLINE == proxy->status)
		{
			if (proxy->hosts.values_num > max_hosts)
				max_hosts = proxy->hosts.values_num;

			if (proxy->hosts.values_num < min_hosts)
				min_hosts = proxy->hosts.values_num;

			online_num++;
			hosts_num += proxy->hosts.values_num;
		}
		else
		{
			for (int j = 0; j < proxy->hosts.values_num; j++)
				zbx_vector_uint64_append(&hostids, proxy->hosts.values[j]->hostid);

			zbx_vector_pg_host_ptr_clear(&proxy->hosts);
		}
	}

	if (max_hosts - min_hosts >= PG_HOSTS_GAP_LIMIT)
	{
		int	hosts_num_avg = (hosts_num + hostids.values_num + online_num - 1) / online_num;

		for (int i = 0; i < group->proxies.values_num; i++)
		{
			zbx_pg_proxy_t	*proxy = group->proxies.values[i];

			if (proxy->hosts.values_num > hosts_num_avg)
			{
				zbx_vector_pg_host_ptr_sort(&proxy->hosts, pg_host_compare_by_revision);

				while (proxy->hosts.values_num > hosts_num_avg)
				{
					int	last = proxy->hosts.values_num - 1;

					zbx_vector_uint64_append(&hostids, proxy->hosts.values[last]->hostid);
					zbx_vector_pg_host_ptr_remove_noorder(&proxy->hosts, last);
				}
			}
			else
			{
				while (proxy->hosts.values_num > hosts_num_avg && 0 != hostids.values_num)
				{
					int	last = hostids.values_num - 1;

					pg_cache_add_link(cache, hostids.values[last], proxy->proxyid);
					zbx_vector_uint64_remove_noorder(&hostids, last);
				}
			}
		}
	}

	zbx_vector_uint64_destroy(&hostids);

#undef PG_HOSTS_GAP_LIMIT
}

void	pg_cache_get_updates(zbx_pg_cache_t *cache, zbx_vector_pg_update_t *groups, zbx_vector_pg_host_t *hosts_new,
		zbx_vector_pg_host_t *hosts_mod, zbx_vector_pg_host_t *hosts_del)
{
	pg_cache_lock(cache);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groups:%d", __func__, cache->updates.values_num);

	for (int i = 0; i < cache->updates.values_num; i++)
	{
		zbx_pg_group_t	*group = cache->updates.values[i];

		pg_cache_reassign_hosts(cache, group);

		if (0 != (group->flags & ZBX_PG_GROUP_UPDATE_STATUS))
		{
			zbx_pg_update_t	update = {.proxy_groupid = group->proxy_groupid, .status = group->status};

			zbx_vector_pg_update_append_ptr(groups, &update);
			group->flags = ZBX_PG_GROUP_UPDATE_NONE;
		}
	}

	zbx_vector_pg_group_ptr_clear(&cache->updates);

	if (0 != groups->values_num || 0 != cache->new_links.num_data)
		cache->links_revision++;

	zbx_hashset_iter_t	iter;
	zbx_pg_host_t		*host, *new_host;

	zbx_hashset_iter_reset(&cache->new_links, &iter);
	while (NULL != (new_host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_pg_host_t	host_local = {
				.hostid = new_host->hostid,
				.proxyid = new_host->proxyid,
			};

		if (NULL != (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->links, &new_host->hostid)))
		{
			if (0 == new_host->proxyid)
			{
				zbx_hashset_remove_direct(&cache->links, host);
				zbx_vector_pg_host_append_ptr(hosts_del, &host_local);
			}
			else
			{
				host_local.hostproxyid = host->hostproxyid;
				zbx_vector_pg_host_append_ptr(hosts_mod, &host_local);
			}
		}
		else
		{
			zbx_vector_pg_host_append_ptr(hosts_new, &host_local);
			host = (zbx_pg_host_t *)zbx_hashset_insert(&cache->links, &host_local, sizeof(host_local));
		}

		if (0 != new_host->proxyid)
		{
			zbx_pg_proxy_t	*proxy;

			if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)))
				zbx_vector_pg_host_ptr_append(&proxy->hosts, host);
		}
	}

	zbx_hashset_clear(&cache->new_links);

	pg_cache_unlock(cache);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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
	for (int i = 0; i < proxy->hosts.values_num; i++)
		zabbix_log(LOG_LEVEL_DEBUG, "        " ZBX_FS_UI64, proxy->hosts.values[i]->hostid);

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

	zbx_hashset_iter_reset(&cache->links, &iter);
	while (NULL != (host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_host(host);

	zabbix_log(LOG_LEVEL_DEBUG, "MAP UPDATES:");

	zbx_hashset_iter_reset(&cache->new_links, &iter);
	while (NULL != (host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_host(host);

}

