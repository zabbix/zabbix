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

#define PGM_STATUS_CHECK_INTERVAL	5

static void	pgm_init(zbx_pg_cache_t *cache)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_uint64_t	map_revision = 0;

	result = zbx_db_select("select nextid from ids where table_name='host_group' and field_name='revision'");

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_DBROW2UINT64(map_revision, row[0]);

	zbx_db_free_result(result);

	pg_cache_init(cache, map_revision);
}

static void	pgm_dc_get_groups(zbx_pg_cache_t *cache)
{
	zbx_uint64_t	old_revision = cache->groups_revision;

	if (SUCCEED != zbx_dc_get_proxy_groups(&cache->groups, &cache->groups_revision))
		return;

	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*group;

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 == group->sync_revision)
		{
			pg_group_clear(group);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		if (old_revision >= group->revision)
			continue;

		pg_cache_queue_group_update(cache, group);
	}
}

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

static void	pgm_db_get_proxies(zbx_pg_cache_t *cache)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	int		clock = 0;
	zbx_pg_proxy_t *proxy;

	result = zbx_db_select("select p.proxyid,p.proxy_groupid,rt.lastaccess"
				" from proxy p,proxy_rtdata rt"
				" where proxy_groupid is not null"
					" and p.proxyid=rt.proxyid");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	proxyid, proxy_groupid;
		zbx_pg_group_t	*group;

		ZBX_DBROW2UINT64(proxyid, row[0]);
		ZBX_DBROW2UINT64(proxy_groupid, row[1]);

		if (NULL == (group = (zbx_pg_group_t *)zbx_hashset_search(&cache->groups, &proxy_groupid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		/* the proxy lastacess is temporary stored in it's firstaccess */
		proxy = pg_cache_group_add_proxy(cache, group, proxyid, atoi(row[2]));

		if (proxy->firstaccess < clock)
			proxy->firstaccess = clock;
	}
	zbx_db_free_result(result);

	/* calculate proxy status by finding the highest proxy lastaccess time */
	/* and using it as current timestamp                                   */

	zbx_hashset_iter_t	iter;
	zbx_hashset_iter_reset(&cache->proxies, &iter);

	while (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_iter_next(&iter)))
	{
		if (clock - proxy->firstaccess >= proxy->group->failover_delay)
			proxy->status = ZBX_PG_PROXY_STATUS_OFFLINE;
		else
			proxy->status = ZBX_PG_PROXY_STATUS_ONLINE;

		proxy->firstaccess = 0;
	}
}

static void	pgm_db_get_map(zbx_pg_cache_t *cache)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;

	result = zbx_db_select("select hostproxyid,hostid,proxyid,revision from host_proxy");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostproxyid, hostid, proxyid, revision;
		zbx_pg_proxy_t	*proxy;

		ZBX_DBROW2UINT64(proxyid, row[2]);

		if (NULL == (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&cache->proxies, &proxyid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_DBROW2UINT64(hostproxyid, row[0]);
		ZBX_DBROW2UINT64(hostid, row[1]);
		ZBX_STR2UINT64(revision, row[3]);

		zbx_pg_host_t	host_local = {
				.hostid = hostid,
				.hostproxyid = hostproxyid,
				.proxyid = proxyid,
				.revision = revision
			}, *host;

		host = (zbx_pg_host_t *)zbx_hashset_insert(&cache->links, &host_local, sizeof(host_local));
		zbx_vector_pg_host_ptr_append(&proxy->hosts, host);

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
			if (NULL == zbx_hashset_search(&cache->links, &group->hostids.values[i]))
				zbx_vector_uint64_append(&group->new_hostids, group->hostids.values[i]);
		}
	}
}

static void	pgm_update_status(zbx_pg_cache_t *cache)
{
	pg_cache_lock(cache);
	zbx_dc_get_group_proxy_lastaccess(&cache->proxies);

	zbx_hashset_iter_t	iter;
	zbx_pg_proxy_t		*proxy;
	int			now;

	pg_cache_lock(cache);

	now = time(NULL);

	zbx_hashset_iter_reset(&cache->proxies, &iter);
	while (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_iter_next(&iter)))
	{
		int	status = ZBX_PG_PROXY_STATUS_UNKNOWN;

		if (now - proxy->lastaccess >= proxy->group->failover_delay)
		{
			if (now - cache->startup_time >= proxy->group->failover_delay)
			{
				status = ZBX_PG_PROXY_STATUS_OFFLINE;
				proxy->firstaccess = 0;
			}
		}
		else
		{
			if (0 == proxy->firstaccess)
				proxy->firstaccess = proxy->lastaccess;

			if (now - proxy->firstaccess >= proxy->group->failover_delay)
				status = ZBX_PG_PROXY_STATUS_ONLINE;
		}

		if (ZBX_PG_PROXY_STATUS_UNKNOWN == status || proxy->status == status)
			continue;

		proxy->status = status;
		pg_cache_queue_group_update(cache, proxy->group);
	}

	for (int i = 0; i < cache->updates.values_num; i++)
	{
		zbx_pg_group_t	*group = cache->updates.values[i];
		int		online = 0, healthy = 0;

		for (int j = 0; j < group->proxies.values_num; j++)
		{
			if (ZBX_PG_PROXY_STATUS_ONLINE == group->proxies.values[i]->status)
			{
				online++;

				if (now - group->proxies.values[i]->lastaccess + PGM_STATUS_CHECK_INTERVAL <
						group->failover_delay)
				{
					healthy++;
				}
			}
		}

		int	status;

		switch (group->status)
		{
			case ZBX_PG_GROUP_STATUS_UNKNOWN:
				status = ZBX_PG_GROUP_STATUS_ONLINE;
				ZBX_FALLTHROUGH;
			case ZBX_PG_GROUP_STATUS_ONLINE:
				if (group->min_online > healthy)
					status = ZBX_PG_GROUP_STATUS_DECAY;
				break;
			case ZBX_PG_GROUP_STATUS_OFFLINE:
				if (group->min_online <= online)
					status = ZBX_PG_GROUP_STATUS_RECOVERY;
				break;
			case ZBX_PG_GROUP_STATUS_RECOVERY:
				if (group->min_online > healthy)
				{
					status = ZBX_PG_GROUP_STATUS_DECAY;
				}
				else if (now - group->status_time > group->failover_delay ||
						group->proxies.values_num == online)
				{
					status = ZBX_PG_GROUP_STATUS_RECOVERY;
				}
				break;
			case ZBX_PG_GROUP_STATUS_DECAY:
				if (group->min_online <= healthy)
					status = ZBX_PG_GROUP_STATUS_ONLINE;
				else if (group->min_online > online)
					status = ZBX_PG_GROUP_STATUS_OFFLINE;
				break;
		}

		if (status != group->status)
		{
			group->status = status;
			group->status_time = now;
			group->flags = ZBX_PG_GROUP_UPDATE_STATUS;
		}
	}

	pg_cache_unlock(cache);
}

static void	pgm_flush_group_updates(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_pg_update_t *groups)
{
	for (int i = 0; i < groups->values_num; i++)
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update proxy_group set status=%d where proxy_groupid=" ZBX_FS_UI64 ";\n",
				groups->values[i].status, groups->values[i].proxy_groupid);

		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
	}
}

static void	pgm_flush_host_proxy_updates(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_pg_host_t *hosts)
{
	for (int i = 0; i < hosts->values_num; i++)
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update host_proxy set proxyid=" ZBX_FS_UI64 ",revision=" ZBX_FS_UI64
					" where hostid=" ZBX_FS_UI64 ";\n",
				hosts->values[i].proxyid, hosts->values[i].revision, hosts->values[i].hostid);

		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
	}
}

static void	pgm_flush_host_proxy_deletes(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_pg_host_t *hosts)
{
	if (0 == hosts->values_num)
		return;

	zbx_vector_uint64_t	hostids;

	zbx_vector_uint64_create(&hostids);

	for (int i = 0; i < hosts->values_num; i++)
		zbx_vector_uint64_append(&hostids, hosts->values[i].hostid);

	zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "delete from host_group where ");
	zbx_db_add_condition_alloc(sql, sql_alloc, sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ";\n");

	zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

	zbx_vector_uint64_destroy(&hostids);
}

static void	pgm_flush_host_proxy_inserts(zbx_vector_pg_host_t *hosts)
{
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "host_proxy", "hostproxyid", "hostid", "proxyid", "revision", NULL);

	for (int i = 0; i < hosts->values_num; i++)
	{
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), hosts->values[i].hostid, hosts->values[i].proxyid,
				hosts->values[i].revision);
	}

	zbx_db_insert_autoincrement(&db_insert, "hostproxyid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

static void	pgm_flush_updates(zbx_pg_cache_t *cache)
{
	zbx_vector_pg_update_t	groups;
	zbx_vector_pg_host_t	hosts_new, hosts_mod, hosts_del;

	zbx_vector_pg_update_create(&groups);
	zbx_vector_pg_host_create(&hosts_new);
	zbx_vector_pg_host_create(&hosts_mod);
	zbx_vector_pg_host_create(&hosts_del);

	pg_cache_get_updates(cache, &groups, &hosts_new, &hosts_mod, &hosts_del);

	if (0 != groups.values_num || 0 != hosts_new.values_num || 0 != hosts_mod.values_num ||
			0 != hosts_del.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0;

		do
		{
			size_t	sql_offset = 0;

			zbx_db_begin();

			zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

			pgm_flush_group_updates(&sql, &sql_alloc, &sql_offset, &groups);
			pgm_flush_host_proxy_updates(&sql, &sql_alloc, &sql_offset, &hosts_mod);
			pgm_flush_host_proxy_deletes(&sql, &sql_alloc, &sql_offset, &hosts_del);

			zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

			if (16 < sql_offset)
				zbx_db_execute("%s", sql);

			pgm_flush_host_proxy_inserts(&hosts_new);

		}
		while (ZBX_DB_DOWN == zbx_db_commit());

		zbx_free(sql);
	}

	zbx_vector_pg_host_destroy(&hosts_del);
	zbx_vector_pg_host_destroy(&hosts_mod);
	zbx_vector_pg_host_destroy(&hosts_new);
	zbx_vector_pg_update_destroy(&groups);
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

	pgm_dc_get_groups(&cache);
	pgm_db_get_hosts(&cache);
	pgm_db_get_proxies(&cache);
	pgm_db_get_map(&cache);

	/* WDN: debug */
	pg_cache_dump(&cache);

	time_update = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(info->process_type), info->process_num);

	while (ZBX_IS_RUNNING())
	{
		double	time_now;

		time_now = zbx_time();

		if (PGM_STATUS_CHECK_INTERVAL >= time_update - time_now)
		{
			pgm_dc_get_groups(&cache);
			pgm_update_status(&cache);

			time_update = time_now;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		zbx_sleep_loop(info, 1);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		if (0 != cache.updates.values_num)
			pgm_flush_updates(&cache);
	}

	pg_service_destroy(&pgs);
	zbx_db_close();

	pg_cache_destroy(&cache);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(info->process_type), info->process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
