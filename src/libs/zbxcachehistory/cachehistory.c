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

#include "zbxcachehistory.h"

#include "zbxmutexs.h"
#include "module.h"
#include "zbxexport.h"
#include "zbxavailability.h"
#include "zbxconnector.h"
#include "zbxtrends.h"
#include "zbxnum.h"
#include "zbxsysinfo.h"
#include "zbx_item_constants.h"
#include "zbxtagfilter.h"
#include "zbxcrypto.h"
#include "zbxalgo.h"
#include "zbxhistory.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxdbschema.h"
#include "zbxjson.h"
#include "zbxshmem.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxvariant.h"
#include "zbxipcservice.h"

static zbx_shmem_info_t	*hc_index_mem = NULL;
static zbx_shmem_info_t	*hc_mem = NULL;
static zbx_shmem_info_t	*trend_mem = NULL;

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

static zbx_get_program_type_f	get_program_type_cb = NULL;
static zbx_history_sync_f	sync_history_cb = NULL;

#define ZBX_IDS_SIZE	14

#define ZBX_HC_ITEMS_INIT_SIZE	1000

#define ZBX_TRENDS_CLEANUP_TIME	(SEC_PER_MIN * 55)

/* the maximum number of characters for history cache values (except binary) */
#define ZBX_HISTORY_VALUE_LEN		(1024 * 64)

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
	zbx_dc_stats_t		stats;

	zbx_hashset_t		history_items;
	zbx_binary_heap_t	history_queue;

	int			history_num;
	int			trends_num;
	int			trends_last_cleanup_hour;
	int			history_num_total;
	int			history_progress_ts;

	unsigned char		db_trigger_queue_lock;

	zbx_hc_proxyqueue_t	proxyqueue;
	int			processing_num;
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
static void	hc_queue_item(zbx_hc_item_t *item);
static int	hc_queue_elem_compare_func(const void *d1, const void *d2);

void	zbx_pp_value_opt_clear(zbx_pp_value_opt_t *opt)
{
	if (0 != (opt->flags & ZBX_PP_VALUE_OPT_LOG))
		zbx_free(opt->source);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves all internal metrics of the database cache              *
 *                                                                            *
 * Parameters: stats - [OUT] write cache metrics                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_get_stats_all(zbx_wcache_info_t *wcache_info)
{
	LOCK_CACHE;

	wcache_info->stats = cache->stats;
	wcache_info->history_free = hc_mem->free_size;
	wcache_info->history_total = hc_mem->total_size;
	wcache_info->index_free = hc_index_mem->free_size;
	wcache_info->index_total = hc_index_mem->total_size;

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
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
void	*zbx_dc_get_stats(int request)
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
		case ZBX_STATS_HISTORY_BIN_COUNTER:
			value_uint = cache->stats.history_bin_counter;
			ret = (void *)&value_uint;
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

void	zbx_trend_add_new_items(const zbx_vector_uint64_t *itemids)
{
	int	i, hour, now = time(NULL);

	hour = now - now % SEC_PER_HOUR;

	LOCK_TRENDS;

	for (i = 0; i < itemids->values_num; i++)
	{
		ZBX_DC_TREND	trend = {.itemid = itemids->values[i], .disable_from = hour};

		(void)zbx_hashset_insert(&cache->trends, &trend, sizeof(ZBX_DC_TREND));
	}

	UNLOCK_TRENDS;
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply disable_from changes to cache                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_update_trends(zbx_vector_uint64_pair_t *trends_diff)
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
			"value_max", (char *)NULL);

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
			zbx_udiv128_64(&avg, &trend->value_avg.ui64, trend->num);

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
	zbx_db_result_t	result;
	zbx_db_row_t	row;
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
				"select itemid"
				" from %s"
				" where clock>=%d and",
				table_name, clocks[j]);

		if (0 < j)
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " clock<%d and", clocks[j - 1]);

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids, *itemids_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);
			uint64_array_remove(itemids, itemids_num, &itemid, 1);
		}
		zbx_db_free_result(result);
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
static void	dc_trends_update_float(ZBX_DC_TREND *trend, zbx_db_row_t row, int num, size_t *sql_offset)
{
	zbx_history_value_t	value_min, value_avg, value_max;

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
static void	dc_trends_update_uint(ZBX_DC_TREND *trend, zbx_db_row_t row, int num, size_t *sql_offset)
{
	zbx_history_value_t	value_min, value_avg, value_max;
	zbx_uint128_t		avg;

	ZBX_STR2UINT64(value_min.ui64, row[2]);
	ZBX_STR2UINT64(value_avg.ui64, row[3]);
	ZBX_STR2UINT64(value_max.ui64, row[4]);

	if (value_min.ui64 < trend->value_min.ui64)
		trend->value_min.ui64 = value_min.ui64;
	if (value_max.ui64 > trend->value_max.ui64)
		trend->value_max.ui64 = value_max.ui64;

	/* calculate the trend average value */
	zbx_umul64_64(&avg, num, value_avg.ui64);
	zbx_uinc128_128(&trend->value_avg.ui64, &avg);
	zbx_udiv128_64(&avg, &trend->value_avg.ui64, trend->num + num);

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
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	itemid;
	ZBX_DC_TREND	*trend;
	size_t		sql_offset;

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select itemid,num,value_min,value_avg,value_max"
			" from %s"
			" where clock=%d and",
			table_name, clock);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids, itemids_num);

	result = zbx_db_select("%s order by itemid,clock", sql);

	sql_offset = 0;

	while (NULL != (row = zbx_db_fetch(result)))
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

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	zbx_db_free_result(result);

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush trend to the database                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_flush_trends(ZBX_DC_TREND *trends, int *trends_num, zbx_vector_uint64_pair_t *trends_diff)
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
			zbx_this_should_never_happen_backtrace();
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
	memset(&trend->value_min, 0, sizeof(zbx_history_value_t));
	memset(&trend->value_avg, 0, sizeof(zbx_value_avg_t));
	memset(&trend->value_max, 0, sizeof(zbx_history_value_t));
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new value to the trends                                       *
 *                                                                            *
 ******************************************************************************/
static void	DCadd_trend(const zbx_dc_history_t *history, ZBX_DC_TREND **trends, int *trends_alloc, int *trends_num)
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
			zbx_uinc128_64(&trend->value_avg.ui64, history->value.ui64);
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
void	zbx_dc_mass_update_trends(const zbx_dc_history_t *history, int history_num, ZBX_DC_TREND **trends,
		int *trends_num, int compression_age)
{
	static int		last_trend_discard = 0;
	zbx_timespec_t		ts;
	int			trends_alloc = 0, i, hour, seconds;
	zbx_vector_uint64_t	del_itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&del_itemids);

	zbx_timespec(&ts);
	seconds = ts.sec % SEC_PER_HOUR;
	hour = ts.sec - seconds;

	LOCK_TRENDS;

	for (i = 0; i < history_num; i++)
	{
		const zbx_dc_history_t	*h = &history[i];

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
			if (0 != compression_age && trend->clock < compression_age && 0 != trend->clock)
			{
				if (SEC_PER_HOUR < (ts.sec - last_trend_discard)) /* log once per hour */
				{
					zabbix_log(LOG_LEVEL_TRACE, "discarding trends that are pointing to"
							" compressed history period");
					last_trend_discard = ts.sec;
				}

				trend->clock = 0;
				trend->num = 0;
				memset(&trend->value_min, 0, sizeof(zbx_history_value_t));
				memset(&trend->value_avg, 0, sizeof(zbx_value_avg_t));
				memset(&trend->value_max, 0, sizeof(zbx_history_value_t));
			}
			else
			{
				if (SUCCEED == zbx_history_requires_trends(trend->value_type) && 0 != trend->num)
					DCflush_trend(trend, trends, &trends_alloc, trends_num);

				/* trend is missing an hour, check if it should be cleared from cache */
				if (0 != trend->disable_from && trend->disable_from < hour - SEC_PER_HOUR)
					zbx_vector_uint64_append(&del_itemids, trend->itemid);
			}
		}

		cache->trends_last_cleanup_hour = hour;
	}

	UNLOCK_TRENDS;

	if (0 != del_itemids.values_num)
	{
		zbx_dc_config_history_sync_unset_existing_itemids(&del_itemids);

		if (0 != del_itemids.values_num)
		{
			LOCK_TRENDS;

			for (i = 0; i < del_itemids.values_num; i++)
			{
				ZBX_DC_TREND	*trend;

				if (NULL == (trend = (ZBX_DC_TREND *)zbx_hashset_search(&cache->trends,
						&del_itemids.values[i])))
				{
					continue;
				}

				zbx_hashset_remove_direct(&cache->trends, trend);
			}

			UNLOCK_TRENDS;
		}
	}

	zbx_vector_uint64_destroy(&del_itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

int	zbx_trend_compare(const void *d1, const void *d2)
{
	const ZBX_DC_TREND	*p1 = (const ZBX_DC_TREND *)d1;
	const ZBX_DC_TREND	*p2 = (const ZBX_DC_TREND *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->itemid, p2->itemid);
	ZBX_RETURN_IF_NOT_EQUAL(p1->clock, p2->clock);

	return 0;
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
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_host_info_t		*host_info;
	zbx_hashset_iter_t	iter;

	for (i = 0; i < hostids->values_num; i++)
	{
		zbx_host_info_t	host_info_new = {.hostid = hostids->values[i]};

		zbx_vector_ptr_create(&host_info_new.groups);
		zbx_hashset_insert(hosts_info, &host_info_new, sizeof(host_info_new));
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct hg.hostid,g.name"
				" from hstgrp g,hosts_groups hg"
				" where g.groupid=hg.groupid"
					" and");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.hostid", hostids->values, hostids->values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostid;

		ZBX_DBROW2UINT64(hostid, row[0]);

		if (NULL == (host_info = (zbx_host_info_t *)zbx_hashset_search(hosts_info, &hostid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_ptr_append(&host_info->groups, zbx_strdup(NULL, row[1]));
	}

	zbx_hashset_iter_reset(hosts_info, &iter);
	while (NULL != (host_info = (zbx_host_info_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_sort(&host_info->groups, ZBX_DEFAULT_STR_COMPARE_FUNC);

	zbx_db_free_result(result);
}

typedef struct
{
	zbx_uint64_t		itemid;
	char			*name;
	zbx_history_sync_item_t	*item;
	zbx_vector_tags_ptr_t	item_tags;
}
zbx_item_info_t;

/******************************************************************************
 *                                                                            *
 * Purpose: get item names                                                    *
 *                                                                            *
 * Parameters: items_info - [IN/OUT] output item names                        *
 *             itemids    - [IN] the item identifiers                         *
 *                                                                            *
 ******************************************************************************/
static void	db_get_item_names_by_itemid(zbx_hashset_t *items_info, const zbx_vector_uint64_t *itemids)
{
	size_t		sql_offset = 0;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select itemid,name from items where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
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

	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get item tags                                                     *
 *                                                                            *
 * Parameters: items_info - [IN/OUT] output item tags                         *
 *             itemids    - [IN] the item identifiers                         *
 *                                                                            *
 ******************************************************************************/
static void	db_get_item_tags_by_itemid(zbx_hashset_t *items_info, const zbx_vector_uint64_t *itemids)
{
	size_t		sql_offset = 0;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_item_info_t	*item_info = NULL;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select itemid,tag,value from item_tag where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by itemid");

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	itemid;
		zbx_tag_t	*item_tag;

		ZBX_DBROW2UINT64(itemid, row[0]);

		if (NULL == item_info || item_info->itemid != itemid)
		{
			if (NULL != item_info)
			{
				zbx_vector_tags_ptr_sort(&item_info->item_tags, zbx_compare_tags);
			}
			if (NULL == (item_info = (zbx_item_info_t *)zbx_hashset_search(items_info, &itemid)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
		}

		item_tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(*item_tag));
		item_tag->tag = zbx_strdup(NULL, row[1]);
		item_tag->value = zbx_strdup(NULL, row[2]);
		zbx_vector_tags_ptr_append(&item_info->item_tags, item_tag);
	}

	if (NULL != item_info)
	{
		zbx_vector_tags_ptr_sort(&item_info->item_tags, zbx_compare_tags);
	}

	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get item names and item tags                                      *
 *                                                                            *
 * Parameters: items_info - [IN/OUT] output item name and item tags           *
 *             itemids    - [IN] the item identifiers                         *
 *                                                                            *
 ******************************************************************************/
static void	db_get_items_info_by_itemid(zbx_hashset_t *items_info, const zbx_vector_uint64_t *itemids)
{
	db_get_item_names_by_itemid(items_info, itemids);
	db_get_item_tags_by_itemid(items_info, itemids);
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
	zbx_vector_tags_ptr_clear_ext(&item_info->item_tags, zbx_free_tag);
	zbx_vector_tags_ptr_destroy(&item_info->item_tags);
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
	struct zbx_json			json;
	const ZBX_DC_TREND		*trend = NULL;
	int				i, j;
	const zbx_history_sync_item_t	*item;
	zbx_host_info_t			*host_info;
	zbx_item_info_t			*item_info;
	zbx_uint128_t			avg;	/* calculate the trend average value */

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
			zbx_tag_t	*item_tag = item_info->item_tags.values[j];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_TAG, item_tag->tag, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, item_tag->value, ZBX_JSON_TYPE_STRING);
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
				zbx_udiv128_64(&avg, &trend->value_avg.ui64, trend->num);
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

static int	match_item_value_type_by_mask(int mask, const zbx_history_sync_item_t *item)
{
	if (0 != (mask & (1 << item->value_type)))
		return SUCCEED;

	return FAIL;
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
static void	DCexport_history(const zbx_dc_history_t *history, int history_num, zbx_hashset_t *hosts_info,
		zbx_hashset_t *items_info, int history_export_enabled, zbx_vector_connector_filter_t *connector_filters,
		unsigned char **data, size_t *data_alloc, size_t *data_offset)
{
	const zbx_dc_history_t		*h;
	const zbx_history_sync_item_t	*item;
	int				i, j;
	zbx_host_info_t			*host_info;
	zbx_item_info_t			*item_info;
	struct zbx_json			json;
	zbx_connector_object_t		connector_object;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_vector_uint64_create(&connector_object.ids);

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

		if (0 != connector_filters->values_num)
		{
			int	k;

			for (k = 0; k < connector_filters->values_num; k++)
			{
				if (SUCCEED == match_item_value_type_by_mask(connector_filters->values[k].
						item_value_type, item) && SUCCEED ==
						zbx_match_tags(connector_filters->values[k].tags_evaltype,
						&connector_filters->values[k].connector_tags, &item_info->item_tags))
				{
					zbx_vector_uint64_append(&connector_object.ids,
							connector_filters->values[k].connectorid);
				}
			}
		}

		if (0 == connector_object.ids.values_num &&
				(FAIL == history_export_enabled || ITEM_VALUE_TYPE_BIN == h->value_type))
		{
			continue;
		}

		zbx_json_clean(&json);

		zbx_json_addobject(&json, ZBX_PROTO_TAG_HOST);
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
			zbx_tag_t	*item_tag = item_info->item_tags.values[j];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_TAG, item_tag->tag, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, item_tag->value, ZBX_JSON_TYPE_STRING);
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
				zbx_json_adddouble(&json, ZBX_PROTO_TAG_VALUE, h->value.dbl);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_json_adduint64(&json, ZBX_PROTO_TAG_VALUE, h->value.ui64);
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
			case ITEM_VALUE_TYPE_BIN:
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
			case ITEM_VALUE_TYPE_NONE:
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
		}

		zbx_json_adduint64(&json, ZBX_PROTO_TAG_TYPE, h->value_type);

		if (0 != connector_object.ids.values_num)
		{
			connector_object.objectid = item->itemid;
			connector_object.ts = h->ts;
			connector_object.str = json.buffer;

			zbx_connector_serialize_object(data, data_alloc, data_offset, &connector_object);

			zbx_vector_uint64_clear(&connector_object.ids);
		}

		if (SUCCEED == history_export_enabled && ITEM_VALUE_TYPE_BIN != h->value_type)
			zbx_history_export_write(json.buffer, json.buffer_size);
	}

	if (SUCCEED == history_export_enabled)
		zbx_history_export_flush();

	zbx_vector_uint64_destroy(&connector_object.ids);
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
void	zbx_dc_export_history_and_trends(const zbx_dc_history_t *history, int history_num,
		const zbx_vector_uint64_t *itemids, zbx_history_sync_item_t *items, const int *errcodes,
		const ZBX_DC_TREND *trends, int trends_num, int history_export_enabled,
		zbx_vector_connector_filter_t *connector_filters, unsigned char **data, size_t *data_alloc,
		size_t *data_offset)
{
	int			i, index, *trend_errcodes = NULL;
	zbx_vector_uint64_t	hostids, item_info_ids, trend_itemids;
	zbx_hashset_t		hosts_info, items_info;
	zbx_history_sync_item_t	*item;
	zbx_item_info_t		item_info;
	zbx_history_sync_item_t	*trend_items = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d trends_num:%d", __func__, history_num, trends_num);

	zbx_vector_uint64_create(&trend_itemids);
	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&item_info_ids);
	zbx_hashset_create_ext(&items_info, itemids->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)zbx_item_info_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	for (i = 0; i < history_num; i++)
	{
		const zbx_dc_history_t	*h = &history[i];

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
		zbx_vector_tags_ptr_create(&item_info.item_tags);
		zbx_hashset_insert(&items_info, &item_info, sizeof(item_info));
	}

	for (i = 0; i < trends_num; i++)
	{
		const ZBX_DC_TREND	*trend = &trends[i];

		if (FAIL == zbx_vector_uint64_bsearch(itemids, trend->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			zbx_vector_uint64_append(&trend_itemids, trend->itemid);
	}

	if (0 != trend_itemids.values_num)
	{
		zbx_vector_uint64_sort(&trend_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&trend_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		trend_items = (zbx_history_sync_item_t *)zbx_malloc(NULL, sizeof(zbx_history_sync_item_t) *
				(size_t)trend_itemids.values_num);
		trend_errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)trend_itemids.values_num);

		zbx_dc_config_history_sync_get_items_by_itemids(trend_items, trend_itemids.values, trend_errcodes,
				(size_t)trend_itemids.values_num, ZBX_ITEM_GET_SYNC_EXPORT);
	}

	for (i = 0; i < trends_num; i++)
	{
		const ZBX_DC_TREND	*trend = &trends[i];
		int			errcode;

		if (FAIL != (index = zbx_vector_uint64_bsearch(itemids, trend->itemid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			item = &items[index];
			errcode = errcodes[index];
		}
		else
		{
			if (FAIL == (index = zbx_vector_uint64_bsearch(&trend_itemids, trend->itemid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = &trend_items[index];
			errcode = trend_errcodes[index];
		}

		if (SUCCEED != errcode)
			continue;

		zbx_vector_uint64_append(&hostids, item->host.hostid);
		zbx_vector_uint64_append(&item_info_ids, item->itemid);

		item_info.itemid = item->itemid;
		item_info.name = NULL;
		item_info.item = item;
		zbx_vector_tags_ptr_create(&item_info.item_tags);
		zbx_hashset_insert(&items_info, &item_info, sizeof(item_info));
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
	{
		DCexport_history(history, history_num, &hosts_info, &items_info, history_export_enabled,
				connector_filters, data, data_alloc, data_offset);
	}

	if (0 != trends_num)
		DCexport_trends(trends, trends_num, &hosts_info, &items_info);

	zbx_hashset_destroy(&hosts_info);
clean:
	zbx_dc_config_clean_history_sync_items(trend_items, trend_errcodes, (size_t)trend_itemids.values_num);
	zbx_hashset_destroy(&items_info);
	zbx_vector_uint64_destroy(&item_info_ids);
	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_uint64_destroy(&trend_itemids);
	zbx_free(trend_items);
	zbx_free(trend_errcodes);

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
	zbx_vector_uint64_t	itemids;
	size_t			num;

	zabbix_log(LOG_LEVEL_WARNING, "exporting trend data...");

	zbx_vector_uint64_create(&itemids);

	while (0 < trends_num)
	{
		num = (size_t)MIN(ZBX_HC_SYNC_MAX, trends_num);

		zbx_dc_export_history_and_trends(NULL, 0, &itemids, NULL, NULL, trends, (int)num, FAIL, NULL, NULL, 0,
			0);

		trends += num;
		trends_num -= (int)num;
	}

	zbx_vector_uint64_destroy(&itemids);
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

	compression_age = zbx_hc_get_history_compression_age();

	zabbix_log(LOG_LEVEL_WARNING, "syncing trend data...");

	LOCK_TRENDS;

	zbx_hashset_iter_reset(&cache->trends, &iter);

	while (NULL != (trend = (ZBX_DC_TREND *)zbx_hashset_iter_next(&iter)))
	{
		if (SUCCEED == zbx_history_requires_trends(trend->value_type) && trend->clock >= compression_age &&
				0 != trend->num)
		{
			DCflush_trend(trend, &trends, &trends_alloc, &trends_num);
		}
	}

	UNLOCK_TRENDS;

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS) && 0 != trends_num)
		DCexport_all_trends(trends, trends_num);

	if (0 < trends_num)
		qsort(trends, trends_num, sizeof(ZBX_DC_TREND), zbx_trend_compare);

	zbx_db_begin();

	while (trends_num > 0)
		zbx_db_flush_trends(trends, &trends_num, NULL);

	zbx_db_commit();

	zbx_free(trends);

	zabbix_log(LOG_LEVEL_WARNING, "syncing trend data done");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	DCadd_update_inventory_sql(size_t *sql_offset, const zbx_vector_inventory_value_ptr_t *inventory_values)
{
	char	*value_esc;
	int	i;

	for (i = 0; i < inventory_values->values_num; i++)
	{
		const zbx_inventory_value_t	*inventory_value = inventory_values->values[i];

		value_esc = zbx_db_dyn_escape_field("host_inventory", inventory_value->field_name,
				inventory_value->value);

		zbx_snprintf_alloc(&sql, &sql_alloc, sql_offset,
				"update host_inventory set %s='%s' where hostid=" ZBX_FS_UI64 ";\n",
				inventory_value->field_name, value_esc, inventory_value->hostid);

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, sql_offset);

		zbx_free(value_esc);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store str/text/log/bin value         *
 *                                                                            *
 * Parameters: history     - [IN] history data                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_history_clean_value(zbx_dc_history_t *history)
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
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_free(history->value.str);
			break;
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			break;
		case ITEM_VALUE_TYPE_NONE:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store str/text/log/bin values        *
 *                                                                            *
 * Parameters: history     - [IN] history data                                *
 *             history_num - [IN] number of values in history data            *
 *                                                                            *
 ******************************************************************************/
void	zbx_hc_free_item_values(zbx_dc_history_t *history, int history_num)
{
	int	i;

	for (i = 0; i < history_num; i++)
		zbx_dc_history_clean_value(&history[i]);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update item data and inventory in database                        *
 *                                                                            *
 * Parameters: item_diff        - item changes                                *
 *             inventory_values - inventory values                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_mass_update_items(const zbx_vector_item_diff_ptr_t *item_diff,
		const zbx_vector_inventory_value_ptr_t *inventory_values)
{
	size_t	sql_offset = 0;
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < item_diff->values_num; i++)
	{
		zbx_item_diff_t	*diff;

		diff = item_diff->values[i];
		if (0 != (ZBX_FLAGS_ITEM_DIFF_UPDATE_DB & diff->flags))
			break;
	}

	if (i != item_diff->values_num || 0 != inventory_values->values_num)
	{
		if (i != item_diff->values_num)
		{
			zbx_db_save_item_changes(&sql, &sql_alloc, &sql_offset, item_diff,
					ZBX_FLAGS_ITEM_DIFF_UPDATE_DB);
		}

		if (0 != inventory_values->values_num)
			DCadd_update_inventory_sql(&sql_offset, inventory_values);

		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

		zbx_dc_config_update_inventory_values(inventory_values);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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
static void	sync_history_cache_full(const zbx_events_funcs_t *events_cbs, int config_history_storage_pipelines)
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

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
	{
		/* unlock all triggers before full sync so no items are locked by triggers */
		zbx_dc_config_unlock_all_triggers();
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

	if (0 != zbx_hc_queue_get_size())
	{
		zabbix_log(LOG_LEVEL_WARNING, "syncing history data...");

		do
		{
			sync_history_cb(&values_num, &triggers_num, events_cbs, NULL, config_history_storage_pipelines,
					&more);

			zabbix_log(LOG_LEVEL_WARNING, "syncing history data... " ZBX_FS_DBL "%%",
					(double)values_num / (cache->history_num + values_num) * 100);
		}
		while (0 != zbx_hc_queue_get_size());

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

/***************************************************************************************
 *                                                                                     *
 * Purpose: writes updates and new data from history cache to database                 *
 *                                                                                     *
 * Parameters:                                                                         *
 *   events_cbs                       - [IN]                                           *
 *   rtc                              - [IN] RTC socket                                *
 *   config_history_storage_pipelines - [IN]                                           *
 *   values_num                       - [OUT] number of synced values                  *
 *   triggers_num                     - [OUT]                                          *
 *   more                             - [OUT] flag indicating cache emptiness:         *
 *                                            ZBX_SYNC_DONE - nothing to sync, go idle *
 *                                            ZBX_SYNC_MORE - more data to sync        *
 *                                                                                     *
 ***************************************************************************************/
void	zbx_sync_history_cache(const zbx_events_funcs_t *events_cbs, zbx_ipc_async_socket_t *rtc,
		int config_history_storage_pipelines, int *values_num, int *triggers_num, int *more)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() history_num:%d", __func__, cache->history_num);

	*values_num = 0;
	*triggers_num = 0;

	sync_history_cb(values_num, triggers_num, events_cbs, rtc, config_history_storage_pipelines, more);
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
		zbx_dc_flush_history();

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
	dc_item_value_t	*item_value = dc_local_get_history_slot();

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

static void	dc_local_add_history_text_bin_helper(unsigned char value_type, zbx_uint64_t itemid,
		unsigned char item_value_type, const zbx_timespec_t *ts, const char *value_orig,
		zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value = dc_local_get_history_slot();

	item_value->itemid = itemid;
	item_value->ts = *ts;
	item_value->item_value_type = item_value_type;
	item_value->value_type = value_type;
	item_value->state = ITEM_STATE_NORMAL;
	item_value->flags = flags;

	if (0 != (item_value->flags & ZBX_DC_FLAG_META))
	{
		item_value->lastlogsize = lastlogsize;
		item_value->mtime = mtime;
	}

	if (0 == (item_value->flags & ZBX_DC_FLAG_NOVALUE))
	{
		size_t	maxlen = (ITEM_VALUE_TYPE_BIN == item_value_type ? ZBX_HISTORY_BIN_VALUE_LEN :
				ZBX_HISTORY_VALUE_LEN);

		item_value->value.value_str.len = zbx_db_strlen_n(value_orig, maxlen) + 1;
		dc_string_buffer_realloc(item_value->value.value_str.len);

		item_value->value.value_str.pvalue = string_values_offset;
		memcpy(&string_values[string_values_offset], value_orig, item_value->value.value_str.len);
		string_values_offset += item_value->value.value_str.len;
	}
	else
		item_value->value.value_str.len = 0;
}

static void	dc_local_add_history_text(zbx_uint64_t itemid, unsigned char item_value_type, const zbx_timespec_t *ts,
		const char *value_orig, zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_local_add_history_text_bin_helper(ITEM_VALUE_TYPE_TEXT, itemid, item_value_type, ts, value_orig,
			lastlogsize, mtime, flags);
}

static void	dc_local_add_history_bin(zbx_uint64_t itemid, unsigned char item_value_type, const zbx_timespec_t *ts,
		const char *value_orig, zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_local_add_history_text_bin_helper(ITEM_VALUE_TYPE_BIN, itemid, item_value_type, ts, value_orig,
			lastlogsize, mtime, flags);
}

static void	dc_local_add_history_log(zbx_uint64_t itemid, unsigned char item_value_type, const zbx_timespec_t *ts,
		const zbx_log_t *log, zbx_uint64_t lastlogsize, int mtime, unsigned char flags)
{
	dc_item_value_t	*item_value = dc_local_get_history_slot();

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
			item_value->source.len = zbx_db_strlen_n(log->source, ZBX_HISTORY_LOG_SOURCE_LEN) + 1;
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
	item_value->item_value_type = ITEM_VALUE_TYPE_NONE;
	item_value->value_type = ITEM_VALUE_TYPE_NONE;
	item_value->state = ITEM_STATE_NOTSUPPORTED;
	item_value->flags = flags;

	if (0 != (item_value->flags & ZBX_DC_FLAG_META))
	{
		item_value->lastlogsize = lastlogsize;
		item_value->mtime = mtime;
	}

	item_value->value.value_str.len = zbx_db_strlen_n(error, ZBX_ITEM_ERROR_LEN) + 1;
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
	item_value->item_value_type = ITEM_VALUE_TYPE_NONE;
	item_value->value_type = ITEM_VALUE_TYPE_NONE;
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
void	zbx_dc_add_history(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, const zbx_timespec_t *ts, unsigned char state, const char *error)
{
	unsigned char	value_flags;

	if (ITEM_STATE_NOTSUPPORTED == state)
	{
		zbx_uint64_t	lastlogsize;
		int		mtime;

		if (NULL != result && 0 != ZBX_ISSET_META(result))
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

	value_flags = 0;

	if (!ZBX_ISSET_VALUE(result))
		value_flags |= ZBX_DC_FLAG_NOVALUE;

	if (ZBX_ISSET_META(result))
		value_flags |= ZBX_DC_FLAG_META;

	/* Add data to the local history cache if:                                           */
	/*   1) the NOVALUE flag is set                                                      */
	/*   2) the NOVALUE flag is not set and value conversion succeeded                   */

	if (0 == (value_flags & ZBX_DC_FLAG_NOVALUE))
	{
		if (0 != (ZBX_FLAG_DISCOVERY_RULE & item_flags))
		{
			if (NULL == ZBX_GET_TEXT_RESULT(result))
				return;

			/* proxy stores low-level discovery (lld) values in db */
			if (0 == (ZBX_PROGRAM_TYPE_SERVER & get_program_type_cb()))
				dc_local_add_history_lld(itemid, ts, result->text);

			return;
		}

		if (ZBX_ISSET_LOG(result))
		{
			dc_local_add_history_log(itemid, item_value_type, ts, result->log, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ZBX_ISSET_UI64(result))
		{
			dc_local_add_history_uint(itemid, item_value_type, ts, result->ui64, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ZBX_ISSET_DBL(result))
		{
			dc_local_add_history_dbl(itemid, item_value_type, ts, result->dbl, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ZBX_ISSET_STR(result))
		{
			dc_local_add_history_text(itemid, item_value_type, ts, result->str, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ZBX_ISSET_TEXT(result))
		{
			dc_local_add_history_text(itemid, item_value_type, ts, result->text, result->lastlogsize,
					result->mtime, value_flags);
		}
		else if (ZBX_ISSET_BIN(result))
		{
			dc_local_add_history_bin(itemid, item_value_type, ts, result->bin, result->lastlogsize,
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

/******************************************************************************
 *                                                                            *
 * Purpose: add new variant value to the cache                                *
 *                                                                            *
 * Parameters:  itemid          - [IN]                                        *
 *              value_type      - [IN] item value type                        *
 *              item_flags      - [IN] item flags (e. g. lld rule)            *
 *              value           - [IN] agent result containing value to add   *
 *              ts              - [IN] value timestamp                        *
 *              value_opt       - [IN]                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_add_history_variant(zbx_uint64_t itemid, unsigned char value_type, unsigned char item_flags,
		zbx_variant_t *value, zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt)
{
	unsigned char	value_flags = 0;
	zbx_uint64_t	lastlogsize;
	int		mtime;

	if (0 != (value_opt->flags & ZBX_PP_VALUE_OPT_META))
	{
		value_flags = ZBX_DC_FLAG_META;
		lastlogsize = value_opt->lastlogsize;
		mtime = value_opt->mtime;

		value_flags |= ZBX_DC_FLAG_META;
	}
	else
	{
		value_flags = 0;
		lastlogsize = 0;
		mtime = 0;
	}

	if (ZBX_VARIANT_ERR == value->type)
	{
		dc_local_add_history_notsupported(itemid, &ts, value->data.err, lastlogsize, mtime, value_flags);

		return;
	}

	if (ZBX_VARIANT_NONE == value->type)
		value_flags |= ZBX_DC_FLAG_NOVALUE;

	/* Add data to the local history cache if:                                           */
	/*   1) the NOVALUE flag is set (data contains either meta information or timestamp) */
	/*   2) the NOVALUE flag is not set and value conversion succeeded                   */

	if (0 != (value_flags & ZBX_DC_FLAG_NOVALUE))
	{
		if (0 != (value_flags & ZBX_DC_FLAG_META))
			dc_local_add_history_log(itemid, value_type, &ts, NULL, lastlogsize, mtime, value_flags);
		else
			dc_local_add_history_empty(itemid, value_type, &ts, value_flags);

		return;
	}

	if (0 != (ZBX_FLAG_DISCOVERY_RULE & item_flags))
	{
		if (ZBX_VARIANT_STR != value->type)
			return;

		/* proxy stores low-level discovery (lld) values in db */
		if (0 == (ZBX_PROGRAM_TYPE_SERVER & get_program_type_cb()))
			dc_local_add_history_lld(itemid, &ts, value->data.str);

		return;
	}

	if (0 != (value_opt->flags & ZBX_PP_VALUE_OPT_LOG))
	{
		zbx_log_t	log;

		if (FAIL == zbx_variant_convert(value, ZBX_VARIANT_STR))
		{
			char	*error;

			zabbix_log(LOG_LEVEL_CRIT, "cannot convert value from %s to %s",
					zbx_variant_type_desc(value), zbx_get_variant_type_desc(ZBX_VARIANT_STR));
			THIS_SHOULD_NEVER_HAPPEN;

			error = zbx_dsprintf(NULL, "Cannot convert value from %s to %s.",
					zbx_variant_type_desc(value), zbx_get_variant_type_desc(ZBX_VARIANT_STR));
			dc_local_add_history_notsupported(itemid, &ts, error, lastlogsize, mtime,
					value_flags);
			zbx_free(error);

			return;
		}

		log.logeventid = value_opt->logeventid;
		log.severity = value_opt->severity;
		log.timestamp = value_opt->timestamp;
		log.source = value_opt->source;
		log.value = value->data.str;

		dc_local_add_history_log(itemid, value_type, &ts, &log, lastlogsize, mtime, value_flags);

		return;
	}

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			dc_local_add_history_uint(itemid, value_type, &ts, value->data.ui64, lastlogsize, mtime,
					value_flags);
			break;
		case ZBX_VARIANT_DBL:
			dc_local_add_history_dbl(itemid, value_type, &ts, value->data.dbl, lastlogsize, mtime,
					value_flags);
			break;
		case ZBX_VARIANT_STR:
			if (ITEM_VALUE_TYPE_BIN == value_type && FAIL == zbx_base64_validate(value->data.str))
			{
				dc_local_add_history_notsupported(itemid, &ts,
						"Binary type requires Base64 encoded string. ", lastlogsize, mtime,
						value_flags);
				return;
			}

			dc_local_add_history_text(itemid, value_type, &ts, value->data.str, lastlogsize, mtime,
					value_flags);
			break;
		case ZBX_VARIANT_NONE:
		case ZBX_VARIANT_BIN:
		case ZBX_VARIANT_VECTOR:
		case ZBX_VARIANT_ERR:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}
}

size_t	zbx_dc_flush_history(void)
{
	int	processing_num;

	if (0 == item_values_num)
		return 0;

	LOCK_CACHE;

	hc_add_item_values(item_values, item_values_num);

	cache->history_num += item_values_num;
	processing_num = cache->processing_num;

	UNLOCK_CACHE;

	zbx_vps_monitor_add_collected((zbx_uint64_t)item_values_num);

	size_t	count = item_values_num;

	item_values_num = 0;
	string_values_offset = 0;

	if (0 != processing_num)
		return 0;

	return count;
}

/******************************************************************************
 *                                                                            *
 * history cache storage                                                      *
 *                                                                            *
 ******************************************************************************/
ZBX_SHMEM_FUNC_IMPL(__hc_index, hc_index_mem)
ZBX_SHMEM_FUNC_IMPL(__hc, hc_mem)

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
		__hc_shmem_free_func(data->value.str);
	}
	else
	{
		if (0 == (data->flags & ZBX_DC_FLAG_NOVALUE))
		{
			switch (data->value_type)
			{
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
				case ITEM_VALUE_TYPE_BIN:
					__hc_shmem_free_func(data->value.str);
					break;
				case ITEM_VALUE_TYPE_LOG:
					__hc_shmem_free_func(data->value.log->value);

					if (NULL != data->value.log->source)
						__hc_shmem_free_func(data->value.log->source);

					__hc_shmem_free_func(data->value.log);
					break;
				case ITEM_VALUE_TYPE_UINT64:
				case ITEM_VALUE_TYPE_FLOAT:
					break;
				case ITEM_VALUE_TYPE_NONE:
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					exit(EXIT_FAILURE);
			}
		}
	}

	__hc_shmem_free_func(data);
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
	zbx_binary_heap_elem_t	elem = {item->itemid, (void *)item};

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

	if (NULL == (ptr = (char *)__hc_shmem_malloc_func(NULL, str->len)))
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
		if (NULL == (*dst = (zbx_log_value_t *)__hc_shmem_realloc_func(NULL, sizeof(zbx_log_value_t))))
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
		if (NULL == (*data = (zbx_hc_data_t *)__hc_shmem_malloc_func(NULL, sizeof(zbx_hc_data_t))))
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
			case ITEM_VALUE_TYPE_TEXT:
			case ITEM_VALUE_TYPE_BIN:
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
			case ITEM_VALUE_TYPE_NONE:
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
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
			case ITEM_VALUE_TYPE_BIN:
				cache->stats.history_bin_counter++;
				break;
			case ITEM_VALUE_TYPE_NONE:
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
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
		if (NULL != (item = hc_get_item(item_value->itemid)) && 0 != (item_value->flags & ZBX_DC_FLAG_NOVALUE))
		{
			/* skip metadata updates when only one value is queued, */
			/* because the item might be already being processed    */
			if (item->head != item->tail)
			{
				if (0 != (item_value->flags & ZBX_DC_FLAG_META))
				{
					item->head->lastlogsize = item_value->lastlogsize;
					item->head->mtime = item_value->mtime;
					item->head->flags |= ZBX_DC_FLAG_META;
				}

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
static void	hc_copy_history_data(zbx_dc_history_t *history, zbx_uint64_t itemid, zbx_hc_data_t *data)
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
			case ITEM_VALUE_TYPE_BIN:
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
			case ITEM_VALUE_TYPE_NONE:
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
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
 *           zbx_hc_push_items() function after they have been processed.         *
 *                                                                            *
 ******************************************************************************/
void	zbx_hc_pop_items(zbx_vector_hc_item_ptr_t *history_items)
{
	zbx_binary_heap_elem_t	*elem;
	zbx_hc_item_t		*item;

	while (ZBX_HC_SYNC_MAX > history_items->values_num && FAIL == zbx_binary_heap_empty(&cache->history_queue))
	{
		elem = zbx_binary_heap_find_min(&cache->history_queue);
		item = elem->data;
		zbx_vector_hc_item_ptr_append(history_items, item);

		zbx_binary_heap_remove_min(&cache->history_queue);
	}

	if (0 != history_items->values_num)
		cache->processing_num++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets item history values                                          *
 *                                                                            *
 * Parameters: history       - [OUT] the history values                       *
 *             history_items - [IN] the history items                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_hc_get_item_values(zbx_dc_history_t *history, zbx_vector_hc_item_ptr_t *history_items)
{
	int		i, history_num = 0;
	zbx_hc_item_t	*item;

	/* we don't need to lock history cache because no other processes can  */
	/* change item's history data until it is pushed back to history queue */
	for (i = 0; i < history_items->values_num; i++)
	{
		item = history_items->values[i];

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
void	zbx_hc_push_items(zbx_vector_hc_item_ptr_t *history_items)
{
	int		i;
	zbx_hc_item_t	*item;
	zbx_hc_data_t	*data_free;

	for (i = 0; i < history_items->values_num; i++)
	{
		item = history_items->values[i];

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

	cache->processing_num--;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve the size of history queue                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_hc_queue_get_size(void)
{
	return cache->history_queue.elems_num;
}

int	zbx_hc_get_history_compression_age(void)
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
 * Purpose: calculate usage percentage of hc memory buffer                    *
 *                                                                            *
 ******************************************************************************/
double	zbx_hc_mem_pused(void)
{
	return 100 * (double)(zbx_dbcache_get_hc_mem()->total_size - zbx_dbcache_get_hc_mem()->free_size) /
			zbx_dbcache_get_hc_mem()->total_size;
}

double	zbx_hc_mem_pused_lock(void)
{
	double	pused;

	zbx_dbcache_lock();

	pused = zbx_hc_mem_pused();

	zbx_dbcache_unlock();

	return pused;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Allocate shared memory for trend cache (part of database cache)   *
 *                                                                            *
 * Comments: Is optionally called from zbx_init_database_cache()              *
 *                                                                            *
 ******************************************************************************/

ZBX_SHMEM_FUNC_IMPL(__trend, trend_mem)

static int	init_trend_cache(zbx_uint64_t *trends_cache_size, char **error)
{
	size_t	sz;
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (ret = zbx_mutex_create(&trends_lock, ZBX_MUTEX_TRENDS, error)))
		goto out;

	sz = zbx_shmem_required_size(1, "trend cache", "TrendCacheSize");
	if (SUCCEED != (ret = zbx_shmem_create(&trend_mem, *trends_cache_size, "trend cache", "TrendCacheSize", 0,
			error)))
	{
		goto out;
	}

	*trends_cache_size -= sz;

	cache->trends_num = 0;
	cache->trends_last_cleanup_hour = 0;

#define INIT_HASHSET_SIZE	100	/* Should be calculated dynamically based on trends size? */
					/* Still does not make sense to have it more than initial */
					/* item hashset size in configuration cache.              */

	zbx_hashset_create_ext(&cache->trends, INIT_HASHSET_SIZE,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL,
			__trend_shmem_malloc_func, __trend_shmem_realloc_func, __trend_shmem_free_func);

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
int	zbx_init_database_cache(zbx_get_program_type_f get_program_type, zbx_history_sync_f sync_history,
		zbx_uint64_t history_cache_size, zbx_uint64_t history_index_cache_size,zbx_uint64_t *trends_cache_size,
		char **error)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	get_program_type_cb = get_program_type;
	sync_history_cb = sync_history;

	if (NULL != cache)
	{
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED != (ret = zbx_mutex_create(&cache_lock, ZBX_MUTEX_CACHE, error)))
		goto out;

	if (SUCCEED != (ret = zbx_mutex_create(&cache_ids_lock, ZBX_MUTEX_CACHE_IDS, error)))
		goto out;

	if (SUCCEED != (ret = zbx_shmem_create(&hc_mem, history_cache_size, "history cache",
			"HistoryCacheSize", 1, error)))
	{
		goto out;
	}

	if (SUCCEED != (ret = zbx_shmem_create(&hc_index_mem, history_index_cache_size, "history index cache",
			"HistoryIndexCacheSize", 0, error)))
	{
		goto out;
	}

	cache = (ZBX_DC_CACHE *)__hc_index_shmem_malloc_func(NULL, sizeof(ZBX_DC_CACHE));
	memset(cache, 0, sizeof(ZBX_DC_CACHE));

	ids = (ZBX_DC_IDS *)__hc_index_shmem_malloc_func(NULL, sizeof(ZBX_DC_IDS));
	memset(ids, 0, sizeof(ZBX_DC_IDS));

	zbx_hashset_create_ext(&cache->history_items, ZBX_HC_ITEMS_INIT_SIZE,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL,
			__hc_index_shmem_malloc_func, __hc_index_shmem_realloc_func, __hc_index_shmem_free_func);

	zbx_binary_heap_create_ext(&cache->history_queue, hc_queue_elem_compare_func, ZBX_BINARY_HEAP_OPTION_EMPTY,
			__hc_index_shmem_malloc_func, __hc_index_shmem_realloc_func, __hc_index_shmem_free_func);

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
	{
		zbx_hashset_create_ext(&(cache->proxyqueue.index), ZBX_HC_SYNC_MAX,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL,
			__hc_index_shmem_malloc_func, __hc_index_shmem_realloc_func, __hc_index_shmem_free_func);

		zbx_list_create_ext(&(cache->proxyqueue.list), __hc_index_shmem_malloc_func,
				__hc_index_shmem_free_func);

		cache->proxyqueue.state = ZBX_HC_PROXYQUEUE_STATE_NORMAL;

		if (SUCCEED != (ret = init_trend_cache(trends_cache_size, error)))
			goto out;
	}

	cache->processing_num = 0;
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
static void	DCsync_all(const zbx_events_funcs_t *events_cbs, int config_history_storage_pipelines)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In DCsync_all()");

	sync_history_cache_full(events_cbs, config_history_storage_pipelines);

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
		DCsync_trends();

	zabbix_log(LOG_LEVEL_DEBUG, "End of DCsync_all()");
}

/******************************************************************************
 *                                                                            *
 * Purpose: Free memory allocated for database cache                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_free_database_cache(int sync, const zbx_events_funcs_t *events_cbs, int config_history_storage_pipelines)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_SYNC_ALL == sync)
		DCsync_all(events_cbs, config_history_storage_pipelines);

	cache = NULL;

	zbx_shmem_destroy(hc_mem);
	hc_mem = NULL;
	zbx_shmem_destroy(hc_index_mem);
	hc_index_mem = NULL;

	zbx_mutex_destroy(&cache_lock);
	zbx_mutex_destroy(&cache_ids_lock);

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
	{
		zbx_shmem_destroy(trend_mem);
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
zbx_uint64_t	zbx_dc_get_nextid(const char *table_name, int num)
{
	int			i;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	const zbx_db_table_t	*table;
	ZBX_DC_ID		*id;
	zbx_uint64_t		min = 0, max = ZBX_DB_MAX_ID, nextid, lastid;

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

	table = zbx_db_get_table(table_name);

	result = zbx_db_select("select max(%s) from %s where %s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
			table->recid, table_name, table->recid, min, max);

	if (NULL != result)
	{
		zbx_strlcpy(id->table_name, table_name, sizeof(id->table_name));

		if (NULL == (row = zbx_db_fetch(result)) || SUCCEED == zbx_db_is_null(row[0]))
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

	zbx_db_free_result(result);

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
void	zbx_dc_update_interfaces_availability(void)
{
	zbx_vector_availability_ptr_t		interfaces;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_availability_ptr_create(&interfaces);

	if (SUCCEED != zbx_dc_reset_interfaces_availability(&interfaces))
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
void	zbx_hc_get_mem_stats(zbx_shmem_stats_t *data, zbx_shmem_stats_t *index)
{
	LOCK_CACHE;

	if (NULL != data)
		zbx_shmem_get_stats(hc_mem, data);

	if (NULL != index)
		zbx_shmem_get_stats(hc_index_mem, index);

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
zbx_uint64_t	zbx_hc_proxyqueue_peek(void)
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
void	zbx_hc_proxyqueue_enqueue(zbx_uint64_t proxyid)
{
	if (NULL == zbx_hashset_search(&cache->proxyqueue.index, &proxyid))
	{
		zbx_uint64_t *ptr;

		ptr = zbx_hashset_insert(&cache->proxyqueue.index, &proxyid, sizeof(proxyid));
		(void)zbx_list_append(&cache->proxyqueue.list, ptr, NULL);
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
int	zbx_hc_proxyqueue_dequeue(zbx_uint64_t proxyid)
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
void	zbx_hc_proxyqueue_clear(void)
{
	zbx_list_destroy(&cache->proxyqueue.list);
	zbx_hashset_clear(&cache->proxyqueue.index);
}

void	zbx_dbcache_lock(void)
{
	LOCK_CACHE;
}

void	zbx_dbcache_unlock(void)
{
	UNLOCK_CACHE;
}

void	zbx_dbcache_set_history_num(int num)
{
	cache->history_num = num;
}

int	zbx_dbcache_get_history_num(void)
{
	return cache->history_num;
}

zbx_shmem_info_t	*zbx_dbcache_get_hc_mem(void)
{
	return hc_mem;
}

void	zbx_dbcache_setproxyqueue_state(int proxyqueue_state)
{
	cache->proxyqueue.state = proxyqueue_state;
}

int	zbx_dbcache_getproxyqueue_state(void)
{
	return cache->proxyqueue.state;
}
