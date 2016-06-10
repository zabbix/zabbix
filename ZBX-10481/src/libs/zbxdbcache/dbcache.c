/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#define ZBX_IDS_SIZE	10

#define ZBX_HC_ITEMS_INIT_SIZE	1000

#define ZBX_TRENDS_CLEANUP_TIME	((SEC_PER_HOUR * 55) / 60)

/* the maximum time spent synchronizing history */
#define ZBX_HC_SYNC_TIME_MAX	SEC_PER_MIN

/* the maximum number of items in one synchronization batch */
#define ZBX_HC_SYNC_MAX		1000

/* the minimum processed item percentage of item candidates to continue synchronizing */
#define ZBX_HC_SYNC_MIN_PCNT	10

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

typedef struct
{
	zbx_uint64_t	itemid;
	history_value_t	value_orig;	/* uninitialized if ZBX_DC_FLAG_NOVALUE is set */
	history_value_t	value;		/* uninitialized if ZBX_DC_FLAG_NOVALUE is set, source for log items */
	zbx_uint64_t	lastlogsize;
	zbx_timespec_t	ts;
	int		timestamp;	/* uninitialized if ZBX_DC_FLAG_NOVALUE is set */
	int		severity;	/* uninitialized if ZBX_DC_FLAG_NOVALUE is set */
	int		logeventid;	/* uninitialized if ZBX_DC_FLAG_NOVALUE is set */
	int		mtime;
	unsigned char	value_type;
	unsigned char	flags;		/* see ZBX_DC_FLAG_* above */
	unsigned char	keep_history;
	unsigned char	keep_trends;
	unsigned char	state;
}
ZBX_DC_HISTORY;

typedef struct
{
	zbx_uint64_t	hostid;
	const char	*field_name;
	char		*value_esc;
}
zbx_inventory_value_t;

/* value_avg_t structure is used for item average value trend calculations. */
/*                                                                          */
/* For double values the average value is calculated on the fly with the    */
/* following formula: avg = (dbl * count + value) / (count + 1) and stored  */
/* into dbl member.                                                         */
/* For uint64 values the item values are summed into ui64 member and the    */
/* average value is calculated before flushing trends to database:          */
/* avg = ui64 / count                                                       */
typedef union
{
	double		dbl;
	zbx_uint128_t	ui64;
}
value_avg_t;

typedef struct
{
	zbx_uint64_t	itemid;
	history_value_t	value_min;
	value_avg_t	value_avg;
	history_value_t	value_max;
	int		clock;
	int		num;
	int		disable_from;
	unsigned char	value_type;
}
ZBX_DC_TREND;

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

	zbx_timespec_t		last_ts;
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
	unsigned char	value_type;
	unsigned char	state;
	unsigned char	flags;		/* see ZBX_DC_FLAG_* above */
}
dc_item_value_t;

static char		*string_values = NULL;
static size_t		string_values_alloc = 0, string_values_offset = 0;
static dc_item_value_t	*item_values = NULL;
static size_t		item_values_alloc = 0, item_values_num = 0;

static void	hc_add_item_values(dc_item_value_t *item_values, int item_values_num);
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
 * Function: DCflush_trends                                                   *
 *                                                                            *
 * Purpose: flush trend to the database                                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DCflush_trends(ZBX_DC_TREND *trends, int *trends_num, int update_cache)
{
	const char	*__function_name = "DCflush_trends";
	DB_RESULT	result;
	DB_ROW		row;
	size_t		sql_offset;
	int		num, i, clock, inserts_num = 0, ids_alloc, ids_num = 0, trends_to = *trends_num;
	history_value_t	value_min, value_avg, value_max;
	unsigned char	value_type;
	zbx_uint64_t	*ids = NULL, itemid;
	ZBX_DC_TREND	*trend = NULL;
	const char	*table_name;
	zbx_db_insert_t	db_insert;

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

	ids_alloc = MIN(ZBX_HC_SYNC_MAX, *trends_num);
	ids = zbx_malloc(ids, ids_alloc * sizeof(zbx_uint64_t));

	for (i = 0; i < *trends_num; i++)
	{
		trend = &trends[i];

		if (clock != trend->clock || value_type != trend->value_type)
			continue;

		inserts_num++;

		if (0 != trend->disable_from)
			continue;

		uint64_array_add(&ids, &ids_alloc, &ids_num, trend->itemid, 64);

		if (ZBX_HC_SYNC_MAX == ids_num)
		{
			trends_to = i + 1;
			break;
		}
	}

	if (0 != ids_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct itemid"
				" from %s"
				" where clock>=%d and",
				table_name, clock);

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", ids, ids_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);
			uint64_array_remove(ids, &ids_num, &itemid, 1);
		}
		DBfree_result(result);

		while (0 != ids_num)
		{
			itemid = ids[--ids_num];

			for (i = 0; i < trends_to; i++)
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

	for (i = 0; i < trends_to; i++)
	{
		trend = &trends[i];

		if (clock != trend->clock || value_type != trend->value_type)
			continue;

		if (0 != trend->disable_from && trend->disable_from <= clock)
			continue;

		uint64_array_add(&ids, &ids_alloc, &ids_num, trend->itemid, 64);
	}

	if (0 != ids_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select itemid,num,value_min,value_avg,value_max"
				" from %s"
				" where clock=%d and",
				table_name, clock);

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", ids, ids_num);

		result = DBselect("%s", sql);

		sql_offset = 0;
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);

			for (i = 0; i < trends_to; i++)
			{
				trend = &trends[i];

				if (itemid != trend->itemid)
					continue;

				if (clock != trend->clock || value_type != trend->value_type)
					continue;

				break;
			}

			if (i == trends_to)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			num = atoi(row[1]);

			if (value_type == ITEM_VALUE_TYPE_FLOAT)
			{
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

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
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
			else
			{
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

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
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

			trend->itemid = 0;

			inserts_num--;

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
		DBfree_result(result);

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (sql_offset > 16)	/* In ORACLE always present begin..end; */
			DBexecute("%s", sql);
	}

	zbx_free(ids);

	/* if 'trends' is not a primary trends buffer */
	if (0 != update_cache)
	{
		/* we update it too */
		LOCK_TRENDS;

		for (i = 0; i < trends_to; i++)
		{
			if (0 == trends[i].itemid)
				continue;

			if (clock != trends[i].clock || value_type != trends[i].value_type)
				continue;

			if (0 == trends[i].disable_from || trends[i].disable_from > clock)
				continue;

			if (NULL != (trend = zbx_hashset_search(&cache->trends, &trends[i].itemid)))
				trend->disable_from = clock + SEC_PER_HOUR;
		}

		UNLOCK_TRENDS;
	}

	if (0 != inserts_num)
	{
		zbx_db_insert_prepare(&db_insert, table_name, "itemid", "clock", "num", "value_min", "value_avg",
				"value_max", NULL);

		for (i = 0; i < trends_to; i++)
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
		*trends = zbx_realloc(*trends, *trends_alloc * sizeof(ZBX_DC_TREND));
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
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_update_trends(ZBX_DC_HISTORY *history, int history_num)
{
	const char	*__function_name = "DCmass_update_trends";
	ZBX_DC_TREND	*trends = NULL;
	zbx_timespec_t	ts;
	int		trends_alloc = 0, trends_num = 0, i, hour, seconds;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_timespec(&ts);
	seconds = ts.sec % SEC_PER_HOUR;
	hour = ts.sec - seconds;

	LOCK_TRENDS;

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
			continue;

		if (0 == h->keep_trends)
			continue;

		DCadd_trend(h, &trends, &trends_alloc, &trends_num);
	}

	if (cache->trends_last_cleanup_hour < hour && ZBX_TRENDS_CLEANUP_TIME < seconds)
	{
		zbx_hashset_iter_t	iter;
		ZBX_DC_TREND		*trend;

		zbx_hashset_iter_reset(&cache->trends, &iter);

		while (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_iter_next(&iter)))
		{
			if (trend->clock != hour)
			{
				DCflush_trend(trend, &trends, &trends_alloc, &trends_num);
				zbx_hashset_iter_remove(&iter);
			}
		}

		cache->trends_last_cleanup_hour = hour;
	}

	UNLOCK_TRENDS;

	while (0 < trends_num)
		DCflush_trends(trends, &trends_num, 1);

	zbx_free(trends);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static void	DCsync_trends()
{
	const char		*__function_name = "DCsync_trends";
	zbx_hashset_iter_t	iter;
	ZBX_DC_TREND		*trends = NULL, *trend;
	int			trends_alloc = 0, trends_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d", __function_name, cache->trends_num);

	zabbix_log(LOG_LEVEL_WARNING, "syncing trends data...");

	LOCK_TRENDS;

	zbx_hashset_iter_reset(&cache->trends, &iter);

	while (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_iter_next(&iter)))
		DCflush_trend(trend, &trends, &trends_alloc, &trends_num);

	UNLOCK_TRENDS;

	DBbegin();

	while (trends_num > 0)
		DCflush_trends(trends, &trends_num, 0);

	DBcommit();

	zbx_free(trends);

	zabbix_log(LOG_LEVEL_WARNING, "syncing trends data done");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_update_triggers                                           *
 *                                                                            *
 * Purpose: re-calculate and update values of triggers related to the items   *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_update_triggers(ZBX_DC_HISTORY *history, int history_num)
{
	const char		*__function_name = "DCmass_update_triggers";
	int			i, item_num = 0;
	zbx_uint64_t		*itemids = NULL;
	zbx_timespec_t		*timespecs = NULL;
	zbx_hashset_t		trigger_info;
	zbx_vector_ptr_t	trigger_order;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	itemids = zbx_malloc(itemids, sizeof(zbx_uint64_t) * history_num);
	timespecs = zbx_malloc(timespecs, sizeof(zbx_timespec_t) * history_num);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
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

	DCconfig_get_triggers_by_itemids(&trigger_info, &trigger_order, itemids, timespecs, NULL, item_num,
			ZBX_EXPAND_MACROS);

	if (0 == trigger_order.values_num)
		goto clean_triggers;

	evaluate_expressions(&trigger_order);

	process_triggers(&trigger_order);

	DCfree_triggers(&trigger_order);
clean_triggers:
	zbx_hashset_destroy(&trigger_info);
	zbx_vector_ptr_destroy(&trigger_order);
clean_items:
	zbx_free(timespecs);
	zbx_free(itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	DBchk_double(double value)
{
	/* field with precision 16, scale 4 [NUMERIC(16,4)] */
	const double	pg_min_numeric = -1e12;
	const double	pg_max_numeric = 1e12;

	if (value <= pg_min_numeric || value >= pg_max_numeric)
		return FAIL;

	return SUCCEED;
}

static double	multiply_item_value_float(DC_ITEM *item, double value)
{
	double	value_double;

	if (ITEM_MULTIPLIER_USE != item->multiplier)
		return value;

	value_double = value * atof(item->formula);

	zabbix_log(LOG_LEVEL_DEBUG, "multiply_item_value_float() " ZBX_FS_DBL ",%s " ZBX_FS_DBL,
			value, item->formula, value_double);

	return value_double;
}

static zbx_uint64_t	multiply_item_value_uint64(DC_ITEM *item, zbx_uint64_t value)
{
	zbx_uint64_t	formula_uint64, value_uint64;

	if (ITEM_MULTIPLIER_USE != item->multiplier)
		return value;

	if (SUCCEED == is_uint64(item->formula, &formula_uint64))
		value_uint64 = value * formula_uint64;
	else
		value_uint64 = (zbx_uint64_t)((double)value * atof(item->formula));

	zabbix_log(LOG_LEVEL_DEBUG, "multiply_item_value_uint64() " ZBX_FS_UI64 ",%s " ZBX_FS_UI64,
			value, item->formula, value_uint64);

	return value_uint64;
}

/******************************************************************************
 *                                                                            *
 * Function: DCcalculate_item_delta_float                                     *
 *                                                                            *
 * Purpose: calculate delta value for items of float value type               *
 *                                                                            *
 * Parameters: item      - [IN] item reference                                *
 *             h         - [IN/OUT] a reference to history cache value        *
 *             deltaitem - [IN] a reference to the last raw history value     *
 *                         (value + timestamp)                                *
 *                                                                            *
 ******************************************************************************/
static void	DCcalculate_item_delta_float(DC_ITEM *item, ZBX_DC_HISTORY *h, zbx_item_history_value_t *deltaitem)
{
	switch (item->delta)
	{
		case ITEM_STORE_AS_IS:
			h->value.dbl = multiply_item_value_float(item, h->value_orig.dbl);

			if (SUCCEED != DBchk_double(h->value.dbl))
			{
				h->state = ITEM_STATE_NOTSUPPORTED;
				h->flags |= ZBX_DC_FLAG_UNDEF;
			}

			break;
		case ITEM_STORE_SPEED_PER_SECOND:
			if (0 != deltaitem->timestamp.sec && deltaitem->value.dbl <= h->value_orig.dbl &&
					0 > zbx_timespec_compare(&deltaitem->timestamp, &h->ts))
			{
				h->value.dbl = (h->value_orig.dbl - deltaitem->value.dbl) /
						((h->ts.sec - deltaitem->timestamp.sec) +
							(double)(h->ts.ns - deltaitem->timestamp.ns) / 1000000000);
				h->value.dbl = multiply_item_value_float(item, h->value.dbl);

				if (SUCCEED != DBchk_double(h->value.dbl))
				{
					h->state = ITEM_STATE_NOTSUPPORTED;
					h->flags |= ZBX_DC_FLAG_UNDEF;
				}
			}
			else
				h->flags |= ZBX_DC_FLAG_UNDEF;

			break;
		case ITEM_STORE_SIMPLE_CHANGE:
			if (0 != deltaitem->timestamp.sec && deltaitem->value.dbl <= h->value_orig.dbl)
			{
				h->value.dbl = h->value_orig.dbl - deltaitem->value.dbl;
				h->value.dbl = multiply_item_value_float(item, h->value.dbl);

				if (SUCCEED != DBchk_double(h->value.dbl))
				{
					h->state = ITEM_STATE_NOTSUPPORTED;
					h->flags |= ZBX_DC_FLAG_UNDEF;
				}
			}
			else
				h->flags |= ZBX_DC_FLAG_UNDEF;

			break;
	}

	if (ITEM_STATE_NOTSUPPORTED == h->state)
	{
		int	errcode = SUCCEED;

		h->value_orig.err = zbx_dsprintf(NULL, "Type of received value"
				" [" ZBX_FS_DBL "] is not suitable for value type [%s]",
				h->value.dbl, zbx_item_value_type_string(item->value_type));

		DCrequeue_items(&h->itemid, &h->state, &h->ts.sec, NULL, NULL, &errcode, 1);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCcalculate_item_delta_uint64                                    *
 *                                                                            *
 * Purpose: calculate delta value for items of uint64 value type              *
 *                                                                            *
 * Parameters: item      - [IN] item reference                                *
 *             h         - [IN/OUT] a reference to history cache value        *
 *             deltaitem - [IN] a reference to the last raw history value     *
 *                         (value + timestamp)                                *
 *                                                                            *
 ******************************************************************************/
static void	DCcalculate_item_delta_uint64(DC_ITEM *item, ZBX_DC_HISTORY *h, zbx_item_history_value_t *deltaitem)
{
	switch (item->delta)
	{
		case ITEM_STORE_AS_IS:
			h->value.ui64 = multiply_item_value_uint64(item, h->value_orig.ui64);

			break;
		case ITEM_STORE_SPEED_PER_SECOND:
			if (0 != deltaitem->timestamp.sec && deltaitem->value.ui64 <= h->value_orig.ui64 &&
					0 > zbx_timespec_compare(&deltaitem->timestamp, &h->ts))
			{
				h->value.ui64 = (h->value_orig.ui64 - deltaitem->value.ui64) /
						((h->ts.sec - deltaitem->timestamp.sec) +
							(double)(h->ts.ns - deltaitem->timestamp.ns) / 1000000000);
				h->value.ui64 = multiply_item_value_uint64(item, h->value.ui64);
			}
			else
				h->flags |= ZBX_DC_FLAG_UNDEF;

			break;
		case ITEM_STORE_SIMPLE_CHANGE:
			if (0 != deltaitem->timestamp.sec && deltaitem->value.ui64 <= h->value_orig.ui64)
			{
				h->value.ui64 = h->value_orig.ui64 - deltaitem->value.ui64;
				h->value.ui64 = multiply_item_value_uint64(item, h->value.ui64);
			}
			else
				h->flags |= ZBX_DC_FLAG_UNDEF;

			break;
	}
}

zbx_item_history_value_t	*DCget_deltaitem(zbx_hashset_t *delta_history, DC_ITEM *item, ZBX_DC_HISTORY *h)
{
	zbx_item_history_value_t	*deltaitem;

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		return NULL;

	if (ITEM_STORE_AS_IS == item->delta)
		return NULL;

	deltaitem = zbx_hashset_search(delta_history, &item->itemid);

	if (ITEM_STATE_NOTSUPPORTED == h->state)
		return deltaitem;

	if (NULL == deltaitem)
	{
		zbx_item_history_value_t	value = {item->itemid};

		deltaitem = zbx_hashset_insert(delta_history, &value, sizeof(value));
	}

	return deltaitem;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_update_item_sql                                            *
 *                                                                            *
 * Purpose: 1) generate sql for updating item in database                     *
 *          2) calculate item delta value                                     *
 *          3) add events (item supported/not supported)                      *
 *          4) update cache (requeue item, add nextcheck)                     *
 *                                                                            *
 * Parameters: item - [IN/OUT] item reference                                 *
 *             h    - [IN/OUT] a reference to history cache value             *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_update_item_sql(size_t *sql_offset, DC_ITEM *item, ZBX_DC_HISTORY *h,
		zbx_hashset_t *delta_history)
{
	char				*value_esc;
	const char			*sql_start = "update items set ", *sql_continue = ",";
	zbx_item_history_value_t	*deltaitem;

	deltaitem = DCget_deltaitem(delta_history, item, h);

	if (ITEM_STATE_NOTSUPPORTED == h->state)
		goto notsupported;

	if (0 == (ZBX_DC_FLAG_NOVALUE & h->flags))
	{
		switch (item->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				DCcalculate_item_delta_float(item, h, deltaitem);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				DCcalculate_item_delta_uint64(item, h, deltaitem);
				break;
		}
	}

	if (0 != (ZBX_DC_FLAG_META & h->flags))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%slastlogsize=" ZBX_FS_UI64 ",mtime=%d",
				sql_start, h->lastlogsize, h->mtime);
		sql_start = sql_continue;
	}

notsupported:
	/* update the last value (raw) of the delta items */
	if (NULL != deltaitem)
	{
		/* set timestamp.sec to zero to remove this record from delta items later */
		if (ITEM_STATE_NOTSUPPORTED == h->state || ITEM_STORE_AS_IS == item->delta)
		{
			deltaitem->timestamp.sec = 0;
		}
		else
		{
			deltaitem->timestamp = h->ts;
			deltaitem->value = h->value_orig;
		}
	}

	if (ITEM_STATE_NOTSUPPORTED == h->state)
	{
		int	update_cache = 0;

		if (ITEM_STATE_NOTSUPPORTED != item->db_state)
		{
			unsigned char	object;

			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" became not supported: %s",
					item->host.host, item->key_orig, h->value_orig.err);

			object = (0 != (ZBX_FLAG_DISCOVERY_RULE & item->flags) ?
					EVENT_OBJECT_LLDRULE : EVENT_OBJECT_ITEM);
			add_event(0, EVENT_SOURCE_INTERNAL, object, item->itemid, &h->ts, h->state, NULL, NULL, 0, 0);

			zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%sstate=%d", sql_start, (int)h->state);
			sql_start = sql_continue;

			update_cache = 1;
		}

		if (0 != strcmp(item->db_error, h->value_orig.err))
		{
			value_esc = DBdyn_escape_string_len(h->value_orig.err, ITEM_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%serror='%s'", sql_start, value_esc);
			sql_start = sql_continue;

			if (ITEM_STATE_NOTSUPPORTED == item->db_state)
			{
				zabbix_log(LOG_LEVEL_WARNING, "error reason for \"%s:%s\" changed: %s", item->host.host,
						item->key_orig, h->value_orig.err);
			}

			zbx_free(value_esc);

			update_cache = 1;
		}

		DCadd_nextcheck(item->itemid, &h->ts, h->value_orig.err);

		if (0 != update_cache)
			DCconfig_set_item_db_state(item->itemid, h->state, h->value_orig.err);
	}
	else
	{
		if (ITEM_STATE_NOTSUPPORTED == item->db_state)
		{
			zabbix_log(LOG_LEVEL_WARNING, "item \"%s:%s\" became supported",
					item->host.host, item->key_orig);

			/* we know it's EVENT_OBJECT_ITEM because LLDRULE that becomes */
			/* supported is handled in lld_process_discovery_rule()        */
			add_event(0, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, item->itemid, &h->ts, h->state,
					NULL, NULL, 0, 0);

			zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%sstate=%d,error=''", sql_start,
					(int)h->state);
			sql_start = sql_continue;

			DCconfig_set_item_db_state(item->itemid, h->state, "");
		}
	}
	if (sql_start == sql_continue)
		zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, " where itemid=" ZBX_FS_UI64 ";\n", item->itemid);
}

static void	DCinventory_value_add(zbx_vector_ptr_t *inventory_values, DC_ITEM *item, ZBX_DC_HISTORY *h)
{
	char			value[MAX_BUFFER_LEN];
	const char		*inventory_field;
	unsigned short		inventory_field_len;
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
			strscpy(value, h->value_orig.str);
			break;
		default:
			return;
	}

	zbx_format_value(value, sizeof(value), item->valuemapid, item->units, h->value_type);

	inventory_field_len = DBget_inventory_field_len(item->inventory_link);

	inventory_value = zbx_malloc(NULL, sizeof(zbx_inventory_value_t));

	inventory_value->hostid = item->host.hostid;
	inventory_value->field_name = inventory_field;
	inventory_value->value_esc = DBdyn_escape_string_len(value, inventory_field_len);

	zbx_vector_ptr_append(inventory_values, inventory_value);
}

static void	DCadd_update_inventory_sql(size_t *sql_offset, zbx_vector_ptr_t *inventory_values)
{
	int	i;

	if (0 == inventory_values->values_num)
		return;

	for (i = 0; i < inventory_values->values_num; i++)
	{
		zbx_inventory_value_t	*inventory_value = (zbx_inventory_value_t *)inventory_values->values[i];

		zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset,
				"update host_inventory set %s='%s' where hostid=" ZBX_FS_UI64 ";\n",
				inventory_value->field_name, inventory_value->value_esc, inventory_value->hostid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, sql_offset);
	}
}

static void	DCinventory_value_free(zbx_inventory_value_t *inventory_value)
{
	zbx_free(inventory_value->value_esc);
	zbx_free(inventory_value);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_update_items                                              *
 *                                                                            *
 * Purpose: update items info after new value is received                     *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev, Alexander Vladishev            *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_update_items(ZBX_DC_HISTORY *history, int history_num)
{
	const char		*__function_name = "DCmass_update_items";

	size_t			sql_offset = 0;
	ZBX_DC_HISTORY		*h;
	zbx_vector_uint64_t	ids;
	DC_ITEM			*items = NULL;
	int			i, j, *errcodes = NULL;
	zbx_hashset_t		delta_history = {NULL};
	zbx_vector_ptr_t	inventory_values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	items = zbx_malloc(items, sizeof(DC_ITEM) * history_num);
	errcodes = zbx_malloc(errcodes, sizeof(int) * history_num);
	zbx_hashset_create(&delta_history, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&inventory_values);
	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_reserve(&ids, history_num);

	for (i = 0; i < history_num; i++)
		zbx_vector_uint64_append(&ids, history[i].itemid);

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DCconfig_get_items_by_itemids(items, ids.values, errcodes, history_num);
	DCget_delta_items(&delta_history, &ids);

	zbx_vector_uint64_clear(&ids);	/* item ids that are not disabled and not deleted in DB */

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < history_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (ITEM_STATUS_ACTIVE != items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != items[i].host.status)
			continue;

		for (j = 0; j < history_num; j++)
		{
			if (items[i].itemid == history[j].itemid)
				break;
		}

		if (history_num == j)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		h = &history[j];

		if (ITEM_STATE_NORMAL == h->state && h->value_type != items[i].value_type)
			continue;

		h->keep_history = (0 != items[i].history);
		if (ITEM_VALUE_TYPE_FLOAT == items[i].value_type || ITEM_VALUE_TYPE_UINT64 == items[i].value_type)
			h->keep_trends = (0 != items[i].trends);

		DCadd_update_item_sql(&sql_offset, &items[i], h, &delta_history);
		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		DCinventory_value_add(&inventory_values, &items[i], h);

		zbx_vector_uint64_append(&ids, items[i].itemid);
	}

	zbx_vector_ptr_sort(&inventory_values, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	DCadd_update_inventory_sql(&sql_offset, &inventory_values);

	zbx_vector_ptr_clear_ext(&inventory_values, (zbx_clean_func_t)DCinventory_value_free);
	zbx_vector_ptr_destroy(&inventory_values);

	/* disable processing of deleted and disabled items by setting ZBX_DC_FLAG_UNDEF flag */
	for (i = 0; i < history_num; i++)
	{
		if (FAIL == zbx_vector_uint64_bsearch(&ids, history[i].itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			history[i].flags |= ZBX_DC_FLAG_UNDEF;
	}

	zbx_vector_uint64_destroy(&ids);

	DCset_delta_items(&delta_history);

	DCconfig_clean_items(items, errcodes, history_num);

	zbx_hashset_destroy(&delta_history);
	zbx_free(errcodes);
	zbx_free(items);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
		DBexecute("%s", sql);
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

	size_t		sql_offset = 0;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < history_num; i++)
	{
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history_dbl                                               *
 *                                                                            *
 * Purpose: helper function for DCmass_add_history()                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_history_dbl(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "history", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
			continue;

		if (0 == h->keep_history)
			continue;

		if (ITEM_VALUE_TYPE_FLOAT != h->value_type)
			continue;

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.dbl);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history_uint                                              *
 *                                                                            *
 * Purpose: helper function for DCmass_add_history()                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_history_uint(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "history_uint", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
			continue;

		if (0 == h->keep_history)
			continue;

		if (ITEM_VALUE_TYPE_UINT64 != history[i].value_type)
			continue;

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value.ui64);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history_str                                               *
 *                                                                            *
 * Purpose: helper function for DCmass_add_history()                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_history_str(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "history_str", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
			continue;

		if (0 == h->keep_history)
			continue;

		if (ITEM_VALUE_TYPE_STR != h->value_type)
			continue;

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value_orig.str);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history_text                                              *
 *                                                                            *
 * Purpose: helper function for DCmass_add_history()                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_history_text(ZBX_DC_HISTORY *history, int history_num, int htext_num)
{
	int		i;
	zbx_uint64_t	id;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "history_text", "id", "itemid", "clock", "ns", "value", NULL);

	id = DBget_maxid_num("history_text", htext_num);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
			continue;

		if (0 == h->keep_history)
			continue;

		if (ITEM_VALUE_TYPE_TEXT != h->value_type)
			continue;

		zbx_db_insert_add_values(&db_insert, id++, h->itemid, h->ts.sec, h->ts.ns, h->value_orig.str);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history_log                                               *
 *                                                                            *
 * Purpose: helper function for DCmass_add_history()                          *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_history_log(ZBX_DC_HISTORY *history, int history_num, int hlog_num)
{
	int			i;
	zbx_uint64_t		id;
	zbx_db_insert_t		db_insert;

	zbx_db_insert_prepare(&db_insert, "history_log", "id", "itemid", "clock", "ns", "timestamp", "source",
			"severity", "value", "logeventid", NULL);

	id = DBget_maxid_num("history_log", hlog_num);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
			continue;

		if (0 == h->keep_history)
			continue;

		if (ITEM_VALUE_TYPE_LOG != h->value_type)
			continue;

		zbx_db_insert_add_values(&db_insert, id++, h->itemid, h->ts.sec, h->ts.ns, h->timestamp,
				NULL != h->value.str ? h->value.str : "", h->severity, h->value_orig.str,
				h->logeventid);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_add_history                                               *
 *                                                                            *
 * Purpose: inserting new history data after new value is received            *
 *                                                                            *
 * Parameters: history     - array of history data                            *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	const char	*__function_name = "DCmass_add_history";

	int		i, h_num = 0, huint_num = 0, hstr_num = 0, htext_num = 0, hlog_num = 0, rc = ZBX_DB_OK;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < history_num; i++)
	{
		const ZBX_DC_HISTORY	*h = &history[i];

		if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
			continue;

		if (0 == h->keep_history)
			continue;

		switch (h->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				h_num++;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				huint_num++;
				break;
			case ITEM_VALUE_TYPE_STR:
				hstr_num++;
				break;
			case ITEM_VALUE_TYPE_TEXT:
				htext_num++;
				break;
			case ITEM_VALUE_TYPE_LOG:
				hlog_num++;
				break;
		}
	}

	/* history */
	if (0 != h_num)
		dc_add_history_dbl(history, history_num);

	/* history_uint */
	if (0 != huint_num)
		dc_add_history_uint(history, history_num);

	/* history_str */
	if (0 != hstr_num)
		dc_add_history_str(history, history_num);

	/* history_text */
	if (0 != htext_num)
		dc_add_history_text(history, history_num, htext_num);

	/* history_log */
	if (0 != hlog_num)
		dc_add_history_log(history, history_num, hlog_num);

	/* update value cache */
	if (ZBX_DB_OK <= rc && 0 != (program_type & ZBX_PROGRAM_TYPE_SERVER) &&
			0 != h_num + huint_num + hstr_num + htext_num + hlog_num)
	{
		/* the history values were written into database, now add to value cache */
		zbx_log_value_t	log;
		history_value_t	value, *pvalue;

		value.log = &log;

		zbx_vc_lock();

		for (i = 0; i < history_num; i++)
		{
			ZBX_DC_HISTORY	*h = &history[i];

			if (0 == h->keep_history)
				continue;

			if (0 != (ZBX_DC_FLAG_UNDEF & h->flags) || 0 != (ZBX_DC_FLAG_NOVALUE & h->flags))
				continue;

			switch (h->value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
					pvalue = &h->value;
					break;
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
					pvalue = &h->value_orig;
					break;
				case ITEM_VALUE_TYPE_LOG:
					log.timestamp = h->timestamp;
					log.severity = h->severity;
					log.logeventid = h->logeventid;
					log.value = h->value_orig.str;
					log.source = h->value.str;
					pvalue = &value;
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
			}

			zbx_vc_add_value(h->itemid, h->value_type, &h->ts, pvalue);
		}

		zbx_vc_unlock();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
				zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_DBL, h->value_orig.dbl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_UI64, h->value_orig.ui64);
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
			case ITEM_VALUE_TYPE_LOG:
				pvalue = h->value_orig.str;
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
					zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_DBL, h->value_orig.dbl);
					break;
				case ITEM_VALUE_TYPE_UINT64:
					zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_UI64, h->value_orig.ui64);
					break;
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
					pvalue = h->value_orig.str;
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
			}
		}
		else
		{
			flags |= PROXY_HISTORY_FLAG_NOVALUE;
			pvalue = "";
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
		unsigned int		flags = PROXY_HISTORY_FLAG_META;
		const char		*pvalue;
		const char		*psource;
		const ZBX_DC_HISTORY	*h = &history[i];

		if (ITEM_STATE_NOTSUPPORTED == h->state)
			continue;

		if (ITEM_VALUE_TYPE_LOG != h->value_type)
			continue;

		if (0 != (h->flags & ZBX_DC_FLAG_NOVALUE))
		{
			/* sent to server only if not 0, see proxy_get_history_data() */
			const int	unset_if_novalue = 0;

			flags |= PROXY_HISTORY_FLAG_NOVALUE;

			pvalue = "";
			psource = "";

			zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, unset_if_novalue, psource,
					unset_if_novalue, pvalue, unset_if_novalue, h->lastlogsize, h->mtime, flags);
		}
		else
		{
			pvalue = h->value_orig.str;

			if (NULL != h->value.str)
				psource = h->value.str;
			else
				psource = "";

			zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, h->timestamp, psource,
					h->severity, pvalue, h->logeventid, h->lastlogsize, h->mtime, flags);
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

		zbx_db_insert_add_values(&db_insert, h->itemid, h->ts.sec, h->ts.ns, h->value_orig.err, (int)h->state);
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
				/* if log item has no meta information it has no other information but value */
				if (0 != (h->flags & ZBX_DC_FLAG_META))
					hlog_num++;
				else
					h_num++;
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
	const char		*__function_name = "DCsync_history";
	static ZBX_DC_HISTORY	*history = NULL;
	int			history_num, candidate_num, next_sync = 0;
	time_t			sync_start, now;
	zbx_vector_uint64_t	triggerids;
	zbx_vector_ptr_t	history_items;
	zbx_binary_heap_t	tmp_history_queue;

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

	if (0 == cache->history_num)
		goto finish;

	sync_start = time(NULL);

	if (NULL == history)
		history = zbx_malloc(history, ZBX_HC_SYNC_MAX * sizeof(ZBX_DC_HISTORY));

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		zbx_vector_uint64_create(&triggerids);
		zbx_vector_uint64_reserve(&triggerids, MIN(cache->history_num, ZBX_HC_SYNC_MAX) + 32);
	}

	zbx_vector_ptr_create(&history_items);
	zbx_vector_ptr_reserve(&history_items, MIN(cache->history_num, ZBX_HC_SYNC_MAX) + 32);

	do
	{
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			zbx_vector_uint64_clear(&triggerids);

		LOCK_CACHE;

		hc_pop_items(&history_items);

		if (0 != history_items.values_num && 0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			history_num = DCconfig_lock_triggers_by_history_items(&history_items, &triggerids);

			/* there are unavailable items, push them back in history queue */
			if (history_num != history_items.values_num)
				hc_push_busy_items(&history_items);
		}
		else
			history_num = history_items.values_num;

		UNLOCK_CACHE;

		if (0 == history_num)
			break;

		hc_get_item_values(history, &history_items);

		DBbegin();

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			DCmass_update_items(history, history_num);
			DCmass_add_history(history, history_num);
			DCmass_update_triggers(history, history_num);
			DCmass_update_trends(history, history_num);
			DCflush_nextchecks();

			/* processing of events, generated in functions: */
			/*   DCmass_update_items() */
			/*   DCmass_update_triggers() */
			/*   DCflush_nextchecks() */
			process_events();
		}
		else
		{
			DCmass_proxy_add_history(history, history_num);
			DCmass_proxy_update_items(history, history_num);
		}

		DBcommit();

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			DCconfig_unlock_triggers(&triggerids);

		LOCK_CACHE;

		next_sync = hc_push_processed_items(&history_items);
		cache->history_num -= history_num;

		UNLOCK_CACHE;

		*total_num += history_num;
		candidate_num = history_items.values_num;

		now = time(NULL);

		if (ZBX_SYNC_FULL == sync_type && now - sync_start >= 10)
		{
			zabbix_log(LOG_LEVEL_WARNING, "syncing history data... " ZBX_FS_DBL "%%",
					(double)*total_num / (cache->history_num + *total_num) * 100);
			sync_start = now;
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
		zbx_vector_uint64_destroy(&triggerids);
finish:
	if (ZBX_SYNC_FULL == sync_type)
	{
		LOCK_CACHE;

		zbx_binary_heap_destroy(&cache->history_queue);
		cache->history_queue = tmp_history_queue;

		UNLOCK_CACHE;

		zabbix_log(LOG_LEVEL_WARNING, "syncing history data done");
	}

	return next_sync;
}

static void	DCcheck_ns(zbx_timespec_t *ts)
{
	if (ts->ns >= 0)
		return;

	ts->ns = cache->last_ts.ns++;
	if ((cache->last_ts.ns > 999900000 && cache->last_ts.sec != ts->sec) || cache->last_ts.ns == 1000000000)
		cache->last_ts.ns = 0;
	cache->last_ts.sec = ts->sec;
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

	string_values = zbx_realloc(string_values, string_values_alloc);
}

static dc_item_value_t	*dc_local_get_history_slot()
{
	if (ZBX_MAX_VALUES_LOCAL == item_values_num)
		dc_flush_history();

	if (item_values_alloc == item_values_num)
	{
		item_values_alloc += ZBX_STRUCT_REALLOC_STEP;
		item_values = zbx_realloc(item_values, item_values_alloc * sizeof(dc_item_value_t));
	}

	return &item_values[item_values_num++];
}

static void	dc_local_add_history_dbl(zbx_uint64_t itemid, const zbx_timespec_t *ts, double value_orig,
		zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
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

static void	dc_local_add_history_uint(zbx_uint64_t itemid, const zbx_timespec_t *ts, zbx_uint64_t value_orig,
		zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
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

static void	dc_local_add_history_str(zbx_uint64_t itemid, const zbx_timespec_t *ts, const char *value_orig,
		zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->value_type = ITEM_VALUE_TYPE_STR;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = flags;

	if (0 != (item_value->flags & ZBX_DC_FLAG_META))
	{
		item_value->lastlogsize = lastlogsize;
		item_value->mtime = mtime;
	}

	if (0 == (item_value->flags & ZBX_DC_FLAG_NOVALUE))
	{
		item_value->value.value_str.len = zbx_db_strlen_n(value_orig, HISTORY_STR_VALUE_LEN) + 1;
		dc_string_buffer_realloc(item_value->value.value_str.len);

		item_value->value.value_str.pvalue = string_values_offset;
		memcpy(&string_values[string_values_offset], value_orig, item_value->value.value_str.len);
		string_values_offset += item_value->value.value_str.len;
	}
	else
		item_value->value.value_str.len = 0;
}

static void	dc_local_add_history_text(zbx_uint64_t itemid, const zbx_timespec_t *ts, const char *value_orig,
		zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
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
		item_value->value.value_str.len = zbx_db_strlen_n(value_orig, HISTORY_TEXT_VALUE_LEN) + 1;
		dc_string_buffer_realloc(item_value->value.value_str.len);

		item_value->value.value_str.pvalue = string_values_offset;
		memcpy(&string_values[string_values_offset], value_orig, item_value->value.value_str.len);
		string_values_offset += item_value->value.value_str.len;
	}
	else
		item_value->value.value_str.len = 0;
}

static void	dc_local_add_history_log(zbx_uint64_t itemid, const zbx_timespec_t *ts, const zbx_log_t *log,
		zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
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

		item_value->value.value_str.len = zbx_db_strlen_n(log->value, HISTORY_LOG_VALUE_LEN) + 1;

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

static void	dc_local_add_history_notsupported(zbx_uint64_t itemid, const zbx_timespec_t *ts, const char *error)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->state = ITEM_STATE_NOTSUPPORTED;
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
 * Parameters:  itemid     - [IN] the itemid                                  *
 *              value_type - [IN] the value type (see ITEM_VALUE_TYPE_* defs) *
 *              item_flags - [IN] the item flags (e. g. lld rule)             *
 *              result     - [IN] agent result containing the value to add    *
 *              ts         - [IN] the value timestamp                         *
 *              state      - [IN] the item state                              *
 *              error      - [IN] the error message in case item state is     *
 *                                ITEM_STATE_NOTSUPPORTED                     *
 *                                                                            *
 ******************************************************************************/
void	dc_add_history(zbx_uint64_t itemid, unsigned char value_type, unsigned char item_flags, AGENT_RESULT *result,
		const zbx_timespec_t *ts, unsigned char state, const char *error)
{
	unsigned char	value_flags;

	if (ITEM_STATE_NOTSUPPORTED == state)
	{
		dc_local_add_history_notsupported(itemid, ts, error);
		return;
	}

	if (0 != (ZBX_FLAG_DISCOVERY_RULE & item_flags))
	{
		if (NULL == GET_TEXT_RESULT(result))
			return;

		/* server processes low-level discovery (lld) items while proxy stores their values in db */
		if (0 != (ZBX_PROGRAM_TYPE_SERVER & program_type))
			lld_process_discovery_rule(itemid, result->text, ts);
		else
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
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (0 != (value_flags & ZBX_DC_FLAG_NOVALUE) || GET_DBL_RESULT(result))
			{
				dc_local_add_history_dbl(itemid, ts, result->dbl, result->lastlogsize, result->mtime,
						value_flags);
			}
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (0 != (value_flags & ZBX_DC_FLAG_NOVALUE) || GET_UI64_RESULT(result))
			{
				dc_local_add_history_uint(itemid, ts, result->ui64, result->lastlogsize, result->mtime,
						value_flags);
			}
			break;
		case ITEM_VALUE_TYPE_STR:
			if (0 != (value_flags & ZBX_DC_FLAG_NOVALUE) || GET_STR_RESULT(result))
			{
				dc_local_add_history_str(itemid, ts, result->str, result->lastlogsize, result->mtime,
						value_flags);
			}
			break;
		case ITEM_VALUE_TYPE_TEXT:
			if (0 != (value_flags & ZBX_DC_FLAG_NOVALUE) || GET_TEXT_RESULT(result))
			{
				dc_local_add_history_text(itemid, ts, result->text, result->lastlogsize, result->mtime,
						value_flags);
			}
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (0 != (value_flags & ZBX_DC_FLAG_NOVALUE) || GET_LOG_RESULT(result))
			{
				dc_local_add_history_log(itemid, ts, result->log, result->lastlogsize, result->mtime,
						value_flags);
			}
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unknown value type [%d] for itemid [" ZBX_FS_UI64 "]",
					value_type, itemid);
			return;
	}
}

void	dc_flush_history()
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
		DCcheck_ns(&(*data)->ts);
	}

	if (ITEM_STATE_NOTSUPPORTED == item_value->state)
	{
		if (NULL == ((*data)->value.str = hc_mem_value_str_dup(&item_value->value.value_str)))
			return FAIL;

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
				cache->stats.history_float_counter++;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				(*data)->value.ui64 = item_value->value.value_uint;
				cache->stats.history_uint_counter++;
				break;
			case ITEM_VALUE_TYPE_STR:
				if (SUCCEED != hc_clone_history_str_data(&(*data)->value.str, &item_value->value.value_str))
					return FAIL;

				cache->stats.history_str_counter++;
				break;
			case ITEM_VALUE_TYPE_TEXT:
				if (SUCCEED != hc_clone_history_str_data(&(*data)->value.str, &item_value->value.value_str))
					return FAIL;

				cache->stats.history_text_counter++;
				break;
			case ITEM_VALUE_TYPE_LOG:
				if (SUCCEED != hc_clone_history_log_data(&(*data)->value.log, item_value))
					return FAIL;

				cache->stats.history_log_counter++;
				break;
		}

		cache->stats.history_counter++;
	}

	(*data)->value_type = item_value->value_type;

	if (0 != (ZBX_DC_FLAG_META & item_value->flags))
	{
		(*data)->lastlogsize = item_value->lastlogsize;
		(*data)->mtime = item_value->mtime;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: hc_add_item_values                                               *
 *                                                                            *
 * Purpose: adds item values to the history cache                             *
 *                                                                            *
 * Parameters: item_values      - [IN] the item values to add                 *
 *             item_values_num  - [IN] the number of item values to add       *
 *                                                                            *
 * Comments: If the history cache is full this function will wait until       *
 *           history syncers processes values freeing enough space to store   *
 *           the new value.                                                   *
 *                                                                            *
 ******************************************************************************/
static void	hc_add_item_values(dc_item_value_t *item_values, int item_values_num)
{
	dc_item_value_t	*item_value;
	int		i;
	zbx_hc_item_t	*item;

	for (i = 0; i < item_values_num; i++)
	{
		zbx_hc_data_t	*data = NULL;

		item_value = &item_values[i];

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
	history->keep_history = 0;
	history->keep_trends = 0;

	if (ITEM_STATE_NOTSUPPORTED == data->state)
	{
		history->value_orig.err = zbx_strdup(NULL, data->value.str);
		history->flags |= ZBX_DC_FLAG_UNDEF;
		return;
	}

	history->value_type = data->value_type;
	history->lastlogsize = data->lastlogsize;
	history->mtime = data->mtime;

	if (0 == (ZBX_DC_FLAG_NOVALUE & data->flags))
	{
		switch (data->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				history->value_orig.dbl = data->value.dbl;
				history->value.dbl = 0;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				history->value_orig.ui64 = data->value.ui64;
				history->value.ui64 = 0;
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				history->value_orig.str = zbx_strdup(NULL, data->value.str);
				break;
			case ITEM_VALUE_TYPE_LOG:
				history->value_orig.str = zbx_strdup(NULL, data->value.log->value);
				if (NULL != data->value.log->source)
					history->value.str = zbx_strdup(NULL, data->value.log->source);
				else
					history->value.str = NULL;

				history->timestamp = data->value.log->timestamp;
				history->severity = data->value.log->severity;
				history->logeventid = data->value.log->logeventid;

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
 * Function: hc_free_item_values                                              *
 *                                                                            *
 * Purpose: frees resources allocated to store str/text/log values            *
 *                                                                            *
 * Parameters: history     - [IN] the history data                            (
 *             history_num - [IN] the number of values in history data        *
 *                                                                            *
 ******************************************************************************/
static void	hc_free_item_values(ZBX_DC_HISTORY *history, int history_num)
{
	int	i;

	for (i = 0; i < history_num; i++)
	{
		if (ITEM_STATE_NOTSUPPORTED == history[i].state)
		{
			zbx_free(history[i].value_orig.err);
			continue;
		}

		if (0 != (ZBX_DC_FLAG_NOVALUE & history[i].flags))
			continue;

		switch (history[i].value_type)
		{
			case ITEM_VALUE_TYPE_LOG:
				zbx_free(history[i].value.str);
				/* break; is not missing here */
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				zbx_free(history[i].value_orig.str);
				break;
		}
	}
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

static void	init_trend_cache()
{
	const char	*__function_name = "init_trend_cache";
	key_t		trend_shm_key;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (-1 == (trend_shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_TREND_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC key for trend cache");
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_mutex_create_force(&trends_lock, ZBX_MUTEX_TRENDS))
	{
		zbx_error("cannot create mutex for trend cache");
		exit(EXIT_FAILURE);
	}

	sz = zbx_mem_required_size(1, "trend cache", "TrendCacheSize");
	zbx_mem_create(&trend_mem, trend_shm_key, ZBX_NO_MUTEX, CONFIG_TRENDS_CACHE_SIZE,
			"trend cache", "TrendCacheSize", 0);
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
void	init_database_cache()
{
	const char	*__function_name = "init_database_cache";
	key_t		hc_shm_key, hc_index_shm_key;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (-1 == (hc_shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_HISTORY_ID)) ||
			-1 == (hc_index_shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_HISTORY_INDEX_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC keys for history cache");
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_mutex_create_force(&cache_lock, ZBX_MUTEX_CACHE))
	{
		zbx_error("cannot create mutex for history cache");
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_mutex_create_force(&cache_ids_lock, ZBX_MUTEX_CACHE_IDS))
	{
		zbx_error("cannot create mutex for IDs cache");
		exit(EXIT_FAILURE);
	}

	/* history cache */
	zbx_mem_create(&hc_mem, hc_shm_key, ZBX_NO_MUTEX, CONFIG_HISTORY_CACHE_SIZE, "history cache",
			"HistoryCacheSize", 1);

	/* history index cache */
	zbx_mem_create(&hc_index_mem, hc_index_shm_key, ZBX_NO_MUTEX, CONFIG_HISTORY_INDEX_CACHE_SIZE,
			"history index cache", "HistoryIndexCacheSize", 0);

	cache = (ZBX_DC_CACHE *)__hc_index_mem_malloc_func(NULL, sizeof(ZBX_DC_CACHE));
	memset(cache, 0, sizeof(ZBX_DC_CACHE));

	ids = (ZBX_DC_IDS *)__hc_index_mem_malloc_func(NULL, sizeof(ZBX_DC_IDS));
	memset(ids, 0, sizeof(ZBX_DC_IDS));

	zbx_hashset_create_ext(&cache->history_items, ZBX_HC_ITEMS_INIT_SIZE,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL,
			__hc_index_mem_malloc_func, __hc_index_mem_realloc_func, __hc_index_mem_free_func);

	zbx_binary_heap_create_ext(&cache->history_queue, hc_queue_elem_compare_func, ZBX_BINARY_HEAP_OPTION_EMPTY,
			__hc_index_mem_malloc_func, __hc_index_mem_realloc_func, __hc_index_mem_free_func);

	/* trend cache */
	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		init_trend_cache();

	if (NULL == sql)
		sql = zbx_malloc(sql, sql_alloc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static void	DCsync_all()
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
void	free_database_cache()
{
	const char	*__function_name = "free_database_cache";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DCsync_all();

	cache = NULL;

	zbx_mem_destroy(hc_mem);
	zbx_mem_destroy(hc_index_mem);

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		zbx_mem_destroy(trend_mem);

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

	zbx_strlcpy(id->table_name, table_name, sizeof(id->table_name));

	table = DBget_table(table_name);

	result = DBselect("select max(%s) from %s where %s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
			table->recid, table_name, table->recid, min, max);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
		id->lastid = min;
	else
		ZBX_STR2UINT64(id->lastid, row[0]);

	nextid = id->lastid + 1;
	id->lastid += num;
	lastid = id->lastid;

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
void	DCupdate_hosts_availability()
{
	const char		*__function_name = "DCupdate_hosts_availability";
	zbx_vector_ptr_t	hosts;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&hosts);

	if (SUCCEED != DCreset_hosts_availability(&hosts))
		goto out;

	DBbegin();
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < hosts.values_num; i++)
	{
		if (SUCCEED == zbx_sql_add_host_availability(&sql, &sql_alloc, &sql_offset, hosts.values[i]))
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
		DBexecute("%s", sql);

	DBcommit();

	zbx_free(sql);
out:
	zbx_vector_ptr_clear_ext(&hosts, (zbx_mem_free_func_t)zbx_host_availability_free);
	zbx_vector_ptr_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
