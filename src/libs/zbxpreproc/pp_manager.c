/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "pp_manager.h"
#include "pp_worker.h"
#include "pp_queue.h"
#include "pp_task.h"
#include "zbxpreproc.h"
#include "zbxalgo.h"
#include "zbxtimekeeper.h"
#include "zbxself.h"
#include "zbxstr.h"
#include "zbxcachehistory.h"
#include "zbxprof.h"
#include "pp_protocol.h"
#include "zbx_item_constants.h"
#include "zbxnix.h"
#include "zbxvariant.h"
#include "zbxlog.h"
#include "pp_cache.h"
#include "zbxcacheconfig.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"
#include "zbxtime.h"
#include "zbxrtc.h"
#include "zbxpreprocbase.h"
#include "zbx_rtc_constants.h"

#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#	include <libxml/parser.h>
#endif

#ifdef HAVE_NETSNMP
#	include "preproc_snmp.h"
#endif

static zbx_prepare_value_func_t	prepare_value_func_cb = NULL;
static zbx_flush_value_func_t	flush_value_func_cb = NULL;
static zbx_get_progname_f	get_progname_func_cb = NULL;

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

void	zbx_init_library_preproc(zbx_prepare_value_func_t prepare_value_cb, zbx_flush_value_func_t flush_value_cb,
		zbx_get_progname_f get_progname_cb)
{
	prepare_value_func_cb = prepare_value_cb;
	flush_value_func_cb = flush_value_cb;
	get_progname_func_cb = get_progname_cb;
}

zbx_get_progname_f	preproc_get_progname_cb(void)
{
	return get_progname_func_cb;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create preprocessing manager                                      *
 *                                                                            *
 * Parameters: workers_num      - [IN] number of workers to create            *
 *             finished_cb      - [IN] callback to call after finishing       *
 *                                     task (optional)                        *
 *             finished_data    - [IN] callback data (optional)               *
 *             config_source_ip - [IN]                                        *
 *             config_timeout   - [IN]                                        *
 *             error            - [OUT]                                       *
 *                                                                            *
 * Return value: The created manager or NULL on error.                        *
 *                                                                            *
 ******************************************************************************/
static zbx_pp_manager_t	*zbx_pp_manager_create(int workers_num, zbx_pp_notify_cb_t finished_cb,
		void *finished_data, const char *config_source_ip, int config_timeout, char **error)
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
		if (SUCCEED != pp_worker_init(&manager->workers[i], i + 1, &manager->queue, manager->timekeeper,
				config_source_ip, error))
		{
			goto out;
		}

		pp_worker_set_finished_cb(&manager->workers[i], finished_cb, finished_data);
	}

	zbx_hashset_create_ext(&manager->items, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			(zbx_clean_func_t)zbx_pp_item_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

	if (FAIL == zbx_ipc_async_socket_open(&manager->rtc, ZBX_IPC_SERVICE_RTC, config_timeout, error))
		goto out;

	/* wait for threads to start */
	time_start = time(NULL);

#define PP_STARTUP_TIMEOUT	10

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

#undef PP_STARTUP_TIMEOUT

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
static void	zbx_pp_manager_free(zbx_pp_manager_t *manager)
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

	zbx_dc_um_shared_handle_release(manager->um_handle);

#ifdef HAVE_NETSNMP
	preproc_shutdown_snmp();
#endif
	pp_curl_destroy();
	pp_xml_destroy();

	zbx_ipc_async_socket_close(&manager->rtc);

	zbx_free(manager);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue value for preprocessing test                                *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             preproc - [IN] item preprocessing data                         *
 *             value   - [IN] value to preprocess, its contents will be       *
 *                            directly copied over and cleared by the task    *
 *             ts      - [IN] value timestamp                                 *
 *             client  - [IN] request source                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_pp_manager_queue_test(zbx_pp_manager_t *manager, zbx_pp_item_preproc_t *preproc, zbx_variant_t *value,
		zbx_timespec_t ts, zbx_ipc_client_t *client)
{
	zbx_pp_task_t	*task = pp_task_test_create(preproc, value, ts, client);

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
static void	zbx_pp_manager_queue_value_preproc(zbx_pp_manager_t *manager, zbx_vector_pp_task_ptr_t *tasks)
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
 * Parameters: manager   - [IN]                                               *
 *             itemid    - [IN] item identifier                               *
 *             value     - [IN] value to preprocess, its contents will be     *
 *                              directly copied over and cleared by the task  *
 *             ts        - [IN] value timestamp                               *
 *             value_opt - [IN] optional value data (optional)                *
 *                                                                            *
 * Return value: The created task or NULL if the data can be flushed directly.*
 *                                                                            *
 ******************************************************************************/
static zbx_pp_task_t	*zbx_pp_manager_create_task(zbx_pp_manager_t *manager, zbx_uint64_t itemid,
		zbx_variant_t *value, zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt)
{
	zbx_pp_item_t	*item;

	if (ZBX_VARIANT_NONE == value->type)
		return NULL;

	if (NULL == (item = (zbx_pp_item_t *)zbx_hashset_search(&manager->items, &itemid)))
		return NULL;

	if (0 == item->preproc->dep_itemids_num && 0 == item->preproc->steps_num)
		return NULL;

	if (ZBX_PP_PROCESS_PARALLEL == item->preproc->mode)
	{
		return pp_task_value_create(item->itemid, item->preproc, manager->um_handle, value, ts, value_opt,
				NULL);
	}
	else
	{
		return pp_task_value_seq_create(item->itemid, item->preproc, manager->um_handle, value, ts, value_opt,
				NULL);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get first dependent item with preprocessing that can be cached    *
 *                                                                            *
 * Parameters: manager     - [IN]                                             *
 *             itemids     - [IN] dependent itemids                           *
 *             itemids_num - [IN] number of dependent itemids                 *
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
 * Parameters: manager        - [IN] manager                                  *
 *             preproc        - [IN] master item preprocessing data           *
 *             um_handle      - [IN] shared user macro cache handle           *
 *             exclude_itemid - [IN] dependent itemid to exclude, can be 0    *
 *             value          - [IN] value                                    *
 *             ts             - [IN] value timestamp                          *
 *             cache          - [IN] preprocessing cache                      *
 *                                   (optional, can be NULL)                  *
 *                                                                            *
 * Comments: This function called within task queue lock.                     *
 *                                                                            *
 ******************************************************************************/
static void	pp_manager_queue_dependents(zbx_pp_manager_t *manager, zbx_pp_item_preproc_t *preproc,
		zbx_dc_um_shared_handle_t *um_handle, zbx_uint64_t exclude_itemid, const zbx_variant_t *value,
		zbx_timespec_t ts, zbx_pp_cache_t *cache)
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
		{
			new_task = pp_task_value_create(item->itemid, item->preproc, um_handle, NULL, ts, NULL,
					cache);
		}
		else
		{
			new_task = pp_task_value_seq_create(item->itemid, item->preproc, um_handle, NULL, ts,
					NULL, cache);
		}

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
 * Parameters: manager - [IN] manager                                         *
 *             task    - [IN] finished value task                             *
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

		d_dep->primary = pp_task_value_create(item->itemid, item->preproc, d->um_handle, &value, d->ts,
				NULL, d_dep->cache);

		pp_task_queue_push_immediate(&manager->queue, dep_task);
		pp_task_queue_notify(&manager->queue);
	}
	else
		pp_manager_queue_dependents(manager, d->preproc, d->um_handle, 0, &d->result, d->ts, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue new tasks in response to finished dependent task            *
 *                                                                            *
 * Parameters: manager - [IN] manager                                         *
 *             task    - [IN] finished dependent task                         *
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
	pp_manager_queue_dependents(manager, d->preproc, dp->um_handle, task_value->itemid, &dp->result, dp->ts, d->cache);

	d->primary = NULL;
	pp_task_free(task);

	return task_value;
}

/******************************************************************************
 *                                                                            *
 * Purpose: requeue sequence task                                             *
 *                                                                            *
 * Parameters: manager  - [IN] manager                                        *
 *             task_seq - [IN] finished sequence task                         *
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

/******************************************************************************
 *                                                                            *
 * Purpose: process finished tasks                                            *
 *                                                                            *
 * Parameters: manager        - [IN] manager                                  *
 *             tasks          - [OUT] finished tasks                          *
 *             pending_num    - [OUT] remaining pending tasks                 *
 *             processing_num - [OUT] processed tasks                         *
 *             finished_num   - [OUT] finished tasks                          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_pp_manager_process_finished(zbx_pp_manager_t *manager, zbx_vector_pp_task_ptr_t *tasks,
		zbx_uint64_t *pending_num, zbx_uint64_t *processing_num, zbx_uint64_t *finished_num)
{
#define PP_FINISHED_TASK_BATCH_SIZE	100

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

#undef PP_FINISHED_TASK_BATCH_SIZE
}

/******************************************************************************
 *                                                                            *
 * Purpose: dump cached item information into log                             *
 *                                                                            *
 * Parameters: manager - [IN] manager                                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_pp_manager_dump_items(zbx_pp_manager_t *manager)
{
	zbx_hashset_iter_t	iter;
	zbx_pp_item_t		*item;

	zbx_hashset_iter_reset(&manager->items, &iter);

	while (NULL != (item = (zbx_pp_item_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "itemid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " revision:" ZBX_FS_UI64
				" type:%u value_type:%u mode:%u flags:%u",
				item->itemid, item->preproc->hostid, item->revision, item->preproc->type,
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
 * Purpose: get item configuration data for reading and updates               *
 *                                                                            *
 ******************************************************************************/
zbx_hashset_t  *zbx_pp_manager_items(zbx_pp_manager_t *manager)
{
	return &manager->items;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get diagnostic statistics                                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_pp_manager_get_diag_stats(zbx_pp_manager_t *manager, zbx_uint64_t *preproc_num,
		zbx_uint64_t *pending_num, zbx_uint64_t *finished_num, zbx_uint64_t *sequences_num)
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
static void	zbx_pp_manager_get_sequence_stats(zbx_pp_manager_t *manager, zbx_vector_pp_top_stats_ptr_t *stats)
{
	pp_task_queue_get_sequence_stats(&manager->queue, stats);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get worker usage statistics                                       *
 *                                                                            *
 ******************************************************************************/
static void	zbx_pp_manager_get_worker_usage(zbx_pp_manager_t *manager, zbx_vector_dbl_t *worker_usage)
{
	(void)zbx_timekeeper_get_usage(manager->timekeeper, worker_usage);
}

/******************************************************************************
 *                                                                            *
 * Purpose: synchronize preprocessing manager with configuration cache data   *
 *                                                                            *
 * Parameters: manager - [IN] manager to be synchronized                      *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_sync_configuration(zbx_pp_manager_t *manager)
{
	zbx_uint64_t	old_revision, revision;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	old_revision = revision = manager->revision;
	zbx_dc_config_get_preprocessable_items(&manager->items, &manager->um_handle, &revision);
	manager->revision = revision;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE) && revision != old_revision)
		zbx_pp_manager_dump_items(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() item config size:%d revision:" ZBX_FS_UI64 "->" ZBX_FS_UI64, __func__,
			manager->items.num_data, old_revision, revision);
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
		zbx_free_agent_result(value->result);
		zbx_free(value->result);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: extract value, timestamp and optional data from preprocessing     *
 *          item value.                                                       *
 *                                                                            *
 * Parameters: value - [IN] preprocessing item value                          *
 *             var   - [OUT] extracted value (including error message)        *
 *             ts    - [OUT] extracted timestamp                              *
 *             opt   - [OUT] extracted optional data                          *
 *                                                                            *
 ******************************************************************************/
static void	preproc_item_value_extract_data(zbx_preproc_item_value_t *value, zbx_variant_t *var, zbx_timespec_t *ts,
		zbx_pp_value_opt_t *opt)
{
	opt->flags = ZBX_PP_VALUE_OPT_NONE;

	if (NULL != value->ts)
	{
		*ts = *value->ts;
	}
	else
	{
		ts->sec = 0;
		ts->ns = 0;
	}

	if (ITEM_STATE_NOTSUPPORTED == value->state)
	{
		if (NULL != value->error)
		{
			zbx_variant_set_error(var, value->error);
			value->error = NULL;
		}
		else if (NULL != value->result && ZBX_ISSET_MSG(value->result))
			zbx_variant_set_error(var, zbx_strdup(NULL, value->result->msg));
		else
			zbx_variant_set_error(var, zbx_strdup(NULL, "Unknown error."));

		return;
	}

	if (NULL == value->result)
	{
		zbx_variant_set_none(var);
		return;
	}

	if (ZBX_ISSET_LOG(value->result))
	{
		zbx_variant_set_str(var, value->result->log->value);
		value->result->log->value = NULL;

		opt->source = value->result->log->source;
		value->result->log->source = NULL;

		opt->logeventid = value->result->log->logeventid;
		opt->severity = value->result->log->severity;
		opt->timestamp = value->result->log->timestamp;

		opt->flags |= ZBX_PP_VALUE_OPT_LOG;
	}
	else if (ZBX_ISSET_UI64(value->result))
	{
		zbx_variant_set_ui64(var, value->result->ui64);
	}
	else if (ZBX_ISSET_DBL(value->result))
	{
		zbx_variant_set_dbl(var, value->result->dbl);
	}
	else if (ZBX_ISSET_STR(value->result))
	{
		zbx_variant_set_str(var, value->result->str);
		value->result->str = NULL;
	}
	else if (ZBX_ISSET_TEXT(value->result))
	{
		zbx_variant_set_str(var, value->result->text);
		value->result->text = NULL;
	}
	else if (ZBX_ISSET_BIN(value->result))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
	else
		zbx_variant_set_none(var);

	if (ZBX_ISSET_META(value->result))
	{
		opt->lastlogsize = value->result->lastlogsize;
		opt->mtime = value->result->mtime;

		opt->flags |= ZBX_PP_VALUE_OPT_META;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush preprocessed value                                          *
 *                                                                            *
 * Parameters: manager    - [IN] preprocessing manager                        *
 *             itemid     - [IN] item identifier                              *
 *             value_type - [IN] item value type                              *
 *             flags      - [IN] item flags                                   *
 *             value      - [IN] preprocessed item value                      *
 *             ts         - [IN] value timestamp                              *
 *             value_opt  - [IN] optional value data                          *
 *                                                                            *
 ******************************************************************************/
static void	preprocessing_flush_value(zbx_pp_manager_t *manager, zbx_uint64_t itemid, unsigned char value_type,
		unsigned char flags, zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_value_opt_t *value_opt)
{
	flush_value_func_cb(manager, itemid, value_type, flags, value, ts, value_opt);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle new preprocessing request                                  *
 *                                                                            *
 * Parameters: manager    - [IN] preprocessing manager                        *
 *             message    - [IN] packed preprocessing request                 *
 *             direct_num - [OUT] number of directly flushed values           *
 *                                                                            *
 *  Return value: The number of requests queued for preprocessing             *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	preprocessor_add_request(zbx_pp_manager_t *manager, zbx_ipc_message_t *message,
		zbx_uint64_t *direct_num)
{
	zbx_uint32_t			offset = 0;
	zbx_preproc_item_value_t	value;
	zbx_uint64_t			queued_num = 0;
	zbx_vector_pp_task_ptr_t	tasks;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_pp_task_ptr_create(&tasks);
	zbx_vector_pp_task_ptr_reserve(&tasks, ZBX_PREPROCESSING_BATCH_SIZE);

	preprocessor_sync_configuration(manager);

	while (offset < message->size)
	{
		zbx_variant_t		var;
		zbx_pp_value_opt_t	var_opt;
		zbx_timespec_t		ts;
		zbx_pp_task_t		*task;

		offset += zbx_preprocessor_unpack_value(&value, message->data + offset);
		preproc_item_value_extract_data(&value, &var, &ts, &var_opt);

		if (NULL == (task = zbx_pp_manager_create_task(manager, value.itemid, &var, ts, &var_opt)))
		{
			(*direct_num)++;
			/* allow empty values */
			preprocessing_flush_value(manager, value.itemid, value.item_value_type, value.item_flags,
					&var, ts, &var_opt);

			zbx_variant_clear(&var);
			zbx_pp_value_opt_clear(&var_opt);
		}
		else
			zbx_vector_pp_task_ptr_append(&tasks, task);

		preproc_item_value_clear(&value);
	}

	if (0 != tasks.values_num)
		zbx_pp_manager_queue_value_preproc(manager, &tasks);

	queued_num = tasks.values_num;
	zbx_vector_pp_task_ptr_destroy(&tasks);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return queued_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle new preprocessing test request                             *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] request source                                  *
 *             message - [IN] packed preprocessing request                    *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_add_test_request(zbx_pp_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_pp_item_preproc_t	*preproc;
	zbx_variant_t		value;
	zbx_timespec_t		ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	preproc = zbx_pp_item_preproc_create(0, 0, 0, 0);
	zbx_preprocessor_unpack_test_request(preproc, &value, &ts, message->data);
	zbx_pp_manager_queue_test(manager, preproc, &value, ts, client);
	zbx_pp_item_preproc_release(preproc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	preprocessor_reply_queue_size(zbx_pp_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_uint64_t	pending_num = manager->queue.pending_num;

	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_QUEUE, (unsigned char *)&pending_num, sizeof(pending_num));
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush processed value task                                        *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             tasks   - [IN] processed tasks                                 *
 *                                                                            *
 ******************************************************************************/
static void	prpeprocessor_flush_value_result(zbx_pp_manager_t *manager, zbx_pp_task_t *task)
{
	zbx_variant_t		*value;
	unsigned char		value_type, flags;
	zbx_timespec_t		ts;
	zbx_pp_value_opt_t	*value_opt;

	zbx_pp_value_task_get_data(task, &value_type, &flags, &value, &ts, &value_opt);

	if (SUCCEED == prepare_value_func_cb(value, value_opt))
		preprocessing_flush_value(manager, task->itemid, value_type, flags, value, ts, value_opt);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send back result of processed test task                           *
 *                                                                            *
 * Parameters: tasks - [IN] processed tasks                                   *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_reply_test_result(zbx_pp_task_t *task)
{
	unsigned char		*data;
	zbx_uint32_t		len;
	zbx_ipc_client_t	*client;
	zbx_variant_t		*result;
	zbx_pp_result_t		*results;
	int			results_num;
	zbx_pp_history_t	*history;

	zbx_pp_test_task_get_data(task, &client, &result, &results, &results_num, &history);

	len = zbx_preprocessor_pack_test_result(&data, results, results_num, history);

	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_TEST_RESULT, data, len);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush processed tasks                                             *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             tasks   - [IN] processed tasks                                 *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_flush_tasks(zbx_pp_manager_t *manager, zbx_vector_pp_task_ptr_t *tasks)
{
	for (int i = 0; i < tasks->values_num; i++)
	{
		switch (tasks->values[i]->type)
		{
			case ZBX_PP_TASK_VALUE:
			case ZBX_PP_TASK_VALUE_SEQ:	/* value and value_seq task contents are identical */
				prpeprocessor_flush_value_result(manager, tasks->values[i]);
				break;
			case ZBX_PP_TASK_TEST:
				preprocessor_reply_test_result(tasks->values[i]);
				break;
			default:
				/* the internal tasks (dependent/sequence) shouldn't get here */
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: respond to diagnostic information request                         *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] request source                                  *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_reply_diag_info(zbx_pp_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_uint64_t	preproc_num, pending_num, finished_num, sequences_num;
	unsigned char	*data;
	zbx_uint32_t	data_len;

	zbx_pp_manager_get_diag_stats(manager, &preproc_num, &pending_num, &finished_num, &sequences_num);
	data_len = zbx_preprocessor_pack_diag_stats(&data, preproc_num, pending_num, finished_num, sequences_num);

	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_DIAG_STATS_RESULT, data, data_len);

	zbx_free(data);
}

static int	preprocessor_compare_top_stats(const void *d1, const void *d2)
{
	const zbx_pp_top_stats_t *s1 = *(const zbx_pp_top_stats_t * const *)d1;
	const zbx_pp_top_stats_t *s2 = *(const zbx_pp_top_stats_t * const *)d2;

	return s2->tasks_num - s1->tasks_num;
}

static void	zbx_pp_manager_items_preproc_peak(zbx_pp_manager_t *manager, zbx_vector_pp_top_stats_ptr_t *stats)
{
	zbx_hashset_iter_t	iter;
	zbx_pp_item_t		*item;

	zbx_hashset_iter_reset(&manager->items, &iter);

	while (NULL != (item = (zbx_pp_item_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_pp_top_stats_t	*stat;

		if (NULL ==  item->preproc || 1 >= item->preproc->refcount_peak - 1)
			continue;

		stat = (zbx_pp_top_stats_t *)zbx_malloc(NULL, sizeof(zbx_pp_top_stats_t));
		stat->tasks_num =  item->preproc->refcount_peak - 1;
		stat->itemid = item->itemid;
		zbx_vector_pp_top_stats_ptr_append(stats, stat);
	}
}

static void	zbx_pp_manager_items_preproc_peak_reset(zbx_pp_manager_t *manager)
{
	zbx_hashset_iter_t	iter;
	zbx_pp_item_t		*item;

	zbx_hashset_iter_reset(&manager->items, &iter);

	while (NULL != (item = (zbx_pp_item_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL ==  item->preproc)
			continue;

		item->preproc->refcount_peak = 1;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: respond to top sequences request                                  *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             client  - [IN] request source                                  *
 *             message - [IN] request message                                 *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_reply_top_stats(zbx_pp_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message, zbx_uint32_t code)
{
	int				limit;
	zbx_vector_pp_top_stats_ptr_t	stats;
	unsigned char			*data;
	zbx_uint32_t			data_len;

	zbx_vector_pp_top_stats_ptr_create(&stats);

	zbx_preprocessor_unpack_top_request(&limit, message->data);

	if (ZBX_IPC_PREPROCESSOR_TOP_SEQUENCES == code)
		zbx_pp_manager_get_sequence_stats(manager, &stats);
	else
		zbx_pp_manager_items_preproc_peak(manager, &stats);

	if (limit > stats.values_num)
		limit = stats.values_num;

	zbx_vector_pp_top_stats_ptr_sort(&stats, preprocessor_compare_top_stats);

	data_len = zbx_preprocessor_pack_top_stats_result(&data, &stats, limit);

	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_TOP_STATS_RESULT, data, data_len);

	zbx_free(data);
	zbx_vector_pp_top_stats_ptr_clear_ext(&stats, (zbx_pp_top_stats_ptr_free_func_t)zbx_ptr_free);
	zbx_vector_pp_top_stats_ptr_destroy(&stats);
}

/******************************************************************************
 *                                                                            *
 * Purpose: respond to worker usage statistics request                        *
 *                                                                            *
 * Parameters: manager     - [IN] preprocessing manager                       *
 *             workers_num - [IN] number of preprocessing workers             *
 *             client      - [IN] request source                              *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_reply_usage_stats(zbx_pp_manager_t *manager, int workers_num, zbx_ipc_client_t *client)
{
	zbx_vector_dbl_t	usage;
	unsigned char		*data;
	zbx_uint32_t		data_len;

	zbx_vector_dbl_create(&usage);
	zbx_pp_manager_get_worker_usage(manager, &usage);

	data_len = zbx_preprocessor_pack_usage_stats(&data, &usage, workers_num);

	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_DIAG_STATS_RESULT, data, data_len);

	zbx_free(data);
	zbx_vector_dbl_destroy(&usage);
}

static void	preprocessor_finished_task_cb(void *data)
{
	zbx_ipc_service_alert((zbx_ipc_service_t *)data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: change worker log level                                           *
 *                                                                            *
 ******************************************************************************/
static void	pp_manager_change_worker_loglevel(zbx_pp_manager_t *manager, int worker_num, int direction)
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

/******************************************************************************
 *                                                                            *
 * Purpose: change log level for the specified worker(s)                      *
 *                                                                            *
 * Parameters: manager   - [IN] preprocessing manager                         *
 *             direction - [IN] 1) increase, -1) decrease                     *
 *             data      - [IN] rtc data in json format                       *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_change_loglevel(zbx_pp_manager_t *manager, int direction, const char *data)
{
	char	*error = NULL;
	pid_t	pid;
	int	proc_type, proc_num;

	if (SUCCEED != zbx_rtc_get_command_target(data, &pid, &proc_type, &proc_num, NULL, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot change log level: %s", error);
		zbx_free(error);
		return;
	}

	if (0 != pid)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot change log level for preprocessing worker by pid");
		return;
	}

	pp_manager_change_worker_loglevel(manager, proc_num, direction);
}

ZBX_THREAD_ENTRY(zbx_pp_manager_thread, args)
{
#define PP_MANAGER_DELAY_SEC	0
#define PP_MANAGER_DELAY_NS	5e8

	zbx_ipc_service_t			service;
	char					*error = NULL;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	double					time_stat, time_idle = 0, time_flush, time_vps_update, time_trim;
	zbx_timespec_t				timeout = {PP_MANAGER_DELAY_SEC, PP_MANAGER_DELAY_NS};
	const zbx_thread_info_t			*info = &((zbx_thread_args_t *)args)->info;
	int					server_num = ((zbx_thread_args_t *)args)->info.server_num,
						process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char				process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_thread_pp_manager_args		*pp_args = ((zbx_thread_args_t *)args)->args;
	zbx_pp_manager_t			*manager;
	zbx_vector_pp_task_ptr_t		tasks;
	zbx_uint32_t				rtc_msgs[] = {ZBX_RTC_LOG_LEVEL_INCREASE, ZBX_RTC_LOG_LEVEL_DECREASE};
	zbx_uint64_t				pending_num, finished_num, processed_num = 0, queued_num = 0,
						processing_num = 0;

	const zbx_thread_pp_manager_args	*pp_manager_args_in = (const zbx_thread_pp_manager_args *)
						(((zbx_thread_args_t *)args)->args);

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_PREPROCESSING, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start preprocessing service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (NULL == (manager = zbx_pp_manager_create(pp_args->workers_num, preprocessor_finished_task_cb,
			(void *)&service, pp_manager_args_in->config_source_ip, pp_manager_args_in->config_timeout,
			&error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize preprocessing manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	/* subscribe for worker log level rtc messages */
	zbx_rtc_subscribe_service(ZBX_PROCESS_TYPE_PREPROCESSOR, 0, rtc_msgs, ARRSIZE(rtc_msgs),
			pp_args->config_timeout, ZBX_IPC_SERVICE_PREPROCESSING);

	zbx_vector_pp_task_ptr_create(&tasks);

	/* initialize statistics */
	time_stat = zbx_time();
	time_flush = time_stat;
	time_vps_update = time_stat;
	time_trim = time_stat;

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		double		time_now = zbx_time();
		zbx_uint64_t	direct_num = 0;

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [queued " ZBX_FS_UI64 ", processed " ZBX_FS_UI64 " values, idle "
					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
					get_process_type_string(process_type), process_num,
					queued_num, processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
			queued_num = 0;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		int	ret = zbx_ipc_service_recv(&service, &timeout, &client, &message);

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		double	sec = zbx_time();

		zbx_update_env(get_process_type_string(process_type), sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_PREPROCESSOR_REQUEST:
					queued_num += preprocessor_add_request(manager, message, &direct_num);
					break;
				case ZBX_IPC_PREPROCESSOR_QUEUE:
					preprocessor_reply_queue_size(manager, client);
					break;
				case ZBX_IPC_PREPROCESSOR_TEST_REQUEST:
					preprocessor_add_test_request(manager, client, message);
					break;
				case ZBX_IPC_PREPROCESSOR_DIAG_STATS:
					preprocessor_reply_diag_info(manager, client);
					break;
				case ZBX_IPC_PREPROCESSOR_TOP_SEQUENCES:
				case ZBX_IPC_PREPROCESSOR_TOP_PEAK:
					preprocessor_reply_top_stats(manager, client, message, message->code);
					break;
				case ZBX_IPC_PREPROCESSOR_USAGE_STATS:
					preprocessor_reply_usage_stats(manager, pp_args->workers_num, client);
					break;
				case ZBX_RTC_LOG_LEVEL_INCREASE:
					preprocessor_change_loglevel(manager, 1, (const char *)message->data);
					break;
				case ZBX_RTC_LOG_LEVEL_DECREASE:
					preprocessor_change_loglevel(manager, -1, (const char *)message->data);
					break;
				case ZBX_RTC_SHUTDOWN:
					zabbix_log(LOG_LEVEL_DEBUG, "shutdown message received, terminating...");
					goto out;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		zbx_pp_manager_process_finished(manager, &tasks, &pending_num, &processing_num, &finished_num);

		if (0 < tasks.values_num)
		{
			processed_num += (unsigned int)tasks.values_num;
			preprocessor_flush_tasks(manager, &tasks);
			zbx_pp_tasks_clear(&tasks);
		}

		if (0 != finished_num || 0 != direct_num)
		{
			timeout.sec = 0;
			timeout.ns = 0;
		}
		else
		{
			timeout.sec = PP_MANAGER_DELAY_SEC;
			timeout.ns = PP_MANAGER_DELAY_NS;
		}

		if (0 == pending_num + processing_num + finished_num + direct_num || 1 < sec - time_flush)
		{
			if (0 != zbx_dc_flush_history())
			{
				zbx_rtc_notify_generic(&manager->rtc, ZBX_PROCESS_TYPE_HISTSYNCER, 1,
						ZBX_RTC_HISTORY_SYNC_NOTIFY, NULL, 0);
			}

			time_flush = sec;
		}

		/* trigger vps monitor update at least once per second */
		if (1 <= sec - time_vps_update)
		{
			zbx_vps_monitor_add_collected(0);
			time_vps_update = sec;
		}

		/* release memory in case of peak periods */
		if (SEC_PER_DAY <= sec - time_trim)
		{
#ifdef	HAVE_MALLOC_TRIM
			malloc_trim(128 * ZBX_MEBIBYTE);
#endif
			zbx_pp_manager_items_preproc_peak_reset(manager);
			time_trim = sec;
		}
	}
out:
	zbx_setproctitle("%s #%d [terminating]", get_process_type_string(process_type), process_num);

	zbx_vector_pp_task_ptr_destroy(&tasks);
	zbx_pp_manager_free(manager);

	zbx_ipc_service_close(&service);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
#undef PP_MANAGER_DELAY_SEC
#undef PP_MANAGER_DELAY_NS
}
