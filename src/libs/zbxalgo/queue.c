/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxalgo.h"

/******************************************************************************
 *                                                                            *
 * Purpose: calculates the number of values in queue                          *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
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
 * Purpose: reserves space in queue for additional values                     *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *             num   - [IN] number of additional values to reserve            *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_reserve(zbx_queue_ptr_t *queue, int num)
{
	int	values_num, alloc_num, resize_num;

	values_num = zbx_queue_ptr_values_num(queue);

	if (values_num + num + 1 <= queue->alloc_num)
		return;

	alloc_num = queue->alloc_num + MAX(num + 1, queue->alloc_num / 2);

	queue->values = (void **)zbx_realloc(queue->values, alloc_num * sizeof(*queue->values));

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
 * Purpose: compacts queue by freeing unused space                            *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_compact(zbx_queue_ptr_t *queue)
{
	int values_num, alloc_num;

	values_num = zbx_queue_ptr_values_num(queue);
	alloc_num = values_num + 1;

	if (alloc_num == queue->alloc_num)
		return;

	if (0 != queue->tail_pos)
	{
		if (queue->tail_pos > queue->head_pos)
		{
			memmove(queue->values + queue->head_pos + 1, queue->values + queue->tail_pos,
					(queue->alloc_num - queue->tail_pos) * sizeof(*queue->values));
			queue->tail_pos = queue->head_pos + 1;
		}
		else
		{
			memmove(queue->values, queue->values + queue->tail_pos, values_num * sizeof(*queue->values));
			queue->tail_pos = 0;
			queue->head_pos = values_num;
		}
	}

	queue->values = (void **)zbx_realloc(queue->values, alloc_num * sizeof(*queue->values));
	queue->alloc_num = alloc_num;
}

void	zbx_queue_ptr_create(zbx_queue_ptr_t *queue)
{
	memset(queue, 0, sizeof(*queue));
}

void	zbx_queue_ptr_destroy(zbx_queue_ptr_t *queue)
{
	zbx_free(queue->values);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pushes value in the queue                                         *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *             value - [IN] Data to push onto queue. Ownership goes to queue. *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_push(zbx_queue_ptr_t *queue, void *value)
{
	zbx_queue_ptr_reserve(queue, 1);
	queue->values[queue->head_pos++] = value;

	if (queue->head_pos == queue->alloc_num)
		queue->head_pos = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pops value in the queue                                           *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *                                                                            *
 * Return value: The first queue element.                                     *
 *                                                                            *
 ******************************************************************************/
void	*zbx_queue_ptr_pop(zbx_queue_ptr_t *queue)
{
	void	*value;

	if (queue->tail_pos != queue->head_pos)
	{
		value = queue->values[queue->tail_pos++];

		if (queue->tail_pos == queue->alloc_num)
			queue->tail_pos = 0;

		if (queue->head_pos == queue->alloc_num)
			queue->head_pos = 0;
	}
	else
		value = NULL;

	return value;
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes specified value from queue                                *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *             value - [IN] value to remove                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_queue_ptr_remove_value(zbx_queue_ptr_t *queue, const void *value)
{
	int	start_pos;

	if (queue->tail_pos == queue->head_pos)
		return;

	if (queue->tail_pos < queue->head_pos)
		start_pos = queue->tail_pos;
	else
		start_pos = 0;

	for (int i = start_pos; i < queue->head_pos; i++)
	{
		if (queue->values[i] == value)
		{
			for (; i < queue->head_pos - 1; i++)
				queue->values[i] = queue->values[i + 1];

			queue->head_pos--;
			return;
		}
	}

	if (queue->tail_pos <= queue->head_pos)
		return;

	for (int i = queue->alloc_num - 1; i >= queue->tail_pos; i--)
	{
		if (queue->values[i] == value)
		{
			for (; i > queue->tail_pos; i--)
				queue->values[i] = queue->values[i - 1];

			if (++queue->tail_pos == queue->alloc_num)
				queue->tail_pos = 0;

			return;
		}
	}
}
