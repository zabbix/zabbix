/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "common.h"
#include "queue.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_create                                                 *
 *                                                                            *
 * Purpose: create queue                                                      *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *             size  - [IN] size of an item value                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_create(queue_t *queue, unsigned int size)
{
	memset(queue, 0, sizeof(*queue));
	queue->size = size;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_destroy                                                *
 *                                                                            *
 * Purpose: destroy queue                                                     *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_destroy(queue_t *queue)
{
	while (FAIL != zbx_queue_dequeue(queue, NULL))
		;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_enqueue_after                                          *
 *                                                                            *
 * Purpose: enqueue value after specified position in the queue               *
 *                                                                            *
 * Parameters: queue    - [IN] the queue                                      *
 *             after    - [IN] specified position                             *
 *             value    - [IN] the value to be enqueued                       *
 *             enqueued - [OUT] pointer to the enqueued queue item            *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_enqueue_after(queue_t *queue, queue_item_t *after, const void *value, queue_item_t **enqueued)
{
	queue_item_t *item = zbx_malloc(NULL, sizeof(queue_item_t) + queue->size - sizeof(void *));
	item->next = NULL;
	memcpy(&item->data, value, queue->size);

	if (NULL == after)
		after = queue->tail;

	if (NULL != after)
	{
		item->next = after->next;
		after->next = item;
	}
	else
		queue->head = item;

	if (after == queue->tail)
		queue->tail = item;

	if (NULL != enqueued)
		*enqueued = item;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_enqueue                                                *
 *                                                                            *
 * Purpose: enqueue value into the queue at the end                           *
 *                                                                            *
 * Parameters: queue    - [IN] the queue                                      *
 *             value    - [IN] the value to add                               *
 *             enqueued - [OUT] pointer to the enqueued queue item            *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_enqueue(queue_t *queue, const void *value, queue_item_t **enqueued)
{
	return zbx_queue_enqueue_after(queue, NULL, value, enqueued);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_prepend                                                *
 *                                                                            *
 * Purpose: prepend value into the queue                                      *
 *                                                                            *
 * Parameters: queue    - [IN] the queue                                      *
 *             value    - [IN] the value to prepend                           *
 *             enqueued - [OUT] pointer to the enqueued queue item            *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_enqueue_first(queue_t *queue, const void *value, queue_item_t **enqueued)
{
	queue_item_t *item = zbx_malloc(NULL, sizeof(queue_item_t) + queue->size - sizeof(void *));
	memcpy(&item->data, value, queue->size);
	item->next = queue->head;
	queue->head = item;

	if (NULL == queue->tail)
		queue->tail = item;

	if (NULL != enqueued)
		*enqueued = item;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_dequeue                                                *
 *                                                                            *
 * Purpose: dequeue value from the queue                                      *
 *                                                                            *
 * Parameters: queue - [IN]  the queue                                        *
 *             value - [OUT] the value                                        *
 *                                                                            *
 * Return value: SUCCEED is returned if queue is not empty, otherwise, FAIL   *
 *               is returned.                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_queue_dequeue(queue_t *queue, void *value)
{
	queue_item_t	*head;

	if (NULL == queue->head)
		return FAIL;

	if (NULL != value)
		memcpy(value, &queue->head->data, queue->size);

	head = queue->head;
	queue->head = queue->head->next;
	zbx_free(head);

	if (NULL == queue->head)
		queue->tail = NULL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_peek                                                   *
 *                                                                            *
 * Purpose: get value from the queue without dequeuing                        *
 *                                                                            *
 * Parameters: queue - [IN]  the queue                                        *
 *             value - [OUT] the value                                        *
 *                                                                            *
 * Return value: SUCCEED is returned if queue is not empty, otherwise, FAIL   *
 *               is returned.                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_queue_peek(const queue_t *queue, void **value)
{
	if (NULL != queue->head)
	{
		*value = &queue->head->data;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_iterator_init                                          *
 *                                                                            *
 * Purpose: initialize queue iterator                                         *
 *                                                                            *
 * Parameters: queue    - [IN]  the queue                                     *
 *             iterator - [OUT] the iterator to be initialized                *
 *                                                                            *
 ******************************************************************************/
void zbx_queue_iterator_init(queue_t *queue, queue_iterator_t *iterator)
{
	iterator->queue = queue;
	iterator->next = queue->head;
	iterator->current = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_iterator_next                                          *
 *                                                                            *
 * Purpose: advance queue iterator                                            *
 *                                                                            *
 * Parameters: iterator - [IN]  the iterator to be advanced                   *
 *             value - [OUT] the value                                        *
 *                                                                            *
 * Return value: SUCCEED is returned if next queue item exists, otherwise,    *
 *               FAIL is returned.                                            *
 *                                                                            *
 ******************************************************************************/
int zbx_queue_iterator_next(queue_iterator_t *iterator)
{
	if (NULL != iterator->next)
	{
		iterator->current = iterator->next;
		iterator->next = iterator->next->next;

		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_iterator_peek                                          *
 *                                                                            *
 * Purpose: get value without removing it from queue                          *
 *                                                                            *
 * Parameters: iterator - [IN]  initialized queue iterator                    *
 *             value    - [OUT] the value                                     *
 *                                                                            *
 * Return value: SUCCEED is returned if item existst, otherwise, FAIL is      *
 *               returned.                                                    *
 *                                                                            *
 ******************************************************************************/
int zbx_queue_iterator_peek(const queue_iterator_t *iterator, void **value)
{
	if (NULL != iterator->current)
	{
		*value = &iterator->current->data;
		return SUCCEED;
	}

	return FAIL;
}
