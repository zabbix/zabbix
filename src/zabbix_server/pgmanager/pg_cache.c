/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "pg_cache.h"
#include "zbxcommon.h"
#include "version.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxpgservice.h"
#include "zbxtime.h"
#include "zbxversion.h"

#define PG_GROUP_UNBALANCE_FACTOR	2
#define PG_GROUP_UNBALANCE_LIMIT	10

#define PG_GROUP_UNBALANCED_YES		1
#define PG_GROUP_UNBALANCED_NO		0

ZBX_VECTOR_IMPL(pg_update, zbx_pg_update_t)

typedef struct
{
	zbx_pg_proxy_t			*proxy;
	zbx_vector_pg_host_ref_ptr_t	host_refs;
	int				hosts_num;
}
zbx_pg_proxy_hosts_t;

ZBX_PTR_VECTOR_DECL(pg_proxy_hosts_ptr, zbx_pg_proxy_hosts_t *)
ZBX_PTR_VECTOR_IMPL(pg_proxy_hosts_ptr, zbx_pg_proxy_hosts_t *)

static void	pg_proxy_hosts_free(zbx_pg_proxy_hosts_t *ph)
{
	zbx_vector_pg_host_ref_ptr_destroy(&ph->host_refs);
	zbx_free(ph);
}

static int	pg_host_ref_compare_by_revision(const void *d1, const void *d2)
{
	const zbx_pg_host_ref_t	*hr1 = *(const zbx_pg_host_ref_t * const *)d1;
	const zbx_pg_host_ref_t	*hr2 = *(const zbx_pg_host_ref_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(hr1->host->revision, hr2->host->revision);

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
	zbx_hashset_destroy(&group->hostids);
	zbx_vector_uint64_destroy(&group->unassigned_hostids);

	zbx_free(group->name);
	zbx_free(group->failover_delay);
	zbx_free(group->min_online);
}

void	pg_proxy_clear(zbx_pg_proxy_t *proxy)
{
	zbx_hashset_destroy(&proxy->hosts);
	zbx_vector_pg_host_destroy(&proxy->deleted_group_hosts);
	zbx_free(proxy->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add proxy group for standalone proxies                            *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_add_standalone_proxy_group(zbx_pg_cache_t *cache)
{
	zbx_pg_group_t	*group, group_local = {.proxy_groupid = 0};

	group = (zbx_pg_group_t *)zbx_hashset_insert(&cache->groups, &group_local, sizeof(group_local));
	zbx_vector_pg_proxy_ptr_create(&group->proxies);
	zbx_hashset_create(&group->hostids, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&group->unassigned_hostids);
	group->state = ZBX_PG_GROUP_STATE_DISABLED;
	group->min_online = zbx_strdup(NULL, "0");
	group->name = zbx_strdup(NULL, "<standalone proxies>");
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
	cache->supported_version = ZBX_COMPONENT_VERSION(ZABBIX_VERSION_MAJOR, ZABBIX_VERSION_MINOR, 0);

	zbx_vector_pg_group_ptr_create(&cache->group_updates);

	zbx_hashset_create(&cache->proxies, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&cache->hostmap, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create(&cache->hostmap_updates, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	int	err;

	if (0 != (err = pthread_mutex_init(&cache->lock, NULL)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize proxy group manager cache mutex: %s", zbx_strerror(err));
		exit(EXIT_FAILURE);
	}

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
 * Purpose: remove proxy group from update queue                              *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_remove_group_update(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		if (cache->group_updates.values[i] == group)
		{
			zbx_vector_pg_group_ptr_remove_noorder(&cache->group_updates, i);
			return;
		}
	}
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


#define PG_REMOVE_DELETED_PROXY		0
#define PG_REMOVE_REASSIGNED_PROXY	1

/******************************************************************************
 *                                                                            *
 * Purpose: remove proxy from group                                           *
 *                                                                            *
 * Parameters: cache - [IN] proxy group cache                                 *
 *             group - [IN] target group                                      *
 *             proxy - [IN] proxy to remove                                   *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_group_remove_proxy(zbx_pg_cache_t *cache, zbx_pg_proxy_t *proxy, int flags)
{
	int			i;
	zbx_hashset_iter_t	iter;
	zbx_pg_host_ref_t	*ref;

	pg_cache_queue_group_update(cache, proxy->group);

	zbx_hashset_iter_reset(&proxy->hosts, &iter);
	while (NULL != (ref = (zbx_pg_host_ref_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_append(&proxy->group->unassigned_hostids, ref->host->hostid);

		if (PG_REMOVE_REASSIGNED_PROXY == flags)
		{
			pg_cache_set_host_proxy(cache, ref->host->hostid, 0);
		}
		else
		{
			/* proxy will perform host_proxy table cleanup when deleting proxies,  */
			/* so there is no need to add proxy hosts to proxy.deleted_group_hosts */
			zbx_hashset_remove_direct(&cache->hostmap, ref->host);
		}
	}

	if (FAIL != (i = zbx_vector_pg_proxy_ptr_search(&proxy->group->proxies, proxy, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
		zbx_vector_pg_proxy_ptr_remove_noorder(&proxy->group->proxies, i);

	zbx_hashset_clear(&proxy->hosts);
	proxy->group = NULL;
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

	zbx_hashset_remove(&group->hostids, &hostid);

	if (FAIL != (i = zbx_vector_uint64_search(&group->unassigned_hostids, hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove_noorder(&group->unassigned_hostids, i);

	if (NULL != (host = (zbx_pg_host_t *)zbx_hashset_search(&cache->hostmap, &hostid)))
	{
		zbx_pg_proxy_t	*proxy;

		if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)))
		{
			zbx_pg_host_t		host_local = {.hostid = hostid};
			zbx_pg_host_ref_t	*hr, hr_local = {.host = &host_local};

			if (NULL != (hr = (zbx_pg_host_ref_t *)zbx_hashset_search(&proxy->hosts, &hr_local)))
				zbx_hashset_remove_direct(&proxy->hosts, hr);
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
	if (NULL == zbx_hashset_search(&group->hostids, &hostid))
	{
		zbx_hashset_insert(&group->hostids, &hostid, sizeof(hostid));
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
static int	pg_proxy_hosts_compare_by_hosts_desc(const void *d1, const void *d2)
{
	const zbx_pg_proxy_hosts_t	*ph1 = *(const zbx_pg_proxy_hosts_t * const *)d1;
	const zbx_pg_proxy_hosts_t	*ph2 = *(const zbx_pg_proxy_hosts_t * const *)d2;

	return ph2->host_refs.values_num - ph1->host_refs.values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare function to sort proxies by number of hosts in ascending  *
 *          order                                                             *
 *                                                                            *
 ******************************************************************************/
static int	pg_proxy_hosts_compare_by_hosts_asc(const void *d1, const void *d2)
{
	const zbx_pg_proxy_hosts_t	*ph1 = *(const zbx_pg_proxy_hosts_t * const *)d1;
	const zbx_pg_proxy_hosts_t	*ph2 = *(const zbx_pg_proxy_hosts_t * const *)d2;

	return ph1->host_refs.values_num - ph2->host_refs.values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unassign last host from proxy                                     *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_proxy_unassign_host(zbx_pg_cache_t *cache, zbx_pg_group_t *group, zbx_pg_proxy_t *proxy,
		zbx_pg_host_ref_t *ref)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() group:%s proxy:%s proxy.hosts:%d hostid:%d", __func__, group->name,
			proxy->name, proxy->hosts.num_data, ref->host->hostid);

	zbx_vector_uint64_append(&group->unassigned_hostids, ref->host->hostid);
	pg_cache_set_host_proxy(cache, ref->host->hostid, 0);
	zbx_hashset_remove_direct(&proxy->hosts, ref);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove hosts from group proxies exceeding host limit              *
 *                                                                            *
 * Parameters: cache    - [IN]                                                *
 *             group    - [IN]                                                *
 *             proxies  - [IN] target proxies                                 *
 *             limit    - [IN] target number of hosts per proxy               *
 *             required - [IN] required number of unassigned hosts            *
 *                                                                            *
 ******************************************************************************/
static void	pg_cache_group_unassign_excess_hosts(zbx_pg_cache_t *cache, zbx_pg_group_t *group,
		zbx_vector_pg_proxy_hosts_ptr_t *proxies, int limit, int required)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() limit:%d required:%d", __func__, limit, required);

	zbx_vector_pg_proxy_hosts_ptr_sort(proxies, pg_proxy_hosts_compare_by_hosts_desc);

	while (group->unassigned_hostids.values_num < required)
	{
		int	hosts_num = proxies->values[0]->host_refs.values_num - 1;

		if (limit > hosts_num)
			goto out;

		for (int i = 0; i < proxies->values_num && group->unassigned_hostids.values_num < required; i++)
		{
			zbx_pg_proxy_hosts_t	*ph = proxies->values[i];

			if (hosts_num >= ph->host_refs.values_num)
				break;

			int	last = ph->host_refs.values_num - 1;

			pg_cache_proxy_unassign_host(cache, group, ph->proxy, ph->host_refs.values[last]);
			zbx_vector_pg_host_ref_ptr_remove_noorder(&ph->host_refs, last);
			ph->hosts_num--;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: distribute unassigned hosts between online proxies                *
 *                                                                            *
 * Parameters: cache     - [IN] proxy group cache                             *
 *             group     - [IN] target group                                  *
 *             proxies   - [IN] target proxies                                *
 *                                                                            *
 *  Return value: SUCCEED - unassigned hosts were distributed between online  *
 *                          proxies                                           *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	pg_cache_group_distribute_hosts(zbx_pg_cache_t *cache, zbx_pg_group_t *group,
		zbx_vector_pg_proxy_hosts_ptr_t *proxies)
{
	if (0 == group->unassigned_hostids.values_num)
		return FAIL;

	zbx_vector_pg_proxy_hosts_ptr_sort(proxies, pg_proxy_hosts_compare_by_hosts_asc);

	while (0 < group->unassigned_hostids.values_num)
	{
		int	hosts_num = proxies->values[0]->hosts_num + 1;

		for (int i = 0; i < proxies->values_num && 0 < group->unassigned_hostids.values_num; i++)
		{
			if (hosts_num <= proxies->values[i]->hosts_num)
				break;

			int	last = group->unassigned_hostids.values_num - 1;

			proxies->values[i]->hosts_num++;
			pg_cache_set_host_proxy(cache, group->unassigned_hostids.values[last],
					proxies->values[i]->proxy->proxyid);
			zbx_vector_uint64_remove_noorder(&group->unassigned_hostids, last);
		}
	}

	return SUCCEED;
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
 *             average (within the group) by at least 10 hosts and            *
 *             factor of 2.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	pg_cache_is_group_balanced(zbx_pg_cache_t *cache, const zbx_dc_um_handle_t *um_handle,
		zbx_pg_group_t *group)
{
	int	ret = FAIL;
	int	hosts_num = 0, proxies_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() group:%s", __func__, group->name);

	if (0 != group->unassigned_hostids.values_num)
		goto out;

	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE != proxy->state || proxy->version != cache->supported_version)
			continue;

		if (0 == proxy->hosts.num_data)
		{
			/* if a proxy has no hosts and another proxy has */
			/* multiple hosts then group is not balanced     */
			for (int j = 0; j < group->proxies.values_num; j++)
			{
				if (1 < group->proxies.values[j]->hosts.num_data)
					goto out;
			}
		}

		hosts_num += proxy->hosts.num_data;
		proxies_num++;
	}

	int	min_online, avg;
	char	*tmp;

	tmp = zbx_strdup(NULL, group->min_online);
	(void)zbx_dc_expand_user_and_func_macros(um_handle, &tmp, NULL, 0, NULL);
	if (0 == (min_online = atoi(tmp)))
		min_online = 1;
	zbx_free(tmp);

	if (0 == proxies_num || proxies_num < min_online)
	{
		/* no enough online proxies with supported version - treat the group as balanced */
		ret = SUCCEED;
		goto out;
	}

	avg = hosts_num / proxies_num;

	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE != proxy->state || proxy->version != cache->supported_version)
			continue;

		if (PG_GROUP_UNBALANCE_LIMIT <= proxy->hosts.num_data - avg &&
				0 != avg && PG_GROUP_UNBALANCE_FACTOR <= proxy->hosts.num_data / avg)
		{
			goto out;
		}

		if (PG_GROUP_UNBALANCE_LIMIT <= avg - proxy->hosts.num_data &&
				0 != proxy->hosts.num_data &&
				PG_GROUP_UNBALANCE_FACTOR <= avg / proxy->hosts.num_data)
		{
			goto out;
		}
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reassign host between online proxies within proxy group           *
 *                                                                            *
 * Parameters: cache     - [IN] proxy group cache                             *
 *             group     - [IN] target group                                  *
 *                                                                            *
 * Return value: SUCCEED - group was fully re-balanced                        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	pg_cache_reassign_hosts(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
	int				hosts_num = 0, hosts_min, hosts_required, ret = FAIL;
	zbx_vector_pg_proxy_hosts_ptr_t	proxies;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() group:%s", __func__, group->name, cache->group_updates.values_num);

	zbx_vector_pg_proxy_hosts_ptr_create(&proxies);

	/* find min/max host number of online proxies and remove hosts from offline proxies */
	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE == proxy->state && proxy->version == cache->supported_version)
		{
			zbx_pg_proxy_hosts_t	*ph;

			ph = (zbx_pg_proxy_hosts_t *)zbx_malloc(NULL, sizeof(zbx_pg_proxy_hosts_t));
			ph->proxy = proxy;
			ph->hosts_num = proxy->hosts.num_data;
			zbx_vector_pg_host_ref_ptr_create(&ph->host_refs);

			zbx_vector_pg_proxy_hosts_ptr_append(&proxies, ph);
			hosts_num += proxy->hosts.num_data;
		}
	}

	if (0 == proxies.values_num)
		goto out;

	if (SUCCEED == pg_cache_group_distribute_hosts(cache, group, &proxies))
		goto out;

	if (hosts_num > proxies.values_num)
		hosts_min = hosts_num / proxies.values_num;
	else
		hosts_min = 1;

	hosts_required = 0;

	/* calculate how many hosts are needed to balance groups with deficit hosts */
	for (int i = 0; i < proxies.values_num; i++)
	{
		zbx_pg_proxy_hosts_t	*ph = proxies.values[i];

		if (proxies.values[i]->hosts_num < hosts_min)
		{
			hosts_required += hosts_min - proxies.values[i]->hosts_num;
		}
		else
		{
			/* proxies with hosts more or equal to average number are possible      */
			/* targets for host unassignment - prepare host list sorted by revision */

			zbx_hashset_iter_t	iter;
			zbx_pg_host_ref_t	*ref;

			zbx_vector_pg_host_ref_ptr_reserve(&ph->host_refs, ph->proxy->hosts.num_data);

			zbx_hashset_iter_reset(&ph->proxy->hosts, &iter);
			while (NULL != (ref = (zbx_pg_host_ref_t *)zbx_hashset_iter_next(&iter)))
				zbx_vector_pg_host_ref_ptr_append(&ph->host_refs, ref);

			zbx_vector_pg_host_ref_ptr_sort(&ph->host_refs, pg_host_ref_compare_by_revision);
		}
	}

	pg_cache_group_unassign_excess_hosts(cache, group, &proxies, hosts_min, hosts_required);
	(void)pg_cache_group_distribute_hosts(cache, group, &proxies);

	ret = SUCCEED;
out:
	zbx_vector_pg_proxy_hosts_ptr_clear_ext(&proxies, pg_proxy_hosts_free);
	zbx_vector_pg_proxy_hosts_ptr_destroy(&proxies);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check group balance and reassign hosts if necessary               *
 *                                                                            *
 * Parameters: cache     - [IN] proxy group cache                             *
 *             um_handle - [IN] user macro cache handle                       *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_rebalance_groups(zbx_pg_cache_t *cache, const zbx_dc_um_handle_t *um_handle)
{
#define PG_UNBALANCE_PERIOD_COEFF	10

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groups:%d", __func__, cache->group_updates.values_num);

	time_t	now;

	now = time(NULL);

	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		zbx_pg_group_t	*group = cache->group_updates.values[i];

		if (ZBX_PG_GROUP_STATE_ONLINE != group->state)
			continue;

		if (SUCCEED != pg_cache_is_group_balanced(cache, um_handle, group))
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
							group->proxy_groupid, tmp, ZBX_PG_DEFAULT_FAILOVER_DELAY);

					failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY;
				}
				zbx_free(tmp);

				group->balance_time = now + failover_delay * PG_UNBALANCE_PERIOD_COEFF;
			}
			else if (now >= group->balance_time)
			{
				if (SUCCEED == pg_cache_reassign_hosts(cache, group))
				{
					group->balance_time = 0;
					group->unbalanced = PG_GROUP_UNBALANCED_NO;
				}
			}
		}
		else
		{
			group->balance_time = 0;
			group->unbalanced = PG_GROUP_UNBALANCED_NO;
		}
	}

#undef PG_UNBALANCE_PERIOD_COEFF
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy group and proxy updates that must be flushed            *
 *          to database                                                       *
 *                                                                            *
 * Parameters: cache         - [IN] proxy group cache                         *
 *             group_updates - [OUT] group updates                            *
 *             proxy_updates - [OUT] proxy updates                            *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_get_group_and_proxy_updates(zbx_pg_cache_t *cache, zbx_vector_pg_update_t *group_updates,
		zbx_vector_pg_update_t *proxy_updates)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() group updates:%d", __func__, cache->group_updates.values_num);

	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		zbx_pg_group_t	*group = cache->group_updates.values[i];
		if (ZBX_PG_GROUP_FLAGS_NONE != group->flags)
		{
			zbx_pg_update_t	group_update = {
					.objectid = group->proxy_groupid,
					.state = group->state,
					.flags = group->flags
				};

			zbx_vector_pg_update_append_ptr(group_updates, &group_update);
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

				zbx_vector_pg_update_append_ptr(proxy_updates, &proxy_update);
				proxy->flags = ZBX_PG_GROUP_FLAGS_NONE;
			}
		}
	}

	for (int i = 0; i < cache->group_updates.values_num;)
	{
		zbx_pg_group_t	*group = cache->group_updates.values[i];

		if (ZBX_PG_GROUP_STATE_RECOVERING == group->state || PG_GROUP_UNBALANCED_YES == group->unbalanced)
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() groups:%d proxies:%d", __func__, group_updates->values_num,
			proxy_updates->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get host-proxy link updates to be flushed to database             *
 *                                                                            *
 * Parameters: cache          - [IN] proxy group cache                        *
 *             hosts_new      - [OUT] new host-proxy links                    *
 *             hosts_mod      - [OUT] modified host-proxy links               *
 *             hosts_del      - [OUT] removed host-proxy links                *
 *             groupids       - [OUT] ids of groups with updated host-proxy   *
 *                                    mapping                                 *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_get_hostmap_updates(zbx_pg_cache_t *cache, zbx_vector_pg_host_t *hosts_new,
		zbx_vector_pg_host_t *hosts_mod, zbx_vector_pg_host_t *hosts_del, zbx_vector_uint64_t *groupids)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostmap updates:%d", __func__, cache->hostmap_updates.num_data);

	if (0 == cache->hostmap_updates.num_data)
		goto out;

	cache->hostmap_revision++;

	zbx_hashset_iter_t	iter;
	zbx_pg_host_t		*host, *new_host;
	zbx_pg_proxy_t		*proxy;

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
			if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)) &&
					NULL != proxy->group)
			{
					zbx_vector_uint64_append(groupids, proxy->group->proxy_groupid);
			}

			host_local.hostproxyid = host->hostproxyid;

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

				if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies,
						&host->proxyid)))
				{
					zbx_pg_host_ref_t	ref_local = {.host = host};

					if (NULL == zbx_hashset_search(&proxy->hosts, &ref_local))
						zbx_hashset_insert(&proxy->hosts, &ref_local, sizeof(ref_local));

					if (NULL != proxy->group)
						zbx_vector_uint64_append(groupids, proxy->group->proxy_groupid);
				}
			}
		}
		else
		{
			if (0 == new_host->proxyid)
				zbx_vector_pg_host_append_ptr(hosts_del, &host_local);
			else
				zbx_vector_pg_host_append_ptr(hosts_new, &host_local);
		}
	}

	zbx_hashset_clear(&cache->hostmap_updates);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, hosts_new->values_num,
			hosts_mod->values_num, hosts_del->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new host-proxy links to cache                                 *
 *                                                                            *
 * Parameters: cache          - [IN] proxy group cache                        *
 *             hosts_new      - [IN] new host-proxy links                     *
 *             groupids       - [OUT] ids of groups with updated host-proxy   *
 *                                    mapping                                 *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_add_new_hostmaps(zbx_pg_cache_t *cache, const zbx_vector_pg_host_t *hosts_new,
		zbx_vector_uint64_t *groupids)
{
	if (0 == hosts_new->values_num)
		return;

	zbx_uint64_t	hostproxyid;

	hostproxyid = zbx_db_get_maxid_num("host_proxy", hosts_new->values_num);
	for (int i = 0; i < hosts_new->values_num; i++)
	{
		zbx_pg_host_t	*host;
		zbx_pg_proxy_t	*proxy;

		hosts_new->values[i].hostproxyid = hostproxyid++;
		host = (zbx_pg_host_t *)zbx_hashset_insert(&cache->hostmap, &hosts_new->values[i],
				sizeof(hosts_new->values[i]));

		if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)))
		{
			zbx_pg_host_ref_t	ref_local = {.host = host};

			zbx_hashset_insert(&proxy->hosts, &ref_local, sizeof(ref_local));

			if (NULL != proxy->group)
				zbx_vector_uint64_append(groupids, proxy->group->proxy_groupid);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add deleted host-proxy links to all proxies of group monitoring   *
 *          corresponding hosts                                               *
 *                                                                            *
 * Parameters: cache          - [IN] proxy group cache                        *
 *             hosts_del      - [OUT] removed host-proxy links                *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_add_deleted_hostmaps(zbx_pg_cache_t *cache, zbx_vector_pg_host_t *hosts_del)
{
	if (0 == hosts_del->values_num)
		return;

	time_t	now;

	now = time(NULL);

	/* add deleted host-proxy links to every group proxy so they are synced to them */
	for (int i = 0; i < hosts_del->values_num; i++)
	{
		zbx_pg_proxy_t	*proxy;
		zbx_pg_host_t	*host = &hosts_del->values[i];

		if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &host->proxyid)) ||
				NULL == proxy->group || ZBX_PG_GROUP_STATE_DISABLED == proxy->group->state)
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
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy group cache from configuration cache                 *
 *                                                                            *
 ******************************************************************************/
int	pg_cache_update_groups(zbx_pg_cache_t *cache)
{
	zbx_uint64_t	old_revision = cache->group_revision;

	if (SUCCEED != zbx_dc_fetch_proxy_groups(&cache->groups, &cache->group_revision))
		return FAIL;

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
			pg_cache_remove_group_update(cache, group);
			pg_group_clear(group);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		group->flags = ZBX_PG_GROUP_FLAGS_NONE;

		if (old_revision < group->revision)
			pg_cache_queue_group_update(cache, group);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy cache from configuration cache                       *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_update_proxies(zbx_pg_cache_t *cache, int flags)
{
	zbx_vector_objmove_t	proxy_reloc;
	zbx_hashset_iter_t	iter;
	zbx_pg_proxy_t		*proxy;

	zbx_vector_objmove_create(&proxy_reloc);

	zbx_dc_fetch_proxies(&cache->groups, &cache->proxies, &cache->proxy_revision, flags, &proxy_reloc);

	/* remove deleted proxies */

	zbx_hashset_iter_reset(&cache->proxies, &iter);
	while (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_iter_next(&iter)))
	{
		if (proxy->revision == cache->proxy_revision)
			continue;

		if (NULL != proxy->group)
			pg_cache_group_remove_proxy(cache, proxy, PG_REMOVE_DELETED_PROXY);
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

		if (NULL != proxy->group)
			pg_cache_group_remove_proxy(cache, proxy, PG_REMOVE_REASSIGNED_PROXY);

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

/******************************************************************************
 *                                                                            *
 * Purpose: unassign hosts from offline proxies in a group                    *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_clear_offline_proxies(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
	for (int i = 0; i < group->proxies.values_num; i++)
	{
		zbx_pg_proxy_t 	*proxy = group->proxies.values[i];

		if (ZBX_PG_PROXY_STATE_ONLINE == proxy->state || 0 == proxy->hosts.num_data)
			continue;

		zbx_hashset_iter_t	iter;
		zbx_pg_host_ref_t	*ref;

		zbx_hashset_iter_reset(&proxy->hosts, &iter);
		while (NULL != (ref = (zbx_pg_host_ref_t *)zbx_hashset_iter_next(&iter)))
		{
			zbx_vector_uint64_append(&group->unassigned_hostids, ref->host->hostid);
			pg_cache_set_host_proxy(cache, ref->host->hostid, 0);
		}

		zbx_hashset_clear(&proxy->hosts);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy states in cache                                      *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_update_proxy_state(zbx_pg_cache_t *cache, zbx_dc_um_handle_t *um_handle, int now)
{
	const char		*proxy_state_str[] = {"unknown", "offline", "online"};

	zbx_pg_proxy_t		*proxy;
	zbx_pg_group_t		*group;
	zbx_hashset_iter_t	iter;
	char			*tmp;
	int			failover_delay;

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		int	update_group = 0;

		tmp = zbx_strdup(NULL, group->failover_delay);
		(void)zbx_dc_expand_user_and_func_macros(um_handle, &tmp, NULL, 0, NULL);

		if (FAIL == zbx_is_time_suffix(tmp, &failover_delay, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "invalid proxy group '" ZBX_FS_UI64 "' failover delay '%s', "
					"using %d seconds default value", group->proxy_groupid, tmp,
					ZBX_PG_DEFAULT_FAILOVER_DELAY);

			failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY;
		}

		zbx_free(tmp);

		for (int i = 0; i < group->proxies.values_num; i++)
		{
			int	state = ZBX_PG_PROXY_STATE_UNKNOWN;

			proxy = group->proxies.values[i];

			if (now - proxy->lastaccess >= failover_delay)
			{
				if (now - cache->startup_time >= failover_delay)
				{
					state = ZBX_PG_PROXY_STATE_OFFLINE;
					proxy->firstaccess = 0;
				}
			}
			else
			{
				if (0 == proxy->firstaccess)
					proxy->firstaccess = proxy->lastaccess;

				/* offline proxies in groups must be alive for failover */
				/* delay time before they are switched to online        */
				if (ZBX_PG_PROXY_STATE_UNKNOWN == proxy->state || 0 == group->proxy_groupid ||
						now - proxy->firstaccess >= failover_delay)
				{
					state = ZBX_PG_PROXY_STATE_ONLINE;
				}
			}

			if (ZBX_PG_PROXY_STATE_UNKNOWN == state || proxy->state == state)
				continue;

			zabbix_log(LOG_LEVEL_WARNING, "Proxy \"%s\" changed state from %s to %s",
					proxy->name, proxy_state_str[proxy->state], proxy_state_str[state]);

			proxy->state = state;
			proxy->flags |= ZBX_PG_PROXY_UPDATE_STATE;
			update_group = 1;
		}

		if (0 != update_group)
			pg_cache_queue_group_update(cache, group);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy group states in cache                                *
 *                                                                            *
 ******************************************************************************/
void	pg_cache_update_group_state(zbx_pg_cache_t *cache, zbx_dc_um_handle_t *um_handle, int now)
{
	const char		*group_state_str[] = {"unknown", "offline", "recovering", "online", "degrading"};

	zbx_pg_group_t		*group;
	char			*tmp;
	int			failover_delay, min_online;
	zbx_hashset_iter_t	iter;

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_PG_GROUP_STATE_DISABLED == group->state)
			continue;

		int	online = 0, offline = 0, healthy = 0, total;

		total = group->proxies.values_num;

		tmp = zbx_strdup(NULL, group->failover_delay);
		(void)zbx_dc_expand_user_and_func_macros(um_handle, &tmp, NULL, 0, NULL);

		if (FAIL == zbx_is_time_suffix(tmp, &failover_delay, ZBX_LENGTH_UNLIMITED))
			failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY;

		tmp = zbx_strdup(tmp, group->min_online);
		(void)zbx_dc_expand_user_and_func_macros(um_handle, &tmp, NULL, 0, NULL);
		if (0 == (min_online = atoi(tmp)))
			min_online = 1;
		zbx_free(tmp);

		for (int j = 0; j < group->proxies.values_num; j++)
		{
			/* treat old version proxies as offline when calculating groups state */
			if (group->proxies.values[j]->version != cache->supported_version)
			{
				offline++;
				continue;
			}

			switch (group->proxies.values[j]->state)
			{
				case ZBX_PG_PROXY_STATE_ONLINE:
					online++;

					if (now - group->proxies.values[j]->lastaccess + PG_STATE_CHECK_INTERVAL <
							failover_delay)
					{
						healthy++;
					}
					break;
				case ZBX_PG_PROXY_STATE_OFFLINE:
					offline++;
					break;
			}
		}

		int	state = group->state;

		switch (group->state)
		{
			case ZBX_PG_GROUP_STATE_UNKNOWN:
				state = ZBX_PG_GROUP_STATE_RECOVERING;
				group->state_time = now;
				ZBX_FALLTHROUGH;
			case ZBX_PG_GROUP_STATE_RECOVERING:
				if (total - offline < min_online)
				{
					state = ZBX_PG_GROUP_STATE_OFFLINE;
				}
				else if (total == online)
				{
					state = ZBX_PG_GROUP_STATE_ONLINE;
				}
				else if (now - group->state_time > failover_delay)
				{
					if (online >= min_online)
						state = ZBX_PG_GROUP_STATE_ONLINE;
					else
						state = ZBX_PG_GROUP_STATE_OFFLINE;
				}
				break;
			case ZBX_PG_GROUP_STATE_DEGRADING:
				if (healthy >= min_online)
					state = ZBX_PG_GROUP_STATE_ONLINE;
				else if (online < min_online)
					state = ZBX_PG_GROUP_STATE_OFFLINE;
				break;
			case ZBX_PG_GROUP_STATE_OFFLINE:
				if (online >= min_online)
					state = ZBX_PG_GROUP_STATE_RECOVERING;
				break;
			case ZBX_PG_GROUP_STATE_ONLINE:
				if (total - offline < min_online)
					state = ZBX_PG_GROUP_STATE_OFFLINE;
				else if (healthy < min_online)
					state = ZBX_PG_GROUP_STATE_DEGRADING;
				break;
		}

		if (state != group->state)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Proxy group \"%s\" changed state from %s to %s",
					group->name, group_state_str[group->state], group_state_str[state]);

			group->state = state;
			group->state_time = now;
			group->flags |= ZBX_PG_GROUP_UPDATE_STATE;

			if (ZBX_PG_GROUP_STATE_ONLINE == group->state)
				pg_cache_queue_group_update(cache, group);
		}

		if (ZBX_PG_GROUP_STATE_ONLINE == group->state)
			pg_cache_clear_offline_proxies(cache, group);
	}
}

static void	pg_cache_dump_group(zbx_pg_group_t *group)
{
	zabbix_log(LOG_LEVEL_TRACE, "proxy group:" ZBX_FS_UI64 " %s", group->proxy_groupid, group->name);
	zabbix_log(LOG_LEVEL_TRACE, "    state:%d failover_delay:%s min_online:%s revision:" ZBX_FS_UI64
			" hostmap_revision:" ZBX_FS_UI64,
			group->state, group->failover_delay, group->min_online, group->revision,
			group->hostmap_revision);

	zbx_hashset_iter_t	iter;
	zbx_uint64_t		*hostid;

	zbx_hashset_iter_reset(&group->hostids, &iter);

	zabbix_log(LOG_LEVEL_TRACE, "    hostids: [%d]", group->hostids.num_data);
	while (NULL != (hostid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, *hostid);

	zabbix_log(LOG_LEVEL_TRACE, "    new hostids: [%d]", group->unassigned_hostids.values_num);
	for (int i = 0; i < group->unassigned_hostids.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, group->unassigned_hostids.values[i]);

	zabbix_log(LOG_LEVEL_TRACE, "    proxies: [%d]", group->proxies.values_num);
	for (int i = 0; i < group->proxies.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, group->proxies.values[i]->proxyid);
}

static void	pg_cache_dump_proxy(zbx_pg_proxy_t *proxy)
{
	zbx_uint64_t	groupid = 0;

	zabbix_log(LOG_LEVEL_TRACE, "proxy:" ZBX_FS_UI64 " %s", proxy->proxyid, proxy->name);

	if (NULL != proxy->group)
		groupid = proxy->group->proxy_groupid;

	zabbix_log(LOG_LEVEL_TRACE, "    state:%d version:%x lastaccess:%d firstaccess:%d groupid:" ZBX_FS_UI64,
			proxy->state, proxy->version, proxy->lastaccess, proxy->firstaccess, groupid);

	zabbix_log(LOG_LEVEL_TRACE, "    hostids: [%d]", proxy->hosts.num_data);

	zbx_hashset_iter_t	iter;
	zbx_pg_host_ref_t	*ref;

	zbx_hashset_iter_reset(&proxy->hosts, &iter);
	while (NULL != (ref = (zbx_pg_host_ref_t *)zbx_hashset_iter_next(&iter)))
		zabbix_log(LOG_LEVEL_TRACE, "        " ZBX_FS_UI64, ref->host->hostid);

	zabbix_log(LOG_LEVEL_TRACE, "    deleted group hostproxyids: [%d]", proxy->deleted_group_hosts.values_num);
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

	zabbix_log(LOG_LEVEL_TRACE, "groups: [%d]", cache->groups.num_data);

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_group(group);

	zabbix_log(LOG_LEVEL_TRACE, "proxies: [%d]", cache->proxies.num_data);

	zbx_hashset_iter_reset(&cache->proxies, &iter);
	while (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_proxy(proxy);

	zabbix_log(LOG_LEVEL_TRACE, "hostmap: [%d]", cache->hostmap.num_data);

	zbx_hashset_iter_reset(&cache->hostmap, &iter);
	while (NULL != (host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_host(host);

	zabbix_log(LOG_LEVEL_TRACE, "hostmap updates: [%d]", cache->hostmap_updates.num_data);

	zbx_hashset_iter_reset(&cache->hostmap_updates, &iter);
	while (NULL != (host = (zbx_pg_host_t *)zbx_hashset_iter_next(&iter)))
		pg_cache_dump_host(host);
}
