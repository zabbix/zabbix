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
#include "zbx_cep.h"

#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxmw.h"

typedef struct
{
	zbx_cep_origin_t	origin;
	zbx_queue_ptr_t		tasks;
	int			processing_num;
}
zbx_cep_task_group_t;

struct zbx_cep_queue
{
	/* remote requests (IPC messages) will be queued as priority tasks */
	zbx_mw_queue_t	base;

	/* pending tasks grouped by event origin (source, object, objectid), each group enforcing */
	zbx_hashset_t	groups;
	/* a limit on parallel tasks with excess tasks stored as pending in the group             */

	/* number of pending tasks in groups */
	int		group_tasks_num;

	/* number of tasks that might require commit */
	int		pending_commits_num;
};

/******************************************************************************
 *                                                                            *
 * Purpose: clear task group and free all tasks                               *
 *                                                                            *
 ******************************************************************************/
static void	cep_task_group_clear(void *d)
{
	zbx_cep_task_group_t	*group = (zbx_cep_task_group_t *)d;
	zbx_mw_task_t		*task;

	while (NULL != (task = (zbx_mw_task_t *)zbx_queue_ptr_pop(&group->tasks)))
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
 * Purpose: initialize queue instance                                         *
 *                                                                            *
 ******************************************************************************/
zbx_cep_queue_t	*cep_queue_create(void)
{
	zbx_cep_queue_t	*queue;

	queue = (zbx_cep_queue_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_queue_t));

	zbx_hashset_create_ext(&queue->groups, 100, cep_task_group_hash, cep_task_group_compare, cep_task_group_clear,
		ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	queue->group_tasks_num = 0;
	queue->pending_commits_num = 0;

	return queue;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy queue instance and free all associated resources          *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_clear(zbx_cep_queue_t *queue)
{
	zbx_hashset_destroy(&queue->groups);
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
static void	cep_queue_push_event(zbx_cep_queue_t *queue, zbx_mw_task_t *task, const zbx_db_event *db_event)
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
		zbx_mw_queue_push_normal(&queue->base, task);
		group->processing_num++;
		pending_num = 0;
	}
	else
	{
		zbx_queue_ptr_push(&group->tasks, task);
		pending_num = zbx_queue_ptr_values_num(&group->tasks);
		queue->group_tasks_num++;
	}

	queue->pending_commits_num++;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() pending:%d", __func__, pending_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue task into queue according to task type                    *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *             task  - [IN] task to enqueue                                   *
 *                                                                            *
 * Comments: The caller must hold the queue lock when calling this function.  *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_push(zbx_cep_queue_t *queue, zbx_mw_task_t *task)
{
	int	pending_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() task:%d", __func__, task->type);

	switch (task->type)
	{
		case CEP_TASK_REMOTE:
			zbx_mw_queue_push_priority(&queue->base, task);
			break;
		case CEP_TASK_EVENT:
			cep_queue_push_event(queue, task, ((zbx_cep_task_event_t *)task)->db_event);
			break;
		default:
			zbx_mw_queue_push_normal(&queue->base, task);
			break;
	}

	pending_num = queue->base.pending_num + queue->group_tasks_num;
	zbx_mw_queue_notify(&queue->base);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() queued:%d", __func__, pending_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue batch of tasks into queue according to their type         *
 *                                                                            *
 * Parameters: queue - [IN] queue instance                                    *
 *             tasks - [IN] vector of tasks to enqueue                        *
 *                                                                            *
 * Comments: The caller must hold the queue lock when calling this function.  *
 *                                                                            *
 ******************************************************************************/
void	cep_queue_push_batch(zbx_cep_queue_t *queue, zbx_vector_mw_task_ptr_t *tasks)
{
	int	pending_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks:%d", __func__, tasks->values_num);

	for (int i = 0; i < tasks->values_num; i++)
	{
		zbx_mw_task_t	*task = tasks->values[i];

		switch (task->type)
		{
			case CEP_TASK_REMOTE:
				zbx_mw_queue_push_priority(&queue->base, task);
				break;
			case CEP_TASK_EVENT:
				cep_queue_push_event(queue, task, ((zbx_cep_task_event_t *)task)->db_event);
				break;
			default:
				zbx_mw_queue_push_normal(&queue->base, task);
				break;
		}
	}

	pending_num = queue->base.pending_num + queue->group_tasks_num;
	zbx_mw_queue_notify_all(&queue->base);

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
static void	cep_queue_push_next_event_task(zbx_cep_queue_t *queue, zbx_cep_task_event_t *task)
{
	zbx_cep_task_group_t	pending_local = {
					.origin = {
						.source = task->db_event->source,
						.object = task->db_event->object,
						.objectid = task->db_event->objectid
					}
	};
	zbx_cep_task_group_t	*group;
	int			pending_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:%d object:%d objectid:" ZBX_FS_UI64, __func__,
			pending_local.origin.source, pending_local.origin.object, pending_local.origin.objectid);

	if (NULL != (group = (zbx_cep_task_group_t *)zbx_hashset_search(&queue->groups, &pending_local)))
	{
		zbx_mw_task_t	*next;

		if (NULL != (next = (zbx_mw_task_t *)zbx_queue_ptr_pop(&group->tasks)))
		{
			zbx_mw_queue_push_normal(&queue->base, next);
			pending_num = zbx_queue_ptr_values_num(&group->tasks);
			queue->group_tasks_num--;
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
void	cep_queue_push_completed(zbx_cep_queue_t *queue, zbx_mw_task_t *task)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() task:%d", __func__, task->type);

	zbx_mw_queue_push_completed(&queue->base, task);

	switch (task->type)
	{
		case CEP_TASK_EVENT:
			cep_queue_push_next_event_task(queue, (zbx_cep_task_event_t *)task);
			queue->pending_commits_num--;
			break;
		default:
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() pending:%d finished:%d", __func__, queue->base.pending_num,
			zbx_queue_ptr_values_num(&queue->base.completed));
}

int	cep_queue_pending_commits_num(zbx_cep_queue_t *queue)
{
	return queue->pending_commits_num;
}

int	cep_queue_is_empty(zbx_cep_queue_t *queue)
{
	if (0 != queue->base.pending_num || 0 != queue->base.processing_num)
		return FAIL;

	if (0 != queue->group_tasks_num)
		return FAIL;

	if (0 != zbx_queue_ptr_values_num(&queue->base.completed))
		return FAIL;

	return SUCCEED;
}
