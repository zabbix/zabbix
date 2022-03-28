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

#include "lld_manager.h"

#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "lld_protocol.h"

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

extern int	CONFIG_LLDWORKER_FORKS;

/*
 * The LLD queue is organized as a queue (rule_queue binary heap) of LLD rules,
 * sorted by their oldest value timestamps. The values are stored in linked lists,
 * each rule having its own list of values. Values inside list are not sorted, so
 * in the case a LLD rule received a value with past timestamp, it will be processed
 * in queuing order, not the value chronological order.
 *
 * During processing the rule with oldest value is popped from queue and sent
 * to a free worker. After processing the rule worker sends done response and
 * manager removes the oldest value from rule's value list. If there are no more
 * values in the list the rule is removed from the index (rule_index hashset),
 * otherwise the rule is enqueued back in LLD queue.
 *
 */

typedef struct
{
	/* workers vector, created during manager initialization */
	zbx_vector_ptr_t	workers;

	/* free workers */
	zbx_queue_ptr_t		free_workers;

	/* workers indexed by IPC service clients */
	zbx_hashset_t		workers_client;

	/* the next worker index to be assigned to new IPC service clients */
	int			next_worker_index;

	/* index of queued LLD rules */
	zbx_hashset_t		rule_index;

	/* LLD rule queue, ordered by the oldest values */
	zbx_binary_heap_t	rule_queue;

	/* the number of queued LLD rules */
	zbx_uint64_t		queued_num;

}
zbx_lld_manager_t;

typedef struct
{
	zbx_ipc_client_t	*client;
	zbx_lld_rule_t		*rule;
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

/* rule_queue binary heap support */
static int	rule_elem_compare_func(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const zbx_lld_rule_t	*rule1 = (const zbx_lld_rule_t *)e1->data;
	const zbx_lld_rule_t	*rule2 = (const zbx_lld_rule_t *)e2->data;

	/* compare by timestamp of the oldest value */
	return zbx_timespec_compare(&rule1->head->ts, &rule2->head->ts);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees LLD data                                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_data_free(zbx_lld_data_t *data)
{
	zbx_free(data->value);
	zbx_free(data->error);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clears LLD rule                                                   *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_clear(zbx_lld_rule_t *rule)
{
	zbx_lld_data_t	*data;

	while (NULL != rule->head)
	{
		data = rule->head;
		rule->head = data->next;
		lld_data_free(data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees LLD worker                                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_worker_free(zbx_lld_worker_t *worker)
{
	zbx_free(worker);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes LLD manager                                           *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_manager_init(zbx_lld_manager_t *manager)
{
	int			i;
	zbx_lld_worker_t	*worker;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers:%d", __func__, CONFIG_LLDWORKER_FORKS);

	zbx_vector_ptr_create(&manager->workers);
	zbx_queue_ptr_create(&manager->free_workers);
	zbx_hashset_create(&manager->workers_client, 0, worker_hash_func, worker_compare_func);

	zbx_hashset_create_ext(&manager->rule_index, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			(zbx_clean_func_t)lld_rule_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_binary_heap_create(&manager->rule_queue, rule_elem_compare_func, ZBX_BINARY_HEAP_OPTION_EMPTY);

	manager->next_worker_index = 0;

	for (i = 0; i < CONFIG_LLDWORKER_FORKS; i++)
	{
		worker = (zbx_lld_worker_t *)zbx_malloc(NULL, sizeof(zbx_lld_worker_t));

		worker->client = NULL;

		zbx_vector_ptr_append(&manager->workers, worker);
	}

	manager->queued_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys LLD manager                                              *
 *                                                                            *
 * Parameters: manager - [IN] the manager to destroy                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_manager_destroy(zbx_lld_manager_t *manager)
{
	zbx_binary_heap_destroy(&manager->rule_queue);
	zbx_hashset_destroy(&manager->rule_index);
	zbx_queue_ptr_destroy(&manager->free_workers);
	zbx_hashset_destroy(&manager->workers_client);
	zbx_vector_ptr_clear_ext(&manager->workers, (zbx_clean_func_t)lld_worker_free);
	zbx_vector_ptr_destroy(&manager->workers);
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns worker by connected IPC client data                       *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected worker                            *
 *                                                                            *
 * Return value: The LLD worker                                               *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_worker_t	*lld_get_worker_by_client(zbx_lld_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_lld_worker_t	**worker, worker_local, *plocal = &worker_local;

	plocal->client = client;
	worker = (zbx_lld_worker_t **)zbx_hashset_search(&manager->workers_client, &plocal);

	if (NULL == worker)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	return *worker;
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers worker                                                  *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected worker IPC client data            *
 *             message - [IN] the received message                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_register_worker(zbx_lld_manager_t *manager, zbx_ipc_client_t *client,
		const zbx_ipc_message_t *message)
{
	zbx_lld_worker_t	*worker;
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queues LLD rule                                                   *
 *                                                                            *
 * Parameters: manager - [IN] the LLD manager                                 *
 *             rule    - [IN] the LLD rule                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_queue_rule(zbx_lld_manager_t *manager, zbx_lld_rule_t *rule)
{
	zbx_binary_heap_elem_t	elem = {rule->hostid, rule};

	zbx_binary_heap_insert(&manager->rule_queue, &elem);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queues low level discovery request                                *
 *                                                                            *
 * Parameters: manager - [IN] the LLD manager                                 *
 *             message - [IN] the message with LLD request                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_queue_request(zbx_lld_manager_t *manager, const zbx_ipc_message_t *message)
{
	zbx_uint64_t	hostid;
	zbx_lld_rule_t	*rule;
	zbx_lld_data_t	*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	data = (zbx_lld_data_t *)zbx_malloc(NULL, sizeof(zbx_lld_data_t));
	data->next = NULL;

	zbx_lld_deserialize_item_value(message->data, &data->itemid, &hostid, &data->value, &data->ts, &data->meta,
			&data->lastlogsize, &data->mtime, &data->error);

	if (NULL == (rule = zbx_hashset_search(&manager->rule_index, &hostid)))
	{
		zbx_lld_rule_t	rule_local = {.hostid = hostid, .values_num = 0, .tail = data, .head = data};

		data->prev = NULL;

		rule = zbx_hashset_insert(&manager->rule_index, &rule_local, sizeof(rule_local));
		lld_queue_rule(manager, rule);
	}
	else
	{
		if (0 == data->meta)
		{
			zbx_lld_data_t	*data_ptr;

			for (data_ptr = rule->tail; NULL != data_ptr; data_ptr = data_ptr->prev)
			{
				/* if there are multiple values then they should be different, check only last one */
				if (data_ptr->itemid == data->itemid)
					break;
			}

			if (NULL != data_ptr && 0 == zbx_strcmp_null(data->error, data_ptr->error) &&
					0 == zbx_strcmp_null(data->value, data_ptr->value))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "skip repeating values for discovery rule:" ZBX_FS_UI64,
						data->itemid);

				lld_data_free(data);
				goto out;
			}
		}

		data->prev = rule->tail;
		rule->tail->next = data;
		rule->tail = data;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "queuing discovery rule:" ZBX_FS_UI64, data->itemid);

	rule->values_num++;
	manager->queued_num++;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes next LLD request from queue                             *
 *                                                                            *
 * Parameters: manager - [IN] the LLD manager                                 *
 *             worker  - [IN] the target worker                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_next_request(zbx_lld_manager_t *manager, zbx_lld_worker_t *worker)
{
	zbx_binary_heap_elem_t	*elem;
	unsigned char		*buf;
	zbx_uint32_t		buf_len;
	zbx_lld_data_t		*data;

	elem = zbx_binary_heap_find_min(&manager->rule_queue);
	worker->rule = (zbx_lld_rule_t *)elem->data;
	zbx_binary_heap_remove_min(&manager->rule_queue);

	data = worker->rule->head;
	buf_len = zbx_lld_serialize_item_value(&buf, data->itemid, 0, data->value, &data->ts, data->meta,
			data->lastlogsize, data->mtime, data->error);
	zbx_ipc_client_send(worker->client, ZBX_IPC_LLD_TASK, buf, buf_len);
	zbx_free(buf);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends queued LLD rules to free workers                            *
 *                                                                            *
 * Parameters: manager - [IN] the LLD manager                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_queue(zbx_lld_manager_t *manager)
{
	zbx_lld_worker_t	*worker;

	while (SUCCEED != zbx_binary_heap_empty(&manager->rule_queue))
	{
		if (NULL == (worker = zbx_queue_ptr_pop(&manager->free_workers)))
			break;

		lld_process_next_request(manager, worker);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes LLD worker 'done' response                              *
 *                                                                            *
 * Parameters: manager - [IN] the LLD manager                                 *
 * Parameters: client  - [IN] the worker's IPC client connection              *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_result(zbx_lld_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_lld_worker_t	*worker;
	zbx_lld_rule_t		*rule;
	zbx_lld_data_t		*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	worker = lld_get_worker_by_client(manager, client);

	zabbix_log(LOG_LEVEL_DEBUG, "discovery rule:" ZBX_FS_UI64 " has been processed", worker->rule->head->itemid);

	rule = worker->rule;
	worker->rule = NULL;

	data = rule->head;
	rule->head = rule->head->next;

	if (NULL == rule->head)
	{
		zbx_hashset_remove_direct(&manager->rule_index, rule);
	}
	else
	{
		rule->head->prev = NULL;
		rule->values_num--;
		lld_queue_rule(manager, rule);
	}

	lld_data_free(data);

	if (SUCCEED != zbx_binary_heap_empty(&manager->rule_queue))
		lld_process_next_request(manager, worker);
	else
		zbx_queue_ptr_push(&manager->free_workers, worker);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes external diagnostic statistics request                  *
 *                                                                            *
 * Parameters: manager - [IN] the LLD manager                                 *
 * Parameters: client  - [IN] the external IPC connection                     *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_diag_stats(zbx_lld_manager_t *manager, zbx_ipc_client_t *client)
{
	unsigned char	*data;
	zbx_uint32_t	data_len;

	data_len = zbx_lld_serialize_diag_stats(&data, manager->rule_index.num_data, manager->queued_num);
	zbx_ipc_client_send(client, ZBX_IPC_LLD_DIAG_STATS_RESULT, data, data_len);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sort lld manager cache item view by second value                  *
 *          (number of values) in descending order                            *
 *                                                                            *
 ******************************************************************************/
static int	lld_diag_item_compare_values_desc(const void *d1, const void *d2)
{
	zbx_lld_rule_info_t	*r1 = *(zbx_lld_rule_info_t **)d1;
	zbx_lld_rule_info_t	*r2 = *(zbx_lld_rule_info_t **)d2;

	return r2->values_num - r1->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes external top items request                              *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected worker IPC client data            *
 *             message - [IN] the received message                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_top_items(zbx_lld_manager_t *manager, zbx_ipc_client_t *client,
		const zbx_ipc_message_t *message)
{
	int			limit;
	unsigned char		*data;
	zbx_uint32_t		data_len;
	zbx_vector_ptr_t	view;
	zbx_hashset_iter_t	iter;
	zbx_hashset_t		rule_infos;
	zbx_lld_rule_t		*rule;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_lld_deserialize_top_items_request(message->data, &limit);

	zbx_hashset_create(&rule_infos, MAX(1000, (size_t)manager->rule_index.num_data), ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_ptr_create(&view);

	zbx_hashset_iter_reset(&manager->rule_index, &iter);
	while (NULL != (rule = (zbx_lld_rule_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_lld_data_t	*data_ptr;

		for (data_ptr = rule->head; NULL != data_ptr; data_ptr = data_ptr->next)
		{
			zbx_lld_rule_info_t	*rule_info, rule_info_local = {.itemid = data_ptr->itemid};

			rule_info = (zbx_lld_rule_info_t *)zbx_hashset_search(&rule_infos, &rule_info_local);

			if (NULL == rule_info)
			{
				rule_info = (zbx_lld_rule_info_t *)zbx_hashset_insert(&rule_infos, &rule_info_local,
						sizeof(zbx_lld_rule_info_t));
				zbx_vector_ptr_append(&view, rule_info);
			}

			rule_info->values_num++;
		}
	}

	zbx_vector_ptr_sort(&view, lld_diag_item_compare_values_desc);

	data_len = zbx_lld_serialize_top_items_result(&data, (const zbx_lld_rule_info_t **)view.values,
			MIN(limit, view.values_num));
	zbx_ipc_client_send(client, ZBX_IPC_LLD_TOP_ITEMS_RESULT, data, data_len);

	zbx_free(data);
	zbx_vector_ptr_destroy(&view);
	zbx_hashset_destroy(&rule_infos);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: main processing loop                                              *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(lld_manager_thread, args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_ipc_service_t	lld_service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	double			time_stat, time_now, sec, time_idle = 0;
	zbx_lld_manager_t	manager;
	zbx_uint64_t		processed_num = 0;
	int			ret;
	zbx_timespec_t		timeout = {1, 0};

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

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [processed " ZBX_FS_UI64 " LLD rules, idle " ZBX_FS_DBL
					"sec during " ZBX_FS_DBL " sec]",
					get_process_type_string(process_type), process_num, processed_num,
					time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&lld_service, &timeout, &client, &message);
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
					lld_queue_request(&manager, message);
					lld_process_queue(&manager);
					break;
				case ZBX_IPC_LLD_DONE:
					lld_process_result(&manager, client);
					processed_num++;
					manager.queued_num--;
					break;
				case ZBX_IPC_LLD_QUEUE:
					zbx_ipc_client_send(client, message->code, (unsigned char *)&manager.queued_num,
							sizeof(zbx_uint64_t));
					break;
				case ZBX_IPC_LLD_DIAG_STATS:
					lld_process_diag_stats(&manager, client);
					break;
				case ZBX_IPC_LLD_TOP_ITEMS:
					lld_process_top_items(&manager, client, message);
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_service_close(&lld_service);
	lld_manager_destroy(&manager);
}
