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

#ifndef ZABBIX_ZBXALGO_H
#define ZABBIX_ZBXALGO_H

#include "zbxnum.h"

/* generic */

typedef zbx_uint32_t zbx_hash_t;

zbx_hash_t	zbx_hash_modfnv(const void *data, size_t len, zbx_hash_t seed);
zbx_hash_t	zbx_hash_splittable64(const void *data);

#define ZBX_DEFAULT_HASH_ALGO		zbx_hash_modfnv
#define ZBX_DEFAULT_PTR_HASH_ALGO	zbx_hash_modfnv
#define ZBX_DEFAULT_UINT64_HASH_ALGO	zbx_hash_modfnv
#define ZBX_DEFAULT_STRING_HASH_ALGO	zbx_hash_modfnv

typedef zbx_hash_t (*zbx_hash_func_t)(const void *data);

zbx_hash_t	zbx_default_ptr_hash_func(const void *data);
zbx_hash_t	zbx_default_string_hash_func(const void *data);
zbx_hash_t	zbx_default_string_ptr_hash_func(const void *data);
zbx_hash_t	zbx_default_uint64_pair_hash_func(const void *data);

#define ZBX_DEFAULT_HASH_SEED		0

#define ZBX_DEFAULT_PTR_HASH_FUNC		zbx_default_ptr_hash_func
#define ZBX_DEFAULT_UINT64_HASH_FUNC		zbx_hash_splittable64
#define ZBX_DEFAULT_STRING_HASH_FUNC		zbx_default_string_hash_func
#define ZBX_DEFAULT_STRING_PTR_HASH_FUNC	zbx_default_string_ptr_hash_func
#define ZBX_DEFAULT_UINT64_PAIR_HASH_FUNC	zbx_default_uint64_pair_hash_func

typedef enum
{
	ZBX_HASHSET_UNIQ_FALSE,
	ZBX_HASHSET_UNIQ_TRUE
}
zbx_hashset_uniq_t;

typedef int (*zbx_compare_func_t)(const void *d1, const void *d2);

int	zbx_default_int_compare_func(const void *d1, const void *d2);
int	zbx_default_uint64_compare_func(const void *d1, const void *d2);
int	zbx_default_uint64_ptr_compare_func(const void *d1, const void *d2);
int	zbx_default_str_compare_func(const void *d1, const void *d2);
int	zbx_default_str_ptr_compare_func(const void *d1, const void *d2);
int	zbx_natural_str_compare_func(const void *d1, const void *d2);
int	zbx_default_ptr_compare_func(const void *d1, const void *d2);
int	zbx_default_uint64_pair_compare_func(const void *d1, const void *d2);
int	zbx_default_dbl_compare_func(const void *d1, const void *d2);

#define ZBX_DEFAULT_INT_COMPARE_FUNC		zbx_default_int_compare_func
#define ZBX_DEFAULT_UINT64_COMPARE_FUNC		zbx_default_uint64_compare_func
#define ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC	zbx_default_uint64_ptr_compare_func
#define ZBX_DEFAULT_STR_COMPARE_FUNC		zbx_default_str_compare_func
#define ZBX_DEFAULT_STR_PTR_COMPARE_FUNC	zbx_default_str_ptr_compare_func
#define ZBX_DEFAULT_PTR_COMPARE_FUNC		zbx_default_ptr_compare_func
#define ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC	zbx_default_uint64_pair_compare_func
#define ZBX_DEFAULT_DBL_COMPARE_FUNC		zbx_default_dbl_compare_func

typedef void *(*zbx_mem_malloc_func_t)(void *old, size_t size);
typedef void *(*zbx_mem_realloc_func_t)(void *old, size_t size);
typedef void (*zbx_mem_free_func_t)(void *ptr);

void	*zbx_default_mem_malloc_func(void *old, size_t size);
void	*zbx_default_mem_realloc_func(void *old, size_t size);
void	zbx_default_mem_free_func(void *ptr);

#define ZBX_DEFAULT_MEM_MALLOC_FUNC	zbx_default_mem_malloc_func
#define ZBX_DEFAULT_MEM_REALLOC_FUNC	zbx_default_mem_realloc_func
#define ZBX_DEFAULT_MEM_FREE_FUNC	zbx_default_mem_free_func

typedef void (*zbx_clean_func_t)(void *data);

#define ZBX_RETURN_IF_NOT_EQUAL(a, b)	\
					\
	if ((a) < (b))			\
		return -1;		\
	if ((a) > (b))			\
		return +1

#define ZBX_RETURN_IF_DBL_NOT_EQUAL(a, b)		\
	do						\
	{						\
		if (FAIL == zbx_double_compare(a, b))	\
		{					\
			if ((a) < (b))			\
				return -1;		\
			else				\
				return +1;		\
		}					\
	}						\
	while(0)

/* pair */

typedef struct
{
	void	*first;
	void	*second;
}
zbx_ptr_pair_t;

typedef struct
{
	zbx_uint64_t	first;
	zbx_uint64_t	second;
}
zbx_uint64_pair_t;

/* hashset */

#define ZBX_HASHSET_ENTRY_T	struct zbx_hashset_entry_s

ZBX_HASHSET_ENTRY_T
{
	ZBX_HASHSET_ENTRY_T	*next;
	zbx_hash_t		hash;
#if SIZEOF_VOID_P > 4
	/* the data member must be properly aligned on 64-bit architectures that require aligned memory access */
	char			padding[sizeof(void *) - sizeof(zbx_hash_t)];
#endif
	char			data[1];
};

typedef struct
{
	ZBX_HASHSET_ENTRY_T	**slots;
	int			num_slots;
	int			num_data;
	zbx_hash_func_t		hash_func;
	zbx_compare_func_t	compare_func;
	zbx_clean_func_t	clean_func;
	zbx_mem_malloc_func_t	mem_malloc_func;
	zbx_mem_realloc_func_t	mem_realloc_func;
	zbx_mem_free_func_t	mem_free_func;
}
zbx_hashset_t;

#define ZBX_HASHSET_ENTRY_OFFSET	offsetof(ZBX_HASHSET_ENTRY_T, data)

void	zbx_hashset_create(zbx_hashset_t *hs, size_t init_size,
				zbx_hash_func_t hash_func,
				zbx_compare_func_t compare_func);
void	zbx_hashset_create_ext(zbx_hashset_t *hs, size_t init_size,
				zbx_hash_func_t hash_func,
				zbx_compare_func_t compare_func,
				zbx_clean_func_t clean_func,
				zbx_mem_malloc_func_t mem_malloc_func,
				zbx_mem_realloc_func_t mem_realloc_func,
				zbx_mem_free_func_t mem_free_func);
void	zbx_hashset_destroy(zbx_hashset_t *hs);

int	zbx_hashset_reserve(zbx_hashset_t *hs, int num_slots_req);
void	*zbx_hashset_insert(zbx_hashset_t *hs, const void *data, size_t size);
void	*zbx_hashset_insert_ext(zbx_hashset_t *hs, const void *data, size_t size, size_t offset, size_t n,
		zbx_hashset_uniq_t uniq);
void	*zbx_hashset_search(const zbx_hashset_t *hs, const void *data);
void	zbx_hashset_remove(zbx_hashset_t *hs, const void *data);
void	zbx_hashset_remove_direct(zbx_hashset_t *hs, void *data);

void	zbx_hashset_clear(zbx_hashset_t *hs);

typedef struct
{
	zbx_hashset_t		*hashset;
	int			slot;
	ZBX_HASHSET_ENTRY_T	*entry;
}
zbx_hashset_iter_t;

void	zbx_hashset_iter_reset(zbx_hashset_t *hs, zbx_hashset_iter_t *iter);
void	*zbx_hashset_iter_next(zbx_hashset_iter_t *iter);
void	zbx_hashset_iter_remove(zbx_hashset_iter_t *iter);
void	zbx_hashset_copy(zbx_hashset_t *dst, const zbx_hashset_t *src, size_t size);

/* hashmap */

/* currently, we only have a very specialized hashmap */
/* that maps zbx_uint64_t keys into non-negative ints */

#define ZBX_HASHMAP_ENTRY_T	struct zbx_hashmap_entry_s
#define ZBX_HASHMAP_SLOT_T	struct zbx_hashmap_slot_s

ZBX_HASHMAP_ENTRY_T
{
	zbx_uint64_t	key;
	int		value;
};

ZBX_HASHMAP_SLOT_T
{
	ZBX_HASHMAP_ENTRY_T	*entries;
	int			entries_num;
	int			entries_alloc;
};

typedef struct
{
	ZBX_HASHMAP_SLOT_T	*slots;
	int			num_slots;
	int			num_data;
	zbx_hash_func_t		hash_func;
	zbx_compare_func_t	compare_func;
	zbx_mem_malloc_func_t	mem_malloc_func;
	zbx_mem_realloc_func_t	mem_realloc_func;
	zbx_mem_free_func_t	mem_free_func;
}
zbx_hashmap_t;

void	zbx_hashmap_create(zbx_hashmap_t *hm, size_t init_size);
void	zbx_hashmap_create_ext(zbx_hashmap_t *hm, size_t init_size,
				zbx_hash_func_t hash_func,
				zbx_compare_func_t compare_func,
				zbx_mem_malloc_func_t mem_malloc_func,
				zbx_mem_realloc_func_t mem_realloc_func,
				zbx_mem_free_func_t mem_free_func);
void	zbx_hashmap_destroy(zbx_hashmap_t *hm);

int	zbx_hashmap_get(zbx_hashmap_t *hm, zbx_uint64_t key);
void	zbx_hashmap_set(zbx_hashmap_t *hm, zbx_uint64_t key, int value);
void	zbx_hashmap_remove(zbx_hashmap_t *hm, zbx_uint64_t key);

void	zbx_hashmap_clear(zbx_hashmap_t *hm);

/* binary heap (min-heap) */

/* currently, we only have a very specialized binary heap that can */
/* store zbx_uint64_t keys with arbitrary auxiliary information */

#define ZBX_BINARY_HEAP_OPTION_EMPTY	0
#define ZBX_BINARY_HEAP_OPTION_DIRECT	(1<<0)	/* support for direct update() and remove() operations */

typedef struct
{
	zbx_uint64_t	key;
	void		*data;
}
zbx_binary_heap_elem_t;

typedef struct
{
	zbx_binary_heap_elem_t	*elems;
	int			elems_num;
	int			elems_alloc;
	int			options;
	zbx_compare_func_t	compare_func;
	zbx_hashmap_t		*key_index;

	/* The binary heap is designed to work correctly only with memory allocation functions */
	/* that return pointer to the allocated memory or quit. Functions that can return NULL */
	/* are not supported (process will exit() if NULL return value is encountered). If     */
	/* using zbx_shmem_info_t and the associated memory functions then ensure that         */
	/* allow_oom is always set to 0.                                                       */
	zbx_mem_malloc_func_t	mem_malloc_func;
	zbx_mem_realloc_func_t	mem_realloc_func;
	zbx_mem_free_func_t	mem_free_func;
}
zbx_binary_heap_t;

void			zbx_binary_heap_create(zbx_binary_heap_t *heap, zbx_compare_func_t compare_func, int options);
void			zbx_binary_heap_create_ext(zbx_binary_heap_t *heap, zbx_compare_func_t compare_func,
							int options, zbx_mem_malloc_func_t mem_malloc_func,
							zbx_mem_realloc_func_t mem_realloc_func,
							zbx_mem_free_func_t mem_free_func);
void			zbx_binary_heap_destroy(zbx_binary_heap_t *heap);

int			zbx_binary_heap_empty(const zbx_binary_heap_t *heap);
zbx_binary_heap_elem_t	*zbx_binary_heap_find_min(const zbx_binary_heap_t *heap);
void			zbx_binary_heap_insert(zbx_binary_heap_t *heap, zbx_binary_heap_elem_t *elem);
void			zbx_binary_heap_update_direct(zbx_binary_heap_t *heap, zbx_binary_heap_elem_t *elem);
void			zbx_binary_heap_remove_min(zbx_binary_heap_t *heap);
void			zbx_binary_heap_remove_direct(zbx_binary_heap_t *heap, zbx_uint64_t key);

void			zbx_binary_heap_clear(zbx_binary_heap_t *heap);

/* vector implementation start */

#define ZBX_VECTOR_STRUCT_DECL(__id, __type)									\
														\
typedef struct													\
{														\
	__type			*values;									\
	int			values_num;									\
	int			values_alloc;									\
	zbx_mem_malloc_func_t	mem_malloc_func;								\
	zbx_mem_realloc_func_t	mem_realloc_func;								\
	zbx_mem_free_func_t	mem_free_func;									\
}														\
zbx_vector_ ## __id ## _t;

#define ZBX_VECTOR_FUNC_DECL(__id, __type, __const)								\
														\
void	zbx_vector_ ## __id ## _create(zbx_vector_ ## __id ## _t *vector);					\
void	zbx_vector_ ## __id ## _create_ext(zbx_vector_ ## __id ## _t *vector,					\
						zbx_mem_malloc_func_t mem_malloc_func,				\
						zbx_mem_realloc_func_t mem_realloc_func,			\
						zbx_mem_free_func_t mem_free_func);				\
void	zbx_vector_ ## __id ## _destroy(zbx_vector_ ## __id ## _t *vector);					\
														\
void	zbx_vector_ ## __id ## _append(zbx_vector_ ## __id ## _t *vector, __type value);			\
void	zbx_vector_ ## __id ## _insert(zbx_vector_ ## __id ## _t *vector, __type value, int before_index);	\
void	zbx_vector_ ## __id ## _append_ptr(zbx_vector_ ## __id ## _t *vector, __type *value);			\
void	zbx_vector_ ## __id ## _append_array(zbx_vector_ ## __id ## _t *vector, __type const *values,		\
									int values_num);			\
void	zbx_vector_ ## __id ## _remove_noorder(zbx_vector_ ## __id ## _t *vector, int index);			\
void	zbx_vector_ ## __id ## _remove(zbx_vector_ ## __id ## _t *vector, int index);				\
														\
void	zbx_vector_ ## __id ## _sort(zbx_vector_ ## __id ## _t *vector, zbx_compare_func_t compare_func);	\
void	zbx_vector_ ## __id ## _uniq(zbx_vector_ ## __id ## _t *vector, zbx_compare_func_t compare_func);	\
														\
int	zbx_vector_ ## __id ## _nearestindex(const zbx_vector_ ## __id ## _t *vector, __const __type value,	\
									zbx_compare_func_t compare_func);	\
int	zbx_vector_ ## __id ## _bsearch(const zbx_vector_ ## __id ## _t *vector, __const __type value,		\
									zbx_compare_func_t compare_func);	\
int	zbx_vector_ ## __id ## _lsearch(const zbx_vector_ ## __id ## _t *vector, __const __type value, int *index,\
									zbx_compare_func_t compare_func);	\
int	zbx_vector_ ## __id ## _search(const zbx_vector_ ## __id ## _t *vector, __const __type value,		\
									zbx_compare_func_t compare_func);	\
void	zbx_vector_ ## __id ## _setdiff(zbx_vector_ ## __id ## _t *left, const zbx_vector_ ## __id ## _t *right,\
									zbx_compare_func_t compare_func);	\
														\
void	zbx_vector_ ## __id ## _reserve(zbx_vector_ ## __id ## _t *vector, size_t size);			\
void	zbx_vector_ ## __id ## _clear(zbx_vector_ ## __id ## _t *vector);

#define ZBX_VECTOR_DECL(__id, __type)	ZBX_VECTOR_STRUCT_DECL(__id, __type)					\
					ZBX_VECTOR_FUNC_DECL(__id, __type, const)

#define ZBX_PTR_VECTOR_FUNC_DECL(__id, __type, __const)									\
														\
ZBX_VECTOR_FUNC_DECL(__id, __type, __const)										\
														\
typedef void (*zbx_ ## __id ## _free_func_t)(__type data);							\
														\
void	zbx_vector_ ## __id ## _clear_ext(zbx_vector_ ## __id ## _t *vector, zbx_ ## __id ## _free_func_t free_func);

#define ZBX_PTR_VECTOR_DECL(__id, __type)	ZBX_VECTOR_STRUCT_DECL(__id, __type)				\
						ZBX_PTR_VECTOR_FUNC_DECL(__id, __type, const)

#define ZBX_CONST_PTR_VECTOR_DECL(__id, __type)	ZBX_VECTOR_STRUCT_DECL(__id, __type)				\
						ZBX_PTR_VECTOR_FUNC_DECL(__id, __type, )

ZBX_VECTOR_DECL(uint64, zbx_uint64_t)
ZBX_VECTOR_DECL(uint32, zbx_uint32_t)
ZBX_VECTOR_DECL(int32, int)
ZBX_PTR_VECTOR_DECL(str, char *)
ZBX_PTR_VECTOR_DECL(ptr, void *)
ZBX_VECTOR_DECL(ptr_pair, zbx_ptr_pair_t)
ZBX_VECTOR_DECL(uint64_pair, zbx_uint64_pair_t)
ZBX_VECTOR_DECL(dbl, double)

ZBX_PTR_VECTOR_DECL(tags_ptr, zbx_tag_t*)

#define	ZBX_VECTOR_ARRAY_GROWTH_FACTOR	3/2

#define	ZBX_VECTOR_FUNC_IMPL(__id, __type, __const)										\
														\
static void	__vector_ ## __id ## _ensure_free_space(zbx_vector_ ## __id ## _t *vector)			\
{														\
	if (NULL == vector->values)										\
	{													\
		vector->values_num = 0;										\
		vector->values_alloc = 32;									\
		vector->values = (__type *)vector->mem_malloc_func(NULL, (size_t)vector->values_alloc *		\
				sizeof(__type));								\
	}													\
	else if (vector->values_num == vector->values_alloc)							\
	{													\
		vector->values_alloc = MAX(vector->values_alloc + 1, vector->values_alloc *			\
				ZBX_VECTOR_ARRAY_GROWTH_FACTOR);						\
		vector->values = (__type *)vector->mem_realloc_func(vector->values,				\
				(size_t)vector->values_alloc * sizeof(__type));					\
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
void	zbx_vector_ ## __id ## _insert(zbx_vector_ ## __id ## _t *vector, __type value, int before_index)	\
{														\
	__vector_ ## __id ## _ensure_free_space(vector);							\
														\
	if (0 < vector->values_num - before_index)								\
	{													\
		memmove(vector->values + before_index + 1, vector->values + before_index,			\
				(size_t)(vector->values_num - before_index) * sizeof(__type));			\
	}													\
														\
	vector->values_num++;											\
	vector->values[before_index] = value;									\
}														\
														\
void	zbx_vector_ ## __id ## _append_ptr(zbx_vector_ ## __id ## _t *vector, __type *value)			\
{														\
	__vector_ ## __id ## _ensure_free_space(vector);							\
	vector->values[vector->values_num++] = *value;								\
}														\
														\
void	zbx_vector_ ## __id ## _append_array(zbx_vector_ ## __id ## _t *vector, __type const *values,		\
									int values_num)				\
{														\
	if (0 == values_num)											\
		return;												\
														\
	zbx_vector_ ## __id ## _reserve(vector, (size_t)(vector->values_num + values_num));			\
	memcpy(vector->values + vector->values_num, values, (size_t)values_num * sizeof(__type));		\
	vector->values_num = vector->values_num + values_num;							\
}														\
														\
void	zbx_vector_ ## __id ## _remove_noorder(zbx_vector_ ## __id ## _t *vector, int index)			\
{														\
	if (!(0 <= index && index < vector->values_num))							\
	{													\
		zabbix_log(LOG_LEVEL_CRIT, "removing a non-existent element at index %d", index);		\
		exit(EXIT_FAILURE);										\
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
		exit(EXIT_FAILURE);										\
	}													\
														\
	vector->values_num--;											\
	memmove(&vector->values[index], &vector->values[index + 1],						\
			sizeof(__type) * (size_t)(vector->values_num - index));					\
}														\
														\
void	zbx_vector_ ## __id ## _sort(zbx_vector_ ## __id ## _t *vector, zbx_compare_func_t compare_func)	\
{														\
	if (2 <= vector->values_num)										\
		qsort(vector->values, (size_t)vector->values_num, sizeof(__type), compare_func);		\
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
int	zbx_vector_ ## __id ## _nearestindex(const zbx_vector_ ## __id ## _t *vector, __const __type value,	\
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
int	zbx_vector_ ## __id ## _bsearch(const zbx_vector_ ## __id ## _t *vector, __const __type value,		\
									zbx_compare_func_t compare_func)	\
{														\
	__type	*ptr;												\
														\
	ptr = (__type *)zbx_bsearch(&value, vector->values, (size_t)vector->values_num, sizeof(__type),		\
			compare_func);										\
														\
	if (NULL != ptr)											\
		return (int)(ptr - vector->values);								\
	else													\
		return FAIL;											\
}														\
														\
int	zbx_vector_ ## __id ## _lsearch(const zbx_vector_ ## __id ## _t *vector, __const __type value, int *index,\
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
int	zbx_vector_ ## __id ## _search(const zbx_vector_ ## __id ## _t *vector, __const __type value,		\
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
														\
void	zbx_vector_ ## __id ## _setdiff(zbx_vector_ ## __id ## _t *left, const zbx_vector_ ## __id ## _t *right,\
									zbx_compare_func_t compare_func)	\
{														\
	int	c, block_start, deleted = 0, left_index = 0, right_index = 0;					\
														\
	while (left_index < left->values_num && right_index < right->values_num)				\
	{													\
		c = compare_func(&left->values[left_index], &right->values[right_index]);			\
														\
		if (0 >= c)											\
			left_index++;										\
														\
		if (0 <= c)											\
			right_index++;										\
														\
		if (0 != c)											\
			continue;										\
														\
		if (0 < deleted++)										\
		{												\
			memmove(&left->values[block_start - deleted + 1], &left->values[block_start],		\
					(size_t)(left_index - 1 - block_start) * sizeof(__type));		\
		}												\
														\
		block_start = left_index;									\
	}													\
														\
	if (0 < deleted)											\
	{													\
		memmove(&left->values[block_start - deleted], &left->values[block_start],			\
				(size_t)(left->values_num - block_start) * sizeof(__type));			\
		left->values_num -= deleted;									\
	}													\
}														\
														\
void	zbx_vector_ ## __id ## _reserve(zbx_vector_ ## __id ## _t *vector, size_t size)				\
{														\
	if ((int)size > vector->values_alloc)									\
	{													\
		vector->values_alloc = (int)size;								\
		vector->values = (__type *)vector->mem_realloc_func(vector->values,				\
				(size_t)vector->values_alloc * sizeof(__type));					\
	}													\
}														\
														\
void	zbx_vector_ ## __id ## _clear(zbx_vector_ ## __id ## _t *vector)					\
{														\
	vector->values_num = 0;											\
}

#define	ZBX_VECTOR_IMPL(__id, __type)	ZBX_VECTOR_FUNC_IMPL(__id, __type, const)

#define	ZBX_PTR_VECTOR_IMPL(__id, __type)									\
														\
ZBX_VECTOR_FUNC_IMPL(__id, __type, const)									\
														\
void	zbx_vector_ ## __id ## _clear_ext(zbx_vector_ ## __id ## _t *vector,					\
		zbx_ ## __id ## _free_func_t free_func)								\
{														\
	if (0 != vector->values_num)										\
	{													\
		int	index;											\
														\
		for (index = 0; index < vector->values_num; index++)						\
			free_func(vector->values[index]);							\
														\
		vector->values_num = 0;										\
	}													\
}

#define	ZBX_CONST_PTR_VECTOR_IMPL(__id, __type)									\
														\
ZBX_VECTOR_FUNC_IMPL(__id, __type, )										\
														\
void	zbx_vector_ ## __id ## _clear_ext(zbx_vector_ ## __id ## _t *vector,					\
		zbx_ ## __id ## _free_func_t free_func)								\
{														\
	ZBX_UNUSED(vector);											\
	ZBX_UNUSED(free_func);											\
	THIS_SHOULD_NEVER_HAPPEN_MSG("constant pointer vector contents must not be freed");			\
}
/* vector implementation end */

/* these functions are only for use with zbx_vector_XXX_clear_ext() */
/* and only if the vector does not contain nested allocations */
void	zbx_ptr_free(void *data);
void	zbx_str_free(char *data);
void	zbx_free_tag(zbx_tag_t *tag);

/* these functions are only for use with zbx_vector_XXX_sort() */
int	zbx_compare_tags(const void *d1, const void *d2);
int	zbx_compare_tags_and_values(const void *d1, const void *d2);
int	zbx_compare_tags_natural(const void *d1, const void *d2);

/* 128 bit unsigned integer handling */
void	zbx_uinc128_64(zbx_uint128_t *base, zbx_uint64_t value);
void	zbx_uinc128_128(zbx_uint128_t *base, const zbx_uint128_t *value);
void	zbx_udiv128_64(zbx_uint128_t *result, const zbx_uint128_t *dividend, zbx_uint64_t value);
void	zbx_umul64_64(zbx_uint128_t *result, zbx_uint64_t value, zbx_uint64_t factor);

unsigned int	zbx_isqrt32(unsigned int value);

/* forecasting */

#define ZBX_MATH_ERROR	-1.0

typedef enum
{
	FIT_LINEAR,
	FIT_POLYNOMIAL,
	FIT_EXPONENTIAL,
	FIT_LOGARITHMIC,
	FIT_POWER,
	FIT_INVALID
}
zbx_fit_t;

typedef enum
{
	MODE_VALUE,
	MODE_MAX,
	MODE_MIN,
	MODE_DELTA,
	MODE_AVG
}
zbx_mode_t;

int	zbx_fit_code(char *fit_str, zbx_fit_t *fit, unsigned *k, char **error);
int	zbx_mode_code(char *mode_str, zbx_mode_t *mode, char **error);
double	zbx_forecast(double *t, double *x, int n, double now, double time, zbx_fit_t fit, unsigned k, zbx_mode_t mode);
double	zbx_timeleft(double *t, double *x, int n, double now, double threshold, zbx_fit_t fit, unsigned k);

/* fifo queue of pointers */

typedef struct
{
	void	**values;
	int	alloc_num;
	int	head_pos;
	int	tail_pos;
}
zbx_queue_ptr_t;

#define zbx_queue_ptr_empty(queue)	((queue)->head_pos == (queue)->tail_pos ? SUCCEED : FAIL)

int	zbx_queue_ptr_values_num(zbx_queue_ptr_t *queue);
void	zbx_queue_ptr_reserve(zbx_queue_ptr_t *queue, int num);
void	zbx_queue_ptr_compact(zbx_queue_ptr_t *queue);
void	zbx_queue_ptr_create(zbx_queue_ptr_t *queue);
void	zbx_queue_ptr_destroy(zbx_queue_ptr_t *queue);
void	zbx_queue_ptr_push(zbx_queue_ptr_t *queue, void *value);
void	*zbx_queue_ptr_pop(zbx_queue_ptr_t *queue);
void	zbx_queue_ptr_remove_value(zbx_queue_ptr_t *queue, const void *value);

/* list item data */
typedef struct list_item
{
	struct list_item	*next;
	void			*data;
}
zbx_list_item_t;

/* list data */
typedef struct
{
	zbx_list_item_t		*head;
	zbx_list_item_t		*tail;
	zbx_mem_malloc_func_t	mem_malloc_func;
	zbx_mem_realloc_func_t	mem_realloc_func;
	zbx_mem_free_func_t	mem_free_func;
}
zbx_list_t;

/* queue item data */
typedef struct
{
	zbx_list_t		*list;
	zbx_list_item_t		*current;
	zbx_list_item_t		*next;
}
zbx_list_iterator_t;

void	zbx_list_create(zbx_list_t *list);
void	zbx_list_create_ext(zbx_list_t *list, zbx_mem_malloc_func_t mem_malloc_func,
		zbx_mem_free_func_t mem_free_func);
void	zbx_list_destroy(zbx_list_t *list);
int	zbx_list_append(zbx_list_t *list, void *value, zbx_list_item_t **inserted);
int	zbx_list_insert_after(zbx_list_t *list, zbx_list_item_t *after, void *value, zbx_list_item_t **inserted);
int	zbx_list_prepend(zbx_list_t *list, void *value, zbx_list_item_t **inserted);
int	zbx_list_pop(zbx_list_t *list, void **value);
int	zbx_list_peek(const zbx_list_t *list, void **value);
void	zbx_list_iterator_init(zbx_list_t *list, zbx_list_iterator_t *iterator);
int	zbx_list_iterator_init_with(zbx_list_t *list, zbx_list_item_t *next, zbx_list_iterator_t *iterator);
int	zbx_list_iterator_next(zbx_list_iterator_t *iterator);
int	zbx_list_iterator_peek(const zbx_list_iterator_t *iterator, void **value);
void	zbx_list_iterator_clear(zbx_list_iterator_t *iterator);
int	zbx_list_iterator_equal(const zbx_list_iterator_t *iterator1, const zbx_list_iterator_t *iterator2);
int	zbx_list_iterator_isset(const zbx_list_iterator_t *iterator);
void	zbx_list_iterator_update(zbx_list_iterator_t *iterator);
void	*zbx_list_iterator_remove_next(zbx_list_iterator_t *iterator);

#endif /* ZABBIX_ZBXALGO_H */
