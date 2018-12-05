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

#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "lld_manager.h"
#include "lld_protocol.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

extern int	CONFIG_LLDWORKER_FORKS;

typedef struct
{
	/* workers vector, created during manager initialization */
	zbx_vector_ptr_t	workers;

	/* free workers */
	zbx_queue_ptr_t		free_workers;

	/* workers indexed by IPC service clients */
	zbx_hashset_t		workers_client;

	/* the next woerker index to be assigned to new IPC service clients */
	int			next_worker_index;

}
zbx_lld_manager_t;

typedef struct
{
	zbx_ipc_client_t	*client;
}
zbx_lld_worker_t;


/* workers_client hashset support */
static zbx_hash_t	worker_hash_func(const void *d)
{
	const zbx_lld_worker_t	*worker = *(const zbx_lld_worker_t **)d;

	zbx_hash_t hash =  ZBX_DEFAULT_PTR_HASH_FUNC(&worker->client);

	return hash;
}

static int	worker_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_worker_t	*p1 = *(const zbx_lld_worker_t **)d1;
	const zbx_lld_worker_t	*p2 = *(const zbx_lld_worker_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->client, p2->client);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_worker_free                                                  *
 *                                                                            *
 * Purpose: frees LLD worker                                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_worker_free(zbx_lld_worker_t *worker)
{
	zbx_ipc_client_close(worker->client);
	zbx_free(worker);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_manager_init                                                 *
 *                                                                            *
 * Purpose: initializes LLD manager                                           *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_manager_init(zbx_lld_manager_t *manager)
{
	const char		*__function_name = "lld_init";
	int			i;
	zbx_lld_worker_t	*worker;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers:%d", __function_name, CONFIG_LLDWORKER_FORKS);

	zbx_vector_ptr_create(&manager->workers);
	zbx_queue_ptr_create(&manager->free_workers);
	zbx_hashset_create(&manager->workers_client, 0, worker_hash_func, worker_compare_func);

	manager->next_worker_index = 0;

	for (i = 0; i < CONFIG_LLDWORKER_FORKS; i++)
	{
		worker = (zbx_lld_worker_t *)zbx_malloc(NULL, sizeof(zbx_lld_worker_t));

		worker->client = NULL;

		zbx_vector_ptr_append(&manager->workers, worker);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_manager_destroy                                              *
 *                                                                            *
 * Purpose: destroys LLD manager                                              *
 *                                                                            *
 * Parameters: manager - [IN] the manager to destroy                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_manager_destroy(zbx_lld_manager_t *manager)
{
	zbx_queue_ptr_destroy(&manager->free_workers);
	zbx_hashset_destroy(&manager->workers_client);
	zbx_vector_ptr_clear_ext(&manager->workers, (zbx_clean_func_t)lld_worker_free);
	zbx_vector_ptr_destroy(&manager->workers);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_register_worker                                              *
 *                                                                            *
 * Purpose: registers worker                                                  *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected worker                            *
 *             message - [IN] the received message                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_register_worker(zbx_lld_manager_t *manager, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	const char		*__function_name = "lld_register_worker";
	zbx_lld_worker_t	*worker = NULL;

	pid_t			ppid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	memcpy(&ppid, message->data, sizeof(ppid));

	if (ppid != getppid())
	{
		zbx_ipc_client_close(client);
		zabbix_log(LOG_LEVEL_DEBUG, "refusing connection from foreign process");
	}
	else
	{
		if (manager->next_worker_index == manager->workers.values_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		worker = (zbx_lld_worker_t *)manager->workers.values[manager->next_worker_index++];
		worker->client = client;

		zbx_hashset_insert(&manager->workers_client, &worker, sizeof(zbx_lld_worker_t *));
		zbx_queue_ptr_push(&manager->free_workers, worker);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}


ZBX_THREAD_ENTRY(lld_manager_thread, args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_ipc_service_t	lld_service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	double			time_stat, time_idle = 0, time_now, sec;
	int			ret;
	zbx_lld_manager_t	manager;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	if (FAIL == zbx_ipc_service_start(&lld_service, ZBX_IPC_SERVICE_LLD, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start LLD manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	lld_manager_init(&manager);

	/* initialize statistics */
	time_stat = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	for (;;)
	{
		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&lld_service, 1, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

		sec = zbx_time();
		zbx_update_env(sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_LLD_REGISTER:
					lld_register_worker(&manager, client, message);
					break;
				case ZBX_IPC_LLD_REQUEST:
					/* TODO: process lld request */
					break;
				case ZBX_IPC_LLD_RESULT:
					/* TODO: process lld result */
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zbx_ipc_service_close(&lld_service);

	lld_manager_destroy(&manager);

	return 0;
}
