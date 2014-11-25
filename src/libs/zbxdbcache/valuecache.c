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
#include "memalloc.h"
#include "ipc.h"
#include "dbcache.h"

#include "vectorimpl.h"

#include "valuecache.h"

/*
 * The cache (zbx_vc_cache_t) is organized as a hashset of item records (zbx_vc_item_t).
 *
 * Each record holds item data (itemid, value_type), statistics (hits, last access time,...)
 * and the historical data (timestamp,value pairs in ascending order).
 *
 * The historical data are stored from largest request (+timeshift) range to the
 * current time. The data is automatically fetched from DB whenever a request
 * exceeds cached value range.
 *
 * In addition to active range value cache tracks item range for last 24 hours. Once
 * per day the active range is updated with daily range and the daily range is reset.
 *
 * If an item is already being cached the new values are automatically added to the cache
 * after being written into database.
 *
 * When cache runs out of memory to store new items it enters in low memory mode.
 * In low memory mode cache continues to function as before with few restrictions:
 *   1) items that weren't accessed during the last day are removed from cache.
 *   2) items with worst hits/values ratio might be removed from cache to free the space.
 *   3) no new items are added to the cache.
 *
 * The low memory mode can't be turned off - it will persist until server is rebooted.
 * In low memory mode a warning message is written into log every 5 minutes.
 */

/* the period of low memory warning messages */
#define ZBX_VC_LOW_MEMORY_WARNING_PERIOD	(5 * SEC_PER_MIN)

/* Redefine the time function so unit tests can simulate time changes. */
/* It should not be changed during normal processing.                  */
static time_t (*vc_time)(time_t *) = time;
#define ZBX_VC_TIME()	vc_time(NULL)

static zbx_mem_info_t	*vc_mem = NULL;

static ZBX_MUTEX	vc_lock;

/* flag indicating that the cache was explicitly locked by this process */
static int	vc_locked = 0;

/* the value cache size */
extern zbx_uint64_t	CONFIG_VALUE_CACHE_SIZE;

ZBX_MEM_FUNC_IMPL(__vc, vc_mem)

#define VC_STRPOOL_INIT_SIZE	(1000)
#define VC_ITEMS_INIT_SIZE	(1000)

#define VC_MAX_NANOSECONDS	999999999

#define VC_MIN_RANGE			SEC_PER_MIN

/* the range synchronization period in hours */
#define ZBX_VC_RANGE_SYNC_PERIOD	24

/* the data chunk used to store data fragment */
typedef struct _zbx_vc_chunk_t
{
	/* a pointer to the previous chunk or NULL if this is the tail chunk */
	struct _zbx_vc_chunk_t	*prev;

	/* a pointer to the next chunk or NULL if this is the head chunk */
	struct _zbx_vc_chunk_t	*next;

	/* the index of first (oldest) value in chunk */
	int			first_value;

	/* the index of last (newest) value in chunk */
	int			last_value;

	/* the number of item value slots in chunk */
	int			slots_num;

	/* the item value data */
	zbx_history_record_t	slots[1];
}
zbx_vc_chunk_t;

/* min/max number number of item history values to store in chunk */

#define ZBX_VC_MIN_CHUNK_RECORDS	2

/* the maximum number is calculated so that the chunk size does not exceed 64KB */
#define ZBX_VC_MAX_CHUNK_RECORDS	((64 * ZBX_KIBIBYTE - sizeof(zbx_vc_chunk_t)) / \
		sizeof(zbx_history_record_t) + 1)

/* the item operational state flags */
#define ZBX_ITEM_STATE_CLEAN_PENDING	1
#define ZBX_ITEM_STATE_REMOVE_PENDING	2

/* indicates that all values from database are cached */
#define ZBX_ITEM_STATUS_CACHED_ALL	1

/* the value cache item data */
typedef struct
{
	/* the item id */
	zbx_uint64_t	itemid;

	/* the item value type */
	unsigned char	value_type;

	/* the item operational state flags (ZBX_ITEM_STATE_*)        */
	unsigned char	state;

	/* the item status flags (ZBX_ITEM_STATUS_*)                  */
	unsigned char	status;

	/* the hour when the current/global range sync was done       */
	unsigned char	range_sync_hour;

	/* The total number of item values in cache.                  */
	/* Used to evaluate if the item must be dropped from cache    */
	/* in low memory situation.                                   */
	int		values_total;

	/* The last time when item cache was accessed.                */
	/* Used to evaluate if the item must be dropped from cache    */
	/* in low memory situation.                                   */
	int		last_accessed;

	/* reference counter indicating number of processes           */
	/* accessing item                                             */
	int		refcount;

	/* The range of the largest request in seconds.               */
	/* Used to determine if data can be removed from cache.       */
	int		active_range;

	/* The range for last 24 hours since active_range update.     */
	/* Once per day the active_range is synchronized (updated)    */
	/* with daily_range and the daily range is reset.             */
	int		daily_range;

	/* The number of cache hits for this item.                    */
	/* Used to evaluate if the item must be dropped from cache    */
	/* in low memory situation.                                   */
	zbx_uint64_t	hits;

	/* the last (newest) chunk of item history data               */
	zbx_vc_chunk_t	*head;

	/* the first (oldest) chunk of item history data              */
	zbx_vc_chunk_t	*tail;
}
zbx_vc_item_t;

/* the value cache data  */
typedef struct
{
	/* the number of cache hits, used for statistics */
	zbx_uint64_t	hits;

	/* the number of cache misses, used for statistics */
	zbx_uint64_t	misses;

	/* the number of database queries performed, used only for unit tests */
	zbx_uint64_t	db_queries;

	/* the low memory mode flag */
	int		low_memory;

	/* timestamp of the last low memory warning message */
	int		last_warning_time;

	/* the minimum number of bytes to be freed when cache runs out of space */
	size_t		min_free_request;

	/* the cached items */
	zbx_hashset_t	items;

	/* the string pool for str, text and log item values */
	zbx_hashset_t	strpool;
}
zbx_vc_cache_t;

/* the item weight data, used to determine if item can be removed from cache */
typedef struct
{
	/* a pointer to the value cache item */
	zbx_vc_item_t	*item;

	/* the item 'weight' - <number of hits> / <number of cache records> */
	double		weight;
}
zbx_vc_item_weight_t;

ZBX_VECTOR_IMPL(history_record, zbx_history_record_t);

ZBX_VECTOR_DECL(vc_itemweight, zbx_vc_item_weight_t);
ZBX_VECTOR_IMPL(vc_itemweight, zbx_vc_item_weight_t);

/* the value cache */
static zbx_vc_cache_t	*vc_cache = NULL;

/* function prototypes */
static void	vc_history_record_copy(zbx_history_record_t *dst, const zbx_history_record_t *src, int value_type);
static int	vc_history_record_compare_asc_func(const zbx_history_record_t *d1, const zbx_history_record_t *d2);
static int	vc_history_record_compare_desc_func(const zbx_history_record_t *d1, const zbx_history_record_t *d2);
static void	vc_history_record_vector_clean(zbx_vector_history_record_t *vector, int value_type);

static size_t	vch_item_free_cache(zbx_vc_item_t *item);
static size_t	vch_item_free_chunk(zbx_vc_item_t *item, zbx_vc_chunk_t *chunk);
static int	vch_item_add_values_at_tail(zbx_vc_item_t *item, const zbx_history_record_t *values, int values_num);
static void	vch_item_clean_cache(zbx_vc_item_t *item);

/******************************************************************************************************************
 *                                                                                                                *
 * Database access API                                                                                            *
 *                                                                                                                *
 ******************************************************************************************************************/

typedef void (*vc_str2value_func_t)(history_value_t *value, DB_ROW row);

/* history table data */
typedef struct
{
	/* table name */
	const char		*name;

	/* field list */
	const char		*fields;

	/* string to value converter function, used to convert string value of DB row */
	/* to the value of appropriate type                                           */
	vc_str2value_func_t	rtov;
}
zbx_vc_history_table_t;

/* row to value converters for all value types */
static void	row2value_str(history_value_t *value, DB_ROW row)
{
	value->str = zbx_strdup(NULL, row[0]);
}

static void	row2value_dbl(history_value_t *value, DB_ROW row)
{
	value->dbl = atof(row[0]);
}

static void	row2value_ui64(history_value_t *value, DB_ROW row)
{
	ZBX_STR2UINT64(value->ui64, row[0]);
}

/* timestamp, logeventid, severity, source, value */
static void	row2value_log(history_value_t *value, DB_ROW row)
{
	value->log = zbx_malloc(NULL, sizeof(zbx_log_value_t));

	value->log->timestamp = atoi(row[0]);
	value->log->logeventid = atoi(row[1]);
	value->log->severity = atoi(row[2]);
	value->log->source = '\0' == *row[3] ? NULL : zbx_strdup(NULL, row[3]);
	value->log->value = zbx_strdup(NULL, row[4]);
}

/* value_type - history table data mapping */
static zbx_vc_history_table_t	vc_history_tables[] = {
	{"history", "value", row2value_dbl},
	{"history_str", "value", row2value_str},
	{"history_log", "timestamp,logeventid,severity,source,value", row2value_log},
	{"history_uint", "value", row2value_ui64},
	{"history_text", "value", row2value_str}
};

/******************************************************************************
 *                                                                            *
 * Function: vc_try_lock                                                      *
 *                                                                            *
 * Purpose: locks the cache unless it was explicitly locked externally with   *
 *          zbx_vc_lock() call.                                               *
 *                                                                            *
 ******************************************************************************/
static void	vc_try_lock(void)
{
	if (NULL != vc_cache && 0 == vc_locked)
		zbx_mutex_lock(&vc_lock);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_try_unlock                                                    *
 *                                                                            *
 * Purpose: unlocks the cache locked by vc_try_lock() function unless it was  *
 *          explicitly locked externally with zbx_vc_lock() call.             *
 *                                                                            *
 ******************************************************************************/
static void	vc_try_unlock(void)
{
	if (NULL != vc_cache && 0 == vc_locked)
		zbx_mutex_unlock(&vc_lock);
}

/*********************************************************************************
 *                                                                               *
 * Function: vc_db_read_values_by_time                                           *
 *                                                                               *
 * Purpose: reads item history data from database                                *
 *                                                                               *
 * Parameters:  itemid        - [IN] the itemid                                  *
 *              value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs) *
 *              values        - [OUT] the item history data values               *
 *              seconds       - [IN] the time period to read                     *
 *              end_timestamp - [IN] the value timestamp to start reading with   *
 *              queries       - [IN/OUT] the database queries counter            *
 *                                                                               *
 * Return value: SUCCEED - the history data were read successfully               *
 *               FAIL - otherwise                                                *
 *                                                                               *
 * Comments: This function reads all values with timestamps in range:            *
 *             end_timestamp - seconds < <value timestamp> <= end_timestamp      *
 *                                                                               *
 *********************************************************************************/
static int	vc_db_read_values_by_time(zbx_uint64_t itemid, int value_type, zbx_vector_history_record_t *values,
		int seconds, int end_timestamp, zbx_uint64_t *queries)
{
	char			*sql = NULL;
	size_t	 		sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];
	int			ret = FAIL;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select clock,ns,%s"
			" from %s"
			" where itemid=" ZBX_FS_UI64,
			table->fields, table->name, itemid);

	if (1 == seconds)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock=%d", end_timestamp);
	}
	else
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>%d and clock<=%d",
				end_timestamp - seconds, end_timestamp);
	}

	(*queries)++;
	result = DBselect("%s", sql);

	zbx_free(sql);

	if (NULL == result)
		goto out;

	while (NULL != (row = DBfetch(result)))
	{
		zbx_history_record_t	value;

		value.timestamp.sec = atoi(row[0]);
		value.timestamp.ns = atoi(row[1]);
		table->rtov(&value.value, row + 2);

		zbx_vector_history_record_append_ptr(values, &value);
	}
	DBfree_result(result);

	ret = SUCCEED;
out:
	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Function: vc_db_read_values_by_count                                             *
 *                                                                                  *
 * Purpose: reads item history data from database                                   *
 *                                                                                  *
 * Parameters:  itemid        - [IN] the itemid                                     *
 *              value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs)    *
 *              values        - [OUT] the item history data values                  *
 *              count         - [IN] the number of values to read                   *
 *              end_timestamp - [IN] the value timestamp to start reading with      *
 *              queries       - [IN/OUT] the database queries counter               *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: this function reads <count> values before <count_timestamp> (including)*
 *           plus all values in range:                                              *
 *             count_timestamp < <value timestamp> <= read_timestamp                *
 *                                                                                  *
 *           To speed up the reading time with huge data loads, data is read by     *
 *           smaller time segments (hours, day, week, month) and the next (larger)  *
 *           time segment is read only if the requested number of values (<count>)  *
 *           is not yet retrieved.                                                  *
 *                                                                                  *
 ************************************************************************************/
static int	vc_db_read_values_by_count(zbx_uint64_t itemid, int value_type, zbx_vector_history_record_t *values,
		int count, int end_timestamp, zbx_uint64_t *queries)
{
	char			*sql = NULL;
	size_t	 		sql_alloc = 0, sql_offset;
	int			clock_to, clock_from, step = 0, ret = FAIL;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];
	const int		periods[] = {SEC_PER_HOUR, SEC_PER_DAY, SEC_PER_WEEK, SEC_PER_MONTH, 0, -1};

	clock_to = end_timestamp;

	while (-1 != periods[step] && 0 < count)
	{
		if (0 > (clock_from = clock_to - periods[step]))
		{
			clock_from = clock_to;
			step = 4;
		}

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select clock,ns,%s"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock<=%d",
				table->fields, table->name, itemid, clock_to);

		if (clock_from != clock_to)
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>%d", clock_from);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by clock desc");

		(*queries)++;
		result = DBselectN(sql, count);

		if (NULL == result)
			goto out;

		while (NULL != (row = DBfetch(result)))
		{
			zbx_history_record_t	value;

			value.timestamp.sec = atoi(row[0]);
			value.timestamp.ns = atoi(row[1]);
			table->rtov(&value.value, row + 2);

			zbx_vector_history_record_append_ptr(values, &value);

			count--;
		}
		DBfree_result(result);

		clock_to -= periods[step];
		step++;
	}


	if (0 < count)
	{
		/* no more data in database, return success */
		ret = SUCCEED;
		goto out;
	}

	/* drop data from the last second and read the whole second again  */
	/* to ensure that data is cached by seconds                        */
	end_timestamp = values->values[values->values_num - 1].timestamp.sec;

	while (0 < values->values_num && values->values[values->values_num - 1].timestamp.sec == end_timestamp)
	{
		values->values_num--;
		zbx_history_record_clear(&values->values[values->values_num], value_type);
	}

	ret = vc_db_read_values_by_time(itemid, value_type, values, 1, end_timestamp, queries);
out:
	zbx_free(sql);

	return ret;
}

/*********************************************************************************
 *                                                                               *
 * Function: vc_db_read_value                                                    *
 *                                                                               *
 * Purpose: read the last history value with a timestamp less or equal to the    *
 *          target timestamp from DB                                             *
 *                                                                               *
 * Parameters:  itemid        - [IN] the itemid                                  *
 *              value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs) *
 *              ts            - [IN] the target timestamp                        *
 *              value         - [OUT] the read value                             *
 *              queries       - [IN/OUT] the database queries counter            *
 *                                                                               *
 * Return value: SUCCEED - the history data were read successfully               *
 *               FAIL - otherwise                                                *
 *                                                                               *
 *********************************************************************************/
static int	vc_db_read_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *ts,
		zbx_history_record_t *value, zbx_uint64_t *queries)
{
	int				ret = FAIL, i;
	zbx_vector_history_record_t	values;

	zbx_history_record_vector_create(&values);

	/* first try to find value in the requested second */
	vc_db_read_values_by_time(itemid, value_type, &values, 1, ts->sec, queries);
	zbx_vector_history_record_sort(&values, (zbx_compare_func_t)vc_history_record_compare_desc_func);

	if (0 == values.values_num || 0 > zbx_timespec_compare(ts, &values.values[values.values_num - 1].timestamp))
	{
		/* if the requested second does not contain matching values, */
		/* get the first older value outside the requested second    */
		vc_history_record_vector_clean(&values, value_type);

		vc_db_read_values_by_count(itemid, value_type, &values, 1, ts->sec - 1, queries);
		zbx_vector_history_record_sort(&values, (zbx_compare_func_t)vc_history_record_compare_desc_func);
	}

	for (i = 0; i < values.values_num; i++)
	{
		if (0 <= zbx_timespec_compare(ts, &values.values[i].timestamp))
		{
			vc_history_record_copy(value, &values.values[i], value_type);
			ret = SUCCEED;

			break;
		}
	}

	zbx_history_record_vector_destroy(&values, value_type);

	return ret;
}

/******************************************************************************************************************
 *                                                                                                                *
 * Common API                                                                                                     *
 *                                                                                                                *
 ******************************************************************************************************************/

/******************************************************************************
 *                                                                            *
 * String pool definitions & functions                                        *
 *                                                                            *
 ******************************************************************************/

#define REFCOUNT_FIELD_SIZE	sizeof(uint32_t)

static zbx_hash_t	vc_strpool_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((char *)data + REFCOUNT_FIELD_SIZE);
}

static int	vc_strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp((char *)d1 + REFCOUNT_FIELD_SIZE, (char *)d2 + REFCOUNT_FIELD_SIZE);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_history_record_compare_asc_func                               *
 *                                                                            *
 * Purpose: compares two cache values by their timestamps                     *
 *                                                                            *
 * Parameters: d1   - [IN] the first value                                    *
 *             d2   - [IN] the second value                                   *
 *                                                                            *
 * Return value:   <0 - the first value timestamp is less than second         *
 *                 =0 - the first value timestamp is equal to the second      *
 *                 >0 - the first value timestamp is greater than second      *
 *                                                                            *
 * Comments: This function is commonly used to sort value vector in ascending *
 *           order.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	vc_history_record_compare_asc_func(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	if (d1->timestamp.sec == d2->timestamp.sec)
		return d1->timestamp.ns - d2->timestamp.ns;

	return d1->timestamp.sec - d2->timestamp.sec;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_history_record_compare_desc_func                              *
 *                                                                            *
 * Purpose: compares two cache values by their timestamps                     *
 *                                                                            *
 * Parameters: d1   - [IN] the first value                                    *
 *             d2   - [IN] the second value                                   *
 *                                                                            *
 * Return value:   >0 - the first value timestamp is less than second         *
 *                 =0 - the first value timestamp is equal to the second      *
 *                 <0 - the first value timestamp is greater than second      *
 *                                                                            *
 * Comments: This function is commonly used to sort value vector in descending*
 *           order.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	vc_history_record_compare_desc_func(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	if (d1->timestamp.sec == d2->timestamp.sec)
		return d2->timestamp.ns - d1->timestamp.ns;

	return d2->timestamp.sec - d1->timestamp.sec;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_weight_compare_func                                      *
 *                                                                            *
 * Purpose: compares two item weight data structures by their 'weight'        *
 *                                                                            *
 * Parameters: d1   - [IN] the first item weight data structure               *
 *             d2   - [IN] the second item weight data structure              *
 *                                                                            *
 ******************************************************************************/
static int	vc_item_weight_compare_func(const zbx_vc_item_weight_t *d1, const zbx_vc_item_weight_t *d2)
{
	ZBX_RETURN_IF_NOT_EQUAL(d1->weight, d2->weight);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_history_logfree                                               *
 *                                                                            *
 * Purpose: frees history log and all resources allocated for it              *
 *                                                                            *
 * Parameters: log   - [IN] the history log to free                           *
 *                                                                            *
 ******************************************************************************/
static void	vc_history_logfree(zbx_log_value_t *log)
{
	zbx_free(log->source);
	zbx_free(log->value);
	zbx_free(log);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_history_logdup                                                *
 *                                                                            *
 * Purpose: duplicates history log by allocating necessary resources and      *
 *          copying the target log values.                                    *
 *                                                                            *
 * Parameters: log   - [IN] the history log to duplicate                      *
 *                                                                            *
 * Return value: the duplicated history log                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_log_value_t	*vc_history_logdup(const zbx_log_value_t *log)
{
	zbx_log_value_t	*plog;

	plog = zbx_malloc(NULL, sizeof(zbx_log_value_t));

	plog->timestamp = log->timestamp;
	plog->logeventid = log->logeventid;
	plog->severity = log->severity;
	plog->source = (NULL == log->source ? NULL : zbx_strdup(NULL, log->source));
	plog->value = zbx_strdup(NULL, log->value);

	return plog;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_history_record_vector_clean                                   *
 *                                                                            *
 * Purpose: releases resources allocated to store history records             *
 *                                                                            *
 * Parameters: vector      - [IN] the history record vector                   *
 *             value_type  - [IN] the type of vector values                   *
 *                                                                            *
 ******************************************************************************/
static void	vc_history_record_vector_clean(zbx_vector_history_record_t *vector, int value_type)
{
	int	i;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			for (i = 0; i < vector->values_num; i++)
				zbx_free(vector->values[i].value.str);

			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = 0; i < vector->values_num; i++)
				vc_history_logfree(vector->values[i].value.log);
	}

	zbx_vector_history_record_clear(vector);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_update_statistics                                             *
 *                                                                            *
 * Purpose: updates cache and item statistics                                 *
 *                                                                            *
 * Parameters: item    - [IN] the item (optional)                             *
 *             hits    - [IN] the number of hits to add                       *
 *             misses  - [IN] the number of misses to add                     *
 *                                                                            *
 * Comments: The misses are added only to cache statistics, while hits are    *
 *           added to both - item and cache statistics.                       *
 *                                                                            *
 ******************************************************************************/
static void	vc_update_statistics(zbx_vc_item_t *item, int hits, int misses)
{
	if (NULL != item)
	{
		item->hits += hits;
		item->last_accessed = ZBX_VC_TIME();
	}

	if (NULL != vc_cache)
	{
		vc_cache->hits += hits;
		vc_cache->misses += misses;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vc_warn_low_memory                                               *
 *                                                                            *
 * Purpose: logs low memory warning                                           *
 *                                                                            *
 * Comments: The low memory warning is written to log every 5 minutes when    *
 *           cache is working in the low memory mode.                         *
 *                                                                            *
 ******************************************************************************/
static void	vc_warn_low_memory()
{
	int		now;

	now = ZBX_VC_TIME();

	if (now - vc_cache->last_warning_time > ZBX_VC_LOW_MEMORY_WARNING_PERIOD)
	{
		vc_cache->last_warning_time = now;

		zabbix_log(LOG_LEVEL_WARNING, "value cache is fully used: please increase ValueCacheSize"
				" configuration parameter");
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vc_release_space                                                 *
 *                                                                            *
 * Purpose: frees space in cache to store the specified number of bytes by    *
 *          dropping the least accessed items                                 *
 *                                                                            *
 * Parameters: item  - [IN] the item requesting more space to store its data  *
 *             space - [IN] the number of bytes to free                       *
 *                                                                            *
 * Comments: The caller item must not be removed from cache to avoid          *
 *           complications (ie - checking if item still is in cache every     *
 *           time after calling vc_free_space() function).                    *
 *           vc_free_space() attempts to free at least min_free_request       *
 *           bytes of space to reduce number of space release requests.       *
 *                                                                            *
 ******************************************************************************/
static void	vc_release_space(zbx_vc_item_t *source_item, size_t space)
{
	zbx_hashset_iter_t		iter;
	zbx_vc_item_t			*item;
	int				timestamp, i;
	size_t				freed = 0;
	zbx_vector_vc_itemweight_t	items;

	timestamp = ZBX_VC_TIME() - SEC_PER_DAY;

	/* reserve at least min_free_request bytes to avoid spamming with free space requests */
	if (space < vc_cache->min_free_request)
		space = vc_cache->min_free_request;

	/* first remove items with the last accessed time older than a day */
	zbx_hashset_iter_reset(&vc_cache->items, &iter);

	while (NULL != (item = zbx_hashset_iter_next(&iter)))
	{
		if (0 == item->refcount && source_item != item && item->last_accessed < timestamp)
		{
			freed += vch_item_free_cache(item) + sizeof(zbx_vc_item_t);
			zbx_hashset_iter_remove(&iter);
		}
	}

	if (freed >= space)
		return;

	/* failed to free enough space by removing old items, entering low memory mode */
	vc_cache->low_memory = 1;

	vc_warn_low_memory();

	/* remove items with least hits/size ratio */
	zbx_vector_vc_itemweight_create(&items);

	zbx_hashset_iter_reset(&vc_cache->items, &iter);

	while (NULL != (item = zbx_hashset_iter_next(&iter)))
	{
		/* don't remove the item that requested the space and also keep */
		/* items currently being accessed                               */
		if (0 == item->refcount)
		{
			zbx_vc_item_weight_t	weight = {item};

			if (0 < item->values_total)
				weight.weight = (double)item->hits / item->values_total;

			zbx_vector_vc_itemweight_append_ptr(&items, &weight);
		}
	}

	zbx_vector_vc_itemweight_sort(&items, (zbx_compare_func_t)vc_item_weight_compare_func);

	for (i = 0; i < items.values_num && freed < space; i++)
	{
		item = items.values[i].item;

		freed += vch_item_free_cache(item) + sizeof(zbx_vc_item_t);
		zbx_hashset_remove(&vc_cache->items, item);
	}
	zbx_vector_vc_itemweight_destroy(&items);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_history_record_copy                                           *
 *                                                                            *
 * Purpose: copies history value                                              *
 *                                                                            *
 * Parameters: dst        - [OUT] a pointer to the destination value          *
 *             src        - [IN] a pointer to the source value                *
 *             value_type - [IN] the value type (see ITEM_VALUE_TYPE_* defs)  *
 *                                                                            *
 * Comments: Additional memory is allocated to store string, text and log     *
 *           value contents. This memory must be freed by the caller.         *
 *                                                                            *
 ******************************************************************************/
static void	vc_history_record_copy(zbx_history_record_t *dst, const zbx_history_record_t *src, int value_type)
{
	dst->timestamp = src->timestamp;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			dst->value.str = zbx_strdup(NULL, src->value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			dst->value.log = vc_history_logdup(src->value.log);
			break;
		default:
			dst->value = src->value;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vc_history_record_vector_append                                  *
 *                                                                            *
 * Purpose: appends the specified value to value vector                       *
 *                                                                            *
 * Parameters: vector     - [IN/OUT] the value vector                         *
 *             value_type - [IN] the type of value to append                  *
 *             value      - [IN] the value to append                          *
 *                                                                            *
 * Comments: Additional memory is allocated to store string, text and log     *
 *           value contents. This memory must be freed by the caller.         *
 *                                                                            *
 ******************************************************************************/
static void	vc_history_record_vector_append(zbx_vector_history_record_t *vector, int value_type,
		zbx_history_record_t *value)
{
	zbx_history_record_t	record;

	vc_history_record_copy(&record, value, value_type);
	zbx_vector_history_record_append_ptr(vector, &record);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_malloc                                                   *
 *                                                                            *
 * Purpose: allocate cache memory to store item's resources                   *
 *                                                                            *
 * Parameters: item   - [IN] the item                                         *
 *             size   - [IN] the number of bytes to allocate                  *
 *                                                                            *
 * Return value:  The pointer to allocated memory or NULL if there is not     *
 *                enough shared memory.                                       *
 *                                                                            *
 * Comments: If allocation fails this function attempts to free the required  *
 *           space in cache by calling vc_free_space() and tries again. If it *
 *           still fails a NULL value is returned.                            *
 *                                                                            *
 ******************************************************************************/
static void	*vc_item_malloc(zbx_vc_item_t *item, size_t size)
{
	char	*ptr;

	if (NULL == (ptr = __vc_mem_malloc_func(NULL, size)))
	{
		/* If failed to allocate required memory, try to free space in      */
		/* cache and allocate again. If there still is not enough space -   */
		/* return NULL as failure.                                          */
		vc_release_space(item, size);
		ptr = __vc_mem_malloc_func(NULL, size);
	}

	return ptr;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_strdup                                                   *
 *                                                                            *
 * Purpose: copies string to the cache memory                                 *
 *                                                                            *
 * Parameters: item  - [IN] the item                                          *
 *             str   - [IN] the string to copy                                *
 *                                                                            *
 * Return value:  The pointer to the copied string or NULL if there was not   *
 *                enough space in cache.                                      *
 *                                                                            *
 * Comments: If the string pool already contains matching string, then its    *
 *           reference counter is incremented and the string returned.        *
 *                                                                            *
 *           Otherwise cache memory is allocated to store the specified       *
 *           string. If the allocation fails this function attempts to free   *
 *           the required space in cache by calling vc_release_space() and    *
 *           tries again. If it still fails then a NULL value is returned.    *
 *                                                                            *
 ******************************************************************************/
static char	*vc_item_strdup(zbx_vc_item_t *item, const char *str)
{
	void	*ptr;

	ptr = zbx_hashset_search(&vc_cache->strpool, str - REFCOUNT_FIELD_SIZE);

	if (NULL == ptr)
	{
		int	tries = 0;
		size_t	len;

		len = strlen(str) + 1;

		while (NULL == (ptr = zbx_hashset_insert_ext(&vc_cache->strpool, str - REFCOUNT_FIELD_SIZE,
				REFCOUNT_FIELD_SIZE + len, REFCOUNT_FIELD_SIZE)))
		{
			/* If there is not enough space - free enough to store string + hashset entry overhead */
			/* and try inserting one more time. If it fails again, then fail the function.         */
			if (0 == tries++)
				vc_release_space(item, len + REFCOUNT_FIELD_SIZE + sizeof(ZBX_HASHSET_ENTRY_T));
			else
				return NULL;
		}

		*(uint32_t *)ptr = 0;
	}

	(*(uint32_t *)ptr)++;

	return (char *)ptr + REFCOUNT_FIELD_SIZE;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_strfree                                                  *
 *                                                                            *
 * Purpose: removes string from cache string pool                             *
 *                                                                            *
 * Parameters: str   - [IN] the string to remove                              *
 *                                                                            *
 * Return value: the number of bytes freed                                    *
 *                                                                            *
 * Comments: This function decrements the string reference counter and        *
 *           removes it from the string pool when counter becomes zero.       *
 *                                                                            *
 *           Note - only strings created with vc_item_strdup() function must  *
 *           be freed with vc_item_strfree().                                 *
 *                                                                            *
 ******************************************************************************/
static size_t	vc_item_strfree(char *str)
{
	size_t	freed = 0;

	if (NULL != str)
	{
		void	*ptr = str - REFCOUNT_FIELD_SIZE;

		if (0 == --(*(uint32_t *)ptr))
		{
			freed = strlen(str) + REFCOUNT_FIELD_SIZE + 1;
			zbx_hashset_remove(&vc_cache->strpool, ptr);
		}
	}

	return freed;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_logdup                                                   *
 *                                                                            *
 * Purpose: copies log value to the cache memory                              *
 *                                                                            *
 * Parameters: item  - [IN] the item                                          *
 *             log   - [IN] the log value to copy                             *
 *                                                                            *
 * Return value:  The pointer to the copied log value or NULL if there was    *
 *                not enough space in cache.                                  *
 *                                                                            *
 * Comments: Cache memory is allocated to store the log value. If the         *
 *           allocation fails this function attempts to free the required     *
 *           space in cache by calling vc_release_space() and tries again.    *
 *           If it still fails then a NULL value is returned.                 *
 *                                                                            *
 ******************************************************************************/
static zbx_log_value_t	*vc_item_logdup(zbx_vc_item_t *item, const zbx_log_value_t *log)
{
	zbx_log_value_t	*plog = NULL;

	if (NULL == (plog = vc_item_malloc(item, sizeof(zbx_log_value_t))))
		return NULL;

	plog->timestamp = log->timestamp;
	plog->logeventid = log->logeventid;
	plog->severity = log->severity;

	if (NULL != log->source)
	{
		if (NULL == (plog->source = vc_item_strdup(item, log->source)))
			goto fail;
	}
	else
		plog->source = NULL;

	if (NULL == (plog->value = vc_item_strdup(item, log->value)))
		goto fail;

	return plog;
fail:
	vc_item_strfree(plog->source);

	__vc_mem_free_func(plog);

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_logfree                                                  *
 *                                                                            *
 * Purpose: removes log resource from cache memory                            *
 *                                                                            *
 * Parameters: str   - [IN] the log to remove                                 *
 *                                                                            *
 * Return value: the number of bytes freed                                    *
 *                                                                            *
 * Comments: Note - only logs created with vc_item_logdup() function must     *
 *           be freed with vc_item_logfree().                                 *
 *                                                                            *
 ******************************************************************************/
static size_t	vc_item_logfree(zbx_log_value_t *log)
{
	size_t	freed = 0;

	if (NULL != log)
	{
		freed += vc_item_strfree(log->source);
		freed += vc_item_strfree(log->value);

		__vc_mem_free_func(log);
		freed += sizeof(zbx_log_value_t);
	}

	return freed;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_free_values                                              *
 *                                                                            *
 * Purpose: frees cache resources of the specified item value range           *
 *                                                                            *
 * Parameters: item    - [IN] the item                                        *
 *             values  - [IN] the target value array                          *
 *             first   - [IN] the first value to free                         *
 *             last    - [IN] the last value to free                          *
 *                                                                            *
 * Return value: the number of bytes freed                                    *
 *                                                                            *
 ******************************************************************************/
static size_t	vc_item_free_values(zbx_vc_item_t *item, zbx_history_record_t *values, int first, int last)
{
	size_t	freed = 0;
	int 	i;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			for (i = first; i <= last; i++)
				freed += vc_item_strfree(values[i].value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = first; i <= last; i++)
				freed += vc_item_logfree(values[i].value.log);
			break;
	}

	item->values_total -= (last - first + 1);

	return freed;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_remove_item                                                   *
 *                                                                            *
 * Purpose: removes item from cache and frees resources allocated for it      *
 *                                                                            *
 * Parameters: item    - [IN] the item                                        *
 *                                                                            *
 ******************************************************************************/
static void	vc_remove_item(zbx_vc_item_t *item)
{
	vch_item_free_cache(item);
	zbx_hashset_remove(&vc_cache->items, item);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_addref                                                   *
 *                                                                            *
 * Purpose: increment item reference counter                                  *
 *                                                                            *
 * Parameters: item     - [IN] the item                                       *
 *                                                                            *
 ******************************************************************************/
static void	vc_item_addref(zbx_vc_item_t *item)
{
	item->refcount++;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_release                                                  *
 *                                                                            *
 * Purpose: decrement item reference counter                                  *
 *                                                                            *
 * Parameters: item     - [IN] the item                                       *
 *                                                                            *
 * Comments: if the resulting reference counter is 0, then this function      *
 *           processes pending item operations                                *
 *                                                                            *
 ******************************************************************************/
static void	vc_item_release(zbx_vc_item_t *item)
{
	if (0 == (--item->refcount))
	{
		if (0 != (item->state & ZBX_ITEM_STATE_REMOVE_PENDING))
		{
			vc_remove_item(item);
			return;
		}

		if (0 != (item->state & ZBX_ITEM_STATE_CLEAN_PENDING))
			vch_item_clean_cache(item);

		item->state = 0;
	}
}

/******************************************************************************************************************
 *                                                                                                                *
 * History storage API                                                                                            *
 *                                                                                                                *
 ******************************************************************************************************************/
/*
 * The value cache caches all values from the largest request range to
 * the current time. The history data are stored in variable size chunks
 * as illustrated in the following diagram:
 *
 *  .----------------.
 *  | zbx_vc_cache_t |
 *  |----------------|      .---------------.
 *  | items          |----->| zbx_vc_item_t |-.
 *  '----------------'      |---------------| |-.
 *  .-----------------------| tail          | | |
 *  |                   .---| head          | | |
 *  |                   |   '---------------' | |
 *  |                   |     '---------------' |
 *  |                   |       '---------------'
 *  |                   |
 *  |                   '-------------------------------------------------.
 *  |                                                                     |
 *  |  .----------------.                                                 |
 *  '->| zbx_vc_chunk_t |<-.                                              |
 *     |----------------|  |  .----------------.                          |
 *     | next           |---->| zbx_vc_chunk_t |<-.                       |
 *     | prev           |  |  |----------------|  |  .----------------.   |
 *     '----------------'  |  | next           |---->| zbx_vc_chunk_t |<--'
 *                         '--| prev           |  |  |----------------|
 *                            '----------------'  |  | next           |
 *                                                '--| prev           |
 *                                                   '----------------'
 *
 * The history values are stored in a double linked list of data chunks, holding
 * variable number of records (depending on largest request size).
 *
 * After adding a new chunk, the older chunks (outside the largest request
 * range) are automatically removed from cache.
 */

/******************************************************************************
 *                                                                            *
 * Function: vch_item_update_range                                            *
 *                                                                            *
 * Purpose: updates item range with current request range                     *
 *                                                                            *
 * Parameters: item   - [IN] the item                                         *
 *             range  - [IN] the request range                                *
 *             now    - [IN] the current timestamp                            *
 *                                                                            *
 ******************************************************************************/
static void	vch_item_update_range(zbx_vc_item_t *item, int range, int now)
{
	int	hour, diff;

	if (VC_MIN_RANGE > range)
		range = VC_MIN_RANGE;

	if (item->daily_range < range)
		item->daily_range = range;

	hour = (now / SEC_PER_HOUR) & 0xff;

	if (0 > (diff = hour - item->range_sync_hour))
		diff += 0xff;

	if (item->active_range < item->daily_range || ZBX_VC_RANGE_SYNC_PERIOD < diff)
	{
		item->active_range = item->daily_range;
		item->daily_range = range;
		item->range_sync_hour = hour;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_chunk_slot_count                                        *
 *                                                                            *
 * Purpose: calculates optimal number of slots for an item data chunk         *
 *                                                                            *
 * Parameters:  item        - [IN] the item                                   *
 *              values_new  - [IN] the number of values to be added           *
 *                                                                            *
 * Return value: the number of slots for a new item data chunk                *
 *                                                                            *
 * Comments: From size perspective the optimal slot count per chunk is        *
 *           approximately square root of the number of cached values.        *
 *           Still creating too many chunks might affect timeshift request    *
 *           performance, so don't try creating more than 32 chunks unless    *
 *           the calculated slot count exceeds the maximum limit.             *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_chunk_slot_count(zbx_vc_item_t *item, int values_new)
{
	int	nslots, values;

	values = item->values_total + values_new;

	nslots = zbx_isqrt32(values);

	if ((values + nslots - 1) / nslots + 1 > 32)
		nslots = values / 32;

	if (nslots > (int)ZBX_VC_MAX_CHUNK_RECORDS)
		nslots = ZBX_VC_MAX_CHUNK_RECORDS;
	if (nslots < (int)ZBX_VC_MIN_CHUNK_RECORDS)
		nslots = ZBX_VC_MIN_CHUNK_RECORDS;

	return nslots;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_add_chunk                                               *
 *                                                                            *
 * Purpose: adds a new data chunk at the end of item's history data list      *
 *                                                                            *
 * Parameters: item          - [IN/OUT] the item to add chunk to              *
 *             nslots        - [IN] the number of slots in the new chunk      *
 *             insert_before - [IN] the target chunk before which the new     *
 *                             chunk must be inserted. If this value is NULL  *
 *                             then the new chunk is appended at the end of   *
 *                             chunk list (making it the newest chunk).       *
 *                                                                            *
 * Return value:  SUCCEED - the chunk was added successfully                  *
 *                FAIL - failed to create a new chunk (not enough memory)     *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_add_chunk(zbx_vc_item_t *item, int nslots, zbx_vc_chunk_t *insert_before)
{
	zbx_vc_chunk_t	*chunk;
	int		chunk_size;

	chunk_size = sizeof(zbx_vc_chunk_t) + sizeof(zbx_history_record_t) * (nslots - 1);

	if (NULL == (chunk = vc_item_malloc(item, chunk_size)))
		return FAIL;

	memset(chunk, 0, sizeof(zbx_vc_chunk_t));
	chunk->slots_num = nslots;

	chunk->next = insert_before;

	if (NULL == insert_before)
	{
		chunk->prev = item->head;

		if (NULL != item->head)
			item->head->next = chunk;
		else
			item->tail = chunk;

		item->head = chunk;
	}
	else
	{
		chunk->prev = insert_before->prev;
		insert_before->prev = chunk;

		if (item->tail == insert_before)
			item->tail = chunk;
		else
			chunk->prev->next = chunk;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_chunk_find_last_value_before                                 *
 *                                                                            *
 * Purpose: find the index of the last value in chunk with timestamp less or  *
 *          equal to the specified timestamp.                                 *
 *                                                                            *
 * Parameters:  chunk      - [IN] the chunk                                   *
 *              timestamp  - [IN] the target timestamp                        *
 *                                                                            *
 * Return value: The index of the last value in chunk with timestamp less or  *
 *               equal to the specified timestamp.                            *
 *               -1 is returned in the case of failure (meaning that all      *
 *               values have timestamps greater than the target timestamp).   *
 *                                                                            *
 ******************************************************************************/
static int	vch_chunk_find_last_value_before(const zbx_vc_chunk_t *chunk, int timestamp)
{
	int	start = chunk->first_value, end = chunk->last_value, middle;

	/* check if the last value timestamp is already greater or equal to the specified timestamp */
	if (chunk->slots[end].timestamp.sec <= timestamp)
		return end;

	/* chunk contains only one value, which did not pass the above check, return failure */
	if (start == end)
		return -1;

	/* perform value lookup using binary search */
	while (start != end)
	{
		middle = start + (end - start) / 2;

		if (chunk->slots[middle].timestamp.sec > timestamp)
		{
			end = middle;
			continue;
		}

		if (chunk->slots[middle + 1].timestamp.sec <= timestamp)
		{
			start = middle;
			continue;
		}

		return middle;
	}

	return -1;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_get_last_value                                          *
 *                                                                            *
 * Purpose: gets the chunk and index of the last value with a timestamp less  *
 *          or equal to the specified timestamp                               *
 *                                                                            *
 * Parameters:  item          - [IN] the item                                 *
 *              end_timestamp - [IN] the target timestamp (0 - current time)  *
 *              pchunk        - [OUT] the chunk containing the target value   *
 *              pindex        - [OUT] the index of the target value           *
 *                                                                            *
 * Return value: SUCCEED - the last value was found successfully              *
 *               FAIL - all values in cache have timestamps greater than the  *
 *                      target (timeshift) timestamp.                         *
 *                                                                            *
 * Comments: If end_timestamp value is 0, then simply the last item value in  *
 *           cache is returned.                                               *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_get_last_value(const zbx_vc_item_t *item, int end_timestamp, zbx_vc_chunk_t **pchunk,
		int *pindex)
{
	zbx_vc_chunk_t	*chunk = item->head;
	int		index;

	if (NULL == chunk)
		return FAIL;

	index = chunk->last_value;

	if (0 == end_timestamp)
		end_timestamp = ZBX_VC_TIME();

	if (chunk->slots[index].timestamp.sec > end_timestamp)
	{
		while (chunk->slots[chunk->first_value].timestamp.sec > end_timestamp)
		{
			chunk = chunk->prev;
			/* there are no values for requested range, return failure */
			if (NULL == chunk)
				return FAIL;
		}
		index = vch_chunk_find_last_value_before(chunk, end_timestamp);
	}

	*pchunk = chunk;
	*pindex = index;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_copy_value                                              *
 *                                                                            *
 * Purpose: copies value in the specified item's chunk slot                   *
 *                                                                            *
 * Parameters: chunk        - [IN/OUT] the target chunk                       *
 *             index        - [IN] the target slot                            *
 *             source_value - [IN] the value to copy                          *
 *                                                                            *
 * Return value: SUCCEED - the value was copied successfully                  *
 *               FAIL    - the value copying failed (not enough space for     *
 *                         string, text or log type data)                     *
 *                                                                            *
 * Comments: This function is used to copy data to cache. The contents of     *
 *           str, text and log type values are stored in cache string pool.   *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_copy_value(zbx_vc_item_t *item, zbx_vc_chunk_t *chunk, int index,
		const zbx_history_record_t *source_value)
{
	zbx_history_record_t	*value;
	int			ret = FAIL;

	value = &chunk->slots[index];

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (NULL == (value->value.str = vc_item_strdup(item, source_value->value.str)))
				goto out;
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (NULL == (value->value.log = vc_item_logdup(item, source_value->value.log)))
				goto out;
			break;
		default:
			value->value = source_value->value;
	}
	value->timestamp = source_value->timestamp;

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_copy_values_at_tail                                     *
 *                                                                            *
 * Purpose: copies values at the beginning of item tail chunk                 *
 *                                                                            *
 * Parameters: item       - [IN/OUT] the target item                          *
 *             values     - [IN] the values to copy                           *
 *             values_num - [IN] the number of values to copy                 *
 *                                                                            *
 * Return value: SUCCEED - the values were copied successfully                *
 *               FAIL    - the value copying failed (not enough space for     *
 *                         string, text or log type data)                     *
 *                                                                            *
 * Comments: This function is used to copy data to cache. The contents of     *
 *           str, text and log type values are stored in cache string pool.   *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_copy_values_at_tail(zbx_vc_item_t *item, const zbx_history_record_t *values, int values_num)
{
	int	i, ret = FAIL, first_value = item->tail->first_value;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			for (i = values_num - 1; i >= 0; i--)
			{
				zbx_history_record_t	*value = &item->tail->slots[item->tail->first_value - 1];

				if (NULL == (value->value.str = vc_item_strdup(item, values[i].value.str)))
					goto out;

				value->timestamp = values[i].timestamp;
				item->tail->first_value--;
			}
			ret = SUCCEED;

			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = values_num - 1; i >= 0; i--)
			{
				zbx_history_record_t	*value = &item->tail->slots[item->tail->first_value - 1];

				if (NULL == (value->value.log = vc_item_logdup(item, values[i].value.log)))
					goto out;

				value->timestamp = values[i].timestamp;
				item->tail->first_value--;
			}
			ret = SUCCEED;

			break;
		default:
			memcpy(&item->tail->slots[item->tail->first_value - values_num], values,
					values_num * sizeof(zbx_history_record_t));
			item->tail->first_value -= values_num;
			ret = SUCCEED;
	}
out:
	item->values_total += first_value - item->tail->first_value;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_free_chunk                                              *
 *                                                                            *
 * Purpose: frees chunk and all resources allocated to store its values       *
 *                                                                            *
 * Parameters: item    - [IN] the chunk owner item                            *
 *             chunk   - [IN] the chunk to free                               *
 *                                                                            *
 * Return value: the number of bytes freed                                    *
 *                                                                            *
 ******************************************************************************/
static size_t	vch_item_free_chunk(zbx_vc_item_t *item, zbx_vc_chunk_t *chunk)
{
	size_t	freed;

	freed = vc_item_free_values(item, chunk->slots, chunk->first_value, chunk->last_value);

	__vc_mem_free_func(chunk);

	return freed + sizeof(zbx_vc_chunk_t) + (chunk->last_value - chunk->first_value) * sizeof(zbx_history_record_t);
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_remove_chunk                                            *
 *                                                                            *
 * Purpose: removes item history data chunk                                   *
 *                                                                            *
 * Parameters: item    - [IN ] the chunk owner item                           *
 *             chunk   - [IN] the chunk to remove                             *
 *                                                                            *
 ******************************************************************************/
static void	vch_item_remove_chunk(zbx_vc_item_t *item, zbx_vc_chunk_t *chunk)
{
	if (NULL != chunk->next)
		chunk->next->prev = chunk->prev;

	if (NULL != chunk->prev)
		chunk->prev->next = chunk->next;

	if (chunk == item->head)
		item->head = chunk->prev;

	if (chunk == item->tail)
		item->tail = chunk->next;

	vch_item_free_chunk(item, chunk);
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_clean_cache                                             *
 *                                                                            *
 * Purpose: removes item history data that are outside (older) the maximum    *
 *          request range                                                     *
 *                                                                            *
 * Parameters:  item   - [IN] the target item                                 *
 *                                                                            *
 ******************************************************************************/
static void	vch_item_clean_cache(zbx_vc_item_t *item)
{
	zbx_vc_chunk_t	*next;

	if (0 != item->active_range)
	{
		zbx_vc_chunk_t	*tail = item->tail;
		zbx_vc_chunk_t	*chunk = tail;
		int		timestamp;

		timestamp = ZBX_VC_TIME() - item->active_range;

		/* try to remove chunks with all history values older than maximum request range */
		while (NULL != chunk && chunk->slots[chunk->last_value].timestamp.sec < timestamp &&
				chunk->slots[chunk->last_value].timestamp.sec !=
						item->head->slots[item->head->last_value].timestamp.sec)
		{
			/* don't remove the head chunk */
			if (NULL == (next = chunk->next))
				break;

			/* Values with the same timestamps (seconds resolution) always should be either   */
			/* kept in cache or removed together. There should not be a case when one of them */
			/* is in cache and the second is dropped.                                         */
			/* Here we are handling rare case, when the last value of first chunk has the     */
			/* same timestamp (seconds resolution) as the first value in the second chunk.    */
			/* In this case increase the first value index of the next chunk until the first  */
			/* value timestamp is greater.                                                    */

			if (next->slots[next->first_value].timestamp.sec != next->slots[next->last_value].timestamp.sec)
			{
				while (next->slots[next->first_value].timestamp.sec ==
						chunk->slots[chunk->last_value].timestamp.sec)
				{
					vc_item_free_values(item, next->slots, next->first_value, next->first_value);
					next->first_value++;
				}
			}

			vch_item_remove_chunk(item, chunk);

			chunk = next;
		}

		/* reset the status flags if data was removed from cache */
		if (tail != item->tail)
			item->status = 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_add_value_at_head                                       *
 *                                                                            *
 * Purpose: adds one item history value at the end of current item's history  *
 *          data                                                              *
 *                                                                            *
 * Parameters:  item   - [IN] the item to add history data to                 *
 *              value  - [IN] the item history data value                     *
 *                                                                            *
 * Return value: SUCCEED - the history data value was added successfully      *
 *               FAIL - failed to add history data value (not enough memory)  *
 *                                                                            *
 * Comments: In the case of failure the item will be removed from cache       *
 *           later.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_add_value_at_head(zbx_vc_item_t *item, const zbx_history_record_t *value)
{
	int		ret = FAIL, index, sindex, nslots = 0;
	zbx_vc_chunk_t	*head = item->head, *chunk, *schunk;

	if (NULL != item->head &&
			0 < vc_history_record_compare_asc_func(&item->head->slots[item->head->last_value], value))
	{
		if (0 < vc_history_record_compare_asc_func(&item->tail->slots[item->tail->first_value], value))
		{
			/* If the added value has the same or older timestamp as the first value in cache */
			/* we can't add it to keep cache consistency. In this case reset the cached all   */
			/* flag and return success.                                                       */
			if (ZBX_ITEM_STATUS_CACHED_ALL == item->status)
				item->status = 0;

			ret = SUCCEED;
			goto out;
		}

		sindex = item->head->last_value;
		schunk = item->head;

		if (0 == item->head->slots_num - item->head->last_value - 1)
		{
			if (FAIL == vch_item_add_chunk(item, vch_item_chunk_slot_count(item, 1), NULL))
				goto out;
		}
		else
			item->head->last_value++;

		item->values_total++;

		chunk = item->head;
		index = item->head->last_value;

		do
		{
			chunk->slots[index] = schunk->slots[sindex];

			chunk = schunk;
			index = sindex;

			if (--sindex < schunk->first_value)
			{
				if (NULL == (schunk = schunk->prev))
				{
					memset(&chunk->slots[index], 0, sizeof(zbx_vc_chunk_t));
					THIS_SHOULD_NEVER_HAPPEN;

					goto out;
				}

				sindex = schunk->last_value;
			}
		} while (0 < zbx_timespec_compare(&schunk->slots[sindex].timestamp, &value->timestamp));
	}
	else
	{
		/* find the number of free slots on the right side in last (head) chunk */
		if (NULL != item->head)
			nslots = item->head->slots_num - item->head->last_value - 1;

		if (0 == nslots)
		{
			if (FAIL == vch_item_add_chunk(item, vch_item_chunk_slot_count(item, 1), NULL))
				goto out;
		}
		else
			item->head->last_value++;

		item->values_total++;

		chunk = item->head;
		index = item->head->last_value;
	}

	if (SUCCEED != vch_item_copy_value(item, chunk, index, value))
		goto out;

	/* try to remove old (unused) chunks if a new chunk was added */
	if (head != item->head)
		item->state |= ZBX_ITEM_STATE_CLEAN_PENDING;

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_add_values_at_tail                                      *
 *                                                                            *
 * Purpose: adds item history values at the beginning of current item's       *
 *          history data                                                      *
 *                                                                            *
 * Parameters:  item   - [IN] the item to add history data to                 *
 *              values - [IN] the item history data values                    *
 *              num    - [IN] the number of history data values to add        *
 *                                                                            *
 * Return value: SUCCEED - the history data values were added successfully    *
 *               FAIL - failed to add history data values (not enough memory) *
 *                                                                            *
 * Comments: In the case of failure the item is removed from cache.           *
 *           Overlapping values (by timestamp seconds) are ignored.           *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_add_values_at_tail(zbx_vc_item_t *item, const zbx_history_record_t *values, int values_num)
{
	int 	count = values_num, ret = FAIL;

	/* skip values already added to the item cache by another process */
	if (NULL != item->tail)
	{
		int	sec = item->tail->slots[item->tail->first_value].timestamp.sec;

		while (--count >= 0 && values[count].timestamp.sec >= sec)
			;
		++count;
	}

	while (0 != count)
	{
		int	copy_slots, nslots = 0;

		/* find the number of free slots on the left side in first (tail) chunk */
		if (NULL != item->tail)
			nslots = item->tail->first_value;

		if (0 == nslots)
		{
			nslots = vch_item_chunk_slot_count(item, count);

			if (FAIL == vch_item_add_chunk(item, nslots, item->tail))
				goto out;

			item->tail->last_value = nslots - 1;
			item->tail->first_value = nslots;
		}

		/* copy values to chunk */
		copy_slots = MIN(nslots, count);
		count -= copy_slots;

		if (FAIL == vch_item_copy_values_at_tail(item, values + count, copy_slots))
			goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_cache_values_by_time                                    *
 *                                                                            *
 * Purpose: cache item history data for the specified time period             *
 *                                                                            *
 * Parameters: item      - [IN] the item                                      *
 *             seconds   - [IN] the time period to retrieve data for          *
 *             timestamp - [IN] the requested period end timestamp            *
 *                                                                            *
 * Return value:  >=0    - the number of values read from database            *
 *                FAIL   - an error occurred while trying to cache values     *
 *                                                                            *
 * Comments: This function checks if the requested value range is cached and  *
 *           updates cache from database if necessary.                        *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_cache_values_by_time(zbx_vc_item_t *item, int seconds, int timestamp)
{
	int	ret = SUCCEED, update_seconds = 0, update_end, start;

	if (ZBX_ITEM_STATUS_CACHED_ALL == item->status)
		return SUCCEED;

	start = timestamp - seconds;
	update_end = ZBX_VC_TIME();

	/* check if the requested period is in the cached range */
	if (0 != item->active_range && update_end - start <= item->active_range)
		return SUCCEED;

	/* find if the cache should be updated to cover the required range */
	if (NULL != item->tail)
	{
		/* we need to get item values before the first cached value, but not including it */
		update_end = item->tail->slots[item->tail->first_value].timestamp.sec - 1;
	}

	update_seconds = update_end - start;

	/* update cache if necessary */
	if (0 < update_seconds)
	{
		zbx_vector_history_record_t	records;
		zbx_uint64_t			queries = 0;

		zbx_vector_history_record_create(&records);

		vc_try_unlock();

		if (SUCCEED == (ret = vc_db_read_values_by_time(item->itemid, item->value_type, &records,
				update_seconds, update_end, &queries)))
		{
			zbx_vector_history_record_sort(&records,
					(zbx_compare_func_t)vc_history_record_compare_asc_func);
		}

		vc_try_lock();

		vc_cache->db_queries += queries;

		if (SUCCEED == ret)
		{
			if (0 < records.values_num)
			{
				ret = vch_item_add_values_at_tail(item, records.values, records.values_num);

				if (SUCCEED == ret)
					ret = records.values_num;
			}
			/* when updating cache with time based request we can always reset status flags */
			/* flag even if the requested period contains no data                           */
			item->status = 0;
		}
		zbx_history_record_vector_destroy(&records, item->value_type);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_cache_values_by_count                                   *
 *                                                                            *
 * Purpose: cache the specified number of history data values since timeshift *
 *                                                                            *
 * Parameters: item      - [IN] the item                                      *
 *             count     - [IN] the number of history values to retrieve      *
 *             timestamp - [IN] the target timestamp                          *
 *                                                                            *
 * Return value:  >=0    - the number of values read from database            *
 *                FAIL   - an error occurred while trying to cache values     *
 *                                                                            *
 * Comments: This function checks if the requested number of values is cached *
 *           and updates cache from database if necessary.                    *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_cache_values_by_count(zbx_vc_item_t *item, int count, int timestamp)
{
	int	ret = SUCCEED, cached_records = 0, update_end;

	if (ZBX_ITEM_STATUS_CACHED_ALL == item->status)
		return SUCCEED;

	/* find if the cache should be updated to cover the required count */
	if (NULL != item->head)
	{
		zbx_vc_chunk_t	*chunk;
		int		index;

		if (SUCCEED == vch_item_get_last_value(item, timestamp, &chunk, &index))
		{
			cached_records = index - chunk->first_value + 1;

			while (NULL != (chunk = chunk->prev) && cached_records < count)
				cached_records += chunk->last_value - chunk->first_value + 1;
		}
	}

	/* update cache if necessary */
	if (cached_records < count)
	{
		zbx_vector_history_record_t	records;
		zbx_uint64_t			queries = 0;

		/* get the end timestamp to which (including) the values should be cached */
		if (NULL != item->head)
			update_end = item->tail->slots[item->tail->first_value].timestamp.sec - 1;
		else
			update_end = ZBX_VC_TIME();

		zbx_vector_history_record_create(&records);

		vc_try_unlock();

		ret = vc_db_read_values_by_count(item->itemid, item->value_type, &records, count - cached_records,
				timestamp < update_end ? timestamp : update_end, &queries);

		if (SUCCEED == ret && update_end > timestamp)
		{
			ret = vc_db_read_values_by_time(item->itemid, item->value_type, &records,
					update_end - timestamp, update_end, &queries);
		}

		if (SUCCEED == ret)
		{
			zbx_vector_history_record_sort(&records,
					(zbx_compare_func_t)vc_history_record_compare_asc_func);
		}

		vc_try_lock();

		vc_cache->db_queries += queries;

		if (SUCCEED == ret && 0 < records.values_num)
		{
			if (SUCCEED == (ret = vch_item_add_values_at_tail(item, records.values, records.values_num)))
				ret = records.values_num;
		}

		zbx_history_record_vector_destroy(&records, item->value_type);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_cache_value                                             *
 *                                                                            *
 * Purpose: cache item history data for the specified timestamp               *
 *                                                                            *
 * Parameters: item      - [IN] the item                                      *
 *             ts        - [IN] the timestamp                                 *
 *                                                                            *
 * Return value:  >=0    - the number of values read from database            *
 *                FAIL   - an error occurred while trying to cache values     *
 *                                                                            *
 * Comments: This function checks if the requested value range is cached and  *
 *           updates cache from database if necessary.                        *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_cache_value(zbx_vc_item_t *item, const zbx_timespec_t *ts)
{
	int				ret = SUCCEED, update_seconds = 0, update_end, start;
	zbx_vector_history_record_t	records;
	zbx_uint64_t			queries = 0;

	if (ZBX_ITEM_STATUS_CACHED_ALL == item->status)
		return SUCCEED;

	start = ts->sec - 1;

	/* find if the cache should be updated to cover the required range */
	if (NULL == item->tail)
	{
		update_end = ZBX_VC_TIME();
	}
	else
	{
		/* we need to get item values before the first cached value, but not including it */
		update_end = item->tail->slots[item->tail->first_value].timestamp.sec - 1;
	}

	update_seconds = update_end - start;

	zbx_vector_history_record_create(&records);

	vc_try_unlock();

	if (0 < update_seconds)
	{
		/* first try to find the requested value in target second interval */
		ret = vc_db_read_values_by_time(item->itemid, item->value_type, &records, update_seconds,
				update_end, &queries);

		zbx_vector_history_record_sort(&records, (zbx_compare_func_t)vc_history_record_compare_asc_func);
	}

	/* the target second does not contain the required value, read first value before it */
	if (SUCCEED == ret && (0 == records.values_num || 0 > zbx_timespec_compare(ts, &records.values[0].timestamp)))
		ret = vc_db_read_values_by_count(item->itemid, item->value_type, &records, 1, ts->sec - 1, &queries);

	if (SUCCEED == ret)
		zbx_vector_history_record_sort(&records, (zbx_compare_func_t)vc_history_record_compare_asc_func);

	vc_try_lock();

	vc_cache->db_queries += queries;

	if (SUCCEED == ret && 0 < records.values_num)
	{
		ret = vch_item_add_values_at_tail(item, records.values, records.values_num);

		if (SUCCEED == ret)
			ret = records.values_num;
	}
	zbx_history_record_vector_destroy(&records, item->value_type);

	return ret;
}


/******************************************************************************
 *                                                                            *
 * Function: vch_item_get_values_by_time                                      *
 *                                                                            *
 * Purpose: retrieves item history data from cache                            *
 *                                                                            *
 * Parameters: item      - [IN] the item                                      *
 *             values    - [OUT] the item history data stored time/value      *
 *                         pairs in undefined order                           *
 *             seconds   - [IN] the time period to retrieve data for          *
 *             timestamp - [IN] the requested period end timestamp            *
 *                                                                            *
 * Return value:  SUCCEED - the item history data was retrieved successfully  *
 *                FAIL    - the item history data was not retrieved           *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_get_values_by_time(zbx_vc_item_t *item, zbx_vector_history_record_t *values, int seconds,
		int timestamp)
{
	int		ret = SUCCEED, index, now;
	int		start = timestamp - seconds;
	zbx_vc_chunk_t	*chunk;

	/* Check if maximum request range is not set and all data are cached.  */
	/* Because that indicates there was a count based request with unknown */
	/* range which might be greater than the current request range.        */
	if (0 != item->active_range || ZBX_ITEM_STATUS_CACHED_ALL != item->status)
	{
		now = ZBX_VC_TIME();
		vch_item_update_range(item, seconds + now - timestamp, now);
	}

	if (FAIL == vch_item_get_last_value(item, timestamp, &chunk, &index))
	{
		/* Cache does not contain records for the specified timeshift & seconds range. */
		/* Return empty vector with success.                                           */
		goto out;
	}

	/* fill the values vector with item history values until the start timestamp is reached */
	while (chunk->slots[chunk->last_value].timestamp.sec > start)
	{
		while (index >= chunk->first_value && chunk->slots[index].timestamp.sec > start)
			vc_history_record_vector_append(values, item->value_type, &chunk->slots[index--]);

		if (NULL == (chunk = chunk->prev))
			break;

		index = chunk->last_value;
	}
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_get_values_by_count                                     *
 *                                                                            *
 * Purpose: retrieves item history data from cache                            *
 *                                                                            *
 * Parameters: item      - [IN] the item                                      *
 *             values    - [OUT] the item history data stored time/value      *
 *                         pairs in undefined order, optional                 *
 *                         If null then cache is updated if necessary, but no *
 *                         values are returned. Used to ensure that cache     *
 *                         contains a value of the specified timestamp.       *
 *             count     - [IN] the number of history values to retrieve      *
 *             timestamp - [IN] the target timestamp                          *
 *                                                                            *
 * Return value:  SUCCEED - the item history data was retrieved successfully  *
 *                FAIL    - the item history data was not retrieved           *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_get_values_by_count(zbx_vc_item_t *item, zbx_vector_history_record_t *values, int count,
		int timestamp)
{
	int		ret = SUCCEED, index, now;
	zbx_vc_chunk_t	*chunk;

	if (FAIL == vch_item_get_last_value(item, timestamp, &chunk, &index))
	{
		/* return empty vector with success */
		goto out;
	}

	/* fill the values vector with item history values until the <count> values are read */
	while (values->values_num < count)
	{
		while (index >= chunk->first_value && values->values_num < count)
			vc_history_record_vector_append(values, item->value_type, &chunk->slots[index--]);

		if (NULL == (chunk = chunk->prev))
			break;

		index = chunk->last_value;
	}

	/* Try setting maximum range only if all requested data was returned.   */
	/* Otherwise the current request range is unknown and can't be compared */
	/* to the maximum request range.                                        */
	if (values->values_num == count)
	{
		now = ZBX_VC_TIME();
		vch_item_update_range(item, now - values->values[values->values_num - 1].timestamp.sec, now);
	}
out:
	if (values->values_num < count)
	{
		/* not enough data in db to fulfill the request */
		item->active_range = 0;
		item->daily_range = 0;
		item->status = ZBX_ITEM_STATUS_CACHED_ALL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_get_value_range                                         *
 *                                                                            *
 * Purpose: get item values for the specified range                           *
 *                                                                            *
 * Parameters: item      - [IN] the item                                      *
 *             values    - [OUT] the item history data stored time/value      *
 *                         pairs in undefined order, optional                 *
 *                         If null then cache is updated if necessary, but no *
 *                         values are returned. Used to ensure that cache     *
 *                         contains a value of the specified timestamp.       *
 *             seconds   - [IN] the time period to retrieve data for          *
 *             count     - [IN] the number of history values to retrieve      *
 *             timestamp - [IN] the target timestamp                          *
 *                                                                            *
 * Return value:  SUCCEED - the item history data was retrieved successfully  *
 *                FAIL    - the item history data was not retrieved           *
 *                                                                            *
 * Comments: This function returns data from cache if necessary updating it   *
 *           from DB. If cache update was required and failed (not enough     *
 *           memory to cache DB values), then this function also fails.       *
 *                                                                            *
 *           If <count> is set then value range is defined as <count> values  *
 *           before <timestamp>. Otherwise the range is defined as <seconds>  *
 *           seconds before <timestamp>.                                      *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_get_value_range(zbx_vc_item_t *item, zbx_vector_history_record_t *values, int seconds,
		int count, int timestamp)
{
	int	ret, records_read, hits, misses;

	zbx_vector_history_record_clear(values);

	if (0 == count)
	{
		if (FAIL == (ret = vch_item_cache_values_by_time(item, seconds, timestamp)))
			goto out;

		records_read = ret;

		if (FAIL == (ret = vch_item_get_values_by_time(item, values, seconds, timestamp)))
			goto out;

		if (records_read > values->values_num)
			records_read = values->values_num;
	}
	else
	{
		if (FAIL == (ret = vch_item_cache_values_by_count(item, count, timestamp)))
			goto out;

		records_read = ret;

		if (FAIL == (ret = vch_item_get_values_by_count(item, values, count, timestamp)))
			goto out;

		if (records_read > values->values_num)
			records_read = values->values_num;
	}

	hits = values->values_num - records_read;
	misses = records_read;

	vc_update_statistics(item, hits, misses);
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_get_value                                               *
 *                                                                            *
 * Purpose: get the last history value with a timestamp less or equal to the  *
 *          target timestamp                                                  *
 *                                                                            *
 * Parameters: item       - [IN] the item id                                  *
 *             ts         - [IN] the target timestamp                         *
 *             value      - [OUT] the value found                             *
 *                                                                            *
 * Return value:  SUCCEED - the item history data was retrieved successfully  *
 *                FAIL    - the item history data was not retrieved           *
 *                                                                            *
 * Comments: This function returns data from cache if necessary updating it   *
 *           from DB. If cache update was required and failed (not enough     *
 *           memory to cache DB values), then this function also fails.       *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_get_value(zbx_vc_item_t *item, const zbx_timespec_t *ts, zbx_history_record_t *value,
		int *found)
{
	int			index, ret = FAIL, hits = 0, misses = 0, now;
	zbx_vc_chunk_t		*chunk;

	*found = 0;

	if (NULL == item->tail || 0 < zbx_timespec_compare(&item->tail->slots[item->tail->first_value].timestamp, ts))
	{
		if (FAIL == vch_item_cache_value(item, ts))
			goto out;

		misses++;
	}
	else
		hits++;

	ret = SUCCEED;

	if (FAIL == vch_item_get_last_value(item, ts->sec, &chunk, &index))
	{
		/* even after cache update the requested value is not there */
		goto out;
	}

	/* find the value by checking nanoseconds too */
	while (0 < zbx_timespec_compare(&chunk->slots[index].timestamp, ts))
	{
		if (--index < chunk->first_value)
		{
			if (NULL == (chunk = chunk->prev))
				goto out;

			index = chunk->last_value;
		}
	}
	vc_update_statistics(item, hits, misses);

	vc_history_record_copy(value, &chunk->slots[index], item->value_type);

	now = ZBX_VC_TIME();
	vch_item_update_range(item, now - value->timestamp.sec, now);

	*found = 1;
out:
	if (0 == *found)
		item->status = ZBX_ITEM_STATUS_CACHED_ALL;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_free_cache                                              *
 *                                                                            *
 * Purpose: frees resources allocated for item history data                   *
 *                                                                            *
 * Parameters: item    - [IN] the item                                        *
 *                                                                            *
 * Return value: the size of freed memory (bytes)                             *
 *                                                                            *
 ******************************************************************************/
static size_t	vch_item_free_cache(zbx_vc_item_t *item)
{
	size_t	freed = 0;

	zbx_vc_chunk_t	*chunk = item->tail;

	while (NULL != chunk)
	{
		zbx_vc_chunk_t	*next = chunk->next;

		freed += vch_item_free_chunk(item, chunk);
		chunk = next;
	}
	item->values_total = 0;
	item->head = NULL;
	item->tail = NULL;

	return freed;
}

/******************************************************************************************************************
 *                                                                                                                *
 * Public API                                                                                                     *
 *                                                                                                                *
 ******************************************************************************************************************/

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_init                                                      *
 *                                                                            *
 * Purpose: initializes value cache                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_vc_init(void)
{
	const char	*__function_name = "zbx_vc_init";
	key_t		shm_key;
	zbx_uint64_t	size_reserved;

	if (0 == CONFIG_VALUE_CACHE_SIZE)
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_VALUECACHE_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC key for value cache");
		exit(EXIT_FAILURE);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&vc_lock, ZBX_MUTEX_VALUECACHE))
	{
		zbx_error("cannot create mutex for value cache");
		exit(EXIT_FAILURE);
	}

	size_reserved = zbx_mem_required_size(1, "value cache size", "ValueCacheSize");

	zbx_mem_create(&vc_mem, shm_key, ZBX_NO_MUTEX, CONFIG_VALUE_CACHE_SIZE,
			"value cache size", "ValueCacheSize", 1);

	CONFIG_VALUE_CACHE_SIZE -= size_reserved;

	vc_cache = __vc_mem_malloc_func(vc_cache, sizeof(zbx_vc_cache_t));

	if (NULL == vc_cache)
	{
		zbx_error("cannot allocate value cache header");
		exit(EXIT_FAILURE);
	}
	memset(vc_cache, 0, sizeof(zbx_vc_cache_t));

	zbx_hashset_create_ext(&vc_cache->items, VC_ITEMS_INIT_SIZE,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC, NULL,
			__vc_mem_malloc_func, __vc_mem_realloc_func, __vc_mem_free_func);

	if (NULL == vc_cache->items.slots)
	{
		zbx_error("cannot allocate value cache data storage");
		exit(EXIT_FAILURE);
	}

	zbx_hashset_create_ext(&vc_cache->strpool, VC_STRPOOL_INIT_SIZE,
			vc_strpool_hash_func, vc_strpool_compare_func, NULL,
			__vc_mem_malloc_func, __vc_mem_realloc_func, __vc_mem_free_func);

	if (NULL == vc_cache->strpool.slots)
	{
		zbx_error("cannot allocate string pool for value cache data storage");
		exit(EXIT_FAILURE);
	}

	/* the free space request should be 5% of cache size, but no more than 128KB */
	vc_cache->min_free_request = (CONFIG_VALUE_CACHE_SIZE / 100) * 5;
	if (vc_cache->min_free_request > 128 * ZBX_KIBIBYTE)
		vc_cache->min_free_request = 128 * ZBX_KIBIBYTE;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_destroy                                                   *
 *                                                                            *
 * Purpose: destroys value cache                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_vc_destroy(void)
{
	const char	*__function_name = "zbx_vc_destroy";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != vc_cache)
	{
		zbx_mem_destroy(vc_mem);
		zbx_mutex_destroy(&vc_lock);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_add_value                                                 *
 *                                                                            *
 * Purpose: adds an item value to the value cache                             *
 *                                                                            *
 * Parameters: itemid     - [IN] the item id                                  *
 *             value_type - [IN] the value type (see ITEM_VALUE_TYPE_* defs)  *
 *             timestamp  - [IN] the value timestamp                          *
 *             value      - [IN] the value to add                             *
 *                                                                            *
 * Return value:  SUCCEED - the item value was added successfully             *
 *                FAIL    - failed to add item value to cache (not fatal      *
 *                          failure - cache might be in low memory mode)      *
 *                                                                            *
 * Comments: This function must be called whenever item receives a new        *
 *           value(s) to keep the value cache updated.                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_vc_add_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *timestamp, history_value_t *value)
{
	const char	*__function_name = "zbx_vc_add_value";

	zbx_vc_item_t	*item;
	int 		ret = FAIL;

	if (NULL == vc_cache)
		return FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " value_type:%d timestamp:%d.%d",
			__function_name, itemid, value_type, timestamp->sec, timestamp->ns);

	vc_try_lock();

	if (NULL != (item = zbx_hashset_search(&vc_cache->items, &itemid)))
	{
		zbx_history_record_t	record = {*timestamp, *value};

		vc_item_addref(item);

		/* If the new value type does not match the item's type in cache we can't  */
		/* change the cache because other processes might still be accessing it    */
		/* at the same time. The only thing that can be done - mark it for removal */
		/* so it could be added later with new type.                               */
		/* Also mark it for removal if the value adding failed. In this case we    */
		/* won't have the latest data in cache - so the requests must go directly  */
		/* to the database.                                                        */
		if (item->value_type != value_type || FAIL == (ret = vch_item_add_value_at_head(item, &record)))
			item->state |= ZBX_ITEM_STATE_REMOVE_PENDING;

		vc_item_release(item);
	}

	vc_try_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_get_value_range                                           *
 *                                                                            *
 * Purpose: get item history data for the specified time period               *
 *                                                                            *
 * Parameters: itemid     - [IN] the item id                                  *
 *             value_type - [IN] the item value type                          *
 *             values     - [OUT] the item history data stored time/value     *
 *                          pairs in descending order                         *
 *             seconds    - [IN] the time period to retrieve data for         *
 *             count      - [IN] the number of history values to retrieve     *
 *             timestamp  - [IN] the period end timestamp                     *
 *                                                                            *
 * Return value:  SUCCEED - the item history data was retrieved successfully  *
 *                FAIL    - the item history data was not retrieved           *
 *                                                                            *
 * Comments: If the data is not in cache, it's read from DB, so this function *
 *           will always return the requested data, unless some error occurs. *
 *                                                                            *
 *           If <count> is set then value range is defined as <count> values  *
 *           before <timestamp>. Otherwise the range is defined as <seconds>  *
 *           seconds before <timestamp>.                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_vc_get_value_range(zbx_uint64_t itemid, int value_type, zbx_vector_history_record_t *values, int seconds,
		int count, int timestamp)
{
	const char	*__function_name = "zbx_vc_get_value_range";
	zbx_vc_item_t	*item = NULL;
	int 		ret = FAIL, cache_used = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " value_type:%d seconds:%d count:%d timestamp:%d",
			__function_name, itemid, value_type, seconds, count, timestamp);

	vc_try_lock();

	if (NULL == vc_cache)
		goto out;

	if (1 == vc_cache->low_memory)
		vc_warn_low_memory();

	if (NULL == (item = zbx_hashset_search(&vc_cache->items, &itemid)))
	{
		if (0 == vc_cache->low_memory)
		{
			zbx_vc_item_t   new_item = {itemid, value_type};

			if (NULL == (item = zbx_hashset_insert(&vc_cache->items, &new_item, sizeof(zbx_vc_item_t))))
				goto out;
		}
		else
			goto out;
	}

	vc_item_addref(item);

	if (0 != (item->state & ZBX_ITEM_STATE_REMOVE_PENDING) || item->value_type != value_type)
		goto out;

	ret = vch_item_get_value_range(item, values, seconds, count, timestamp);
out:
	if (FAIL == ret)
	{
		zbx_uint64_t	queries = 0;

		if (NULL != item)
			item->state |= ZBX_ITEM_STATE_REMOVE_PENDING;

		cache_used = 0;

		vc_try_unlock();

		if (0 == count)
		{
			if (SUCCEED == (ret = vc_db_read_values_by_time(itemid, value_type, values, seconds, timestamp,
					&queries)))
			{
				zbx_vector_history_record_sort(values,
						(zbx_compare_func_t)vc_history_record_compare_desc_func);
			}
		}
		else
		{
			if (SUCCEED == (ret = vc_db_read_values_by_count(itemid, value_type, values, count, timestamp,
					&queries)))
			{
				zbx_vector_history_record_sort(values,
						(zbx_compare_func_t)vc_history_record_compare_desc_func);

				/* vc_db_read_values_by_count() returns requested values + the rest of values having */
				/* within the same second as the last value - so drop the values outside request     */
				/* range                                                                             */
				while (count < values->values_num)
					zbx_history_record_clear(&values->values[--values->values_num], value_type);
			}
		}

		vc_try_lock();

		vc_cache->db_queries += queries;

		if (SUCCEED == ret)
			vc_update_statistics(NULL, 0, values->values_num);
	}

	if (NULL != item)
		vc_item_release(item);

	vc_try_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s count:%d cached:%d",
			__function_name, zbx_result_string(ret), values->values_num, cache_used);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_get_value                                                 *
 *                                                                            *
 * Purpose: get the last history value with a timestamp less or equal to the  *
 *          target timestamp                                                  *
 *                                                                            *
 * Parameters: itemid     - [IN] the item id                                  *
 *             value_type - [IN] the item value type                          *
 *             ts         - [IN] the target timestamp                         *
 *             value      - [OUT] the value found                             *
 *                                                                            *
 * Return Value: SUCCEED - the item was retrieved                             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Depending on the value type this function might allocate memory  *
 *           to store value data. To free it use zbx_vc_history_value_clear() *
 *           function.                                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_vc_get_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *ts, zbx_history_record_t *value)
{
	const char	*__function_name = "zbx_vc_get_value";
	zbx_vc_item_t	*item = NULL;
	int 		ret = FAIL, cache_used = 1, found = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " value_type:%d timestamp:%d.%d",
			__function_name, itemid, value_type, ts->sec, ts->ns);

	vc_try_lock();

	if (NULL == vc_cache)
		goto out;

	if (1 == vc_cache->low_memory)
		vc_warn_low_memory();

	if (NULL == (item = zbx_hashset_search(&vc_cache->items, &itemid)))
	{
		if (0 == vc_cache->low_memory)
		{
			zbx_vc_item_t   new_item = {itemid, value_type};

			if (NULL == (item = zbx_hashset_insert(&vc_cache->items, &new_item, sizeof(zbx_vc_item_t))))
				goto out;
		}
		else
			goto out;
	}

	vc_item_addref(item);

	if (0 != (item->state & ZBX_ITEM_STATE_REMOVE_PENDING) || item->value_type != value_type)
		goto out;

	ret = vch_item_get_value(item, ts, value, &found);
out:
	if (FAIL == ret)
	{
		zbx_uint64_t	queries = 0;

		if (NULL != item)
			item->state |= ZBX_ITEM_STATE_REMOVE_PENDING;

		cache_used = 0;

		vc_try_unlock();

		ret = vc_db_read_value(itemid, value_type, ts, value, &queries);

		vc_try_lock();

		vc_cache->db_queries += queries;

		if (SUCCEED == ret)
		{
			vc_update_statistics(NULL, 0, 1);
			found = 1;
		}
	}

	if (NULL != item)
		vc_item_release(item);

	vc_try_unlock();

	ret = (1 == found ? SUCCEED : FAIL);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s cache_used:%d", __function_name, zbx_result_string(ret),
			cache_used);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_get_statistics                                            *
 *                                                                            *
 * Purpose: retrieves usage cache statistics                                  *
 *                                                                            *
 * Parameters: stats     - [OUT] the cache usage statistics                   *
 *                                                                            *
 * Return value:  SUCCEED - the cache statistics were retrieved successfully  *
 *                FAIL    - failed to retrieve cache statistics               *
 *                          (cache was not initialized)                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_vc_get_statistics(zbx_vc_stats_t *stats)
{
	if (NULL == vc_cache)
		return FAIL;

	vc_try_lock();

	stats->hits = vc_cache->hits;
	stats->misses = vc_cache->misses;
	stats->low_memory = vc_cache->low_memory;

	stats->total_size = vc_mem->total_size;
	stats->free_size = vc_mem->free_size;

	vc_try_unlock();

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_history_record_vector_destroy                                *
 *                                                                            *
 * Purpose: destroys value vector and frees resources allocated for it        *
 *                                                                            *
 * Parameters: vector    - [IN] the value vector                              *
 *                                                                            *
 * Comments: Use this function to destroy value vectors created by            *
 *           zbx_vc_get_values_by_* functions.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_vector_destroy(zbx_vector_history_record_t *vector, int value_type)
{
	if (NULL != vector->values)
	{
		vc_history_record_vector_clean(vector, value_type);
		zbx_vector_history_record_destroy(vector);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_history_record_clear                                         *
 *                                                                            *
 * Purpose: frees resources allocated by a cached value                       *
 *                                                                            *
 * Parameters: value      - [IN] the cached value to clear                    *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_clear(zbx_history_record_t *value, int value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_free(value->value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			vc_history_logfree(value->value.log);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_history_value2str                                         *
 *                                                                            *
 * Purpose: converts history value to string format                           *
 *                                                                            *
 * Parameters: buffer     - [OUT] the output buffer                           *
 *             size       - [IN] the output buffer size                       *
 *             value      - [IN] the value to convert                         *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_vc_history_value2str(char *buffer, size_t size, history_value_t *value, int value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(buffer, size, ZBX_FS_DBL, value->dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(buffer, size, ZBX_FS_UI64, value->ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_strlcpy(buffer, value->str, size);
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_strlcpy(buffer, value->log->value, size);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_lock                                                      *
 *                                                                            *
 * Purpose: locks the cache for batch usage                                   *
 *                                                                            *
 * Comments: Use zbx_vc_lock()/zbx_vc_unlock to explicitly lock/unlock cache  *
 *           for batch usage. The cache is automatically locked during every  *
 *           API call using the cache unless it was explicitly locked with    *
 *           zbx_vc_lock() function by the same process.                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_vc_lock(void)
{
	zbx_mutex_lock(&vc_lock);
	vc_locked = 1;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_unlock                                                    *
 *                                                                            *
 * Purpose: unlocks cache after it has been locked with zbx_vc_lock()         *
 *                                                                            *
 * Comments: See zbx_vc_lock() function.                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_vc_unlock(void)
{
	vc_locked = 0;
	zbx_mutex_unlock(&vc_lock);
}
