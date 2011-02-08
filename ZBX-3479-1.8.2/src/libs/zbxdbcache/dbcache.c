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

#define	LOCK_CACHE	zbx_mutex_lock(&cache_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(&cache_lock)
#define	LOCK_CACHE_IDS		zbx_mutex_lock(&cache_ids_lock)
#define	UNLOCK_CACHE_IDS	zbx_mutex_unlock(&cache_ids_lock)

#define ZBX_GET_SHM_DBCACHE_KEY(smk_key)				\
	if( -1 == (shm_key = zbx_ftok(CONFIG_FILE, (int)'c') ))		\
	{								\
		zbx_error("Cannot create IPC key for DB cache");	\
		exit(1);						\
	}

ZBX_DC_CACHE		*cache = NULL;
ZBX_DC_IDS		*ids = NULL;

static ZBX_MUTEX	cache_lock;
static ZBX_MUTEX	cache_ids_lock;

static char		*sql = NULL;
static int		sql_allocated = 65536;

zbx_process_t		zbx_process;

extern int		CONFIG_DBSYNCER_FREQUENCY;

static int		ZBX_HISTORY_SIZE = 0;
int			ZBX_SYNC_MAX = 1000;	/* Must be less than ZBX_HISTORY_SIZE */
static int		ZBX_TREND_SIZE = 0;

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

		UNLOCK_CACHE;

		if (NULL != first_text)
			free_len -= cache->last_text - first_text;
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
		value_uint = CONFIG_TRENDS_CACHE_SIZE;
		return &value_uint;
	case ZBX_STATS_TREND_USED:
		value_uint = cache->trends_num * sizeof(ZBX_DC_TREND);
		return &value_uint;
	case ZBX_STATS_TREND_FREE:
		value_uint = CONFIG_TRENDS_CACHE_SIZE - cache->trends_num * sizeof(ZBX_DC_TREND);
		return &value_uint;
	case ZBX_STATS_TREND_PFREE:
		value_double = 100 * ((double)(ZBX_TREND_SIZE - cache->trends_num) / ZBX_TREND_SIZE);
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
 * Function: DCget_trend_nearestindex                                         *
 *                                                                            *
 * Purpose: find nearest index by itemid in array of ZBX_DC_TREND             *
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
static int	DCget_trend_nearestindex(zbx_uint64_t itemid)
{
	int	first_index, last_index, index;

	if (cache->trends_num == 0)
		return 0;

	first_index = 0;
	last_index = cache->trends_num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (cache->trends[index].itemid == itemid)
			return index;
		else if (last_index == first_index)
		{
			if (cache->trends[index].itemid < itemid)
				index++;
			return index;
		}
		else if (cache->trends[index].itemid < itemid)
			first_index = index + 1;
		else
			last_index = index;
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
 * Return value: pointer to a new structure or NULL if array is full          *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_TREND	*DCget_trend(zbx_uint64_t itemid)
{
	int	index;

	index = DCget_trend_nearestindex(itemid);
	if (index < cache->trends_num && cache->trends[index].itemid == itemid)
		return &cache->trends[index];

	if (cache->trends_num == ZBX_TREND_SIZE)
		return NULL;

	memmove(&cache->trends[index + 1], &cache->trends[index], sizeof(ZBX_DC_TREND) * (cache->trends_num - index));
	memset(&cache->trends[index], 0, sizeof(ZBX_DC_TREND));
	cache->trends[index].itemid = itemid;
	cache->trends_num++;

	return &cache->trends[index];
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
static void	DCflush_trends(ZBX_DC_TREND *trends, int *trends_num)
{
	const char	*__function_name = "DCflush_trends";
	DB_RESULT	result;
	DB_ROW		row;
	int		num, i, clock, sql_offset = 0;
	history_value_t	value_min, value_avg, value_max;
	unsigned char	value_type;
	zbx_uint64_t	*ids = NULL, itemid;
	int		ids_alloc, ids_num = 0;
	ZBX_DC_TREND	*trend;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d",
			__function_name, *trends_num);

	clock = trends[0].clock;
	value_type = trends[0].value_type;

	ids_alloc = *trends_num;
	ids = zbx_malloc(ids, ids_alloc * sizeof(zbx_uint64_t));

	for (i = 0; i < *trends_num; i++)
		if (clock == trends[i].clock && value_type == trends[i].value_type)
			uint64_array_add(&ids, &ids_alloc, &ids_num, trends[i].itemid, 64);

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
			"select itemid,num,value_min,value_avg,value_max"
			" from %s"
			" where clock=%d and",
			value_type == ITEM_VALUE_TYPE_FLOAT ? "trends" : "trends_uint",
			clock);

	DBadd_condition_alloc(&sql, &sql_allocated, &sql_offset, "itemid", ids, ids_num);

	zbx_free(ids);

	result = DBselect("%s", sql);

	sql_offset = 0;

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	if (value_type == ITEM_VALUE_TYPE_FLOAT)
	{
		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);

			trend = NULL;

			for (i = 0; i < *trends_num; i++)
				if (itemid == trends[i].itemid && clock == trends[i].clock &&
						value_type == trends[i].value_type)
				{
					trend = &trends[i];
					break;
				}

			if (NULL == trend)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "WARNING: Trend with itemid:" ZBX_FS_UI64 " not found", itemid);
				continue;
			}

			num = atoi(row[1]);

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

			trend->itemid = 0;

			DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
		}

		for (i = 0; i < *trends_num; i++)
			if (0 != trends[i].itemid && clock == trends[i].clock && value_type == trends[i].value_type)
			{
				trend = &trends[i];
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"insert into trends (itemid,clock,num,value_min,value_avg,value_max)"
						" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL ");\n",
						trend->itemid,
						trend->clock,
						trend->num,
						trend->value_min.value_float,
						trend->value_avg.value_float,
						trend->value_max.value_float);

				trend->itemid = 0;

				DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
			}
	}
	else
	{
		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);

			trend = NULL;

			for (i = 0; i < *trends_num; i++)
				if (itemid == trends[i].itemid && clock == trends[i].clock &&
						value_type == trends[i].value_type)
				{
					trend = &trends[i];
					break;
				}

			if (NULL == trend)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "WARNING: Trend with itemid:" ZBX_FS_UI64 " not found", itemid);
				continue;
			}

			num = atoi(row[1]);

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

			trend->itemid = 0;

			DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
		}

		for (i = 0; i < *trends_num; i++)
			if (0 != trends[i].itemid && clock == trends[i].clock && value_type == trends[i].value_type)
			{
				trend = &trends[i];
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"insert into trends_uint (itemid,clock,num,value_min,value_avg,value_max)"
						" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ");\n",
						trend->itemid,
						trend->clock,
						trend->num,
						trend->value_min.value_uint64,
						trend->value_avg.value_uint64,
						trend->value_max.value_uint64);

				trend->itemid = 0;

				DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
			}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	/* clean trends */
	for (i = 0, num = 0; i < *trends_num; i++)
		if (0 != trends[i].itemid)
			memcpy(&trends[num++], &trends[i], sizeof(ZBX_DC_TREND));
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
	ZBX_DC_TREND	*trend = NULL, trend_static;
	size_t		sz;
	int		hour;

	sz = sizeof(ZBX_DC_TREND);
	hour = history->clock - history->clock % 3600;

	if (NULL == (trend = DCget_trend(history->itemid)))
	{
		trend = &trend_static;
		memset(trend, 0, sz);
		trend->itemid = history->itemid;
	}

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

	if (trend == &trend_static)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Insufficient space for trends. Flushing to disk.");
		DCflush_trend(trend, trends, trends_alloc, trends_num);
	}
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
	ZBX_DC_TREND	*trends = NULL;
	int		trends_alloc = 0, trends_num = 0, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_update_trends()");

	for (i = 0; i < history_num; i++)
	{
		if (0 == history[i].keep_trends)
			continue;

		if (history[i].value_type != ITEM_VALUE_TYPE_FLOAT && history[i].value_type != ITEM_VALUE_TYPE_UINT64)
			continue;

		if (0 != history[i].value_null)
			continue;

		DCadd_trend(&history[i], &trends, &trends_alloc, &trends_num);
	}

	while (trends_num > 0)
		DCflush_trends(trends, &trends_num);

	zbx_free(trends);
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
	const char	*__function_name = "DCsync_trends";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trends_num:%d",
			__function_name, cache->trends_num);

	zabbix_log(LOG_LEVEL_WARNING, "Syncing trends data...");

	while (cache->trends_num > 0)
	{
		DBbegin();
		DCflush_trends(cache->trends, &cache->trends_num);
		DBcommit();
	}

	zabbix_log(LOG_LEVEL_WARNING, "Syncing trends data...done.");

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
	char		*exp;
	char		error[MAX_STRING_LEN];
	int		exp_value;
	DB_TRIGGER	trigger;
	DB_RESULT	result;
	DB_ROW		row;
	int		sql_offset = 0, i;
	ZBX_DC_HISTORY	*h;
	zbx_uint64_t	itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_update_triggers()");

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1024,
			"select distinct t.triggerid,t.expression,t.description,t.url,"
				"t.comments,t.status,t.value,t.priority,t.type,t.error,f.itemid"
			" from triggers t,functions f,items i"
			" where i.status not in (%d)"
				" and i.itemid=f.itemid"
				" and t.status=%d"
				" and f.triggerid=t.triggerid"
				" and f.itemid in (",
			ITEM_STATUS_NOTSUPPORTED,
			TRIGGER_STATUS_ENABLED);

	for (i = 0; i < history_num; i++)
		if (0 != history[i].functions)
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ZBX_FS_UI64 ",",
					history[i].itemid);

	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ")");
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items with triggers");
		return;
	}

	result = DBselect("%s", sql);

	sql_offset = 0;

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(trigger.triggerid, row[0]);
		strscpy(trigger.expression, row[1]);
		strscpy(trigger.description, row[2]);
		trigger.url		= row[3];
		trigger.comments	= row[4];
		trigger.status		= atoi(row[5]);
		trigger.value		= atoi(row[6]);
		trigger.priority	= atoi(row[7]);
		trigger.type		= atoi(row[8]);
		strscpy(trigger.error, row[9]);
		ZBX_STR2UINT64(itemid, row[10]);

		h = NULL;

		for (i = 0; i < history_num; i++)
		{
			if (itemid == history[i].itemid)
			{
				h = &history[i];
				break;
			}
		}

		if (NULL == h)
			continue;

		exp = strdup(trigger.expression);

		if (SUCCEED != evaluate_expression(&exp_value, &exp, &trigger, error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Expression [%s] for item [" ZBX_FS_UI64 "][%s] cannot be evaluated: %s",
					trigger.expression,
					itemid,
					zbx_host_key_string(itemid),
					error);
			zabbix_syslog("Expression [%s] for item [" ZBX_FS_UI64 "][%s] cannot be evaluated: %s",
					trigger.expression,
					itemid,
					zbx_host_key_string(itemid),
					error);
/*			We shouldn't update triggervalue if expressions failed */
			DBupdate_trigger_value(&trigger, TRIGGER_VALUE_UNKNOWN, h->clock, error);
		}
		else
			DBupdate_trigger_value(&trigger, exp_value, h->clock, NULL);

		zbx_free(exp);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_update_item                                               *
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
static void	DCmass_update_item(ZBX_DC_HISTORY *history, int history_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	char		*value_esc, *message = NULL;
	int		sql_offset = 0, i;
	ZBX_DC_HISTORY	*h;
	zbx_uint64_t	*ids = NULL;
	int		ids_alloc, ids_num = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In DCmass_update_item()");

	ids_alloc = history_num;
	ids = zbx_malloc(ids, ids_alloc * sizeof(zbx_uint64_t));

	for (i = 0; i < history_num; i++)
		uint64_array_add(&ids, &ids_alloc, &ids_num, history[i].itemid, 64);

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
			"select itemid,status,lastclock,prevorgvalue,delta,multiplier,formula,history,trends"
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

		if (zbx_process == ZBX_PROCESS_PROXY)
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

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "update items set lastclock=%d",
				h->clock);

		switch (h->value_type) {
		case ITEM_VALUE_TYPE_FLOAT:
			switch (item.delta) {
			case ITEM_STORE_AS_IS:
				h->value.value_float = DBmultiply_value_float(&item, h->value_orig.value_float);
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						",prevvalue=lastvalue,prevorgvalue=NULL,lastvalue='" ZBX_FS_DBL "'",
						h->value.value_float);
				break;
			case ITEM_STORE_SPEED_PER_SECOND:
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_dbl <= h->value_orig.value_float && item.lastclock < h->clock)
				{
					h->value.value_float = (h->value_orig.value_float - item.prevorgvalue_dbl) / (h->clock - item.lastclock);
					h->value.value_float = DBmultiply_value_float(&item, h->value.value_float);
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
					h->value_null = 1;
				}
				break;
			case ITEM_STORE_SIMPLE_CHANGE:
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_dbl <= h->value_orig.value_float)
				{
					h->value.value_float = h->value_orig.value_float - item.prevorgvalue_dbl;
					h->value.value_float = DBmultiply_value_float(&item, h->value.value_float);
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
					h->value_null = 1;
				}
				break;
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
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_uint64 <= h->value_orig.value_uint64 && item.lastclock < h->clock)
				{
					h->value.value_uint64 = (h->value_orig.value_uint64 - item.prevorgvalue_uint64) / (h->clock - item.lastclock);
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
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 576,
					",prevvalue=lastvalue,lastvalue='%s'",
					value_esc);
			zbx_free(value_esc);
			break;
		case ITEM_VALUE_TYPE_LOG:
			value_esc = DBdyn_escape_string_len(h->value_orig.value_str, ITEM_LASTVALUE_LEN);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 600,
					",prevvalue=lastvalue,lastvalue='%s',lastlogsize=%d,mtime=%d",
					value_esc,
					h->lastlogsize,
					h->mtime);
			zbx_free(value_esc);
			break;
		}

		/* Update item status if required */
		if (item.status == ITEM_STATUS_NOTSUPPORTED)
		{
			message = zbx_dsprintf(message, "Parameter [" ZBX_FS_UI64 "][%s] became supported by agent",
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
 * Function: DCmass_proxy_update_item                                         *
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
static void	DCmass_proxy_update_item(ZBX_DC_HISTORY *history, int history_num)
{
	int		sql_offset = 0, i, j;
	zbx_uint64_t	*ids = NULL;
	int		ids_alloc, ids_num = 0;
	int		lastlogsize, mtime;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_proxy_update_item()");

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
 * Function: DCmass_function_update                                           *
 *                                                                            *
 * Purpose: update functions lastvalue after new value is received            *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksander Vladishev                             *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void DCmass_function_update(ZBX_DC_HISTORY *history, int history_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_FUNCTION	function;
	DB_ITEM		item;
	ZBX_DC_HISTORY	*h;
	char		*lastvalue;
	char		value[MAX_STRING_LEN], *value_esc, *function_esc, *parameter_esc;
	int		sql_offset = 0, i;
	zbx_uint64_t	*ids = NULL;
	int		ids_alloc, ids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_function_update()");

	ids_alloc = history_num;
	ids = zbx_malloc(ids, ids_alloc * sizeof(zbx_uint64_t));

	for (i = 0; i < history_num; i++)
	{
		history[i].functions = 0;
		if (0 == history[i].value_null)
			uint64_array_add(&ids, &ids_alloc, &ids_num, history[i].itemid, 64);
	}

	if (0 == ids_num)
	{
		zbx_free(ids);
		return;
	}

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1024,
			"select distinct %s,f.function,f.parameter,f.itemid,f.lastvalue from %s,functions f,triggers t"
			" where f.itemid=i.itemid and h.hostid=i.hostid and f.triggerid=t.triggerid and t.status in (%d)"
			" and",
			ZBX_SQL_ITEM_FIELDS,
			ZBX_SQL_ITEM_TABLES,
			TRIGGER_STATUS_ENABLED);

	DBadd_condition_alloc(&sql, &sql_allocated, &sql_offset, "f.itemid", ids, ids_num);

	zbx_free(ids);

	result = DBselect("%s", sql);

	sql_offset = 0;

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	while (NULL != (row = DBfetch(result)))
	{
		DBget_item_from_db(&item, row);

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

		h->functions		= 1;

		function.function	= row[ZBX_SQL_ITEM_FIELDS_NUM];
		function.parameter	= row[ZBX_SQL_ITEM_FIELDS_NUM + 1];
		ZBX_STR2UINT64(function.itemid, row[ZBX_SQL_ITEM_FIELDS_NUM + 2]);
/*		It is not required to check lastvalue for NULL here */
		lastvalue		= row[ZBX_SQL_ITEM_FIELDS_NUM + 3];

		if (FAIL == evaluate_function(value, &item, function.function, function.parameter, h->clock))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Evaluation failed for function:%s",
					function.function);
			continue;
		}

		/* Update only if lastvalue differs from new one */
		if (DBis_null(lastvalue) == SUCCEED || strcmp(lastvalue, value) != 0)
		{
			value_esc = DBdyn_escape_string_len(value, FUNCTION_LASTVALUE_LEN);
			function_esc = DBdyn_escape_string(function.function);
			parameter_esc = DBdyn_escape_string(function.parameter);

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1280,
					"update functions set lastvalue='%s' where itemid=" ZBX_FS_UI64
					" and function='%s' and parameter='%s';\n",
					value_esc,
					function.itemid,
					function_esc,
					parameter_esc);

			zbx_free(parameter_esc);
			zbx_free(function_esc);
			zbx_free(value_esc);
		}
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
#ifdef HAVE_MYSQL
	int		tmp_offset;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_add_history()");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

/*
 * history
 */
#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (0 == history[i].keep_history)
			continue;

		if (history[i].value_type != ITEM_VALUE_TYPE_FLOAT)
			continue;

		if (0 != history[i].value_null)
			continue;

#ifdef HAVE_MYSQL
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"(" ZBX_FS_UI64 ",%d," ZBX_FS_DBL "),",
				history[i].itemid,
				history[i].clock,
				history[i].value.value_float);
#else
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history (itemid,clock,value) values "
				"(" ZBX_FS_UI64 ",%d," ZBX_FS_DBL ");\n",
				history[i].itemid,
				history[i].clock,
				history[i].value.value_float);
#endif
	}

#ifdef HAVE_MYSQL
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
#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_sync (nodeid,itemid,clock,value) values ");
#endif

		for (i = 0; i < history_num; i++)
		{
			if (0 == history[i].keep_history)
				continue;

			if (history[i].value_type != ITEM_VALUE_TYPE_FLOAT)
				continue;

			if (0 != history[i].value_null)
				continue;

#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL "),",
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					history[i].value.value_float);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_sync (nodeid,itemid,clock,value) values "
					"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL ");\n",
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					history[i].value.value_float);
#endif
		}

#ifdef HAVE_MYSQL
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
#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into history_uint (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (0 == history[i].keep_history)
			continue;

		if (history[i].value_type != ITEM_VALUE_TYPE_UINT64)
			continue;

		if (0 != history[i].value_null)
			continue;

#ifdef HAVE_MYSQL
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"(" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 "),",
				history[i].itemid,
				history[i].clock,
				history[i].value.value_uint64);
#else
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_uint (itemid,clock,value) values "
				"(" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ");\n",
				history[i].itemid,
				history[i].clock,
				history[i].value.value_uint64);
#endif
	}

#ifdef HAVE_MYSQL
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
#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_uint_sync (nodeid,itemid,clock,value) values ");
#endif

		for (i = 0; i < history_num; i++)
		{
			if (0 == history[i].keep_history)
				continue;

			if (history[i].value_type != ITEM_VALUE_TYPE_UINT64)
				continue;

			if (0 != history[i].value_null)
				continue;

#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_UI64 "),",
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					history[i].value.value_uint64);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_uint_sync (nodeid,itemid,clock,value) values "
					"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ");\n",
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					history[i].value.value_uint64);
#endif
		}

#ifdef HAVE_MYSQL
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
#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into history_str (itemid,clock,value) values ");
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
#ifdef HAVE_MYSQL
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"(" ZBX_FS_UI64 ",%d,'%s'),",
				history[i].itemid,
				history[i].clock,
				value_esc);
#else
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_str (itemid,clock,value) values "
				"(" ZBX_FS_UI64 ",%d,'%s');\n",
				history[i].itemid,
				history[i].clock,
				value_esc);
#endif
		zbx_free(value_esc);
	}

#ifdef HAVE_MYSQL
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
#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_str_sync (nodeid,itemid,clock,value) values ");
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
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(%d," ZBX_FS_UI64 ",%d,'%s'),",
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					value_esc);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_str_sync (nodeid,itemid,clock,value) values "
					"(%d," ZBX_FS_UI64 ",%d,'%s');\n",
					get_nodeid_by_id(history[i].itemid),
					history[i].itemid,
					history[i].clock,
					value_esc);
#endif
			zbx_free(value_esc);
		}

#ifdef HAVE_MYSQL
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
		id = DBget_maxid_num("history_text", "id", history_text_num);

#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_text (id,itemid,clock,value) values ");
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
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s'),",
					id,
					history[i].itemid,
					history[i].clock,
					value_esc);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"insert into history_text (id,itemid,clock,value) values "
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s');\n",
					id,
					history[i].itemid,
					history[i].clock,
					value_esc);
#endif
			zbx_free(value_esc);
			id++;
		}

#ifdef HAVE_MYSQL
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
		id = DBget_maxid_num("history_log", "id", history_log_num);

#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into history_log (id,itemid,clock,timestamp,source,severity,value,logeventid) values ");
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
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s',%d),",
					id,
					history[i].itemid,
					history[i].clock,
					history[i].timestamp,
					source_esc,
					history[i].severity,
					value_esc,
					history[i].logeventid);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"insert into history_log (id,itemid,clock,timestamp,source,severity,value,logeventid) values "
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s',%d);\n",
					id,
					history[i].itemid,
					history[i].clock,
					history[i].timestamp,
					source_esc,
					history[i].severity,
					value_esc,
					history[i].logeventid);
#endif
			zbx_free(value_esc);
			zbx_free(source_esc);
			id++;
		}

#ifdef HAVE_MYSQL
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

#ifdef HAVE_MYSQL
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
#ifdef HAVE_MYSQL
	int		tmp_offset;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_proxy_add_history()");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into proxy_history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_FLOAT)
		{
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d,'" ZBX_FS_DBL "'),",
					history[i].itemid,
					history[i].clock,
					history[i].value_orig.value_float);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into proxy_history (itemid,clock,value) values "
					"(" ZBX_FS_UI64 ",%d,'" ZBX_FS_DBL "');\n",
					history[i].itemid,
					history[i].clock,
					history[i].value_orig.value_float);
#endif
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into proxy_history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_UINT64)
		{
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d,'" ZBX_FS_UI64 "'),",
					history[i].itemid,
					history[i].clock,
					history[i].value_orig.value_uint64);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into proxy_history (itemid,clock,value) values "
					"(" ZBX_FS_UI64 ",%d,'" ZBX_FS_UI64 "');\n",
					history[i].itemid,
					history[i].clock,
					history[i].value_orig.value_uint64);
#endif
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into proxy_history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_STR)
		{
			value_esc = DBdyn_escape_string(history[i].value_orig.value_str);
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d,'%s'),",
					history[i].itemid,
					history[i].clock,
					value_esc);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into proxy_history (itemid,clock,value) values "
					"(" ZBX_FS_UI64 ",%d,'%s');\n",
					history[i].itemid,
					history[i].clock,
					value_esc);
#endif
			zbx_free(value_esc);
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			"insert into proxy_history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_TEXT)
		{
			value_esc = DBdyn_escape_string(history[i].value_orig.value_str);
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"(" ZBX_FS_UI64 ",%d,'%s'),",
					history[i].itemid,
					history[i].clock,
					value_esc);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"insert into proxy_history (itemid,clock,value) values "
					"(" ZBX_FS_UI64 ",%d,'%s');\n",
					history[i].itemid,
					history[i].clock,
					value_esc);
#endif
			zbx_free(value_esc);
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"insert into proxy_history (itemid,clock,timestamp,source,severity,value,logeventid) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_LOG)
		{
			source_esc = DBdyn_escape_string_len(history[i].source, HISTORY_LOG_SOURCE_LEN);
			value_esc = DBdyn_escape_string(history[i].value_orig.value_str);
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"(" ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s',%d),",
					history[i].itemid,
					history[i].clock,
					history[i].timestamp,
					source_esc,
					history[i].severity,
					value_esc,
					history[i].logeventid);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512 + strlen(value_esc),
					"insert into proxy_history (itemid,clock,timestamp,source,severity,value,logeventid) values "
					"(" ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s',%d);\n",
					history[i].itemid,
					history[i].clock,
					history[i].timestamp,
					source_esc,
					history[i].severity,
					value_esc,
					history[i].logeventid);
#endif
			zbx_free(value_esc);
			zbx_free(source_esc);
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	sql[sql_offset] = '\0';
#endif

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

static int DCitem_already_exists(ZBX_DC_HISTORY *history, int history_num, zbx_uint64_t itemid)
{
	int	i;

	for (i = 0; i < history_num; i++)
		if (itemid == history[i].itemid)
			return SUCCEED;

	return FAIL;
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
	}

	if (0 == cache->history_num)
		return 0;

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
			if (zbx_process == ZBX_PROCESS_PROXY || FAIL == DCitem_already_exists(history, history_num, cache->history[f].itemid))
			{
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

		DBbegin();

		if (zbx_process == ZBX_PROCESS_SERVER)
		{
			DCmass_update_item(history, history_num);
			DCmass_add_history(history, history_num);
			DCmass_function_update(history, history_num);
			DCmass_update_triggers(history, history_num);
			DCmass_update_trends(history, history_num);
		}
		else
		{
			DCmass_proxy_add_history(history, history_num);
			DCmass_proxy_update_item(history, history_num);
		}

		DBcommit();

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
			zabbix_log(LOG_LEVEL_WARNING, "Syncing history data..." ZBX_FS_DBL "%%",
					(double)total_num / (cache->history_num + total_num) * 100);
			now = time(NULL);
		}
	} while (--syncs > 0 || sync_type == ZBX_SYNC_FULL || (skipped_clock != 0 && skipped_clock < max_delay));

	if (ZBX_SYNC_FULL == sync_type)
		zabbix_log(LOG_LEVEL_WARNING, "Syncing history data...done.");

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
			return;

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
	} else
		cache->last_text = cache->text;
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
static ZBX_DC_HISTORY *DCget_history_ptr(zbx_uint64_t itemid, size_t text_len)
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
void	DCadd_history(zbx_uint64_t itemid, double value_orig, int clock)
{
	ZBX_DC_HISTORY	*history;

	LOCK_CACHE;

	history = DCget_history_ptr(itemid, 0);

	history->itemid			= itemid;
	history->clock			= clock;
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
void	DCadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value_orig, int clock)
{
	ZBX_DC_HISTORY	*history;

	LOCK_CACHE;

	history = DCget_history_ptr(itemid, 0);

	history->itemid				= itemid;
	history->clock				= clock;
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
void	DCadd_history_str(zbx_uint64_t itemid, char *value_orig, int clock)
{
	ZBX_DC_HISTORY	*history;
	size_t		len;

	LOCK_CACHE;

	if (HISTORY_STR_VALUE_LEN_MAX < (len = strlen(value_orig) + 1))
		len = HISTORY_STR_VALUE_LEN_MAX;
	history = DCget_history_ptr(itemid, len);

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_STR;
	history->value_orig.value_str	= cache->last_text;
	history->value.value_str	= cache->last_text;
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
void	DCadd_history_text(zbx_uint64_t itemid, char *value_orig, int clock)
{
	ZBX_DC_HISTORY	*history;
	size_t		len;

	LOCK_CACHE;

	if (HISTORY_TEXT_VALUE_LEN_MAX < (len = strlen(value_orig) + 1))
		len = HISTORY_TEXT_VALUE_LEN_MAX;
	history = DCget_history_ptr(itemid, len);

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_TEXT;
	history->value_orig.value_str	= cache->last_text;
	history->value.value_str	= cache->last_text;
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
void	DCadd_history_log(zbx_uint64_t itemid, char *value_orig, int clock, int timestamp, char *source, int severity,
		int logeventid, int lastlogsize, int mtime)
{
	ZBX_DC_HISTORY	*history;
	size_t		len1, len2;

	LOCK_CACHE;

	if (HISTORY_LOG_VALUE_LEN_MAX < (len1 = strlen(value_orig) + 1))
		len1 = HISTORY_LOG_VALUE_LEN_MAX;
	if (HISTORY_LOG_SOURCE_LEN_MAX < (len2 = (NULL != source && *source != '\0') ? strlen(source) + 1 : 0))
		len2 = HISTORY_LOG_SOURCE_LEN_MAX;
	history = DCget_history_ptr(itemid, len1 + len2);

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_LOG;
	history->value_orig.value_str	= cache->last_text;
	history->value.value_str	= cache->last_text;
	zbx_strlcpy(cache->last_text, value_orig, len1);
	history->value_null		= 0;
	cache->last_text		+= len1;
	history->timestamp		= timestamp;

	if (0 != len2) {
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
 * Function: init_database_cache                                              *
 *                                                                            *
 * Purpose: Allocate shared memory for database cache                         *
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
void	init_database_cache(zbx_process_t p)
{
	key_t	shm_key;
	int	shm_id;
	size_t	sz;
	void	*ptr;

	zbx_process = p;

	ZBX_GET_SHM_DBCACHE_KEY(shm_key);

	ZBX_HISTORY_SIZE = CONFIG_HISTORY_CACHE_SIZE / sizeof(ZBX_DC_HISTORY);
	ZBX_TREND_SIZE = CONFIG_TRENDS_CACHE_SIZE / sizeof(ZBX_DC_TREND);
	if (ZBX_SYNC_MAX > ZBX_HISTORY_SIZE)
		ZBX_SYNC_MAX = ZBX_HISTORY_SIZE;

	sz = sizeof(ZBX_DC_CACHE);
	sz += ZBX_HISTORY_SIZE * sizeof(ZBX_DC_HISTORY);
	sz += ZBX_TREND_SIZE * sizeof(ZBX_DC_TREND);
	sz += CONFIG_TEXT_CACHE_SIZE;
	sz += sizeof(ZBX_DC_IDS);

	zabbix_log(LOG_LEVEL_DEBUG, "In init_database_cache() size:%d", (int)sz);

	if ( -1 == (shm_id = zbx_shmget(shm_key, sz)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't allocate shared memory for database cache.");
		exit(1);
	}

	ptr = shmat(shm_id, 0, 0);

	if ((void*)(-1) == ptr)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't attach shared memory for database cache. [%s]",strerror(errno));
		exit(FAIL);
	}

	if(ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cache_lock, ZBX_MUTEX_CACHE))
	{
		zbx_error("Unable to create mutex for database cache");
		exit(FAIL);
	}

	if(ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cache_ids_lock, ZBX_MUTEX_CACHE_IDS))
	{
		zbx_error("Unable to create mutex for database cache");
		exit(FAIL);
	}

	cache = ptr;
	cache->history_first = 0;
	cache->history_num = 0;
	cache->trends_num = 0;

	ptr += sizeof(ZBX_DC_CACHE);
	cache->history = ptr;

	ptr += ZBX_HISTORY_SIZE * sizeof(ZBX_DC_HISTORY);
	cache->trends = ptr;

	ptr += ZBX_TREND_SIZE * sizeof(ZBX_DC_TREND);
	cache->text = ptr;
	cache->last_text = cache->text;

	ptr += CONFIG_TEXT_CACHE_SIZE;
	ids = ptr;
	memset(ids, 0, sizeof(ZBX_DC_IDS));
	memset(&cache->stats, 0, sizeof(ZBX_DC_STATS));

	if (NULL == sql)
		sql = zbx_malloc(sql, sql_allocated);
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
	zabbix_log(LOG_LEVEL_DEBUG,"In DCsync_all()");

	DCsync_history(ZBX_SYNC_FULL);
	DCsync_trends();

	zabbix_log(LOG_LEVEL_DEBUG,"End of DCsync_all()");
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
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	free_database_cache()
{
	key_t	shm_key;
	int	shm_id;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In free_database_cache()");

	DCsync_all();

	LOCK_CACHE;
	LOCK_CACHE_IDS;

	ZBX_GET_SHM_DBCACHE_KEY(shm_key);

	sz = sizeof(ZBX_DC_IDS);
	sz += sizeof(ZBX_DC_CACHE);

	shm_id = shmget(shm_key, sz, 0);

	if (-1 == shm_id)
	{
		zabbix_log(LOG_LEVEL_ERR, "Can't find shared memory for database cache. [%s]",strerror(errno));
		exit(1);
	}

	shmctl(shm_id, IPC_RMID, 0);

	cache = NULL;

	UNLOCK_CACHE;
	UNLOCK_CACHE_IDS;

	zbx_mutex_destroy(&cache_lock);
	zbx_mutex_destroy(&cache_ids_lock);

	zabbix_log(LOG_LEVEL_DEBUG,"End of free_database_cache()");
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_maxid                                                      *
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
zbx_uint64_t	DCget_nextid(const char *table_name, const char *field_name, int num)
{
	int		i, nodeid;
	DB_RESULT	result;
	DB_ROW		row;
	const ZBX_TABLE	*table;
	ZBX_DC_ID	*id;
	zbx_uint64_t	min, max, nextid;

	LOCK_CACHE_IDS;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCget_nextid %s.%s [%d]",
			table_name, field_name, num);

	for (i = 0; i < ZBX_IDS_SIZE; i++)
	{
		id = &ids->id[i];
		if ('\0' == *id->table_name)
			break;

		if (0 == strcmp(id->table_name, table_name) && 0 == strcmp(id->field_name, field_name))
		{
			nextid = id->lastid + 1;
			id->lastid += num;

			zabbix_log(LOG_LEVEL_DEBUG, "End of DCget_nextid %s.%s [" ZBX_FS_UI64 ":" ZBX_FS_UI64 "]",
					table_name, field_name, nextid, id->lastid);

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
	zbx_strlcpy(id->field_name, field_name, sizeof(id->field_name));

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
			field_name,
			table_name,
			field_name,
			min, max);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
		id->lastid = min;
	else
		ZBX_STR2UINT64(id->lastid, row[0]);

	nextid = id->lastid + 1;
	id->lastid += num;

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of DCget_nextid %s.%s [" ZBX_FS_UI64 ":" ZBX_FS_UI64 "]",
			table_name, field_name, nextid, id->lastid);

	UNLOCK_CACHE_IDS;

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
	int	i, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCget_item_lastclock()");

	LOCK_CACHE;

	for (i = 0; i < cache->history_num; i++)
	{
		index = (cache->history_first + i) % ZBX_HISTORY_SIZE;
		if (cache->history[index].itemid == itemid)
		{
			UNLOCK_CACHE;

			return cache->history[index].clock;
		}
	}

	UNLOCK_CACHE;

	return FAIL;
}

