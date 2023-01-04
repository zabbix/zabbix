/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "pp_queue.h"
#include "pp_task.h"

#include "zbxcommon.h"
#include "log.h"
#include "zbxalgo.h"
#include "zbxsysinc.h"

#define PP_TASK_QUEUE_INIT_NONE		0x00
#define PP_TASK_QUEUE_INIT_LOCK		0x01
#define PP_TASK_QUEUE_INIT_EVENT	0x02

/* task sequence registry by itemid */
typedef struct
{
	zbx_uint64_t	itemid;
	zbx_pp_task_t	*task;
}
zbx_pp_item_task_sequence_t;

static void	pp_item_task_sequence_clear(void *d)
{
	zbx_pp_item_task_sequence_t	*seq = (zbx_pp_item_task_sequence_t *)d;

	pp_task_free(seq->task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize task queue                                             *
 *                                                                            *
 * Parameters: queue      - [IN] the task queue                               *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the task queue was initialized successfully        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	pp_task_queue_init(zbx_pp_queue_t *queue, char **error)
{
	int	err, ret = FAIL;

	queue->workers_num = 0;
	queue->queued_num = 0;
	zbx_list_create(&queue->pending);
	zbx_list_create(&queue->immediate);
	zbx_list_create(&queue->finished);

	zbx_hashset_create_ext(&queue->sequences, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			pp_item_task_sequence_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

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
 * Purpose: destroy task queue                                                *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_destroy(zbx_pp_queue_t *queue)
{
	if (0 != (queue->init_flags & PP_TASK_QUEUE_INIT_LOCK))
		pthread_mutex_destroy(&queue->lock);

	if (0 != (queue->init_flags & PP_TASK_QUEUE_INIT_EVENT))
		pthread_cond_destroy(&queue->event);

	zbx_hashset_destroy(&queue->sequences);

	zbx_list_destroy(&queue->pending);
	zbx_list_destroy(&queue->immediate);
	zbx_list_destroy(&queue->finished);

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
 * Parameters: queue - [IN] the task queue                                    *
 *             task  - [IN] the task to add                                   *
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

	zbx_list_append(&d_seq->tasks, task, NULL);

	return new_task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue task to be processed before normal tasks                    *
 *                                                                            *
 * Parameters: queue - [IN] the task queue                                    *
 *             task  - [IN] the task to push                                  *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_push_immediate(zbx_pp_queue_t *queue, zbx_pp_task_t *task)
{
	if (ZBX_PP_TASK_VALUE_SEQ == task->type || ZBX_PP_TASK_DEPENDENT == task->type)
	{
		if (NULL == (task = pp_task_queue_add_sequence(queue, task)))
			return;
	}

	queue->queued_num++;

	zbx_list_append(&queue->immediate, task, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove task sequence                                              *
 *                                                                            *
 * Parameters: queue  - [IN] the task queue                                   *
 *             itemid - [IN] the task sequence itemid                         *
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
 * Parameters: queue - [IN] the task queue                                    *
 *             task  - [IN] the task                                          *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_push_test(zbx_pp_queue_t *queue, zbx_pp_task_t *task)
{
	queue->queued_num++;
	zbx_list_append(&queue->immediate, task, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue normal task to be processed                                 *
 *                                                                            *
 * Parameters: queue - [IN] the task queue                                    *
 *             task  - [IN] the task                                          *
 *                                                                            *
 * Comments: This function is used to push tasks created by new preprocessing *
 *           or testing requests.                                             *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_push(zbx_pp_queue_t *queue, zbx_pp_item_t *item, zbx_pp_task_t *task)
{
	if (ITEM_TYPE_INTERNAL != item->preproc->type)
	{
		queue->queued_num++;
		zbx_list_append(&queue->pending, task, NULL);
		return;
	}

	if (ZBX_PP_TASK_VALUE == task->type)
	{
		queue->queued_num++;
		zbx_list_append(&queue->immediate, task, NULL);
		return;
	}

	zbx_pp_task_t	*seq_task;

	if (NULL != (seq_task = pp_task_queue_add_sequence(queue, task)))
	{
		queue->queued_num++;
		zbx_list_append(&queue->immediate, seq_task, NULL);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop task from task queue                                          *
 *                                                                            *
 * Parameters: queue - [IN] the task queue                                    *
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
		queue->queued_num--;
		return (zbx_pp_task_t *)task;
	}

	while (SUCCEED == zbx_list_pop(&queue->pending, (void **)&task))
	{
		queue->queued_num--;

		if (ZBX_PP_TASK_VALUE_SEQ == task->type)
			task = pp_task_queue_add_sequence(queue, task);

		if (NULL != task)
			return task;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: push finished task into queue                                     *
 *                                                                            *
 * Parameters: queue - [IN] the task queue                                    *
 *             task  - [IN] the task                                          *
 *                                                                            *
 ******************************************************************************/
void	pp_task_queue_push_finished(zbx_pp_queue_t *queue, zbx_pp_task_t *task)
{
	zbx_list_append(&queue->finished, task, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pop finished task from queue                                      *
 *                                                                            *
 * Parameters: queue - [IN] the task queue                                    *
 *                                                                            *
 * Return value: The popped task or NULL if there are no finished tasks.      *
 *                                                                            *
 ******************************************************************************/
zbx_pp_task_t	*pp_task_queue_pop_finished(zbx_pp_queue_t *queue)
{
	zbx_pp_task_t	*task;

	if (SUCCEED == zbx_list_pop(&queue->finished, (void **)&task))
		return task;

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for queue notifications                                      *
 *                                                                            *
 * Parameters: queue - [IN] the task queue                                    *
 *                                                                            *
 * Return value: SUCCEED - the wait succeeded                                 *
 *               FAIL    - an error has occurred                              *
 *                                                                            *
 * Comments: This function is used by workers to wait for new tasks.          *
 *                                                                            *
 ******************************************************************************/
int	pp_task_queue_wait(zbx_pp_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_wait(&queue->event, &queue->lock)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot wait for conditional variable: %s", zbx_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify one worker                                                 *
 *                                                                            *
 * Parameters: queue - [IN] the task queue                                    *
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
 * Parameters: queue - [IN] the task queue                                    *
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
