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
#include "zbxtrends.h"
#include "trends.h"

#include "zbxalgo.h"
#include "zbxmutexs.h"
#include "zbxshmem.h"
#include "zbxnum.h"
#include "zbxstr.h"

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
	zbx_uint32_t		prev_value;	/* index of the previous value list */
	zbx_uint32_t		next_value;	/* index of the next value list */
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
	size_t		slots_size;
	zbx_uint32_t	slots_num;
	zbx_uint32_t	free_slot;
	zbx_uint32_t	free_head;
	zbx_uint32_t	lru_head;
	zbx_uint32_t	lru_tail;
	zbx_uint64_t	hits;
	zbx_uint64_t	misses;
	zbx_uint64_t	items_num;
	zbx_uint64_t	conf_size;
}
zbx_tfc_t;

static zbx_tfc_t	*cache = NULL;
static int		alloc_num = 0;

/*
 * The shared memory is split in three parts:
 *   1) header, containing cache information
 *   2) indexing hashset slots pointer array, allocated during cache initialization
 *   3) slots array, allocated during cache initialization and used for hashset entry allocations
 */
static zbx_shmem_info_t	*tfc_mem = NULL;

static zbx_mutex_t	tfc_lock = ZBX_MUTEX_NULL;

ZBX_SHMEM_FUNC_IMPL(__tfc, tfc_mem)

#define LOCK_CACHE	zbx_mutex_lock(tfc_lock)
#define UNLOCK_CACHE	zbx_mutex_unlock(tfc_lock)

static void	tfc_free_slot(zbx_tfc_slot_t *slot)
{
	zbx_uint32_t	index = slot - cache->slots;

	slot->data.next = cache->free_head;
	slot->data.prev = UINT32_MAX;
	cache->free_head = index;
}

static zbx_tfc_slot_t	*tfc_alloc_slot(void)
{
	zbx_uint32_t	index;

	if (cache->free_slot != cache->slots_num)
		tfc_free_slot(&cache->slots[cache->free_slot++]);

	if (UINT32_MAX == cache->free_head)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	index = cache->free_head;
	cache->free_head = cache->slots[index].data.next;

	return &cache->slots[index];
}

static zbx_uint32_t	tfc_data_slot_index(zbx_tfc_data_t *data)
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

/******************************************************************************
 *                                                                            *
 * Purpose: allocate memory for indexing hashset                              *
 *                                                                            *
 * Comments: There are two kinds of allocations that should be done:          *
 *             1) initial allocation of hashset slots array                   *
 *             2) allocations of hashset entries                              *
 *           The initial hashset size is chosen large enough to hold all      *
 *           entries without reallocation. So there should be no other        *
 *           allocations done.                                                *
 *                                                                            *
 ******************************************************************************/
static void	*tfc_malloc_func(void *old, size_t size)
{
	if (sizeof(zbx_tfc_slot_t) == size)
		return tfc_alloc_slot();

	if (0 == alloc_num++)
		return __tfc_shmem_malloc_func(old, size);

	return NULL;
}

static void	*tfc_realloc_func(void *old, size_t size)
{
	ZBX_UNUSED(old);
	ZBX_UNUSED(size);

	return NULL;
}

static void	tfc_free_func(void *ptr)
{
	if (ptr >= (void *)cache->slots && (char *)ptr < (char *)cache->slots + cache->slots_size)
	{
		tfc_free_slot(ptr);
		return;
	}

	__tfc_shmem_free_func(ptr);
}

/******************************************************************************
 *                                                                            *
 * Purpose: append data to the tail of least recently used slot list          *
 *                                                                            *
 ******************************************************************************/
static void	tfc_lru_append(zbx_tfc_data_t *data)
{
	zbx_uint32_t	index;

	index = tfc_data_slot_index(data);

	data->prev = cache->lru_tail;
	data->next = UINT32_MAX;

	if (UINT32_MAX != data->prev)
		cache->slots[data->prev].data.next = index;
	else
		cache->lru_head = index;

	cache->lru_tail = index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove data from least recently used slot list                    *
 *                                                                            *
 ******************************************************************************/
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
 * Purpose: append data to the tail of same item value list                   *
 *                                                                            *
 ******************************************************************************/
static void	tfc_value_append(zbx_tfc_data_t *root, zbx_tfc_data_t *data)
{
	zbx_uint32_t	index, root_index;

	if (root->prev_value == (index = tfc_data_slot_index(data)))
		return;

	root_index = tfc_data_slot_index(root);

	data->next_value = root_index;
	data->prev_value = root->prev_value;

	root->prev_value = index;
	cache->slots[data->prev_value].data.next_value = index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove data from same item value list                             *
 *                                                                            *
 ******************************************************************************/
static void	tfc_value_remove(zbx_tfc_data_t *data)
{
	cache->slots[data->prev_value].data.next_value = data->next_value;
	cache->slots[data->next_value].data.prev_value = data->prev_value;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees slot used to store trends function data                     *
 *                                                                            *
 ******************************************************************************/
static void	tfc_free_data(zbx_tfc_data_t *data)
{
	tfc_lru_remove(data);
	tfc_value_remove(data);

	if (data->prev_value == data->next_value)
	{
		zbx_hashset_remove_direct(&cache->index, &cache->slots[data->prev_value].data);
		cache->items_num--;
	}

	zbx_hashset_remove_direct(&cache->index, data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: ensure there is a free slot available                             *
 *                                                                            *
 ******************************************************************************/
static void	tfc_reserve_slot(void)
{
	if (UINT32_MAX == cache->free_head && cache->slots_num == cache->free_slot)
	{
		if (UINT32_MAX == cache->lru_head)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(1);
		}

		tfc_free_data(&cache->slots[cache->lru_head].data);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: indexes data by adding it to the index hashset                    *
 *                                                                            *
 ******************************************************************************/
static zbx_tfc_data_t	*tfc_index_add(zbx_tfc_data_t *data_local)
{
	zbx_tfc_data_t	*data;

	if (NULL == (data = (zbx_tfc_data_t *)zbx_hashset_insert(&cache->index, data_local, sizeof(zbx_tfc_data_t))))
	{
		if (cache->slots_num != (zbx_uint32_t)cache->index.num_data)
		{
			zabbix_log(LOG_LEVEL_WARNING, "estimated trends function cache slot count %u for " ZBX_FS_UI64 " bytes was "
					"too large, setting it to %d", cache->slots_num, cache->conf_size,
					cache->index.num_data);

			/* force slot limit to current hashset size and remove all free slots */
			cache->slots_num = cache->index.num_data;
			cache->free_slot = cache->slots_num;
			cache->free_head = UINT32_MAX;
		}

		tfc_reserve_slot();

		if (NULL == (data = (zbx_tfc_data_t *)zbx_hashset_insert(&cache->index, data_local,
				sizeof(zbx_tfc_data_t))))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}
	}

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return trend function name in readable format                     *
 *                                                                            *
 ******************************************************************************/
static const char	*tfc_function_str(zbx_trend_function_t function)
{
	switch (function)
	{
		case ZBX_TREND_FUNCTION_AVG:
			return "avg";
		case ZBX_TREND_FUNCTION_COUNT:
			return "count";
		case ZBX_TREND_FUNCTION_DELTA:
			return "delta";
		case ZBX_TREND_FUNCTION_MAX:
			return "max";
		case ZBX_TREND_FUNCTION_MIN:
			return "min";
		case ZBX_TREND_FUNCTION_SUM:
			return "sum";
		default:
			return "unknown";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: return trend cache state in readable format                       *
 *                                                                            *
 ******************************************************************************/
static const char	*tfc_state_str(zbx_trend_state_t state)
{
	switch (state)
	{
		case ZBX_TREND_STATE_NORMAL:
			return "cached";
		case ZBX_TREND_STATE_NODATA:
			return "nodata";
		case ZBX_TREND_STATE_OVERFLOW:
			return "overflow";
		default:
			return "unknown";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize trend function cache                                   *
 *                                                                            *
 * Parameters: cache_size - [IN] trend function cache size, can be 0          *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the cache was initialized successfully             *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_tfc_init(zbx_uint64_t cache_size, char **error)
{
	zbx_uint64_t	size_actual, size_entry;
	int		ret = FAIL;

	if (0 == cache_size)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): trends function cache disabled", __func__);
		return SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_mutex_create(&tfc_lock, ZBX_MUTEX_TREND_FUNC, error))
		goto out;

	if (SUCCEED != zbx_shmem_create(&tfc_mem, cache_size, "trend function cache size",
			"TrendFunctionCacheSize", 1, error))
	{
		goto out;
	}

	cache =  (zbx_tfc_t *)__tfc_shmem_realloc_func(NULL, sizeof(zbx_tfc_t));

	cache->conf_size = cache_size;

	/* reserve space for hashset slot and entry array allocations */
	size_actual = tfc_mem->free_size - (2 * 8) * 2;
	size_entry = sizeof(zbx_tfc_slot_t) + ZBX_HASHSET_ENTRY_OFFSET;

	/* Estimate the slot limit so that the hashset slot and entry arrays will */
	/* fit the remaining cache memory. The number of hashset slots must be    */
	/* 5/4 of hashset entries (critical load factor).                         */
	cache->slots_num = size_actual / (sizeof(void *) * 5 / 4 + size_entry);

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): slots:%u", __func__, cache->slots_num);

	/* add +4 to compensate for possible rounding errors when checking if hashset */
	/* should be resized and applying critical load factor '4 / 5'                */
	zbx_hashset_create_ext(&cache->index, cache->slots_num * 5 / 4 + 4, tfc_hash_func, tfc_compare_func,
			NULL, tfc_malloc_func, tfc_realloc_func, tfc_free_func);

	cache->lru_head = UINT32_MAX;
	cache->lru_tail = UINT32_MAX;

	/* reserve the rest of memory for hashset entries */
	cache->slots_size = tfc_mem->free_size - (2 * 8);
	cache->slots = (zbx_tfc_slot_t *)__tfc_shmem_malloc_func(NULL, cache->slots_size);

	cache->free_head = UINT32_MAX;
	cache->free_slot = 0;

	cache->hits = 0;
	cache->misses = 0;
	cache->items_num = 0;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __func__, ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy trend function cache                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_tfc_destroy(void)
{
	if (NULL != tfc_mem)
	{
		zbx_shmem_destroy(tfc_mem);
		tfc_mem = NULL;
		zbx_mutex_destroy(&tfc_lock);
		alloc_num = 0;
	}
}

/******************************************************************************
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
int	zbx_tfc_get_value(zbx_uint64_t itemid, time_t start, time_t end, zbx_trend_function_t function, double *value,
		zbx_trend_state_t *state)
{
	zbx_tfc_data_t	*data, data_local;

	if (NULL == cache)
		return FAIL;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		time_t		ts_time;
		struct tm	tm_start, tm_end;

		ts_time = start;
		localtime_r(&ts_time, &tm_start);
		ts_time = end;
		localtime_r(&ts_time, &tm_end);

		zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " %s(%04d.%02d.%02d/%02d,"
				" %04d.%02d.%02d/%02d)", __func__, itemid, tfc_function_str(function),
				tm_start.tm_year + 1900, tm_start.tm_mon + 1, tm_start.tm_mday, tm_start.tm_hour,
				tm_end.tm_year + 1900, tm_end.tm_mon + 1, tm_end.tm_mday, tm_end.tm_hour);
	}

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

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		if (NULL != data)
		{
			char	buf[ZBX_MAX_DOUBLE_LEN + 1];

			if (data->state == ZBX_TREND_STATE_NODATA)
				zbx_strlcpy(buf, "none", sizeof(buf));
			else
				zbx_print_double(buf, sizeof(buf), data->value);

			zabbix_log(LOG_LEVEL_DEBUG, "End of %s() state:%s value:%s", __func__,
					tfc_state_str(data->state), buf);
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s():not cached", __func__);
	}

	return NULL != data ? SUCCEED : FAIL;
}

/******************************************************************************
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
void	zbx_tfc_put_value(zbx_uint64_t itemid, time_t start, time_t end, zbx_trend_function_t function, double value,
		zbx_trend_state_t state)
{
	zbx_tfc_data_t	*data, data_local, *root;

	if (NULL == cache)
		return;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		time_t		ts_time;
		struct tm	tm_start, tm_end;
		char		buf[ZBX_MAX_DOUBLE_LEN + 1];

		ts_time = start;
		localtime_r(&ts_time, &tm_start);
		ts_time = end;
		localtime_r(&ts_time, &tm_end);

		if (state == ZBX_TREND_STATE_NODATA)
			zbx_strlcpy(buf, "none", sizeof(buf));
		else
			zbx_print_double(buf, sizeof(buf), value);

		zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " %s(%04d.%02d.%02d/%02d,"
				" %04d.%02d.%02d/%02d)=%s state:%s", __func__, itemid, tfc_function_str(function),
				tm_start.tm_year + 1900, tm_start.tm_mon + 1, tm_start.tm_mday, tm_start.tm_hour,
				tm_end.tm_year + 1900, tm_end.tm_mon + 1, tm_end.tm_mday, tm_end.tm_hour, buf,
				tfc_state_str(state));
	}

	data_local.itemid = itemid;
	data_local.start = 0;
	data_local.end = 0;
	data_local.function = ZBX_TREND_FUNCTION_UNKNOWN;

	LOCK_CACHE;

	tfc_reserve_slot();

	if (NULL == (root = (zbx_tfc_data_t *)zbx_hashset_search(&cache->index, &data_local)))
	{
		root = tfc_index_add(&data_local);
		root->prev_value = tfc_data_slot_index(root);
		root->next_value = root->prev_value;
		cache->items_num++;
		tfc_reserve_slot();
	}

	data_local.start = start;
	data_local.end = end;
	data_local.function = function;
	data_local.state = ZBX_TREND_STATE_UNKNOWN;
	data = tfc_index_add(&data_local);

	if (ZBX_TREND_STATE_UNKNOWN == data->state)
	{
		/* new slot was allocated, link it */
		tfc_lru_append(data);
		tfc_value_append(root, data);
	}

	data->value = value;
	data->state = state;

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_tfc_invalidate_trends(ZBX_DC_TREND *trends, int trends_num)
{
	zbx_tfc_data_t	*root, *data, data_local;
	int		i, next;

	if (NULL == cache)
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d", __func__, trends_num);

	data_local.start = 0;
	data_local.end = 0;
	data_local.function = ZBX_TREND_FUNCTION_UNKNOWN;

	LOCK_CACHE;

	for (i = 0; i < trends_num; i++)
	{
		data_local.itemid = trends[i].itemid;

		if (NULL == (root = (zbx_tfc_data_t *)zbx_hashset_search(&cache->index, &data_local)))
			continue;

		for (data = &cache->slots[root->next_value].data; data != root; data = &cache->slots[next].data)
		{
			next = data->next_value;

			if (trends[i].clock < data->start || trends[i].clock > data->end)
				continue;

			tfc_free_data(data);
		}
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

int	zbx_tfc_get_stats(zbx_tfc_stats_t *stats, char **error)
{
	if (NULL == cache)
	{
		if (NULL != error)
			*error = zbx_strdup(*error, "Trends function cache is disabled.");

		return FAIL;
	}

	LOCK_CACHE;

	stats->hits = cache->hits;
	stats->misses = cache->misses;
	stats->items_num = cache->items_num;
	stats->requests_num = cache->index.num_data - cache->items_num;

	UNLOCK_CACHE;

	return SUCCEED;
}
