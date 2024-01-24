/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxipcservice.h"
#include "zbxalgo.h"

static void	dbconfig_worker_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size)
{
	static zbx_ipc_socket_t	socket;

	/* configuration syncer process has a permanent connection to worker */
	if (0 == socket.fd)
	{
		char	*error = NULL;

		if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_DBCONFIG_WORKER, SEC_PER_MIN, &error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot connect to connector manager service: %s", error);
			exit(EXIT_FAILURE);
		}
	}

	if (FAIL == zbx_ipc_socket_write(&socket, code, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to connector manager service");
		exit(EXIT_FAILURE);
	}
}

void	zbx_dbconfig_worker_send_ids(const zbx_vector_uint64_t *hostids)
{
	if (0 != hostids->values_num)
	{
		unsigned char	*data = NULL;
		size_t		data_offset = 0;

		zbx_dbconfig_worker_serialize_ids(&data, &data_offset, hostids);
		dbconfig_worker_send(ZBX_IPC_DBCONFIG_WORKER_REQUEST, data, data_offset);

		free(data);
	}
}

