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
#include "cep.h"
#include "cep_rule_op_event.h"
#include "zabbix_server/cep/zbx_cep.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxipcservice.h"
#include "zbxdbwrap.h"
#include "zbxjson.h"

static void	cep_task_request_remote_free(void *mw_task);
static void	cep_task_event_free(void *mw_task);
static void	cep_task_close_event_free(void *mw_task);
static void	cep_task_event_commit_free(void *mw_task);
static void	cep_task_add_tags_free(void *mw_task);
static void	cep_task_sync_event_free(void *mw_task);
static void	cep_task_window_free(void *mw_task);
static void	cep_task_acknowledge_free(void *mw_task);

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

	task = (zbx_cep_task_remote_t *)zbx_mw_task_create(CEP_TASK_REMOTE, cep_task_request_remote_free,
			sizeof(zbx_cep_task_remote_t));

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
 * Purpose: initialize event creation task                                    *
 *                                                                            *
 * Parameters: task  - [OUT] event task to initialize                         *
 *             event - [IN]  event details                                    *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_event_init(zbx_cep_task_event_t *task, zbx_db_event *event)
{
	task->db_event = event;
	zbx_vector_uint64_create(&task->eventids);
	task->event_op = CEP_EVENT_NONE;
	task->action_state = CEP_ACTION_ENABLED;
	task->obj_value = TRIGGER_VALUE_NONE;
	task->userid = 0;
	zbx_vector_cep_event_update_create(&task->updates);
	task->event = NULL;
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

	task = (zbx_cep_task_event_t *)zbx_mw_task_create(CEP_TASK_EVENT, cep_task_event_free,
			sizeof(zbx_cep_task_event_t));
	cep_task_event_init(task, event);

	return (zbx_mw_task_t *)task;
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
}

/******************************************************************************
 *                                                                            *
 * Purpose: free event task                                                   *
 *                                                                            *
 * Parameters: task - [IN] event task to free                                 *
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
 * Purpose: create event closing task                                         *
 *                                                                            *
 * Parameters: event         - [IN] event details                             *
 *             eventid       - [IN] identifier of the event to close          *
 *             userid        - [IN] user identifier performing the close      *
 *             correlationid - [IN] correlation identifier for the operation  *
 *             cep_ruleid    - [IN] cep ruleid for the operation              *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_close_event(zbx_db_event *event, zbx_uint64_t eventid, zbx_uint64_t userid,
		zbx_uint64_t correlationid, zbx_uint64_t cep_ruleid)
{
	zbx_cep_task_close_event_t	*task;

	task = (zbx_cep_task_close_event_t *)zbx_mw_task_create(CEP_TASK_CLOSE_EVENT, cep_task_close_event_free,
			sizeof(zbx_cep_task_close_event_t));

	cep_task_event_init(&task->parent, event);
	task->eventid = eventid;
	task->userid = userid;
	task->correlationid = correlationid;
	task->cep_ruleid = cep_ruleid;

	return (zbx_mw_task_t *)task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free 'close event' task                                           *
 *                                                                            *
 * Parameters: task - [IN] event closing task to free                         *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_close_event_free(void *mw_task)
{
	zbx_cep_task_close_event_t	*task = (zbx_cep_task_close_event_t *)mw_task;

	cep_task_event_clear(&task->parent);
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

	task = (zbx_cep_task_commit_t *)zbx_mw_task_create(CEP_TASK_COMMIT, cep_task_event_commit_free,
			sizeof(zbx_cep_task_commit_t));

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

	task = (zbx_cep_task_add_tags_t *)zbx_mw_task_create(CEP_TASK_ADD_TAGS, cep_task_add_tags_free,
			sizeof(zbx_cep_task_add_tags_t));

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
 * Parameters: event - [IN] event to sync                                     *
 *             flags - [IN] update flags specifying what should be updated    *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_sync_event(zbx_cep_event_handle_t event, zbx_uint32_t flags)
{
	zbx_cep_task_sync_event_t	*task;

	task = (zbx_cep_task_sync_event_t *)zbx_mw_task_create(CEP_TASK_SYNC_EVENT, cep_task_sync_event_free,
			sizeof(zbx_cep_task_sync_event_t));

	task->hevent = zbx_cep_event_handle_addref(event);
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
 * Purpose: create task process event window                                  *
 *                                                                            *
 * Parameters: widnow - [IN] window to process                                *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_window(zbx_cep_window_t *window, time_t now)
{
	zbx_cep_task_window_t	*task;

	task = (zbx_cep_task_window_t *)zbx_mw_task_create(CEP_TASK_WINDOW, cep_task_window_free,
			sizeof(zbx_cep_task_window_t));

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
 * Purpose: create task process event window                                  *
 *                                                                            *
 * Parameters: widnow - [IN] window to process                                *
 *                                                                            *
 * Return value: created task                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*cep_create_task_acknowledge(zbx_cep_acknowledge_t *ack, zbx_uint64_t ruleid, zbx_uint64_t eventid)
{
	zbx_cep_task_acknowledge_t	*task;

	task = (zbx_cep_task_acknowledge_t *)zbx_mw_task_create(CEP_TASK_ACKNOWLEDGE, cep_task_acknowledge_free,
			sizeof(zbx_cep_task_acknowledge_t));

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
 * Purpose: free a task                                                       *
 *                                                                            *
 ******************************************************************************/
void	cep_task_free(zbx_mw_task_t *mw_task)
{
	zbx_mw_task_t	*task = (zbx_mw_task_t *)mw_task;

	switch (task->type)
	{
		case CEP_TASK_REMOTE:
			cep_task_request_remote_free((zbx_cep_task_remote_t *)task);
			break;
		case CEP_TASK_EVENT:
			cep_task_event_free((zbx_cep_task_event_t *)task);
			break;
		case CEP_TASK_CLOSE_EVENT:
			cep_task_close_event_free((zbx_cep_task_close_event_t *)task);
			break;
		case CEP_TASK_COMMIT:
			cep_task_event_commit_free((zbx_cep_task_commit_t *)task);
			break;
		case CEP_TASK_ADD_TAGS:
			cep_task_add_tags_free((zbx_cep_task_add_tags_t *)task);
			break;
		case CEP_TASK_SYNC_EVENT:
			cep_task_sync_event_free((zbx_cep_task_sync_event_t *)task);
			break;
		case CEP_TASK_WINDOW:
			cep_task_window_free((zbx_cep_task_window_t *)task);
			break;
		case CEP_TASK_ACKNOWLEDGE:
			cep_task_acknowledge_free((zbx_cep_task_acknowledge_t *)task);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unknown CEP task %d", task->type);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: obtain event task associated with the given task                  *
 *                                                                            *
 * Parameters: task - [IN]  pointer to task structure                         *
 *                                                                            *
 * Return value: pointer to associated event task                             *
 *                                                                            *
 * Comments: Returns the underlying event task for supported task types.      *
 *                                                                            *
 ******************************************************************************/
const zbx_cep_task_event_t	*cep_get_event_task(const zbx_mw_task_t *task)
{
	switch (task->type)
	{
		case CEP_TASK_EVENT:
			return (zbx_cep_task_event_t *)task;
		case CEP_TASK_CLOSE_EVENT:
			return &((zbx_cep_task_close_event_t *)task)->parent;
		default:
			break;
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported CEP task of type %d is being accessed", task->type);
	zbx_exit(EXIT_FAILURE);
}

