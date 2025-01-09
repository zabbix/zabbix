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

#include "lld_worker.h"
#include "lld.h"
#include "lld_protocol.h"

#include "../events/events.h"

#include "zbxtimekeeper.h"
#include "zbxnix.h"
#include "zbxlog.h"
#include "zbxipcservice.h"
#include "zbxself.h"
#include "zbxtime.h"
#include "zbx_item_constants.h"
#include "zbxstr.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"

/******************************************************************************
 *                                                                            *
 * Purpose: registers LLD worker with LLD manager                             *
 *                                                                            *
 * Parameters: socket - [IN] connections socket                               *
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
 * Purpose: Processes LLD task and updates rule state/error in configuration  *
 *          cache and database.                                               *
 *                                                                            *
 * Parameters: message - [IN] message with LLD request                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_task(const zbx_ipc_message_t *message)
{
	zbx_uint64_t		itemid, hostid, lastlogsize;
	char			*value, *error;
	zbx_timespec_t		ts;
	zbx_item_diff_t		diff;
	zbx_dc_item_t		item;
	int			errcode, mtime;
	unsigned char		state, meta;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_lld_deserialize_item_value(message->data, &itemid, &hostid, &value, &ts, &meta, &lastlogsize, &mtime,
			&error);

	zbx_dc_config_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED != errcode)
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "processing discovery rule:" ZBX_FS_UI64, itemid);

	diff.flags = ZBX_FLAGS_ITEM_DIFF_UNSET;

	if (NULL != error || NULL != value)
	{
		if (NULL == error && SUCCEED == lld_process_discovery_rule(itemid, value, &error))
			state = ITEM_STATE_NORMAL;
		else
			state = ITEM_STATE_NOTSUPPORTED;

		if (state != item.state)
		{
			diff.state = state;
			diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE;

			if (ITEM_STATE_NORMAL == state)
			{
				zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s:%s\" became supported",
						item.host.host, item.key_orig);

				zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE, itemid, &ts,
						ITEM_STATE_NORMAL, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL,
						NULL, NULL);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s:%s\" became not supported: %s",
						item.host.host, item.key_orig, error);

				zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE, itemid, &ts,
						ITEM_STATE_NOTSUPPORTED, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0,
						NULL, NULL, error);
			}

			zbx_db_begin();
			zbx_process_events(NULL, NULL, NULL);
			zbx_db_commit();

			zbx_clean_events();
		}

		/* with successful LLD processing LLD error will be set to empty string */
		if (NULL != error && 0 != strcmp(error, ZBX_NULL2EMPTY_STR(item.error)))
		{
			diff.error = error;
			diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;
		}
	}

	if (0 != meta)
	{
		if (item.lastlogsize != lastlogsize)
		{
			diff.lastlogsize = lastlogsize;
			diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE;
		}
		if (item.mtime != mtime)
		{
			diff.mtime = mtime;
			diff.flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME;
		}
	}

	if (ZBX_FLAGS_ITEM_DIFF_UNSET != diff.flags)
	{
		zbx_vector_item_diff_ptr_t	diffs;
		char				*sql = NULL;
		size_t				sql_alloc = 0, sql_offset = 0;

		zbx_vector_item_diff_ptr_create(&diffs);
		diff.itemid = itemid;
		zbx_vector_item_diff_ptr_append(&diffs, &diff);

		zbx_db_save_item_changes(&sql, &sql_alloc, &sql_offset, &diffs, ZBX_FLAGS_ITEM_DIFF_UPDATE_DB);

		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

		zbx_dc_config_items_apply_changes(&diffs);

		zbx_vector_item_diff_ptr_destroy(&diffs);
		zbx_free(sql);
	}

	zbx_dc_config_clean_items(&item, &errcode, 1);
out:
	zbx_free(value);
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

ZBX_THREAD_ENTRY(lld_worker_thread, args)
{
	char			*error = NULL;
	zbx_ipc_socket_t	lld_socket;
	zbx_ipc_message_t	message;
	double			time_stat, time_idle = 0, time_now, time_read;
	zbx_uint64_t		processed_num = 0;
	zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&lld_socket, ZBX_IPC_SERVICE_LLD, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to lld manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	lld_register_worker(&lld_socket);

	time_stat = zbx_time();

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */
		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [processed " ZBX_FS_UI64 " LLD rules, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
		}
#undef STAT_INTERVAL
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		if (SUCCEED != zbx_ipc_socket_read(&lld_socket, &message))
		{
			if (ZBX_IS_RUNNING())
				zabbix_log(LOG_LEVEL_CRIT, "cannot read LLD manager service request");
			exit(EXIT_FAILURE);
		}
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		time_read = zbx_time();
		time_idle += time_read - time_now;
		zbx_update_env(get_process_type_string(process_type), time_read);

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

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
