/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "log.h"

#include "zbxalgo.h"

static void	swap(zbx_binary_heap_t *heap, int index_1, int index_2);

static void	__binary_heap_ensure_free_space(zbx_binary_heap_t *heap);

static int	__binary_heap_bubble_up(zbx_binary_heap_t *heap, int index);
static int	__binary_heap_bubble_down(zbx_binary_heap_t *heap, int index);

#define	ARRAY_GROWTH_FACTOR	3/2

#define	HAS_DIRECT_OPTION(heap)	(0 != (heap->options & ZBX_BINARY_HEAP_OPTION_DIRECT))

/* helper functions */

static void	swap(zbx_binary_heap_t *heap, int index_1, int index_2)
{
	zbx_binary_heap_elem_t	tmp;

	tmp = heap->elems[index_1];
	heap->elems[index_1] = heap->elems[index_2];
	heap->elems[index_2] = tmp;

	if (HAS_DIRECT_OPTION(heap))
	{
		zbx_hashmap_set(heap->key_index, heap->elems[index_1].key, index_1);
		zbx_hashmap_set(heap->key_index, heap->elems[index_2].key, index_2);
	}
}

/* private binary heap functions */

static void	__binary_heap_ensure_free_space(zbx_binary_heap_t *heap)
{
	if (NULL == heap->elems)
	{
		heap->elems_num = 0;
		heap->elems_alloc = 32;
		heap->elems = heap->mem_malloc_func(NULL, heap->elems_alloc * sizeof(zbx_binary_heap_elem_t));
	}
	else if (heap->elems_num == heap->elems_alloc)
	{
		heap->elems_alloc = MAX(heap->elems_alloc + 1, heap->elems_alloc * ARRAY_GROWTH_FACTOR);
		heap->elems = heap->mem_realloc_func(heap->elems, heap->elems_alloc * sizeof(zbx_binary_heap_elem_t));
	}
}

static int	__binary_heap_bubble_up(zbx_binary_heap_t *heap, int index)
{
	while (0 != index)
	{
		if (heap->compare_func(&heap->elems[(index - 1) / 2], &heap->elems[index]) <= 0)
			break;

		swap(heap, (index - 1) / 2, index);
		index = (index - 1) / 2;
	}

	return index;
}

static int	__binary_heap_bubble_down(zbx_binary_heap_t *heap, int index)
{
	while (1)
	{
		int left = 2 * index + 1;
		int right = 2 * index + 2;

		if (left >= heap->elems_num)
			break;

		if (right >= heap->elems_num)
		{
			if (heap->compare_func(&heap->elems[index], &heap->elems[left]) > 0)
			{
				swap(heap, index, left);
				index = left;
			}

			break;
		}

		if (heap->compare_func(&heap->elems[left], &heap->elems[right]) <= 0)
		{
			if (heap->compare_func(&heap->elems[index], &heap->elems[left]) > 0)
			{
				swap(heap, index, left);
				index = left;
			}
			else
				break;
		}
		else
		{
			if (heap->compare_func(&heap->elems[index], &heap->elems[right]) > 0)
			{
				swap(heap, index, right);
				index = right;
			}
			else
				break;
		}
	}

	return index;
}

/* public binary heap interface */

void	zbx_binary_heap_create(zbx_binary_heap_t *heap, zbx_compare_func_t compare_func, int options)
{
	zbx_binary_heap_create_ext(heap, compare_func, options,
					ZBX_DEFAULT_MEM_MALLOC_FUNC,
					ZBX_DEFAULT_MEM_REALLOC_FUNC,
					ZBX_DEFAULT_MEM_FREE_FUNC);
}

void	zbx_binary_heap_create_ext(zbx_binary_heap_t *heap, zbx_compare_func_t compare_func, int options,
					zbx_mem_malloc_func_t mem_malloc_func,
					zbx_mem_realloc_func_t mem_realloc_func,
					zbx_mem_free_func_t mem_free_func)
{
	heap->elems = NULL;
	heap->elems_num = 0;
	heap->elems_alloc = 0;
	heap->compare_func = compare_func;
	heap->options = options;

	if (HAS_DIRECT_OPTION(heap))
	{
		heap->key_index = mem_malloc_func(NULL, sizeof(zbx_hashmap_t));
		zbx_hashmap_create_ext(heap->key_index, 512,
					ZBX_DEFAULT_UINT64_HASH_FUNC,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC,
					mem_malloc_func,
					mem_realloc_func,
					mem_free_func);
	}
	else
		heap->key_index = NULL;

	heap->mem_malloc_func = mem_malloc_func;
	heap->mem_realloc_func = mem_realloc_func;
	heap->mem_free_func = mem_free_func;
}

void	zbx_binary_heap_destroy(zbx_binary_heap_t *heap)
{
	if (NULL != heap->elems)
	{
		heap->mem_free_func(heap->elems);
		heap->elems = NULL;
		heap->elems_num = 0;
		heap->elems_alloc = 0;
	}

	heap->compare_func = NULL;

	if (HAS_DIRECT_OPTION(heap))
	{
		zbx_hashmap_destroy(heap->key_index);
		heap->mem_free_func(heap->key_index);
		heap->key_index = NULL;
		heap->options = 0;
	}

	heap->mem_malloc_func = NULL;
	heap->mem_realloc_func = NULL;
	heap->mem_free_func = NULL;
}

int	zbx_binary_heap_empty(zbx_binary_heap_t *heap)
{
	return (0 == heap->elems_num ? SUCCEED : FAIL);
}

zbx_binary_heap_elem_t	*zbx_binary_heap_find_min(zbx_binary_heap_t *heap)
{
	if (0 == heap->elems_num)
	{
		zabbix_log(LOG_LEVEL_CRIT, "asking for a minimum in an empty heap");
		exit(EXIT_FAILURE);
	}

	return &heap->elems[0];
}

void	zbx_binary_heap_insert(zbx_binary_heap_t *heap, zbx_binary_heap_elem_t *elem)
{
	int	index;

	if (HAS_DIRECT_OPTION(heap) && FAIL != zbx_hashmap_get(heap->key_index, elem->key))
	{
		zabbix_log(LOG_LEVEL_CRIT, "inserting a duplicate key into a heap with direct option");
		exit(EXIT_FAILURE);
	}

	__binary_heap_ensure_free_space(heap);

	index = heap->elems_num++;
	heap->elems[index] = *elem;

	index = __binary_heap_bubble_up(heap, index);

	if (HAS_DIRECT_OPTION(heap) && index == heap->elems_num - 1)
		zbx_hashmap_set(heap->key_index, elem->key, index);
}

void	zbx_binary_heap_update_direct(zbx_binary_heap_t *heap, zbx_binary_heap_elem_t *elem)
{
	int	index;

	if (!HAS_DIRECT_OPTION(heap))
	{
		zabbix_log(LOG_LEVEL_CRIT, "direct update operation is not supported for this heap");
		exit(EXIT_FAILURE);
	}

	if (FAIL != (index = zbx_hashmap_get(heap->key_index, elem->key)))
	{
		heap->elems[index] = *elem;

		if (index == __binary_heap_bubble_up(heap, index))
			__binary_heap_bubble_down(heap, index);
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "element with key " ZBX_FS_UI64 " not found in heap for update", elem->key);
		exit(EXIT_FAILURE);
	}
}

void	zbx_binary_heap_remove_min(zbx_binary_heap_t *heap)
{
	int	index;

	if (0 == heap->elems_num)
	{
		zabbix_log(LOG_LEVEL_CRIT, "removing a minimum from an empty heap");
		exit(EXIT_FAILURE);
	}

	if (HAS_DIRECT_OPTION(heap))
		zbx_hashmap_remove(heap->key_index, heap->elems[0].key);

	if (0 != (--heap->elems_num))
	{
		heap->elems[0] = heap->elems[heap->elems_num];
		index = __binary_heap_bubble_down(heap, 0);

		if (HAS_DIRECT_OPTION(heap) && index == 0)
			zbx_hashmap_set(heap->key_index, heap->elems[index].key, index);
	}
}

void	zbx_binary_heap_remove_direct(zbx_binary_heap_t *heap, zbx_uint64_t key)
{
	int	index;

	if (!HAS_DIRECT_OPTION(heap))
	{
		zabbix_log(LOG_LEVEL_CRIT, "direct remove operation is not supported for this heap");
		exit(EXIT_FAILURE);
	}

	if (FAIL != (index = zbx_hashmap_get(heap->key_index, key)))
	{
		zbx_hashmap_remove(heap->key_index, key);

		if (index != (--heap->elems_num))
		{
			heap->elems[index] = heap->elems[heap->elems_num];

			if (index == __binary_heap_bubble_down(heap, index))
				zbx_hashmap_set(heap->key_index, heap->elems[index].key, index);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "element with key " ZBX_FS_UI64 " not found in heap for remove", key);
		exit(EXIT_FAILURE);
	}
}

void	zbx_binary_heap_clear(zbx_binary_heap_t *heap)
{
	if (NULL != heap->elems)
	{
		heap->mem_free_func(heap->elems);
		heap->elems = NULL;
		heap->elems_num = 0;
		heap->elems_alloc = 0;
	}

	if (HAS_DIRECT_OPTION(heap))
		zbx_hashmap_clear(heap->key_index);
}
