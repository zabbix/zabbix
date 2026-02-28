/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "cep_db.h"
#include "cep_task.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxlog.h"
#include "zbxescalations.h"
#include "zbxconnector.h"
#include "zbxexport.h"
#include "../actions/actions.h"
#include "../events/events.h"
#include <stdint.h>

typedef struct
{
	zbx_uint64_t	p_eventid;
	zbx_uint64_t	r_eventid;
	int		clock;
	int		ns;
	zbx_uint64_t	userid;
	zbx_uint64_t	correlationid;
}
zbx_cep_db_event_recovery_t;

ZBX_VECTOR_DECL(cep_db_event_recovery, zbx_cep_db_event_recovery_t)
ZBX_VECTOR_IMPL(cep_db_event_recovery, zbx_cep_db_event_recovery_t)

typedef struct
{
	zbx_uint64_t	objectid;
	int		value;
	int		lastchange;
}
zbx_cep_object_value_t;

ZBX_VECTOR_DECL(cep_object_value, zbx_cep_object_value_t)
ZBX_VECTOR_IMPL(cep_object_value, zbx_cep_object_value_t)

/******************************************************************************
 *                                                                            *
 * Purpose: get user ID associated with a task                                *
 *                                                                            *
 * Parameters: task - [IN]                                                    *
 *                                                                            *
 * Return value: user ID for close event tasks if created by user,            *
 *               0 otherwise                                                  *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	cep_get_close_event_task_userid(const zbx_cep_task_t *task)
{
	switch (task->type)
	{
		case CEP_TASK_CLOSE_EVENT:
			return ((zbx_cep_task_close_event_t *)task)->userid;
		default:
			return 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get correlation ID associated with a task                         *
 *                                                                            *
 * Parameters: task - [IN]                                                    *
 *                                                                            *
 * Return value: correlation ID for close event tasks if created by           *
 *               correlation, 0 otherwise                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	cep_get_close_event_task_correlationid(const zbx_cep_task_t *task)
{
	switch (task->type)
	{
		case CEP_TASK_CLOSE_EVENT:
			return ((zbx_cep_task_close_event_t *)task)->correlationid;
		default:
			return 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: write events created by tasks and their tags to the database      *
 *                                                                            *
 * Parameters: db     - [IN]  database connection                             *
 *             tasks  - [IN]  list of tasks containing events                 *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_events(zbx_dbconn_t *db, const zbx_vector_cep_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_db_insert_t	db_insert_events = {0}, db_insert_event_tag = {0};

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = cep_get_event_task(tasks->values[i]);
		zbx_db_event			*db_event = task->db_event;

		if (SUCCEED != zbx_db_insert_is_prepared(&db_insert_events))
		{
			zbx_dbconn_prepare_insert(db, &db_insert_events, "events", "eventid", "source", "object",
					"objectid", "clock", "ns", "value", "name", "severity", (char *)NULL);
		}

		zbx_db_insert_add_values(&db_insert_events, db_event->eventid, db_event->source, db_event->object,
				db_event->objectid, db_event->clock, db_event->ns, db_event->value,
				ZBX_NULL2EMPTY_STR(db_event->name), db_event->severity);

		if (0 == db_event->tags.values_num)
			continue;

		if (SUCCEED != zbx_db_insert_is_prepared(&db_insert_event_tag))
		{
			zbx_dbconn_prepare_insert(db, &db_insert_event_tag, "event_tag", "eventtagid", "eventid",
					"tag", "value", (char *)NULL);
		}

		for (int j = 0; j < db_event->tags.values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert_event_tag, __UINT64_C(0), db_event->eventid,
					db_event->tags.values[j]->tag, db_event->tags.values[j]->value);
		}
	}

	if (SUCCEED == zbx_db_insert_is_prepared(&db_insert_events))
	{
		zbx_db_insert_execute(&db_insert_events);
		zbx_db_insert_clean(&db_insert_events);
	}

	if (SUCCEED == zbx_db_insert_is_prepared(&db_insert_event_tag))
	{
		zbx_db_insert_autoincrement(&db_insert_event_tag, "eventtagid");
		zbx_db_insert_execute(&db_insert_event_tag);
		zbx_db_insert_clean(&db_insert_event_tag);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write problems (open events) created by tasks to the database     *
 *                                                                            *
 * Parameters: db     - [IN]  database connection                             *
 *             tasks  - [IN]  list of tasks containing events                 *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_problems(zbx_dbconn_t *db, const zbx_vector_cep_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_db_insert_t	db_insert_problem = {0}, db_insert_problem_tag = {0};
	int		problems_num = 0;

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = cep_get_event_task(tasks->values[i]);

		if (CEP_EVENT_OPEN != task->event_op)
			continue;

		if (SUCCEED != zbx_db_insert_is_prepared(&db_insert_problem))
		{
			zbx_dbconn_prepare_insert(db, &db_insert_problem, "problem", "eventid", "source", "object",
					"objectid", "clock", "ns", "name", "severity", (char *)NULL);
		}

		zbx_db_event	*db_event = task->db_event;

		zbx_db_insert_add_values(&db_insert_problem, db_event->eventid, db_event->source, db_event->object,
				db_event->objectid, db_event->clock, db_event->ns, ZBX_NULL2EMPTY_STR(db_event->name),
				db_event->severity);

		if (0 == db_event->tags.values_num)
			continue;

		if (SUCCEED != zbx_db_insert_is_prepared(&db_insert_problem_tag))
		{
			zbx_dbconn_prepare_insert(db, &db_insert_problem_tag, "problem_tag", "problemtagid", "eventid",
					"tag", "value", (char *)NULL);
		}

		for (int j = 0; j < db_event->tags.values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert_problem_tag, __UINT64_C(0), db_event->eventid,
					db_event->tags.values[j]->tag, db_event->tags.values[j]->value);
		}
	}

	if (SUCCEED == zbx_db_insert_is_prepared(&db_insert_problem))
	{
		problems_num = zbx_db_insert_get_row_count(&db_insert_problem);
		zbx_db_insert_execute(&db_insert_problem);
		zbx_db_insert_clean(&db_insert_problem);
	}

	if (SUCCEED == zbx_db_insert_is_prepared(&db_insert_problem_tag))
	{
		zbx_db_insert_autoincrement(&db_insert_problem_tag, "problemtagid");
		zbx_db_insert_execute(&db_insert_problem_tag);
		zbx_db_insert_clean(&db_insert_problem_tag);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() problems:%d", __func__, problems_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write event recovery records to the database                      *
 *                                                                            *
 * Parameters: db     - [IN]  database connection                             *
 *             tasks  - [IN]  list of tasks containing recovery events        *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_event_recovery(zbx_dbconn_t *db, const zbx_vector_cep_task_ptr_t *tasks)
{
	zbx_db_insert_t				db_insert_event_recovery = {0};
	zbx_vector_cep_db_event_recovery_t	recoveries;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	int					recoveries_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_cep_db_event_recovery_create(&recoveries);

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = cep_get_event_task(tasks->values[i]);

		if (CEP_EVENT_CLOSE != task->event_op)
			continue;

		for (int j = 0; j < task->eventids.values_num; j++)
		{
			zbx_cep_db_event_recovery_t	recovery_local = {
				.p_eventid = task->eventids.values[j],
				.r_eventid = task->db_event->eventid,
				.clock = task->db_event->clock,
				.ns = task->db_event->ns,
				.userid = cep_get_close_event_task_userid(tasks->values[i]),
				.correlationid = cep_get_close_event_task_correlationid(tasks->values[i])
			};

			zbx_vector_cep_db_event_recovery_append(&recoveries, recovery_local);
		}
	}

	if (0 != recoveries.values_num)
	{
		recoveries_num = recoveries.values_num;

		zbx_vector_cep_db_event_recovery_sort(&recoveries, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_dbconn_prepare_insert(db, &db_insert_event_recovery,  "event_recovery", "eventid",
			"r_eventid", (char *)NULL);

		for (int i = 0; i < recoveries.values_num; i++)
		{
			zbx_cep_db_event_recovery_t	*recovery = &recoveries.values[i];

			zbx_db_insert_add_values(&db_insert_event_recovery, recovery->p_eventid, recovery->r_eventid);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update problem set r_eventid=" ZBX_FS_UI64 ",r_clock=%d,r_ns=%d",
					recovery->r_eventid, recovery->clock, recovery->ns);

			if (0 != recovery->userid)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",userid=" ZBX_FS_UI64,
						recovery->userid);
			}
			if (0 != recovery->correlationid)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",correlationid=" ZBX_FS_UI64,
						recovery->correlationid);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where eventid=" ZBX_FS_UI64 ";\n",
					recovery->p_eventid);

			zbx_dbconn_execute_overflowed_sql(db, &sql, &sql_alloc, &sql_offset, NULL);
		}
	}

	if (SUCCEED == zbx_db_insert_is_prepared(&db_insert_event_recovery))
	{
		zbx_db_insert_execute(&db_insert_event_recovery);
		zbx_db_insert_clean(&db_insert_event_recovery);

		(void)zbx_dbconn_flush_overflowed_sql(db, sql, sql_offset);
		zbx_free(sql);
	}

	zbx_vector_cep_db_event_recovery_destroy(&recoveries);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() recovered problems:%d", __func__, recoveries_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write event suppression records to the database                   *
 *                                                                            *
 * Parameters: db     - [IN]  database connection                             *
 *             tasks  - [IN]  list of tasks containing suppressed events      *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_event_suppress(zbx_dbconn_t *db, const zbx_vector_cep_task_ptr_t *tasks)
{
	zbx_vector_cep_task_ptr_t	problem_tasks;
	zbx_vector_uint64_t		maintenanceids;
	zbx_db_insert_t			db_insert = {0};
	int				suppress_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_cep_task_ptr_create(&problem_tasks);
	zbx_vector_uint64_create(&maintenanceids);

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = cep_get_event_task(tasks->values[i]);
		const zbx_db_event		*event = task->db_event;

		if (CEP_EVENT_OPEN != task->event_op)
			continue;

		if (EVENT_SOURCE_TRIGGERS != event->source || EVENT_OBJECT_TRIGGER != event->object)
			continue;

		if (NULL == event->suppress)
			continue;

		if (SUCCEED != zbx_db_insert_is_prepared(&db_insert))
		{
			zbx_dbconn_prepare_insert(db, &db_insert, "event_suppress", "event_suppressid",
					"eventid", "maintenanceid", "suppress_until", (char *)NULL);
		}

		for (int j = 0; j < event->suppress->values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), event->eventid,
					event->suppress->values[j].maintenanceid, event->suppress->values[j].until);
		}
	}

	if (SUCCEED == zbx_db_insert_is_prepared(&db_insert))
	{
		suppress_num = zbx_db_insert_get_row_count(&db_insert);
		zbx_db_insert_autoincrement(&db_insert, "event_suppressid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() suppressed problems:%d", __func__, suppress_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write trigger run-time data to the database                       *
 *                                                                            *
 * Parameters: db            - [IN]     database connection                   *
 *             tasks         - [IN]     list of tasks with trigger data       *
 *             trigger_diffs - [IN/OUT] list of trigger state differences     *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_trigger_rtdata(zbx_dbconn_t *db, const zbx_vector_cep_task_ptr_t *tasks,
		zbx_vector_trigger_diff_ptr_t *trigger_diffs)
{
	zbx_vector_cep_object_value_t	updates;
	int				updates_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_cep_object_value_create(&updates);

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = cep_get_event_task(tasks->values[i]);

		if (EVENT_SOURCE_TRIGGERS == task->db_event->source && TRIGGER_VALUE_NONE != task->obj_value)
		{
			zbx_cep_object_value_t	update_local = {
					.objectid = task->db_event->objectid,
					.value = task->obj_value,
					.lastchange = task->db_event->clock
				};

			zbx_vector_cep_object_value_append(&updates, update_local);
		}
	}

	if (0 != updates.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		updates_num = updates.values_num;

		zbx_vector_trigger_diff_ptr_reserve(trigger_diffs, (size_t)updates.values_num);
		zbx_vector_trigger_diff_ptr_clear_ext(trigger_diffs, zbx_trigger_diff_free);

		zbx_vector_cep_object_value_sort(&updates, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		for (int i = 0; i < updates.values_num; i++)
		{
			zbx_cep_object_value_t	*update = &updates.values[i];

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update trigger_rtdata"
					" set value=%d,lastchange=%d"
					" where triggerid=" ZBX_FS_UI64 ";\n",
					update->value, update->lastchange, update->objectid);
			zbx_dbconn_execute_overflowed_sql(db, &sql, &sql_alloc, &sql_offset, NULL);

			zbx_append_trigger_diff(trigger_diffs, update->objectid, 0,
					ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE | ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE,
					update->value, 0, update->lastchange, NULL);
		}

		(void)zbx_dbconn_flush_overflowed_sql(db, sql, sql_offset);
		zbx_free(sql);
	}

	zbx_vector_cep_object_value_destroy(&updates);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() updated triggers:%d", __func__, updates_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush events created by tasks to database                         *
 *                                                                            *
 * Parameters: dbpool - [IN] database connection pool                         *
 *             tasks  - [IN] list of tasks containing events                  *
 *                                                                            *
 ******************************************************************************/
void	cep_db_flush_events(zbx_dbconn_pool_t *dbpool, const zbx_vector_cep_task_ptr_t *tasks)
{
	zbx_dbconn_t			*db;
	zbx_vector_trigger_diff_ptr_t	trigger_diffs;

	zbx_vector_trigger_diff_ptr_create(&trigger_diffs);

	/* WDN: remove */
	zabbix_increase_log_level();

	for (int ret = ZBX_DB_DOWN; ret == ZBX_DB_DOWN;)
	{
		db = zbx_dbconn_pool_acquire_connection(dbpool);
		zbx_dbconn_begin(db);

		cep_db_write_events(db, tasks);
		cep_db_write_problems(db, tasks);
		cep_db_write_event_recovery(db, tasks);
		cep_db_write_event_suppress(db, tasks);
		cep_db_write_trigger_rtdata(db, tasks, &trigger_diffs);

		ret = zbx_dbconn_commit(db);
		zbx_dbconn_pool_release_connection(dbpool, db);
	}

	/* WDN: remove */
	zabbix_decrease_log_level();

	zbx_dc_config_triggers_apply_changes(trigger_diffs.values, trigger_diffs.values_num);
	zbx_vector_trigger_diff_ptr_clear_ext(&trigger_diffs, zbx_trigger_diff_free);
	zbx_vector_trigger_diff_ptr_destroy(&trigger_diffs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process CEP task actions for created events                       *
 *                                                                            *
 * Parameters: dbpool - [IN] database connection pool                         *
 *             tasks  - [IN] list of tasks containing events and actions      *
 *             rtc    - [IN] RTC service socket                               *
 *                                                                            *
 ******************************************************************************/
void	cep_db_process_actions(zbx_dbconn_pool_t *dbpool, const zbx_vector_cep_task_ptr_t *tasks,
		zbx_ipc_async_socket_t *rtc)
{
	zbx_vector_uint64_pair_t	event_recovery;
	zbx_vector_escalation_new_ptr_t	escalations;
	zbx_vector_db_event_t		events;
	zbx_dbconn_t			*db;
	int				ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_db_event_create(&events);
	zbx_vector_db_event_reserve(&events, (size_t)tasks->values_num);
	zbx_vector_uint64_pair_create(&event_recovery);
	zbx_vector_escalation_new_ptr_create(&escalations);

	for (int i = 0; i < tasks->values_num; i++)
	{
		zbx_uint64_pair_t		pair;
		const zbx_cep_task_event_t	*task = cep_get_event_task(tasks->values[i]);

		if (CEP_ACTION_ENABLED != task->action_state)
			continue;

		zbx_vector_db_event_append(&events, task->db_event);

		if (CEP_EVENT_CLOSE != task->event_op)
			continue;

		pair.second = task->db_event->eventid;

		for (int j = 0; j < task->eventids.values_num; j++)
		{
			pair.first = task->eventids.values[j];
			zbx_vector_uint64_pair_append(&event_recovery, pair);
		}
	}

	for (ret = ZBX_DB_DOWN; ret == ZBX_DB_DOWN;)
	{
		zbx_vector_escalation_new_ptr_clear_ext(&escalations, zbx_escalation_new_ptr_free);

		db = zbx_dbconn_pool_acquire_connection(dbpool);
		zbx_dbconn_begin(db);
		process_actions(db, &events, &event_recovery, &escalations);
		ret = zbx_dbconn_commit(db);
		zbx_dbconn_pool_release_connection(dbpool, db);
	}

	if (ZBX_DB_OK == ret && 0 != escalations.values_num)
			zbx_start_escalations(rtc, &escalations);

	zbx_vector_escalation_new_ptr_clear_ext(&escalations, zbx_escalation_new_ptr_free);
	zbx_vector_escalation_new_ptr_destroy(&escalations);
	zbx_vector_uint64_pair_destroy(&event_recovery);
	zbx_vector_db_event_destroy(&events);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: export events to a export files                                   *
 *                                                                            *
 * Parameters: dbpool         - [IN] database connection pool                 *
 *             tasks          - [IN] list of tasks containing events          *
 *             problem_export - [IN] problem export context                   *
 *                                                                            *
 ******************************************************************************/
void	cep_db_export_events(zbx_dbconn_pool_t *dbpool, const zbx_vector_cep_task_ptr_t *tasks,
		zbx_export_file_t *problem_export)
{
	zbx_vector_db_event_t		problems;
	zbx_vector_db_event_recovery_t	recovery;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_db_event_create(&problems);
	zbx_vector_db_event_reserve(&problems, (size_t)tasks->values_num);

	zbx_vector_db_event_recovery_create(&recovery);
	zbx_vector_db_event_recovery_reserve(&recovery, (size_t)tasks->values_num);

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = cep_get_event_task(tasks->values[i]);

		if (EVENT_SOURCE_TRIGGERS != task->db_event->source)
			continue;

		if (CEP_EVENT_OPEN == task->event_op)
		{
			zbx_vector_db_event_append(&problems, task->db_event);
		}
		else if (CEP_EVENT_CLOSE == task->event_op)
		{
			zbx_db_event_recovery_t	recovery_local;

			recovery_local.event = task->db_event;
			zbx_vector_uint64_create(&recovery_local.p_eventids);
			zbx_vector_uint64_append_array(&recovery_local.p_eventids, task->eventids.values,
					task->eventids.values_num);
			zbx_vector_db_event_recovery_append(&recovery, recovery_local);
		}
	}

	if (0 != problems.values_num || 0 != recovery.values_num)
	{
		zbx_dbconn_t			*db;
		zbx_vector_connector_filter_t	connectors;

		zbx_vector_connector_filter_create(&connectors);

		zbx_dc_config_history_sync_get_connector_filters(NULL, &connectors);

		db = zbx_dbconn_pool_acquire_connection(dbpool);

		if (0 != connectors.values_num || NULL != problem_export)
		{
			unsigned char	*data = NULL;
			size_t		data_alloc = 0, data_offset = 0;

			zbx_export_events(db, &problems, &recovery, problem_export, &connectors, &data, &data_alloc,
					&data_offset);

			if (0 != data_offset)
				zbx_connector_send(ZBX_IPC_CONNECTOR_REQUEST, data, (zbx_uint32_t)data_offset);

			zbx_free(data);
		}

		zbx_dbconn_pool_release_connection(dbpool, db);

		zbx_vector_connector_filter_clear_ext(&connectors, zbx_connector_filter_free);
		zbx_vector_connector_filter_destroy(&connectors);
	}

	for (int i = 0; i < recovery.values_num; i++)
		zbx_vector_uint64_destroy(&recovery.values[i].p_eventids);

	zbx_vector_db_event_recovery_destroy(&recovery);
	zbx_vector_db_event_destroy(&problems);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add tags to events in the database                                *
 *                                                                            *
 * Parameters: dbpool - [IN] database connection pool                         *
 *             tasks  - [IN] list of tasks containing events and tags         *
 *                                                                            *
 ******************************************************************************/
void	cep_db_add_tags(zbx_dbconn_pool_t *dbpool, const zbx_vector_cep_task_ptr_t *tasks)
{
	zbx_vector_uint64_t		eventids;
	zbx_vector_event_tags_ptr_t	db_tags, event_tags;
	int				event_tags_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_uint64_create(&eventids);
	zbx_vector_event_tags_ptr_create(&db_tags);
	zbx_vector_event_tags_ptr_create(&event_tags);

	/* WDN: remove */
	zabbix_increase_log_level();

	for (int i = 0; i < tasks->values_num; i++)
	{
		zbx_cep_task_add_tags_t	*t = (zbx_cep_task_add_tags_t *)tasks->values[i];

		for (int j = 0; j < t->db_tags.values_num; j++)
			zbx_vector_event_tags_ptr_append(&db_tags, &t->db_tags.values[j]);

		event_tags_num += t->db_tags.values_num;
		event_tags_num += t->cached_tags.values_num;
	}

	zbx_vector_event_tags_ptr_reserve(&event_tags, (size_t)event_tags_num);

	zbx_dbconn_t	*db = zbx_dbconn_pool_acquire_connection(dbpool);

	do
	{
		zbx_dbconn_begin(db);

		if (0 != db_tags.values_num)
			zbx_db_validate_tags(db, &db_tags);

		/* validation would remove event_tag if it contained only duplicated tags */
		if (0 != db_tags.values_num)
			zbx_vector_event_tags_ptr_append_array(&event_tags, db_tags.values, db_tags.values_num);

		for (int i = 0; i < tasks->values_num; i++)
		{
			zbx_cep_task_add_tags_t	*t = (zbx_cep_task_add_tags_t *)tasks->values[i];

			for (int j = 0; j < t->cached_tags.values_num; j++)
				zbx_vector_event_tags_ptr_append(&event_tags, &t->cached_tags.values[j]);
		}

		for (int i = 0; i < event_tags.values_num; i++)
			zbx_vector_uint64_append(&eventids, event_tags.values[i]->eventid);

		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_db_write_tags(db, &event_tags, "events", "eventid", "event_tag", "eventtagid", &eventids);
		zbx_db_write_tags(db, &event_tags, "problem", "eventid", "problem_tag", "problemtagid", &eventids);

		zbx_vector_uint64_clear(&eventids);
		zbx_vector_event_tags_ptr_clear(&event_tags);
	}
	while (ZBX_DB_DOWN == zbx_dbconn_commit(db));

	zbx_dbconn_pool_release_connection(dbpool, db);

	zbx_vector_uint64_destroy(&eventids);
	zbx_vector_event_tags_ptr_destroy(&event_tags);
	zbx_vector_event_tags_ptr_destroy(&db_tags);

	/* WDN: remove */
	zabbix_decrease_log_level();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
