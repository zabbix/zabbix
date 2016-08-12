/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "../events.h"

#define ZBX_TASKMANAGER_TIMEOUT		5

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: tm_execute_task_close_problem                                    *
 *                                                                            *
 * Purpose: close the specified problem event and remove task                 *
 *                                                                            *
 * Parameters: taskid            - [IN] the task identifier                   *
 *             triggerid         - [IN] the source trigger id                 *
 *             eventid           - [IN] the problem eventid to close          *
 *             userid            - [IN] the user that requested to close the  *
 *                                      problem                               *
 *             locked_triggerids - [IN] the locked trigger identifiers        *
 *                                                                            *
 ******************************************************************************/
static void	tm_execute_task_close_problem(zbx_uint64_t taskid, zbx_uint64_t triggerid, zbx_uint64_t eventid,
		zbx_uint64_t userid, zbx_vector_uint64_t *locked_triggerids)
{
	const char	*__function_name = "tm_execute_task_close_problem";

	DB_RESULT	result;
	DC_TRIGGER	trigger;
	int		errcode;
	zbx_timespec_t	ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64 " eventid:" ZBX_FS_UI64, __function_name,
			taskid, eventid);

	DBbegin();

	result = DBselect("select null from problem where eventid=" ZBX_FS_UI64 " and r_eventid is null", eventid);

	/* check if the task hasn't been already closed by another process */
	if (NULL != DBfetch(result))
	{
		DCconfig_get_triggers_by_triggerids(&trigger, &triggerid, &errcode, 1);

		if (SUCCEED == errcode)
		{
			zbx_vector_ptr_t	trigger_diff;

			zbx_vector_ptr_create(&trigger_diff);

			zbx_append_trigger_diff(&trigger_diff, triggerid, trigger.priority,
					ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT, trigger.value,
					TRIGGER_STATE_NORMAL, 0, NULL);

			zbx_timespec(&ts);

			close_event(eventid, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, triggerid,
					&ts, userid, 0, 0, trigger.description, trigger.expression_orig,
					trigger.recovery_expression_orig, trigger.priority, trigger.type, NULL,
					ZBX_TRIGGER_CORRELATION_NONE, "");

			process_trigger_events(&trigger_diff, locked_triggerids, ZBX_EVENTS_SKIP_CORRELATION);
			DCconfig_triggers_apply_changes(&trigger_diff);
			zbx_save_trigger_changes(&trigger_diff);

			zbx_vector_ptr_clear_ext(&trigger_diff, (zbx_clean_func_t)zbx_trigger_diff_free);
			zbx_vector_ptr_destroy(&trigger_diff);
		}

		DCconfig_clean_triggers(&trigger, &errcode, 1);
	}
	DBfree_result(result);

	DBexecute("delete from task where taskid=" ZBX_FS_UI64, taskid);

	DBcommit();

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
			tm_execute_task_close_problem(taskid, triggerid, eventid, userid, &locked_triggerids);

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
 * Function: tm_process_tasks                                                 *
 *                                                                            *
 * Purpose: process task manager tasks depending on task type                 *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_tasks()
{
	DB_ROW		row;
	DB_RESULT	result;
	int		type, ret, processed_num = 0;
	zbx_uint64_t	taskid;

	result = DBselect("select taskid,type from task order by taskid");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(taskid, row[0]);
		ZBX_STR2UCHAR(type, row[1]);

		switch (type)
		{
			case ZBX_TM_TASK_CLOSE_PROBLEM:
				ret = tm_try_task_close_problem(taskid);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				ret = FAIL;
				break;
		}

		if (FAIL != ret)
			processed_num++;
	}
	DBfree_result(result);

	return 0;
}

ZBX_THREAD_ENTRY(taskmanager_thread, args)
{
	double	sec1, sec2;
	int	tasks_num = 0, sleeptime, nextcheck;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	sec1 = zbx_time();
	sec2 = sec1;

	sleeptime = ZBX_TASKMANAGER_TIMEOUT - (int)sec1 % ZBX_TASKMANAGER_TIMEOUT;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	for (;;)
	{
		zbx_sleep_loop(sleeptime);

		zbx_handle_log();

		zbx_setproctitle("%s [processing tasks]", get_process_type_string(process_type));

		sec1 = zbx_time();
		tasks_num = tm_process_tasks();
		sec2 = zbx_time();

		nextcheck = (int)sec1 - (int)sec1 % ZBX_TASKMANAGER_TIMEOUT + ZBX_TASKMANAGER_TIMEOUT;

		if (0 > (sleeptime = nextcheck - (int)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [processed %d task(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), tasks_num, sec2 - sec1, sleeptime);
	}
}
