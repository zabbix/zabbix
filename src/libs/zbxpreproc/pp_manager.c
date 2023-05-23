/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
#include "preproc_snmp.h"
#include "zbxpreproc.h"
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxtimekeeper.h"
#include "zbxself.h"
#include "zbxstr.h"
#include "zbxcachehistory.h"
#include "zbxprof.h"

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
 * Purpose: create preprocessing manager                                      *
 *                                                                            *
 * Parameters: program_type  - [IN] the component type (server/proxy)         *
 *             workers_num   - [IN] the number of workers to create           *
 *             finished_cb   - [IN] a callback to call after finishing        *
 *                                  task (optional)                           *
 *             finished_data - [IN] the callback data (optional)              *
 *             error         - [OUT] the error message                        *
 *                                                                            *
 * Return value: The created manager or NULL on error.                        *
 *                                                                            *
 ******************************************************************************/
zbx_pp_manager_t	*zbx_pp_manager_create(int workers_num, zbx_pp_notify_cb_t finished_cb,
		void *finished_data, char **error)
{
	int			i, ret = FAIL, started_num = 0;
	time_t			time_start;
	struct timespec		poll_delay = {0, 1e8};
	zbx_pp_manager_t	*manager;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers:%d", __func__, workers_num);

	pp_xml_init();
	pp_curl_init();
#ifdef HAVE_NETSNMP
	preproc_init_snmp();
#endif
	manager = (zbx_pp_manager_t *)zbx_malloc(NULL, sizeof(zbx_pp_manager_t));
	memset(manager, 0, sizeof(zbx_pp_manager_t));

	if (SUCCEED != pp_task_queue_init(&manager->queue, error))
		goto out;

	manager->timekeeper = zbx_timekeeper_create(workers_num, NULL);

	manager->workers_num = workers_num;
	manager->workers = (zbx_pp_worker_t *)zbx_calloc(NULL, (size_t)workers_num, sizeof(zbx_pp_worker_t));

	for (i = 0; i < workers_num; i++)
	{
		if (SUCCEED != pp_worker_init(&manager->workers[i], i + 1, &manager->queue, manager->timekeeper, error))
			goto out;

		pp_worker_set_finished_cb(&manager->workers[i], finished_cb, finished_data);
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
		zbx_free(manager);

		manager = NULL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%s error:%s", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return manager;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy preprocessing manager                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_free(zbx_pp_manager_t *manager)
{
	int	i;

	pp_task_queue_lock(&manager->queue);
	for (i = 0; i < manager->workers_num; i++)
		pp_worker_stop(&manager->workers[i]);

	pp_task_queue_notify_all(&manager->queue);
	pp_task_queue_unlock(&manager->queue);

	for (i = 0; i < manager->workers_num; i++)
		pp_worker_destroy(&manager->workers[i]);

	zbx_free(manager->workers);

	pp_task_queue_destroy(&manager->queue);
	zbx_hashset_destroy(&manager->items);

	zbx_timekeeper_free(manager->timekeeper);

#ifdef HAVE_NETSNMP
	preproc_shutdown_snmp();
#endif
	pp_curl_destroy();
	pp_xml_destroy();

	zbx_free(manager);
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
void	zbx_pp_manager_queue_test(zbx_pp_manager_t *manager, zbx_pp_item_preproc_t *preproc, zbx_variant_t *value,
		zbx_timespec_t ts, zbx_ipc_client_t *client)
{
	zbx_pp_task_t	*task;

	task = pp_task_test_create(preproc, value, ts, client);
	pp_task_queue_lock(&manager->queue);
	pp_task_queue_push_test(&manager->queue, task);
	pp_task_queue_notify(&manager->queue);
	pp_task_queue_unlock(&manager->queue);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue value/value_sec preprocessing tasks                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_queue_value_preproc(zbx_pp_manager_t *manager, zbx_vector_pp_task_ptr_t *tasks)
{
	zbx_prof_start(__func__, ZBX_PROF_MUTEX);
	pp_task_queue_lock(&manager->queue);
	zbx_prof_end_wait();

	for (int i = 0; i < tasks->values_num; i++)
		pp_task_queue_push(&manager->queue, tasks->values[i]);

	pp_task_queue_notify(&manager->queue);

	pp_task_queue_unlock(&manager->queue);
	zbx_prof_end();
}

/******************************************************************************
 *                                                                            *
 * Purpose: create preprocessing task from request                            *
 *                                                                            *
 * Parameters: manager   - [IN] the manager                                   *
 *             itemid    - [IN] the item identifier                           *
 *             value     - [IN] the value to preprocess, its contents will be *
 *                              directly copied over and cleared by the task  *
 *             ts        - [IN] the value timestamp                           *
 *             value_opt - [IN] the optional value data (optional)            *
 *                                                                            *
 * Return value: The created task or NULL if the data can be flushed directly.*
 *                                                                            *
 ******************************************************************************/
zbx_pp_task_t	*zbx_pp_manager_create_task(zbx_pp_manager_t *manager, zbx_uint64_t itemid, zbx_variant_t *value,
		zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt)
{
	zbx_pp_item_t	*item;

	if (ZBX_VARIANT_NONE == value->type)
		return NULL;

	if (NULL == (item = (zbx_pp_item_t *)zbx_hashset_search(&manager->items, &itemid)))
		return NULL;

	if (0 == item->preproc->dep_itemids_num && 0 == item->preproc->steps_num)
		return NULL;

	if (ZBX_PP_PROCESS_PARALLEL == item->preproc->mode)
		return pp_task_value_create(item->itemid, item->preproc, value, ts, value_opt, NULL);
	else
		return pp_task_value_seq_create(item->itemid, item->preproc, value, ts, value_opt, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get first dependent item with preprocessing that can be cached    *
 *                                                                            *
 * Parameters: manager     - [IN] the manager                                 *
 *             itemids     - [IN] the dependent itemids                       *
 *             itemids_num - [IN] the number of dependent itemids             *
 *                                                                            *
 * Return value: The first dependent item with cacheable preprocessing data   *
 *               or NULL.                                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_pp_item_t	*pp_manager_get_cacheable_dependent_item(zbx_pp_manager_t *manager, zbx_uint64_t *itemids,
		int itemids_num)
{
	zbx_pp_item_t	*item;

	for (int i = 0; i < itemids_num; i++)
	{
		if (NULL == (item = (zbx_pp_item_t *)zbx_hashset_search(&manager->items, &itemids[i])))
			continue;

		if (SUCCEED == pp_cache_is_supported(item->preproc))
			return item;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and queue tasks for dependent items                        *
 *                                                                            *
 * Parameters: manager        - [IN] the manager                              *
 *             preproc        - [IN] the master item preprocessing data       *
 *             exclude_itemid - [IN] the dependent itemid to exclude, can be 0*
 *             ts             - [IN] the value timestamp                      *
 *             cache          - [IN] the preprocessing cache                  *
 *                                   (optional, can be NULL)                  *
 *                                                                            *
 * Comments: This function called within task queue lock.                     *
 *                                                                            *
 ******************************************************************************/
static void	pp_manager_queue_dependents(zbx_pp_manager_t *manager, zbx_pp_item_preproc_t *preproc,
		zbx_uint64_t exclude_itemid, const zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_cache_t *cache)
{
	int	queued_num = 0;

	if (0 == preproc->dep_itemids_num)
		return;

	cache = pp_cache_copy(cache);

	if (NULL == cache)
		cache = pp_cache_create(preproc, value);

	for (int i = 0; i < preproc->dep_itemids_num; i++)
	{
		zbx_pp_item_t	*item;
		zbx_pp_task_t	*new_task;

		/* skip already preprocessed dependent item */
		if (preproc->dep_itemids[i] == exclude_itemid)
			continue;

		/* skip disabled/removed items */
		if (NULL == (item = (zbx_pp_item_t *)zbx_hashset_search(&manager->items, &preproc->dep_itemids[i])))
			continue;

		if (ZBX_PP_PROCESS_PARALLEL == item->preproc->mode)
			new_task = pp_task_value_create(item->itemid, item->preproc, NULL, ts, NULL, cache);
		else
			new_task = pp_task_value_seq_create(item->itemid, item->preproc, NULL, ts, NULL, cache);

		pp_task_queue_push_immediate(&manager->queue, new_task);
		queued_num++;
	}

	if (0 < queued_num)
		pp_task_queue_notify(&manager->queue);

	pp_cache_release(cache);
}


/******************************************************************************
 *                                                                            *
 * Purpose: queue new tasks in response to finished value task                *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             task    - [IN] the finished value task                         *
 *                                                                            *
 * Comments: This function called within task queue lock.                     *
 *                                                                            *
 ******************************************************************************/
static void	pp_manager_queue_value_task_result(zbx_pp_manager_t *manager, zbx_pp_task_t *task)
{
	zbx_pp_task_value_t	*d = (zbx_pp_task_value_t *)PP_TASK_DATA(task);
	zbx_pp_item_t		*item;

	if (ZBX_VARIANT_NONE == d->result.type)
		return;

	if (NULL != (item = pp_manager_get_cacheable_dependent_item(manager, d->preproc->dep_itemids,
			d->preproc->dep_itemids_num)))
	{
		zbx_pp_task_t	*dep_task;
		zbx_variant_t	value;

		dep_task = pp_task_dependent_create(task->itemid, d->preproc);
		zbx_pp_task_dependent_t	*d_dep = (zbx_pp_task_dependent_t *)PP_TASK_DATA(dep_task);

		d_dep->cache = pp_cache_create(item->preproc, &d->result);
		zbx_variant_set_none(&value);

		d_dep->primary = pp_task_value_create(item->itemid, item->preproc, &value, d->ts, NULL, d_dep->cache);

		pp_task_queue_push_immediate(&manager->queue, dep_task);
		pp_task_queue_notify(&manager->queue);
	}
	else
		pp_manager_queue_dependents(manager, d->preproc, 0, &d->result, d->ts, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue new tasks in response to finished dependent task            *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             task    - [IN] the finished dependent task                     *
 *                                                                            *
 * Comments: This function called within task queue lock.                     *
 *                                                                            *
 ******************************************************************************/
static zbx_pp_task_t	*pp_manager_queue_dependent_task_result(zbx_pp_manager_t *manager, zbx_pp_task_t *task)
{
	zbx_pp_task_dependent_t	*d = (zbx_pp_task_dependent_t *)PP_TASK_DATA(task);
	zbx_pp_task_t		*task_value = d->primary;
	zbx_pp_task_value_t	*dp = (zbx_pp_task_value_t *)PP_TASK_DATA(task_value);

	pp_manager_queue_value_task_result(manager, d->primary);
	pp_manager_queue_dependents(manager, d->preproc, task_value->itemid, &dp->result, dp->ts, d->cache);

	d->primary = NULL;
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
 * Comments: This function called within task queue lock.                     *
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
	{
		pp_task_queue_remove_sequence(&manager->queue, task_seq->itemid);
		pp_task_free(task_seq);
	}

	return task;
}

#define PP_FINISHED_TASK_BATCH_SIZE	100

/******************************************************************************
 *                                                                            *
 * Purpose: process finished tasks                                            *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_process_finished(zbx_pp_manager_t *manager, zbx_vector_pp_task_ptr_t *tasks,
		zbx_uint64_t *pending_num, zbx_uint64_t *processing_num, zbx_uint64_t *finished_num)
{
	zbx_pp_task_t	*task;
	static time_t	timekeeper_clock = 0;
	time_t		now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_pp_task_ptr_reserve(tasks, PP_FINISHED_TASK_BATCH_SIZE);
	zbx_prof_start(__func__, ZBX_PROF_MUTEX);
	pp_task_queue_lock(&manager->queue);
	zbx_prof_end_wait();
	while (PP_FINISHED_TASK_BATCH_SIZE > tasks->values_num)
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

		zbx_vector_pp_task_ptr_append(tasks, task);
	}

	*pending_num = manager->queue.pending_num;
	*finished_num = manager->queue.finished_num;
	*processing_num = manager->queue.processing_num;

	pp_task_queue_unlock(&manager->queue);
	zbx_prof_end();
	now = time(NULL);
	if (now != timekeeper_clock)
	{
		zbx_timekeeper_collect(manager->timekeeper);
		timekeeper_clock = now;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values_num:%d", __func__, tasks->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: dump cached item information into log                             *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_dump_items(zbx_pp_manager_t *manager)
{
	zbx_hashset_iter_t	iter;
	zbx_pp_item_t		*item;

	zbx_hashset_iter_reset(&manager->items, &iter);
	while (NULL != (item = (zbx_pp_item_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "itemid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " revision:" ZBX_FS_UI64
				" type:%u value_type:%u mode:%u flags:%u",
				item->itemid, item->hostid, item->revision, item->preproc->type,
				item->preproc->value_type, item->preproc->mode, item->preproc->flags);

		zabbix_log(LOG_LEVEL_TRACE, "  preprocessing steps:");
		for (int i = 0; i < item->preproc->steps_num; i++)
		{
			zabbix_log(LOG_LEVEL_TRACE, "    type:%d params:'%s' err_handler:%d err_params:'%s'",
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
 * Purpose: get manager configuration revision                                *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_pp_manager_get_revision(const zbx_pp_manager_t *manager)
{
	return manager->revision;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set manager configuration revision                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_set_revision(zbx_pp_manager_t *manager, zbx_uint64_t revision)
{
	manager->revision = revision;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get item configuration data for reading and updates               *
 *                                                                            *
 ******************************************************************************/
zbx_hashset_t	*zbx_pp_manager_items(zbx_pp_manager_t *manager)
{
	return &manager->items;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get number of pending preprocessing tasks                         *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_pp_manager_get_pending_num(zbx_pp_manager_t *manager)
{
	return manager->queue.pending_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get diagnostic statistics                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_get_diag_stats(zbx_pp_manager_t *manager, zbx_uint64_t *preproc_num, zbx_uint64_t *pending_num,
		zbx_uint64_t *finished_num, zbx_uint64_t *sequences_num)
{
	*preproc_num = (zbx_uint64_t)manager->items.num_data;
	*pending_num = manager->queue.pending_num;
	*finished_num = manager->queue.finished_num;
	*sequences_num = (zbx_uint64_t)manager->queue.sequences.num_data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get task sequence statistics                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_get_sequence_stats(zbx_pp_manager_t *manager, zbx_vector_pp_sequence_stats_ptr_t *sequences)
{
	pp_task_queue_get_sequence_stats(&manager->queue, sequences);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get worker usage statistics                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_get_worker_usage(zbx_pp_manager_t *manager, zbx_vector_dbl_t *worker_usage)
{
	(void)zbx_timekeeper_get_usage(manager->timekeeper, worker_usage);
}

/******************************************************************************
 *                                                                            *
 * Purpose: change worker log level                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_pp_manager_change_worker_loglevel(zbx_pp_manager_t *manager, int worker_num, int direction)
{
	if (0 > worker_num || manager->workers_num < worker_num)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "Cannot change log level for preprocessing worker #%d:"
				" no such instance", worker_num);
		return;
	}

	for (int i = 0; i < manager->workers_num; i++)
	{
		if (0 != worker_num && worker_num != i + 1)
			continue;

		zbx_change_component_log_level(&manager->workers[i].logger, direction);
	}
}
