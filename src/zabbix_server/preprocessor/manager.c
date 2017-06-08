/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "zbxpreproc.h"
#include "manager.h"
#include "queue.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num, CONFIG_PREPROCESSOR_FORKS;

#define ZBX_PREPROCESSING_MANAGER_DELAY	1

typedef enum
{
	REQUEST_STATE_QUEUED		= 0,		/* requires preprocessing */
	REQUEST_STATE_PROCESSING	= 1,		/* is being preprocessed  */
	REQUEST_STATE_DONE		= 2		/* value is set, waiting for flush */
}
preprocessing_states;

/* preprocessing request */
typedef struct preprocessing_request
{
	preprocessing_states		state;		/* request state */
	struct preprocessing_request	*dependency;	/* other request that current request depends on */
	int				locks;		/* count of dependent requests in queue */
	zbx_ipc_message_t		*source;	/* source message */
	zbx_preproc_item_value_t	*value;		/* unpacked item value */
	int				step_count;	/* preprocessing step count */
	zbx_item_preproc_t		*steps;		/* preprocessing steps */
	unsigned char			value_type;	/* value type from configuration */
}
preprocessing_request_t;

/* preprocessing worker data */
typedef struct
{
	zbx_ipc_client_t	*client;	/* the connected preprocessing worker client */
	queue_item_t		*queue_item;	/* queued item */
}
preprocessing_worker_t;

/* delta item index */
typedef struct
{
	zbx_uint64_t	itemid;		/* item id */
	queue_item_t	*queue_item;	/* queued item */
}
delta_item_index_t;

/* preprocessing manager data */
typedef struct
{
	preprocessing_worker_t	*workers;	/* preprocessing worker array */
	int			worker_count;	/* preprocessing worker count */
	queue_t			queue;		/* queue of item values */
	zbx_hashset_t		item_config;	/* item configuration L2 cache */
	zbx_hashset_t		history_cache;	/* item value history cache for delta preprocessing */
	zbx_hashset_t		delta_items;	/* delta items placed in queue */
	int			cache_ts;	/* cache timestamp */
	unsigned int		lock;		/* bulk item flush counter */
	zbx_uint64_t		processed_num;	/* processed value counter */
	zbx_uint64_t		queued_num;	/* queued value counter */
}
preprocessing_manager_t;

static void	preprocessor_enqueue_dependent(preprocessing_manager_t *manager, preprocessing_request_t *request,
		queue_item_t *master);

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_sync_configuration                                  *
 *                                                                            *
 * Purpose: synchronize preprocessing manager with configuration cache data   *
 *                                                                            *
 * Parameters: manager - [IN] the manager to be synchronized                  *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_sync_configuration(preprocessing_manager_t *manager)
{
	const char			*__function_name = "preprocessor_sync_configuration";
	zbx_hashset_iter_t		iter;
	DC_ITEM				*item, item_local;
	zbx_item_history_value_t	*history_value;
	int				ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ts = manager->cache_ts;
	DCconfig_get_preprocessable_items(&manager->item_config, &manager->cache_ts);

	if (ts != manager->cache_ts)
	{
		zbx_hashset_iter_reset(&manager->history_cache, &iter);
		while (NULL != (history_value = zbx_hashset_iter_next(&iter)))
		{
			item_local.itemid = history_value->itemid;
			if (NULL == (item = zbx_hashset_search(&manager->item_config, &item_local)) ||
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
static queue_item_t	*preprocessor_get_queued_item(preprocessing_manager_t *manager)
{
	const char		*__function_name = "preprocessor_get_queued_item";
	queue_iterator_t	iterator;
	preprocessing_request_t	*request;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_queue_iterator_init(&manager->queue, &iterator);
	while (SUCCEED == zbx_queue_iterator_next(&iterator))
	{
		zbx_queue_iterator_peek(&iterator, (void **)&request);

		if (REQUEST_STATE_QUEUED == request->state && (NULL == request->dependency ||
				REQUEST_STATE_DONE == request->dependency->state))
		{
			/* queued item is found */
			return iterator.current;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return NULL;
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
static preprocessing_worker_t	*preprocessor_get_worker_by_client(preprocessing_manager_t *manager,
		zbx_ipc_client_t *client)
{
	int			i;
	preprocessing_worker_t	*worker = NULL;

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
static preprocessing_worker_t	*preprocessor_get_free_worker(preprocessing_manager_t *manager)
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
static zbx_uint32_t	preprocessor_create_task(preprocessing_manager_t *manager, preprocessing_request_t *request,
		unsigned char **task)
{
	zbx_uint32_t		size;
	zbx_variant_t		value;

	if (ISSET_LOG(request->value->result))
		zbx_variant_set_str(&value, request->value->result->log->value);
	else if (ISSET_UI64(request->value->result))
		zbx_variant_set_ui64(&value, request->value->result->ui64);
	else if (ISSET_DBL(request->value->result))
		zbx_variant_set_dbl(&value, request->value->result->dbl);
	else if (ISSET_STR(request->value->result))
		zbx_variant_set_str(&value, request->value->result->str);
	else if (ISSET_TEXT(request->value->result))
		zbx_variant_set_str(&value, request->value->result->text);
	else
		THIS_SHOULD_NEVER_HAPPEN;

	size = zbx_preprocessor_pack_task(task, request->value->itemid, request->value->ts, &value,
			zbx_hashset_search(&manager->history_cache, &request->value->itemid), request->step_count,
			request->steps);

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
static void	preprocessor_assign_tasks(preprocessing_manager_t *manager)
{
	const char		*__function_name = "preprocessor_assign_tasks";
	queue_item_t		*queue_item;
	preprocessing_request_t	*request;
	preprocessing_worker_t	*worker;
	zbx_uint32_t		size;
	unsigned char		*task;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (NULL != (worker = preprocessor_get_free_worker(manager)) &&
			NULL != (queue_item = preprocessor_get_queued_item(manager)))
	{
		request = (preprocessing_request_t *)&queue_item->data;
		size = preprocessor_create_task(manager, request, &task);

		if (FAIL == zbx_ipc_client_send(worker->client, ZBX_IPC_PREPROCESSOR_REQUEST, task, size))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot send data to preprocessing worker");
			exit(EXIT_FAILURE);
		}

		request->state = REQUEST_STATE_PROCESSING;
		worker->queue_item = queue_item;
		zbx_free(request->steps);
		zbx_free(task);

		if (NULL != request->dependency)
			request->dependency->locks--;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static void	preprocessor_free_request(preprocessing_request_t *request)
{
	zbx_ipc_message_free(request->source);
	zbx_free(request->steps);
}


/******************************************************************************
 *                                                                            *
 * Function: preprocessor_flush_request                                       *
 *                                                                            *
 * Purpose: add new value to the local history cache                          *
 *                                                                            *
 * Parameters: request - [IN] request to be added                             *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_flush_request(preprocessing_request_t *request)
{
	dc_add_history(request->value->itemid, request->value->item_flags, request->value->result,
			request->value->ts, request->value->state, request->value->error);
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
static void	preprocessing_flush_queue(preprocessing_manager_t *manager)
{
	preprocessing_request_t	*request;

	while (SUCCEED == zbx_queue_peek(&manager->queue, (void **)&request) && REQUEST_STATE_DONE == request->state &&
			0 == request->locks)
	{
		preprocessor_flush_request(request);
		preprocessor_free_request(request);
		zbx_queue_dequeue(&manager->queue, NULL);
		manager->processed_num++;
		manager->queued_num--;
	}

	/* flush item values to the history cache if no lock was requested */
	if (0 == manager->lock)
		dc_flush_history();
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_link_delta_items                                    *
 *                                                                            *
 * Purpose: create relation between multiple same delta item values within    *
 *          queue                                                             *
 *                                                                            *
 * Parameters: manager     - [IN] preprocessing manager                       *
 *             enqueued_at - [IN] position in queue                           *
 *             item        - [IN] item configuration data                     *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_link_delta_items(preprocessing_manager_t *manager, queue_item_t *enqueued_at,
		DC_ITEM *item)
{
	unsigned char		type;
	int			i;
	preprocessing_request_t	*request, *dependency;
	delta_item_index_t	*index, index_local;

	for (i = 0; i < item->preproc_ops_num; i++)
	{
		type = item->preproc_ops[i].type;
		if (ZBX_PREPROC_DELTA_VALUE == type || ZBX_PREPROC_DELTA_SPEED == type)
			break;
	}

	if (i != item->preproc_ops_num)
	{
		/* existing delta item*/
		if (NULL != (index = zbx_hashset_search(&manager->delta_items, &item->itemid)))
		{
			request = (preprocessing_request_t *)(&enqueued_at->data);
			dependency = (preprocessing_request_t *)(&index->queue_item->data);

			if (REQUEST_STATE_DONE != dependency->state)
			{
				request->dependency = dependency;
				dependency->locks++;
			}

			index->queue_item = enqueued_at;
		}
		else
		{
			index_local.itemid = item->itemid;
			index_local.queue_item = enqueued_at;

			zbx_hashset_insert(&manager->delta_items, &index_local, sizeof(delta_item_index_t));
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_enqueue                                             *
 *                                                                            *
 * Purpose: enqueue preprocessing request                                     *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             message - [IN] packed preprocessing request                    *
 *             master  - [IN] request should be enqueued after this item      *
 *                            (NULL for the end of the queue)                 *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_enqueue(preprocessing_manager_t *manager, zbx_ipc_message_t *message, queue_item_t *master)
{
	const char			*__function_name = "preprocessor_enqueue";
	preprocessing_request_t		request;
	DC_ITEM				*item, item_local;
	queue_item_t			*enqueued_at;
	zbx_preproc_item_value_t	*value;

	zbx_preprocessor_unpack_value(&value, message->data);
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid: %" PRIu64, __function_name, value->itemid);

	item_local.itemid = value->itemid;
	item = zbx_hashset_search(&manager->item_config, &item_local);
	memset(&request, 0, sizeof(preprocessing_request_t));
	request.source = message;
	request.value = value;

	if (NULL != value->result && ISSET_VALUE(value->result) && NULL != item && 0 != item->preproc_ops_num)
	{
		request.state = REQUEST_STATE_QUEUED;
		request.value_type = item->value_type;
		request.step_count = item->preproc_ops_num;
		request.steps = (zbx_item_preproc_t *)zbx_malloc(NULL, request.step_count * sizeof(zbx_item_preproc_t));
		memcpy(request.steps, item->preproc_ops, request.step_count * sizeof(zbx_item_preproc_t));
	}
	else
	{
		request.state = REQUEST_STATE_DONE;

		if (NULL == manager->queue.head || (NULL != item && ITEM_TYPE_INTERNAL == item->type))
		{
			/* queue is empty (or item is an internal check), item is done, it can be flushed */
			preprocessor_flush_request(&request);
			manager->processed_num++;
			preprocessing_flush_queue(manager);
			preprocessor_enqueue_dependent(manager, &request, NULL);
			preprocessor_free_request(&request);

			goto out;
		}
	}

	/* internal items are enqueued at the beginning of the line */
	if (NULL != item && ITEM_TYPE_INTERNAL == item->type)
		zbx_queue_enqueue_first(&manager->queue, &request, &enqueued_at);
	else
		zbx_queue_enqueue_after(&manager->queue, master, &request, &enqueued_at);

	preprocessor_link_delta_items(manager, enqueued_at, item);

	/* if no preprocessing is needed, dependent items are enqueued */
	if (REQUEST_STATE_DONE == request.state)
		preprocessor_enqueue_dependent(manager, &request, enqueued_at);

	manager->queued_num++;

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_process_command                                     *
 *                                                                            *
 * Purpose: allow switching from single value flush mode to bulk flush mode   *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             command - [IN] preprocessor command                            *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_process_command(preprocessing_manager_t *manager, unsigned char command)
{
	const char	*__function_name = "preprocessor_process_command";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	switch (command)
	{
		case ZBX_PREPROCESSOR_COMMAND_FLUSH:
			/* flush item values to the history cache */
			if (0 != manager->lock)
				manager->lock--;

			dc_flush_history();
		break;

		case ZBX_PREPROCESSOR_COMMAND_HOLD:
			/* use local value cache until it is full or until values are flushed */
			manager->lock++;
		break;

		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_process_command                                     *
 *                                                                            *
 * Purpose: allow switching from single value flush mode to bulk flush mode   *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             request - [IN] preprocessing request                           *
 *             master  - [IN] dependent item should be enqueued after this    *
 *                            item                                            *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_enqueue_dependent(preprocessing_manager_t *manager,
		preprocessing_request_t *request, queue_item_t *master)
{
	const char			*__function_name = "preprocessor_enqueue_dependent";
	int				i;
	DC_ITEM				*item, item_local;
	zbx_ipc_message_t		*message;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid: %" PRIu64, __function_name, request->value->itemid);

	if (NULL != request->value->result && ISSET_VALUE(request->value->result))
	{
		item_local.itemid = request->value->itemid;
		if (NULL != (item = zbx_hashset_search(&manager->item_config, &item_local)) &&
				0 != item->dependent_item_count)
		{
			for (i = 0; i < item->dependent_item_count; i++)
			{
				message = (zbx_ipc_message_t *)zbx_malloc(NULL, sizeof(zbx_ipc_message_t));
				zbx_ipc_message_copy(message, request->source);
				memcpy(message->data, &item->dependent_items[i], sizeof(zbx_uint64_t));
				preprocessor_enqueue(manager, message, master);
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
static void	preprocessor_add_request(preprocessing_manager_t *manager, zbx_ipc_message_t *message)
{
	const char			*__function_name = "preprocessor_add_request";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	preprocessor_sync_configuration(manager);
	preprocessor_enqueue(manager, message, NULL);
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
static int	preprocessor_set_variant_result(preprocessing_request_t *request, zbx_variant_t *value, char *error)
{
	int		type, ret = FAIL;
	char		*allocated = NULL;
	unsigned char	*data;

	if (NULL != error)
	{
		/* on error item state is set to ITEM_STATE_NOTSUPPORTED */
		request->value->state = ITEM_STATE_NOTSUPPORTED;
		request->value->error = error;
		ret = FAIL;

		goto out;
	}

	if (ZBX_VARIANT_NONE == value->type)
	{
		/* value is removed as there is none */
		request->value->result->type &= (AR_MESSAGE | AR_META);
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
				SET_DBL_RESULT(request->value->result, value->data.dbl);
				break;
			case ITEM_VALUE_TYPE_STR:
				SET_STR_RESULT(request->value->result, value->data.str);
				break;
			case ITEM_VALUE_TYPE_LOG:
				allocated = zbx_strdup(NULL, value->data.str);
				request->value->result->log->value = allocated;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				SET_DBL_RESULT(request->value->result, value->data.ui64);
				break;
			case ITEM_VALUE_TYPE_TEXT:
				allocated = zbx_strdup(NULL, value->data.str);
				SET_TEXT_RESULT(request->value->result, allocated);
				break;
		}
	}
	else
	{
		allocated = zbx_dsprintf(NULL, "Value \"%s\" of type \"%s\" is not suitable for"
			" value type \"%s\"", zbx_variant_value_desc(value), zbx_variant_type_desc(value),
			zbx_item_value_type_string(request->value_type));

		request->value->state = ITEM_STATE_NOTSUPPORTED;
		request->value->error = allocated;
		ret = FAIL;
	}

	if (NULL != allocated)
	{
		/* value was changed, it is being repacked */
		request->source->size = zbx_preprocessor_pack_value(&data, request->value);
		zbx_free(request->source->data);
		request->source->data = data;
		zbx_preprocessor_unpack_value(&request->value, data);
		zbx_free(allocated);
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
static void	preprocessor_add_result(preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	const char			*__function_name = "preprocessor_add_result";
	preprocessing_worker_t		*worker;
	preprocessing_request_t		*request;
	zbx_variant_t			value;
	char				*error;
	zbx_item_history_value_t	*history_value, *cached_value;
	delta_item_index_t		*index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	worker = preprocessor_get_worker_by_client(manager, client);
	request = (preprocessing_request_t *)&worker->queue_item->data;

	zbx_preprocessor_unpack_result(&value, &history_value, &error, message->data);

	if (NULL != history_value)
	{
		history_value->value_type = request->value_type;

		if (NULL != (cached_value = zbx_hashset_search(&manager->history_cache, history_value)))
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

	if (NULL != (index = zbx_hashset_search(&manager->delta_items, &request->value->itemid)) &&
			worker->queue_item == index->queue_item)
	{
		/* item is removed from delta index if it was present in delta item index*/
		zbx_hashset_remove_direct(&manager->delta_items, index);
	}

	if (FAIL != preprocessor_set_variant_result(request, &value, error))
		preprocessor_enqueue_dependent(manager, request, worker->queue_item);

	worker->queue_item = NULL;
	zbx_variant_clear(&value);
	zbx_free(error);

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_item_hash                                                     *
 *                                                                            *
 * Purpose: create a hash from DC_ITEM based on itemid                        *
 *                                                                            *
 * Parameters: data - [IN] item configuration data                            *
 *                                                                            *
 * Return value: hash value                                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	dc_item_hash(const void *data)
{
	const DC_ITEM	*item = (const DC_ITEM *)data;
	return ZBX_DEFAULT_UINT64_HASH_ALGO(&item->itemid, sizeof(zbx_uint64_t), ZBX_DEFAULT_HASH_SEED);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_item_compare                                                  *
 *                                                                            *
 * Purpose: compare itemid of two item configuration data structures          *
 *                                                                            *
 * Parameters: d1 - [IN] first item configuration data                        *
 *             d2 - [IN] second item configuration data                       *
 *                                                                            *
 * Return value: compare result (-1 for d1<d2, 1 for d1>d2, 0 for d1==d2)     *
 *                                                                            *
 ******************************************************************************/
static int	dc_item_compare(const void *d1, const void *d2)
{
	const DC_ITEM	*item1 = (const DC_ITEM	*)d1;
	const DC_ITEM	*item2 = (const DC_ITEM	*)d2;

	return ZBX_DEFAULT_UINT64_COMPARE_FUNC(&item1->itemid, &item2->itemid);
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
static void	preprocessor_init_manager(preprocessing_manager_t *manager)
{
	const char	*__function_name = "preprocessor_init_manager";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers: %d", __function_name, CONFIG_PREPROCESSOR_FORKS);

	memset(manager, 0, sizeof(preprocessing_manager_t));

	manager->workers = zbx_calloc(NULL, CONFIG_PREPROCESSOR_FORKS, sizeof(preprocessing_worker_t));
	zbx_queue_create(&manager->queue, sizeof(preprocessing_request_t));
	zbx_hashset_create(&manager->item_config, 0, dc_item_hash, dc_item_compare);
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
 *                                                                            *
 ******************************************************************************/
static void preprocessor_register_worker(preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	const char		*__function_name = "preprocessor_register_worker";
	preprocessing_worker_t	*worker = NULL;
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
		if (CONFIG_PREPROCESSOR_FORKS == manager->worker_count)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		worker = (preprocessing_worker_t *)&manager->workers[manager->worker_count++];
		worker->client = client;

		preprocessor_assign_tasks(manager);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_free_worker                                         *
 *                                                                            *
 * Purpose: free preprocessing worker                                         *
 *                                                                            *
 * Parameters: worker - [IN] the preprocessing worker                         *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_free_worker(preprocessing_worker_t *worker)
{
	zbx_ipc_client_close(worker->client);
	zbx_free(worker);
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
static void	preprocessor_destroy_manager(preprocessing_manager_t *manager)
{
	int			i;
	zbx_hashset_iter_t	iter;
	DC_ITEM			*item;
	preprocessing_request_t	*request;

	for (i = 0; i < manager->worker_count; i++)
		preprocessor_free_worker(&manager->workers[i]);

	zbx_free(manager->workers);

	/* this is the place where values are lost */
	while (SUCCEED == zbx_queue_dequeue(&manager->queue, (void **)&request))
		preprocessor_free_request(request);

	zbx_queue_destroy(&manager->queue);

	zbx_hashset_iter_reset(&manager->item_config, &iter);
	while (NULL != (item = zbx_hashset_iter_next(&iter)))
		DCconfig_clean_items(item, NULL, 1);

	zbx_hashset_destroy(&manager->item_config);
	zbx_hashset_destroy(&manager->delta_items);
	zbx_hashset_destroy(&manager->history_cache);
}

ZBX_THREAD_ENTRY(preprocessing_manager_thread, args)
{
	zbx_ipc_service_t	service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	preprocessing_manager_t	manager;
	int			ret;
	double			time_stat, time_idle, time_now;

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
	time_now = time_stat;
	time_idle = 0;

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

		zbx_handle_log();
		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

		ret = zbx_ipc_service_recv(&service, ZBX_PREPROCESSING_MANAGER_DELAY, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += zbx_time() - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_PREPROCESSOR_COMMAND:
					preprocessor_process_command(&manager, *message->data);
					break;

				case ZBX_IPC_PREPROCESSOR_WORKER:
					preprocessor_register_worker(&manager, client, message);
					break;

				case ZBX_IPC_PREPROCESSOR_REQUEST:
					preprocessor_add_request(&manager, message);
					message = NULL;
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
	}

	zbx_ipc_service_close(&service);
	preprocessor_destroy_manager(&manager);

	return 0;
#undef STAT_INTERVAL
}
