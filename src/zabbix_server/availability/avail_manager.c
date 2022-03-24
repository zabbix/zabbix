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

#include "avail_manager.h"

#include "log.h"
#include "zbxself.h"
#include "zbxavailability.h"
#include "zbxipcservice.h"
#include "daemon.h"
#include "sighandler.h"
#include "dbcache.h"
#include "avail_protocol.h"


extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;
static sigset_t				orig_mask;

#define ZBX_AVAILABILITY_MANAGER_DELAY			1
#define ZBX_AVAILABILITY_MANAGER_FLUSH_DELAY_SEC	5

static int	interface_availability_compare(const void *d1, const void *d2)
{
	const zbx_interface_availability_t	*ia1 = *(const zbx_interface_availability_t **)d1;
	const zbx_interface_availability_t	*ia2 = *(const zbx_interface_availability_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ia1->interfaceid, ia2->interfaceid);

	return ia1->id - ia2->id;
}

ZBX_THREAD_ENTRY(availability_manager_thread, args)
{
	zbx_ipc_service_t		service;
	char				*error = NULL;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	int				ret, processed_num = 0;
	double				time_stat, time_idle = 0, time_now, time_flush, sec;
	zbx_vector_availability_ptr_t	interface_availabilities;
	zbx_timespec_t			timeout = {ZBX_AVAILABILITY_MANAGER_DELAY, 0};

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
				server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_AVAILABILITY, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start availability manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	/* initialize statistics */
	time_stat = zbx_time();
	time_flush = time_stat;

	zbx_vector_availability_ptr_create(&interface_availabilities);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [queued %d, processed %d values, idle "
					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
					get_process_type_string(process_type), process_num,
					interface_availabilities.values_num, processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, &timeout, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			zbx_availability_deserialize(message->data, message->size, &interface_availabilities);
			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (ZBX_AVAILABILITY_MANAGER_FLUSH_DELAY_SEC < time_now - time_flush)
		{
			time_flush = time_now;

			if (0 == interface_availabilities.values_num)
				continue;

			zbx_block_signals(&orig_mask);
			zbx_vector_availability_ptr_sort(&interface_availabilities, interface_availability_compare);
			zbx_db_update_interface_availabilities(&interface_availabilities);
			zbx_unblock_signals(&orig_mask);

			processed_num = interface_availabilities.values_num;
			zbx_vector_availability_ptr_clear_ext(&interface_availabilities,
					zbx_interface_availability_free);
		}
	}

	zbx_block_signals(&orig_mask);
	if (0 != interface_availabilities.values_num)
	{
		zbx_vector_availability_ptr_sort(&interface_availabilities, interface_availability_compare);
		zbx_db_update_interface_availabilities(&interface_availabilities);
	}
	DBclose();
	zbx_unblock_signals(&orig_mask);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}
