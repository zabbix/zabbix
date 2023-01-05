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

#include "connector_worker.h"

#include "../db_lengths.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "zbxconnector.h"
#include "zbxembed.h"
#include "zbxtime.h"

extern unsigned char			program_type;

static void	worker_process_request(zbx_ipc_socket_t *socket, zbx_ipc_message_t *message,
		zbx_vector_connector_object_t *connector_objects)
{
	zbx_connector_t	connector;

	zbx_connector_deserialize_connector(message->data, message->size,
			&connector, connector_objects);
	zbx_free(connector.url);
	/*zbx_free(connector->timeout);
	zbx_free(connector->token);
	zbx_free(connector->http_proxy);
	zbx_free(connector->username);
	zbx_free(connector->password);
	zbx_free(connector->ssl_cert_file);
	zbx_free(connector->ssl_key_file);
	zbx_free(connector->ssl_key_password);*/

	zbx_vector_connector_object_clear_ext(connector_objects, zbx_connector_object_free);

	if (FAIL == zbx_ipc_socket_write(socket, ZBX_IPC_CONNECTOR_RESULT, NULL, 0))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send preprocessing result");
		exit(EXIT_FAILURE);
	}
}

ZBX_THREAD_ENTRY(connector_worker_thread, args)
{
	pid_t				ppid;
	char				*error = NULL;
	zbx_ipc_socket_t		socket;
	zbx_ipc_message_t		message;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_vector_connector_object_t	connector_objects;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_CONNECTOR, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to preprocessing service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ppid = getppid();
	zbx_ipc_socket_write(&socket, ZBX_IPC_CONNECTOR_WORKER, (unsigned char *)&ppid, sizeof(ppid));

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_vector_connector_object_create(&connector_objects);

	while (ZBX_IS_RUNNING())
	{
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED != zbx_ipc_socket_read(&socket, &message))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot read connector service request");
			exit(EXIT_FAILURE);
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
		zbx_update_env(get_process_type_string(process_type), zbx_time());

		switch (message.code)
		{
			case ZBX_IPC_CONNECTOR_REQUEST:
				worker_process_request(&socket, &message, &connector_objects);
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
