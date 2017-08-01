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
#include "linked_list.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_create                                                  *
 *                                                                            *
 * Purpose: create singly linked list                                         *
 *                                                                            *
 * Parameters: list - [IN] the list                                           *
 *             size - [IN] size of an item value                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_create(zbx_list_t *queue, unsigned int size)
{
	memset(queue, 0, sizeof(*queue));
	queue->size = size;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_destroy                                                 *
 *                                                                            *
 * Purpose: destroy list                                                      *
 *                                                                            *
 * Parameters: list - [IN] the list                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_destroy(zbx_list_t *list)
{
	while (FAIL != zbx_list_pop(list, NULL))
		;
}

/******************************************************************************
 *                                                                            *
 * Function: list_create_item                                                 *
 *                                                                            *
 * Purpose: allocate memory and copy data for a new list item                 *
 *                                                                            *
 * Parameters: list     - [IN] the list                                       *
 *             value    - [IN] the data to be stored                          *
 *             created  - [OUT] pointer to the created list item              *
 *                                                                            *
 ******************************************************************************/
static void	list_create_item(zbx_list_t *list, const void *value, zbx_list_item_t **created)
{
	zbx_list_item_t *item;

	item = (zbx_list_item_t *)zbx_malloc(NULL, sizeof(zbx_list_item_t));
	item->next = NULL;
	item->data = zbx_malloc(NULL, list->size);
	memcpy(item->data, value, list->size);

	*created = item;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_insert_after                                            *
 *                                                                            *
 * Purpose: insert value after specified position in the list                 *
 *                                                                            *
 * Parameters: list     - [IN] the list                                       *
 *             after    - [IN] specified position (can be NULL to insert at   *
 *                             the end of the list)                           *
 *             value    - [IN] the value to be inserted                       *
 *             inserted - [OUT] pointer to the inserted list item             *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_insert_after(zbx_list_t *list, zbx_list_item_t *after, const void *value, zbx_list_item_t **inserted)
{
	zbx_list_item_t *item;

	list_create_item(list, value, &item);

	if (NULL == after)
		after = list->tail;

	if (NULL != after)
	{
		item->next = after->next;
		after->next = item;
	}
	else
		list->head = item;

	if (after == list->tail)
		list->tail = item;

	if (NULL != inserted)
		*inserted = item;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_append                                                  *
 *                                                                            *
 * Purpose: append value to the end of the list                               *
 *                                                                            *
 * Parameters: list     - [IN] the list                                       *
 *             value    - [IN] the value to append                            *
 *             inserted - [OUT] pointer to the inserted list item             *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_append(zbx_list_t *list, const void *value, zbx_list_item_t **inserted)
{
	return zbx_list_insert_after(list, NULL, value, inserted);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_prepend                                                 *
 *                                                                            *
 * Purpose: prepend value to the beginning of the list                        *
 *                                                                            *
 * Parameters: list     - [IN] the list                                       *
 *             value    - [IN] the value to prepend                           *
 *             inserted - [OUT] pointer to the inserted list item             *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_prepend(zbx_list_t *list, const void *value, zbx_list_item_t **inserted)
{
	zbx_list_item_t *item;

	list_create_item(list, value, &item);
	item->next = list->head;
	list->head = item;

	if (NULL == list->tail)
		list->tail = item;

	if (NULL != inserted)
		*inserted = item;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_pop                                                     *
 *                                                                            *
 * Purpose: removes a value from the beginning of the list                    *
 *                                                                            *
 * Parameters: list  - [IN]  the list                                         *
 *             value - [OUT] the value                                        *
 *                                                                            *
 * Return value: SUCCEED is returned if list is not empty, otherwise, FAIL is *
 *               returned.                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_list_pop(zbx_list_t *list, void *value)
{
	zbx_list_item_t	*head;

	if (NULL == list->head)
		return FAIL;

	if (NULL != value)
		memcpy(value, list->head->data, list->size);

	head = list->head;
	list->head = list->head->next;
	zbx_free(head->data);
	zbx_free(head);

	if (NULL == list->head)
		list->tail = NULL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_peek                                                    *
 *                                                                            *
 * Purpose: get value from the queue without dequeuing                        *
 *                                                                            *
 * Parameters: list - [IN]  the list                                          *
 *             value - [OUT] the value                                        *
 *                                                                            *
 * Return value: SUCCEED is returned if list is not empty, otherwise, FAIL is *
 *               returned.                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_list_peek(const zbx_list_t *list, void **value)
{
	if (NULL != list->head)
	{
		*value = list->head->data;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_iterator_init                                           *
 *                                                                            *
 * Purpose: initialize list iterator                                          *
 *                                                                            *
 * Parameters: list     - [IN]  the list                                      *
 *             iterator - [OUT] the iterator to be initialized                *
 *                                                                            *
 ******************************************************************************/
void zbx_list_iterator_init(zbx_list_t *list, zbx_list_iterator_t *iterator)
{
	iterator->list = list;
	iterator->next = list->head;
	iterator->current = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_list_iterator_next                                           *
 *                                                                            *
 * Purpose: advance list iterator                                             *
 *                                                                            *
 * Parameters: iterator - [IN] the iterator to be advanced                    *
 *                                                                            *
 * Return value: SUCCEED is returned if next list item exists, otherwise,     *
 *               FAIL is returned.                                            *
 *                                                                            *
 ******************************************************************************/
int zbx_list_iterator_next(zbx_list_iterator_t *iterator)
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
 * Function: zbx_list_iterator_peek                                           *
 *                                                                            *
 * Purpose: get value without removing it from list                           *
 *                                                                            *
 * Parameters: iterator - [IN]  initialized list iterator                     *
 *             value    - [OUT] the value                                     *
 *                                                                            *
 * Return value: SUCCEED is returned if item exists, otherwise, FAIL is       *
 *               returned.                                                    *
 *                                                                            *
 ******************************************************************************/
int zbx_list_iterator_peek(const zbx_list_iterator_t *iterator, void **value)
{
	if (NULL != iterator->current)
	{
		*value = iterator->current->data;
		return SUCCEED;
	}

	return FAIL;
}
