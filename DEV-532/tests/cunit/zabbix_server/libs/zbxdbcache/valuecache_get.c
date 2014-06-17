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

/*
 * The valuecache_get.c file contains value cache basic get request tests:
 * 1) data retrieval of all item value types
 * 2) the caching and retrieval of all request types (count, time and timestamp
 *    based) when requesting uncached, partially cached, not cached and not
 *    existing data.
 * 3) retrieval of all request types (count, time and timestamp based) with
 *    cache working in low memory mode
 * 4) retrieval of all request types (count, time and timestamp based) when
 *    the requested value type is different from the initial request (value
 *    type has been changed for the item)
 *
 * In all test cases after value cache request the following data is checked:
 * 1) data returned by request
 * 2) value cache contents
 * 3) number of database queries made to fulfill the request
 * 4) value cache statistics (hits/misses) increase
 * 5) heap memory leaks
 *
 * Additionaly at the end of every test suite the value cache is checked for
 * shared memory leaks.
 *
 */


/*
 * value cache test suite: get #1
 *
 * This test suite checks if all item value types are correctly cached and
 * retrieved.
 *
 */

static void	cuvc_suite_get1_test_type(int value_type)
{
	int				i, now;
	zbx_vector_history_record_t	expected;
	zbx_vector_history_record_t	actual;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&expected);
	zbx_history_record_vector_create(&actual);

	/* generate the expected values */
	cuvc_generate_records1(&expected, value_type);

	now = time(NULL);

	/* get all history data */
	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(CUVC_ITEMID_BASE + value_type, value_type, &actual, now, 0, now));

	/* check the retrieved data */
	ZBX_CU_ASSERT_INT_EQ_FATAL(actual.values_num, expected.values_num);

	for (i = 0; i < expected.values_num; i++)
		cuvc_history_record_compare(&actual.values[i], &expected.values[i], value_type);

	zbx_history_record_vector_destroy(&actual, value_type);
	zbx_history_record_vector_destroy(&expected, value_type);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_get1_test1()
{
	cuvc_suite_get1_test_type(ITEM_VALUE_TYPE_FLOAT);
}

static void	cuvc_suite_get1_test2()
{
	cuvc_suite_get1_test_type(ITEM_VALUE_TYPE_STR);
}

static void	cuvc_suite_get1_test3()
{
	cuvc_suite_get1_test_type(ITEM_VALUE_TYPE_LOG);
}

static void	cuvc_suite_get1_test4()
{
	cuvc_suite_get1_test_type(ITEM_VALUE_TYPE_UINT64);
}

static void	cuvc_suite_get1_test5()
{
	cuvc_suite_get1_test_type(ITEM_VALUE_TYPE_TEXT);
}

static void	cuvc_suite_get1_testN()
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
 * value cache test suite: get #2
 *
 * This test suite checks if the data is being correctly cached and retrieved
 * by time based requests.
 *
 */

static void	cuvc_suite_get2_test1()
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

static void	cuvc_suite_get2_test2()
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

static void	cuvc_suite_get2_test3()
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

static void	cuvc_suite_get2_test4()
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

static void	cuvc_suite_get2_test5()
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

static void	cuvc_suite_get2_test6()
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

static void	cuvc_suite_get2_testN()
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
 * value cache test suite: get #3
 *
 * This test suite checks if the data is being correctly cached and retrieved
 * by count based requests.
 *
 */
static void	cuvc_suite_get3_test1()
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

static void	cuvc_suite_get3_test2()
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

static void	cuvc_suite_get3_test3()
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

static void	cuvc_suite_get3_test4()
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

static void	cuvc_suite_get3_test5()
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

static void	cuvc_suite_get3_testN()
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
 * value cache test suite: get #4
 *
 * This test suite checks if the data is being correctly cached and retrieved
 * by timestamp based requests.
 *
 */
static void	cuvc_suite_get4_test1()
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

static void	cuvc_suite_get4_test2()
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

static void	cuvc_suite_get4_test3()
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

static void	cuvc_suite_get4_test4()
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

static void	cuvc_suite_get4_test5()
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


static void	cuvc_suite_get4_test6()
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


static void	cuvc_suite_get4_test7()
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

static void	cuvc_suite_get4_test8()
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

static void	cuvc_suite_get4_test9()
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

static void	cuvc_suite_get4_test10()
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

static void	cuvc_suite_get4_testN()
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
 * value cache test suite get: #5
 *
 * This test suite checks if the data is being correctly cached and retrieved
 * by mixed request types.
 *
 */
static void	cuvc_suite_get5_test1()
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

static void	cuvc_suite_get5_test2()
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

static void	cuvc_suite_get5_test3()
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

static void	cuvc_suite_get5_test4()
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

static void	cuvc_suite_get5_test5()
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

static void	cuvc_suite_get5_test6()
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

static void	cuvc_suite_get5_test7()
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

static void	cuvc_suite_get5_testN()
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
 * value cache test suite: get #6
 *
 * This test suite checks if the data is being correctly retrieved
 * by all request types on empty history tables.
 *
 */
static void	cuvc_suite_get6_test1()
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

static void	cuvc_suite_get6_test2()
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

static void	cuvc_suite_get6_test3()
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

static void	cuvc_suite_get6_testN()
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
 * value cache test suite: get #7
 *
 * This test suite checks if the data is being correctly retrieved
 * by all request types when the requested value type differs from
 * the cached value type (item value type has been changed).
 *
 */
static void	cuvc_suite_get7_test1()
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

static void	cuvc_suite_get7_test2()
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

static void	cuvc_suite_get7_test3()
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

static void	cuvc_suite_get7_test4()
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

static void	cuvc_suite_get7_test5()
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

static void	cuvc_suite_get7_test6()
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

static void	cuvc_suite_get7_testN()
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
 * value cache test suite_get #8
 *
 * This test suite checks if the data is being correctly retrieved
 * (and not cached) by time based requests in low memory mode.
 *
 */

static void	cuvc_suite_get8_test1()
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

static void	cuvc_suite_get8_test2()
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

static void	cuvc_suite_get8_test3()
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

static void	cuvc_suite_get8_test4()
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

static void	cuvc_suite_get8_test5()
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

static void	cuvc_suite_get8_testN()
{
	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 * value cache test suite: get #9
 *
 * This test suite checks if the data is being correctly retrieved
 * (and not cached) by count based requests in low memory mode.
 *
 */
static void	cuvc_suite_get9_test1()
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

static void	cuvc_suite_get9_test2()
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

static void	cuvc_suite_get9_test3()
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

static void	cuvc_suite_get9_testN()
{
	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 * value cache test suite_get #10
 *
 * This test suite checks if the data is being correctly retrieved
 * (and not cached) by timestamp based requests in low memory mode.
 *
 */
static void	cuvc_suite_get10_test1()
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

static void	cuvc_suite_get10_test2()
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

static void	cuvc_suite_get10_test3()
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

static void	cuvc_suite_get10_test4()
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

static void	cuvc_suite_get10_test5()
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


static void	cuvc_suite_get10_test6()
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


static void	cuvc_suite_get10_test7()
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

static void	cuvc_suite_get10_test8()
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

static void	cuvc_suite_get10_test9()
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

static void	cuvc_suite_get10_test10()
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

static void	cuvc_suite_get10_testN()
{
	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}



