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

typedef struct
{
	zbx_hashset_t			groups;
	zbx_uint64_t			group_revision;
	zbx_vector_pgm_group_ptr_t	updates;
}
zbx_pgm_t;

static void	pgm_group_clear(zbx_pgm_group_t *group);

static void	pgm_init(zbx_pgm_t *pgm)
{
	zbx_hashset_create(&pgm->groups, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	pgm->group_revision = 0;

	zbx_vector_pgm_group_ptr_create(&pgm->updates);
}

static void	pgm_destroy(zbx_pgm_t *pgm)
{
	zbx_vector_pgm_group_ptr_destroy(&pgm->updates);

	zbx_hashset_iter_t	iter;
	zbx_pgm_group_t		*group;

	zbx_hashset_iter_reset(&pgm->groups, &iter);
	while (NULL != (group = (zbx_pgm_group_t *)zbx_hashset_iter_next(&iter)))
		pgm_group_clear(group);

	zbx_hashset_destroy(&pgm->groups);

}

static void	pgm_group_clear(zbx_pgm_group_t *group)
{
	for (int i = 0; i < group->proxies.values_num; i++)
		group->proxies.values[i]->group = NULL;

	zbx_vector_pgm_proxy_ptr_destroy(&group->proxies);
	zbx_vector_uint64_destroy(&group->hostids);
}

static void	pgm_queue_update(zbx_pgm_t *pgm, zbx_pgm_group_t *group)
{
	int	i;

	for (i = 0; i < pgm->updates.values_num; i++)
	{
		if (pgm->updates.values[i] == group)
			return;
	}

	zbx_vector_pgm_group_ptr_append(&pgm->updates, group);
}

static void	pgm_sync_config(zbx_pgm_t *pgm)
{
	zbx_uint64_t	old_revision = pgm->group_revision;

	if (SUCCEED != zbx_dc_get_proxy_groups(&pgm->groups, &pgm->group_revision))
		return;

	zbx_hashset_iter_t	iter;
	zbx_pgm_group_t		*group;

	zbx_hashset_iter_reset(&pgm->groups, &iter);
	while (NULL != (group = (zbx_pgm_group_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 == group->sync_revision)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "[WDN] remove proxy group " ZBX_FS_UI64, group->proxy_groupid);
			pgm_group_clear(group);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		if (old_revision >= group->revision)
			continue;

		zabbix_log(LOG_LEVEL_DEBUG, "[WDN] update proxy group " ZBX_FS_UI64, group->proxy_groupid);
		pgm_queue_update(pgm, group);
	}
}

/*
 * main process loop
 */

ZBX_THREAD_ENTRY(pg_manager_thread, args)
{
	zbx_pg_service_t	pgs;
	char			*error = NULL;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	zbx_pgm_t		pgm;

	zbx_setproctitle("%s #%d starting", get_process_type_string(info->process_type), info->process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			info->server_num, get_process_type_string(info->process_type), info->process_num);

	if (FAIL == pg_service_init(&pgs, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start proxy group manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	pgm_init(&pgm);
	pgm_sync_config(&pgm);

	zbx_setproctitle("%s #%d started", get_process_type_string(info->process_type), info->process_num);

	while (ZBX_IS_RUNNING())
	{
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		zbx_sleep_loop(info, 1);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		pgm_sync_config(&pgm);

		/* TODO: perform reassignment */
		zbx_vector_pgm_group_ptr_clear(&pgm.updates);
	}

	pg_service_destroy(&pgs);
	zbx_db_close();

	pgm_destroy(&pgm);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(info->process_type), info->process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
