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

#include "connector_server.h"

#include "zbxtimekeeper.h"
#include "zbxthreads.h"
#include "zbxlog.h"
#include "zbxself.h"
#include "zbxconnector.h"
#include "zbxipcservice.h"
#include "zbxnix.h"
#include "zbxtime.h"
#include "zbxcacheconfig.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"

#define ZBX_CONNECTOR_MANAGER_DELAY	1
#define ZBX_CONNECTOR_FLUSH_INTERVAL	1

#define ZBX_CONNECTOR_RESCHEDULE_FALSE	0
#define ZBX_CONNECTOR_RESCHEDULE_TRUE	1

/* connector worker data */
typedef struct
{
	zbx_ipc_client_t	*client;	/* the connected worker client */
	zbx_uint64_t		taskid;		/* the current task id (connectorid) */
	zbx_vector_uint64_t	ids;
	int			reschedule;
}
zbx_connector_worker_t;

/* connector manager data */
typedef struct
{
	zbx_connector_worker_t		*workers;		/*c onnector worker array */
	int				worker_count;		/* registered connector worker count */
	int				worker_fork_count;	/* connector worker fork count */
	zbx_hashset_t			connectors;		/* connectors */
	zbx_hashset_iter_t		iter;			/* connector iterator */
	zbx_uint64_t			config_revision;	/* configuration revision */
	zbx_uint64_t			connector_revision;	/* connector configuration revision */
}
zbx_connector_manager_t;

typedef struct
{
	zbx_uint64_t				objectid;
	zbx_vector_connector_data_point_t	connector_data_points;
}
zbx_data_point_link_t;

static void	connector_clear(zbx_connector_t *connector)
{
	zbx_free(connector->url);
	zbx_free(connector->url_orig);
	zbx_free(connector->timeout);
	zbx_free(connector->timeout_orig);
	zbx_free(connector->token);
	zbx_free(connector->token_orig);
	zbx_free(connector->http_proxy);
	zbx_free(connector->http_proxy_orig);
	zbx_free(connector->username);
	zbx_free(connector->username_orig);
	zbx_free(connector->password);
	zbx_free(connector->password_orig);
	zbx_free(connector->ssl_cert_file);
	zbx_free(connector->ssl_cert_file_orig);
	zbx_free(connector->ssl_key_file);
	zbx_free(connector->ssl_key_file_orig);
	zbx_free(connector->ssl_key_password);
	zbx_free(connector->ssl_key_password_orig);
	zbx_free(connector->attempt_interval);
	zbx_list_destroy(&connector->data_point_link_queue);
	zbx_hashset_destroy(&connector->data_point_links);
}

static void	data_point_link_clean(zbx_data_point_link_t *data_point_link)
{
	zbx_vector_connector_data_point_clear_ext(&data_point_link->connector_data_points,
			zbx_connector_data_point_free);
	zbx_vector_connector_data_point_destroy(&data_point_link->connector_data_points);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes connector manager                                     *
 *                                                                            *
 * Parameters: manager           - [IN] the manager to initialize             *
 *             worker_fork_count - [IN] number of worker forks                *
 *                                                                            *
 ******************************************************************************/
static void	connector_init_manager(zbx_connector_manager_t *manager, int worker_fork_count)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers: %d", __func__, worker_fork_count);

	memset(manager, 0, sizeof(zbx_connector_manager_t));

	manager->worker_fork_count = worker_fork_count;
	manager->workers = (zbx_connector_worker_t *)zbx_calloc(NULL,
			(size_t)manager->worker_fork_count, sizeof(zbx_connector_worker_t));

	zbx_hashset_create_ext(&manager->connectors, 0, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)connector_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	zbx_hashset_iter_reset(&manager->connectors, &manager->iter);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	connector_destroy_manager(zbx_connector_manager_t *manager)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers: %d", __func__, manager->worker_count);

	for (i = 0; i < manager->worker_count; i++)
		zbx_vector_uint64_destroy(&manager->workers[i].ids);

	zbx_free(manager->workers);
	zbx_hashset_destroy(&manager->connectors);

	memset(manager, 0, sizeof(zbx_connector_manager_t));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers connector worker                                        *
 *                                                                            *
 * Parameters: manager - [IN] manager                                         *
 *             client  - [IN] connected connector worker                      *
 *             message - [IN] message received by connector manager           *
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
		if (manager->worker_fork_count == manager->worker_count)
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
 * Purpose: get worker without active task                                    *
 *                                                                            *
 * Parameters: manager - [IN] connector manager                               *
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

static void	connector_get_next_task(zbx_connector_t *connector, zbx_connector_worker_t *worker,
		unsigned char **data, size_t *data_alloc, size_t *data_offset, int *reschedule, int *processed_num)
{
#define ZBX_DATA_JSON_RESERVED		(ZBX_HISTORY_TEXT_VALUE_LEN * 4 + ZBX_KIBIBYTE * 4)
#define ZBX_DATA_JSON_RECORD_LIMIT	(ZBX_MAX_RECV_DATA_SIZE - ZBX_DATA_JSON_RESERVED)
	zbx_data_point_link_t	*data_point_link;
	int			i, records = 0;

	*reschedule = ZBX_CONNECTOR_RESCHEDULE_FALSE;

	while (ZBX_CONNECTOR_RESCHEDULE_FALSE == *reschedule &&
			SUCCEED == zbx_list_pop(&connector->data_point_link_queue, (void **)&data_point_link))
	{
		if (0 == *data_offset)
			zbx_connector_serialize_connector(data, data_alloc, data_offset, connector);

		for (i = 0; i < data_point_link->connector_data_points.values_num; i++, records++)
		{
			if ((records == connector->max_records && 0 != connector->max_records) ||
					*data_offset > ZBX_DATA_JSON_RECORD_LIMIT)
			{
				*reschedule = ZBX_CONNECTOR_RESCHEDULE_TRUE;
				break;
			}

			zbx_connector_serialize_data_point(data, data_alloc, data_offset,
					&data_point_link->connector_data_points.values[i]);
		}

		/* return back to list if over the limit */
		if (0 == i)
		{
			(void)zbx_list_prepend(&connector->data_point_link_queue, data_point_link, NULL);
			break;
		}

		if (i != data_point_link->connector_data_points.values_num)
		{
			zbx_vector_connector_data_point_t	connector_data_points_remaining;

			zbx_vector_connector_data_point_create(&connector_data_points_remaining);

			zbx_vector_connector_data_point_append_array(&connector_data_points_remaining,
					&data_point_link->connector_data_points.values[i],
					data_point_link->connector_data_points.values_num - i);

			data_point_link->connector_data_points.values_num = i;
			zbx_vector_connector_data_point_clear_ext(&data_point_link->connector_data_points,
					zbx_connector_data_point_free);
			zbx_vector_connector_data_point_destroy(&data_point_link->connector_data_points);
			data_point_link->connector_data_points = connector_data_points_remaining;
		}
		else
		{
			zbx_vector_connector_data_point_clear_ext(&data_point_link->connector_data_points,
					zbx_connector_data_point_free);
		}

		zbx_vector_uint64_append(&worker->ids, data_point_link->objectid);
	}

	*processed_num += records;

	if (0 != worker->ids.values_num)
	{
		worker->reschedule = *reschedule;
		worker->taskid = connector->connectorid;
	}

#undef ZBX_DATA_JSON_RESERVED
#undef ZBX_DATA_JSON_RECORD_LIMIT
}
/******************************************************************************
 *                                                                            *
 * Purpose: assign available queued connector tasks to free workers           *
 *                                                                            *
 * Parameters: manager       - [IN] connector manager                         *
 *             now           - [IN] current time                              *
 *             processed_num - [OUT] number of records sent to workers        *
 *                                                                            *
 ******************************************************************************/
static void	connector_assign_tasks(zbx_connector_manager_t *manager, int now, int *processed_num)
{
	zbx_connector_worker_t	*worker;
	zbx_connector_t		*connector = NULL;
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;

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
			data_offset = 0;
			int	reschedule;

			connector_get_next_task(connector, worker, &data, &data_alloc, &data_offset, &reschedule,
					processed_num);

			if (0 == data_offset)
				break;

			if (FAIL == zbx_ipc_client_send(worker->client, ZBX_IPC_CONNECTOR_REQUEST, data,
					(zbx_uint32_t)data_offset))
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot send data to connector worker");
				exit(EXIT_FAILURE);
			}

			connector->senders++;

			if (NULL == (worker = connector_get_free_worker(manager)))
			{
				if (ZBX_CONNECTOR_RESCHEDULE_TRUE == reschedule)
					connector->time_flush = now;

				break;
			}
		}
	}
	while (NULL != worker && NULL != (connector = (zbx_connector_t *)zbx_hashset_iter_next(&manager->iter)));

	zbx_free(data);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	connector_enqueue(zbx_connector_manager_t *manager, zbx_vector_connector_object_t *connector_objects)
{
	zbx_connector_t	*connector = NULL;
	int		i, j;

	for (i = 0; i < connector_objects->values_num; i++)
	{
		zbx_data_point_link_t	*data_point_link;

		for (j = 0; j < connector_objects->values[i].ids.values_num; j++)
		{
			zbx_connector_data_point_t	connector_data_point;

			if (NULL == connector || connector->connectorid != connector_objects->values[i].ids.values[j])
			{
				if (NULL == (connector = (zbx_connector_t *)zbx_hashset_search(&manager->connectors,
						&connector_objects->values[i].ids.values[j])))
				{
					continue;
				}
			}

			if (NULL == (data_point_link = (zbx_data_point_link_t *)zbx_hashset_search(
					&connector->data_point_links, &connector_objects->values[i].objectid)))
			{
				zbx_data_point_link_t	data_point_link_local = {.objectid =
						connector_objects->values[i].objectid};

				data_point_link = (zbx_data_point_link_t *)zbx_hashset_insert(
						&connector->data_point_links, &data_point_link_local,
						sizeof(data_point_link_local));
				zbx_vector_connector_data_point_create(&data_point_link->connector_data_points);

				(void)zbx_list_insert_after(&connector->data_point_link_queue, NULL, data_point_link,
						NULL);
			}

			connector_data_point.ts = connector_objects->values[i].ts;
			connector_data_point.str = connector_objects->values[i].str;

			zbx_vector_connector_data_point_append(&data_point_link->connector_data_points,
					connector_data_point);

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
			zbx_data_point_link_t	*data_point_link;

			if (NULL == (data_point_link = (zbx_data_point_link_t *)zbx_hashset_search(
					&connector->data_point_links, &worker->ids.values[i])))
			{
				continue;
			}

			if (0 == data_point_link->connector_data_points.values_num)
			{
				zbx_hashset_remove_direct(&connector->data_point_links, data_point_link);
			}
			else
			{
				(void)zbx_list_insert_after(&connector->data_point_link_queue, NULL, data_point_link,
						NULL);
			}
		}

		connector->senders--;

		if (ZBX_CONNECTOR_RESCHEDULE_TRUE == worker->reschedule)
			connector->time_flush = now;
	}

	zbx_vector_uint64_clear(&worker->ids);
}

static	void	connector_get_items_totals(zbx_connector_manager_t *manager, zbx_uint64_t *queued)
{
	zbx_connector_t		*connector;
	zbx_hashset_iter_t	iter;

	*queued = 0;

	zbx_hashset_iter_reset(&manager->connectors, &iter);
	while (NULL != (connector = (zbx_connector_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_hashset_iter_t	links_iter;
		zbx_data_point_link_t	*data_point_link;

		zbx_hashset_iter_reset(&connector->data_point_links, &links_iter);
		while (NULL != (data_point_link = (zbx_data_point_link_t *)zbx_hashset_iter_next(&links_iter)))
			*queued += (zbx_uint64_t)data_point_link->connector_data_points.values_num;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: return diagnostic statistics                                      *
 *                                                                            *
 * Parameters: manager - [IN] connector manager                               *
 *             client  - [IN] IPC client                                      *
 *                                                                            *
 ******************************************************************************/
static void	connector_get_diag_stats(zbx_connector_manager_t *manager, zbx_ipc_client_t *client)
{
	unsigned char	*data;
	zbx_uint32_t	data_len;
	zbx_uint64_t	queued;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	connector_get_items_totals(manager, &queued);

	data_len = zbx_connector_pack_diag_stats(&data, queued);
	zbx_ipc_client_send(client, ZBX_IPC_CONNECTOR_DIAG_STATS_RESULT, data, data_len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare connector statistics by value                             *
 *                                                                            *
 ******************************************************************************/
static int	connector_sort_item_by_values_desc(const void *d1, const void *d2)
{
	const zbx_connector_stat_t	*i1 = *(const zbx_connector_stat_t * const *)d1;
	const zbx_connector_stat_t	*i2 = *(const zbx_connector_stat_t * const *)d2;

	return i2->values_num - i1->values_num;
}

static	void	preprocessor_get_items_view(zbx_connector_manager_t *manager, zbx_vector_ptr_t *view)
{
	zbx_connector_t		*connector;
	zbx_hashset_iter_t	iter;

	zbx_hashset_iter_reset(&manager->connectors, &iter);
	while (NULL != (connector = (zbx_connector_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_hashset_iter_t	links_iter;
		zbx_data_point_link_t	*data_point_link;
		zbx_connector_stat_t	*connector_stat;
		zbx_list_iterator_t	iterator;

		connector_stat = zbx_malloc(NULL, sizeof(zbx_connector_stat_t));
		connector_stat->connectorid = connector->connectorid;
		connector_stat->links_num = connector->data_point_links.num_data;

		connector_stat->values_num = 0;
		zbx_hashset_iter_reset(&connector->data_point_links, &links_iter);
		while (NULL != (data_point_link = (zbx_data_point_link_t *)zbx_hashset_iter_next(&links_iter)))
			connector_stat->values_num += data_point_link->connector_data_points.values_num;

		connector_stat->queued_links_num = 0;
		zbx_list_iterator_init(&connector->data_point_link_queue, &iterator);
		while (SUCCEED == zbx_list_iterator_next(&iterator))
			connector_stat->queued_links_num++;

		zbx_vector_ptr_append(view, connector_stat);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: return diagnostic statistics                                      *
 *                                                                            *
 * Parameters: manager - [IN] connector manager                               *
 *             client  - [IN] IPC client                                      *
 *                                                                            *
 ******************************************************************************/
static void	connector_get_queue(zbx_connector_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_uint64_t	queued;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	connector_get_items_totals(manager, &queued);

	zbx_ipc_client_send(client, ZBX_IPC_CONNECTOR_QUEUE_RESULT, (unsigned char *)&queued,
			sizeof(zbx_uint64_t));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: return diagnostic top view                                        *
 *                                                                            *
 * Parameters: manager - [IN] connector manager                               *
 *             client  - [IN] IPC client                                      *
 *             message - [IN] the message with request                        *
 *                                                                            *
 ******************************************************************************/
static void	connector_get_top_items(zbx_connector_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	int			limit;
	unsigned char		*data;
	zbx_uint32_t		data_len;
	zbx_vector_ptr_t	view;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_connector_unpack_top_request(&limit, message->data);

	zbx_vector_ptr_create(&view);

	preprocessor_get_items_view(manager, &view);

	zbx_vector_ptr_sort(&view, connector_sort_item_by_values_desc);

	data_len = zbx_connector_pack_top_connectors_result(&data, (zbx_connector_stat_t **)view.values,
			MIN(limit, view.values_num));
	zbx_ipc_client_send(client, ZBX_IPC_CONNECTOR_TOP_CONNECTORS_RESULT, data, data_len);
	zbx_free(data);

	zbx_vector_ptr_clear_ext(&view, zbx_ptr_free);
	zbx_vector_ptr_destroy(&view);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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
	zbx_thread_connector_manager_args	*args_in;

	args_in = (zbx_thread_connector_manager_args *)(((zbx_thread_args_t *)args)->args);
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
				server_num, get_process_type_string(process_type), process_num);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_CONNECTOR, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start connector manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	connector_init_manager(&manager, args_in->get_process_forks_cb_arg(ZBX_PROCESS_TYPE_CONNECTORWORKER));

	/* initialize statistics */
	time_stat = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
	zbx_vector_connector_object_create(&connector_objects);

	for (;;)
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [processed %d, idle "
					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
					get_process_type_string(process_type), process_num, processed_num,
					time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
		}

		connector_assign_tasks(&manager, (int)time_now, &processed_num);

		time_now = zbx_time();
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
					zbx_dc_config_history_sync_get_connectors(&manager.connectors, &manager.iter,
							&manager.config_revision, &manager.connector_revision,
							(zbx_clean_func_t)data_point_link_clean);
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
					connector_add_result(&manager, client, (int)time_now);
					break;
				case ZBX_IPC_CONNECTOR_DIAG_STATS:
					connector_get_diag_stats(&manager, client);
					break;
				case ZBX_IPC_CONNECTOR_TOP_CONNECTORS:
					connector_get_top_items(&manager, client, message);
					break;
				case ZBX_IPC_CONNECTOR_QUEUE:
					connector_get_queue(&manager, client);
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (!ZBX_IS_RUNNING() && ZBX_IPC_RECV_TIMEOUT == ret)
		{
			zbx_connector_t		*connector;
			zbx_hashset_iter_t	iter;
			int			num = 0;

			zbx_hashset_iter_reset(&manager.connectors, &iter);
			while (NULL != (connector = (zbx_connector_t *)zbx_hashset_iter_next(&iter)))
			{
				if (0 != connector->data_point_links.num_data)
				{
					num = connector->data_point_links.num_data;
					break;
				}
			}

			if (0 == num)
			{
				if (0 == timeout.sec)
					break;

				timeout.sec = 0;
				timeout.ns = 100000000;
			}
		}
	}

	zbx_vector_connector_object_destroy(&connector_objects);
	connector_destroy_manager(&manager);
	zbx_ipc_service_close(&service);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}
