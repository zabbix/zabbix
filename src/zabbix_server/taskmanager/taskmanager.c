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

#include "taskmanager.h"

#include "../db_lengths.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "log.h"
#include "dbcache.h"
#include "zbxtasks.h"
#include "../events.h"
#include "../actions.h"
#include "zbxexport.h"
#include "zbxdiag.h"
#include "zbxservice.h"
#include "zbxjson.h"
#include "zbxrtc.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_proxy.h"
#include "dbcache.h"

#define ZBX_TM_PROCESS_PERIOD		5
#define ZBX_TM_CLEANUP_PERIOD		SEC_PER_HOUR
#define ZBX_TASKMANAGER_TIMEOUT		5

#define ZBX_TM_TEMP_SUPPRESION_ACTION_SUPPRESS		32
#define ZBX_TM_TEMP_SUPPRESION_ACTION_UNSUPPRESS	64
#define ZBX_TM_TEMP_SUPPRESION_INDEFINITE_TIME		0

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Purpose: close the specified problem event and remove task                 *
 *                                                                            *
 * Parameters: triggerid         - [IN] the source trigger id                 *
 *             eventid           - [IN] the problem eventid to close          *
 *             userid            - [IN] the user that requested to close the  *
 *                                      problem                               *
 *                                                                            *
 ******************************************************************************/
static void	tm_execute_task_close_problem(zbx_uint64_t taskid, zbx_uint64_t triggerid, zbx_uint64_t eventid,
		zbx_uint64_t userid)
{
	DB_RESULT	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64, __func__, eventid);

	result = DBselect("select null from problem where eventid=" ZBX_FS_UI64 " and r_eventid is null", eventid);

	/* check if the task hasn't been already closed by another process */
	if (NULL != DBfetch(result))
		zbx_close_problem(triggerid, eventid, userid);

	DBfree_result(result);

	DBexecute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE, taskid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: try to close problem by event acknowledgment action               *
 *                                                                            *
 * Parameters: taskid - [IN] the task identifier                              *
 *                                                                            *
 * Return value: SUCCEED - task was executed and removed                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_try_task_close_problem(zbx_uint64_t taskid)
{
	DB_ROW			row;
	DB_RESULT		result;
	int			ret = FAIL;
	zbx_uint64_t		userid, triggerid, eventid;
	zbx_vector_uint64_t	triggerids, locked_triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __func__, taskid);

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_create(&locked_triggerids);

	result = DBselect("select a.userid,a.eventid,e.objectid"
				" from task_close_problem tcp"
				" left join acknowledges a"
					" on tcp.acknowledgeid=a.acknowledgeid"
				" left join events e"
					" on a.eventid=e.eventid"
				" where tcp.taskid=" ZBX_FS_UI64,
			taskid);

	if (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED == DBis_null(row[0]))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process close problem task because related event"
					" was removed");
			DBexecute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE, taskid);

			ret = SUCCEED;
		}
		else
		{
			ZBX_STR2UINT64(triggerid, row[2]);
			zbx_vector_uint64_append(&triggerids, triggerid);
			DCconfig_lock_triggers_by_triggerids(&triggerids, &locked_triggerids);

			/* close the problem if source trigger was successfully locked or */
			/* if the trigger doesn't exist, but event still exists */
			if (0 != locked_triggerids.values_num)
			{
				ZBX_STR2UINT64(userid, row[0]);
				ZBX_STR2UINT64(eventid, row[1]);
				tm_execute_task_close_problem(taskid, triggerid, eventid, userid);

				DCconfig_unlock_triggers(&locked_triggerids);

				ret = SUCCEED;
			}
			else if (FAIL == DCconfig_trigger_exists(triggerid))
			{
				DBexecute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE,
						taskid);
				ret = SUCCEED;
			}
		}
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&locked_triggerids);
	zbx_vector_uint64_destroy(&triggerids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process expired remote command task                               *
 *                                                                            *
 ******************************************************************************/
static void	tm_expire_remote_command(zbx_uint64_t taskid)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	alertid;
	char		*error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __func__, taskid);

	DBbegin();

	result = DBselect("select alertid from task_remote_command where taskid=" ZBX_FS_UI64, taskid);

	if (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED != DBis_null(row[0]))
		{
			ZBX_STR2UINT64(alertid, row[0]);

			error = DBdyn_escape_string_len("Remote command has been expired.", ALERT_ERROR_LEN);
			DBexecute("update alerts set error='%s',status=%d where alertid=" ZBX_FS_UI64,
					error, ALERT_STATUS_FAILED, alertid);
			zbx_free(error);
		}
	}

	DBfree_result(result);

	DBexecute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_EXPIRED, taskid);

	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process remote command result task                                *
 *                                                                            *
 * Return value: SUCCEED - the task was processed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_remote_command_result(zbx_uint64_t taskid)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	alertid, parent_taskid = 0;
	int		status, ret = FAIL;
	char		*error, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __func__, taskid);

	DBbegin();

	result = DBselect("select r.status,r.info,a.alertid,r.parent_taskid"
			" from task_remote_command_result r"
			" left join task_remote_command c"
				" on c.taskid=r.parent_taskid"
			" left join alerts a"
				" on a.alertid=c.alertid"
			" where r.taskid=" ZBX_FS_UI64, taskid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(parent_taskid, row[3]);

		if (SUCCEED != DBis_null(row[2]))
		{
			ZBX_STR2UINT64(alertid, row[2]);
			status = atoi(row[0]);

			if (SUCCEED == status)
			{
				DBexecute("update alerts set status=%d where alertid=" ZBX_FS_UI64, ALERT_STATUS_SENT,
						alertid);
			}
			else
			{
				error = DBdyn_escape_string_len(row[1], ALERT_ERROR_LEN);
				DBexecute("update alerts set error='%s',status=%d where alertid=" ZBX_FS_UI64,
						error, ALERT_STATUS_FAILED, alertid);
				zbx_free(error);
			}
		}

		ret = SUCCEED;
	}

	DBfree_result(result);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_DONE, taskid);
	if (0 != parent_taskid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " or taskid=" ZBX_FS_UI64, parent_taskid);

	DBexecute("%s", sql);
	zbx_free(sql);

	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process data task result                                          *
 *                                                                            *
 ******************************************************************************/
static void	tm_process_data_result(zbx_uint64_t taskid)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	parent_taskid = 0;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __func__, taskid);

	DBbegin();

	result = DBselect("select parent_taskid"
			" from task_result"
			" where taskid=" ZBX_FS_UI64,
			taskid);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(parent_taskid, row[0]);

	DBfree_result(result);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_DONE, taskid);
	if (0 != parent_taskid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " or taskid=" ZBX_FS_UI64, parent_taskid);

	DBexecute("%s", sql);
	zbx_free(sql);

	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
/******************************************************************************
 *                                                                            *
 * Purpose: notify service manager about problem severity changes             *
 *                                                                            *
 ******************************************************************************/
static void	notify_service_manager(const zbx_vector_ptr_t *ack_tasks)
{
	int			i;
	zbx_vector_ptr_t	event_severities;

	zbx_vector_ptr_create(&event_severities);

	for (i = 0; i < ack_tasks->values_num; i++)
	{
		zbx_ack_task_t	*ack_task = (zbx_ack_task_t *)ack_tasks->values[i];

		if (ack_task->old_severity != ack_task->new_severity)
		{
			zbx_event_severity_t	*es;

			es = (zbx_event_severity_t *)zbx_malloc(NULL, sizeof(zbx_event_severity_t));
			es->eventid = ack_task->eventid;
			es->severity = ack_task->new_severity;
			zbx_vector_ptr_append(&event_severities, es);
		}
	}

	if (0 != event_severities.values_num)
	{
		unsigned char	*data;
		zbx_uint32_t	size;

		size = zbx_service_serialize_event_severities(&data, &event_severities);
		zbx_service_send(ZBX_IPC_SERVICE_EVENT_SEVERITIES, data, size, NULL);
		zbx_free(data);
	}

	zbx_vector_ptr_clear_ext(&event_severities, zbx_ptr_free);
	zbx_vector_ptr_destroy(&event_severities);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process acknowledgments for alerts sending                        *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_acknowledgments(zbx_vector_uint64_t *ack_taskids)
{
	DB_ROW			row;
	DB_RESULT		result;
	int			processed_num = 0;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_ptr_t	ack_tasks;
	zbx_ack_task_t		*ack_task;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __func__, ack_taskids->values_num);

	zbx_vector_uint64_sort(ack_taskids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&ack_tasks);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select a.eventid,ta.acknowledgeid,ta.taskid,a.old_severity,a.new_severity"
			" from task_acknowledge ta"
			" left join acknowledges a"
				" on ta.acknowledgeid=a.acknowledgeid"
			" left join events e"
				" on a.eventid=e.eventid"
			" left join task t"
				" on ta.taskid=t.taskid"
			" where t.status=%d and",
			ZBX_TM_STATUS_NEW);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.taskid", ack_taskids->values, ack_taskids->values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED == DBis_null(row[0]))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process acknowledge tasks because related event"
					" was removed");
			continue;
		}

		ack_task = (zbx_ack_task_t *)zbx_malloc(NULL, sizeof(zbx_ack_task_t));

		ZBX_STR2UINT64(ack_task->eventid, row[0]);
		ZBX_STR2UINT64(ack_task->acknowledgeid, row[1]);
		ZBX_STR2UINT64(ack_task->taskid, row[2]);
		ack_task->old_severity = atoi(row[3]);
		ack_task->new_severity = atoi(row[4]);
		zbx_vector_ptr_append(&ack_tasks, ack_task);
	}
	DBfree_result(result);

	if (0 < ack_tasks.values_num)
	{
		zbx_vector_ptr_sort(&ack_tasks, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		processed_num = process_actions_by_acknowledgments(&ack_tasks);

		notify_service_manager(&ack_tasks);
	}

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset , "update task set status=%d where", ZBX_TM_STATUS_DONE);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", ack_taskids->values, ack_taskids->values_num);
	DBexecute("%s", sql);

	zbx_free(sql);

	zbx_vector_ptr_clear_ext(&ack_tasks, zbx_ptr_free);
	zbx_vector_ptr_destroy(&ack_tasks);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process check now tasks for item rescheduling                     *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_check_now(zbx_vector_uint64_t *taskids)
{
	DB_ROW			row;
	DB_RESULT		result;
	int			i, processed_num = 0;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_ptr_t	tasks;
	zbx_vector_uint64_t	done_taskids, itemids;
	zbx_uint64_t		taskid, itemid, proxy_hostid, *proxy_hostids;
	zbx_tm_task_t		*task;
	zbx_tm_check_now_t	*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __func__, taskids->values_num);

	zbx_vector_ptr_create(&tasks);
	zbx_vector_uint64_create(&done_taskids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.taskid,t.status,t.proxy_hostid,td.itemid"
			" from task t"
			" left join task_check_now td"
				" on t.taskid=td.taskid"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.taskid", taskids->values, taskids->values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(taskid, row[0]);

		if (SUCCEED == DBis_null(row[3]))
		{
			zbx_vector_uint64_append(&done_taskids, taskid);
			continue;
		}

		ZBX_DBROW2UINT64(proxy_hostid, row[2]);
		if (0 != proxy_hostid)
		{
			if (ZBX_TM_STATUS_INPROGRESS == atoi(row[1]))
			{
				/* task has been sent to proxy, mark as done */
				zbx_vector_uint64_append(&done_taskids, taskid);
				continue;
			}
		}

		ZBX_STR2UINT64(itemid, row[3]);

		/* zbx_task_t here is used only to store taskid, proxyhostid, data->itemid - */
		/* the rest of task properties are not used                                  */
		task = zbx_tm_task_create(taskid, ZBX_TM_TASK_CHECK_NOW, 0, 0, 0, proxy_hostid);
		task->data = (void *)zbx_tm_check_now_create(itemid);
		zbx_vector_ptr_append(&tasks, task);
	}
	DBfree_result(result);

	if (0 != tasks.values_num)
	{
		zbx_vector_uint64_create(&itemids);

		for (i = 0; i < tasks.values_num; i++)
		{
			task = (zbx_tm_task_t *)tasks.values[i];
			data = (zbx_tm_check_now_t *)task->data;
			zbx_vector_uint64_append(&itemids, data->itemid);
		}

		proxy_hostids = (zbx_uint64_t *)zbx_malloc(NULL, tasks.values_num * sizeof(zbx_uint64_t));
		zbx_dc_reschedule_items(&itemids, time(NULL), proxy_hostids);

		sql_offset = 0;
		zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < tasks.values_num; i++)
		{
			task = (zbx_tm_task_t *)tasks.values[i];

			if (0 != proxy_hostids[i] && task->proxy_hostid == proxy_hostids[i])
				continue;

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset , "update task set");

			if (0 == proxy_hostids[i])
			{
				/* close tasks managed by server -                  */
				/* items either have been rescheduled or not cached */
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " status=%d", ZBX_TM_STATUS_DONE);
				if (0 != task->proxy_hostid)
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",proxy_hostid=null");

				processed_num++;
			}
			else
			{
				/* update target proxy hostid */
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " proxy_hostid=" ZBX_FS_UI64,
						proxy_hostids[i]);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where taskid=" ZBX_FS_UI64 ";\n",
					task->taskid);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)	/* in ORACLE always present begin..end; */
			DBexecute("%s", sql);

		zbx_vector_uint64_destroy(&itemids);
		zbx_free(proxy_hostids);

		zbx_vector_ptr_clear_ext(&tasks, (zbx_clean_func_t)zbx_tm_task_free);
	}

	if (0 != done_taskids.values_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where",
				ZBX_TM_STATUS_DONE);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", done_taskids.values,
				done_taskids.values_num);
		DBexecute("%s", sql);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&done_taskids);
	zbx_vector_ptr_destroy(&tasks);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process diaginfo task                                             *
 *                                                                            *
 ******************************************************************************/
static void	tm_process_diaginfo(zbx_uint64_t taskid, const char *data)
{
	zbx_tm_task_t		*task;
	int			ret;
	char			*info = NULL;
	struct zbx_json_parse	jp_data;

	task = zbx_tm_task_create(0, ZBX_TM_TASK_DATA_RESULT, ZBX_TM_STATUS_NEW, time(NULL), 0, 0);

	if (SUCCEED == zbx_json_open(data, &jp_data))
	{
		ret = zbx_diag_get_info(&jp_data, &info);
		task->data = zbx_tm_data_result_create(taskid, ret, info);
		zbx_free(info);
	}
	else
		task->data = zbx_tm_data_result_create(taskid, FAIL, zbx_json_strerror());

	zbx_tm_save_task(task);
	zbx_tm_task_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create config cache reload task to be sent to active proxy        *
 *                                                                            *
 ******************************************************************************/
static zbx_tm_task_t	*tm_create_active_proxy_reload_task(zbx_uint64_t proxyid)
{
	zbx_tm_task_t	*task;

	task = zbx_tm_task_create(0, ZBX_TM_TASK_DATA, ZBX_TM_STATUS_NEW, (int)time(NULL),
			ZBX_DATA_ACTIVE_PROXY_CONFIG_RELOAD_TTL, proxyid);

	task->data = zbx_tm_data_create(0, "", 0, ZBX_TM_DATA_TYPE_ACTIVE_PROXY_CONFIG_RELOAD);

	return task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process task for reload of configuration cache on proxies         *
 *                                                                            *
 * Parameters: rtc    - [IN] the RTC service                                  *
 *             data   - [IN] the JSON with request                            *
 *                                                                            *
 ******************************************************************************/
static void	tm_process_proxy_config_reload_task(zbx_ipc_async_socket_t *rtc, const char *data)
{
	struct zbx_json_parse	jp, jp_data;
	const char		*ptr;
	char			buffer[MAX_ID_LEN + 1];
	int			passive_proxy_count = 0;
	zbx_vector_ptr_t	tasks_active;
	zbx_vector_str_t	proxynames_log;

	if (FAIL == zbx_json_open(data, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse proxy config cache reload task data");
		return;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_PROXY_HOSTIDS, &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse proxy config cache reload task data: field "
				ZBX_PROTO_TAG_PROXY_HOSTIDS " not found");
		return;
	}

	zbx_vector_ptr_create(&tasks_active);
	zbx_vector_str_create(&proxynames_log);

	for (ptr = NULL; NULL != (ptr = zbx_json_next(&jp_data, ptr));)
	{
		if (NULL != zbx_json_decodevalue(ptr, buffer, sizeof(buffer), NULL))
		{
			zbx_uint64_t	proxyid;
			int		type;
			char		*name;

			ZBX_STR2UINT64(proxyid, buffer);

			if (FAIL == zbx_dc_get_proxy_name_type_by_id(proxyid, &type, &name))
			{
				zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache "
						"for proxy " ZBX_FS_UI64 ": proxy is not in cache", proxyid);
				continue;
			}

			if (HOST_STATUS_PROXY_ACTIVE == type)
			{
				zbx_tm_task_t	*task;

				task = tm_create_active_proxy_reload_task(proxyid);
				zbx_vector_ptr_append(&tasks_active, task);
				zbx_vector_str_append(&proxynames_log, name);
			}
			else if (HOST_STATUS_PROXY_PASSIVE == type)
			{
				if (FAIL == zbx_dc_update_passive_proxy_nextcheck(proxyid))
				{
					zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache on proxy "
							"with id " ZBX_FS_UI64 ": failed to update nextcheck", proxyid);
					zbx_free(name);
				}
				else
				{
					passive_proxy_count++;
					zbx_vector_str_append(&proxynames_log, name);
				}
			}
			else
			{
				zbx_free(name);
				THIS_SHOULD_NEVER_HAPPEN;
			}
		}
	}

	if (1 == proxynames_log.values_num)
	{
		zabbix_log(LOG_LEVEL_WARNING, "reloading configuration on proxy \"%s\"", proxynames_log.values[0]);
	}
	else if (1 < proxynames_log.values_num)
	{
		int	i = 0;
		char	*names_success = NULL;

		while (1)
		{
			if (i + 1 == proxynames_log.values_num)
			{
				names_success = zbx_strdcatf(names_success, "\"%s\"", proxynames_log.values[i]);
				break;
			}
			else
			{
				names_success = zbx_strdcatf(names_success, "\"%s\", ", proxynames_log.values[i]);
				i++;
			}
		}

		zabbix_log(LOG_LEVEL_WARNING, "reloading configuration on proxies %s", names_success);
		zbx_free(names_success);
	}

	if (0 < tasks_active.values_num)
	{
		zbx_tm_save_tasks(&tasks_active);
		zbx_vector_ptr_clear_ext(&tasks_active, (zbx_clean_func_t)zbx_tm_task_free);
	}

	zbx_vector_str_clear_ext(&proxynames_log, zbx_str_free);
	zbx_vector_str_destroy(&proxynames_log);
	zbx_vector_ptr_destroy(&tasks_active);

	if (passive_proxy_count > 0)
		zbx_ipc_async_socket_send(rtc, ZBX_RTC_PROXYPOLLER_PROCESS, NULL, 0);
}


/******************************************************************************
 *                                                                            *
 * Purpose: process task for reload of configuration cache on passive proxy   *
 *          (received from that passive proxy)                                *
 *                                                                            *
 * Parameters: rtc    - [IN] the RTC service                                  *
 *             data   - [IN] the JSON with request                            *
 *                                                                            *
 ******************************************************************************/
static void	tm_process_passive_proxy_cache_reload_request(zbx_ipc_async_socket_t *rtc, const char *data)
{
	struct zbx_json_parse	jp;
	char			hostname[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	zbx_uint64_t		proxyid;

	if (FAIL == zbx_json_open(data, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse passive proxy config cache reload request");
		return;
	}

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROXY_NAME, hostname, sizeof(hostname), NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "broken passive proxy config cache reload request was received");
		return;
	}

	if (FAIL == zbx_dc_get_proxyid_by_name(hostname, &proxyid, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache on proxy '%s': proxy is not in "
				"cache", hostname);
		return;
	}

	if (FAIL == zbx_dc_update_passive_proxy_nextcheck(proxyid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache on proxy "
				"with id " ZBX_FS_UI64 ": failed to update nextcheck", proxyid);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "reloading configuration cache on proxy '%s'", hostname);
		zbx_ipc_async_socket_send(rtc, ZBX_RTC_PROXYPOLLER_PROCESS, NULL, 0);
	}

	zbx_audit_prepare();
	zbx_audit_proxy_config_reload(proxyid, hostname);
	zbx_audit_flush();
}

static void	tm_process_temp_suppression(const char *data)
{
	struct zbx_json_parse	jp;
	char			tmp_eventid[MAX_ID_LEN], tmp_userid[MAX_ID_LEN], tmp_ts[MAX_ID_LEN], tmp_action[12];
	zbx_uint64_t		eventid, userid, action;
	unsigned int		ts;

	if (FAIL == zbx_json_open(data, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse temporary suppression data request");
		return;
	}

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_EVENTID, tmp_eventid, sizeof(tmp_eventid), NULL) ||
			FAIL == is_uint64(tmp_eventid, &eventid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse temporary suppression data request: failed to retrieve "
				" \"%s\" tag", ZBX_PROTO_TAG_EVENTID);
		return;
	}

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_USERID, tmp_userid, sizeof(tmp_userid), NULL) ||
			FAIL == is_uint64(tmp_userid, &userid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse temporary suppression data request: failed to retrieve "
				" \"%s\" tag", ZBX_PROTO_TAG_USERID);
		return;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_ACTION, tmp_action, sizeof(tmp_action), NULL))
	{
		if (0 == strcmp(ZBX_PROTO_VALUE_SUPPRESSION_SUPPRESS, tmp_action))
		{
			action = ZBX_TM_TEMP_SUPPRESION_ACTION_SUPPRESS;
		}
		else if (0 == strcmp(ZBX_PROTO_VALUE_SUPPRESSION_UNSUPPRESS, tmp_action))
		{
			action = ZBX_TM_TEMP_SUPPRESION_ACTION_UNSUPPRESS;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to parse temporary suppression data request: failed to "
				"retrieve \"%s\" tag's value '%s'", ZBX_PROTO_TAG_ACTION, tmp_action);

			return;
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse temporary suppression data request: failed to retrieve "
				" \"%s\" tag", ZBX_PROTO_TAG_ACTION);
		return;
	}

	if (ZBX_TM_TEMP_SUPPRESION_ACTION_SUPPRESS == action)
	{
		if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_SUPPRESS_UNTIL, tmp_ts, sizeof(tmp_ts), NULL) ||
				FAIL == is_uint32(tmp_ts, &ts))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to parse temporary suppression data request: failed to retrieve "
					" \"%s\" tag", ZBX_PROTO_TAG_SUPPRESS_UNTIL);
			return;
		}

	}

	if (SUCCEED != DBlock_record("users", userid, NULL, 0))
		return;

	if (ZBX_TM_TEMP_SUPPRESION_ACTION_UNSUPPRESS == action ||
			(ZBX_TM_TEMP_SUPPRESION_INDEFINITE_TIME != ts && time(NULL) >= ts))
	{
		DBexecute("delete from event_suppress where eventid=" ZBX_FS_UI64 " and maintenanceid is null",
				eventid);
	}
	else if (ZBX_TM_TEMP_SUPPRESION_ACTION_SUPPRESS == action)
	{
		DB_ROW		row;
		DB_RESULT	result;

		if (SUCCEED != DBlock_record("events", eventid, NULL, 0))
			return;

		result = DBselect("select event_suppressid,suppress_until from event_suppress where eventid="
				ZBX_FS_UI64 " and maintenanceid is null" ZBX_FOR_UPDATE, eventid);

		if (NULL != (row = DBfetch(result)))
		{
			DBexecute("update event_suppress set suppress_until=%u,userid=" ZBX_FS_UI64
					" where event_suppressid=%s", ts, userid, row[0]);
		}
		else
		{
			zbx_db_insert_t	db_insert;

			zbx_db_insert_prepare(&db_insert, "event_suppress", "event_suppressid", "eventid",
					"suppress_until", "userid", NULL);
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), eventid, ts, userid);

			zbx_db_insert_autoincrement(&db_insert, "event_suppressid");
			zbx_db_insert_execute(&db_insert);
			zbx_db_insert_clean(&db_insert);
		}

		DBfree_result(result);
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process data tasks                                                *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_data(zbx_ipc_async_socket_t *rtc, zbx_vector_uint64_t *taskids)
{
	DB_ROW			row;
	DB_RESULT		result;
	int			processed_num = 0, data_type;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	done_taskids;
	zbx_uint64_t		taskid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __func__, taskids->values_num);

	DBbegin();

	zbx_vector_uint64_create(&done_taskids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.taskid,td.type,td.data"
			" from task t"
			" left join task_data td"
				" on t.taskid=td.taskid"
			" where t.proxy_hostid is null"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.taskid", taskids->values, taskids->values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(taskid, row[0]);

		if (SUCCEED == DBis_null(row[1]))
		{
			zbx_vector_uint64_append(&done_taskids, taskid);
			continue;
		}

		data_type = atoi(row[1]);

		switch (data_type)
		{
			case ZBX_TM_DATA_TYPE_DIAGINFO:
				tm_process_diaginfo(taskid, row[2]);
				zbx_vector_uint64_append(&done_taskids, taskid);
				break;
			case ZBX_TM_DATA_TYPE_PROXY_HOSTIDS:
				tm_process_proxy_config_reload_task(rtc, row[2]);
				zbx_vector_uint64_append(&done_taskids, taskid);
				break;
			case ZBX_TM_DATA_TYPE_PROXY_HOSTNAME:
				tm_process_passive_proxy_cache_reload_request(rtc, row[2]);
				zbx_vector_uint64_append(&done_taskids, taskid);
				break;
			case ZBX_TM_DATA_TYPE_TEMP_SUPPRESSION:
				tm_process_temp_suppression(row[2]);
				zbx_vector_uint64_append(&done_taskids, taskid);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
	DBfree_result(result);

	if (0 != (processed_num = done_taskids.values_num))
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where",
				ZBX_TM_STATUS_DONE);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", done_taskids.values,
				done_taskids.values_num);
		DBexecute("%s", sql);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&done_taskids);

	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: expires tasks that don't require specific expiration handling     *
 *                                                                            *
 * Return value: The number of successfully expired tasks                     *
 *                                                                            *
 ******************************************************************************/
static int	tm_expire_generic_tasks(zbx_vector_uint64_t *taskids)
{
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where", ZBX_TM_STATUS_EXPIRED);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", taskids->values, taskids->values_num);
	DBexecute("%s", sql);
	zbx_free(sql);

	return taskids->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process task manager tasks depending on task type                 *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_tasks(zbx_ipc_async_socket_t *rtc, int now)
{
	DB_ROW			row;
	DB_RESULT		result;
	int			type, processed_num = 0, expired_num = 0, clock, ttl;
	zbx_uint64_t		taskid;
	zbx_vector_uint64_t	ack_taskids, check_now_taskids, expire_taskids, data_taskids;

	zbx_vector_uint64_create(&ack_taskids);
	zbx_vector_uint64_create(&check_now_taskids);
	zbx_vector_uint64_create(&expire_taskids);
	zbx_vector_uint64_create(&data_taskids);

	result = DBselect("select taskid,type,clock,ttl"
				" from task"
				" where status in (%d,%d)"
				" order by taskid",
			ZBX_TM_STATUS_NEW, ZBX_TM_STATUS_INPROGRESS);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(taskid, row[0]);
		ZBX_STR2UCHAR(type, row[1]);
		clock = atoi(row[2]);
		ttl = atoi(row[3]);

		switch (type)
		{
			case ZBX_TM_TASK_CLOSE_PROBLEM:
				/* close problem tasks will never have 'in progress' status */
				if (SUCCEED == tm_try_task_close_problem(taskid))
					processed_num++;
				break;
			case ZBX_TM_TASK_REMOTE_COMMAND:
				/* both - 'new' and 'in progress' remote tasks should expire */
				if (0 != ttl && clock + ttl < now)
				{
					tm_expire_remote_command(taskid);
					expired_num++;
				}
				break;
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				/* close problem tasks will never have 'in progress' status */
				if (SUCCEED == tm_process_remote_command_result(taskid))
					processed_num++;
				break;
			case ZBX_TM_TASK_ACKNOWLEDGE:
				zbx_vector_uint64_append(&ack_taskids, taskid);
				break;
			case ZBX_TM_TASK_CHECK_NOW:
				if (0 != ttl && clock + ttl < now)
					zbx_vector_uint64_append(&expire_taskids, taskid);
				else
					zbx_vector_uint64_append(&check_now_taskids, taskid);
				break;
			case ZBX_TM_TASK_DATA:
			case ZBX_TM_PROXYDATA:
				/* both - 'new' and 'in progress' tasks should expire */
				if (0 != ttl && clock + ttl < now)
					zbx_vector_uint64_append(&expire_taskids, taskid);
				else
					zbx_vector_uint64_append(&data_taskids, taskid);
				break;
			case ZBX_TM_TASK_DATA_RESULT:
				tm_process_data_result(taskid);
				processed_num++;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}

	}
	DBfree_result(result);

	if (0 < ack_taskids.values_num)
		processed_num += tm_process_acknowledgments(&ack_taskids);

	if (0 < check_now_taskids.values_num)
		processed_num += tm_process_check_now(&check_now_taskids);

	if (0 < data_taskids.values_num)
		processed_num += tm_process_data(rtc, &data_taskids);

	if (0 < expire_taskids.values_num)
		expired_num += tm_expire_generic_tasks(&expire_taskids);

	zbx_vector_uint64_destroy(&data_taskids);
	zbx_vector_uint64_destroy(&expire_taskids);
	zbx_vector_uint64_destroy(&check_now_taskids);
	zbx_vector_uint64_destroy(&ack_taskids);

	return processed_num + expired_num;
}

static void	zbx_cached_proxy_free(zbx_cached_proxy_t *proxy)
{
	zbx_free(proxy->name);
	zbx_free(proxy);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove old done/expired tasks                                     *
 *                                                                            *
 ******************************************************************************/
static void	tm_remove_old_tasks(int now)
{
	DBbegin();
	DBexecute("delete from task where status in (%d,%d) and clock<=%d",
			ZBX_TM_STATUS_DONE, ZBX_TM_STATUS_EXPIRED, now - ZBX_TM_CLEANUP_TASK_AGE);
	DBcommit();
}

static void	tm_reload_each_proxy_cache(zbx_ipc_async_socket_t *rtc)
{
	int				i, notify_proxypollers = 0;
	zbx_vector_cached_proxy_t	proxies;
	zbx_vector_ptr_t		tasks_active;

	zbx_vector_cached_proxy_create(&proxies);

	zbx_vector_ptr_create(&tasks_active);

	zbx_dc_get_all_proxies(&proxies);

	zabbix_log(LOG_LEVEL_WARNING, "reloading configuration cache on all proxies");

	zbx_audit_prepare();

	for (i = 0; i < proxies.values_num; i++)
	{
		zbx_tm_task_t		*task;
		zbx_cached_proxy_t	*proxy;

		proxy = proxies.values[i];

		if (HOST_STATUS_PROXY_ACTIVE == proxy->status)
		{
			task = tm_create_active_proxy_reload_task(proxy->hostid);
			zbx_vector_ptr_append(&tasks_active, task);
		}
		else if (HOST_STATUS_PROXY_PASSIVE == proxy->status)
		{
			if (FAIL == zbx_dc_update_passive_proxy_nextcheck(proxy->hostid))
			{
				zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache on proxy "
						"with id " ZBX_FS_UI64 " [%s]: failed to update nextcheck",
						proxy->hostid, proxy->name);
			}
			else
				notify_proxypollers = 1;
		}

		zbx_audit_proxy_config_reload(proxy->hostid, proxy->name);
	}

	zbx_audit_flush();

	if (0 != notify_proxypollers)
		zbx_ipc_async_socket_send(rtc, ZBX_RTC_PROXYPOLLER_PROCESS, NULL, 0);

	if (0 < tasks_active.values_num)
	{
		DBbegin();
		zbx_tm_save_tasks(&tasks_active);
		DBcommit();
		zbx_vector_ptr_clear_ext(&tasks_active, (zbx_clean_func_t)zbx_tm_task_free);
	}

	zbx_vector_ptr_destroy(&tasks_active);

	zbx_vector_cached_proxy_clear_ext(&proxies, zbx_cached_proxy_free);
	zbx_vector_cached_proxy_destroy(&proxies);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reload configuration cache on proxies using given proxy names     *
 *                                                                            *
 * Parameters: rtc    - [IN] the RTC service                                  *
 *             data   - [IN] the JSON with request                            *
 *                                                                            *
 ******************************************************************************/
static void	tm_reload_proxy_cache_by_names(zbx_ipc_async_socket_t *rtc, const unsigned char *data)
{
	struct zbx_json_parse	jp, jp_data;
	const char		*ptr;
	char			name[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	zbx_vector_ptr_t	tasks_active;
	zbx_vector_str_t	proxynames_log;
	char			*names_success = NULL;

	zbx_vector_str_create(&proxynames_log);

	if (FAIL == zbx_json_open((const char *)data, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse proxy config cache reload data");
		return;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_PROXY_NAMES, &jp_data))
	{
		tm_reload_each_proxy_cache(rtc);
		return;
	}

	zbx_vector_ptr_create(&tasks_active);

	zbx_audit_prepare();

	for (ptr = NULL; NULL != (ptr = zbx_json_next(&jp_data, ptr));)
	{
		if (NULL != zbx_json_decodevalue(ptr, name, sizeof(name), NULL))
		{
			zbx_uint64_t	proxyid;
			unsigned char	type;

			if (FAIL == zbx_dc_get_proxyid_by_name(name, &proxyid, &type))
			{
				zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache "
						"on proxy '%s': proxy is not in cache", name);
				continue;
			}

			if (HOST_STATUS_PROXY_ACTIVE == type)
			{
				zbx_tm_task_t	*task;

				task = tm_create_active_proxy_reload_task(proxyid);
				zbx_vector_ptr_append(&tasks_active, task);
				zbx_vector_str_append(&proxynames_log, zbx_strdup(NULL, name));
			}
			else if (HOST_STATUS_PROXY_PASSIVE == type)
			{
				if (FAIL == zbx_dc_update_passive_proxy_nextcheck(proxyid))
				{
					zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache on proxy "
							"with id " ZBX_FS_UI64 ": failed to update nextcheck", proxyid);
				}
				else
					zbx_vector_str_append(&proxynames_log, zbx_strdup(NULL, name));
			}

			zbx_audit_proxy_config_reload(proxyid, name);
		}
	}

	zbx_audit_flush();

	if (1 == proxynames_log.values_num)
	{
		zabbix_log(LOG_LEVEL_WARNING, "reloading configuration on proxy \"%s\"", proxynames_log.values[0]);
	}
	else if (1 < proxynames_log.values_num)
	{
		int	i = 0;

		while (1)
		{
			if (i + 1 == proxynames_log.values_num)
			{
				names_success = zbx_strdcatf(names_success, "\"%s\"", proxynames_log.values[i]);
				break;
			}
			else
			{
				names_success = zbx_strdcatf(names_success, "\"%s\", ", proxynames_log.values[i]);
				i++;
			}
		}

		zabbix_log(LOG_LEVEL_WARNING, "reloading configuration on proxies %s", names_success);
		zbx_free(names_success);

		zbx_ipc_async_socket_send(rtc, ZBX_RTC_PROXYPOLLER_PROCESS, NULL, 0);
	}

	zbx_vector_str_clear_ext(&proxynames_log, zbx_str_free);
	zbx_vector_str_destroy(&proxynames_log);

	if (0 < tasks_active.values_num)
	{
		DBbegin();
		zbx_tm_save_tasks(&tasks_active);
		DBcommit();
		zbx_vector_ptr_clear_ext(&tasks_active, (zbx_clean_func_t)zbx_tm_task_free);
	}

	zbx_vector_ptr_destroy(&tasks_active);
}

ZBX_THREAD_ENTRY(taskmanager_thread, args)
{
	static int		cleanup_time = 0;
	double			sec1, sec2;
	int			tasks_num, sleeptime, nextcheck;
	zbx_ipc_async_socket_t	rtc;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		zbx_problems_export_init("task-manager", process_num);

	sec1 = zbx_time();

	sleeptime = ZBX_TM_PROCESS_PERIOD - (int)sec1 % ZBX_TM_PROCESS_PERIOD;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	zbx_rtc_subscribe(&rtc, process_type, process_num);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data = NULL;

		if (SUCCEED == zbx_rtc_wait(&rtc, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_PROXY_CONFIG_CACHE_RELOAD == rtc_cmd)
				tm_reload_proxy_cache_by_names(&rtc, rtc_data);

			zbx_free(rtc_data);

			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}

		sec1 = zbx_time();
		zbx_update_env(sec1);

		zbx_setproctitle("%s [processing tasks]", get_process_type_string(process_type));

		tasks_num = tm_process_tasks(&rtc, (int)sec1);
		if (ZBX_TM_CLEANUP_PERIOD <= sec1 - cleanup_time)
		{
			tm_remove_old_tasks((int)sec1);
			cleanup_time = sec1;
		}

		sec2 = zbx_time();

		nextcheck = (int)sec1 - (int)sec1 % ZBX_TM_PROCESS_PERIOD + ZBX_TM_PROCESS_PERIOD;

		if (0 > (sleeptime = nextcheck - (int)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [processed %d task(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), tasks_num, sec2 - sec1, sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
