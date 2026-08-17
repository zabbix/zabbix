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
#include "cep_rule.h"
#include "cep_task.h"
#include "cep_correlation.h"
#include "cep_rule_operation.h"
#include "cep_window.h"
#include "cep_event.h"
#include "zbx_cep.h"
#include "zbx_cep_client.h"
#include "zbxmw.h"

#include "zbx_item_constants.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxipcservice.h"
#include "zbxrtc.h"
#include "zbxnix.h"
#include "zbxregexp.h"
#include "zbxserialize.h"
#include "zbxsupervisor_client.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"

/******************************************************************************
 *                                                                            *
 * Purpose: initialize event processor worker                                 *
 *                                                                            *
 * Parameters: dbpool           - [IN] database connection pool               *
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

	cep_stats_update_events_accessed((zbx_uint64_t)queries.values_num);
	zbx_vector_cep_assessment_query_destroy(&queries);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check trigger dependency status                                   *
 *                                                                            *
 * Parameters: task - [IN] task containing trigger status                     *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_check_trigger_deps(zbx_cep_task_remote_t *task)
{
	zbx_cep_t		*cep;
	zbx_vector_uint64_t	triggerids;

	zbx_vector_uint64_create(&triggerids);

	zbx_cep_deserialize_ids(task->message->data, &triggerids);

	cep_cache_acquire(&cep);
	*task->response = (unsigned char)cep_check_trigger_deps(cep, &triggerids);
	cep_cache_release(&cep);

	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize events and enqueue corresponding event creation tasks *
 *                                                                            *
 * Parameters: task   - [IN]  remote task containing serialized events        *
 *             tasks  - [OUT] new tasks to be queued                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_add_events(zbx_cep_task_remote_t *task, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_vector_db_event_t		events;

	zbx_vector_db_event_create(&events);

	zbx_cep_deserialize_events(task->message->data, &events);

	if (0 != events.values_num)
	{
		for (int i = 0; i < events.values_num; i++)
			zbx_vector_mw_task_ptr_append(tasks, cep_create_task_event(events.values[i]));
	}

	(void)zbx_serialize_value(task->response, events.values_num);

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
	t = cep_create_task_event_by_user(event, eventid, userid);

	zbx_mw_queue_lock(worker->base.queue);
	cep_queue_push((zbx_cep_queue_t *)worker->base.queue, t);
	zbx_mw_queue_unlock(worker->base.queue);
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

	if (0 != handles.values_num)
	{
		if (0 != zbx_dc_local_get_itservices_num())
		{
			cep_post_event_handle_action(handles.values, handles.values_num, action);
		}
		else
		{
			for (int i = 0; i < handles.values_num; i++)
				zbx_cep_event_handle_release(handles.values[i]);
		}
	}

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
	{
		cep_post_event_handle_action(handles.values, handles.values_num, CEP_EVENT_UPDATE_SEVERITY);
	}
	else
	{
		for (int i = 0; i < handles.values_num; i++)
			zbx_cep_event_handle_release(handles.values[i]);
	}

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

	zbx_vector_event_tags_sort(&event_tags, zbx_event_tags_compare);

	zbx_vector_cep_event_handle_create(&handles);
	zbx_vector_cep_event_handle_reserve(&handles, (size_t)event_tags.values_num);

	cep_cache_acquire(&cep);
	cep_add_event_tags(cep, &event_tags, &handles);
	cep_cache_release(&cep);

	zbx_cep_get_eventids_from_handles(handles.values, handles.values_num, &eventids);

	zbx_cep_task_add_tags_t	*db_task = (zbx_cep_task_add_tags_t *)cep_create_task_add_tags(&event_tags, &eventids);

	if (0 != handles.values_num)
	{
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
	{
		cep_post_event_handle_action(handles.values, handles.values_num, CEP_EVENT_DELETE);
	}
	else
	{
		for (int i = 0; i < handles.values_num; i++)
			zbx_cep_event_handle_release(handles.values[i]);
	}

	zbx_vector_cep_event_handle_destroy(&handles);
	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize CEP statistics into a remote task response buffer       *
 *                                                                            *
 * Parameters: task - [IN/OUT] remote task with pre-allocated response buffer *
 *                                                                            *
 * Comments: The result is stored in pre-allocated task->response buffer      *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_get_stats(zbx_cep_worker_t *worker, zbx_cep_task_remote_t *task)
{
	zbx_cep_stats_t			stats;
	unsigned char			*ptr = task->response;
	zbx_cep_window_pool_stats_t	win_stats;
	zbx_cep_window_pool_t		*pool;

	cep_stats_collect(&stats);

	zbx_mw_queue_lock(worker->base.queue);
	zbx_mw_queue_get_stats(worker->base.queue, &stats.task_remote_num, &stats.task_internal_num,
			&stats.task_completed_num);
	zbx_mw_queue_unlock(worker->base.queue);

	cep_window_pool_acquire(&pool);
	cep_window_pool_get_stats(pool, &win_stats);
	cep_window_pool_release(&pool);

	ptr += zbx_serialize_value(ptr, stats.events_accessed);
	ptr += zbx_serialize_value(ptr, stats.events_processed);
	ptr += zbx_serialize_value(ptr, stats.events_discarded);
	ptr += zbx_serialize_value(ptr, stats.task_remote_num);
	ptr += zbx_serialize_value(ptr, stats.task_internal_num);
	ptr += zbx_serialize_value(ptr, stats.task_completed_num);
	ptr += zbx_serialize_value(ptr, stats.events_num);
	ptr += zbx_serialize_value(ptr, stats.objects_num);
	ptr += zbx_serialize_value(ptr, win_stats.windows_num);
	ptr += zbx_serialize_value(ptr, win_stats.alarms_num);
	(void)zbx_serialize_value(ptr, win_stats.ticks_num);

}

static void	cep_worker_set_event_cause(zbx_cep_task_remote_t *task)
{
	zbx_cep_t	*cep;
	unsigned char	*ptr = task->message->data;
	zbx_uint64_t	eventid, cause_eventid;

	ptr += zbx_deserialize_value(ptr, &eventid);
	(void)zbx_deserialize_value(ptr, &cause_eventid);

	cep_cache_acquire(&cep);
	cep_set_event_cause(cep, eventid, cause_eventid);
	cep_cache_release(&cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process a remote task                                             *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *             task   - [IN]  remote task to process                          *
 *             tasks  - [OUT] new tasks to be queued                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_task_remote(zbx_cep_worker_t *worker, zbx_cep_task_remote_t *task,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() task :%u", __func__, task->message->code);

	switch (task->message->code)
	{
		case ZBX_CEP_ASSESS_TRIGGER_EVENTS:
			cep_worker_assess_trigger_events(task);
			break;
		case ZBX_CEP_CHECK_TRIGGER_DEPS:
			cep_worker_check_trigger_deps(task);
			break;
		case ZBX_CEP_ADD_EVENTS:
			cep_worker_add_events(task, tasks);
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
		case ZBX_CEP_GET_STATS:
			cep_worker_get_stats(worker, task);
			break;
		case ZBX_CEP_SET_EVENT_CAUSE:
			cep_worker_set_event_cause(task);
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
 *             tasks  - [OUT] new tasks to be queued                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_open_trigger_event(zbx_cep_worker_t *worker, zbx_cep_task_event_t *task,
		zbx_vector_mw_task_ptr_t *tasks)
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
	{
		cep_stats_update_events_discarded(1);
		return;
	}

	cep_stats_update_events_processed(1);

	db_event->eventid = eventid;

	zbx_cep_event_t	*event = cep_event_create(db_event->eventid, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER,
			db_event->objectid, db_event->name, db_event->clock, db_event->ns, TRIGGER_VALUE_PROBLEM,
			db_event->severity, task->flags, 0, &db_event->tags, db_event->suppress);

	cep_stats_update_events_processed(1);

	const zbx_cep_rule_t		**rules = NULL;
	int				rules_num = 0;
	zbx_cep_config_handle_t		hconfig;
	zbx_cep_event_context_t		event_ctx = {.db_event = db_event, .event = cep_event_addref(event),
						.pos = CEP_POS_LAST, .sync_flags = CEP_SYNC_IGNORE};
	int				corr_ret;

	/* cep config returns NULL handle if there are no cep rules to process */
	if (NULL != (hconfig = zbx_cep_config_open()))
	{
		if (SUCCEED != cep_event_process_rules(hconfig, &rules, &rules_num, &event_ctx, tasks))
		{
			cep_cache_acquire(&cep);
			cep_origin_pending_event_done(cep, &event->origin);
			cep_cache_release(&cep);
			zbx_cep_event_release(event);
			zbx_vector_mw_task_ptr_clear_ext(tasks, cep_task_free);

			goto out;
		}
	}

	cep_cache_acquire(&cep);
	h = cep_add_event(cep, event);
	event_ctx.hevent = zbx_cep_event_handle_addref(h);
	cep_cache_release(&cep);

	if (0 != rules_num)
		cep_event_add_to_rules(&event_ctx, rules, rules_num, tasks);

	corr_ret = cep_correlate_db_event(db_event, worker->dbpool, tasks);

	/* 'close new' operations must skip actions for the problem and generated ok event */
	if (0 != (corr_ret & CORRELATION_RESULT_CLOSE_NEW))
		task->action_state = CEP_ACTION_DISABLED;

	task->event_op = CEP_EVENT_OPEN;
	task->event = cep_event_addref(event_ctx.event);
	task->hevent = zbx_cep_event_handle_addref(h);

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
out:
	cep_event_context_clear(&event_ctx);

	if (hconfig != NULL)
	{
		zbx_free(rules);
		zbx_cep_config_close(hconfig);
	}

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
		EVENT_OBJECT_TRIGGER, db_event->objectid, db_event->name, db_event->clock, db_event->ns,
		TRIGGER_VALUE_OK, db_event->severity, ZBX_EVENT_NORMAL, 0, &db_event->tags, db_event->suppress);

	cep_cache_acquire(&cep);
	cep_resolve_trigger_events(cep, r_event, handles);
	cep_cache_release(&cep);

	task->event = r_event;
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
	if (0 == task->target_eventid)
	{
		r_eventid = cep_close_trigger_events(cep, db_event->objectid, &db_event->trigger.dep_triggerids,
				db_event->trigger.correlation_mode, db_event->trigger.correlation_tag, &db_event->tags,
				&handles, &task->obj_value);
	}
	else
	{
		r_eventid = cep_close_trigger_event_by_eventid(cep, db_event->objectid, task->target_eventid, &handles,
			&task->obj_value);
	}
	cep_cache_release(&cep);

	if (0 != r_eventid)
	{
		cep_worker_resolve_trigger_events(db_event, r_eventid, &handles, task);
		cep_stats_update_events_processed(1);
	}
	else
		cep_stats_update_events_discarded(1);

	zbx_vector_cep_event_handle_destroy(&handles);

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create or close a trigger event based on its value                *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *             task   - [IN]  trigger event task to process                   *
 *             tasks  - [OUT] new tasks to be queued                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_trigger_event(zbx_cep_worker_t *worker, zbx_cep_task_event_t *task,
		zbx_vector_mw_task_ptr_t *tasks)
{
	if (TRIGGER_VALUE_PROBLEM == task->db_event->value)
		cep_worker_open_trigger_event(worker, task, tasks);
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
	zbx_cep_t		*cep;
	zbx_db_event		*db_event = task->db_event;
	zbx_uint64_t		eventid;
	zbx_cep_event_handle_t	h;

	cep_cache_acquire(&cep);
	eventid = cep_open_internal_event(cep, db_event->object, db_event->objectid);
	cep_cache_release(&cep);

	if (0 == eventid)
	{
		cep_stats_update_events_discarded(1);
		return;
	}

	db_event->eventid = eventid;

	/* don't cache tags for internal events since they are not used */
	zbx_cep_event_t	*event = cep_event_create(db_event->eventid, EVENT_SOURCE_INTERNAL, db_event->object,
			db_event->objectid, db_event->name, db_event->clock, db_event->ns, db_event->value, 0,
			ZBX_EVENT_NORMAL, 0, NULL, NULL);

	cep_cache_acquire(&cep);
	h = cep_add_event(cep, event);
	cep_cache_release(&cep);

	zbx_cep_event_handle_release(h);
	cep_stats_update_events_processed(1);

	task->event_op = CEP_EVENT_OPEN;
	task->event = NULL;
	task->hevent = NULL;

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
	{
		cep_stats_update_events_discarded(1);
		return;
	}

	cep_stats_update_events_processed(1);

	db_event->eventid = eventid;
	task->event_op = CEP_EVENT_CLOSE;
	task->event = NULL;

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
 * Parameters: task  - [IN] event task                                        *
 *             tasks - [OUT] new tasks to be queued                           *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_task_event(zbx_cep_worker_t *worker, zbx_cep_task_event_t *task,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:%d object:%d objectid:" ZBX_FS_UI64 " name:%s value:%d ts:%d.%09d",
			__func__, task->db_event->source, task->db_event->object, task->db_event->objectid,
			task->db_event->name, task->db_event->value, task->db_event->clock, task->db_event->ns);

	/* check if event must be created */
	if (EVENT_SOURCE_TRIGGERS == task->db_event->source && EVENT_OBJECT_TRIGGER == task->db_event->object)
	{
		cep_worker_process_trigger_event(worker, task, tasks);
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() event_op:%d tasks:%d", __func__, task->event_op, tasks->values_num);
}

static int	cep_task_sync_event_compare(const void *a1, const void *a2)
{
	const zbx_cep_task_sync_event_t	*t1 = *(const zbx_cep_task_sync_event_t * const *)a1;
	const zbx_cep_task_sync_event_t	*t2 = *(const zbx_cep_task_sync_event_t * const *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(zbx_cep_event_handle_eventid(t1->hevent), zbx_cep_event_handle_eventid(t2->hevent));

	return 0;
}

static int	cep_task_ack_compare(const void *a1, const void *a2)
{
	const zbx_cep_task_acknowledge_t	*ack1 = *(const zbx_cep_task_acknowledge_t * const *)a1;
	const zbx_cep_task_acknowledge_t	*ack2 = *(const zbx_cep_task_acknowledge_t * const *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(ack1->eventid, ack2->eventid);

	return 0;
}

static int	cep_task_rule_error_compare(const void *a1, const void *a2)
{
	const zbx_cep_task_rule_error_t	*re1 = *(const zbx_cep_task_rule_error_t * const *)a1;
	const zbx_cep_task_rule_error_t	*re2 = *(const zbx_cep_task_rule_error_t * const *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(re1->ruleid, re2->ruleid);

	return 0;
}

static int	cep_task_win_sync_compare(const void *a1, const void *a2)
{
	const zbx_cep_task_window_sync_t	*ws1 = *(const zbx_cep_task_window_sync_t * const *)a1;
	const zbx_cep_task_window_sync_t	*ws2 = *(const zbx_cep_task_window_sync_t * const *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(ws1->window, ws2->window);

	return 0;
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
	zbx_vector_mw_task_ptr_t	event_tasks, add_tags_tasks, sync_tasks, ack_tasks, rule_tasks, win_tasks;

	zbx_vector_mw_task_ptr_create(&event_tasks);
	zbx_vector_mw_task_ptr_create(&add_tags_tasks);
	zbx_vector_mw_task_ptr_create(&sync_tasks);
	zbx_vector_mw_task_ptr_create(&ack_tasks);
	zbx_vector_mw_task_ptr_create(&rule_tasks);
	zbx_vector_mw_task_ptr_create(&win_tasks);

	for (int i = 0; i < task->tasks.values_num; i++)
	{
		switch (task->tasks.values[i]->type)
		{
			case CEP_TASK_EVENT:
				zbx_vector_mw_task_ptr_append(&event_tasks, task->tasks.values[i]);
				break;
			case CEP_TASK_ADD_TAGS:
				zbx_vector_mw_task_ptr_append(&add_tags_tasks, task->tasks.values[i]);
				break;
			case CEP_TASK_SYNC_EVENT:
				zbx_vector_mw_task_ptr_append(&sync_tasks, task->tasks.values[i]);
				break;
			case CEP_TASK_ACKNOWLEDGE:
				zbx_vector_mw_task_ptr_append(&ack_tasks, task->tasks.values[i]);
				break;
			case CEP_TASK_RULE_ERROR:
				zbx_vector_mw_task_ptr_append(&rule_tasks, task->tasks.values[i]);
				break;
			case CEP_TASK_WINDOW_SYNC:
				zbx_vector_mw_task_ptr_append(&win_tasks, task->tasks.values[i]);
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

			updates = &((zbx_cep_task_event_t *)event_tasks.values[i])->updates;

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

	if (0 != sync_tasks.values_num)
	{
		zbx_vector_cep_event_update_t	updates;

		zbx_vector_mw_task_ptr_sort(&sync_tasks, cep_task_sync_event_compare);
		cep_db_sync_events(worker->dbpool, &sync_tasks);

		zbx_vector_cep_event_update_create(&updates);

		for (int i = 0; i < sync_tasks.values_num; i++)
		{
			zbx_cep_task_sync_event_t	*sync = (zbx_cep_task_sync_event_t *)sync_tasks.values[i];
			zbx_cep_event_update_t		update;

			if (0 != (sync->flags & CEP_SYNC_EVENT_TAGS))
			{
				update.handle = zbx_cep_event_handle_addref(sync->hevent);
				update.op = CEP_EVENT_UPDATE_TAGS;
				zbx_vector_cep_event_update_append(&updates, update);
			}
			if (0 != (sync->flags & CEP_SYNC_EVENT_SUPPRESS))
			{
				update.handle = zbx_cep_event_handle_addref(sync->hevent);
				update.op = CEP_EVENT_SUPPRESS;
				zbx_vector_cep_event_update_append(&updates, update);
			}
			if (0 != (sync->flags & CEP_SYNC_EVENT_UNSUPPRESS))
			{
				update.handle = zbx_cep_event_handle_addref(sync->hevent);
				update.op = CEP_EVENT_UNSUPPRESS;
				zbx_vector_cep_event_update_append(&updates, update);
			}
			if (0 != (sync->flags & CEP_SYNC_EVENT_SEVERITY))
			{
				update.handle = zbx_cep_event_handle_addref(sync->hevent);
				update.op = CEP_EVENT_UPDATE_SEVERITY;
				zbx_vector_cep_event_update_append(&updates, update);
			}
		}

		if (0 != updates.values_num)
			cep_post_event_updates(updates.values, updates.values_num);

		zbx_vector_cep_event_update_destroy(&updates);
	}

	if (0 != ack_tasks.values_num)
	{
		zbx_vector_mw_task_ptr_sort(&ack_tasks, cep_task_ack_compare);
		cep_db_add_acknowledges(worker->dbpool, &ack_tasks);
	}

	if (0 != rule_tasks.values_num)
	{
		zbx_vector_mw_task_ptr_sort(&rule_tasks, cep_task_rule_error_compare);
		cep_db_update_rule_errors(worker->dbpool, &rule_tasks);
	}

	if (0 != win_tasks.values_num)
	{
		zbx_vector_mw_task_ptr_sort(&win_tasks, cep_task_win_sync_compare);
		cep_db_sync_windows(worker->dbpool, &win_tasks);
	}


	zbx_vector_mw_task_ptr_destroy(&win_tasks);
	zbx_vector_mw_task_ptr_destroy(&rule_tasks);
	zbx_vector_mw_task_ptr_destroy(&ack_tasks);
	zbx_vector_mw_task_ptr_destroy(&sync_tasks);
	zbx_vector_mw_task_ptr_destroy(&add_tags_tasks);
	zbx_vector_mw_task_ptr_destroy(&event_tasks);
}

static void	cep_worker_process_task_window(zbx_cep_task_window_t *task, zbx_vector_mw_task_ptr_t *tasks)
{
	cep_window_process(task->window, task->now, tasks);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process rule reset task                                           *
 *                                                                            *
 * Parameters: task  - [IN] rule reset task                                   *
 *             tasks - [OUT] new tasks to be queued                           *
 *                                                                            *
 ******************************************************************************/
static void	cep_worker_process_task_rule_reset(zbx_cep_task_rule_reset_t *task, zbx_vector_mw_task_ptr_t *tasks)
{
	cep_remove_windows_by_rule(task->ruleid, tasks);
}

static void	cep_worker_expect_events(zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_vector_db_event_t	db_events;

	if (0 == tasks->values_num)
		return;

	zbx_vector_db_event_create(&db_events);

	for (int i = 0; i < tasks->values_num; i++)
	{
		if (CEP_TASK_EVENT != tasks->values[i]->type)
			continue;

		zbx_vector_db_event_append(&db_events, ((zbx_cep_task_event_t *)tasks->values[i])->db_event);
	}

	if (0 != db_events.values_num)
		cep_events_expect(db_events.values, db_events.values_num);

	zbx_vector_db_event_destroy(&db_events);
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

	zbx_cep_worker_t		*worker = (zbx_cep_worker_t *)args;
	char				*error = NULL;
	zbx_vector_mw_task_ptr_t	tasks;

	zbx_supervisor_update_activity("%s starting", worker->base.name);

	zbx_init_regexp_env();

	if (FAIL == zbx_ipc_async_socket_open(&worker->rtc, ZBX_IPC_SERVICE_RTC, 10, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to RTC service: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	zbx_vector_mw_task_ptr_create(&tasks);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		worker->problem_export = zbx_problems_export_init("event-processor", worker->base.id);

	zbx_dc_config_local_acquire();

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started");
	zbx_supervisor_update_activity("%s running", worker->base.name);

	zbx_mw_queue_lock(worker->base.queue);

	while (SUCCEED == zbx_mw_worker_is_running(&worker->base))
	{
		zbx_mw_task_t	*task;

		if (NULL != (task = zbx_mw_queue_pop(worker->base.queue)))
		{
			zbx_mw_queue_unlock(worker->base.queue);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() process task type:%u", __func__, task->type);

			switch (task->type)
			{
				case CEP_TASK_REMOTE:
					cep_worker_process_task_remote(worker, (zbx_cep_task_remote_t *)task, &tasks);
					break;
				case CEP_TASK_EVENT:
					cep_worker_process_task_event(worker, (zbx_cep_task_event_t *)task, &tasks);
					cep_worker_expect_events(&tasks);
					break;
				case CEP_TASK_COMMIT:
					cep_worker_process_task_commit(worker, (zbx_cep_task_commit_t *)task);
					break;
				case CEP_TASK_WINDOW:
					cep_worker_process_task_window((zbx_cep_task_window_t *)task, &tasks);
					cep_worker_expect_events(&tasks);
					break;
				case CEP_TASK_RULE_RESET:
					cep_worker_process_task_rule_reset((zbx_cep_task_rule_reset_t *)task, &tasks);
					break;
				case CEP_TASK_SYNC_EVENT:
				case CEP_TASK_ACKNOWLEDGE:
				case CEP_TASK_RULE_ERROR:
				case CEP_TASK_WINDOW_SYNC:
					/* nop tasks, contains data for commit */
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN_MSG("unknown task type %d", task->type);
					break;
			}

			zbx_mw_queue_lock(worker->base.queue);
			cep_queue_push_completed((zbx_cep_queue_t *)worker->base.queue, task);
			if (0 != tasks.values_num)
			{
				cep_queue_push_batch((zbx_cep_queue_t *)worker->base.queue, &tasks);
				zbx_vector_mw_task_ptr_clear(&tasks);
			}
			zbx_mw_worker_notify(&worker->base);

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

	zbx_dc_config_local_release();

	zbx_vector_mw_task_ptr_destroy(&tasks);

	zbx_supervisor_update_activity("%s stopped", worker->base.name);
	zabbix_log(LOG_LEVEL_INFORMATION, "thread stopped");

	return NULL;

#undef CEP_RTC_OPEN_TIMEOUT
}

