/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "connector_manager.h"

#include "log.h"
#include "zbxself.h"
#include "zbxconnector.h"
#include "zbxipcservice.h"
#include "zbxnix.h"
#include "zbxnum.h"
#include "zbxtime.h"

extern unsigned char			program_type;
static sigset_t				orig_mask;

typedef struct
{
	zbx_uint64_t	hostid;
	int		lastaccess;
}
zbx_active_avail_proxy_t;

#define ZBX_AVAILABILITY_MANAGER_DELAY				1
#define ZBX_AVAILABILITY_MANAGER_FLUSH_DELAY_SEC		5
#define ZBX_AVAILABILITY_MANAGER_ACTIVE_HEARTBEAT_DELAY_SEC	10
#define ZBX_AVAILABILITY_MANAGER_PROXY_ACTIVE_AVAIL_DELAY_SEC	(SEC_PER_MIN * 10)
#define ZBX_AVAILABILITY_MANAGER_PROXY_ACTIVE_AUTOFLUSH_DELAY	(SEC_PER_MIN * 5)

ZBX_THREAD_ENTRY(connector_manager_thread, args)
{
	zbx_ipc_service_t		service;
	char				*error = NULL;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	int				ret, processed_num = 0;
	double				time_stat, time_idle = 0, time_now, time_flush, sec, last_proxy_flush;
	zbx_timespec_t			timeout = {ZBX_AVAILABILITY_MANAGER_DELAY, 0};
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
				server_num, get_process_type_string(process_type), process_num);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_CONNECTOR, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start availability manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	/* initialize statistics */
	time_stat = last_proxy_flush = zbx_time();
	time_flush = time_stat;

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [queued %d, processed %d values, idle "
					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
					get_process_type_string(process_type), process_num,
					0, processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, &timeout, &client, &message);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_CONNECTOR_REQUEST:
					//zbx_availability_deserialize(message->data, message->size,
					//		&interface_availabilities);
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zbx_block_signals(&orig_mask);
	zbx_unblock_signals(&orig_mask);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}
