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

#include <zbxcunit.h>

#include "log.h"

#define CUVC_ITEMID_BASE	100000
#define CUVC_ITEMID_FLOAT	100000
#define CUVC_ITEMID_STR		100001
#define CUVC_ITEMID_LOG		100002
#define CUVC_ITEMID_UINT64	100003
#define CUVC_ITEMID_TEXT	100004

/* Free space in value cache.                                       */
/* Commonly this is set during test suite initialization and tested */
/* in the last (cleanup) test of the test suite to check valuecache */
/* shared memory leaks.                                             */
static zbx_uint64_t	cuvc_free_space;

/* The cuvc_time variable together with cuvc_time_func() is used to */
/* manipulate valuecache current time functionality in unit tests.  */
static time_t	cuvc_time;

static time_t	cuvc_time_func(time_t *ptime)
{
	return cuvc_time;
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_write_history                                               *
 *                                                                            *
 * Purpose: writes data into history table                                    *
 *                                                                            *
 * Parameters: itemid      - [IN] the item id                                 *
 *             records     - [IN] the data to write                           *
 *             value_type  - [IN] the item value type                         *
 *                                                                            *
 * Comments: This function is used to prepare history tables for value cache  *
 *           unit tests.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	cuvc_write_history(zbx_uint64_t itemid, zbx_vector_history_record_t *records, int value_type)
{
	int		i, ret = FAIL;
	zbx_db_insert_t	db_insert;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_LOG:
			zbx_db_insert_prepare(&db_insert, "history_log", "itemid", "value", "timestamp", "source",
					"severity", "logeventid", "clock", "ns", NULL);

			for (i = 0; i < records->values_num; i++)
			{
				zbx_history_record_t	*record = &records->values[i];

				zbx_db_insert_add_values(&db_insert, itemid, record->value.log->value,
						record->value.log->timestamp, record->value.log->source,
						record->value.log->severity, record->value.log->logeventid,
						record->timestamp.sec, record->timestamp.ns);
			}
			break;
		case ITEM_VALUE_TYPE_TEXT:
			zbx_db_insert_prepare(&db_insert, "history_text", "itemid", "value", "clock", "ns", NULL);

			for (i = 0; i < records->values_num; i++)
			{
				zbx_history_record_t	*record = &records->values[i];

				zbx_db_insert_add_values(&db_insert, itemid, record->value.str,
						record->timestamp.sec, record->timestamp.ns);

			}
			break;
		case ITEM_VALUE_TYPE_STR:
			zbx_db_insert_prepare(&db_insert, "history_str", "itemid", "value", "clock", "ns", NULL);

			for (i = 0; i < records->values_num; i++)
			{
				zbx_history_record_t	*record = &records->values[i];

				zbx_db_insert_add_values(&db_insert, itemid, record->value.str,
						record->timestamp.sec, record->timestamp.ns);
			}
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_db_insert_prepare(&db_insert, "history", "itemid", "value", "clock", "ns", NULL);

			for (i = 0; i < records->values_num; i++)
			{
				zbx_history_record_t	*record = &records->values[i];

				zbx_db_insert_add_values(&db_insert, itemid, record->value.dbl,
						record->timestamp.sec, record->timestamp.ns);
			}
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_db_insert_prepare(&db_insert, "history_uint", "itemid", "value", "clock", "ns", NULL);

			for (i = 0; i < records->values_num; i++)
			{
				zbx_history_record_t	*record = &records->values[i];

				zbx_db_insert_add_values(&db_insert, itemid, record->value.ui64,
						record->timestamp.sec, record->timestamp.ns);
			}
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unknown value type %d", value_type);
			DBrollback();
			return FAIL;
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (0 == zbx_db_txn_error())
		ret = SUCCEED;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_add_record_float                                            *
 *                                                                            *
 * Purpose: add floating value to history record vector                       *
 *                                                                            *
 ******************************************************************************/
static void	cuvc_add_record_float(zbx_vector_history_record_t *records, double value, int sec, int ns)
{
	zbx_history_record_t	record;

	record.value.dbl = value;
	record.timestamp.sec = sec;
	record.timestamp.ns = ns;

	zbx_vector_history_record_append_ptr(records, &record);
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_add_record_uint64                                           *
 *                                                                            *
 * Purpose: add uint64 value to history record vector                         *
 *                                                                            *
 ******************************************************************************/
static void	cuvc_add_record_uint64(zbx_vector_history_record_t *records, zbx_uint64_t value, int sec, int ns)
{
	zbx_history_record_t	record;

	record.value.ui64 = value;
	record.timestamp.sec = sec;
	record.timestamp.ns = ns;

	zbx_vector_history_record_append_ptr(records, &record);
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_add_record_str                                              *
 *                                                                            *
 * Purpose: add string value to history record vector                         *
 *                                                                            *
 ******************************************************************************/
static void	cuvc_add_record_str(zbx_vector_history_record_t *records, const char *value, int sec, int ns)
{
	zbx_history_record_t	record;

	record.value.str = zbx_strdup(NULL, value);
	record.timestamp.sec = sec;
	record.timestamp.ns = ns;

	zbx_vector_history_record_append_ptr(records, &record);
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_add_record_log                                              *
 *                                                                            *
 * Purpose: add string value to history record vector                         *
 *                                                                            *
 ******************************************************************************/
static void	cuvc_add_record_log(zbx_vector_history_record_t *records, const char *value, int timestamp,
		const char *source, int severity, int logeventid, int sec, int ns)
{
	zbx_history_record_t	record;

	record.value.log = zbx_malloc(NULL, sizeof(zbx_log_value_t));
	record.value.log->value = zbx_strdup(NULL, value);
	record.value.log->timestamp = timestamp;
	record.value.log->source = zbx_strdup(NULL, source);
	record.value.log->severity = severity;
	record.value.log->logeventid = logeventid;
	record.timestamp.sec = sec;
	record.timestamp.ns = ns;

	zbx_vector_history_record_append_ptr(records, &record);
}

typedef struct
{
	zbx_uint64_t	hits;
	zbx_uint64_t	misses;
	zbx_uint64_t	db_queries;
}
cuvc_snapshot_t;

/******************************************************************************
 *                                                                            *
 * Function: cuvc_snapshot                                                    *
 *                                                                            *
 * Purpose: takes value cache statistics snapshot                             *
 *                                                                            *
 ******************************************************************************/
static void	cuvc_snapshot(cuvc_snapshot_t *snapshot)
{
	snapshot->hits = vc_cache->hits;
	snapshot->misses = vc_cache->misses;
	snapshot->db_queries = vc_cache->db_queries;
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_check_records_str                                           *
 *                                                                            *
 * Purpose: compare string history values from vector with arguments          *
 *                                                                            *
 * Parameters: records     - [IN] the values to compare                       *
 *             ...         - [IN] the list values to compare with, ending     *
 *                           with NULL                                        *
 *                                                                            *
 ******************************************************************************/
static void	cuvc_check_records_str(const zbx_vector_history_record_t *records, ...)
{
	int		i;
	va_list		args;
	const char	*value;

	va_start(args, records);

	for (i = 0; i < records->values_num; i++)
	{
		value = va_arg(args, const char *);
		CU_ASSERT_PTR_NOT_NULL_FATAL(value);
		ZBX_CU_ASSERT_STRING_EQ(records->values[i].value.str, value);
	}

	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_check_cache_str                                             *
 *                                                                            *
 * Purpose: compare cached string values with arguments                       *
 *                                                                            *
 * Parameters: records     - [IN] the item                                    *
 *             ...         - [IN] the list values to compare with, ending     *
 *                           with NULL                                        *
 *                                                                            *
 ******************************************************************************/

static void	cuvc_check_cache_str(zbx_vc_item_t *item, ...)
{
	va_list		args;
	const char	*value;
	zbx_vc_chunk_t	*chunk;
	int		index = 0;

	va_start(args, item);

	for (chunk = item->tail, value = va_arg(args, const char *); NULL != value || NULL != chunk;
			value = va_arg(args, const char *))
	{
		CU_ASSERT_PTR_NOT_NULL_FATAL(chunk);
		CU_ASSERT_PTR_NOT_NULL_FATAL(value);

		if (index < chunk->first_value)
			index = chunk->first_value;

		ZBX_CU_ASSERT_STRING_EQ(chunk->slots[index].value.str, value);

		if (++index > chunk->last_value)
		{
			chunk = chunk->next;
			index = 0;
		}
	}

	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_history_record_compare                                      *
 *                                                                            *
 * Purpose: compare two history records                                       *
 *                                                                            *
 ******************************************************************************/
static void	cuvc_history_record_compare(const zbx_history_record_t *h1, const zbx_history_record_t *h2,
		int value_type)
{
	ZBX_CU_ASSERT_INT_EQ(h1->timestamp.sec, h2->timestamp.sec);
	ZBX_CU_ASSERT_INT_EQ(h1->timestamp.ns, h2->timestamp.ns);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			CU_ASSERT_DOUBLE_EQUAL(h1->value.dbl, h2->value.dbl, ZBX_DOUBLE_EPSILON);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			CU_ASSERT_STRING_EQUAL(h1->value.str, h2->value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			CU_ASSERT_STRING_EQUAL(h1->value.log->value, h2->value.log->value);
			CU_ASSERT_STRING_EQUAL(h1->value.log->source, h2->value.log->source);
			ZBX_CU_ASSERT_INT_EQ(h1->value.log->timestamp, h2->value.log->timestamp);
			ZBX_CU_ASSERT_INT_EQ(h1->value.log->severity, h2->value.log->severity);
			ZBX_CU_ASSERT_INT_EQ(h1->value.log->logeventid, h2->value.log->logeventid);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_CU_ASSERT_UINT64_EQ(h1->value.ui64, h2->value.ui64);
			break;
		default:
			CU_FAIL("unknown value type");
	}
}

/******************************************************************************
 *                                                                            *
 * Function: cuvc_generate_records1                                           *
 *                                                                            *
 * Purpose: generate sample set of history records                            *
 *                                                                            *
 ******************************************************************************/
static void	cuvc_generate_records1(zbx_vector_history_record_t *records, int value_type)
{
	switch (value_type)
	{
	case ITEM_VALUE_TYPE_FLOAT:
		cuvc_add_record_float(records, 1005.7, 1005, 700);
		cuvc_add_record_float(records, 1005.5, 1005, 500);
		cuvc_add_record_float(records, 1005.2, 1005, 200);

		cuvc_add_record_float(records, 1004.7, 1004, 700);
		cuvc_add_record_float(records, 1004.5, 1004, 500);
		cuvc_add_record_float(records, 1004.2, 1004, 200);

		cuvc_add_record_float(records, 1003.7, 1003, 700);
		cuvc_add_record_float(records, 1003.5, 1003, 500);
		cuvc_add_record_float(records, 1003.2, 1003, 200);

		cuvc_add_record_float(records, 1002.7, 1002, 700);
		cuvc_add_record_float(records, 1002.5, 1002, 500);
		cuvc_add_record_float(records, 1002.2, 1002, 200);

		cuvc_add_record_float(records, 1001.7, 1001, 700);
		cuvc_add_record_float(records, 1001.5, 1001, 500);
		cuvc_add_record_float(records, 1001.2, 1001, 200);
		break;
	case ITEM_VALUE_TYPE_STR:
		cuvc_add_record_str(records, "1005:700", 1005, 700);
		cuvc_add_record_str(records, "1005:500", 1005, 500);
		cuvc_add_record_str(records, "1005:200", 1005, 200);

		cuvc_add_record_str(records, "1004:700", 1004, 700);
		cuvc_add_record_str(records, "1004:500", 1004, 500);
		cuvc_add_record_str(records, "1004:200", 1004, 200);

		cuvc_add_record_str(records, "1003:700", 1003, 700);
		cuvc_add_record_str(records, "1003:500", 1003, 500);
		cuvc_add_record_str(records, "1003:200", 1003, 200);

		cuvc_add_record_str(records, "1002:700", 1002, 700);
		cuvc_add_record_str(records, "1002:500", 1002, 500);
		cuvc_add_record_str(records, "1002:200", 1002, 200);

		cuvc_add_record_str(records, "1001:700", 1001, 700);
		cuvc_add_record_str(records, "1001:500", 1001, 500);
		cuvc_add_record_str(records, "1001:200", 1001, 200);
		break;
	case ITEM_VALUE_TYPE_LOG:
		cuvc_add_record_log(records, "1005:700", 10057, "1005-7", 100570, 100571, 1005, 700);
		cuvc_add_record_log(records, "1005:500", 10055, "1005-5", 100550, 100551, 1005, 500);
		cuvc_add_record_log(records, "1005:200", 10052, "1005-2", 100520, 100521, 1005, 200);

		cuvc_add_record_log(records, "1004:700", 10047, "1004-7", 100470, 100471, 1004, 700);
		cuvc_add_record_log(records, "1004:500", 10045, "1004-5", 100450, 100451, 1004, 500);
		cuvc_add_record_log(records, "1004:200", 10042, "1004-2", 100420, 100421, 1004, 200);

		cuvc_add_record_log(records, "1003:700", 10037, "1003-7", 100370, 100371, 1003, 700);
		cuvc_add_record_log(records, "1003:500", 10035, "1003-5", 100350, 100351, 1003, 500);
		cuvc_add_record_log(records, "1003:200", 10032, "1003-2", 100320, 100321, 1003, 200);

		cuvc_add_record_log(records, "1002:700", 10027, "1002-7", 100270, 100271, 1002, 700);
		cuvc_add_record_log(records, "1002:500", 10025, "1002-5", 100250, 100251, 1002, 500);
		cuvc_add_record_log(records, "1002:200", 10022, "1002-2", 100220, 100221, 1002, 200);

		cuvc_add_record_log(records, "1001:700", 10017, "1001-7", 100170, 100171, 1001, 700);
		cuvc_add_record_log(records, "1001:500", 10015, "1001-5", 100150, 100151, 1001, 500);
		cuvc_add_record_log(records, "1001:200", 10012, "1001-2", 100120, 100121, 1001, 200);
		break;
	case ITEM_VALUE_TYPE_UINT64:
		cuvc_add_record_uint64(records, 10057, 1005, 700);
		cuvc_add_record_uint64(records, 10055, 1005, 500);
		cuvc_add_record_uint64(records, 10052, 1005, 200);

		cuvc_add_record_uint64(records, 10047, 1004, 700);
		cuvc_add_record_uint64(records, 10045, 1004, 500);
		cuvc_add_record_uint64(records, 10042, 1004, 200);

		cuvc_add_record_uint64(records, 10037, 1003, 700);
		cuvc_add_record_uint64(records, 10035, 1003, 500);
		cuvc_add_record_uint64(records, 10032, 1003, 200);

		cuvc_add_record_uint64(records, 10027, 1002, 700);
		cuvc_add_record_uint64(records, 10025, 1002, 500);
		cuvc_add_record_uint64(records, 10022, 1002, 200);

		cuvc_add_record_uint64(records, 10017, 1001, 700);
		cuvc_add_record_uint64(records, 10015, 1001, 500);
		cuvc_add_record_uint64(records, 10012, 1001, 200);
		break;
	case ITEM_VALUE_TYPE_TEXT:
		cuvc_add_record_str(records, "1005:7", 1005, 700);
		cuvc_add_record_str(records, "1005:5", 1005, 500);
		cuvc_add_record_str(records, "1005:2", 1005, 200);

		cuvc_add_record_str(records, "1004:7", 1004, 700);
		cuvc_add_record_str(records, "1004:5", 1004, 500);
		cuvc_add_record_str(records, "1004:2", 1004, 200);

		cuvc_add_record_str(records, "1003:7", 1003, 700);
		cuvc_add_record_str(records, "1003:5", 1003, 500);
		cuvc_add_record_str(records, "1003:2", 1003, 200);

		cuvc_add_record_str(records, "1002:7", 1002, 700);
		cuvc_add_record_str(records, "1002:5", 1002, 500);
		cuvc_add_record_str(records, "1002:2", 1002, 200);

		cuvc_add_record_str(records, "1001:7", 1001, 700);
		cuvc_add_record_str(records, "1001:5", 1001, 500);
		cuvc_add_record_str(records, "1001:2", 1001, 200);
		break;
	default:
		CU_FAIL_FATAL("unknown value type");
	}
}

/*
 *
 * common suite initialization/cleanup functions
 *
 */

/*
 * empty history
 */

static int	cuvc_init_empty()
{
	return CUE_SUCCESS;
}

static int	cuvc_clean_empty()
{
	return CUE_SUCCESS;
}
/*
 * basic set of all value types
 */
static int	cuvc_init_all_types()
{
	int				i, curet = CUE_SINIT_FAILED;
	zbx_vector_history_record_t	records;

	zbx_vector_history_record_create(&records);

	DBbegin();

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		cuvc_generate_records1(&records, i);

		if (FAIL == cuvc_write_history(CUVC_ITEMID_BASE + i, &records, i))
			goto out;

		vc_history_record_vector_clean(&records, i);
	}

	curet = CUE_SUCCESS;
out:
	DBcommit();

	zbx_vector_history_record_destroy(&records);

	cuvc_free_space = vc_mem->free_size;

	return curet;
}

static int	cuvc_clean_all_types()
{
	int curet = CUE_SCLEAN_FAILED;

	DBbegin();

	DBexecute("delete from history");
	DBexecute("delete from history_str");
	DBexecute("delete from history_log");
	DBexecute("delete from history_uint");
	DBexecute("delete from history_text");

	if (0 == zbx_db_txn_error())
		curet = CUE_SUCCESS;

	DBcommit();

	return curet;
}

/*
 * basic set of string values
 */

static int	cuvc_init_str()
{
	int				curet = CUE_SINIT_FAILED;
	zbx_vector_history_record_t	records;

	zbx_vector_history_record_create(&records);

	DBbegin();

	cuvc_generate_records1(&records, ITEM_VALUE_TYPE_STR);

	if (FAIL != cuvc_write_history(CUVC_ITEMID_STR, &records, ITEM_VALUE_TYPE_STR))
		curet = CUE_SUCCESS;

	DBcommit();

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	cuvc_free_space = vc_mem->free_size;

	return curet;
}

static int	cuvc_add_str(const char *value, zbx_timespec_t *ts)
{
	int				ret;
	zbx_vector_history_record_t	records;

	zbx_vector_history_record_create(&records);

	DBbegin();

	cuvc_add_record_str(&records, value, ts->sec, ts->ns);

	ret = cuvc_write_history(CUVC_ITEMID_STR, &records, ITEM_VALUE_TYPE_STR);

	DBcommit();

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	return ret;
}

static int	cuvc_remove_str(const char *value, zbx_timespec_t *ts)
{
	char	*value_esc;
	DBbegin();

	value_esc = DBdyn_escape_string(value);

	DBexecute("delete from history_str where value='%s' and clock=%d and ns=%d",
			value_esc, ts->sec, ts->ns);

	DBcommit();

	zbx_free(value_esc);

	return SUCCEED;
}

static int	cuvc_clean_str()
{
	int curet = CUE_SCLEAN_FAILED;

	DBbegin();

	DBexecute("delete from history_str");

	if (0 == zbx_db_txn_error())
		curet = CUE_SUCCESS;

	DBcommit();

	return curet;
}

/*
 * basic set of string values in low memory mode
 */

static int	cuvc_init_str_lowmem()
{
	vc_cache->mode = ZBX_VC_MODE_LOWMEM;
	vc_cache->mode_time = time(NULL);
	return cuvc_init_str();
}

static int	cuvc_clean_str_lowmem()
{
	vc_cache->mode = ZBX_VC_MODE_NORMAL;
	vc_cache->mode_time = time(NULL);
	return cuvc_clean_str();
}

static void	cuvc_print_record(zbx_vc_item_t *item, zbx_history_record_t *record)
{
	char	value[4096], *pvalue = value;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(value, sizeof(value), "%f", record->value.dbl);
			break;

		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(value, sizeof(value), ZBX_FS_UI64, record->value.ui64);
			break;

		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			pvalue = record->value.str;
			break;

		case ITEM_VALUE_TYPE_LOG:
			zbx_snprintf(value, sizeof(value), "%d %d %d [%s] %s",
					record->value.log->timestamp, record->value.log->logeventid,
					record->value.log->severity, record->value.log->source,
					record->value.log->value);

	}
	printf("\t\t\t%d.%d %s\n", record->timestamp.sec, record->timestamp.ns, pvalue);
}

static void	cuvc_dump_cache(zbx_vc_item_t *item)
{
	zbx_vc_chunk_t	*chunk = item->tail;

	printf("ITEM DUMP: " ZBX_FS_UI64 " (type=%d, records=%d, recount=%d, hits=" ZBX_FS_UI64,
			item->itemid, item->value_type, item->values_total, item->refcount, item->hits);

	printf(", status=%d, active range=%d, daily range=%d)\n", item->status, item->active_range, item->daily_range);

	while (NULL != chunk)
	{
		int i;

		printf("\tchunk: %d-%d\n", chunk->slots[chunk->first_value].timestamp.sec,
				chunk->slots[chunk->last_value].timestamp.sec);
		printf("\t\trecords: %d-%d\n", chunk->first_value, chunk->last_value);

		for (i = chunk->first_value; i <= chunk->last_value; i++)
		{
			cuvc_print_record(item, &chunk->slots[i]);
		}

		chunk = chunk->next;
	}
	printf("========\n");
}


/*
 * include test suites grouped by functionality
 */
#include "valuecache_get.c"
#include "valuecache_add.c"
#include "valuecache_misc.c"

/******************************************************************************
 *                                                                            *
 * Purpose: Value cache test suite setup                                      *
 *                                                                            *
 ******************************************************************************/
int	ZBX_CU_MODULE(valuecache)
{
	CU_pSuite	suite = NULL;

	/* test suite: get1                                                                         */
	/*   check if all value types are properly cached/returned                                  */
	if (NULL == (suite = CU_add_suite("valuecache storage of all value types", cuvc_init_all_types,
			cuvc_clean_all_types)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_FLOAT", cuvc_suite_get1_test1);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_STR", cuvc_suite_get1_test2);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_LOG", cuvc_suite_get1_test3);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_UINT64", cuvc_suite_get1_test4);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_TEXT", cuvc_suite_get1_test5);
	ZBX_CU_ADD_TEST(suite, "cleanup", cuvc_suite_get1_cleanup);

	/* test suite: get2                                                                         */
	/*   check if the data is being correctly cached and retrieved by time based requests.      */
	if (NULL == (suite = CU_add_suite("valuecache basic time based requests", cuvc_init_str, cuvc_clean_str)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 1004 timestamp", cuvc_suite_get2_test1);
	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 1002 timestamp", cuvc_suite_get2_test2);
	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 1005 timestamp", cuvc_suite_get2_test3);
	ZBX_CU_ADD_TEST(suite, "get 1s interval of values time 2 from 1003 timestamp", cuvc_suite_get2_test4);
	ZBX_CU_ADD_TEST(suite, "get 10s interval of values from 1001 timestamp", cuvc_suite_get2_test5);
	ZBX_CU_ADD_TEST(suite, "get 10s interval of values from 1001 timestamp", cuvc_suite_get2_test6);
	ZBX_CU_ADD_TEST(suite, "cleanup", cuvc_suite_get2_cleanup);

	/* test suite: get3                                                                         */
	/*   check if the data is being correctly cached and retrieved by count based requests.     */
	if (NULL == (suite = CU_add_suite("valuecache basic count based requests", cuvc_init_str, cuvc_clean_str)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, "get 1 value from 1004 timestamp", cuvc_suite_get3_test1);
	ZBX_CU_ADD_TEST(suite, "get 1 value from 1004 timestamp (2)", cuvc_suite_get3_test2);
	ZBX_CU_ADD_TEST(suite, "get 2 values from 1004 timestamp", cuvc_suite_get3_test3);
	ZBX_CU_ADD_TEST(suite, "get 4 values from 1001 timestamp", cuvc_suite_get3_test4);
	ZBX_CU_ADD_TEST(suite, "get 4 values from 1001 timestamp (2)", cuvc_suite_get3_test5);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get3_cleanup);

	/* test suite: get  4                                                                       */
	/*   check  if the data is being correctly cached and retrieved by timestamp based requests */
	if (NULL == (suite = CU_add_suite("valuecache basic timestamp based requests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get value with 1004.900 timestamp", cuvc_suite_get4_test1);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.700 timestamp", cuvc_suite_get4_test2);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.600 timestamp", cuvc_suite_get4_test3);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.500 timestamp", cuvc_suite_get4_test4);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.300 timestamp", cuvc_suite_get4_test5);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.200 timestamp", cuvc_suite_get4_test6);
	ZBX_CU_ADD_TEST(suite, "get value with 1003.000 timestamp", cuvc_suite_get4_test7);
	ZBX_CU_ADD_TEST(suite, "get value with 1002.700 timestamp", cuvc_suite_get4_test8);
	ZBX_CU_ADD_TEST(suite, "get value with 1002.600 timestamp", cuvc_suite_get4_test9);
	ZBX_CU_ADD_TEST(suite, "get value with 1001.000 timestamp", cuvc_suite_get4_test10);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get4_cleanup);

	/* test suite: get5                                                                         */
	/*   if the data is being correctly cached and retrieved by mixed request types             */
	if (NULL == (suite = CU_add_suite("valuecache basic mixed requests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get value with 1005.600 timestamp", cuvc_suite_get5_test1);
	ZBX_CU_ADD_TEST(suite, "get value with 1005.000 timestamp", cuvc_suite_get5_test2);
	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 1003 timestamp", cuvc_suite_get5_test3);
	ZBX_CU_ADD_TEST(suite, "get value with 1003.000 timestamp", cuvc_suite_get5_test4);
	ZBX_CU_ADD_TEST(suite, "get 1 value from 1001 timestamp", cuvc_suite_get5_test5);
	ZBX_CU_ADD_TEST(suite, "get 4 values from 1001 timestamp", cuvc_suite_get5_test6);
	ZBX_CU_ADD_TEST(suite, "get 10s interval of values from 1001 timestamp", cuvc_suite_get5_test7);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get5_cleanup);

	/* test suite: get6                                                                         */
	/*   check if the data is being correctly retrieved by all request types on empty history   */
	/*   tables                                                                                 */
	if (NULL == (suite = CU_add_suite("valuecache empty database requests",
			cuvc_init_empty, cuvc_clean_empty)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 10000000 timestamp", cuvc_suite_get6_test1);
	ZBX_CU_ADD_TEST(suite, "get 1 value from 10000000 timestamp", cuvc_suite_get6_test2);
	ZBX_CU_ADD_TEST(suite, "get value with 10000000.100 timestamp", cuvc_suite_get6_test3);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get6_cleanup);

	/* test suite: get7                                                                         */
	/*   check if the data is being correctly retrieved by all request types when the requested */
	/*   value type differs from the cached value type (item value type has been changed)       */
	if (NULL == (suite = CU_add_suite("valuecache different value type requests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get 3 string values from 1005 timestamp", cuvc_suite_get7_test1);
	ZBX_CU_ADD_TEST(suite, "get 1 text value from 1005 timestamp", cuvc_suite_get7_test2);
	ZBX_CU_ADD_TEST(suite, "get 3 string values from 1005 timestamp (2)", cuvc_suite_get7_test1);
	ZBX_CU_ADD_TEST(suite, "get text value with 1005.500 timestamp", cuvc_suite_get7_test3);
	ZBX_CU_ADD_TEST(suite, "get 3 string values from 1005 timestamp (3)", cuvc_suite_get7_test1);
	ZBX_CU_ADD_TEST(suite, "get 1s interval of text values from 1005 timestamp", cuvc_suite_get7_test4);
	ZBX_CU_ADD_TEST(suite, "get 3 string values from 1005 timestamp (4)", cuvc_suite_get7_test1);
	ZBX_CU_ADD_TEST(suite, "get 1s interval text values from 1004 timestamp", cuvc_suite_get7_test5);
	ZBX_CU_ADD_TEST(suite, "get 1s interval text values from 1004 timestamp (2)", cuvc_suite_get7_test6);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get7_cleanup);

	/* test suite: get8                                                                         */
	/*   check if the data is being correctly retrieved (and not cached) by time based requests */
	/*   in low memory mode                                                                     */
	if (NULL == (suite = CU_add_suite("valuecache basic time based requests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 1004 timestamp", cuvc_suite_get8_test1);
	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 1002 timestamp", cuvc_suite_get8_test2);
	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 1005 timestamp", cuvc_suite_get8_test3);
	ZBX_CU_ADD_TEST(suite, "get 1s interval of values from 1003 timestmap", cuvc_suite_get8_test4);
	ZBX_CU_ADD_TEST(suite, "get 10s interval of values from 1001 timestamp", cuvc_suite_get8_test5);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get8_cleanup);

	/* test suite: get9                                                                         */
	/*   check if the data is being correctly retrieved (and not cached) by count based         */
	/*   requests in low memory mode                                                            */
	if (NULL == (suite = CU_add_suite("valuecache basic count based requests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get 1 value form 1004 timestamp", cuvc_suite_get9_test1);
	ZBX_CU_ADD_TEST(suite, "get 2 values from 1004 timestamp", cuvc_suite_get9_test2);
	ZBX_CU_ADD_TEST(suite, "get 4 values from 1001 timestamp", cuvc_suite_get9_test3);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get9_cleanup);

	/* test suite: get10                                                                         */
	/*   check if the data is being correctly retrieved (and not cached) by timestamp based      */
	/*   requests in low memory mode                                                             */
	if (NULL == (suite = CU_add_suite("valuecache basic timestamp based requests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get value with 1004.900 timestamp", cuvc_suite_get10_test1);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.700 timestamp", cuvc_suite_get10_test2);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.600 timestamp", cuvc_suite_get10_test3);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.500 timestamp", cuvc_suite_get10_test4);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.300 timestamp", cuvc_suite_get10_test5);
	ZBX_CU_ADD_TEST(suite, "get value with 1004.200 timestamp", cuvc_suite_get10_test6);
	ZBX_CU_ADD_TEST(suite, "get value with 1003.000 timestamp", cuvc_suite_get10_test7);
	ZBX_CU_ADD_TEST(suite, "get value with 1002.700 timestamp", cuvc_suite_get10_test8);
	ZBX_CU_ADD_TEST(suite, "get value with 1002.600 timestamp", cuvc_suite_get10_test9);
	ZBX_CU_ADD_TEST(suite, "get value with 1001.000 timestamp", cuvc_suite_get10_test10);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get10_cleanup);

	/* test suite: get11                                                              */
	/*   check if the data is being correctly cached and retrieved by count in time.  */
	if (NULL == (suite = CU_add_suite("valuecache basic count in time", cuvc_init_str, cuvc_clean_str)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, "get 1 value in 500s interval from 2000 timestamp", cuvc_suite_get11_test1);
	ZBX_CU_ADD_TEST(suite, "get 1 value in 100s interval from 1005 timestamp", cuvc_suite_get11_test2);
	ZBX_CU_ADD_TEST(suite, "get 2 values in 1s interval from 1005 timestamp", cuvc_suite_get11_test3);
	ZBX_CU_ADD_TEST(suite, "get 3 values in 1s interval from 1005 timestamp", cuvc_suite_get11_test4);
	ZBX_CU_ADD_TEST(suite, "get 4 values in 1s interval from 1005 timestamp", cuvc_suite_get11_test5);
	ZBX_CU_ADD_TEST(suite, "get 1 value in 1s interval from 1003 timestamp", cuvc_suite_get11_test6);
	ZBX_CU_ADD_TEST(suite, "get 4 values in 2s interval from 1003 timestamp", cuvc_suite_get11_test7);
	ZBX_CU_ADD_TEST(suite, "cleanup", cuvc_suite_get11_cleanup);

	/* test suite: add1                                                                          */
	/*   check if all item value types are correctly added to cache                              */
	if (NULL == (suite = CU_add_suite("valuecache adding of all value types",  cuvc_init_all_types,
			cuvc_clean_all_types)))
	{
		return CU_get_error();
	}
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_FLOAT", cuvc_suite_add1_test1);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_STR", cuvc_suite_add1_test2);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_LOG", cuvc_suite_add1_test3);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_UINT64", cuvc_suite_add1_test4);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_TEXT", cuvc_suite_add1_test5);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_add1_cleanup);

	/* test suite: add2                                                                       */
	/*   check if the value is added to cache in the right location - after cached data,      */
	/*   in the middle of cached data, before cached data                                     */
	if (NULL == (suite = CU_add_suite("valuecache adding value",  cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "add value of not cached item", cuvc_suite_add2_test1);
	ZBX_CU_ADD_TEST(suite, "add value at the end of cached data", cuvc_suite_add2_test2);
	ZBX_CU_ADD_TEST(suite, "add value in the middle of cached data", cuvc_suite_add2_test3);
	ZBX_CU_ADD_TEST(suite, "add value at the beginning of cached data", cuvc_suite_add2_test4);
	ZBX_CU_ADD_TEST(suite, "add value at the beginning of cached data, all data were cached",
			cuvc_suite_add2_test5);
	ZBX_CU_ADD_TEST(suite, "add value after the beginning of cached data, all data were cached",
			cuvc_suite_add2_test6);
	ZBX_CU_ADD_TEST(suite, "add value after the beginning of cached data, check db coverage",
			cuvc_suite_add2_test7);
	ZBX_CU_ADD_TEST(suite, "add value at the beginning of cached data with matching timestamp seconds",
			cuvc_suite_add2_test8);
	ZBX_CU_ADD_TEST(suite, "add value at the beginning of cached data with matching timestamp seconds (2)",
			cuvc_suite_add2_test9);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_add2_cleanup);

	/* test suite: add3                                                                       */
	/*   check if the values are added in low memory situation                                */
	if (NULL == (suite = CU_add_suite("valuecache adding value in low memory mode",  cuvc_init_str,
			cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "add value of not cached item", cuvc_suite_add3_test1);
	ZBX_CU_ADD_TEST(suite, "add value at the end of cached data", cuvc_suite_add3_test2);
	ZBX_CU_ADD_TEST(suite, "add value in the middle of cached data", cuvc_suite_add3_test3);
	ZBX_CU_ADD_TEST(suite, "add value at the beginning of cached data", cuvc_suite_add3_test4);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_add3_cleanup);

	/* test suite: add4                                                                       */
	/*   check  if the value type change is handled correctly                                 */
	if (NULL == (suite = CU_add_suite("valuecache adding value of different type",  cuvc_init_str,
			cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get string value with 1006:000 timestamp", cuvc_suite_add4_test1);
	ZBX_CU_ADD_TEST(suite, "add text value", cuvc_suite_add4_test2);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_add4_cleanup);

	/* test suite: add5                                                                       */
	/*   check if the old data (outside request range) are correctly dropped when current     */
	/*   time is advanced and a new value added to cache                                      */
	if (NULL == (suite = CU_add_suite("valuecache old data cleanup",  cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get value with 1004:000 timestamp", cuvc_suite_add5_test1);
	ZBX_CU_ADD_TEST(suite, "add value beyond the minimum range interval", cuvc_suite_add5_test2);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_add5_cleanup);

	/* test suite: misc  1                                                                     */
	if (NULL == (suite = CU_add_suite("valuecache access synchronization",  cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "add value of different type when item is held by other process", cuvc_suite_misc1_test1);
	ZBX_CU_ADD_TEST(suite, "load the same value range from multiple proceses", cuvc_suite_misc1_test2);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_misc1_cleanup);

	/* test suite: misc2                                                                       */
	if (NULL == (suite = CU_add_suite("valuecache forced cleanup",  cuvc_init_all_types, cuvc_clean_all_types)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "remove items not accessed for a day", cuvc_suite_misc2_test1);
	ZBX_CU_ADD_TEST(suite, "remove less accessed data in low memory mode", cuvc_suite_misc2_test2);
	ZBX_CU_ADD_TEST(suite, "switch back to normal mode after 24h", cuvc_suite_misc2_test3);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_misc2_cleanup);

	/* test suite: misc3                                                                       */
	if (NULL == (suite = CU_add_suite("valuecache range synchronization",  cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get 1000s interval of values from 1000000 timestamp", cuvc_suite_misc3_test1);
	ZBX_CU_ADD_TEST(suite, "get 800s interval of values from +1.1 day timestamp", cuvc_suite_misc3_test2);
	ZBX_CU_ADD_TEST(suite, "get 600s interval of values from +1.1 day timestamp", cuvc_suite_misc3_test3);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_misc3_cleanup);

	return CUE_SUCCESS;
}


