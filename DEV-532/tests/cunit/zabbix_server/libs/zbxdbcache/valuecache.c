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
 *
 * value cache test suite #1
 *
 */


static void	cuvc_suite1_test_type(int value_type)
{
	int				i, now;
	zbx_vector_history_record_t	recordsin;
	zbx_vector_history_record_t	recordsout;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&recordsin);
	zbx_history_record_vector_create(&recordsout);

	cuvc_generate_records1(&recordsin, value_type);

	now = time(NULL);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(CUVC_ITEMID_BASE + value_type, value_type, &recordsout, now, 0, now));
	ZBX_CU_ASSERT_INT_EQ_FATAL(recordsout.values_num, recordsin.values_num);

	for (i = 0; i < recordsin.values_num; i++)
		cuvc_history_record_compare(&recordsout.values[i], &recordsin.values[i], value_type);

	zbx_history_record_vector_destroy(&recordsout, value_type);
	zbx_history_record_vector_destroy(&recordsin, value_type);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite1_test1()
{
	cuvc_suite1_test_type(ITEM_VALUE_TYPE_FLOAT);
}

static void	cuvc_suite1_test2()
{
	cuvc_suite1_test_type(ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite1_test3()
{
	cuvc_suite1_test_type(ITEM_VALUE_TYPE_LOG);
}

static void	cuvc_suite1_test4()
{
	cuvc_suite1_test_type(ITEM_VALUE_TYPE_UINT64);
}

static void	cuvc_suite1_test5()
{
	cuvc_suite1_test_type(ITEM_VALUE_TYPE_TEXT);
}

static void	cuvc_suite1_testN()
{
	zbx_vc_item_t	*item;
	int		i;

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		zbx_uint64_t	itemid = CUVC_ITEMID_BASE + i;

		item = zbx_hashset_search(&vc_cache->items, &itemid);
		CU_ASSERT_PTR_NOT_NULL_FATAL(item);

		vc_remove_item(item);

		item = zbx_hashset_search(&vc_cache->items, &itemid);
		CU_ASSERT_PTR_NULL(item);
	}

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #2
 *
 */

static void	cuvc_suite2_test1()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:700", "1004:500", "1004:200", NULL);
	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 6);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite2_test2()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 1002));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1002:700", "1002:500", "1002:200", NULL);
	cuvc_check_cache_str(item, "1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 12);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite2_test3()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 1005));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1005:700", "1005:500", "1005:200", NULL);
	cuvc_check_cache_str(item, "1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 12);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite2_test4()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 2, 0, 1003));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 6);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1003:700", "1003:500", "1003:200", "1002:700", "1002:500", "1002:200", NULL);
	cuvc_check_cache_str(item, "1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 12);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite2_test5()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 10, 0, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", "1001:500", "1001:200", NULL);
	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700",
			"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 15);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite2_test6()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 10, 0, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", "1001:500", "1001:200", NULL);
	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700",
			"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 15);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite2_testN()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_remove_item(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #3
 *
 */
static void	cuvc_suite3_test1()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 1, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:700", NULL);
	cuvc_check_cache_str(item, "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 4);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite3_test2()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 1, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:700", NULL);
	cuvc_check_cache_str(item, "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 4);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite3_test3()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 2, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 2);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:700", "1004:500", NULL);
	cuvc_check_cache_str(item, "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 5);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite3_test4()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 4, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", "1001:500", "1001:200", NULL);
	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700",
			"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);
	ZBX_CU_ASSERT_INT_EQ(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 15);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite3_test5()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 4, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", "1001:500", "1001:200", NULL);
	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700",
			"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);
	ZBX_CU_ASSERT_INT_EQ(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 15);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite3_testN()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_remove_item(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #4
 *
 */
static void	cuvc_suite4_test1()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 900};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:700"},
			.timestamp = {.sec = 1004, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 6);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite4_test2()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 700};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:700"},
			.timestamp = {.sec = 1004, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 6);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite4_test3()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 600};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:500"},
			.timestamp = {.sec = 1004, .ns = 500}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 6);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite4_test4()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 500};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:500"},
			.timestamp = {.sec = 1004, .ns = 500}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 6);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite4_test5()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 300};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:200"},
			.timestamp = {.sec = 1004, .ns = 200}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 6);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}


static void	cuvc_suite4_test6()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 200};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:200"},
			.timestamp = {.sec = 1004, .ns = 200}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 6);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}


static void	cuvc_suite4_test7()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1003, 000};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1002:700"},
			.timestamp = {.sec = 1002, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1002:700", "1003:200", "1003:500", "1003:700",
				"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 10);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite4_test8()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1002, 700};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1002:700"},
			.timestamp = {.sec = 1002, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1002:700", "1003:200", "1003:500", "1003:700",
				"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 10);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite4_test9()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1002, 600};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1002:500"},
			.timestamp = {.sec = 1002, .ns = 500}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
				"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 12);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite4_test10()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1001, 000};
	int			found = 0;
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(0, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700",
				"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
				"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 15);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite4_testN()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_remove_item(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #5
 *
 */
static void	cuvc_suite5_test1()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1005, 600};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1005:500"},
			.timestamp = {.sec = 1005, .ns = 500}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 3);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite5_test2()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1005, 100};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:700"},
			.timestamp = {.sec = 1004, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 4);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite5_test3()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 1003));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1003:700", "1003:500", "1003:200", NULL);
	cuvc_check_cache_str(item, "1003:200", "1003:500", "1003:700",
				"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 9);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite5_test4()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1003, 000};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1002:700"},
			.timestamp = {.sec = 1002, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);
	cuvc_check_cache_str(item, "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 10);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite5_test5()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 1, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", NULL);
	cuvc_check_cache_str(item, "1001:700", "1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
				"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 13);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite5_test6()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 4, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", "1001:500", "1001:200", NULL);
	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700", "1002:200", "1002:500", "1002:700",
				"1003:200", "1003:500", "1003:700",
				"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);
	ZBX_CU_ASSERT_INT_EQ(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 15);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite5_test7()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 10, 0, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", "1001:500", "1001:200", NULL);
	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700", "1002:200", "1002:500", "1002:700",
				"1003:200", "1003:500", "1003:700",
				"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);
	ZBX_CU_ASSERT_INT_EQ(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 15);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite5_testN()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_remove_item(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #6
 *
 */
static void	cuvc_suite6_test1()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 10000000));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(records.values_num, 0);
	CU_ASSERT_PTR_NULL(item->tail)
	CU_ASSERT_PTR_NULL(item->head)

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 0);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite6_test2()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_FLOAT;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_FLOAT, &records, 0, 1, 10000000));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 6);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(records.values_num, 0);
	CU_ASSERT_PTR_NULL(item->tail)
	CU_ASSERT_PTR_NULL(item->head)

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);
	ZBX_CU_ASSERT_INT_EQ(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 0);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_FLOAT);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite6_test3()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_UINT64;
	zbx_timespec_t		ts = {10000000, 100};
	int			found = 0;
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_UINT64, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(0, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 6);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	CU_ASSERT_PTR_NULL(item->tail)
	CU_ASSERT_PTR_NULL(item->head)

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);
	ZBX_CU_ASSERT_INT_EQ(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 0);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite6_testN()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid1 = CUVC_ITEMID_STR, itemid2 = CUVC_ITEMID_FLOAT, itemid3 = CUVC_ITEMID_UINT64;

	item = zbx_hashset_search(&vc_cache->items, &itemid1);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);
	vc_remove_item(item);
	item = zbx_hashset_search(&vc_cache->items, &itemid1);
	CU_ASSERT_PTR_NULL(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid2);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);
	vc_remove_item(item);
	item = zbx_hashset_search(&vc_cache->items, &itemid2);
	CU_ASSERT_PTR_NULL(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid3);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);
	vc_remove_item(item);
	item = zbx_hashset_search(&vc_cache->items, &itemid3);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #7
 *
 */
static void	cuvc_suite7_test1()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 3, 1005));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1005:700", "1005:500", "1005:200", NULL);
	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_RELOAD_FIRST);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 3);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite7_test2()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_TEXT, &records, 0, 1, 1005));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(records.values_num, 0);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_TEXT);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite7_test3()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1005, 500};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1005:500"},
			.timestamp = {.sec = 1005, .ns = 500}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_TEXT, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(0, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite7_test4()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_TEXT, &records, 1, 0, 1005));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(records.values_num, 0);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_TEXT);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite7_test5()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_add_record_str(&records, "1004:5", 1004, 500);
	cuvc_write_history(itemid, &records, ITEM_VALUE_TYPE_TEXT);
	zbx_history_record_clear(records.values, ITEM_VALUE_TYPE_TEXT);
	records.values_num = 0;

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_TEXT, &records, 1, 0, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:5", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_TEXT);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite7_test6()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_TEXT, &records, 1, 0, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:5", NULL);
	cuvc_check_cache_str(item, "1004:5", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_NE(item->range, 0);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 1);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_TEXT);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite7_testN()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_remove_item(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	DBbegin();
	DBexecute("delete from history_text");
	ZBX_CU_ASSERT_INT_EQ(zbx_db_txn_error(), SUCCEED);
	DBcommit();

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #8
 *
 */

static void	cuvc_suite8_test1()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:700", "1004:500", "1004:200", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite8_test2()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 1002));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1002:700", "1002:500", "1002:200", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite8_test3()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 1005));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1005:700", "1005:500", "1005:200", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite8_test4()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 2, 0, 1003));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 6);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1003:700", "1003:500", "1003:200", "1002:700", "1002:500", "1002:200", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite8_test5()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 10, 0, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", "1001:500", "1001:200", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite8_testN()
{
	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #9
 *
 */
static void	cuvc_suite9_test1()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 1, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:700", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite9_test2()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 2, 1004));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 2);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1004:700", "1004:500", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite9_test3()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 0, 4, 1001));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1001:700", "1001:500", "1001:200", NULL);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite9_testN()
{
	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 *
 * value cache test suite #10
 *
 */
static void	cuvc_suite10_test1()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 900};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:700"},
			.timestamp = {.sec = 1004, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite10_test2()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 700};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:700"},
			.timestamp = {.sec = 1004, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite10_test3()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 600};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:500"},
			.timestamp = {.sec = 1004, .ns = 500}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite10_test4()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 500};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:500"},
			.timestamp = {.sec = 1004, .ns = 500}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite10_test5()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 300};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:200"},
			.timestamp = {.sec = 1004, .ns = 200}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}


static void	cuvc_suite10_test6()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1004, 200};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1004:200"},
			.timestamp = {.sec = 1004, .ns = 200}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}


static void	cuvc_suite10_test7()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1003, 000};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1002:700"},
			.timestamp = {.sec = 1002, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite10_test8()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1002, 700};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1002:700"},
			.timestamp = {.sec = 1002, .ns = 700}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite10_test9()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1002, 600};
	int			found = 0;
	zbx_history_record_t	expected = {
			.value = {.str = "1002:500"},
			.timestamp = {.sec = 1002, .ns = 500}
	};
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(1, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 1);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	cuvc_history_record_compare(&actual, &expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_clear(&actual, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite10_test10()
{
	cuvc_snapshot_t		s1, s2;
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid = CUVC_ITEMID_STR;
	zbx_timespec_t		ts = {1001, 000};
	int			found = 0;
	zbx_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &actual, &found));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ_FATAL(0, found);
	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 2);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite10_testN()
{
	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

int	ZBX_CU_MODULE(valuecache)
{
	CU_pSuite	suite = NULL;

	/* test suite #1 - check if all data types are properly stored/returned */
	if (NULL == (suite = CU_add_suite("valuecache value type storage tets", cuvc_init_all_types, cuvc_clean_all_types)))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "floating values", cuvc_suite1_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "string values", cuvc_suite1_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "log values", cuvc_suite1_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "unsigned values", cuvc_suite1_test4))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "text values", cuvc_suite1_test5))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite1_testN))
		return CU_get_error();

	/* test suite #2 - check basic behavior of time based requests */
	if (NULL == (suite = CU_add_suite("valuecache basic time based request tests", cuvc_init_str, cuvc_clean_str)))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004:1s", cuvc_suite2_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1002:1s", cuvc_suite2_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005:1s", cuvc_suite2_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1003:2s", cuvc_suite2_test4))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:10s", cuvc_suite2_test5))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:10s (2)", cuvc_suite2_test6))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite2_testN))
		return CU_get_error();

	/* test suite #3 - check basic behavior of count based requests */
	if (NULL == (suite = CU_add_suite("valuecache basic count based request tests", cuvc_init_str, cuvc_clean_str)))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004:#1", cuvc_suite3_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004:#1 (2)", cuvc_suite3_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004:#2", cuvc_suite3_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:#4", cuvc_suite3_test4))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:#4 (2)", cuvc_suite3_test5))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite3_testN))
		return CU_get_error();

	/* test suite #4 - check basic behavior of timestamp based single value requests */
	if (NULL == (suite = CU_add_suite("valuecache basic timestamp based request tests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	if (NULL == CU_add_test(suite, "get 1004.9:", cuvc_suite4_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.7:", cuvc_suite4_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.6:", cuvc_suite4_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.5:", cuvc_suite4_test4))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.3:", cuvc_suite4_test5))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.2:", cuvc_suite4_test6))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1003.0:", cuvc_suite4_test7))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1002.7:", cuvc_suite4_test8))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1002.6:", cuvc_suite4_test9))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001.0:", cuvc_suite4_test10))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite4_testN))
		return CU_get_error();

	/* test suite #5 - check basic behavior of mixed requests */
	if (NULL == (suite = CU_add_suite("valuecache basic mixed request tests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	if (NULL == CU_add_test(suite, "get 1005.6:", cuvc_suite5_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005.0:", cuvc_suite5_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1003:1s", cuvc_suite5_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1003.0:", cuvc_suite5_test4))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:#1:", cuvc_suite5_test5))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:#4:", cuvc_suite5_test6))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:10s:", cuvc_suite5_test7))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite5_testN))
		return CU_get_error();

	/* test suite #6 - check basic behavior of mixed requests */
	if (NULL == (suite = CU_add_suite("valuecache empty database request tests",
			cuvc_init_empty, cuvc_clean_empty)))
	{
		return CU_get_error();
	}

	if (NULL == CU_add_test(suite, "get 10000000:1s:", cuvc_suite6_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 10000000:#1:", cuvc_suite6_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 10000000.1:", cuvc_suite6_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite6_testN))
		return CU_get_error();

	/* test suite #7 - check basic behavior of mixed requests */
	if (NULL == (suite = CU_add_suite("valuecache different value type request tests",
			cuvc_init_str, cuvc_clean_str)))
	{
		return CU_get_error();
	}

	if (NULL == CU_add_test(suite, "get 1005:#3 [string]", cuvc_suite7_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005:#1 [text]", cuvc_suite7_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005:#3 [string] (2)", cuvc_suite7_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005.5: [text]", cuvc_suite7_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005:#3 [string] (3)", cuvc_suite7_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005:1s [text]", cuvc_suite7_test4))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005:#3 [string] (4)", cuvc_suite7_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004:1s [text]", cuvc_suite7_test5))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004:1s [text] (2)", cuvc_suite7_test6))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite7_testN))
		return CU_get_error();

	/* test suite #8 - check basic behavior of time based requests in low memory mode */
	if (NULL == (suite = CU_add_suite("valuecache basic time based request tests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	if (NULL == CU_add_test(suite, "get 1004:1s", cuvc_suite8_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1002:1s", cuvc_suite8_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1005:1s", cuvc_suite8_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1003:2s", cuvc_suite8_test4))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:10s", cuvc_suite8_test5))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite8_testN))
		return CU_get_error();

	/* test suite #9 - check basic behavior of count based requests in low memory mode */
	if (NULL == (suite = CU_add_suite("valuecache basic count based request tests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	if (NULL == CU_add_test(suite, "get 1004:#1", cuvc_suite9_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004:#2", cuvc_suite9_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001:#4", cuvc_suite9_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite9_testN))
		return CU_get_error();

	/* test suite #10 - check basic behavior of timestamp based requests in low memory mode */
	if (NULL == (suite = CU_add_suite("valuecache basic timestamp based request tests in low memory mode",
			cuvc_init_str_lowmem, cuvc_clean_str_lowmem)))
	{
		return CU_get_error();
	}

	if (NULL == CU_add_test(suite, "get 1004.9:", cuvc_suite10_test1))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.7:", cuvc_suite10_test2))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.6:", cuvc_suite10_test3))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.5:", cuvc_suite10_test4))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.3:", cuvc_suite10_test5))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1004.2:", cuvc_suite10_test6))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1003.0:", cuvc_suite10_test7))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1002.7:", cuvc_suite10_test8))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1002.6:", cuvc_suite10_test9))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "get 1001.0:", cuvc_suite10_test10))
		return CU_get_error();

	if (NULL == CU_add_test(suite, "remove items", cuvc_suite10_testN))
		return CU_get_error();

	return CUE_SUCCESS;
}


