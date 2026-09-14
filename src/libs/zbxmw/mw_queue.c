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

#include "mw_queue.h"
#include "zbxmw.h"
#include "zbxalgo.h"

/******************************************************************************
 *                                                                            *
 * Purpose: initialize task queue                                             *
 *                                                                            *
 * Parameters: queue - [OUT] queue to initialize                              *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	mw_queue_init(zbx_mw_queue_t *queue, char **error)
{
	int	err;

	if (0 != (err = pthread_mutex_init(&queue->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize CEP task queue mutex: %s", zbx_strerror(err));

		return FAIL;
	}

	if (0 != (err = pthread_cond_init(&queue->notify, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize CEP task queue conditional variable: %s",
			zbx_strerror(err));
		pthread_mutex_destroy(&queue->lock);

		return FAIL;
	}

	zbx_queue_ptr_create(&queue->priority);
	zbx_queue_ptr_create(&queue->normal);
	zbx_queue_ptr_create(&queue->completed);

	queue->pending_num = 0;
	queue->processing_num = 0;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release resources allocated by task queue                         *
 *                                                                            *
 * Comments: Remaining tasks in all queues are freed using their own          *
 *           free_func callbacks.                                             *
 *                                                                            *
 ******************************************************************************/
void	mw_queue_clear(zbx_mw_queue_t *queue)
{
	zbx_mw_task_t	*task;

	while (NULL != (task = (zbx_mw_task_t *)zbx_queue_ptr_pop(&queue->priority)))
		task->free_func(task);

	zbx_queue_ptr_destroy(&queue->priority);

	while (NULL != (task = (zbx_mw_task_t *)zbx_queue_ptr_pop(&queue->normal)))
		task->free_func(task);

	zbx_queue_ptr_destroy(&queue->normal);

	while (NULL != (task = (zbx_mw_task_t *)zbx_queue_ptr_pop(&queue->completed)))
		task->free_func(task);

	zbx_queue_ptr_destroy(&queue->completed);

	pthread_mutex_destroy(&queue->lock);
	pthread_cond_destroy(&queue->notify);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify one worker waiting on the queue                            *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_notify(zbx_mw_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_signal(&queue->notify)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot signal conditional variable: %s", zbx_strerror(err));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify all workers waiting on the queue                           *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_notify_all(zbx_mw_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_broadcast(&queue->notify)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot broadcast conditional variable: %s", zbx_strerror(err));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for queue notification                                       *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 * Comments: Must be called with the queue lock held.                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_mw_queue_wait(zbx_mw_queue_t *queue, char **error)
{
	int	err;

	if (0 != (err = pthread_cond_wait(&queue->notify, &queue->lock)))
	{
		*error = zbx_dsprintf(NULL, "cannot wait for conditional variable: %s", zbx_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: push task to priority queue                                       *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *             task  - [IN] task to push                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_push_priority(zbx_mw_queue_t *queue, zbx_mw_task_t *task)
{
	zbx_queue_ptr_push(&queue->priority, task);
	queue->pending_num++;
	zbx_mw_queue_notify(queue);
}

/******************************************************************************
 *                                                                            *
 * Purpose: push task to normal queue                                         *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *             task  - [IN] task to push                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_push_normal(zbx_mw_queue_t *queue, zbx_mw_task_t *task)
{
	zbx_queue_ptr_push(&queue->normal, task);
	queue->pending_num++;
	zbx_mw_queue_notify(queue);
}

/******************************************************************************
 *                                                                            *
 * Purpose: lock queue                                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_lock(zbx_mw_queue_t *queue)
{
	pthread_mutex_lock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlock queue                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_unlock(zbx_mw_queue_t *queue)
{
	pthread_mutex_unlock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop next task from queue                                          *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *                                                                            *
 * Return value: next task, or NULL if queue is empty                         *
 *                                                                            *
 * Comments:  Must be called with the queue lock held.                        *
 *            Priority queue is checked before normal queue.                  *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*zbx_mw_queue_pop(zbx_mw_queue_t *queue)
{
	zbx_mw_task_t	*task;

	if (NULL == (task = zbx_queue_ptr_pop(&queue->priority)))
		task = zbx_queue_ptr_pop(&queue->normal);

	if (NULL != task)
	{
		queue->pending_num--;
		queue->processing_num++;
	}

	return task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: push completed task to completed queue                            *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *             task  - [IN] completed task                                    *
 *                                                                            *
 * Comments: Must be called with the queue lock held.                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_push_completed(zbx_mw_queue_t *queue, zbx_mw_task_t *task)
{
	zbx_queue_ptr_push(&queue->completed, task);
	queue->processing_num--;
}

/******************************************************************************
 *                                                                            *
 * Purpose: push completed task to completed queue without updating counters  *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *             task  - [IN] completed task                                    *
 *                                                                            *
 * Comments: Must be called with the queue lock held.                         *
 *           Does not decrement processing_num; use when the task was         *
 *           completed without being processed.                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_push_completed_direct(zbx_mw_queue_t *queue, zbx_mw_task_t *task)
{
	zbx_queue_ptr_push(&queue->completed, task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop next task from completed queue                                *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *                                                                            *
 * Return value: next completed task, or NULL if queue is empty               *
 *                                                                            *
 * Comments: Must be called with the queue lock held.                         *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*zbx_mw_queue_pop_completed(zbx_mw_queue_t *queue)
{
	return (zbx_mw_task_t *)zbx_queue_ptr_pop(&queue->completed);
}

/******************************************************************************
 *                                                                            *
 * Purpose: move all completed tasks to vector                                *
 *                                                                            *
 * Parameters: queue - [IN/OUT]                                               *
 *             tasks - [OUT] vector to append completed tasks to              *
 *                                                                            *
 * Return value: number of pending tasks                                      *
 *                                                                            *
 * Comments: Must be called with the queue lock held.                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_mw_queue_drain_completed(zbx_mw_queue_t *queue, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_mw_task_t	*task;
	int		pending_num;

	zbx_vector_mw_task_ptr_reserve(tasks, (size_t)zbx_queue_ptr_values_num(&queue->completed));

	while (NULL != (task = (zbx_mw_task_t *)zbx_queue_ptr_pop(&queue->completed)))
	{
		zbx_vector_mw_task_ptr_append(tasks, task);
	}

	pending_num = zbx_queue_ptr_values_num(&queue->priority) + zbx_queue_ptr_values_num(&queue->normal);

	return pending_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get queue statistics                                              *
 *                                                                            *
 * Parameters: queue         - [IN]  queue to get statistics for              *
 *             priority_num  - [OUT] number of priority tasks                 *
 *             normal_num    - [OUT] number of normal tasks                   *
 *             completed_num - [OUT] number of completed tasks                *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_queue_get_stats(zbx_mw_queue_t *queue, int *priority_num, int *normal_num, int *completed_num)
{
	*priority_num = zbx_queue_ptr_values_num(&queue->priority);
	*normal_num = zbx_queue_ptr_values_num(&queue->normal);
	*completed_num = zbx_queue_ptr_values_num(&queue->completed);
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocate and initialize task                                      *
 *                                                                            *
 * Parameters: type      - [IN] task type                                     *
 *             task_free - [IN] task destructor callback                      *
 *             size      - [IN] total size of the task structure              *
 *                                                                            *
 * Return value: allocated task                                               *
 *                                                                            *
 * Comments: Size allows allocating extended task structures that embed       *
 *           zbx_mw_task_t as their first member.                             *
 *                                                                            *
 ******************************************************************************/
zbx_mw_task_t	*zbx_mw_task_create(int type, void (*task_free)(void *a), size_t size)
{
	zbx_mw_task_t	*task;

	task = (zbx_mw_task_t *)zbx_calloc(NULL, 1, size);

	task->type = type;
	task->free_func = task_free;

	return task;
}

