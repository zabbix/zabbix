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

#include "cep_task.h"
#include "cep_rule_operation.h"
#include "cep_window.h"
#include "zbx_cep.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxipcservice.h"
#include "zbxdbwrap.h"
#include "zbxjson.h"

static void	cep_task_free_impl(void *mw_task);

static void	cep_task_init(zbx_cep_task_t *task)
{
	zbx_vector_mw_task_ptr_create(&task->blocked);
	task->blockers = 0;
}

static void	cep_task_clear(zbx_cep_task_t *task)
{
	zbx_vector_mw_task_ptr_destroy(&task->blocked);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to handle remote request                              *
 *                                                                            *
 * Parameters: client       - [IN] IPC client handle                          *
 *             message      - [IN] IPC request message                        *
 *             response     - [IN] response buffer                            *
 *             response_len - [IN] length of the response buffer              *
 *                                                                            *
 * Return value: pointer to newly created task or NULL on failure             *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_remote(zbx_ipc_client_t *client, zbx_ipc_message_t *message, unsigned char *response,
		zbx_uint32_t response_len)
{
	zbx_cep_task_remote_t	*task;

	task = (zbx_cep_task_remote_t *)zbx_mw_task_create(CEP_TASK_REMOTE, cep_task_free_impl,
			sizeof(zbx_cep_task_remote_t));

	cep_task_init(&task->base);
	task->message = message;
	task->client = client;
	task->response = response;
	task->response_len = response_len;

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources of remote request task                             *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_request_remote_free(void *mw_task)
{
	zbx_cep_task_remote_t	*task = (zbx_cep_task_remote_t *)mw_task;

	zbx_ipc_message_free(task->message);
	zbx_ipc_client_release(task->client);
	zbx_free(task->response);
	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create event creation task                                        *
 *                                                                            *
 * Parameters: event - [IN] event details                                     *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_event(zbx_db_event *event)
{
	zbx_cep_task_event_t	*task;

	task = (zbx_cep_task_event_t *)zbx_mw_task_create(CEP_TASK_EVENT, cep_task_free_impl,
			sizeof(zbx_cep_task_event_t));
	cep_task_init(&task->base);

	task->db_event = event;
	zbx_vector_uint64_create(&task->eventids);
	task->event_op = CEP_EVENT_NONE;
	task->action_state = CEP_ACTION_ENABLED;
	task->obj_value = TRIGGER_VALUE_NONE;
	zbx_vector_cep_event_update_create(&task->updates);
	task->event = NULL;
	task->flags = ZBX_EVENT_NORMAL;
	task->hevent = NULL;

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to create a copy of another event                     *
 *                                                                            *
 * Parameters: event   - [IN] copied event                                    *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_event_by_copy(zbx_db_event *event)
{
	zbx_mw_task_t		*task = cep_create_task_event(event);
	zbx_cep_task_event_t	*event_task = (zbx_cep_task_event_t *)task;

	event_task->flags = ZBX_EVENT_COPIED;

	return task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to close specified event by user                      *
 *                                                                            *
 * Parameters: event   - [IN] new event that will close the specified event   *
 *             eventid - [IN] id of event to be closed                        *
 *             userid  - [IN] id of user closing the event                    *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_event_by_user(zbx_db_event *event, zbx_uint64_t eventid, zbx_uint64_t userid)
{
	zbx_mw_task_t		*task = cep_create_task_event(event);
	zbx_cep_task_event_t	*event_task = (zbx_cep_task_event_t *)task;

	event_task->target_eventid = eventid;
	event_task->creator.userid = userid;

	return task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to close specified event by correlation               *
 *                                                                            *
 * Parameters: event         - [IN] new event that will close the specified   *
 *                                  event                                     *
 *             eventid       - [IN] id of event to be closed                  *
 *             correlationid - [IN] id of correlation rule closing the        *
 *                                  event                                     *
 *             c_eventid     - [IN] id of event that caused correlation       *
 *                                  match                                     *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_event_by_correlation(zbx_db_event *event, zbx_uint64_t eventid,
		zbx_uint64_t correlationid, zbx_uint64_t c_eventid)
{
	zbx_mw_task_t		*task = cep_create_task_event(event);
	zbx_cep_task_event_t	*event_task = (zbx_cep_task_event_t *)task;

	event_task->target_eventid = eventid;
	event_task->creator.correlationid = correlationid;
	event_task->creator.c_eventid = c_eventid;

	return task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to close specified event by CEP rule                  *
 *                                                                            *
 * Parameters: event      - [IN] new event that will close the specified      *
 *                                  event                                     *
 *             eventid    - [IN] id of event to be closed                     *
 *             cep_ruleid - [IN] id of cep rule closing the event             *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_event_by_cep_rule(zbx_db_event *event, zbx_uint64_t eventid,
		zbx_uint64_t cep_ruleid)
{
	zbx_mw_task_t		*task = cep_create_task_event(event);
	zbx_cep_task_event_t	*event_task = (zbx_cep_task_event_t *)task;

	event_task->target_eventid = eventid;
	event_task->creator.cep_ruleid = cep_ruleid;

	return task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear event task resources                                        *
 *                                                                            *
 * Parameters: task - [IN] event task to clear                                *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_event_clear(zbx_cep_task_event_t *task)
{
	zbx_vector_uint64_destroy(&task->eventids);

	for (int i = 0; i < task->updates.values_num; i++)
		zbx_cep_event_handle_release(task->updates.values[i].handle);
	zbx_vector_cep_event_update_destroy(&task->updates);

	zbx_db_free_event(task->db_event);

	if (NULL != task->event)
		zbx_cep_event_release(task->event);

	if (NULL != task->hevent)
		zbx_cep_event_handle_release(task->hevent);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free event task                                                   *
 *                                                                            *
 * Parameters: mw_task - [IN] event task to free                              *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_event_free(void *mw_task)
{
	zbx_cep_task_event_t *task = (zbx_cep_task_event_t *)mw_task;

	cep_task_event_clear(task);
	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to commit events created by other tasks               *
 *                                                                            *
 * Parameters: tasks - [IN] list of tasks that have created events            *
 *                        to be committed as a single atomic operation        *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_commit(zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_task_commit_t	*task;

	task = (zbx_cep_task_commit_t *)zbx_mw_task_create(CEP_TASK_COMMIT, cep_task_free_impl,
			sizeof(zbx_cep_task_commit_t));
	cep_task_init(&task->base);

	zbx_vector_mw_task_ptr_create(&task->tasks);
	zbx_vector_mw_task_ptr_append_array(&task->tasks, tasks->values, tasks->values_num);

	zbx_vector_mw_task_ptr_clear(tasks);

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'event commit' task                                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_event_commit_free(void *mw_task)
{
	zbx_cep_task_commit_t	*task = (zbx_cep_task_commit_t *)mw_task;

	zbx_vector_mw_task_ptr_clear_ext(&task->tasks, cep_task_free);
	zbx_vector_mw_task_ptr_destroy(&task->tasks);

	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to add tags to events                                 *
 *                                                                            *
 * Parameters: event_tags - [IN] list of tags to be added                     *
 *             eventids   - [IN] list of event identifiers to update          *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_add_tags(zbx_vector_event_tags_t *event_tags, zbx_vector_uint64_t *eventids)
{
	zbx_cep_task_add_tags_t	*task;

	task = (zbx_cep_task_add_tags_t *)zbx_mw_task_create(CEP_TASK_ADD_TAGS, cep_task_free_impl,
			sizeof(zbx_cep_task_add_tags_t));
	cep_task_init(&task->base);

	zbx_vector_cep_event_update_create(&task->updates);

	zbx_vector_event_tags_create(&task->cached_tags);
	zbx_vector_event_tags_reserve(&task->cached_tags, (size_t)event_tags->values_num);

	zbx_vector_event_tags_create(&task->db_tags);
	zbx_vector_event_tags_reserve(&task->db_tags, (size_t)event_tags->values_num);

	for (int i = 0; i < event_tags->values_num; i++)
	{
		zbx_event_tags_t	*et = &event_tags->values[i];

		if (FAIL == zbx_vector_uint64_bsearch(eventids, et->eventid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			zbx_vector_event_tags_append_ptr(&task->db_tags, et);
		else
			zbx_vector_event_tags_append_ptr(&task->cached_tags, et);
	}

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'add tags' task                                              *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_add_tags_free(void *mw_task)
{
	zbx_cep_task_add_tags_t	*task = (zbx_cep_task_add_tags_t *)mw_task;

	for (int i = 0; i < task->cached_tags.values_num; i++)
		zbx_event_tags_clear(&task->cached_tags.values[i]);
	zbx_vector_event_tags_destroy(&task->cached_tags);

	for (int i = 0; i < task->db_tags.values_num; i++)
		zbx_event_tags_clear(&task->db_tags.values[i]);
	zbx_vector_event_tags_destroy(&task->db_tags);

	for (int i = 0; i < task->updates.values_num; i++)
		zbx_cep_event_handle_release(task->updates.values[i].handle);
	zbx_vector_cep_event_update_destroy(&task->updates);

	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to sync event changes from cache to db                *
 *                                                                            *
 * Parameters: hevent - [IN] event to sync                                    *
 *             flags  - [IN] update flags specifying what should be updated   *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_sync_event(zbx_cep_event_handle_t hevent, zbx_uint32_t flags)
{
	zbx_cep_task_sync_event_t	*task;

	task = (zbx_cep_task_sync_event_t *)zbx_mw_task_create(CEP_TASK_SYNC_EVENT, cep_task_free_impl,
			sizeof(zbx_cep_task_sync_event_t));
	cep_task_init(&task->base);

	task->hevent = zbx_cep_event_handle_addref(hevent);
	task->flags = flags;

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'sync event' task                                            *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_sync_event_free(void *mw_task)
{
	zbx_cep_task_sync_event_t	*task = (zbx_cep_task_sync_event_t *)mw_task;

	zbx_cep_event_handle_release(task->hevent);
	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to process event window                               *
 *                                                                            *
 * Parameters: window - [IN] window to process                                *
 *             now    - [IN] current time                                     *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_window(zbx_cep_window_t *window, time_t now)
{
	zbx_cep_task_window_t	*task;

	task = (zbx_cep_task_window_t *)zbx_mw_task_create(CEP_TASK_WINDOW, cep_task_free_impl,
			sizeof(zbx_cep_task_window_t));
	cep_task_init(&task->base);

	task->window = window;
	task->now = now;

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'process window' task                                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_window_free(void *mw_task)
{
	zbx_cep_task_window_t	*task = (zbx_cep_task_window_t *)mw_task;

	cep_window_release(task->window);
	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create task to sync windows changes                               *
 *                                                                            *
 * Parameters: window - [IN] window to process                                *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_window_sync(zbx_cep_window_t *window)
{
	zbx_cep_task_window_sync_t	*task;

	task = (zbx_cep_task_window_sync_t *)zbx_mw_task_create(CEP_TASK_WINDOW_SYNC, cep_task_free_impl,
			sizeof(zbx_cep_task_window_sync_t));
	cep_task_init(&task->base);

	task->window = window;

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'process window' task                                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_window_sync_free(void *mw_task)
{
	zbx_cep_task_window_sync_t	*task = (zbx_cep_task_window_sync_t *)mw_task;

	cep_window_release(task->window);
	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create an acknowledge task                                        *
 *                                                                            *
 * Parameters: ack     - [IN/OUT] acknowledge data; reset after transfer      *
 *             ruleid  - [IN]                                                 *
 *             eventid - [IN]                                                 *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_acknowledge(zbx_cep_acknowledge_t *ack, zbx_uint64_t ruleid, zbx_uint64_t eventid)
{
	zbx_cep_task_acknowledge_t	*task;

	task = (zbx_cep_task_acknowledge_t *)zbx_mw_task_create(CEP_TASK_ACKNOWLEDGE, cep_task_free_impl,
			sizeof(zbx_cep_task_acknowledge_t));
	cep_task_init(&task->base);

	task->ruleid = ruleid;
	task->eventid = eventid;
	zbx_json_copy(&task->details, &ack->json);
	memset(ack, 0, sizeof(zbx_cep_acknowledge_t));

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'process window' task                                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_acknowledge_free(void *mw_task)
{
	zbx_cep_task_acknowledge_t	*task = (zbx_cep_task_acknowledge_t *)mw_task;

	zbx_json_free(&task->details);
	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a rule error task                                          *
 *                                                                            *
 * Parameters: ruleid - [IN]                                                  *
 *             error  - [IN] error string or NULL; ownership is transferred   *
 *                          to task                                           *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_rule_error(zbx_uint64_t ruleid, char *error)
{
	zbx_cep_task_rule_error_t	*task;

	task = (zbx_cep_task_rule_error_t *)zbx_mw_task_create(CEP_TASK_RULE_ERROR, cep_task_free_impl,
			sizeof(zbx_cep_task_rule_error_t));
	cep_task_init(&task->base);

	task->ruleid = ruleid;
	task->error = error;

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'rule error' task                                            *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_rule_error_free(void *mw_task)
{
	zbx_cep_task_rule_error_t	*task = (zbx_cep_task_rule_error_t *)mw_task;

	zbx_free(task->error);
	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a rule reset task                                          *
 *                                                                            *
 * Parameters: ruleid - [IN]                                                  *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_rule_reset(zbx_uint64_t ruleid)
{
	zbx_cep_task_rule_reset_t	*task;

	task = (zbx_cep_task_rule_reset_t *)zbx_mw_task_create(CEP_TASK_RULE_RESET, cep_task_free_impl,
			sizeof(zbx_cep_task_rule_reset_t));
	cep_task_init(&task->base);

	task->ruleid = ruleid;

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'rule reset' task                                            *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_rule_reset_free(void *mw_task)
{
	zbx_free(mw_task);
}

static void	cep_task_free_impl(void *mw_task)
{
	zbx_mw_task_t	*task = (zbx_mw_task_t *)mw_task;

	cep_task_clear((zbx_cep_task_t *)mw_task);

	switch (task->type)
	{
		case CEP_TASK_REMOTE:
			cep_task_request_remote_free(task);
			break;
		case CEP_TASK_EVENT:
			cep_task_event_free(task);
			break;
		case CEP_TASK_COMMIT:
			cep_task_event_commit_free(task);
			break;
		case CEP_TASK_ADD_TAGS:
			cep_task_add_tags_free(task);
			break;
		case CEP_TASK_SYNC_EVENT:
			cep_task_sync_event_free(task);
			break;
		case CEP_TASK_WINDOW:
			cep_task_window_free(task);
			break;
		case CEP_TASK_WINDOW_SYNC:
			cep_task_window_sync_free(task);
			break;
		case CEP_TASK_ACKNOWLEDGE:
			cep_task_acknowledge_free(task);
			break;
		case CEP_TASK_RULE_ERROR:
			cep_task_rule_error_free(task);
			break;
		case CEP_TASK_RULE_RESET:
			cep_task_rule_reset_free(task);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unknown CEP task %d", task->type);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: free a task                                                       *
 *                                                                            *
 ******************************************************************************/
void	cep_task_free(zbx_mw_task_t *task)
{
	cep_task_free_impl((void *)task);
}

