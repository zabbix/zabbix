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

#include "pp_manager.h"

#include "pp_worker.h"
#include "pp_queue.h"
#include "pp_item.h"
#include "pp_task.h"
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxtimekeeper.h"
#include "zbxself.h"
#include "zbxstr.h"
#include "zbxcachehistory.h"

#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

#define PP_STARTUP_TIMEOUT	10

/******************************************************************************
 *                                                                            *
 * Purpose: initialize xml library, called before creating worker threads     *
 *                                                                            *
 ******************************************************************************/
static void	pp_xml_init(void)
{
#ifdef HAVE_LIBXML2
	xmlInitParser();
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: release xml library resources                                     *
 *                                                                            *
 ******************************************************************************/
static void	pp_xml_destroy(void)
{
#ifdef HAVE_LIBXML2
	xmlCleanupParser();
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize curl library, called before creating worker threads    *
 *                                                                            *
 ******************************************************************************/
static void	pp_curl_init(void)
{
#ifdef HAVE_LIBCURL
	curl_global_init(CURL_GLOBAL_DEFAULT);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: release curl library resources                                    *
 *                                                                            *
 ******************************************************************************/
static void	pp_curl_destroy(void)
{
#ifdef HAVE_LIBCURL
	curl_global_cleanup();
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize preprocessing manager                                  *
 *                                                                            *
 * Parameters: manager     - [IN] the manager                                 *
 *             workers_num - [IN] the number of workers to create             *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Return value: SUCCEED - the manager was initialized successfully           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	pp_manager_init(zbx_pp_manager_t *manager, int program_type, int workers_num, char **error)
{
	int		i, ret = FAIL, started_num = 0;
	time_t		time_start;
	struct timespec	poll_delay = {0, 1e8};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers:%d", __func__, workers_num);

	pp_xml_init();
	pp_curl_init();
#ifdef HAVE_NETSNMP
	preproc_init_snmp();
#endif
	memset(manager, 0, sizeof(zbx_pp_manager_t));

	if (SUCCEED != pp_task_queue_init(&manager->queue, error))
		goto out;

	manager->timekeeper = zbx_timekeeper_create(workers_num, NULL);

	manager->workers_num = workers_num;
	manager->workers = (zbx_pp_worker_t *)zbx_calloc(NULL, workers_num, sizeof(zbx_pp_worker_t));

	manager->program_type = program_type;

	for (i = 0; i < workers_num; i++)
	{
		if (SUCCEED != pp_worker_init(&manager->workers[i], i + 1, &manager->queue, manager->timekeeper, error))
			goto out;
	}

	zbx_hashset_create_ext(&manager->items, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			(zbx_clean_func_t)pp_item_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

	/* wait for threads to start */
	time_start = time(NULL);

	while (started_num != workers_num)
	{
		if (time_start + PP_STARTUP_TIMEOUT < time(NULL))
		{
			*error = zbx_strdup(NULL, "timeout occurred while waiting for workers to start");
			goto out;
		}

		pthread_mutex_lock(&manager->queue.lock);
		started_num = manager->queue.workers_num;
		pthread_mutex_unlock(&manager->queue.lock);

		nanosleep(&poll_delay, NULL);
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		for (i = 0; i < manager->workers_num; i++)
			pp_worker_stop(&manager->workers[i]);

		pp_task_queue_destroy(&manager->queue);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%s error:%s", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy preprocessing manager                                     *
 *                                                                            *
 ******************************************************************************/
void	pp_manager_destroy(zbx_pp_manager_t *manager)
{
	int	i;

	for (i = 0; i < manager->workers_num; i++)
		pp_worker_stop(&manager->workers[i]);

	pp_task_queue_notify_all(&manager->queue);

	for (i = 0; i < manager->workers_num; i++)
		pp_worker_destroy(&manager->workers[i]);

	pp_task_queue_destroy(&manager->queue);
	zbx_hashset_destroy(&manager->items);

	zbx_timekeeper_free(manager->timekeeper);

#ifdef HAVE_NETSNMP
	preproc_shutdown_snmp();
#endif
	pp_curl_destroy();
	pp_xml_destroy();
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue value for preprocessing test                                *
 *                                                                            *
 * Parameters: manager   - [IN] the manager                                   *
 *             preproc   - [IN] the item preprocessing data                   *
 *             value     - [IN] the value to preprocess, its contents will be *
 *                              directly copied over and cleared by the task  *
 *             ts        - [IN] the value timestamp                           *
 *             client    - [IN] the request source                            *
 *                                                                            *
 ******************************************************************************/
void	pp_manager_queue_test(zbx_pp_manager_t *manager, zbx_pp_item_preproc_t *preproc, zbx_variant_t *value,
		zbx_timespec_t ts, zbx_ipc_client_t *client)
{
	zbx_pp_task_t	*task;

	task = pp_task_test_create(preproc, value, ts, client);
	pp_task_queue_push_test(&manager->queue, task);
	pp_task_queue_notify(&manager->queue);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue item value for preprocessing                                *
 *                                                                            *
 * Parameters: manager   - [IN] the manager                                   *
 *             itemid    - [IN] the item identifier                           *
 *             value     - [IN] the value to preprocess, its contents will be *
 *                              directly copied over and cleared by the task  *
 *             ts        - [IN] the value timestamp                           *
 *             value_opt - [IN] the optional value data (optional)            *
 *                                                                            *
 * Return value: SUCCEED - new task was created and queued for preprocessing  *
 *               FAIl    - item does not need preprocessing                   *
 *                                                                            *
 ******************************************************************************/
int	pp_manager_queue_preproc(zbx_pp_manager_t *manager, zbx_uint64_t itemid, zbx_variant_t *value,
		zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt)
{
	zbx_pp_item_t	*item;
	zbx_pp_task_t	*task;

	if (NULL == (item = (zbx_pp_item_t *)zbx_hashset_search(&manager->items, &itemid)))
		return FAIL;

	if (0 == item->preproc->dep_itemids_num && 0 == item->preproc->steps_num)
		return FAIL;

	if (ZBX_PP_PROCESS_PARALLEL == item->preproc->mode)
		task = pp_task_value_create(item->itemid, item->preproc, value, ts, value_opt, NULL);
	else
		task = pp_task_value_seq_create(item->itemid, item->preproc, value, ts, value_opt, NULL);

	pp_task_queue_push(&manager->queue, item, task);
	pp_task_queue_notify(&manager->queue);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue new tasks in response to finished value task                *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             task    - [IN] the finished value task                         *
 *                                                                            *
 ******************************************************************************/
static void	pp_manager_queue_value_task_result(zbx_pp_manager_t *manager, zbx_pp_task_t *task)
{
	zbx_pp_task_value_t	*d = (zbx_pp_task_value_t *)PP_TASK_DATA(task);

	if (0 != d->preproc->dep_itemids_num)
	{
		zbx_pp_task_t		*dep_task;
		zbx_pp_item_t		*item;
		zbx_pp_item_preproc_t	*preproc;
		zbx_variant_t		value;

		dep_task = pp_task_dependent_create(d->preproc->dep_itemids[0], d->preproc);

		if (NULL != (item = (zbx_pp_item_t *)zbx_hashset_search(&manager->items, &d->preproc->dep_itemids[0])))
			preproc = item->preproc;
		else
			preproc = NULL;

		zbx_pp_task_dependent_t	*d_dep = (zbx_pp_task_dependent_t *)PP_TASK_DATA(dep_task);

		zbx_variant_copy(&value, &d->result);
		d_dep->first_task = pp_task_value_create(d->preproc->dep_itemids[0], preproc, &value, d->ts, NULL,
				NULL);

		pp_task_queue_push_immediate(&manager->queue, dep_task);
		pp_task_queue_notify(&manager->queue);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue new tasks in response to finished dependent task            *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             task    - [IN] the finished dependent task                     *
 *                                                                            *
 ******************************************************************************/
static zbx_pp_task_t	*pp_manager_queue_dependent_task_result(zbx_pp_manager_t *manager, zbx_pp_task_t *task)
{
	int	i;

	zbx_pp_task_dependent_t	*d = (zbx_pp_task_dependent_t *)PP_TASK_DATA(task);
	zbx_pp_task_t		*task_value = d->first_task;
	zbx_pp_task_value_t	*d_first = (zbx_pp_task_value_t *)PP_TASK_DATA(task_value);

	pp_manager_queue_value_task_result(manager, d->first_task);

	for (i = 1; i < d->preproc->dep_itemids_num; i++)
	{
		zbx_pp_item_t	*item;
		zbx_pp_task_t	*new_task;

		if (NULL == (item = (zbx_pp_item_t *)zbx_hashset_search(&manager->items, &d->preproc->dep_itemids[i])))
			continue;

		if (ZBX_PP_PROCESS_PARALLEL == item->preproc->mode)
		{
			new_task = pp_task_value_create(item->itemid, item->preproc, NULL, d_first->ts, NULL, d->cache);
		}
		else
		{
			new_task = pp_task_value_seq_create(item->itemid, item->preproc, NULL, d_first->ts, NULL,
					d->cache);
		}

		pp_task_queue_push_immediate(&manager->queue, new_task);
	}

	pp_task_queue_notify_all(&manager->queue);

	d->first_task = NULL;
	pp_task_free(task);

	return task_value;
}

/******************************************************************************
 *                                                                            *
 * Purpose: requeue sequence task                                             *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             task    - [IN] the finished sequence task                      *
 *                                                                            *
 ******************************************************************************/
static zbx_pp_task_t	*pp_manager_requeue_next_sequence_task(zbx_pp_manager_t *manager, zbx_pp_task_t *task_seq)
{
	zbx_pp_task_sequence_t	*d_seq = (zbx_pp_task_sequence_t *)PP_TASK_DATA(task_seq);
	zbx_pp_task_t		*task = NULL, *tmp_task;

	if (SUCCEED == zbx_list_pop(&d_seq->tasks, (void **)&task))
	{
		switch (task->type)
		{
			case ZBX_PP_TASK_VALUE:
			case ZBX_PP_TASK_VALUE_SEQ:
				pp_manager_queue_value_task_result(manager, task);
				break;
			case ZBX_PP_TASK_DEPENDENT:
				task = pp_manager_queue_dependent_task_result(manager, task);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}
	}

	if (SUCCEED == zbx_list_peek(&d_seq->tasks, (void **)&tmp_task))
	{
		pp_task_queue_push_immediate(&manager->queue, task_seq);
		pp_task_queue_notify(&manager->queue);
	}
	else
		pp_task_queue_remove_sequence(&manager->queue, task_seq->itemid);

	return task;
}

#define PP_FINISSHED_TASK_BATCH_SIZE	100

ZBX_PTR_VECTOR_DECL(pp_task, zbx_pp_task_t *)
ZBX_PTR_VECTOR_IMPL(pp_task, zbx_pp_task_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: process finished tasks                                            *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *                                                                            *
 ******************************************************************************/
void	pp_manager_process_finished(zbx_pp_manager_t *manager)
{
	zbx_vector_pp_task_t	tasks;
	zbx_pp_task_t		*task;
	int			values_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_pp_task_create(&tasks);
	zbx_vector_pp_task_reserve(&tasks, PP_FINISSHED_TASK_BATCH_SIZE);

	pp_task_queue_lock(&manager->queue);

	while (PP_FINISSHED_TASK_BATCH_SIZE > tasks.values_num)
	{
		if (NULL != (task = pp_task_queue_pop_finished(&manager->queue)))
		{
			switch (task->type)
			{
				case ZBX_PP_TASK_VALUE:
					pp_manager_queue_value_task_result(manager, task);
					break;
				case ZBX_PP_TASK_DEPENDENT:
					task = pp_manager_queue_dependent_task_result(manager, task);
					break;
				case ZBX_PP_TASK_SEQUENCE:
					task = pp_manager_requeue_next_sequence_task(manager, task);
					break;
				default:
					break;
			}
		}

		if (NULL == task)
			break;

		zbx_vector_pp_task_append(&tasks, task);
	}

	pp_task_queue_unlock(&manager->queue);

	for (int i = 0; i < tasks.values_num; i++)
	{
		switch (tasks.values[i]->type)
		{
			case ZBX_PP_TASK_VALUE:
			case ZBX_PP_TASK_VALUE_SEQ:	/* value and value_seq task data is identical */
				zbx_pp_task_value_t	*d = (zbx_pp_task_value_t *)PP_TASK_DATA(tasks.values[i]);

				pp_manager_flush_value(manager, tasks.values[i]->itemid, d->preproc->value_type,
						d->preproc->flags, &d->result, d->ts, &d->opt);
				break;
			case ZBX_PP_TASK_TEST:
				/* the result has been already sent back by worker */
				break;
			default:
				/* the internal tasks (dependent/sequence) shouldn't get here */
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	values_num = tasks.values_num;

	zbx_vector_pp_task_clear_ext(&tasks, pp_task_free);
	zbx_vector_pp_task_destroy(&tasks);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values_num:%d", __func__, values_num);

}

/******************************************************************************
 *                                                                            *
 * Purpose: dump cached item information into log                             *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *                                                                            *
 ******************************************************************************/
void	pp_manager_dump_items(zbx_pp_manager_t *manager)
{
	zbx_hashset_iter_t	iter;
	zbx_pp_item_t		*item;

	zbx_hashset_iter_reset(&manager->items, &iter);
	while (NULL != (item = (zbx_pp_item_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "itemid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " revision:" ZBX_FS_UI64
				" type:%u value_type:%u mode:%d flags:%u",
				item->itemid, item->hostid, item->revision, item->preproc->type,
				item->preproc->value_type, item->preproc->mode, item->preproc->flags);

		zabbix_log(LOG_LEVEL_TRACE, "  preprocessing steps:");
		for (int i = 0; i < item->preproc->steps_num; i++)
		{
			zabbix_log(LOG_LEVEL_TRACE, "    type:%u params:'%s' err_handler:%u err_params:'%s'",
					item->preproc->steps[i].type,
					ZBX_NULL2EMPTY_STR(item->preproc->steps[i].params),
					item->preproc->steps[i].error_handler,
					ZBX_NULL2EMPTY_STR(item->preproc->steps[i].error_handler_params));
		}

		zabbix_log(LOG_LEVEL_TRACE, "  dependent items:");
		for (int i = 0; i < item->preproc->dep_itemids_num; i++)
			zabbix_log(LOG_LEVEL_TRACE, "    " ZBX_FS_UI64, item->preproc->dep_itemids[i]);
	}

}

/******************************************************************************
 *                                                                            *
 * Purpose: flush preprocessed value                                          *
 *                                                                            *
 * Parameters: manager    - [IN] the preprocessing manager                    *
 *             itemid     - [IN] the item identifier                          *
 *             value_type - [IN] the item value type                          *
 *             flags      - [IN] the item flags                               *
 *             value      - [IN] preprocessed item value                      *
 *             ts         - [IN] the value timestamp                          *
 *             value_opt  - [IN] the optional value data                      *
 *                                                                            *
 ******************************************************************************/
void	pp_manager_flush_value(zbx_pp_manager_t *manager, zbx_uint64_t itemid, unsigned char value_type,
		unsigned char flags, zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_value_opt_t *value_opt)
{
	if (0 == (flags & ZBX_FLAG_DISCOVERY_RULE) || 0 == (manager->program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		dc_add_history_variant(itemid, value_type, flags, value, ts, value_opt);
	}
	else
	{
		zbx_pp_item_t	*item;

		if (NULL != (item = (zbx_pp_item_t *)zbx_hashset_search(&manager->items, &itemid)))
		{
			const char	*value_lld = NULL, *error_lld = NULL;
			unsigned char	meta = 0;
			zbx_uint64_t	lastlogsize = 0;
			int		mtime = 0;

			if (ZBX_VARIANT_ERR == value->type)
			{
				error_lld = value->data.err;
			}
			else
			{
				if (SUCCEED == zbx_variant_convert(value, ZBX_VARIANT_STR))
					value_lld = value->data.str;
			}

			if (0 != (value_opt->flags & ZBX_PP_VALUE_OPT_META))
			{
				meta = 1;
				lastlogsize = value_opt->lastlogsize;
				mtime = value_opt->mtime;
			}

			if (NULL != value_lld || NULL != error_lld || 0 != meta)
			{
				zbx_lld_process_value(itemid, item->hostid, value_lld, &ts, meta, lastlogsize, mtime,
						error_lld);
			}
		}
	}
}
