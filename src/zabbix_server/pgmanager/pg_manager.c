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

#include "pg_manager.h"

#include "pg_cache.h"
#include "pg_service.h"

#include "zbxtimekeeper.h"
#include "zbx_host_constants.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxstr.h"
#include "zbxtime.h"

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
 * Purpose: update hosts and host-proxy group assignments from database       *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_get_hosts(zbx_pg_cache_t *cache)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;

	result = zbx_db_select("select h.hostid,hp.proxyid,hp.revision,hp.hostproxyid,h.monitored_by,h.proxy_groupid"
			" from hosts h"
			" left join host_proxy hp"
			" on h.hostid=hp.hostid");

	pg_cache_lock(cache);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostid, proxyid, revision, hostproxyid, proxy_groupid;
		zbx_pg_proxy_t	*proxy;
		zbx_pg_group_t	*group;
		unsigned char	monitored_by;

		ZBX_DBROW2UINT64(hostid, row[0]);
		ZBX_DBROW2UINT64(proxyid, row[1]);
		ZBX_DBROW2UINT64(revision, row[2]);
		ZBX_DBROW2UINT64(hostproxyid, row[3]);
		ZBX_STR2UCHAR(monitored_by, row[4]);
		ZBX_DBROW2UINT64(proxy_groupid, row[5]);

		if (HOST_MONITORED_BY_PROXY_GROUP != monitored_by || 0 == proxy_groupid ||
				NULL == (group = (zbx_pg_group_t *)zbx_hashset_search(&cache->groups, &proxy_groupid)))
		{
			/* remove hostmap if host is not monitored by proxy group */
			if (0 != proxyid)
				pg_cache_set_host_proxy(cache, hostid, 0);

			continue;
		}

		zbx_hashset_insert(&group->hostids, &hostid, sizeof(hostid));

		if (0 == proxyid)
		{
			zbx_vector_uint64_append(&group->unassigned_hostids, hostid);
			continue;
		}

		if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &proxyid)) ||
				group != proxy->group)
		{
			zbx_vector_uint64_append(&group->unassigned_hostids, hostid);
			pg_cache_set_host_proxy(cache, hostid, 0);
			continue;
		}

		zbx_pg_host_t	host_local = {
				.hostid = hostid,
				.proxyid = proxyid,
				.revision = revision,
				.hostproxyid = hostproxyid
			};

		zbx_pg_host_ref_t	ref_local;

		ref_local.host = (zbx_pg_host_t *)zbx_hashset_insert(&cache->hostmap, &host_local, sizeof(host_local));

		zbx_hashset_insert(&proxy->hosts, &ref_local, sizeof(ref_local));

		if (group->hostmap_revision < revision)
			group->hostmap_revision = revision;
	}

	pg_cache_unlock(cache);

	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update configuration and re-calculate proxy group/proxy statuses  *
 *                                                                            *
 ******************************************************************************/
static void	pgm_update(zbx_pg_cache_t *cache)
{
	int			now, flags;
	zbx_dc_um_handle_t	*um_handle;

	um_handle = zbx_dc_open_user_macros();

	pg_cache_lock(cache);

	if (SUCCEED == pg_cache_update_groups(cache))
		flags = ZBX_PG_PROXY_FETCH_FORCE;
	else
		flags = ZBX_PG_PROXY_FETCH_REVISION;

	pg_cache_update_proxies(cache, flags);

	now = (int)time(NULL);

	pg_cache_update_proxy_state(cache, um_handle, now);
	pg_cache_update_group_state(cache, um_handle, now);

	pg_cache_unlock(cache);

	zbx_dc_close_user_macros(um_handle);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush proxy group updates to database                             *
 *                                                                            *
 ******************************************************************************/
static void	pgm_db_flush_group_updates(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_pg_update_t *group_updates)
{
	for (int i = 0; i < group_updates->values_num; i++)
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update proxy_group_rtdata set state=%d where proxy_groupid=" ZBX_FS_UI64 ";\n",
				group_updates->values[i].state, group_updates->values[i].objectid);
	}

	zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
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
		if (0 == (proxies->values[i].flags & ZBX_PG_PROXY_UPDATE_STATE))
			continue;

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update proxy_rtdata set state=%d where proxyid=" ZBX_FS_UI64 ";\n",
				proxies->values[i].state, proxies->values[i].objectid);

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
	for (int i = 0; i < hosts->values_num; i += PGM_INSERT_BATCH_SIZE)
	{
		int	size = hosts->values_num - i;

		if (PGM_INSERT_BATCH_SIZE < size)
			size = PGM_INSERT_BATCH_SIZE;

		pgm_db_flush_host_proxy_insert_batch(hosts->values + i, size);

		zabbix_log(LOG_LEVEL_DEBUG, "assigned hostid " ZBX_FS_UI64 " to proxyid " ZBX_FS_UI64,
				hosts->values[i].hostid, hosts->values[i].proxyid);
	}
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
static void	pgm_rebalance_and_flush_updates(zbx_pg_cache_t *cache)
{
	zbx_vector_pg_update_t	group_updates, proxy_updates;
	zbx_vector_pg_host_t	hosts_new, hosts_mod, hosts_del;
	zbx_vector_uint64_t	groupids;
	zbx_uint64_t		hostmap_revision;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_pg_update_create(&group_updates);
	zbx_vector_pg_update_create(&proxy_updates);
	zbx_vector_pg_host_create(&hosts_new);
	zbx_vector_pg_host_create(&hosts_mod);
	zbx_vector_pg_host_create(&hosts_del);
	zbx_vector_uint64_create(&groupids);

	um_handle = zbx_dc_open_user_macros();
	pg_cache_lock(cache);

	hostmap_revision = cache->hostmap_revision;

	pg_cache_rebalance_groups(cache, um_handle);
	pg_cache_get_group_and_proxy_updates(cache, &group_updates, &proxy_updates);
	pg_cache_get_hostmap_updates(cache, &hosts_new, &hosts_mod, &hosts_del, &groupids);
	pg_cache_add_new_hostmaps(cache, &hosts_new, &groupids);
	pg_cache_add_deleted_hostmaps(cache, &hosts_del);
	pg_cache_unlock(cache);
	zbx_dc_close_user_macros(um_handle);

	if (0 != group_updates.values_num || 0 != proxy_updates.values_num || 0 != hosts_new.values_num ||
			0 != hosts_mod.values_num || 0 != hosts_del.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0;
		int	ret;

		do
		{
			size_t	sql_offset = 0;

			zbx_db_begin();

			pgm_db_flush_group_updates(&sql, &sql_alloc, &sql_offset, &group_updates);
			pgm_db_flush_proxy_updates(&sql, &sql_alloc, &sql_offset, &proxy_updates);
			pgm_db_flush_host_proxy_updates(&sql, &sql_alloc, &sql_offset, &hosts_mod);
			pgm_db_flush_host_proxy_deletes(&sql, &sql_alloc, &sql_offset, &hosts_del);

			(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

			pgm_db_flush_host_proxy_inserts(&hosts_new);

			if (hostmap_revision != cache->hostmap_revision)
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
	zbx_vector_pg_update_destroy(&proxy_updates);
	zbx_vector_pg_update_destroy(&group_updates);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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

	pgm_update(&cache);
	pgm_db_get_hosts(&cache);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		pg_cache_dump(&cache);

	time_update = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(info->process_type), info->process_num);

	while (ZBX_IS_RUNNING())
	{
		double	time_now;

		time_now = zbx_time();

		if (PG_STATE_CHECK_INTERVAL < time_now - time_update)
		{
			pgm_update(&cache);
			time_update = time_now;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		zbx_sleep_loop(info, 1);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		if (0 != cache.group_updates.values_num)
			pgm_rebalance_and_flush_updates(&cache);
	}

	pg_service_destroy(&pgs);
	zbx_db_close();

	pg_cache_destroy(&cache);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(info->process_type), info->process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
