/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "pp_queue.h"
#include "pp_task.h"
#include "zbxalgo.h"
#include "zbxpreprocbase.h"

#define PP_TASK_QUEUE_INIT_NONE		0x00
#define PP_TASK_QUEUE_INIT_LOCK		0x01
#define PP_TASK_QUEUE_INIT_EVENT	0x02

ZBX_PTR_VECTOR_IMPL(pp_top_stats_ptr, zbx_pp_top_stats_t *)

/* task sequence registry by itemid */
typedef struct
{
	zbx_uint64_t	itemid;
	zbx_pp_task_t	*task;
}
zbx_pp_item_task_sequence_t;

/******************************************************************************
 *                                                                            *
 * Purpose: initialize task queue                                             *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *             error - [OUT]                                                  *
 *                                                                            *
 * Return value: SUCCEED - the task queue was initialized successfully        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	pp_task_queue_init(zbx_pp_queue_t *queue, char **error)
{
	int	err, ret = FAIL;

	queue->workers_num = 0;
	queue->pending_num = 0;
	queue->finished_num = 0;
	queue->processing_num = 0;
	zbx_list_create(&queue->pending);
	zbx_list_create(&queue->immediate);
	zbx_list_create(&queue->finished);

	zbx_hashset_create(&queue->sequences, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != (err = pthread_mutex_init(&queue->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize task queue mutex: %s", zbx_strerror(err));
		goto out;
	}
	queue->init_flags |= PP_TASK_QUEUE_INIT_LOCK;

	if (0 != (err = pthread_cond_init(&queue->event, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize task queue conditional variable: %s", zbx_strerror(err));
		goto out;
	}
	queue->init_flags |= PP_TASK_QUEUE_INIT_EVENT;

	ret = SUCCEED;
out:
	if (FAIL == ret)
		pp_task_queue_destroy(queue);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear task list                                                   *
 *                                                                            *
 ******************************************************************************/
static void	pp_task_queue_clear_tasks(zbx_list_t *tasks)
{
	zbx_pp_task_t	*task = NULL;

	while (SUCCEED == zbx_list_pop(tasks, (void **)&task))
		pp_task_free(task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy task queue                                                *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_destroy(zbx_pp_queue_t *queue)
{
	if (0 != (queue->init_flags & PP_TASK_QUEUE_INIT_LOCK))
		pthread_mutex_destroy(&queue->lock);

	if (0 != (queue->init_flags & PP_TASK_QUEUE_INIT_EVENT))
		pthread_cond_destroy(&queue->event);

	pp_task_queue_clear_tasks(&queue->pending);
	zbx_list_destroy(&queue->pending);

	pp_task_queue_clear_tasks(&queue->immediate);
	zbx_list_destroy(&queue->immediate);

	pp_task_queue_clear_tasks(&queue->finished);
	zbx_list_destroy(&queue->finished);

	zbx_hashset_destroy(&queue->sequences);

	queue->init_flags = PP_TASK_QUEUE_INIT_NONE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: lock task queue                                                   *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_lock(zbx_pp_queue_t *queue)
{
	pthread_mutex_lock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlock task queue                                                 *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_unlock(zbx_pp_queue_t *queue)
{
	pthread_mutex_unlock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: register a new worker                                             *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_register_worker(zbx_pp_queue_t *queue)
{
	queue->workers_num++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deregister a worker                                               *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_deregister_worker(zbx_pp_queue_t *queue)
{
	queue->workers_num--;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add task to an existing sequence or create/append to a new one    *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *             task  - [IN] task to add                                       *
 *                                                                            *
 * Return value: The created sequence task or NULL if task was added to an    *
 *               existing sequence.                                           *
 *                                                                            *
 ******************************************************************************/
static zbx_pp_task_t	*pp_task_queue_add_sequence(zbx_pp_queue_t *queue, zbx_pp_task_t *task)
{
	zbx_pp_item_task_sequence_t	*sequence;
	zbx_pp_task_t			*new_task;

	if (NULL == (sequence = (zbx_pp_item_task_sequence_t *)zbx_hashset_search(&queue->sequences, &task->itemid)))
	{
		zbx_pp_item_task_sequence_t	sequence_local = {.itemid = task->itemid};

		sequence = (zbx_pp_item_task_sequence_t *)zbx_hashset_insert(&queue->sequences, &sequence_local,
				sizeof(sequence_local));

		sequence->task = pp_task_sequence_create(task->itemid);
		new_task = sequence->task;
	}
	else
		new_task = NULL;

	zbx_pp_task_sequence_t	*d_seq = (zbx_pp_task_sequence_t *)PP_TASK_DATA(sequence->task);

	(void)zbx_list_append(&d_seq->tasks, task, NULL);

	return new_task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue task to be processed before normal tasks                    *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *             task  - [IN] task to push                                      *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_push_immediate(zbx_pp_queue_t *queue, zbx_pp_task_t *task)
{
	switch (task->type)
	{
		case ZBX_PP_TASK_VALUE_SEQ:
		case ZBX_PP_TASK_DEPENDENT:
			queue->pending_num++;
			if (NULL == (task = pp_task_queue_add_sequence(queue, task)))
				return;
			break;
		case ZBX_PP_TASK_SEQUENCE:
			/* sequence task is just a container for other tasks - it does not affect statistics, */
			/* so there is no need to increment queue->pending_num                                */
			break;
		default:
			queue->pending_num++;
			break;
	}

	(void)zbx_list_append(&queue->immediate, task, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove task sequence                                              *
 *                                                                            *
 * Parameters: queue  - [IN] task queue                                       *
 *             itemid - [IN] task sequence itemid                             *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_remove_sequence(zbx_pp_queue_t *queue, zbx_uint64_t itemid)
{
	zbx_hashset_remove(&queue->sequences, &itemid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue test task to be processed                                   *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *             task  - [IN] task                                              *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_push_test(zbx_pp_queue_t *queue, zbx_pp_task_t *task)
{
	queue->pending_num++;
	(void)zbx_list_append(&queue->immediate, task, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue normal task to be processed                                 *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *             task  - [IN] task                                              *
 *                                                                            *
 * Comments: This function is used to push tasks created by new preprocessing *
 *           or testing requests.                                             *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_push(zbx_pp_queue_t *queue, zbx_pp_task_t *task)
{
	zbx_pp_task_value_t	*d = (zbx_pp_task_value_t *)PP_TASK_DATA(task);
	queue->pending_num++;

	if (ITEM_TYPE_INTERNAL != d->preproc->type)
	{
		(void)zbx_list_append(&queue->pending, task, NULL);
		return;
	}

	if (ZBX_PP_TASK_VALUE == task->type)
	{
		(void)zbx_list_append(&queue->immediate, task, NULL);
		return;
	}

	zbx_pp_task_t	*seq_task;

	if (NULL != (seq_task = pp_task_queue_add_sequence(queue, task)))
		(void)zbx_list_append(&queue->immediate, seq_task, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop task from task queue                                          *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *                                                                            *
 * Return value: The popped task or NULL if there are no tasks to be          *
 *               processed.                                                   *
 *                                                                            *
 * Comments: This function is used by workers to pop tasks for processing.    *
 *           Sequence tasks will be moved to existing tasks sequences or      *
 *           returned if there are no registered sequences for this item.     *
 *                                                                            *
 ******************************************************************************/
zbx_pp_task_t	*pp_task_queue_pop_new(zbx_pp_queue_t *queue)
{
	zbx_pp_task_t	*task = NULL;

	if (SUCCEED == zbx_list_pop(&queue->immediate, (void **)&task))
	{
		/* while sequence tasks do not affect statistics, the first task in sequence */
		/* does, so the statistics can be updated for all tasks                      */
		queue->pending_num--;
		queue->processing_num++;

		return (zbx_pp_task_t *)task;
	}

	while (SUCCEED == zbx_list_pop(&queue->pending, (void **)&task))
	{
		if (ZBX_PP_TASK_VALUE_SEQ == task->type)
		{
			/* task is being moved from pending to immediate queue */
			/* while still pending, so statistics are not affected */
			task = pp_task_queue_add_sequence(queue, task);
		}

		if (NULL != task)
		{
			queue->pending_num--;
			queue->processing_num++;

			return task;
		}
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: push finished task into queue                                     *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *             task  - [IN] task                                              *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_push_finished(zbx_pp_queue_t *queue, zbx_pp_task_t *task)
{
	queue->finished_num++;
	queue->processing_num--;
	(void)zbx_list_append(&queue->finished, task, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop finished task from queue                                      *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *                                                                            *
 * Return value: The popped task or NULL if there are no finished tasks.      *
 *                                                                            *
 ******************************************************************************/
zbx_pp_task_t	*pp_task_queue_pop_finished(zbx_pp_queue_t *queue)
{
	zbx_pp_task_t	*task;

	if (SUCCEED == zbx_list_pop(&queue->finished, (void **)&task))
	{
		queue->finished_num--;
		return task;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for queue notifications                                      *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *             error - [IN]                                                   *
 *                                                                            *
 * Return value: SUCCEED - the wait succeeded                                 *
 *               FAIL    - an error has occurred                              *
 *                                                                            *
 * Comments: This function is used by workers to wait for new tasks.          *
 *                                                                            *
 ******************************************************************************/
int	pp_task_queue_wait(zbx_pp_queue_t *queue, char **error)
{
	int	err;

	if (0 != (err = pthread_cond_wait(&queue->event, &queue->lock)))
	{
		*error = zbx_dsprintf(NULL, "cannot wait for conditional variable: %s", zbx_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify one worker                                                 *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *                                                                            *
 * Comments: This function is used by manager to notify a worker when a new   *
 *           task has been queued.                                            *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_notify(zbx_pp_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_signal(&queue->event)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot signal conditional variable: %s", zbx_strerror(err));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify all workers                                                *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *                                                                            *
 * Comments: This function is used by manager to notify workers when either   *
 *           multiple tasks have been pushed or when stopping workers.        *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_notify_all(zbx_pp_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_broadcast(&queue->event)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot broadcast conditional variable: %s", zbx_strerror(err));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get registered task sequence statistics sorted by number of tasks *
 *          in descending order                                               *
 *                                                                            *
 * Parameters: queue - [IN] task queue                                        *
 *             stats - [IN] sequence statistics                               *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_get_sequence_stats(zbx_pp_queue_t *queue, zbx_vector_pp_top_stats_ptr_t *stats)
{
	zbx_hashset_iter_t		iter;
	zbx_pp_item_task_sequence_t	*sequence;
	zbx_pp_top_stats_t		*stat;
	zbx_list_iterator_t		li;

	pp_task_queue_lock(queue);

	zbx_hashset_iter_reset(&queue->sequences, &iter);
	while (NULL != (sequence = (zbx_pp_item_task_sequence_t *)zbx_hashset_iter_next(&iter)))
	{
		stat = (zbx_pp_top_stats_t *)zbx_malloc(NULL, sizeof(zbx_pp_top_stats_t));
		stat->tasks_num = 0;
		stat->itemid = sequence->itemid;

		zbx_pp_task_sequence_t	*d_seq = (zbx_pp_task_sequence_t *)PP_TASK_DATA(sequence->task);

		if (NULL != d_seq->tasks.head)
		{
			zbx_list_iterator_init(&d_seq->tasks, &li);

			do
			{
				stat->tasks_num++;
			}
			while (SUCCEED == zbx_list_iterator_next(&li));
		}

		zbx_vector_pp_top_stats_ptr_append(stats, stat);
	}

	pp_task_queue_unlock(queue);

}
