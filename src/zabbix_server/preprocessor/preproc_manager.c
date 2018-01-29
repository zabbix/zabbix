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

#include "dbcache.h"
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "zbxserver.h"
#include "sysinfo.h"
#include "zbxserialize.h"
#include "zbxipcservice.h"

#include "preprocessing.h"
#include "preproc_manager.h"
#include "linked_list.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num, CONFIG_PREPROCESSOR_FORKS;

#define ZBX_PREPROCESSING_MANAGER_DELAY	1

#define ZBX_PREPROC_PRIORITY_NONE	0
#define ZBX_PREPROC_PRIORITY_FIRST	1

typedef enum
{
	REQUEST_STATE_QUEUED		= 0,		/* requires preprocessing */
	REQUEST_STATE_PROCESSING	= 1,		/* is being preprocessed  */
	REQUEST_STATE_DONE		= 2,		/* value is set, waiting for flush */
	REQUEST_STATE_PENDING		= 3		/* value requires preprocessing, */
							/* but is waiting on other request to complete */
}
zbx_preprocessing_states_t;

/* preprocessing request */
typedef struct preprocessing_request
{
	zbx_preprocessing_states_t	state;		/* request state */
	struct preprocessing_request	*pending;	/* the request waiting on this request to complete */
	zbx_preproc_item_value_t	value;		/* unpacked item value */
	zbx_preproc_op_t		*steps;		/* preprocessing steps */
	int				steps_num;	/* number of preprocessing steps */
	unsigned char			value_type;	/* value type from configuration */
							/* at the beginning of preprocessing queue */
}
zbx_preprocessing_request_t;

/* preprocessing worker data */
typedef struct
{
	zbx_ipc_client_t	*client;	/* the connected preprocessing worker client */
	zbx_list_item_t		*queue_item;	/* queued item */
}
zbx_preprocessing_worker_t;

/* delta item index */
typedef struct
{
	zbx_uint64_t		itemid;		/* item id */
	zbx_list_item_t		*queue_item;	/* queued item */
}
zbx_delta_item_index_t;

/* preprocessing manager data */
typedef struct
{
	zbx_preprocessing_worker_t	*workers;	/* preprocessing worker array */
	int				worker_count;	/* preprocessing worker count */
	zbx_list_t			queue;		/* queue of item values */
	zbx_hashset_t			item_config;	/* item configuration L2 cache */
	zbx_hashset_t			history_cache;	/* item value history cache for delta preprocessing */
	zbx_hashset_t			delta_items;	/* delta items placed in queue */
	int				cache_ts;	/* cache timestamp */
	zbx_uint64_t			processed_num;	/* processed value counter */
	zbx_uint64_t			queued_num;	/* queued value counter */
	zbx_uint64_t			preproc_num;	/* queued values with preprocessing steps */
	zbx_list_iterator_t		priority_tail;	/* iterator to the last queued priority item */
}
zbx_preprocessing_manager_t;

static void	preprocessor_enqueue_dependent(zbx_preprocessing_manager_t *manager,
		zbx_preproc_item_value_t *value, zbx_list_item_t *master);

/* cleanup functions */

static void	preproc_item_clear(zbx_preproc_item_t *item)
{
	int	i;

	zbx_free(item->dep_itemids);

	for (i = 0; i < item->preproc_ops_num; i++)
		zbx_free(item->preproc_ops[i].params);

	zbx_free(item->preproc_ops);
}

static void	request_free_steps(zbx_preprocessing_request_t *request)
{
	while (0 < request->steps_num--)
		zbx_free(request->steps[request->steps_num].params);

	zbx_free(request->steps);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_sync_configuration                                  *
 *                                                                            *
 * Purpose: synchronize preprocessing manager with configuration cache data   *
 *                                                                            *
 * Parameters: manager - [IN] the manager to be synchronized                  *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_sync_configuration(zbx_preprocessing_manager_t *manager)
{
	const char			*__function_name = "preprocessor_sync_configuration";
	zbx_hashset_iter_t		iter;
	zbx_preproc_item_t		*item, item_local;
	zbx_item_history_value_t	*history_value;
	int				ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ts = manager->cache_ts;
	DCconfig_get_preprocessable_items(&manager->item_config, &manager->cache_ts);

	if (ts != manager->cache_ts)
	{
		zbx_hashset_iter_reset(&manager->history_cache, &iter);
		while (NULL != (history_value = (zbx_item_history_value_t *)zbx_hashset_iter_next(&iter)))
		{
			item_local.itemid = history_value->itemid;
			if (NULL == (item = (zbx_preproc_item_t *)zbx_hashset_search(&manager->item_config, &item_local)) ||
					history_value->value_type != item->value_type)
			{
				/* history value is removed if item was removed/disabled or item value type changed */
				zbx_hashset_iter_remove(&iter);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() item config size: %d, history cache size: %d", __function_name,
			manager->item_config.num_data, manager->history_cache.num_data);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_get_queued_item                                     *
 *                                                                            *
 * Purpose: get queued item value with no dependencies (or with resolved      *
 *          dependencies)                                                     *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *                                                                            *
 * Return value: pointer to the queued item or NULL if none                   *
 *                                                                            *
 ******************************************************************************/
static zbx_list_item_t	*preprocessor_get_queued_item(zbx_preprocessing_manager_t *manager)
{
	const char			*__function_name = "preprocessor_get_queued_item";
	zbx_list_iterator_t		iterator;
	zbx_preprocessing_request_t	*request;
	zbx_list_item_t			*item = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_list_iterator_init(&manager->queue, &iterator);
	while (SUCCEED == zbx_list_iterator_next(&iterator))
	{
		zbx_list_iterator_peek(&iterator, (void **)&request);

		if (REQUEST_STATE_QUEUED == request->state)
		{
			/* queued item is found */
			item = iterator.current;
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return item;
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_get_worker_by_client                                *
 *                                                                            *
 * Purpose: get worker data by IPC client                                     *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] IPC client                                      *
 *                                                                            *
 * Return value: pointer to the worker data                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_preprocessing_worker_t	*preprocessor_get_worker_by_client(zbx_preprocessing_manager_t *manager,
		zbx_ipc_client_t *client)
{
	int				i;
	zbx_preprocessing_worker_t	*worker = NULL;

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

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_get_free_worker                                     *
 *                                                                            *
 * Purpose: get worker without active preprocessing task                      *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *                                                                            *
 * Return value: pointer to the worker data or NULL if none                   *
 *                                                                            *
 ******************************************************************************/
static zbx_preprocessing_worker_t	*preprocessor_get_free_worker(zbx_preprocessing_manager_t *manager)
{
	int	i;

	for (i = 0; i < manager->worker_count; i++)
	{
		if (NULL == manager->workers[i].queue_item)
			return &manager->workers[i];
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_create_task                                         *
 *                                                                            *
 * Purpose: create preprocessing task for request                             *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             request - [IN] preprocessing request                           *
 *             task    - [OUT] preprocessing task data                        *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	preprocessor_create_task(zbx_preprocessing_manager_t *manager,
		zbx_preprocessing_request_t *request, unsigned char **task)
{
	zbx_uint32_t	size;
	zbx_variant_t	value;

	if (ISSET_LOG(request->value.result))
		zbx_variant_set_str(&value, request->value.result->log->value);
	else if (ISSET_UI64(request->value.result))
		zbx_variant_set_ui64(&value, request->value.result->ui64);
	else if (ISSET_DBL(request->value.result))
		zbx_variant_set_dbl(&value, request->value.result->dbl);
	else if (ISSET_STR(request->value.result))
		zbx_variant_set_str(&value, request->value.result->str);
	else if (ISSET_TEXT(request->value.result))
		zbx_variant_set_str(&value, request->value.result->text);
	else
		THIS_SHOULD_NEVER_HAPPEN;

	size = zbx_preprocessor_pack_task(task, request->value.itemid, request->value_type, request->value.ts, &value,
			(zbx_item_history_value_t *)zbx_hashset_search(&manager->history_cache, &request->value.itemid), request->steps,
			request->steps_num);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_assign_tasks                                        *
 *                                                                            *
 * Purpose: assign available queued preprocessing tasks to free workers       *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_assign_tasks(zbx_preprocessing_manager_t *manager)
{
	const char			*__function_name = "preprocessor_assign_tasks";
	zbx_list_item_t			*queue_item;
	zbx_preprocessing_request_t	*request;
	zbx_preprocessing_worker_t	*worker;
	zbx_uint32_t			size;
	unsigned char			*task;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (NULL != (worker = preprocessor_get_free_worker(manager)) &&
			NULL != (queue_item = preprocessor_get_queued_item(manager)))
	{
		request = (zbx_preprocessing_request_t *)queue_item->data;
		size = preprocessor_create_task(manager, request, &task);

		if (FAIL == zbx_ipc_client_send(worker->client, ZBX_IPC_PREPROCESSOR_REQUEST, task, size))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot send data to preprocessing worker");
			exit(EXIT_FAILURE);
		}

		request->state = REQUEST_STATE_PROCESSING;
		worker->queue_item = queue_item;

		request_free_steps(request);
		zbx_free(task);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preproc_item_value_clear                                         *
 *                                                                            *
 * Purpose: frees resources allocated by preprocessor item value              *
 *                                                                            *
 * Parameters: value - [IN] value to be freed                                 *
 *                                                                            *
 ******************************************************************************/
static void	preproc_item_value_clear(zbx_preproc_item_value_t *value)
{
	zbx_free(value->error);
	if (NULL != value->result)
	{
		free_result(value->result);
		zbx_free(value->result);
	}
	zbx_free(value->ts);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_free_request                                        *
 *                                                                            *
 * Purpose: free preprocessing request                                        *
 *                                                                            *
 * Parameters: request - [IN] request data to be freed                        *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_free_request(zbx_preprocessing_request_t *request)
{
	preproc_item_value_clear(&request->value);

	request_free_steps(request);
	zbx_free(request);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_flush_value                                         *
 *                                                                            *
 * Purpose: add new value to the local history cache                          *
 *                                                                            *
 * Parameters: value - [IN] value to be added                                 *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_flush_value(zbx_preproc_item_value_t *value)
{
	dc_add_history(value->itemid, value->item_value_type, value->item_flags, value->result, value->ts, value->state,
			value->error);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessing_flush_queue                                        *
 *                                                                            *
 * Purpose: add all sequential processed values from beginning of the queue   *
 *          to the local history cache                                        *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *                                                                            *
 ******************************************************************************/
static void	preprocessing_flush_queue(zbx_preprocessing_manager_t *manager)
{
	zbx_preprocessing_request_t	*request;
	zbx_list_iterator_t		iterator;

	zbx_list_iterator_init(&manager->queue, &iterator);
	while (SUCCEED == zbx_list_iterator_next(&iterator))
	{
		zbx_list_iterator_peek(&iterator, (void **)&request);

		if (REQUEST_STATE_DONE != request->state)
			break;

		preprocessor_flush_value(&request->value);
		preprocessor_free_request(request);

		if (SUCCEED == zbx_list_iterator_equal(&iterator, &manager->priority_tail))
			zbx_list_iterator_clear(&manager->priority_tail);

		zbx_list_pop(&manager->queue, NULL);

		manager->processed_num++;
		manager->queued_num--;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_link_delta_items                                    *
 *                                                                            *
 * Purpose: create relation between multiple same delta item values within    *
 *          value queue                                                       *
 *                                                                            *
 * Parameters: manager     - [IN] preprocessing manager                       *
 *             enqueued_at - [IN] position in value queue                     *
 *             item        - [IN] item configuration data                     *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_link_delta_items(zbx_preprocessing_manager_t *manager, zbx_list_item_t *enqueued_at,
		zbx_preproc_item_t *item)
{
	int				i;
	zbx_preprocessing_request_t	*request, *dep_request;
	zbx_delta_item_index_t		*index, index_local;
	zbx_preproc_op_t		*op;

	for (i = 0; i < item->preproc_ops_num; i++)
	{
		op = &item->preproc_ops[i];

		if (ZBX_PREPROC_DELTA_VALUE == op->type || ZBX_PREPROC_DELTA_SPEED == op->type)
			break;
	}

	if (i != item->preproc_ops_num)
	{
		/* existing delta item*/
		if (NULL != (index = (zbx_delta_item_index_t *)zbx_hashset_search(&manager->delta_items, &item->itemid)))
		{
			dep_request = (zbx_preprocessing_request_t *)(enqueued_at->data);
			request = (zbx_preprocessing_request_t *)(index->queue_item->data);

			if (REQUEST_STATE_DONE != request->state)
			{
				request->pending = dep_request;
				dep_request->state = REQUEST_STATE_PENDING;
			}

			index->queue_item = enqueued_at;
		}
		else
		{
			index_local.itemid = item->itemid;
			index_local.queue_item = enqueued_at;

			zbx_hashset_insert(&manager->delta_items, &index_local, sizeof(zbx_delta_item_index_t));
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_copy_value                                          *
 *                                                                            *
 * Purpose: create a copy of existing item value                              *
 *                                                                            *
 * Parameters: target  - [OUT] created copy                                   *
 *             source  - [IN]  value to be copied                             *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_copy_value(zbx_preproc_item_value_t *target, zbx_preproc_item_value_t *source)
{
	memcpy(target, source, sizeof(zbx_preproc_item_value_t));

	if (NULL != source->error)
		target->error = zbx_strdup(NULL, source->error);

	if (NULL != source->ts)
	{
		target->ts = (zbx_timespec_t *)zbx_malloc(NULL, sizeof(zbx_timespec_t));
		memcpy(target->ts, source->ts, sizeof(zbx_timespec_t));
	}

	if (NULL != source->result)
	{
		target->result = (AGENT_RESULT *)zbx_malloc(NULL, sizeof(AGENT_RESULT));
		memcpy(target->result, source->result, sizeof(AGENT_RESULT));

		if (NULL != source->result->str)
			target->result->str = zbx_strdup(NULL, source->result->str);

		if (NULL != source->result->text)
			target->result->text = zbx_strdup(NULL, source->result->text);

		if (NULL != source->result->msg)
			target->result->msg = zbx_strdup(NULL, source->result->msg);

		if (NULL != source->result->log)
		{
			target->result->log = (zbx_log_t *)zbx_malloc(NULL, sizeof(zbx_log_t));
			memcpy(target->result->log, source->result->log, sizeof(zbx_log_t));

			if (NULL != source->result->log->value)
				target->result->log->value = zbx_strdup(NULL, source->result->log->value);

			if (NULL != source->result->log->source)
				target->result->log->source = zbx_strdup(NULL, source->result->log->source);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_enqueue                                             *
 *                                                                            *
 * Purpose: enqueue preprocessing request                                     *
 *                                                                            *
 * Parameters: manage   - [IN] preprocessing manager                          *
 *             value    - [IN] item value                                     *
 *             master   - [IN] request should be enqueued after this item     *
 *                             (NULL for the end of the queue)                *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_enqueue(zbx_preprocessing_manager_t *manager, zbx_preproc_item_value_t *value,
		zbx_list_item_t *master)
{
	const char			*__function_name = "preprocessor_enqueue";
	zbx_preprocessing_request_t	*request;
	zbx_preproc_item_t		*item, item_local;
	zbx_list_item_t			*enqueued_at;
	int				i;
	zbx_preprocessing_states_t	state;
	unsigned char			priority = ZBX_PREPROC_PRIORITY_NONE;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid: %" PRIu64, __function_name, value->itemid);

	item_local.itemid = value->itemid;
	item = (zbx_preproc_item_t *)zbx_hashset_search(&manager->item_config, &item_local);

	/* override priority based on item type */
	if (NULL != item && ITEM_TYPE_INTERNAL == item->type)
		priority = ZBX_PREPROC_PRIORITY_FIRST;

	if (NULL == item || 0 == item->preproc_ops_num || NULL == value->result || 0 == ISSET_VALUE(value->result))
	{
		state = REQUEST_STATE_DONE;

		if (NULL == manager->queue.head)
		{
			/* queue is empty and item is done, it can be flushed */
			preprocessor_flush_value(value);
			manager->processed_num++;
			preprocessor_enqueue_dependent(manager, value, NULL);
			preproc_item_value_clear(value);

			goto out;
		}
	}
	else
		state = REQUEST_STATE_QUEUED;

	request = (zbx_preprocessing_request_t *)zbx_malloc(NULL, sizeof(zbx_preprocessing_request_t));
	memset(request, 0, sizeof(zbx_preprocessing_request_t));
	memcpy(&request->value, value, sizeof(zbx_preproc_item_value_t));
	request->state = state;

	if (REQUEST_STATE_QUEUED == state)
	{
		request->value_type = item->value_type;
		request->steps = (zbx_preproc_op_t *)zbx_malloc(NULL, sizeof(zbx_preproc_op_t) * item->preproc_ops_num);
		request->steps_num = item->preproc_ops_num;

		for (i = 0; i < item->preproc_ops_num; i++)
		{
			request->steps[i].type = item->preproc_ops[i].type;
			request->steps[i].params = zbx_strdup(NULL, item->preproc_ops[i].params);
		}

		manager->preproc_num++;
	}

	/* priority items are enqueued at the beginning of the line */
	if (NULL == master && ZBX_PREPROC_PRIORITY_FIRST == priority)
	{
		if (SUCCEED == zbx_list_iterator_isset(&manager->priority_tail))
		{
			/* insert after the last internal item */
			zbx_list_insert_after(&manager->queue, manager->priority_tail.current, request, &enqueued_at);
			zbx_list_iterator_update(&manager->priority_tail);
		}
		else
		{
			/* no internal items in queue, insert at the beginning */
			zbx_list_prepend(&manager->queue, request, &enqueued_at);
			zbx_list_iterator_init(&manager->queue, &manager->priority_tail);
		}

		zbx_list_iterator_next(&manager->priority_tail);
	}
	else
	{
		zbx_list_insert_after(&manager->queue, master, request, &enqueued_at);
		zbx_list_iterator_update(&manager->priority_tail);

		/* move internal item tail position if we are inserting after last internal item */
		if (NULL != master && master == manager->priority_tail.current)
			zbx_list_iterator_next(&manager->priority_tail);
	}

	if (REQUEST_STATE_QUEUED == request->state)
		preprocessor_link_delta_items(manager, enqueued_at, item);

	/* if no preprocessing is needed, dependent items are enqueued */
	if (REQUEST_STATE_DONE == request->state)
		preprocessor_enqueue_dependent(manager, value, enqueued_at);

	manager->queued_num++;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_enqueue_dependent                                   *
 *                                                                            *
 * Purpose: enqueue dependent items (if any)                                  *
 *                                                                            *
 * Parameters: manager      - [IN] preprocessing manager                      *
 *             source_value - [IN] master item value                          *
 *             master       - [IN] dependent item should be enqueued after    *
 *                                 this item                                  *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_enqueue_dependent(zbx_preprocessing_manager_t *manager,
		zbx_preproc_item_value_t *source_value, zbx_list_item_t *master)
{
	const char			*__function_name = "preprocessor_enqueue_dependent";
	int				i;
	zbx_preproc_item_t		*item, item_local;
	zbx_preproc_item_value_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid: %" PRIu64, __function_name, source_value->itemid);

	if (NULL != source_value->result && ISSET_VALUE(source_value->result))
	{
		item_local.itemid = source_value->itemid;
		if (NULL != (item = (zbx_preproc_item_t *)zbx_hashset_search(&manager->item_config, &item_local)) &&
				0 != item->dep_itemids_num)
		{
			for (i = item->dep_itemids_num - 1; i >= 0; i--)
			{
				preprocessor_copy_value(&value, source_value);
				value.itemid = item->dep_itemids[i];
				preprocessor_enqueue(manager, &value, master);
			}

			preprocessor_assign_tasks(manager);
			preprocessing_flush_queue(manager);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_add_request                                         *
 *                                                                            *
 * Purpose: handle new preprocessing request                                  *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             message - [IN] packed preprocessing request                    *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_add_request(zbx_preprocessing_manager_t *manager, zbx_ipc_message_t *message)
{
	const char			*__function_name = "preprocessor_add_request";
	zbx_uint32_t			offset = 0;
	zbx_preproc_item_value_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	preprocessor_sync_configuration(manager);

	while (offset < message->size)
	{
		offset += zbx_preprocessor_unpack_value(&value, message->data + offset);
		preprocessor_enqueue(manager, &value, NULL);
	}

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_set_variant_result                                  *
 *                                                                            *
 * Purpose: get result data from variant and error message                    *
 *                                                                            *
 * Parameters: request - [IN/OUT] preprocessing request                       *
 *             value   - [IN] variant value                                   *
 *             error   - [IN] error message (if any)                          *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_set_variant_result(zbx_preprocessing_request_t *request, zbx_variant_t *value, char *error)
{
	int		type, ret = FAIL;
	zbx_log_t	*log;

	if (NULL != error)
	{
		/* on error item state is set to ITEM_STATE_NOTSUPPORTED */
		request->value.state = ITEM_STATE_NOTSUPPORTED;
		request->value.error = error;
		ret = FAIL;

		goto out;
	}

	if (ZBX_VARIANT_NONE == value->type)
	{
		UNSET_UI64_RESULT(request->value.result);
		UNSET_DBL_RESULT(request->value.result);
		UNSET_STR_RESULT(request->value.result);
		UNSET_TEXT_RESULT(request->value.result);
		UNSET_LOG_RESULT(request->value.result);
		UNSET_MSG_RESULT(request->value.result);
		ret = FAIL;

		goto out;
	}

	switch (request->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			type = ZBX_VARIANT_DBL;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			type = ZBX_VARIANT_UI64;
			break;
		default:
			/* ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_LOG */
			type = ZBX_VARIANT_STR;
	}

	if (FAIL != (ret = zbx_variant_convert(value, type)))
	{
		switch (request->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				UNSET_RESULT_EXCLUDING(request->value.result, AR_DOUBLE);
				SET_DBL_RESULT(request->value.result, value->data.dbl);
				break;
			case ITEM_VALUE_TYPE_STR:
				UNSET_RESULT_EXCLUDING(request->value.result, AR_STRING);
				UNSET_STR_RESULT(request->value.result);
				SET_STR_RESULT(request->value.result, value->data.str);
				break;
			case ITEM_VALUE_TYPE_LOG:
				UNSET_RESULT_EXCLUDING(request->value.result, AR_LOG);
				if (ISSET_LOG(request->value.result))
				{
					log = GET_LOG_RESULT(request->value.result);
					zbx_free(log->value);
				}
				else
				{
					log = (zbx_log_t *)zbx_malloc(NULL, sizeof(zbx_log_t));
					memset(log, 0, sizeof(zbx_log_t));
					SET_LOG_RESULT(request->value.result, log);
				}
				log->value = value->data.str;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				UNSET_RESULT_EXCLUDING(request->value.result, AR_UINT64);
				SET_UI64_RESULT(request->value.result, value->data.ui64);
				break;
			case ITEM_VALUE_TYPE_TEXT:
				UNSET_RESULT_EXCLUDING(request->value.result, AR_TEXT);
				UNSET_TEXT_RESULT(request->value.result);
				SET_TEXT_RESULT(request->value.result, value->data.str);
				break;
		}

		zbx_variant_set_none(value);
	}
	else
	{
		zbx_free(request->value.error);
		request->value.error = zbx_dsprintf(NULL, "Value \"%s\" of type \"%s\" is not suitable for"
			" value type \"%s\"", zbx_variant_value_desc(value), zbx_variant_type_desc(value),
			zbx_item_value_type_string((zbx_item_value_type_t)request->value_type));

		request->value.state = ITEM_STATE_NOTSUPPORTED;
		ret = FAIL;
	}

out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_add_result                                          *
 *                                                                            *
 * Purpose: handle preprocessing result                                       *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] IPC client                                      *
 *             message - [IN] packed preprocessing result                     *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_add_result(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	const char			*__function_name = "preprocessor_add_result";
	zbx_preprocessing_worker_t	*worker;
	zbx_preprocessing_request_t	*request;
	zbx_variant_t			value;
	char				*error;
	zbx_item_history_value_t	*history_value, *cached_value;
	zbx_delta_item_index_t		*index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	worker = preprocessor_get_worker_by_client(manager, client);
	request = (zbx_preprocessing_request_t *)worker->queue_item->data;

	zbx_preprocessor_unpack_result(&value, &history_value, &error, message->data);

	if (NULL != history_value)
	{
		history_value->itemid = request->value.itemid;
		history_value->value_type = request->value_type;

		if (NULL != (cached_value = (zbx_item_history_value_t *)zbx_hashset_search(&manager->history_cache, history_value)))
		{
			if (0 < zbx_timespec_compare(&history_value->timestamp, &cached_value->timestamp))
			{
				/* history_value can only be numeric so it can be copied without extra memory */
				/* allocation */
				cached_value->timestamp = history_value->timestamp;
				cached_value->value = history_value->value;
			}
		}
		else
			zbx_hashset_insert(&manager->history_cache, history_value, sizeof(zbx_item_history_value_t));
	}

	request->state = REQUEST_STATE_DONE;

	/* value processed - the pending value can now be processed */
	if (NULL != request->pending)
		request->pending->state = REQUEST_STATE_QUEUED;

	if (NULL != (index = (zbx_delta_item_index_t *)zbx_hashset_search(&manager->delta_items, &request->value.itemid)) &&
			worker->queue_item == index->queue_item)
	{
		/* item is removed from delta index if it was present in delta item index*/
		zbx_hashset_remove_direct(&manager->delta_items, index);
	}

	if (FAIL != preprocessor_set_variant_result(request, &value, error))
		preprocessor_enqueue_dependent(manager, &request->value, worker->queue_item);

	worker->queue_item = NULL;
	zbx_variant_clear(&value);
	zbx_free(history_value);

	manager->preproc_num--;

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_init_manager                                        *
 *                                                                            *
 * Purpose: initializes preprocessing manager                                 *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_init_manager(zbx_preprocessing_manager_t *manager)
{
	const char	*__function_name = "preprocessor_init_manager";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers: %d", __function_name, CONFIG_PREPROCESSOR_FORKS);

	memset(manager, 0, sizeof(zbx_preprocessing_manager_t));

	manager->workers = (zbx_preprocessing_worker_t *)zbx_calloc(NULL, CONFIG_PREPROCESSOR_FORKS, sizeof(zbx_preprocessing_worker_t));
	zbx_list_create(&manager->queue);
	zbx_hashset_create_ext(&manager->item_config, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			(zbx_clean_func_t)preproc_item_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	zbx_hashset_create(&manager->delta_items, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&manager->history_cache, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_register_worker                                     *
 *                                                                            *
 * Purpose: registers preprocessing worker                                    *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected preprocessing worker              *
 *             message - [IN] message recieved by preprocessing manager       *
 *                                                                            *
 ******************************************************************************/
static void preprocessor_register_worker(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	const char			*__function_name = "preprocessor_register_worker";
	zbx_preprocessing_worker_t	*worker = NULL;
	pid_t				ppid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	memcpy(&ppid, message->data, sizeof(ppid));

	if (ppid != getppid())
	{
		zbx_ipc_client_close(client);
		zabbix_log(LOG_LEVEL_DEBUG, "refusing connection from foreign process");
	}
	else
	{
		if (CONFIG_PREPROCESSOR_FORKS == manager->worker_count)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		worker = (zbx_preprocessing_worker_t *)&manager->workers[manager->worker_count++];
		worker->client = client;

		preprocessor_assign_tasks(manager);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_destroy_manager                                     *
 *                                                                            *
 * Purpose: destroy preprocessing manager                                     *
 *                                                                            *
 * Parameters: manager - [IN] the manager to destroy                          *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_destroy_manager(zbx_preprocessing_manager_t *manager)
{
	zbx_preprocessing_request_t	*request;

	zbx_free(manager->workers);

	/* this is the place where values are lost */
	while (SUCCEED == zbx_list_pop(&manager->queue, (void **)&request))
		preprocessor_free_request(request);

	zbx_list_destroy(&manager->queue);

	zbx_hashset_destroy(&manager->item_config);
	zbx_hashset_destroy(&manager->delta_items);
	zbx_hashset_destroy(&manager->history_cache);
}

ZBX_THREAD_ENTRY(preprocessing_manager_thread, args)
{
	zbx_ipc_service_t		service;
	char				*error = NULL;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	zbx_preprocessing_manager_t	manager;
	int				ret;
	double				time_stat, time_idle = 0, time_now, time_flush, time_file = 0;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_PREPROCESSING, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start preprocessing service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	preprocessor_init_manager(&manager);

	/* initialize statistics */
	time_stat = zbx_time();
	time_flush = time_stat;

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	for (;;)
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [queued %d, processed %d values, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					manager.queued_num, manager.processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			manager.processed_num = 0;
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, ZBX_PREPROCESSING_MANAGER_DELAY, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

		/* handle /etc/resolv.conf update and log rotate less often than once a second */
		if (1.0 < time_now - time_file)
		{
			time_file = time_now;
			zbx_handle_log();
#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
			zbx_update_resolver_conf();
#endif
		}

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += zbx_time() - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_PREPROCESSOR_WORKER:
					preprocessor_register_worker(&manager, client, message);
					break;

				case ZBX_IPC_PREPROCESSOR_REQUEST:
					preprocessor_add_request(&manager, message);
					break;

				case ZBX_IPC_PREPROCESSOR_RESULT:
					preprocessor_add_result(&manager, client, message);
					break;

				case ZBX_IPC_PREPROCESSOR_QUEUE:
					zbx_ipc_client_send(client, message->code, (unsigned char *)&manager.queued_num,
							sizeof(zbx_uint64_t));
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (0 == manager.preproc_num || 1 < time_now - time_flush)
		{
			dc_flush_history();
			time_flush = time_now;
		}
	}

	zbx_ipc_service_close(&service);
	preprocessor_destroy_manager(&manager);

	return 0;
#undef STAT_INTERVAL
}
