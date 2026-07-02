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

#include "cep_manager.h"
#include "cep.h"
#include "cep_task.h"
#include "cep_window.h"
#include "cep_worker.h"
#include "cep_queue.h"
#include "cep_api.h"
#include "zbx_cep.h"
#include "zbx_cep_client.h"

#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxipcservice.h"
#include "zbxlog.h"
#include "zbxmw.h"
#include "zbxserialize.h"
#include "zbxsupervisor_client.h"
#include "zbxtimekeeper.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbxrtc.h"
#include "zbxprof.h"
#include "zbx_rtc_constants.h"
#include "zbxthreads.h"
#include "zbxtime.h"
#include "zbxcachehistory.h"

#define CEP_WORKERS_MAX		100
#define CEP_WORKERS_DEFAULT	10

typedef enum
{
	CEP_POOL_IDLE,
	CEP_POOL_GROWING,	/* worker thread(s) are being started */
	CEP_POOL_SHRINKING	/* worker thread(s) are being stopped */
}
zbx_cep_pool_state_t;

typedef struct
{
	zbx_mw_manager_t		base;

	/* tasks drained from the completed queue that are ready to commit; */
	/* when a task carrying a problem event is moved here, its eventid */
	/* is registered in events_pending */
	zbx_vector_mw_task_ptr_t	commits;

	/* tasks drained from the completed queue whose eventid is currently */
	/* in events_pending - i.e. an earlier task for the same event is */
	/* still being committed. Held here so event-related data is never */
	/* committed before the event itself has been committed */
	zbx_vector_mw_task_ptr_t	commits_pending;

	/* eventids of problem events currently being committed */
	zbx_hashset_t			events_pending;

	/* number of commit tasks currently queued/in progress - created when */
	/* the manager decides to commit the tasks accumulated in "commits", */
	/* moving them into a new commit task and queuing it */
	int				commit_task_num;
}
zbx_cep_manager_t;

/******************************************************************************
 *                                                                            *
 * Purpose: free CEP manager resources                                        *
 *                                                                            *
 * Parameters: manager - [IN] the CEP manager instance                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_free(zbx_cep_manager_t *manager)
{
	zbx_mw_manager_clear(&manager->base);

	/* workers are created after queue, if queue failed there will be no workers to stop/destroy */
	if (NULL != manager->base.queue)
	{
		cep_queue_clear((zbx_cep_queue_t *)manager->base.queue);
		zbx_free(manager->base.queue);

		if (NULL != manager->base.workers)
		{
			for (int i = 0; i < manager->base.workers_max; i++)
				zbx_free(manager->base.workers[i]);

			zbx_free(manager->base.workers);
		}
	}

	zbx_cep_api_release();

	zbx_vector_mw_task_ptr_clear_ext(&manager->commits, cep_task_free);
	zbx_vector_mw_task_ptr_destroy(&manager->commits);

	zbx_vector_mw_task_ptr_clear_ext(&manager->commits_pending, cep_task_free);
	zbx_vector_mw_task_ptr_destroy(&manager->commits_pending);

	zbx_hashset_destroy(&manager->events_pending);

	zbx_free(manager);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize CEP manager instance                        *
 *                                                                            *
 * Parameters: workers_num      - [IN] initial number of workers              *
 *             dbpool           - [IN] database connection pool               *
 *             config_source_ip - [IN] source ip from conf parameters         *
 *             error            - [OUT] error message                         *
 *                                                                            *
 * Return value: pointer to the created CEP manager instance or NULL on       *
 *               error                                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_manager_t	*cep_manager_create(const zbx_thread_info_t *info, zbx_dbconn_pool_t *dbpool,
		const char *config_source_ip, char **error)
{
	zbx_cep_manager_t	*manager;
	int			ret = FAIL;
	zbx_cep_t		*cep;
	zbx_cep_worker_t	**workers;
	zbx_cep_queue_t		*queue;

	manager = (zbx_cep_manager_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_manager_t));
	workers = (zbx_cep_worker_t **)zbx_calloc(NULL, (size_t)CEP_WORKERS_MAX, sizeof(zbx_cep_worker_t));

	for (int i = 0; i < CEP_WORKERS_MAX; i++)
		workers[i] = cep_worker_create(dbpool);

	queue = cep_queue_create();

	zbx_vector_mw_task_ptr_create(&manager->commits);
	zbx_vector_mw_task_ptr_create(&manager->commits_pending);
	zbx_hashset_create(&manager->events_pending, 100, ZBX_DEFAULT_ID_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	manager->commit_task_num = 0;

	if (SUCCEED != zbx_mw_manager_init(&manager->base, info, ZBX_IPC_SERVICE_CEP, ZBX_PROCESS_TYPE_CEP_WORKER,
			(zbx_mw_worker_t **)workers, CEP_WORKERS_MAX, CEP_WORKERS_DEFAULT, cep_worker_entry,
			(zbx_mw_queue_t *)queue, error))
	{
		goto out;
	}

	if (FAIL == cep_api_create(config_source_ip, error))
		goto out;

	zbx_cep_api_acquire();

	cep_cache_acquire(&cep);
	cep_init(cep, dbpool);
	cep_cache_release(&cep);

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		cep_manager_free(manager);
		manager = NULL;
	}

	return manager;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add remote task to CEP manager                                    *
 *                                                                            *
 * Parameters: manager      - [IN]  CEP manager instance                      *
 *             client       - [IN]  IPC client                                *
 *             message      - [IN]  IPC message                               *
 *             response     - [IN]  response buffer                           *
 *             response_len - [IN]  length of the response buffer             *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_add_remote_task(zbx_cep_manager_t *manager, zbx_ipc_client_t **client,
		zbx_ipc_message_t **message, unsigned char *response, zbx_uint32_t response_len)
{
	zbx_mw_task_t	*task;

	task = cep_create_task_remote(*client, *message, response, response_len);

	zbx_mw_queue_lock(manager->base.queue);
	cep_queue_push((zbx_cep_queue_t *)manager->base.queue, task);
	zbx_mw_queue_unlock(manager->base.queue);

	*client = NULL;
	*message = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add assess trigger events task to CEP manager                     *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance                            *
 *             client  - [IN] IPC client handle                               *
 *             message - [IN] IPC message descriptor                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_add_assess_trigger_events(zbx_cep_manager_t *manager, zbx_ipc_client_t **client,
	zbx_ipc_message_t **message)
{
	unsigned char*	response;
	int		queries_num;

	queries_num = zbx_cep_peek_event_queries((*message)->data);
	response = (unsigned char*)zbx_malloc(NULL, (size_t)queries_num);

	cep_manager_add_remote_task(manager, client, message, response, (zbx_uint32_t)queries_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check trigger dependency status                                   *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance                            *
 *             client  - [IN] IPC client handle                               *
 *             message - [IN] IPC message descriptor                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_check_trigger_deps(zbx_cep_manager_t *manager, zbx_ipc_client_t **client,
	zbx_ipc_message_t **message)
{
	unsigned char*	response;

	response = (unsigned char*)zbx_malloc(NULL, 1);

	cep_manager_add_remote_task(manager, client, message, response, 1);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get cep statistics                                                *
 *                                                                            *
 * Parameters: manager - [IN/OUT] CEP manager                                 *
 *             client  - [IN/OUT] IPC client requesting the statistics        *
 *             message - [IN/OUT] IPC message from the client                 *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_get_stats(zbx_cep_manager_t *manager, zbx_ipc_client_t **client,
	zbx_ipc_message_t **message)
{
	unsigned char*	response;
	zbx_uint32_t	reponse_len = sizeof(zbx_uint64_t) * 3 + sizeof(int) * 4;

	response = (unsigned char*)zbx_malloc(NULL, reponse_len);

	cep_manager_add_remote_task(manager, client, message, response, reponse_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync CEP object runtime state and notify the requesting client    *
 *                                                                            *
 * Parameters: dbpool  - [IN] database connection pool                        *
 *             client  - [IN] requesting IPC client to notify on completion   *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_sync_object_state(zbx_dbconn_pool_t *dbpool, zbx_ipc_client_t **client)
{
	zbx_cep_t	*cep;
	zbx_dbconn_t	*db;

	db = zbx_dbconn_pool_acquire_connection(dbpool);

	cep_cache_acquire(&cep);
	cep_sync_object_state(cep, db);
	cep_cache_release(&cep);

	zbx_dbconn_pool_release_connection(dbpool, db);

	zbx_ipc_client_send(*client, ZBX_CEP_SYNC_OBJECT_STATE, NULL, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: push a rule reset task to the cep queue                           *
 *                                                                            *
 * Parameters: manager - [IN] cep manager                                     *
 *             message - [IN] IPC message containing rule identifier          *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_reset_rule(zbx_cep_manager_t *manager, const zbx_ipc_message_t *message)
{
	zbx_uint64_t	ruleid;

	(void)zbx_deserialize_value(message->data, &ruleid);

	zbx_mw_queue_lock(manager->base.queue);
	cep_queue_push((zbx_cep_queue_t *)manager->base.queue, cep_create_task_rule_reset(ruleid));
	zbx_mw_queue_unlock(manager->base.queue);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send remote CEP task response if available                        *
 *                                                                            *
 * Parameters: task - [IN] remote CEP task descriptor                         *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_flush_remote_task(zbx_cep_task_remote_t *task)
{
	if (NULL != task->response)
		zbx_ipc_client_send(task->client, task->message->code, task->response, task->response_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event is pending                                         *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager                                     *
 *             eventid - [IN] event identifier                                *
 *                                                                            *
 * Return value: SUCCEED - the event is pending                               *
 *               FAIL    - the event is not pending                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_is_event_pending(zbx_cep_manager_t *manager, zbx_uint64_t eventid)
{
	if (NULL != zbx_hashset_search(&manager->events_pending, &eventid))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event task is pending                                    *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager                                     *
 *             task    - [IN] event task to check                             *
 *                                                                            *
 * Return value: SUCCEED - at least one event in the task is pending          *
 *               FAIL    - none of the task events are pending                *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_is_task_event_pending(zbx_cep_manager_t *manager, const zbx_cep_task_event_t *task)
{
	for (int i = 0; i < task->eventids.values_num; i++)
	{
		if (SUCCEED == cep_manager_is_event_pending(manager, task->eventids.values[i]))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if task is pending                                          *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager                                     *
 *             task    - [IN] task to check                                   *
 *                                                                            *
 * Return value: SUCCEED - the task is pending                                *
 *               FAIL    - the task is not pending                            *
 *                                                                            *
 * Comments: Task is pending if it has to update an event and the event task  *
 *           is not yet committed to the database.                            *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_is_task_pending(zbx_cep_manager_t *manager, const zbx_mw_task_t *task)
{
	zbx_uint64_t	eventid;

	switch (task->type)
	{
		case CEP_TASK_EVENT:
			return cep_manager_is_task_event_pending(manager, (const zbx_cep_task_event_t *)task);
		case CEP_TASK_CLOSE_EVENT:
			return cep_manager_is_task_event_pending(manager,
					&((zbx_cep_task_close_event_t *)task)->parent);
		case CEP_TASK_SYNC_EVENT:
			eventid = zbx_cep_event_handle_eventid(((zbx_cep_task_sync_event_t *)task)->hevent);
			return cep_manager_is_event_pending(manager, eventid);
		case CEP_TASK_ACKNOWLEDGE:
			return cep_manager_is_event_pending(manager, ((zbx_cep_task_acknowledge_t *)task)->eventid);
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue an event task for commit, deferring it if a matching        *
 *          resolve event is still pending                                    *
 *                                                                            *
 * Parameters: manager - [IN/OUT] cep manager                                 *
 *             task    - [IN] event task to commit                            *
 *                                                                            *
 * Return value: SUCCEED - the task was queued for commit or deferred         *
 *               FAIL - the task has no event operation                       *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_commit_event_task(zbx_cep_manager_t *manager, zbx_mw_task_t *task)
{
	const zbx_cep_task_event_t	*event_task =  cep_get_event_task(task);

	if (CEP_EVENT_NONE == event_task->event_op)
		return FAIL;

	if (event_task->db_event->value == TRIGGER_VALUE_OK)
	{
		if (SUCCEED == cep_manager_is_task_event_pending(manager, event_task))
		{
			zbx_vector_mw_task_ptr_append(&manager->commits_pending, task);
			return SUCCEED;
		}
	}
	else
	{
		zbx_hashset_insert(&manager->events_pending, &event_task->db_event->eventid, sizeof(zbx_uint64_t));
	}

	zbx_vector_mw_task_ptr_append(&manager->commits, task);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue a task for commit, deferring it if its event commit is      *
 *          currently pending                                                 *
 *                                                                            *
 * Parameters: manager - [IN/OUT] cep manager                                 *
 *             eventid - [IN] identifier of the event associated with the     *
 *                       task                                                 *
 *             task    - [IN] task to commit                                  *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_commit_task(zbx_cep_manager_t *manager, zbx_uint64_t eventid, zbx_mw_task_t *task)
{
	if (SUCCEED == cep_manager_is_event_pending(manager, eventid))
		zbx_vector_mw_task_ptr_append(&manager->commits_pending, task);
	else
		zbx_vector_mw_task_ptr_append(&manager->commits, task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove problem events committed by a commit task from the         *
 *          pending events set                                                *
 *                                                                            *
 * Parameters: manager - [IN/OUT] cep manager                                 *
 *             task    - [IN] commit task                                     *
 *                                                                            *
 ******************************************************************************/
static  void	cep_manager_remove_pending_events(zbx_cep_manager_t *manager, zbx_mw_task_t *task)
{
	zbx_cep_task_commit_t	*commit = (zbx_cep_task_commit_t *)task;

	for (int i = 0; i < commit->tasks.values_num; i++)
	{
		if (CEP_TASK_EVENT != commit->tasks.values[i]->type)
			continue;

		const zbx_cep_task_event_t *event_task = (zbx_cep_task_event_t *)commit->tasks.values[i];

		if (event_task->db_event->value == TRIGGER_VALUE_PROBLEM)
			zbx_hashset_remove(&manager->events_pending, &event_task->db_event->eventid);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: move deferred tasks that are no longer pending to the commit      *
 *          queue                                                             *
 *                                                                            *
 * Parameters: manager - [IN/OUT] cep manager                                 *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_process_pending(zbx_cep_manager_t *manager)
{
	for (int i = 0; i < manager->commits_pending.values_num; )
	{
		if (SUCCEED != cep_manager_is_task_pending(manager, manager->commits_pending.values[i]))
		{
			zbx_vector_mw_task_ptr_append(&manager->commits, manager->commits_pending.values[i]);
			zbx_vector_mw_task_ptr_remove(&manager->commits_pending, i);
			continue;
		}

		i++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process finished CEP tasks and handle commits                     *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance                            *
 *             tasks   - [IN] vector of finished CEP tasks                    *
 *                                                                            *
 * Comments: Remote tasks are flushed, event-related tasks with pending       *
 *           operations are queued for commit, and other tasks are freed.     *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_process_finished(zbx_cep_manager_t *manager, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_task_sync_event_t	*task_update;
	zbx_cep_task_acknowledge_t	*task_ack;
	zbx_uint64_t			eventid;

	for (int i = 0; i < tasks->values_num; i++)
	{
		switch (tasks->values[i]->type)
		{
			case CEP_TASK_REMOTE:
				cep_manager_flush_remote_task((zbx_cep_task_remote_t *)tasks->values[i]);
				break;
			case CEP_TASK_EVENT:
			case CEP_TASK_CLOSE_EVENT:
				if (SUCCEED == cep_manager_commit_event_task(manager, tasks->values[i]))
					continue;
				break;
			case CEP_TASK_COMMIT:
				cep_manager_remove_pending_events(manager, tasks->values[i]);
				manager->commit_task_num--;
				break;
			case CEP_TASK_ADD_TAGS:
				zbx_vector_mw_task_ptr_append(&manager->commits, tasks->values[i]);
				continue;
			case CEP_TASK_SYNC_EVENT:
				task_update = (zbx_cep_task_sync_event_t *)tasks->values[i];
				eventid = zbx_cep_event_handle_eventid(task_update->hevent);
				cep_manager_commit_task(manager, eventid, tasks->values[i]);
				continue;
			case CEP_TASK_ACKNOWLEDGE:
				task_ack = (zbx_cep_task_acknowledge_t *)tasks->values[i];
				cep_manager_commit_task(manager, task_ack->eventid, tasks->values[i]);
				continue;
			case CEP_TASK_RULE_ERROR:
				zbx_vector_mw_task_ptr_append(&manager->commits, tasks->values[i]);
				continue;

		}

		cep_task_free(tasks->values[i]);
	}

	zbx_vector_mw_task_ptr_clear(tasks);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue commit task for pending CEP operations                    *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance                            *
 *                                                                            *
 * Comments: Creates a commit task for all queued commit operations and       *
 *           pushes it to the CEP manager queue.                              *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_flush_commmits(zbx_cep_manager_t *manager)
{
	zbx_mw_task_t	*task = cep_create_task_commit(&manager->commits);

	zbx_mw_queue_lock(manager->base.queue);
	cep_queue_push((zbx_cep_queue_t *)manager->base.queue, task);
	zbx_mw_queue_unlock(manager->base.queue);

	manager->commit_task_num++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: determine the maximum number of concurrent commit tasks for the   *
 *          cep manager                                                       *
 *                                                                            *
 * Parameters: manager - [IN] cep manager                                     *
 *             args    - [IN] cep manager thread arguments                    *
 *                                                                            *
 * Return value: the commit limit                                             *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_commit_limit(zbx_cep_manager_t *manager, const zbx_thread_cep_manager_args_t *args)
{
	if (1 == manager->base.workers_num)
		return 1;

	if (args->commit_limit > manager->base.workers_num - 1)
		return manager->base.workers_num - 1;

	return args->commit_limit;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create window tasks for windows scheduled to be processed at      *
 *          the given time                                                    *
 *                                                                            *
 * Parameters: now   - [IN] current time                                      *
 *             tasks - [OUT] vector to append created window tasks to         *
 *                                                                            *
 * Return value: the number of added tasks                                    *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_process_windows(int now, zbx_vector_mw_task_ptr_t *tasks)
{
#define	CEP_WINDOW_BATCH	1000

	zbx_cep_window_pool_t		*pool;
	zbx_vector_cep_window_ptr_t	windows;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_cep_window_ptr_create(&windows);
	zbx_vector_cep_window_ptr_reserve(&windows, CEP_WINDOW_BATCH);

	do
	{
		zbx_vector_cep_window_ptr_clear(&windows);
		cep_window_pool_acquire(&pool);
		cep_window_pool_next_batch(pool, now, &windows);
		cep_window_pool_release(&pool);

		for (int i = 0; i < windows.values_num; i++)
		{
			zbx_mw_task_t	*t = cep_create_task_window(windows.values[i], now);

			zbx_vector_mw_task_ptr_append(tasks, t);
		}
	}
	while (CEP_WINDOW_BATCH == windows.values_num);


	zbx_vector_cep_window_ptr_destroy(&windows);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() tasks:%d", __func__, tasks->values_num);

	return tasks->values_num;
#undef CEP_WINDOW_BATCH
}

/******************************************************************************
 *                                                                            *
 * Purpose: load window groups into the window pool                           *
 *                                                                            *
 * Parameters: dbpool - [IN] database connection pool                         *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_load_window_groups(zbx_dbconn_pool_t *dbpool)
{
	zbx_cep_window_pool_t	*pool;

	cep_window_pool_acquire(&pool);
	cep_window_pool_load(pool, dbpool);
	cep_window_pool_release(&pool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: save window groups from the window pool                           *
 *                                                                            *
 * Parameters: dbpool - [IN] database connection pool                         *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_save_window_groups(zbx_dbconn_pool_t *dbpool)
{
	zbx_cep_window_pool_t	*pool;

	cep_window_pool_acquire(&pool);
	cep_window_pool_save(pool, dbpool);
	cep_window_pool_release(&pool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: CEP manager main thread function                                  *
 *                                                                            *
 * Parameters: args - [IN] thread arguments                                   *
 *                                                                            *
 * Return value: NULL                                                         *
 *                                                                            *
 ******************************************************************************/
void	*zbx_cep_manager_thread(void *args)
{
#define CEP_MANAGER_DELAY_SEC		0
#define CEP_MANAGER_DELAY_NS		5e8
#define CEP_MANAGER_BATCH_LIMIT		1000
#define CEP_MANAGER_FLUSH_TIMEOUT	1.0

	char					*error = NULL;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	double					time_stat, time_idle = 0, time_flush;
	zbx_supervisor_unit_args_t		*unit_args = (zbx_supervisor_unit_args_t *)args;
	const zbx_thread_info_t			*info = &unit_args->args.info;
	int					server_num = info->server_num,
						process_num = info->process_num;
	unsigned char				process_type = info->process_type;
	const zbx_thread_cep_manager_args_t	*cep_args = (const zbx_thread_cep_manager_args_t *)unit_args->args.args;
	zbx_cep_manager_t			*manager;
	zbx_vector_mw_task_ptr_t		tasks;
	int					shutdown = 0;
	zbx_cep_stats_t				stats = {0};

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */


	zbx_supervisor_update_activity("%s starting", unit_args->name);
	zabbix_log(LOG_LEVEL_INFORMATION, "thread started");

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_vector_mw_task_ptr_create(&tasks);

	if (NULL == (manager = cep_manager_create(info, unit_args->shared->dbpool, cep_args->config_source_ip, &error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize CEP manager: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	cep_manager_load_window_groups(unit_args->shared->dbpool);

	/* initialize statistics */
	time_stat = zbx_time();

	zbx_supervisor_update_activity("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_supervisor_set_process_running(server_num);

	time_flush = zbx_time();

	while (1)
	{
		int		pending_num;
		double		time_start = zbx_time();

		if (STAT_INTERVAL < time_start - time_stat)
		{
			zbx_cep_stats_t	stats_tmp;
			zbx_uint64_t	accessed_num, processed_num, discarded_num;

			cep_stats_collect(&stats_tmp);

			accessed_num = stats_tmp.events_accessed - stats.events_accessed;
			processed_num = stats_tmp.events_processed - stats.events_processed;
			discarded_num = stats_tmp.events_discarded - stats.events_discarded;

			zbx_supervisor_update_activity("%s #%d [assessed:" ZBX_FS_UI64 " processed:" ZBX_FS_UI64
					" discarded:" ZBX_FS_UI64 " events, idle %.1fs, during %.1fs]",
					get_process_type_string(process_type), process_num,
					accessed_num, processed_num, discarded_num,
					time_idle, time_start - time_stat);

			time_stat = time_start;
			time_idle = 0;
			stats = stats_tmp;

			/* WDN remove debug logging */
			zbx_cep_t	*cep;
			int	loglevel = zbx_set_log_level(LOG_LEVEL_TRACE);

			cep_cache_acquire(&cep);
			cep_dump(cep, "========");
			cep_cache_release(&cep);

			zbx_cep_window_pool_t	*pool;

			cep_window_pool_acquire(&pool);
			cep_window_pool_dump(pool);
			cep_window_pool_release(&pool);

			zbx_set_log_level(loglevel);
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		(void)zbx_mw_manager_recv(&manager->base, &client, &message);

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		double	time_now = zbx_time();

		time_idle += time_now - time_start;

		zbx_prof_update(get_process_type_string(process_type), time_now);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_CEP_ASSESS_TRIGGER_EVENTS:
					cep_manager_add_assess_trigger_events(manager, &client, &message);
					break;
				case ZBX_CEP_CHECK_TRIGGER_DEPS:
					cep_manager_check_trigger_deps(manager, &client, &message);
					break;
				case ZBX_CEP_GET_STATS:
					cep_manager_get_stats(manager, &client, &message);
					break;
				case ZBX_CEP_ADD_EVENTS:
				case ZBX_CEP_ADD_USER_CLOSE_EVENT:
				case ZBX_CEP_SUPPRESS_EVENTS:
				case ZBX_CEP_UNSUPPRESS_EVENTS:
				case ZBX_CEP_UPDATE_SEVERITIES:
				case ZBX_CEP_ADD_EVENT_TAGS:
				case ZBX_CEP_DELETE_EVENTS:
				case ZBX_CEP_SET_EVENT_CAUSE:
					cep_manager_add_remote_task(manager, &client, &message, NULL, 0);
					break;
				case ZBX_CEP_SYNC_OBJECT_STATE:
					cep_manager_sync_object_state(unit_args->shared->dbpool, &client);
					break;
				case ZBX_CEP_RESET_RULE:
					cep_manager_reset_rule(manager, message);
					break;
				case ZBX_RTC_SHUTDOWN:
					zabbix_log(LOG_LEVEL_DEBUG, "shutdown message received, terminating...");
					shutdown = 1;
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		/* only stop cep when history syncers no longer require it */
		if ((!ZBX_IS_RUNNING() || 1 == shutdown) && 0 == zbx_hc_refcount_peek())
		{
			int	is_empty;

			zbx_mw_queue_lock(manager->base.queue);
			is_empty = cep_queue_is_empty((zbx_cep_queue_t *)manager->base.queue);
			zbx_mw_queue_unlock(manager->base.queue);

			if (SUCCEED == is_empty)
				break;
		}

		zbx_mw_queue_lock(manager->base.queue);
		(void)zbx_mw_queue_drain_completed(manager->base.queue, &tasks);
		pending_num = cep_queue_pending_commits_num((zbx_cep_queue_t *)manager->base.queue);
		zbx_mw_queue_unlock(manager->base.queue);

		cep_manager_process_pending(manager);

		if (0 != tasks.values_num)
		{
			int	commits_num = manager->commits.values_num;

			cep_manager_process_finished(manager, &tasks);

			if (0 == commits_num && 0 != manager->commits.values_num)
				time_flush = time_now;
		}

		if (0 != manager->commits.values_num &&
				manager->commit_task_num < cep_manager_commit_limit(manager, cep_args))
		{
			int	commits_num = manager->commits.values_num + manager->commits_pending.values_num;

			if ( CEP_MANAGER_BATCH_LIMIT <= commits_num || 0 == pending_num ||
					CEP_MANAGER_FLUSH_TIMEOUT < time_now - time_flush)
			{
				cep_manager_flush_commmits(manager);
			}
		}

		if (0 != cep_manager_process_windows((int)time_now, &tasks))
		{
			zbx_mw_queue_lock(manager->base.queue);
			cep_queue_push_batch((zbx_cep_queue_t *)manager->base.queue, &tasks);
			zbx_mw_queue_unlock(manager->base.queue);

			zbx_vector_mw_task_ptr_clear(&tasks);
		}
	}

	zbx_supervisor_update_activity("%s [terminating]", unit_args->name);

	/* on normal exit the shutdown message already has been processed and no more messages will be sent */
	if (SUCCEED != ZBX_EXIT_STATUS())
		zbx_rtc_unsubscribe_service(cep_args->config_timeout, ZBX_IPC_SERVICE_CEP);

	cep_manager_save_window_groups(unit_args->shared->dbpool);
	cep_manager_free(manager);

	zbx_deinit_regexp_env();
	zbx_vector_mw_task_ptr_destroy(&tasks);

	zbx_supervisor_update_activity("%s [terminated]", unit_args->name);
	zbx_free(args);

#undef STAT_INTERVAL

	return NULL;
}
