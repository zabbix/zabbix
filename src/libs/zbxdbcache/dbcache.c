/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "threads.h"

#include "db.h"
#include "dbcache.h"
#include "ipc.h"
#include "mutexs.h"
#include "zbxserver.h"
#include "proxy.h"
#include "events.h"
#include "memalloc.h"
#include "zbxalgo.h"
#include "valuecache.h"
#include "zbxmodules.h"
#include "module.h"
#include "export.h"
#include "zbxjson.h"
#include "zbxhistory.h"

static zbx_mem_info_t	*hc_index_mem = NULL;
static zbx_mem_info_t	*hc_mem = NULL;
static zbx_mem_info_t	*trend_mem = NULL;

#define	LOCK_CACHE	zbx_mutex_lock(&cache_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(&cache_lock)
#define	LOCK_TRENDS	zbx_mutex_lock(&trends_lock)
#define	UNLOCK_TRENDS	zbx_mutex_unlock(&trends_lock)
#define	LOCK_CACHE_IDS		zbx_mutex_lock(&cache_ids_lock)
#define	UNLOCK_CACHE_IDS	zbx_mutex_unlock(&cache_ids_lock)

static ZBX_MUTEX	cache_lock = ZBX_MUTEX_NULL;
static ZBX_MUTEX	trends_lock = ZBX_MUTEX_NULL;
static ZBX_MUTEX	cache_ids_lock = ZBX_MUTEX_NULL;

static char		*sql = NULL;
static size_t		sql_alloc = 64 * ZBX_KIBIBYTE;

extern unsigned char	program_type;

#define ZBX_IDS_SIZE	8

#define ZBX_HC_ITEMS_INIT_SIZE	1000

#define ZBX_TRENDS_CLEANUP_TIME	((SEC_PER_HOUR * 55) / 60)

/* the maximum time spent synchronizing history */
#define ZBX_HC_SYNC_TIME_MAX	SEC_PER_MIN

/* the maximum number of items in one synchronization batch */
#define ZBX_HC_SYNC_MAX		1000

/* the minimum processed item percentage of item candidates to continue synchronizing */
#define ZBX_HC_SYNC_MIN_PCNT	10

/* the maximum number of characters for history cache values */
#define ZBX_HISTORY_VALUE_LEN	(1024 * 64)

#define ZBX_DC_FLAGS_NOT_FOR_HISTORY	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF | ZBX_DC_FLAG_NOHISTORY)
#define ZBX_DC_FLAGS_NOT_FOR_TRENDS	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF | ZBX_DC_FLAG_NOTRENDS)
#define ZBX_DC_FLAGS_NOT_FOR_MODULES	(ZBX_DC_FLAGS_NOT_FOR_HISTORY | ZBX_DC_FLAG_LLD)
#define ZBX_DC_FLAGS_NOT_FOR_EXPORT	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF)

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

#define ZBX_DC_FLAG_META	0x01	/* contains meta information (lastlogsize and mtime) */
#define ZBX_DC_FLAG_NOVALUE	0x02	/* entry contains no value */
#define ZBX_DC_FLAG_LLD		0x04	/* low-level discovery value */
#define ZBX_DC_FLAG_UNDEF	0x08	/* unsupported or undefined (delta calculation failed) value */
#define ZBX_DC_FLAG_NOHISTORY	0x10	/* values should not be kept in history */
#define ZBX_DC_FLAG_NOTRENDS	0x20	/* values should not be kept in trends */

typedef struct
{
	zbx_uint64_t	history_counter;	/* the total number of processed values */
	zbx_uint64_t	history_float_counter;	/* the number of processed float values */
	zbx_uint64_t	history_uint_counter;	/* the number of processed uint values */
	zbx_uint64_t	history_str_counter;	/* the number of processed str values */
	zbx_uint64_t	history_log_counter;	/* the number of processed log values */
	zbx_uint64_t	history_text_counter;	/* the number of processed text values */
	zbx_uint64_t	notsupported_counter;	/* the number of processed not supported items */
}
ZBX_DC_STATS;

typedef struct
{
	zbx_hashset_t		trends;
	ZBX_DC_STATS		stats;

	zbx_hashset_t		history_items;
	zbx_binary_heap_t	history_queue;

	int			history_num;
	int			trends_num;
	int			trends_last_cleanup_hour;
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
static void	hc_push_busy_items(zbx_vector_ptr_t *history_items);
static int	hc_push_processed_items(zbx_vector_ptr_t *history_items);
static void	hc_free_item_values(ZBX_DC_HISTORY *history, int history_num);
static void	hc_queue_item(zbx_hc_item_t *item);
static int	hc_queue_elem_compare_func(const void *d1, const void *d2);

/******************************************************************************
 *                                                                            *
 * Function: DCget_stats                                                      *
 *                                                                            *
 * Purpose: get statistics of the database cache                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Function: DCget_trend                                                      *
 *                                                                            *
 * Purpose: find existing or add new structure and return pointer             *
 *                                                                            *
 * Return value: pointer to a trend structure                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Function: DCupdate_trends                                                  *
 *                                                                            *
 * Purpose: apply disable_from changes to cache                               *
 *                                                                            *
 ******************************************************************************/
static void	DCupdate_trends(zbx_vector_uint64_pair_t *trends_diff)
{
	const char	*__function_name = "DCupdate_trends";
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_TRENDS;

	for (i = 0; i < trends_diff->values_num; i++)
	{
		ZBX_DC_TREND	*trend;

		if (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_search(&cache->trends, &trends_diff->values[i].first)))
			trend->disable_from = trends_diff->values[i].second;
	}

	UNLOCK_TRENDS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_insert_trends_in_db                                           *
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
 * Function: dc_remove_updated_trends                                         *
 *                                                                            *
 * Purpose: helper function for DCflush trends                                *
 *                                                                            *
 ******************************************************************************/
static void	dc_remove_updated_trends(ZBX_DC_TREND *trends, int trends_num, const char *table_name,
		int value_type, zbx_uint64_t *itemids, int *itemids_num, int clock)
{
	int		i;
	ZBX_DC_TREND	*trend;
	zbx_uint64_t	itemid;
	size_t		sql_offset;
	DB_RESULT	result;
	DB_ROW		row;

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct itemid"
			" from %s"
			" where clock>=%d and",
			table_name, clock);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids, *itemids_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		uint64_array_remove(itemids, itemids_num, &itemid, 1);
	}
	DBfree_result(result);

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
 * Function: dc_trends_update_float                                           *
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
	trend->value_avg.dbl = (trend->num * trend->value_avg.dbl
			+ num * value_avg.dbl) / (trend->num + num);
	trend->num += num;

	zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset,
			"update trends set num=%d,value_min=" ZBX_FS_DBL ",value_avg="
			ZBX_FS_DBL ",value_max=" ZBX_FS_DBL " where itemid=" ZBX_FS_UI64
			" and clock=%d;\n",
			trend->num,
			trend->value_min.dbl,
			trend->value_avg.dbl,
			trend->value_max.dbl,
			trend->itemid,
			trend->clock);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_trends_update_uint                                            *
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
 * Function: dc_trends_fetch_and_update                                       *
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

	result = DBselect("%s", sql);

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
 * Function: DBflush_trends                                                   *
 *                                                                            *
 * Purpose: flush trend to the database                                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DBflush_trends(ZBX_DC_TREND *trends, int *trends_num, zbx_vector_uint64_pair_t *trends_diff)
{
	const char	*__function_name = "DCflush_trends";
	int		num, i, clock, inserts_num = 0, itemids_alloc, itemids_num = 0, trends_to = *trends_num;
	unsigned char	value_type;
	zbx_uint64_t	*itemids = NULL;
	ZBX_DC_TREND	*trend = NULL;
	const char	*table_name;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d", __function_name, *trends_num);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCflush_trend                                                    *
 *                                                                            *
 * Purpose: move trend to the array of trends for flushing to DB              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Function: DCadd_trend                                                      *
 *                                                                            *
 * Purpose: add new value to the trends                                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_trend(const ZBX_DC_HISTORY *history, ZBX_DC_TREND **trends, int *trends_alloc, int *trends_num)
{
	ZBX_DC_TREND	*trend = NULL;
	int		hour;

	hour = history->ts.sec - history->ts.sec % SEC_PER_HOUR;

	trend = DCget_trend(history->itemid);

	if (trend->num > 0 && (trend->clock != hour || trend->value_type != history->value_type))
		DCflush_trend(trend, trends, trends_alloc, trends_num);

	trend->value_type = history->value_type;
	trend->clock = hour;

	switch (trend->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (trend->num == 0 || history->value.dbl < trend->value_min.dbl)
				trend->value_min.dbl = history->value.dbl;
			if (trend->num == 0 || history->value.dbl > trend->value_max.dbl)
				trend->value_max.dbl = history->value.dbl;
			trend->value_avg.dbl = (trend->num * trend->value_avg.dbl
				+ history->value.dbl) / (trend->num + 1);
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
 * Function: DCmass_update_trends                                             *
 *                                                                            *
 * Purpose: update trends cache and get list of trends to flush into database *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *             trends      - list of trends to flush into database            *
 *             trends_num  - number of trends                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_update_trends(const ZBX_DC_HISTORY *history, int history_num, ZBX_DC_TREND **trends,
		int *trends_num)
{
	const char	*__function_name = "DCmass_update_trends";

	zbx_timespec_t	ts;
	int		trends_alloc = 0, i, hour, seconds;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

			if (SUCCEED == zbx_history_requires_trends(trend->value_type))
				DCflush_trend(trend, trends, &trends_alloc, trends_num);

			zbx_hashset_iter_remove(&iter);
		}

		cache->trends_last_cleanup_hour = hour;
	}

	UNLOCK_TRENDS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBmass_update_trends                                             *
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

static void	zbx_host_info_clean(zbx_host_info_t *host_info)
{
	zbx_vector_ptr_clear_ext(&host_info->groups, zbx_ptr_free);
	zbx_vector_ptr_destroy(&host_info->groups);
}

static void	db_get_hosts_by_hostid(zbx_hashset_t *hosts_info, const zbx_vector_uint64_t *hostids)
{
	int		i;
	size_t		sql_offset;
	DB_RESULT	result;
	DB_ROW		row;

	for (i = 0; i < hostids->values_num; i++)
	{
		zbx_host_info_t	host_info = {.hostid = hostids->values[i]};

		zbx_vector_ptr_create(&host_info.groups);
		zbx_hashset_insert(hosts_info, &host_info, sizeof(host_info));
	}

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct hg.hostid, g.name"
				" from groups g, hosts_groups hg"
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
	zbx_vector_ptr_t	applications;
}
zbx_item_info_t;

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

		zbx_substitute_item_name_macros(item_info->item, row[1], &item_info->name);
	}
	DBfree_result(result);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.itemid,a.name"
			" from applications a,items_applications i"
			" where a.applicationid=i.applicationid"
				" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", itemids->values, itemids->values_num);

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

		zbx_vector_ptr_append(&item_info->applications, zbx_strdup(NULL, row[1]));
	}
	DBfree_result(result);
}

static void	zbx_item_info_clean(zbx_item_info_t *item_info)
{
	zbx_vector_ptr_clear_ext(&item_info->applications, zbx_ptr_free);
	zbx_vector_ptr_destroy(&item_info->applications);
	zbx_free(item_info->name);
}

static void	DCexport_trends(const ZBX_DC_TREND *trends, int trends_num, zbx_hashset_t *hosts,
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

		if (NULL == (host_info = (zbx_host_info_t *)zbx_hashset_search(hosts, &item->host.hostid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_json_clean(&json);
		zbx_json_addstring(&json, ZBX_EXPORT_TAG_HOST, item->host.name, ZBX_JSON_TYPE_STRING);
		zbx_json_addarray(&json, ZBX_EXPORT_TAG_GROUPS);

		for (j = 0; j < host_info->groups.values_num; j++)
			zbx_json_addstring(&json, NULL, host_info->groups.values[j], ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);

		if (NULL != trend)
			zbx_json_addarray(&json, ZBX_EXPORT_TAG_APPLICATIONS);

		for (j = 0; j < item_info->applications.values_num; j++)
			zbx_json_addstring(&json, NULL, item_info->applications.values[j], ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);
		zbx_json_adduint64(&json, ZBX_EXPORT_TAG_ITEMID, item->itemid);

		if (NULL != item_info->name)
			zbx_json_addstring(&json, ZBX_EXPORT_TAG_NAME, item_info->name, ZBX_JSON_TYPE_STRING);

		zbx_json_addint64(&json, ZBX_EXPORT_TAG_CLOCK, trend->clock);
		zbx_json_addint64(&json, ZBX_EXPORT_TAG_COUNT, trend->num);

		switch (trend->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_json_addfloat(&json, ZBX_EXPORT_TAG_MIN, trend->value_min.dbl);
				zbx_json_addfloat(&json, ZBX_EXPORT_TAG_AVG, trend->value_avg.dbl);
				zbx_json_addfloat(&json, ZBX_EXPORT_TAG_MAX, trend->value_max.dbl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_json_adduint64(&json, ZBX_EXPORT_TAG_MIN, trend->value_min.ui64);
				udiv128_64(&avg, &trend->value_avg.ui64, trend->num);
				zbx_json_adduint64(&json, ZBX_EXPORT_TAG_AVG, avg.lo);
				zbx_json_adduint64(&json, ZBX_EXPORT_TAG_MAX, trend->value_max.ui64);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		zbx_trends_export_write(json.buffer, json.buffer_size);
	}

	zbx_trends_export_flush();
	zbx_json_free(&json);
}

static void	DCexport_history(const ZBX_DC_HISTORY *history, int history_num, zbx_hashset_t *hosts,
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

		if (NULL == (host_info = (zbx_host_info_t *)zbx_hashset_search(hosts, &item->host.hostid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_json_clean(&json);
		zbx_json_addstring(&json, ZBX_EXPORT_TAG_HOST, item->host.name, ZBX_JSON_TYPE_STRING);
		zbx_json_addarray(&json, ZBX_EXPORT_TAG_GROUPS);

		for (j = 0; j < host_info->groups.values_num; j++)
			zbx_json_addstring(&json, NULL, host_info->groups.values[j], ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);

		zbx_json_addarray(&json, ZBX_EXPORT_TAG_APPLICATIONS);

		for (j = 0; j < item_info->applications.values_num; j++)
			zbx_json_addstring(&json, NULL, item_info->applications.values[j], ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);
		zbx_json_adduint64(&json, ZBX_EXPORT_TAG_ITEMID, item->itemid);

		if (NULL != item_info->name)
			zbx_json_addstring(&json, ZBX_EXPORT_TAG_NAME, item_info->name, ZBX_JSON_TYPE_STRING);

		zbx_json_addint64(&json, ZBX_EXPORT_TAG_CLOCK, h->ts.sec);
		zbx_json_addint64(&json, ZBX_EXPORT_TAG_NS, h->ts.ns);

		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_json_addfloat(&json, ZBX_EXPORT_TAG_VALUE, h->value.dbl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_json_adduint64(&json, ZBX_EXPORT_TAG_VALUE, h->value.ui64);
				break;
			case ITEM_VALUE_TYPE_STR:
				zbx_json_addstring(&json, ZBX_EXPORT_TAG_VALUE, h->value.str, ZBX_JSON_TYPE_STRING);
				break;
			case ITEM_VALUE_TYPE_TEXT:
				zbx_json_addstring(&json, ZBX_EXPORT_TAG_VALUE, h->value.str, ZBX_JSON_TYPE_STRING);
				break;
			case ITEM_VALUE_TYPE_LOG:
				zbx_json_addint64(&json, ZBX_EXPORT_TAG_TIMESTAMP, h->value.log->timestamp);
				zbx_json_addstring(&json, ZBX_EXPORT_TAG_SOURCE, ZBX_NULL2EMPTY_STR(h->value.log->source),
						ZBX_JSON_TYPE_STRING);
				zbx_json_addint64(&json, ZBX_EXPORT_TAG_SEVERITY, h->value.log->severity);
				zbx_json_addint64(&json, ZBX_EXPORT_TAG_LOGEVENTID, h->value.log->logeventid);
				zbx_json_addstring(&json, ZBX_EXPORT_TAG_VALUE, h->value.log->value,
						ZBX_JSON_TYPE_STRING);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		zbx_history_export_write(json.buffer, json.buffer_size);
	}

	zbx_history_export_flush();
	zbx_json_free(&json);
}

static void	DCexport_history_and_trends(const ZBX_DC_HISTORY *history, int history_num,
		const zbx_vector_uint64_t *itemids, DC_ITEM *items, const int *errcodes,
		const ZBX_DC_TREND *trends, int trends_num)
{
	const char		*__function_name = "DCexport_history_and_trends";
	int			i, index;
	zbx_vector_uint64_t	hostids, item_info_ids;
	zbx_hashset_t		hosts_info, items_info;
	DC_ITEM			*item;
	zbx_item_info_t		item_info;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d trends_num:%d", __function_name, history_num, trends_num);

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
		zbx_vector_ptr_create(&item_info.applications);
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
			zbx_vector_ptr_create(&item_info.applications);
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

	db_get_hosts_by_hostid(&hosts_info, &hostids);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

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
 * Function: DCsync_trends                                                    *
 *                                                                            *
 * Purpose: flush all trends to the database                                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_trends(void)
{
	const char		*__function_name = "DCsync_trends";
	zbx_hashset_iter_t	iter;
	ZBX_DC_TREND		*trends = NULL, *trend;
	int			trends_alloc = 0, trends_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d", __function_name, cache->trends_num);

	zabbix_log(LOG_LEVEL_WARNING, "syncing trend data...");

	LOCK_TRENDS;

	zbx_hashset_iter_reset(&cache->trends, &iter);

	while (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_iter_next(&iter)))
	{
		if (SUCCEED == zbx_history_requires_trends(trend->value_type))
			DCflush_trend(trend, &trends, &trends_alloc, &trends_num);
	}

	UNLOCK_TRENDS;

	if (SUCCEED == zbx_is_export_enabled() && 0 != trends_num)
		DCexport_all_trends(trends, trends_num);

	DBbegin();

	while (trends_num > 0)
		DBflush_trends(trends, &trends_num, NULL);

	DBcommit();

	zbx_free(trends);

	zabbix_log(LOG_LEVEL_WARNING, "syncing trend data done");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBmass_update_triggers                                           *
 *                                                                            *
 * Purpose: re-calculate and update values of triggers related to the items   *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 ******************************************************************************/
static void	DBmass_update_triggers(const ZBX_DC_HISTORY *history, int history_num, zbx_vector_ptr_t *trigger_diff)
{
	const char		*__function_name = "DBmass_update_triggers";
	int			i, item_num = 0;
	zbx_uint64_t		*itemids = NULL;
	zbx_timespec_t		*timespecs = NULL;
	zbx_hashset_t		trigger_info;
	zbx_vector_ptr_t	trigger_order;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	itemids = (zbx_uint64_t *)zbx_malloc(itemids, sizeof(zbx_uint64_t) * (size_t)history_num);
	timespecs = (zbx_timespec_t *)zbx_malloc(timespecs, sizeof(zbx_timespec_t) * (size_t)history_num);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
			continue;

		itemids[item_num] = h->itemid;
		timespecs[item_num] = h->ts;
		item_num++;
	}

	if (0 == item_num)
		goto clean_items;

	zbx_hashset_create(&trigger_info, MAX(100, 2 * item_num),
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&trigger_order);
	zbx_vector_ptr_reserve(&trigger_order, item_num);

	DCconfig_get_triggers_by_itemids(&trigger_info, &trigger_order, itemids, timespecs, item_num);

	zbx_determine_items_in_expressions(&trigger_order, itemids, item_num);

	if (0 == trigger_order.values_num)
		goto clean_triggers;

	evaluate_expressions(&trigger_order);

	zbx_process_triggers(&trigger_order, trigger_diff);

	DCfree_triggers(&trigger_order);
clean_triggers:
	zbx_hashset_destroy(&trigger_info);
	zbx_vector_ptr_destroy(&trigger_order);
clean_items:
	zbx_free(timespecs);
	zbx_free(itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
			zbx_snprintf(value, sizeof(value), ZBX_FS_DBL, h->value.dbl);
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

	zbx_format_value(value, sizeof(value), item->valuemapid, item->units, h->value_type);

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
 * Function: dc_history_clean_value                                           *
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
 * Function: hc_free_item_values                                              *
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
 * Function: dc_history_set_error                                             *
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
 * Function: dc_history_set_value                                             *
 *                                                                            *
 * Purpose: sets history data value                                           *
 *                                                                            *
 * Parameters: hdata      - [IN/OUT] the history data                         *
 *             value_type - [IN] the item value type                          *
 *             value      - [IN] the value to set                             *
 *                                                                            *
 * Return value: SUCCEED - Value conversion was successful.                   *
 *               FAIL    - Otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dc_history_set_value(ZBX_DC_HISTORY *hdata, unsigned char value_type, zbx_variant_t *value)
{
	int	ret;
	char	*errmsg = NULL;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (SUCCEED == (ret = zbx_variant_convert(value, ZBX_VARIANT_DBL)))
			{
				if (FAIL == (ret = zbx_validate_value_dbl(value->data.dbl)))
				{
					errmsg = zbx_dsprintf(NULL, "Value " ZBX_FS_DBL " is too small or too large.",
							value->data.dbl);
				}
			}
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ret = zbx_variant_convert(value, ZBX_VARIANT_UI64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_LOG:
			ret = zbx_variant_convert(value, ZBX_VARIANT_STR);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	if (FAIL == ret)
	{
		if (NULL == errmsg)
		{
			errmsg = zbx_dsprintf(NULL, "Value \"%s\" of type \"%s\" is not suitable for"
				" value type \"%s\"", zbx_variant_value_desc(value),
				zbx_variant_type_desc(value), zbx_item_value_type_string(value_type));
		}

		dc_history_set_error(hdata, errmsg);
		return FAIL;
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

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: normalize_item_value                                             *
 *                                                                            *
 * Purpose: normalize item value by performing truncation of long text        *
 *          values and changes value format according to the item value type  *
 *                                                                            *
 * Parameters: item          - [IN] the item                                  *
 *             hdata         - [IN/OUT] the historical data to process        *
 *                                                                            *
 * Return value: SUCCEED - Normalization was successful.                      *
 *               FAIL    - Otherwise - ZBX_DC_FLAG_UNDEF will be set and item *
 *                         state changed to ZBX_NOTSUPPORTED.                 *
 *                                                                            *
 ******************************************************************************/
static int	normalize_item_value(const DC_ITEM *item, ZBX_DC_HISTORY *hdata)
{
	int		ret = FAIL;
	char		*logvalue;
	zbx_variant_t	value_var;

	if (0 != (hdata->flags & ZBX_DC_FLAG_NOVALUE))
	{
		ret = SUCCEED;
		goto out;
	}

	if (ITEM_STATE_NOTSUPPORTED == hdata->state)
		goto out;

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
				if (FAIL == zbx_validate_value_dbl(hdata->value.dbl))
				{
					dc_history_set_error(hdata, zbx_dsprintf(NULL, "Value " ZBX_FS_DBL
							" is too small or too large.", hdata->value.dbl));
					return FAIL;
				}
				break;
		}
		return SUCCEED;
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

	ret = dc_history_set_value(hdata, item->value_type, &value_var);
	zbx_variant_clear(&value_var);
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: calculate_item_update                                            *
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
static zbx_item_diff_t	*calculate_item_update(const DC_ITEM *item, const ZBX_DC_HISTORY *h)
{
	zbx_uint64_t	flags = ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTCLOCK;
	const char	*item_error = NULL;
	zbx_item_diff_t	*diff;
	int		object;

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

			object = (0 != (ZBX_FLAG_DISCOVERY_RULE & item->flags) ?
					EVENT_OBJECT_LLDRULE : EVENT_OBJECT_ITEM);

			zbx_add_event(EVENT_SOURCE_INTERNAL, object, item->itemid, &h->ts, h->state, NULL, NULL, NULL,
					0, 0, NULL, 0, NULL, 0, h->value.err);

			if (0 != strcmp(item->error, h->value.err))
				item_error = h->value.err;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" became supported",
					item->host.host, item->key_orig);

			/* we know it's EVENT_OBJECT_ITEM because LLDRULE that becomes */
			/* supported is handled in lld_process_discovery_rule()        */
			zbx_add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, item->itemid, &h->ts, h->state,
					NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL);

			item_error = "";
		}
	}
	else if (ITEM_STATE_NOTSUPPORTED == h->state && 0 != strcmp(item->error, h->value.err))
	{
		zabbix_log(LOG_LEVEL_WARNING, "error reason for \"%s:%s\" changed: %s", item->host.host,
				item->key_orig, h->value.err);

		item_error = h->value.err;
	}

	if (NULL != item_error)
		flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR;

	diff = (zbx_item_diff_t *)zbx_malloc(NULL, sizeof(zbx_item_diff_t));
	diff->itemid = item->itemid;
	diff->lastclock = h->ts.sec;
	diff->flags = flags;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE & flags))
		diff->lastlogsize = h->lastlogsize;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME & flags))
		diff->mtime = h->mtime;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE & flags))
		diff->state = h->state;

	if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR & flags))
		diff->error = item_error;

	return diff;
}

/******************************************************************************
 *                                                                            *
 * Function: db_save_item_changes                                             *
 *                                                                            *
 * Purpose: save item state, error, mtime, lastlogsize changes to             *
 *          database                                                          *
 *                                                                            *
 ******************************************************************************/
static void	db_save_item_changes(size_t *sql_offset, const zbx_vector_ptr_t *item_diff)
{
	int			i;
	const zbx_item_diff_t	*diff;
	char			*value_esc;

	for (i = 0; i < item_diff->values_num; i++)
	{
		char	delim = ' ';

		diff = (const zbx_item_diff_t *)item_diff->values[i];

		if (0 == (ZBX_FLAGS_ITEM_DIFF_UPDATE_DB & diff->flags))
			continue;

		zbx_strcpy_alloc(&sql, &sql_alloc, sql_offset, "update items set");

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE & diff->flags))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%clastlogsize=" ZBX_FS_UI64, delim,
					diff->lastlogsize);
			delim = ',';
		}

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME & diff->flags))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%cmtime=%d", delim, diff->mtime);
			delim = ',';
		}

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE & diff->flags))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%cstate=%d", delim, (int)diff->state);
			delim = ',';
		}

		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR & diff->flags))
		{
			value_esc = DBdyn_escape_field("items", "error", diff->error);
			zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%cerror='%s'", delim, value_esc);
			zbx_free(value_esc);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, " where itemid=" ZBX_FS_UI64 ";\n", diff->itemid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, sql_offset);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBmass_update_items                                              *
 *                                                                            *
 * Purpose: update item data and inventory in database                        *
 *                                                                            *
 * Parameters: item_diff        - item changes                                *
 *             inventory_values - inventory values                            *
 *                                                                            *
 ******************************************************************************/
static void	DBmass_update_items(const zbx_vector_ptr_t *item_diff, const zbx_vector_ptr_t *inventory_values)
{
	const char	*__function_name = "DBmass_update_items";

	size_t		sql_offset = 0;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
			db_save_item_changes(&sql_offset, item_diff);

		if (0 != inventory_values->values_num)
			DCadd_update_inventory_sql(&sql_offset, inventory_values);

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (sql_offset > 16)	/* In ORACLE always present begin..end; */
			DBexecute("%s", sql);

		DCconfig_update_inventory_values(inventory_values);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_proxy_update_items                                        *
 *                                                                            *
 * Purpose: update items info after new value is received                     *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev, Alexander Vladishev            *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_proxy_update_items(ZBX_DC_HISTORY *history, int history_num)
{
	const char	*__function_name = "DCmass_proxy_update_items";

	size_t			sql_offset = 0;
	int			i;
	zbx_vector_ptr_t	item_diff;
	zbx_item_diff_t		*diffs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&item_diff);
	zbx_vector_ptr_reserve(&item_diff, history_num);

	/* preallocate zbx_item_diff_t structures for item_diff vector */
	diffs = (zbx_item_diff_t *)zbx_malloc(NULL, sizeof(zbx_item_diff_t) * history_num);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < history_num; i++)
	{
		zbx_item_diff_t	*diff = &diffs[i];

		diff->itemid = history[i].itemid;
		diff->state = history[i].state;
		diff->lastclock = history[i].ts.sec;
		diff->flags = ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE | ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTCLOCK;

		if (0 != (ZBX_DC_FLAG_META & history[i].flags))
		{
			diff->lastlogsize = history[i].lastlogsize;
			diff->mtime = history[i].mtime;
			diff->flags |= ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE | ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME;
		}

		zbx_vector_ptr_append(&item_diff, diff);

		if (ITEM_STATE_NOTSUPPORTED == history[i].state)
			continue;

		if (0 == (ZBX_DC_FLAG_META & history[i].flags))
			continue;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update items"
				" set lastlogsize=" ZBX_FS_UI64
					",mtime=%d"
				" where itemid=" ZBX_FS_UI64 ";\n",
				history[i].lastlogsize, history[i].mtime, history[i].itemid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	if (0 != item_diff.values_num)
		DCconfig_items_apply_changes(&item_diff);

	zbx_vector_ptr_destroy(&item_diff);
	zbx_free(diffs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBmass_add_history                                               *
 *                                                                            *
 * Purpose: inserting new history data after new value is received            *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 ******************************************************************************/
static int	DBmass_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	const char	*__function_name = "DBmass_add_history";

	int			i, ret = SUCCEED;
	zbx_vector_ptr_t	history_values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&history_values);
	zbx_vector_ptr_reserve(&history_values, history_num);

	for (i = 0; i < history_num; i++)
	{
		ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAGS_NOT_FOR_HISTORY & h->flags))
			continue;

		zbx_vector_ptr_append(&history_values, h);
	}

	if (0 != history_values.values_num)
		ret = zbx_vc_add_values(&history_values);

	zbx_vector_ptr_destroy(&history_values);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_proxy_history                                             *
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type other other than *
 *          ITEM_VALUE_TYPE_LOG not containing meta information in result     *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	char		buffer[64], *pvalue;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (h->flags & ZBX_DC_FLAG_UNDEF))
			continue;

		if (0 != (h->flags & ZBX_DC_FLAG_META))
			continue;

		if (ITEM_STATE_NOTSUPPORTED == h->state)
			continue;

		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_DBL, h->value.dbl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_UI64, h->value.ui64);
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				pvalue = h->value.str;
				break;
			default:
				continue;
		}

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, pvalue);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_proxy_history_meta                                        *
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type other other than *
 *          ITEM_VALUE_TYPE_LOG containing meta information in result         *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_meta(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	char		buffer[64], *pvalue;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "value", "lastlogsize", "mtime",
			"flags", NULL);

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
					zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_DBL, h->value.dbl);
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
				flags);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_proxy_history_log                                         *
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 * Comment: this function is meant for items with value_type                  *
 *          ITEM_VALUE_TYPE_LOG                                               *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_log(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	zbx_db_insert_t	db_insert;

	/* see hc_copy_history_data() for fields that might be uninitialized and need special handling here */
	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "timestamp", "source", "severity",
			"value", "logeventid", "lastlogsize", "mtime", "flags",  NULL);

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
					lastlogsize, mtime, flags);
		}
		else
		{
			/* sent to server only if not 0, see proxy_get_history_data() */
			const int	unset_if_novalue = 0;

			flags = PROXY_HISTORY_FLAG_META | PROXY_HISTORY_FLAG_NOVALUE;

			zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, unset_if_novalue, "",
					unset_if_novalue, "", unset_if_novalue, h->lastlogsize, h->mtime, flags);
		}
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_proxy_history_notsupported                                *
 *                                                                            *
 * Purpose: helper function for DCmass_proxy_add_history()                    *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_proxy_history_notsupported(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "value", "state", NULL);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (ITEM_STATE_NOTSUPPORTED != h->state)
			continue;

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, ZBX_NULL2EMPTY_STR(h->value.err),
				(int)h->state);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_proxy_add_history                                         *
 *                                                                            *
 * Purpose: inserting new history data after new value is received            *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_proxy_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	const char	*__function_name = "DCmass_proxy_add_history";
	int		i, h_num = 0, h_meta_num = 0, hlog_num = 0, notsupported_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_prepare_history                                           *
 *                                                                            *
 * Purpose: prepare history data using items from configuration cache and     *
 *          generate item changes to be applied and host inventory values to  *
 *          be added                                                          *
 *                                                                            *
 * Parameters: history          - [IN/OUT] array of history data              *
 *             itemids          - [IN] the item identifiers                   *
 *                                     (used for item lookup)                 *
 *             items            - [IN] the items                              *
 *             errcodes         - [IN] item error codes                       *
 *             history_num      - [IN] number of history structures           *
 *             item_diff        - [OUT] the changes in item data              *
 *             inventory_values - [OUT] the inventory values to add           *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_prepare_history(ZBX_DC_HISTORY *history, const zbx_vector_uint64_t *itemids,
		const DC_ITEM *items, const int *errcodes, int history_num, zbx_vector_ptr_t *item_diff,
		zbx_vector_ptr_t *inventory_values)
{
	const char	*__function_name = "DCmass_prepare_history";
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d", __function_name, history_num);

	for (i = 0; i < history_num; i++)
	{
		ZBX_DC_HISTORY	*h = &history[i];
		const DC_ITEM	*item;
		zbx_item_diff_t	*diff;
		int		index;

		if (FAIL == (index = zbx_vector_uint64_bsearch(itemids, h->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		if (SUCCEED != errcodes[index])
		{
			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		item = &items[index];

		if (ITEM_STATUS_ACTIVE != item->status || HOST_STATUS_MONITORED != item->host.status)
		{
			h->flags |= ZBX_DC_FLAG_UNDEF;
			continue;
		}

		if (0 == item->history)
			h->flags |= ZBX_DC_FLAG_NOHISTORY;

		if ((ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type) ||
				0 == item->trends)
		{
			h->flags |= ZBX_DC_FLAG_NOTRENDS;
		}

		normalize_item_value(item, h);

		diff = calculate_item_update(item, h);
		zbx_vector_ptr_append(item_diff, diff);
		DCinventory_value_add(inventory_values, item, h);
	}

	zbx_vector_ptr_sort(inventory_values, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmodule_prepare_history                                         *
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
 * Function: DCsync_history                                                   *
 *                                                                            *
 * Purpose: writes updates and new data from pool to database                 *
 *                                                                            *
 * Return value: the timestamp of next history queue value to sync,           *
 *               0 if the queue is empty or most of items are locked by       *
 *               triggers.                                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	DCsync_history(int sync_type, int *total_num)
{
	const char			*__function_name = "DCsync_history";

	static ZBX_DC_HISTORY		*history = NULL;	/* array of structures where item data from history  */
								/* cache are copied into to process triggers, trends */
								/* etc and finally write into db */
	static ZBX_HISTORY_FLOAT	*history_float;
	static ZBX_HISTORY_INTEGER	*history_integer;
	static ZBX_HISTORY_STRING	*history_string;
	static ZBX_HISTORY_TEXT		*history_text;
	static ZBX_HISTORY_LOG		*history_log;
	int				history_num, candidate_num, next_sync = 0, history_float_num,
					history_integer_num, history_string_num, history_text_num, history_log_num, ret,
					txn_error;
	time_t				sync_start, now;
	zbx_vector_uint64_t		triggerids;
	zbx_vector_ptr_t		history_items, trigger_diff, item_diff, inventory_values;
	zbx_vector_uint64_pair_t	trends_diff;
	zbx_binary_heap_t		tmp_history_queue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d", __function_name, cache->history_num);

	*total_num = 0;

	if (ZBX_SYNC_FULL == sync_type)
	{
		zbx_hashset_iter_t	iter;
		zbx_hc_item_t		*item;

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

		/* unlock all triggers before full sync so no items are locked by triggers */
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			DCconfig_unlock_all_triggers();

		LOCK_CACHE;

		tmp_history_queue = cache->history_queue;

		zbx_binary_heap_create(&cache->history_queue, hc_queue_elem_compare_func, ZBX_BINARY_HEAP_OPTION_EMPTY);
		zbx_hashset_iter_reset(&cache->history_items, &iter);

		/* add all items from history index to the new history queue */
		while (NULL != (item = (zbx_hc_item_t *)zbx_hashset_iter_next(&iter)))
			hc_queue_item(item);

		UNLOCK_CACHE;

		zabbix_log(LOG_LEVEL_WARNING, "syncing history data...");
	}

	if (0 == cache->history_num && 0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		/* try flushing correlated event queue in the case      */
		/* some OK events are queued from the last history sync */
		zbx_flush_correlated_events();
		goto finish;
	}

	sync_start = time(NULL);

	if (NULL == history)
		history = (ZBX_DC_HISTORY *)zbx_malloc(history, ZBX_HC_SYNC_MAX * sizeof(ZBX_DC_HISTORY));

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		if (NULL == history_float && NULL != history_float_cbs)
		{
			history_float = (ZBX_HISTORY_FLOAT *)zbx_malloc(history_float,
					ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_FLOAT));
		}

		if (NULL == history_integer && NULL != history_integer_cbs)
		{
			history_integer = (ZBX_HISTORY_INTEGER *)zbx_malloc(history_integer,
					ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_INTEGER));
		}

		if (NULL == history_string && NULL != history_string_cbs)
		{
			history_string = (ZBX_HISTORY_STRING *)zbx_malloc(history_string,
					ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_STRING));
		}

		if (NULL == history_text && NULL != history_text_cbs)
		{
			history_text = (ZBX_HISTORY_TEXT *)zbx_malloc(history_text,
					ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_TEXT));
		}

		if (NULL == history_log && NULL != history_log_cbs)
		{
			history_log = (ZBX_HISTORY_LOG *)zbx_malloc(history_log,
					ZBX_HC_SYNC_MAX * sizeof(ZBX_HISTORY_LOG));
		}

		zbx_vector_ptr_create(&inventory_values);
		zbx_vector_ptr_create(&item_diff);
		zbx_vector_ptr_create(&trigger_diff);
		zbx_vector_uint64_pair_create(&trends_diff);

		zbx_vector_uint64_create(&triggerids);
		zbx_vector_uint64_reserve(&triggerids, MIN(cache->history_num, ZBX_HC_SYNC_MAX) + 32);
	}

	zbx_vector_ptr_create(&history_items);
	zbx_vector_ptr_reserve(&history_items, MIN(cache->history_num, ZBX_HC_SYNC_MAX) + 32);

	do
	{
		DC_ITEM			*items;
		int			*errcodes, trends_num = 0;
		zbx_vector_uint64_t	itemids;
		ZBX_DC_TREND		*trends = NULL;

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			zbx_vector_uint64_clear(&triggerids);

		LOCK_CACHE;

		hc_pop_items(&history_items);		/* select and take items out of history cache */

		if (0 != history_items.values_num && 0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			history_num = DCconfig_lock_triggers_by_history_items(&history_items, &triggerids);

			/* if there are unavailable items, push them back in history queue */
			if (history_num != history_items.values_num)
				hc_push_busy_items(&history_items);
		}
		else
			history_num = history_items.values_num;

		UNLOCK_CACHE;

		if (0 == history_num)
			break;

		hc_get_item_values(history, &history_items);	/* copy item data from history cache */

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			int	i;

			items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * (size_t)history_num);
			errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)history_num);

			zbx_vector_uint64_create(&itemids);
			zbx_vector_uint64_reserve(&itemids, history_num);

			for (i = 0; i < history_num; i++)
				zbx_vector_uint64_append(&itemids, history[i].itemid);

			zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			DCconfig_get_items_by_itemids(items, itemids.values, errcodes, history_num);

			DCmass_prepare_history(history, &itemids, items, errcodes, history_num, &item_diff,
					&inventory_values);

			/* process values only if they were successfully stored by the history backend */
			if (FAIL != (ret = DBmass_add_history(history, history_num)))
			{
				/* trigger calculation requires up to date information */
				/* in configuration and value caches                   */
				DCconfig_items_apply_changes(&item_diff);
				DCmass_update_trends(history, history_num, &trends, &trends_num);

				do
				{
					DBbegin();

					DBmass_update_items(&item_diff, &inventory_values);
					DBmass_update_triggers(history, history_num, &trigger_diff);
					DBmass_update_trends(trends, trends_num, &trends_diff);

					/* processing of events, generated in functions: */
					/* DBmass_update_items() */
					/* DBmass_update_triggers() */
					if (0 != zbx_process_events(&trigger_diff, &triggerids))
						zbx_db_save_trigger_changes(&trigger_diff);

					if (ZBX_DB_OK == (txn_error = DBcommit()))
					{
						DCconfig_triggers_apply_changes(&trigger_diff);
						DCupdate_trends(&trends_diff);
						DBupdate_itservices(&trigger_diff);
					}
					else
						zbx_clean_events();

					zbx_vector_ptr_clear_ext(&trigger_diff, (zbx_clean_func_t)zbx_trigger_diff_free);

					zbx_vector_uint64_pair_clear(&trends_diff);
				}
				while (ZBX_DB_DOWN == txn_error);
			}

			DCconfig_unlock_triggers(&triggerids);
			zbx_vector_ptr_clear_ext(&inventory_values, (zbx_clean_func_t)DCinventory_value_free);
			zbx_vector_ptr_clear_ext(&item_diff, (zbx_clean_func_t)zbx_ptr_free);
		}
		else
		{
			do
			{
				DBbegin();

				DCmass_proxy_add_history(history, history_num);
				DCmass_proxy_update_items(history, history_num);
			}
			while (ZBX_DB_DOWN == (txn_error = DBcommit()));

			ret = ZBX_DB_OK == txn_error ? SUCCEED : FAIL;
		}

		LOCK_CACHE;

		next_sync = hc_push_processed_items(&history_items);	/* return processed items into history cache */
		cache->history_num -= history_num;

		UNLOCK_CACHE;

		*total_num += history_num;
		candidate_num = history_items.values_num;

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER) && SUCCEED == ret)
		{
			if (SUCCEED == zbx_is_export_enabled())
			{
				DCexport_history_and_trends(history, history_num, &itemids, items, errcodes, trends,
						trends_num);
				zbx_export_events();
			}

			DCmodule_prepare_history(history, history_num, history_float, &history_float_num,
					history_integer, &history_integer_num, history_string, &history_string_num,
					history_text, &history_text_num, history_log, &history_log_num);

			DCmodule_sync_history(history_float_num, history_integer_num, history_string_num,
					history_text_num, history_log_num, history_float, history_integer,
					history_string, history_text, history_log);
		}

		now = time(NULL);

		if (ZBX_SYNC_FULL == sync_type && now - sync_start >= 10)
		{
			zabbix_log(LOG_LEVEL_WARNING, "syncing history data... " ZBX_FS_DBL "%%",
					(double)*total_num / (cache->history_num + *total_num) * 100);
			sync_start = now;
		}

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			zbx_clean_events();
			zbx_free(trends);
			zbx_vector_uint64_destroy(&itemids);
			DCconfig_clean_items(items, errcodes, history_num);
			zbx_free(errcodes);
			zbx_free(items);
		}

		zbx_vector_ptr_clear(&history_items);
		hc_free_item_values(history, history_num);

		if (ZBX_HC_SYNC_MIN_PCNT > history_num * 100 / candidate_num)
		{
			/* Stop sync if only small percentage of sync candidates were processed     */
			/* (meaning most of sync candidates are locked by triggers).                */
			/* In this case is better to wait a bit for other syncers to unlock items   */
			/* rather than trying and failing to sync locked items over and over again. */

			next_sync = 0;
			break;
		}

		/* Exit from sync loop if we have spent too much time here */
		/* unless we are doing full sync. This is done to allow    */
		/* syncer process to update their statistics.              */
	}
	while ((ZBX_HC_SYNC_TIME_MAX >= now - sync_start && 0 != next_sync) || sync_type == ZBX_SYNC_FULL);

	zbx_vector_ptr_destroy(&history_items);
	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		zbx_vector_ptr_destroy(&inventory_values);
		zbx_vector_ptr_destroy(&item_diff);
		zbx_vector_ptr_destroy(&trigger_diff);
		zbx_vector_uint64_pair_destroy(&trends_diff);

		zbx_vector_uint64_destroy(&triggerids);
	}
finish:
	if (ZBX_SYNC_FULL == sync_type)
	{
		LOCK_CACHE;

		zbx_binary_heap_destroy(&cache->history_queue);
		cache->history_queue = tmp_history_queue;

		UNLOCK_CACHE;

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			/* try flushing correlated event queue until it's empty */
			while (0 != zbx_flush_correlated_events())
				;
		}

		zabbix_log(LOG_LEVEL_WARNING, "syncing history data done");
	}

	return next_sync;
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

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history                                                   *
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

	if (0 != (ZBX_FLAG_DISCOVERY_RULE & item_flags))
	{
		if (NULL == GET_TEXT_RESULT(result))
			return;

		/* proxy stores low-level discovery (lld) values in db */
		if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
			dc_local_add_history_lld(itemid, ts, result->text);

		return;
	}

	if (!ISSET_VALUE(result) && !ISSET_META(result))
		return;

	value_flags = 0;

	if (!ISSET_VALUE(result))
		value_flags |= ZBX_DC_FLAG_NOVALUE;

	if (ISSET_META(result))
		value_flags |= ZBX_DC_FLAG_META;

	/* Add data to the local history cache if:                            */
	/*   1) the NOVALUE flag is set (data contains only meta information) */
	/*   2) the NOVALUE flag is not set and value conversion succeeded    */

	if (0 == (value_flags & ZBX_DC_FLAG_NOVALUE))
	{
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
			THIS_SHOULD_NEVER_HAPPEN;

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

struct zbx_hc_data
{
	history_value_t	value;
	zbx_uint64_t	lastlogsize;
	zbx_timespec_t	ts;
	int		mtime;
	unsigned char	value_type;
	unsigned char	flags;
	unsigned char	state;

	struct zbx_hc_data	*next;
};

/******************************************************************************
 *                                                                            *
 * Function: hc_queue_elem_compare_func                                       *
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
 * Function: hc_free_data                                                     *
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
 * Function: hc_queue_item                                                    *
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
 * Function: hc_get_item                                                      *
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
 * Function: hc_add_item                                                      *
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
	zbx_hc_item_t	item_local = {itemid, ZBX_HC_ITEM_STATUS_NORMAL, data, data};

	return (zbx_hc_item_t *)zbx_hashset_insert(&cache->history_items, &item_local, sizeof(item_local));
}

/******************************************************************************
 *                                                                            *
 * Function: hc_mem_value_str_dup                                             *
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
 * Function: hc_clone_history_str_data                                        *
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
 * Function: hc_clone_history_log_data                                        *
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
 * Function: hc_clone_history_data                                            *
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
 * Function: hc_add_item_values                                               *
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

		while (SUCCEED != hc_clone_history_data(&data, item_value))
		{
			UNLOCK_CACHE;

			zabbix_log(LOG_LEVEL_DEBUG, "History buffer is full. Sleeping for 1 second.");
			sleep(1);

			LOCK_CACHE;
		}

		if (NULL == (item = hc_get_item(item_value->itemid)))
		{
			item = hc_add_item(item_value->itemid, data);
			hc_queue_item(item);
		}
		else
		{
			item->head->next = data;
			item->head = data;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: hc_copy_history_data                                             *
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
 * Function: hc_pop_items                                                     *
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
 * Function: hc_get_item_values                                               *
 *                                                                            *
 * Purpose: gets item history values                                          *
 *                                                                            *
 * Parameters: history       - [OUT] the history valeus                       *
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
		/* busy items were replaced with NULL values in hc_push_busy_items() function */
		if (NULL == (item = (zbx_hc_item_t *)history_items->values[i]))
			continue;

		hc_copy_history_data(&history[history_num++], item->itemid, item->tail);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: hc_push_busy_items                                               *
 *                                                                            *
 * Purpose: push back the busy (locked by triggers) items into history cache  *
 *                                                                            *
 * Parameters: history_items - [IN] the history items                         *
 *                                                                            *
 ******************************************************************************/
static void	hc_push_busy_items(zbx_vector_ptr_t *history_items)
{
	int		i;
	zbx_hc_item_t	*item;

	for (i = 0; i < history_items->values_num; i++)
	{
		item = (zbx_hc_item_t *)history_items->values[i];

		if (ZBX_HC_ITEM_STATUS_NORMAL == item->status)
			continue;

		/* reset item status before returning it to queue */
		item->status = ZBX_HC_ITEM_STATUS_NORMAL;
		hc_queue_item(item);

		/* After pushing back to queue current syncer has released ownership of this item. */
		/* To avoid using it further reset the item reference in vector to NULL.           */
		history_items->values[i] = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: hc_push_processed_items                                          *
 *                                                                            *
 * Purpose: push back the processed history items into history cache          *
 *                                                                            *
 * Parameters: history_items - [IN] the history items containing processed    *
 *                                  (available) and busy items                *
 *                                                                            *
 * Return value: time of the next history item to sync                        *
 *                                                                            *
 * Comments: This function removes processed value from history cache.        *
 *           If there is no more data for this item, then the item itself is  *
 *           removed from history index.                                      *
 *                                                                            *
 ******************************************************************************/
static int	hc_push_processed_items(zbx_vector_ptr_t *history_items)
{
	int		i;
	zbx_hc_item_t	*item;
	zbx_hc_data_t	*data_free;
	int		next_sync;

	for (i = 0; i < history_items->values_num; i++)
	{
		/* busy items were replaced with NULL values in hc_push_busy_items() function */
		if (NULL == (item = (zbx_hc_item_t *)history_items->values[i]))
			continue;

		data_free = item->tail;
		item->tail = item->tail->next;
		hc_free_data(data_free);

		if (NULL == item->tail)
		{
			zbx_hashset_remove(&cache->history_items, item);
			continue;
		}

		hc_queue_item(item);
	}

	if (FAIL == zbx_binary_heap_empty(&cache->history_queue))
	{
		zbx_binary_heap_elem_t	*elem;

		elem = zbx_binary_heap_find_min(&cache->history_queue);
		item = (zbx_hc_item_t *)elem->data;

		next_sync = item->tail->ts.sec;
	}
	else
		next_sync = 0;

	return next_sync;
}

/******************************************************************************
 *                                                                            *
 * Function: init_trend_cache                                                 *
 *                                                                            *
 * Purpose: Allocate shared memory for trend cache (part of database cache)   *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments: Is optionally called from init_database_cache()                  *
 *                                                                            *
 ******************************************************************************/

ZBX_MEM_FUNC_IMPL(__trend, trend_mem);

static int	init_trend_cache(char **error)
{
	const char	*__function_name = "init_trend_cache";
	size_t		sz;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: init_database_cache                                              *
 *                                                                            *
 * Purpose: Allocate shared memory for database cache                         *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 ******************************************************************************/
int	init_database_cache(char **error)
{
	const char	*__function_name = "init_database_cache";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
		if (SUCCEED != (ret = init_trend_cache(error)))
			goto out;
	}

	if (NULL == sql)
		sql = (char *)zbx_malloc(sql, sql_alloc);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_all                                                       *
 *                                                                            *
 * Purpose: writes updates and new data from pool and cache data to database  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_all(void)
{
	int	sync_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCsync_all()");

	DCsync_history(ZBX_SYNC_FULL, &sync_num);
	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		DCsync_trends();

	zabbix_log(LOG_LEVEL_DEBUG, "End of DCsync_all()");
}

/******************************************************************************
 *                                                                            *
 * Function: free_database_cache                                              *
 *                                                                            *
 * Purpose: Free memory allocated for database cache                          *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 ******************************************************************************/
void	free_database_cache(void)
{
	const char	*__function_name = "free_database_cache";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DCsync_all();

	cache = NULL;

	zbx_mutex_destroy(&cache_lock);
	zbx_mutex_destroy(&cache_ids_lock);

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		zbx_mutex_destroy(&trends_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_nextid                                                     *
 *                                                                            *
 * Purpose: Return next id for requested table                                *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_nextid(const char *table_name, int num)
{
	const char	*__function_name = "DCget_nextid";
	int		i;
	DB_RESULT	result;
	DB_ROW		row;
	const ZBX_TABLE	*table;
	ZBX_DC_ID	*id;
	zbx_uint64_t	min = 0, max = ZBX_DB_MAX_ID, nextid, lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' num:%d",
			__function_name, table_name, num);

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
					__function_name, table_name, nextid, lastid);

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
			__function_name, table_name, nextid, lastid);

	return nextid;
}

/******************************************************************************
 *                                                                            *
 * Function: DCupdate_hosts_availability                                      *
 *                                                                            *
 * Purpose: performs host availability reset for hosts with availability set  *
 *          on interfaces without enabled items                               *
 *                                                                            *
 ******************************************************************************/
void	DCupdate_hosts_availability(void)
{
	const char		*__function_name = "DCupdate_hosts_availability";
	zbx_vector_ptr_t	hosts;
	char			*sql_buf = NULL;
	size_t			sql_buf_alloc = 0, sql_buf_offset = 0;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&hosts);

	if (SUCCEED != DCreset_hosts_availability(&hosts))
		goto out;

	DBbegin();
	DBbegin_multiple_update(&sql_buf, &sql_buf_alloc, &sql_buf_offset);

	for (i = 0; i < hosts.values_num; i++)
	{
		if (SUCCEED == zbx_sql_add_host_availability(&sql_buf, &sql_buf_alloc, &sql_buf_offset,
				(zbx_host_availability_t *)hosts.values[i]))
		{
			zbx_strcpy_alloc(&sql_buf, &sql_buf_alloc, &sql_buf_offset, ";\n");
		}

		DBexecute_overflowed_sql(&sql_buf, &sql_buf_alloc, &sql_buf_offset);
	}

	DBend_multiple_update(&sql_buf, &sql_buf_alloc, &sql_buf_offset);

	if (16 < sql_buf_offset)
		DBexecute("%s", sql_buf);

	DBcommit();

	zbx_free(sql_buf);
out:
	zbx_vector_ptr_clear_ext(&hosts, (zbx_mem_free_func_t)zbx_host_availability_free);
	zbx_vector_ptr_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
