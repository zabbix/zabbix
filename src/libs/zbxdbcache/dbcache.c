/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "log.h"
#include "zlog.h"
#include "threads.h"

#include "db.h"
#include "dbcache.h"
#include "ipc.h"
#include "mutexs.h"
#include "zbxserver.h"

#include "memalloc.h"
#include "zbxalgo.h"

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
static int		sql_allocated = 65536;

static unsigned char	zbx_process;

extern int		CONFIG_DBSYNCER_FREQUENCY;

static int		ZBX_HISTORY_SIZE = 0;
int			ZBX_SYNC_MAX = 1000;	/* Must be less than ZBX_HISTORY_SIZE */
static int		ZBX_ITEMIDS_SIZE = 0;

#define ZBX_IDS_SIZE	10
#define ZBX_DC_ID	struct zbx_dc_id_type
#define ZBX_DC_IDS	struct zbx_dc_ids_type

ZBX_DC_ID
{
	char		table_name[16];
	zbx_uint64_t	lastid;
	int		reserved;
};

ZBX_DC_IDS
{
	ZBX_DC_ID	id[ZBX_IDS_SIZE];
};

ZBX_DC_IDS		*ids = NULL;

typedef union {
	double		value_float;
	zbx_uint64_t	value_uint64;
	char		*value_str;
} history_value_t;

#define ZBX_DC_HISTORY	struct zbx_dc_history_type
#define ZBX_DC_TREND	struct zbx_dc_trend_type
#define ZBX_DC_STATS	struct zbx_dc_stats_type
#define ZBX_DC_CACHE	struct zbx_dc_cache_type

ZBX_DC_HISTORY
{
	zbx_uint64_t	itemid;
	history_value_t	value_orig;
	history_value_t	value;
	char		*source;
	int		clock;
	int		ns;
	int		timestamp;
	int		severity;
	int		logeventid;
	int		lastlogsize;
	int		mtime;
	unsigned char	value_type;
	unsigned char	value_null;
	unsigned char	keep_history;
	unsigned char	keep_trends;
};

ZBX_DC_TREND
{
	zbx_uint64_t	itemid;
	history_value_t	value_min;
	history_value_t	value_avg;
	history_value_t	value_max;
	int		clock;
	int		num;
	int		disable_from;
	unsigned char	value_type;
};

ZBX_DC_STATS
{
	zbx_uint64_t	history_counter;	/* Total number of saved values in the DB */
	zbx_uint64_t	history_float_counter;	/* Number of saved float values in the DB */
	zbx_uint64_t	history_uint_counter;	/* Number of saved uint values in the DB */
	zbx_uint64_t	history_str_counter;	/* Number of saved str values in the DB */
	zbx_uint64_t	history_log_counter;	/* Number of saved log values in the DB */
	zbx_uint64_t	history_text_counter;	/* Number of saved text values in the DB */
};

ZBX_DC_CACHE
{
	zbx_hashset_t	trends;
	ZBX_DC_STATS	stats;
	ZBX_DC_HISTORY	*history;	/* [ZBX_HISTORY_SIZE] */
	char		*text;		/* [ZBX_TEXTBUFFER_SIZE] */
	zbx_uint64_t	*itemids;	/* items, processed by other syncers */
	char		*last_text;
	int		history_first;
	int		history_num;
	int		trends_num;
	int		itemids_alloc, itemids_num;
	zbx_timespec_t	last_ts;
};

ZBX_DC_CACHE		*cache = NULL;

/******************************************************************************
 *                                                                            *
 * Function: DCget_stats                                                      *
 *                                                                            *
 * Purpose: get statistics of the database cache                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	*DCget_stats(int request)
{
	static zbx_uint64_t	value_uint;
	static double		value_double;
	char			*first_text = NULL;
	size_t			free_len = 0;
	int			i, index;

	switch (request)
	{
	case ZBX_STATS_TEXT_USED:
	case ZBX_STATS_TEXT_FREE:
	case ZBX_STATS_TEXT_PFREE:
		free_len = CONFIG_TEXT_CACHE_SIZE;

		LOCK_CACHE;

		for (i = 0; i < cache->history_num; i++)
		{
			index = (cache->history_first + i) % ZBX_HISTORY_SIZE;
			if (cache->history[index].value_type == ITEM_VALUE_TYPE_STR
					|| cache->history[index].value_type == ITEM_VALUE_TYPE_TEXT
					|| cache->history[index].value_type == ITEM_VALUE_TYPE_LOG)
			{
				first_text = cache->history[index].value_orig.value_str;
				break;
			}
		}

		if (NULL != first_text)
			free_len -= cache->last_text - first_text;

		UNLOCK_CACHE;

		break;
	}

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
	case ZBX_STATS_HISTORY_TOTAL:
		value_uint = CONFIG_HISTORY_CACHE_SIZE;
		return &value_uint;
	case ZBX_STATS_HISTORY_USED:
		value_uint = cache->history_num * sizeof(ZBX_DC_HISTORY);
		return &value_uint;
	case ZBX_STATS_HISTORY_FREE:
		value_uint = CONFIG_HISTORY_CACHE_SIZE - cache->history_num * sizeof(ZBX_DC_HISTORY);
		return &value_uint;
	case ZBX_STATS_HISTORY_PFREE:
		value_double = 100 * ((double)(ZBX_HISTORY_SIZE - cache->history_num) / ZBX_HISTORY_SIZE);
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
		value_uint = CONFIG_TEXT_CACHE_SIZE - free_len;
		return &value_uint;
	case ZBX_STATS_TEXT_FREE:
		value_uint = free_len;
		return &value_uint;
	case ZBX_STATS_TEXT_PFREE:
		value_double = 100.0 * ((double)free_len / CONFIG_TEXT_CACHE_SIZE);
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
 * Parameters:                                                                *
 *                                                                            *
 * Return value: pointer to a trend structure                                 *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_TREND	*DCget_trend(zbx_uint64_t itemid)
{
	ZBX_DC_TREND	*ptr, trend;

	if (NULL != (ptr = (ZBX_DC_TREND *)zbx_hashset_search(&cache->trends, &itemid)))
		return ptr;

	memset(&trend, 0, sizeof(ZBX_DC_TREND));
	trend.itemid = itemid;
	ptr = (ZBX_DC_TREND *)zbx_hashset_insert(&cache->trends, &trend,
			sizeof(ZBX_DC_TREND));

	return ptr;
}

/******************************************************************************
 *                                                                            *
 * Function: DCflush_trends                                                   *
 *                                                                            *
 * Purpose: flush trend to the database                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCflush_trends(ZBX_DC_TREND *trends, int *trends_num, int update_cache)
{
	const char	*__function_name = "DCflush_trends";
	DB_RESULT	result;
	DB_ROW		row;
	int		num, i, clock, sql_offset;
	history_value_t	value_min, value_avg, value_max;
	unsigned char	value_type;
	zbx_uint64_t	*ids = NULL, itemid;
	int		ids_alloc, ids_num = 0;
	ZBX_DC_TREND	*trend = NULL;
	const char	*table_name;
#ifdef HAVE_MULTIROW_INSERT
	int		tmp_offset;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d",
			__function_name, *trends_num);

	clock = trends[0].clock;
	value_type = trends[0].value_type;

	switch (value_type) {
	case ITEM_VALUE_TYPE_FLOAT: table_name = "trends"; break;
	case ITEM_VALUE_TYPE_UINT64: table_name = "trends_uint"; break;
	default:
		zbx_error("Unsupported value type for trends");
		assert(0 == 1);
	}

	ids_alloc = *trends_num;
	ids = zbx_malloc(ids, ids_alloc * sizeof(zbx_uint64_t));

	for (i = 0; i < *trends_num; i++)
	{
		trend = &trends[i];

		if (clock != trend->clock || value_type != trend->value_type)
			continue;

		if (trend->disable_from != 0 && trend->disable_from <= clock)
			continue;

		uint64_array_add(&ids, &ids_alloc, &ids_num, trend->itemid, 64);
	}

	if (0 != ids_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 96,
				"select distinct itemid"
				" from %s"
				" where clock>=%d and",
				table_name,
				clock);

		DBadd_condition_alloc(&sql, &sql_allocated, &sql_offset, "itemid", ids, ids_num);

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

			for (i = 0; i < *trends_num; i++)
			{
				trend = &trends[i];

				if (itemid == trend->itemid && clock == trend->clock &&
						value_type == trend->value_type)
					break;
			}

			if (i == *trends_num)
				continue;	/* this should never happen */

			trend->disable_from = clock;

			/* if 'trends' is not a primary trends buffer */
			if (0 != update_cache)
			{
				LOCK_TRENDS;

				/* we update it too */
				if (NULL != (trend = zbx_hashset_search(&cache->trends, &itemid)))
					trend->disable_from = clock;

				UNLOCK_TRENDS;
			}
		}
	}

	ids_num = 0;

	for (i = 0; i < *trends_num; i++)
	{
		trend = &trends[i];

		if (clock != trend->clock || value_type != trend->value_type)
			continue;

		if (trend->disable_from != 0 && trend->disable_from <= clock)
			continue;

		uint64_array_add(&ids, &ids_alloc, &ids_num, trend->itemid, 64);
	}

	if (0 != ids_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
				"select itemid,num,value_min,value_avg,value_max"
				" from %s"
				" where clock=%d and",
				table_name,
				clock);

		DBadd_condition_alloc(&sql, &sql_allocated, &sql_offset, "itemid", ids, ids_num);

		result = DBselect("%s", sql);

		sql_offset = 0;
#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);

			for (i = 0; i < *trends_num; i++)
			{
				trend = &trends[i];

				if (itemid == trend->itemid && clock == trend->clock &&
						value_type == trend->value_type)
					break;
			}

			if (i == *trends_num)
				continue;	/* this should never happen */

			num = atoi(row[1]);

			if (value_type == ITEM_VALUE_TYPE_FLOAT)
			{
				value_min.value_float = atof(row[2]);
				value_avg.value_float = atof(row[3]);
				value_max.value_float = atof(row[4]);

				if (value_min.value_float < trend->value_min.value_float)
					trend->value_min.value_float = value_min.value_float;
				if (value_max.value_float > trend->value_max.value_float)
					trend->value_max.value_float = value_max.value_float;
				trend->value_avg.value_float = (trend->num * trend->value_avg.value_float
						+ num * value_avg.value_float) / (trend->num + num);
				trend->num += num;

				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"update trends set num=%d,value_min=" ZBX_FS_DBL ",value_avg=" ZBX_FS_DBL
						",value_max=" ZBX_FS_DBL " where itemid=" ZBX_FS_UI64 " and clock=%d;\n",
						trend->num,
						trend->value_min.value_float,
						trend->value_avg.value_float,
						trend->value_max.value_float,
						trend->itemid,
						trend->clock);
			}
			else
			{
				ZBX_STR2UINT64(value_min.value_uint64, row[2]);
				ZBX_STR2UINT64(value_avg.value_uint64, row[3]);
				ZBX_STR2UINT64(value_max.value_uint64, row[4]);

				if (value_min.value_uint64 < trend->value_min.value_uint64)
					trend->value_min.value_uint64 = value_min.value_uint64;
				if (value_max.value_uint64 > trend->value_max.value_uint64)
					trend->value_max.value_uint64 = value_max.value_uint64;
				trend->value_avg.value_uint64 = (trend->num * trend->value_avg.value_uint64
						+ num * value_avg.value_uint64) / (trend->num + num);
				trend->num += num;

				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"update trends_uint set num=%d,value_min=" ZBX_FS_UI64 ",value_avg=" ZBX_FS_UI64
						",value_max=" ZBX_FS_UI64 " where itemid=" ZBX_FS_UI64 " and clock=%d;\n",
						trend->num,
						trend->value_min.value_uint64,
						trend->value_avg.value_uint64,
						trend->value_max.value_uint64,
						trend->itemid,
						trend->clock);
			}

			trend->itemid = 0;
		}
		DBfree_result(result);

#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

		if (sql_offset > 16)	/* In ORACLE always present begin..end; */
			DBexecute("%s", sql);
	}

	zbx_free(ids);

	sql_offset = 0;
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	if (value_type == ITEM_VALUE_TYPE_FLOAT)
	{
#ifdef HAVE_MULTIROW_INSERT
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 96,
				"insert into trends (itemid,clock,num,value_min,value_avg,value_max) values ");
#endif
		for (i = 0; i < *trends_num; i++)
		{
			trend = &trends[i];

			if (0 == trend->itemid)
				continue;

			if (clock != trend->clock || value_type != trend->value_type)
				continue;

#ifdef HAVE_MULTIROW_INSERT
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d,%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL "),",
					trend->itemid,
					trend->clock,
					trend->num,
					trend->value_min.value_float,
					trend->value_avg.value_float,
					trend->value_max.value_float);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into trends (itemid,clock,num,value_min,value_avg,value_max)"
					" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL ");\n",
					trend->itemid,
					trend->clock,
					trend->num,
					trend->value_min.value_float,
					trend->value_avg.value_float,
					trend->value_max.value_float);
#endif
			trend->itemid = 0;
		}
#ifdef HAVE_MULTIROW_INSERT
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}
	else
	{
#ifdef HAVE_MULTIROW_INSERT
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 96,
				"insert into trends_uint (itemid,clock,num,value_min,value_avg,value_max) values ");
#endif
		for (i = 0; i < *trends_num; i++)
		{
			trend = &trends[i];

			if (0 == trend->itemid)
				continue;

			if (clock != trend->clock || value_type != trend->value_type)
				continue;

#ifdef HAVE_MULTIROW_INSERT
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
					"(" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "),",
					trend->itemid,
					trend->clock,
					trend->num,
					trend->value_min.value_uint64,
					trend->value_avg.value_uint64,
					trend->value_max.value_uint64);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 256,
					"insert into trends_uint (itemid,clock,num,value_min,value_avg,value_max)"
					" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ");\n",
					trend->itemid,
					trend->clock,
					trend->num,
					trend->value_min.value_uint64,
					trend->value_avg.value_uint64,
					trend->value_max.value_uint64);
#endif
			trend->itemid = 0;
		}
#ifdef HAVE_MULTIROW_INSERT
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

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
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
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
	memset(&trend->value_avg, 0, sizeof(history_value_t));
	memset(&trend->value_max, 0, sizeof(history_value_t));
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_trend                                                      *
 *                                                                            *
 * Purpose: add new value to the trends                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_trend(ZBX_DC_HISTORY *history, ZBX_DC_TREND **trends, int *trends_alloc, int *trends_num)
{
	ZBX_DC_TREND	*trend = NULL;
	int		hour;

	hour = history->clock - history->clock % 3600;

	trend = DCget_trend(history->itemid);

	if (trend->num > 0 && (trend->clock != hour || trend->value_type != history->value_type))
		DCflush_trend(trend, trends, trends_alloc, trends_num);

	trend->value_type = history->value_type;
	trend->clock = hour;

	switch (trend->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (trend->num == 0 || history->value.value_float < trend->value_min.value_float)
				trend->value_min.value_float = history->value.value_float;
			if (trend->num == 0 || history->value.value_float > trend->value_max.value_float)
				trend->value_max.value_float = history->value.value_float;
			trend->value_avg.value_float = (trend->num * trend->value_avg.value_float
				+ history->value.value_float) / (trend->num + 1);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (trend->num == 0 || history->value.value_uint64 < trend->value_min.value_uint64)
				trend->value_min.value_uint64 = history->value.value_uint64;
			if (trend->num == 0 || history->value.value_uint64 > trend->value_max.value_uint64)
				trend->value_max.value_uint64 = history->value.value_uint64;
			trend->value_avg.value_uint64 = (trend->num * trend->value_avg.value_uint64
				+ history->value.value_uint64) / (trend->num + 1);
			break;
	}
	trend->num++;
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_update_trends                                             *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
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
		if (0 == history[i].keep_trends)
			continue;

		if (history[i].value_type != ITEM_VALUE_TYPE_FLOAT &&
				history[i].value_type != ITEM_VALUE_TYPE_UINT64)
			continue;

		if (0 != history[i].value_null)
			continue;

		DCadd_trend(&history[i], &trends, &trends_alloc, &trends_num);
	}

	UNLOCK_TRENDS;

	while (trends_num > 0)
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
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_trends()
{
	const char		*__function_name = "DCsync_trends";
	zbx_hashset_iter_t	iter;
	ZBX_DC_TREND		*trends = NULL, *trend;
	int			trends_alloc = 0, trends_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d",
			__function_name, cache->trends_num);

	zabbix_log(LOG_LEVEL_WARNING, "Syncing trends data...");

	LOCK_TRENDS;

	zbx_hashset_iter_reset(&cache->trends, &iter);

	while (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_iter_next(&iter)))
		DCflush_trend(trend, &trends, &trends_alloc, &trends_num);

	UNLOCK_TRENDS;

	DBbegin();

	while (trends_num > 0)
		DCflush_trends(trends, &trends_num, 0);

	DBcommit();

	zabbix_log(LOG_LEVEL_WARNING, "Syncing trends data... done.");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_update_triggers                                           *
 *                                                                            *
 * Purpose: re-calculate and updates values of triggers related to the items  *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksander Vladishev                             *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_update_triggers(ZBX_DC_HISTORY *history, int history_num)
{
	const char	*__function_name = "DCmass_update_triggers";

	typedef struct zbx_trigger_s
	{
		zbx_uint64_t	triggerid;
		char		*exp;
		char		*error;
		zbx_timespec_t	ts;
		unsigned char	type;
		unsigned char	value;
		unsigned char	flags;
	} zbx_trigger_t;

	zbx_trigger_t	*tr = NULL, *tr_last = NULL;
	int		tr_alloc, tr_num = 0;

	char		error[MAX_STRING_LEN];
	int		exp_value;
	DB_RESULT	result;
	DB_ROW		row;
	int		sql_offset = 0, i;
	zbx_uint64_t	itemid, triggerid;
	unsigned char	flags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1024,
			"select distinct t.triggerid,t.type,t.value,t.error,t.expression,f.itemid,i.flags"
			" from triggers t,functions f,items i"
			" where i.status not in (%d)"
				" and i.itemid=f.itemid"
				" and t.status=%d"
				" and f.triggerid=t.triggerid"
				" and f.itemid in (",
			ITEM_STATUS_NOTSUPPORTED,
			TRIGGER_STATUS_ENABLED);

	for (i = 0; i < history_num; i++)
	{
		if (0 != history[i].value_null)
			continue;

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 22, ZBX_FS_UI64 ",",
				history[i].itemid);
	}

	if (sql[--sql_offset] == ',')
	{
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 23, ") order by t.triggerid");
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s():no items with triggers", __function_name);
		goto exit;
	}

	result = DBselect("%s", sql);

	sql_offset = 0;

	tr_alloc = history_num;
	tr = zbx_malloc(tr, tr_alloc * sizeof(zbx_trigger_t));

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		if (NULL == tr_last || tr_last->triggerid != triggerid)
		{
			if (tr_num == tr_alloc)
			{
				tr_alloc += 64;
				tr = zbx_realloc(tr, tr_alloc * sizeof(zbx_trigger_t));
			}

			tr_last = &tr[tr_num++];
			tr_last->triggerid = triggerid;
			tr_last->type = (unsigned char)atoi(row[1]);
			tr_last->value = (unsigned char)atoi(row[2]);
			tr_last->error = strdup(row[3]);
			tr_last->exp = strdup(row[4]);
			tr_last->ts.sec = 0;
			tr_last->ts.ns = 0;
			tr_last->flags = 0x00;
		}

		flags = (unsigned char)atoi(row[6]);

		if (0 != (ZBX_FLAG_DISCOVERY_CHILD & flags))
		{
			tr_last->flags = flags;
			continue;
		}

		ZBX_STR2UINT64(itemid, row[5]);

		for (i = 0; i < history_num; i++)
		{
			if (itemid == history[i].itemid)
			{
				if (tr_last->ts.sec < history[i].clock || 
						(tr_last->ts.sec == history[i].clock && tr_last->ts.ns < history[i].ns))
				{
					tr_last->ts.sec = history[i].clock;
					tr_last->ts.ns = history[i].ns;
				}
				break;
			}
		}
	}

	DBfree_result(result);

	for (i = 0; i < tr_num; i++)
	{
		/* skip triggers with expression with discovery child items */
		if (0 != (ZBX_FLAG_DISCOVERY_CHILD & tr_last->flags))
			continue;

		if (0 == tr[i].ts.sec)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		if (SUCCEED != evaluate_expression(&exp_value, &tr[i].exp, tr[i].ts.sec,
					tr[i].triggerid, tr[i].value, error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Expression [%s] cannot be evaluated: %s",
					tr[i].exp, error);
			zabbix_syslog("Expression [%s] cannot be evaluated: %s",
					tr[i].exp, error);

			DBupdate_trigger_value(tr[i].triggerid, tr[i].type, tr[i].value,
					tr[i].error, TRIGGER_VALUE_UNKNOWN, &tr[i].ts, error);
		}
		else
			DBupdate_trigger_value(tr[i].triggerid, tr[i].type, tr[i].value,
					tr[i].error, exp_value, &tr[i].ts, NULL);

		zbx_free(tr[i].error);
		zbx_free(tr[i].exp);
	}

	zbx_free(tr);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	DBchk_double(double value)
{
	/* field with precision 16, scale 4 [NUMERIC(16,4)] */
	register double	pg_min_numeric = (double)-1E12;
	register double	pg_max_numeric = (double)1E12;

	if (value <= pg_min_numeric || value >= pg_max_numeric)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_update_items                                              *
 *                                                                            *
 * Purpose: update items info after new value is received                     *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev, Aleksander Vladishev           *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_update_items(ZBX_DC_HISTORY *history, int history_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	char		*value_esc, *message = NULL;
	int		sql_offset = 0, i;
	ZBX_DC_HISTORY	*h;
	zbx_uint64_t	*ids = NULL;
	int		ids_alloc, ids_num = 0;
	unsigned char	status;

	zabbix_log( LOG_LEVEL_DEBUG, "In DCmass_update_items()");

	ids_alloc = history_num;
	ids = zbx_malloc(ids, ids_alloc * sizeof(zbx_uint64_t));

	for (i = 0; i < history_num; i++)
		uint64_array_add(&ids, &ids_alloc, &ids_num, history[i].itemid, 64);

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
			"select itemid,status,lastclock,prevorgvalue,delta,multiplier,formula,history,trends,lastns"
			" from items"
			" where");

	DBadd_condition_alloc(&sql, &sql_allocated, &sql_offset, "itemid", ids, ids_num);

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 20, " order by itemid");

	zbx_free(ids);

	result = DBselect("%s", sql);

	sql_offset = 0;

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(item.itemid, row[0]);

		h = NULL;

		for (i = 0; i < history_num; i++)
		{
			if (item.itemid == history[i].itemid)
			{
				h = &history[i];
				break;
			}
		}

		if (NULL == h)
			continue;

		item.status	= atoi(row[1]);
		if (SUCCEED != DBis_null(row[2]))
			item.lastclock	= atoi(row[2]);
		else
			item.lastclock	= 0;
		if (SUCCEED != DBis_null(row[9]))
			item.lastns	= atoi(row[9]);
		else
			item.lastns	= 0;
		if (SUCCEED != DBis_null(row[3]))
		{
			item.prevorgvalue_null	= 0;
			switch (h->value_type) {
			case ITEM_VALUE_TYPE_FLOAT:
				item.prevorgvalue_dbl = atof(row[3]);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				ZBX_STR2UINT64(item.prevorgvalue_uint64, row[3]);
				break;
			}
		}
		else
			item.prevorgvalue_null = 1;
		item.delta	= atoi(row[4]);
		item.multiplier	= atoi(row[5]);
		item.formula	= row[6];
		item.history	= atoi(row[7]);
		item.trends	= atoi(row[8]);

		if (0 != (zbx_process & ZBX_PROCESS_PROXY))
		{
			item.delta = ITEM_STORE_AS_IS;
			h->keep_history = 1;
			h->keep_trends = 0;
		}
		else
		{
			h->keep_history = (unsigned char)(item.history ? 1 : 0);
			h->keep_trends = (unsigned char)(item.trends ? 1 : 0);
		}

		status = ITEM_STATUS_ACTIVE;

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "update items set lastclock=%d,lastns=%d",
				h->clock, h->ns);

		switch (h->value_type) {
		case ITEM_VALUE_TYPE_FLOAT:
			switch (item.delta) {
			case ITEM_STORE_AS_IS:
				h->value.value_float = DBmultiply_value_float(&item, h->value_orig.value_float);

				if (SUCCEED == DBchk_double(h->value.value_float))
				{
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevvalue=lastvalue,prevorgvalue=NULL,lastvalue='" ZBX_FS_DBL "'",
							h->value.value_float);
				}
				else
				{
					status = ITEM_STATUS_NOTSUPPORTED;
					h->value_null = 1;
				}
				break;
			case ITEM_STORE_SPEED_PER_SECOND:
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_dbl <= h->value_orig.value_float &&
						(item.lastclock < h->clock || (item.lastclock == h->clock && item.lastns < h->ns)))
				{
					h->value.value_float = (h->value_orig.value_float - item.prevorgvalue_dbl) /
							((h->clock - item.lastclock) + (double)(h->ns - item.lastns) / 1000000000);
					h->value.value_float = DBmultiply_value_float(&item, h->value.value_float);

					if (SUCCEED == DBchk_double(h->value.value_float))
					{
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
								",prevvalue=lastvalue,prevorgvalue='" ZBX_FS_DBL "'"
								",lastvalue='" ZBX_FS_DBL "'",
								h->value_orig.value_float,
								h->value.value_float);
					}
					else
					{
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
								",prevorgvalue='" ZBX_FS_DBL "'",
								h->value_orig.value_float);
						status = ITEM_STATUS_NOTSUPPORTED;
						h->value_null = 1;
					}
				}
				else
				{
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevorgvalue='" ZBX_FS_DBL "'",
							h->value_orig.value_float);
					h->value_null = 1;
				}
				break;
			case ITEM_STORE_SIMPLE_CHANGE:
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_dbl <= h->value_orig.value_float)
				{
					h->value.value_float = h->value_orig.value_float - item.prevorgvalue_dbl;
					h->value.value_float = DBmultiply_value_float(&item, h->value.value_float);

					if (SUCCEED == DBchk_double(h->value.value_float))
					{
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
								",prevvalue=lastvalue,prevorgvalue='" ZBX_FS_DBL "'"
								",lastvalue='" ZBX_FS_DBL "'",
								h->value_orig.value_float,
								h->value.value_float);
					}
					else
					{
						zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
								",prevorgvalue='" ZBX_FS_DBL "'",
								h->value_orig.value_float);
						status = ITEM_STATUS_NOTSUPPORTED;
						h->value_null = 1;
					}
				}
				else
				{
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevorgvalue='" ZBX_FS_DBL "'",
							h->value_orig.value_float);
					h->value_null = 1;
				}
				break;
			}

			if (ITEM_STATUS_NOTSUPPORTED == status)
			{
				const char	*hostkey_name;

				hostkey_name = zbx_host_key_string(h->itemid);

				message = zbx_dsprintf(message, "Type of received value"
						" [" ZBX_FS_DBL "] is not suitable for value type [%s]",
						h->value.value_float,
						zbx_item_value_type_string(h->value_type));

				zabbix_log(LOG_LEVEL_WARNING, "Item [%s] error: %s",
						hostkey_name, message);
				zabbix_syslog("Item [%s] error: %s",
						hostkey_name, message);

				if (ITEM_STATUS_NOTSUPPORTED != item.status)
				{
					zabbix_log(LOG_LEVEL_WARNING, "Parameter [%s] is not supported, old status [%d]",
							hostkey_name, item.status);
					zabbix_syslog("Parameter [%s] is not supported",
							hostkey_name);
				}

				DCadd_nextcheck(h->itemid, h->clock, message);	/* update error & status field in items table */
				DCrequeue_reachable_item(h->itemid, ITEM_STATUS_NOTSUPPORTED, h->clock);

				zbx_free(message);
			}
			break;
		case ITEM_VALUE_TYPE_UINT64:
			switch (item.delta) {
			case ITEM_STORE_AS_IS:
				h->value.value_uint64 = DBmultiply_value_uint64(&item, h->value_orig.value_uint64);
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						",prevvalue=lastvalue,prevorgvalue=NULL,lastvalue='" ZBX_FS_UI64 "'",
						h->value.value_uint64);
				break;
			case ITEM_STORE_SPEED_PER_SECOND:
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_uint64 <= h->value_orig.value_uint64 &&
						(item.lastclock < h->clock || (item.lastclock == h->clock && item.lastns < h->ns)))
				{
					h->value.value_uint64 = (h->value_orig.value_uint64 - item.prevorgvalue_uint64) /
							((h->clock - item.lastclock) + (double)(h->ns - item.lastns) / 1000000000);
					h->value.value_uint64 = DBmultiply_value_uint64(&item, h->value.value_uint64);
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevvalue=lastvalue,prevorgvalue='" ZBX_FS_UI64 "'"
							",lastvalue='" ZBX_FS_UI64 "'",
							h->value_orig.value_uint64,
							h->value.value_uint64);
				}
				else
				{
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevorgvalue='" ZBX_FS_UI64 "'",
							h->value_orig.value_uint64);
					h->value_null = 1;
				}
				break;
			case ITEM_STORE_SIMPLE_CHANGE:
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_uint64 <= h->value_orig.value_uint64)
				{
					h->value.value_uint64 = h->value_orig.value_uint64 - item.prevorgvalue_uint64;
					h->value.value_uint64 = DBmultiply_value_uint64(&item, h->value.value_uint64);
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevvalue=lastvalue,prevorgvalue='" ZBX_FS_UI64 "'"
							",lastvalue='" ZBX_FS_UI64 "'",
							h->value_orig.value_uint64,
							h->value.value_uint64);
				}
				else
				{
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevorgvalue='" ZBX_FS_UI64 "'",
							h->value_orig.value_uint64);
					h->value_null = 1;
				}
				break;
			}
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			value_esc = DBdyn_escape_string_len(h->value_orig.value_str, ITEM_LASTVALUE_LEN);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 34 + strlen(value_esc),
					",prevvalue=lastvalue,lastvalue='%s'",
					value_esc);
			zbx_free(value_esc);
			break;
		case ITEM_VALUE_TYPE_LOG:
			value_esc = DBdyn_escape_string_len(h->value_orig.value_str, ITEM_LASTVALUE_LEN);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 74 + strlen(value_esc),
					",prevvalue=lastvalue,lastvalue='%s',lastlogsize=%d,mtime=%d",
					value_esc,
					h->lastlogsize,
					h->mtime);
			zbx_free(value_esc);
			break;
		}

		/* Update item status if required */
		if (item.status == ITEM_STATUS_NOTSUPPORTED && status == ITEM_STATUS_ACTIVE)
		{
			message = zbx_dsprintf(message, "Parameter [" ZBX_FS_UI64 "][%s] became supported",
					item.itemid, zbx_host_key_string(item.itemid));
			zabbix_log(LOG_LEVEL_WARNING, "%s", message);
			zabbix_syslog("%s", message);
			zbx_free(message);

			item.status = ITEM_STATUS_ACTIVE;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ",status=%d,error=''",
					item.status);
		}

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, " where itemid=" ZBX_FS_UI64 ";\n",
				item.itemid);

		DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_proxy_update_items                                        *
 *                                                                            *
 * Purpose: update items info after new value is received                     *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev, Aleksander Vladishev           *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_proxy_update_items(ZBX_DC_HISTORY *history, int history_num)
{
	int		sql_offset = 0, i, j;
	zbx_uint64_t	*ids = NULL;
	int		ids_alloc, ids_num = 0;
	int		lastlogsize, mtime;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_proxy_update_items()");

	ids_alloc = history_num;
	ids = zbx_malloc(ids, ids_alloc * sizeof(zbx_uint64_t));

	for (i = 0; i < history_num; i++)
		if (history[i].value_type == ITEM_VALUE_TYPE_LOG)
			uint64_array_add(&ids, &ids_alloc, &ids_num, history[i].itemid, 64);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	for (i = 0; i < ids_num; i++)
	{
		lastlogsize = mtime = -1;

		for (j = 0; j < history_num; j++)
		{
			if (history[j].itemid != ids[i])
				continue;

			if (history[j].value_type != ITEM_VALUE_TYPE_LOG)
				continue;

			if (lastlogsize < history[j].lastlogsize)
				lastlogsize = history[j].lastlogsize;
			if (mtime < history[j].mtime)
				mtime = history[j].mtime;
		}

		if (-1 == lastlogsize || -1 == mtime)
			continue;

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
				"update items set lastlogsize=%d, mtime=%d where itemid=" ZBX_FS_UI64 ";\n",
				lastlogsize,
				mtime,
				ids[i]);

		DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	zbx_free(ids);

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_add_history                                               *
 *                                                                            *
 * Purpose: inserting new history data after new value is received            *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	int		sql_offset = 0, i;
	char		*value_esc, *source_esc;
	int		history_text_num, history_log_num;
	zbx_uint64_t	id;
#ifdef HAVE_MULTIROW_INSERT
	int		tmp_offset;
	const char	*row_dl = ",";
#else
	const char	*row_dl = ";\n";
#endif
	const char	*nsfield;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_add_history()");

	if (0 != CONFIG_NS_SUPPORT)
		nsfield = ",ns";
	else
		nsfield = "";

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

/*
 * history
 */
#ifdef HAVE_MULTIROW_INSERT
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into history (itemid,clock,value%s) values ",
			nsfield);
#endif

	for (i = 0; i < history_num; i++)
	{
		if (0 == history[i].keep_history)
			continue;

		if (history[i].value_type != ITEM_VALUE_TYPE_FLOAT)
			continue;

		if (0 != history[i].value_null)
			continue;

#ifndef HAVE_MULTIROW_INSERT
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history (itemid,clock,value%s) values ",
				nsfield);
#endif
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"(" ZBX_FS_UI64 ",%d," ZBX_FS_DBL,
				history[i].itemid,
				history[i].clock,
				history[i].value.value_float);
		if (0 != CONFIG_NS_SUPPORT)
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 11,
					",%d", history[i].ns);
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")%s", row_dl);
	}

#ifdef HAVE_MULTIROW_INSERT
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

	if (CONFIG_NODE_NOHISTORY == 0 && CONFIG_MASTER_NODEID > 0)
	{
#ifdef HAVE_MULTIROW_INSERT
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_sync (nodeid,itemid,clock,value%s) values ",
				nsfield);
#endif

		for (i = 0; i < history_num; i++)
		{
			if (0 == history[i].keep_history)
				continue;

			if (history[i].value_type != ITEM_VALUE_TYPE_FLOAT)
				continue;

			if (0 != history[i].value_null)
				continue;

#ifndef HAVE_MULTIROW_INSERT
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_sync (nodeid,itemid,clock,value%s) values ",
					nsfield);
#endif
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL,
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					history[i].value.value_float);
			if (0 != CONFIG_NS_SUPPORT)
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 11,
						",%d", history[i].ns);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")%s", row_dl);
		}

#ifdef HAVE_MULTIROW_INSERT
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

/*
 * history_uint
 */
#ifdef HAVE_MULTIROW_INSERT
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into history_uint (itemid,clock,value%s) values ",
			nsfield);
#endif

	for (i = 0; i < history_num; i++)
	{
		if (0 == history[i].keep_history)
			continue;

		if (history[i].value_type != ITEM_VALUE_TYPE_UINT64)
			continue;

		if (0 != history[i].value_null)
			continue;

#ifndef HAVE_MULTIROW_INSERT
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_uint (itemid,clock,value%s) values ",
				nsfield);
#endif
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"(" ZBX_FS_UI64 ",%d," ZBX_FS_UI64,
				history[i].itemid,
				history[i].clock,
				history[i].value.value_uint64);
		if (0 != CONFIG_NS_SUPPORT)
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 11,
					",%d", history[i].ns);
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")%s", row_dl);
	}

#ifdef HAVE_MULTIROW_INSERT
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

	if (CONFIG_NODE_NOHISTORY == 0 && CONFIG_MASTER_NODEID > 0)
	{
#ifdef HAVE_MULTIROW_INSERT
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_uint_sync (nodeid,itemid,clock,value%s) values ",
				nsfield);
#endif

		for (i = 0; i < history_num; i++)
		{
			if (0 == history[i].keep_history)
				continue;

			if (history[i].value_type != ITEM_VALUE_TYPE_UINT64)
				continue;

			if (0 != history[i].value_null)
				continue;

#ifndef HAVE_MULTIROW_INSERT
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_uint_sync (nodeid,itemid,clock,value%s) values ",
					nsfield);
#endif
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_UI64,
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					history[i].value.value_uint64);
			if (0 != CONFIG_NS_SUPPORT)
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 11,
						",%d", history[i].ns);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")%s", row_dl);
		}

#ifdef HAVE_MULTIROW_INSERT
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

/*
 * history_str
 */
#ifdef HAVE_MULTIROW_INSERT
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into history_str (itemid,clock,value%s) values ",
			nsfield);
#endif

	for (i = 0; i < history_num; i++)
	{
		if (0 == history[i].keep_history)
			continue;

		if (history[i].value_type != ITEM_VALUE_TYPE_STR)
			continue;

		if (0 != history[i].value_null)
			continue;

		value_esc = DBdyn_escape_string_len(history[i].value_orig.value_str, HISTORY_STR_VALUE_LEN);
#ifndef HAVE_MULTIROW_INSERT
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_str (itemid,clock,value%s) values ",
				nsfield);
#endif
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"(" ZBX_FS_UI64 ",%d,'%s'",
				history[i].itemid,
				history[i].clock,
				value_esc);
		if (0 != CONFIG_NS_SUPPORT)
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 11,
					",%d", history[i].ns);
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")%s", row_dl);
		zbx_free(value_esc);
	}

#ifdef HAVE_MULTIROW_INSERT
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

	if (CONFIG_NODE_NOHISTORY == 0 && CONFIG_MASTER_NODEID > 0)
	{
#ifdef HAVE_MULTIROW_INSERT
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_str_sync (nodeid,itemid,clock,value%s) values ",
				nsfield);
#endif

		for (i = 0; i < history_num; i++)
		{
			if (0 == history[i].keep_history)
				continue;

			if (history[i].value_type != ITEM_VALUE_TYPE_STR)
				continue;

			if (0 != history[i].value_null)
				continue;

			value_esc = DBdyn_escape_string_len(history[i].value_orig.value_str, HISTORY_STR_VALUE_LEN);
#ifndef HAVE_MULTIROW_INSERT
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_str_sync (nodeid,itemid,clock,value%s) values ",
					nsfield);
#endif
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(%d," ZBX_FS_UI64 ",%d,'%s'",
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					value_esc);
			if (0 != CONFIG_NS_SUPPORT)
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 11,
						",%d", history[i].ns);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")%s", row_dl);
			zbx_free(value_esc);
		}

#ifdef HAVE_MULTIROW_INSERT
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

	history_text_num = 0;
	history_log_num = 0;

	for (i = 0; i < history_num; i++)
		if (history[i].value_type == ITEM_VALUE_TYPE_TEXT)
			history_text_num++;
		else if (history[i].value_type == ITEM_VALUE_TYPE_LOG)
			history_log_num++;

/*
 * history_text
 */
	if (history_text_num > 0)
	{
		id = DBget_maxid_num("history_text", history_text_num);

#ifdef HAVE_MULTIROW_INSERT
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_text (id,itemid,clock,value%s) values ",
				nsfield);
#endif

		for (i = 0; i < history_num; i++)
		{
			if (0 == history[i].keep_history)
				continue;

			if (history[i].value_type != ITEM_VALUE_TYPE_TEXT)
				continue;

			if (0 != history[i].value_null)
				continue;

			value_esc = DBdyn_escape_string(history[i].value_orig.value_str);
#ifndef HAVE_MULTIROW_INSERT
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_text (id,itemid,clock,value%s) values ",
					nsfield);
#endif
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s'",
					id,
					history[i].itemid,
					history[i].clock,
					value_esc);
			if (0 != CONFIG_NS_SUPPORT)
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 11,
						",%d", history[i].ns);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")%s", row_dl);
			zbx_free(value_esc);
			id++;
		}

#ifdef HAVE_MULTIROW_INSERT
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

/*
 * history_log
 */
	if (history_log_num > 0)
	{
		id = DBget_maxid_num("history_log", history_log_num);

#ifdef HAVE_MULTIROW_INSERT
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_log (id,itemid,clock,timestamp,"
				"source,severity,value,logeventid%s) values ", nsfield);
#endif

		for (i = 0; i < history_num; i++)
		{
			if (0 == history[i].keep_history)
				continue;

			if (history[i].value_type != ITEM_VALUE_TYPE_LOG)
				continue;

			if (0 != history[i].value_null)
				continue;

			source_esc = DBdyn_escape_string_len(history[i].source, HISTORY_LOG_SOURCE_LEN);
			value_esc = DBdyn_escape_string(history[i].value_orig.value_str);
#ifndef HAVE_MULTIROW_INSERT
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_log (id,itemid,clock,timestamp,"
					"source,severity,value,logeventid%s) values ", nsfield);
#endif
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s',%d",
					id,
					history[i].itemid,
					history[i].clock,
					history[i].timestamp,
					source_esc,
					history[i].severity,
					value_esc,
					history[i].logeventid);
			if (0 != CONFIG_NS_SUPPORT)
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 11,
						",%d", history[i].ns);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")%s", row_dl);
			zbx_free(value_esc);
			zbx_free(source_esc);
			id++;
		}

#ifdef HAVE_MULTIROW_INSERT
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

#ifdef HAVE_MULTIROW_INSERT
	sql[sql_offset] = '\0';
#endif

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_proxy_add_history                                         *
 *                                                                            *
 * Purpose: inserting new history data after new value is received            *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_proxy_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	int		sql_offset = 0, i;
	char		*value_esc, *source_esc;
#ifdef HAVE_MULTIROW_INSERT
	int		tmp_offset;
	const char	*row_dl = ",";
#else
	const char	*row_dl = ";\n";
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_proxy_add_history()");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

#ifdef HAVE_MULTIROW_INSERT
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 58,
			"insert into proxy_history (itemid,clock,ns,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
#ifndef HAVE_MULTIROW_INSERT
		switch (history[i].value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 58,
						"insert into proxy_history (itemid,clock,ns,value) values ");
				break;
		}
#endif
		switch (history[i].value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"(" ZBX_FS_UI64 ",%d,%d,'" ZBX_FS_DBL "')%s",
						history[i].itemid,
						history[i].clock,
						history[i].ns,
						history[i].value_orig.value_float,
						row_dl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"(" ZBX_FS_UI64 ",%d,%d,'" ZBX_FS_UI64 "')%s",
						history[i].itemid,
						history[i].clock,
						history[i].ns,
						history[i].value_orig.value_uint64,
						row_dl);
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				value_esc = DBdyn_escape_string(history[i].value_orig.value_str);

				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
						"(" ZBX_FS_UI64 ",%d,%d,'%s')%s",
						history[i].itemid,
						history[i].clock,
						history[i].ns,
						value_esc,
						row_dl);

				zbx_free(value_esc);
				break;
		}
	}

#ifdef HAVE_MULTIROW_INSERT
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MULTIROW_INSERT
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 95,
			"insert into proxy_history (itemid,clock,ns,timestamp,source,severity,value,logeventid) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type != ITEM_VALUE_TYPE_LOG)
			continue;

#ifndef HAVE_MULTIROW_INSERT
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 95,
				"insert into proxy_history (itemid,clock,ns,timestamp,source,severity,value,logeventid) values ");
#endif
		source_esc = DBdyn_escape_string_len(history[i].source, HISTORY_LOG_SOURCE_LEN);
		value_esc = DBdyn_escape_string(history[i].value_orig.value_str);

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
				"(" ZBX_FS_UI64 ",%d,%d,%d,'%s',%d,'%s',%d)%s",
				history[i].itemid,
				history[i].clock,
				history[i].ns,
				history[i].timestamp,
				source_esc,
				history[i].severity,
				value_esc,
				history[i].logeventid,
				row_dl);

		zbx_free(value_esc);
		zbx_free(source_esc);
	}

#ifdef HAVE_MULTIROW_INSERT
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MULTIROW_INSERT
	sql[sql_offset] = '\0';
#endif

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync                                                           *
 *                                                                            *
 * Purpose: writes updates and new data from pool to database                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: number of synced values                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	DCsync_history(int sync_type)
{
	static ZBX_DC_HISTORY	*history = NULL;
	int			i, j, history_num, n, f;
	int			syncs;
	int			total_num = 0;
	int			skipped_clock, max_delay;
	time_t			now = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCsync_history(history_first:%d history_num:%d)",
			cache->history_first,
			cache->history_num);

	if (ZBX_SYNC_FULL == sync_type)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Syncing history data...");
		now = time(NULL);
		cache->itemids_num = 0;
	}

	if (0 == cache->history_num)
		goto finish;

	if (NULL == history)
		history = zbx_malloc(history, ZBX_SYNC_MAX * sizeof(ZBX_DC_HISTORY));

	syncs = cache->history_num / ZBX_SYNC_MAX;
	max_delay = (int)time(NULL) - CONFIG_DBSYNCER_FREQUENCY;

	do
	{
		LOCK_CACHE;

		history_num = 0;
		n = cache->history_num;
		f = cache->history_first;
		skipped_clock = 0;

		while (n > 0 && history_num < ZBX_SYNC_MAX)
		{
			if (0 != (zbx_process & ZBX_PROCESS_PROXY) ||
					FAIL == uint64_array_exists(cache->itemids, cache->itemids_num, cache->history[f].itemid))
			{
				uint64_array_add(&cache->itemids, &cache->itemids_alloc,
						&cache->itemids_num, cache->history[f].itemid, 0);

				memcpy(&history[history_num], &cache->history[f], sizeof(ZBX_DC_HISTORY));
				if (history[history_num].value_type == ITEM_VALUE_TYPE_STR
						|| history[history_num].value_type == ITEM_VALUE_TYPE_TEXT
						|| history[history_num].value_type == ITEM_VALUE_TYPE_LOG)
				{
					history[history_num].value_orig.value_str = strdup(cache->history[f].value_orig.value_str);

					if (history[history_num].value_type == ITEM_VALUE_TYPE_LOG)
					{
						if (NULL != cache->history[f].source)
							history[history_num].source = strdup(cache->history[f].source);
						else
							history[history_num].source = NULL;
					}
				}

				for (j = f; j != cache->history_first; j = (j == 0 ? ZBX_HISTORY_SIZE : j) - 1)
				{
					i = (j == 0 ? ZBX_HISTORY_SIZE : j) - 1;
					memcpy(&cache->history[j], &cache->history[i], sizeof(ZBX_DC_HISTORY));
				}

				cache->history_num--;
				cache->history_first++;
				cache->history_first = cache->history_first % ZBX_HISTORY_SIZE;

				history_num++;
			}
			else if (skipped_clock == 0)
				skipped_clock = cache->history[f].clock;

			n--;
			f++;
			f = f % ZBX_HISTORY_SIZE;
		}

		UNLOCK_CACHE;

		if (0 == history_num)
			break;

		DCinit_nextchecks();

		DBbegin();

		if (0 != (zbx_process & ZBX_PROCESS_SERVER))
		{
			DCmass_update_items(history, history_num);
			DCmass_add_history(history, history_num);
			DCmass_update_triggers(history, history_num);
			DCmass_update_trends(history, history_num);
		}
		else
		{
			DCmass_proxy_add_history(history, history_num);
			DCmass_proxy_update_items(history, history_num);
		}

		DBcommit();

		DCflush_nextchecks();

		LOCK_CACHE;

		for (i = 0; i < history_num; i ++)
			uint64_array_remove(cache->itemids, &cache->itemids_num, &history[i].itemid, 1);

		UNLOCK_CACHE;

		for (i = 0; i < history_num; i ++)
		{
			if (history[i].value_type == ITEM_VALUE_TYPE_STR
					|| history[i].value_type == ITEM_VALUE_TYPE_TEXT
					|| history[i].value_type == ITEM_VALUE_TYPE_LOG)
			{
				zbx_free(history[i].value_orig.value_str);

				if (history[i].value_type == ITEM_VALUE_TYPE_LOG && NULL != history[i].source)
					zbx_free(history[i].source);
			}
		}
		total_num += history_num;

		if (ZBX_SYNC_FULL == sync_type && time(NULL) - now >= 10)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Syncing history data... " ZBX_FS_DBL "%%",
					(double)total_num / (cache->history_num + total_num) * 100);
			now = time(NULL);
		}
	} while (--syncs > 0 || sync_type == ZBX_SYNC_FULL || (skipped_clock != 0 && skipped_clock < max_delay));
finish:
	if (ZBX_SYNC_FULL == sync_type)
		zabbix_log(LOG_LEVEL_WARNING, "Syncing history data... done.");

	return total_num;
}

/******************************************************************************
 *                                                                            *
 * Function: DCvacuum_text                                                    *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void DCvacuum_text()
{
	char	*first_text;
	int	i, index;
	size_t	offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCvacuum_text()");

	/* vacuuming text buffer */
	first_text = NULL;
	for (i = 0; i < cache->history_num; i++)
	{
		index = (cache->history_first + i) % ZBX_HISTORY_SIZE;
		if (cache->history[index].value_type == ITEM_VALUE_TYPE_STR
				|| cache->history[index].value_type == ITEM_VALUE_TYPE_TEXT
				|| cache->history[index].value_type == ITEM_VALUE_TYPE_LOG)
		{
			first_text = cache->history[index].value_orig.value_str;
			break;
		}
	}

	if (NULL != first_text)
	{
		if (0 == (offset = first_text - cache->text))
			goto quit;

		memmove(cache->text, first_text, CONFIG_TEXT_CACHE_SIZE - offset);

		for (i = 0; i < cache->history_num; i++)
		{
			index = (cache->history_first + i) % ZBX_HISTORY_SIZE;
			if (cache->history[index].value_type == ITEM_VALUE_TYPE_STR
					|| cache->history[index].value_type == ITEM_VALUE_TYPE_TEXT
					|| cache->history[index].value_type == ITEM_VALUE_TYPE_LOG)
			{
				cache->history[index].value_orig.value_str -= offset;

				if (cache->history[index].value_type == ITEM_VALUE_TYPE_LOG && NULL != cache->history[index].source)
					cache->history[index].source -= offset;
			}
		}
		cache->last_text -= offset;
	}
	else
		cache->last_text = cache->text;

quit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of DCvacuum_text()");
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_history_ptr                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_HISTORY	*DCget_history_ptr(zbx_uint64_t itemid, size_t text_len)
{
	ZBX_DC_HISTORY	*history;
	int		index;
	size_t		free_len;

retry:
	if (cache->history_num >= ZBX_HISTORY_SIZE)
	{
		UNLOCK_CACHE;

		zabbix_log(LOG_LEVEL_DEBUG, "History buffer is full. Sleeping for 1 second.");
		sleep(1);

		LOCK_CACHE;

		goto retry;
	}

	if (0 != text_len)
	{
		if (text_len > CONFIG_TEXT_CACHE_SIZE)
		{
			zabbix_log(LOG_LEVEL_ERR, "Insufficient shared memory for text cache");
			exit(-1);
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

	index = (cache->history_first + cache->history_num) % ZBX_HISTORY_SIZE;
	history = &cache->history[index];

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

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history                                                    *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_history(zbx_uint64_t itemid, double value_orig, zbx_timespec_t *ts)
{
	ZBX_DC_HISTORY	*history;

	LOCK_CACHE;

	DCcheck_ns(ts);

	history = DCget_history_ptr(itemid, 0);

	history->itemid			= itemid;
	history->clock			= ts->sec;
	history->ns			= ts->ns;
	history->value_type		= ITEM_VALUE_TYPE_FLOAT;
	history->value_orig.value_float	= value_orig;
	history->value.value_float	= 0;
	history->value_null		= 0;
	history->keep_history		= 0;
	history->keep_trends		= 0;

	cache->stats.history_counter++;
	cache->stats.history_float_counter++;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history_uint                                               *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value_orig, zbx_timespec_t *ts)
{
	ZBX_DC_HISTORY	*history;

	LOCK_CACHE;

	DCcheck_ns(ts);

	history = DCget_history_ptr(itemid, 0);

	history->itemid				= itemid;
	history->clock				= ts->sec;
	history->ns				= ts->ns;
	history->value_type			= ITEM_VALUE_TYPE_UINT64;
	history->value_orig.value_uint64	= value_orig;
	history->value.value_uint64		= 0;
	history->value_null			= 0;
	history->keep_history			= 0;
	history->keep_trends			= 0;

	cache->stats.history_counter++;
	cache->stats.history_uint_counter++;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history_str                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_history_str(zbx_uint64_t itemid, char *value_orig, zbx_timespec_t *ts)
{
	ZBX_DC_HISTORY	*history;
	size_t		len;

	LOCK_CACHE;

	DCcheck_ns(ts);

	if (HISTORY_STR_VALUE_LEN_MAX < (len = strlen(value_orig) + 1))
		len = HISTORY_STR_VALUE_LEN_MAX;
	history = DCget_history_ptr(itemid, len);

	history->itemid			= itemid;
	history->clock			= ts->sec;
	history->ns			= ts->ns;
	history->value_type		= ITEM_VALUE_TYPE_STR;
	history->value_orig.value_str	= cache->last_text;
	history->value.value_str	= NULL;
	zbx_strlcpy(cache->last_text, value_orig, len);
	history->value_null		= 0;
	cache->last_text		+= len;
	history->keep_history		= 0;
	history->keep_trends		= 0;

	cache->stats.history_counter++;
	cache->stats.history_str_counter++;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history_text                                               *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_history_text(zbx_uint64_t itemid, char *value_orig, zbx_timespec_t *ts)
{
	ZBX_DC_HISTORY	*history;
	size_t		len;

	LOCK_CACHE;

	DCcheck_ns(ts);

	if (HISTORY_TEXT_VALUE_LEN_MAX < (len = strlen(value_orig) + 1))
		len = HISTORY_TEXT_VALUE_LEN_MAX;
	history = DCget_history_ptr(itemid, len);

	history->itemid			= itemid;
	history->clock			= ts->sec;
	history->ns			= ts->ns;
	history->value_type		= ITEM_VALUE_TYPE_TEXT;
	history->value_orig.value_str	= cache->last_text;
	history->value.value_str	= NULL;
	zbx_strlcpy(cache->last_text, value_orig, len);
	history->value_null		= 0;
	cache->last_text		+= len;
	history->keep_history		= 0;
	history->keep_trends		= 0;

	cache->stats.history_counter++;
	cache->stats.history_text_counter++;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history_log                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_history_log(zbx_uint64_t itemid, char *value_orig, zbx_timespec_t *ts,
		int timestamp, char *source, int severity, int logeventid, int lastlogsize, int mtime)
{
	ZBX_DC_HISTORY	*history;
	size_t		len1, len2;

	LOCK_CACHE;

	DCcheck_ns(ts);

	if (HISTORY_LOG_VALUE_LEN_MAX < (len1 = strlen(value_orig) + 1))
		len1 = HISTORY_LOG_VALUE_LEN_MAX;
	if (HISTORY_LOG_SOURCE_LEN_MAX < (len2 = (NULL != source && *source != '\0') ? strlen(source) + 1 : 0))
		len2 = HISTORY_LOG_SOURCE_LEN_MAX;
	history = DCget_history_ptr(itemid, len1 + len2);

	history->itemid			= itemid;
	history->clock			= ts->sec;
	history->ns			= ts->ns;
	history->value_type		= ITEM_VALUE_TYPE_LOG;
	history->value_orig.value_str	= cache->last_text;
	history->value.value_str	= NULL;
	zbx_strlcpy(cache->last_text, value_orig, len1);
	history->value_null		= 0;
	cache->last_text		+= len1;
	history->timestamp		= timestamp;

	if (0 != len2)
	{
		history->source		= cache->last_text;
		zbx_strlcpy(cache->last_text, source, len2);
		cache->last_text	+= len2;
	}
	else
		history->source		= NULL;

	history->severity		= severity;
	history->logeventid		= logeventid;
	history->lastlogsize		= lastlogsize;
	history->mtime			= mtime;
	history->keep_history		= 0;
	history->keep_trends		= 0;

	cache->stats.history_counter++;
	cache->stats.history_log_counter++;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history                                                   *
 *                                                                            *
 * Purpose: add new value to the cache                                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	dc_add_history(zbx_uint64_t itemid, unsigned char value_type, AGENT_RESULT *value, zbx_timespec_t *ts,
		int timestamp, char *source, int severity, int logeventid, int lastlogsize, int mtime)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (GET_DBL_RESULT(value))
				DCadd_history(itemid, value->dbl, ts);
			break;
		case ITEM_VALUE_TYPE_STR:
			if (GET_STR_RESULT(value))
				DCadd_history_str(itemid, value->str, ts);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (GET_STR_RESULT(value))
				DCadd_history_log(itemid, value->str, ts, timestamp, source, severity,
						logeventid, lastlogsize, mtime);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (GET_UI64_RESULT(value))
				DCadd_history_uint(itemid, value->ui64, ts);
			break;
		case ITEM_VALUE_TYPE_TEXT:
			if (GET_TEXT_RESULT(value))
				DCadd_history_text(itemid, value->text, ts);
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "Unknown value type [%d] for itemid [" ZBX_FS_UI64 "]",
				value_type,
				itemid);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: init_database_cache                                              *
 *                                                                            *
 * Purpose: Allocate shared memory for database cache                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

ZBX_MEM_FUNC1_IMPL_MALLOC(__history, history_mem);
ZBX_MEM_FUNC1_IMPL_MALLOC(__history_text, history_text_mem);
ZBX_MEM_FUNC_IMPL(__trend, trend_mem);

void	init_database_cache(unsigned char p)
{
	const char	*__function_name = "init_database_cache";
	key_t		history_shm_key, history_text_shm_key, trend_shm_key;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_process = p;

	if (-1 == (history_shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_HISTORY_ID)) ||
			-1 == (history_text_shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_HISTORY_TEXT_ID)) ||
			-1 == (trend_shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_TREND_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot create IPC keys for history and trend caches");
		exit(FAIL);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cache_lock, ZBX_MUTEX_CACHE))
	{
		zbx_error("Unable to create mutex for history cache");
		exit(FAIL);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&trends_lock, ZBX_MUTEX_TRENDS))
	{
		zbx_error("Unable to create mutex for trend cache");
		exit(FAIL);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cache_ids_lock, ZBX_MUTEX_CACHE_IDS))
	{
		zbx_error("Unable to create mutex for id cache");
		exit(FAIL);
	}

	ZBX_HISTORY_SIZE = CONFIG_HISTORY_CACHE_SIZE / sizeof(ZBX_DC_HISTORY);
	if (ZBX_SYNC_MAX > ZBX_HISTORY_SIZE)
		ZBX_SYNC_MAX = ZBX_HISTORY_SIZE;
	ZBX_ITEMIDS_SIZE = CONFIG_DBSYNCER_FORKS * ZBX_SYNC_MAX;

	/* history cache */

	sz = sizeof(ZBX_DC_CACHE);
	sz += ZBX_HISTORY_SIZE * sizeof(ZBX_DC_HISTORY);
	sz += ZBX_ITEMIDS_SIZE * sizeof(zbx_uint64_t);
	sz += sizeof(ZBX_DC_IDS);
	sz = zbx_mem_required_size(sz, 4, "history cache", "HistoryCacheSize");

	zbx_mem_create(&history_mem, history_shm_key, ZBX_NO_MUTEX, sz, "history cache", "HistoryCacheSize");

	cache = (ZBX_DC_CACHE *)__history_mem_malloc_func(NULL, sizeof(ZBX_DC_CACHE));

	cache->history = (ZBX_DC_HISTORY *)__history_mem_malloc_func(NULL, ZBX_HISTORY_SIZE * sizeof(ZBX_DC_HISTORY));
	cache->history_first = 0;
	cache->history_num = 0;
	cache->itemids = (zbx_uint64_t *)__history_mem_malloc_func(NULL, ZBX_ITEMIDS_SIZE * sizeof(zbx_uint64_t));
	cache->itemids_alloc = ZBX_ITEMIDS_SIZE;
	cache->itemids_num = 0;
	memset(&cache->stats, 0, sizeof(ZBX_DC_STATS));

	ids = (ZBX_DC_IDS *)__history_mem_malloc_func(NULL, sizeof(ZBX_DC_IDS));
	memset(ids, 0, sizeof(ZBX_DC_IDS));

	/* history text cache */

	sz = zbx_mem_required_size(CONFIG_TEXT_CACHE_SIZE, 1, "history text cache", "HistoryTextCacheSize");

	zbx_mem_create(&history_text_mem, history_text_shm_key, ZBX_NO_MUTEX, sz, "history text cache", "HistoryTextCacheSize");

	cache->text = (char *)__history_text_mem_malloc_func(NULL, CONFIG_TEXT_CACHE_SIZE);
	cache->last_text = cache->text;

	/* trend cache */

	sz = zbx_mem_required_size(CONFIG_TRENDS_CACHE_SIZE, 1, "trend cache", "TrendCacheSize");

	zbx_mem_create(&trend_mem, trend_shm_key, ZBX_NO_MUTEX, sz, "trend cache", "TrendCacheSize");

	cache->trends_num = 0;
	cache->last_ts.sec = 0;
	cache->last_ts.ns = 0;

#define	INIT_HASHSET_SIZE	1000 /* should be calculated dynamically based on trends size? */

	zbx_hashset_create_ext(&cache->trends, INIT_HASHSET_SIZE,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			__trend_mem_malloc_func, __trend_mem_realloc_func, __trend_mem_free_func);

#undef	INIT_HASHSET_SIZE

	if (NULL == sql)
		sql = zbx_malloc(sql, sql_allocated);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_all                                                       *
 *                                                                            *
 * Purpose: writes updates and new data from pool and cache data to database  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_all()
{
	zabbix_log(LOG_LEVEL_DEBUG, "In DCsync_all()");

	DCsync_history(ZBX_SYNC_FULL);
	DCsync_trends();

	zabbix_log(LOG_LEVEL_DEBUG, "End of DCsync_all()");
}

/******************************************************************************
 *                                                                            *
 * Function: free_database_cache                                              *
 *                                                                            *
 * Purpose: Free memory allocated for database cache                          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	free_database_cache()
{
	const char	*__function_name = "free_database_cache";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DCsync_all();

	LOCK_CACHE;
	LOCK_TRENDS;
	LOCK_CACHE_IDS;

	cache = NULL;
	zbx_mem_destroy(history_mem);
	zbx_mem_destroy(history_text_mem);
	zbx_mem_destroy(trend_mem);

	UNLOCK_CACHE_IDS;
	UNLOCK_TRENDS;
	UNLOCK_CACHE;

	zbx_mutex_destroy(&cache_lock);
	zbx_mutex_destroy(&trends_lock);
	zbx_mutex_destroy(&cache_ids_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_nextid                                                     *
 *                                                                            *
 * Purpose: Return next id for requested table                                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_nextid(const char *table_name, int num)
{
	const char	*__function_name = "DCget_nextid";
	int		i, nodeid;
	DB_RESULT	result;
	DB_ROW		row;
	const ZBX_TABLE	*table;
	ZBX_DC_ID	*id;
	zbx_uint64_t	min, max, nextid;

	LOCK_CACHE_IDS;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' num:%d",
			__function_name, table_name, num);

	for (i = 0; i < ZBX_IDS_SIZE; i++)
	{
		id = &ids->id[i];
		if ('\0' == *id->table_name)
			break;

		if (0 == strcmp(id->table_name, table_name))
		{
			nextid = id->lastid + 1;
			id->lastid += num;

			zabbix_log(LOG_LEVEL_DEBUG, "End of %s() table:'%s' [" ZBX_FS_UI64 ":" ZBX_FS_UI64 "]",
					__function_name, table_name, nextid, id->lastid);

			UNLOCK_CACHE_IDS;

			return nextid;
		}
	}

	if (i == ZBX_IDS_SIZE)
	{
		zabbix_log(LOG_LEVEL_ERR, "Insufficient shared memory for ids");
		exit(-1);
	}

	zbx_strlcpy(id->table_name, table_name, sizeof(id->table_name));

	table = DBget_table(table_name);
	nodeid = CONFIG_NODEID >= 0 ? CONFIG_NODEID : 0;

	min = (zbx_uint64_t)__UINT64_C(100000000000000) * (zbx_uint64_t)nodeid;
	max = (zbx_uint64_t)__UINT64_C(100000000000000) * (zbx_uint64_t)nodeid;

	if (table->flags & ZBX_SYNC)
	{
		min += (zbx_uint64_t)__UINT64_C(100000000000) * (zbx_uint64_t)nodeid;
		max += (zbx_uint64_t)__UINT64_C(100000000000) * (zbx_uint64_t)nodeid + (zbx_uint64_t)__UINT64_C(99999999999);
	}
	else
		max += (zbx_uint64_t)__UINT64_C(99999999999999);

	result = DBselect("select max(%s) from %s where %s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
			table->recid,
			table_name,
			table->recid,
			min, max);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
		id->lastid = min;
	else
		ZBX_STR2UINT64(id->lastid, row[0]);

	nextid = id->lastid + 1;
	id->lastid += num;

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() table:'%s' [" ZBX_FS_UI64 ":" ZBX_FS_UI64 "]",
			__function_name, table_name, nextid, id->lastid);

	UNLOCK_CACHE_IDS;

	return nextid;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_nextid_shared                                              *
 *                                                                            *
 * Purpose: Return next id for requested table and store it in ids table      *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DCget_nextid_shared(const char *table_name)
{
#define ZBX_RESERVE	256
	const char	*__function_name = "DCget_nextid_shared";
	int		i;
	ZBX_DC_ID	*id;
	zbx_uint64_t	nextid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'",
			__function_name, table_name);

	LOCK_CACHE_IDS;

	for (i = 0; i < ZBX_IDS_SIZE; i++)
	{
		id = &ids->id[i];

		if ('\0' == *id->table_name)
		{
			zbx_strlcpy(id->table_name, table_name, sizeof(id->table_name));
			id->lastid = 0;
			id->reserved = 0;

			break;
		}

		if (0 == strcmp(id->table_name, table_name))
			break;
	}

	if (i == ZBX_IDS_SIZE)
	{
		zabbix_log(LOG_LEVEL_ERR, "Insufficient shared memory for ids");
		exit(-1);
	}

	if (id->reserved > 0)
	{
		id->lastid++;
		id->reserved--;

		nextid = id->lastid;

		UNLOCK_CACHE_IDS;

		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() table:'%s' [" ZBX_FS_UI64 "]",
				__function_name, table_name, nextid);

		return nextid;
	}

	UNLOCK_CACHE_IDS;

retry:
	nextid = DBget_nextid(table_name, ZBX_RESERVE) - 1;

	LOCK_CACHE_IDS;

	if (nextid < id->lastid)
	{
		UNLOCK_CACHE_IDS;
		goto retry;
	}

	if (0 == id->reserved)
	{
		id->lastid = nextid;
		id->reserved = ZBX_RESERVE;
	}
	else if (id->lastid + id->reserved == nextid)
	{
		id->reserved += ZBX_RESERVE;
	}
	else if (id->reserved < ZBX_RESERVE && nextid > id->lastid)
	{
		id->lastid = nextid;
		id->reserved = ZBX_RESERVE;
	}

	id->lastid++;
	id->reserved--;

	nextid = id->lastid;

	UNLOCK_CACHE_IDS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() table:'%s' [" ZBX_FS_UI64 "]",
			__function_name, table_name, nextid);

	return nextid;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_item_lastclock                                             *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: last clock or FAIL if item not found in dbcache              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	DCget_item_lastclock(zbx_uint64_t itemid)
{
	int	i, index, clock = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCget_item_lastclock(): itemid [" ZBX_FS_UI64 "]", itemid);

	LOCK_CACHE;

	index = (cache->history_first + cache->history_num - 1) % ZBX_HISTORY_SIZE;

	for (i = cache->history_num - 1; i >= 0; i--)
	{
		if (cache->history[index].itemid == itemid)
		{
			clock = cache->history[index].clock;
			break;
		}

		if (--index < 0)
			index = ZBX_HISTORY_SIZE - 1;
	}

	UNLOCK_CACHE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of DCget_item_lastclock(): %d", clock);

	return clock;
}
