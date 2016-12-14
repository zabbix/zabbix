/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "zbxalgo.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_ptr_values_num                                         *
 *                                                                            *
 * Purpose: calculates the number of values in queue                          *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *                                                                            *
 * Return value: The number of values in queue                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_queue_ptr_values_num(zbx_queue_ptr_t *queue)
{
	int	values_num;

	values_num = queue->head_pos - queue->tail_pos;
	if (0 > values_num)
		values_num += queue->alloc_num;

	return values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_ptr_reserve                                            *
 *                                                                            *
 * Purpose: reserves space in queue for additional values                     *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *             num   - [IN] the number of additional values to reserve        *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_reserve(zbx_queue_ptr_t *queue, int num)
{
	int	values_num, alloc_num, resize_num;

	values_num = zbx_queue_ptr_values_num(queue);

	if (values_num + num + 1 <= queue->alloc_num)
		return;

	alloc_num = MAX(queue->alloc_num + num + 1, queue->alloc_num * 1.5);
	queue->values = zbx_realloc(queue->values, alloc_num * sizeof(*queue->values));

	if (queue->tail_pos > queue->head_pos)
	{
		resize_num = alloc_num - queue->alloc_num;
		memmove(queue->values + queue->tail_pos + resize_num, queue->values + queue->tail_pos,
				(queue->alloc_num - queue->tail_pos) * sizeof(*queue->values));
		queue->tail_pos += resize_num;
	}

	queue->alloc_num = alloc_num;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_ptr_compact                                            *
 *                                                                            *
 * Purpose: compacts queue by freeing unused space                            *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_compact(zbx_queue_ptr_t *queue)
{
	int values_num, resize_num;

	values_num = zbx_queue_ptr_values_num(queue) + 1;

	resize_num = queue->alloc_num - values_num;
	queue->alloc_num = values_num;

	if (queue->tail_pos > queue->head_pos)
	{
		memmove(queue->values + queue->head_pos + 1, queue->values + queue->tail_pos,
				resize_num * sizeof(*queue->values));
		queue->tail_pos = queue->head_pos + 1;
	}

	queue->values = zbx_realloc(queue->values, queue->alloc_num * sizeof(*queue->values));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_ptr_create                                             *
 *                                                                            *
 * Purpose: creates queue                                                     *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_create(zbx_queue_ptr_t *queue)
{
	memset(queue, 0, sizeof(*queue));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_ptr_destroy                                            *
 *                                                                            *
 * Purpose: destroys queue                                                    *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_destroy(zbx_queue_ptr_t *queue)
{
	zbx_free(queue->values);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_ptr_push                                               *
 *                                                                            *
 * Purpose: pushes value in the queue                                         *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *             elem  - [IN] the value                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_push(zbx_queue_ptr_t *queue, void *elem)
{
	zbx_queue_ptr_reserve(queue, 1);
	queue->values[queue->head_pos++] = elem;

	if (queue->head_pos == queue->alloc_num)
		queue->head_pos = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_queue_ptr_pop                                                *
 *                                                                            *
 * Purpose: pops value in the queue                                           *
 *                                                                            *
 * Parameters: queue - [IN] the queue                                         *
 *                                                                            *
 * Return value: The first queue element.                                     *
 *                                                                            *
 ******************************************************************************/
void	*zbx_queue_ptr_pop(zbx_queue_ptr_t *queue)
{
	void	*elem;

	if (queue->tail_pos != queue->head_pos)
	{
		elem = queue->values[queue->tail_pos++];

		if (queue->tail_pos == queue->alloc_num)
			queue->tail_pos = 0;
	}
	else
		elem = NULL;

	return elem;
}


