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

#include "zbxdbconfigworker.h"
#include "zbx_host_constants.h"

#include "zbxlog.h"
#include "zbxself.h"
#include "zbxipcservice.h"
#include "zbxnix.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxcacheconfig.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxtypes.h"

#define ZBX_CONNECTOR_MANAGER_DELAY	1
#define ZBX_CONNECTOR_FLUSH_INTERVAL	1
#define ZBX_DBSYNC_BATCH_SIZE		1000

static int	dbsync_item_rtname(zbx_vector_uint64_t *hostids, int *processed_num, int *updated_num,
		int *macro_used)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			deleted = 0;
	zbx_dc_um_handle_t	*um_handle;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	char			*sql_select = NULL;
	size_t			sql_select_alloc = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == hostids->values[0])
	{
		if (0 == *macro_used)
			zbx_vector_uint64_remove(hostids, 0);
		else
			hostids->values_num = 1;

		if (0 == hostids->values_num)
			goto out;
	}

	um_handle = zbx_dc_open_user_macros();

	zbx_db_begin();
	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (zbx_uint64_t *batch = hostids->values; batch < hostids->values + hostids->values_num;
			batch += ZBX_DBSYNC_BATCH_SIZE)
	{
		int	batch_size = MIN(ZBX_DBSYNC_BATCH_SIZE, hostids->values + hostids->values_num - batch);
		size_t	sql_select_offset = 0;

		zbx_strcpy_alloc(&sql_select, &sql_select_alloc, &sql_select_offset,
				"select i.itemid,i.hostid,i.name,ir.name_resolved"
				" from items i,item_rtname ir"
				" where i.name like '%%{$%%' and ir.itemid=i.itemid");
		if (0 != batch[0])
		{
			zbx_strcpy_alloc(&sql_select, &sql_select_alloc, &sql_select_offset, " and");
			zbx_db_add_condition_alloc(&sql_select, &sql_select_alloc, &sql_select_offset, "i.hostid",
					batch, batch_size);
		}
		zbx_strcpy_alloc(&sql_select, &sql_select_alloc, &sql_select_offset, " order by i.itemid");

		result = zbx_db_select("%s", sql_select);

		zabbix_log(LOG_LEVEL_DEBUG, "fetch started");

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	itemid, hostid;
			const char	*name_resolved_current;
			char		*name_resolved_new;

			(*processed_num)++;

			ZBX_STR2UINT64(itemid, row[0]);
			ZBX_STR2UINT64(hostid, row[1]);
			name_resolved_new = zbx_strdup(NULL, row[2]);
			name_resolved_current = row[3];

			(void)zbx_dc_expand_user_macros(um_handle, &name_resolved_new, &hostid, 1, NULL);

			if (0 != strcmp(name_resolved_current, name_resolved_new))
			{
				char	*name_resolved_esc;

				name_resolved_esc = zbx_db_dyn_escape_string(name_resolved_new);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update item_rtname set"
						" name_resolved='%s',name_resolved_upper=upper('%s')"
						" where itemid=" ZBX_FS_UI64 ";\n",
						name_resolved_esc, name_resolved_esc, itemid);
				zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

				zbx_free(name_resolved_esc);
				(*updated_num)++;
			}

			zbx_free(name_resolved_new);
		}
		zbx_db_free_result(result);
	}

	zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		zbx_db_execute("%s", sql);

	zbx_free(sql_select);
	zbx_free(sql);
	zbx_dc_close_user_macros(um_handle);
	zbx_db_commit();

	if (0 == hostids->values[0] || 0 != *processed_num)
		*macro_used = *processed_num;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return deleted;
}

ZBX_THREAD_ENTRY(dbconfig_worker_thread, args)
{
#define DBCONFIG_WORKER_FLUSH_DELAY_SEC		1
	zbx_ipc_service_t	service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	int			processed_num = 0, updated_num = 0, macro_used = 1;
	int			delay = DBCONFIG_WORKER_FLUSH_DELAY_SEC;
	double			sec = 0, time_flush;
	zbx_timespec_t		timeout = {ZBX_CONNECTOR_MANAGER_DELAY, 0};
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int			process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_vector_uint64_t	hostids;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
				server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_DBCONFIG_WORKER, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start connector manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
	zabbix_increase_log_level();
	/* initialize statistics */
	time_flush = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_append(&hostids, 0);

	while (ZBX_IS_RUNNING())
	{
		if (delay < zbx_time() - time_flush && 0 != hostids.values_num)
		{
			zbx_setproctitle("%s [synced %d, updated %d item names in " ZBX_FS_DBL " sec, syncing]",
					get_process_type_string(process_type), processed_num, updated_num, sec);
			processed_num = 0;
			updated_num = 0;
			sec = zbx_time();

			zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			dbsync_item_rtname(&hostids, &processed_num, &updated_num, &macro_used);

			zbx_vector_uint64_clear(&hostids);

			time_flush = zbx_time();

			if ((sec = time_flush - sec) > delay)
				delay *= 2;
			else
				delay = DBCONFIG_WORKER_FLUSH_DELAY_SEC;

			zbx_setproctitle("%s [synced %d, updated %d item names in " ZBX_FS_DBL " sec, idle]",
					get_process_type_string(process_type), processed_num, updated_num, sec);
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		zbx_ipc_service_recv(&service, &timeout, &client, &message);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		zbx_update_env(get_process_type_string(process_type), zbx_time());

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_DBCONFIG_WORKER_REQUEST:
					zbx_dbconfig_worker_deserialize_ids(message->data, message->size, &hostids);
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_ipc_service_close(&service);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}
