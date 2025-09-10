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

#include "trigger_housekeeper.h"
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

static int	housekeep_problems_events(const zbx_vector_uint64_t *eventids, int events_mode)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	offset = 0;

	while (offset < eventids->values_num)
	{
		const zbx_uint64_t	*eventids_offset = eventids->values + offset;
		int			count = MIN(ZBX_DB_LARGE_QUERY_BATCH_SIZE, eventids->values_num - offset);
		int			txn_rc;

		sql_offset = 0;
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "cause_eventid", eventids_offset,
				count);

		zbx_db_execute("update problem set cause_eventid=null where%s", sql);
		zbx_db_execute("delete from event_symptom where%s", sql);

		sql_offset = 0;
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids_offset, count);

		do
		{
			zbx_db_begin();

			zbx_db_execute("delete from problem where%s", sql);

			if (ZBX_HK_OPTION_DISABLED != events_mode)
			{
				zbx_db_execute("delete from event_recovery where%s", sql);
				zbx_db_execute("delete from events where%s", sql);
			}
		}
		while (ZBX_DB_DOWN == (txn_rc = zbx_db_commit()));

		if (ZBX_DB_OK != txn_rc)
			break;

		offset += count;
	}

	housekeep_service_problems(eventids);
	zbx_free(sql);

	return offset;
}

int	zbx_housekeep_problems_events(const char *query, int config_max_hk_delete, int events_mode, int *more)
{
	zbx_vector_uint64_t	eventids;
	int			deleted = 0;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	zbx_vector_uint64_create(&eventids);

	if (0 == config_max_hk_delete)
		result = zbx_db_select(query);
	else
		result = zbx_db_select_n(query, config_max_hk_delete);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	eventid;

		ZBX_STR2UINT64(eventid, row[0]);
		zbx_vector_uint64_append(&eventids, eventid);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != eventids.values_num)
		deleted = housekeep_problems_events(&eventids, events_mode);

	zbx_vector_uint64_destroy(&eventids);

	if (0 != config_max_hk_delete && deleted >= config_max_hk_delete)
		*more = 1;

	return deleted;
}

static int	housekeep_problems_without_triggers(int config_max_hk_delete, int *more)
{
	int	deleted;
	char	query[MAX_STRING_LEN];

	zbx_snprintf(query, sizeof(query), "select eventid"
			" from problem"
			" where source=%d"
				" and object=%d"
				" and not exists (select NULL from triggers where triggerid=objectid) order by eventid",
				EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);

	deleted = zbx_housekeep_problems_events(query, config_max_hk_delete, ZBX_HK_OPTION_DISABLED, more);

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
		int	more = 0, deleted = housekeep_problems_without_triggers(0, &more);

		zbx_setproctitle("%s [deleted %d problems records in " ZBX_FS_DBL " sec, idle for %d second(s)]",
				get_process_type_string(process_type), deleted, zbx_time() - sec,
				trigger_housekeeper_args_in->config_problemhousekeeping_frequency);
	}

	zbx_ipc_async_socket_close(&rtc);

	zbx_db_close();

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
