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

#define ZBX_CONNECTOR_MANAGER_DELAY	1
#define ZBX_CONNECTOR_FLUSH_INTERVAL	1


static int	dbsync_item_rtname(void)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			deleted = 0;
	zbx_dc_um_handle_t	*um_handle;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	double			sec = zbx_time();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	um_handle = zbx_dc_open_user_macros();
	zbx_db_begin();

	result = zbx_db_select("select i.itemid,i.hostid,i.name,n.name_resolved,i.flags,h.status"
			" from items i,item_rtname n,hosts h"
			" where i.hostid=h.hostid and n.itemid=i.itemid and n.name_upper like '%%{$%%'"
			" order by n.itemid");

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zabbix_log(LOG_LEVEL_DEBUG, "fetch started");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	itemid, hostid;
		const char	*name_resolved_current;
		char		*name_resolved_new;

		if (ZBX_FLAG_DISCOVERY_PROTOTYPE == atoi(row[4]))
			continue;

		if (HOST_STATUS_TEMPLATE == atoi(row[5]))
			continue;

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
					" name_resolved='%s',name_resolved_upper=upper(name_resolved)"
					" where itemid=" ZBX_FS_UI64 ";\n",
					name_resolved_esc, itemid);
			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			zbx_free(name_resolved_esc);
		}

		zbx_free(name_resolved_new);
	}
	zbx_db_free_result(result);

	zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		zbx_db_execute("%s", sql);

	zbx_free(sql);
	zbx_dc_close_user_macros(um_handle);
	zbx_db_commit();

	zabbix_log(LOG_LEVEL_INFORMATION, "End of %s() in:" ZBX_FS_DBL, __func__, zbx_time() - sec);

	return deleted;
}

ZBX_THREAD_ENTRY(dbconfig_worker_thread, args)
{
#define DBCONFIG_WORKER_FLUSH_DELAY_SEC		1
	zbx_ipc_service_t			service;
	char					*error = NULL;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	int					ret, processed_num = 0, delay = DBCONFIG_WORKER_FLUSH_DELAY_SEC;
	double					sec = 0, time_flush;
	zbx_timespec_t				timeout = {ZBX_CONNECTOR_MANAGER_DELAY, 0};
	const zbx_thread_info_t			*info = &((zbx_thread_args_t *)args)->info;
	int					server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int					process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char				process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_vector_uint64_t			hostids;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
				server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	zabbix_increase_log_level();
	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_DBCONFIG_WORKER, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start connector manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	/* initialize statistics */
	time_flush = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_append(&hostids, 0);

	while (ZBX_IS_RUNNING())
	{
		if (delay < zbx_time() - time_flush)
		{
			zbx_setproctitle("%s [synced macros in " ZBX_FS_DBL " sec, syncing configuration]",
					get_process_type_string(process_type), sec);

			sec = zbx_time();

			if (0 != hostids.values_num)
			{
				zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
				zbx_vector_uint64_uniq(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

				if (0 == hostids.values[0])
					dbsync_item_rtname();

				zbx_vector_uint64_clear(&hostids);
			}

			time_flush = zbx_time();

			if ((sec = time_flush - sec) > delay)
				delay *= 2;
			else
				delay = DBCONFIG_WORKER_FLUSH_DELAY_SEC;

			zbx_setproctitle("%s [synced macros in " ZBX_FS_DBL " sec, idle %d sec]",
					get_process_type_string(process_type), sec, delay);
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, &timeout, &client, &message);
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
