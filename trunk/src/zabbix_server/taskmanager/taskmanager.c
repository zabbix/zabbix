/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "db.h"
#include "dbcache.h"
#include "zbxtasks.h"
#include "../events.h"
#include "../actions.h"

#define ZBX_TM_PROCESS_PERIOD		5
#define ZBX_TM_CLEANUP_PERIOD		SEC_PER_HOUR
#define ZBX_TASKMANAGER_TIMEOUT		5

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: tm_execute_task_close_problem                                    *
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
	const char		*__function_name = "tm_execute_task_close_problem";

	DB_RESULT	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64, __function_name, eventid);

	result = DBselect("select null from problem where eventid=" ZBX_FS_UI64 " and r_eventid is null", eventid);

	/* check if the task hasn't been already closed by another process */
	if (NULL != DBfetch(result))
		zbx_close_problem(triggerid, eventid, userid);

	DBfree_result(result);

	DBexecute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE, taskid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_try_task_close_problem                                        *
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
	const char		*__function_name = "tm_try_task_close_problem";

	DB_ROW			row;
	DB_RESULT		result;
	int			ret = FAIL;
	zbx_uint64_t		userid, triggerid, eventid;
	zbx_vector_uint64_t	triggerids, locked_triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __function_name, taskid);

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_create(&locked_triggerids);

	result = DBselect("select a.userid,a.eventid,e.objectid"
				" from task_close_problem tcp,acknowledges a"
				" left join events e"
					" on a.eventid=e.eventid"
				" where tcp.taskid=" ZBX_FS_UI64
					" and tcp.acknowledgeid=a.acknowledgeid",
			taskid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[2]);
		zbx_vector_uint64_append(&triggerids, triggerid);
		DCconfig_lock_triggers_by_triggerids(&triggerids, &locked_triggerids);

		/* only close the problem if source trigger was successfully locked */
		if (0 != locked_triggerids.values_num)
		{
			ZBX_STR2UINT64(userid, row[0]);
			ZBX_STR2UINT64(eventid, row[1]);
			tm_execute_task_close_problem(taskid, triggerid, eventid, userid);

			DCconfig_unlock_triggers(&locked_triggerids);

			ret = SUCCEED;
		}
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&locked_triggerids);
	zbx_vector_uint64_destroy(&triggerids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_expire_remote_command                                         *
 *                                                                            *
 * Purpose: process expired remote command task                               *
 *                                                                            *
 ******************************************************************************/
static void	tm_expire_remote_command(zbx_uint64_t taskid)
{
	const char	*__function_name = "tm_expire_remote_command";

	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	alertid;
	char		*error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __function_name, taskid);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_process_remote_command_result                                 *
 *                                                                            *
 * Purpose: process remote command result task                                *
 *                                                                            *
 * Return value: SUCCEED - the task was processed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_remote_command_result(zbx_uint64_t taskid)
{
	const char	*__function_name = "tm_process_remote_command_result";

	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	alertid, parent_taskid = 0;
	int		status, ret = FAIL;
	char		*error, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __function_name, taskid);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_process_acknowledgments                                       *
 *                                                                            *
 * Purpose: process acknowledgments for alerts sending                        *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_acknowledgments(zbx_vector_uint64_t *ack_taskids)
{
	const char		*__function_name = "tm_process_acknowledgments";

	DB_ROW			row;
	DB_RESULT		result;
	int			processed_num = 0;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_ptr_t	ack_tasks;
	zbx_ack_task_t		*ack_task;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __function_name, ack_taskids->values_num);

	zbx_vector_uint64_sort(ack_taskids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&ack_tasks);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select a.eventid,ta.acknowledgeid,ta.taskid"
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
		zbx_vector_ptr_append(&ack_tasks, ack_task);
	}
	DBfree_result(result);

	if (0 < ack_tasks.values_num)
	{
		zbx_vector_ptr_sort(&ack_tasks, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		processed_num = process_actions_by_acknowledgments(&ack_tasks);
	}

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset , "update task set status=%d where", ZBX_TM_STATUS_DONE);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", ack_taskids->values, ack_taskids->values_num);
	DBexecute("%s", sql);

	zbx_free(sql);

	zbx_vector_ptr_clear_ext(&ack_tasks, zbx_ptr_free);
	zbx_vector_ptr_destroy(&ack_tasks);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __function_name, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_process_tasks                                                 *
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
	int			type, processed_num = 0, clock, ttl;
	zbx_uint64_t		taskid;
	zbx_vector_uint64_t	ack_taskids;

	zbx_vector_uint64_create(&ack_taskids);

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
					tm_expire_remote_command(taskid);
				processed_num++;
				break;
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				/* close problem tasks will never have 'in progress' status */
				if (SUCCEED == tm_process_remote_command_result(taskid))
					processed_num++;
				break;
			case ZBX_TM_TASK_ACKNOWLEDGE:
				zbx_vector_uint64_append(&ack_taskids, taskid);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}

	}
	DBfree_result(result);

	if (0 < ack_taskids.values_num)
		processed_num += tm_process_acknowledgments(&ack_taskids);

	zbx_vector_uint64_destroy(&ack_taskids);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_remove_old_tasks                                              *
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

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	sec1 = zbx_time();

	sleeptime = ZBX_TM_PROCESS_PERIOD - (int)sec1 % ZBX_TM_PROCESS_PERIOD;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	for (;;)
	{
		zbx_sleep_loop(sleeptime);

		zbx_handle_log();

		zbx_setproctitle("%s [processing tasks]", get_process_type_string(process_type));

		sec1 = zbx_time();

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

#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		zbx_update_resolver_conf();	/* handle /etc/resolv.conf update */
#endif
	}
}
