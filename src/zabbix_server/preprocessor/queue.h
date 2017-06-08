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

#ifndef ZABBIX_QUEUE_H
#define ZABBIX_QUEUE_H

#include "common.h"

/* queue item data */
typedef struct queue_item
{
	struct queue_item	*next;
	void			*data;
}
queue_item_t;

/* queue data */
typedef struct
{
	queue_item_t		*head;
	queue_item_t		*tail;
	unsigned int		size;
}
queue_t;

/* queue item data */
typedef struct
{
	queue_t			*queue;
	queue_item_t		*current;
	queue_item_t		*next;
}
queue_iterator_t;

void	zbx_queue_create(queue_t *queue, unsigned int size);
void	zbx_queue_destroy(queue_t *queue);
void	zbx_queue_enqueue(queue_t *queue, const void *value, queue_item_t **enqueued);
void	zbx_queue_enqueue_after(queue_t *queue, queue_item_t *after, const void *value, queue_item_t **enqueued);
void	zbx_queue_enqueue_first(queue_t *queue, const void *value, queue_item_t **enqueued);
int	zbx_queue_dequeue(queue_t *queue, void *value);
int	zbx_queue_peek(const queue_t *queue, void **value);
void	zbx_queue_iterator_init(queue_t *queue, queue_iterator_t *iterator);
int	zbx_queue_iterator_next(queue_iterator_t *iterator);
int	zbx_queue_iterator_peek(const queue_iterator_t *iterator, void **value);

#endif
