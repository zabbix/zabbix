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

#include "taskmanager_server.h"

#include "../db_lengths_constants.h"
#include "../events/events.h"
#include "../actions/actions.h"
#include "../audit/audit_server.h"

#include "zbxtimekeeper.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxlog.h"
#include "zbxcacheconfig.h"
#include "zbxtasks.h"
#include "zbxexport.h"
#include "zbxdiag.h"
#include "zbxservice.h"
#include "zbxjson.h"
#include "zbxrtc.h"
#include "audit/zbxaudit.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxversion.h"
#include "zbx_rtc_constants.h"
#include "zbxdbwrap.h"
#include "zbxevent.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"
#include "zbxstr.h"
#include "zbxserialize.h"

zbx_export_file_t		*problems_export = NULL;
static zbx_export_file_t	*get_problems_export(void)
{
	return problems_export;
}

/******************************************************************************
 *                                                                            *
 * Purpose: closes specified problem event and removes task                   *
 *                                                                            *
 * Parameters: taskid            - [IN]                                       *
 *             triggerid         - [IN] source trigger id                     *
 *             eventid           - [IN] problem eventid to close              *
 *             userid            - [IN] user that requested to close problem  *
 *             rtc                 [IN] RTC socket                            *
 *                                                                            *
 ******************************************************************************/
static void	tm_execute_task_close_problem(zbx_uint64_t taskid, zbx_uint64_t triggerid, zbx_uint64_t eventid,
		zbx_uint64_t userid, zbx_ipc_async_socket_t *rtc)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64, __func__, eventid);

	zbx_db_result_t	result = zbx_db_select("select null from problem where eventid=" ZBX_FS_UI64
			" and r_eventid is null", eventid);

	/* check if the task hasn't been already closed by another process */
	if (NULL != zbx_db_fetch(result))
		zbx_close_problem(triggerid, eventid, userid, rtc);

	zbx_db_free_result(result);

	zbx_db_execute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE, taskid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: tries to close problem by event acknowledgment action             *
 *                                                                            *
 * Parameters: taskid - [IN]                                                  *
 *             rtc    - [IN] RTC socket                                       *
 *                                                                            *
 * Return value: SUCCEED - task was executed and removed                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_try_task_close_problem(zbx_uint64_t taskid, zbx_ipc_async_socket_t *rtc)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	int			ret = FAIL;
	zbx_uint64_t		userid, triggerid, eventid;
	zbx_vector_uint64_t	triggerids, locked_triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __func__, taskid);

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_create(&locked_triggerids);

	result = zbx_db_select("select a.userid,a.eventid,e.objectid"
				" from task_close_problem tcp"
				" left join acknowledges a"
					" on tcp.acknowledgeid=a.acknowledgeid"
				" left join events e"
					" on a.eventid=e.eventid"
				" where tcp.taskid=" ZBX_FS_UI64,
			taskid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		if (SUCCEED == zbx_db_is_null(row[0]))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process close problem task because related event"
					" was removed");
			zbx_db_execute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE,
					taskid);

			ret = SUCCEED;
		}
		else
		{
			ZBX_STR2UINT64(triggerid, row[2]);
			zbx_vector_uint64_append(&triggerids, triggerid);
			zbx_dc_config_lock_triggers_by_triggerids(&triggerids, &locked_triggerids);

			/* close the problem if source trigger was successfully locked or */
			/* if the trigger doesn't exist, but event still exists */
			if (0 != locked_triggerids.values_num)
			{
				ZBX_STR2UINT64(userid, row[0]);
				ZBX_STR2UINT64(eventid, row[1]);
				tm_execute_task_close_problem(taskid, triggerid, eventid, userid, rtc);

				zbx_dc_config_unlock_triggers(&locked_triggerids);

				ret = SUCCEED;
			}
			else if (FAIL == zbx_dc_config_trigger_exists(triggerid))
			{
				zbx_db_execute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE,
						taskid);
				ret = SUCCEED;
			}
		}
	}
	zbx_db_free_result(result);

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
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_uint64_t	alertid;
	char		*error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __func__, taskid);

	zbx_db_begin();

	result = zbx_db_select("select alertid from task_remote_command where taskid=" ZBX_FS_UI64, taskid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		if (SUCCEED != zbx_db_is_null(row[0]))
		{
			ZBX_STR2UINT64(alertid, row[0]);

			error = zbx_db_dyn_escape_string_len("Remote command has been expired.", ALERT_ERROR_LEN);
			zbx_db_execute("update alerts set error='%s',status=%d where alertid=" ZBX_FS_UI64,
					error, ALERT_STATUS_FAILED, alertid);
			zbx_free(error);
		}
	}

	zbx_db_free_result(result);

	zbx_db_execute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_EXPIRED, taskid);

	zbx_db_commit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes remote command result task                              *
 *                                                                            *
 * Return value: SUCCEED - task was processed successfully                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_remote_command_result(zbx_uint64_t taskid)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_uint64_t	alertid, parent_taskid = 0;
	int		status, ret = FAIL;
	char		*error, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __func__, taskid);

	zbx_db_begin();

	result = zbx_db_select("select r.status,r.info,a.alertid,r.parent_taskid"
			" from task_remote_command_result r"
			" left join task_remote_command c"
				" on c.taskid=r.parent_taskid"
			" left join alerts a"
				" on a.alertid=c.alertid"
			" where r.taskid=" ZBX_FS_UI64, taskid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(parent_taskid, row[3]);

		if (SUCCEED != zbx_db_is_null(row[2]))
		{
			ZBX_STR2UINT64(alertid, row[2]);
			status = atoi(row[0]);

			if (SUCCEED == status)
			{
				zbx_db_execute("update alerts set status=%d where alertid=" ZBX_FS_UI64,
						ALERT_STATUS_SENT, alertid);
			}
			else
			{
				error = zbx_db_dyn_escape_string_len(row[1], ALERT_ERROR_LEN);
				zbx_db_execute("update alerts set error='%s',status=%d where alertid=" ZBX_FS_UI64,
						error, ALERT_STATUS_FAILED, alertid);
				zbx_free(error);
			}
		}

		ret = SUCCEED;
	}

	zbx_db_free_result(result);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_DONE, taskid);
	if (0 != parent_taskid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " or taskid=" ZBX_FS_UI64, parent_taskid);

	zbx_db_execute("%s", sql);
	zbx_free(sql);

	zbx_db_commit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes data task result                                        *
 *                                                                            *
 ******************************************************************************/
static void	tm_process_data_result(zbx_uint64_t taskid)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_uint64_t	parent_taskid = 0;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid:" ZBX_FS_UI64, __func__, taskid);

	zbx_db_begin();

	result = zbx_db_select("select parent_taskid"
			" from task_result"
			" where taskid=" ZBX_FS_UI64,
			taskid);

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_STR2UINT64(parent_taskid, row[0]);

	zbx_db_free_result(result);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where taskid=" ZBX_FS_UI64,
			ZBX_TM_STATUS_DONE, taskid);
	if (0 != parent_taskid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " or taskid=" ZBX_FS_UI64, parent_taskid);

	zbx_db_execute("%s", sql);
	zbx_free(sql);

	zbx_db_commit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: ranks event/problem as cause                                      *
 *                                                                            *
 * Parameters: eventid     - [IN] event/problem, which should be ranked       *
 *                                as cause                                    *
 *                                                                            *
 * Return value: SUCCEED - if there are no database errors                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_rank_event_as_cause(zbx_uint64_t eventid)
{
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid: " ZBX_FS_UI64, __func__, eventid);

	if (ZBX_DB_OK > zbx_db_execute("update problem set cause_eventid=null where eventid=" ZBX_FS_UI64, eventid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to convert problem (eventid:" ZBX_FS_UI64 ") from symptom to"
				" cause", eventid);
		ret = FAIL;
		goto out;
	}

	if (ZBX_DB_OK > zbx_db_execute("delete from event_symptom where eventid=" ZBX_FS_UI64, eventid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to convert event (id:" ZBX_FS_UI64 ") from symptom to cause",
				eventid);
		ret = FAIL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: ranks event/problem as symptom                                    *
 *                                                                            *
 * Parameters:                                                                *
 *     eventid           - [IN] event id of new symptom                       *
 *     cause_eventid     - [IN] event id of new cause                         *
 *     old_cause_eventid - [IN] event id of old cause before ranking          *
 *                                                                            *
 * Return value: SUCCEED - if there are no database errors                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 *****************************************************************************/
static int	tm_rank_event_as_symptom(zbx_uint64_t eventid, zbx_uint64_t cause_eventid,
		zbx_uint64_t old_cause_eventid)
{
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid: " ZBX_FS_UI64 ", cause_eventid: " ZBX_FS_UI64,  __func__, eventid,
			cause_eventid);

	if (ZBX_DB_OK > zbx_db_execute(
			"update problem"
			" set cause_eventid=" ZBX_FS_UI64
			" where eventid=" ZBX_FS_UI64 " or cause_eventid=" ZBX_FS_UI64,
			cause_eventid, eventid, eventid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to set new cause (eventid:" ZBX_FS_UI64 ") for problem"
				" (eventid:" ZBX_FS_UI64 ")", cause_eventid, eventid);
		ret = FAIL;
		goto out;
	}

	if (ZBX_DB_OK > zbx_db_execute(
			"update event_symptom"
			" set cause_eventid=" ZBX_FS_UI64
			" where eventid=" ZBX_FS_UI64 " or cause_eventid=" ZBX_FS_UI64,
			cause_eventid, eventid, eventid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to set new cause (eventid:" ZBX_FS_UI64 ") for event"
				" (eventid:" ZBX_FS_UI64 ")", cause_eventid, eventid);
		ret = FAIL;
		goto out;
	}

	if (0 == old_cause_eventid && ZBX_DB_OK > zbx_db_execute(
			"insert into event_symptom (eventid,cause_eventid)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
			eventid, cause_eventid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to convert cause event " ZBX_FS_UI64 " to symptom of "
				ZBX_FS_UI64, eventid, cause_eventid);
		ret = FAIL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: ranks event task                                                  *
 *                                                                            *
 * Parameters: taskid - [IN]                                                  *
 *             data   - [IN] JSON with with acknowledge id, action, event id  *
 *                           for all actions and cause_eventid for rank to    *
 *                           symptom action                                   *
 *                                                                            *
 * Comments: Logic of this function is described in comments to test cases in *
 *           the integration test testEventsCauseAndSymptoms.                 *
 *                                                                            *
 ******************************************************************************/
static void	tm_process_rank_event(zbx_uint64_t taskid, const char *data)
{
	zbx_uint64_t		acknowledgeid, eventid, action;
	struct zbx_json_parse	jp;
	char			tmp[MAX_ID_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() taskid: " ZBX_FS_UI64 ", data: '%s'",  __func__, taskid, data);

	if (FAIL == zbx_json_open(data, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse process rank event task data");
		goto fail;
	}

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_ACKNOWLEDGEID, tmp, sizeof(tmp), NULL) ||
			FAIL == zbx_is_uint64(tmp, &acknowledgeid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse process rank event task data: failed to retrieve"
				" \"%s\" tag", ZBX_PROTO_TAG_ACKNOWLEDGEID);
		goto fail;
	}

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_ACTION, tmp, sizeof(tmp), NULL) ||
			FAIL == zbx_is_uint64(tmp, &action))
	{

		zabbix_log(LOG_LEVEL_WARNING, "failed to parse process rank event task data: failed to retrieve"
				" \"%s\" tag", ZBX_PROTO_TAG_ACTION);
		goto fail;
	}

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_EVENTID, tmp, sizeof(tmp), NULL) ||
			FAIL == zbx_is_uint64(tmp, &eventid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse process rank event task data: failed to retrieve"
				" \"%s\" tag", ZBX_PROTO_TAG_EVENTID);
		goto fail;
	}

	if (0 != (action & ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE))
	{
		if (SUCCEED != tm_rank_event_as_cause(eventid))
			goto fail;
	}
	else if (0 != (action & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM))
	{
		int		should_swap;
		zbx_uint64_t	requested_cause_eventid, target_cause_eventid, old_cause_eventid,
				target_cause_triggerid;

		if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_CAUSE_EVENTID, tmp, sizeof(tmp), NULL) ||
				FAIL == zbx_is_uint64(tmp, &requested_cause_eventid))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to parse process rank event task data: failed to retrieve"
					" \"%s\" tag", ZBX_PROTO_TAG_CAUSE_EVENTID);
			goto fail;
		}

		/* the event specified in task data by cause_eventid might be a symptom, find the actual target cause */
		if (0 == (target_cause_eventid = zbx_db_get_cause_eventid(requested_cause_eventid)))
			target_cause_eventid = requested_cause_eventid;

		if (target_cause_eventid == eventid)
		{
			/* cause and its symptom should be swapped */
			should_swap = 1;
			target_cause_eventid = requested_cause_eventid;
			old_cause_eventid = 0;
		}
		else
		{
			should_swap = 0;
			old_cause_eventid = zbx_db_get_cause_eventid(eventid);
		}

		if (0 == (target_cause_triggerid = zbx_get_objectid_by_eventid(target_cause_eventid)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "trigger id should never be '0' for target cause event (eventid: "
					ZBX_FS_UI64 ")", target_cause_eventid);
			goto fail;
		}
		else if (SUCCEED != zbx_db_lock_triggerid(target_cause_triggerid))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "the trigger (triggerid: " ZBX_FS_UI64 "), which generated the"
					" target cause event (eventid: " ZBX_FS_UI64 ") was deleted, skip ranking"
					" events as symptoms", target_cause_triggerid, target_cause_eventid);
			goto skip;
		}

		/* start swap by turning the symptom into a cause */
		if (1 == should_swap && SUCCEED != tm_rank_event_as_cause(requested_cause_eventid))
			goto fail;

		if (SUCCEED != tm_rank_event_as_symptom(eventid, target_cause_eventid, old_cause_eventid))
			goto fail;
	}
skip:
	if (ZBX_DB_OK > zbx_db_execute("update acknowledges set taskid=null where acknowledgeid=" ZBX_FS_UI64,
			acknowledgeid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to change taskid from " ZBX_FS_UI64 " to null in table"
				" acknowledges where acknowledgeid is " ZBX_FS_UI64, taskid, acknowledgeid);
	}
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notifies service manager about problem severity changes           *
 *                                                                            *
 ******************************************************************************/
static void	notify_service_manager(const zbx_vector_ack_task_ptr_t *ack_tasks)
{
	zbx_vector_event_severity_ptr_t	event_severities;

	zbx_vector_event_severity_ptr_create(&event_severities);

	for (int i = 0; i < ack_tasks->values_num; i++)
	{
		zbx_ack_task_t	*ack_task = ack_tasks->values[i];

		if (ack_task->old_severity != ack_task->new_severity)
		{
			zbx_event_severity_t	*es;

			es = (zbx_event_severity_t *)zbx_malloc(NULL, sizeof(zbx_event_severity_t));
			es->eventid = ack_task->eventid;
			es->severity = ack_task->new_severity;
			zbx_vector_event_severity_ptr_append(&event_severities, es);
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

	zbx_vector_event_severity_ptr_clear_ext(&event_severities, zbx_event_severity_free);
	zbx_vector_event_severity_ptr_destroy(&event_severities);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process acknowledgments for alerts sending                        *
 *                                                                            *
 * Return value: number of successfully processed tasks                       *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_acknowledgments(zbx_vector_uint64_t *ack_taskids)
{
	zbx_db_row_t			row;
	zbx_db_result_t			result;
	int				processed_num = 0;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_ack_task_ptr_t	ack_tasks;
	zbx_ack_task_t			*ack_task;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __func__, ack_taskids->values_num);

	zbx_vector_uint64_sort(ack_taskids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ack_task_ptr_create(&ack_tasks);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select a.eventid,ta.acknowledgeid,ta.taskid,a.old_severity,a.new_severity,a.action"
			" from task_acknowledge ta"
			" left join acknowledges a"
				" on ta.acknowledgeid=a.acknowledgeid"
			" left join events e"
				" on a.eventid=e.eventid"
			" left join task t"
				" on ta.taskid=t.taskid"
			" where t.status=%d and",
			ZBX_TM_STATUS_NEW);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.taskid", ack_taskids->values,
			ack_taskids->values_num);
	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (SUCCEED == zbx_db_is_null(row[0]))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process acknowledge tasks because related event"
					" was removed");
			continue;
		}

		/* do not notify only rank changes */
		if (0 == (atoi(row[5]) & ~(ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE | ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM)))
			continue;

		ack_task = (zbx_ack_task_t *)zbx_malloc(NULL, sizeof(zbx_ack_task_t));

		ZBX_STR2UINT64(ack_task->eventid, row[0]);
		ZBX_STR2UINT64(ack_task->acknowledgeid, row[1]);
		ZBX_STR2UINT64(ack_task->taskid, row[2]);
		ack_task->old_severity = atoi(row[3]);
		ack_task->new_severity = atoi(row[4]);
		zbx_vector_ack_task_ptr_append(&ack_tasks, ack_task);
	}
	zbx_db_free_result(result);

	if (0 < ack_tasks.values_num)
	{
		zbx_vector_ack_task_ptr_sort(&ack_tasks, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		processed_num = process_actions_by_acknowledgments(&ack_tasks);

		notify_service_manager(&ack_tasks);
	}

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset , "update task set status=%d where", ZBX_TM_STATUS_DONE);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", ack_taskids->values,
			ack_taskids->values_num);
	zbx_db_execute("%s", sql);

	zbx_free(sql);

	zbx_vector_ack_task_ptr_clear_ext(&ack_tasks, zbx_ack_task_free);
	zbx_vector_ack_task_ptr_destroy(&ack_tasks);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes 'check now' tasks for item rescheduling                 *
 *                                                                            *
 * Return value: number of successfully processed tasks                       *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_check_now(zbx_vector_uint64_t *taskids)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	int			processed_num = 0;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_tm_task_t	tasks;
	zbx_vector_uint64_t	done_taskids, itemids;
	zbx_uint64_t		taskid, itemid, proxyid, *proxyids;
	zbx_tm_task_t		*task;
	zbx_tm_check_now_t	*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __func__, taskids->values_num);

	zbx_vector_tm_task_create(&tasks);
	zbx_vector_uint64_create(&done_taskids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.taskid,t.status,t.proxyid,td.itemid"
			" from task t"
			" left join task_check_now td"
				" on t.taskid=td.taskid"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.taskid", taskids->values, taskids->values_num);
	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(taskid, row[0]);

		if (SUCCEED == zbx_db_is_null(row[3]))
		{
			zbx_vector_uint64_append(&done_taskids, taskid);
			continue;
		}

		ZBX_DBROW2UINT64(proxyid, row[2]);
		if (0 != proxyid)
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
		task = zbx_tm_task_create(taskid, ZBX_TM_TASK_CHECK_NOW, 0, 0, 0, proxyid);
		task->data = (void *)zbx_tm_check_now_create(itemid);
		zbx_vector_tm_task_append(&tasks, task);
	}
	zbx_db_free_result(result);

	if (0 != tasks.values_num)
	{
		zbx_vector_uint64_create(&itemids);

		for (int i = 0; i < tasks.values_num; i++)
		{
			task = tasks.values[i];
			data = (zbx_tm_check_now_t *)task->data;
			zbx_vector_uint64_append(&itemids, data->itemid);
		}

		proxyids = (zbx_uint64_t *)zbx_malloc(NULL, tasks.values_num * sizeof(zbx_uint64_t));
		zbx_dc_reschedule_items(&itemids, time(NULL), proxyids);

		sql_offset = 0;

		for (int i = 0; i < tasks.values_num; i++)
		{
			task = tasks.values[i];

			if (0 != proxyids[i] && task->proxyid == proxyids[i])
				continue;

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset , "update task set");

			if (0 == proxyids[i])
			{
				/* close tasks managed by server -                  */
				/* items either have been rescheduled or not cached */
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " status=%d", ZBX_TM_STATUS_DONE);
				if (0 != task->proxyid)
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",proxyid=null");

				processed_num++;
			}
			else
			{
				/* update target proxy hostid */
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " proxyid=" ZBX_FS_UI64,
						proxyids[i]);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where taskid=" ZBX_FS_UI64 ";\n",
					task->taskid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

		zbx_vector_uint64_destroy(&itemids);
		zbx_free(proxyids);

		zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
	}

	if (0 != done_taskids.values_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where",
				ZBX_TM_STATUS_DONE);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", done_taskids.values,
				done_taskids.values_num);
		zbx_db_execute("%s", sql);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&done_taskids);
	zbx_vector_tm_task_destroy(&tasks);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes diaginfo task                                           *
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
 * Purpose: creates config cache reload task to be sent to active proxy       *
 *                                                                            *
 ******************************************************************************/
static zbx_tm_task_t	*tm_create_active_proxy_reload_task(zbx_uint64_t proxyid)
{
	zbx_tm_task_t	*task = zbx_tm_task_create(0, ZBX_TM_TASK_DATA, ZBX_TM_STATUS_NEW, (int)time(NULL),
			ZBX_DATA_ACTIVE_PROXY_CONFIG_RELOAD_TTL, proxyid);

	task->data = zbx_tm_data_create(0, "", 0, ZBX_TM_DATA_TYPE_ACTIVE_PROXY_CONFIG_RELOAD);

	return task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes task for reload of configuration cache on proxies       *
 *                                                                            *
 * Parameters: rtc    - [IN] RTC service                                      *
 *             data   - [IN] JSON with request                                *
 *                                                                            *
 ******************************************************************************/
static void	tm_process_proxy_config_reload_task(zbx_ipc_async_socket_t *rtc, const char *data)
{
	struct zbx_json_parse	jp, jp_data;
	char			buffer[MAX_ID_LEN + 1];
	int			passive_proxy_count = 0;
	zbx_vector_tm_task_t	tasks_active;
	zbx_vector_str_t	proxynames_log;

	if (FAIL == zbx_json_open(data, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse proxy config cache reload task data");
		return;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_PROXYIDS, &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse proxy config cache reload task data: field "
				ZBX_PROTO_TAG_PROXYIDS " not found");
		return;
	}

	zbx_vector_tm_task_create(&tasks_active);
	zbx_vector_str_create(&proxynames_log);

	for (const char *ptr = NULL; NULL != (ptr = zbx_json_next(&jp_data, ptr));)
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

			if (PROXY_OPERATING_MODE_ACTIVE == type)
			{
				zbx_tm_task_t	*task;

				task = tm_create_active_proxy_reload_task(proxyid);
				zbx_vector_tm_task_append(&tasks_active, task);
				zbx_vector_str_append(&proxynames_log, name);
			}
			else if (PROXY_OPERATING_MODE_PASSIVE == type)
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
		zbx_vector_tm_task_clear_ext(&tasks_active, zbx_tm_task_free);
	}

	zbx_vector_str_clear_ext(&proxynames_log, zbx_str_free);
	zbx_vector_str_destroy(&proxynames_log);
	zbx_vector_tm_task_destroy(&tasks_active);

	if (passive_proxy_count > 0)
		zbx_ipc_async_socket_send(rtc, ZBX_RTC_PROXYPOLLER_PROCESS, NULL, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes task for reload of configuration cache on passive proxy *
 *          (received from that passive proxy)                                *
 *                                                                            *
 * Parameters: rtc    - [IN] RTC service                                      *
 *             data   - [IN] JSON with request                                *
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

	zbx_audit_prepare(ZBX_AUDIT_TASKS_RELOAD_CONTEXT);
	zbx_audit_proxy_config_reload(ZBX_AUDIT_TASKS_RELOAD_CONTEXT, proxyid, hostname);
	zbx_audit_flush(ZBX_AUDIT_TASKS_RELOAD_CONTEXT);
}

#define ZBX_TM_TEMP_SUPPRESION_ACTION_SUPPRESS		32
#define ZBX_TM_TEMP_SUPPRESION_ACTION_UNSUPPRESS	64
#define ZBX_TM_TEMP_SUPPRESION_INDEFINITE_TIME		0
static void	tm_service_manager_send_suppression_action(zbx_uint64_t eventid, zbx_uint64_t action)
{
	unsigned char	*data = NULL, *ptr;
	zbx_uint64_t	maintenanceid = 0;
	zbx_uint32_t	data_len = 2 * sizeof(zbx_uint64_t) + sizeof(int);
	int		events_num = 1;

	ptr = data = (unsigned char *)zbx_malloc(NULL, (size_t)data_len);
	ptr += zbx_serialize_value(ptr, events_num);
	ptr += zbx_serialize_value(ptr, eventid);
	(void)zbx_serialize_value(ptr, maintenanceid);

	if (action == ZBX_TM_TEMP_SUPPRESION_ACTION_UNSUPPRESS)
		zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_EVENTS_UNSUPPRESS, data, data_len);
	else if (action == ZBX_TM_TEMP_SUPPRESION_ACTION_SUPPRESS)
		zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_EVENTS_SUPPRESS, data, data_len);
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_free(data);
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
			FAIL == zbx_is_uint64(tmp_eventid, &eventid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to parse temporary suppression data request: failed to retrieve "
				" \"%s\" tag", ZBX_PROTO_TAG_EVENTID);
		return;
	}

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_USERID, tmp_userid, sizeof(tmp_userid), NULL) ||
			FAIL == zbx_is_uint64(tmp_userid, &userid))
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
				FAIL == zbx_is_uint32(tmp_ts, &ts))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to parse temporary suppression data request: "
					"failed to retrieve \"%s\" tag", ZBX_PROTO_TAG_SUPPRESS_UNTIL);
			return;
		}

	}

	if (SUCCEED != zbx_db_lock_record("users", userid, NULL, 0))
		return;

	if (ZBX_TM_TEMP_SUPPRESION_ACTION_UNSUPPRESS == action ||
			(ZBX_TM_TEMP_SUPPRESION_INDEFINITE_TIME != ts && time(NULL) >= ts))
	{
		zbx_db_execute("delete from event_suppress where eventid=" ZBX_FS_UI64 " and maintenanceid is null",
				eventid);
	}
	else if (ZBX_TM_TEMP_SUPPRESION_ACTION_SUPPRESS == action)
	{
		zbx_db_row_t	row;
		zbx_db_result_t	result;

		if (SUCCEED != zbx_db_lock_record("events", eventid, NULL, 0))
			return;

		result = zbx_db_select("select event_suppressid,suppress_until from event_suppress where eventid="
				ZBX_FS_UI64 " and maintenanceid is null" ZBX_FOR_UPDATE, eventid);

		if (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_db_execute("update event_suppress set suppress_until=%u,userid=" ZBX_FS_UI64
					" where event_suppressid=%s", ts, userid, row[0]);
		}
		else
		{
			zbx_db_insert_t	db_insert;

			zbx_db_insert_prepare(&db_insert, "event_suppress", "event_suppressid", "eventid",
					"suppress_until", "userid", (char *)NULL);
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), eventid, ts, userid);

			zbx_db_insert_autoincrement(&db_insert, "event_suppressid");
			zbx_db_insert_execute(&db_insert);
			zbx_db_insert_clean(&db_insert);
		}

		zbx_db_free_result(result);
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	tm_service_manager_send_suppression_action(eventid, action);
}
#undef ZBX_TM_TEMP_SUPPRESION_ACTION_SUPPRESS
#undef ZBX_TM_TEMP_SUPPRESION_ACTION_UNSUPPRESS
#undef ZBX_TM_TEMP_SUPPRESION_INDEFINITE_TIME

/******************************************************************************
 *                                                                            *
 * Purpose: processes data tasks                                              *
 *                                                                            *
 * Return value: number of successfully processed tasks                       *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_data(zbx_ipc_async_socket_t *rtc, zbx_vector_uint64_t *taskids)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	int			processed_num = 0, data_type;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	done_taskids;
	zbx_uint64_t		taskid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __func__, taskids->values_num);

	zbx_db_begin();

	zbx_vector_uint64_create(&done_taskids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.taskid,td.type,td.data"
			" from task t"
			" left join task_data td"
				" on t.taskid=td.taskid"
			" where t.proxyid is null"
				" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.taskid", taskids->values, taskids->values_num);
	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(taskid, row[0]);

		if (SUCCEED == zbx_db_is_null(row[1]))
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
			case ZBX_TM_DATA_TYPE_PROXYIDS:
				tm_process_proxy_config_reload_task(rtc, row[2]);
				zbx_vector_uint64_append(&done_taskids, taskid);
				break;
			case ZBX_TM_DATA_TYPE_PROXYNAME:
				tm_process_passive_proxy_cache_reload_request(rtc, row[2]);
				zbx_vector_uint64_append(&done_taskids, taskid);
				break;
			case ZBX_TM_DATA_TYPE_TEMP_SUPPRESSION:
				tm_process_temp_suppression(row[2]);
				zbx_vector_uint64_append(&done_taskids, taskid);
				break;
			case ZBX_TM_DATA_TYPE_RANK_EVENT:
				tm_process_rank_event(taskid, row[2]);
				zbx_vector_uint64_append(&done_taskids, taskid);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
	zbx_db_free_result(result);

	if (0 != (processed_num = done_taskids.values_num))
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where",
				ZBX_TM_STATUS_DONE);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", done_taskids.values,
				done_taskids.values_num);
		zbx_db_execute("%s", sql);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&done_taskids);

	zbx_db_commit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: expires tasks that don't require specific expiration handling     *
 *                                                                            *
 * Return value: number of successfully expired tasks                         *
 *                                                                            *
 ******************************************************************************/
static int	tm_expire_generic_tasks(zbx_vector_uint64_t *taskids)
{
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where", ZBX_TM_STATUS_EXPIRED);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", taskids->values, taskids->values_num);
	zbx_db_execute("%s", sql);
	zbx_free(sql);

	return taskids->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets proxy version compatibility with server version              *
 *                                                                            *
 ******************************************************************************/
static zbx_proxy_compatibility_t	tm_get_proxy_compatibility(zbx_uint64_t proxyid)
{
	zbx_proxy_compatibility_t	compatibility = ZBX_PROXY_VERSION_UNDEFINED;

	if (0 < proxyid)
	{
		zbx_db_row_t	row;

		zbx_db_result_t	result = zbx_db_select(
				"select compatibility"
				" from proxy_rtdata"
				" where proxyid=" ZBX_FS_UI64, proxyid);

		if (NULL != (row = zbx_db_fetch(result)))
			compatibility = (zbx_proxy_compatibility_t)atoi(row[0]);

		zbx_db_free_result(result);
	}

	return compatibility;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes task manager tasks depending on task type               *
 *                                                                            *
 * Return value: number of successfully processed tasks                       *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_tasks(zbx_ipc_async_socket_t *rtc, time_t now)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	int			type, processed_num = 0, expired_num = 0, clock, ttl;
	zbx_uint64_t		taskid, proxyid;
	zbx_vector_uint64_t	ack_taskids, check_now_taskids, expire_taskids, data_taskids;

	zbx_vector_uint64_create(&ack_taskids);
	zbx_vector_uint64_create(&check_now_taskids);
	zbx_vector_uint64_create(&expire_taskids);
	zbx_vector_uint64_create(&data_taskids);

	result = zbx_db_select("select taskid,type,clock,ttl,proxyid"
				" from task"
				" where status in (%d,%d)"
				" order by taskid",
			ZBX_TM_STATUS_NEW, ZBX_TM_STATUS_INPROGRESS);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_proxy_compatibility_t	compatibility;

		ZBX_STR2UINT64(taskid, row[0]);
		ZBX_STR2UCHAR(type, row[1]);
		clock = atoi(row[2]);
		ttl = atoi(row[3]);
		ZBX_DBROW2UINT64(proxyid, row[4]);

		switch (type)
		{
			case ZBX_TM_TASK_CLOSE_PROBLEM:
				/* close problem tasks will never have 'in progress' status */
				if (SUCCEED == tm_try_task_close_problem(taskid, rtc))
					processed_num++;
				break;
			case ZBX_TM_TASK_REMOTE_COMMAND:
				compatibility = tm_get_proxy_compatibility(proxyid);

				if (ZBX_PROXY_VERSION_UNSUPPORTED == compatibility)
				{
					zbx_tm_task_t	*task;
					const char	*error = "Remote commands are disabled on unsupported proxies.";
					double		t;

					zabbix_log(LOG_LEVEL_WARNING, "%s", error);
					t = zbx_time();
					task = zbx_tm_task_create(0, ZBX_TM_TASK_REMOTE_COMMAND_RESULT,
							ZBX_TM_STATUS_NEW, (time_t)t, 0, 0);
					task->data = zbx_tm_remote_command_result_create(taskid, FAIL, error);
					zbx_tm_save_task(task);
					zbx_tm_task_free(task);
				}

				/* both - 'new' and 'in progress' remote tasks should expire */
				if ((0 != ttl && clock + ttl < now) || (ZBX_PROXY_VERSION_UNSUPPORTED == compatibility))
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
				compatibility = tm_get_proxy_compatibility(proxyid);

				if (ZBX_PROXY_VERSION_UNSUPPORTED == compatibility)
				{
					zabbix_log(LOG_LEVEL_WARNING, "Execute now task is disabled on unsupported"
							" proxies.");
				}

				if ((0 != ttl && clock + ttl < now) || (ZBX_PROXY_VERSION_UNSUPPORTED == compatibility))
					zbx_vector_uint64_append(&expire_taskids, taskid);
				else
					zbx_vector_uint64_append(&check_now_taskids, taskid);
				break;
			case ZBX_TM_TASK_DATA:
				compatibility = tm_get_proxy_compatibility(proxyid);

				if (ZBX_PROXY_VERSION_OUTDATED == compatibility ||
						ZBX_PROXY_VERSION_UNSUPPORTED == compatibility)
				{
					zbx_tm_task_t	*task;
					const char	*error = "The requested task is disabled. Proxy major"
							" version does not match server major version.";
					double		t;

					zabbix_log(LOG_LEVEL_WARNING, "%s", error);
					t = zbx_time();
					task = zbx_tm_task_create(0, ZBX_TM_TASK_DATA_RESULT, ZBX_TM_STATUS_NEW,
							(time_t)t, 0, 0);
					task->data = zbx_tm_data_result_create(taskid, FAIL, error);
					zbx_tm_save_task(task);
					zbx_tm_task_free(task);

					zbx_vector_uint64_append(&expire_taskids, taskid);
					break;
				}
				ZBX_FALLTHROUGH;
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
	zbx_db_free_result(result);

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

/******************************************************************************
 *                                                                            *
 * Purpose: removes old done/expired tasks                                    *
 *                                                                            *
 ******************************************************************************/
static void	tm_remove_old_tasks(time_t now)
{
	zbx_db_begin();
	zbx_db_execute("delete from task where status in (%d,%d) and clock<=" ZBX_FS_TIME_T,
			ZBX_TM_STATUS_DONE, ZBX_TM_STATUS_EXPIRED, (zbx_fs_time_t)(now - ZBX_TM_CLEANUP_TASK_AGE));
	zbx_db_commit();
}

static void	tm_reload_each_proxy_cache(zbx_ipc_async_socket_t *rtc)
{
	int				notify_proxypollers = 0;
	zbx_vector_cached_proxy_ptr_t	proxies;
	zbx_vector_tm_task_t		tasks_active;

	zbx_vector_cached_proxy_ptr_create(&proxies);

	zbx_vector_tm_task_create(&tasks_active);

	zbx_dc_get_all_proxies(&proxies);

	zabbix_log(LOG_LEVEL_WARNING, "reloading configuration cache on all proxies");

	zbx_audit_prepare(ZBX_AUDIT_TASKS_RELOAD_CONTEXT);

	for (int i = 0; i < proxies.values_num; i++)
	{
		zbx_cached_proxy_t	*proxy = proxies.values[i];

		if (PROXY_OPERATING_MODE_ACTIVE == proxy->mode)
		{
			zbx_tm_task_t	*task = tm_create_active_proxy_reload_task(proxy->proxyid);
			zbx_vector_tm_task_append(&tasks_active, task);
		}
		else if (PROXY_OPERATING_MODE_PASSIVE == proxy->mode)
		{
			if (FAIL == zbx_dc_update_passive_proxy_nextcheck(proxy->proxyid))
			{
				zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache on proxy "
						"with id " ZBX_FS_UI64 " [%s]: failed to update nextcheck",
						proxy->proxyid, proxy->name);
			}
			else
				notify_proxypollers = 1;
		}

		zbx_audit_proxy_config_reload(ZBX_AUDIT_TASKS_RELOAD_CONTEXT, proxy->proxyid, proxy->name);
	}

	zbx_audit_flush(ZBX_AUDIT_TASKS_RELOAD_CONTEXT);

	if (0 != notify_proxypollers)
		zbx_ipc_async_socket_send(rtc, ZBX_RTC_PROXYPOLLER_PROCESS, NULL, 0);

	if (0 < tasks_active.values_num)
	{
		zbx_db_begin();
		zbx_tm_save_tasks(&tasks_active);
		zbx_db_commit();
		zbx_vector_tm_task_clear_ext(&tasks_active, zbx_tm_task_free);
	}

	zbx_vector_tm_task_destroy(&tasks_active);

	zbx_vector_cached_proxy_ptr_clear_ext(&proxies, zbx_cached_proxy_free);
	zbx_vector_cached_proxy_ptr_destroy(&proxies);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reloads configuration cache on proxies using given proxy names    *
 *                                                                            *
 * Parameters: rtc    - [IN] RTC service                                      *
 *             data   - [IN] JSON with request                                *
 *                                                                            *
 ******************************************************************************/
static void	tm_reload_proxy_cache_by_names(zbx_ipc_async_socket_t *rtc, const unsigned char *data)
{
	struct zbx_json_parse	jp, jp_data;
	char			name[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	zbx_vector_tm_task_t	tasks_active;
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

	zbx_vector_tm_task_create(&tasks_active);

	zbx_audit_prepare(ZBX_AUDIT_TASKS_RELOAD_CONTEXT);

	for (const char *ptr = NULL; NULL != (ptr = zbx_json_next(&jp_data, ptr));)
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

			if (PROXY_OPERATING_MODE_ACTIVE == type)
			{
				zbx_tm_task_t	*task;

				task = tm_create_active_proxy_reload_task(proxyid);
				zbx_vector_tm_task_append(&tasks_active, task);
				zbx_vector_str_append(&proxynames_log, zbx_strdup(NULL, name));
			}
			else if (PROXY_OPERATING_MODE_PASSIVE == type)
			{
				if (FAIL == zbx_dc_update_passive_proxy_nextcheck(proxyid))
				{
					zabbix_log(LOG_LEVEL_WARNING, "failed to reload configuration cache on proxy "
							"with id " ZBX_FS_UI64 ": failed to update nextcheck", proxyid);
				}
				else
					zbx_vector_str_append(&proxynames_log, zbx_strdup(NULL, name));
			}

			zbx_audit_proxy_config_reload(ZBX_AUDIT_TASKS_RELOAD_CONTEXT, proxyid, name);
		}
	}

	zbx_audit_flush(ZBX_AUDIT_TASKS_RELOAD_CONTEXT);

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
		zbx_db_begin();
		zbx_tm_save_tasks(&tasks_active);
		zbx_db_commit();
		zbx_vector_tm_task_clear_ext(&tasks_active, zbx_tm_task_free);
	}

	zbx_vector_tm_task_destroy(&tasks_active);
}

ZBX_THREAD_ENTRY(taskmanager_thread, args)
{
#define ZBX_TM_PROCESS_PERIOD		5
#define ZBX_TM_CLEANUP_PERIOD		SEC_PER_HOUR
	static time_t		cleanup_time = 0;
	zbx_ipc_async_socket_t	rtc;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			tasks_num, sleeptime, server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_uint32_t		rtc_msgs[] = {ZBX_RTC_PROXY_CONFIG_CACHE_RELOAD};

	zbx_thread_taskmanager_args	*taskmanager_args_in = (zbx_thread_taskmanager_args *)
			((((zbx_thread_args_t *)args))->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		problems_export = zbx_problems_export_init(get_problems_export, "task-manager", process_num);

	double	sec1 = zbx_time();

	sleeptime = ZBX_TM_PROCESS_PERIOD - (time_t)sec1 % ZBX_TM_PROCESS_PERIOD;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, ARRSIZE(rtc_msgs), taskmanager_args_in->config_timeout,
			&rtc);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data = NULL;

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_PROXY_CONFIG_CACHE_RELOAD == rtc_cmd)
				tm_reload_proxy_cache_by_names(&rtc, rtc_data);

			zbx_free(rtc_data);

			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}

		sec1 = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec1);

		zbx_setproctitle("%s [processing tasks]", get_process_type_string(process_type));

		tasks_num = tm_process_tasks(&rtc, (time_t)sec1);
		if (ZBX_TM_CLEANUP_PERIOD <= sec1 - cleanup_time)
		{
			tm_remove_old_tasks((time_t)sec1);
			cleanup_time = (time_t)sec1;
		}

		double	sec2 = zbx_time();

		time_t	nextcheck = (time_t)sec1 - (time_t)sec1 % ZBX_TM_PROCESS_PERIOD + ZBX_TM_PROCESS_PERIOD;

		if (0 > (sleeptime = nextcheck - (time_t)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [processed %d task(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), tasks_num, sec2 - sec1, sleeptime);
	}

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		zbx_export_deinit(problems_export);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef ZBX_TM_PROCESS_PERIOD
#undef ZBX_TM_CLEANUP_PERIOD
}
