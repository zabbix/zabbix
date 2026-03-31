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

#include "cep_worker.h"
#include "cep.h"
#include "cep_db.h"
#include "cep_api.h"
#include "cep_queue.h"
#include "cep_task.h"
#include "cep_correlation.h"
#include "zbxcep.h"
#include "zbxcep_client.h"
#include "zbxmw.h"

#include "zbx_item_constants.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxipcservice.h"
#include "zbxrtc.h"
#include "zbxlog.h"
#include "zbxnix.h"
#include "zbxregexp.h"
#include "zbxsupervisor_client.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"

/******************************************************************************
 *                                                                            *
 * Purpose: initialize event processor worker                                 *
 *                                                                            *
 * Parameters: dboool           - [IN] database connection pool               *
 *                                                                            *
 * Return value: created worker                                               *
 *                                                                            *
 ******************************************************************************/
zbx_cep_worker_t	*cep_worker_create( zbx_dbconn_pool_t *dbpool)
{
	zbx_cep_worker_t	*worker;

	worker = (zbx_cep_worker_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_worker_t));
	worker->dbpool = dbpool;

	return worker;
}

/******************************************************************************
 *                                                                            *
 * Purpose: assess trigger-related events received from a client              *
 *                                                                            *
 * Parameters: task - [IN] task containing trigger events to assess           *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_assess_trigger_events(zbx_cep_task_remote_t *task)
{
	zbx_vector_cep_assessment_query_t	queries;
	zbx_cep_t				*cep;

	zbx_vector_cep_assessment_query_create(&queries);

	zbx_cep_deserialize_event_queries(task->message->data, &queries);

	cep_cache_acquire(&cep);
	cep_assess_trigger_events(cep, &queries, task->response);
	cep_cache_release(&cep);

	for (int i = 0; i < queries.values_num; i++)
		zbx_cep_assessment_query_clear(&queries.values[i]);

	zbx_vector_cep_assessment_query_destroy(&queries);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize events and enqueue corresponding event creation tasks *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *             task   - [IN]  remote task containing serialized events        *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_add_events(zbx_cep_worker_t *worker, zbx_cep_task_remote_t *task)
{
	zbx_vector_db_event_t		events;

	zbx_vector_db_event_create(&events);

	zbx_cep_deserialize_events(task->message->data, &events);

	if (0 != events.values_num)
	{
		zbx_vector_mw_task_ptr_t	tasks;

		zbx_vector_mw_task_ptr_create(&tasks);

		for (int i = 0; i < events.values_num; i++)
			zbx_vector_mw_task_ptr_append(&tasks, cep_create_task_event(events.values[i]));

		zbx_mw_queue_lock(worker->base.queue);
		cep_queue_push_batch((zbx_cep_queue_t *)worker->base.queue, &tasks);
		zbx_mw_queue_unlock(worker->base.queue);

		zbx_vector_mw_task_ptr_destroy(&tasks);
	}

	zbx_vector_db_event_destroy(&events);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue task to close a problem based on the specified remote task*
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *             task   - [IN]  remote task containing problem close request    *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_add_close_problem(zbx_cep_worker_t *worker, zbx_cep_task_remote_t *task)
{
	zbx_db_event	*event;
	zbx_mw_task_t	*t;
	zbx_uint64_t	eventid, userid;

	zbx_cep_deserialize_close_problem(task->message->data, &event, &eventid, &userid);
	t = cep_create_task_close_event(event, eventid, userid, 0);

	zbx_mw_queue_lock(worker->base.queue);
	cep_queue_push((zbx_cep_queue_t *)worker->base.queue, t);
	zbx_mw_queue_lock(worker->base.queue);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update event maintenance data according to the specified action   *
 *                                                                            *
 * Parameters: task   - [IN]  remote task containing event data               *
 *             action - [IN]  maintenance operation to apply to events        *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_update_event_maintenances(zbx_cep_task_remote_t *task, zbx_cep_event_op_t action)
{
	zbx_vector_event_maintenance_t	events;
	zbx_cep_t			*cep;
	zbx_vector_cep_event_handle_t	handles;

	zbx_vector_event_maintenance_create(&events);
	zbx_cep_deserialize_event_maintenance(task->message->data, &events);

	zbx_vector_cep_event_handle_create(&handles);
	zbx_vector_cep_event_handle_reserve(&handles, (size_t)events.values_num);

	cep_cache_acquire(&cep);
	cep_update_event_maintenances(cep, &events, action, &handles);
	cep_cache_release(&cep);

	if (0 != handles.values_num && 0 != zbx_dc_local_get_itservices_num())
		cep_post_event_handle_action(handles.values, handles.values_num, action);

	zbx_vector_cep_event_handle_destroy(&handles);
	zbx_vector_event_maintenance_destroy(&events);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update event severities from the specified remote task            *
 *                                                                            *
 * Parameters: task - [IN]  remote task containing event severity updates     *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_update_event_severities(zbx_cep_task_remote_t *task)
{
	zbx_vector_event_severity_t	events;
	zbx_cep_t			*cep;
	zbx_vector_cep_event_handle_t	handles;

	zbx_vector_event_severity_create(&events);

	zbx_cep_deserialize_event_severities(task->message->data, &events);

	zbx_vector_cep_event_handle_create(&handles);
	zbx_vector_cep_event_handle_reserve(&handles, (size_t)events.values_num);

	cep_cache_acquire(&cep);
	cep_update_event_severities(cep, &events, &handles);
	cep_cache_release(&cep);

	if (0 != zbx_dc_local_get_itservices_num())
		cep_post_event_handle_action(handles.values, handles.values_num, CEP_EVENT_UPDATE_SEVERITY);

	zbx_vector_cep_event_handle_destroy(&handles);
	zbx_vector_event_severity_destroy(&events);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add event tag creation tasks from the specified remote task       *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *             task   - [IN]  remote task containing event tag data           *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_add_event_tags(zbx_cep_worker_t *worker, zbx_cep_task_remote_t *task)
{
	zbx_vector_event_tags_t		event_tags;
	zbx_cep_t			*cep;
	zbx_vector_cep_event_handle_t	handles;
	zbx_vector_uint64_t		eventids;

	zbx_vector_event_tags_create(&event_tags);
	zbx_vector_uint64_create(&eventids);

	zbx_deserialize_event_tags(task->message->data, &event_tags);

	zbx_vector_cep_event_handle_create(&handles);
	zbx_vector_cep_event_handle_reserve(&handles, (size_t)event_tags.values_num);

	cep_cache_acquire(&cep);
	cep_add_event_tags(cep, &event_tags, &handles);
	cep_cache_release(&cep);

	zbx_cep_task_add_tags_t	*db_task = (zbx_cep_task_add_tags_t *)cep_create_task_add_tags(&event_tags, &eventids);

	if (0 != handles.values_num)
	{
		zbx_cep_get_eventids_from_handles(handles.values, handles.values_num, &eventids);
		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_event_tags_sort(&event_tags, zbx_event_tags_compare);

		/* remove non trigger event handles from returned handles - no need */
		/* to notify IT service manager about internal event tag changes    */

		zbx_vector_cep_event_update_reserve(&db_task->updates, (size_t)handles.values_num);

		for (int i = 0; i < handles.values_num; i++)
		{
			zbx_event_tags_t	et_local;
			int			index;

			/* there is 1:1 mapping between handles and eventids */
			et_local.eventid = eventids.values[i];

			if (FAIL == (index = zbx_vector_event_tags_bsearch(&event_tags, et_local,
					zbx_event_tags_compare)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (EVENT_SOURCE_TRIGGERS == event_tags.values[index].source)
			{
				zbx_cep_event_update_t	update_local = {
					.handle = handles.values[i],
					.op = CEP_EVENT_ADD_TAG
				};

				zbx_vector_cep_event_update_append(&db_task->updates, update_local);
			}
			else
				zbx_cep_event_handle_release(handles.values[i]);
		}
	}

	zbx_mw_queue_lock(worker->base.queue);
	zbx_mw_queue_push_completed_direct(worker->base.queue, (zbx_mw_task_t *)db_task);
	zbx_mw_queue_unlock(worker->base.queue);

	zbx_vector_uint64_destroy(&eventids);
	zbx_vector_cep_event_handle_destroy(&handles);

	/* tags were copied over to the add tags task */
	zbx_vector_event_tags_destroy(&event_tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue event deletion tasks from the specified remote task       *
 *                                                                            *
 * Parameters: task - [IN]  remote task containing events to delete           *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_delete_events(zbx_cep_task_remote_t *task)
{
	zbx_vector_uint64_t		eventids;
	zbx_vector_cep_event_handle_t	handles;
	zbx_cep_t			*cep;

	zbx_vector_uint64_create(&eventids);
	zbx_cep_deserialize_ids(task->message->data, &eventids);

	zbx_vector_cep_event_handle_create(&handles);
	zbx_vector_cep_event_handle_reserve(&handles, (size_t)eventids.values_num);


	cep_cache_acquire(&cep);
	cep_delete_events(cep, &eventids, &handles);
	cep_cache_release(&cep);

	if (0 != zbx_dc_local_get_itservices_num())
		cep_post_event_handle_action(handles.values, handles.values_num, CEP_EVENT_DELETE);

	zbx_vector_cep_event_handle_destroy(&handles);
	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process a remote task                                             *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *             task   - [IN]  remote task to process                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_task_remote(zbx_cep_worker_t *worker, zbx_cep_task_remote_t *task)
{
	zabbix_log(LOG_LEVEL_DEBUG, "%s() process remote task :%u", __func__, task->message->code);

	switch (task->message->code)
	{
		case ZBX_CEP_ASSESS_TRIGGER_EVENTS:
			cep_worker_assess_trigger_events(task);
			break;
		case ZBX_CEP_ADD_EVENTS:
			cep_worker_add_events(worker, task);
			break;
		case ZBX_CEP_ADD_USER_CLOSE_EVENT:
			cep_worker_add_close_problem(worker, task);
			break;
		case ZBX_CEP_SUPPRESS_EVENTS:
			cep_worker_update_event_maintenances(task, CEP_EVENT_SUPPRESS);
			break;
		case ZBX_CEP_UNSUPPRESS_EVENTS:
			cep_worker_update_event_maintenances(task, CEP_EVENT_UNSUPPRESS);
			break;
		case ZBX_CEP_UPDATE_SEVERITIES:
			cep_worker_update_event_severities(task);
			break;
		case ZBX_CEP_ADD_EVENT_TAGS:
			cep_worker_add_event_tags(worker, task);
			break;
		case ZBX_CEP_DELETE_EVENTS:
			cep_worker_delete_events(task);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a problem event for the specified trigger                  *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *             task   - [IN]  trigger event task describing the problem       *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_open_trigger_event(zbx_cep_worker_t *worker, zbx_cep_task_event_t *task)
{
	zbx_cep_t		*cep;
	zbx_db_event		*db_event = task->db_event;
	zbx_uint64_t		eventid;
	zbx_cep_event_handle_t	h;

	cep_cache_acquire(&cep);
	eventid = cep_open_trigger_event(cep, db_event->objectid, db_event->trigger.type,
			&db_event->trigger.dep_triggerids, &task->obj_value);
	cep_cache_release(&cep);

	if (0 == eventid)
		return;

	db_event->eventid = eventid;

	zbx_cep_event_t	*event = cep_event_create(db_event->eventid, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER,
			db_event->trigger.triggerid, db_event->clock, db_event->ns, TRIGGER_VALUE_PROBLEM,
			db_event->severity, &db_event->tags, db_event->suppress);

	cep_cache_acquire(&cep);
	h = zbx_cep_event_handle_addref(cep_add_event(cep, event));
	cep_cache_release(&cep);

	zbx_vector_mw_task_ptr_t	tasks;
	int				corr_ret;

	zbx_vector_mw_task_ptr_create(&tasks);
	corr_ret = cep_correlate_db_event(db_event, worker->dbpool, &tasks);

	/* 'close new' operations must skip actions for the problem and generated ok event */
	if (0 != (corr_ret & CORRELATION_RESULT_CLOSE_NEW))
		task->action_state = CEP_ACTION_DISABLED;

	if (0 != tasks.values_num)
	{
		zbx_mw_queue_lock(worker->base.queue);
		cep_queue_push_batch((zbx_cep_queue_t *)worker->base.queue, &tasks);
		zbx_mw_queue_unlock(worker->base.queue);
	}

	zbx_vector_mw_task_ptr_destroy(&tasks);

	task->event_op = CEP_EVENT_OPEN;

	if (0 != zbx_dc_local_get_itservices_num() && CEP_ACTION_DISABLED != task->action_state)
	{
		zbx_cep_event_update_t	update_local = {
			.handle = h,
			.op = CEP_EVENT_OPEN
		};

		zbx_vector_cep_event_update_reserve(&task->updates, 1);
		zbx_vector_cep_event_update_append(&task->updates, update_local);
	}
	else
		zbx_cep_event_handle_release(h);

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create OK event to resolve trigger problem events                 *
 *                                                                            *
 * Parameters: db_event  - [IN]  OK event                                     *
 *             r_eventid - [IN]  resolving OK event identifier                *
 *             handles   - [IN]  problem events to resolve                    *
 *             task      - [IN]  trigger event task                           *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_resolve_trigger_events(zbx_db_event *db_event, zbx_uint64_t r_eventid,
		zbx_vector_cep_event_handle_t *handles, zbx_cep_task_event_t *task)
{
	zbx_cep_t	*cep;

	db_event->eventid = r_eventid;

	zbx_cep_event_t	*r_event = cep_event_create(db_event->eventid, EVENT_SOURCE_TRIGGERS,
		EVENT_OBJECT_TRIGGER, db_event->trigger.triggerid, db_event->clock, db_event->ns,
		TRIGGER_VALUE_OK, db_event->severity, &db_event->tags, db_event->suppress);

	cep_cache_acquire(&cep);
	cep_resolve_trigger_events(cep, r_event, handles);
	cep_cache_release(&cep);

	task->obj_value = TRIGGER_VALUE_OK;
	task->event_op = CEP_EVENT_CLOSE;
	zbx_cep_get_eventids_from_handles(handles->values, handles->values_num, &task->eventids);

	if (0 != zbx_dc_local_get_itservices_num() && CEP_ACTION_DISABLED != task->action_state)
	{
		zbx_vector_cep_event_update_reserve(&task->updates, (size_t)handles->values_num);
		for (int i = 0; i < handles->values_num; i++)
		{
			zbx_cep_event_update_t	update_local = {
				.handle = handles->values[i],
				.op = CEP_EVENT_CLOSE
			};

			zbx_vector_cep_event_update_append(&task->updates, update_local);
		}
	}
	else
	{
		for (int i = 0; i < handles->values_num; i++)
			zbx_cep_event_handle_release(handles->values[i]);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: create an OK event to close corresponding problem events          *
 *                                                                            *
 * Parameters: task - [IN]  trigger event task                                *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_close_trigger_event(zbx_cep_task_event_t *task)
{
	zbx_cep_t			*cep;
	zbx_db_event			*db_event = task->db_event;
	zbx_uint64_t			r_eventid;
	zbx_vector_cep_event_handle_t	handles;

	zbx_vector_cep_event_handle_create(&handles);

	cep_cache_acquire(&cep);
	r_eventid = cep_close_trigger_events(cep, db_event->objectid, &db_event->trigger.dep_triggerids,
			db_event->trigger.correlation_mode, db_event->trigger.correlation_tag, &db_event->tags,
			&handles);
	cep_cache_release(&cep);

	if (0 != r_eventid)
		cep_worker_resolve_trigger_events(db_event, r_eventid, &handles, task);

	zbx_vector_cep_event_handle_destroy(&handles);

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create or close a trigger event based on its value                *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *             task   - [IN]  trigger event task to process                   *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_trigger_event(zbx_cep_worker_t *worker, zbx_cep_task_event_t *task)
{
	if (TRIGGER_VALUE_PROBLEM == task->db_event->value)
		cep_worker_open_trigger_event(worker, task);
	else
		cep_worker_close_trigger_event(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a problem event for an internal event                      *
 *                                                                            *
 * Parameters: task - [IN] event task                                         *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_open_internal_event(zbx_cep_task_event_t *task)
{
	zbx_cep_t	*cep;
	zbx_db_event	*db_event = task->db_event;
	zbx_uint64_t	eventid;

	cep_cache_acquire(&cep);
	eventid = cep_open_internal_event(cep, db_event->object, db_event->objectid);
	cep_cache_release(&cep);

	if (0 == eventid)
		return;

	db_event->eventid = eventid;

	/* don't cache tags for internal events since they are not used */
	zbx_cep_event_t	*event = cep_event_create(db_event->eventid, EVENT_SOURCE_INTERNAL, db_event->object,
			db_event->objectid, db_event->clock, db_event->ns, db_event->value, 0, NULL, NULL);

	cep_cache_acquire(&cep);
	(void)cep_add_event(cep, event);
	cep_cache_release(&cep);

	task->event_op = CEP_EVENT_OPEN;

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create an OK event to close an internal problem event             *
 *                                                                            *
 * Parameters: task - [IN]  event task                                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_close_internal_event(zbx_cep_task_event_t *task)
{
	zbx_cep_t	*cep;
	zbx_db_event	*db_event = task->db_event;
	zbx_uint64_t	eventid;

	cep_cache_acquire(&cep);
	eventid = cep_close_internal_event(cep, db_event->object, db_event->objectid, &task->eventids);
	cep_cache_release(&cep);

	if (0 == eventid)
		return;

	db_event->eventid = eventid;
	task->event_op = CEP_EVENT_CLOSE;

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create or close an internal event based on its value              *
 *                                                                            *
 * Parameters: task - [IN] event task                                         *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_internal_event(zbx_cep_task_event_t *task)
{
	switch (task->db_event->object)
	{
		case EVENT_OBJECT_ITEM:
		case EVENT_OBJECT_LLDRULE:
			if (ITEM_STATE_NOTSUPPORTED == task->db_event->value)
				cep_worker_open_internal_event(task);
			else
				cep_worker_close_internal_event(task);
			break;
		case EVENT_OBJECT_TRIGGER:
			if (TRIGGER_STATE_UNKNOWN == task->db_event->value)
				cep_worker_open_internal_event(task);
			else
				cep_worker_close_internal_event(task);
			break;
		default:
			return;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process an event task by delegating handling based on its type    *
 *                                                                            *
 * Parameters: task - [IN] event task                                         *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_task_event(zbx_cep_worker_t *worker, zbx_cep_task_event_t *task)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:%d object:%d objectid:" ZBX_FS_UI64 " name:%s value:%d ts:%d.%09d",
			__func__, task->db_event->source, task->db_event->object, task->db_event->objectid,
			task->db_event->name, task->db_event->value, task->db_event->clock, task->db_event->ns);

	/* check if event must be created */
	if (EVENT_SOURCE_TRIGGERS == task->db_event->source && EVENT_OBJECT_TRIGGER == task->db_event->object)
	{
		cep_worker_process_trigger_event(worker, task);
	}
	else if (EVENT_SOURCE_INTERNAL == task->db_event->source)
	{
		cep_worker_process_internal_event(task);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("Unsupported event source:%d and object:%d", task->db_event->source,
				task->db_event->object);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create an OK event to close specified problem event               *
 *                                                                            *
 * Parameters: task - [IN]  close event task                                  *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_task_close_event(zbx_cep_task_close_event_t *task)
{
	zbx_cep_t			*cep;
	zbx_db_event			*db_event = task->parent.db_event;
	zbx_uint64_t			r_eventid;
	zbx_vector_cep_event_handle_t	handles;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:%d object:%d objectid:" ZBX_FS_UI64 " name:%s value:%d ts:%d.%09d",
			__func__, db_event->source, db_event->object, db_event->objectid, db_event->name,
			db_event->value, db_event->clock, db_event->ns);

	zbx_vector_cep_event_handle_create(&handles);

	cep_cache_acquire(&cep);
	r_eventid = cep_close_trigger_event_by_eventid(cep, db_event->objectid, task->eventid, &handles);
	cep_cache_release(&cep);

	if (0 != r_eventid)
		cep_worker_resolve_trigger_events(db_event, r_eventid, &handles, &task->parent);

	zbx_vector_cep_event_handle_destroy(&handles);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() r_eventid:" ZBX_FS_UI64, __func__, r_eventid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: commit queued finished tasks                                      *
 *                                                                            *
 * Parameters: task - [IN]  commit task                                       *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_task_commit(zbx_cep_worker_t *worker, zbx_cep_task_commit_t *task)
{
	zbx_vector_mw_task_ptr_t	event_tasks, add_tags_tasks;

	zbx_vector_mw_task_ptr_create(&event_tasks);
	zbx_vector_mw_task_ptr_create(&add_tags_tasks);

	for (int i = 0; i < task->tasks.values_num; i++)
	{
		switch (task->tasks.values[i]->type)
		{
			case CEP_TASK_EVENT:
			case CEP_TASK_CLOSE_EVENT:
				zbx_vector_mw_task_ptr_append(&event_tasks, task->tasks.values[i]);
				break;
			case CEP_TASK_ADD_TAGS:
				zbx_vector_mw_task_ptr_append(&add_tags_tasks, task->tasks.values[i]);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported task %d in commit",
						task->tasks.values[i]->type);
				break;
		}
	}

	if (0 != event_tasks.values_num)
	{
		cep_db_flush_events(worker->dbpool, &event_tasks);
		cep_db_process_actions(worker->dbpool, &event_tasks, &worker->rtc);
		cep_db_export_events(worker->dbpool, &event_tasks, worker->problem_export);

		for (int i = 0; i < event_tasks.values_num; i++)
		{
			zbx_vector_cep_event_update_t	*updates;

			if (CEP_TASK_EVENT == event_tasks.values[i]->type)
				updates = &((zbx_cep_task_event_t *)event_tasks.values[i])->updates;
			else
				updates = &((zbx_cep_task_close_event_t *)event_tasks.values[i])->parent.updates;

			if (0 != updates->values_num)
			{
				cep_post_event_updates(updates->values, updates->values_num);
				zbx_vector_cep_event_update_clear(updates);
			}
		}
	}

	if (0 != add_tags_tasks.values_num)
	{
		cep_db_add_tags(worker->dbpool, &add_tags_tasks);

		for (int i = 0; i < add_tags_tasks.values_num; i++)
		{
			zbx_vector_cep_event_update_t	*updates;

			updates = &((zbx_cep_task_add_tags_t *)add_tags_tasks.values[i])->updates;

			if (0 != updates->values_num)
			{
				cep_post_event_updates(updates->values, updates->values_num);
				zbx_vector_cep_event_update_clear(updates);
			}
		}
	}

	zbx_vector_mw_task_ptr_destroy(&add_tags_tasks);
	zbx_vector_mw_task_ptr_destroy(&event_tasks);
}

/******************************************************************************
 *                                                                            *
 * Purpose: event processor thread entry point                                *
 *                                                                            *
 * Parameters: args - [IN] worker thread arguments                            *
 *                                                                            *
 * Return value: NULL                                                         *
 *                                                                            *
 ******************************************************************************/
void	*cep_worker_entry(void *args)
{
#define CEP_RTC_OPEN_TIMEOUT	10

	zbx_cep_worker_t	*worker = (zbx_cep_worker_t *)args;
	char			*error = NULL;

	zbx_supervisor_update_activity("%s starting", worker->base.name);

	zbx_init_regexp_env();

	if (FAIL == zbx_ipc_async_socket_open(&worker->rtc, ZBX_IPC_SERVICE_RTC, 10, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to RTC service: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		worker->problem_export = zbx_problems_export_init("event-processor", worker->base.id);

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started");
	zbx_supervisor_update_activity("%s running", worker->base.name);

	zbx_mw_queue_lock(worker->base.queue);

	while (SUCCEED == zbx_mw_worker_is_running(&worker->base))
	{
		zbx_mw_task_t	*task;

		while (NULL != (task = zbx_mw_queue_pop(worker->base.queue)))
		{
			zbx_mw_queue_unlock(worker->base.queue);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() process task type:%u", __func__, task->type);

			switch (task->type)
			{
				case CEP_TASK_REMOTE:
					cep_worker_process_task_remote(worker, (zbx_cep_task_remote_t *)task);
					break;
				case CEP_TASK_EVENT:
					cep_worker_process_task_event(worker, (zbx_cep_task_event_t *)task);
					break;
				case CEP_TASK_CLOSE_EVENT:
					cep_worker_process_task_close_event((zbx_cep_task_close_event_t *)task);
					break;
				case CEP_TASK_COMMIT:
					cep_worker_process_task_commit(worker, (zbx_cep_task_commit_t *)task);
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN_MSG("unknown task type %d", task->type);
					break;
			}

			zbx_mw_queue_lock(worker->base.queue);
			cep_queue_push_completed((zbx_cep_queue_t *)worker->base.queue, task);

			continue;
		}

		if (SUCCEED != zbx_mw_queue_wait(worker->base.queue, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s", error);
			zbx_free(error);

			zbx_mw_worker_stop(&worker->base);

			zbx_set_exiting_with_fail();
		}
	}

	zbx_mw_queue_unlock(worker->base.queue);

	zbx_ipc_async_socket_close(&worker->rtc);
	zbx_deinit_regexp_env();

	if (NULL != worker->problem_export)
		zbx_export_deinit(worker->problem_export);

	zbx_supervisor_update_activity("%s stopped", worker->base.name);
	zabbix_log(LOG_LEVEL_INFORMATION, "thread stopped");

	return NULL;

#undef CEP_RTC_OPEN_TIMEOUT
}

