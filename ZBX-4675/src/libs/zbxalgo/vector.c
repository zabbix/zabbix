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

#define	ARRAY_GROWTH_FACTOR	3/2

#define	ZBX_VECTOR_IMPL(__id, __type)										\
														\
static void	__vector_ ## __id ## _ensure_free_space(zbx_vector_ ## __id ## _t *vector)			\
{														\
	if (NULL == vector->values)										\
	{													\
		vector->values_num = 0;										\
		vector->values_alloc = 32;									\
		vector->values = vector->mem_malloc_func(NULL, vector->values_alloc * sizeof(__type));		\
	}													\
	else if (vector->values_num == vector->values_alloc)							\
	{													\
		vector->values_alloc = MAX(vector->values_alloc + 1, vector->values_alloc * ARRAY_GROWTH_FACTOR); \
		vector->values = vector->mem_realloc_func(vector->values, vector->values_alloc * sizeof(__type)); \
	}													\
}														\
														\
void	zbx_vector_ ## __id ## _create(zbx_vector_ ## __id ## _t *vector)					\
{														\
	zbx_vector_ ## __id ## _create_ext(vector,								\
						ZBX_DEFAULT_MEM_MALLOC_FUNC,					\
						ZBX_DEFAULT_MEM_REALLOC_FUNC,					\
						ZBX_DEFAULT_MEM_FREE_FUNC);					\
}														\
														\
void	zbx_vector_ ## __id ## _create_ext(zbx_vector_ ## __id ## _t *vector,					\
						zbx_mem_malloc_func_t mem_malloc_func,				\
						zbx_mem_realloc_func_t mem_realloc_func,			\
						zbx_mem_free_func_t mem_free_func)				\
{														\
	vector->values = NULL;											\
	vector->values_num = 0;											\
	vector->values_alloc = 0;										\
														\
	vector->mem_malloc_func = mem_malloc_func;								\
	vector->mem_realloc_func = mem_realloc_func;								\
	vector->mem_free_func = mem_free_func;									\
}														\
														\
void	zbx_vector_ ## __id ## _destroy(zbx_vector_ ## __id ## _t *vector)					\
{														\
	if (NULL != vector->values)										\
	{													\
		vector->mem_free_func(vector->values);								\
		vector->values = NULL;										\
		vector->values_num = 0;										\
		vector->values_alloc = 0;									\
	}													\
														\
	vector->mem_malloc_func = NULL;										\
	vector->mem_realloc_func = NULL;									\
	vector->mem_free_func = NULL;										\
}														\
														\
void	zbx_vector_ ## __id ## _append(zbx_vector_ ## __id ## _t *vector, __type value)				\
{														\
	__vector_ ## __id ## _ensure_free_space(vector);							\
	vector->values[vector->values_num++] = value;								\
}														\
														\
void	zbx_vector_ ## __id ## _remove_noorder(zbx_vector_ ## __id ## _t *vector, int index)			\
{														\
	if (!(0 <= index && index < vector->values_num))							\
	{													\
		zabbix_log(LOG_LEVEL_CRIT, "removing a non-existent element at index %d", index);		\
		exit(FAIL);											\
	}													\
														\
	vector->values[index] = vector->values[--vector->values_num];						\
}														\
														\
void	zbx_vector_ ## __id ## _remove(zbx_vector_ ## __id ## _t *vector, int index)				\
{														\
	if (!(0 <= index && index < vector->values_num))							\
	{													\
		zabbix_log(LOG_LEVEL_CRIT, "removing a non-existent element at index %d", index);		\
		exit(FAIL);											\
	}													\
														\
	vector->values_num--;											\
	memmove(&vector->values[index], &vector->values[index + 1],						\
			sizeof(__type) * (vector->values_num - index));						\
}														\
														\
void	zbx_vector_ ## __id ## _sort(zbx_vector_ ## __id ## _t *vector, zbx_compare_func_t compare_func)	\
{														\
	if (2 <= vector->values_num)										\
	{													\
		qsort(vector->values, vector->values_num, sizeof(__type), compare_func);			\
	}													\
}														\
														\
void	zbx_vector_ ## __id ## _uniq(zbx_vector_ ## __id ## _t *vector, zbx_compare_func_t compare_func)	\
{														\
	if (2 <= vector->values_num)										\
	{													\
		int	i, j = 1;										\
														\
		for (i = 1; i < vector->values_num; i++)							\
		{												\
			if (0 != compare_func(&vector->values[i - 1], &vector->values[i]))			\
				vector->values[j++] = vector->values[i];					\
		}												\
														\
		vector->values_num = j;										\
	}													\
}														\
														\
int	zbx_vector_ ## __id ## _nearestindex(zbx_vector_ ## __id ## _t *vector, __type value,			\
									zbx_compare_func_t compare_func)	\
{														\
	int	lo = 0, hi = vector->values_num, mid, c;							\
														\
	while (1 <= hi - lo)											\
	{													\
		mid = (lo + hi) / 2;										\
														\
		c = compare_func(&vector->values[mid], &value);							\
														\
		if (0 > c)											\
		{												\
			lo = mid + 1;										\
		}												\
		else if (0 == c)										\
		{												\
			return mid;										\
		}												\
		else												\
			hi = mid;										\
	}													\
														\
	return hi;												\
}														\
														\
int	zbx_vector_ ## __id ## _bsearch(zbx_vector_ ## __id ## _t *vector, __type value,			\
									zbx_compare_func_t compare_func)	\
{														\
	__type	*ptr;												\
														\
	ptr = (__type *)bsearch(&value, vector->values, vector->values_num, sizeof(__type), compare_func);	\
														\
	if (NULL != ptr)											\
		return (int)(ptr - vector->values);								\
	else													\
		return FAIL;											\
}														\
														\
int	zbx_vector_ ## __id ## _lsearch(zbx_vector_ ## __id ## _t *vector, __type value, int *index,		\
									zbx_compare_func_t compare_func)	\
{														\
	while (*index < vector->values_num)									\
	{													\
		int	c = compare_func(&vector->values[*index], &value);					\
														\
		if (0 > c)											\
		{												\
			(*index)++;										\
			continue;										\
		}												\
														\
		if (0 == c)											\
			return SUCCEED;										\
														\
		if (0 < c)											\
			break;											\
	}													\
														\
	return FAIL;												\
}														\
														\
int	zbx_vector_ ## __id ## _search(zbx_vector_ ## __id ## _t *vector, __type value,				\
									zbx_compare_func_t compare_func)	\
{														\
	int	index;												\
														\
	for (index = 0; index < vector->values_num; index++)							\
	{													\
		if (0 == compare_func(&vector->values[index], &value))						\
			return index;										\
	}													\
														\
	return FAIL;												\
}														\
														\
void	zbx_vector_ ## __id ## _reserve(zbx_vector_ ## __id ## _t *vector, size_t size)				\
{														\
	if (size > vector->values_alloc)									\
	{													\
		vector->values_alloc = (int)size;								\
		vector->values = vector->mem_realloc_func(vector->values, vector->values_alloc * sizeof(__type)); \
	}													\
}														\
														\
void	zbx_vector_ ## __id ## _clear(zbx_vector_ ## __id ## _t *vector)					\
{														\
	if (NULL != vector->values)										\
	{													\
		vector->mem_free_func(vector->values);								\
		vector->values = NULL;										\
		vector->values_num = 0;										\
		vector->values_alloc = 0;									\
	}													\
}

ZBX_VECTOR_IMPL(uint64, zbx_uint64_t);
ZBX_VECTOR_IMPL(str, char *);
ZBX_VECTOR_IMPL(ptr, void *);
ZBX_VECTOR_IMPL(ptr_pair, zbx_ptr_pair_t);
