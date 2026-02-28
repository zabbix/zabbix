/*
** Copyright (C) 2001-2026 Zabbix SIA
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
#include "zbxcep_client.h"
#include "zbxserialize.h"

ZBX_PTR_VECTOR_IMPL(db_service, zbx_db_service *)

static	zbx_ipc_socket_t	*service_client_socket(void)
{
	static ZBX_THREAD_LOCAL zbx_ipc_socket_t	socket;

	if (FAIL == zbx_ipc_socket_connected(&socket))
	{
		char	*error = NULL;

		if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_SERVICE, SEC_PER_MIN, &error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot connect to service manager service: %s", error);
			zbx_exit(EXIT_FAILURE);
		}
	}

	return &socket;
}

void	zbx_service_flush(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size)
{
	if (FAIL == zbx_ipc_socket_write(service_client_socket(), code, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to service manager service");
		zbx_exit(EXIT_FAILURE);
	}
}

void	zbx_service_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size, zbx_ipc_message_t *response)
{
	if (FAIL == zbx_ipc_socket_write(service_client_socket(), code, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to service");
		zbx_exit(EXIT_FAILURE);
	}

	if (NULL != response && FAIL == zbx_ipc_socket_read(service_client_socket(), response))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot receive data from service");
		zbx_exit(EXIT_FAILURE);
	}
}

void	zbx_service_send_event_tags(zbx_event_tags_t * const *events, int events_num)
{
	unsigned char		*data;
	zbx_uint32_t		data_alloc = 4096, data_offset = 0;

	data = (unsigned char *)zbx_malloc(NULL, data_alloc);
	data_offset = zbx_serialize_value(data, events_num);

	for (int i = 0; i < events_num; i++)
		zbx_buffer_serialize_event_tags(&data, &data_alloc, &data_offset, events[i]);

	if (FAIL == zbx_ipc_socket_write(service_client_socket(), ZBX_IPC_SERVICE_SERVICE_PROBLEMS_TAGS, data,
			data_offset))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send event tag update message to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

void	zbx_service_reload_cache(void)
{
	char			*error = NULL;
	zbx_ipc_socket_t	socket;

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_SERVICE, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to service: %s", error);
		zbx_exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_IPC_SERVICE_RELOAD_CACHE, NULL, 0))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_ipc_socket_close(&socket);
}
