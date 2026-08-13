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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "../../src/libs/zbxipmi/ipmi_manager.c"
#include "zbxipcservice.h"
#include "zbxtime.h"
#include "zbxalgo.h"

struct zbx_ipc_client
{
	zbx_ipc_socket_t	csocket;
	zbx_ipc_service_t	*service;

	zbx_uint32_t		rx_header[2];
	unsigned char		*rx_data;
	zbx_uint32_t		rx_bytes;
	zbx_queue_ptr_t		rx_queue;
	struct event		*rx_event;

	zbx_uint32_t		tx_header[2];
	unsigned char		*tx_data;
	zbx_uint32_t		tx_bytes;
	zbx_queue_ptr_t		tx_queue;
	struct event		*tx_event;

	zbx_uint64_t		id;
	unsigned char		state;

	void			*userdata;

	zbx_uint32_t		refcount;
};



int	__wrap_zbx_ipc_service_recv(zbx_ipc_service_t *service, const zbx_timespec_t *timeout,
			zbx_ipc_client_t **client, zbx_ipc_message_t **message);
__pid_t	__wrap_getppid (void);
void	__wrap_zbx_ipc_client_close(zbx_ipc_client_t *client);

int	__wrap_zbx_ipc_service_recv(zbx_ipc_service_t *service, const zbx_timespec_t *timeout,
			zbx_ipc_client_t **client, zbx_ipc_message_t **message)
{
	ZBX_UNUSED(service);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(message);

	*client = (zbx_ipc_client_t *)zbx_malloc(NULL, sizeof(zbx_ipc_client_t));
	memset(*client, 0, sizeof(zbx_ipc_client_t));
	zbx_ipc_client_addref(*client);
	client[0]->csocket.fd = -1;
	client[0]->state = 1;

	return ZBX_IPC_RECV_IMMEDIATE;
}

__pid_t	__wrap_getppid (void)
{
	return 100;
}

static int	mock_get_config_forks(unsigned char process_type)
{
	ZBX_UNUSED(process_type);
	return zbx_mock_get_parameter_int("in.worker_count");
}

void	__wrap_zbx_ipc_client_close(zbx_ipc_client_t *client)
{
	client->state = 0;
}

void	zbx_mock_test_entry(void **state)
{
#ifdef HAVE_OPENIPMI
	zbx_ipc_service_t		service;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	zbx_ipmi_manager_t		manager;
	int				worker_cnt, expected_worker_count, expected_refcount;
	__pid_t				pid;
	zbx_timespec_t			timeout = {0, 0};
	zbx_thread_ipmi_manager_args	ipmi_manager_args =
	{
		.config_timeout = 0,
		.config_unavailable_delay = 0,
		.config_unreachable_delay = 0,
		.config_unreachable_period = 0,
		.get_config_forks = mock_get_config_forks
	};

	ZBX_UNUSED(state);

	message = (zbx_ipc_message_t *)zbx_malloc(NULL, sizeof(zbx_ipc_message_t));
	message->data = (unsigned char *)&pid;

	pid = zbx_mock_get_parameter_int("in.pid");
	worker_cnt = ipmi_manager_args.get_config_forks(1);
	expected_refcount = zbx_mock_get_parameter_int("out.addref_count");
	expected_worker_count = zbx_mock_get_parameter_int("out.worker_count");

	ipmi_manager_init(&manager, ipmi_manager_args.get_config_forks);

	if (NULL == manager.pollers.values && 0 != worker_cnt)
		fail_msg("lld_init_manager() workers init: Failed to init manager.workers");

	zbx_mock_assert_int_eq("connector_init_manager() worker forks:",manager.pollers.values_num, worker_cnt);

	zbx_ipc_service_recv(&service, &timeout, &client, &message);

	for (int i = 0; i < worker_cnt; i++)
	{
		ipmi_manager_register_poller(&manager, client, message);
	}

	zbx_mock_assert_int_eq("connector_register_worker() worker count:", expected_worker_count,
			manager.next_poller_index);
	zbx_mock_assert_int_eq("connector_register_worker() refcount value:", expected_refcount,
			client->refcount);

	zbx_free(message);
	zbx_free(client);
# endif
}
