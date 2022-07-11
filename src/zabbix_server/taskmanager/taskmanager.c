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

#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "dbcache.h"
#include "zbxtasks.h"
#include "../events.h"
#include "../actions.h"
#include "export.h"
#include "zbxdiag.h"
#include "service_protocol.h"

#define ZBX_TM_PROCESS_PERIOD		5
#define ZBX_TM_CLEANUP_PERIOD		SEC_PER_HOUR
#define ZBX_TASKMANAGER_TIMEOUT		5

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
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

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

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

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
 * Purpose: process data tasks                                                *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_data(zbx_vector_uint64_t *taskids)
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
static int	tm_process_tasks(int now)
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
		processed_num += tm_process_data(&data_taskids);

	if (0 < expire_taskids.values_num)
		expired_num += tm_expire_generic_tasks(&expire_taskids);

	zbx_vector_uint64_destroy(&data_taskids);
	zbx_vector_uint64_destroy(&expire_taskids);
	zbx_vector_uint64_destroy(&check_now_taskids);
	zbx_vector_uint64_destroy(&ack_taskids);

	return processed_num + expired_num;
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

ZBX_THREAD_ENTRY(taskmanager_thread, args)
{
	static int	cleanup_time = 0;
	double		sec1, sec2;
	int		tasks_num, sleeptime, nextcheck;

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

	while (ZBX_IS_RUNNING())
	{
		zbx_sleep_loop(sleeptime);

		sec1 = zbx_time();
		zbx_update_env(sec1);

		zbx_setproctitle("%s [processing tasks]", get_process_type_string(process_type));

		tasks_num = tm_process_tasks((int)sec1);
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
