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

static void	pgm_dc_get_groups(zbx_pg_cache_t *cache)
{
	zbx_uint64_t	old_revision = cache->group_revision;

	if (SUCCEED != zbx_dc_get_proxy_groups(&cache->groups, &cache->group_revision))
		return;

	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*group;

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 == group->sync_revision)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "[WDN] remove proxy group " ZBX_FS_UI64, group->proxy_groupid);
			pg_group_clear(group);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		if (old_revision >= group->revision)
			continue;

		zabbix_log(LOG_LEVEL_DEBUG, "[WDN] update proxy group " ZBX_FS_UI64, group->proxy_groupid);
		pg_cache_queue_update(cache, group);
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

/*
 * main process loop
 */

ZBX_THREAD_ENTRY(pg_manager_thread, args)
{
#define PGM_UPDATE_DELAY	5

	zbx_pg_service_t	pgs;
	char			*error = NULL;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	zbx_pg_cache_t		cache;
	double			time_update = 0;

	zbx_setproctitle("%s #%d starting", get_process_type_string(info->process_type), info->process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			info->server_num, get_process_type_string(info->process_type), info->process_num);

	pg_cache_init(&cache);

	if (FAIL == pg_service_init(&pgs, &cache, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start proxy group manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	pgm_dc_get_groups(&cache);
	pgm_db_get_hosts(&cache);

	time_update = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(info->process_type), info->process_num);

	while (ZBX_IS_RUNNING())
	{
		double	time_now;

		time_now = zbx_time();

		if (PGM_UPDATE_DELAY >= time_update - time_now)
		{
			pgm_dc_get_groups(&cache);
			time_update = time_now;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		zbx_sleep_loop(info, 1);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		if (0 != cache.updates.values_num)
			pg_cache_process_updates(&cache);
	}

	pg_service_destroy(&pgs);
	zbx_db_close();

	pg_cache_destroy(&cache);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(info->process_type), info->process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

#undef PGM_UPDATE_DELAY
}
