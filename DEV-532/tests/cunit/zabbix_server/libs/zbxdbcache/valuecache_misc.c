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
 * value cache test suite: misc1
 *
 *
 */

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7} (STR)
 *
 * get_value(100001, STR, 1005.0)
 *   returned:
 *     {1004.7}
 *   cached:
 *     [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7} (STR)
 *
 * addref(10001)
 *
 * get_value(100001, TEXT, 1005.0)
 *   returned:
 *     {}
 *   cached:
 *     [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7} (STR, remove pending)
 *
 * release(10001)
 *   cached:
 *
 */
static void	cuvc_suite_misc1_test1()
{
	zbx_history_record_t		record;
	zbx_timespec_t			ts = {1005, 0};
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT_FATAL(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));
	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_item_addref(item);

	CU_ASSERT_FATAL(FAIL == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_TEXT, &ts, &record));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);
	ZBX_CU_ASSERT_INT_EQ(item->state, ZBX_ITEM_STATE_REMOVE_PENDING);

	vc_item_release(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7} (STR)
 *
 * get_value(100001, STR, 1004.999)
 *   returned:
 *     {1004.7}
 *   cached:
 *     [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 *
 * vch_item_add_values_at_tail(100001, STR, {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7,
 *                                            1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *   cached:
 *     [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *               1005.2, 1005.5, 1005.7}
 *
 * remove(10001)
 *   cached:
 *
 */
static void	cuvc_suite_misc1_test2()
{
	zbx_history_record_t		record;
	zbx_timespec_t			ts = {1004, 999};
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vector_history_record_t	records;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);
	cuvc_generate_records1(&records, ITEM_VALUE_TYPE_STR);
	zbx_vector_history_record_sort(&records, (zbx_compare_func_t)vc_history_record_compare_asc_func);

	CU_ASSERT_FATAL(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));
	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	vch_item_add_values_at_tail(item, records.values, records.values_num);

	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700",
			"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);


	vc_remove_item(item);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_misc1_cleanup()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 * database:
 *   [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *   [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *   [100003] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *   [100004] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * set_time(1006)
 *
 * get_value(100000, FLOAT, 1004.999)
 * get_value(100001, STR, 1004.999)
 * get_value(100002, LOG, 1004.999)
 * get_value(100003, UINT64, 1004.999)
 * get_value(100004, TEXT, 1004.999)
 *  cached:
 *       [100000] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100002] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100003] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100004] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 *  ; advance time by a day and request 100000, 100001 items to refresh they last access time
 * set_time(1007 + SEC_PER_DAY)
 *
 * get_value(100000, FLOAT, 1004.999)
 * get_value(100001, STR, 1004.999)
 *
 * ; simulate free space request by item 100002 - the accessed items + 100002 item should stay in cache
 * ; while the rest of items should be removed as stale (not accessed during a day) data
 * addref(100002)
 * vc_release_space(100002, 128)
 *  cached:
 *       [100000] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100002] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 * remove(1000000)
 * remove(1000001)
 * remove(1000002)
 *   cached:
 */
static void	cuvc_suite_misc2_test1()
{
	int				i;
	zbx_history_record_t		record;
	zbx_timespec_t			ts = {1004, 999};
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_time = 1006;
	vc_time = cuvc_time_func;

	/* load all types in cache */
	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		itemid = CUVC_ITEMID_BASE + i;

		CU_ASSERT_FATAL(SUCCEED == zbx_vc_get_value(itemid, i, &ts, &record));
		zbx_history_record_clear(&record, i);
	}

	/* advance time */
	cuvc_time += SEC_PER_DAY + 1;

	/* access first value types */
	for (i = 0; i < 2; i++)
	{
		itemid = CUVC_ITEMID_BASE + i;

		CU_ASSERT_FATAL(SUCCEED == zbx_vc_get_value(itemid, i, &ts, &record));
		zbx_history_record_clear(&record, i);
	}

	/* force minimum free request to lower value for tests */
	vc_cache->min_free_request = 128;

	itemid = CUVC_ITEMID_BASE + 2;
	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_item_addref(item);
	vc_release_space(item, 128);

	/* the accessed 2 and reserved item must still be in cache */
	for (i = 0; i < 3; i++)
	{
		itemid = CUVC_ITEMID_BASE + i;

		item = zbx_hashset_search(&vc_cache->items, &itemid);
		CU_ASSERT_PTR_NOT_NULL(item);

		vc_remove_item(item);
	}

	/* the last items should be removed form cache as stale data */
	for (i = 3; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		itemid = CUVC_ITEMID_BASE + i;

		item = zbx_hashset_search(&vc_cache->items, &itemid);
		CU_ASSERT_PTR_NULL(item);
	}

	ZBX_CU_ASSERT_INT_EQ(vc_cache->mode, ZBX_VC_MODE_NORMAL);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_misc2_test2_hittype(int value_type)
{
	zbx_history_record_t	record;
	zbx_uint64_t		itemid = CUVC_ITEMID_BASE + value_type;
	zbx_timespec_t		ts = {1004, 999};

	CU_ASSERT_FATAL(SUCCEED == zbx_vc_get_value(itemid, value_type, &ts, &record));
	zbx_history_record_clear(&record, value_type);
}

/*
 * database:
 *   [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *   [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *   [100003] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *   [100004] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * set_time(1006)
 *
 * get_value(100000, FLOAT, 1004.999)
 * get_value(100001, STR, 1004.999)
 * get_value(100002, LOG, 1004.999)
 * get_value(100003, UINT64, 1004.999)
 * get_value(100004, TEXT, 1004.999)
 *  cached:
 *       [100000] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100002] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100003] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100004] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 * ; generate cache hits for the items
 * get_value(100000, FLOAT, 1004.999) x 10
 * get_value(100001, STR, 1004.999) x 5
 * get_value(100002, LOG, 1004.999) x 20
 * get_value(100003, UINT64, 1004.999) x 15
 * get_value(100004, TEXT, 1004.999) x 25
 *
 * ; simulate free space request by item 100001 - items with last hits should be removed.
 * ; while item 100001 should be unaffected as the free space request source
 * addref(100001)
 * vc_release_space(100001, 128)
 *  cached:
 *       [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100002] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100003] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *       [100004] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 * remove(1000001)
 * remove(1000002)
 * remove(1000003)
 * remove(1000004)
 *   cached:
 */
static void	cuvc_suite_misc2_test2()
{
	int			i, found = 0;
	zbx_history_record_t	record;
	zbx_timespec_t		ts = {1004, 999};
	zbx_vc_item_t		*item;
	zbx_uint64_t		itemid;

	ZBX_CU_LEAK_CHECK_START();

	/* load all types in cache */
	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
		cuvc_suite_misc2_test2_hittype(i);

	/* generate hits */
	for (i = 0; i < 10; i++)
		cuvc_suite_misc2_test2_hittype(ITEM_VALUE_TYPE_FLOAT);

	for (i = 0; i < 5; i++)
		cuvc_suite_misc2_test2_hittype(ITEM_VALUE_TYPE_STR);

	for (i = 0; i < 20; i++)
		cuvc_suite_misc2_test2_hittype(ITEM_VALUE_TYPE_LOG);

	for (i = 0; i < 15; i++)
		cuvc_suite_misc2_test2_hittype(ITEM_VALUE_TYPE_UINT64);

	for (i = 0; i < 25; i++)
		cuvc_suite_misc2_test2_hittype(ITEM_VALUE_TYPE_TEXT);


	/* force minimum free request to lower value for tests */
	vc_cache->min_free_request = 128;

	itemid = CUVC_ITEMID_STR;
	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_item_addref(item);
	vc_release_space(item, 128);

	/* item with less hits should be removed */
	itemid = CUVC_ITEMID_FLOAT;
	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	/* the last items should be left in the cache */
	for (i = 1; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		itemid = CUVC_ITEMID_BASE + i;

		item = zbx_hashset_search(&vc_cache->items, &itemid);
		CU_ASSERT_PTR_NOT_NULL_FATAL(item);

		vc_remove_item(item);
	}

	ZBX_CU_ASSERT_INT_EQ(vc_cache->mode, ZBX_VC_MODE_LOWMEM);
	ZBX_CU_ASSERT_INT_EQ(vc_cache->mode_time, cuvc_time);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_misc2_test3()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_time += SEC_PER_DAY + 1;

	zbx_history_record_vector_create(&records);

	/* first request */
	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1, 0, 1005));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_INT_EQ(vc_cache->mode, ZBX_VC_MODE_NORMAL);
	ZBX_CU_ASSERT_INT_EQ(vc_cache->mode_time, cuvc_time);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 3);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_records_str(&records, "1005:700", "1005:500", "1005:200", NULL);
	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_EQ(item->active_range, SEC_PER_DAY * 2 + 4);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 3);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	vc_remove_item(item);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_misc2_cleanup()
{
	vc_time = time;
	vc_cache->mode = ZBX_VC_MODE_NORMAL;

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * set_time(1000000)
 *
 * get_by_time(100001, STR, 1000, time())
 *  cached:
 *       [100001] {}
 */
static void	cuvc_suite_misc3_test1()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_time = 1000000;
	vc_time = cuvc_time_func;

	zbx_history_record_vector_create(&records);

	/* first request */
	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 1000, 0, cuvc_time));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 1);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(records.values_num, 0);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_EQ(item->active_range, 1000);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 0);
	ZBX_CU_ASSERT_INT_EQ(item->db_cached_from, 999001);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * set_time(time() + 1.1 * SEC_PER_DAY)
 *
 * get_by_time(100001, STR, 800, time())
 *  cached:
 *       [100001] {}
 */
static void	cuvc_suite_misc3_test2()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_time += SEC_PER_DAY * 1.1;

	zbx_history_record_vector_create(&records);

	/* first request */
	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 800, 0, cuvc_time));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(records.values_num, 0);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_EQ(item->active_range, 1000);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 0);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * set_time(time() + 1.1 * SEC_PER_DAY)
 *
 * get_by_time(100001, STR, 600, time())
 *  cached:
 *       [100001] {}
 */
static void	cuvc_suite_misc3_test3()
{
	cuvc_snapshot_t			s1, s2;
	zbx_vector_history_record_t	records;
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_time += SEC_PER_DAY * 1.1;

	zbx_history_record_vector_create(&records);

	/* first request */
	cuvc_snapshot(&s1);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 600, 0, cuvc_time));

	cuvc_snapshot(&s2);

	ZBX_CU_ASSERT_UINT64_EQ(s2.misses - s1.misses, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.hits - s1.hits, 0);
	ZBX_CU_ASSERT_UINT64_EQ(s2.db_queries - s1.db_queries, 0);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(records.values_num, 0);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);
	ZBX_CU_ASSERT_INT_EQ(item->active_range, 800);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 0);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}


static void	cuvc_suite_misc3_cleanup()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	vc_time = time;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	vc_remove_item(item);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}
