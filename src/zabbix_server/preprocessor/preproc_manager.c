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

#include "pp_manager.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "log.h"
#include "zbxlld.h"
#include "preprocessing.h"
#include "preproc_manager.h"
#include "zbxtime.h"
#include "zbxsysinfo.h"
#include "zbx_item_constants.h"
#include "zbxcachehistory.h"

extern unsigned char			program_type;

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
	zbx_uint64_t	old_revision;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	old_revision = manager->revision;
	DCconfig_get_preprocessable_items(&manager->items, &manager->revision);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE) && old_revision != manager->revision)
		pp_manager_dump_items(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() item config size:%d revision:" ZBX_FS_UI64 "->" ZBX_FS_UI64, __func__,
			manager->items.num_data, old_revision, manager->revision);
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
		if (NULL != value->result && ZBX_ISSET_MSG(value->result))
		{
			zbx_variant_set_error(var, value->result->msg);
			value->result->msg = NULL;
		}
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
	{
		zbx_variant_set_error(var, zbx_strdup(NULL, "Unsupported incoming value type."));
		THIS_SHOULD_NEVER_HAPPEN;
	}

	if (ZBX_ISSET_META(value->result))
	{
		opt->lastlogsize = value->result->lastlogsize;
		opt->mtime = value->result->mtime;

		opt->flags |= ZBX_PP_VALUE_OPT_META;
	}
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

		if (SUCCEED != pp_manager_queue_preproc(manager, value.itemid, &var, ts, &var_opt))
		{
			pp_manager_flush_value(manager, value.itemid, value.item_value_type, value.item_flags,
					&var, ts, &var_opt);

			zbx_variant_clear(&var);
			pp_value_opt_clear(&var_opt);
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
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/*
	zbx_ipc_client_addref(client);
	direct_request = zbx_malloc(NULL, sizeof(zbx_preprocessing_direct_request_t));
	direct_request->client = client;
	zbx_ipc_message_copy(&direct_request->message, message);
	zbx_list_append(&manager->direct_queue, direct_request, NULL);

	preprocessor_assign_tasks(manager);
	preprocessing_flush_queue(manager);
	*/

	// TODO: create and queue test task

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	preprocessor_reply_queue_size(zbx_pp_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_ipc_client_send(client, ZBX_IPC_PREPROCESSOR_QUEUE, (unsigned char *)&manager->queue.queued_num,
			sizeof(zbx_uint64_t));

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
	zbx_pp_manager_t		manager;

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

	if (SUCCEED != pp_manager_init(&manager, program_type, pp_args->workers_num, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize preprocessing manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

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
					preprocessor_add_request(&manager, message);
					break;
				case ZBX_IPC_PREPROCESSOR_QUEUE:
					preprocessor_reply_queue_size(&manager, client);
					break;
				case ZBX_IPC_PREPROCESSOR_TEST_REQUEST:
					//preprocessor_add_test_request(&manager, client, message);
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

		pp_manager_process_finished(&manager);
		if (0 == manager.queue.queued_num || 1 < time_now - time_flush)
		{
			dc_flush_history();
			time_flush = time_now;
		}
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "destroy manager");
	pp_manager_destroy(&manager);

	zabbix_log(LOG_LEVEL_INFORMATION, "wait for finish");
	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_service_close(&service);

#undef STAT_INTERVAL
}
