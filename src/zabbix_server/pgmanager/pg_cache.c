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

	zbx_free(group->name);
	zbx_free(group->failover_delay);
	zbx_free(group->min_online);
}

void	pg_proxy_clear(zbx_pg_proxy_t *proxy)
{
	zbx_vector_pg_host_ptr_destroy(&proxy->hosts);
	zbx_vector_pg_host_destroy(&proxy->deleted_group_hosts);
	zbx_free(proxy->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize proxy group cache                                      *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_init(zbx_pg_cache_t *cache, zbx_uint64_t map_revision)
{
	zbx_hashset_create(&cache->groups, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	cache->group_revision = 0;
	cache->proxy_revision = 0;

	zbx_vector_pg_group_ptr_create(&cache->group_updates);

	zbx_hashset_create(&cache->proxies, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&cache->hostmap, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create(&cache->hostmap_updates, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	pthread_mutex_init(&cache->lock, NULL);

	cache->startup_time = (int)time(NULL);
	cache->hostmap_revision = map_revision;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy proxy group cache                                         *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_destroy(zbx_pg_cache_t *cache)
{
	pthread_mutex_destroy(&cache->lock);

	zbx_hashset_destroy(&cache->hostmap_updates);
	zbx_hashset_destroy(&cache->hostmap);

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

/******************************************************************************
 *                                                                            *
 * Purpose: queue proxy group for host-proxy updates                          *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_queue_group_update(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		if (cache->group_updates.values[i] == group)
			return;
	}

	zbx_vector_pg_group_ptr_append(&cache->group_updates, group);
}

/******************************************************************************
 *                                                                            *
 * Purpose: assign proxy to host                                              *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_set_host_proxy(zbx_pg_cache_t *cache, zbx_uint64_t hostid, zbx_uint64_t proxyid)
{
	zbx_pg_host_t	*host;

	if (NULL == (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->hostmap_updates, &hostid)))
	{
		zbx_pg_host_t	host_local = {.hostid = hostid, .proxyid = proxyid};

		zbx_hashset_insert(&cache->hostmap_updates, &host_local, sizeof(host_local));
	}
	else
		host->proxyid = proxyid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove proxy from group                                           *
 *                                                                            *
 * Parameters: cache - [IN] proxy group cache                                 *
 *             group - [IN] target group                                      *
 *             proxy - [IN] proxy to remove                                   *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_group_remove_proxy(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_pg_proxy_t *proxy)
{
	int	i;

	for (i = 0; i < proxy->hosts.values_num; i++)
	{
		zbx_vector_uint64_append(&group->new_hostids, proxy->hosts.values[i]->hostid);
		pg_cache_set_host_proxy(cache, proxy->hosts.values[i]->hostid, 0);
	}

	if (NULL != proxy->group)
	{
		if (FAIL != (i = zbx_vector_pg_proxy_ptr_search(&proxy->group->proxies, proxy,
				ZBX_DEFAULT_PTR_COMPARE_FUNC)))
		{
			zbx_vector_pg_proxy_ptr_remove_noorder(&proxy->group->proxies, i);
		}
	}
}

void	pg_cache_proxy_free(zbx_pg_cache_t *cache, zbx_pg_proxy_t *proxy)
{
	pg_proxy_clear(proxy);
	zbx_hashset_remove_direct(&cache->proxies, proxy);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add host to from group                                            *
 *                                                                            *
 * Parameters: cache  - [IN] proxy group cache                                *
 *             group  - [IN] target group                                     *
 *             hostid - [IN] host identifier                                  *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_group_remove_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_uint64_t hostid)
{
	int		i;
	zbx_pg_host_t	*host;

	if (FAIL != (i = zbx_vector_uint64_search(&group->hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(&group->hostids, i);

	if (FAIL != (i = zbx_vector_uint64_search(&group->new_hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(&group->new_hostids, i);

	if (NULL != (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->hostmap, &hostid)))
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

		pg_cache_set_host_proxy(cache, hostid, 0);
	}

	pg_cache_queue_group_update(cache, group);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove host from group                                            *
 *                                                                            *
 * Parameters: cache  - [IN] proxy group cache                                *
 *             group  - [IN] target group                                     *
 *             hostid - [IN] host identifier                                  *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: remove hosts from group proxies exceeding host limit              *
 *                                                                            *
 * Parameters: group   - [IN] target group                                    *
 *             limit   - [IN] maximum number of hosts per proxy               *
 *             pad_num - [IN] number of proxies with limit padded by 1        *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_group_unassign_excess_hosts(zbx_pg_group_t *group, int limit, int pad_num)
{
	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (proxy->hosts.values_num > limit)
		{
			zbx_vector_pg_host_ptr_sort(&proxy->hosts, pg_host_compare_by_revision);

			while (proxy->hosts.values_num > limit)
			{
				int	last = proxy->hosts.values_num - 1;

				zbx_vector_uint64_append(&group->new_hostids, proxy->hosts.values[last]->hostid);
				zbx_vector_pg_host_ptr_remove_noorder(&proxy->hosts, last);
			}
		}

		if (0 == --pad_num)
			limit--;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: distribute unassigned hosts between proxies                       *
 *                                                                            *
 * Parameters: cache     - [IN] proxy group cache                             *
 *             group     - [IN] target group                                  *
 *             limit     - [IN] maximum number of hosts per proxy             *
 *             hosts_num - [IN] number of hosts per proxy to assign           *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_group_distribute_hosts(zbx_pg_cache_t *cache, zbx_pg_group_t *group, int limit, int hosts_num)
{
	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATUS_ONLINE != proxy->status)
			continue;

		for (int j = 0; j < limit - proxy->hosts.values_num && j < hosts_num; j++)
		{
			if (0 == group->new_hostids.values_num)
				break;

			int	last = group->new_hostids.values_num - 1;

			pg_cache_set_host_proxy(cache, group->new_hostids.values[last], proxy->proxyid);
			zbx_vector_uint64_remove_noorder(&group->new_hostids, last);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: reassign host between online proxies within proxy group           *
 *                                                                            *
 * Parameters: cache  - [IN] proxy group cache                                *
 *             group  - [IN] target group                                     *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_reassign_hosts(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
#define PG_HOSTS_GAP_LIMIT	1

	int	min_hosts = INT32_MAX, max_hosts = 0, online_num = 0, hosts_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() group:%s", group->name, __func__, cache->group_updates.values_num);

	/* find min/max host number of online proxies and remove hosts from offline proxies */
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
				zbx_vector_uint64_append(&group->new_hostids, proxy->hosts.values[j]->hostid);

			zbx_vector_pg_host_ptr_clear(&proxy->hosts);
		}
	}

	/* reassign hosts if necessary */
	if (max_hosts - min_hosts >= PG_HOSTS_GAP_LIMIT || 0 != group->new_hostids.values_num)
	{
		hosts_num += group->new_hostids.values_num;

		int	hosts_num_avg = (hosts_num + online_num - 1) / online_num;

		pg_cache_group_unassign_excess_hosts(group, hosts_num_avg, hosts_num % online_num);

		/* first distribute hosts with lower limit to have even distribution */
		if (0 != hosts_num_avg)
			pg_cache_group_distribute_hosts(cache, group, hosts_num_avg - 1, hosts_num_avg - 1);

		pg_cache_group_distribute_hosts(cache, group, hosts_num_avg, 1);

		group->flags |= ZBX_PG_GROUP_UPDATE_HP_MAP;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

#undef PG_HOSTS_GAP_LIMIT
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply pending changes to local cache and return changeset for     *
 *          database update                                                   *
 *                                                                            *
 * Parameters: cache          - [IN] proxy group cache                        *
 *             groups         - [IN] target groups                            *
 *             hosts_new      - [OUT] new host-proxy links                    *
 *             hosts_mod      - [OUT] modified host-proxy links               *
 *             hosts_del      - [OUT] removed host-proxy links                *
 *             groupids       - [OUT] ids of groups with updated host-proxy   *
 *                                    mapping                                 *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_get_updates(zbx_pg_cache_t *cache, zbx_vector_pg_update_t *groups, zbx_vector_pg_host_t *hosts_new,
		zbx_vector_pg_host_t *hosts_mod, zbx_vector_pg_host_t *hosts_del, zbx_vector_uint64_t *groupids)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groups:%d", __func__, cache->group_updates.values_num);

	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		zbx_pg_group_t	*group = cache->group_updates.values[i];

		if (ZBX_PG_GROUP_STATUS_ONLINE == group->status)
			pg_cache_reassign_hosts(cache, group);

		if (ZBX_PG_GROUP_FLAGS_NONE != group->flags)
		{
			zbx_pg_update_t	update = {
					.proxy_groupid = group->proxy_groupid,
					.status = group->status,
					.flags = group->flags
				};

			zbx_vector_pg_update_append_ptr(groups, &update);
			group->flags = ZBX_PG_GROUP_FLAGS_NONE;
		}
	}

	for (int i = 0; i < cache->group_updates.values_num;)
	{
		zbx_pg_group_t	*group = cache->group_updates.values[i];

		if (ZBX_PG_GROUP_STATUS_RECOVERY == group->status)
		{
			/* recovery status change is also affected by time, not only proxy status changes - */
			/* leave it in updates to periodically check until it changes status                */
			i++;
		}
		else
			zbx_vector_pg_group_ptr_remove_noorder(&cache->group_updates, i);
	}

	if (0 != cache->hostmap_updates.num_data)
		cache->hostmap_revision++;

	zbx_hashset_iter_t		iter;
	zbx_pg_host_t			*host, *new_host;
	zbx_vector_pg_host_ptr_t	added_hosts;
	zbx_pg_proxy_t			*proxy;

	zbx_vector_pg_host_ptr_create(&added_hosts);

	zbx_hashset_iter_reset(&cache->hostmap_updates, &iter);
	while (NULL != (new_host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_pg_host_t	host_local = {
				.hostid = new_host->hostid,
				.proxyid = new_host->proxyid,
				.revision = cache->hostmap_revision
			};

		if (NULL != (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->hostmap, &new_host->hostid)))
		{
			host_local.hostproxyid = host->hostproxyid;

			if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)) &&
					NULL != proxy->group)
			{
				zbx_vector_uint64_append(groupids, proxy->group->proxy_groupid);
			}

			if (0 == new_host->proxyid)
			{
				host_local.proxyid = host->proxyid;
				zbx_hashset_remove_direct(&cache->hostmap, host);
				zbx_vector_pg_host_append_ptr(hosts_del, &host_local);
			}
			else
			{
				zbx_vector_pg_host_append_ptr(hosts_mod, &host_local);
				host->proxyid = new_host->proxyid;
				host->revision = cache->hostmap_revision;
			}
		}
		else
		{
			zbx_vector_pg_host_append_ptr(hosts_new, &host_local);
			host = (zbx_pg_host_t *)zbx_hashset_insert(&cache->hostmap, &host_local, sizeof(host_local));
			zbx_vector_pg_host_ptr_append(&added_hosts, host);
		}

		if (0 != new_host->proxyid)
		{
			if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)))
			{
				zbx_vector_pg_host_ptr_append(&proxy->hosts, host);

				if (NULL != proxy->group)
					zbx_vector_uint64_append(groupids, proxy->group->proxy_groupid);
			}
		}
	}

	/* assign hostproxyid for new hosts-proxy links */

	zbx_uint64_t	hostproxyid;

	hostproxyid = zbx_db_get_maxid_num("host_proxy", added_hosts.values_num);
	for (int i = 0; i < added_hosts.values_num; i++)
	{
		added_hosts.values[i]->hostproxyid = hostproxyid;
		hosts_new->values[i].hostproxyid = hostproxyid++;
	}

	/* add deleted host-group links to every group proxy so they are synced to them */

	time_t	now;

	now = time(NULL);

	for (int i = 0; i < hosts_del->values_num; i++)
	{
		host = &hosts_del->values[i];

		if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)) ||
				NULL == proxy->group)
		{
			continue;
		}

		zbx_pg_group_t	*group = proxy->group;

		for (int j = 0; j < group->proxies.values_num; j++)
		{
			proxy = group->proxies.values[j];

			/* Full hostmap sync will be forced if proxy has not been connected yet   */
			/* or its last connection was more than 24h ago. Until then track deleted */
			/* host-proxy links so they can be synced to proxies.                     */
			if (0 != proxy->sync_time)
			{
				if (SEC_PER_DAY > now - proxy->sync_time)
					zbx_vector_pg_host_append_ptr(&proxy->deleted_group_hosts, host);
				else
					zbx_vector_pg_host_clear(&proxy->deleted_group_hosts);
			}
		}
	}

	zbx_vector_pg_host_ptr_destroy(&added_hosts);

	zbx_hashset_clear(&cache->hostmap_updates);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy group cache from configuration cache                 *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_update_groups(zbx_pg_cache_t *cache)
{
	zbx_uint64_t	old_revision = cache->group_revision;

	if (SUCCEED != zbx_dc_fetch_proxy_groups(&cache->groups, &cache->group_revision))
		return;

	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*group;

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_PG_GROUP_FLAGS_NONE == group->flags)
		{
			pg_group_clear(group);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		group->flags = ZBX_PG_GROUP_FLAGS_NONE;

		if (old_revision < group->revision)
			pg_cache_queue_group_update(cache, group);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update hostmap revisions of the specified proxy groups            *
 *                                                                            *
 * Parameters: cache     - [IN] proxy group cache                             *
 *             hosts_new - [OUT] new host-proxy links                         *
 *             hosts_mod - [OUT] modified host-proxy links                    *
 *             hosts_del - [OUT] removed host-proxy links                     *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_update_hostmap_revision(zbx_pg_cache_t *cache, zbx_vector_uint64_t *groupids)
{
	for (int i = 0; i < groupids->values_num; i++)
	{
		zbx_pg_group_t	*pg;

		if (NULL != (pg = (zbx_pg_group_t *)zbx_hashset_search(&cache->groups, &groupids->values[i])))
			pg->hostmap_revision = cache->hostmap_revision;
	}
}

static void	pg_cache_dump_group(zbx_pg_group_t *group)
{
	zabbix_log(LOG_LEVEL_TRACE, "proxy group:" ZBX_FS_UI64 " %s", group->proxy_groupid, group->name);
	zabbix_log(LOG_LEVEL_TRACE, "    status:%d failover_delay:%s min_online:%s revision:" ZBX_FS_UI64
			" hostmap_revision:" ZBX_FS_UI64,
			group->status, group->failover_delay, group->min_online, group->revision,
			group->hostmap_revision);

	zabbix_log(LOG_LEVEL_TRACE, "    hostids:");
	for (int i = 0; i < group->hostids.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, group->hostids.values[i]);

	zabbix_log(LOG_LEVEL_TRACE, "    new hostids:");
	for (int i = 0; i < group->new_hostids.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, group->new_hostids.values[i]);

	zabbix_log(LOG_LEVEL_TRACE, "    proxies:");
	for (int i = 0; i < group->proxies.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, group->proxies.values[i]->proxyid);
}

static void	pg_cache_dump_proxy(zbx_pg_proxy_t *proxy)
{
	zbx_uint64_t	groupid = 0;

	zabbix_log(LOG_LEVEL_TRACE, "proxy:" ZBX_FS_UI64 " %s", proxy->proxyid, proxy->name);

	if (NULL != proxy->group)
		groupid = proxy->group->proxy_groupid;

	zabbix_log(LOG_LEVEL_TRACE, "    status:%d lastaccess:%d firstaccess:%d groupid:" ZBX_FS_UI64,
			proxy->status, proxy->lastaccess, proxy->firstaccess, groupid);

	zabbix_log(LOG_LEVEL_TRACE, "    hostids:");
	for (int i = 0; i < proxy->hosts.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, proxy->hosts.values[i]->hostid);

	zabbix_log(LOG_LEVEL_TRACE, "    deleted group hostproxyids:");
	for (int i = 0; i < proxy->deleted_group_hosts.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, proxy->deleted_group_hosts.values[i].hostproxyid);

}

static void	pg_cache_dump_host(zbx_pg_host_t *host)
{
	zabbix_log(LOG_LEVEL_TRACE, ZBX_FS_UI64 " -> " ZBX_FS_UI64 " :" ZBX_FS_UI64,
			host->hostid, host->proxyid, host->revision);
}

void	pg_cache_dump(zbx_pg_cache_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*group;
	zbx_pg_proxy_t		*proxy;
	zbx_pg_host_t		*host;

	zabbix_log(LOG_LEVEL_TRACE, "groups:");

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_group(group);

	zabbix_log(LOG_LEVEL_TRACE, "proxies:");

	zbx_hashset_iter_reset(&cache->proxies, &iter);
	while (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_proxy(proxy);

	zabbix_log(LOG_LEVEL_TRACE, "hostmap:");

	zbx_hashset_iter_reset(&cache->hostmap, &iter);
	while (NULL != (host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_host(host);

	zabbix_log(LOG_LEVEL_TRACE, "hostmap updates:");

	zbx_hashset_iter_reset(&cache->hostmap_updates, &iter);
	while (NULL != (host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_host(host);

}

