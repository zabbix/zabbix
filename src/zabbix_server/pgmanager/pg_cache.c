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

#define PG_GROUP_UNBALANCE_FACTOR	2
#define PG_GROUP_UNBALANCE_LIMIT	10

#define PG_GROUP_UNBALANCED_YES		1
#define PG_GROUP_UNBALANCED_NO		0

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
	zbx_vector_uint64_destroy(&group->unassigned_hostids);

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
 * Purpose: add proxy group for standalone proxies                            *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_add_standalone_proxy_group(zbx_pg_cache_t *cache)
{
	zbx_pg_group_t	*group, group_local = {.proxy_groupid = 0};

	group = (zbx_pg_group_t *)zbx_hashset_insert(&cache->groups, &group_local, sizeof(group_local));
	zbx_vector_pg_proxy_ptr_create(&group->proxies);
	zbx_vector_uint64_create(&group->hostids);
	zbx_vector_uint64_create(&group->unassigned_hostids);
	group->state = ZBX_PG_GROUP_STATE_DISABLED;
	group->min_online = zbx_strdup(NULL, "0");
	group->failover_delay = zbx_dsprintf(NULL, ZBX_PG_DEFAULT_FAILOVER_DELAY_STR);
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

	pg_cache_add_standalone_proxy_group(cache);
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
		zbx_vector_uint64_append(&group->unassigned_hostids, proxy->hosts.values[i]->hostid);
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

	if (FAIL != (i = zbx_vector_uint64_search(&group->unassigned_hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(&group->unassigned_hostids, i);

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
		zbx_vector_uint64_append(&group->unassigned_hostids, hostid);
	}

	pg_cache_queue_group_update(cache, group);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare function to sort proxies by number of hosts in descending *
 *          order                                                             *
 *                                                                            *
 ******************************************************************************/
static int	pg_proxy_compare_by_hosts_desc(const void *d1, const void *d2)
{
	const zbx_pg_proxy_t	*p1 = *(const zbx_pg_proxy_t * const *)d1;
	const zbx_pg_proxy_t	*p2 = *(const zbx_pg_proxy_t * const *)d2;

	return p2->hosts.values_num - p1->hosts.values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare function to sort proxies by number of hosts in ascending  *
 *          order                                                             *
 *                                                                            *
 ******************************************************************************/
static int	pg_proxy_compare_by_hosts_asc(const void *d1, const void *d2)
{
	const zbx_pg_proxy_t	*p1 = *(const zbx_pg_proxy_t * const *)d1;
	const zbx_pg_proxy_t	*p2 = *(const zbx_pg_proxy_t * const *)d2;

	return p1->hosts.values_num - p2->hosts.values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unassign last host from proxy                                     *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_proxy_unassign_last_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group,  zbx_pg_proxy_t *proxy)
{
	int	last = proxy->hosts.values_num - 1;

	zbx_vector_uint64_append(&group->unassigned_hostids, proxy->hosts.values[last]->hostid);
	pg_cache_set_host_proxy(cache, proxy->hosts.values[last]->hostid, 0);
	zbx_vector_pg_host_ptr_remove_noorder(&proxy->hosts, last);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove hosts from group proxies exceeding host limit              *
 *                                                                            *
 * Parameters: group    - [IN]                                                *
 *             limit    - [IN] target number of hosts per proxy               *
 *             required - [IN] required number of unassigned hosts            *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_group_unassign_excess_hosts(zbx_pg_cache_t *cache, zbx_pg_group_t *group, int limit,
		int required)
{
	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE != proxy->state)
			continue;

		/* proxies not exceeding balance limits could still have hosts taken   */
		/* to balance proxies with host deficit - so sort potential candidates */
		if (proxy->hosts.values_num > limit)
			zbx_vector_pg_host_ptr_sort(&proxy->hosts, pg_host_compare_by_revision);

		if (PG_GROUP_UNBALANCE_LIMIT > proxy->hosts.values_num - limit)
			continue;

		if (PG_GROUP_UNBALANCE_FACTOR > proxy->hosts.values_num / limit)
			continue;

		while (proxy->hosts.values_num > limit)
			pg_cache_proxy_unassign_last_host(cache, group, proxy);
	}

	if (group->unassigned_hostids.values_num >= required)
		return;

	zbx_vector_pg_proxy_ptr_t	proxies;

	zbx_vector_pg_proxy_ptr_create(&proxies);

	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE != proxy->state)
			continue;

		zbx_vector_pg_proxy_ptr_append(&proxies, proxy);
	}

	if (0 != proxies.values_num)
	{
		zbx_vector_pg_proxy_ptr_sort(&proxies, pg_proxy_compare_by_hosts_desc);

		while (group->unassigned_hostids.values_num < required)
		{
			int	hosts_num = proxies.values[0]->hosts.values_num - 1;

			for (int i = 0; i < proxies.values_num && group->unassigned_hostids.values_num < required; i++)
			{
				zbx_pg_proxy_t	*proxy = proxies.values[i];

				if (hosts_num >= proxy->hosts.values_num)
					break;

				pg_cache_proxy_unassign_last_host(cache, group, proxy);
			}
		}
	}

	zbx_vector_pg_proxy_ptr_destroy(&proxies);
}

/******************************************************************************
 *                                                                            *
 * Purpose: distribute unassigned hosts between online proxies                *
 *                                                                            *
 * Parameters: cache     - [IN] proxy group cache                             *
 *             group     - [IN] target group                                  *
 *             limit     - [IN] maximum number of hosts per proxy             *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_group_distribute_hosts(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
	zbx_vector_pg_proxy_ptr_t	proxies;

	zbx_vector_pg_proxy_ptr_create(&proxies);

	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE != proxy->state)
			continue;

		zbx_vector_pg_proxy_ptr_append(&proxies, proxy);
	}

	if (0 != proxies.values_num)
	{
		zbx_vector_pg_proxy_ptr_sort(&proxies, pg_proxy_compare_by_hosts_asc);

		while (0 < group->unassigned_hostids.values_num)
		{
			int	hosts_num = proxies.values[0]->hosts.values_num + 1;

			for (int i = 0; i < proxies.values_num && 0 < group->unassigned_hostids.values_num; i++)
			{
				zbx_pg_proxy_t	*proxy = proxies.values[i];

				if (hosts_num <= proxy->hosts.values_num)
					break;

				int	last = group->unassigned_hostids.values_num - 1;

				pg_cache_set_host_proxy(cache, group->unassigned_hostids.values[last], proxy->proxyid);
				zbx_vector_uint64_remove_noorder(&group->unassigned_hostids, last);
			}
		}
	}

	zbx_vector_pg_proxy_ptr_destroy(&proxies);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the proxy group is balanced                              *
 *                                                                            *
 * Return value: SUCCEED - the proxy group is balanced                        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Proxy group is not balanced if:                                  *
 *           - it has unassigned hosts                                        *
 *           - an online proxy has no assigned hosts                          *
 *           - the number of hosts assigned to a proxy differs from the       *
 *             average (withing the group) by at least 10 hosts and           *
 *             factor of 2.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	pg_cache_group_is_balanced(zbx_pg_group_t *group)
{
	if (0 != group->unassigned_hostids.values_num)
		return FAIL;

	int	 hosts_num = 0, proxies_num;

	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE != proxy->state)
			continue;

		if (0 == proxy->hosts.values_num)
			return FAIL;

		hosts_num += proxy->hosts.values_num;
		proxies_num++;
	}

	if (0 == proxies_num)
	{
		/* this function must be called only for online groups,  */
		/* and online groups will have at least one online proxy */
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	int	avg = hosts_num / proxies_num;

	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE != proxy->state)
			continue;

		if (PG_GROUP_UNBALANCE_LIMIT <= proxy->hosts.values_num - avg &&
				PG_GROUP_UNBALANCE_FACTOR <= proxy->hosts.values_num / avg)
		{
			return FAIL;
		}

		if (PG_GROUP_UNBALANCE_LIMIT <= avg - proxy->hosts.values_num &&
				PG_GROUP_UNBALANCE_FACTOR <= avg / proxy->hosts.values_num)
		{
			return FAIL;
		}
	}

	return SUCCEED;
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
	int	online_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() group:%s", __func__, group->name, cache->group_updates.values_num);

	/* find min/max host number of online proxies and remove hosts from offline proxies */
	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE == proxy->state)
		{
			online_num++;
		}
		else if (0 != proxy->hosts.values_num)
		{
			for (int j = 0; j < proxy->hosts.values_num; j++)
				zbx_vector_uint64_append(&group->unassigned_hostids, proxy->hosts.values[j]->hostid);

			zbx_vector_pg_host_ptr_clear(&proxy->hosts);
		}
	}

	int	hosts_avg = group->hostids.values_num / online_num, hosts_required = 0;

	/* calculate how many hosts are needed to balance groups with deficit hosts */
	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE != proxy->state)
			continue;

		if (PG_GROUP_UNBALANCE_LIMIT > hosts_avg - proxy->hosts.values_num)
			continue;

		if (PG_GROUP_UNBALANCE_FACTOR > hosts_avg / proxy->hosts.values_num)
			continue;

		hosts_required += hosts_avg - proxy->hosts.values_num;
	}

	pg_cache_group_unassign_excess_hosts(cache, group, hosts_avg, hosts_required);
	pg_cache_group_distribute_hosts(cache, group);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply pending changes to local cache and return changeset for     *
 *          database update                                                   *
 *                                                                            *
 * Parameters: cache          - [IN] proxy group cache                        *
 *             um_handle      - [IN] user macro cache handle                  *
 *             groups         - [OUT] group database updates                  *
 *             proxies        - [OUT] proxy database updates                  *
 *             hosts_new      - [OUT] new host-proxy links                    *
 *             hosts_mod      - [OUT] modified host-proxy links               *
 *             hosts_del      - [OUT] removed host-proxy links                *
 *             groupids       - [OUT] ids of groups with updated host-proxy   *
 *                                    mapping                                 *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_get_updates(zbx_pg_cache_t *cache, const zbx_dc_um_handle_t *um_handle, zbx_vector_pg_update_t *groups,
		zbx_vector_pg_update_t *proxies, zbx_vector_pg_host_t *hosts_new, zbx_vector_pg_host_t *hosts_mod,
		zbx_vector_pg_host_t *hosts_del, zbx_vector_uint64_t *groupids)
{
#define PG_UNBALANCE_PERIOD_COEFF	10

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groups:%d", __func__, cache->group_updates.values_num);

	time_t	now;

	now = time(NULL);

	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		zbx_pg_group_t	*group = cache->group_updates.values[i];

		if (ZBX_PG_GROUP_STATE_ONLINE == group->state)
		{
			if (SUCCEED != pg_cache_group_is_balanced(group))
			{
				group->unbalanced = PG_GROUP_UNBALANCED_YES;

				/* unassigned hostids must be assigned without balancing delays */
				if (0 != group->unassigned_hostids.values_num)
					group->balance_time = now;

				if (0 == group->balance_time)
				{
					char	*tmp;
					int	failover_delay;

					tmp = zbx_strdup(NULL, group->failover_delay);
					(void)zbx_dc_expand_user_and_func_macros(um_handle, &tmp, NULL, 0, NULL);

					if (FAIL == zbx_is_time_suffix(tmp, &failover_delay, ZBX_LENGTH_UNLIMITED))
					{
						zabbix_log(LOG_LEVEL_DEBUG, "invalid proxy group '" ZBX_FS_UI64
								"' failover delay '%s', using %d seconds default value",
								group->proxy_groupid, tmp,
								ZBX_PG_DEFAULT_FAILOVER_DELAY);

						failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY;
					}

					group->balance_time = now + failover_delay * PG_UNBALANCE_PERIOD_COEFF;
				}
				else if (now >= group->balance_time)
				{
					pg_cache_reassign_hosts(cache, group);
					group->balance_time = 0;
					group->unbalanced = PG_GROUP_UNBALANCED_NO;
				}
			}
			else
			{
				group->balance_time = 0;
				group->unbalanced = PG_GROUP_UNBALANCED_NO;
			}
		}

		if (ZBX_PG_GROUP_FLAGS_NONE != group->flags)
		{
			zbx_pg_update_t	group_update = {
					.objectid = group->proxy_groupid,
					.state = group->state,
					.flags = group->flags
				};

			zbx_vector_pg_update_append_ptr(groups, &group_update);
			group->flags = ZBX_PG_GROUP_FLAGS_NONE;
		}

		for (int j = 0; j < group->proxies.values_num; j++)
		{
			zbx_pg_proxy_t	*proxy = group->proxies.values[j];

			if (ZBX_PG_PROXY_FLAGS_NONE != proxy->flags)
			{
				zbx_pg_update_t	proxy_update = {
						.objectid = proxy->proxyid,
						.state = proxy->state,
						.flags = proxy->flags
					};

				zbx_vector_pg_update_append_ptr(proxies, &proxy_update);
				proxy->flags = ZBX_PG_GROUP_FLAGS_NONE;
			}
		}
	}

	for (int i = 0; i < cache->group_updates.values_num;)
	{
		zbx_pg_group_t	*group = cache->group_updates.values[i];

		if (ZBX_PG_GROUP_STATE_RECOVERY == group->state || PG_GROUP_UNBALANCED_YES == group->unbalanced)
		{
			/* Recovery state change is also affected by time, not only proxy state changes - */
			/* leave it in updates to periodically check until it changes state.              */
			/* Similarly unbalanced groups needs to be checked periodically until the         */
			/* unbalance period expires and group is rebalanced.                              */
			i++;
		}
		else
		{
			zbx_vector_pg_group_ptr_remove_noorder(&cache->group_updates, i);
		}
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

#undef PG_UNBALANCE_PERIOD_COEFF
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

	/* remove deleted groups */

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		/* skip the internal standalone proxy group */
		if (0 == group->proxy_groupid)
			continue;

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
 * Purpose: update proxy cache from configuration cache                       *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_update_proxies(zbx_pg_cache_t *cache)
{
	zbx_vector_objmove_t	proxy_reloc;
	zbx_hashset_iter_t	iter;
	zbx_pg_proxy_t		*proxy;

	zbx_vector_objmove_create(&proxy_reloc);

	zbx_dc_fetch_proxies(&cache->proxies, &cache->proxy_revision, &proxy_reloc);

	/* remove deleted proxies */

	zbx_hashset_iter_reset(&cache->proxies, &iter);
	while (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_iter_next(&iter)))
	{
		if (proxy->revision == cache->proxy_revision)
			continue;

		pg_cache_group_remove_proxy(cache, proxy->group, proxy);
		pg_proxy_clear(proxy);
		zbx_hashset_iter_remove(&iter);
	}

	/* add proxies to groups */
	for (int i = 0; i < proxy_reloc.values_num; i++)
	{
		zbx_pg_group_t	*group;
		zbx_objmove_t	*reloc = &proxy_reloc.values[i];

		if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &reloc->objid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		/* new proxies will have the same srcid and dstid and no old group needs to be updated */
		if (reloc->srcid != reloc->dstid)
		{
			if (NULL != (group = (zbx_pg_group_t *)zbx_hashset_search(&cache->groups, &reloc->srcid)))
			{
				pg_cache_group_remove_proxy(cache, group, proxy);
				pg_cache_queue_group_update(cache, group);
			}
		}

		if (NULL != (group = (zbx_pg_group_t *)zbx_hashset_search(&cache->groups, &reloc->dstid)))
		{
			proxy->group = group;
			zbx_vector_pg_proxy_ptr_append(&group->proxies, proxy);
			pg_cache_queue_group_update(cache, group);
		}
	}

	zbx_vector_objmove_destroy(&proxy_reloc);
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
	zabbix_log(LOG_LEVEL_TRACE, "    state:%d failover_delay:%s min_online:%s revision:" ZBX_FS_UI64
			" hostmap_revision:" ZBX_FS_UI64,
			group->state, group->failover_delay, group->min_online, group->revision,
			group->hostmap_revision);

	zabbix_log(LOG_LEVEL_TRACE, "    hostids:");
	for (int i = 0; i < group->hostids.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, group->hostids.values[i]);

	zabbix_log(LOG_LEVEL_TRACE, "    new hostids:");
	for (int i = 0; i < group->unassigned_hostids.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, group->unassigned_hostids.values[i]);

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

	zabbix_log(LOG_LEVEL_TRACE, "    state:%d lastaccess:%d firstaccess:%d groupid:" ZBX_FS_UI64,
			proxy->state, proxy->lastaccess, proxy->firstaccess, groupid);

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



