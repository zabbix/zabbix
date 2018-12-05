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


ZBX_THREAD_ENTRY(lld_worker_thread, args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	char			*error = NULL;
	zbx_ipc_socket_t	lld_socket;
	zbx_ipc_message_t	message;
	double			time_stat, time_idle = 0, time_now, time_read;

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
			zbx_setproctitle("%s #%d [TODO: message, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
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
				/* TODO: process lld task */
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	DBclose();

	zbx_ipc_socket_close(&lld_socket);
}
