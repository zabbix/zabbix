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

#include "preproc_manager.h"

#include "zbxnix.h"
#include "zbxself.h"
#include "log.h"
#include "zbxlld.h"
#include "preprocessing.h"
#include "preproc_history.h"
#include "preproc_manager.h"

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;
extern int				CONFIG_PREPROCESSOR_FORKS;

#define ZBX_PREPROCESSING_MANAGER_DELAY	1

#define ZBX_PREPROC_PRIORITY_NONE	0
#define ZBX_PREPROC_PRIORITY_FIRST	1

typedef enum
{
	REQUEST_STATE_QUEUED		= 0,		/* requires preprocessing */
	REQUEST_STATE_PROCESSING	= 1,		/* is being preprocessed  */
	REQUEST_STATE_DONE		= 2,		/* value is set, waiting for flush */
	REQUEST_STATE_PENDING		= 3,		/* value requires preprocessing, */
							/* but is waiting on other request to complete */
}
zbx_preprocessing_states_t;

typedef enum
{
	ZBX_PREPROC_ITEM,	/* item preprocessing request */
	ZBX_PREPROC_DEPS	/* dependent item preprocessing request */
}
zbx_preprocessing_kind_t;

typedef struct zbx_preprocessing_request_base zbx_preprocessing_request_base_t;

ZBX_PTR_VECTOR_DECL(preprocessing_request_base, zbx_preprocessing_request_base_t *)
ZBX_PTR_VECTOR_IMPL(preprocessing_request_base, zbx_preprocessing_request_base_t *)

struct zbx_preprocessing_request_base
{
	zbx_preprocessing_kind_t		kind;
	zbx_preprocessing_states_t		state;
	zbx_preprocessing_request_base_t	*pending;	/* the request waiting on this request to complete */
	zbx_vector_preprocessing_request_base_t	flush_queue;	/* processed request waiting to be flushed */
};

/* preprocessing request */
typedef struct preprocessing_request
{
	zbx_preprocessing_request_base_t	base;		/* common data for various requests - must be first */
								/* field preprocessing request structure            */
	zbx_preproc_item_value_t		value;		/* unpacked item value */
	zbx_preproc_op_t			*steps;		/* preprocessing steps */
	int					steps_num;	/* number of preprocessing steps */
	unsigned char				value_type;	/* value type from configuration */
								/* at the beginning of preprocessing queue */
}
zbx_preprocessing_request_t;

/* bulk dependent item preprocessing request*/
typedef struct
{
	zbx_preprocessing_request_base_t	base;		/* common data for various requests - must be first */
								/* field preprocessing request structure            */
	zbx_uint64_t				hostid;
	zbx_uint64_t				master_itemid;
	unsigned char				value_type;	/* value type for items without preproc config */
								/* inherited from master item                  */
	zbx_variant_t				value;
	zbx_timespec_t				ts;

	zbx_vector_ipcmsg_t			messages;	/* IPC messages with dependent item preproc data */

	zbx_preproc_dep_result_t		*results;
	int					results_alloc;
	int					results_offset;
}
zbx_preprocessing_dep_request_t;

/* preprocessing worker data */
typedef struct
{
	zbx_ipc_client_t	*client;	/* the connected preprocessing worker client */
	void			*task;		/* the current task data */
}
zbx_preprocessing_worker_t;

/* item link index */
typedef struct
{
	zbx_uint64_t			itemid;		/* item id */
	zbx_preprocessing_kind_t	kind;
	zbx_list_item_t			*queue_item;	/* queued item */
}
zbx_item_link_t;

/* direct request to be forwarded to worker, bypassing the preprocessing queue */
typedef struct
{
	zbx_ipc_client_t	*client;	/* the IPC client sending forward message to worker */
	zbx_ipc_message_t	message;
}
zbx_preprocessing_direct_request_t;

/* preprocessing manager data */
typedef struct
{
	zbx_preprocessing_worker_t	*workers;	/* preprocessing worker array */
	int				worker_count;	/* preprocessing worker count */
	zbx_list_t			queue;		/* queue of item values */
	zbx_hashset_t			item_config;	/* item configuration L2 cache */
	zbx_hashset_t			history_cache;	/* item value history cache */
	zbx_hashset_t			linked_items;	/* linked items placed in queue */
	int				cache_ts;	/* cache timestamp */
	zbx_uint64_t			processed_num;	/* processed value counter */
	zbx_uint64_t			queued_num;	/* queued value counter */
	zbx_uint64_t			preproc_num;	/* queued values with preprocessing steps */
	zbx_list_iterator_t		priority_tail;	/* iterator to the last queued priority item */

	zbx_list_t			direct_queue;	/* Queue of external requests that have to be */
							/* forwarded to workers for preprocessing.    */
}
zbx_preprocessing_manager_t;

static void	preprocessor_enqueue_dependent(zbx_preprocessing_manager_t *manager, zbx_uint64_t hostid,
		zbx_uint64_t itemid, AGENT_RESULT *ar, unsigned char value_type, const zbx_timespec_t *ts);

static void	preprocessor_update_history(zbx_preprocessing_manager_t *manager, zbx_uint64_t itemid,
		zbx_vector_ptr_t *history);

/* cleanup functions */

static void	preproc_item_clear(zbx_preproc_item_t *item)
{
	int	i;

	zbx_free(item->dep_itemids);

	for (i = 0; i < item->preproc_ops_num; i++)
	{
		zbx_free(item->preproc_ops[i].params);
		zbx_free(item->preproc_ops[i].error_handler_params);
	}

	zbx_free(item->preproc_ops);
}

static void	request_free_steps(zbx_preprocessing_request_t *request)
{
	while (0 < request->steps_num)
	{
		request->steps_num--;
		zbx_free(request->steps[request->steps_num].params);
		zbx_free(request->steps[request->steps_num].error_handler_params);
	}

	zbx_free(request->steps);
}

/******************************************************************************
 *                                                                            *
 * Purpose: synchronize preprocessing manager with configuration cache data   *
 *                                                                            *
 * Parameters: manager - [IN] the manager to be synchronized                  *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_sync_configuration(zbx_preprocessing_manager_t *manager)
{
	zbx_hashset_iter_t	iter;
	int			ts;
	zbx_preproc_history_t	*vault;
	zbx_preproc_item_t	*item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ts = manager->cache_ts;
	DCconfig_get_preprocessable_items(&manager->item_config, &manager->cache_ts);

	if (ts != manager->cache_ts)
	{
		/* drop items with removed preprocessing steps from preprocessing history cache */
		zbx_hashset_iter_reset(&manager->history_cache, &iter);
		while (NULL != (vault = (zbx_preproc_history_t *)zbx_hashset_iter_next(&iter)))
		{
			if (NULL != zbx_hashset_search(&manager->item_config, &vault->itemid))
				continue;

			zbx_vector_ptr_clear_ext(&vault->history, (zbx_clean_func_t)zbx_preproc_op_history_free);
			zbx_vector_ptr_destroy(&vault->history);
			zbx_hashset_iter_remove(&iter);
		}

		/* reset preprocessing history for an item if its preprocessing step was modified */
		zbx_hashset_iter_reset(&manager->item_config, &iter);
		while (NULL != (item = (zbx_preproc_item_t *)zbx_hashset_iter_next(&iter)))
		{
			if (ts >= item->update_time && ZBX_PREPROC_MACRO_UPDATE_FALSE == item->macro_update)
				continue;

			item->macro_update = ZBX_PREPROC_MACRO_UPDATE_FALSE;

			if (NULL == (vault = (zbx_preproc_history_t *)zbx_hashset_search(&manager->history_cache,
					&item->itemid)))
			{
				continue;
			}

			zbx_vector_ptr_clear_ext(&vault->history, (zbx_clean_func_t)zbx_preproc_op_history_free);
			zbx_vector_ptr_destroy(&vault->history);
			zbx_hashset_remove_direct(&manager->history_cache, vault);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() item config size: %d, history cache size: %d", __func__,
			manager->item_config.num_data, manager->history_cache.num_data);
}

static void	preprocessing_ar_to_variant(AGENT_RESULT *ar, zbx_variant_t *value)
{
	if (ISSET_LOG(ar))
		zbx_variant_set_str(value,ar->log->value);
	else if (ISSET_UI64(ar))
		zbx_variant_set_ui64(value, ar->ui64);
	else if (ISSET_DBL(ar))
		zbx_variant_set_dbl(value, ar->dbl);
	else if (ISSET_STR(ar))
		zbx_variant_set_str(value, ar->str);
	else if (ISSET_TEXT(ar))
		zbx_variant_set_str(value, ar->text);
	else
		THIS_SHOULD_NEVER_HAPPEN;
}

/******************************************************************************
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
	zbx_variant_t		value;
	zbx_preproc_history_t	*vault;
	zbx_vector_ptr_t	*phistory;

	if (ITEM_STATE_NOTSUPPORTED == request->value.state)
		zbx_variant_set_str(&value, "");
	else
		preprocessing_ar_to_variant(request->value.result, &value);

	if (NULL != (vault = (zbx_preproc_history_t *)zbx_hashset_search(&manager->history_cache,
				&request->value.itemid)))
	{
		phistory = &vault->history;
	}
	else
		phistory = NULL;

	return zbx_preprocessor_pack_task(task, request->value.itemid, request->value_type, request->value.ts, &value,
			phistory, request->steps, request->steps_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set request state to done and handle linked items                 *
 *                                                                            *
 * Parameters: manager    - [IN] preprocessing manager                        *
 *             request    - [IN] preprocessing request                        *
 *             queue_item - [IN] queued item                                  *
 *                                                                            *
 ******************************************************************************/
static	void	preprocessor_set_request_state_done(zbx_preprocessing_manager_t *manager,
		zbx_preprocessing_request_base_t *base, const zbx_list_item_t *queue_item)
{
	zbx_item_link_t				*index, index_local;
	zbx_list_iterator_t			iterator, next_iterator;
	zbx_preprocessing_request_t		*request;
	zbx_preprocessing_dep_request_t		*dep_request;
	zbx_preprocessing_request_base_t	*prev;

	base->state = REQUEST_STATE_DONE;

	/* value processed - the pending value can now be processed */
	if (NULL != base->pending)
		base->pending->state = REQUEST_STATE_QUEUED;

	switch (base->kind)
	{
		case ZBX_PREPROC_ITEM:
			request = (zbx_preprocessing_request_t *)base;
			index_local.itemid = request->value.itemid;
			break;
		case ZBX_PREPROC_DEPS:
			dep_request = (zbx_preprocessing_dep_request_t *)base;
			index_local.itemid = dep_request->master_itemid;
			break;
	}

	index_local.kind = base->kind;

	if (NULL != (index = (zbx_item_link_t *)zbx_hashset_search(&manager->linked_items, &index_local)) &&
			queue_item == index->queue_item)
	{
		zbx_hashset_remove_direct(&manager->linked_items, index);
	}

	if (NULL == manager->queue.head)
		return;

	zbx_list_iterator_init(&manager->queue, &iterator);
	if (iterator.next == queue_item)
		return;

	while (SUCCEED == zbx_list_iterator_next(&iterator))
	{
		if (iterator.next == queue_item)
			break;
	}

	prev = (zbx_preprocessing_request_base_t *)iterator.current->data;
	zbx_vector_preprocessing_request_base_append(&prev->flush_queue, base);

	next_iterator = iterator;
	if (SUCCEED == zbx_list_iterator_next(&next_iterator))
	{
		if (SUCCEED == zbx_list_iterator_equal(&next_iterator, &manager->priority_tail))
			manager->priority_tail = iterator;
	}

	(void)zbx_list_iterator_remove_next(&iterator);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create message(s) for dependent item bulk preprocessing           *
 *                                                                            *
 * Parameters: manager  - [IN] preprocessing manager                          *
 *             request  - [IN] preprocessing request                          *
 *                                                                            *
 * Return value: the number of created messages                               *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_create_dep_message(zbx_preprocessing_manager_t *manager,
		zbx_preprocessing_dep_request_t *request)
{
	int			i;
	zbx_preproc_dep_t	*deps;
	zbx_preproc_item_t	*master_item;

	if (NULL == (master_item = (zbx_preproc_item_t *)zbx_hashset_search(&manager->item_config,
			&request->master_itemid)) || 0 == master_item->dep_itemids_num)
	{
		return 0;
	}

	deps = (zbx_preproc_dep_t *)zbx_malloc(NULL, (size_t)master_item->dep_itemids_num * sizeof(zbx_preproc_dep_t));

	for (i = 0; i < master_item->dep_itemids_num; i++)
	{
		zbx_preproc_history_t	*vault;
		zbx_preproc_item_t	*item;

		deps[i].itemid = master_item->dep_itemids[i].first;
		deps[i].flags = (unsigned char)master_item->dep_itemids[i].second;

		if (NULL == (item = (zbx_preproc_item_t *)zbx_hashset_search(&manager->item_config,
				&master_item->dep_itemids[i].first)))
		{
			deps[i].value_type = request->value_type;
			deps[i].steps = NULL;
			deps[i].steps_num = 0;

			/* items without preprocessing do not have history */
			zbx_vector_ptr_create(&deps[i].history);
			continue;
		}

		deps[i].value_type = item->value_type;
		deps[i].steps = item->preproc_ops;
		deps[i].steps_num = item->preproc_ops_num;

		if (NULL != (vault = (zbx_preproc_history_t *)zbx_hashset_search(&manager->history_cache,
				&item->itemid)))
		{
			deps[i].history = vault->history;
		}
		else
			zbx_vector_ptr_create(&deps[i].history);
	}

	zbx_preprocessor_pack_dep_request(&request->value, &request->ts, deps, master_item->dep_itemids_num,
			&request->messages);

	zbx_free(deps);

	return request->messages.values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns next dependent item preprocessing message                 *
 *                                                                            *
 * Parameters: request - [IN] the dependent item preprocessing request        *
 *             message - [OUT] the next message to be sent                    *
 *                                                                            *
 * Return value: SUCCEED - the next message was returned                      *
 *               FAIL    - no more messages to send                           *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_dep_request_next_message(zbx_preprocessing_dep_request_t *request,
		zbx_ipc_message_t *message)
{
	if (0 == request->messages.values_num)
		return FAIL;

	*message = *request->messages.values[0];
	zbx_free(request->messages.values[0]);
	zbx_vector_ipcmsg_remove(&request->messages, 0);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets next task to be sent to worker                               *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             message - [OUT] the serialized task to be sent                 *
 *                                                                            *
 * Return value: pointer to the task object                                   *
 *                                                                            *
 ******************************************************************************/
static void	*preprocessor_get_next_task(zbx_preprocessing_manager_t *manager, zbx_ipc_message_t *message)
{
	zbx_list_iterator_t			iterator;
	zbx_preprocessing_request_base_t	*base;
	zbx_preprocessing_request_t		*request = NULL;
	void					*task = NULL;
	zbx_preprocessing_direct_request_t	*direct_request;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == zbx_list_pop(&manager->direct_queue, (void **)&direct_request))
	{
		*message = direct_request->message;
		zbx_ipc_message_init(&direct_request->message);
		task = direct_request;
		goto out;
	}

	zbx_list_iterator_init(&manager->queue, &iterator);
	while (SUCCEED == zbx_list_iterator_next(&iterator))
	{
		int process_notsupported = 0;

		zbx_list_iterator_peek(&iterator, (void **)&base);

		if (REQUEST_STATE_QUEUED != base->state)
			continue;

		switch (base->kind)
		{
			case ZBX_PREPROC_DEPS:
				if (0 == preprocessor_create_dep_message(manager,
						(zbx_preprocessing_dep_request_t *)base))
				{
					base->state = REQUEST_STATE_DONE;
					continue;
				}
				(void)preprocessor_dep_request_next_message((zbx_preprocessing_dep_request_t *)base,
						message);
				break;
			case ZBX_PREPROC_ITEM:
				request = (zbx_preprocessing_request_t *)base;
				if (NULL != request->steps &&
						ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == request->steps[0].type)
				{
					process_notsupported = 1;
				}

				if (ITEM_STATE_NOTSUPPORTED == request->value.state && 0 == process_notsupported)
				{
					zbx_preproc_history_t	*vault;

					if (NULL != (vault = (zbx_preproc_history_t *) zbx_hashset_search(
							&manager->history_cache, &request->value.itemid)))
					{
						zbx_vector_ptr_clear_ext(&vault->history,
								(zbx_clean_func_t) zbx_preproc_op_history_free);
						zbx_vector_ptr_destroy(&vault->history);
						zbx_hashset_remove_direct(&manager->history_cache, vault);
					}

					preprocessor_set_request_state_done(manager, base, iterator.current);
					continue;
				}

				message->code = ZBX_IPC_PREPROCESSOR_REQUEST;
				message->size = preprocessor_create_task(manager, request, &message->data);
				request_free_steps(request);
				break;
		}

		task = iterator.current;
		base->state = REQUEST_STATE_PROCESSING;
		break;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return task;
}

/******************************************************************************
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
		if (NULL == manager->workers[i].task)
			return &manager->workers[i];
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: assign available queued preprocessing tasks to free workers       *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_assign_tasks(zbx_preprocessing_manager_t *manager)
{
	zbx_preprocessing_worker_t	*worker;
	void				*data;
	zbx_ipc_message_t		message;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (NULL != (worker = preprocessor_get_free_worker(manager)) &&
			NULL != (data = preprocessor_get_next_task(manager, &message)))
	{
		if (FAIL == zbx_ipc_client_send(worker->client, message.code, message.data, message.size))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot send data to preprocessing worker");
			exit(EXIT_FAILURE);
		}

		worker->task = data;
		zbx_ipc_message_clean(&message);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated by preprocessor item value              *
 *                                                                            *
 * Parameters: value - [IN] value to be freed                                 *
 *                                                                            *
 ******************************************************************************/
static void	preproc_item_value_clear(zbx_preproc_item_value_t *value)
{
	zbx_free(value->error);
	zbx_free(value->ts);

	if (NULL != value->result)
	{
		free_result(value->result);
		zbx_free(value->result);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: free preprocessing request                                        *
 *                                                                            *
 * Parameters: base - [IN] request data to be freed                           *
 *                                                                            *
 * Comments: This handles freeing normal and dependent item requests          *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_free_request(zbx_preprocessing_request_base_t *base)
{
	zbx_preprocessing_request_t	*request;
	zbx_preprocessing_dep_request_t	*dep_request;

	zbx_vector_preprocessing_request_base_clear_ext(&base->flush_queue, preprocessor_free_request);
	zbx_vector_preprocessing_request_base_destroy(&base->flush_queue);

	switch (base->kind)
	{
		case ZBX_PREPROC_ITEM:
			request = (zbx_preprocessing_request_t *)base;
			preproc_item_value_clear(&request->value);
			request_free_steps(request);
			break;
		case ZBX_PREPROC_DEPS:
			dep_request = (zbx_preprocessing_dep_request_t *)base;
			zbx_preprocessor_free_dep_results(dep_request->results, dep_request->results_offset);
			zbx_variant_clear(&dep_request->value);
			zbx_vector_ipcmsg_clear_ext(&dep_request->messages, zbx_ipc_message_free);
			zbx_vector_ipcmsg_destroy(&dep_request->messages);
			break;
	}

	zbx_free(base);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free preprocessing direct request                                 *
 *                                                                            *
 * Parameters: direct_request - [IN] forward data to be freed                 *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_free_direct_request(zbx_preprocessing_direct_request_t *direct_request)
{
	zbx_ipc_client_release(direct_request->client);
	zbx_ipc_message_clean(&direct_request->message);
	zbx_free(direct_request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new value to the local history cache or send to LLD manager   *
 *                                                                            *
 * Parameters: value - [IN] value to be added or sent                         *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_flush_value(const zbx_preproc_item_value_t *value)
{
	if (0 == (value->item_flags & ZBX_FLAG_DISCOVERY_RULE) || 0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		dc_add_history(value->itemid, value->item_value_type, value->item_flags, value->result,
				value->ts, value->state, value->error);
	}
	else
	{
		zbx_lld_process_agent_result(value->itemid, value->hostid, value->result, value->ts,
				value->error);
	}
}

static void	preprocessor_flush_dep_results(zbx_preprocessing_manager_t *manager,
		zbx_preprocessing_dep_request_t *request)
{
	int	i;

	for (i = 0; i < request->results_alloc; i++)
	{
		unsigned char	state;

		state = (NULL == request->results[i].error ? ITEM_STATE_NORMAL : ITEM_STATE_NOTSUPPORTED);

		if (0 == (request->results[i].flags & ZBX_FLAG_DISCOVERY_RULE) ||
				0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			dc_add_history(request->results[i].itemid, request->results[i].value_type,
					request->results[i].flags, &request->results[i].value, &request->ts, state,
					request->results[i].error);
		}
		else
		{
			zbx_lld_process_agent_result(request->results[i].itemid, request->hostid,
					&request->results[i].value, &request->ts, request->results[i].error);
		}
	}

	manager->processed_num += (zbx_uint64_t)request->results_alloc;
	manager->preproc_num--;
}

/******************************************************************************
 *                                                                            *
 * Purpose: recursively flush processed request and the other processed       *
 *          requests that were waiting on this request to be finished         *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             base    - [IN] the preprocessing request                       *
 *                                                                            *
 ******************************************************************************/
static void	preprocessing_flush_request(zbx_preprocessing_manager_t *manager,
		zbx_preprocessing_request_base_t *base)
{
	zbx_preprocessing_request_t	*request;
	zbx_preprocessing_dep_request_t	*dep_request;
	int				i;

	switch (base->kind)
	{
		case ZBX_PREPROC_ITEM:
			request = (zbx_preprocessing_request_t *)base;
			preprocessor_flush_value(&request->value);
			manager->processed_num++;
			manager->queued_num--;
			break;
		case ZBX_PREPROC_DEPS:
			dep_request = (zbx_preprocessing_dep_request_t *)base;
			preprocessor_flush_dep_results(manager, dep_request);
			break;
	}

	for (i = 0; i < base->flush_queue.values_num; i++)
		preprocessing_flush_request(manager, base->flush_queue.values[i]);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add all sequential processed values from beginning of the queue   *
 *          to the local history cache                                        *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *                                                                            *
 ******************************************************************************/
static void	preprocessing_flush_queue(zbx_preprocessing_manager_t *manager)
{
	zbx_preprocessing_request_base_t	*base;
	zbx_list_iterator_t			iterator;

	zbx_list_iterator_init(&manager->queue, &iterator);
	while (SUCCEED == zbx_list_iterator_next(&iterator))
	{
		zbx_list_iterator_peek(&iterator, (void **)&base);

		if (REQUEST_STATE_DONE != base->state)
			break;

		preprocessing_flush_request(manager, base);

		if (SUCCEED == zbx_list_iterator_equal(&iterator, &manager->priority_tail))
			zbx_list_iterator_clear(&manager->priority_tail);

		zbx_list_pop(&manager->queue, NULL);
		preprocessor_free_request(base);
	}
}

static void	preproc_link_nodes(zbx_preprocessing_manager_t *manager, zbx_uint64_t itemid,
		zbx_preprocessing_kind_t kind, zbx_list_item_t *enqueued_at)
{
	zbx_item_link_t				*index, index_local;
	zbx_preprocessing_request_base_t	*request, *linked_request;

	index_local.itemid = itemid;
	index_local.kind = kind;

	/* existing linked item*/
	if (NULL != (index = (zbx_item_link_t *)zbx_hashset_search(&manager->linked_items, &index_local)))
	{
		linked_request = (zbx_preprocessing_request_base_t *)(enqueued_at->data);
		request = (zbx_preprocessing_request_base_t *)(index->queue_item->data);

		if (REQUEST_STATE_DONE != request->state)
		{
			request->pending = linked_request;
			linked_request->state = REQUEST_STATE_PENDING;
		}

		index->queue_item = enqueued_at;
	}
	else
	{
		index_local.queue_item = enqueued_at;
		zbx_hashset_insert(&manager->linked_items, &index_local, sizeof(zbx_item_link_t));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: create relation between item values within value queue            *
 *                                                                            *
 * Parameters: manager     - [IN] preprocessing manager                       *
 *             enqueued_at - [IN] position in value queue                     *
 *             item        - [IN] item configuration data                     *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_link_items(zbx_preprocessing_manager_t *manager, zbx_list_item_t *enqueued_at,
		zbx_preproc_item_t *item)
{
	int			i;
	zbx_preproc_op_t	*op;

	/* allow out of order processing for items without dependent items */
	/* or preprocessing steps requiring serial processing              */
	if (0 == item->dep_itemids_num)
	{
		for (i = 0; i < item->preproc_ops_num; i++)
		{
			op = &item->preproc_ops[i];

			if (ZBX_PREPROC_DELTA_VALUE == op->type || ZBX_PREPROC_DELTA_SPEED == op->type)
				break;

			if (ZBX_PREPROC_THROTTLE_VALUE == op->type || ZBX_PREPROC_THROTTLE_TIMED_VALUE == op->type)
				break;
		}

		if (i == item->preproc_ops_num)
			return;
	}

	preproc_link_nodes(manager, item->itemid, ZBX_PREPROC_ITEM, enqueued_at);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue dependent items (if any) by preproc value                 *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             value   - [IN] the item preproc value                          *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_enqueue_dependent_value(zbx_preprocessing_manager_t *manager,
		zbx_preproc_item_value_t *value)
{
	if (NULL == value->result)
		return;

	preprocessor_enqueue_dependent(manager, value->hostid, value->itemid, value->result,
			value->item_value_type, value->ts);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue preprocessing request                                     *
 *                                                                            *
 * Parameters: manage   - [IN] preprocessing manager                          *
 *             value    - [IN] item value                                     *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_enqueue(zbx_preprocessing_manager_t *manager, zbx_preproc_item_value_t *value)
{
	zbx_preprocessing_request_t	*request;
	zbx_preproc_item_t		*item, item_local;
	zbx_list_item_t			*enqueued_at;
	int				i;
	zbx_preprocessing_states_t	state;
	unsigned char			priority = ZBX_PREPROC_PRIORITY_NONE;
	int				notsupp_shift;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid: " ZBX_FS_UI64, __func__, value->itemid);

	item_local.itemid = value->itemid;
	item = (zbx_preproc_item_t *)zbx_hashset_search(&manager->item_config, &item_local);

	/* override priority based on item type */
	if (NULL != item && ITEM_TYPE_INTERNAL == item->type)
		priority = ZBX_PREPROC_PRIORITY_FIRST;

	if (NULL == item || 0 == item->preproc_ops_num || (ITEM_STATE_NOTSUPPORTED != value->state &&
			(NULL == value->result || 0 == ISSET_VALUE(value->result))))
	{
		state = REQUEST_STATE_DONE;

		if (NULL == manager->queue.head)
		{
			/* queue is empty and item is done, it can be flushed */
			preprocessor_flush_value(value);
			manager->processed_num++;
			preprocessor_enqueue_dependent_value(manager, value);
			preproc_item_value_clear(value);

			goto out;
		}
	}
	else
		state = REQUEST_STATE_QUEUED;

	request = (zbx_preprocessing_request_t *)zbx_malloc(NULL, sizeof(zbx_preprocessing_request_t));
	memset(request, 0, sizeof(zbx_preprocessing_request_t));
	request->base.kind = ZBX_PREPROC_ITEM;
	zbx_vector_preprocessing_request_base_create(&request->base.flush_queue);
	memcpy(&request->value, value, sizeof(zbx_preproc_item_value_t));
	request->base.state = state;

	if (REQUEST_STATE_QUEUED == state)
	{
		notsupp_shift = ITEM_STATE_NOTSUPPORTED == value->state ? -1 : 0;

		for (i = 0; i < item->preproc_ops_num; i++)
		{
			if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == item->preproc_ops[i].type)
			{
				notsupp_shift = ITEM_STATE_NOTSUPPORTED != value->state;
				if (0 != i)
					THIS_SHOULD_NEVER_HAPPEN;
				break;
			}
		}
	}

	if (REQUEST_STATE_QUEUED == state && 0 <= notsupp_shift)
	{
		request->value_type = item->value_type;
		if (0 < item->preproc_ops_num - notsupp_shift)
		{
			request->steps = (zbx_preproc_op_t *)zbx_malloc(NULL, sizeof(zbx_preproc_op_t) *
					(size_t)(item->preproc_ops_num - notsupp_shift));
			request->steps_num = item->preproc_ops_num - notsupp_shift;

			for (i = 0; i < item->preproc_ops_num - notsupp_shift; i++)
			{
				request->steps[i].type = item->preproc_ops[i + notsupp_shift].type;
				request->steps[i].params = zbx_strdup(NULL, item->preproc_ops[i + notsupp_shift].params);
				request->steps[i].error_handler = item->preproc_ops[i + notsupp_shift].error_handler;
				request->steps[i].error_handler_params = zbx_strdup(NULL,
						item->preproc_ops[i + notsupp_shift].error_handler_params);
			}
		}
		else
			request->base.state = REQUEST_STATE_DONE;

		manager->preproc_num++;
	}

	/* priority items are enqueued at the beginning of the line */
	if (ZBX_PREPROC_PRIORITY_FIRST == priority)
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
		zbx_list_insert_after(&manager->queue, NULL, request, &enqueued_at);
		zbx_list_iterator_update(&manager->priority_tail);
	}

	if (REQUEST_STATE_QUEUED == request->base.state)
		preprocessor_link_items(manager, enqueued_at, item);

	/* if no preprocessing is needed, dependent items are enqueued */
	if (REQUEST_STATE_DONE == request->base.state)
		preprocessor_enqueue_dependent_value(manager, value);

	manager->queued_num++;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue dependent items (if any)                                  *
 *                                                                            *
 * Parameters: manager      - [IN] preprocessing manager                      *
 *             source_value - [IN] master item value                          *
 *             master       - [IN] dependent item should be enqueued after    *
 *                                 this item                                  *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_enqueue_dependent(zbx_preprocessing_manager_t *manager, zbx_uint64_t hostid,
		zbx_uint64_t itemid, AGENT_RESULT *ar, unsigned char value_type, const zbx_timespec_t *ts)
{
	zbx_preproc_item_t	*item, item_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid: " ZBX_FS_UI64, __func__, itemid);

	if (ISSET_VALUE(ar))
	{
		item_local.itemid = itemid;
		if (NULL != (item = (zbx_preproc_item_t *)zbx_hashset_search(&manager->item_config, &item_local)) &&
				0 != item->dep_itemids_num)
		{
			zbx_preprocessing_dep_request_t	*dep_request;
			zbx_variant_t			value;
			zbx_list_item_t			*enqueued_at;

			dep_request = zbx_malloc(NULL, sizeof(zbx_preprocessing_dep_request_t));
			dep_request->base.kind = ZBX_PREPROC_DEPS;
			dep_request->base.state = REQUEST_STATE_QUEUED;
			dep_request->base.pending = NULL;
			zbx_vector_preprocessing_request_base_create(&dep_request->base.flush_queue);
			dep_request->hostid = hostid;

			dep_request->ts = NULL != ts ? *ts : (zbx_timespec_t){0, 0};

			/* the data is copied without allocation - the variant value must not be cleared afterwards */
			preprocessing_ar_to_variant(ar, &value);
			zbx_variant_copy(&dep_request->value, &value);

			dep_request->value_type = value_type;
			dep_request->master_itemid = itemid;

			zbx_vector_ipcmsg_create(&dep_request->messages);

			dep_request->results = NULL;
			dep_request->results_alloc = 0;
			dep_request->results_offset = 0;

			zbx_list_append(&manager->queue, dep_request, &enqueued_at);

			preproc_link_nodes(manager, itemid, ZBX_PREPROC_DEPS, enqueued_at);

			preprocessor_assign_tasks(manager);
			preprocessing_flush_queue(manager);

			manager->preproc_num++;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle new preprocessing request                                  *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             message - [IN] packed preprocessing request                    *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_add_request(zbx_preprocessing_manager_t *manager, zbx_ipc_message_t *message)
{
	zbx_uint32_t			offset = 0;
	zbx_preproc_item_value_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	preprocessor_sync_configuration(manager);

	while (offset < message->size)
	{
		offset += zbx_preprocessor_unpack_value(&value, message->data + offset);
		preprocessor_enqueue(manager, &value);
	}

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle new preprocessing test request                             *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             message - [IN] packed preprocessing request                    *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_add_test_request(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_preprocessing_direct_request_t	*direct_request;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_ipc_client_addref(client);
	direct_request = zbx_malloc(NULL, sizeof(zbx_preprocessing_direct_request_t));
	direct_request->client = client;
	zbx_ipc_message_copy(&direct_request->message, message);
	zbx_list_append(&manager->direct_queue, direct_request, NULL);

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get result data from variant and error message                    *
 *                                                                            *
 * Parameters: request - [IN/OUT] preprocessing request                       *
 *             value   - [IN] variant value                                   *
 *             error   - [IN] error message (if any)                          *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_set_variant_result(zbx_preprocessing_request_t *request,
		zbx_variant_t *value, char *error)
{
	int		type, ret = FAIL;
	zbx_log_t	*log;

	if (NULL != error)
	{
		/* on error item state is set to ITEM_STATE_NOTSUPPORTED */
		request->value.state = ITEM_STATE_NOTSUPPORTED;
		zbx_free(request->value.error);
		request->value.error = error;
		ret = FAIL;

		goto out;
	}

	if (ZBX_VARIANT_NONE == value->type)
	{
		if (NULL != request->value.result)
			free_result(request->value.result);

		zbx_free(request->value.error);

		request->value.state = ITEM_STATE_NORMAL;
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
		/* old result is shared between dependent and master items, it cannot be modified, create new result */

		if (NULL == request->value.result)
		{
			request->value.result = (AGENT_RESULT *)zbx_malloc(NULL, sizeof(AGENT_RESULT));
			init_result(request->value.result);
		}
		else
		{
			/* preserve eventlog related information */
			if (ITEM_VALUE_TYPE_LOG != request->value_type)
				free_result(request->value.result);
		}

		if (ITEM_STATE_NOTSUPPORTED == request->value.state)
			request->value.state = ITEM_STATE_NORMAL;

		switch (request->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				SET_DBL_RESULT(request->value.result, value->data.dbl);
				break;
			case ITEM_VALUE_TYPE_STR:
				SET_STR_RESULT(request->value.result, value->data.str);
				break;
			case ITEM_VALUE_TYPE_LOG:
				if (ISSET_LOG(request->value.result))
				{
					log = GET_LOG_RESULT(request->value.result);
					zbx_free(log->value);
				}
				else
				{
					log = zbx_malloc(NULL, sizeof(zbx_log_t));
					memset(log, 0, sizeof(zbx_log_t));
					SET_LOG_RESULT(request->value.result, log);
				}
				log->value = value->data.str;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				SET_UI64_RESULT(request->value.result, value->data.ui64);
				break;
			case ITEM_VALUE_TYPE_TEXT:
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
	zbx_preprocessing_worker_t	*worker;
	zbx_preprocessing_request_t	*request;
	zbx_variant_t			value;
	char				*error;
	zbx_vector_ptr_t		history;
	zbx_preproc_history_t		*vault;
	zbx_list_item_t			*node;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	worker = preprocessor_get_worker_by_client(manager, client);
	node = (zbx_list_item_t *)worker->task;
	request = (zbx_preprocessing_request_t *)node->data;

	zbx_vector_ptr_create(&history);
	zbx_preprocessor_unpack_result(&value, &history, &error, message->data);

	if (NULL != (vault = (zbx_preproc_history_t *)zbx_hashset_search(&manager->history_cache,
			&request->value.itemid)))
	{
		zbx_vector_ptr_clear_ext(&vault->history, (zbx_clean_func_t)zbx_preproc_op_history_free);
	}

	if (0 != history.values_num)
	{
		if (NULL == vault)
		{
			zbx_preproc_history_t	history_local;

			history_local.itemid = request->value.itemid;
			vault = (zbx_preproc_history_t *)zbx_hashset_insert(&manager->history_cache, &history_local,
					sizeof(history_local));
			zbx_vector_ptr_create(&vault->history);
		}

		zbx_vector_ptr_append_array(&vault->history, history.values, history.values_num);
		zbx_vector_ptr_clear(&history);
	}
	else
	{
		if (NULL != vault)
		{
			zbx_vector_ptr_destroy(&vault->history);
			zbx_hashset_remove_direct(&manager->history_cache, vault);
		}
	}

	preprocessor_set_request_state_done(manager, (zbx_preprocessing_request_base_t *)request, worker->task);

	if (FAIL != preprocessor_set_variant_result(request, &value, error))
		preprocessor_enqueue_dependent_value(manager, &request->value);

	worker->task = NULL;
	zbx_variant_clear(&value);

	manager->preproc_num--;

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);

	zbx_vector_ptr_destroy(&history);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle preprocessing result                                       *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             itemid  - [IN] the item identifier                             *
 *             history - [IN] the new preprocessing history                   *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_update_history(zbx_preprocessing_manager_t *manager, zbx_uint64_t itemid,
		zbx_vector_ptr_t *history)
{
	zbx_preproc_history_t	*vault;

	if (NULL != (vault = (zbx_preproc_history_t *)zbx_hashset_search(&manager->history_cache,
			&itemid)))
	{
		zbx_vector_ptr_clear_ext(&vault->history, (zbx_clean_func_t)zbx_preproc_op_history_free);
	}

	if (0 != history->values_num)
	{
		if (NULL == vault)
		{
			zbx_preproc_history_t	history_local;

			history_local.itemid = itemid;
			zbx_vector_ptr_create(&history_local.history);

			vault = (zbx_preproc_history_t *)zbx_hashset_insert(&manager->history_cache, &history_local,
					sizeof(history_local));
		}

		zbx_vector_ptr_append_array(&vault->history, history->values, history->values_num);
		zbx_vector_ptr_clear(history);
	}
	else
	{
		if (NULL != vault)
		{
			zbx_vector_ptr_destroy(&vault->history);
			zbx_hashset_remove_direct(&manager->history_cache, vault);
		}
	}
}

static void	preprocessor_finalize_dep_results(zbx_preprocessing_manager_t *manager,
		zbx_preprocessing_dep_request_t *request, zbx_preprocessing_worker_t *worker)
{
	int	i;

	if (request->results_alloc != request->results_offset)
		return;

	for (i = 0; i < request->results_alloc; i++)
	{
		if (NULL == request->results[i].error)
		{
			preprocessor_update_history(manager, request->results[i].itemid, &request->results[i].history);

			preprocessor_enqueue_dependent(manager, request->hostid, request->results[i].itemid,
					&request->results[i].value, request->results[i].value_type, &request->ts);
		}

	}

	preprocessor_set_request_state_done(manager, (zbx_preprocessing_request_base_t *)request,
			(zbx_list_item_t *)worker->task);

	worker->task = NULL;

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle dependent item batch preprocessing result                  *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] IPC client                                      *
 *             message - [IN] packed preprocessing result                     *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_process_dep_result(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_preprocessing_worker_t	*worker;
	zbx_preprocessing_dep_request_t	*request;
	zbx_list_item_t			*node;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	worker = preprocessor_get_worker_by_client(manager, client);
	node = (zbx_list_item_t *)worker->task;
	request = (zbx_preprocessing_dep_request_t *)node->data;

	zbx_preprocessor_unpack_dep_result(&request->results_alloc, &request->results_offset, &request->results,
			message->data);

	preprocessor_finalize_dep_results(manager, request, worker);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle next dependent item batch preprocessing result             *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] IPC client                                      *
 *             message - [IN] packed preprocessing result                     *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_process_dep_result_cont(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_preprocessing_worker_t	*worker;
	zbx_preprocessing_dep_request_t	*request;
	zbx_list_item_t			*node;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	worker = preprocessor_get_worker_by_client(manager, client);
	node = (zbx_list_item_t *)worker->task;
	request = (zbx_preprocessing_dep_request_t *)node->data;

	zbx_preprocessor_unpack_dep_result_cont(&request->results_offset, request->results + request->results_offset,
			message->data);

	preprocessor_finalize_dep_results(manager, request, worker);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle dependent item batch preprocessing result                  *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] IPC client                                      *
 *             message - [IN] packed preprocessing result                     *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_next_dep_request(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_preprocessing_worker_t	*worker;
	zbx_preprocessing_dep_request_t	*request;
	zbx_ipc_message_t		message;
	zbx_list_item_t			*node;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	worker = preprocessor_get_worker_by_client(manager, client);
	node = (zbx_list_item_t *)worker->task;
	request = (zbx_preprocessing_dep_request_t *)node->data;

	if (SUCCEED == preprocessor_dep_request_next_message(request, &message))
	{
		zbx_ipc_client_send(client, message.code, message.data, message.size);
		zbx_ipc_message_clean(&message);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle preprocessing result                                       *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] IPC client                                      *
 *             message - [IN] packed preprocessing result                     *
 *                                                                            *
 * Comments: Preprocessing testing results are directly forwarded to source   *
 *           client as they are.                                              *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_flush_test_result(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_preprocessing_worker_t		*worker;
	zbx_preprocessing_direct_request_t	*direct_request;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	worker = preprocessor_get_worker_by_client(manager, client);
	direct_request = (zbx_preprocessing_direct_request_t *)worker->task;

	/* forward the response to the client */
	if (SUCCEED == zbx_ipc_client_connected(direct_request->client))
		zbx_ipc_client_send(direct_request->client, message->code, message->data, message->size);

	worker->task = NULL;
	preprocessor_free_direct_request(direct_request);

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	preprocessor_add_item_stats(zbx_uint64_t itemid, zbx_preprocessing_states_t state, zbx_hashset_t *items,
		int *total, int *queued, int *processing, int *done, int *pending)
{
#define ZBX_MAX_REQUEST_STATE_PRINT_LIMIT	25

	zbx_preproc_item_stats_t	*item;

	if (NULL == (item = zbx_hashset_search(items, &itemid)))
	{
		zbx_preproc_item_stats_t	item_local = {.itemid = itemid};

		item = zbx_hashset_insert(items, &item_local, sizeof(item_local));
	}

	switch(state)
	{
		case REQUEST_STATE_QUEUED:
			if (*queued < ZBX_MAX_REQUEST_STATE_PRINT_LIMIT)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "oldest queued itemid: " ZBX_FS_UI64
						" values:%d pos:%d", item->itemid, item->values_num, *total);
			}
			(*queued)++;
			break;
		case REQUEST_STATE_PROCESSING:
			if (*processing < ZBX_MAX_REQUEST_STATE_PRINT_LIMIT)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "oldest processing itemid: " ZBX_FS_UI64
						" values:%d pos:%d", item->itemid, item->values_num, *total);
			}
			(*processing)++;
			break;
		case REQUEST_STATE_DONE:
			if (*done < ZBX_MAX_REQUEST_STATE_PRINT_LIMIT)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "oldest done itemid: " ZBX_FS_UI64
						" values:%d pos:%d", item->itemid, item->values_num, *total);
			}
			(*done)++;
			break;
		case REQUEST_STATE_PENDING:
			if (*pending < ZBX_MAX_REQUEST_STATE_PRINT_LIMIT)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "oldest pending itemid: " ZBX_FS_UI64
						" values:%d pos:%d", item->itemid, item->values_num, *total);
			}
			(*pending)++;
			break;
	}

	item->values_num++;
	(*total)++;

#undef ZBX_MAX_REQUEST_STATE_PRINT_LIMIT
}

static	void	preprocessor_get_items_totals(zbx_preprocessing_manager_t *manager, int *total, int *queued,
		int *processing, int *done, int *pending)
{

	zbx_list_iterator_t			iterator;
	zbx_hashset_t				items;
	zbx_preprocessing_request_base_t	*base;
	zbx_preprocessing_request_t		*request;
	zbx_preprocessing_dep_request_t		*dep_request;
	zbx_preproc_item_t			*master_item;

	*total = 0;
	*queued = 0;
	*processing = 0;
	*done = 0;
	*pending = 0;

	zbx_hashset_create(&items, 1024, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_list_iterator_init(&manager->queue, &iterator);
	while (SUCCEED == zbx_list_iterator_next(&iterator))
	{
		zbx_list_iterator_peek(&iterator, (void **)&base);

		switch (base->kind)
		{
			case ZBX_PREPROC_ITEM:
				request = (zbx_preprocessing_request_t *)base;
				preprocessor_add_item_stats(request->value.itemid, base->state, &items, total, queued,
						processing, done, pending);
				break;
			case ZBX_PREPROC_DEPS:
				dep_request = (zbx_preprocessing_dep_request_t *)base;
				if (NULL != (master_item = (zbx_preproc_item_t *)zbx_hashset_search(
						&manager->item_config, &dep_request->master_itemid)))
				{
					int	i;

					for (i = 0; i < master_item->dep_itemids_num; i++)
					{
						preprocessor_add_item_stats(master_item->dep_itemids[i].first,
								base->state, &items, total, queued, processing, done,
								pending);

					}
				}
				break;
		}
	}

	zbx_hashset_destroy(&items);
#undef ZBX_MAX_REQUEST_STATE_PRINT_LIMIT
}

/******************************************************************************
 *                                                                            *
 * Purpose: return diagnostic statistics                                      *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] IPC client                                      *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_get_diag_stats(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client)
{
	unsigned char	*data;
	zbx_uint32_t	data_len;
	int		total, queued, processing, done, pending;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	preprocessor_get_items_totals(manager, &total, &queued, &processing, &done, &pending);

	data_len = zbx_preprocessor_pack_diag_stats(&data, total, queued, processing, done, pending);
	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_DIAG_STATS_RESULT, data, data_len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare item statistics by value                                  *
 *                                                                            *
 ******************************************************************************/
static int	preproc_sort_item_by_values_desc(const void *d1, const void *d2)
{
	const zbx_preproc_item_stats_t	*i1 = *(const zbx_preproc_item_stats_t * const *)d1;
	const zbx_preproc_item_stats_t	*i2 = *(const zbx_preproc_item_stats_t * const *)d2;

	return i2->values_num - i1->values_num;
}

static void	preprocessor_add_item_view(zbx_preprocessing_manager_t *manager, zbx_uint64_t itemid,
		zbx_hashset_t *items, zbx_vector_ptr_t *view)
{
	zbx_preproc_item_stats_t	*item;

	if (NULL == (item = zbx_hashset_search(items, &itemid)))
	{
		zbx_preproc_item_stats_t	item_local = {.itemid = itemid};
		zbx_preproc_item_t		*child;

		item = zbx_hashset_insert(items, &item_local, sizeof(item_local));

		if (NULL != (child = (zbx_preproc_item_t *)zbx_hashset_search(&manager->item_config, &itemid)))
			item->steps_num = child->preproc_ops_num;

		zbx_vector_ptr_append(view, item);
	}
	item->values_num++;
}

static	void	preprocessor_get_items_view(zbx_preprocessing_manager_t *manager, zbx_hashset_t *items,
		zbx_vector_ptr_t *view)
{
	zbx_list_iterator_t			iterator;
	zbx_preprocessing_request_base_t	*base;
	zbx_preprocessing_request_t		*request;
	zbx_preprocessing_dep_request_t		*dep_request;
	zbx_preproc_item_t			*master_item;

	zbx_list_iterator_init(&manager->queue, &iterator);
	while (SUCCEED == zbx_list_iterator_next(&iterator))
	{
		zbx_list_iterator_peek(&iterator, (void **)&base);

		switch (base->kind)
		{
			case ZBX_PREPROC_ITEM:
				request = (zbx_preprocessing_request_t *)base;
				preprocessor_add_item_view(manager, request->value.itemid, items, view);
				break;
			case ZBX_PREPROC_DEPS:
				dep_request = (zbx_preprocessing_dep_request_t *)base;
				if (NULL != (master_item = (zbx_preproc_item_t *)zbx_hashset_search(
						&manager->item_config, &dep_request->master_itemid)))
				{
					int	i;

					for (i = 0; i < master_item->dep_itemids_num; i++)
					{
						preprocessor_add_item_view(manager, master_item->dep_itemids[i].first,
								items, view);
					}
				}
				break;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: return diagnostic top view                                        *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] IPC client                                      *
 *             message - [IN] the message with request                        *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_get_top_items(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	int			limit;
	unsigned char		*data;
	zbx_uint32_t		data_len;
	zbx_hashset_t		items;
	zbx_vector_ptr_t	view;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_preprocessor_unpack_top_request(&limit, message->data);

	zbx_hashset_create(&items, 1024, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_ptr_create(&view);

	preprocessor_get_items_view(manager, &items, &view);

	zbx_vector_ptr_sort(&view, preproc_sort_item_by_values_desc);

	data_len = zbx_preprocessor_pack_top_items_result(&data, (zbx_preproc_item_stats_t **)view.values,
			MIN(limit, view.values_num));
	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_TOP_ITEMS_RESULT, data, data_len);
	zbx_free(data);

	zbx_vector_ptr_destroy(&view);
	zbx_hashset_destroy(&items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	preprocessor_get_oldest_preproc_items(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	int			limit, i;
	unsigned char		*data;
	zbx_uint32_t		data_len;
	zbx_hashset_t		items;
	zbx_vector_ptr_t	view, view_preproc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_preprocessor_unpack_top_request(&limit, message->data);

	zbx_hashset_create(&items, 1024, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_ptr_create(&view);
	zbx_vector_ptr_create(&view_preproc);
	zbx_vector_ptr_reserve(&view_preproc, (size_t)limit);

	preprocessor_get_items_view(manager, &items, &view);

	for (i = 0; i < view.values_num && 0 < limit; i++)
	{
		zbx_preproc_item_stats_t	*item;

		item = (zbx_preproc_item_stats_t *)view.values[i];

		/* only items with preprocessing can slow down queue */
		if (0 == item->steps_num)
			continue;

		zbx_vector_ptr_append(&view_preproc, item);
		limit--;
	}

	data_len = zbx_preprocessor_pack_top_items_result(&data, (zbx_preproc_item_stats_t **)view_preproc.values,
			MIN(limit, view_preproc.values_num));
	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_TOP_ITEMS_RESULT, data, data_len);
	zbx_free(data);

	zbx_vector_ptr_destroy(&view_preproc);
	zbx_vector_ptr_destroy(&view);
	zbx_hashset_destroy(&items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_hash_t	preproc_item_link_hash(const void *d)
{
	const zbx_item_link_t	*link = (const zbx_item_link_t *)d;
	zbx_hash_t		hash;
	unsigned char		kind = link->kind;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&link->itemid);
	return ZBX_DEFAULT_STRING_HASH_ALGO(&kind, 1, hash);
}

static int	preproc_item_link_compare(const void *d1, const void *d2)
{
	const zbx_item_link_t	*l1 = (const zbx_item_link_t *)d1;
	const zbx_item_link_t	*l2 = (const zbx_item_link_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(l1->itemid, l2->itemid);

	return (int)l1->kind - (int)l2->kind;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes preprocessing manager                                 *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_init_manager(zbx_preprocessing_manager_t *manager)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers: %d", __func__, CONFIG_PREPROCESSOR_FORKS);

	memset(manager, 0, sizeof(zbx_preprocessing_manager_t));

	manager->workers = (zbx_preprocessing_worker_t *)zbx_calloc(NULL, (size_t)CONFIG_PREPROCESSOR_FORKS,
			sizeof(zbx_preprocessing_worker_t));
	zbx_list_create(&manager->queue);
	zbx_list_create(&manager->direct_queue);
	zbx_hashset_create_ext(&manager->item_config, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			(zbx_clean_func_t)preproc_item_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	zbx_hashset_create(&manager->linked_items, 0, preproc_item_link_hash, preproc_item_link_compare);
	zbx_hashset_create(&manager->history_cache, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

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
static void preprocessor_register_worker(zbx_preprocessing_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_preprocessing_worker_t	*worker = NULL;
	pid_t				ppid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy preprocessing manager                                     *
 *                                                                            *
 * Parameters: manager - [IN] the manager to destroy                          *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_destroy_manager(zbx_preprocessing_manager_t *manager)
{
	zbx_preprocessing_direct_request_t	*direct_request;
	zbx_preprocessing_request_base_t	*base;

	zbx_free(manager->workers);

	/* this is the place where values are lost */
	while (SUCCEED == zbx_list_pop(&manager->direct_queue, (void **)&direct_request))
		preprocessor_free_direct_request(direct_request);

	zbx_list_destroy(&manager->direct_queue);

	while (SUCCEED == zbx_list_pop(&manager->queue, (void **)&base))
		preprocessor_free_request(base);

	zbx_list_destroy(&manager->queue);

	zbx_hashset_destroy(&manager->item_config);
	zbx_hashset_destroy(&manager->linked_items);
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
	double				time_stat, time_idle = 0, time_now, time_flush, sec;
	zbx_timespec_t			timeout = {ZBX_PREPROCESSING_MANAGER_DELAY, 0};

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

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

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [queued " ZBX_FS_UI64 ", processed " ZBX_FS_UI64 " values, idle "
					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
					get_process_type_string(process_type), process_num,
					manager.queued_num, manager.processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			manager.processed_num = 0;
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, &timeout, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

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
				case ZBX_IPC_PREPROCESSOR_DEP_NEXT:
					preprocessor_next_dep_request(&manager, client);
					break;
				case ZBX_IPC_PREPROCESSOR_DEP_RESULT:
					preprocessor_process_dep_result(&manager, client, message);
					break;
				case ZBX_IPC_PREPROCESSOR_DEP_RESULT_CONT:
					preprocessor_process_dep_result_cont(&manager, client, message);
					break;
				case ZBX_IPC_PREPROCESSOR_QUEUE:
					zbx_ipc_client_send(client, message->code, (unsigned char *)&manager.queued_num,
							sizeof(zbx_uint64_t));
					break;
				case ZBX_IPC_PREPROCESSOR_TEST_REQUEST:
					preprocessor_add_test_request(&manager, client, message);
					break;
				case ZBX_IPC_PREPROCESSOR_TEST_RESULT:
					preprocessor_flush_test_result(&manager, client, message);
					break;
				case ZBX_IPC_PREPROCESSOR_DIAG_STATS:
					preprocessor_get_diag_stats(&manager, client);
					break;
				case ZBX_IPC_PREPROCESSOR_TOP_ITEMS:
					preprocessor_get_top_items(&manager, client, message);
					break;
				case ZBX_IPC_PREPROCESSOR_TOP_OLDEST_PREPROC_ITEMS:
					preprocessor_get_oldest_preproc_items(&manager, client, message);
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

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_service_close(&service);
	preprocessor_destroy_manager(&manager);
#undef STAT_INTERVAL
}
