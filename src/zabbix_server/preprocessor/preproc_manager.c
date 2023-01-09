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

#include "zbxpreproc.h"
#include "preprocessing.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "log.h"
#include "zbxlld.h"
#include "zbxtime.h"
#include "zbxsysinfo.h"
#include "zbx_item_constants.h"
#include "zbxcachehistory.h"

extern unsigned char	program_type;

#define PP_MANAGER_DELAY_SEC	0
#define PP_MANAGER_DELAY_NS	5e8

/******************************************************************************
 *                                                                            *
 * Purpose: synchronize preprocessing manager with configuration cache data   *
 *                                                                            *
 * Parameters: manager - [IN] the manager to be synchronized                  *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_sync_configuration(zbx_pp_manager_t *manager)
{
	zbx_uint64_t	old_revision, revision;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	old_revision = revision = zbx_pp_manager_get_revision(manager);
	DCconfig_get_preprocessable_items(zbx_pp_manager_items(manager), &revision);
	zbx_pp_manager_set_revision(manager, revision);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE) && revision != old_revision)
		zbx_pp_manager_dump_items(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() item config size:%d revision:" ZBX_FS_UI64 "->" ZBX_FS_UI64, __func__,
			zbx_pp_manager_items(manager)->num_data, old_revision, revision);
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
 *             var   - [OUT] the extracted value (including error message)    *
 *             ts    - [OUT] the extracted timestamp                          *
 *             opt   - [OUT] the extracted optional data                      *
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
 * Parameters: manager    - [IN] the preprocessing manager                    *
 *             itemid     - [IN] the item identifier                          *
 *             value_type - [IN] the item value type                          *
 *             flags      - [IN] the item flags                               *
 *             value      - [IN] preprocessed item value                      *
 *             ts         - [IN] the value timestamp                          *
 *             value_opt  - [IN] the optional value data                      *
 *                                                                            *
 ******************************************************************************/
void	preprocessing_flush_value(zbx_pp_manager_t *manager, zbx_uint64_t itemid, unsigned char value_type,
		unsigned char flags, zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_value_opt_t *value_opt)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == (flags & ZBX_FLAG_DISCOVERY_RULE) || 0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		dc_add_history_variant(itemid, value_type, flags, value, ts, value_opt);
	}
	else
	{
		zbx_pp_item_t	*item;

		if (NULL != (item = (zbx_pp_item_t *)zbx_hashset_search(zbx_pp_manager_items(manager), &itemid)))
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
static void	preprocessor_add_request(zbx_pp_manager_t *manager, zbx_ipc_message_t *message)
{
	zbx_uint32_t			offset = 0;
	zbx_preproc_item_value_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	preprocessor_sync_configuration(manager);

	while (offset < message->size)
	{
		zbx_variant_t		var;
		zbx_pp_value_opt_t	var_opt;
		zbx_timespec_t		ts;

		offset += zbx_preprocessor_unpack_value(&value, message->data + offset);
		preproc_item_value_extract_data(&value, &var, &ts, &var_opt);

		if (SUCCEED != zbx_pp_manager_queue_preproc(manager, value.itemid, &var, ts, &var_opt))
		{
			preprocessing_flush_value(manager, value.itemid, value.item_value_type, value.item_flags,
					&var, ts, &var_opt);

			zbx_variant_clear(&var);
			zbx_pp_value_opt_clear(&var_opt);
		}

		preproc_item_value_clear(&value);
	}

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
static void	preprocessor_add_test_request(zbx_pp_manager_t *manager, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	zbx_pp_item_preproc_t	*preproc;
	zbx_variant_t		value;
	zbx_timespec_t		ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	preproc = zbx_pp_item_preproc_create(0, 0, 0);
	zbx_preprocessor_unpack_test_request(preproc, &value, &ts, message->data);
	zbx_pp_manager_queue_test(manager, preproc, &value, ts, client);
	zbx_pp_item_preproc_release(preproc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	preprocessor_reply_queue_size(zbx_pp_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_uint64_t	queued_num = zbx_pp_manager_get_queued_num(manager);

	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_QUEUE, (unsigned char *)&queued_num, sizeof(queued_num));
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush processed value task                                        *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             tasks   - [IN] the processed tasks                             *
 *                                                                            *
 ******************************************************************************/
static void	prpeprocessor_flush_value_result(zbx_pp_manager_t *manager, zbx_pp_task_t *task)
{
	zbx_variant_t		*value;
	unsigned char		value_type, flags;
	zbx_timespec_t		ts;
	zbx_pp_value_opt_t	*value_opt;

	zbx_pp_value_task_get_data(task, &value_type, &flags, &value, &ts, &value_opt);
	preprocessing_flush_value(manager, task->itemid, value_type, flags, value, ts, value_opt);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send back result of processed test task                           *
 *                                                                            *
 * Parameters: manager - [IN] preprocessing manager                           *
 *             tasks   - [IN] the processed tasks                             *
 *                                                                            *
 ******************************************************************************/
static void	prpeprocessor_reply_test_result(zbx_pp_task_t *task)
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
 *             tasks   - [IN] the processed tasks                             *
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
				prpeprocessor_reply_test_result(tasks->values[i]);
				break;
			default:
				/* the internal tasks (dependent/sequence) shouldn't get here */
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
}

ZBX_THREAD_ENTRY(preprocessing_manager_thread, args)
{
	zbx_ipc_service_t		service;
	char				*error = NULL;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	int				ret;
	double				time_stat, time_idle = 0, time_now, time_flush, sec;
	zbx_timespec_t			timeout = {PP_MANAGER_DELAY_SEC, PP_MANAGER_DELAY_NS};
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	const zbx_thread_preprocessing_manager_args	*pp_args = ((zbx_thread_args_t *)args)->args;
	zbx_pp_manager_t		*manager;
	zbx_vector_pp_task_ptr_t		tasks;

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

	if (NULL == (manager = zbx_pp_manager_create(pp_args->workers_num, &error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize preprocessing manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_vector_pp_task_ptr_create(&tasks);

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
					0, 0, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
		}

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
				case ZBX_IPC_PREPROCESSOR_REQUEST:
					preprocessor_add_request(manager, message);
					break;
				case ZBX_IPC_PREPROCESSOR_QUEUE:
					preprocessor_reply_queue_size(manager, client);
					break;
				case ZBX_IPC_PREPROCESSOR_TEST_REQUEST:
					preprocessor_add_test_request(manager, client, message);
					break;
				case ZBX_IPC_PREPROCESSOR_DIAG_STATS:
					//preprocessor_get_diag_stats(&manager, client);
					break;
				case ZBX_IPC_PREPROCESSOR_TOP_ITEMS:
					//preprocessor_get_top_items(&manager, client, message);
					break;
				case ZBX_IPC_PREPROCESSOR_TOP_OLDEST_PREPROC_ITEMS:
					//preprocessor_get_oldest_preproc_items(&manager, client, message);
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		zbx_pp_manager_process_finished(manager, &tasks);

		if (0 < tasks.values_num)
		{
			preprocessor_flush_tasks(manager, &tasks);
			zbx_pp_tasks_clear(&tasks);

			timeout.sec = 0;
			timeout.ns = 0;
		}
		else
		{
			timeout.sec = PP_MANAGER_DELAY_SEC;
			timeout.ns = PP_MANAGER_DELAY_NS;
		}

		if (0 == zbx_pp_manager_get_queued_num(manager) || 1 < time_now - time_flush)
		{
			dc_flush_history();
			time_flush = time_now;
		}
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	zbx_vector_pp_task_ptr_destroy(&tasks);
	zbx_pp_manager_free(manager);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_service_close(&service);

#undef STAT_INTERVAL
}
