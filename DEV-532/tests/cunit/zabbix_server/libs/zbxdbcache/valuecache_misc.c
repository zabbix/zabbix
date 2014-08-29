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

	ZBX_CU_ASSERT_INT_EQ(vc_cache->low_memory, 0);

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

	/* the last items should be removed form cache as stale data */
	for (i = 1; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		itemid = CUVC_ITEMID_BASE + i;

		item = zbx_hashset_search(&vc_cache->items, &itemid);
		CU_ASSERT_PTR_NOT_NULL_FATAL(item);

		vc_remove_item(item);
	}

	ZBX_CU_ASSERT_INT_EQ(vc_cache->low_memory, 1);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_misc2_cleanup()
{
	vc_time = time;

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}


