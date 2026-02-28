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

#include "cep_queue.h"
#include "cep_task.h"
#include "cep.h"

#include "zbxalgo.h"
#include "zbxcommon.h"

typedef struct
{
	zbx_cep_origin_t	origin;
	zbx_queue_ptr_t		tasks;
	int			processing_num;
}
zbx_cep_task_group_t;

struct zbx_cep_queue
{
	/* requests from other processes containing IPC messages, have priority over locally generated tasks */
	zbx_queue_ptr_t	remote;

	/* locally created tasks for processing */
	zbx_queue_ptr_t	local;

	/* tasks that have completed processing */
	zbx_queue_ptr_t	finished;

	/* pending tasks grouped by event origin (source, object, objectid), each group enforcing */
	/* a limit on parallel tasks with excess tasks stored as pending in the group             */
	zbx_hashset_t	groups;

	/* total number of pending tasks in remote and local queues plus pending tasks in groups */
	int		pending_num;

	pthread_mutex_t	lock;
	pthread_cond_t	alarm;
};

/******************************************************************************
 *                                                                            *
 * Purpose: clear task group and free all tasks                               *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_group_clear(void *d)
{
	zbx_cep_task_group_t	*group = (zbx_cep_task_group_t *)d;
	zbx_cep_task_t		*task;

	while (NULL != (task = (zbx_cep_task_t *)zbx_queue_ptr_pop(&group->tasks)))
		cep_task_free(task);

	zbx_queue_ptr_destroy(&group->tasks);
}

static zbx_hash_t	cep_task_group_hash(const void *d)
{
	zbx_cep_task_group_t	*group = (zbx_cep_task_group_t *)d;

	return cep_origin_hash(&group->origin);
}

static int	cep_task_group_compare(const void *d1, const void *d2)
{
	zbx_cep_task_group_t	*g1 = (zbx_cep_task_group_t *)d1;
	zbx_cep_task_group_t	*g2 = (zbx_cep_task_group_t *)d2;

	return cep_origin_compare(&g1->origin, &g2->origin);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create queue instance                                             *
 *                                                                            *
 * Parameters: error - [OUT] error message in case of failure                 *
 *                                                                            *
 * Return value: pointer to newly created queue instance or NULL on failure   *
 *                                                                            *
 ******************************************************************************/
zbx_cep_queue_t	*cep_queue_create(char **error)
{
	zbx_cep_queue_t	*queue;
	int		err;

	queue = (zbx_cep_queue_t *)zbx_malloc(NULL, sizeof(zbx_cep_queue_t));

	if (0 != (err = pthread_mutex_init(&queue->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize CEP task queue mutex: %s", zbx_strerror(err));
		zbx_free(queue);

		return NULL;
	}

	if (0 != (err = pthread_cond_init(&queue->alarm, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize CEP task queue conditional variable: %s",
			zbx_strerror(err));
		pthread_mutex_destroy(&queue->lock);
		zbx_free(queue);

		return NULL;
	}

	zbx_queue_ptr_create(&queue->remote);
	zbx_queue_ptr_create(&queue->local);
	zbx_queue_ptr_create(&queue->finished);

	zbx_hashset_create_ext(&queue->groups, 100, cep_task_group_hash, cep_task_group_compare, cep_task_group_clear,
		ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	queue->pending_num = 0;

	return queue;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy queue instance and free all associated resources          *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_destroy(zbx_cep_queue_t *queue)
{
	zbx_cep_task_t	*task;

	zbx_hashset_destroy(&queue->groups);

	while (NULL != (task = (zbx_cep_task_t *)zbx_queue_ptr_pop(&queue->remote)))
		cep_task_free(task);

	zbx_queue_ptr_destroy(&queue->remote);

	while (NULL != (task = (zbx_cep_task_t *)zbx_queue_ptr_pop(&queue->local)))
		cep_task_free(task);

	zbx_queue_ptr_destroy(&queue->local);

	while (NULL != (task = (zbx_cep_task_t *)zbx_queue_ptr_pop(&queue->finished)))
		cep_task_free(task);

	zbx_queue_ptr_destroy(&queue->finished);

	pthread_mutex_destroy(&queue->lock);
	pthread_cond_destroy(&queue->alarm);

	zbx_free(queue);
}

void	cep_queue_lock(zbx_cep_queue_t *queue)
{
	pthread_mutex_lock(&queue->lock);
}

void	cep_queue_unlock(zbx_cep_queue_t *queue)
{
	pthread_mutex_unlock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get maximum number of parallel tasks for a given origin           *
 *                                                                            *
 * Parameters: origin - [IN] event origin descriptor                          *
 *                                                                            *
 * Return value: limit of concurrent tasks allowed for the origin             *
 *                                                                            *
 ******************************************************************************/
static int	cep_queue_task_limit_by_origin(const zbx_cep_origin_t *origin)
{
	switch (origin->source)
	{
		case EVENT_SOURCE_TRIGGERS:
		case EVENT_SOURCE_INTERNAL:
			return 1;
		default:
			return 1;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue event task and apply per-origin parallelism limits        *
 *                                                                            *
 * Parameters: queue    - [IN] queue instance                                 *
 *             task     - [IN] event task to enqueue                          *
 *             db_event - [IN] database event used to determine task origin   *
 *                                                                            *
 * Comments: The caller must hold the queue lock when calling this function.  *
 *           The task is either pushed to the local queue for immediate       *
 *           processing or stored as pending in the corresponding origin      *
 *           group when the per-origin limit is reached.                      *
 *                                                                            *
 ******************************************************************************/
static void	cep_queue_push_event_nl(zbx_cep_queue_t *queue, zbx_cep_task_t *task, const zbx_db_event *db_event)
{
	zbx_cep_task_group_t	pending_local = {
						.origin = {
							.source = db_event->source,
							.object = db_event->object,
							.objectid = db_event->objectid
						}
					};
	zbx_cep_task_group_t	*group;
	int			groups_num = queue->groups.num_data, pending_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:%d object:%d objectid:" ZBX_FS_UI64, __func__,
			pending_local.origin.source, pending_local.origin.object, pending_local.origin.objectid);

	group = (zbx_cep_task_group_t *)zbx_hashset_insert(&queue->groups, &pending_local, sizeof(pending_local));

	if (groups_num != queue->groups.num_data)
	{
		zbx_queue_ptr_create(&group->tasks);
		group->processing_num = 0;
	}

	if (group->processing_num < cep_queue_task_limit_by_origin(&group->origin))
	{
		zbx_queue_ptr_push(&queue->local, task);
		group->processing_num++;
		pending_num = 0;
	}
	else
	{
		zbx_queue_ptr_push(&group->tasks, task);
		pending_num = zbx_queue_ptr_values_num(&group->tasks);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() pending:%d", __func__, pending_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue task into queue according to task type                    *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *             task  - [IN] task to enqueue                                   *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_push(zbx_cep_queue_t *queue, zbx_cep_task_t *task)
{
	int	pending_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() task:%d", task->type);

	pthread_mutex_lock(&queue->lock);

	switch (task->type)
	{
		case CEP_TASK_REMOTE:
			zbx_queue_ptr_push(&queue->remote, task);
			break;
		case CEP_TASK_EVENT:
			cep_queue_push_event_nl(queue, task, ((zbx_cep_task_event_t *)task)->db_event);
			break;
		case CEP_TASK_CLOSE_EVENT:
			cep_queue_push_event_nl(queue, task, ((zbx_cep_task_close_event_t *)task)->parent.db_event);
			break;
		default:
			zbx_queue_ptr_push(&queue->local, task);
			break;
	}

	pending_num = ++queue->pending_num;
	cep_queue_notify(queue);
	pthread_mutex_unlock(&queue->lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() queued:%d", __func__, pending_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue batch of tasks into queue according to their type         *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *             tasks - [IN] vector of tasks to enqueue                        *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_push_batch(zbx_cep_queue_t *queue, zbx_vector_cep_task_ptr_t *tasks)
{
	int	pending_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	pthread_mutex_lock(&queue->lock);

	for (int i = 0; i < tasks->values_num; i++)
	{
		zbx_cep_task_t	*task = tasks->values[i];

		switch (task->type)
		{
			case CEP_TASK_REMOTE:
				zbx_queue_ptr_push(&queue->remote, task);
				break;
			case CEP_TASK_EVENT:
				cep_queue_push_event_nl(queue, task, ((zbx_cep_task_event_t *)task)->db_event);
				break;
			case CEP_TASK_CLOSE_EVENT:
				cep_queue_push_event_nl(queue, task,
						((zbx_cep_task_close_event_t *)task)->parent.db_event);
				break;
			default:
				zbx_queue_ptr_push(&queue->local, task);
				break;
		}
	}

	queue->pending_num += tasks->values_num;
	pending_num = queue->pending_num;
	cep_queue_notify_all(queue);
	pthread_mutex_unlock(&queue->lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() queued:%d", __func__, pending_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue next pending event task for the same origin               *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *             task  - [IN] completed event task used to locate origin group  *
 *                                                                            *
 * Comments: The caller must hold the queue lock when calling this function.  *
 *           It finds the task group by event origin and either enqueues the  *
 *           next pending task from the group into the local queue.           *
 *                                                                            *
 ******************************************************************************/
static void	cep_queue_push_next_event_task_nl(zbx_cep_queue_t *queue, zbx_cep_task_event_t *task)
{
	zbx_cep_task_group_t	pending_local = {
					.origin = {
						.source = task->db_event->source,
						.object = task->db_event->object,
						.objectid = task->db_event->objectid
					}
	};
	zbx_cep_task_group_t	*group;
	int			pending_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:%d object:%d objectid:" ZBX_FS_UI64, __func__,
			pending_local.origin.source, pending_local.origin.object, pending_local.origin.objectid);

	if (NULL != (group = (zbx_cep_task_group_t *)zbx_hashset_search(&queue->groups, &pending_local)))
	{
		zbx_cep_task_t	*next;

		if (NULL != (next = (zbx_cep_task_t *)zbx_queue_ptr_pop(&group->tasks)))
		{
			zbx_queue_ptr_push(&queue->local, next);
			pending_num = zbx_queue_ptr_values_num(&group->tasks);
		}
		else
		{
			if (0 == --group->processing_num)
				zbx_hashset_remove_direct(&queue->groups, group);

			pending_num = 0;
		}
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("flushing unregistered task for source:%d object:%d objectid:"
				ZBX_FS_UI64, task->db_event->source, task->db_event->object, task->db_event->objectid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() queued:%d", __func__, pending_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue finished task                                             *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *             task  - [IN] finished task to enqueue                          *
 *                                                                            *
 * Comments: The caller must hold the queue lock when calling this function.  *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_push_finished_nl(zbx_cep_queue_t *queue, zbx_cep_task_t *task)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() task:%d", task->type);

	zbx_queue_ptr_push(&queue->finished, task);
	queue->pending_num--;

	switch (task->type)
	{
		case CEP_TASK_EVENT:
			cep_queue_push_next_event_task_nl(queue, (zbx_cep_task_event_t *)task);
			break;
		case CEP_TASK_CLOSE_EVENT:
			cep_queue_push_next_event_task_nl(queue, &((zbx_cep_task_close_event_t *)task)->parent);
			break;
		default:
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() pending:%d finished:%d", __func__, queue->pending_num,
			queue->finished);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue finished task                                             *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *             task  - [IN] finished task to enqueue                          *
 *                                                                            *
 * Comments: Allows adding a task for committing without processing.          *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_push_finished_direct(zbx_cep_queue_t *queue, zbx_cep_task_t *task)
{
	pthread_mutex_lock(&queue->lock);
	queue->pending_num++;
	cep_queue_push_finished_nl(queue, task);
	pthread_mutex_unlock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop next task from queue                                          *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *                                                                            *
 * Return value: next task or NULL if the queue is empty                      *
 *                                                                            *
 * Comments: The caller must hold the queue lock when calling this function.  *
 *                                                                            *
 ******************************************************************************/

zbx_cep_task_t	*cep_queue_pop_nl(zbx_cep_queue_t *queue)
{
	zbx_cep_task_t	*task;

	if (NULL == (task = zbx_queue_ptr_pop(&queue->remote)))
		task = zbx_queue_ptr_pop(&queue->local);

	return task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait on queue until notified                                      *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *             error - [OUT] error message in case of failure                 *
 *                                                                            *
 * Return value: SUCCEED on success or FAIL on error                          *
 *                                                                            *
 * Comments: The caller must hold the queue lock when calling this function.  *
 *                                                                            *
 ******************************************************************************/
int	cep_queue_wait(zbx_cep_queue_t *queue, char **error)
{
	int	err;

	if (0 != (err = pthread_cond_wait(&queue->alarm, &queue->lock)))
	{
		*error = zbx_dsprintf(NULL, "cannot wait for conditional variable: %s", zbx_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify one waiting worker about queue update                      *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *                                                                            *
 * Comments: Used to wake a single waiter when new task is available or       *
 *           queue state has changed.                                         *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_notify(zbx_cep_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_signal(&queue->alarm)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot signal conditional variable: %s", zbx_strerror(err));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify all waiting workers about queue update                     *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *                                                                            *
 * Comments: Used to wake all waiters when new tasks are available or the     *
 *           queue state has changed.                                         *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_notify_all(zbx_cep_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_broadcast(&queue->alarm)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot broadcast conditional variable: %s", zbx_strerror(err));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop all finished tasks from queue                                 *
 *                                                                            *
 * Parameters: queue - [IN]  queue instance                                   *
 *             tasks - [OUT] vector to store popped finished tasks            *
 *                                                                            *
 * Return value: total number of pending tasks after popping finished tasks   *
 *                                                                            *
 * Comments: The function acquires the queue lock, moves all finished tasks   *
 *           to the provided vector, reads the current pending task count,    *
 *           and then releases the lock.                                      *
 *                                                                            *
 ******************************************************************************/
int	cep_queue_pop_finished(zbx_cep_queue_t *queue, zbx_vector_cep_task_ptr_t *tasks)
{
	zbx_cep_task_t	*task;
	int		pending_num;

	pthread_mutex_lock(&queue->lock);

	zbx_vector_cep_task_ptr_reserve(tasks, zbx_queue_ptr_values_num(&queue->finished));
	while (NULL != (task = (zbx_cep_task_t *)zbx_queue_ptr_pop(&queue->finished)))
		zbx_vector_cep_task_ptr_append(tasks, task);

	pending_num = queue->pending_num;

	pthread_mutex_unlock(&queue->lock);

	return pending_num;
}

