/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
 * The historical data storage mode is adapted for every item depending on request the
 * type and size. Currently the following storage modes are supported:
 *  1) lastvalue storage mode
 *     Stores only the last and previous item values. Does not support data fetching
 *     from database.
 *
 *  2) history storage mode.
 *     Stores item's history data from the largest request (+timeshift) range to the
 *     current time. Automatically reads from history data from DB whenever request
 *     exceeds cached value range.
 *
 * When cache runs out of memory to store new items it enters in low memory mode.
 * In low memory mode cache continues to function as before with few restrictions:
 *   1) items that weren't accessed during the last day are removed from cache.
 *   2) items with history storage mode and worst hits/values ratio might be removed
 *      from cache to free space.
 *   3) only items with few values are added to cache.
 *
 * The low memory mode can't be turned off - it will persist until server is rebooted.
 *
 */

/* the period of low memory warning messages */
#define ZBX_VC_LOW_MEMORY_WARNING_PERIOD	(60 * 5)

static zbx_mem_info_t	*vc_mem = NULL;

static ZBX_MUTEX	vc_lock;

/* flag indicating that the cache was explicitly locked by this process */
static int	vc_locked = 0;

/* the value cache size */
extern zbx_uint64_t	CONFIG_VALUE_CACHE_SIZE;

ZBX_MEM_FUNC_IMPL(__vc, vc_mem)

#define VC_STRPOOL_INIT_SIZE	(1000)
#define VC_ITEMS_INIT_SIZE	(1000)

/* the minimum number of bytes that will be freed when freeing cache space */
#define VC_MIN_FREE_SPACE	(ZBX_KIBIBYTE * 16)

/* the data chunk used to store data fragment for history storage mode */
typedef struct _zbx_vc_chunk_t
{
	/* a pointer to the previous chunk or NULL if this is the tail chunk */
	struct _zbx_vc_chunk_t	*prev;

	/* a pointer to the nest chunk or NULL if this is the head chunk */
	struct _zbx_vc_chunk_t	*next;

	/* the index of first (oldest) value in chunk */
	int			first_value;

	/* the index of last (newest) value in chunk */
	int			last_value;

	/* the number of item value slots in chunk */
	int			slots_num;

	/* the item value data */
	zbx_vc_value_t		slots[1];
}
zbx_vc_chunk_t;

/* min/max number number of item history values to store in chunk */

/* the minimum number is calculated so that 3 chunks of 1/2 value count size takes less space */
/* than 2 chunks of 1 value count size                                                        */
#define ZBX_VC_MIN_CHUNK_RECORDS	(1 + (sizeof(zbx_vc_chunk_t) - sizeof(zbx_vc_value_t)) / sizeof(zbx_vc_value_t))

/* the maximum number is calculated so that the chunk size does not exceed 4KB */
#define ZBX_VC_MAX_CHUNK_RECORDS	((4 * ZBX_KIBIBYTE - sizeof(zbx_vc_chunk_t)) / sizeof(zbx_vc_value_t) + 1)

/* data storage modes */
#define ZBX_VC_MODE_LASTVALUE	0
#define ZBX_VC_MODE_HISTORY	1

/* indexes of last and previous values for lastvalue storage mode */
#define ZBX_VC_LAST	0
#define ZBX_VC_PREV	1

/* the the lastvalue storage mode data*/
typedef struct
{
	/* array holding the last and previous values */
	zbx_vc_value_t	values[2];
}
zbx_vc_data_lastvalue_t;

/* the the history storage mode data*/
typedef struct
{
	/* The range of the largest request in seconds.          */
	/* Used to determine if data can be removed from cache.  */
	int		range;

	/* the flag indicating that all data from DB are cached  */
	int		cached_all;

	/* the number of slots in chunk to store the largest     */
	/* request data in a signle chunk                        */
	int		slots_max;

	/* the last (newest) chunk if item history data          */
	zbx_vc_chunk_t	*head;

	/* the first (oldest) chunk if item history data         */
	zbx_vc_chunk_t	*tail;
}
zbx_vc_data_history_t;

/* the item history data storage */
typedef union
{
	zbx_vc_data_history_t	history;

	zbx_vc_data_lastvalue_t	lastvalue;
}
zbx_vc_data_t;

/* the value cache item data */
typedef struct
{
	/* the item id */
	zbx_uint64_t	itemid;

	/* the item value type */
	unsigned char	value_type;

	/* the data storage mode  - see ZBX_VC_MODE_* defines      */
	unsigned char	mode;

	/* The total number of item values in cache.               */
	/* Used to evaluate if the item must be dropped from cache */
	/* in low memory situation.                                */
	int		values_total;

	/* The last time when item cache was accessed.             */
	/* Used to evaluate if the item must be dropped from cache */
	/* in low memory situation.                                */
	int		last_accessed;

	/* The number of cache hits for this item.                 */
	/* Used to evaluate if the item must be dropped from cache */
	/* in low memory situation.                                */
	zbx_uint64_t	hits;

	/* the cache data, contents depends on storage mode        */
	zbx_vc_data_t	data;
}
zbx_vc_item_t;

/* The value cache data interface. This structure contains functions */
/* that are used to access item's value cache data independent of    */
/* the item's data storage mode.                                     */
typedef struct
{
	/* initializes data storage */
	int (*init)(zbx_vc_item_t * item, zbx_vector_vc_value_t *values, int seconds, int count, int timestamp,
			const zbx_timespec_t *ts);

	/* checks if the value request (seconds, count, timestamp, ts) can be cached in current storage mode */
	int (*supports_request)(zbx_vc_item_t *item, int seconds, int count, int timestamp, const zbx_timespec_t *ts);

	/* adds values to the cache */
	int (*add_values)(zbx_vc_item_t *item, const zbx_vc_value_t *values, int values_num);

	/* retrieves the specified range of values (seconds, count, timestamp) from cache */
	int (*get_value_range)(zbx_vc_item_t *item, zbx_vector_vc_value_t *values, int seconds, int count,
			int timestamp);

	/* retrieves the specified value (ts) from cache */
	int (*get_value)(zbx_vc_item_t *item, const zbx_timespec_t *ts, zbx_vc_value_t *value, int *found);

	/* retrieves all values from cache */
	void (*get_all_values)(const zbx_vc_item_t *item, zbx_vector_vc_value_t *values);

	/* frees resources allocated for item cache data */
	size_t (*free)(zbx_vc_item_t *item);
}
zbx_vc_idata_t;

/* data interfaces for storage modes */
extern zbx_vc_idata_t	*vc_data_interface[2];

/* use CACHE(item)->function() to access <function> of the current item's storage mode data interface */
#define CACHE(item)	vc_data_interface[item->mode]

#define ZBX_VC_TIME()		time(NULL)

/* the value cache data  */
typedef struct
{
	/* the number of cache hits, used for statistics */
	zbx_uint64_t	hits;

	/* the number of cache misses, used for statistics */
	zbx_uint64_t	misses;

	/* the low memory mode flag */
	int		low_memory;

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

ZBX_VECTOR_IMPL(vc_value, zbx_vc_value_t);

ZBX_VECTOR_DECL(vc_itemweight, zbx_vc_item_weight_t);
ZBX_VECTOR_IMPL(vc_itemweight, zbx_vc_item_weight_t);

/* the value cache */
static zbx_vc_cache_t	*vc_cache = NULL;

/* function prototypes */
static void	vc_value_copy(zbx_vc_value_t* dst, const zbx_vc_value_t* src, int value_type);
static int	vc_value_compare_asc_func(const zbx_vc_value_t *d1, const zbx_vc_value_t *d2);
static int	vc_value_compare_desc_func(const zbx_vc_value_t *d1, const zbx_vc_value_t *d2);

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

/* timestmap, logeventid, severity, source, value */
static void	row2value_log(history_value_t *value, DB_ROW row)
{
	value->log = zbx_malloc(0, sizeof(zbx_history_log_t));

	value->log->timestamp = atoi(row[0]);
	value->log->logeventid = atoi(row[1]);
	value->log->severity = atoi(row[2]);
	value->log->source = zbx_strdup(NULL, row[3]);
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

/*********************************************************************************
 *                                                                               *
 * Function: vc_db_read_values_by_time                                           *
 *                                                                               *
 * Purpose: reads item history data from database                                *
 *                                                                               *
 * Parameters:  itemid        - [IN] the itemid                                  *
 * 		value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs) *
 *              values        - [OUT] the item history data values               *
 *              seconds       - [IN] the time period to read                     *
 *              end_timestamp - [IN] the value timestamp to start reading with   *
 *                                                                               *
 * Return value: SUCCEED - the history data were read successfully               *
 *               FAIL -                                                          *
 *                                                                               *
 * Comments: This function reads all values with timestamps in range:            *
 *             end_timestamp - seconds < <value timestamp> <= end_timestamp      *
 *                                                                               *
 *********************************************************************************/
static int	vc_db_read_values_by_time(zbx_uint64_t itemid, int value_type, zbx_vector_vc_value_t *values,
		int seconds, int end_timestamp)
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
			" where itemid=" ZBX_FS_UI64
				" and clock>%d and clock<=%d",
			table->fields, table->name, itemid, end_timestamp - seconds, end_timestamp);

	result = DBselect("%s", sql);

	zbx_free(sql);

	if (NULL == result)
		goto out;

	while (NULL != (row = DBfetch(result)))
	{
		zbx_vc_value_t	value;

		value.timestamp.sec = atoi(row[0]);
		value.timestamp.ns = atoi(row[1]);
		table->rtov(&value.value, row + 2);

		zbx_vector_vc_value_append_ptr(values, &value);
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
 * Parameters:  itemid          - [IN] the itemid                                   *
 *              value_type      - [IN] the value type (see ITEM_VALUE_TYPE_* defs)  *
 *              count           - [IN] the number of values to read                 *
 *              read_timestamp  - [IN] the value timestamp to start reading with    *
 *              count_timestamp - [IN] the value timestamp to start counting with   *
 *              direct          - [IN] 1 - data is read from DB to directly         *
 *                                         return it  to user.                      *
 *                                     0 - data is read from DB to store in cache   *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL -                                                             *
 *                                                                                  *
 * Comments: this function reads <count> values before <count_timestamp> (including)*
 *           plus all values in range:                                              *
 *             count_timestamp < <value timestamp> <= read_timestamp                *
 *                                                                                  *
 *           When <direct> is set to 0 this function reads <count> values before    *
 *           <timeshift> plus all values with timestamp seconds matching <count>th  *
 *           value timestamp.                                                       *
 *                                                                                  *
 *           To speed up the reading time with huge data loads, data is read by     *
 *           smaller time segments (hours, day, week, month) and the next (larger)  *
 *           time segment is read only if the requested number of values (<count>)  *
 *           is not yet retrieved.                                                  *
 *                                                                                  *
 ************************************************************************************/
static int	vc_db_read_values_by_count(zbx_uint64_t itemid, int value_type, zbx_vector_vc_value_t *values,
		int count, int read_timestamp, int count_timestamp, int direct)
{
	char			*sql = NULL;
	size_t	 		sql_alloc = 0, sql_offset = 0;
	int			last_timestamp = 0, clock_to, clock_from, step = 0, ret = FAIL;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];
	const int		periods[] = {SEC_PER_HOUR, SEC_PER_DAY, SEC_PER_WEEK, SEC_PER_MONTH, 0, -1};

	clock_to = read_timestamp;

	while (-1 != periods[step] && values->values_num < count)
	{
		clock_from = clock_to - periods[step];

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select clock,ns,%s"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock<=%d",
				table->fields, table->name, itemid, clock_to);

		if (clock_from != clock_to)
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock>%d", clock_from);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by clock desc,ns desc");

		if (1 == direct)
			result = DBselectN(sql, count);
		else
			result = DBselect("%s", sql);

		if (NULL == result)
			goto out;

		while (NULL != (row = DBfetch(result)))
		{
			zbx_vc_value_t	value;

			value.timestamp.sec = atoi(row[0]);

			if (values->values_num >= count && value.timestamp.sec != last_timestamp)
				break;

			value.timestamp.ns = atoi(row[1]);
			table->rtov(&value.value, row + 2);

			zbx_vector_vc_value_append_ptr(values, &value);

			if (value.timestamp.sec > count_timestamp)
				count++;

			if (1 != direct)
				last_timestamp = value.timestamp.sec;
		}
		DBfree_result(result);

		clock_to -= periods[step];
		step++;
	}

	ret = SUCCEED;
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
 * 		value_type    - [IN] the value type (see ITEM_VALUE_TYPE_* defs) *
 * 		ts            - [IN] the target timestamp                        *
 *              value         - [OUT] the read value                             *
 *                                                                               *
 * Return value: SUCCEED - the history data were read successfully               *
 *               FAIL - otherwise                                                *
 *                                                                               *
 *********************************************************************************/
static int	vc_db_read_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *ts, zbx_vc_value_t *value)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	int			ret = FAIL, i;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vc_history_table_t	*table = &vc_history_tables[value_type];
	zbx_vector_vc_value_t	values;

	/* first try to find a value matching the target timestamp */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select clock,ns,%s"
			" from %s"
			" where itemid=" ZBX_FS_UI64
				" and clock=%d and ns=%d",
			table->fields, table->name, itemid, ts->sec, ts->ns);

	result = DBselect("%s", sql);

	zbx_free(sql);

	if (NULL != (row = DBfetch(result)))
	{
		value->timestamp.sec = atoi(row[0]);
		value->timestamp.ns = atoi(row[1]);
		table->rtov(&value->value, row + 2);
		ret = SUCCEED;
	}
	else
	{
		/* if failed to find a value matching target timestamp - */
		/* find the older value closest to the timestamp         */

		zbx_vc_value_vector_create(&values);
		vc_db_read_values_by_count(itemid, value_type, &values, 1, ts->sec, ts->sec, 0);
		zbx_vector_vc_value_sort(&values, (zbx_compare_func_t)vc_value_compare_desc_func);

		for (i = 0; i < values.values_num; i++)
		{
			if ((values.values[i].timestamp.sec == ts->sec && values.values[i].timestamp.ns <= ts->ns) ||
					values.values[i].timestamp.sec < ts->sec)
			{
				vc_value_copy(value, &values.values[0], value_type);
				ret = SUCCEED;
			}
		}

		zbx_vc_value_vector_destroy(&values, value_type);
	}

	DBfree_result(result);

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
	return ZBX_DEFAULT_STRING_HASH_FUNC(data + REFCOUNT_FIELD_SIZE);
}

static int	vc_strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp(d1 + REFCOUNT_FIELD_SIZE, d2 + REFCOUNT_FIELD_SIZE);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_value_compare_asc_func                                        *
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
static int	vc_value_compare_asc_func(const zbx_vc_value_t *d1, const zbx_vc_value_t *d2)
{
	if (d1->timestamp.sec == d2->timestamp.sec)
		return d1->timestamp.ns - d2->timestamp.ns;

	return d1->timestamp.sec - d2->timestamp.sec;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_value_compare_desc_func                                       *
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
 *           order                                                            *
 *                                                                            *
 ******************************************************************************/
static int	vc_value_compare_desc_func(const zbx_vc_value_t *d1, const zbx_vc_value_t *d2)
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
	if (d1->weight > d2->weight)
		return 1;

	if (d1->weight < d2->weight)
		return -1;

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
static void	vc_history_logfree(zbx_history_log_t *log)
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
static zbx_history_log_t	*vc_history_logdup(const zbx_history_log_t *log)
{
	zbx_history_log_t	*plog;

	plog = zbx_malloc(NULL, sizeof(zbx_history_log_t));

	plog->timestamp = log->timestamp;
	plog->logeventid = log->logeventid;
	plog->severity = log->severity;
	plog->source = (NULL == log->source ? NULL : zbx_strdup(NULL, log->source));
	plog->value = zbx_strdup(NULL, log->value);

	return plog;
}

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
		item->hits += hits;

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
	static int	last_warning_time = 0;
	int		now;

	now = ZBX_VC_TIME();

	if (now - last_warning_time > ZBX_VC_LOW_MEMORY_WARNING_PERIOD)
	{
		last_warning_time = now;

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
 *           vc_free_space() attempts to free at least VC_MIN_FREE_SPACE      *
 *           bytes of space to avoid free space request spam.                 *
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

	/* reserve at least VC_MIN_FREE_SPACE bytes to avoid spamming with free space requests */
	if (space < VC_MIN_FREE_SPACE)
		space = VC_MIN_FREE_SPACE;

	/* first remove items with the last accessed time older than a day */
	zbx_hashset_iter_reset(&vc_cache->items, &iter);

	while (NULL != (item = zbx_hashset_iter_next(&iter)))
	{
		if (source_item != item && item->last_accessed < timestamp)
		{
			freed += CACHE(item)->free(item) + sizeof(zbx_vc_item_t);
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
		/* items with lastvalue storage mode in cache                   */
		if (source_item != item && ZBX_VC_MODE_LASTVALUE != item->mode)
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
		freed += CACHE(item)->free(item) + sizeof(zbx_vc_item_t);
		zbx_hashset_remove(&vc_cache->items, item);
	}
	zbx_vector_vc_itemweight_destroy(&items);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_value_copy                                                    *
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
static void	vc_value_copy(zbx_vc_value_t* dst, const zbx_vc_value_t* src, int value_type)
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
 * Function: vc_value_vector_append                                           *
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
static void	vc_value_vector_append(zbx_vector_vc_value_t *vector, int value_type, zbx_vc_value_t *value)
{
	zbx_vc_value_t	record;

	vc_value_copy(&record, value, value_type);
	zbx_vector_vc_value_append_ptr(vector, &record);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_value_vector_copy                                             *
 *                                                                            *
 * Purpose: copies cache value array into value vector                        *
 *                                                                            *
 * Parameters:  vector     - [OUT] the target vector                          *
 *              value_type - [IN] the value type                              *
 *              values     - [IN] the array to copy                           *
 *              values_num - [IN] the number of values in the array           *
 *                                                                            *
 * Comments: Additional memory is allocated to store string, text and log     *
 *           value contents. This memory must be freed by the caller.         *
 *           zbx_vc_value_vector_destroy() function properly frees the        *
 *           value vector together with memory allocated to store value       *
 *           contents.                                                        *
 *                                                                            *
 ******************************************************************************/
static void	vc_value_vector_copy(zbx_vector_vc_value_t *vector, int value_type,
		const zbx_vc_value_t *values, int values_num)
{
	int	i;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			for (i = 0; i < values_num; i++)
			{
				zbx_vc_value_t	value;

				value.value.str = zbx_strdup(NULL, values[i].value.str);
				value.timestamp = values[i].timestamp;
				zbx_vector_vc_value_append_ptr(vector, &value);
			}

			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = 0; i < values_num; i++)
			{
				zbx_vc_value_t	value;

				value.value.log = vc_history_logdup(values[i].value.log);
				value.timestamp = values[i].timestamp;
				zbx_vector_vc_value_append_ptr(vector, &value);
			}

			break;
		default:
			zbx_vector_vc_value_reserve(vector, vector->values_num + values_num);
			memcpy(vector->values + vector->values_num, values, sizeof(zbx_vc_value_t) * values_num);
			vector->values_num += values_num;
	}
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

	return ptr + REFCOUNT_FIELD_SIZE;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_strfree                                                  *
 *                                                                            *
 * Purpose: removes string from cache string pool                             *
 *                                                                            *
 * Parameters: str   - [IN] the string to remove                              *
 *                                                                            *
 * Return value: The number of bytes freed                                    *
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
	void	*ptr = str - REFCOUNT_FIELD_SIZE;
	size_t	freed = 0;

	if (0 == --(*(uint32_t *)ptr))
	{
		freed = strlen(str) + REFCOUNT_FIELD_SIZE + 1;
		zbx_hashset_remove(&vc_cache->strpool, ptr);
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
 *                not enough space  in cache.                                 *
 *                                                                            *
 * Comments: Cache memory is allocated to store the log value. If the         *
 *           allocation fails this function attempts to free the required     *
 *           space in cache by calling vc_release_space() and tries again.    *
 *           If it still fails then a NULL value is returned.                 *
 *                                                                            *
 ******************************************************************************/
static zbx_history_log_t	*vc_item_logdup(zbx_vc_item_t *item, const zbx_history_log_t *log)
{
	zbx_history_log_t	*plog = NULL;

	if (NULL == (plog = vc_item_malloc(item, sizeof(zbx_history_log_t))))
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
	if (NULL != plog->source)
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
 * Return value: The number of bytes freed                                    *
 *                                                                            *
 * Comments: Note - only logs created with vc_item_logdup() function must     *
 *           be freed with vc_item_logfree().                                 *
 *                                                                            *
 ******************************************************************************/
static size_t	vc_item_logfree(zbx_history_log_t *log)
{
	size_t	freed = 0;

	if (NULL != log->source)
		freed += vc_item_strfree(log->source);

	if (NULL != log->value)
		freed += vc_item_strfree(log->value);

	__vc_mem_free_func(log);

	return freed + sizeof(zbx_history_log_t);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_value_copy                                               *
 *                                                                            *
 * Purpose: copies history value to item cache memory                         *
 *                                                                            *
 * Parameters: item       - [IN] the target item                              *
 *             dst        - [OUT] a pointer to the destination value. The     *
 *                          destination value must be previously allocated in *
 *                          cache memory.                                     *
 *             src        - [IN] a pointer to the source value                *
 *                                                                            *
 * Comments: Additional cache memory is allocated to store string, text and   *
 *           log value contents. This memory must be freed by the caller.     *
 *                                                                            *
 ******************************************************************************/
static int	vc_item_value_copy(zbx_vc_item_t *item, zbx_vc_value_t *dst, const zbx_vc_value_t* src)
{
	dst->timestamp = src->timestamp;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (NULL == (dst->value.str = vc_item_strdup(item, src->value.str)))
				return FAIL;
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (NULL == (dst->value.log = vc_item_logdup(item, src->value.log)))
				return FAIL;
			break;
		default:
			dst->value = src->value;
	}

	return SUCCEED;
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
 * Return value: the number of bytes freed.                                   *
 *                                                                            *
 ******************************************************************************/
static size_t	vc_item_free_values(zbx_vc_item_t *item, zbx_vc_value_t *values, int first, int last)
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
 * Function: vc_item_reset_cache                                              *
 *                                                                            *
 * Purpose: resets item cache by freeing allocated data and setting cache     *
 *          data to 0.                                                        *
 *                                                                            *
 * Parameters: item    - [IN] the item                                        *
 *                                                                            *
 ******************************************************************************/
static void	vc_item_reset_cache(zbx_vc_item_t *item)
{
	CACHE(item)->free(item);

	/* reset all item data except itemid and value_type which are the first members in zbx_vc_item_t structure */
	memset((void*)item + sizeof(item->itemid) + sizeof(item->value_type), 0,
			sizeof(zbx_vc_item_t) - sizeof(item->itemid) - sizeof(item->value_type));
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_change_value_type                                        *
 *                                                                            *
 * Purpose: changes item value type                                           *
 *                                                                            *
 * Parameters:  item       - [IN] the item                                    *
 *              value_type - [IN] the new value type                          *
 *                                                                            *
 * Comments: Changing item value type means dropping all cached data and      *
 *           reseting item cache parameters.                                  *
 *                                                                            *
 ******************************************************************************/
static void	vc_item_change_value_type(zbx_vc_item_t *item, int value_type)
{
	vc_item_reset_cache(item);
	item->value_type = value_type;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_remove_item                                                   *
 *                                                                            *
 * Purpose: removes item from cache and frees resources allocated for it      *
 *                                                                            *
 * Parameters: item    - [IN] the item                                        *
 *                                                                            *
 * Return value: the size of freed memory done(bytes)                         *
 *                                                                            *
 ******************************************************************************/
static void	vc_remove_item(zbx_vc_item_t *item)
{
	CACHE(item)->free(item);
	zbx_hashset_remove(&vc_cache->items, item);
}

/******************************************************************************
 *                                                                            *
 * Function: vc_add_item                                                      *
 *                                                                            *
 * Purpose: adds a new item to the cache with the requested history data      *
 *                                                                            *
 * Parameters: itemid     - [IN] the item id                                  *
 *             value_type - [IN] the item value type                          *
 *             seconds    - [IN] the time period to retrieve data for         *
 *             count      - [IN] the number of history values to retrieve.    *
 *             timestamp  - [IN] the period end timestamp                     *
 *             ts         - [IN] the value timestamp                          *
 *             values     - [OUT] the cached values                           *
 *                                                                            *
 * Return value:  the prepared item or NULL if item could not be added        *
 *                (not enough memory to store item data)                      *
 *                                                                            *
 * Comments: In low memory mode only items with request data <= 2 are added   *
 *           to the cache.                                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_vc_item_t	*vc_add_item(zbx_uint64_t itemid, int value_type, int seconds, int count,
		int timestamp, const zbx_timespec_t *ts, zbx_vector_vc_value_t *values)
{
	int			ret = FAIL, now;
	zbx_vc_item_t		*item = NULL;

	now = ZBX_VC_TIME();

	/* Read the item values from database (try to get at least 2 records */
	/* to fill the lastvalue cache)                                      */
	if (NULL != ts)
	{
		ret = vc_db_read_values_by_count(itemid, value_type, values, 2, now, ts->sec, 0);
	}
	else if (0 == count)
	{
		ret = vc_db_read_values_by_time(itemid, value_type, values, now - timestamp + seconds, now);
	}
	else
	{
		if (1 == count)
			count++;

		ret = vc_db_read_values_by_count(itemid, value_type, values, count, now, timestamp, 0);
	}

	if (SUCCEED == ret)
	{
		zbx_vector_vc_value_sort(values, (zbx_compare_func_t)vc_value_compare_asc_func);

		/* add item only if working in normal mode or the data to cache can be stored */
		/* in lastvalue storage mode                                                  */
		if (0 == vc_cache->low_memory || 2 >= values->values_num)
		{
			zbx_vc_item_t	new_item = {itemid, value_type, ZBX_VC_MODE_LASTVALUE};

			if (NULL == (item = zbx_hashset_insert(&vc_cache->items, &new_item, sizeof(zbx_vc_item_t))))
				goto out;

			/* find the storage mode sufficient to cache the values */
			while (SUCCEED != CACHE(item)->init(item, values, seconds, count, timestamp, ts))
				item->mode++;
		}
	}
out:
	return item;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_prepare_item                                                  *
 *                                                                            *
 * Purpose: prepares the item for history request                             *
 *                                                                            *
 * Parameters: item       - [IN] the item to prepare                          *
 *             value_type - [IN] the new value type                           *
 *             seconds    - [IN] the time period to retrieve data for         *
 *             count      - [IN] the number of history values to retrieve.    *
 *             timestamp  - [IN] the period end timestamp                     *
 *             ts         - [IN] the value timestamp                          *
 *                                                                            *
 * Return value:  SUCCEED - the item can't handle the history request         *
 *                FAIL    - the item can't handle the request and  the data   *
 *                          must be read from database.                       *
 *                                                                            *
 * Comments: In low memory mode cached items can't change storage mode which  *
 *           is the only possible failure cause.                              *
 *                                                                            *
 ******************************************************************************/
static int	vc_prepare_item(zbx_vc_item_t *item, int value_type, int seconds, int count,
		int timestamp, const zbx_timespec_t *ts)
{
	int			ret = SUCCEED;
	zbx_vector_vc_value_t	values;

	if (item->value_type != value_type)
		vc_item_change_value_type(item, value_type);

	/* check if the request is supported by storage mode mode */
	if (SUCCEED != CACHE(item)->supports_request(item, seconds, count, timestamp, ts))
	{
		if (1 == vc_cache->low_memory)
		{
			/* in low memory mode storage mode should not be changed, */
			/* but item still must be kept in the cache               */
			item = NULL;
			ret = FAIL;
			goto out;
		}

		zbx_vc_value_vector_create(&values);

		CACHE(item)->get_all_values(item, &values);

		/* drop the item cache to ensure that there are no holes left when */
		/* converting to the new storage mode                              */
		CACHE(item)->free(item);

		/* increase mode until find one that supports the request */
		do
		{
			item->mode++;
		}
		while (SUCCEED != CACHE(item)->supports_request(item, seconds, count, timestamp, ts));

		memset(&item->data, 0, sizeof(item->data));

		if (0 != values.values_num)
			ret = CACHE(item)->add_values(item, values.values, values.values_num);

		zbx_vc_value_vector_destroy(&values, value_type);
	}
out:
	if (FAIL == ret && NULL != item)
	{
		/* Remove item from cache if preparation failed. Most probable cause - not enough memory */
		vc_remove_item(item);
		item = NULL;
	}

	return ret;
}

/******************************************************************************************************************
 *                                                                                                                *
 * History storage mode data API                                                                                  *
 *                                                                                                                *
 ******************************************************************************************************************/

/*
 * The history storage mode caches all values from the largest request range to
 * the current time
 *
 * The history storage mode supports requests of any range, but it's not
 * efficient when caching timeshifted requests.
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

static size_t	vch_item_free_cache(zbx_vc_item_t *item);
static size_t	vch_item_free_chunk(zbx_vc_item_t *item, zbx_vc_chunk_t *chunk);

/******************************************************************************
 *                                                                            *
 * Function: vch_get_new_chunk_slot_count                                     *
 *                                                                            *
 * Purpose: calculates optimal number of slots for an item data chunk         *
 *                                                                            *
 * Parameters:  item   - [IN] the item                                        *
 *              nslots - [IN] the number of requested slots                   *
 *                                                                            *
 * Return value: the number of slots for a new item data chunk                *
 *                                                                            *
 ******************************************************************************/
static int	vch_get_new_chunk_slot_count(int nslots)
{
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
	zbx_vc_data_history_t	*data = &item->data.history;
	zbx_vc_chunk_t		*chunk;
	int			chunk_size;

	chunk_size = sizeof(zbx_vc_chunk_t) + sizeof(zbx_vc_value_t) * (nslots - 1);

	if (NULL == (chunk = vc_item_malloc(item, chunk_size)))
		return FAIL;

	memset(chunk, 0, sizeof(zbx_vc_chunk_t));
	chunk->slots_num = nslots;

	chunk->next = insert_before;

	if (NULL == insert_before)
	{
		chunk->prev = data->head;

		if (NULL != data->head)
			data->head->next = chunk;
		else
			data->tail = chunk;

		data->head = chunk;
	}
	else
	{
		chunk->prev = insert_before->prev;
		insert_before->prev = chunk;

		if (data->tail == insert_before)
			data->tail = chunk;
		else
			chunk->prev->next = chunk;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: vc_chunk_find_last_value_before                                  *
 *                                                                            *
 * Purpose: find the index of the last value in chunk with timestamp less or  *
 *          equal to the specified timestamp.                                 *
 *                                                                            *
 * Parameters:  chunk      - [IN] the chunk                                   *
 *              timestamp  - [IN] the target timestamp                        *
 *                                                                            *
 * Return value: the index of the last value in chunk with timestamp less or  *
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
	const zbx_vc_data_history_t	*data = &item->data.history;
	zbx_vc_chunk_t			*chunk = data->head;
	int				index;

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
 * Function: vch_item_copy_values_at_end                                      *
 *                                                                            *
 * Purpose: copies values at the end of the item head chunk                   *
 *                                                                            *
 * Parameters: item    - [IN/OUT] the target item                             *
 *             values  - [IN] the values to copy                              *
 *             nvalues - [IN] the number of values to copy                    *
 *                                                                            *
 * Return value: SUCCEED - the values were copied successfully                *
 *               FAIL    - the value copying failed (not enough space for     *
 *                         string, text or log type data)                     *
 *                                                                            *
 * Comments: This function is used to copy data to cache. The contents of     *
 *           str, text and log type values are stored in cache string pool.   *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_copy_values_at_end(zbx_vc_item_t *item, const zbx_vc_value_t *values, int nvalues)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			i, ret = FAIL, last_value = data->head->last_value;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			for (i = 0; i < nvalues; i++)
			{
				zbx_vc_value_t	*value = &data->head->slots[data->head->last_value + 1];

				if (NULL == (value->value.str = vc_item_strdup(item, values[i].value.str)))
					goto out;

				value->timestamp = values[i].timestamp;
				data->head->last_value++;
			}
			ret = SUCCEED;

			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = 0; i < nvalues; i++)
			{
				zbx_vc_value_t	*value = &data->head->slots[data->head->last_value + 1];

				if (NULL == (value->value.log = vc_item_logdup(item, values[i].value.log)))
					goto out;

				value->timestamp = values[i].timestamp;
				data->head->last_value++;
			}
			ret = SUCCEED;

			break;
		default:
			memcpy(&data->head->slots[data->head->last_value + 1], values,
					nvalues * sizeof(zbx_vc_value_t));
			data->head->last_value += nvalues;
			ret = SUCCEED;

	}
out:
	item->values_total += data->head->last_value - last_value;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_copy_values_at_beginning                                *
 *                                                                            *
 * Purpose: copies values at the beginning of item tail chunk                 *
 *                                                                            *
 * Parameters: item    - [IN/OUT] the target item                             *
 *             values  - [IN] the values to copy                              *
 *             nvalues - [IN] the number of values to copy                    *
 *                                                                            *
 * Return value: SUCCEED - the values were copied successfully                *
 *               FAIL    - the value copying failed (not enough space for     *
 *                         string, text or log type data)                     *
 *                                                                            *
 * Comments: This function is used to copy data to cache. The contents of     *
 *           str, text and log type values are stored in cache string pool.   *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_copy_values_at_beginning(zbx_vc_item_t *item, const zbx_vc_value_t *values, int nvalues)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			i, ret = FAIL, first_value = data->tail->first_value;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			for (i = nvalues - 1; i >= 0; i--)
			{
				zbx_vc_value_t	*value = &data->tail->slots[data->tail->first_value - 1];

				if (NULL == (value->value.str = vc_item_strdup(item, values[i].value.str)))
					goto out;

				value->timestamp = values[i].timestamp;
				data->tail->first_value--;
			}
			ret = SUCCEED;

			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = nvalues - 1; i >= 0; i--)
			{
				zbx_vc_value_t	*value = &data->tail->slots[data->tail->first_value - 1];

				if (NULL == (value->value.log = vc_item_logdup(item, values[i].value.log)))
					goto out;

				value->timestamp = values[i].timestamp;
				data->tail->first_value--;
			}
			ret = SUCCEED;

			break;
		default:
			memcpy(&data->tail->slots[data->tail->first_value - nvalues], values,
					nvalues * sizeof(zbx_vc_value_t));

			data->tail->first_value -= nvalues;
			ret = SUCCEED;

	}
out:
	item->values_total += first_value - data->tail->first_value;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_get_values_from                                         *
 *                                                                            *
 * Purpose: get all item values starting with the specified chunk and index   *
 *                                                                            *
 * Parameters:  item     - [IN] the item                                      *
 *              chunk    - [IN] the starting chunk                            *
 *              index    - [IN] the starting index in the specified chunk     *
 *              values   - [OUT] the output vector                            *
 *                                                                            *
 * Comments: Additional memory is allocated to store string, text and log     *
 *           value contents. This memory must be freed by the caller.         *
 *           zbx_vc_value_vector_destroy() function properly frees the        *
 *           value vector together with memory allocated to store value       *
 *           contents.                                                        *
 *                                                                            *
 ******************************************************************************/
static void	vch_item_get_values_from(const zbx_vc_item_t *item, const zbx_vc_chunk_t *chunk, int index,
		zbx_vector_vc_value_t *values)
{
	while (1)
	{
		while (index <= chunk->last_value)
		{
			zbx_vc_value_t	value;

			vc_value_copy(&value, chunk->slots + index, item->value_type);
			zbx_vector_vc_value_append_ptr(values, &value);
			index++;
		}

		if (NULL == (chunk = chunk->next))
			break;

		index = chunk->first_value;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vc_item_remove_values_after                                      *
 *                                                                            *
 * Purpose: removes all item history values starting with the specified chunk *
 *          and index.                                                        *
 *                                                                            *
 * Parameters:  item     - [IN] the item                                      *
 *              chunk    - [IN] the starting chunk                            *
 *              index    - [IN] the starting index in the specified chunk     *
 *                                                                            *
 ******************************************************************************/
static void	vch_item_remove_values_from(zbx_vc_item_t *item, zbx_vc_chunk_t *chunk, int index)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	zbx_vc_chunk_t		*head;

	/* find the last value to keep in cache (the previous value of the specified chunk/index) */
	if (--index < chunk->first_value)
	{
		if (NULL == chunk->prev)
		{
			/* the specified value is the first value in cache - all data must be dropped */
			vch_item_free_cache(item);
			data->tail = data->head = NULL;
			return;
		}
		chunk = chunk->prev;
		index = chunk->last_value;
	}

	/* free the resources allocated by removable values in the new head chunk */
	vc_item_free_values(item, chunk->slots, index + 1, chunk->last_value);

	/* free the chunks following the new head chunk */
	head = chunk->next;
	while (NULL != head)
	{
		zbx_vc_chunk_t	*next = head->next;

		vch_item_free_chunk(item, head);
		head = next;
	}

	/* set the new head chunk and last value */
	data->head = chunk;
	chunk->last_value = index;
	chunk->next = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_get_values                                              *
 *                                                                            *
 * Purpose: get all item values from cache                                    *
 *                                                                            *
 * Parameters:  item     - [IN] the item                                      *
 *              values   - [OUT] the output vector                            *
 *                                                                            *
 * Comments: Additional memory is allocated to store string, text and log     *
 *           value contents. This memory must be freed by the caller.         *
 *           zbx_vc_value_vector_destroy() function properly frees the        *
 *           value vector together with memory allocated to store value       *
 *           contents.                                                        *
 *                                                                            *
 ******************************************************************************/
static void	vch_item_get_values(const zbx_vc_item_t *item, zbx_vector_vc_value_t *values)
{
	if (NULL != item->data.history.tail)
		vch_item_get_values_from(item, item->data.history.tail, item->data.history.tail->first_value, values);
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
 * Return value:  The number of bytes freed                                   *
 *                                                                            *
 ******************************************************************************/
static size_t	vch_item_free_chunk(zbx_vc_item_t *item, zbx_vc_chunk_t *chunk)
{
	size_t	freed;

	freed = vc_item_free_values(item, chunk->slots, chunk->first_value, chunk->last_value);

	__vc_mem_free_func(chunk);

	return freed + sizeof(zbx_vc_chunk_t) + (chunk->last_value - chunk->first_value) * sizeof(zbx_vc_value_t);
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
	zbx_vc_data_history_t	*data = &item->data.history;

	if (NULL != chunk->next)
		chunk->next->prev = chunk->prev;

	if (NULL != chunk->prev)
		chunk->prev->next = chunk->next;

	if (chunk == data->head)
		data->head = chunk->prev;

	if (chunk == data->tail)
		data->tail = chunk->next;

	vch_item_free_chunk(item, chunk);
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_clean_cache                                              *
 *                                                                            *
 * Purpose: removes item history data that are outside (older) the maximum    *
 *          request range                                                     *
 *                                                                            *
 * Parameters:  item   - [IN] the target item                                 *
 *                                                                            *
 ******************************************************************************/
static void	vch_item_clean_cache(zbx_vc_item_t *item)
{
	zbx_vc_data_history_t	*data = &item->data.history;

	if (0 != data->range)
	{
		zbx_vc_chunk_t	*tail = data->tail;
		zbx_vc_chunk_t	*chunk = tail;
		int		timestamp;

		timestamp = ZBX_VC_TIME() - data->range - SEC_PER_MIN;

		/* try to remove chunks with all history values older than maximum request range */
		while (NULL != chunk && chunk->slots[chunk->last_value].timestamp.sec < timestamp)
		{
			zbx_vc_chunk_t	*next = chunk->next;

			/* Values with the same timestamps (seconds resolution) always should be either   */
			/* kept in cache or removed together. There should not be a case one one of the   */
			/* is in cache and the second is dropped.                                         */
			/* Here we are handling rare case, when the last value of first chunk has the     */
			/* same timestamp (seconds resolution) as the first value in the second chunk.    */
			/* In this case increase the first value index of the next chunk until the first  */
			/* value timestamp is greater.                                                    */

			if (NULL != next && next->slots[next->first_value].timestamp.sec !=
					next->slots[next->last_value].timestamp.sec)
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

		/* reset the cached_all flag if data was removed from cache */
		if (tail != data->tail)
			data->cached_all = 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_add_values_at_end                                       *
 *                                                                            *
 * Purpose: adds item history values at the end of current item's history     *
 *          data                                                              *
 *                                                                            *
 * Parameters:  item   - [IN] the item to add history data to                 *
 *              values - [IN] the item history data values                    *
 *              num    - [IN] the number of history data values to add        *
 *                                                                            *
 * Return value: SUCCEED - the history data values were added successfully    *
 *               FAIL - failed to add history data values (not enough memory) *
 *                                                                            *
 * Comments: In the case of failure the item is removed from cache.           *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_add_values_at_end(zbx_vc_item_t *item, const zbx_vc_value_t *values, int values_num)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			copied = 0, ret = FAIL, count = values_num;
	zbx_vector_vc_value_t	values_ext = {NULL};
	zbx_vc_chunk_t		*head = data->head;

	if (NULL != data->head && 0 < vc_value_compare_asc_func(&data->head->slots[data->head->last_value], values))
	{
		zbx_vc_chunk_t	*chunk;
		int		index, start = 0, now;

		/* To avoid creating potential holes in cache skip adding values with timestamps */
		/* lower than the first value in cache.                                          */
		for (start = 0; start < values_num; start++)
		{
			if (0 >= vc_value_compare_asc_func(&data->tail->slots[data->tail->first_value], &values[start]))
				break;
		}

		if (0 < start)
		{
			/* if some of values were not added to cache, the cached_all flag */
			/* must be reset and the maximum request range adjusted           */
			data->cached_all = 0;

			now = ZBX_VC_TIME();
			if (now - values[start - 1].timestamp.sec <= data->range)
				data->range = now - values[start - 1].timestamp.sec - 1;
		}

		if (start >= values_num)
		{
			ret = SUCCEED;
			goto out;
		}

		zbx_vector_vc_value_create(&values_ext);

		vc_value_vector_copy(&values_ext, item->value_type, values + start, values_num - start);

		/* found the first value that is in the new values timestamp range */
		if (FAIL == vch_item_get_last_value(item, values[0].timestamp.sec, &chunk, &index))
		{
			chunk = data->tail;
			index = chunk->first_value;
		}
		else
		{
			int		iindex = index;
			zbx_vc_chunk_t	*ichunk = chunk;

			while (ichunk->slots[iindex].timestamp.sec == values[0].timestamp.sec)
			{
				index = iindex--;
				if (iindex < ichunk->first_value)
				{
					chunk = ichunk;
					if (NULL == (ichunk = ichunk->prev))
						break;

					iindex = ichunk->last_value;
				}
			}
		}
		vch_item_get_values_from(item, chunk, index, &values_ext);
		vch_item_remove_values_from(item, chunk, index);

		zbx_vector_vc_value_sort(&values_ext, (zbx_compare_func_t)vc_value_compare_asc_func);

		values = values_ext.values;
		count = values_ext.values_num;
	}

	while (count)
	{
		int	copy_slots, nslots = 0;

		/* find the number of free slots on the right side in last (head) chunk */
		if (NULL != data->head)
			nslots = data->head->slots_num - data->head->last_value - 1;

		if (0 == nslots)
		{
			/* When appending values (adding newer data) keep the chunk slot count at the half  */
			/* of max values per request. This way the memory taken by item cache will be:      */
			/*   3 * (<max values per request> * <slot count> + <chunk header size> )           */
			nslots = vch_get_new_chunk_slot_count(data->slots_max / 2 + 1);

			if (FAIL == vch_item_add_chunk(item, nslots, NULL))
				goto out;

			data->head->first_value = 0;
			data->head->last_value = -1;
		}
		/* copy values to chunk */
		copy_slots = MIN(nslots, count);

		if (FAIL == vch_item_copy_values_at_end(item, values + copied, copy_slots))
			goto out;

		copied += copy_slots;
		count -= copy_slots;
	}

	/* try to remove old (unused) chunks if a new chunk was added */
	if (head != data->head)
		vch_item_clean_cache(item);

	ret = SUCCEED;
out:
	if (NULL != values_ext.values)
		zbx_vc_value_vector_destroy(&values_ext, item->value_type);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_item_add_values_at_beginning                                 *
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
 *                                                                            *
 ******************************************************************************/
static int	vch_item_add_values_at_beginning(zbx_vc_item_t *item, const zbx_vc_value_t *values, int values_num)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int 			count = values_num, ret = FAIL;

	while (count)
	{
		int	copy_slots, nslots = 0;

		/* find the number of free slots on the left side in first (tail) chunk */
		if (NULL != data->tail)
			nslots = data->tail->first_value;

		if (0 == nslots)
		{
			/* when inserting before existing data reserve space only to fit the request */
			nslots = vch_get_new_chunk_slot_count(count);

			if (FAIL == vch_item_add_chunk(item, nslots, data->tail))
				goto out;

			data->tail->last_value = nslots - 1;
			data->tail->first_value = nslots;
		}

		/* copy values to chunk */
		copy_slots = MIN(nslots, count);
		count -= copy_slots;

		if (FAIL == vch_item_copy_values_at_beginning(item, values + count, copy_slots))
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
 *                FAIL   - an error occured while trying to cache values      *
 *                                                                            *
 * Comments: This function checks if the requested value range is cached and  *
 *           updates cache from database if necessary.                        *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_cache_values_by_time(zbx_vc_item_t *item, int seconds, int timestamp)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			ret = SUCCEED, update_seconds = 0, update_end, now;
	int			start = timestamp - seconds;

	now = ZBX_VC_TIME();

	/* find if the cache should be updated to cover the required range */
	if (NULL == data->tail)
	{
		update_seconds = now - timestamp + seconds;
		update_end = now;
	}
	else if (start < now - data->range)
	{
		/* We need to get item values before the first cached value,  but not including it.   */
		/* As vc_item_read_values_by_time() function returns data including the end timestamp */
		/* decrement the first timestamp by 1.                                                */
		update_end = data->tail->slots[data->tail->first_value].timestamp.sec - 1;
		update_seconds = update_end - start;
	}

	/* update cache if necessary */
	if (0 < update_seconds && 0 == data->cached_all)
	{
		zbx_vector_vc_value_t	records;

		zbx_vector_vc_value_create(&records);

		if (SUCCEED == (ret = vc_db_read_values_by_time(item->itemid, item->value_type, &records,
				update_seconds, update_end)) && 0 < records.values_num)
		{
			zbx_vector_vc_value_sort(&records, (zbx_compare_func_t)vc_value_compare_asc_func);
			if (SUCCEED == (ret = vch_item_add_values_at_beginning(item, records.values, records.values_num)))
				ret = records.values_num;
		}
		zbx_vc_value_vector_destroy(&records, item->value_type);
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
 *                FAIL   - an error occured while trying to cache values      *
 *                                                                            *
 * Comments: This function checks if the requested number number of values is *
 *           cached and updates cache from database if necessary.             *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_cache_values_by_count(zbx_vc_item_t *item,  int count, int timestamp)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			ret = SUCCEED, cache_records = 0, update_end;

	/* find if the cache should be updated to cover the required count */
	if (NULL != data->head)
	{
		zbx_vc_chunk_t	*chunk;
		int		index;

		if (SUCCEED == vch_item_get_last_value(item, timestamp, &chunk, &index))
		{
			cache_records = index - chunk->last_value;

			do
			{
				cache_records += chunk->last_value - chunk->first_value + 1;
			} while (NULL != (chunk = chunk->prev) && cache_records < count);
		}

		/* We need to get item values before the first cached value,  but not including it.   */
		/* As vc_item_read_values_by_time() function returns data including the end timestamp */
		/* decrement the first timestamp by 1.                                                */
		update_end = data->tail->slots[data->tail->first_value].timestamp.sec - 1;
	}
	else
		update_end = ZBX_VC_TIME();


	/* update cache if necessary */
	if (0 == data->cached_all && cache_records < count)
	{
		zbx_vector_vc_value_t	records;

		zbx_vector_vc_value_create(&records);

		if (SUCCEED == (ret = vc_db_read_values_by_count(item->itemid, item->value_type,
				&records, count - cache_records, update_end, timestamp, 0)) && 0 < records.values_num)
		{
			zbx_vector_vc_value_sort(&records, (zbx_compare_func_t)vc_value_compare_asc_func);
			if (SUCCEED == (ret = vch_item_add_values_at_beginning(item, records.values, records.values_num)))
				ret = records.values_num;
		}
		zbx_vc_value_vector_destroy(&records, item->value_type);
	}

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
static int	vch_item_get_values_by_time(zbx_vc_item_t *item, zbx_vector_vc_value_t *values, int seconds,
		int timestamp)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			ret = SUCCEED, index, now;
	int			start = timestamp - seconds;
	zbx_vc_chunk_t		*chunk;

	now = ZBX_VC_TIME();

	/* Check if maximum request range is not set and all data are cached.  */
	/* Because that indicates there was a count based request with unknown */
	/* range which might be greater than the current request range.        */
	if (0 != data->range || 0 == data->cached_all)
	{
		if (data->range < seconds + now - timestamp)
			data->range = seconds + now - timestamp;
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
			vc_value_vector_append(values, item->value_type, &chunk->slots[index--]);

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
 *                         contains a value of the specified timestamp        *
 *             count     - [IN] the number of history values to retrieve      *
 *             timestamp - [IN] the target timestamp                          *
 *                                                                            *
 * Return value:  SUCCEED - the item history data was retrieved successfully  *
 *                FAIL    - the item history data was not retrieved           *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	vch_item_get_values_by_count(zbx_vc_item_t *item,  zbx_vector_vc_value_t *values, int count,
		int timestamp)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			ret = SUCCEED, index, now;
	zbx_vc_chunk_t		*chunk;

	now = ZBX_VC_TIME();

	if (FAIL == vch_item_get_last_value(item, timestamp, &chunk, &index))
	{
		/* return empty vector with success */
		goto out;
	}

	/* fill the values vector with item history values until the <count> values are read */
	while (values->values_num < count)
	{
		while (index >= chunk->first_value && values->values_num < count)
			vc_value_vector_append(values, item->value_type, &chunk->slots[index--]);

		if (NULL == (chunk = chunk->prev))
			break;

		index = chunk->last_value;
	}

	/* Try setting maximum range only if all requested data was returned.   */
	/* Otherwise the current request range is unknown and can't be compared */
	/* to the maximum request range.                                        */
	if (values->values_num == count)
	{
		if (data->range < now - values->values[values->values_num - 1].timestamp.sec)
			data->range = now - values->values[values->values_num - 1].timestamp.sec;
	}
out:
	if (values->values_num < count)
	{
		/* not enough data in db to fulfill the request */
		data->range = 0;
		data->cached_all = 1;
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
 *                         contains a value of the specified timestamp        *
 *             seconds   - [IN] the time period to retrieve data for          *
 *             count     - [IN] the number of history values to retrieve.     *
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
static int	vch_item_get_value_range(zbx_vc_item_t *item,  zbx_vector_vc_value_t *values, int seconds, int count,
		int timestamp)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			ret, records_read, hits, misses, nslots;

	values->values_num = 0;

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

		if (records_read > count)
			records_read = count;
	}

	nslots = MAX(values->values_num, count);
	if (data->slots_max < nslots)
		data->slots_max = nslots;

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
static int	vch_item_get_value(zbx_vc_item_t *item, const zbx_timespec_t *ts, zbx_vc_value_t *value, int *found)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			index, ret = FAIL, hits = 0, misses = 0, now;
	zbx_vc_chunk_t		*chunk;

	now = ZBX_VC_TIME();
	*found = 0;

	if (NULL == data->tail || data->tail->slots[data->tail->first_value].timestamp.sec > ts->sec)
	{
		/* the requested value is not in cache, request cache update */
		if (FAIL == vch_item_cache_values_by_count(item, 1, ts->sec))
			goto out;

		misses++;
	}
	else
		hits++;

	ret = SUCCEED;

	if (FAIL == vch_item_get_last_value(item, ts->sec, &chunk, &index))
	{
		/* event after cache update the requested value is not there */
		goto out;
	}

	while (chunk->slots[index].timestamp.sec == ts->sec && chunk->slots[index].timestamp.ns > ts->ns)
	{
		if (--index < chunk->first_value)
		{
			if (NULL == (chunk = chunk->prev))
				goto out;

			index = chunk->last_value;
		}
	}

	vc_update_statistics(item, hits, misses);

	vc_value_copy(value, &chunk->slots[index], item->value_type);

	if (1 > data->slots_max)
		data->slots_max = 1;

	if (data->range < now - value->timestamp.sec)
		data->range = now - value->timestamp.sec;

	*found = 1;
out:
	if (0 == *found)
		data->cached_all = 1;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_supports_request                                             *
 *                                                                            *
 * Purpose: checks if history storage mode supports the value request         *
 *                                                                            *
 * Parameters: item       - [IN] the item                                     *
 *             seconds    - [IN] the request time period                      *
 *             count      - [IN] the number of values to get                  *
 *             timestamp  - [IN] the request period end timestamp             *
 *             ts         - [IN] the value timestamp for single request, can  *
 *                          be NULL.                                          *
 *                                                                            *
 * Return value: SUCCEED - the history storage mode supports value request    *
 *               FAIL    -                                                    *
 *                                                                            *
 * Comments: The request parameter priority is as follows:                    *
 *             1) ts (ts != NULL)                                             *
 *             2) count + timestamp (ts == NULL && count != NULL)             *
 *             3) seconds + timestamp (ts == NULL && count == NULL)           *
 *                                                                            *
 ******************************************************************************/
static int	vch_supports_request(zbx_vc_item_t *item, int seconds, int count, int timestamp,
		const zbx_timespec_t *ts)
{
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: vch_init                                                         *
 *                                                                            *
 * Purpose: initializes item cache in history storage mode                    *
 *                                                                            *
 * Parameters: item       - [IN] the item                                     *
 *             values     - [IN] the initial history data                     *
 *             seconds    - [IN] the request time period                      *
 *             count      - [IN] the number of values to get                  *
 *             timestamp  - [IN] the request period end timestamp             *
 *             ts         - [IN] the value timestamp for single request, can  *
 *                                                                            *
 * Return value: SUCCEED - storage mode was initialized successfully          *
 *               FAIL    -                                                    *
 *                                                                            *
 * Comments: If the storage mode can cache the <values> returned by the       *
 *           initial request (<seconds>, <count>, <timestamp>, <ts>), then    *
 *           the item cache is initialized in this storage mode and <values>  *
 *           are added to cache.                                              *
 *                                                                            *
 ******************************************************************************/
static int	vch_init(zbx_vc_item_t *item, zbx_vector_vc_value_t *values, int seconds, int count, int timestamp,
		const zbx_timespec_t *ts)
{
	zbx_vc_data_history_t	*data = &item->data.history;
	int			ret, now;

	memset(data, 0, sizeof(zbx_vc_data_history_t));

	if (0 < values->values_num)
	{
		data->slots_max = values->values_num;

		if (NULL != ts)
		{
			zbx_timespec_t	*first_ts = &values->values->timestamp;

			/* check if the init value vector contains the requested value */
			if ((first_ts->sec == ts->sec && first_ts->ns) > ts->ns || first_ts->sec > ts->sec)
				data->cached_all = 1;
		}
		else if (0 != count)
		{
			int i;

			/* check if the init value vector contains the requested number of values before timestamp */
			for (i = 0; i < values->values_num && 0 < count; i++)
			{
				if (values->values[i].timestamp.sec > timestamp)
					break;

				count--;
			}
			if (0 < count)
				data->cached_all = 1;
		}

		now = ZBX_VC_TIME();

		if (0 != seconds)
		{
			data->range = now - timestamp + seconds;
		}
		else
		{
			/* don't set the range if all data was cached by the initial request */
			if (0 == data->cached_all)
				data->range = now - values->values->timestamp.sec;
		}
	}
	if (SUCCEED == (ret = vch_item_add_values_at_beginning(item, values->values, values->values_num)))
		vc_update_statistics(item, 0, values->values_num);

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
	size_t		freed = 0;

	zbx_vc_chunk_t	*chunk = item->data.history.tail;

	while (NULL != chunk)
	{
		zbx_vc_chunk_t	*next = chunk->next;

		freed += vch_item_free_chunk(item, chunk);
		chunk = next;
	}
	item->values_total = 0;

	return freed;
}

/* the history storage mode data interface */
static zbx_vc_idata_t	vc_data_history_interface =
{
	vch_init,
	vch_supports_request,

	vch_item_add_values_at_end,

	vch_item_get_value_range,
	vch_item_get_value,

	vch_item_get_values,

	vch_item_free_cache
};

/******************************************************************************************************************
 *                                                                                                                *
 * Lastvalue storage mode data API                                                                                *
 *                                                                                                                *
 ******************************************************************************************************************/

/*
 * The lastvalue storage mode caches last and previous item values.
 *
 *  .----------------.
 *  | zbx_vc_cache_t |
 *  |----------------|      .----------------.
 *  | items          |----->| zbx_vc_item_t  |-.
 *  '----------------'      |----------------| |-.
 *                          | last value     | | |
 *                          | previous value | | |
 *                          '----------------' | |
 *                            '----------------' |
 *                              '----------------'
 *
 * The last and previous values are stored directly in cache item to minimize
 * memory usage.
 *
 * The lastvalue storage mode is used when maximum 2 item values must be cached.
 *
 * In the lastvalue storage mode the cache is never updated from database - so
 * supports_request() function fails if the request can't be fully retrieved
 * from cache.
 */

/******************************************************************************
 *                                                                            *
 * Function: vcl_item_add_values                                              *
 *                                                                            *
 * Purpose: adds item history values to the lastvalue storage mode data       *
 *                                                                            *
 * Parameters:  item   - [IN] the item to add history data to                 *
 *              values - [IN] the item history data values                    *
 *              num    - [IN] the number of history data values to add        *
 *                                                                            *
 * Return value: SUCCEED - the history data values were added successfully    *
 *               FAIL - failed to add history data values (not enough memory) *
 *                                                                            *
 * Comments: In the case of failure the item is removed from cache.           *
 *                                                                            *
 ******************************************************************************/
static int	vcl_item_add_values(zbx_vc_item_t *item, const zbx_vc_value_t *values, int values_num)
{
	int 				ret = FAIL, ivalue = values_num - 1, index;
	zbx_vc_data_lastvalue_t		*data = &item->data.lastvalue;

	for (index = ZBX_VC_LAST; index <= ZBX_VC_PREV && 0 <= ivalue; index++)
	{
		if (index >= item->values_total ||
				0 >= vc_value_compare_asc_func(&data->values[index], &values[ivalue]))
		{
			/* if we had prev values before - free it, as it will be overwritten by last */
			if (2 == item->values_total)
				vc_item_free_values(item, data->values, ZBX_VC_PREV, ZBX_VC_PREV);

			/* if we had last value, copy it to prev */
			if (1 == item->values_total && ZBX_VC_LAST == index )
				data->values[ZBX_VC_PREV] = data->values[ZBX_VC_LAST];

			/* copy the value */
			if (FAIL == vc_item_value_copy(item, &data->values[index], &values[ivalue]))
			{
				/* Release cache resources so item can be dropped without memory leaks. */
				if (1 == item->values_total)
					vc_item_free_values(item, data->values, 1 - index, 1 - index);

				goto out;
			}
			item->values_total++;
			ivalue--;
		}
	}
	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vcl_item_get_value_range                                         *
 *                                                                            *
 * Purpose: get item values for the specified range                           *
 *                                                                            *
 * Parameters: item      - [IN] the item                                      *
 *             values    - [OUT] the item history data stored time/value      *
 *                         pairs in undefined order                           *
 *             seconds   - [IN] the time period to retrieve data for          *
 *             count     - [IN] the number of history values to retrieve.     *
 *             timestamp - [IN] the target timestamp                          *
 *                                                                            *
 * Return value:  SUCCEED - the item history data was retrieved successfully  *
 *                FAIL    - the item history data was not retrieved           *
 *                                                                            *
 * Comments: If <count> is set then value range is defined as <count> values  *
 *           before <timestamp>. Otherwise the range is defined as <seconds>  *
 *           seconds before <timestamp>.                                      *
 *                                                                            *
 ******************************************************************************/
static int	vcl_item_get_value_range(zbx_vc_item_t *item,  zbx_vector_vc_value_t *values, int seconds, int count,
		int timestamp)
{
	int				ret = FAIL, i, start = 0;
	zbx_vc_data_lastvalue_t		*data = &item->data.lastvalue;

	while (start < item->values_total && data->values[start].timestamp.sec > timestamp)
		start++;

	if (0 == count)
	{
		/* only count based requests are supported by lastvalue storage mode */
		ret = FAIL;
	}
	else
	{
		for (i = start; i < item->values_total && values->values_num < count; i++)
			vc_value_vector_append(values, item->value_type, &data->values[i]);

		ret = SUCCEED;
	}

	if (SUCCEED == ret)
		vc_update_statistics(item, values->values_num, 0);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vcl_item_get_value                                               *
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
 * Comments: If the requested value was not cached this function fails        *
 *                                                                            *
 ******************************************************************************/
static int	vcl_item_get_value(zbx_vc_item_t *item, const zbx_timespec_t *ts, zbx_vc_value_t *value, int *found)
{
	int				i;
	zbx_vc_data_lastvalue_t		*data = &item->data.lastvalue;

	for (i = 0; i < item->values_total; i++)
	{
		if ((data->values[i].timestamp.sec == ts->sec && data->values[i].timestamp.ns <= ts->ns) ||
				data->values[i].timestamp.sec < ts->sec)
		{
			vc_value_copy(value, &data->values[i], item->value_type);
			vc_update_statistics(item, 1, 0);
			*found = 1;

			goto out;
		}
	}
	*found = 0;
out:
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: vcl_item_get_values                                              *
 *                                                                            *
 * Purpose: get all item values from cache                                    *
 *                                                                            *
 * Parameters:  item     - [IN] the item                                      *
 *              values   - [OUT] the output vector                            *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	vcl_item_get_values(const zbx_vc_item_t *item, zbx_vector_vc_value_t *values)
{
	const zbx_vc_data_lastvalue_t		*data = &item->data.lastvalue;

	/* In lastvalue mode there is no guarantees that values with the same  */
	/* timestamp seconds are no split between cache and DB. The exception  */
	/* is when only one value is stored (meaning there are no more history */
	/* data).                                                              */
	if (1 == item->values_total)
	{
		zbx_vc_value_t	value;

		vc_value_copy(&value, &data->values[0], item->value_type);
		zbx_vector_vc_value_append_ptr(values, &value);
	}

	return;
}

/******************************************************************************
 *                                                                            *
 * Function: vcl_supports_request                                             *
 *                                                                            *
 * Purpose: checks if lastvalue storage mode supports the value request       *
 *                                                                            *
 * Parameters: item       - [IN] the item                                     *
 *             seconds    - [IN] the request time period                      *
 *             count      - [IN] the number of values to get                  *
 *             timestamp  - [IN] the request period end timestamp             *
 *             ts         - [IN] the value timestamp for single request, can  *
 *                          be NULL.                                          *
 *                                                                            *
 * Return value: SUCCEED - the lastvalue storage mode supports value request  *
 *               FAIL    -                                                    *
 *                                                                            *
 * Comments: The request parameter priority is as follows:                    *
 *             1) ts (ts != NULL)                                             *
 *             2) count + timestamp (ts == NULL && count != NULL)             *
 *             3) seconds + timestamp (ts == NULL && count == NULL)           *
 *                                                                            *
 *           The lastvalue storage mode supports only requests that can be    *
 *           completed with cached (last, prev) values.                       *
 *                                                                            *
 ******************************************************************************/
static int	vcl_supports_request(zbx_vc_item_t *item, int seconds, int count, int timestamp,
		const zbx_timespec_t *ts)
{
	int				ret = FAIL, i;
	zbx_vc_data_lastvalue_t		*data = &item->data.lastvalue;

	if (0 == item->values_total)
		goto out;

	if (NULL != ts) /* value request */
	{
		/* check if cache contains the requested value */
		zbx_timespec_t	*first_ts = &data->values[item->values_total - 1].timestamp;

		if ((first_ts->sec == ts->sec && first_ts->ns <= ts->ns) || first_ts->sec < ts->sec)
			ret = SUCCEED;

		goto out;
	}

	/* seconds request is not supported by lastvalue storage mode, check only count requests */
	if (0 != count && 2 >= count)
	{
		if (2 > item->values_total)
		{
			/* lastvalue storage mode always cashes 2 values. The only exception is when */
			/* there are not enough historical data. In this case all data (0-1 records) */
			/* are cached and any request can be processed.                              */
			ret = SUCCEED;
			goto out;
		}

		/* check if the cache contains the requested number of values before timestamp */
		for (i = item->values_total - 1; i >= 0 && 0 < count; i--)
		{
			if (data->values[i].timestamp.sec > timestamp)
				break;

			count--;
		}

		if (0 == count)
			ret = SUCCEED;
	}
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: vcl_init                                                         *
 *                                                                            *
 * Purpose: initializes item cache in history storage mode                    *
 *                                                                            *
 * Parameters: item       - [IN] the item                                     *
 *             values     - [IN] the initial history data                     *
 *             seconds    - [IN] the request time period                      *
 *             count      - [IN] the number of values to get                  *
 *             timestamp  - [IN] the request period end timestamp             *
 *             ts         - [IN] the value timestamp for single request, can  *
 *                                                                            *
 * Return value: SUCCEED - storage mode was initialized successfully          *
 *               FAIL    -                                                    *
 *                                                                            *
 * Comments: If the storage mode can cache the <values> returned by the       *
 *           initial request (<seconds>, <count>, <timestamp>, <ts>), then    *
 *           the item cache is initialized in this storage mode and <values>  *
 *           are added to cache.                                              *
 *                                                                            *
 ******************************************************************************/
static int	vcl_init(zbx_vc_item_t *item, zbx_vector_vc_value_t *values, int seconds, int count, int timestamp,
		const zbx_timespec_t *ts)
{
	int	ret = FAIL;

	/* lastvalue storage mode supports only count based requests and   */
	/* can store only 2 values - last and previous. And there is no    */
	/* point in using lastvalue storage mode for larger count requests */
	/* even if currently only 2 values are returned - when more values */
	/* are added to item it will be switched to history storage mode   */
	/* anyway.                                                         */
	if (0 == seconds && 2 >= count && 2 >= values->values_num)
	{
		if (SUCCEED == (ret = vcl_item_add_values(item, values->values, values->values_num)))
			vc_update_statistics(item, 0, values->values_num);
	}

	return ret;
}


/******************************************************************************
 *                                                                            *
 * Function: vcl_item_free_cache                                              *
 *                                                                            *
 * Purpose: frees resources allocated for item history data                   *
 *                                                                            *
 * Parameters: item    - [IN] the item                                        *
 *                                                                            *
 * Return value: the size of freed memory (bytes)                             *
 *                                                                            *
 ******************************************************************************/
static size_t	vcl_item_free_cache(zbx_vc_item_t *item)
{
	size_t	freed = 0;

	if (0 < item->values_total)
		freed = vc_item_free_values(item, item->data.lastvalue.values, 0, item->values_total - 1);

	return freed;
}

/* the lastvalue storage mode data interface */
static zbx_vc_idata_t	vc_data_lastvalue_interface =
{
	vcl_init,
	vcl_supports_request,

	vcl_item_add_values,

	vcl_item_get_value_range,
	vcl_item_get_value,

	vcl_item_get_values,

	vcl_item_free_cache
};

/******************************************************************************************************************
 *                                                                                                                *
 * Public API                                                                                                     *
 *                                                                                                                *
 ******************************************************************************************************************/

/* the storage mode data interfaces */
zbx_vc_idata_t	*vc_data_interface[] =
{
	&vc_data_lastvalue_interface,
	&vc_data_history_interface
};

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

	zbx_mem_create(&vc_mem, shm_key, ZBX_NO_MUTEX,  CONFIG_VALUE_CACHE_SIZE,
			"value cache size", "ValueCacheSize", 1);

	CONFIG_VALUE_CACHE_SIZE -= size_reserved;

	vc_cache = __vc_mem_malloc_func(vc_cache, sizeof(zbx_vc_cache_t));

	if (NULL == vc_cache)
	{
		zbx_error("cannot allocate value cache header");
		exit(EXIT_FAILURE);
	}
	memset(vc_cache, 0, sizeof(zbx_vc_cache_t));

	zbx_hashset_create_ext(&vc_cache->items, VC_ITEMS_INIT_SIZE, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, __vc_mem_malloc_func, __vc_mem_realloc_func, __vc_mem_free_func);

	if (NULL == vc_cache->items.slots)
	{
		zbx_error("cannot allocate value cache data storage");
		exit(EXIT_FAILURE);
	}

	zbx_hashset_create_ext(&vc_cache->strpool, VC_STRPOOL_INIT_SIZE, vc_strpool_hash_func, vc_strpool_compare_func,
			__vc_mem_malloc_func, __vc_mem_realloc_func, __vc_mem_free_func);

	if (NULL == vc_cache->strpool.slots)
	{
		zbx_error("cannot allocate string pool for value cache data storage");
		exit(EXIT_FAILURE);
	}
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
 *             timestamp  - [IN] the value timestmap                          *
 *             value      - [IN] the value to add                             *
 *                                                                            *
 * Return value:  SUCCEED - the item values were added successfully           *
 *                FAIL    - failed to add item values to cache (not fatal     *
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
		zbx_vc_value_t	record = {*timestamp, *value};

		if (item->value_type != value_type)
			vc_item_change_value_type(item, value_type);

		if (FAIL == (ret = CACHE(item)->add_values(item, &record, 1)))
			vc_remove_item(item);
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
 *             count      - [IN] the number of history values to retrieve.    *
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
int	zbx_vc_get_value_range(zbx_uint64_t itemid, int value_type, zbx_vector_vc_value_t *values, int seconds,
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
		int	i, start = 0;

		item = vc_add_item(itemid, value_type, seconds, count, timestamp, NULL, values);

		/* The values vector contains cache data sorted in ascending order, which  */
		/* might be larger than requested number of values for timeshift and count */
		/* based requests. To convert it in the expected format the following must */
		/* be done:                                                                */
		/*    1) sort it in descending order                                       */
		/*    2) remove values outside (newer) timeshift range                     */
		/*    3) remove values outside (older) count request range                 */

		/* sort it in descending order */
		zbx_vector_vc_value_sort(values, (zbx_compare_func_t)vc_value_compare_desc_func);

		/* release resources of values beyond request period end timestamp */
		while (start < values->values_num && values->values[start].timestamp.sec > timestamp)
		{
			zbx_vc_value_clear(&values->values[start], value_type);
			start++;
		}

		/* Count based requests might have returned more values than requested to */
		/* ensure cache continuity. Cut the overhead.                             */
		if (0 != count && values->values_num > count + start)
		{
			for (i = count + start; i < values->values_num; i++)
				zbx_vc_value_clear(&values->values[i], value_type);

			values->values_num = count + start;
		}

		/* shift the vector downwards if necessary */
		if (0 != start)
		{
			memmove(values->values, &values->values[start],
					(values->values_num - start) * sizeof(zbx_vc_value_t));
			values->values_num -= start;
		}

		ret = SUCCEED;

		goto out;
	}

	if (FAIL == vc_prepare_item(item, value_type, seconds, count, timestamp, NULL))
		goto out;

	ret = CACHE(item)->get_value_range(item, values, seconds, count, timestamp);
out:
	if (FAIL == ret)
	{
		if (NULL != item)
			vc_remove_item(item);

		cache_used = 0;

		if (0 == count)
			ret = vc_db_read_values_by_time(itemid, value_type, values, seconds, timestamp);
		else
			ret = vc_db_read_values_by_count(itemid, value_type, values, count, timestamp, ZBX_VC_TIME(), 1);

		if (SUCCEED == ret)
		{
			zbx_vector_vc_value_sort(values, (zbx_compare_func_t)vc_value_compare_desc_func);
			vc_update_statistics(NULL, 0, values->values_num);
		}
	}
	else
	{
		if (NULL != item)
			item->last_accessed = ZBX_VC_TIME();
	}
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
 *             found      - [OUT] 1 - the value was found, 0 - otherwise      *
 *                                                                            *
 * Return value:  SUCCEED - the item history data was retrieved successfully  *
 *                FAIL    - the item history data was not retrieved           *
 *                                                                            *
 * Comments: Depending on the value type this function might allocate memory  *
 *           to store value data. To free it use zbx_vc_history_value_clear() *
 *           function.                                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_vc_get_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *ts, zbx_vc_value_t *value,
		int *found)
{
	const char	*__function_name = "zbx_vc_get_value";
	zbx_vc_item_t	*item = NULL;
	int 		ret = FAIL, cache_used = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " value_type:%d timestamp:%d.%d",
			__function_name, itemid, value_type, ts->sec, ts->ns);

	vc_try_lock();

	if (NULL == vc_cache)
		goto out;

	if (1 == vc_cache->low_memory)
		vc_warn_low_memory();

	if (NULL == (item = zbx_hashset_search(&vc_cache->items, &itemid)))
	{
		zbx_vector_vc_value_t	values;
		int			i;

		zbx_vector_vc_value_create(&values);

		item = vc_add_item(itemid, value_type, 0, 0, 0, ts, &values);

		/* the values vector contains cache data sorted in ascending order - */
		/* find the target value inside this vector                          */

		for (i = values.values_num - 1; i >= 0; i--)
		{
			zbx_vc_value_t	*cache_value = &values.values[i];

			if (cache_value->timestamp.sec < ts->sec ||
					(cache_value->timestamp.sec == ts->sec && cache_value->timestamp.ns <= ts->ns))
			{
				vc_value_copy(value, cache_value, value_type);
				ret = SUCCEED;
				break;
			}
		}
		zbx_vc_value_vector_destroy(&values, value_type);

		goto finish;
	}

	if (FAIL == vc_prepare_item(item, value_type, 0, 0, 0, ts))
		goto out;

	ret = CACHE(item)->get_value(item, ts, value, found);
out:
	if (FAIL == ret)
	{
		cache_used = 0;
		*found = 0;

		if (SUCCEED == (ret = vc_db_read_value(itemid, value_type, ts, value)))
		{
			vc_update_statistics(NULL, 0, 1);
			*found = 1;
		}
	}
	else
	{
		if (NULL != item)
			item->last_accessed = ZBX_VC_TIME();
	}
finish:
	vc_try_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s cached:%d", __function_name, zbx_result_string(ret), cache_used);

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
	vc_try_lock();

	if (NULL == vc_cache)
		return FAIL;

	stats->hits = vc_cache->hits;
	stats->misses = vc_cache->misses;
	stats->low_memory = vc_cache->low_memory;

	stats->total = vc_mem->total_size;
	stats->used = vc_mem->used_size;

	vc_try_unlock();

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_value_vector_destroy                                      *
 *                                                                            *
 * Purpose: destroys value vector and frees resources allocated for it        *
 *                                                                            *
 * Parameters: vector    - [IN] the value vector                              *
 *                                                                            *
 * Comments: Use this function to destroy value vectors created by            *
 *           zbx_vc_get_values_by_* functions.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_vc_value_vector_destroy(zbx_vector_vc_value_t *vector, int value_type)
{
	int i;

	if (NULL != vector->values)
	{
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
		zbx_vector_vc_value_destroy(vector);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_value_clear                                               *
 *                                                                            *
 * Purpose: frees resources allocated by a cached value                       *
 *                                                                            *
 * Parameters: value      - [IN] the cached value to clear                    *
 *             value_type - [IN] the the history value type                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_vc_value_clear(zbx_vc_value_t *value, int value_type)
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
 *             value_type - [IN] the the history value type                   *
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
