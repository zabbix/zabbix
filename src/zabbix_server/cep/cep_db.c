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
#include "cep.h"
#include "cep_api.h"
#include "cep_task.h"
#include "zabbix_server/cep/cep_window.h"
#include "zbx_cep.h"

#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxescalations.h"
#include "zbxconnector.h"
#include "zbxexport.h"
#include "zbxnum.h"
#include "zbxtypes.h"
#include "zbxstr.h"
#include "../actions/actions.h"
#include "../events/events.h"
#include "zbxevent.h"

typedef struct
{
	zbx_uint64_t	p_eventid;
	zbx_uint64_t	r_eventid;
	zbx_uint64_t	c_eventid;
	int		clock;
	int		ns;
	zbx_uint64_t	userid;
	zbx_uint64_t	correlationid;
	zbx_uint64_t	cep_ruleid;
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

static void	cep_db_write_event(const zbx_cep_event_t *event, zbx_dbconn_t *db, zbx_db_insert_t *db_insert_events,
		zbx_db_insert_t *db_insert_tag)
{
	zbx_db_insert_add_values(db_insert_events, event->eventid, event->origin.source, event->origin.object,
			event->origin.objectid, event->clock, event->ns, event->value,
			ZBX_NULL2EMPTY_STR(event->name), event->severity, (int)event->flags);

	if (0 == event->tags.values_num)
		return;

	if (SUCCEED != zbx_db_insert_is_prepared(db_insert_tag))
	{
		zbx_dbconn_prepare_insert(db, db_insert_tag, "event_tag", "eventtagid", "eventid", "tag", "value",
				(char *)NULL);
	}

	for (int j = 0; j < event->tags.values_num; j++)
	{
		zbx_db_insert_add_values(db_insert_tag, __UINT64_C(0), event->eventid,
				event->tags.values[j].tag, event->tags.values[j].value);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare a CEP problem and its tags for insertion into the         *
 *          database                                                          *
 *                                                                            *
 * Parameters: event             - [IN] event to write                        *
 *             db                - [IN] database connection used to prepare   *
 *                                 the insert statements                      *
 *             db_insert_problem - [IN/OUT] insert batch for the problem      *
 *                                 table                                      *
 *             db_insert_tag     - [IN/OUT] insert batch for the problem_tag  *
 *                                 table                                      *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_problem(const zbx_cep_event_t *event, zbx_dbconn_t *db, zbx_db_insert_t *db_insert_problem,
		zbx_db_insert_t *db_insert_tag, const zbx_vector_uint64_t *ref_eventids)
{
	zbx_uint64_t	cause_eventid = event->cause_eventid;

	if (0 != cause_eventid && FAIL == zbx_vector_uint64_bsearch(ref_eventids, cause_eventid,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC))
	{
		zbx_cep_t	*cep;

		cause_eventid = 0;

		cep_cache_acquire(&cep);
		cep_set_event_cause(cep, event->eventid, cause_eventid);
		cep_cache_release(&cep);
	}

	zbx_db_insert_add_values(db_insert_problem, event->eventid, event->origin.source, event->origin.object,
			event->origin.objectid, event->clock, event->ns, ZBX_NULL2EMPTY_STR(event->name),
			event->severity, cause_eventid, (int)event->flags);

	if (0 == event->tags.values_num)
		return;

	if (SUCCEED != zbx_db_insert_is_prepared(db_insert_tag))
	{
		zbx_dbconn_prepare_insert(db, db_insert_tag, "problem_tag", "problemtagid", "eventid", "tag", "value",
				(char *)NULL);
	}

	for (int j = 0; j < event->tags.values_num; j++)
	{
		zbx_db_insert_add_values(db_insert_tag, __UINT64_C(0), event->eventid,
				event->tags.values[j].tag, event->tags.values[j].value);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepares a database event and its tags for insertion into the     *
 *          database                                                          *
 *                                                                            *
 * Parameters: db_event         - [IN] event to write                         *
 *             db               - [IN] database connection used to prepare    *
 *                                the insert statements                       *
 *             db_insert_events - [IN/OUT] insert batch for the events        *
 *                                table                                       *
 *             db_insert_tag    - [IN/OUT] insert batch for the event_tag     *
 *                                table                                       *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_db_event(const zbx_db_event *db_event, zbx_dbconn_t *db, zbx_db_insert_t *db_insert_events,
		zbx_db_insert_t *db_insert_tag)
{
	zbx_db_insert_add_values(db_insert_events, db_event->eventid, db_event->source, db_event->object,
			db_event->objectid, db_event->clock, db_event->ns, db_event->value,
			ZBX_NULL2EMPTY_STR(db_event->name), db_event->severity, 0);

	if (0 == db_event->tags.values_num)
		return;

	if (SUCCEED != zbx_db_insert_is_prepared(db_insert_tag))
	{
		zbx_dbconn_prepare_insert(db, db_insert_tag, "event_tag", "eventtagid", "eventid", "tag", "value",
				(char *)NULL);
	}

	for (int j = 0; j < db_event->tags.values_num; j++)
	{
		zbx_db_insert_add_values(db_insert_tag, __UINT64_C(0), db_event->eventid,
				db_event->tags.values[j]->tag, db_event->tags.values[j]->value);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare a database problem and its tags for insertion into the    *
 *          database                                                          *
 *                                                                            *
 * Parameters: db_event          - [IN] event to write                        *
 *             db                - [IN] database connection used to           *
 *                                 prepare the insert statements              *
 *             db_insert_problem - [IN/OUT] insert batch for the problem      *
 *                                 table                                      *
 *             db_insert_tag     - [IN/OUT] insert batch for the problem_tag  *
 *                                 table                                      *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_db_problem(const zbx_db_event *db_event, zbx_dbconn_t *db,
		zbx_db_insert_t *db_insert_problem, zbx_db_insert_t *db_insert_tag)
{
	zbx_db_insert_add_values(db_insert_problem, db_event->eventid, db_event->source, db_event->object,
			db_event->objectid, db_event->clock, db_event->ns, ZBX_NULL2EMPTY_STR(db_event->name),
			db_event->severity, __UINT64_C(0), 0);

	if (0 == db_event->tags.values_num)
		return;

	if (SUCCEED != zbx_db_insert_is_prepared(db_insert_tag))
	{
		zbx_dbconn_prepare_insert(db, db_insert_tag, "problem_tag", "problemtagid", "eventid", "tag", "value",
				(char *)NULL);
	}

	for (int j = 0; j < db_event->tags.values_num; j++)
	{
		zbx_db_insert_add_values(db_insert_tag, __UINT64_C(0), db_event->eventid,
				db_event->tags.values[j]->tag, db_event->tags.values[j]->value);
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
static void	cep_db_write_events(zbx_dbconn_t *db, const zbx_vector_mw_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_db_insert_t	db_insert_events = {0}, db_insert_event_tag = {0};

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

		if (SUCCEED != zbx_db_insert_is_prepared(&db_insert_events))
		{
			zbx_dbconn_prepare_insert(db, &db_insert_events, "events", "eventid", "source", "object",
					"objectid", "clock", "ns", "value", "name", "severity", "flags", (char *)NULL);
		}

		/* trigger event might have been changed by CEP - need to commit from cache                  */
		/* while other (internal) event tags are not cached - need to commit from received db_event  */
		if (EVENT_SOURCE_TRIGGERS == task->db_event->source)
			cep_db_write_event(task->event, db, &db_insert_events, &db_insert_event_tag);
		else
			cep_db_write_db_event(task->db_event, db, &db_insert_events, &db_insert_event_tag);
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
 *             ref_eventids - [IN] referenced eventids                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_problems(zbx_dbconn_t *db, const zbx_vector_mw_task_ptr_t *tasks,
		const zbx_vector_uint64_t *ref_eventids)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_db_insert_t	db_insert_problem = {0}, db_insert_problem_tag = {0};
	int		problems_num = 0;

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

		if (CEP_EVENT_OPEN != task->event_op)
			continue;

		if (SUCCEED != zbx_db_insert_is_prepared(&db_insert_problem))
		{
			zbx_dbconn_prepare_insert(db, &db_insert_problem, "problem", "eventid", "source", "object",
				"objectid", "clock", "ns", "name", "severity", "cause_eventid", "flags", (char *)NULL);
		}

		/* trigger event might have been changed by CEP - need to commit from cache                  */
		/* while other (internal) event tags are not cached - need to commit from received db_event  */
		if (EVENT_SOURCE_TRIGGERS == task->db_event->source)
		{
			cep_db_write_problem(task->event, db, &db_insert_problem, &db_insert_problem_tag,
					ref_eventids);
		}
		else
			cep_db_write_db_problem(task->db_event, db, &db_insert_problem, &db_insert_problem_tag);
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
 * Purpose: write symptom->cause links to database                            *
 *                                                                            *
 * Parameters: db     - [IN]  database connection                             *
 *             tasks  - [IN]  list of tasks containing events                 *
 *             ref_eventids - [IN] referenced eventids                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_symptoms(zbx_dbconn_t *db, const zbx_vector_mw_task_ptr_t *tasks,
		const zbx_vector_uint64_t *ref_eventids)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	int				symptoms_num = 0;
	zbx_vector_mw_task_ptr_t	symptom_tasks;

	zbx_vector_mw_task_ptr_create(&symptom_tasks);
	zbx_vector_mw_task_ptr_reserve(&symptom_tasks, (size_t)tasks->values_num);

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

		if (EVENT_SOURCE_TRIGGERS != task->db_event->source || EVENT_OBJECT_TRIGGER != task->db_event->object)
			continue;

		if (CEP_EVENT_OPEN != task->event_op)
			continue;

		zbx_cep_event_t	*event = task->event;

		if (0 == event->cause_eventid)
			continue;

		zbx_vector_mw_task_ptr_append(&symptom_tasks, tasks->values[i]);
	}

	if (0 != symptom_tasks.values_num)
	{
		zbx_db_insert_t	db_insert;

		zbx_dbconn_prepare_insert(db, &db_insert, "event_symptom", "eventid", "cause_eventid", (char *)NULL);

		for (int i = 0; i < symptom_tasks.values_num; i++)
		{
			const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)symptom_tasks.values[i];
			zbx_cep_event_t			*event = task->event;

			if (FAIL == zbx_vector_uint64_bsearch(ref_eventids, event->cause_eventid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
					continue;
			}

			zbx_db_insert_add_values(&db_insert, event->eventid, event->cause_eventid);
		}

		symptoms_num =  zbx_db_insert_get_row_count(&db_insert);
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_vector_mw_task_ptr_destroy(&symptom_tasks);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() symptoms:%d", __func__, symptoms_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write event recovery records to the database                      *
 *                                                                            *
 * Parameters: db     - [IN]  database connection                             *
 *             tasks  - [IN]  list of tasks containing recovery events        *
 *             ref_eventids - [IN] referenced eventids                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_write_event_recovery(zbx_dbconn_t *db, const zbx_vector_mw_task_ptr_t *tasks,
		const zbx_vector_uint64_t *ref_eventids)
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
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

		if (CEP_EVENT_CLOSE != task->event_op)
			continue;

		for (int j = 0; j < task->eventids.values_num; j++)
		{
			zbx_cep_db_event_recovery_t	recovery_local = {
				.p_eventid = task->eventids.values[j],
				.r_eventid = task->db_event->eventid,
				.clock = task->db_event->clock,
				.ns = task->db_event->ns,
				.userid = task->creator.userid,
				.correlationid = task->creator.correlationid,
				.c_eventid = task->creator.c_eventid,
				.cep_ruleid = task->creator.cep_ruleid
			};

			zbx_vector_cep_db_event_recovery_append(&recoveries, recovery_local);
		}
	}

	if (0 != recoveries.values_num)
	{
		recoveries_num = recoveries.values_num;

		zbx_vector_cep_db_event_recovery_sort(&recoveries, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_dbconn_prepare_insert(db, &db_insert_event_recovery,  "event_recovery", "eventid",
			"r_eventid", "userid", "correlationid", "c_eventid", "cep_ruleid", (char *)NULL);

		for (int i = 0; i < recoveries.values_num; i++)
		{
			zbx_cep_db_event_recovery_t	*recovery = &recoveries.values[i];

			if (FAIL == zbx_vector_uint64_bsearch(ref_eventids, recovery->p_eventid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				continue;
			}

			zbx_db_insert_add_values(&db_insert_event_recovery, recovery->p_eventid, recovery->r_eventid,
					recovery->userid, recovery->correlationid, recovery->c_eventid,
					recovery->cep_ruleid);

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

			if (0 != recovery->cep_ruleid)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",cep_ruleid=" ZBX_FS_UI64,
						recovery->cep_ruleid);
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
static void	cep_db_write_event_suppress(zbx_dbconn_t *db, const zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_vector_uint64_t		maintenanceids, cep_ruleids;
	zbx_db_insert_t			db_insert_es = {0}, db_insert_ack = {0};
	int				suppress_num = 0, now;
	zbx_vector_cep_event_ptr_t	events;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	now = (int)time(NULL);

	zbx_vector_uint64_create(&maintenanceids);
	zbx_vector_uint64_create(&cep_ruleids);
	zbx_vector_cep_event_ptr_create(&events);

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];
		zbx_cep_event_t			*event;

		if (EVENT_SOURCE_TRIGGERS != task->db_event->source || EVENT_OBJECT_TRIGGER != task->db_event->object)
			continue;

		if (CEP_EVENT_OPEN != task->event_op)
			continue;

		if (NULL == (event = (zbx_cep_event_t *)task->event))
			continue;

		if (0 != event->suppress.values_num)
		{
			for (int j = 0; j < event->suppress.values_num; j++)
			{
				zbx_db_event_suppress_t	*suppress = &event->suppress.values[j];

				if (0 != suppress->maintenanceid)
					zbx_vector_uint64_append(&maintenanceids, suppress->maintenanceid);

				if (0 != suppress->cep_ruleid)
					zbx_vector_uint64_append(&cep_ruleids, suppress->cep_ruleid);
			}

			zbx_vector_cep_event_ptr_append(&events, event);
		}
	}

	if (0 != events.values_num)
	{
		zbx_vector_uint64_sort(&maintenanceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&maintenanceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_dbconn_lock_ids_pk(db, "maintenances", "maintenanceid", &maintenanceids);

		zbx_vector_uint64_sort(&cep_ruleids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&cep_ruleids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_dbconn_lock_ids_pk(db, "cep_rule", "cep_ruleid", &cep_ruleids);

		zbx_dbconn_prepare_insert(db, &db_insert_es, "event_suppress", "event_suppressid",
				"eventid", "maintenanceid", "cep_ruleid", "suppress_until", (char *)NULL);

		zbx_dbconn_prepare_insert(db, &db_insert_ack, "acknowledges", "acknowledgeid",
				"eventid", "clock", "action", "suppress_until", "maintenanceid", (char *)NULL);

		for (int i = 0; i < events.values_num; i++)
		{
			zbx_cep_event_t	*event = events.values[i];

			for (int j = 0; j < event->suppress.values_num; j++)
			{
				zbx_db_event_suppress_t	*suppress = &event->suppress.values[j];
				zbx_vector_uint64_t	*fk_ids;
				zbx_uint64_t		fk_id = 0;

				if (0 != suppress->maintenanceid)
				{
					fk_ids = &maintenanceids;
					fk_id = suppress->maintenanceid;
				}
				else if (0 != suppress->cep_ruleid)
				{
					fk_ids = &cep_ruleids;
					fk_id = suppress->cep_ruleid;
				}
				else
				{
					THIS_SHOULD_NEVER_HAPPEN_MSG("event suppress data without actor id");
					continue;
				}

				if (FAIL == zbx_vector_uint64_bsearch(fk_ids, fk_id, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
					continue;

				zbx_db_insert_add_values(&db_insert_es, __UINT64_C(0), event->eventid,
						suppress->maintenanceid, suppress->cep_ruleid, suppress->until);

				if (0 == suppress->cep_ruleid)
				{
					zbx_db_insert_add_values(&db_insert_ack, __UINT64_C(0), event->eventid, now,
							ZBX_PROBLEM_UPDATE_MAINTENANCE_SUPPRESS, suppress->until,
							suppress->maintenanceid);
				}
			}
		}
	}

	if (SUCCEED == zbx_db_insert_is_prepared(&db_insert_es))
	{
		suppress_num = zbx_db_insert_get_row_count(&db_insert_es);
		zbx_db_insert_autoincrement(&db_insert_es, "event_suppressid");
		zbx_db_insert_execute(&db_insert_es);
		zbx_db_insert_clean(&db_insert_es);

		zbx_db_insert_autoincrement(&db_insert_ack, "acknowledgeid");
		zbx_db_insert_execute(&db_insert_ack);
		zbx_db_insert_clean(&db_insert_ack);
	}

	zbx_vector_uint64_destroy(&maintenanceids);
	zbx_vector_uint64_destroy(&cep_ruleids);
	zbx_vector_cep_event_ptr_destroy(&events);

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
static void	cep_db_write_trigger_rtdata(zbx_dbconn_t *db, const zbx_vector_mw_task_ptr_t *tasks,
		zbx_vector_trigger_diff_ptr_t *trigger_diffs)
{
	zbx_vector_cep_object_value_t	updates;
	int				updates_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_cep_object_value_create(&updates);

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

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
 * Purpose: mark open CEP events as committed to the database                 *
 *                                                                            *
 * Parameters: tasks - [IN] committed tasks                                   *
 *                                                                            *
 ******************************************************************************/
void	cep_db_mark_committed(const zbx_vector_mw_task_ptr_t *tasks)
{
	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

		if (CEP_EVENT_OPEN != task->event_op)
			continue;


		if (NULL != task->hevent)
			cep_event_handle_set_committed(task->hevent);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush events created by tasks to database                         *
 *                                                                            *
 * Parameters: db    - [IN] database connection                               *
 *             tasks - [IN] list of tasks containing events                   *
 *             trigger_diffs - [OUT] trigger changeset to be applied to cache *
 *                                                                            *
 ******************************************************************************/
void	cep_db_flush_events(zbx_dbconn_t *db, const zbx_vector_mw_task_ptr_t *tasks,
		zbx_vector_trigger_diff_ptr_t *trigger_diffs)
{
	zbx_vector_uint64_t	ref_eventids;

	zbx_vector_uint64_create(&ref_eventids);

	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

		if (CEP_EVENT_CLOSE == task->event_op)
		{
			for (int j = 0; j < task->eventids.values_num; j++)
				zbx_vector_uint64_append(&ref_eventids, task->eventids.values[j]);
		}
		else if (CEP_EVENT_OPEN == task->event_op)
		{
			if (EVENT_SOURCE_TRIGGERS == task->db_event->source &&
					EVENT_OBJECT_TRIGGER == task->db_event->object)
			{
				if (0 != task->event->cause_eventid)
					zbx_vector_uint64_append(&ref_eventids, task->event->cause_eventid);
			}
		}
	}

	zbx_vector_uint64_sort(&ref_eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&ref_eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	cep_db_write_events(db, tasks);

	zbx_dbconn_lock_ids_pk(db, "events", "eventid", &ref_eventids);

	cep_db_write_problems(db, tasks, &ref_eventids);
	cep_db_write_symptoms(db, tasks, &ref_eventids);
	cep_db_write_event_recovery(db, tasks, &ref_eventids);
	cep_db_write_event_suppress(db, tasks);
	cep_db_write_trigger_rtdata(db, tasks, trigger_diffs);

	zbx_vector_uint64_destroy(&ref_eventids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process CEP task actions for created events                       *
 *                                                                            *
 * Parameters: db     - [IN] database connection                              *
 *             tasks  - [IN] list of tasks containing events and actions      *
 *             escalations - [OUT] created escalations                        *
 *                                                                            *
 ******************************************************************************/
void	cep_db_process_actions(zbx_dbconn_t *db, const zbx_vector_mw_task_ptr_t *tasks,
	zbx_vector_escalation_new_ptr_t *escalations)
{
	zbx_vector_uint64_pair_t	event_recovery;
	zbx_vector_db_event_t		events;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_db_event_create(&events);
	zbx_vector_db_event_reserve(&events, (size_t)tasks->values_num);
	zbx_vector_uint64_pair_create(&event_recovery);

	for (int i = 0; i < tasks->values_num; i++)
	{
		zbx_uint64_pair_t		pair;
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

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
	zbx_vector_uint64_pair_sort(&event_recovery, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	process_actions(db, &events, &event_recovery, escalations);

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
void	cep_db_export_events(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks,
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
		const zbx_cep_task_event_t	*task = (const zbx_cep_task_event_t *)tasks->values[i];

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
void	cep_db_add_tags(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_vector_uint64_t		eventids;
	zbx_vector_event_tags_ptr_t	db_tags, event_tags;
	int				event_tags_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_uint64_create(&eventids);
	zbx_vector_event_tags_ptr_create(&db_tags);
	zbx_vector_event_tags_ptr_create(&event_tags);

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

		zbx_dbconn_lock_ids_pk(db, "events", "eventid", &eventids);
		zbx_db_write_tags(db, &event_tags, "events", "eventid", "event_tag", "eventtagid", &eventids);

		zbx_dbconn_lock_ids_pk(db, "problem", "eventid", &eventids);
		zbx_db_write_tags(db, &event_tags, "problem", "eventid", "problem_tag", "problemtagid", &eventids);

		zbx_vector_uint64_clear(&eventids);
		zbx_vector_event_tags_ptr_clear(&event_tags);
	}
	while (ZBX_DB_DOWN == zbx_dbconn_commit(db));

	zbx_dbconn_pool_release_connection(dbpool, db);

	zbx_vector_uint64_destroy(&eventids);
	zbx_vector_event_tags_ptr_destroy(&event_tags);
	zbx_vector_event_tags_ptr_destroy(&db_tags);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: advance through the cached CEP events and synchronize their       *
 *          tags with the database, up to and including the event that        *
 *          matches eventid                                                   *
 *                                                                            *
 * Parameters: table       - [IN] name of the tag table used for update       *
 *                           statements                                       *
 *             field       - [IN] name of the eventid column in the tag       *
 *                           table                                            *
 *             events      - [IN] array of cached CEP events, sorted by       *
 *                           eventid                                          *
 *             events_num  - [IN] number of elements in events                *
 *             event_index - [IN] index into events to start processing from  *
 *             eventid     - [IN] event identifier of the database row to     *
 *                           match against                                    *
 *             db_tags     - [IN/OUT] tag rows read from the database for     *
 *                           the matched event, merged with the cached        *
 *                           tags in place; may be NULL                       *
 *             db          - [IN] database connection                         *
 *             db_insert   - [IN/OUT] insert batch for new tag rows           *
 *             sql         - [IN/OUT] buffer used to accumulate update        *
 *                           statements                                       *
 *             sql_alloc   - [IN/OUT] allocated size of sql                   *
 *             sql_offset  - [IN/OUT] current length of sql                   *
 *             deleteids   - [OUT] deleted tag ids                            *
 *                                                                            *
 * Return value: index into events past the processed event, or               *
 *               events_num if no more events remain                          *
 *                                                                            *
 ******************************************************************************/
static int	cep_db_sync_event_tags(const char *table, const char *field, const zbx_cep_event_t **events,
		int events_num, int event_index, zbx_uint64_t eventid, zbx_sync_rowset_t *db_tags, zbx_dbconn_t *db,
		zbx_db_insert_t *db_insert, char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_vector_uint64_t *deleteids)
{
	zbx_sync_rowset_t	cache_tags;

	while (event_index < events_num)
	{
		if (events[event_index]->eventid > eventid)
			return event_index;

		if (events[event_index]->eventid == eventid)
			break;

		/* there are no tags for this event in database, insert them */
		for (int i = 0; i < events[event_index]->tags.values_num; i++)
		{
			zbx_tag_t	*tag = &events[event_index]->tags.values[i];

			zbx_db_insert_add_values(db_insert, __UINT64_C(0), events[event_index]->eventid, tag->tag,
					tag->value);
		}
		event_index++;
	}

	if (event_index == events_num || NULL == db_tags)
		return event_index;

	zbx_sync_rowset_init(&cache_tags, 2);

	for (int i = 0; i < events[event_index]->tags.values_num; i++)
	{
		zbx_tag_t	*tag = &events[event_index]->tags.values[i];

		zbx_sync_rowset_add_row(&cache_tags, NULL, tag->tag, tag->value);
	}

	zbx_sync_rowset_merge(db_tags, &cache_tags);

	for (int i = 0; i < db_tags->rows.values_num; i++)
	{
		zbx_sync_row_t	*row = db_tags->rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
		{
			zbx_vector_uint64_append(deleteids, row->rowid);
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), eventid, row->cols[0], row->cols[1]);
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
		{
			const char	*fields[] = {"tag", "value"};
			char		delim = ' ';

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "update %s set", table);

			for (int j = 0; j < row->cols_num; j++)
			{
				if (0 == (row->flags & (UINT32_C(1) << j)))
					continue;

				char	*value_esc;

				value_esc = zbx_dbconn_dyn_escape_string(db, row->cols[j]);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%s='%s'", delim, fields[j],
						value_esc);
				zbx_free(value_esc);

				delim = ',';
			}

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where %s=" ZBX_FS_UI64 ";\n",  field,
					row->rowid);
			zbx_dbconn_execute_overflowed_sql(db, sql, sql_alloc, sql_offset, NULL);
		}
	}
	zbx_sync_rowset_clear(&cache_tags);

	return ++event_index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: synchronize cached event tag changes to database                  *
 *                                                                            *
 * Parameters: db         - [IN] database connection                          *
 *             table      - [IN] name of the tag table to synchronize         *
 *             field      - [IN] name of the eventid column in the tag        *
 *                          table                                             *
 *             events     - [IN] array of cached CEP events, sorted by        *
 *                          eventid                                           *
 *             events_num - [IN] number of elements in events                 *
 *             eventids   - [IN] event identifiers to select tag rows for     *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_sync_event_tags_table(zbx_dbconn_t *db, const char *table, const char *field,
		const zbx_cep_event_t **events, int events_num, const zbx_vector_uint64_t *eventids)
{
	zbx_db_result_t				result;
	zbx_db_row_t				row;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t				eventid, last_eventid = 0;
	int					event_index = 0;
	zbx_sync_rowset_t			db_tags;
	zbx_vector_uint64_t			deleteids;
	zbx_db_insert_t				db_insert;

	if (0 == events_num || 0 == eventids->values_num)
		return;

	zbx_vector_uint64_create(&deleteids);
	zbx_sync_rowset_init(&db_tags, 2);
	zbx_dbconn_prepare_insert(db, &db_insert, table, field, "eventid", "tag", "value", NULL);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select %s,eventid,tag,value from %s where",
			field, table);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids->values, eventids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by eventid");

	result = zbx_dbconn_select(db, "%s", sql);
	sql_offset = 0;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[1]);

		if (eventid != last_eventid)
		{
			if (0 != last_eventid)
			{
				event_index = cep_db_sync_event_tags(table, field, events, events_num, event_index,
						last_eventid, &db_tags, db, &db_insert, &sql, &sql_alloc, &sql_offset,
						&deleteids);
				zbx_sync_rowset_clear(&db_tags);
				zbx_sync_rowset_init(&db_tags, 2);
			}
			last_eventid = eventid;
		}

		zbx_sync_rowset_add_row(&db_tags, row[0], row[2], row[3]);
	}
	zbx_db_free_result(result);

	event_index = cep_db_sync_event_tags(table, field, events, events_num, event_index, last_eventid, &db_tags,
			db, &db_insert, &sql, &sql_alloc, &sql_offset, &deleteids);

	(void)cep_db_sync_event_tags(table, field, events, events_num, event_index, ZBX_MAX_UINT64, NULL, db,
			&db_insert, &sql, &sql_alloc, &sql_offset, &deleteids);

	zbx_dbconn_flush_overflowed_sql(db, sql, sql_offset);

	if (0 != deleteids.values_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where", table);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, field, deleteids.values,
				deleteids.values_num);
		zbx_dbconn_execute(db, "%s", sql);
	}

	zbx_db_insert_autoincrement(&db_insert, field);
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(sql);

	zbx_sync_rowset_clear(&db_tags);
	zbx_vector_uint64_destroy(&deleteids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: synchronize event and problem tag changes to database for the     *
 *          given CEP events                                                  *
 *                                                                            *
 * Parameters: db    - [IN] database connection                               *
 *             htags - [IN] handles of CEP events whose tags need to be       *
 *                     synchronized                                           *
 *             eventids - [IN] locked events                                  *
 *             problemids - [IN] locked problems                              *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_update_event_tags(zbx_dbconn_t *db, const zbx_vector_cep_event_handle_t *htags,
		const zbx_vector_uint64_t *eventids, const zbx_vector_uint64_t *problemids)
{
	zbx_cep_event_t		**events, **problems;
	int			events_num = 0, problems_num = 0;
	zbx_cep_t		*cep;

	events = (zbx_cep_event_t **)zbx_malloc(NULL, sizeof(zbx_cep_event_t *) * htags->values_num);
	problems = (zbx_cep_event_t **)zbx_malloc(NULL, sizeof(zbx_cep_event_t *) * htags->values_num);

	cep_cache_acquire(&cep);
	cep_get_events_by_handles(cep, htags->values, htags->values_num, events);
	cep_cache_release(&cep);

	for (int i = 0; i < htags->values_num; i++)
	{
		if (NULL == events[i])
			continue;

		events[events_num++] = events[i];

		if (FAIL != zbx_vector_uint64_bsearch(problemids, events[i]->eventid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			problems[problems_num++] = events[i];
	}

	cep_db_sync_event_tags_table(db, "event_tag", "eventtagid",  (const zbx_cep_event_t **)events, events_num,
			eventids);
	cep_db_sync_event_tags_table(db, "problem_tag", "problemtagid",  (const zbx_cep_event_t **)problems,
			problems_num, problemids);

	for (int i = 0; i < events_num; i++)
	{
		if (NULL != events[i])
			zbx_cep_event_release(events[i]);
	}

	zbx_free(problems);
	zbx_free(events);
}

typedef struct
{
	zbx_uint64_t	event_suppressid;
	zbx_uint64_t	ruleid;
	int		suppress_until;
}
zbx_cep_event_suppress_t;

ZBX_VECTOR_DECL(cep_event_suppress, zbx_cep_event_suppress_t)
ZBX_VECTOR_IMPL(cep_event_suppress, zbx_cep_event_suppress_t)

static int	cep_event_suppress_compare(const void *a1, const void *a2)
{
	const zbx_cep_event_suppress_t	*s1 = (const zbx_cep_event_suppress_t *)a1;
	const zbx_cep_event_suppress_t	*s2 = (const zbx_cep_event_suppress_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(s1->ruleid, s2->ruleid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: insert or update event suppress records in the database to        *
 *          match an event's suppress data                                    *
 *                                                                            *
 * Parameters: db          - [IN] database connection                         *
 *             sql         - [IN/OUT] sql statement buffer                    *
 *             sql_alloc   - [IN/OUT] allocated size of sql                   *
 *             sql_offset  - [IN/OUT] used size of sql                        *
 *             db_insert   - [IN/OUT] insert accumulator for new suppress     *
 *                           records                                          *
 *             event       - [IN] event to sync suppress records for          *
 *             suppress    - [IN] existing suppress records for the event     *
 *             deleteids   - [IN] event suppress ids to delete                *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_update_event_suppress(zbx_dbconn_t *db, char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_db_insert_t *db_insert, const zbx_cep_event_t *event,
		const zbx_vector_cep_event_suppress_t *suppress, zbx_vector_uint64_t *deleteids)
{
	for (int i = 0; i < event->suppress.values_num; i++)
	{
		const zbx_db_event_suppress_t	*sup = &event->suppress.values[i];
		zbx_cep_event_suppress_t	sup_local;
		int				j;

		if (0 == sup->cep_ruleid)
			continue;

		sup_local.ruleid = sup->cep_ruleid;

		if (FAIL == (j = zbx_vector_cep_event_suppress_search(suppress, sup_local, cep_event_suppress_compare)))
		{
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), event->eventid, sup_local.ruleid,
					sup->until);
		}
		else
		{
			if (suppress->values[j].suppress_until != sup->until)
			{
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
						"update event_suppress set suppress_until=%d where event_suppressid="
						ZBX_FS_UI64 ";\n", sup->until,  suppress->values[j].event_suppressid);
				zbx_dbconn_execute_overflowed_sql(db, sql, sql_alloc, sql_offset, NULL);
			}
		}
	}

	for (int i = 0; i < suppress->values_num; i++)
	{
		zbx_db_event_suppress_t	db_sup_local = {.cep_ruleid = suppress->values[i].ruleid};

		if (FAIL == zbx_vector_db_event_suppress_search(&event->suppress, db_sup_local,
				db_event_suppress_compare))
		{
			zbx_vector_uint64_append(deleteids, suppress->values[i].event_suppressid);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync suppress records in the database for a set of events         *
 *          referenced by handles                                             *
 *                                                                            *
 * Parameters: db        - [IN] database connection                           *
 *             hsuppress - [IN] handles of events to sync suppress records for*
 *                                                                            *
 ******************************************************************************/
static void	cep_db_update_events_suppress(zbx_dbconn_t *db, const zbx_vector_cep_event_handle_t *hsuppress)
{
	zbx_vector_uint64_t		eventids, deleteids;
	zbx_cep_event_t			**events;
	int				events_num = 0, index = 0;
	zbx_cep_t			*cep;
	zbx_db_row_t			row;
	zbx_db_result_t			result;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_cep_event_suppress_t	suppress;
	zbx_db_insert_t			db_insert;

	zbx_vector_uint64_create(&eventids);
	zbx_vector_uint64_create(&deleteids);
	zbx_vector_uint64_reserve(&eventids, (size_t)hsuppress->values_num);

	zbx_vector_cep_event_suppress_create(&suppress);

	events = (zbx_cep_event_t **)zbx_malloc(NULL, sizeof(zbx_cep_event_t *) * hsuppress->values_num);

	cep_cache_acquire(&cep);
	cep_get_events_by_handles(cep, hsuppress->values, hsuppress->values_num, events);
	cep_cache_release(&cep);

	for (int i = 0; i < hsuppress->values_num; i++)
	{
		if (NULL == events[i])
			continue;

		events[events_num++] = events[i];
		zbx_vector_uint64_append(&eventids, events[i]->eventid);
	}

	zbx_dbconn_prepare_insert(db, &db_insert, "event_suppress", "event_suppressid", "eventid", "cep_ruleid",
		"suppress_until", NULL);

	if (0 < eventids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select event_suppressid,eventid,cep_ruleid,suppress_until"
				" from event_suppress"
				" where cep_ruleid is not null and");

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid",  eventids.values,
				eventids.values_num);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by eventid");

		result = zbx_dbconn_select(db, "%s", sql);
		sql_offset = 0;

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_cep_event_suppress_t	sup_local;
			zbx_uint64_t	eventid;

			ZBX_STR2UINT64(eventid, row[1]);

			while (events[index]->eventid != eventid)
			{
				cep_db_update_event_suppress(db, &sql, &sql_alloc, &sql_offset, &db_insert,
						events[index], &suppress, &deleteids);
				zbx_vector_cep_event_suppress_clear(&suppress);
				index++;
			}

			ZBX_STR2UINT64(sup_local.event_suppressid, row[0]);
			ZBX_STR2UINT64(sup_local.ruleid, row[2]);
			sup_local.suppress_until = atoi(row[3]);
			zbx_vector_cep_event_suppress_append(&suppress, sup_local);
		}
		zbx_db_free_result(result);
	}

	for (;index < events_num; index++)
	{
		cep_db_update_event_suppress(db, &sql, &sql_alloc, &sql_offset, &db_insert,
				events[index], &suppress, &deleteids);
		zbx_vector_cep_event_suppress_clear(&suppress);
	}

	zbx_dbconn_flush_overflowed_sql(db, sql, sql_offset);

	if (0 != deleteids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from event_suppress where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "event_suppressid", deleteids.values,
				deleteids.values_num);
		zbx_dbconn_execute(db, "%s", sql);
	}

	zbx_db_insert_autoincrement(&db_insert, "event_suppressid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(sql);
	zbx_vector_cep_event_suppress_destroy(&suppress);
	zbx_vector_uint64_destroy(&deleteids);
	zbx_vector_uint64_destroy(&eventids);

	for (int i = 0; i < events_num; i++)
	{
		if (NULL != events[i])
			zbx_cep_event_release(events[i]);
	}
	zbx_free(events);
}

typedef struct
{
	zbx_cep_event_handle_t	hevent;
	zbx_uint32_t		flags;
}
zbx_cep_event_sync_t;

ZBX_VECTOR_DECL(cep_event_sync, zbx_cep_event_sync_t)
ZBX_VECTOR_IMPL(cep_event_sync, zbx_cep_event_sync_t)

/******************************************************************************
 *                                                                            *
 * Purpose: synchronize changed CEP event fields to the events and problem    *
 *          tables                                                            *
 *                                                                            *
 * Parameters: db   - [IN] database connection                                *
 *             sync - [IN] event handles with flags indicating which          *
 *                    fields have changed                                     *
 *                                                                            *
 * Comments: Handles that no longer resolve to a cached event are skipped.    *
 *                                                                            *
 ******************************************************************************/
static void	cep_db_sync_event(zbx_dbconn_t *db, const zbx_vector_cep_event_sync_t *sync)
{
	zbx_cep_event_t			**events;
	char				*sql_events = NULL, *sql_problem = NULL;
	size_t				sql_events_alloc = 0, sql_events_offset = 0, sql_problem_alloc = 0,
					sql_problem_offset = 0;
	zbx_vector_cep_event_handle_t	hevents;
	zbx_cep_t			*cep;

	zbx_vector_cep_event_handle_create(&hevents);
	zbx_vector_cep_event_handle_reserve(&hevents, (size_t)sync->values_num);
	for (int i = 0; i < sync->values_num; i++)
		zbx_vector_cep_event_handle_append(&hevents, sync->values[i].hevent);

	events = (zbx_cep_event_t **)zbx_malloc(NULL, sizeof(zbx_cep_event_t *) * sync->values_num);

	cep_cache_acquire(&cep);
	cep_get_events_by_handles(cep, hevents.values, hevents.values_num, events);
	cep_cache_release(&cep);

	for (int i = 0; i < sync->values_num; i++)
	{
		char	delim = ' ';

		if (NULL == events[i])
			continue;

		zbx_strcpy_alloc(&sql_events, &sql_events_alloc, &sql_events_offset, "update events set");
		zbx_strcpy_alloc(&sql_problem, &sql_problem_alloc, &sql_problem_offset, "update problem set");

		if (0 != (sync->values[i].flags & CEP_SYNC_EVENT_SEVERITY))
		{
			zbx_snprintf_alloc(&sql_events, &sql_events_alloc, &sql_events_offset,
					"%cseverity=%d", delim, events[i]->severity);
			zbx_snprintf_alloc(&sql_problem, &sql_problem_alloc, &sql_problem_offset,
					"%cseverity=%d", delim, events[i]->severity);

			delim = ',';
		}

		if (0 != (sync->values[i].flags & CEP_SYNC_EVENT_NAME))
		{
			char	*name_esc = zbx_dbconn_dyn_escape_string(db, events[i]->name);

			zbx_snprintf_alloc(&sql_events, &sql_events_alloc, &sql_events_offset,
					"%cname='%s'", delim, name_esc);
			zbx_snprintf_alloc(&sql_problem, &sql_problem_alloc, &sql_problem_offset,
					"%cname='%s'", delim, name_esc);

			zbx_free(name_esc);
		}

		zbx_snprintf_alloc(&sql_events, &sql_events_alloc, &sql_events_offset,
				" where eventid=" ZBX_FS_UI64 ";\n", events[i]->eventid);
		zbx_snprintf_alloc(&sql_problem, &sql_problem_alloc, &sql_problem_offset,
				" where eventid=" ZBX_FS_UI64 ";\n", events[i]->eventid);

		zbx_dbconn_execute_overflowed_sql(db, &sql_events, &sql_events_alloc, &sql_events_offset, NULL);
		zbx_dbconn_execute_overflowed_sql(db, &sql_problem, &sql_problem_alloc, &sql_problem_offset, NULL);

		zbx_cep_event_release(events[i]);
	}

	(void)zbx_dbconn_flush_overflowed_sql(db, sql_events, sql_events_offset);
	(void)zbx_dbconn_flush_overflowed_sql(db, sql_problem, sql_problem_offset);

	zbx_free(sql_events);
	zbx_free(sql_problem);
	zbx_free(events);
	zbx_vector_cep_event_handle_destroy(&hevents);
}

/******************************************************************************
 *                                                                            *
 * Purpose: synchronize event changes to the database                         *
 *                                                                            *
 * Parameters: dbpool - [IN] database connection pool                         *
 *             tasks  - [IN] sync tasks with changed events, sorted by eventid*
 *                           of the affected event                            *
 *                                                                            *
 ******************************************************************************/
void	cep_db_sync_events(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_vector_cep_event_handle_t	htags, hsuppress;
	zbx_vector_cep_event_sync_t	sync;
	zbx_vector_uint64_t		eventids, problemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_cep_event_sync_create(&sync);
	zbx_vector_cep_event_handle_create(&htags);
	zbx_vector_cep_event_handle_create(&hsuppress);
	zbx_vector_uint64_create(&eventids);
	zbx_vector_uint64_create(&problemids);

	zbx_dbconn_t	*db = zbx_dbconn_pool_acquire_connection(dbpool);

	do
	{
		zbx_cep_event_handle_t	hsync_last = NULL;

		zbx_dbconn_begin(db);

		for (int i = 0; i < tasks->values_num; i++)
		{
			const zbx_cep_task_sync_event_t	*task = (const zbx_cep_task_sync_event_t *)tasks->values[i];

			zbx_vector_uint64_append(&eventids, zbx_cep_event_handle_eventid(task->hevent));
		}

		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_vector_uint64_append_array(&problemids, eventids.values, eventids.values_num);

		zbx_dbconn_lock_ids_pk(db, "events", "eventid", &eventids);
		zbx_dbconn_lock_ids_pk(db, "problem", "eventid", &problemids);

		for (int i = 0; i < tasks->values_num; i++)
		{
			const zbx_cep_task_sync_event_t	*task = (const zbx_cep_task_sync_event_t *)tasks->values[i];

			if (FAIL == zbx_vector_uint64_bsearch(&eventids, zbx_cep_event_handle_eventid(task->hevent),
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				continue;
			}

			if (0 != (task->flags & (CEP_SYNC_EVENT_SEVERITY | CEP_SYNC_EVENT_NAME)))
			{
				if (NULL != hsync_last && hsync_last == task->hevent)
				{
					sync.values[sync.values_num - 1].flags |= task->flags;
				}
				else
				{
					zbx_cep_event_sync_t	sync_local = {.hevent = task->hevent,
							.flags = task->flags};

					zbx_vector_cep_event_sync_append(&sync, sync_local);
					hsync_last = task->hevent;
				}
			}

			if (0 != (task->flags & CEP_SYNC_EVENT_TAGS))
				zbx_vector_cep_event_handle_append(&htags, task->hevent);

			if (0 != (task->flags & (CEP_SYNC_EVENT_SUPPRESS | CEP_SYNC_EVENT_UNSUPPRESS)))
				zbx_vector_cep_event_handle_append(&hsuppress, task->hevent);
		}

		zbx_vector_cep_event_handle_uniq(&htags, cep_event_handle_compare);
		zbx_vector_cep_event_handle_uniq(&hsuppress, cep_event_handle_compare);

		if (0 != sync.values_num)
			cep_db_sync_event(db, &sync);

		if (0 != htags.values_num)
			cep_db_update_event_tags(db, &htags, &eventids, &problemids);

		if (0 != hsuppress.values_num)
			cep_db_update_events_suppress(db, &hsuppress);

		zbx_vector_uint64_clear(&problemids);
		zbx_vector_uint64_clear(&eventids);
		zbx_vector_cep_event_handle_clear(&hsuppress);
		zbx_vector_cep_event_handle_clear(&htags);
		zbx_vector_cep_event_sync_clear(&sync);
	}
	while (ZBX_DB_DOWN == zbx_dbconn_commit(db));

	zbx_dbconn_pool_release_connection(dbpool, db);

	zbx_vector_uint64_destroy(&problemids);
	zbx_vector_uint64_destroy(&eventids);
	zbx_vector_cep_event_handle_destroy(&hsuppress);
	zbx_vector_cep_event_handle_destroy(&htags);
	zbx_vector_cep_event_sync_destroy(&sync);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: insert acknowledge records into the database for CEP              *
 *          acknowledge tasks                                                 *
 *                                                                            *
 * Parameters: dbpool - [IN] database connection pool                         *
 *             tasks  - [IN] acknowledge tasks to write                       *
 *                                                                            *
 ******************************************************************************/
void	cep_db_add_acknowledges(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_vector_uint64_t			eventids;
	const zbx_cep_task_acknowledge_t	*task;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_dbconn_t	*db = zbx_dbconn_pool_acquire_connection(dbpool);

	zbx_vector_uint64_create(&eventids);

	do
	{
		for (int i = 0; i < tasks->values_num; i++)
		{
			task = (const zbx_cep_task_acknowledge_t *)tasks->values[i];

			zbx_vector_uint64_append(&eventids, task->eventid);
		}

		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_dbconn_begin(db);

		zbx_dbconn_lock_ids_pk(db, "events", "eventid", &eventids);

		if (0 != eventids.values_num)
		{
			zbx_db_insert_t	db_insert;
			int		now = (int)time(NULL);

			zbx_dbconn_prepare_insert(db, &db_insert, "acknowledges", "acknowledgeid", "eventid", "clock",
					"action", "cep_ruleid", "details", NULL);

			for (int i = 0; i < tasks->values_num; i++)
			{
				task = (const zbx_cep_task_acknowledge_t *)tasks->values[i];

				if (FAIL == zbx_vector_uint64_bsearch(&eventids, task->eventid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					continue;
				}

				zbx_db_insert_add_values(&db_insert, __UINT64_C(0), task->eventid, now,
						ZBX_PROBLEM_UPDATE_CEP, task->ruleid, task->details.buffer);
			}

			zbx_db_insert_autoincrement(&db_insert, "acknowledgeid");
			zbx_db_insert_execute(&db_insert);
			zbx_db_insert_clean(&db_insert);
		}
		zbx_vector_uint64_clear(&eventids);
	}
	while (ZBX_DB_DOWN == zbx_dbconn_commit(db));

	zbx_dbconn_pool_release_connection(dbpool, db);

	zbx_vector_uint64_destroy(&eventids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update CEP rule error messages in the database and cache          *
 *                                                                            *
 * Parameters: dbpool - [IN] database connection pool                         *
 *             tasks  - [IN] rule error tasks to write                        *
 *                                                                            *
 ******************************************************************************/
void	cep_db_update_rule_errors(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_dbconn_t	*db = zbx_dbconn_pool_acquire_connection(dbpool);

	do
	{
		size_t	sql_offset = 0;

		zbx_dbconn_begin(db);

		for (int i = 0; i < tasks->values_num; i++)
		{
			const zbx_cep_task_rule_error_t	*task = (const zbx_cep_task_rule_error_t *)tasks->values[i];
			char				*error_dyn;

			error_dyn = zbx_dbconn_dyn_escape_string(db, ZBX_NULL2EMPTY_STR(task->error));
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update cep_rule_rtdata set error='%s' where cep_ruleid=" ZBX_FS_UI64 ";\n",
					error_dyn, task->ruleid);
			zbx_free(error_dyn);

			zbx_dbconn_execute_overflowed_sql(db, &sql, &sql_alloc, &sql_offset, NULL);
		}
		(void)zbx_dbconn_flush_overflowed_sql(db, sql, sql_offset);
	}
	while (ZBX_DB_DOWN == zbx_dbconn_commit(db));

	zbx_dbconn_pool_release_connection(dbpool, db);
	zbx_free(sql);

	zbx_cep_t	*cep;

	cep_cache_acquire(&cep);
	for (int i = 0; i < tasks->values_num; i++)
	{
		const zbx_cep_task_rule_error_t	*task = (const zbx_cep_task_rule_error_t *)tasks->values[i];

		cep_rule_set_error(cep, task->ruleid, task->error);
	}
	cep_cache_release(&cep);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

typedef struct
{
	zbx_cep_window_t			*window;
	zbx_vector_cep_window_sync_entry_t	log;
}
zbx_cep_window_sync_t;

ZBX_VECTOR_LITE_DECL(cep_window_sync, zbx_cep_window_sync_t)
ZBX_VECTOR_LITE_IMPL(cep_window_sync, zbx_cep_window_sync_t)

static int	cep_window_sync_entry_compare(const void *a1, const void *a2)
{
	const zbx_cep_window_sync_entry_t	*e1 = (const zbx_cep_window_sync_entry_t *)a1;
	const zbx_cep_window_sync_entry_t	*e2 = (const zbx_cep_window_sync_entry_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(e1->eventid, e2->eventid);
	ZBX_RETURN_IF_NOT_EQUAL(e1->type, e2->type);

	return 0;
}

static int	cep_db_sync_window_create(zbx_dbconn_t *db, zbx_db_insert_t *db_insert_group,
		zbx_cep_window_t *window)
{
	zbx_cep_window_ref_t	*ref;
	int			ret = FAIL;

	if (SUCCEED != zbx_db_insert_is_prepared(db_insert_group))
	{
		zbx_dbconn_prepare_insert(db, db_insert_group, "cep_window", "cep_windowid", "cep_ruleid", "group_by",
				"groupid", "hostid", "tags", "tags_value", "created_at", NULL);
	}

	cep_window_lock(window);

	if (NULL != (ref = window->ref))
	{
		zbx_db_insert_add_values(db_insert_group, window->windowid, ref->ruleid, ref->group_by,
				ref->hostgroupid, ref->hostid, ZBX_NULL2EMPTY_STR(ref->tag),
				ZBX_NULL2EMPTY_STR(ref->tag_value), (int)window->time_created);
		ret = SUCCEED;
	}

	cep_window_unlock(window);

	return ret;
}

static void	cep_db_sync_window_reset(zbx_dbconn_t *db, char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_cep_window_t *window)
{
	/* window creation time is updated for cause-symptom windows when they are being closed by duration */
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "update cep_window set created_at=%d where cep_windowid="
			ZBX_FS_UI64 ";\n", (int)cep_window_get_time_created(window), window->windowid);

	zbx_dbconn_execute_overflowed_sql(db, sql, sql_alloc, sql_offset, NULL);
}

static void	cep_db_window_sync_event_add(zbx_dbconn_t *db, zbx_db_insert_t *db_insert_group_event,
		zbx_uint64_t windowid, zbx_uint64_t eventid, zbx_uint64_t index)
{
	if (SUCCEED != zbx_db_insert_is_prepared(db_insert_group_event))
	{
		zbx_dbconn_prepare_insert(db, db_insert_group_event, "cep_window_event", "cep_windowid", "eventid",
				"event_index", NULL);
	}

	zbx_db_insert_add_values(db_insert_group_event, windowid, eventid, index);
}

static void	cep_db_sync_window(zbx_dbconn_t *db, char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_db_insert_t *db_insert_group, zbx_db_insert_t *db_insert_group_event,
		zbx_vector_uint64_t *delete_windowids, zbx_cep_window_sync_t *sync)
{
	zbx_uint64_t		last_eventid = 0;
	zbx_vector_uint64_t	eventids;

	zbx_vector_uint64_create(&eventids);

	for (int i = 0; i < sync->log.values_num; i++)
	{
		const zbx_cep_window_sync_entry_t	*entry = &sync->log.values[i];

		switch (entry->type)
		{
			case CEP_WINDOW_SYNC_DESTROY:
				zbx_vector_uint64_append(delete_windowids, sync->window->windowid);
				return;
			case CEP_WINDOW_SYNC_CREATE:
				if (FAIL == cep_db_sync_window_create(db, db_insert_group, sync->window))
					return;
				break;
			case CEP_WINDOW_SYNC_RESET:
				cep_db_sync_window_reset(db, sql, sql_alloc, sql_offset, sync->window);
				break;
			case CEP_WINDOW_SYNC_EVENT_REMOVE:
				zbx_vector_uint64_append(&eventids, entry->eventid);
				last_eventid = entry->eventid;
				break;
			case CEP_WINDOW_SYNC_EVENT_ADD:
				if (entry->eventid != last_eventid)
				{
					cep_db_window_sync_event_add(db, db_insert_group_event, sync->window->windowid,
							entry->eventid, entry->index);
				}
				break;
		}
	}

	if (0 != eventids.values_num)
	{
		char	*query = NULL;
		size_t	query_alloc = 0, query_offset = 0;

		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_snprintf_alloc(&query, &query_alloc, &query_offset, "delete from cep_window_event"
				" where cep_windowid=" ZBX_FS_UI64 " and", sync->window->windowid);

		zbx_dbconn_prepare_multiple_query(db, query, "eventid", &eventids, sql, sql_alloc, sql_offset);

		zbx_free(query);
	}

	zbx_vector_uint64_destroy(&eventids);
}

void	cep_db_sync_windows(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_vector_cep_window_sync_t	syncs;
	zbx_cep_window_t		*window = NULL;
	char				*sql = NULL;
	size_t				sql_alloc = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	zbx_vector_cep_window_sync_create(&syncs);

	for (int i = 0; i < tasks->values_num; i++)
	{
		zbx_cep_task_window_sync_t	*task = (zbx_cep_task_window_sync_t *)tasks->values[i];

		if (task->window != window)
		{
			zbx_cep_window_sync_t	sync_local  = {
				.window = task->window
			};

			zbx_vector_cep_window_sync_entry_create(&sync_local.log);
			zbx_vector_cep_window_sync_append(&syncs, sync_local);

			window = task->window;
		}

		cep_window_sync_detach(task->window, &syncs.values[syncs.values_num - 1].log);
	}

	for (int i = 0; i < syncs.values_num; i++)
		zbx_vector_cep_window_sync_entry_sort(&syncs.values[i].log, cep_window_sync_entry_compare);

	zbx_dbconn_t	*db = zbx_dbconn_pool_acquire_connection(dbpool);

	do
	{
		size_t			sql_offset = 0;
		zbx_db_insert_t		db_insert_group = {0}, db_insert_group_event = {0};
		zbx_vector_uint64_t	delete_windowids;

		zbx_vector_uint64_create(&delete_windowids);

		zbx_dbconn_begin(db);

		for (int i = 0; i < syncs.values_num; i++)
		{
			cep_db_sync_window(db, &sql, &sql_alloc, &sql_offset, &db_insert_group, &db_insert_group_event,
				&delete_windowids, &syncs.values[i]);
		}

		if (0 != delete_windowids.values_num)
		{
			zbx_vector_uint64_sort(&delete_windowids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(&delete_windowids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			zbx_dbconn_prepare_multiple_query(db, "delete from cep_window_event where", "cep_windowid",
					&delete_windowids, &sql, &sql_alloc, &sql_offset);

			zbx_dbconn_prepare_multiple_query(db, "delete from cep_window where", "cep_windowid",
					&delete_windowids, &sql, &sql_alloc, &sql_offset);
		}

		zbx_dbconn_flush_overflowed_sql(db, sql, sql_offset);

		if (SUCCEED == zbx_db_insert_is_prepared(&db_insert_group))
		{
			zbx_db_insert_execute(&db_insert_group);
			zbx_db_insert_clean(&db_insert_group);
		}

		if (SUCCEED == zbx_db_insert_is_prepared(&db_insert_group_event))
		{
			zbx_db_insert_execute(&db_insert_group_event);
			zbx_db_insert_clean(&db_insert_group_event);
		}

		zbx_vector_uint64_destroy(&delete_windowids);
	}
	while (ZBX_DB_DOWN == zbx_dbconn_commit(db));

	zbx_dbconn_pool_release_connection(dbpool, db);

	for (int i = 0; i < syncs.values_num; i++)
		zbx_vector_cep_window_sync_entry_destroy(&syncs.values[i].log);
	zbx_vector_cep_window_sync_destroy(&syncs);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
