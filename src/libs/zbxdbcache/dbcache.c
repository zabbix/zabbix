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

#include "dbcache.h"

#include "common.h"
#include "log.h"
#include "mutexs.h"
#include "zbxserver.h"
#include "events.h"
#include "valuecache.h"
#include "zbxmodules.h"
#include "module.h"
#include "export.h"
#include "zbxhistory.h"
#include "daemon.h"
#include "zbxavailability.h"
#include "zbxtrends.h"
#include "../zbxalgo/vectorimpl.h"

static zbx_mem_info_t	*hc_index_mem = NULL;
static zbx_mem_info_t	*hc_mem = NULL;
static zbx_mem_info_t	*trend_mem = NULL;

#define	LOCK_CACHE	zbx_mutex_lock(cache_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(cache_lock)
#define	LOCK_TRENDS	zbx_mutex_lock(trends_lock)
#define	UNLOCK_TRENDS	zbx_mutex_unlock(trends_lock)
#define	LOCK_CACHE_IDS		zbx_mutex_lock(cache_ids_lock)
#define	UNLOCK_CACHE_IDS	zbx_mutex_unlock(cache_ids_lock)

static zbx_mutex_t	cache_lock = ZBX_MUTEX_NULL;
static zbx_mutex_t	trends_lock = ZBX_MUTEX_NULL;
static zbx_mutex_t	cache_ids_lock = ZBX_MUTEX_NULL;

static char		*sql = NULL;
static size_t		sql_alloc = 4 * ZBX_KIBIBYTE;

extern unsigned char	program_type;
extern int		CONFIG_DOUBLE_PRECISION;
extern char		*CONFIG_EXPORT_DIR;

#define ZBX_IDS_SIZE	10

#define ZBX_HC_ITEMS_INIT_SIZE	1000

#define ZBX_TRENDS_CLEANUP_TIME	((SEC_PER_HOUR * 55) / 60)

/* the maximum time spent synchronizing history */
#define ZBX_HC_SYNC_TIME_MAX	10

/* the maximum number of items in one synchronization batch */
#define ZBX_HC_SYNC_MAX		1000
#define ZBX_HC_TIMER_MAX	(ZBX_HC_SYNC_MAX / 2)
#define ZBX_HC_TIMER_SOFT_MAX	(ZBX_HC_TIMER_MAX - 10)

/* the minimum processed item percentage of item candidates to continue synchronizing */
#define ZBX_HC_SYNC_MIN_PCNT	10

/* the maximum number of characters for history cache values */
#define ZBX_HISTORY_VALUE_LEN	(1024 * 64)

#define ZBX_DC_FLAGS_NOT_FOR_HISTORY	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF | ZBX_DC_FLAG_NOHISTORY)
#define ZBX_DC_FLAGS_NOT_FOR_TRENDS	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF | ZBX_DC_FLAG_NOTRENDS)
#define ZBX_DC_FLAGS_NOT_FOR_MODULES	(ZBX_DC_FLAGS_NOT_FOR_HISTORY | ZBX_DC_FLAG_LLD)
#define ZBX_DC_FLAGS_NOT_FOR_EXPORT	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF)

#define ZBX_HC_PROXYQUEUE_STATE_NORMAL 0
#define ZBX_HC_PROXYQUEUE_STATE_WAIT 1

typedef struct
{
	char		table_name[ZBX_TABLENAME_LEN_MAX];
	zbx_uint64_t	lastid;
}
ZBX_DC_ID;

typedef struct
{
	ZBX_DC_ID	id[ZBX_IDS_SIZE];
}
ZBX_DC_IDS;

static ZBX_DC_IDS	*ids = NULL;

typedef struct
{
	zbx_list_t	list;
	zbx_hashset_t	index;
	int		state;
}
zbx_hc_proxyqueue_t;

typedef struct
{
	zbx_hashset_t		trends;
	ZBX_DC_STATS		stats;

	zbx_hashset_t		history_items;
	zbx_binary_heap_t	history_queue;

	int			history_num;
	int			trends_num;
	int			trends_last_cleanup_hour;
	int			history_num_total;
	int			history_progress_ts;

	unsigned char		db_trigger_queue_lock;

	zbx_hc_proxyqueue_t     proxyqueue;
}
ZBX_DC_CACHE;

static ZBX_DC_CACHE	*cache = NULL;

/* local history cache */
#define ZBX_MAX_VALUES_LOCAL	256
#define ZBX_STRUCT_REALLOC_STEP	8
#define ZBX_STRING_REALLOC_STEP	ZBX_KIBIBYTE

typedef struct
{
	size_t	pvalue;
	size_t	len;
}
dc_value_str_t;

typedef struct
{
	double		value_dbl;
	zbx_uint64_t	value_uint;
	dc_value_str_t	value_str;
}
dc_value_t;

typedef struct
{
	zbx_uint64_t	itemid;
	dc_value_t	value;
	zbx_timespec_t	ts;
	dc_value_str_t	source;		/* for log items only */
	zbx_uint64_t	lastlogsize;
	int		timestamp;	/* for log items only */
	int		severity;	/* for log items only */
	int		logeventid;	/* for log items only */
	int		mtime;
	unsigned char	item_value_type;
	unsigned char	value_type;
	unsigned char	state;
	unsigned char	flags;		/* see ZBX_DC_FLAG_* above */
}
dc_item_value_t;

static char		*string_values = NULL;
static size_t		string_values_alloc = 0, string_values_offset = 0;
static dc_item_value_t	*item_values = NULL;
static size_t		item_values_alloc = 0, item_values_num = 0;

static void	hc_add_item_values(dc_item_value_t *values, int values_num);
static void	hc_pop_items(zbx_vector_ptr_t *history_items);
static void	hc_get_item_values(ZBX_DC_HISTORY *history, zbx_vector_ptr_t *history_items);
static void	hc_push_items(zbx_vector_ptr_t *history_items);
static void	hc_free_item_values(ZBX_DC_HISTORY *history, int history_num);
static void	hc_queue_item(zbx_hc_item_t *item);
static int	hc_queue_elem_compare_func(const void *d1, const void *d2);
static int	hc_queue_get_size(void);
static int	hc_get_history_compression_age(void);

ZBX_PTR_VECTOR_DECL(item_tag, zbx_tag_t)
ZBX_PTR_VECTOR_IMPL(item_tag, zbx_tag_t)

ZBX_PTR_VECTOR_IMPL(tags, zbx_tag_t*)

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves all internal metrics of the database cache              *
 *                                                                            *
 * Parameters: stats - [OUT] write cache metrics                              *
 *                                                                            *
 ******************************************************************************/
void	DCget_stats_all(zbx_wcache_info_t *wcache_info)
{
	LOCK_CACHE;

	wcache_info->stats = cache->stats;
	wcache_info->history_free = hc_mem->free_size;
	wcache_info->history_total = hc_mem->total_size;
	wcache_info->index_free = hc_index_mem->free_size;
	wcache_info->index_total = hc_index_mem->total_size;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		wcache_info->trend_free = trend_mem->free_size;
		wcache_info->trend_total = trend_mem->orig_size;
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get statistics of the database cache                              *
 *                                                                            *
 ******************************************************************************/
void	*DCget_stats(int request)
{
	static zbx_uint64_t	value_uint;
	static double		value_double;
	void			*ret;

	LOCK_CACHE;

	switch (request)
	{
		case ZBX_STATS_HISTORY_COUNTER:
			value_uint = cache->stats.history_counter;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_FLOAT_COUNTER:
			value_uint = cache->stats.history_float_counter;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_UINT_COUNTER:
			value_uint = cache->stats.history_uint_counter;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_STR_COUNTER:
			value_uint = cache->stats.history_str_counter;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_LOG_COUNTER:
			value_uint = cache->stats.history_log_counter;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_TEXT_COUNTER:
			value_uint = cache->stats.history_text_counter;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_NOTSUPPORTED_COUNTER:
			value_uint = cache->stats.notsupported_counter;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_TOTAL:
			value_uint = hc_mem->total_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_USED:
			value_uint = hc_mem->total_size - hc_mem->free_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_FREE:
			value_uint = hc_mem->free_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_PUSED:
			value_double = 100 * (double)(hc_mem->total_size - hc_mem->free_size) / hc_mem->total_size;
			ret = (void *)&value_double;
			break;
		case ZBX_STATS_HISTORY_PFREE:
			value_double = 100 * (double)hc_mem->free_size / hc_mem->total_size;
			ret = (void *)&value_double;
			break;
		case ZBX_STATS_TREND_TOTAL:
			value_uint = trend_mem->orig_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_TREND_USED:
			value_uint = trend_mem->orig_size - trend_mem->free_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_TREND_FREE:
			value_uint = trend_mem->free_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_TREND_PUSED:
			value_double = 100 * (double)(trend_mem->orig_size - trend_mem->free_size) /
					trend_mem->orig_size;
			ret = (void *)&value_double;
			break;
		case ZBX_STATS_TREND_PFREE:
			value_double = 100 * (double)trend_mem->free_size / trend_mem->orig_size;
			ret = (void *)&value_double;
			break;
		case ZBX_STATS_HISTORY_INDEX_TOTAL:
			value_uint = hc_index_mem->total_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_INDEX_USED:
			value_uint = hc_index_mem->total_size - hc_index_mem->free_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_INDEX_FREE:
			value_uint = hc_index_mem->free_size;
			ret = (void *)&value_uint;
			break;
		case ZBX_STATS_HISTORY_INDEX_PUSED:
			value_double = 100 * (double)(hc_index_mem->total_size - hc_index_mem->free_size) /
					hc_index_mem->total_size;
			ret = (void *)&value_double;
			break;
		case ZBX_STATS_HISTORY_INDEX_PFREE:
			value_double = 100 * (double)hc_index_mem->free_size / hc_index_mem->total_size;
			ret = (void *)&value_double;
			break;
		default:
			ret = NULL;
	}

	UNLOCK_CACHE;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find existing or add new structure and return pointer             *
 *                                                                            *
 * Return value: pointer to a trend structure                                 *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_TREND	*DCget_trend(zbx_uint64_t itemid)
{
	ZBX_DC_TREND	*ptr, trend;

	if (NULL != (ptr = (ZBX_DC_TREND *)zbx_hashset_search(&cache->trends, &itemid)))
		return ptr;

	memset(&trend, 0, sizeof(ZBX_DC_TREND));
	trend.itemid = itemid;

	return (ZBX_DC_TREND *)zbx_hashset_insert(&cache->trends, &trend, sizeof(ZBX_DC_TREND));
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply disable_from changes to cache                               *
 *                                                                            *
 ******************************************************************************/
static void	DCupdate_trends(zbx_vector_uint64_pair_t *trends_diff)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	LOCK_TRENDS;

	for (i = 0; i < trends_diff->values_num; i++)
	{
		ZBX_DC_TREND	*trend;

		if (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_search(&cache->trends, &trends_diff->values[i].first)))
			trend->disable_from = trends_diff->values[i].second;
	}

	UNLOCK_TRENDS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCflush trends                                *
 *                                                                            *
 ******************************************************************************/
static void	dc_insert_trends_in_db(ZBX_DC_TREND *trends, int trends_num, unsigned char value_type,
		const char *table_name, int clock)
{
	ZBX_DC_TREND	*trend;
	int		i;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, table_name, "itemid", "clock", "num", "value_min", "value_avg",
			"value_max", NULL);

	for (i = 0; i < trends_num; i++)
	{
		trend = &trends[i];

		if (0 == trend->itemid)
			continue;

		if (clock != trend->clock || value_type != trend->value_type)
			continue;

		if (ITEM_VALUE_TYPE_FLOAT == value_type)
		{
			zbx_db_insert_add_values(&db_insert, trend->itemid, trend->clock, trend->num,
					trend->value_min.dbl, trend->value_avg.dbl, trend->value_max.dbl);
		}
		else
		{
			zbx_uint128_t	avg;

			/* calculate the trend average value */
			udiv128_64(&avg, &trend->value_avg.ui64, trend->num);

			zbx_db_insert_add_values(&db_insert, trend->itemid, trend->clock, trend->num,
					trend->value_min.ui64, avg.lo, trend->value_max.ui64);
		}

		trend->itemid = 0;
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Update trends disable_until for items without trends data past or *
 *          equal the specified clock                                         *
 *                                                                            *
 * Comments: A helper function for DCflush trends                             *
 *                                                                            *
 ******************************************************************************/
static void	dc_remove_updated_trends(ZBX_DC_TREND *trends, int trends_num, const char *table_name,
		int value_type, zbx_uint64_t *itemids, int *itemids_num, int clock)
{
	int		i, j, clocks_num, now, age;
	ZBX_DC_TREND	*trend;
	zbx_uint64_t	itemid;
	size_t		sql_offset;
	DB_RESULT	result;
	DB_ROW		row;
	int		clocks[] = {SEC_PER_DAY, SEC_PER_WEEK, SEC_PER_MONTH, SEC_PER_YEAR, INT_MAX};

	now = time(NULL);
	age = now - clock;
	for (clocks_num = 0; age > clocks[clocks_num]; clocks_num++)
		clocks[clocks_num] = now - clocks[clocks_num];
	clocks[clocks_num] = clock;

	/* remove itemids with trends data past or equal the clock */
	for (j = 0; j <= clocks_num && 0 < *itemids_num; j++)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct itemid"
				" from %s"
				" where clock>=%d and",
				table_name, clocks[j]);

		if (0 < j)
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " clock<%d and", clocks[j - 1]);

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids, *itemids_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);
			uint64_array_remove(itemids, itemids_num, &itemid, 1);
		}
		DBfree_result(result);
	}

	/* update trends disable_until for the leftover itemids */
	while (0 != *itemids_num)
	{
		itemid = itemids[--*itemids_num];

		for (i = 0; i < trends_num; i++)
		{
			trend = &trends[i];

			if (itemid != trend->itemid)
				continue;

			if (clock != trend->clock || value_type != trend->value_type)
				continue;

			trend->disable_from = clock;
			break;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCflush trends                                *
 *                                                                            *
 ******************************************************************************/
static void	dc_trends_update_float(ZBX_DC_TREND *trend, DB_ROW row, int num, size_t *sql_offset)
{
	history_value_t	value_min, value_avg, value_max;

	value_min.dbl = atof(row[2]);
	value_avg.dbl = atof(row[3]);
	value_max.dbl = atof(row[4]);

	if (value_min.dbl < trend->value_min.dbl)
		trend->value_min.dbl = value_min.dbl;

	if (value_max.dbl > trend->value_max.dbl)
		trend->value_max.dbl = value_max.dbl;

	trend->value_avg.dbl = trend->value_avg.dbl / (trend->num + num) * trend->num +
			value_avg.dbl / (trend->num + num) * num;
	trend->num += num;

	zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "update trends set"
			" num=%d,value_min=" ZBX_FS_DBL64_SQL ",value_avg=" ZBX_FS_DBL64_SQL
			",value_max=" ZBX_FS_DBL64_SQL
			" where itemid=" ZBX_FS_UI64 " and clock=%d;\n",
			trend->num, trend->value_min.dbl, trend->value_avg.dbl, trend->value_max.dbl,
			trend->itemid, trend->clock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCflush trends                                *
 *                                                                            *
 ******************************************************************************/
static void	dc_trends_update_uint(ZBX_DC_TREND *trend, DB_ROW row, int num, size_t *sql_offset)
{
	history_value_t	value_min, value_avg, value_max;
	zbx_uint128_t	avg;

	ZBX_STR2UINT64(value_min.ui64, row[2]);
	ZBX_STR2UINT64(value_avg.ui64, row[3]);
	ZBX_STR2UINT64(value_max.ui64, row[4]);

	if (value_min.ui64 < trend->value_min.ui64)
		trend->value_min.ui64 = value_min.ui64;
	if (value_max.ui64 > trend->value_max.ui64)
		trend->value_max.ui64 = value_max.ui64;

	/* calculate the trend average value */
	umul64_64(&avg, num, value_avg.ui64);
	uinc128_128(&trend->value_avg.ui64, &avg);
	udiv128_64(&avg, &trend->value_avg.ui64, trend->num + num);

	trend->num += num;

	zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset,
			"update trends_uint set num=%d,value_min=" ZBX_FS_UI64 ",value_avg="
			ZBX_FS_UI64 ",value_max=" ZBX_FS_UI64 " where itemid=" ZBX_FS_UI64
			" and clock=%d;\n",
			trend->num,
			trend->value_min.ui64,
			avg.lo,
			trend->value_max.ui64,
			trend->itemid,
			trend->clock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCflush trends                                *
 *                                                                            *
 ******************************************************************************/
static void	dc_trends_fetch_and_update(ZBX_DC_TREND *trends, int trends_num, zbx_uint64_t *itemids,
		int itemids_num, int *inserts_num, unsigned char value_type,
		const char *table_name, int clock)
{

	int		i, num;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;
	ZBX_DC_TREND	*trend;
	size_t		sql_offset;

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select itemid,num,value_min,value_avg,value_max"
			" from %s"
			" where clock=%d and",
			table_name, clock);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids, itemids_num);

	result = DBselect("%s order by itemid,clock", sql);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);

		for (i = 0; i < trends_num; i++)
		{
			trend = &trends[i];

			if (itemid != trend->itemid)
				continue;

			if (clock != trend->clock || value_type != trend->value_type)
				continue;

			break;
		}

		if (i == trends_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		num = atoi(row[1]);

		if (value_type == ITEM_VALUE_TYPE_FLOAT)
			dc_trends_update_float(trend, row, num, &sql_offset);
		else
			dc_trends_update_uint(trend, row, num, &sql_offset);

		trend->itemid = 0;

		--*inserts_num;

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush trend to the database                                       *
 *                                                                            *
 ******************************************************************************/
static void	DBflush_trends(ZBX_DC_TREND *trends, int *trends_num, zbx_vector_uint64_pair_t *trends_diff)
{
	int		num, i, clock, inserts_num = 0, itemids_alloc, itemids_num = 0, trends_to = *trends_num;
	unsigned char	value_type;
	zbx_uint64_t	*itemids = NULL;
	ZBX_DC_TREND	*trend = NULL;
	const char	*table_name;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d", __func__, *trends_num);

	clock = trends[0].clock;
	value_type = trends[0].value_type;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			table_name = "trends";
			break;
		case ITEM_VALUE_TYPE_UINT64:
			table_name = "trends_uint";
			break;
		default:
			assert(0);
	}

	itemids_alloc = MIN(ZBX_HC_SYNC_MAX, *trends_num);
	itemids = (zbx_uint64_t *)zbx_malloc(itemids, itemids_alloc * sizeof(zbx_uint64_t));

	for (i = 0; i < *trends_num; i++)
	{
		trend = &trends[i];

		if (clock != trend->clock || value_type != trend->value_type)
			continue;

		inserts_num++;

		if (0 != trend->disable_from)
			continue;

		uint64_array_add(&itemids, &itemids_alloc, &itemids_num, trend->itemid, 64);

		if (ZBX_HC_SYNC_MAX == itemids_num)
		{
			trends_to = i + 1;
			break;
		}
	}

	if (0 != itemids_num)
	{
		dc_remove_updated_trends(trends, trends_to, table_name, value_type, itemids,
				&itemids_num, clock);
	}

	for (i = 0; i < trends_to; i++)
	{
		trend = &trends[i];

		if (clock != trend->clock || value_type != trend->value_type)
			continue;

		if (0 != trend->disable_from && clock >= trend->disable_from)
			continue;

		uint64_array_add(&itemids, &itemids_alloc, &itemids_num, trend->itemid, 64);
	}

	if (0 != itemids_num)
	{
		dc_trends_fetch_and_update(trends, trends_to, itemids, itemids_num,
				&inserts_num, value_type, table_name, clock);
	}

	zbx_free(itemids);

	/* if 'trends' is not a primary trends buffer */
	if (NULL != trends_diff)
	{
		/* we update it too */
		for (i = 0; i < trends_to; i++)
		{
			zbx_uint64_pair_t	pair;

			if (0 == trends[i].itemid)
				continue;

			if (clock != trends[i].clock || value_type != trends[i].value_type)
				continue;

			if (0 == trends[i].disable_from || trends[i].disable_from > clock)
				continue;

			pair.first = trends[i].itemid;
			pair.second = clock + SEC_PER_HOUR;
			zbx_vector_uint64_pair_append(trends_diff, pair);
		}
	}

	if (0 != inserts_num)
		dc_insert_trends_in_db(trends, trends_to, value_type, table_name, clock);

	/* clean trends */
	for (i = 0, num = 0; i < *trends_num; i++)
	{
		if (0 == trends[i].itemid)
			continue;

		memcpy(&trends[num++], &trends[i], sizeof(ZBX_DC_TREND));
	}
	*trends_num = num;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: move trend to the array of trends for flushing to DB              *
 *                                                                            *
 ******************************************************************************/
static void	DCflush_trend(ZBX_DC_TREND *trend, ZBX_DC_TREND **trends, int *trends_alloc, int *trends_num)
{
	if (*trends_num == *trends_alloc)
	{
		*trends_alloc += 256;
		*trends = (ZBX_DC_TREND *)zbx_realloc(*trends, *trends_alloc * sizeof(ZBX_DC_TREND));
	}

	memcpy(&(*trends)[*trends_num], trend, sizeof(ZBX_DC_TREND));
	(*trends_num)++;

	trend->clock = 0;
	trend->num = 0;
	memset(&trend->value_min, 0, sizeof(history_value_t));
	memset(&trend->value_avg, 0, sizeof(value_avg_t));
	memset(&trend->value_max, 0, sizeof(history_value_t));
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new value to the trends                                       *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_trend(const ZBX_DC_HISTORY *history, ZBX_DC_TREND **trends, int *trends_alloc, int *trends_num)
{
	ZBX_DC_TREND	*trend = NULL;
	int		hour;

	hour = history->ts.sec - history->ts.sec % SEC_PER_HOUR;

	trend = DCget_trend(history->itemid);

	if (trend->num > 0 && (trend->clock != hour || trend->value_type != history->value_type) &&
			SUCCEED == zbx_history_requires_trends(trend->value_type))
	{
		DCflush_trend(trend, trends, trends_alloc, trends_num);
	}

	trend->value_type = history->value_type;
	trend->clock = hour;

	switch (trend->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (trend->num == 0 || history->value.dbl < trend->value_min.dbl)
				trend->value_min.dbl = history->value.dbl;
			if (trend->num == 0 || history->value.dbl > trend->value_max.dbl)
				trend->value_max.dbl = history->value.dbl;
			trend->value_avg.dbl += history->value.dbl / (trend->num + 1) -
					trend->value_avg.dbl / (trend->num + 1);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (trend->num == 0 || history->value.ui64 < trend->value_min.ui64)
				trend->value_min.ui64 = history->value.ui64;
			if (trend->num == 0 || history->value.ui64 > trend->value_max.ui64)
				trend->value_max.ui64 = history->value.ui64;
			uinc128_64(&trend->value_avg.ui64, history->value.ui64);
			break;
	}
	trend->num++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update trends cache and get list of trends to flush into database *
 *                                                                            *
 * Parameters: history         - [IN]  array of history data                  *
 *             history_num     - [IN]  number of history structures           *
 *             trends          - [OUT] list of trends to flush into database  *
 *             trends_num      - [OUT] number of trends                       *
 *             compression_age - [IN]  history compression age                *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_update_trends(const ZBX_DC_HISTORY *history, int history_num, ZBX_DC_TREND **trends,
		int *trends_num, int compression_age)
{
	static int	last_trend_discard = 0;
	zbx_timespec_t	ts;
	int		trends_alloc = 0, i, hour, seconds;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_timespec(&ts);
	seconds = ts.sec % SEC_PER_HOUR;
	hour = ts.sec - seconds;

	LOCK_TRENDS;

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAGS_NOT_FOR_TRENDS & h->flags))
			continue;

		DCadd_trend(h, trends, &trends_alloc, trends_num);
	}

	if (cache->trends_last_cleanup_hour < hour && ZBX_TRENDS_CLEANUP_TIME < seconds)
	{
		zbx_hashset_iter_t	iter;
		ZBX_DC_TREND		*trend;

		zbx_hashset_iter_reset(&cache->trends, &iter);

		while (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_iter_next(&iter)))
		{
			if (trend->clock == hour)
				continue;

			/* discard trend items that are older than compression age */
			if (0 != compression_age && trend->clock < compression_age)
			{
				if (SEC_PER_HOUR < (ts.sec - last_trend_discard)) /* log once per hour */
				{
					zabbix_log(LOG_LEVEL_TRACE, "discarding trends that are pointing to"
							" compressed history period");
					last_trend_discard = ts.sec;
				}
			}
			else if (SUCCEED == zbx_history_requires_trends(trend->value_type))
				DCflush_trend(trend, trends, &trends_alloc, trends_num);

			zbx_hashset_iter_remove(&iter);
		}

		cache->trends_last_cleanup_hour = hour;
	}

	UNLOCK_TRENDS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	zbx_trend_compare(const void *d1, const void *d2)
{
	const ZBX_DC_TREND	*p1 = (const ZBX_DC_TREND *)d1;
	const ZBX_DC_TREND	*p2 = (const ZBX_DC_TREND *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->itemid, p2->itemid);
	ZBX_RETURN_IF_NOT_EQUAL(p1->clock, p2->clock);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare history data using items from configuration cache         *
 *                                                                            *
 * Parameters: trends      - [IN] trends from cache to be added to database   *
 *             trends_num  - [IN] number of trends to add to database         *
 *             trends_diff - [OUT] disable_from updates                       *
 *                                                                            *
 ******************************************************************************/
static void	DBmass_update_trends(const ZBX_DC_TREND *trends, int trends_num,
		zbx_vector_uint64_pair_t *trends_diff)
{
	ZBX_DC_TREND	*trends_tmp;

	if (0 != trends_num)
	{
		trends_tmp = (ZBX_DC_TREND *)zbx_malloc(NULL, trends_num * sizeof(ZBX_DC_TREND));
		memcpy(trends_tmp, trends, trends_num * sizeof(ZBX_DC_TREND));
		qsort(trends_tmp, trends_num, sizeof(ZBX_DC_TREND), zbx_trend_compare);

		while (0 < trends_num)
			DBflush_trends(trends_tmp, &trends_num, trends_diff);

		zbx_free(trends_tmp);
	}
}

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_vector_ptr_t	groups;
}
zbx_host_info_t;

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store host groups names              *
 *                                                                            *
 * Parameters: host_info - [IN] host information                              *
 *                                                                            *
 ******************************************************************************/
static void	zbx_host_info_clean(zbx_host_info_t *host_info)
{
	zbx_vector_ptr_clear_ext(&host_info->groups, zbx_ptr_free);
	zbx_vector_ptr_destroy(&host_info->groups);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get hosts groups names                                            *
 *                                                                            *
 * Parameters: hosts_info - [IN/OUT] output names of host groups for a host   *
 *             hostids    - [IN] hosts identifiers                            *
 *                                                                            *
 ******************************************************************************/
static void	db_get_hosts_info_by_hostid(zbx_hashset_t *hosts_info, const zbx_vector_uint64_t *hostids)
{
	int		i;
	size_t		sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;

	for (i = 0; i < hostids->values_num; i++)
	{
		zbx_host_info_t	host_info = {.hostid = hostids->values[i]};

		zbx_vector_ptr_create(&host_info.groups);
		zbx_hashset_insert(hosts_info, &host_info, sizeof(host_info));
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct hg.hostid,g.name"
				" from hstgrp g,hosts_groups hg"
				" where g.groupid=hg.groupid"
					" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.hostid", hostids->values, hostids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	hostid;
		zbx_host_info_t	*host_info;

		ZBX_DBROW2UINT64(hostid, row[0]);

		if (NULL == (host_info = (zbx_host_info_t *)zbx_hashset_search(hosts_info, &hostid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_ptr_append(&host_info->groups, zbx_strdup(NULL, row[1]));
	}
	DBfree_result(result);
}

typedef struct
{
	zbx_uint64_t		itemid;
	char			*name;
	DC_ITEM			*item;
	zbx_vector_item_tag_t	item_tags;
}
zbx_item_info_t;

/******************************************************************************
 *                                                                            *
 * Purpose: get items name and item tags                                      *
 *                                                                            *
 * Parameters: items_info - [IN/OUT] output item name and item tags           *
 *             itemids    - [IN] the item identifiers                         *
 *                                                                            *
 ******************************************************************************/
static void	db_get_items_info_by_itemid(zbx_hashset_t *items_info, const zbx_vector_uint64_t *itemids)
{
	size_t		sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select itemid,name from items where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;
		zbx_item_info_t	*item_info;

		ZBX_DBROW2UINT64(itemid, row[0]);

		if (NULL == (item_info = (zbx_item_info_t *)zbx_hashset_search(items_info, &itemid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_info->name = zbx_strdup(item_info->name, row[1]);
	}
	DBfree_result(result);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select itemid,tag,value from item_tag where");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;
		zbx_item_info_t	*item_info;
		zbx_tag_t	item_tag;

		ZBX_DBROW2UINT64(itemid, row[0]);

		if (NULL == (item_info = (zbx_item_info_t *)zbx_hashset_search(items_info, &itemid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_tag.tag = zbx_strdup(NULL, row[1]);
		item_tag.value = zbx_strdup(NULL, row[2]);
		zbx_vector_item_tag_append(&item_info->item_tags, item_tag);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store item tag                       *
 *                                                                            *
 * Parameters: item_tag - [IN] item tag                                       *
 *                                                                            *
 ******************************************************************************/
static void	item_tag_free(zbx_tag_t item_tag)
{
	zbx_free(item_tag.tag);
	zbx_free(item_tag.value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store item tags and name             *
 *                                                                            *
 * Parameters: item_info - [IN] item information                              *
 *                                                                            *
 ******************************************************************************/
static void	zbx_item_info_clean(zbx_item_info_t *item_info)
{
	zbx_vector_item_tag_clear_ext(&item_info->item_tags, item_tag_free);
	zbx_vector_item_tag_destroy(&item_info->item_tags);
	zbx_free(item_info->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: export trends                                                     *
 *                                                                            *
 * Parameters: trends     - [IN] trends from cache                            *
 *             trends_num - [IN] number of trends                             *
 *             hosts_info - [IN] hosts groups names                           *
 *             items_info - [IN] item names and tags                          *
 *                                                                            *
 ******************************************************************************/
static void	DCexport_trends(const ZBX_DC_TREND *trends, int trends_num, zbx_hashset_t *hosts_info,
		zbx_hashset_t *items_info)
{
	struct zbx_json		json;
	const ZBX_DC_TREND	*trend = NULL;
	int			i, j;
	const DC_ITEM		*item;
	zbx_host_info_t		*host_info;
	zbx_item_info_t		*item_info;
	zbx_uint128_t		avg;	/* calculate the trend average value */

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < trends_num; i++)
	{
		trend = &trends[i];

		if (NULL == (item_info = (zbx_item_info_t *)zbx_hashset_search(items_info, &trend->itemid)))
			continue;

		item = item_info->item;

		if (NULL == (host_info = (zbx_host_info_t *)zbx_hashset_search(hosts_info, &item->host.hostid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_json_clean(&json);

		zbx_json_addobject(&json,ZBX_PROTO_TAG_HOST);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, item->host.host, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, item->host.name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_GROUPS);

		for (j = 0; j < host_info->groups.values_num; j++)
			zbx_json_addstring(&json, NULL, host_info->groups.values[j], ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_ITEM_TAGS);

		for (j = 0; j < item_info->item_tags.values_num; j++)
		{
			zbx_tag_t	item_tag = item_info->item_tags.values[j];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_TAG, item_tag.tag, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, item_tag.value, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json);
		}

		zbx_json_close(&json);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_ITEMID, item->itemid);

		if (NULL != item_info->name)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, item_info->name, ZBX_JSON_TYPE_STRING);

		zbx_json_addint64(&json, ZBX_PROTO_TAG_CLOCK, trend->clock);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_COUNT, trend->num);

		switch (trend->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_json_addfloat(&json, ZBX_PROTO_TAG_MIN, trend->value_min.dbl);
				zbx_json_addfloat(&json, ZBX_PROTO_TAG_AVG, trend->value_avg.dbl);
				zbx_json_addfloat(&json, ZBX_PROTO_TAG_MAX, trend->value_max.dbl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_json_adduint64(&json, ZBX_PROTO_TAG_MIN, trend->value_min.ui64);
				udiv128_64(&avg, &trend->value_avg.ui64, trend->num);
				zbx_json_adduint64(&json, ZBX_PROTO_TAG_AVG, avg.lo);
				zbx_json_adduint64(&json, ZBX_PROTO_TAG_MAX, trend->value_max.ui64);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		zbx_json_adduint64(&json, ZBX_PROTO_TAG_TYPE, trend->value_type);
		zbx_trends_export_write(json.buffer, json.buffer_size);
	}

	zbx_trends_export_flush();
	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: export history                                                    *
 *                                                                            *
 * Parameters: history     - [IN/OUT] array of history data                   *
 *             history_num - [IN] number of history structures                *
 *             hosts_info  - [IN] hosts groups names                          *
 *             items_info  - [IN] item names and tags                         *
 *                                                                            *
 ******************************************************************************/
static void	DCexport_history(const ZBX_DC_HISTORY *history, int history_num, zbx_hashset_t *hosts_info,
		zbx_hashset_t *items_info)
{
	const ZBX_DC_HISTORY	*h;
	const DC_ITEM		*item;
	int			i, j;
	zbx_host_info_t		*host_info;
	zbx_item_info_t		*item_info;
	struct zbx_json		json;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < history_num; i++)
	{
		h = &history[i];

		if (0 != (ZBX_DC_FLAGS_NOT_FOR_MODULES & h->flags))
			continue;

		if (NULL == (item_info = (zbx_item_info_t *)zbx_hashset_search(items_info, &h->itemid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item = item_info->item;

		if (NULL == (host_info = (zbx_host_info_t *)zbx_hashset_search(hosts_info, &item->host.hostid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_json_clean(&json);

		zbx_json_addobject(&json,ZBX_PROTO_TAG_HOST);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, item->host.host, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, item->host.name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_GROUPS);

		for (j = 0; j < host_info->groups.values_num; j++)
			zbx_json_addstring(&json, NULL, host_info->groups.values[j], ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_ITEM_TAGS);

		for (j = 0; j < item_info->item_tags.values_num; j++)
		{
			zbx_tag_t	item_tag = item_info->item_tags.values[j];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_TAG, item_tag.tag, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, item_tag.value, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json);
		}

		zbx_json_close(&json);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_ITEMID, item->itemid);

		if (NULL != item_info->name)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, item_info->name, ZBX_JSON_TYPE_STRING);

		zbx_json_addint64(&json, ZBX_PROTO_TAG_CLOCK, h->ts.sec);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_NS, h->ts.ns);

		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_json_addfloat(&json, ZBX_PROTO_TAG_VALUE, h->value.dbl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_json_adduint64(&json, ZBX_PROTO_TAG_VALUE, h->value.ui64);
				break;
			case ITEM_VALUE_TYPE_STR:
				zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, h->value.str, ZBX_JSON_TYPE_STRING);
				break;
			case ITEM_VALUE_TYPE_TEXT:
				zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, h->value.str, ZBX_JSON_TYPE_STRING);
				break;
			case ITEM_VALUE_TYPE_LOG:
				zbx_json_addint64(&json, ZBX_PROTO_TAG_LOGTIMESTAMP, h->value.log->timestamp);
				zbx_json_addstring(&json, ZBX_PROTO_TAG_LOGSOURCE,
						ZBX_NULL2EMPTY_STR(h->value.log->source), ZBX_JSON_TYPE_STRING);
				zbx_json_addint64(&json, ZBX_PROTO_TAG_LOGSEVERITY, h->value.log->severity);
				zbx_json_addint64(&json, ZBX_PROTO_TAG_LOGEVENTID, h->value.log->logeventid);
				zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, h->value.log->value,
						ZBX_JSON_TYPE_STRING);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		zbx_json_adduint64(&json, ZBX_PROTO_TAG_TYPE, h->value_type);
		zbx_history_export_write(json.buffer, json.buffer_size);
	}

	zbx_history_export_flush();
	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: export history and trends                                         *
 *                                                                            *
 * Parameters: history     - [IN/OUT] array of history data                   *
 *             history_num - [IN] number of history structures                *
 *             itemids     - [IN] the item identifiers                        *
 *                                (used for item lookup)                      *
 *             items       - [IN] the items                                   *
 *             errcodes    - [IN] item error codes                            *
 *             trends      - [IN] trends from cache                           *
 *             trends_num  - [IN] number of trends                            *
 *                                                                            *
 ******************************************************************************/
static void	DCexport_history_and_trends(const ZBX_DC_HISTORY *history, int history_num,
		const zbx_vector_uint64_t *itemids, DC_ITEM *items, const int *errcodes, const ZBX_DC_TREND *trends,
		int trends_num)
{
	int			i, index;
	zbx_vector_uint64_t	hostids, item_info_ids;
	zbx_hashset_t		hosts_info, items_info;
	DC_ITEM			*item;
	zbx_item_info_t		item_info;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d trends_num:%d", __func__, history_num, trends_num);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&item_info_ids);
	zbx_hashset_create_ext(&items_info, itemids->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)zbx_item_info_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAGS_NOT_FOR_EXPORT & h->flags))
			continue;

		if (FAIL == (index = zbx_vector_uint64_bsearch(itemids, h->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		if (SUCCEED != errcodes[index])
			continue;

		item = &items[index];

		zbx_vector_uint64_append(&hostids, item->host.hostid);
		zbx_vector_uint64_append(&item_info_ids, item->itemid);

		item_info.itemid = item->itemid;
		item_info.name = NULL;
		item_info.item = item;
		zbx_vector_item_tag_create(&item_info.item_tags);
		zbx_hashset_insert(&items_info, &item_info, sizeof(item_info));
	}

	if (0 == history_num)
	{
		for (i = 0; i < trends_num; i++)
		{
			const ZBX_DC_TREND	*trend = &trends[i];

			if (FAIL == (index = zbx_vector_uint64_bsearch(itemids, trend->itemid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != errcodes[index])
				continue;

			item = &items[index];

			zbx_vector_uint64_append(&hostids, item->host.hostid);
			zbx_vector_uint64_append(&item_info_ids, item->itemid);

			item_info.itemid = item->itemid;
			item_info.name = NULL;
			item_info.item = item;
			zbx_vector_item_tag_create(&item_info.item_tags);
			zbx_hashset_insert(&items_info, &item_info, sizeof(item_info));
		}
	}

	if (0 == item_info_ids.values_num)
		goto clean;

	zbx_vector_uint64_sort(&item_info_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create_ext(&hosts_info, hostids.values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)zbx_host_info_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	db_get_hosts_info_by_hostid(&hosts_info, &hostids);

	db_get_items_info_by_itemid(&items_info, &item_info_ids);

	if (0 != history_num)
		DCexport_history(history, history_num, &hosts_info, &items_info);

	if (0 != trends_num)
		DCexport_trends(trends, trends_num, &hosts_info, &items_info);

	zbx_hashset_destroy(&hosts_info);
clean:
	zbx_hashset_destroy(&items_info);
	zbx_vector_uint64_destroy(&item_info_ids);
	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: export all trends                                                 *
 *                                                                            *
 * Parameters: trends     - [IN] trends from cache                            *
 *             trends_num - [IN] number of trends                             *
 *                                                                            *
 ******************************************************************************/
static void	DCexport_all_trends(const ZBX_DC_TREND *trends, int trends_num)
{
	DC_ITEM			*items;
	zbx_vector_uint64_t	itemids;
	int			*errcodes, i, num;

	zabbix_log(LOG_LEVEL_WARNING, "exporting trend data...");

	while (0 < trends_num)
	{
		num = MIN(ZBX_HC_SYNC_MAX, trends_num);

		items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * (size_t)num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)num);

		zbx_vector_uint64_create(&itemids);
		zbx_vector_uint64_reserve(&itemids, num);

		for (i = 0; i < num; i++)
			zbx_vector_uint64_append(&itemids, trends[i].itemid);

		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DCconfig_get_items_by_itemids(items, itemids.values, errcodes, num);

		DCexport_history_and_trends(NULL, 0, &itemids, items, errcodes, trends, num);

		DCconfig_clean_items(items, errcodes, num);
		zbx_vector_uint64_destroy(&itemids);
		zbx_free(items);
		zbx_free(errcodes);

		trends += num;
		trends_num -= num;
	}

	zabbix_log(LOG_LEVEL_WARNING, "exporting trend data done");
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush all trends to the database                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_trends(void)
{
	zbx_hashset_iter_t	iter;
	ZBX_DC_TREND		*trends = NULL, *trend;
	int			trends_alloc = 0, trends_num = 0, compression_age;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d", __func__, cache->trends_num);

	compression_age = hc_get_history_compression_age();

	zabbix_log(LOG_LEVEL_WARNING, "syncing trend data...");

	LOCK_TRENDS;

	zbx_hashset_iter_reset(&cache->trends, &iter);

	while (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_iter_next(&iter)))
	{
		if (SUCCEED == zbx_history_requires_trends(trend->value_type) && trend->clock >= compression_age)
			DCflush_trend(trend, &trends, &trends_alloc, &trends_num);
	}

	UNLOCK_TRENDS;

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS) && 0 != trends_num)
		DCexport_all_trends(trends, trends_num);

	if (0 < trends_num)
		qsort(trends, trends_num, sizeof(ZBX_DC_TREND), zbx_trend_compare);

	DBbegin();

	while (trends_num > 0)
		DBflush_trends(trends, &trends_num, NULL);

	DBcommit();

	zbx_free(trends);

	zabbix_log(LOG_LEVEL_WARNING, "syncing trend data done");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: re-calculate and update values of triggers related to the items   *
 *                                                                            *
 * Parameters: history           - [IN] array of history data                 *
 *             history_num       - [IN] number of history structures          *
 *             history_itemids   - [IN] the item identifiers                  *
 *                                      (used for item lookup)                *
 *             history_items     - [IN] the items                             *
 *             history_errcodes  - [IN] item error codes                      *
 *             timers            - [IN] the trigger timers                    *
 *             trigger_diff      - [OUT] trigger updates                      *
 *             itemids           - [OUT] the item identifiers                 *
 *                                      (used for item lookup)                *
 *             timespecs         - [OUT] timestamp for item identifiers       *
 *             trigger_info      - [OUT] triggers                             *
 *             trigger_order     - [OUT] pointer to the list of triggers      *
 *                                                                            *
 ******************************************************************************/
static void	recalculate_triggers(const ZBX_DC_HISTORY *history, int history_num,
		const zbx_vector_uint64_t *history_itemids, const DC_ITEM *history_items, const int *history_errcodes,
		const zbx_vector_ptr_t *timers, zbx_vector_ptr_t *trigger_diff, zbx_uint64_t *itemids,
		zbx_timespec_t *timespecs, zbx_hashset_t *trigger_info, zbx_vector_ptr_t *trigger_order)
{
	int			i, item_num = 0, timers_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != history_num)
	{
		for (i = 0; i < history_num; i++)
		{
			const ZBX_DC_HISTORY	*h = &history[i];

			if (0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
				continue;

			itemids[item_num] = h->itemid;
			timespecs[item_num] = h->ts;
			item_num++;
		}
	}

	for (i = 0; i < timers->values_num; i++)
	{
		zbx_trigger_timer_t	*timer = (zbx_trigger_timer_t *)timers->values[i];

		if (0 != timer->lock)
			timers_num++;
	}

	if (0 == item_num && 0 == timers_num)
		goto out;

	if (SUCCEED != zbx_hashset_reserve(trigger_info, MAX(100, 2 * item_num + timers_num)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
	}

	zbx_vector_ptr_reserve(trigger_order, trigger_info->num_slots);

	if (0 != item_num)
	{
		DCconfig_get_triggers_by_itemids(trigger_info, trigger_order, itemids, timespecs, item_num);
		prepare_triggers((DC_TRIGGER **)trigger_order->values, trigger_order->values_num);
		zbx_determine_items_in_expressions(trigger_order, itemids, item_num);
	}

	if (0 != timers_num)
	{
		int	offset = trigger_order->values_num;

		zbx_dc_get_triggers_by_timers(trigger_info, trigger_order, timers);

		if (offset != trigger_order->values_num)
		{
			prepare_triggers((DC_TRIGGER **)trigger_order->values + offset,
					trigger_order->values_num - offset);
		}
	}

	zbx_vector_ptr_sort(trigger_order, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	evaluate_expressions(trigger_order, history_itemids, history_items, history_errcodes);
	zbx_process_triggers(trigger_order, trigger_diff);

	DCfree_triggers(trigger_order);

	zbx_hashset_clear(trigger_info);
	zbx_vector_ptr_clear(trigger_order);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	DCinventory_value_add(zbx_vector_ptr_t *inventory_values, const DC_ITEM *item, ZBX_DC_HISTORY *h)
{
	char			value[MAX_BUFFER_LEN];
	const char		*inventory_field;
	zbx_inventory_value_t	*inventory_value;

	if (ITEM_STATE_NOTSUPPORTED == h->state)
		return;

	if (HOST_INVENTORY_AUTOMATIC != item->host.inventory_mode)
		return;

	if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags) ||
			NULL == (inventory_field = DBget_inventory_field(item->inventory_link)))
	{
		return;
	}

	switch (h->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_print_double(value, sizeof(value), h->value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(value, sizeof(value), ZBX_FS_UI64, h->value.ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			strscpy(value, h->value.str);
			break;
		default:
			return;
	}

	zbx_format_value(value, sizeof(value), item->valuemapid, ZBX_NULL2EMPTY_STR(item->units), h->value_type);

	inventory_value = (zbx_inventory_value_t *)zbx_malloc(NULL, sizeof(zbx_inventory_value_t));

	inventory_value->hostid = item->host.hostid;
	inventory_value->idx = item->inventory_link - 1;
	inventory_value->field_name = inventory_field;
	inventory_value->value = zbx_strdup(NULL, value);

	zbx_vector_ptr_append(inventory_values, inventory_value);
}

static void	DCadd_update_inventory_sql(size_t *sql_offset, const zbx_vector_ptr_t *inventory_values)
{
	char	*value_esc;
	int	i;

	for (i = 0; i < inventory_values->values_num; i++)
	{
		const zbx_inventory_value_t	*inventory_value = (zbx_inventory_value_t *)inventory_values->values[i];

		value_esc = DBdyn_escape_field("host_inventory", inventory_value->field_name, inventory_value->value);

		zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset,
				"update host_inventory set %s='%s' where hostid=" ZBX_FS_UI64 ";\n",
				inventory_value->field_name, value_esc, inventory_value->hostid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, sql_offset);

		zbx_free(value_esc);
	}
}

static void	DCinventory_value_free(zbx_inventory_value_t *inventory_value)
{
	zbx_free(inventory_value->value);
	zbx_free(inventory_value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store str/text/log value             *
 *                                                                            *
 * Parameters: history     - [IN] the history data                            *
 *             history_num - [IN] the number of values in history data        *
 *                                                                            *
 ******************************************************************************/
static void	dc_history_clean_value(ZBX_DC_HISTORY *history)
{
	if (ITEM_STATE_NOTSUPPORTED == history->state)
	{
		zbx_free(history->value.err);
		return;
	}

	if (0 != (ZBX_DC_FLAG_NOVALUE & history->flags))
		return;

	switch (history->value_type)
	{
		case ITEM_VALUE_TYPE_LOG:
			zbx_free(history->value.log->value);
			zbx_free(history->value.log->source);
			zbx_free(history->value.log);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_free(history->value.str);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store str/text/log values            *
 *                                                                            *
 * Parameters: history     - [IN] the history data                            *
 *             history_num - [IN] the number of values in history data        *
 *                                                                            *
 ******************************************************************************/
static void	hc_free_item_values(ZBX_DC_HISTORY *history, int history_num)
{
	int	i;

	for (i = 0; i < history_num; i++)
		dc_history_clean_value(&history[i]);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets history data to notsupported                                 *
 *                                                                            *
 * Parameters: history  - [IN] the history data                               *
 *             errmsg   - [IN] the error message                              *
 *                                                                            *
 * Comments: The error message is stored directly and freed with when history *
 *           data is cleaned.                                                 *
 *                                                                            *
 ******************************************************************************/
static void	dc_history_set_error(ZBX_DC_HISTORY *hdata, char *errmsg)
{
	dc_history_clean_value(hdata);
	hdata->value.err = errmsg;
	hdata->state = ITEM_STATE_NOTSUPPORTED;
	hdata->flags |= ZBX_DC_FLAG_UNDEF;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets history data value                                           *
 *                                                                            *
 * Parameters: hdata      - [IN/OUT] the history data                         *
 *             value_type - [IN] the item value type                          *
 *             value      - [IN] the value to set                             *
 *                                                                            *
 ******************************************************************************/
static void	dc_history_set_value(ZBX_DC_HISTORY *hdata, unsigned char value_type, zbx_variant_t *value)
{
	char	*errmsg = NULL;

	if (FAIL == zbx_variant_to_value_type(value, value_type, CONFIG_DOUBLE_PRECISION, &errmsg))
	{
		dc_history_set_error(hdata, errmsg);
		return;
	}

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			dc_history_clean_value(hdata);
			hdata->value.dbl = value->data.dbl;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			dc_history_clean_value(hdata);
			hdata->value.ui64 = value->data.ui64;
			break;
		case ITEM_VALUE_TYPE_STR:
			dc_history_clean_value(hdata);
			hdata->value.str = value->data.str;
			hdata->value.str[zbx_db_strlen_n(hdata->value.str, HISTORY_STR_VALUE_LEN)] = '\0';
			break;
		case ITEM_VALUE_TYPE_TEXT:
			dc_history_clean_value(hdata);
			hdata->value.str = value->data.str;
			hdata->value.str[zbx_db_strlen_n(hdata->value.str, HISTORY_TEXT_VALUE_LEN)] = '\0';
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (ITEM_VALUE_TYPE_LOG != hdata->value_type)
			{
				dc_history_clean_value(hdata);
				hdata->value.log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));
				memset(hdata->value.log, 0, sizeof(zbx_log_value_t));
			}
			hdata->value.log->value = value->data.str;
			hdata->value.str[zbx_db_strlen_n(hdata->value.str, HISTORY_LOG_VALUE_LEN)] = '\0';
	}

	hdata->value_type = value_type;
	zbx_variant_set_none(value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: normalize item value by performing truncation of long text        *
 *          values and changes value format according to the item value type  *
 *                                                                            *
 * Parameters: item          - [IN] the item                                  *
 *             hdata         - [IN/OUT] the historical data to process        *
 *                                                                            *
 ******************************************************************************/
static void	normalize_item_value(const DC_ITEM *item, ZBX_DC_HISTORY *hdata)
{
	char		*logvalue;
	zbx_variant_t	value_var;

	if (0 != (hdata->flags & ZBX_DC_FLAG_NOVALUE))
		return;

	if (ITEM_STATE_NOTSUPPORTED == hdata->state)
		return;

	if (0 == (hdata->flags & ZBX_DC_FLAG_NOHISTORY))
		hdata->ttl = item->history_sec;

	if (item->value_type == hdata->value_type)
	{
		/* truncate text based values if necessary */
		switch (hdata->value_type)
		{
			case ITEM_VALUE_TYPE_STR:
				hdata->value.str[zbx_db_strlen_n(hdata->value.str, HISTORY_STR_VALUE_LEN)] = '\0';
				break;
			case ITEM_VALUE_TYPE_TEXT:
				hdata->value.str[zbx_db_strlen_n(hdata->value.str, HISTORY_TEXT_VALUE_LEN)] = '\0';
				break;
			case ITEM_VALUE_TYPE_LOG:
				logvalue = hdata->value.log->value;
				logvalue[zbx_db_strlen_n(logvalue, HISTORY_LOG_VALUE_LEN)] = '\0';
				break;
			case ITEM_VALUE_TYPE_FLOAT:
				if (FAIL == zbx_validate_value_dbl(hdata->value.dbl, CONFIG_DOUBLE_PRECISION))
				{
					char	buffer[ZBX_MAX_DOUBLE_LEN + 1];

					dc_history_set_error(hdata, zbx_dsprintf(NULL,
							"Value %s is too small or too large.",
							zbx_print_double(buffer, sizeof(buffer), hdata->value.dbl)));
				}
				break;
		}
		return;
	}

	switch (hdata->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_variant_set_dbl(&value_var, hdata->value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_variant_set_ui64(&value_var, hdata->value.ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_variant_set_str(&value_var, hdata->value.str);
			hdata->value.str = NULL;
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_variant_set_str(&value_var, hdata->value.log->value);
			hdata->value.log->value = NULL;
			break;
	}

	dc_history_set_value(hdata, item->value_type, &value_var);
	zbx_variant_clear(&value_var);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates what item fields must be updated                       *
 *                                                                            *
 * Parameters: item      - [IN] the item                                      *
 *             h         - [IN] the historical data to process                *
 *                                                                            *
 * Return value: The update data. This data must be freed by the caller.      *
 *                                                                            *
 * Comments: Will generate internal events when item state switches.          *
 *                                                                            *
 ******************************************************************************/
static zbx_item_diff_t	*calculate_item_update(DC_ITEM *item, const ZBX_DC_HISTORY *h)
{
	zbx_uint64_t	flags = 0;
	const char	*item_error = NULL;
	zbx_item_diff_t	*diff;

	if (0 != (ZBX_DC_FLAG_META & h->flags))
	{
		if (item->lastlogsize != h->lastlogsize)
			flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE;

		if (item->mtime != h->mtime)
			flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME;
	}

	if (h->state != item->state)
	{
		flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE;

		if (ITEM_STATE_NOTSUPPORTED == h->state)
		{
			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" became not supported: %s",
					item->host.host, item->key_orig, h->value.str);

			zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, item->itemid, &h->ts, h->state, NULL,
					NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL, NULL, h->value.err);

			if (0 != strcmp(ZBX_NULL2EMPTY_STR(item->error), h->value.err))
				item_error = h->value.err;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" became supported",
					item->host.host, item->key_orig);

			/* we know it's EVENT_OBJECT_ITEM because LLDRULE that becomes */
			/* supported is handled in lld_process_discovery_rule()        */
			zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, item->itemid, &h->ts, h->state,
					NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL, NULL, NULL);

			item_error = "";
		}
	}
	else if (ITEM_STATE_NOTSUPPORTED == h->state && 0 != strcmp(ZBX_NULL2EMPTY_STR(item->error), h->value.err))
	{
		zabbix_log(LOG_LEVEL_WARNING, "error reason for \"%s:%s\" changed: %s", item->host.host,
				item->key_orig, h->value.err);

		item_error = h->value.err;
	}

	if (NULL != item_error)
		flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;

	if (0 == flags)
		return NULL;

	diff = (zbx_item_diff_t *)zbx_malloc(NULL, sizeof(zbx_item_diff_t));
	diff->itemid = item->itemid;
	diff->flags = flags;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE & flags))
		diff->lastlogsize = h->lastlogsize;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME & flags))
		diff->mtime = h->mtime;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE & flags))
	{
		diff->state = h->state;
		item->state = h->state;
	}

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR & flags))
		diff->error = item_error;

	return diff;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update item data and inventory in database                        *
 *                                                                            *
 * Parameters: item_diff        - item changes                                *
 *             inventory_values - inventory values                            *
 *                                                                            *
 ******************************************************************************/
static void	DBmass_update_items(const zbx_vector_ptr_t *item_diff, const zbx_vector_ptr_t *inventory_values)
{
	size_t	sql_offset = 0;
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < item_diff->values_num; i++)
	{
		zbx_item_diff_t	*diff;

		diff = (zbx_item_diff_t *)item_diff->values[i];
		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_DB & diff->flags))
			break;
	}

	if (i != item_diff->values_num || 0 != inventory_values->values_num)
	{
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (i != item_diff->values_num)
		{
			zbx_db_save_item_changes(&sql, &sql_alloc, &sql_offset, item_diff,
					ZBX_FLAGS_ITEM_DIFF_UPDATE_DB);
		}

		if (0 != inventory_values->values_num)
			DCadd_update_inventory_sql(&sql_offset, inventory_values);

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (sql_offset > 16)	/* In ORACLE always present begin..end; */
			DBexecute("%s", sql);

		DCconfig_update_inventory_values(inventory_values);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare itemdiff after receiving new values                       *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *             item_diff   - vector to store prepared diff                    *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_proxy_prepare_itemdiff(ZBX_DC_HISTORY *history, int history_num, zbx_vector_ptr_t *item_diff)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_reserve(item_diff, history_num);

	for (i = 0; i < history_num; i++)
	{
		zbx_item_diff_t	*diff = (zbx_item_diff_t *)zbx_malloc(NULL, sizeof(zbx_item_diff_t));

		diff->itemid = history[i].itemid;
		diff->state = history[i].state;
		diff->flags = ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE;

		if (0 != (ZBX_DC_FLAG_META & history[i].flags))
		{
			diff->lastlogsize = history[i].lastlogsize;
			diff->mtime = history[i].mtime;
			diff->flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE | ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME;
		}

		zbx_vector_ptr_append(item_diff, diff);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update items info after new value is received                     *
 *                                                                            *
 * Parameters: item_diff - diff of items to be updated                        *
 *                                                                            *
 ******************************************************************************/
static void	DBmass_proxy_update_items(zbx_vector_ptr_t *item_diff)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != item_diff->values_num)
	{
		size_t	sql_offset = 0;

		zbx_vector_ptr_sort(item_diff, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		zbx_db_save_item_changes(&sql, &sql_alloc, &sql_offset, item_diff,
				ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE | ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME);

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (sql_offset > 16)	/* In ORACLE always present begin..end; */
			DBexecute("%s", sql);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

typedef struct
{
	char	*table_name;
	char	*sql;
	size_t	sql_alloc, sql_offset;
}
zbx_history_dupl_select_t;

static int	history_value_compare_func(const void *d1, const void *d2)
{
	const ZBX_DC_HISTORY	*i1 = *(const ZBX_DC_HISTORY **)d1;
	const ZBX_DC_HISTORY	*i2 = *(const ZBX_DC_HISTORY **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->itemid, i2->itemid);
	ZBX_RETURN_IF_NOT_EQUAL(i1->value_type, i2->value_type);
	ZBX_RETURN_IF_NOT_EQUAL(i1->ts.sec, i2->ts.sec);
	ZBX_RETURN_IF_NOT_EQUAL(i1->ts.ns, i2->ts.ns);

	return 0;
}

static void	vc_flag_duplicates(zbx_vector_ptr_t *history_index, zbx_vector_ptr_t *duplicates)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < duplicates->values_num; i++)
	{
		int	idx_cached;

		if (FAIL != (idx_cached = zbx_vector_ptr_bsearch(history_index, duplicates->values[i],
				history_value_compare_func)))
		{
			ZBX_DC_HISTORY	*cached_value = (ZBX_DC_HISTORY *)history_index->values[idx_cached];

			dc_history_clean_value(cached_value);
			cached_value->flags |= ZBX_DC_FLAGS_NOT_FOR_HISTORY;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	db_fetch_duplicates(zbx_history_dupl_select_t *query, unsigned char value_type,
		zbx_vector_ptr_t *duplicates)
{
	DB_RESULT	result;
	DB_ROW		row;

	if (NULL == query->sql)
		return;

	result = DBselect("%s", query->sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DC_HISTORY	*d = (ZBX_DC_HISTORY *)zbx_malloc(NULL, sizeof(ZBX_DC_HISTORY));

		ZBX_STR2UINT64(d->itemid, row[0]);
		d->ts.sec = atoi(row[1]);
		d->ts.ns = atoi(row[2]);

		d->value_type = value_type;

		zbx_vector_ptr_append(duplicates, d);
	}
	DBfree_result(result);

	zbx_free(query->sql);
}

static void	remove_history_duplicates(zbx_vector_ptr_t *history)
{
	int				i;
	zbx_history_dupl_select_t	select_flt = {.table_name = "history"},
					select_uint = {.table_name = "history_uint"},
					select_str = {.table_name = "history_str"},
					select_log = {.table_name = "history_log"},
					select_text = {.table_name = "history_text"};
	zbx_vector_ptr_t		duplicates, history_index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&duplicates);
	zbx_vector_ptr_create(&history_index);

	zbx_vector_ptr_append_array(&history_index, history->values, history->values_num);
	zbx_vector_ptr_sort(&history_index, history_value_compare_func);

	for (i = 0; i < history_index.values_num; i++)
	{
		ZBX_DC_HISTORY			*h = history_index.values[i];
		zbx_history_dupl_select_t	*select_ptr;
		char				*separator = " or";

		if (h->value_type == ITEM_VALUE_TYPE_FLOAT)
			select_ptr = &select_flt;
		else if (h->value_type == ITEM_VALUE_TYPE_UINT64)
			select_ptr = &select_uint;
		else if (h->value_type == ITEM_VALUE_TYPE_STR)
			select_ptr = &select_str;
		else if (h->value_type == ITEM_VALUE_TYPE_LOG)
			select_ptr = &select_log;
		else if (h->value_type == ITEM_VALUE_TYPE_TEXT)
			select_ptr = &select_text;
		else
			continue;

		if (NULL == select_ptr->sql)
		{
			zbx_snprintf_alloc(&select_ptr->sql, &select_ptr->sql_alloc, &select_ptr->sql_offset,
					"select itemid,clock,ns"
					" from %s"
					" where", select_ptr->table_name);
			separator = "";
		}

		zbx_snprintf_alloc(&select_ptr->sql, &select_ptr->sql_alloc, &select_ptr->sql_offset,
				"%s (itemid=" ZBX_FS_UI64 " and clock=%d and ns=%d)", separator , h->itemid,
				h->ts.sec, h->ts.ns);
	}

	db_fetch_duplicates(&select_flt, ITEM_VALUE_TYPE_FLOAT, &duplicates);
	db_fetch_duplicates(&select_uint, ITEM_VALUE_TYPE_UINT64, &duplicates);
	db_fetch_duplicates(&select_str, ITEM_VALUE_TYPE_STR, &duplicates);
	db_fetch_duplicates(&select_log, ITEM_VALUE_TYPE_LOG, &duplicates);
	db_fetch_duplicates(&select_text, ITEM_VALUE_TYPE_TEXT, &duplicates);

	vc_flag_duplicates(&history_index, &duplicates);

	zbx_vector_ptr_clear_ext(&duplicates, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_destroy(&duplicates);
	zbx_vector_ptr_destroy(&history_index);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	add_history(ZBX_DC_HISTORY *history, int history_num, zbx_vector_ptr_t *history_values, int *ret_flush)
{
	int	i, ret = SUCCEED;

	for (i = 0; i < history_num; i++)
	{
		ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAGS_NOT_FOR_HISTORY & h->flags))
			continue;

		zbx_vector_ptr_append(history_values, h);
	}

	if (0 != history_values->values_num)
		ret = zbx_vc_add_values(history_values, ret_flush);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: inserting new history data after new value is received            *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 ******************************************************************************/
static int	DBmass_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	int			ret, ret_flush = FLUSH_SUCCEED, num;
	zbx_vector_ptr_t	history_values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&history_values);
	zbx_vector_ptr_reserve(&history_values, history_num);

	if (FAIL == (ret = add_history(history, history_num, &history_values, &ret_flush)) &&
			FLUSH_DUPL_REJECTED == ret_flush)
	{
		num = history_values.values_num;
		remove_history_duplicates(&history_values);
		zbx_vector_ptr_clear(&history_values);

		if (SUCCEED == (ret = add_history(history, history_num, &history_values, &ret_flush)))
			zabbix_log(LOG_LEVEL_WARNING, "skipped %d duplicates", num - history_values.values_num);
	}

	zbx_vector_ptr_destroy(&history_values);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type other than       *
 *          ITEM_VALUE_TYPE_LOG not containing meta information in result     *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history(ZBX_DC_HISTORY *history, int history_num)
{
	int		i, now;
	unsigned int	flags;
	char		buffer[64], *pvalue;
	zbx_db_insert_t	db_insert;

	now = (int)time(NULL);
	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "value", "flags", "write_clock",
			NULL);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (h->flags & ZBX_DC_FLAG_UNDEF))
			continue;

		if (0 != (h->flags & ZBX_DC_FLAG_META))
			continue;

		if (ITEM_STATE_NOTSUPPORTED == h->state)
			continue;

		if (0 == (h->flags & ZBX_DC_FLAG_NOVALUE))
		{
			switch (h->value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
					zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_DBL64, h->value.dbl);
					break;
				case ITEM_VALUE_TYPE_UINT64:
					zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_UI64, h->value.ui64);
					break;
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
					pvalue = h->value.str;
					break;
				case ITEM_VALUE_TYPE_LOG:
					continue;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
			}
			flags = 0;
		}
		else
		{
			flags = PROXY_HISTORY_FLAG_NOVALUE;
			pvalue = (char *)"";
		}

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, pvalue, flags, now);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type other than       *
 *          ITEM_VALUE_TYPE_LOG containing meta information in result         *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_meta(ZBX_DC_HISTORY *history, int history_num)
{
	int		i, now;
	char		buffer[64], *pvalue;
	zbx_db_insert_t	db_insert;

	now = (int)time(NULL);
	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "value", "lastlogsize", "mtime",
			"flags", "write_clock", NULL);

	for (i = 0; i < history_num; i++)
	{
		unsigned int		flags = PROXY_HISTORY_FLAG_META;
		const ZBX_DC_HISTORY	*h = &history[i];

		if (ITEM_STATE_NOTSUPPORTED == h->state)
			continue;

		if (0 != (h->flags & ZBX_DC_FLAG_UNDEF))
			continue;

		if (0 == (h->flags & ZBX_DC_FLAG_META))
			continue;

		if (ITEM_VALUE_TYPE_LOG == h->value_type)
			continue;

		if (0 == (h->flags & ZBX_DC_FLAG_NOVALUE))
		{
			switch (h->value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
					zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_DBL64, h->value.dbl);
					break;
				case ITEM_VALUE_TYPE_UINT64:
					zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_UI64, h->value.ui64);
					break;
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
					pvalue = h->value.str;
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
			}
		}
		else
		{
			flags |= PROXY_HISTORY_FLAG_NOVALUE;
			pvalue = (char *)"";
		}

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, pvalue, h->lastlogsize, h->mtime,
				flags, now);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type                  *
 *          ITEM_VALUE_TYPE_LOG                                               *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_log(ZBX_DC_HISTORY *history, int history_num)
{
	int		i, now;
	zbx_db_insert_t	db_insert;

	now = (int)time(NULL);

	/* see hc_copy_history_data() for fields that might be uninitialized and need special handling here */
	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "timestamp", "source", "severity",
			"value", "logeventid", "lastlogsize", "mtime", "flags", "write_clock", NULL);

	for (i = 0; i < history_num; i++)
	{
		unsigned int		flags;
		zbx_uint64_t		lastlogsize;
		int			mtime;
		const ZBX_DC_HISTORY	*h = &history[i];

		if (ITEM_STATE_NOTSUPPORTED == h->state)
			continue;

		if (ITEM_VALUE_TYPE_LOG != h->value_type)
			continue;

		if (0 == (h->flags & ZBX_DC_FLAG_NOVALUE))
		{
			zbx_log_value_t *log = h->value.log;

			if (0 != (h->flags & ZBX_DC_FLAG_META))
			{
				flags = PROXY_HISTORY_FLAG_META;
				lastlogsize = h->lastlogsize;
				mtime = h->mtime;
			}
			else
			{
				flags = 0;
				lastlogsize = 0;
				mtime = 0;
			}

			zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, log->timestamp,
					ZBX_NULL2EMPTY_STR(log->source), log->severity, log->value, log->logeventid,
					lastlogsize, mtime, flags, now);
		}
		else
		{
			/* sent to server only if not 0, see proxy_get_history_data() */
			const int	unset_if_novalue = 0;

			flags = PROXY_HISTORY_FLAG_META | PROXY_HISTORY_FLAG_NOVALUE;

			zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, unset_if_novalue, "",
					unset_if_novalue, "", unset_if_novalue, h->lastlogsize, h->mtime, flags, now);
		}
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_notsupported(ZBX_DC_HISTORY *history, int history_num)
{
	int		i, now;
	zbx_db_insert_t	db_insert;

	now = (int)time(NULL);
	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "value", "state", "write_clock",
			NULL);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (ITEM_STATE_NOTSUPPORTED != h->state)
			continue;

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, ZBX_NULL2EMPTY_STR(h->value.err),
				(int)h->state, now);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Purpose: inserting new history data after new value is received            *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 ******************************************************************************/
static void	DBmass_proxy_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	int	i, h_num = 0, h_meta_num = 0, hlog_num = 0, notsupported_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (ITEM_STATE_NOTSUPPORTED == h->state)
		{
			notsupported_num++;
			continue;
		}

		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_LOG:
				hlog_num++;
				break;
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				if (0 != (h->flags & ZBX_DC_FLAG_META))
					h_meta_num++;
				else
					h_num++;
				break;
			case ITEM_VALUE_TYPE_NONE:
				h_num++;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	if (0 != h_num)
		dc_add_proxy_history(history, history_num);

	if (0 != h_meta_num)
		dc_add_proxy_history_meta(history, history_num);

	if (0 != hlog_num)
		dc_add_proxy_history_log(history, history_num);

	if (0 != notsupported_num)
		dc_add_proxy_history_notsupported(history, history_num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare history data using items from configuration cache and     *
 *          generate item changes to be applied and host inventory values to  *
 *          be added                                                          *
 *                                                                            *
 * Parameters: history             - [IN/OUT] array of history data           *
 *             itemids             - [IN] the item identifiers                *
 *                                        (used for item lookup)              *
 *             items               - [IN] the items                           *
 *             errcodes            - [IN] item error codes                    *
 *             history_num         - [IN] number of history structures        *
 *             item_diff           - [OUT] the changes in item data           *
 *             inventory_values    - [OUT] the inventory values to add        *
 *             compression_age     - [IN] history compression age             *
 *             proxy_subscribtions - [IN] history compression age             *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_prepare_history(ZBX_DC_HISTORY *history, DC_ITEM *items, const int *errcodes, int history_num,
		zbx_vector_ptr_t *item_diff, zbx_vector_ptr_t *inventory_values, int compression_age,
		zbx_vector_uint64_pair_t *proxy_subscribtions)
{
	static time_t	last_history_discard = 0;
	time_t		now;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d", __func__, history_num);

	now = time(NULL);

	for (i = 0; i < history_num; i++)
	{
		ZBX_DC_HISTORY	*h = &history[i];
		DC_ITEM		*item;
		zbx_item_diff_t	*diff;

		/* discard history items that are older than compression age */
		if (0 != compression_age && h->ts.sec < compression_age)
		{
			if (SEC_PER_HOUR < (now - last_history_discard)) /* log once per hour */
			{
				zabbix_log(LOG_LEVEL_TRACE, "discarding history that is pointing to"
							" compressed history period");
				last_history_discard = now;
			}

			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		if (SUCCEED != errcodes[i])
		{
			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		item = &items[i];

		if (ITEM_STATUS_ACTIVE != item->status || HOST_STATUS_MONITORED != item->host.status)
		{
			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		if (0 == item->history)
		{
			h->flags |= ZBX_DC_FLAG_NOHISTORY;
		}
		else if (now - h->ts.sec > item->history_sec)
		{
			h->flags |= ZBX_DC_FLAG_NOHISTORY;
			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" value timestamp \"%s %s\" is outside history "
					"storage period", item->host.host, item->key_orig,
					zbx_date2str(h->ts.sec, NULL), zbx_time2str(h->ts.sec, NULL));
		}

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type || ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			if (0 == item->trends)
			{
				h->flags |= ZBX_DC_FLAG_NOTRENDS;
			}
			else if (now - h->ts.sec > item->trends_sec)
			{
				h->flags |= ZBX_DC_FLAG_NOTRENDS;
				zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" value timestamp \"%s %s\" is outside "
						"trends storage period", item->host.host, item->key_orig,
						zbx_date2str(h->ts.sec, NULL), zbx_time2str(h->ts.sec, NULL));
			}
		}
		else
			h->flags |= ZBX_DC_FLAG_NOTRENDS;

		normalize_item_value(item, h);

		/* calculate item update and update already retrieved item status for trigger calculation */
		if (NULL != (diff = calculate_item_update(item, h)))
			zbx_vector_ptr_append(item_diff, diff);

		DCinventory_value_add(inventory_values, item, h);

		if (0 != item->host.proxy_hostid && FAIL == is_item_processed_by_server(item->type, item->key_orig))
		{
			zbx_uint64_pair_t	p = {item->host.proxy_hostid, h->ts.sec};

			zbx_vector_uint64_pair_append(proxy_subscribtions, p);
		}
	}

	zbx_vector_ptr_sort(inventory_values, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	zbx_vector_ptr_sort(item_diff, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare history data to share them with loadable modules, sort    *
 *          data by type skipping low-level discovery data, meta information  *
 *          updates and notsupported items                                    *
 *                                                                            *
 * Parameters: history            - [IN] array of history data                *
 *             history_num        - [IN] number of history structures         *
 *             history_<type>     - [OUT] array of historical data of a       *
 *                                  specific data type                        *
 *             history_<type>_num - [OUT] number of values of a specific      *
 *                                  data type                                 *
 *                                                                            *
 ******************************************************************************/
static void	DCmodule_prepare_history(ZBX_DC_HISTORY *history, int history_num, ZBX_HISTORY_FLOAT *history_float,
		int *history_float_num, ZBX_HISTORY_INTEGER *history_integer, int *history_integer_num,
		ZBX_HISTORY_STRING *history_string, int *history_string_num, ZBX_HISTORY_TEXT *history_text,
		int *history_text_num, ZBX_HISTORY_LOG *history_log, int *history_log_num)
{
	ZBX_DC_HISTORY		*h;
	ZBX_HISTORY_FLOAT	*h_float;
	ZBX_HISTORY_INTEGER	*h_integer;
	ZBX_HISTORY_STRING	*h_string;
	ZBX_HISTORY_TEXT	*h_text;
	ZBX_HISTORY_LOG		*h_log;
	int			i;
	const zbx_log_value_t	*log;

	*history_float_num = 0;
	*history_integer_num = 0;
	*history_string_num = 0;
	*history_text_num = 0;
	*history_log_num = 0;

	for (i = 0; i < history_num; i++)
	{
		h = &history[i];

		if (0 != (ZBX_DC_FLAGS_NOT_FOR_MODULES & h->flags))
			continue;

		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				if (NULL == history_float_cbs)
					continue;

				h_float = &history_float[(*history_float_num)++];
				h_float->itemid = h->itemid;
				h_float->clock = h->ts.sec;
				h_float->ns = h->ts.ns;
				h_float->value = h->value.dbl;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				if (NULL == history_integer_cbs)
					continue;

				h_integer = &history_integer[(*history_integer_num)++];
				h_integer->itemid = h->itemid;
				h_integer->clock = h->ts.sec;
				h_integer->ns = h->ts.ns;
				h_integer->value = h->value.ui64;
				break;
			case ITEM_VALUE_TYPE_STR:
				if (NULL == history_string_cbs)
					continue;

				h_string = &history_string[(*history_string_num)++];
				h_string->itemid = h->itemid;
				h_string->clock = h->ts.sec;
				h_string->ns = h->ts.ns;
				h_string->value = h->value.str;
				break;
			case ITEM_VALUE_TYPE_TEXT:
				if (NULL == history_text_cbs)
					continue;

				h_text = &history_text[(*history_text_num)++];
				h_text->itemid = h->itemid;
				h_text->clock = h->ts.sec;
				h_text->ns = h->ts.ns;
				h_text->value = h->value.str;
				break;
			case ITEM_VALUE_TYPE_LOG:
				if (NULL == history_log_cbs)
					continue;

				log = h->value.log;
				h_log = &history_log[(*history_log_num)++];
				h_log->itemid = h->itemid;
				h_log->clock = h->ts.sec;
				h_log->ns = h->ts.ns;
				h_log->value = log->value;
				h_log->source = ZBX_NULL2EMPTY_STR(log->source);
				h_log->timestamp = log->timestamp;
				h_log->logeventid = log->logeventid;
				h_log->severity = log->severity;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
}

static void	DCmodule_sync_history(int history_float_num, int history_integer_num, int history_string_num,
		int history_text_num, int history_log_num, ZBX_HISTORY_FLOAT *history_float,
		ZBX_HISTORY_INTEGER *history_integer, ZBX_HISTORY_STRING *history_string,
		ZBX_HISTORY_TEXT *history_text, ZBX_HISTORY_LOG *history_log)
{
	if (0 != history_float_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing float history data with modules...");

		for (i = 0; NULL != history_float_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_float_cbs[i].module->name);
			history_float_cbs[i].history_float_cb(history_float, history_float_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d float values with modules", history_float_num);
	}

	if (0 != history_integer_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing integer history data with modules...");

		for (i = 0; NULL != history_integer_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_integer_cbs[i].module->name);
			history_integer_cbs[i].history_integer_cb(history_integer, history_integer_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d integer values with modules", history_integer_num);
	}

	if (0 != history_string_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing string history data with modules...");

		for (i = 0; NULL != history_string_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_string_cbs[i].module->name);
			history_string_cbs[i].history_string_cb(history_string, history_string_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d string values with modules", history_string_num);
	}

	if (0 != history_text_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing text history data with modules...");

		for (i = 0; NULL != history_text_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_text_cbs[i].module->name);
			history_text_cbs[i].history_text_cb(history_text, history_text_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d text values with modules", history_text_num);
	}

	if (0 != history_log_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_DEBUG, "syncing log history data with modules...");

		for (i = 0; NULL != history_log_cbs[i].module; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "... module \"%s\"", history_log_cbs[i].module->name);
			history_log_cbs[i].history_log_cb(history_log, history_log_num);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "synced %d log values with modules", history_log_num);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepares history update by checking which values must be stored   *
 *                                                                            *
 * Parameters: history     - [IN/OUT] the history values                      *
 *             history_num - [IN] the number of history values                *
 *                                                                            *
 ******************************************************************************/
static void	proxy_prepare_history(ZBX_DC_HISTORY *history, int history_num)
{
	int			i, *errcodes;
	DC_ITEM			*items;
	zbx_vector_uint64_t	itemids;

	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_reserve(&itemids, history_num);

	for (i = 0; i < history_num; i++)
		zbx_vector_uint64_append(&itemids, history[i].itemid);

	items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * (size_t)history_num);
	errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)history_num);

	DCconfig_get_items_by_itemids(items, itemids.values, errcodes, itemids.values_num);

	for (i = 0; i < history_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		/* store items with enabled history  */
		if (0 != items[i].history)
			continue;

		/* store numeric items to handle data conversion errors on server and trends */
		if (ITEM_VALUE_TYPE_FLOAT == items[i].value_type || ITEM_VALUE_TYPE_UINT64 == items[i].value_type)
			continue;

		/* store discovery rules */
		if (0 != (items[i].flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		/* store errors or first value after an error */
		if (ITEM_STATE_NOTSUPPORTED == history[i].state || ITEM_STATE_NOTSUPPORTED == items[i].state)
			continue;

		/* store items linked to host inventory */
		if (0 != items[i].inventory_link)
			continue;

		dc_history_clean_value(history + i);

		/* all checks passed, item value must not be stored in proxy history/sent to server */
		history[i].flags |= ZBX_DC_FLAG_NOVALUE;
	}

	DCconfig_clean_items(items, errcodes, history_num);
	zbx_free(items);
	zbx_free(errcodes);
	zbx_vector_uint64_destroy(&itemids);
}

static void	sync_proxy_history(int *total_num, int *more)
{
	int			history_num, txn_rc;
	time_t			sync_start;
	zbx_vector_ptr_t	history_items;
	zbx_vector_ptr_t	item_diff;
	ZBX_DC_HISTORY		history[ZBX_HC_SYNC_MAX];

	zbx_vector_ptr_create(&history_items);
	zbx_vector_ptr_reserve(&history_items, ZBX_HC_SYNC_MAX);
	zbx_vector_ptr_create(&item_diff);

	sync_start = time(NULL);

	do
	{
		*more = ZBX_SYNC_DONE;

		LOCK_CACHE;

		hc_pop_items(&history_items);		/* select and take items out of history cache */
		history_num = history_items.values_num;

		UNLOCK_CACHE;

		if (0 == history_num)
			break;

		hc_get_item_values(history, &history_items);	/* copy item data from history cache */
		proxy_prepare_history(history, history_items.values_num);

		DCmass_proxy_prepare_itemdiff(history, history_num, &item_diff);

		do
		{
			DBbegin();

			DBmass_proxy_add_history(history, history_num);
			DBmass_proxy_update_items(&item_diff);
		}
		while (ZBX_DB_DOWN == (txn_rc = DBcommit()));

		LOCK_CACHE;

		hc_push_items(&history_items);	/* return items to history cache */

		if (ZBX_DB_FAIL != txn_rc)
		{
			if (0 != item_diff.values_num)
				DCconfig_items_apply_changes(&item_diff);

			cache->history_num -= history_num;

			if (0 != hc_queue_get_size())
				*more = ZBX_SYNC_MORE;

			UNLOCK_CACHE;

			*total_num += history_num;

			hc_free_item_values(history, history_num);
		}
		else
		{
			*more = ZBX_SYNC_MORE;
			UNLOCK_CACHE;
		}

		zbx_vector_ptr_clear(&history_items);
		zbx_vector_ptr_clear_ext(&item_diff, zbx_default_mem_free_func);

		/* Exit from sync loop if we have spent too much time here */
		/* unless we are doing full sync. This is done to allow    */
		/* syncer process to update their statistics.              */
	}
	while (ZBX_SYNC_MORE == *more && ZBX_HC_SYNC_TIME_MAX >= time(NULL) - sync_start);

	zbx_vector_ptr_destroy(&item_diff);
	zbx_vector_ptr_destroy(&history_items);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush history cache to database, process triggers of flushed      *
 *          and timer triggers from timer queue                               *
 *                                                                            *
 * Parameters: sync_timeout - [IN] the timeout in seconds                     *
 *             values_num   - [IN/OUT] the number of synced values            *
 *             triggers_num - [IN/OUT] the number of processed timers         *
 *             more         - [OUT] a flag indicating the cache emptiness:    *
 *                               ZBX_SYNC_DONE - nothing to sync, go idle     *
 *                               ZBX_SYNC_MORE - more data to sync            *
 *                                                                            *
 * Comments: This function loops syncing history values by 1k batches and     *
 *           processing timer triggers by batches of 500 triggers.            *
 *           Unless full sync is being done the loop is aborted if either     *
 *           timeout has passed or there are no more data to process.         *
 *           The last is assumed when the following is true:                  *
 *            a) history cache is empty or less than 10% of batch values were *
 *               processed (the other items were locked by triggers)          *
 *            b) less than 500 (full batch) timer triggers were processed     *
 *                                                                            *
 ******************************************************************************/
static void	sync_server_history(int *values_num, int *triggers_num, int *more)
{
	static ZBX_HISTORY_FLOAT	*history_float;
	static ZBX_HISTORY_INTEGER	*history_integer;
	static ZBX_HISTORY_STRING	*history_string;
	static ZBX_HISTORY_TEXT		*history_text;
	static ZBX_HISTORY_LOG		*history_log;
	static int			module_enabled = FAIL;
	int				i, history_num, history_float_num, history_integer_num, history_string_num,
					history_text_num, history_log_num, txn_error, compression_age;
	unsigned int			item_retrieve_mode;
	time_t				sync_start;
	zbx_vector_uint64_t		triggerids ;
	zbx_vector_ptr_t		history_items, trigger_diff, item_diff, inventory_values, trigger_timers,
					trigger_order;
	zbx_vector_uint64_pair_t	trends_diff, proxy_subscribtions;
	ZBX_DC_HISTORY			history[ZBX_HC_SYNC_MAX];
	zbx_uint64_t			trigger_itemids[ZBX_HC_SYNC_MAX];
	zbx_timespec_t			trigger_timespecs[ZBX_HC_SYNC_MAX];
	DC_ITEM				*items = NULL;
	int				*errcodes = NULL;
	zbx_vector_uint64_t		itemids;
	zbx_hashset_t			trigger_info;

	item_retrieve_mode = NULL == CONFIG_EXPORT_DIR ? ZBX_ITEM_GET_SYNC : ZBX_ITEM_GET_SYNC_EXPORT;

	if (NULL == history_float && NULL != history_float_cbs)
	{
		module_enabled = SUCCEED;
		history_float = (ZBX_HISTORY_FLOAT *)zbx_malloc(history_float,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_FLOAT));
	}

	if (NULL == history_integer && NULL != history_integer_cbs)
	{
		module_enabled = SUCCEED;
		history_integer = (ZBX_HISTORY_INTEGER *)zbx_malloc(history_integer,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_INTEGER));
	}

	if (NULL == history_string && NULL != history_string_cbs)
	{
		module_enabled = SUCCEED;
		history_string = (ZBX_HISTORY_STRING *)zbx_malloc(history_string,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_STRING));
	}

	if (NULL == history_text && NULL != history_text_cbs)
	{
		module_enabled = SUCCEED;
		history_text = (ZBX_HISTORY_TEXT *)zbx_malloc(history_text,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_TEXT));
	}

	if (NULL == history_log && NULL != history_log_cbs)
	{
		module_enabled = SUCCEED;
		history_log = (ZBX_HISTORY_LOG *)zbx_malloc(history_log,
				ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_LOG));
	}

	compression_age = hc_get_history_compression_age();

	zbx_vector_ptr_create(&inventory_values);
	zbx_vector_ptr_create(&item_diff);
	zbx_vector_ptr_create(&trigger_diff);
	zbx_vector_uint64_pair_create(&trends_diff);
	zbx_vector_uint64_pair_create(&proxy_subscribtions);

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, ZBX_HC_SYNC_MAX);

	zbx_vector_ptr_create(&trigger_timers);
	zbx_vector_ptr_reserve(&trigger_timers, ZBX_HC_TIMER_MAX);

	zbx_vector_ptr_create(&history_items);
	zbx_vector_ptr_reserve(&history_items, ZBX_HC_SYNC_MAX);

	zbx_vector_ptr_create(&trigger_order);
	zbx_hashset_create(&trigger_info, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_create(&itemids);

	sync_start = time(NULL);

	do
	{
		int			trends_num = 0, timers_num = 0, ret = SUCCEED;
		ZBX_DC_TREND		*trends = NULL;

		*more = ZBX_SYNC_DONE;

		LOCK_CACHE;
		hc_pop_items(&history_items);		/* select and take items out of history cache */
		UNLOCK_CACHE;

		if (0 != history_items.values_num)
		{
			if (0 == (history_num = DCconfig_lock_triggers_by_history_items(&history_items, &triggerids)))
			{
				LOCK_CACHE;
				hc_push_items(&history_items);
				UNLOCK_CACHE;
				zbx_vector_ptr_clear(&history_items);
			}
		}
		else
			history_num = 0;

		if (0 != history_num)
		{
			zbx_vector_ptr_sort(&history_items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			hc_get_item_values(history, &history_items);	/* copy item data from history cache */

			if (NULL == items)
				items = (DC_ITEM *)zbx_calloc(NULL, 1, sizeof(DC_ITEM) * (size_t)ZBX_HC_SYNC_MAX);

			if (NULL == errcodes)
				errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)ZBX_HC_SYNC_MAX);

			zbx_vector_uint64_reserve(&itemids, history_num);

			for (i = 0; i < history_num; i++)
				zbx_vector_uint64_append(&itemids, history[i].itemid);

			DCconfig_get_items_by_itemids_partial(items, itemids.values, errcodes, history_num,
					item_retrieve_mode);

			DCmass_prepare_history(history, items, errcodes, history_num, &item_diff,
					&inventory_values, compression_age, &proxy_subscribtions);

			if (FAIL != (ret = DBmass_add_history(history, history_num)))
			{
				DCconfig_items_apply_changes(&item_diff);
				DCmass_update_trends(history, history_num, &trends, &trends_num, compression_age);

				if (0 != trends_num)
					zbx_tfc_invalidate_trends(trends, trends_num);

				do
				{
					DBbegin();

					DBmass_update_items(&item_diff, &inventory_values);
					DBmass_update_trends(trends, trends_num, &trends_diff);

					/* process internal events generated by DCmass_prepare_history() */
					zbx_process_events(NULL, NULL);

					if (ZBX_DB_OK == (txn_error = DBcommit()))
						DCupdate_trends(&trends_diff);
					else
						zbx_reset_event_recovery();

					zbx_vector_uint64_pair_clear(&trends_diff);
				}
				while (ZBX_DB_DOWN == txn_error);
			}

			zbx_clean_events();

			zbx_vector_ptr_clear_ext(&inventory_values, (zbx_clean_func_t)DCinventory_value_free);
			zbx_vector_ptr_clear_ext(&item_diff, (zbx_clean_func_t)zbx_ptr_free);
		}

		if (FAIL != ret)
		{
			/* don't process trigger timers when server is shutting down */
			if (ZBX_IS_RUNNING())
			{
				zbx_dc_get_trigger_timers(&trigger_timers, time(NULL), ZBX_HC_TIMER_SOFT_MAX,
						ZBX_HC_TIMER_MAX);
			}

			timers_num = trigger_timers.values_num;

			if (ZBX_HC_TIMER_SOFT_MAX <= timers_num)
				*more = ZBX_SYNC_MORE;

			if (0 != history_num || 0 != timers_num)
			{
				for (i = 0; i < trigger_timers.values_num; i++)
				{
					zbx_trigger_timer_t	*timer = (zbx_trigger_timer_t *)trigger_timers.values[i];

					if (0 != timer->lock)
						zbx_vector_uint64_append(&triggerids, timer->triggerid);
				}

				do
				{
					DBbegin();

					recalculate_triggers(history, history_num, &itemids, items, errcodes,
							&trigger_timers, &trigger_diff, trigger_itemids,
							trigger_timespecs, &trigger_info, &trigger_order);

					/* process trigger events generated by recalculate_triggers() */
					zbx_process_events(&trigger_diff, &triggerids);
					if (0 != trigger_diff.values_num)
						zbx_db_save_trigger_changes(&trigger_diff);

					if (ZBX_DB_OK == (txn_error = DBcommit()))
						DCconfig_triggers_apply_changes(&trigger_diff);
					else
						zbx_clean_events();

					zbx_vector_ptr_clear_ext(&trigger_diff, (zbx_clean_func_t)zbx_trigger_diff_free);
				}
				while (ZBX_DB_DOWN == txn_error);

				if (ZBX_DB_OK == txn_error)
					zbx_events_update_itservices();
			}
		}

		if (0 != triggerids.values_num)
		{
			*triggers_num += triggerids.values_num;
			DCconfig_unlock_triggers(&triggerids);
			zbx_vector_uint64_clear(&triggerids);
		}

		if (0 != trigger_timers.values_num)
		{
			zbx_dc_reschedule_trigger_timers(&trigger_timers, time(NULL));
			zbx_vector_ptr_clear(&trigger_timers);
		}

		if (0 != proxy_subscribtions.values_num)
		{
			zbx_vector_uint64_pair_sort(&proxy_subscribtions, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_dc_proxy_update_nodata(&proxy_subscribtions);
			zbx_vector_uint64_pair_clear(&proxy_subscribtions);
		}

		if (0 != history_num)
		{
			LOCK_CACHE;
			hc_push_items(&history_items);	/* return items to history cache */
			cache->history_num -= history_num;

			if (0 != hc_queue_get_size())
			{
				/* Continue sync if enough of sync candidates were processed       */
				/* (meaning most of sync candidates are not locked by triggers).   */
				/* Otherwise better to wait a bit for other syncers to unlock      */
				/* items rather than trying and failing to sync locked items over  */
				/* and over again.                                                 */
				if (ZBX_HC_SYNC_MIN_PCNT <= history_num * 100 / history_items.values_num)
					*more = ZBX_SYNC_MORE;
			}

			UNLOCK_CACHE;

			*values_num += history_num;
		}

		if (FAIL != ret)
		{
			if (0 != history_num)
			{
				const ZBX_DC_HISTORY	*phistory = NULL;
				const ZBX_DC_TREND	*ptrends = NULL;
				int			history_num_loc = 0, trends_num_loc = 0;

				if (SUCCEED == module_enabled)
				{
					DCmodule_prepare_history(history, history_num, history_float, &history_float_num,
							history_integer, &history_integer_num, history_string,
							&history_string_num, history_text, &history_text_num, history_log,
							&history_log_num);

					DCmodule_sync_history(history_float_num, history_integer_num, history_string_num,
							history_text_num, history_log_num, history_float, history_integer,
							history_string, history_text, history_log);
				}

				if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_HISTORY))
				{
					phistory = history;
					history_num_loc = history_num;
				}

				if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS))
				{
					ptrends = trends;
					trends_num_loc = trends_num;
				}

				if (NULL != phistory || NULL != ptrends)
				{
					DCexport_history_and_trends(phistory, history_num_loc, &itemids, items,
							errcodes, ptrends, trends_num_loc);
				}
			}

			if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
				zbx_export_events();
		}

		if (0 != history_num || 0 != timers_num)
			zbx_clean_events();

		if (0 != history_num)
		{
			zbx_free(trends);
			DCconfig_clean_items(items, errcodes, history_num);

			zbx_vector_ptr_clear(&history_items);
			hc_free_item_values(history, history_num);
		}

		zbx_vector_uint64_clear(&itemids);

		/* Exit from sync loop if we have spent too much time here.       */
		/* This is done to allow syncer process to update its statistics. */
	}
	while (ZBX_SYNC_MORE == *more && ZBX_HC_SYNC_TIME_MAX >= time(NULL) - sync_start);

	zbx_free(items);
	zbx_free(errcodes);

	zbx_vector_ptr_destroy(&trigger_order);
	zbx_hashset_destroy(&trigger_info);

	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_ptr_destroy(&history_items);
	zbx_vector_ptr_destroy(&inventory_values);
	zbx_vector_ptr_destroy(&item_diff);
	zbx_vector_ptr_destroy(&trigger_diff);
	zbx_vector_uint64_pair_destroy(&trends_diff);
	zbx_vector_uint64_pair_destroy(&proxy_subscribtions);

	zbx_vector_ptr_destroy(&trigger_timers);
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: writes updates and new data from history cache to database        *
 *                                                                            *
 * Comments: This function is used to flush history cache at server/proxy     *
 *           exit.                                                            *
 *           Other processes are already terminated, so cache locking is      *
 *           unnecessary.                                                     *
 *                                                                            *
 ******************************************************************************/
static void	sync_history_cache_full(void)
{
	int			values_num = 0, triggers_num = 0, more;
	zbx_hashset_iter_t	iter;
	zbx_hc_item_t		*item;
	zbx_binary_heap_t	tmp_history_queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d", __func__, cache->history_num);

	/* History index cache might be full without any space left for queueing items from history index to  */
	/* history queue. The solution: replace the shared-memory history queue with heap-allocated one. Add  */
	/* all items from history index to the new history queue.                                             */
	/*                                                                                                    */
	/* Assertions that must be true.                                                                      */
	/*   * This is the main server or proxy process,                                                      */
	/*   * There are no other users of history index cache stored in shared memory. Other processes       */
	/*     should have quit by this point.                                                                */
	/*   * other parts of the program do not hold pointers to the elements of history queue that is       */
	/*     stored in the shared memory.                                                                   */

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		/* unlock all triggers before full sync so no items are locked by triggers */
		DCconfig_unlock_all_triggers();
	}

	tmp_history_queue = cache->history_queue;

	zbx_binary_heap_create(&cache->history_queue, hc_queue_elem_compare_func, ZBX_BINARY_HEAP_OPTION_EMPTY);
	zbx_hashset_iter_reset(&cache->history_items, &iter);

	/* add all items from history index to the new history queue */
	while (NULL != (item = (zbx_hc_item_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL != item->tail)
		{
			item->status = ZBX_HC_ITEM_STATUS_NORMAL;
			hc_queue_item(item);
		}
	}

	if (0 != hc_queue_get_size())
	{
		zabbix_log(LOG_LEVEL_WARNING, "syncing history data...");

		do
		{
			if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
				sync_server_history(&values_num, &triggers_num, &more);
			else
				sync_proxy_history(&values_num, &more);

			zabbix_log(LOG_LEVEL_WARNING, "syncing history data... " ZBX_FS_DBL "%%",
					(double)values_num / (cache->history_num + values_num) * 100);
		}
		while (0 != hc_queue_get_size());

		zabbix_log(LOG_LEVEL_WARNING, "syncing history data done");
	}

	zbx_binary_heap_destroy(&cache->history_queue);
	cache->history_queue = tmp_history_queue;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: log progress of syncing history data                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_log_sync_history_cache_progress(void)
{
	double		pcnt = -1.0;
	int		ts_last, ts_next, sec;

	LOCK_CACHE;

	if (INT_MAX == cache->history_progress_ts)
	{
		UNLOCK_CACHE;
		return;
	}

	ts_last = cache->history_progress_ts;
	sec = time(NULL);

	if (0 == cache->history_progress_ts)
	{
		cache->history_num_total = cache->history_num;
		cache->history_progress_ts = sec;
	}

	if (ZBX_HC_SYNC_TIME_MAX <= sec - cache->history_progress_ts || 0 == cache->history_num)
	{
		if (0 != cache->history_num_total)
			pcnt = 100 * (double)(cache->history_num_total - cache->history_num) / cache->history_num_total;

		cache->history_progress_ts = (0 == cache->history_num ? INT_MAX : sec);
	}

	ts_next = cache->history_progress_ts;

	UNLOCK_CACHE;

	if (0 == ts_last)
		zabbix_log(LOG_LEVEL_WARNING, "syncing history data in progress... ");

	if (-1.0 != pcnt)
		zabbix_log(LOG_LEVEL_WARNING, "syncing history data... " ZBX_FS_DBL "%%", pcnt);

	if (INT_MAX == ts_next)
		zabbix_log(LOG_LEVEL_WARNING, "syncing history data done");
}

/******************************************************************************
 *                                                                            *
 * Purpose: writes updates and new data from history cache to database        *
 *                                                                            *
 * Parameters: values_num - [OUT] the number of synced values                  *
 *             more      - [OUT] a flag indicating the cache emptiness:       *
 *                                ZBX_SYNC_DONE - nothing to sync, go idle    *
 *                                ZBX_SYNC_MORE - more data to sync           *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_history_cache(int *values_num, int *triggers_num, int *more)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d", __func__, cache->history_num);

	*values_num = 0;
	*triggers_num = 0;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		sync_server_history(values_num, triggers_num, more);
	else
		sync_proxy_history(values_num, more);
}

/******************************************************************************
 *                                                                            *
 * local history cache                                                        *
 *                                                                            *
 ******************************************************************************/
static void	dc_string_buffer_realloc(size_t len)
{
	if (string_values_alloc >= string_values_offset + len)
		return;

	do
	{
		string_values_alloc += ZBX_STRING_REALLOC_STEP;
	}
	while (string_values_alloc < string_values_offset + len);

	string_values = (char *)zbx_realloc(string_values, string_values_alloc);
}

static dc_item_value_t	*dc_local_get_history_slot(void)
{
	if (ZBX_MAX_VALUES_LOCAL == item_values_num)
		dc_flush_history();

	if (item_values_alloc == item_values_num)
	{
		item_values_alloc += ZBX_STRUCT_REALLOC_STEP;
		item_values = (dc_item_value_t *)zbx_realloc(item_values, item_values_alloc * sizeof(dc_item_value_t));
	}

	return &item_values[item_values_num++];
}

static void	dc_local_add_history_dbl(zbx_uint64_t itemid, unsigned char item_value_type, const zbx_timespec_t *ts,
		double value_orig, zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->item_value_type = item_value_type;
	item_value->value_type = ITEM_VALUE_TYPE_FLOAT;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = flags;

	if (0 != (item_value->flags & ZBX_DC_FLAG_META))
	{
		item_value->lastlogsize = lastlogsize;
		item_value->mtime = mtime;
	}

	if (0 == (item_value->flags & ZBX_DC_FLAG_NOVALUE))
		item_value->value.value_dbl = value_orig;
}

static void	dc_local_add_history_uint(zbx_uint64_t itemid, unsigned char item_value_type, const zbx_timespec_t *ts,
		zbx_uint64_t value_orig, zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->item_value_type = item_value_type;
	item_value->value_type = ITEM_VALUE_TYPE_UINT64;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = flags;

	if (0 != (item_value->flags & ZBX_DC_FLAG_META))
	{
		item_value->lastlogsize = lastlogsize;
		item_value->mtime = mtime;
	}

	if (0 == (item_value->flags & ZBX_DC_FLAG_NOVALUE))
		item_value->value.value_uint = value_orig;
}

static void	dc_local_add_history_text(zbx_uint64_t itemid, unsigned char item_value_type, const zbx_timespec_t *ts,
		const char *value_orig, zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->item_value_type = item_value_type;
	item_value->value_type = ITEM_VALUE_TYPE_TEXT;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = flags;

	if (0 != (item_value->flags & ZBX_DC_FLAG_META))
	{
		item_value->lastlogsize = lastlogsize;
		item_value->mtime = mtime;
	}

	if (0 == (item_value->flags & ZBX_DC_FLAG_NOVALUE))
	{
		item_value->value.value_str.len = zbx_db_strlen_n(value_orig, ZBX_HISTORY_VALUE_LEN) + 1;
		dc_string_buffer_realloc(item_value->value.value_str.len);

		item_value->value.value_str.pvalue = string_values_offset;
		memcpy(&string_values[string_values_offset], value_orig, item_value->value.value_str.len);
		string_values_offset += item_value->value.value_str.len;
	}
	else
		item_value->value.value_str.len = 0;
}

static void	dc_local_add_history_log(zbx_uint64_t itemid, unsigned char item_value_type, const zbx_timespec_t *ts,
		const zbx_log_t *log, zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->item_value_type = item_value_type;
	item_value->value_type = ITEM_VALUE_TYPE_LOG;
	item_value->state = ITEM_STATE_NORMAL;

	item_value->flags = flags;

	if (0 != (item_value->flags & ZBX_DC_FLAG_META))
	{
		item_value->lastlogsize = lastlogsize;
		item_value->mtime = mtime;
	}

	if (0 == (item_value->flags & ZBX_DC_FLAG_NOVALUE))
	{
		item_value->severity = log->severity;
		item_value->logeventid = log->logeventid;
		item_value->timestamp = log->timestamp;

		item_value->value.value_str.len = zbx_db_strlen_n(log->value, ZBX_HISTORY_VALUE_LEN) + 1;

		if (NULL != log->source && '\0' != *log->source)
			item_value->source.len = zbx_db_strlen_n(log->source, HISTORY_LOG_SOURCE_LEN) + 1;
		else
			item_value->source.len = 0;
	}
	else
	{
		item_value->value.value_str.len = 0;
		item_value->source.len = 0;
	}

	if (0 != item_value->value.value_str.len + item_value->source.len)
	{
		dc_string_buffer_realloc(item_value->value.value_str.len + item_value->source.len);

		if (0 != item_value->value.value_str.len)
		{
			item_value->value.value_str.pvalue = string_values_offset;
			memcpy(&string_values[string_values_offset], log->value, item_value->value.value_str.len);
			string_values_offset += item_value->value.value_str.len;
		}

		if (0 != item_value->source.len)
		{
			item_value->source.pvalue = string_values_offset;
			memcpy(&string_values[string_values_offset], log->source, item_value->source.len);
			string_values_offset += item_value->source.len;
		}
	}
}

static void	dc_local_add_history_notsupported(zbx_uint64_t itemid, const zbx_timespec_t *ts, const char *error,
		zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->state = ITEM_STATE_NOTSUPPORTED;
	item_value->flags = flags;

	if (0 != (item_value->flags & ZBX_DC_FLAG_META))
	{
		item_value->lastlogsize = lastlogsize;
		item_value->mtime = mtime;
	}

	item_value->value.value_str.len = zbx_db_strlen_n(error, ITEM_ERROR_LEN) + 1;
	dc_string_buffer_realloc(item_value->value.value_str.len);
	item_value->value.value_str.pvalue = string_values_offset;
	memcpy(&string_values[string_values_offset], error, item_value->value.value_str.len);
	string_values_offset += item_value->value.value_str.len;
}

static void	dc_local_add_history_lld(zbx_uint64_t itemid, const zbx_timespec_t *ts, const char *value_orig)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = ZBX_DC_FLAG_LLD;
	item_value->value.value_str.len = strlen(value_orig) + 1;

	dc_string_buffer_realloc(item_value->value.value_str.len);
	item_value->value.value_str.pvalue = string_values_offset;
	memcpy(&string_values[string_values_offset], value_orig, item_value->value.value_str.len);
	string_values_offset += item_value->value.value_str.len;
}

static void	dc_local_add_history_empty(zbx_uint64_t itemid, unsigned char item_value_type, const zbx_timespec_t *ts,
		unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->item_value_type = item_value_type;
	item_value->value_type = ITEM_VALUE_TYPE_NONE;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = flags;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new value to the cache                                        *
 *                                                                            *
 * Parameters:  itemid          - [IN] the itemid                             *
 *              item_value_type - [IN] the item value type                    *
 *              item_flags      - [IN] the item flags (e. g. lld rule)        *
 *              result          - [IN] agent result containing the value      *
 *                                to add                                      *
 *              ts              - [IN] the value timestamp                    *
 *              state           - [IN] the item state                         *
 *              error           - [IN] the error message in case item state   *
 *                                is ITEM_STATE_NOTSUPPORTED                  *
 *                                                                            *
 ******************************************************************************/
void	dc_add_history(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, const zbx_timespec_t *ts, unsigned char state, const char *error)
{
	unsigned char	value_flags;

	if (ITEM_STATE_NOTSUPPORTED == state)
	{
		zbx_uint64_t	lastlogsize;
		int		mtime;

		if (NULL != result && 0 != ISSET_META(result))
		{
			value_flags = ZBX_DC_FLAG_META;
			lastlogsize = result->lastlogsize;
			mtime = result->mtime;
		}
		else
		{
			value_flags = 0;
			lastlogsize = 0;
			mtime = 0;
		}
		dc_local_add_history_notsupported(itemid, ts, error, lastlogsize, mtime, value_flags);
		return;
	}

	if (NULL == result)
		return;

	/* allow proxy to send timestamps of empty (throttled etc) values to update nextchecks for queue */
	if (!ISSET_VALUE(result) && !ISSET_META(result) && 0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return;

	value_flags = 0;

	if (!ISSET_VALUE(result))
		value_flags |= ZBX_DC_FLAG_NOVALUE;

	if (ISSET_META(result))
		value_flags |= ZBX_DC_FLAG_META;

	/* Add data to the local history cache if:                                           */
	/*   1) the NOVALUE flag is set (data contains either meta information or timestamp) */
	/*   2) the NOVALUE flag is not set and value conversion succeeded                   */

	if (0 == (value_flags & ZBX_DC_FLAG_NOVALUE))
	{
		if (0 != (ZBX_FLAG_DISCOVERY_RULE & item_flags))
		{
			if (NULL == GET_TEXT_RESULT(result))
				return;

			/* proxy stores low-level discovery (lld) values in db */
			if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
				dc_local_add_history_lld(itemid, ts, result->text);

			return;
		}

		if (ISSET_LOG(result))
		{
			dc_local_add_history_log(itemid, item_value_type, ts, result->log, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ISSET_UI64(result))
		{
			dc_local_add_history_uint(itemid, item_value_type, ts, result->ui64, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ISSET_DBL(result))
		{
			dc_local_add_history_dbl(itemid, item_value_type, ts, result->dbl, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ISSET_STR(result))
		{
			dc_local_add_history_text(itemid, item_value_type, ts, result->str, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ISSET_TEXT(result))
		{
			dc_local_add_history_text(itemid, item_value_type, ts, result->text, result->lastlogsize,
					result->mtime, value_flags);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
		}
	}
	else
	{
		if (0 != (value_flags & ZBX_DC_FLAG_META))
		{
			dc_local_add_history_log(itemid, item_value_type, ts, NULL, result->lastlogsize, result->mtime,
					value_flags);
		}
		else
			dc_local_add_history_empty(itemid, item_value_type, ts, value_flags);
	}
}

void	dc_flush_history(void)
{
	if (0 == item_values_num)
		return;

	LOCK_CACHE;

	hc_add_item_values(item_values, item_values_num);

	cache->history_num += item_values_num;

	UNLOCK_CACHE;

	item_values_num = 0;
	string_values_offset = 0;
}

/******************************************************************************
 *                                                                            *
 * history cache storage                                                      *
 *                                                                            *
 ******************************************************************************/
ZBX_MEM_FUNC_IMPL(__hc_index, hc_index_mem)
ZBX_MEM_FUNC_IMPL(__hc, hc_mem)

/******************************************************************************
 *                                                                            *
 * Purpose: compares history queue elements                                   *
 *                                                                            *
 ******************************************************************************/
static int	hc_queue_elem_compare_func(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	const zbx_hc_item_t	*item1 = (const zbx_hc_item_t *)e1->data;
	const zbx_hc_item_t	*item2 = (const zbx_hc_item_t *)e2->data;

	/* compare by timestamp of the oldest value */
	return zbx_timespec_compare(&item1->tail->ts, &item2->tail->ts);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free history item data allocated in history cache                 *
 *                                                                            *
 * Parameters: data - [IN] history item data                                  *
 *                                                                            *
 ******************************************************************************/
static void	hc_free_data(zbx_hc_data_t *data)
{
	if (ITEM_STATE_NOTSUPPORTED == data->state)
	{
		__hc_mem_free_func(data->value.str);
	}
	else
	{
		if (0 == (data->flags & ZBX_DC_FLAG_NOVALUE))
		{
			switch (data->value_type)
			{
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
					__hc_mem_free_func(data->value.str);
					break;
				case ITEM_VALUE_TYPE_LOG:
					__hc_mem_free_func(data->value.log->value);

					if (NULL != data->value.log->source)
						__hc_mem_free_func(data->value.log->source);

					__hc_mem_free_func(data->value.log);
					break;
			}
		}
	}

	__hc_mem_free_func(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: put back item into history queue                                  *
 *                                                                            *
 * Parameters: data - [IN] history item data                                  *
 *                                                                            *
 ******************************************************************************/
static void	hc_queue_item(zbx_hc_item_t *item)
{
	zbx_binary_heap_elem_t	elem = {item->itemid, (const void *)item};

	zbx_binary_heap_insert(&cache->history_queue, &elem);
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns history item by itemid                                    *
 *                                                                            *
 * Parameters: itemid - [IN] the item id                                      *
 *                                                                            *
 * Return value: the history item or NULL if the requested item is not in     *
 *               history cache                                                *
 *                                                                            *
 ******************************************************************************/
static zbx_hc_item_t	*hc_get_item(zbx_uint64_t itemid)
{
	return (zbx_hc_item_t *)zbx_hashset_search(&cache->history_items, &itemid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds a new item to history cache                                  *
 *                                                                            *
 * Parameters: itemid - [IN] the item id                                      *
 *                      [IN] the item data                                    *
 *                                                                            *
 * Return value: the added history item                                       *
 *                                                                            *
 ******************************************************************************/
static zbx_hc_item_t	*hc_add_item(zbx_uint64_t itemid, zbx_hc_data_t *data)
{
	zbx_hc_item_t	item_local = {itemid, ZBX_HC_ITEM_STATUS_NORMAL, 0, data, data};

	return (zbx_hc_item_t *)zbx_hashset_insert(&cache->history_items, &item_local, sizeof(item_local));
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies string value to history cache                              *
 *                                                                            *
 * Parameters: str - [IN] the string value                                    *
 *                                                                            *
 * Return value: the copied string or NULL if there was not enough memory     *
 *                                                                            *
 ******************************************************************************/
static char	*hc_mem_value_str_dup(const dc_value_str_t *str)
{
	char	*ptr;

	if (NULL == (ptr = (char *)__hc_mem_malloc_func(NULL, str->len)))
		return NULL;

	memcpy(ptr, &string_values[str->pvalue], str->len - 1);
	ptr[str->len - 1] = '\0';

	return ptr;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clones string value into history data memory                      *
 *                                                                            *
 * Parameters: dst - [IN/OUT] a reference to the cloned value                 *
 *             str - [IN] the string value to clone                           *
 *                                                                            *
 * Return value: SUCCESS - either there was no need to clone the string       *
 *                         (it was empty or already cloned) or the string was *
 *                          cloned successfully                               *
 *               FAIL    - not enough memory                                  *
 *                                                                            *
 * Comments: This function can be called in loop with the same dst value      *
 *           until it finishes cloning string value.                          *
 *                                                                            *
 ******************************************************************************/
static int	hc_clone_history_str_data(char **dst, const dc_value_str_t *str)
{
	if (0 == str->len)
		return SUCCEED;

	if (NULL != *dst)
		return SUCCEED;

	if (NULL != (*dst = hc_mem_value_str_dup(str)))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clones log value into history data memory                         *
 *                                                                            *
 * Parameters: dst        - [IN/OUT] a reference to the cloned value          *
 *             item_value - [IN] the log value to clone                       *
 *                                                                            *
 * Return value: SUCCESS - the log value was cloned successfully              *
 *               FAIL    - not enough memory                                  *
 *                                                                            *
 * Comments: This function can be called in loop with the same dst value      *
 *           until it finishes cloning log value.                             *
 *                                                                            *
 ******************************************************************************/
static int	hc_clone_history_log_data(zbx_log_value_t **dst, const dc_item_value_t *item_value)
{
	if (NULL == *dst)
	{
		/* using realloc instead of malloc just to suppress 'not used' warning for realloc */
		if (NULL == (*dst = (zbx_log_value_t *)__hc_mem_realloc_func(NULL, sizeof(zbx_log_value_t))))
			return FAIL;

		memset(*dst, 0, sizeof(zbx_log_value_t));
	}

	if (SUCCEED != hc_clone_history_str_data(&(*dst)->value, &item_value->value.value_str))
		return FAIL;

	if (SUCCEED != hc_clone_history_str_data(&(*dst)->source, &item_value->source))
		return FAIL;

	(*dst)->logeventid = item_value->logeventid;
	(*dst)->severity = item_value->severity;
	(*dst)->timestamp = item_value->timestamp;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clones item value from local cache into history cache             *
 *                                                                            *
 * Parameters: data       - [IN/OUT] a reference to the cloned value          *
 *             item_value - [IN] the item value                               *
 *                                                                            *
 * Return value: SUCCESS - the item value was cloned successfully             *
 *               FAIL    - not enough memory                                  *
 *                                                                            *
 * Comments: This function can be called in loop with the same data value     *
 *           until it finishes cloning item value.                            *
 *                                                                            *
 ******************************************************************************/
static int	hc_clone_history_data(zbx_hc_data_t **data, const dc_item_value_t *item_value)
{
	if (NULL == *data)
	{
		if (NULL == (*data = (zbx_hc_data_t *)__hc_mem_malloc_func(NULL, sizeof(zbx_hc_data_t))))
			return FAIL;

		memset(*data, 0, sizeof(zbx_hc_data_t));

		(*data)->state = item_value->state;
		(*data)->ts = item_value->ts;
		(*data)->flags = item_value->flags;
	}

	if (0 != (ZBX_DC_FLAG_META & item_value->flags))
	{
		(*data)->lastlogsize = item_value->lastlogsize;
		(*data)->mtime = item_value->mtime;
	}

	if (ITEM_STATE_NOTSUPPORTED == item_value->state)
	{
		if (NULL == ((*data)->value.str = hc_mem_value_str_dup(&item_value->value.value_str)))
			return FAIL;

		(*data)->value_type = item_value->value_type;
		cache->stats.notsupported_counter++;

		return SUCCEED;
	}

	if (0 != (ZBX_DC_FLAG_LLD & item_value->flags))
	{
		if (NULL == ((*data)->value.str = hc_mem_value_str_dup(&item_value->value.value_str)))
			return FAIL;

		(*data)->value_type = ITEM_VALUE_TYPE_TEXT;

		cache->stats.history_text_counter++;
		cache->stats.history_counter++;

		return SUCCEED;
	}

	if (0 == (ZBX_DC_FLAG_NOVALUE & item_value->flags))
	{
		switch (item_value->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				(*data)->value.dbl = item_value->value.value_dbl;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				(*data)->value.ui64 = item_value->value.value_uint;
				break;
			case ITEM_VALUE_TYPE_STR:
				if (SUCCEED != hc_clone_history_str_data(&(*data)->value.str,
						&item_value->value.value_str))
				{
					return FAIL;
				}
				break;
			case ITEM_VALUE_TYPE_TEXT:
				if (SUCCEED != hc_clone_history_str_data(&(*data)->value.str,
						&item_value->value.value_str))
				{
					return FAIL;
				}
				break;
			case ITEM_VALUE_TYPE_LOG:
				if (SUCCEED != hc_clone_history_log_data(&(*data)->value.log, item_value))
					return FAIL;
				break;
		}

		switch (item_value->item_value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				cache->stats.history_float_counter++;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				cache->stats.history_uint_counter++;
				break;
			case ITEM_VALUE_TYPE_STR:
				cache->stats.history_str_counter++;
				break;
			case ITEM_VALUE_TYPE_TEXT:
				cache->stats.history_text_counter++;
				break;
			case ITEM_VALUE_TYPE_LOG:
				cache->stats.history_log_counter++;
				break;
		}

		cache->stats.history_counter++;
	}

	(*data)->value_type = item_value->value_type;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds item values to the history cache                             *
 *                                                                            *
 * Parameters: values     - [IN] the item values to add                       *
 *             values_num - [IN] the number of item values to add             *
 *                                                                            *
 * Comments: If the history cache is full this function will wait until       *
 *           history syncers processes values freeing enough space to store   *
 *           the new value.                                                   *
 *                                                                            *
 ******************************************************************************/
static void	hc_add_item_values(dc_item_value_t *values, int values_num)
{
	dc_item_value_t	*item_value;
	int		i;
	zbx_hc_item_t	*item;

	for (i = 0; i < values_num; i++)
	{
		zbx_hc_data_t	*data = NULL;

		item_value = &values[i];

		/* a record with metadata and no value can be dropped if  */
		/* the metadata update is copied to the last queued value */
		if (NULL != (item = hc_get_item(item_value->itemid)) &&
				0 != (item_value->flags & ZBX_DC_FLAG_NOVALUE) &&
				0 != (item_value->flags & ZBX_DC_FLAG_META))
		{
			/* skip metadata updates when only one value is queued, */
			/* because the item might be already being processed    */
			if (item->head != item->tail)
			{
				item->head->lastlogsize = item_value->lastlogsize;
				item->head->mtime = item_value->mtime;
				item->head->flags |= ZBX_DC_FLAG_META;
				continue;
			}
		}

		if (SUCCEED != hc_clone_history_data(&data, item_value))
		{
			do
			{
				UNLOCK_CACHE;

				zabbix_log(LOG_LEVEL_DEBUG, "History cache is full. Sleeping for 1 second.");
				sleep(1);

				LOCK_CACHE;
			}
			while (SUCCEED != hc_clone_history_data(&data, item_value));

			item = hc_get_item(item_value->itemid);
		}

		if (NULL == item)
		{
			item = hc_add_item(item_value->itemid, data);
			hc_queue_item(item);
		}
		else
		{
			item->head->next = data;
			item->head = data;
		}
		item->values_num++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies item value from history cache into the specified history   *
 *          value                                                             *
 *                                                                            *
 * Parameters: history - [OUT] the history value                              *
 *             itemid  - [IN] the item identifier                             *
 *             data    - [IN] the history data to copy                        *
 *                                                                            *
 * Comments: handling of uninitialized fields in dc_add_proxy_history_log()   *
 *                                                                            *
 ******************************************************************************/
static void	hc_copy_history_data(ZBX_DC_HISTORY *history, zbx_uint64_t itemid, zbx_hc_data_t *data)
{
	history->itemid = itemid;
	history->ts = data->ts;
	history->state = data->state;
	history->flags = data->flags;
	history->lastlogsize = data->lastlogsize;
	history->mtime = data->mtime;

	if (ITEM_STATE_NOTSUPPORTED == data->state)
	{
		history->value.err = zbx_strdup(NULL, data->value.str);
		history->flags |= ZBX_DC_FLAG_UNDEF;
		return;
	}

	history->value_type = data->value_type;

	if (0 == (ZBX_DC_FLAG_NOVALUE & data->flags))
	{
		switch (data->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				history->value.dbl = data->value.dbl;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				history->value.ui64 = data->value.ui64;
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				history->value.str = zbx_strdup(NULL, data->value.str);
				break;
			case ITEM_VALUE_TYPE_LOG:
				history->value.log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));
				history->value.log->value = zbx_strdup(NULL, data->value.log->value);

				if (NULL != data->value.log->source)
					history->value.log->source = zbx_strdup(NULL, data->value.log->source);
				else
					history->value.log->source = NULL;

				history->value.log->timestamp = data->value.log->timestamp;
				history->value.log->severity = data->value.log->severity;
				history->value.log->logeventid = data->value.log->logeventid;

				break;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: pops the next batch of history items from cache for processing    *
 *                                                                            *
 * Parameters: history_items - [OUT] the locked history items                 *
 *                                                                            *
 * Comments: The history_items must be returned back to history cache with    *
 *           hc_push_items() function after they have been processed.         *
 *                                                                            *
 ******************************************************************************/
static void	hc_pop_items(zbx_vector_ptr_t *history_items)
{
	zbx_binary_heap_elem_t	*elem;
	zbx_hc_item_t		*item;

	while (ZBX_HC_SYNC_MAX > history_items->values_num && FAIL == zbx_binary_heap_empty(&cache->history_queue))
	{
		elem = zbx_binary_heap_find_min(&cache->history_queue);
		item = (zbx_hc_item_t *)elem->data;
		zbx_vector_ptr_append(history_items, item);

		zbx_binary_heap_remove_min(&cache->history_queue);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets item history values                                          *
 *                                                                            *
 * Parameters: history       - [OUT] the history values                       *
 *             history_items - [IN] the history items                         *
 *                                                                            *
 ******************************************************************************/
static void	hc_get_item_values(ZBX_DC_HISTORY *history, zbx_vector_ptr_t *history_items)
{
	int		i, history_num = 0;
	zbx_hc_item_t	*item;

	/* we don't need to lock history cache because no other processes can  */
	/* change item's history data until it is pushed back to history queue */
	for (i = 0; i < history_items->values_num; i++)
	{
		item = (zbx_hc_item_t *)history_items->values[i];

		if (ZBX_HC_ITEM_STATUS_BUSY == item->status)
			continue;

		hc_copy_history_data(&history[history_num++], item->itemid, item->tail);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: push back the processed history items into history cache          *
 *                                                                            *
 * Parameters: history_items - [IN] the history items containing processed    *
 *                                  (available) and busy items                *
 *                                                                            *
 * Comments: This function removes processed value from history cache.        *
 *           If there is no more data for this item, then the item itself is  *
 *           removed from history index.                                      *
 *                                                                            *
 ******************************************************************************/
void	hc_push_items(zbx_vector_ptr_t *history_items)
{
	int		i;
	zbx_hc_item_t	*item;
	zbx_hc_data_t	*data_free;

	for (i = 0; i < history_items->values_num; i++)
	{
		item = (zbx_hc_item_t *)history_items->values[i];

		switch (item->status)
		{
			case ZBX_HC_ITEM_STATUS_BUSY:
				/* reset item status before returning it to queue */
				item->status = ZBX_HC_ITEM_STATUS_NORMAL;
				hc_queue_item(item);
				break;
			case ZBX_HC_ITEM_STATUS_NORMAL:
				item->values_num--;
				data_free = item->tail;
				item->tail = item->tail->next;
				hc_free_data(data_free);
				if (NULL == item->tail)
					zbx_hashset_remove(&cache->history_items, item);
				else
					hc_queue_item(item);
				break;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve the size of history queue                                *
 *                                                                            *
 ******************************************************************************/
int	hc_queue_get_size(void)
{
	return cache->history_queue.elems_num;
}

int	hc_get_history_compression_age(void)
{
#if defined(HAVE_POSTGRESQL)
	zbx_config_t	cfg;
	int		compression_age = 0;

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_DB_EXTENSION);

	if (ON == cfg.db.history_compression_status && 0 != cfg.db.history_compress_older)
	{
		compression_age = (int)time(NULL) - cfg.db.history_compress_older;
	}

	zbx_config_clean(&cfg);

	return compression_age;
#else
	return 0;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: Allocate shared memory for trend cache (part of database cache)   *
 *                                                                            *
 * Comments: Is optionally called from init_database_cache()                  *
 *                                                                            *
 ******************************************************************************/

ZBX_MEM_FUNC_IMPL(__trend, trend_mem)

static int	init_trend_cache(char **error)
{
	size_t	sz;
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (ret = zbx_mutex_create(&trends_lock, ZBX_MUTEX_TRENDS, error)))
		goto out;

	sz = zbx_mem_required_size(1, "trend cache", "TrendCacheSize");
	if (SUCCEED != (ret = zbx_mem_create(&trend_mem, CONFIG_TRENDS_CACHE_SIZE, "trend cache", "TrendCacheSize", 0,
			error)))
	{
		goto out;
	}

	CONFIG_TRENDS_CACHE_SIZE -= sz;

	cache->trends_num = 0;
	cache->trends_last_cleanup_hour = 0;

#define INIT_HASHSET_SIZE	100	/* Should be calculated dynamically based on trends size? */
					/* Still does not make sense to have it more than initial */
					/* item hashset size in configuration cache.              */

	zbx_hashset_create_ext(&cache->trends, INIT_HASHSET_SIZE,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL,
			__trend_mem_malloc_func, __trend_mem_realloc_func, __trend_mem_free_func);

#undef INIT_HASHSET_SIZE
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Allocate shared memory for database cache                         *
 *                                                                            *
 ******************************************************************************/
int	init_database_cache(char **error)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != cache)
	{
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED != (ret = zbx_mutex_create(&cache_lock, ZBX_MUTEX_CACHE, error)))
		goto out;

	if (SUCCEED != (ret = zbx_mutex_create(&cache_ids_lock, ZBX_MUTEX_CACHE_IDS, error)))
		goto out;

	if (SUCCEED != (ret = zbx_mem_create(&hc_mem, CONFIG_HISTORY_CACHE_SIZE, "history cache",
			"HistoryCacheSize", 1, error)))
	{
		goto out;
	}

	if (SUCCEED != (ret = zbx_mem_create(&hc_index_mem, CONFIG_HISTORY_INDEX_CACHE_SIZE, "history index cache",
			"HistoryIndexCacheSize", 0, error)))
	{
		goto out;
	}

	cache = (ZBX_DC_CACHE *)__hc_index_mem_malloc_func(NULL, sizeof(ZBX_DC_CACHE));
	memset(cache, 0, sizeof(ZBX_DC_CACHE));

	ids = (ZBX_DC_IDS *)__hc_index_mem_malloc_func(NULL, sizeof(ZBX_DC_IDS));
	memset(ids, 0, sizeof(ZBX_DC_IDS));

	zbx_hashset_create_ext(&cache->history_items, ZBX_HC_ITEMS_INIT_SIZE,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL,
			__hc_index_mem_malloc_func, __hc_index_mem_realloc_func, __hc_index_mem_free_func);

	zbx_binary_heap_create_ext(&cache->history_queue, hc_queue_elem_compare_func, ZBX_BINARY_HEAP_OPTION_EMPTY,
			__hc_index_mem_malloc_func, __hc_index_mem_realloc_func, __hc_index_mem_free_func);

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		zbx_hashset_create_ext(&(cache->proxyqueue.index), ZBX_HC_SYNC_MAX,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL,
			__hc_index_mem_malloc_func, __hc_index_mem_realloc_func, __hc_index_mem_free_func);

		zbx_list_create_ext(&(cache->proxyqueue.list), __hc_index_mem_malloc_func, __hc_index_mem_free_func);

		cache->proxyqueue.state = ZBX_HC_PROXYQUEUE_STATE_NORMAL;

		if (SUCCEED != (ret = init_trend_cache(error)))
			goto out;
	}

	cache->history_num_total = 0;
	cache->history_progress_ts = 0;

	cache->db_trigger_queue_lock = 1;

	if (NULL == sql)
		sql = (char *)zbx_malloc(sql, sql_alloc);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: writes updates and new data from pool and cache data to database  *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_all(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In DCsync_all()");

	sync_history_cache_full();
	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		DCsync_trends();

	zabbix_log(LOG_LEVEL_DEBUG, "End of DCsync_all()");
}

/******************************************************************************
 *                                                                            *
 * Purpose: Free memory allocated for database cache                          *
 *                                                                            *
 ******************************************************************************/
void	free_database_cache(int sync)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_SYNC_ALL == sync)
		DCsync_all();

	cache = NULL;

	zbx_mem_destroy(hc_mem);
	hc_mem = NULL;
	zbx_mem_destroy(hc_index_mem);
	hc_index_mem = NULL;

	zbx_mutex_destroy(&cache_lock);
	zbx_mutex_destroy(&cache_ids_lock);

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		zbx_mem_destroy(trend_mem);
		trend_mem = NULL;
		zbx_mutex_destroy(&trends_lock);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Return next id for requested table                                *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_nextid(const char *table_name, int num)
{
	int		i;
	DB_RESULT	result;
	DB_ROW		row;
	const ZBX_TABLE	*table;
	ZBX_DC_ID	*id;
	zbx_uint64_t	min = 0, max = ZBX_DB_MAX_ID, nextid, lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' num:%d", __func__, table_name, num);

	LOCK_CACHE_IDS;

	for (i = 0; i < ZBX_IDS_SIZE; i++)
	{
		id = &ids->id[i];
		if ('\0' == *id->table_name)
			break;

		if (0 == strcmp(id->table_name, table_name))
		{
			nextid = id->lastid + 1;
			id->lastid += num;
			lastid = id->lastid;

			UNLOCK_CACHE_IDS;

			zabbix_log(LOG_LEVEL_DEBUG, "End of %s() table:'%s' [" ZBX_FS_UI64 ":" ZBX_FS_UI64 "]",
					__func__, table_name, nextid, lastid);

			return nextid;
		}
	}

	if (i == ZBX_IDS_SIZE)
	{
		zabbix_log(LOG_LEVEL_ERR, "insufficient shared memory for ids");
		exit(EXIT_FAILURE);
	}

	table = DBget_table(table_name);

	result = DBselect("select max(%s) from %s where %s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
			table->recid, table_name, table->recid, min, max);

	if (NULL != result)
	{
		zbx_strlcpy(id->table_name, table_name, sizeof(id->table_name));

		if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
			id->lastid = min;
		else
			ZBX_STR2UINT64(id->lastid, row[0]);

		nextid = id->lastid + 1;
		id->lastid += num;
		lastid = id->lastid;
	}
	else
		nextid = lastid = 0;

	UNLOCK_CACHE_IDS;

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() table:'%s' [" ZBX_FS_UI64 ":" ZBX_FS_UI64 "]",
			__func__, table_name, nextid, lastid);

	return nextid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: performs interface availability reset for hosts with              *
 *          availability set on interfaces without enabled items              *
 *                                                                            *
 ******************************************************************************/
void	DCupdate_interfaces_availability(void)
{
	zbx_vector_availability_ptr_t		interfaces;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_availability_ptr_create(&interfaces);

	if (SUCCEED != DCreset_interfaces_availability(&interfaces))
		goto out;

	zbx_availabilities_flush(&interfaces);
out:
	zbx_vector_availability_ptr_clear_ext(&interfaces, zbx_interface_availability_free);
	zbx_vector_availability_ptr_destroy(&interfaces);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get history cache diagnostics statistics                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_hc_get_diag_stats(zbx_uint64_t *items_num, zbx_uint64_t *values_num)
{
	LOCK_CACHE;

	*values_num = cache->history_num;
	*items_num = cache->history_items.num_data;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get shared memory allocator statistics                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_hc_get_mem_stats(zbx_mem_stats_t *data, zbx_mem_stats_t *index)
{
	LOCK_CACHE;

	if (NULL != data)
		zbx_mem_get_stats(hc_mem, data);

	if (NULL != index)
		zbx_mem_get_stats(hc_index_mem, index);

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get statistics of cached items                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_hc_get_items(zbx_vector_uint64_pair_t *items)
{
	zbx_hashset_iter_t	iter;
	zbx_hc_item_t		*item;

	LOCK_CACHE;

	zbx_vector_uint64_pair_reserve(items, cache->history_items.num_data);

	zbx_hashset_iter_reset(&cache->history_items, &iter);
	while (NULL != (item = (zbx_hc_item_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_uint64_pair_t	pair = {item->itemid, item->values_num};
		zbx_vector_uint64_pair_append_ptr(items, &pair);
	}

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if database trigger queue table is locked                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_trigger_queue_locked(void)
{
	return 0 == cache->db_trigger_queue_lock ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlocks database trigger queue table                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_queue_unlock(void)
{
	cache->db_trigger_queue_lock = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return first proxy in a queue, function assumes that a queue is   *
 *          not empty                                                         *
 *                                                                            *
 * Return value: proxyid at the top a queue                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	zbx_hc_proxyqueue_peek(void)
{
	zbx_uint64_t	*p_val;

	if (NULL == cache->proxyqueue.list.head)
		return 0;

	p_val = (zbx_uint64_t *)(cache->proxyqueue.list.head->data);

	return *p_val;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new proxyid to a queue                                        *
 *                                                                            *
 * Parameters: proxyid   - [IN] the proxy id                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_hc_proxyqueue_enqueue(zbx_uint64_t proxyid)
{
	if (NULL == zbx_hashset_search(&cache->proxyqueue.index, &proxyid))
	{
		zbx_uint64_t *ptr;

		ptr = zbx_hashset_insert(&cache->proxyqueue.index, &proxyid, sizeof(proxyid));
		zbx_list_append(&cache->proxyqueue.list, ptr, NULL);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: try to dequeue proxyid from a proxy queue                         *
 *                                                                            *
 * Parameters: chk_proxyid  - [IN] the proxyid                                *
 *                                                                            *
 * Return value: SUCCEED - retrieval successful                               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_hc_proxyqueue_dequeue(zbx_uint64_t proxyid)
{
	zbx_uint64_t	top_val;
	void		*rem_val = 0;

	top_val = zbx_hc_proxyqueue_peek();

	if (proxyid != top_val)
		return FAIL;

	if (FAIL == zbx_list_pop(&cache->proxyqueue.list, &rem_val))
		return FAIL;

	zbx_hashset_remove_direct(&cache->proxyqueue.index, rem_val);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove all proxies from proxy priority queue                      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_hc_proxyqueue_clear(void)
{
	zbx_list_destroy(&cache->proxyqueue.list);
	zbx_hashset_clear(&cache->proxyqueue.index);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check status of a history cache usage, enqueue/dequeue proxy      *
 *          from priority list and accordingly enable or disable wait mode    *
 *                                                                            *
 * Parameters: proxyid   - [IN] the proxyid                                   *
 *                                                                            *
 * Return value: SUCCEED - proxy can be processed now                         *
 *               FAIL    - proxy cannot be processed now, it got enqueued     *
 *                                                                            *
 ******************************************************************************/
int	zbx_hc_check_proxy(zbx_uint64_t proxyid)
{
	double	hc_pused;
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxyid:"ZBX_FS_UI64, __func__, proxyid);

	LOCK_CACHE;

	hc_pused = 100 * (double)(hc_mem->total_size - hc_mem->free_size) / hc_mem->total_size;

	if (20 >= hc_pused)
	{
		cache->proxyqueue.state = ZBX_HC_PROXYQUEUE_STATE_NORMAL;

		zbx_hc_proxyqueue_clear();

		ret = SUCCEED;
		goto out;
	}

	if (ZBX_HC_PROXYQUEUE_STATE_WAIT == cache->proxyqueue.state)
	{
		zbx_hc_proxyqueue_enqueue(proxyid);

		if (60 < hc_pused)
		{
			ret = FAIL;
			goto out;
		}

		cache->proxyqueue.state = ZBX_HC_PROXYQUEUE_STATE_NORMAL;
	}
	else
	{
		if (80 <= hc_pused)
		{
			cache->proxyqueue.state = ZBX_HC_PROXYQUEUE_STATE_WAIT;
			zbx_hc_proxyqueue_enqueue(proxyid);

			ret = FAIL;
			goto out;
		}
	}

	if (0 == zbx_hc_proxyqueue_peek())
	{
		ret = SUCCEED;
		goto out;
	}

	ret = zbx_hc_proxyqueue_dequeue(proxyid);

out:
	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
