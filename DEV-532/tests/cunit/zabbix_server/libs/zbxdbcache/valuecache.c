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

static zbx_uint64_t	cuvc_free_space;

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
	zbx_uint64_t	id = 1;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_LOG:
			zbx_db_insert_prepare(&db_insert, "history_log", "id", "itemid", "value", "timestamp", "source",
					"severity", "logeventid", "clock", "ns", NULL);

			for (i = 0; i < records->values_num; i++)
			{
				zbx_history_record_t	*record = &records->values[i];

				zbx_db_insert_add_values(&db_insert, id++, itemid, record->value.log->value,
						record->value.log->timestamp, record->value.log->source,
						record->value.log->severity, record->value.log->logeventid,
						record->timestamp.sec, record->timestamp.ns);
			}
			break;
		case ITEM_VALUE_TYPE_TEXT:
			zbx_db_insert_prepare(&db_insert, "history_text", "id", "itemid", "value", "clock", "ns", NULL);

			for (i = 0; i < records->values_num; i++)
			{
				zbx_history_record_t	*record = &records->values[i];

				zbx_db_insert_add_values(&db_insert, id++, itemid, record->value.str,
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
 * Function: cuvc_check_records_str                                           *
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

	va_start(args, item);

	for (chunk = item->tail; NULL != chunk; chunk = chunk->next)
	{
		int i;

		for (i = chunk->first_value; i <= chunk->last_value; i++)
		{
			value = va_arg(args, const char *);
			CU_ASSERT_PTR_NOT_NULL_FATAL(value);
			ZBX_CU_ASSERT_STRING_EQ(chunk->slots[i].value.str, value);
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
	vc_cache->low_memory = 1;
	return cuvc_init_str();
}

static int	cuvc_clean_str_lowmem()
{
	vc_cache->low_memory = 0;
	return cuvc_clean_str();
}

/*
 * include test suites grouped by functionality
 */
#include "valuecache_get.c"

int	ZBX_CU_MODULE(valuecache)
{
	CU_pSuite	suite = NULL;

	/* test suite: get #1                                                                       */
	/*   check if all value types are properly cached/returned                                  */
	if (NULL == (suite = CU_add_suite("valuecache value type storage tests", cuvc_init_all_types,
			cuvc_clean_all_types)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_FLOAT", cuvc_suite_get1_test1);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_STR", cuvc_suite_get1_test2);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_LOG", cuvc_suite_get1_test3);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_UINT64", cuvc_suite_get1_test4);
	ZBX_CU_ADD_TEST(suite, "ITEM_VALUE_TYPE_TEXT", cuvc_suite_get1_test5);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get1_testN);

	/* test suite: get #2                                                                       */
	/*   check if the data is being correctly cached and retrieved by time based requests.      */
	if (NULL == (suite = CU_add_suite("valuecache basic time based request tests", cuvc_init_str, cuvc_clean_str)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, "get time 1 from 1004", cuvc_suite_get2_test1);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1002", cuvc_suite_get2_test2);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1005", cuvc_suite_get2_test3);
	ZBX_CU_ADD_TEST(suite, "get time 2 from 1003", cuvc_suite_get2_test4);
	ZBX_CU_ADD_TEST(suite, "get time 10 from 1001", cuvc_suite_get2_test5);
	ZBX_CU_ADD_TEST(suite, "get time 10 from 1001", cuvc_suite_get2_test6);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get2_testN);

	/* test suite: get #3                                                                       */
	/*   check if the data is being correctly cached and retrieved by count based requests.     */
	if (NULL == (suite = CU_add_suite("valuecache basic count based request tests", cuvc_init_str, cuvc_clean_str)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, "get count 1 from 1004", cuvc_suite_get3_test1);
	ZBX_CU_ADD_TEST(suite, "get count 1 from 1004 #2", cuvc_suite_get3_test2);
	ZBX_CU_ADD_TEST(suite, "get count 2 from 1004", cuvc_suite_get3_test3);
	ZBX_CU_ADD_TEST(suite, "get count 4 from 1001", cuvc_suite_get3_test4);
	ZBX_CU_ADD_TEST(suite, "get count 4 from 1001 #2", cuvc_suite_get3_test5);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get3_testN);

	/* test suite: get #4                                                                       */
	/*   check  if the data is being correctly cached and retrieved by timestamp based requests */
	if (NULL == (suite = CU_add_suite("valuecache basic timestamp based request tests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get timestamp 1004.900", cuvc_suite_get4_test1);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1004.700:", cuvc_suite_get4_test2);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1004.600:", cuvc_suite_get4_test3);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1004.500", cuvc_suite_get4_test4);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1004.300", cuvc_suite_get4_test5);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1004.200", cuvc_suite_get4_test6);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1003.000", cuvc_suite_get4_test7);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1002.700", cuvc_suite_get4_test8);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1002.600", cuvc_suite_get4_test9);
	ZBX_CU_ADD_TEST(suite, "get timestmap 1001.000", cuvc_suite_get4_test10);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get4_testN);

	/* test suite: get #5                                                                       */
	/*   if the data is being correctly cached and retrieved by mixed request types             */
	if (NULL == (suite = CU_add_suite("valuecache basic mixed request tests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get timestamp 1005.600", cuvc_suite_get5_test1);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1005.000", cuvc_suite_get5_test2);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1003", cuvc_suite_get5_test3);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1003.000", cuvc_suite_get5_test4);
	ZBX_CU_ADD_TEST(suite, "get count 1 from 1001", cuvc_suite_get5_test5);
	ZBX_CU_ADD_TEST(suite, "get count 4 from 1001", cuvc_suite_get5_test6);
	ZBX_CU_ADD_TEST(suite, "get time 10 from 1001", cuvc_suite_get5_test7);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get5_testN);

	/* test suite: get #6                                                                       */
	/*   check if the data is being correctly retrieved by all request types on empty history   */
	/*   tables                                                                                 */
	if (NULL == (suite = CU_add_suite("valuecache empty database request tests",
			cuvc_init_empty, cuvc_clean_empty)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get time 1 from 10000000", cuvc_suite_get6_test1);
	ZBX_CU_ADD_TEST(suite, "get count 1 from 10000000", cuvc_suite_get6_test2);
	ZBX_CU_ADD_TEST(suite, "get timestamp 10000000.100", cuvc_suite_get6_test3);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get6_testN);

	/* test suite: get #7                                                                       */
	/*   check if the data is being correctly retrieved by all request types when the requested */
	/*   value type differs from the cached value type (item value type has been changed)       */
	if (NULL == (suite = CU_add_suite("valuecache different value type request tests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get count 3 from 1005 of type STR", cuvc_suite_get7_test1);
	ZBX_CU_ADD_TEST(suite, "get count 1 from 1005 of type TEXT", cuvc_suite_get7_test2);
	ZBX_CU_ADD_TEST(suite, "get count 3 from 1005 of type STR #2", cuvc_suite_get7_test1);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1005.500 of type TEXT", cuvc_suite_get7_test3);
	ZBX_CU_ADD_TEST(suite, "get count 3 from 1005 of type STR #3", cuvc_suite_get7_test1);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1005 of type TEXT", cuvc_suite_get7_test4);
	ZBX_CU_ADD_TEST(suite, "get count 3 from 1005 of type STR #4", cuvc_suite_get7_test1);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1004 of type TEXT", cuvc_suite_get7_test5);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1004 of type TEXT #2", cuvc_suite_get7_test6);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get7_testN);

	/* test suite: get #8                                                                       */
	/*   check if the data is being correctly retrieved (and not cached) by time based requests */
	/*   in low memory mode                                                                     */
	if (NULL == (suite = CU_add_suite("valuecache basic time based request tests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get time 1 from 1004", cuvc_suite_get8_test1);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1002", cuvc_suite_get8_test2);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1005", cuvc_suite_get8_test3);
	ZBX_CU_ADD_TEST(suite, "get time 1 from 1003", cuvc_suite_get8_test4);
	ZBX_CU_ADD_TEST(suite, "get time 10 from 1001", cuvc_suite_get8_test5);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get8_testN);

	/* test suite: get #9                                                                       */
	/*   check if the data is being correctly retrieved (and not cached) by count based         */
	/*   requests in low memory mode                                                            */
	if (NULL == (suite = CU_add_suite("valuecache basic count based request tests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get count 1 form 1004", cuvc_suite_get9_test1);
	ZBX_CU_ADD_TEST(suite, "get count 2 from 1004", cuvc_suite_get9_test2);
	ZBX_CU_ADD_TEST(suite, "get count 4 from 1001", cuvc_suite_get9_test3);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get9_testN);

	/* test suite: get #10                                                                       */
	/*   check if the data is being correctly retrieved (and not cached) by timestamp based      */
	/*   requests in low memory mode                                                             */
	if (NULL == (suite = CU_add_suite("valuecache basic timestamp based request tests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	ZBX_CU_ADD_TEST(suite, "get timestamp 1004.900", cuvc_suite_get10_test1);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1004.700", cuvc_suite_get10_test2);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1004.600", cuvc_suite_get10_test3);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1004.500", cuvc_suite_get10_test4);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1004.300", cuvc_suite_get10_test5);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1004.200", cuvc_suite_get10_test6);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1003.000", cuvc_suite_get10_test7);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1002.700", cuvc_suite_get10_test8);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1002.600", cuvc_suite_get10_test9);
	ZBX_CU_ADD_TEST(suite, "get timestamp 1001.000", cuvc_suite_get10_test10);
	ZBX_CU_ADD_TEST(suite, "remove items", cuvc_suite_get10_testN);

	return CUE_SUCCESS;
}


