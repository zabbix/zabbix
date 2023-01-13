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
#include "zbxcacheconfig.h"

extern unsigned char			program_type;
extern int				CONFIG_FORKS[ZBX_PROCESS_TYPE_COUNT];

#define ZBX_CONNECTOR_MANAGER_DELAY	1
#define ZBX_CONNECTOR_FLUSH_INTERVAL	1

#define ZBX_CONNECTOR_RESCHEDULE_FALSE	0
#define ZBX_CONNECTOR_RESCHEDULE_TRUE	1

static sigset_t				orig_mask;

/* preprocessing worker data */
typedef struct
{
	zbx_ipc_client_t	*client;	/* the connected preprocessing worker client */
	zbx_uint64_t		taskid;		/* the current task data */
	zbx_vector_uint64_t	ids;
	unsigned char		reschedule;
}
zbx_connector_worker_t;

/* preprocessing manager data */
typedef struct
{
	zbx_connector_worker_t		*workers;	/* preprocessing worker array */
	int				worker_count;	/* preprocessing worker count */
	zbx_hashset_t			connectors;
	zbx_hashset_iter_t		iter;
	zbx_uint64_t			revision;	/* the configuration revision */
	zbx_uint64_t			processed_num;	/* processed value counter */
	zbx_uint64_t			queued_num;	/* queued value counter */
	zbx_uint64_t			preproc_num;	/* queued values with preprocessing steps */
}
zbx_connector_manager_t;

typedef struct
{
	zbx_uint64_t				objectid;
	zbx_vector_connector_object_data_t	connector_objects;
}
zbx_object_link_t;

static void	connector_clear(zbx_connector_t *connector)
{
	int	i;

	zbx_free(connector->url);
	zbx_free(connector->timeout);
	zbx_free(connector->token);
	zbx_free(connector->http_proxy);
	zbx_free(connector->username);
	zbx_free(connector->password);
	zbx_free(connector->ssl_cert_file);
	zbx_free(connector->ssl_key_file);
	zbx_free(connector->ssl_key_password);
	zbx_list_destroy(&connector->queue);
	zbx_hashset_destroy(&connector->object_link);
}

static void	object_link_clean(zbx_object_link_t *object_link)
{
	zbx_vector_connector_object_data_clear_ext(&object_link->connector_objects, zbx_connector_object_data_free);
	zbx_vector_connector_object_data_destroy(&object_link->connector_objects);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes preprocessing manager                                 *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static void	connector_init_manager(zbx_connector_manager_t *manager)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers: %d", __func__, CONFIG_FORKS[ZBX_PROCESS_TYPE_CONNECTORWORKER]);

	memset(manager, 0, sizeof(zbx_connector_manager_t));

	manager->workers = (zbx_connector_worker_t *)zbx_calloc(NULL,
			(size_t)CONFIG_FORKS[ZBX_PROCESS_TYPE_CONNECTORWORKER], sizeof(zbx_connector_worker_t));

	zbx_hashset_create_ext(&manager->connectors, 0, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)connector_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	zbx_hashset_iter_reset(&manager->connectors, &manager->iter);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers preprocessing worker                                    *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected preprocessing worker              *
 *             message - [IN] message received by preprocessing manager       *
 *                                                                            *
 ******************************************************************************/
static void	connector_register_worker(zbx_connector_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_connector_worker_t	*worker = NULL;
	pid_t			ppid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memcpy(&ppid, message->data, sizeof(ppid));

	if (ppid != getppid())
	{
		zbx_ipc_client_close(client);
		zabbix_log(LOG_LEVEL_DEBUG, "refusing connection from foreign process");
	}
	else
	{
		if (CONFIG_FORKS[ZBX_PROCESS_TYPE_CONNECTORWORKER] == manager->worker_count)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		worker = (zbx_connector_worker_t *)&manager->workers[manager->worker_count++];
		worker->client = client;
		zbx_vector_uint64_create(&worker->ids);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get worker without active preprocessing task                      *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *                                                                            *
 * Return value: pointer to the worker data or NULL if none                   *
 *                                                                            *
 ******************************************************************************/
static zbx_connector_worker_t	*connector_get_free_worker(zbx_connector_manager_t *manager)
{
	int	i;

	for (i = 0; i < manager->worker_count; i++)
	{
		if (0 == manager->workers[i].ids.values_num)
			return &manager->workers[i];
	}

	return NULL;
}

static void	connector_get_next_task(zbx_connector_t *connector, zbx_ipc_message_t *message,
		zbx_connector_worker_t *worker)
{
#define ZBX_DATA_JSON_RESERVED		(ZBX_HISTORY_TEXT_VALUE_LEN * 4 + ZBX_KIBIBYTE * 4)
#define ZBX_DATA_JSON_RECORD_LIMIT	(ZBX_MAX_RECV_DATA_SIZE - ZBX_DATA_JSON_RESERVED)
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;
	zbx_object_link_t	*object_link;
	int			i, records = 0, ret = SUCCEED;

	while (SUCCEED == ret && SUCCEED == zbx_list_pop(&connector->queue, (void **)&object_link))
	{
		if (NULL == data)
			zbx_connector_serialize_connector(&data, &data_alloc, &data_offset, connector);

		for (i = 0; i < object_link->connector_objects.values_num; i++, records++)
		{
			if ((records == connector->max_records && 0 != connector->max_records) ||
					data_offset > ZBX_DATA_JSON_RECORD_LIMIT)
			{
				ret = FAIL;
				break;
			}

			zbx_connector_serialize_object_data(&data, &data_alloc, &data_offset,
					&object_link->connector_objects.values[i]);
		}

		if (i != object_link->connector_objects.values_num)
		{
			zbx_vector_connector_object_data_t	connector_objects_remaining;

			zbx_vector_connector_object_data_create(&connector_objects_remaining);

			zbx_vector_connector_object_data_append_array(&connector_objects_remaining,
					&object_link->connector_objects.values[i],
					object_link->connector_objects.values_num - i);

			object_link->connector_objects.values_num = i;
			zbx_vector_connector_object_data_clear_ext(&object_link->connector_objects,
					zbx_connector_object_data_free);
			zbx_vector_connector_object_data_destroy(&object_link->connector_objects);
			object_link->connector_objects = connector_objects_remaining;
		}
		else
		{
			zbx_vector_connector_object_data_clear_ext(&object_link->connector_objects,
					zbx_connector_object_data_free);
		}

		zbx_vector_uint64_append(&worker->ids, object_link->objectid);
	}

	message->code = ZBX_IPC_CONNECTOR_REQUEST;
	message->data = data;
	message->size = data_offset;

	if (0 != worker->ids.values_num)
	{
		worker->reschedule = FAIL == ret ? ZBX_CONNECTOR_RESCHEDULE_TRUE : ZBX_CONNECTOR_RESCHEDULE_FALSE;
		worker->taskid = connector->connectorid;
	}

#undef ZBX_DATA_JSON_RESERVED
#undef ZBX_DATA_JSON_RECORD_LIMIT
}
/******************************************************************************
 *                                                                            *
 * Purpose: assign available queued preprocessing tasks to free workers       *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *                                                                            *
 ******************************************************************************/
static void	connector_assign_tasks(zbx_connector_manager_t *manager, int now)
{
	zbx_connector_worker_t	*worker;
	zbx_ipc_message_t	message;
	zbx_connector_t		*connector = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (worker = connector_get_free_worker(manager)))
		goto out;

	if (NULL == (connector = (zbx_connector_t *)zbx_hashset_iter_next(&manager->iter)))
	{
		zbx_hashset_iter_reset(&manager->connectors, &manager->iter);
		if (NULL == (connector = (zbx_connector_t *)zbx_hashset_iter_next(&manager->iter)))
			goto out;
	}

	do
	{
		if (connector->time_flush > now)
			continue;

		connector->time_flush = now + ZBX_CONNECTOR_FLUSH_INTERVAL;

		while (connector->senders < connector->max_senders)
		{
			connector_get_next_task(connector, &message, worker);

			if (NULL == message.data)
				break;

			if (FAIL == zbx_ipc_client_send(worker->client, message.code, message.data, message.size))
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot send data to connector worker");
				exit(EXIT_FAILURE);
			}

			zbx_ipc_message_clean(&message);
			connector->senders++;

			if (NULL == (worker = connector_get_free_worker(manager)))
				break;
		}
	}
	while (NULL != worker && NULL != (connector = (zbx_connector_t *)zbx_hashset_iter_next(&manager->iter)));
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	connector_enqueue(zbx_connector_manager_t *manager, zbx_vector_connector_object_t *connector_objects)
{
	zbx_connector_t	*connector = NULL;
	int		i, j;

	for (i = 0; i < connector_objects->values_num; i++)
	{
		zbx_object_link_t	*object_link;

		for (j = 0; j < connector_objects->values[i].ids.values_num; j++)
		{
			zbx_connector_object_data_t	connector_object_data;

			if (NULL == connector || connector->connectorid != connector_objects->values[i].ids.values[j])
			{
				if (NULL == (connector = (zbx_connector_t *)zbx_hashset_search(&manager->connectors,
						&connector_objects->values[i].ids.values[j])))
				{
					continue;
				}
			}

			if (NULL == (object_link = (zbx_object_link_t *)zbx_hashset_search(&connector->object_link,
					&connector_objects->values[i].objectid)))
			{
				zbx_object_link_t	object_link_local = {.objectid =
						connector_objects->values[i].objectid};

				object_link = (zbx_object_link_t *)zbx_hashset_insert(
						&connector->object_link, &object_link_local, sizeof(object_link_local));
				zbx_vector_connector_object_data_create(&object_link->connector_objects);

				zbx_list_insert_after(&connector->queue, NULL, object_link, NULL);
			}

			connector_object_data.ts = connector_objects->values[i].ts;
			connector_object_data.str = connector_objects->values[i].str;

			zbx_vector_connector_object_data_append(&object_link->connector_objects, connector_object_data);

			if (j == connector_objects->values[i].ids.values_num - 1)
				connector_objects->values[i].str = NULL;
			else
				connector_objects->values[i].str = zbx_strdup(NULL, connector_objects->values[i].str);
		}
	}
}

static zbx_connector_worker_t	*connector_get_worker_by_client(zbx_connector_manager_t *manager,
		zbx_ipc_client_t *client)
{
	int				i;
	zbx_connector_worker_t	*worker = NULL;

	for (i = 0; i < manager->worker_count; i++)
	{
		if (client == manager->workers[i].client)
		{
			worker = &manager->workers[i];
			break;
		}
	}

	if (NULL == worker)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	return worker;
}

static void	connector_add_result(zbx_connector_manager_t *manager, zbx_ipc_client_t *client, int now)
{
	zbx_connector_worker_t	*worker;
	zbx_connector_t		*connector;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	worker = connector_get_worker_by_client(manager, client);

	if (NULL != (connector = (zbx_connector_t *)zbx_hashset_search(&manager->connectors, &worker->taskid)))
	{
		for (i = 0; i < worker->ids.values_num; i++)
		{
			zbx_object_link_t	*object_link;

			if (NULL == (object_link = (zbx_object_link_t *)zbx_hashset_search(&connector->object_link,
					&worker->ids.values[i])))
			{
				continue;
			}

			if (0 == object_link->connector_objects.values_num)
			{
				zbx_hashset_remove_direct(&connector->object_link, object_link);
			}
			else
				zbx_list_insert_after(&connector->queue, NULL, object_link, NULL);
		}

		connector->senders--;

		if (ZBX_CONNECTOR_RESCHEDULE_TRUE == worker->reschedule)
			connector->time_flush = now;
	}

	zbx_vector_uint64_clear(&worker->ids);
}

ZBX_THREAD_ENTRY(connector_manager_thread, args)
{
	zbx_ipc_service_t			service;
	char					*error = NULL;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	int					ret, processed_num = 0;
	double					time_stat, time_idle = 0, time_now, sec;
	zbx_timespec_t				timeout = {ZBX_CONNECTOR_MANAGER_DELAY, 0};
	const zbx_thread_info_t			*info = &((zbx_thread_args_t *)args)->info;
	int					server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int					process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char				process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_vector_connector_object_t		connector_objects;
	zbx_connector_manager_t			manager;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */
#define	FLUSH_INTERVAL	1

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
				server_num, get_process_type_string(process_type), process_num);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_CONNECTOR, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start availability manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	connector_init_manager(&manager);

	/* initialize statistics */
	time_stat = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
	zbx_vector_connector_object_create(&connector_objects);

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

		connector_assign_tasks(&manager, time_now);

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, &timeout, &client, &message);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			zbx_dc_config_history_sync_get_connectors(&manager.connectors, &manager.iter,
					&manager.revision, (zbx_clean_func_t)object_link_clean);

			switch (message->code)
			{
				case ZBX_IPC_CONNECTOR_REQUEST:
					zbx_connector_deserialize_object(message->data, message->size,
							&connector_objects);
					connector_enqueue(&manager, &connector_objects);
					zbx_vector_connector_object_clear_ext(&connector_objects,
							zbx_connector_object_free);
					break;
				case ZBX_IPC_CONNECTOR_WORKER:
					connector_register_worker(&manager, client, message);
					break;
				case ZBX_IPC_CONNECTOR_RESULT:
					connector_add_result(&manager, client, time_now);
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
