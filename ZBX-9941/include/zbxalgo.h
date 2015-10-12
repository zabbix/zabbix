/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#ifndef ZABBIX_ZBXALGO_H
#define ZABBIX_ZBXALGO_H

#include "common.h"

/* generic */

typedef uint32_t zbx_hash_t;

zbx_hash_t	zbx_hash_lookup2(const void *data, size_t len, zbx_hash_t seed);
zbx_hash_t	zbx_hash_modfnv(const void *data, size_t len, zbx_hash_t seed);
zbx_hash_t	zbx_hash_murmur2(const void *data, size_t len, zbx_hash_t seed);
zbx_hash_t	zbx_hash_sdbm(const void *data, size_t len, zbx_hash_t seed);
zbx_hash_t	zbx_hash_djb2(const void *data, size_t len, zbx_hash_t seed);

#define ZBX_DEFAULT_HASH_ALGO		zbx_hash_modfnv
#define ZBX_DEFAULT_PTR_HASH_ALGO	zbx_hash_modfnv
#define ZBX_DEFAULT_UINT64_HASH_ALGO	zbx_hash_modfnv
#define ZBX_DEFAULT_STRING_HASH_ALGO	zbx_hash_modfnv

typedef zbx_hash_t (*zbx_hash_func_t)(const void *data);

zbx_hash_t	zbx_default_uint64_hash_func(const void *data);
zbx_hash_t	zbx_default_string_hash_func(const void *data);

#define ZBX_DEFAULT_HASH_SEED		0

#define ZBX_DEFAULT_UINT64_HASH_FUNC	zbx_default_uint64_hash_func
#define ZBX_DEFAULT_STRING_HASH_FUNC	zbx_default_string_hash_func

typedef int (*zbx_compare_func_t)(const void *d1, const void *d2);

int	zbx_default_int_compare_func(const void *d1, const void *d2);
int	zbx_default_uint64_compare_func(const void *d1, const void *d2);
int	zbx_default_uint64_ptr_compare_func(const void *d1, const void *d2);
int	zbx_default_str_compare_func(const void *d1, const void *d2);
int	zbx_default_ptr_compare_func(const void *d1, const void *d2);

#define ZBX_DEFAULT_INT_COMPARE_FUNC		zbx_default_int_compare_func
#define ZBX_DEFAULT_UINT64_COMPARE_FUNC		zbx_default_uint64_compare_func
#define ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC	zbx_default_uint64_ptr_compare_func
#define ZBX_DEFAULT_STR_COMPARE_FUNC		zbx_default_str_compare_func
#define ZBX_DEFAULT_PTR_COMPARE_FUNC		zbx_default_ptr_compare_func

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

int	is_prime(int n);
int	next_prime(int n);

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

void	*zbx_hashset_insert(zbx_hashset_t *hs, const void *data, size_t size);
void	*zbx_hashset_insert_ext(zbx_hashset_t *hs, const void *data, size_t size, size_t offset);
void	*zbx_hashset_search(zbx_hashset_t *hs, const void *data);
void	zbx_hashset_remove(zbx_hashset_t *hs, const void *data);

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
	zbx_uint64_t		key;
	const void		*data;
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
	zbx_mem_malloc_func_t	mem_malloc_func;
	zbx_mem_realloc_func_t	mem_realloc_func;
	zbx_mem_free_func_t	mem_free_func;
}
zbx_binary_heap_t;

void			zbx_binary_heap_create(zbx_binary_heap_t *heap, zbx_compare_func_t compare_func, int options);
void			zbx_binary_heap_create_ext(zbx_binary_heap_t *heap, zbx_compare_func_t compare_func, int options,
							zbx_mem_malloc_func_t mem_malloc_func,
							zbx_mem_realloc_func_t mem_realloc_func,
							zbx_mem_free_func_t mem_free_func);
void			zbx_binary_heap_destroy(zbx_binary_heap_t *heap);

int			zbx_binary_heap_empty(zbx_binary_heap_t *heap);
zbx_binary_heap_elem_t	*zbx_binary_heap_find_min(zbx_binary_heap_t *heap);
void			zbx_binary_heap_insert(zbx_binary_heap_t *heap, zbx_binary_heap_elem_t *elem);
void			zbx_binary_heap_update_direct(zbx_binary_heap_t *heap, zbx_binary_heap_elem_t *elem);
void			zbx_binary_heap_remove_min(zbx_binary_heap_t *heap);
void			zbx_binary_heap_remove_direct(zbx_binary_heap_t *heap, zbx_uint64_t key);

void			zbx_binary_heap_clear(zbx_binary_heap_t *heap);

/* vector */

#define ZBX_VECTOR_DECL(__id, __type)										\
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
zbx_vector_ ## __id ## _t;											\
														\
void	zbx_vector_ ## __id ## _create(zbx_vector_ ## __id ## _t *vector);					\
void	zbx_vector_ ## __id ## _create_ext(zbx_vector_ ## __id ## _t *vector,					\
						zbx_mem_malloc_func_t mem_malloc_func,				\
						zbx_mem_realloc_func_t mem_realloc_func,			\
						zbx_mem_free_func_t mem_free_func);				\
void	zbx_vector_ ## __id ## _destroy(zbx_vector_ ## __id ## _t *vector);					\
														\
void	zbx_vector_ ## __id ## _append(zbx_vector_ ## __id ## _t *vector, __type value);			\
void	zbx_vector_ ## __id ## _append_ptr(zbx_vector_ ## __id ## _t *vector, __type *value);			\
void	zbx_vector_ ## __id ## _remove_noorder(zbx_vector_ ## __id ## _t *vector, int index);			\
void	zbx_vector_ ## __id ## _remove(zbx_vector_ ## __id ## _t *vector, int index);				\
														\
void	zbx_vector_ ## __id ## _sort(zbx_vector_ ## __id ## _t *vector, zbx_compare_func_t compare_func);	\
void	zbx_vector_ ## __id ## _uniq(zbx_vector_ ## __id ## _t *vector, zbx_compare_func_t compare_func);	\
														\
int	zbx_vector_ ## __id ## _nearestindex(const zbx_vector_ ## __id ## _t *vector, const __type value,	\
									zbx_compare_func_t compare_func);	\
int	zbx_vector_ ## __id ## _bsearch(const zbx_vector_ ## __id ## _t *vector, const __type value,		\
									zbx_compare_func_t compare_func);	\
int	zbx_vector_ ## __id ## _lsearch(const zbx_vector_ ## __id ## _t *vector, const __type value, int *index,\
									zbx_compare_func_t compare_func);	\
int	zbx_vector_ ## __id ## _search(const zbx_vector_ ## __id ## _t *vector, const __type value,		\
									zbx_compare_func_t compare_func);	\
														\
void	zbx_vector_ ## __id ## _reserve(zbx_vector_ ## __id ## _t *vector, size_t size);			\
void	zbx_vector_ ## __id ## _clear(zbx_vector_ ## __id ## _t *vector);

#define ZBX_PTR_VECTOR_DECL(__id, __type)									\
														\
ZBX_VECTOR_DECL(__id, __type);											\
														\
void	zbx_vector_ ## __id ## _clear_ext(zbx_vector_ ## __id ## _t *vector, zbx_clean_func_t clean_func);

ZBX_VECTOR_DECL(uint64, zbx_uint64_t);
ZBX_PTR_VECTOR_DECL(str, char *);
ZBX_PTR_VECTOR_DECL(ptr, void *);
ZBX_VECTOR_DECL(ptr_pair, zbx_ptr_pair_t);
ZBX_VECTOR_DECL(uint64_pair, zbx_uint64_pair_t);

/* this function is only for use with zbx_vector_XXX_clear_ext() */
/* and only if the vector does not contain nested allocations */
void	zbx_ptr_free(void *data);

/* 128 bit unsigned integer handling */
#define uset128(base, hi64, lo64)	(base)->hi = hi64; (base)->lo = lo64

void	uinc128_64(zbx_uint128_t *base, zbx_uint64_t value);
void	uinc128_128(zbx_uint128_t *base, const zbx_uint128_t *value);
void	udiv128_64(zbx_uint128_t *result, const zbx_uint128_t *base, zbx_uint64_t value);
void	umul64_64(zbx_uint128_t *result, zbx_uint64_t value, zbx_uint64_t factor);

unsigned int	zbx_isqrt32(unsigned int value);

/* expression evaluation */

int	evaluate(double *value, const char *expression, char *error, int max_error_len);

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
	MODE_AVG,
	MODE_INVALID
}
zbx_mode_t;

int	zbx_fit_code(char *fit_str, zbx_fit_t *fit, unsigned *k, char **error);
int	zbx_mode_code(char *mode_str, zbx_mode_t *mode, char **error);
double	zbx_forecast(double *t, double *x, int n, double now, double time, zbx_fit_t fit, unsigned k, zbx_mode_t mode);
double	zbx_timeleft(double *t, double *x, int n, double now, double threshold, zbx_fit_t fit, unsigned k);

#endif
