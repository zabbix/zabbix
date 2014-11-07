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

static zbx_mem_info_t	*history_mem = NULL;
static zbx_mem_info_t	*history_text_mem = NULL;
static zbx_mem_info_t	*trend_mem = NULL;

#define	LOCK_CACHE	zbx_mutex_lock(&cache_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(&cache_lock)
#define	LOCK_TRENDS	zbx_mutex_lock(&trends_lock)
#define	UNLOCK_TRENDS	zbx_mutex_unlock(&trends_lock)
#define	LOCK_CACHE_IDS		zbx_mutex_lock(&cache_ids_lock)
#define	UNLOCK_CACHE_IDS	zbx_mutex_unlock(&cache_ids_lock)

static ZBX_MUTEX	cache_lock;
static ZBX_MUTEX	trends_lock;
static ZBX_MUTEX	cache_ids_lock;

static char		*sql = NULL;
static size_t		sql_alloc = 64 * ZBX_KIBIBYTE;

extern unsigned char	daemon_type;

extern int		CONFIG_HISTSYNCER_FREQUENCY;

static int		ZBX_HISTORY_SIZE = 0;	/* must be greater than ZBX_SYNC_MAX */

#define ZBX_IDS_SIZE	10

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
	zbx_uint64_t	itemid;
	history_value_t	value_orig;
	history_value_t	value;			/* used as source for log items */
	zbx_uint64_t	lastlogsize;
	zbx_timespec_t	ts;
	int		timestamp;
	int		severity;
	int		logeventid;
	int		mtime;
	int		num;			/* number of continuous values with the same itemid */
	unsigned char	value_type;
	unsigned char	value_null;
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
	zbx_hashset_t	trends;
	ZBX_DC_STATS	stats;
	ZBX_DC_HISTORY	*history;
	char		*text;
	zbx_uint64_t	*itemids;	/* items, processed by other syncers */
	char		*last_text;
	int		history_first;
	int		history_num;
	int		history_gap_num;
	int		text_free;
	int		trends_num;
	int		itemids_alloc;
	int		itemids_num;
	zbx_timespec_t	last_ts;
}
ZBX_DC_CACHE;

static ZBX_DC_CACHE	*cache = NULL;

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

	switch (request)
	{
		case ZBX_STATS_HISTORY_COUNTER:
			value_uint = cache->stats.history_counter;
			return &value_uint;
		case ZBX_STATS_HISTORY_FLOAT_COUNTER:
			value_uint = cache->stats.history_float_counter;
			return &value_uint;
		case ZBX_STATS_HISTORY_UINT_COUNTER:
			value_uint = cache->stats.history_uint_counter;
			return &value_uint;
		case ZBX_STATS_HISTORY_STR_COUNTER:
			value_uint = cache->stats.history_str_counter;
			return &value_uint;
		case ZBX_STATS_HISTORY_LOG_COUNTER:
			value_uint = cache->stats.history_log_counter;
			return &value_uint;
		case ZBX_STATS_HISTORY_TEXT_COUNTER:
			value_uint = cache->stats.history_text_counter;
			return &value_uint;
		case ZBX_STATS_NOTSUPPORTED_COUNTER:
			value_uint = cache->stats.notsupported_counter;
			return &value_uint;
		case ZBX_STATS_HISTORY_TOTAL:
			value_uint = CONFIG_HISTORY_CACHE_SIZE;
			return &value_uint;
		case ZBX_STATS_HISTORY_USED:
			value_uint = (cache->history_num - cache->history_gap_num) * sizeof(ZBX_DC_HISTORY);
			return &value_uint;
		case ZBX_STATS_HISTORY_FREE:
			value_uint = CONFIG_HISTORY_CACHE_SIZE - (cache->history_num - cache->history_gap_num) *
					sizeof(ZBX_DC_HISTORY);
			return &value_uint;
		case ZBX_STATS_HISTORY_PFREE:
			value_double = 100 * ((double)(ZBX_HISTORY_SIZE - cache->history_num + cache->history_gap_num) /
					ZBX_HISTORY_SIZE);
			return &value_double;
		case ZBX_STATS_TREND_TOTAL:
			value_uint = trend_mem->orig_size;
			return &value_uint;
		case ZBX_STATS_TREND_USED:
			value_uint = trend_mem->orig_size - trend_mem->free_size;
			return &value_uint;
		case ZBX_STATS_TREND_FREE:
			value_uint = trend_mem->free_size;
			return &value_uint;
		case ZBX_STATS_TREND_PFREE:
			value_double = 100 * ((double)trend_mem->free_size / trend_mem->orig_size);
			return &value_double;
		case ZBX_STATS_TEXT_TOTAL:
			value_uint = CONFIG_TEXT_CACHE_SIZE;
			return &value_uint;
		case ZBX_STATS_TEXT_USED:
			value_uint = CONFIG_TEXT_CACHE_SIZE - cache->text_free;
			return &value_uint;
		case ZBX_STATS_TEXT_FREE:
			value_uint = cache->text_free;
			return &value_uint;
		case ZBX_STATS_TEXT_PFREE:
			value_double = 100.0 * ((double)cache->text_free / CONFIG_TEXT_CACHE_SIZE);
			return &value_double;
		default:
			return NULL;
	}
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

	ids_alloc = MIN(ZBX_SYNC_MAX, *trends_num);
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

		if (ZBX_SYNC_MAX == ids_num)
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
				zbx_uint128_t avg;

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
static void	DCadd_trend(ZBX_DC_HISTORY *history, ZBX_DC_TREND **trends, int *trends_alloc, int *trends_num)
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
	int		trends_alloc = 0, trends_num = 0, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_TRENDS;

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type != ITEM_VALUE_TYPE_FLOAT && history[i].value_type != ITEM_VALUE_TYPE_UINT64)
			continue;

		if (0 != history[i].value_null || 0 == history[i].keep_trends)
			continue;

		DCadd_trend(&history[i], &trends, &trends_alloc, &trends_num);
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
		if (0 != history[i].value_null)
			continue;

		itemids[item_num] = history[i].itemid;
		timespecs[item_num] = history[i].ts;
		item_num++;
	}

	if (0 == item_num)
		goto clean_items;

	zbx_hashset_create(&trigger_info, MAX(100, 2 * item_num),
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&trigger_order);
	zbx_vector_ptr_reserve(&trigger_order, item_num);

	DCconfig_get_triggers_by_itemids(&trigger_info, &trigger_order, itemids, timespecs, NULL, item_num);

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
				h->value_null = 1;
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
					h->value_null = 1;
				}
			}
			else
				h->value_null = 1;

			break;
		case ITEM_STORE_SIMPLE_CHANGE:
			if (0 != deltaitem->timestamp.sec && deltaitem->value.dbl <= h->value_orig.dbl)
			{
				h->value.dbl = h->value_orig.dbl - deltaitem->value.dbl;
				h->value.dbl = multiply_item_value_float(item, h->value.dbl);

				if (SUCCEED != DBchk_double(h->value.dbl))
				{
					h->state = ITEM_STATE_NOTSUPPORTED;
					h->value_null = 1;
				}
			}
			else
				h->value_null = 1;

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
				h->value_null = 1;

			break;
		case ITEM_STORE_SIMPLE_CHANGE:
			if (0 != deltaitem->timestamp.sec && deltaitem->value.ui64 <= h->value_orig.ui64)
			{
				h->value.ui64 = h->value_orig.ui64 - deltaitem->value.ui64;
				h->value.ui64 = multiply_item_value_uint64(item, h->value.ui64);
			}
			else
				h->value_null = 1;

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

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			DCcalculate_item_delta_float(item, h, deltaitem);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			DCcalculate_item_delta_uint64(item, h, deltaitem);
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset, "%slastlogsize=" ZBX_FS_UI64 ",mtime=%d",
					sql_start, h->lastlogsize, h->mtime);
			sql_start = sql_continue;
			break;
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

	if (1 == h->value_null || NULL == (inventory_field = DBget_inventory_field(item->inventory_link)))
		return;

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

	/* disable processing of deleted and disabled items by setting value_null */
	for (i = 0; i < history_num; i++)
	{
		if (FAIL == zbx_vector_uint64_bsearch(&ids, history[i].itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			history[i].value_null = 1;
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
	const char		*__function_name = "DCmass_proxy_update_items";

	size_t			sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	int			i, j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&itemids);

	for (i = 0; i < history_num; i++)
	{
		if (ITEM_VALUE_TYPE_LOG == history[i].value_type)
			zbx_vector_uint64_append(&itemids, history[i].itemid);
	}

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < itemids.values_num; i++)
	{
		for (j = history_num - 1; j >= 0; j--)
		{
			if (history[j].itemid != itemids.values[i])
				continue;

			if (ITEM_VALUE_TYPE_LOG != history[j].value_type)
				continue;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update items"
					" set lastlogsize=" ZBX_FS_UI64
						",mtime=%d"
					" where itemid=" ZBX_FS_UI64 ";\n",
					history[j].lastlogsize, history[j].mtime, history[j].itemid);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			break;
		}
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_vector_uint64_destroy(&itemids);

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
		if (ITEM_VALUE_TYPE_FLOAT != history[i].value_type)
			continue;

		if (0 != history[i].value_null || 0 == history[i].keep_history)
			continue;

		zbx_db_insert_add_values(&db_insert, history[i].itemid, history[i].ts.sec, history[i].ts.ns,
				history[i].value.dbl);
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
		if (ITEM_VALUE_TYPE_UINT64 != history[i].value_type)
			continue;

		if (0 != history[i].value_null || 0 == history[i].keep_history)
			continue;

		zbx_db_insert_add_values(&db_insert, history[i].itemid, history[i].ts.sec, history[i].ts.ns,
				history[i].value.ui64);
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
		if (ITEM_VALUE_TYPE_STR != history[i].value_type)
			continue;

		if (0 != history[i].value_null || 0 == history[i].keep_history)
			continue;

		zbx_db_insert_add_values(&db_insert, history[i].itemid, history[i].ts.sec, history[i].ts.ns,
				history[i].value_orig.str);
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
		if (ITEM_VALUE_TYPE_TEXT != history[i].value_type)
			continue;

		if (0 != history[i].value_null || 0 == history[i].keep_history)
			continue;

		zbx_db_insert_add_values(&db_insert, id++, history[i].itemid, history[i].ts.sec, history[i].ts.ns,
				history[i].value_orig.str);
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
	int		i;
	zbx_uint64_t	id;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "history_log", "id", "itemid", "clock", "ns", "timestamp", "source",
			"severity", "value", "logeventid", NULL);

	id = DBget_maxid_num("history_log", hlog_num);

	for (i = 0; i < history_num; i++)
	{
		if (ITEM_VALUE_TYPE_LOG != history[i].value_type)
			continue;

		if (0 != history[i].value_null || 0 == history[i].keep_history)
			continue;

		zbx_db_insert_add_values(&db_insert, id++, history[i].itemid, history[i].ts.sec, history[i].ts.ns,
				history[i].timestamp, (NULL != history[i].value.str ? history[i].value.str : ""),
				history[i].severity, history[i].value_orig.str, history[i].logeventid);
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
		if (0 != history[i].value_null || 0 == history[i].keep_history)
			continue;

		switch (history[i].value_type)
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
	if (ZBX_DB_OK <= rc && 0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER) &&
			0 != h_num + huint_num + hstr_num + htext_num + hlog_num)
	{
		/* the history values were written into database, now add to value cache */
		zbx_log_value_t	log;
		history_value_t	value, *pvalue;

		value.log = &log;

		zbx_vc_lock();

		for (i = 0; i < history_num; i++)
		{
			if (0 != history[i].value_null || 0 == history[i].keep_history)
				continue;

			switch (history[i].value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
					pvalue = &history[i].value;
					break;
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
					pvalue = &history[i].value_orig;
					break;
				case ITEM_VALUE_TYPE_LOG:
					log.timestamp = history[i].timestamp;
					log.severity = history[i].severity;
					log.logeventid = history[i].logeventid;
					log.value = history[i].value_orig.str;
					log.source = history[i].value.str;
					pvalue = &value;
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
			}
			zbx_vc_add_value(history[i].itemid, history[i].value_type, &history[i].ts, pvalue);
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
 ******************************************************************************/
static void	dc_add_proxy_history(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	char		buffer[64], *pvalue;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "value", NULL);

	for (i = 0; i < history_num; i++)
	{
		if (ITEM_STATE_NOTSUPPORTED == history[i].state)
			continue;

		switch (history[i].value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_DBL, history[i].value_orig.dbl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_snprintf(pvalue = buffer, sizeof(buffer), ZBX_FS_UI64, history[i].value_orig.ui64);
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				pvalue = history[i].value_orig.str;
				break;
			default:
				continue;
		}

		zbx_db_insert_add_values(&db_insert, history[i].itemid, history[i].ts.sec, history[i].ts.ns, pvalue);
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
 ******************************************************************************/
static void	dc_add_proxy_history_log(ZBX_DC_HISTORY *history, int history_num)
{
	int		i;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "proxy_history", "itemid", "clock", "ns", "timestamp", "source", "severity",
			"value", "logeventid", NULL);

	for (i = 0; i < history_num; i++)
	{
		if (ITEM_VALUE_TYPE_LOG != history[i].value_type)
			continue;

		if (ITEM_STATE_NOTSUPPORTED == history[i].state)
			continue;

		zbx_db_insert_add_values(&db_insert, history[i].itemid, history[i].ts.sec, history[i].ts.ns,
				history[i].timestamp, NULL !=  history[i].value.str ? history[i].value.str : "",
				history[i].severity, history[i].value_orig.str, history[i].logeventid);
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
		if (ITEM_STATE_NOTSUPPORTED != history[i].state)
			continue;

		zbx_db_insert_add_values(&db_insert, history[i].itemid, history[i].ts.sec, history[i].ts.ns,
				history[i].value_orig.err, (int)history[i].state);
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
	int		i, h_num = 0, hlog_num = 0, notsupported_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < history_num; i++)
	{
		if (ITEM_STATE_NOTSUPPORTED == history[i].state)
		{
			notsupported_num++;
			continue;
		}

		switch (history[i].value_type)
		{
			case ITEM_VALUE_TYPE_LOG:
				hlog_num++;
				break;
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				h_num++;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	if (0 != h_num)
		dc_add_proxy_history(history, history_num);

	if (0 != hlog_num)
		dc_add_proxy_history_log(history, history_num);

	if (0 != notsupported_num)
		dc_add_proxy_history_notsupported(history, history_num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	DCskip_items(int index, int n)
{
	zbx_uint64_t	itemid;
	int		f, num;

	itemid = cache->history[index].itemid;
	num = cache->history[index].num;

	while (0 < n - num)
	{
		if (ZBX_HISTORY_SIZE <= (f = index + num))
			f -= ZBX_HISTORY_SIZE;

		if (itemid != cache->history[f].itemid)
			break;

		num += cache->history[f].num;
	}

	cache->history[index].num = num;

	if (1 < num)
	{
		if (ZBX_HISTORY_SIZE == (f = index + 1))
			f = 0;

		cache->history[f].num = num - 1;
	}

	return num;
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_history                                                   *
 *                                                                            *
 * Purpose: writes updates and new data from pool to database                 *
 *                                                                            *
 * Return value: number of synced values                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	DCsync_history(int sync_type)
{
	const char		*__function_name = "DCsync_history";
	static ZBX_DC_HISTORY	*history = NULL;
	int			i, history_num, n, f;
	int			syncs, iterations;
	int			total_num = 0;
	int			skipped_clock, max_delay;
	time_t			now = 0;
	int			candidate_num;
	int			indices[ZBX_SYNC_MAX];
	zbx_uint64_t		itemids[ZBX_SYNC_MAX];
	zbx_vector_uint64_t	triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_first:%d history_num:%d",
			__function_name, cache->history_first, cache->history_num);

	if (ZBX_SYNC_FULL == sync_type)
	{
		zabbix_log(LOG_LEVEL_WARNING, "syncing history data...");
		now = time(NULL);
		cache->itemids_num = 0;
	}

	if (0 == cache->history_num)
		goto finish;

	if (NULL == history)
		history = zbx_malloc(history, ZBX_SYNC_MAX * sizeof(ZBX_DC_HISTORY));

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, MIN(cache->history_num, ZBX_SYNC_MAX) + 32);

	syncs = cache->history_num / ZBX_SYNC_MAX;
	max_delay = (int)time(NULL) - CONFIG_HISTSYNCER_FREQUENCY;

	do
	{
		LOCK_CACHE;

		candidate_num = 0;
		skipped_clock = 0;
		iterations = 0;

		for (n = cache->history_num, f = cache->history_first; 0 < n && ZBX_SYNC_MAX > candidate_num;)
		{
			int	num;

			if (ZBX_HISTORY_SIZE <= f)
				f -= ZBX_HISTORY_SIZE;

			num = DCskip_items(f, n);

			if (0 == cache->history[f].itemid)
			{
				if (f == cache->history_first)
				{
					cache->history_num -= num;
					cache->history_gap_num -= num;
					if (ZBX_HISTORY_SIZE <= (cache->history_first += num))
						cache->history_first -= ZBX_HISTORY_SIZE;
				}
				n -= num;
				f += num;
				continue;
			}

			/* Limit iteration count to improve handling of situation when few items */
			/* have flooded history cache with several hundred thousands of values.  */
			/* This is achieved by breaking out of the loop if the number of values  */
			/* that we take is less than 10% of the values that we see. This way, at */
			/* least ZBX_SYNC_MAX and at most ZBX_SYNC_MAX * 10 iterations are done. */
			if (ZBX_SYNC_MAX <= iterations && candidate_num * 10 < iterations)
				break;

			iterations++;

			if (SUCCEED == uint64_array_exists(cache->itemids, cache->itemids_num,
					cache->history[f].itemid))
			{
				if (0 == skipped_clock)
					skipped_clock = cache->history[f].ts.sec;
				n -= num;
				f += num;
				continue;
			}
			else if (1 < num && 0 == skipped_clock)
			{
				skipped_clock = cache->history[ZBX_HISTORY_SIZE == f + 1 ? 0 : f + 1].ts.sec;
			}

			uint64_array_add(&cache->itemids, &cache->itemids_alloc,
					&cache->itemids_num, cache->history[f].itemid, 0);

			indices[candidate_num] = f;
			itemids[candidate_num] = cache->history[f].itemid;

			candidate_num++;

			f += num;
			n -= num;
		}

		if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
			DCconfig_lock_triggers_by_itemids(itemids, candidate_num, &triggerids);

		history_num = 0;

		for (i = 0; i < candidate_num; i++)
		{
			f = indices[i];

			if (0 == itemids[i])
			{
				if (0 == skipped_clock || skipped_clock > cache->history[f].ts.sec)
					skipped_clock = cache->history[f].ts.sec;

				uint64_array_remove(cache->itemids, &cache->itemids_num, &cache->history[f].itemid, 1);

				continue;
			}

			memcpy(&history[history_num], &cache->history[f], sizeof(ZBX_DC_HISTORY));

			if (ITEM_STATE_NOTSUPPORTED == history[history_num].state)
			{
				history[history_num].value_orig.err =
						zbx_strdup(NULL, cache->history[f].value_orig.err);
				cache->text_free += strlen(cache->history[f].value_orig.err) + 1;
			}
			else
			{
				switch (history[history_num].value_type)
				{
					case ITEM_VALUE_TYPE_LOG:
						if (NULL != cache->history[f].value.str)
						{
							history[history_num].value.str =
									zbx_strdup(NULL, cache->history[f].value.str);
							cache->text_free += strlen(cache->history[f].value.str) + 1;
						}
						/* break; is not missing here */
					case ITEM_VALUE_TYPE_STR:
					case ITEM_VALUE_TYPE_TEXT:
						history[history_num].value_orig.str =
								zbx_strdup(NULL, cache->history[f].value_orig.str);
						cache->text_free += strlen(cache->history[f].value_orig.str) + 1;
						break;
				}
			}

			if (f == cache->history_first)
			{
				cache->history_num--;
				if (ZBX_HISTORY_SIZE == ++cache->history_first)
					cache->history_first = 0;
			}
			else
			{
				cache->history[f].itemid = 0;
				cache->history[f].num = 1;
				cache->history_gap_num++;
			}

			history_num++;
		}

		if (ZBX_HISTORY_SIZE <= (f = cache->history_first + cache->history_num))
			f -= ZBX_HISTORY_SIZE;

		for (n = cache->history_num; 0 < n; n--)
		{
			if (0 == f)
				f = ZBX_HISTORY_SIZE;
			f--;

			if (0 != cache->history[f].itemid)
				break;

			cache->history_num--;
			cache->history_gap_num--;
		}

		UNLOCK_CACHE;

		if (0 == history_num)
			break;

		DBbegin();

		if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
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

		if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
			DCconfig_unlock_triggers(&triggerids);

		LOCK_CACHE;

		for (i = 0; i < history_num; i ++)
			uint64_array_remove(cache->itemids, &cache->itemids_num, &history[i].itemid, 1);

		UNLOCK_CACHE;

		for (i = 0; i < history_num; i++)
		{
			if (ITEM_STATE_NOTSUPPORTED == history[i].state)
			{
				zbx_free(history[i].value_orig.err);
			}
			else
			{
				switch (history[i].value_type)
				{
					case ITEM_VALUE_TYPE_LOG:
						zbx_free(history[i].value.str);
					case ITEM_VALUE_TYPE_STR:
					case ITEM_VALUE_TYPE_TEXT:
						zbx_free(history[i].value_orig.str);
						break;
				}
			}
		}

		total_num += history_num;

		if (ZBX_SYNC_FULL == sync_type && time(NULL) - now >= 10)
		{
			zabbix_log(LOG_LEVEL_WARNING, "syncing history data... " ZBX_FS_DBL "%%",
					(double)total_num / (cache->history_num + total_num) * 100);
			now = time(NULL);
		}
	}
	while (--syncs > 0 || sync_type == ZBX_SYNC_FULL || (skipped_clock != 0 && skipped_clock < max_delay));

	zbx_vector_uint64_destroy(&triggerids);
finish:
	if (ZBX_SYNC_FULL == sync_type)
		zabbix_log(LOG_LEVEL_WARNING, "syncing history data done");

	return total_num;
}

static void	DCmove_history(int src, int n_data, int n_gap)
{
	int	dst, n_data1, n_data2;

	dst = src + n_gap;

	if (ZBX_HISTORY_SIZE <= dst || ZBX_HISTORY_SIZE >= dst + n_data)
	{
		if (ZBX_HISTORY_SIZE <= dst)
			dst -= ZBX_HISTORY_SIZE;
		memmove(&cache->history[dst], &cache->history[src], n_data * sizeof(ZBX_DC_HISTORY));
	}
	else
	{
		n_data2 = dst + n_data - ZBX_HISTORY_SIZE;
		n_data1 = n_data - n_data2;
		memmove(&cache->history[0], &cache->history[src + n_data1], n_data2 * sizeof(ZBX_DC_HISTORY));
		memmove(&cache->history[dst], &cache->history[src], n_data1 * sizeof(ZBX_DC_HISTORY));
	}
}

static void	DCvacuum_history()
{
	const char	*__function_name = "DCvacuum_history";
	int		n, f, n_gap = 0, n_data = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_gap_num:%d/%d",
			__function_name, cache->history_gap_num, ZBX_HISTORY_SIZE);

	if (ZBX_HISTORY_SIZE / 100 >= cache->history_gap_num)
		goto exit;

	if (ZBX_HISTORY_SIZE <= (f = cache->history_first + cache->history_num))
		f -= ZBX_HISTORY_SIZE;

	for (n = cache->history_num; 0 < n; n--)
	{
		if (0 == f)
			f = ZBX_HISTORY_SIZE;
		f--;

		if (0 == cache->history[f].itemid)
		{
			if (0 != n_data)
			{
				DCmove_history(f + 1, n_data, n_gap);
				n_data = 0;
			}

			n_gap++;
		}
		else if (0 != n_gap)
		{
			n_data++;

			if (0 == f)
			{
				DCmove_history(f, n_data, n_gap);
				n_data = 0;
			}
		}
	}

	if (0 != n_data)
		DCmove_history(f, n_data, n_gap);

	cache->history_num -= n_gap;
	cache->history_gap_num -= n_gap;
	if (ZBX_HISTORY_SIZE <= (cache->history_first += n_gap))
		cache->history_first -= ZBX_HISTORY_SIZE;
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DCmove_text(char **str)
{
	size_t	sz;

	sz = strlen(*str) + 1;

	if (cache->last_text != *str)
	{
		memmove(cache->last_text, *str, sz);
		*str = cache->last_text;
	}

	cache->last_text += sz;
}

/******************************************************************************
 *                                                                            *
 * Function: DCvacuum_text                                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DCvacuum_text()
{
	const char	*__function_name = "DCvacuum_text";
	int		n, f;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() text_free:%d/%d",
			__function_name, cache->text_free, CONFIG_TEXT_CACHE_SIZE);

	if (CONFIG_TEXT_CACHE_SIZE / 1024 >= cache->text_free)
		goto exit;

	cache->last_text = cache->text;

	for (n = cache->history_num, f = cache->history_first; 0 < n; n--, f++)
	{
		if (ZBX_HISTORY_SIZE == f)
			f = 0;

		if (ITEM_STATE_NOTSUPPORTED == cache->history[f].state)
		{
			DCmove_text(&cache->history[f].value_orig.err);
			continue;
		}

		switch (cache->history[f].value_type)
		{
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				DCmove_text(&cache->history[f].value_orig.str);
				break;
			case ITEM_VALUE_TYPE_LOG:
				DCmove_text(&cache->history[f].value_orig.str);
				if (NULL != cache->history[f].value.str)
					DCmove_text(&cache->history[f].value.str);
				break;
		}
	}
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_history_ptr                                                *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_HISTORY	*DCget_history_ptr(size_t text_len)
{
	ZBX_DC_HISTORY	*history;
	int		f;
	size_t		free_len;
retry:
	if (cache->history_num == ZBX_HISTORY_SIZE)
	{
		DCvacuum_history();

		if (cache->history_num == ZBX_HISTORY_SIZE)
		{
			UNLOCK_CACHE;

			zabbix_log(LOG_LEVEL_DEBUG, "History buffer is full. Sleeping for 1 second.");
			sleep(1);

			LOCK_CACHE;

			goto retry;
		}
	}

	if (0 != text_len)
	{
		if (text_len > CONFIG_TEXT_CACHE_SIZE)
		{
			zabbix_log(LOG_LEVEL_ERR, "insufficient shared memory for text cache");
			exit(EXIT_FAILURE);
		}

		free_len = CONFIG_TEXT_CACHE_SIZE - (cache->last_text - cache->text);

		if (text_len > free_len)
		{
			DCvacuum_text();

			free_len = CONFIG_TEXT_CACHE_SIZE - (cache->last_text - cache->text);

			if (text_len > free_len)
			{
				UNLOCK_CACHE;

				zabbix_log(LOG_LEVEL_DEBUG, "History text buffer is full. Sleeping for 1 second.");
				sleep(1);

				LOCK_CACHE;

				goto retry;
			}
		}
	}

	if (ZBX_HISTORY_SIZE <= (f = (cache->history_first + cache->history_num)))
		f -= ZBX_HISTORY_SIZE;
	history = &cache->history[f];
	history->num = 1;
	history->keep_history = 0;
	history->keep_trends = 0;

	cache->history_num++;

	return history;
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

static void	DCadd_text(char **dst, const char *src, size_t len)
{
	*dst = cache->last_text;
	cache->last_text += len;
	cache->text_free -= len;

	len--;	/* '\0' */
	memcpy(*dst, src, len);
	(*dst)[len] = '\0';
}

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
	zbx_uint64_t	lastlogsize;	/* for log items only */
	int		timestamp;	/* for log items only */
	int		severity;	/* for log items only */
	int		logeventid;	/* for log items only */
	int		mtime;		/* for log items only */
	unsigned char	value_type;
	unsigned char	state;
	unsigned char	flags;
}
dc_item_value_t;

typedef struct
{
	zbx_uint64_t	itemid;
	size_t		perror, len;
	zbx_timespec_t	ts;
}
value_notsupported_t;

#define ZBX_MAX_VALUES_LOCAL	256
#define ZBX_STRUCT_REALLOC_STEP	8
#define ZBX_STRING_REALLOC_STEP	ZBX_KIBIBYTE
static char		*string_values = NULL;
static size_t		string_values_alloc = 0, string_values_offset = 0;
static dc_item_value_t	*item_values = NULL;
static size_t		item_values_alloc = 0, item_values_num = 0;

static void	DCadd_history_dbl(dc_item_value_t *value)
{
	ZBX_DC_HISTORY	*history;

	DCcheck_ns(&value->ts);

	history = DCget_history_ptr(0);

	history->itemid = value->itemid;
	history->ts = value->ts;
	history->state = ITEM_STATE_NORMAL;
	history->value_type = ITEM_VALUE_TYPE_FLOAT;
	history->value_orig.dbl = value->value.value_dbl;
	history->value.dbl = 0;
	history->value_null = 0;

	cache->stats.history_counter++;
	cache->stats.history_float_counter++;
}

static void	DCadd_history_uint(dc_item_value_t *value)
{
	ZBX_DC_HISTORY	*history;

	DCcheck_ns(&value->ts);

	history = DCget_history_ptr(0);

	history->itemid = value->itemid;
	history->ts = value->ts;
	history->state = ITEM_STATE_NORMAL;
	history->value_type = ITEM_VALUE_TYPE_UINT64;
	history->value_orig.ui64 = value->value.value_uint;
	history->value.ui64 = 0;
	history->value_null = 0;

	cache->stats.history_counter++;
	cache->stats.history_uint_counter++;
}

static void	DCadd_history_str(dc_item_value_t *value)
{
	ZBX_DC_HISTORY	*history;

	DCcheck_ns(&value->ts);

	history = DCget_history_ptr(value->value.value_str.len);

	history->itemid = value->itemid;
	history->ts = value->ts;
	history->state = ITEM_STATE_NORMAL;
	history->value_type = ITEM_VALUE_TYPE_STR;
	DCadd_text(&history->value_orig.str, &string_values[value->value.value_str.pvalue], value->value.value_str.len);
	history->value_null = 0;

	cache->stats.history_counter++;
	cache->stats.history_str_counter++;
}

static void	DCadd_history_text(dc_item_value_t *value)
{
	ZBX_DC_HISTORY	*history;

	DCcheck_ns(&value->ts);

	history = DCget_history_ptr(value->value.value_str.len);

	history->itemid = value->itemid;
	history->ts = value->ts;
	history->state = ITEM_STATE_NORMAL;
	history->value_type = ITEM_VALUE_TYPE_TEXT;
	DCadd_text(&history->value_orig.str, &string_values[value->value.value_str.pvalue], value->value.value_str.len);
	history->value_null = 0;

	cache->stats.history_counter++;
	cache->stats.history_text_counter++;
}

/* lld item values should be stored without a limit */
static void	DCadd_history_lld(dc_item_value_t *value)
{
	ZBX_DC_HISTORY	*history;

	DCcheck_ns(&value->ts);

	history = DCget_history_ptr(value->value.value_str.len);

	history->itemid = value->itemid;
	history->ts = value->ts;
	history->state = ITEM_STATE_NORMAL;
	history->value_type = ITEM_VALUE_TYPE_TEXT;
	DCadd_text(&history->value_orig.str, &string_values[value->value.value_str.pvalue], value->value.value_str.len);
	history->value_null = 0;

	cache->stats.history_counter++;
	cache->stats.history_text_counter++;
}

static void	DCadd_history_log(dc_item_value_t *value)
{
	ZBX_DC_HISTORY	*history;

	DCcheck_ns(&value->ts);

	history = DCget_history_ptr(value->value.value_str.len + value->source.len);

	history->itemid = value->itemid;
	history->ts = value->ts;
	history->state = ITEM_STATE_NORMAL;
	history->value_type = ITEM_VALUE_TYPE_LOG;
	DCadd_text(&history->value_orig.str, &string_values[value->value.value_str.pvalue], value->value.value_str.len);
	history->value_null = 0;
	history->timestamp = value->timestamp;

	if (0 != value->source.len)
		DCadd_text(&history->value.str, &string_values[value->source.pvalue], value->source.len);
	else
		history->value.str = NULL;

	history->severity = value->severity;
	history->logeventid = value->logeventid;
	history->lastlogsize = value->lastlogsize;
	history->mtime = value->mtime;

	cache->stats.history_counter++;
	cache->stats.history_log_counter++;
}

static void	DCadd_history_notsupported(dc_item_value_t *value)
{
	ZBX_DC_HISTORY	*history;

	DCcheck_ns(&value->ts);

	history = DCget_history_ptr(value->value.value_str.len);

	history->itemid = value->itemid;
	history->ts = value->ts;
	history->state = ITEM_STATE_NOTSUPPORTED;
	DCadd_text(&history->value_orig.err, &string_values[value->value.value_str.pvalue], value->value.value_str.len);
	history->value_null = 1;

	cache->stats.notsupported_counter++;
}

static void	dc_string_buffer_realloc(size_t len)
{
	while (string_values_alloc < string_values_offset + len)
	{
		string_values_alloc += ZBX_STRING_REALLOC_STEP;
		string_values = zbx_realloc(string_values, string_values_alloc);
	}
}

static dc_item_value_t	*dc_local_get_history_slot()
{
	if (ZBX_MAX_VALUES_LOCAL == item_values_num)
		dc_flush_history();

	while (item_values_alloc == item_values_num)
	{
		item_values_alloc += ZBX_STRUCT_REALLOC_STEP;
		item_values = zbx_realloc(item_values, item_values_alloc * sizeof(dc_item_value_t));
	}

	return &item_values[item_values_num++];
}

static void	dc_local_add_history_dbl(zbx_uint64_t itemid, zbx_timespec_t *ts, double value_orig)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->value_type = ITEM_VALUE_TYPE_FLOAT;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = 0;
	item_value->value.value_dbl = value_orig;
}

static void	dc_local_add_history_uint(zbx_uint64_t itemid, zbx_timespec_t *ts, zbx_uint64_t value_orig)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->value_type = ITEM_VALUE_TYPE_UINT64;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = 0;
	item_value->value.value_uint = value_orig;
}

static void	dc_local_add_history_str(zbx_uint64_t itemid, zbx_timespec_t *ts, const char *value_orig)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->value_type = ITEM_VALUE_TYPE_STR;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = 0;
	item_value->value.value_str.len = zbx_db_strlen_n(value_orig, HISTORY_STR_VALUE_LEN) + 1;

	dc_string_buffer_realloc(item_value->value.value_str.len);
	item_value->value.value_str.pvalue = string_values_offset;
	memcpy(&string_values[string_values_offset], value_orig, item_value->value.value_str.len);
	string_values_offset += item_value->value.value_str.len;
}

static void	dc_local_add_history_text(zbx_uint64_t itemid, zbx_timespec_t *ts, const char *value_orig)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->value_type = ITEM_VALUE_TYPE_TEXT;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = 0;
	item_value->value.value_str.len = zbx_db_strlen_n(value_orig, HISTORY_TEXT_VALUE_LEN) + 1;

	dc_string_buffer_realloc(item_value->value.value_str.len);
	item_value->value.value_str.pvalue = string_values_offset;
	memcpy(&string_values[string_values_offset], value_orig, item_value->value.value_str.len);
	string_values_offset += item_value->value.value_str.len;
}

static void	dc_local_add_history_log(zbx_uint64_t itemid, zbx_timespec_t *ts, const char *value_orig,
		int timestamp, const char *source, int severity, int logeventid, zbx_uint64_t lastlogsize, int mtime)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->value_type = ITEM_VALUE_TYPE_LOG;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = 0;
	item_value->value.value_str.len = zbx_db_strlen_n(value_orig, HISTORY_LOG_VALUE_LEN) + 1;
	item_value->timestamp = timestamp;
	if (NULL != source && '\0' != *source)
		item_value->source.len = zbx_db_strlen_n(source, HISTORY_LOG_SOURCE_LEN) + 1;
	else
		item_value->source.len = 0;
	item_value->severity = severity;
	item_value->logeventid = logeventid;
	item_value->lastlogsize = lastlogsize;
	item_value->mtime = mtime;

	dc_string_buffer_realloc(item_value->value.value_str.len + item_value->source.len);
	item_value->value.value_str.pvalue = string_values_offset;
	memcpy(&string_values[string_values_offset], value_orig, item_value->value.value_str.len);
	string_values_offset += item_value->value.value_str.len;
	if (0 != item_value->source.len)
	{
		item_value->source.pvalue = string_values_offset;
		memcpy(&string_values[string_values_offset], source, item_value->source.len);
		string_values_offset += item_value->source.len;
	}
}

static void	dc_local_add_history_notsupported(zbx_uint64_t itemid, zbx_timespec_t *ts, const char *error)
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

static void	dc_local_add_history_lld(zbx_uint64_t itemid, zbx_timespec_t *ts, const char *value_orig)
{
	dc_item_value_t	*item_value;

	item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = ZBX_FLAG_DISCOVERY_RULE;
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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	dc_add_history(zbx_uint64_t itemid, unsigned char value_type, unsigned char flags, AGENT_RESULT *value,
		zbx_timespec_t *ts, unsigned char state, const char *error)
{
	if (ITEM_STATE_NOTSUPPORTED == state)
	{
		dc_local_add_history_notsupported(itemid, ts, error);
		return;
	}

	if (0 != (ZBX_FLAG_DISCOVERY_RULE & flags))
	{
		if (NULL == GET_TEXT_RESULT(value))
			return;

		/* server processes low-level discovery (lld) items while proxy stores their values in db */
		if (0 != (ZBX_DAEMON_TYPE_SERVER & daemon_type))
			lld_process_discovery_rule(itemid, value->text, ts);
		else
			dc_local_add_history_lld(itemid, ts, value->text);

		return;
	}

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (GET_DBL_RESULT(value))
				dc_local_add_history_dbl(itemid, ts, value->dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (GET_UI64_RESULT(value))
				dc_local_add_history_uint(itemid, ts, value->ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
			if (GET_STR_RESULT(value))
				dc_local_add_history_str(itemid, ts, value->str);
			break;
		case ITEM_VALUE_TYPE_TEXT:
			if (GET_TEXT_RESULT(value))
				dc_local_add_history_text(itemid, ts, value->text);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (GET_LOG_RESULT(value))
			{
				size_t		i;
				zbx_log_t	*log;

				for (i = 0; NULL != value->logs[i]; i++)
				{
					log = value->logs[i];

					dc_local_add_history_log(itemid, ts, log->value, log->timestamp, log->source,
							log->severity, log->logeventid, log->lastlogsize, log->mtime);
				}
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
	size_t		i;
	dc_item_value_t	*item_value;

	if (0 == item_values_num)
		return;

	LOCK_CACHE;

	for (i = 0; i < item_values_num; i++)
	{
		item_value = &item_values[i];

		if (ITEM_STATE_NOTSUPPORTED == item_value->state)
		{
			DCadd_history_notsupported(item_value);
		}
		else if (0 != (ZBX_FLAG_DISCOVERY_RULE & item_value->flags))
		{
			DCadd_history_lld(item_value);
		}
		else
		{
			switch (item_value->value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
					DCadd_history_dbl(item_value);
					break;
				case ITEM_VALUE_TYPE_UINT64:
					DCadd_history_uint(item_value);
					break;
				case ITEM_VALUE_TYPE_STR:
					DCadd_history_str(item_value);
					break;
				case ITEM_VALUE_TYPE_TEXT:
					DCadd_history_text(item_value);
					break;
				case ITEM_VALUE_TYPE_LOG:
					DCadd_history_log(item_value);
					break;
			}
		}
	}

	UNLOCK_CACHE;

	item_values_num = string_values_offset = 0;
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

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&trends_lock, ZBX_MUTEX_TRENDS))
	{
		zbx_error("cannot create mutex for trend cache");
		exit(EXIT_FAILURE);
	}

	sz = zbx_mem_required_size(1, "trend cache", "TrendCacheSize");
	zbx_mem_create(&trend_mem, trend_shm_key, ZBX_NO_MUTEX, CONFIG_TRENDS_CACHE_SIZE,
			"trend cache", "TrendCacheSize", 0);
	CONFIG_TRENDS_CACHE_SIZE -= sz;

	cache->trends_num = 0;

#define INIT_HASHSET_SIZE	1000	/* should be calculated dynamically based on trends size? */

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

ZBX_MEM_FUNC1_IMPL_MALLOC(__history, history_mem);
ZBX_MEM_FUNC1_IMPL_MALLOC(__history_text, history_text_mem);

void	init_database_cache()
{
	const char	*__function_name = "init_database_cache";
	key_t		history_shm_key, history_text_shm_key;
	size_t		sz, sz_itemids, sz_min, sz_history;
	int		itemids_alloc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (-1 == (history_shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_HISTORY_ID)) ||
			-1 == (history_text_shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_HISTORY_TEXT_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC keys for history cache");
		exit(EXIT_FAILURE);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cache_lock, ZBX_MUTEX_CACHE))
	{
		zbx_error("cannot create mutex for history cache");
		exit(EXIT_FAILURE);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cache_ids_lock, ZBX_MUTEX_CACHE_IDS))
	{
		zbx_error("cannot create mutex for IDs cache");
		exit(EXIT_FAILURE);
	}

	itemids_alloc = CONFIG_HISTSYNCER_FORKS * ZBX_SYNC_MAX;
	sz_itemids = itemids_alloc * sizeof(zbx_uint64_t);

	/* history cache */

	sz = zbx_mem_required_size(4, "history cache", "HistoryCacheSize");
	sz += sizeof(ZBX_DC_CACHE);
	sz += sz_itemids;
	sz += sizeof(ZBX_DC_IDS);

	sz_min = sz + ZBX_SYNC_MAX * sizeof(ZBX_DC_HISTORY);
	if (CONFIG_HISTORY_CACHE_SIZE < sz_min)
		CONFIG_HISTORY_CACHE_SIZE = sz_min;

	ZBX_HISTORY_SIZE = (CONFIG_HISTORY_CACHE_SIZE - sz) / sizeof(ZBX_DC_HISTORY);
	sz_history = ZBX_HISTORY_SIZE * sizeof(ZBX_DC_HISTORY);

	sz += sz_history;

	zbx_mem_create(&history_mem, history_shm_key, ZBX_NO_MUTEX, sz, "history cache", "HistoryCacheSize", 0);

	cache = (ZBX_DC_CACHE *)__history_mem_malloc_func(NULL, sizeof(ZBX_DC_CACHE));

	cache->history = (ZBX_DC_HISTORY *)__history_mem_malloc_func(NULL, sz_history);
	cache->history_first = 0;
	cache->history_num = 0;
	cache->itemids = (zbx_uint64_t *)__history_mem_malloc_func(NULL, sz_itemids);
	cache->itemids_alloc = itemids_alloc;
	cache->itemids_num = 0;
	memset(&cache->stats, 0, sizeof(ZBX_DC_STATS));

	ids = (ZBX_DC_IDS *)__history_mem_malloc_func(NULL, sizeof(ZBX_DC_IDS));
	memset(ids, 0, sizeof(ZBX_DC_IDS));

	/* history text cache */

	sz = zbx_mem_required_size(1, "history text cache", "HistoryTextCacheSize");

	zbx_mem_create(&history_text_mem, history_text_shm_key, ZBX_NO_MUTEX, CONFIG_TEXT_CACHE_SIZE,
			"history text cache", "HistoryTextCacheSize", 0);
	CONFIG_TEXT_CACHE_SIZE -= sz;

	cache->text = (char *)__history_text_mem_malloc_func(NULL, CONFIG_TEXT_CACHE_SIZE);
	cache->last_text = cache->text;
	cache->text_free = CONFIG_TEXT_CACHE_SIZE;

	/* trend cache */
	if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
		init_trend_cache();

	cache->last_ts.sec = 0;
	cache->last_ts.ns = 0;

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
	zabbix_log(LOG_LEVEL_DEBUG, "In DCsync_all()");

	DCsync_history(ZBX_SYNC_FULL);
	if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
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
	zbx_mem_destroy(history_mem);
	zbx_mem_destroy(history_text_mem);
	if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
		zbx_mem_destroy(trend_mem);

	zbx_mutex_destroy(&cache_lock);
	zbx_mutex_destroy(&cache_ids_lock);
	if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
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
