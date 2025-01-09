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

#include "housekeeper_server.h"

#include "zbxtimekeeper.h"
#include "zbxthreads.h"
#include "zbxlog.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbxservice.h"
#include "zbxrtc.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxipcservice.h"
#include "zbxcacheconfig.h"

static void	housekeep_service_problems(const zbx_vector_uint64_t *eventids)
{
	unsigned char	*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;

	for (int i = 0; i < eventids->values_num; i++)
		zbx_service_serialize_id(&data, &data_alloc, &data_offset, eventids->values[i]);

	if (NULL == data)
		return;

	if (0 != zbx_dc_get_itservices_num())
		zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_PROBLEMS_DELETE, data, (zbx_uint32_t)data_offset);
	zbx_free(data);
}

static int	housekeep_problems_without_triggers(void)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	ids;
	int			deleted = 0;

	zbx_vector_uint64_create(&ids);

	result = zbx_db_select("select eventid"
			" from problem"
			" where source=%d"
				" and object=%d"
				" and not exists (select NULL from triggers where triggerid=objectid)",
				EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	id;

		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(&ids, id);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != ids.values_num)
	{
		if (SUCCEED != zbx_db_execute_multiple_query(
				"update problem"
				" set cause_eventid=null"
				" where", "cause_eventid", &ids))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Failed to unlink problem symptoms while housekeeping a cause"
					" problem without a trigger");
			goto fail;
		}

		if (SUCCEED != zbx_db_execute_multiple_query(
				"delete"
				" from event_symptom"
				" where", "cause_eventid", &ids))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Failed to unlink event symptoms while housekeeping a cause"
					" problem without a trigger");
			goto fail;
		}

		if (SUCCEED != zbx_db_execute_multiple_query(
				"delete"
				" from problem"
				" where", "eventid", &ids))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Failed to delete a problem without a trigger");
		}
		else
			deleted = ids.values_num;

		housekeep_service_problems(&ids);
	}
fail:
	zbx_vector_uint64_destroy(&ids);

	return deleted;
}

ZBX_THREAD_ENTRY(trigger_housekeeper_thread, args)
{
	zbx_ipc_async_socket_t	rtc;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_uint32_t		rtc_msgs[] = {ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE};

	zbx_thread_server_trigger_housekeeper_args	*trigger_housekeeper_args_in =
			(zbx_thread_server_trigger_housekeeper_args *) ((((zbx_thread_args_t *)args))->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	zbx_setproctitle("%s [startup idle for %d second(s)]", get_process_type_string(process_type),
			trigger_housekeeper_args_in->config_problemhousekeeping_frequency);

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, ARRSIZE(rtc_msgs),
			trigger_housekeeper_args_in->config_timeout, &rtc);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data,
				trigger_housekeeper_args_in->config_problemhousekeeping_frequency) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;

			if (ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE == rtc_cmd)
				zabbix_log(LOG_LEVEL_WARNING, "forced execution of the trigger housekeeper");
			else
				continue;
		}

		if (!ZBX_IS_RUNNING())
			break;

		zbx_update_env(get_process_type_string(process_type), zbx_time());

		zbx_setproctitle("%s [removing deleted triggers problems]", get_process_type_string(process_type));

		double	sec = zbx_time();
		int	deleted = housekeep_problems_without_triggers();

		zbx_setproctitle("%s [deleted %d problems records in " ZBX_FS_DBL " sec, idle for %d second(s)]",
				get_process_type_string(process_type), deleted, zbx_time() - sec,
				trigger_housekeeper_args_in->config_problemhousekeeping_frequency);
	}

	zbx_db_close();

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
