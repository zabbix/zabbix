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

#include "pg_manager.h"
#include "pg_service.h"

#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbxcacheconfig.h"
#include "zbxpgservice.h"

#define PGM_STATUS_CHECK_INTERVAL	5

/******************************************************************************
 *                                                                            *
 * Purpose: initialize proxy group manager                                    *
 *                                                                            *
 ******************************************************************************/
static void	pgm_init(zbx_pg_cache_t *cache)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_uint64_t	map_revision = 0;

	result = zbx_db_select("select nextid from ids where table_name='host_proxy' and field_name='revision'");

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_DBROW2UINT64(map_revision, row[0]);

	zbx_db_free_result(result);

	pg_cache_init(cache, map_revision);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update host-proxy group assignments from database                 *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_get_hosts(zbx_pg_cache_t *cache)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;

	result = zbx_db_select("select hostid,proxy_groupid from hosts where proxy_groupid is not null");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostid, proxy_groupid;
		zbx_pg_group_t	*group;

		ZBX_DBROW2UINT64(hostid, row[0]);
		ZBX_DBROW2UINT64(proxy_groupid, row[1]);

		if (NULL == (group = (zbx_pg_group_t *)zbx_hashset_search(&cache->groups, &proxy_groupid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_uint64_append(&group->hostids, hostid);
	}
	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update host-proxy mapping from database                           *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_get_hpmap(zbx_pg_cache_t *cache)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	int		now;

	now = (int)time(NULL);

	result = zbx_db_select("select hostid,proxyid,revision,hostproxyid from host_proxy");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostid, proxyid, revision,hostproxyid;
		zbx_pg_proxy_t	*proxy;

		ZBX_DBROW2UINT64(proxyid, row[1]);
		ZBX_DBROW2UINT64(hostid, row[0]);
		ZBX_STR2UINT64(revision, row[2]);
		ZBX_STR2UINT64(hostproxyid, row[3]);

		zbx_pg_host_t	host_local = {
				.hostid = hostid,
				.proxyid = proxyid,
				.revision = revision,
				.hostproxyid = hostproxyid
			}, *host;

		host = (zbx_pg_host_t *)zbx_hashset_insert(&cache->hostmap, &host_local, sizeof(host_local));

		if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &proxyid)) ||
				NULL == proxy->group ||
				FAIL == zbx_vector_uint64_search(&proxy->group->hostids, hostid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			pg_cache_set_host_proxy(cache, hostid, 0);
			continue;
		}

		zbx_vector_pg_host_ptr_append(&proxy->hosts, host);

		/* proxies with assigned hosts are assumed to be online */
		proxy->status = ZBX_PG_PROXY_STATUS_ONLINE;
		proxy->lastaccess = now;

		if (NULL != proxy->group && proxy->group->hostmap_revision < revision)
			proxy->group->hostmap_revision = revision;
	}
	zbx_db_free_result(result);

	/* queue unmapped hosts for proxy assignment */

	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*group;

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		for (int i = 0; i < group->hostids.values_num; i++)
		{
			if (NULL == zbx_hashset_search(&cache->hostmap, &group->hostids.values[i]))
				zbx_vector_uint64_append(&group->new_hostids, group->hostids.values[i]);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy and proxy group status in cache                      *
 *                                                                            *
 ******************************************************************************/
static void	pgm_update_status(zbx_pg_cache_t *cache)
{
	const char	*proxy_status_str[] = {"unknown", "offline", "online"};
	const char	*group_status_str[] = {"unknown", "offline", "recovery", "online", "decay"};

	zbx_hashset_iter_t	iter;
	zbx_pg_proxy_t		*proxy;
	zbx_pg_group_t		*group;
	int			now, failover_delay, min_online;
	zbx_dc_um_handle_t	*um_handle;
	char			*tmp;

	um_handle = zbx_dc_open_user_macros();

	pg_cache_lock(cache);

	now = (int)time(NULL);

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
			int	status = ZBX_PG_PROXY_STATUS_UNKNOWN;

			proxy = group->proxies.values[i];

			if (now - proxy->lastaccess >= failover_delay)
			{
				if (now - cache->startup_time >= failover_delay)
				{
					status = ZBX_PG_PROXY_STATUS_OFFLINE;
					proxy->firstaccess = 0;
				}
			}
			else
			{
				if (0 == proxy->firstaccess)
					proxy->firstaccess = proxy->lastaccess;

				if (now - proxy->firstaccess >= failover_delay)
					status = ZBX_PG_PROXY_STATUS_ONLINE;
			}

			if (ZBX_PG_PROXY_STATUS_UNKNOWN == status || proxy->status == status)
				continue;

			zabbix_log(LOG_LEVEL_WARNING, "Proxy \"%s\" changed status from %s to %s",
					proxy->name, proxy_status_str[proxy->status], proxy_status_str[status]);

			proxy->status = status;
			proxy->flags |= ZBX_PG_PROXY_UPDATE_STATUS;
			update_group = 1;
		}

		if (0 != update_group)
			pg_cache_queue_group_update(cache, proxy->group);
	}

	for (int i = 0; i < cache->group_updates.values_num; i++)
	{
		int		online = 0, offline = 0, healthy = 0, total = group->proxies.values_num;

		group = cache->group_updates.values[i];

		tmp = zbx_strdup(NULL, group->failover_delay);
		(void)zbx_dc_expand_user_and_func_macros(um_handle, &tmp, NULL, 0, NULL);

		if (FAIL == zbx_is_time_suffix(tmp, &failover_delay, ZBX_LENGTH_UNLIMITED))
			failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY;

		tmp = zbx_strdup(tmp, group->min_online);
		(void)zbx_dc_expand_user_and_func_macros(um_handle, &tmp, NULL, 0, NULL);
		min_online = atoi(tmp);
		zbx_free(tmp);

		for (int j = 0; j < group->proxies.values_num; j++)
		{
			switch (group->proxies.values[j]->status)
			{
				case ZBX_PG_PROXY_STATUS_ONLINE:
					online++;

					if (now - group->proxies.values[j]->lastaccess + PGM_STATUS_CHECK_INTERVAL <
							failover_delay)
					{
						healthy++;
					}
					break;
				case ZBX_PG_PROXY_STATUS_OFFLINE:
					offline++;
					break;
			}
		}

		int	status = group->status;

		switch (group->status)
		{
			case ZBX_PG_GROUP_STATUS_UNKNOWN:
				status = ZBX_PG_GROUP_STATUS_RECOVERY;
				ZBX_FALLTHROUGH;
			case ZBX_PG_GROUP_STATUS_RECOVERY:
				if (total - offline < min_online)
				{
					status = ZBX_PG_GROUP_STATUS_OFFLINE;
				}
				else if (total == online)
				{
					status = ZBX_PG_GROUP_STATUS_ONLINE;
				}
				else if (now - group->status_time > failover_delay)
				{
					if (online >= min_online)
						status = ZBX_PG_GROUP_STATUS_ONLINE;
					else
						status = ZBX_PG_GROUP_STATUS_OFFLINE;
				}
				break;
			case ZBX_PG_GROUP_STATUS_DECAY:
				if (healthy >= min_online)
					status = ZBX_PG_GROUP_STATUS_ONLINE;
				else if (online < min_online)
					status = ZBX_PG_GROUP_STATUS_OFFLINE;
				break;
			case ZBX_PG_GROUP_STATUS_OFFLINE:
				if (online >= min_online)
					status = ZBX_PG_GROUP_STATUS_RECOVERY;
				break;
			case ZBX_PG_GROUP_STATUS_ONLINE:
				if (healthy < min_online)
					status = ZBX_PG_GROUP_STATUS_DECAY;
				break;
		}

		if (status != group->status)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Proxy group \"%s\" changed status from %s to %s",
					group->name, group_status_str[group->status], group_status_str[status]);

			group->status = status;
			group->status_time = now;
			group->flags |= ZBX_PG_GROUP_UPDATE_STATUS;
		}
	}

	pg_cache_unlock(cache);

	zbx_dc_close_user_macros(um_handle);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush proxy group updates to database                             *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_flush_group_updates(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_pg_update_t *groups)
{
	for (int i = 0; i < groups->values_num; i++)
	{
		if (0 == (groups->values[i].flags & ZBX_PG_GROUP_UPDATE_STATUS))
			continue;

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update proxy_group set status=%d where proxy_groupid=" ZBX_FS_UI64 ";\n",
				groups->values[i].status, groups->values[i].objectid);

		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush proxy updates to database                                   *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_flush_proxy_updates(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_pg_update_t *proxies)
{
	for (int i = 0; i < proxies->values_num; i++)
	{
		if (0 == (proxies->values[i].flags & ZBX_PG_PROXY_UPDATE_STATUS))
			continue;

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update proxy set status=%d where proxyid=" ZBX_FS_UI64 ";\n",
				proxies->values[i].status, proxies->values[i].objectid);

		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush host-proxy mapping changes to database                      *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_flush_host_proxy_updates(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_pg_host_t *hosts)
{
	for (int i = 0; i < hosts->values_num; i++)
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update host_proxy set proxyid=" ZBX_FS_UI64 ",revision=" ZBX_FS_UI64
					" where hostid=" ZBX_FS_UI64 ";\n",
				hosts->values[i].proxyid, hosts->values[i].revision, hosts->values[i].hostid);

		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		zabbix_log(LOG_LEVEL_DEBUG, "re-assigned hostid " ZBX_FS_UI64 " to proxyid " ZBX_FS_UI64,
				hosts->values[i].hostid, hosts->values[i].proxyid);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete removed host-proxy mapping records from database           *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_flush_host_proxy_deletes(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_pg_host_t *hosts)
{
	if (0 == hosts->values_num)
		return;

	zbx_vector_uint64_t	hostids;

	zbx_vector_uint64_create(&hostids);

	for (int i = 0; i < hosts->values_num; i++)
	{
		zbx_vector_uint64_append(&hostids, hosts->values[i].hostid);

		zabbix_log(LOG_LEVEL_DEBUG, "unassigned hostid " ZBX_FS_UI64 " from proxyid " ZBX_FS_UI64,
				hosts->values[i].hostid, hosts->values[i].proxyid);
	}

	zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "delete from host_proxy where ");
	zbx_db_add_condition_alloc(sql, sql_alloc, sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ";\n");

	zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

	zbx_vector_uint64_destroy(&hostids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get record identifiers from database and lock them                *
 *                                                                            *
 * Parameters: ids   - [IN] vector with identifier to lock                    *
 *             table - [IN] target table                                      *
 *             field - [IN] record identifier field name                      *
 *             index - [OUT] locked identifiers                               *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_get_recids_for_update(zbx_vector_uint64_t *ids, const char *table, const char *field,
		zbx_hashset_t *index)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_uint64_t	id;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select %s from %s where ", field, table);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, field, ids->values, ids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ZBX_FOR_UPDATE);

	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_DBROW2UINT64(id, row[0]);
		zbx_hashset_insert(index, &id, sizeof(id));
	}

	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush new host-mapping record batch to database                   *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_flush_host_proxy_insert_batch(zbx_pg_host_t *hosts, int hosts_num)
{
	zbx_vector_uint64_t	hostids, proxyids;
	zbx_hashset_t		host_index, proxy_index;

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&proxyids);

	zbx_hashset_create(&host_index, (size_t)hosts_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&proxy_index, (size_t)hosts_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (int i = 0; i < hosts_num; i++)
	{
		zbx_vector_uint64_append(&hostids, hosts[i].hostid);
		zbx_vector_uint64_append(&proxyids, hosts[i].proxyid);
	}

	pgm_db_get_recids_for_update(&hostids, "hosts", "hostid", &host_index);
	pgm_db_get_recids_for_update(&proxyids, "proxy", "proxyid", &proxy_index);

	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "host_proxy", "hostproxyid", "hostid", "proxyid", "revision", NULL);

	for (int i = 0; i < hosts_num; i++)
	{
		if (NULL == zbx_hashset_search(&host_index, &hosts[i].hostid))
			continue;

		if (NULL == zbx_hashset_search(&proxy_index, &hosts[i].proxyid))
			continue;

		zbx_db_insert_add_values(&db_insert, hosts[i].hostproxyid, hosts[i].hostid, hosts[i].proxyid,
				hosts[i].revision);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_hashset_destroy(&proxy_index);
	zbx_hashset_destroy(&host_index);

	zbx_vector_uint64_destroy(&proxyids);
	zbx_vector_uint64_destroy(&hostids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush new host-mapping records to database                        *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_flush_host_proxy_inserts(zbx_vector_pg_host_t *hosts)
{
#define PGM_INSERT_BATCH_SIZE	1000
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "host_proxy", "hostproxyid", "hostid", "proxyid", "revision", NULL);

	for (int i = 0; i < hosts->values_num; i += PGM_INSERT_BATCH_SIZE)
	{
		int	size = hosts->values_num - i;

		if (PGM_INSERT_BATCH_SIZE < size)
			size = PGM_INSERT_BATCH_SIZE;

		pgm_db_flush_host_proxy_insert_batch(hosts->values + i, size);

		zabbix_log(LOG_LEVEL_DEBUG, "assigned hostid " ZBX_FS_UI64 " to proxyid " ZBX_FS_UI64,
				hosts->values[i].hostid, hosts->values[i].proxyid);
	}

	zbx_db_insert_autoincrement(&db_insert, "hostproxyid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

#undef PGM_INSERT_BATCH_SIZE
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush host-proxy mapping revision to database                     *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_flush_host_proxy_revision(zbx_uint64_t revision)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;

	result = zbx_db_select("select nextid from ids where table_name='host_proxy' and field_name='revision'");

	if (NULL == (row = zbx_db_fetch(result)))
	{
		zbx_db_insert_t	db_insert;

		zbx_db_insert_prepare(&db_insert, "ids", "table_name", "field_name", "nextid", NULL);
		zbx_db_insert_add_values(&db_insert, "host_proxy", "revision", revision);
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}
	else
	{
		zbx_uint64_t	nextid;

		ZBX_DBROW2UINT64(nextid, row[0]);

		if (nextid != revision)
		{
			zbx_db_execute("update ids set nextid=" ZBX_FS_UI64
					" where table_name='host_proxy' and field_name='revision'", revision);
		}
	}

	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush proxy group and host-proxy mapping updates to database      *
 *                                                                            *
 ******************************************************************************/
static void	pgm_flush_updates(zbx_pg_cache_t *cache)
{
	zbx_vector_pg_update_t	groups, proxies;
	zbx_vector_pg_host_t	hosts_new, hosts_mod, hosts_del;
	zbx_vector_uint64_t	groupids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_pg_update_create(&groups);
	zbx_vector_pg_update_create(&proxies);
	zbx_vector_pg_host_create(&hosts_new);
	zbx_vector_pg_host_create(&hosts_mod);
	zbx_vector_pg_host_create(&hosts_del);
	zbx_vector_uint64_create(&groupids);

	pg_cache_lock(cache);
	pg_cache_get_updates(cache, &groups, &proxies, &hosts_new, &hosts_mod, &hosts_del, &groupids);
	pg_cache_unlock(cache);

	if (0 != groups.values_num || 0 != proxies.values_num || 0 != hosts_new.values_num ||
			0 != hosts_mod.values_num || 0 != hosts_del.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0;
		int	ret;

		do
		{
			size_t	sql_offset = 0;

			zbx_db_begin();

			zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

			pgm_db_flush_group_updates(&sql, &sql_alloc, &sql_offset, &groups);
			pgm_db_flush_proxy_updates(&sql, &sql_alloc, &sql_offset, &proxies);
			pgm_db_flush_host_proxy_updates(&sql, &sql_alloc, &sql_offset, &hosts_mod);
			pgm_db_flush_host_proxy_deletes(&sql, &sql_alloc, &sql_offset, &hosts_del);

			zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

			if (16 < sql_offset)
				zbx_db_execute("%s", sql);

			pgm_db_flush_host_proxy_inserts(&hosts_new);

			pgm_db_flush_host_proxy_revision(cache->hostmap_revision);

		}
		while (ZBX_DB_DOWN == (ret = zbx_db_commit()));

		zbx_free(sql);

		if (ZBX_DB_OK <= ret && 0 != groupids.values_num)
		{
			zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			pg_cache_lock(cache);
			pg_cache_update_hostmap_revision(cache, &groupids);
			pg_cache_unlock(cache);
		}

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
			pg_cache_dump(cache);
	}

	zbx_vector_uint64_destroy(&groupids);
	zbx_vector_pg_host_destroy(&hosts_del);
	zbx_vector_pg_host_destroy(&hosts_mod);
	zbx_vector_pg_host_destroy(&hosts_new);
	zbx_vector_pg_update_destroy(&proxies);
	zbx_vector_pg_update_destroy(&groups);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	pgm_update_proxies(zbx_pg_cache_t *cache)
{
	zbx_vector_objmove_t	proxy_reloc;

	zbx_vector_objmove_create(&proxy_reloc);

	pg_cache_lock(cache);

	zbx_dc_fetch_proxies(&cache->proxies, &cache->proxy_revision, &proxy_reloc);

	for (int i = 0; i < proxy_reloc.values_num; i++)
	{
		zbx_pg_group_t	*group;
		zbx_pg_proxy_t	*proxy;
		zbx_objmove_t	*reloc = &proxy_reloc.values[i];

		if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &reloc->objid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		if (0 != reloc->srcid)
		{
			if (NULL != (group = (zbx_pg_group_t *)zbx_hashset_search(&cache->groups, &reloc->srcid)))
			{
				pg_cache_group_remove_proxy(cache, group, proxy);
				pg_cache_queue_group_update(cache, group);
			}
		}

		if (0 != reloc->dstid)
		{
			if (NULL != (group = (zbx_pg_group_t *)zbx_hashset_search(&cache->groups, &reloc->dstid)))
			{
				proxy->group = group;
				zbx_vector_pg_proxy_ptr_append(&group->proxies, proxy);
				pg_cache_queue_group_update(cache, group);
			}
		}
		else
			pg_cache_proxy_free(cache, proxy);
	}

	pg_cache_unlock(cache);

	zbx_vector_objmove_destroy(&proxy_reloc);
}

static void	pgm_update_groups(zbx_pg_cache_t *cache)
{
	pg_cache_lock(cache);
	pg_cache_update_groups(cache);
	pg_cache_unlock(cache);
}

/*
 * main process loop
 */

ZBX_THREAD_ENTRY(pg_manager_thread, args)
{
	zbx_pg_service_t	pgs;
	char			*error = NULL;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	zbx_pg_cache_t		cache;
	double			time_update = 0;

	zbx_setproctitle("%s #%d starting", get_process_type_string(info->process_type), info->process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			info->server_num, get_process_type_string(info->process_type), info->process_num);

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	pgm_init(&cache);

	if (FAIL == pg_service_init(&pgs, &cache, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start proxy group manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	pgm_update_groups(&cache);
	pgm_update_proxies(&cache);
	pgm_db_get_hosts(&cache);
	pgm_db_get_hpmap(&cache);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		pg_cache_dump(&cache);

	time_update = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(info->process_type), info->process_num);

	while (ZBX_IS_RUNNING())
	{
		double	time_now;

		time_now = zbx_time();

		if (PGM_STATUS_CHECK_INTERVAL >= time_update - time_now)
		{
			pgm_update_groups(&cache);
			pgm_update_proxies(&cache);
			pgm_update_status(&cache);

			time_update = time_now;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		zbx_sleep_loop(info, 1);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		if (0 != cache.group_updates.values_num)
			pgm_flush_updates(&cache);
	}

	pg_service_destroy(&pgs);
	zbx_db_close();

	pg_cache_destroy(&cache);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(info->process_type), info->process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
