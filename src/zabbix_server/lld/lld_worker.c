/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"
#include "db.h"
#include "log.h"
#include "zbxipcservice.h"
#include "zbxself.h"
#include "dbcache.h"
#include "proxy.h"
#include "../events.h"

#include "lld_worker.h"
#include "lld_protocol.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: lld_register_worker                                              *
 *                                                                            *
 * Purpose: registers lld worker with lld manager                             *
 *                                                                            *
 * Parameters: socket - [IN] the connections socket                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_register_worker(zbx_ipc_socket_t *socket)
{
	pid_t	ppid;

	ppid = getppid();

	zbx_ipc_socket_write(socket, ZBX_IPC_LLD_REGISTER, (unsigned char *)&ppid, sizeof(ppid));
}

/******************************************************************************
 *                                                                            *
 * Function: lld_process_task                                                 *
 *                                                                            *
 * Purpose: processes lld task and updates rule state/error in configuration  *
 *          cache and database                                                *
 *                                                                            *
 * Parameters: message - [IN] the message with LLD request                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_task(zbx_ipc_message_t *message)
{
	const char		*__function_name = "lld_process_task";

	zbx_uint64_t		itemid;
	char			*value, *error;
	zbx_timespec_t		ts;
	zbx_item_diff_t		diff;
	DC_ITEM			item;
	int			errcode;
	unsigned char		state;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_lld_deserialize_item_value(message->data, &itemid, &value, &ts, &error);

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);
	if (SUCCEED != errcode)
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "processing discovery rule:" ZBX_FS_UI64, itemid);

	diff.flags = ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE | ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;

	if (NULL == error && SUCCEED == lld_process_discovery_rule(itemid, value, &error))
		state = ITEM_STATE_NORMAL;
	else
		state = ITEM_STATE_NOTSUPPORTED;

	diff.flags = ZBX_FLAGS_ITEM_DIFF_UNSET;

	if (state != item.state)
	{
		diff.state = state;
		diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE;

		if (ITEM_STATE_NORMAL == state)
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s:%s\" became supported", item.host.host,
					item.key_orig);

			zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE, itemid, &ts, ITEM_STATE_NORMAL,
					NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s:%s\" became  not supported: %s",
					item.host.host, item.key_orig, error);

			zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE, itemid, &ts, ITEM_STATE_NOTSUPPORTED,
					NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, error);
		}

		zbx_process_events(NULL, NULL);
		zbx_clean_events();
	}

	/* with successful LLD processing LLD error will be set to empty string */
	if (NULL != error && 0 != strcmp(error, item.error))
	{
		diff.error = error;
		diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;
	}

	if (ZBX_FLAGS_ITEM_DIFF_UNSET != diff.flags)
	{
		zbx_vector_ptr_t	diffs;
		char			*sql = NULL, delim = ' ';
		size_t			sql_alloc = 0, sql_offset = 0;

		zbx_vector_ptr_create(&diffs);
		diff.itemid = itemid;
		zbx_vector_ptr_append(&diffs, &diff);
		DCconfig_items_apply_changes(&diffs);
		zbx_vector_ptr_destroy(&diffs);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update items set");
		if (0 != (diff.flags & ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cstate=%d", delim, (int)diff.state);
			delim = ',';
		}
		if (0 != (diff.flags & ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR))
		{
			char	*error_esc;

			error_esc = DBdyn_escape_field("items", "error", diff.error);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cerror='%s'", delim, error_esc);
			zbx_free(error_esc);
		}
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where itemid=" ZBX_FS_UI64, itemid);
		DBexecute("%s", sql);
		zbx_free(sql);
	}

	zbx_free(value);
	zbx_free(error);

	DCconfig_clean_items(&item, &errcode, 1);
out:
	sleep(1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}


ZBX_THREAD_ENTRY(lld_worker_thread, args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	char			*error = NULL;
	zbx_ipc_socket_t	lld_socket;
	zbx_ipc_message_t	message;
	double			time_stat, time_idle = 0, time_now, time_read;
	zbx_uint64_t		processed_num = 0;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&lld_socket, ZBX_IPC_SERVICE_LLD, 10, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to lld manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	lld_register_worker(&lld_socket);

	time_stat = zbx_time();


	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	for (;;)
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [processed " ZBX_FS_UI64 " LLD rules, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		if (SUCCEED != zbx_ipc_socket_read(&lld_socket, &message))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot read LLD manager service request");
			exit(EXIT_FAILURE);
		}
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

		time_read = zbx_time();
		time_idle += time_read - time_now;
		zbx_update_env(time_read);

		switch (message.code)
		{
			case ZBX_IPC_LLD_TASK:
				lld_process_task(&message);
				zbx_ipc_socket_write(&lld_socket, ZBX_IPC_LLD_DONE, NULL, 0);
				processed_num++;
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	DBclose();

	zbx_ipc_socket_close(&lld_socket);
}
