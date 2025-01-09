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

#include "zbxservice.h"

#include "zbxipcservice.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"

void	zbx_event_severity_free(zbx_event_severity_t *event_severity)
{
	zbx_free(event_severity);
}

ZBX_PTR_VECTOR_IMPL(db_service, zbx_db_service *)
ZBX_PTR_VECTOR_IMPL(event_severity_ptr, zbx_event_severity_t *)

void	zbx_service_flush(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size)
{
	static zbx_ipc_socket_t	socket;

	/* each process has a permanent connection to service manager */
	if (FAIL == zbx_ipc_socket_connected(&socket))
	{
		char	*error = NULL;

		if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_SERVICE, SEC_PER_MIN, &error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot connect to service manager service: %s", error);
			exit(EXIT_FAILURE);
		}
	}

	if (FAIL == zbx_ipc_socket_write(&socket, code, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to service manager service");
		exit(EXIT_FAILURE);
	}
}

void	zbx_service_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size, zbx_ipc_message_t *response)
{
	char			*error = NULL;
	static zbx_ipc_socket_t	socket = {0};

	/* each process has a permanent connection to service manager */
	if (0 == socket.fd && FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_SERVICE, SEC_PER_MIN,
			&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to service: %s", error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_socket_write(&socket, code, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to service");
		exit(EXIT_FAILURE);
	}

	if (NULL != response && FAIL == zbx_ipc_socket_read(&socket, response))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot receive data from service");
		exit(EXIT_FAILURE);
	}
}

void	zbx_service_reload_cache(void)
{
	char			*error = NULL;
	zbx_ipc_socket_t	socket;

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_SERVICE, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to service: %s", error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_IPC_SERVICE_RELOAD_CACHE, NULL, 0))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to service");
		exit(EXIT_FAILURE);
	}

	zbx_ipc_socket_close(&socket);
}
