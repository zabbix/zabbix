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

#include "zbxalgo.h"

#include "common.h"

/******************************************************************************
 *                                                                            *
 * Purpose: create singly linked list (with custom memory functions)          *
 *                                                                            *
 * Parameters: queue           - [IN] the list                                *
 *             mem_malloc_func - [IN] callback for malloc                     *
 *             mem_free_func   - [IN] callback for free                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_create_ext(zbx_list_t *queue, zbx_mem_malloc_func_t mem_malloc_func, zbx_mem_free_func_t mem_free_func)
{
	memset(queue, 0, sizeof(*queue));

	queue->mem_malloc_func = mem_malloc_func;
	queue->mem_free_func = mem_free_func;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create singly linked list                                         *
 *                                                                            *
 * Parameters: queue - [IN] the list                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_create(zbx_list_t *queue)
{
	zbx_list_create_ext(queue, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
}

/******************************************************************************
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
 * Purpose: allocate memory and initialize a new list item                    *
 *                                                                            *
 * Parameters: list     - [IN] the list                                       *
 *             value    - [IN] the data to be stored                          *
 *             created  - [OUT] pointer to the created list item              *
 *                                                                            *
 ******************************************************************************/
static void	list_create_item(zbx_list_t *list, void *value, zbx_list_item_t **created)
{
	zbx_list_item_t *item;

	ZBX_UNUSED(list);

	item = (zbx_list_item_t *)list->mem_malloc_func(NULL, sizeof(zbx_list_item_t));
	item->next = NULL;
	item->data = value;

	*created = item;
}

/******************************************************************************
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
void	zbx_list_insert_after(zbx_list_t *list, zbx_list_item_t *after, void *value, zbx_list_item_t **inserted)
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
	{
		list->head = item;
	}

	if (after == list->tail)
		list->tail = item;

	if (NULL != inserted)
		*inserted = item;
}

/******************************************************************************
 *                                                                            *
 * Purpose: append value to the end of the list                               *
 *                                                                            *
 * Parameters: list     - [IN] the list                                       *
 *             value    - [IN] the value to append                            *
 *             inserted - [OUT] pointer to the inserted list item             *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_append(zbx_list_t *list, void *value, zbx_list_item_t **inserted)
{
	zbx_list_insert_after(list, NULL, value, inserted);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepend value to the beginning of the list                        *
 *                                                                            *
 * Parameters: list     - [IN] the list                                       *
 *             value    - [IN] the value to prepend                           *
 *             inserted - [OUT] pointer to the inserted list item             *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_prepend(zbx_list_t *list, void *value, zbx_list_item_t **inserted)
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
 * Purpose: removes a value from the beginning of the list                    *
 *                                                                            *
 * Parameters: list  - [IN]  the list                                         *
 *             value - [OUT] the value                                        *
 *                                                                            *
 * Return value: SUCCEED is returned if list is not empty, otherwise, FAIL is *
 *               returned.                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_list_pop(zbx_list_t *list, void **value)
{
	zbx_list_item_t	*head;

	if (NULL == list->head)
		return FAIL;

	head = list->head;

	if (NULL != value)
		*value = head->data;

	list->head = list->head->next;
	list->mem_free_func(head);

	if (NULL == list->head)
		list->tail = NULL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get value from the queue without dequeuing                        *
 *                                                                            *
 * Parameters: list  - [IN]  the list                                         *
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
 * Purpose: initialize list iterator                                          *
 *                                                                            *
 * Parameters: list     - [IN]  the list                                      *
 *             iterator - [OUT] the iterator to be initialized                *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_iterator_init(zbx_list_t *list, zbx_list_iterator_t *iterator)
{
	iterator->list = list;
	iterator->next = list->head;
	iterator->current = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: advance list iterator                                             *
 *                                                                            *
 * Parameters: iterator - [IN] the iterator to be advanced                    *
 *                                                                            *
 * Return value: SUCCEED is returned if next list item exists, otherwise,     *
 *               FAIL is returned.                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_list_iterator_next(zbx_list_iterator_t *iterator)
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
 * Purpose: get value without removing it from list                           *
 *                                                                            *
 * Parameters: iterator - [IN]  initialized list iterator                     *
 *             value    - [OUT] the value                                     *
 *                                                                            *
 * Return value: SUCCEED is returned if item exists, otherwise, FAIL is       *
 *               returned.                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_list_iterator_peek(const zbx_list_iterator_t *iterator, void **value)
{
	if (NULL != iterator->current)
	{
		*value = iterator->current->data;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clears iterator leaving it in uninitialized state                 *
 *                                                                            *
 * Parameters: iterator - [IN]  list iterator                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_iterator_clear(zbx_list_iterator_t *iterator)
{
	memset(iterator, 0, sizeof(zbx_list_iterator_t));
}

/******************************************************************************
 *                                                                            *
 * Purpose: tests if two iterators points at the same list item               *
 *                                                                            *
 * Parameters: iterator1 - [IN] first list iterator                           *
 *             iterator2 - [IN] second list iterator                          *
 *                                                                            *
 * Return value: SUCCEED is returned if both iterator point at the same item, *
 *               FAIL otherwise.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_list_iterator_equal(const zbx_list_iterator_t *iterator1, const zbx_list_iterator_t *iterator2)
{
	if (iterator1->list == iterator2->list && iterator1->current == iterator2->current)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the iterator points at some list item                   *
 *                                                                            *
 * Parameters: iterator - [IN] list iterator                                  *
 *                                                                            *
 * Return value: SUCCEED is returned if iterator is set, FAIL otherwise.      *
 *                                                                            *
 ******************************************************************************/
int	zbx_list_iterator_isset(const zbx_list_iterator_t *iterator)
{
	return (NULL == iterator->list ? FAIL : SUCCEED);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates iterator                                                  *
 *                                                                            *
 * Parameters: iterator - [IN] list iterator                                  *
 *                                                                            *
 * Comments: This function must be used after an item has been inserted in    *
 *           list during iteration process.                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_list_iterator_update(zbx_list_iterator_t *iterator)
{
	if (NULL != iterator->current)
		iterator->next = iterator->current->next;
}


/******************************************************************************
 *                                                                            *
 * Purpose: removes next iterator value from list                             *
 *                                                                            *
 * Parameters: iterator - [IN] list iterator                                  *
 *                                                                            *
 * Return value: The data held by the removed item.                           *
 *                                                                            *
 ******************************************************************************/
void	*zbx_list_iterator_remove_next(zbx_list_iterator_t *iterator)
{
	zbx_list_item_t	*next;
	void		*data;

	if (NULL == iterator->current || NULL == iterator->next)
		return NULL;

	next = iterator->next;
	data = next->data;

	if (iterator->list->tail == next)
		iterator->list->tail = iterator->current;

	iterator->current->next = next->next;
	iterator->next = next->next;
	iterator->list->mem_free_func(next);

	return data;
}
