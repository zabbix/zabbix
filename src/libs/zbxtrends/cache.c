/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "db.h"
#include "log.h"
#include "zbxtrends.h"
#include "mutexs.h"
#include "memalloc.h"
#include "trends.h"

extern zbx_uint64_t	CONFIG_TREND_FUNC_CACHE_SIZE;

typedef struct
{
	zbx_uint64_t		itemid;		/* the itemid */
	int			start;		/* the period start time */
	int			end;		/* the period end time */
	zbx_trend_function_t	function;	/* the trends function */
	zbx_trend_state_t	state;		/* the cached value state */
	double			value;		/* the cached value */
	zbx_uint32_t		prev;		/* index of the previous LRU list or unused entry */
	zbx_uint32_t		next;		/* index of the next LRU list or unused entry */
}
zbx_tfc_data_t;

typedef struct
{
	char		header[ZBX_HASHSET_ENTRY_OFFSET];
	zbx_tfc_data_t	data;
}
zbx_tfc_slot_t;

typedef struct
{
	zbx_hashset_t	index;
	zbx_tfc_slot_t	*slots;
	zbx_uint32_t	slots_num;
	zbx_uint32_t	free_head;
	zbx_uint32_t	lru_head;
	zbx_uint32_t	lru_tail;
	zbx_uint64_t	hits;
	zbx_uint64_t	misses;
}
zbx_tfc_t;

static zbx_tfc_t	*cache = NULL;
static zbx_mem_info_t	*tfc_mem = NULL;
static zbx_mutex_t	tfc_lock = ZBX_MUTEX_NULL;

ZBX_MEM_FUNC_IMPL(__tfc, tfc_mem)

#define	LOCK_CACHE	zbx_mutex_lock(tfc_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(tfc_lock)

static zbx_tfc_slot_t	*tfc_alloc_slot()
{
	zbx_uint32_t	index;

	if (UINT32_MAX == cache->free_head)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	index = cache->free_head;
	cache->free_head = cache->slots[index].data.next;

	return &cache->slots[index];
}

static void	tfc_free_slot(zbx_tfc_slot_t *slot)
{
	zbx_uint32_t	index = slot - cache->slots;

	slot->data.next = cache->free_head;
	cache->free_head = index;
}

static zbx_uint32_t	tfc_data_index(zbx_tfc_data_t *data)
{
	return (zbx_tfc_slot_t *)((char *)data - ZBX_HASHSET_ENTRY_OFFSET) - cache->slots;
}

static zbx_hash_t	tfc_hash_func(const void *v)
{
	const zbx_tfc_data_t	*d = (const zbx_tfc_data_t *)v;
	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&d->itemid);
	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&d->start, sizeof(d->start), hash);
	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&d->end, sizeof(d->end), hash);

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&d->function, sizeof(d->function), hash);
}

static int	tfc_compare_func(const void *v1, const void *v2)
{
	const zbx_tfc_data_t	*d1 = (const zbx_tfc_data_t *)v1;
	const zbx_tfc_data_t	*d2 = (const zbx_tfc_data_t *)v2;

	ZBX_RETURN_IF_NOT_EQUAL(d1->itemid, d2->itemid);
	ZBX_RETURN_IF_NOT_EQUAL(d1->start, d2->start);
	ZBX_RETURN_IF_NOT_EQUAL(d1->end, d2->end);

	return d1->function - d2->function;
}

void	*tfc_malloc_func(void *old, size_t size)
{
	if (sizeof(zbx_tfc_slot_t) == size)
		return tfc_alloc_slot();


	return __tfc_mem_malloc_func(old, size);
}

void	*tfc_realloc_func(void *old, size_t size)
{
	return __tfc_mem_realloc_func(old, size);
}

void	tfc_free_func(void *ptr)
{
	if (ptr >= (void *)cache->slots && ptr < (void *)(cache->slots + cache->slots_num))
		return tfc_free_slot(ptr);

	return __tfc_mem_free_func(ptr);
}

static void	tfc_lru_append(zbx_tfc_data_t *data)
{
	zbx_uint32_t	index;

	if (cache->lru_tail == (index = tfc_data_index(data)))
		return;

	data->prev = cache->lru_tail;
	data->next = UINT32_MAX;

	if (UINT32_MAX != data->prev)
		cache->slots[data->prev].data.next = index;
	else
		cache->lru_head = tfc_data_index(data);

	cache->lru_tail = index;
}

static void	tfc_lru_remove(zbx_tfc_data_t *data)
{
	if (UINT32_MAX != data->prev)
		cache->slots[data->prev].data.next = data->next;
	else
		cache->lru_head = data->next;

	if (UINT32_MAX != data->next)
		cache->slots[data->next].data.prev = data->prev;
	else
		cache->lru_tail = data->prev;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tfc_init                                                     *
 *                                                                            *
 * Purpose: initialize trend function cache                                   *
 *                                                                            *
 * Parameters: error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the cache was initialized successfully             *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_tfc_init(char **error)
{
	zbx_uint64_t	size_reserved;
	int		ret = FAIL;
	zbx_uint32_t	i;

	if (0 == CONFIG_TREND_FUNC_CACHE_SIZE)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): trends function cache disabled", __func__);
		return SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_mutex_create(&tfc_lock, ZBX_MUTEX_TREND_FUNC, error))
		goto out;

	size_reserved = zbx_mem_required_size(1, "trend function cache size", "TrendFunctionCacheSize");

	if (SUCCEED != zbx_mem_create(&tfc_mem, CONFIG_TREND_FUNC_CACHE_SIZE, "trend function cache size",
			"TrendFunctionCacheSize", 1, error))
	{
		goto out;
	}

	cache =  (zbx_tfc_t *)__tfc_mem_malloc_func(cache, sizeof(zbx_tfc_t));

	/* (8 + 8) * 3 - overhead for 3 allocations */
	CONFIG_TREND_FUNC_CACHE_SIZE -= size_reserved + sizeof(zbx_tfc_t) + (8 + 8) * 3;

	cache->slots_num = CONFIG_TREND_FUNC_CACHE_SIZE / (16 + sizeof(zbx_tfc_slot_t));
	// WDN
	cache->slots_num = 10;

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): slots:%u", __func__, cache->slots_num);

	zbx_hashset_create_ext(&cache->index, cache->slots_num, tfc_hash_func, tfc_compare_func,
			NULL, tfc_malloc_func, tfc_realloc_func, tfc_free_func);

	cache->lru_head = UINT32_MAX;
	cache->lru_tail = UINT32_MAX;

	cache->slots = (zbx_tfc_slot_t *)zbx_malloc(NULL, sizeof(zbx_tfc_slot_t) * cache->slots_num);
	cache->free_head = UINT32_MAX;
	for (i = 0; i < cache->slots_num; i++)
		tfc_free_slot(&cache->slots[i]);

	cache->hits = 0;
	cache->misses = 0;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __func__, ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tfc_get_value                                                *
 *                                                                            *
 * Purpose: get value and state from trend function cache                     *
 *                                                                            *
 * Parameters: itemid   - [IN] the itemid                                     *
 *             start    - [IN] the period start time (including)              *
 *             end      - [IN] the period end time (including)                *
 *             function - [IN] the trend function                             *
 *             value    - [OUT] the cached value                              *
 *             state    - [OUT] the cached state                              *
 *                                                                            *
 * Return value: SUCCEED - the value/state was retrieved successfully         *
 *               FAIL - no cached item value of the function over the range   *
 *                                                                            *
 ******************************************************************************/
int	zbx_tfc_get_value(zbx_uint64_t itemid, int start, int end, zbx_trend_function_t function, double *value,
		zbx_trend_state_t *state)
{
	zbx_tfc_data_t	*data, data_local;

	if (NULL == cache)
		return FAIL;

	data_local.itemid = itemid;
	data_local.start = start;
	data_local.end = end;
	data_local.function = function;

	LOCK_CACHE;

	if (NULL != (data = (zbx_tfc_data_t *)zbx_hashset_search(&cache->index, &data_local)))
	{
		tfc_lru_remove(data);
		tfc_lru_append(data);

		*value = data->value;
		*state = data->state;

		cache->hits++;
	}
	else
		cache->misses++;

	UNLOCK_CACHE;

	return NULL != data ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tfc_put_value                                                *
 *                                                                            *
 * Purpose: put value and state from trend function cache                     *
 *                                                                            *
 * Parameters: itemid   - [IN] the itemid                                     *
 *             start    - [IN] the period start time (including)              *
 *             end      - [IN] the period end time (including)                *
 *             function - [IN] the trend function                             *
 *             value    - [IN] the value to cache                             *
 *             state    - [IN] the state to cache                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_tfc_put_value(zbx_uint64_t itemid, int start, int end, zbx_trend_function_t function, double value,
		zbx_trend_state_t state)
{
	zbx_tfc_data_t	*data, data_local;

	if (NULL == cache)
		return;

	data_local.itemid = itemid;
	data_local.start = start;
	data_local.end = end;
	data_local.function = function;

	LOCK_CACHE;

	if (UINT32_MAX == cache->free_head)
	{
		zbx_uint32_t	index;

		index = cache->lru_head;
		cache->lru_head = cache->slots[index].data.next;
		cache->slots[cache->lru_head].data.prev = UINT32_MAX;

		zbx_hashset_remove_direct(&cache->index, &cache->slots[index].data);
	}

	data = zbx_hashset_insert(&cache->index, &data_local, sizeof(data_local));
	data->value = value;
	data->state = state;

	tfc_lru_append(data);

	UNLOCK_CACHE;
}
