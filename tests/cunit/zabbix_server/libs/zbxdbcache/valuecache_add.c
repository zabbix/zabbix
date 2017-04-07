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
 * The valuecache_add.c file contains value cache value adding tests:
 * 1) adding of all item value types
 * 2) all value adding scenarious (after cached data, in the middle of
 *    cached data, before cached data)
 * 3) value adding in low memory mode
 * 4) adding different type value
 * 5) automatic old data (outside request range) removal when adding values
 *
 * In all test cases after value cache request the following data is checked:
 * 1) value cache contents
 * 2) heap memory leaks
 *
 * Additionaly at the end of every test suite the value cache is checked for
 * shared memory leaks.
 *
 */

/*
 * value cache test suite: add1
 *
 * This test suite checks if all item value types are correctly added to cache.
 *
 */
static void	cuvc_suite_add1_test_type(zbx_vector_history_record_t *expected, int value_type)
{
	int				i, now;
	zbx_vector_history_record_t	actual;
	zbx_history_record_t		record;
	zbx_timespec_t			ts = {0, 0};
	zbx_vc_item_t			*item;
	zbx_uint64_t			itemid = CUVC_ITEMID_BASE + value_type;

	zbx_history_record_vector_create(&actual);

	now = time(NULL);

	CU_ASSERT(FAIL == zbx_vc_get_value(itemid, value_type, &ts, &record));

	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, value_type, &expected->values[0].timestamp,
			&expected->values[0].value));

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, value_type, &actual, 0, 2, now));

	ZBX_CU_ASSERT_INT_EQ(actual.values_num, expected->values_num);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(item->values_total, 16);

	for (i = 0; i < actual.values_num; i++)
		cuvc_history_record_compare(&actual.values[i], &expected->values[i], value_type);

	zbx_history_record_vector_destroy(&actual, value_type);
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
 * get_value(100000, FLOAT, 0.0)
 *    returned:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * add_value(100000, FLOAT, 1006.0)
 *
 * get_by time(100000, FLOAT, 2, now)
 *    returned:
 *       [100000] {1006.0, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 */
static void	cuvc_suite_add1_test1()
{
	zbx_vector_history_record_t	expected;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&expected);

	cuvc_add_record_float(&expected, 1006.0, 1006, 000);
	cuvc_add_record_float(&expected, 1005.7, 1005, 700);

	cuvc_suite_add1_test_type(&expected, ITEM_VALUE_TYPE_FLOAT);

	zbx_history_record_vector_destroy(&expected, ITEM_VALUE_TYPE_FLOAT);

	ZBX_CU_LEAK_CHECK_END();
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
 * get_value(100001, STR, 0.0)
 *    returned:
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1006.0)
 *
 * get_by time(100001, STR, 2, now)
 *    returned:
 *       [100001] {1006.0, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 */
static void	cuvc_suite_add1_test2()
{
	zbx_vector_history_record_t	expected;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&expected);

	cuvc_add_record_str(&expected, "1006:000", 1006, 000);
	cuvc_add_record_str(&expected, "1005:700", 1005, 700);

	cuvc_suite_add1_test_type(&expected, ITEM_VALUE_TYPE_STR);

	zbx_history_record_vector_destroy(&expected, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
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
 * get_value(100002, FLOAT, 0.0)
 *    returned:
 *       [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * add_value(100002, FLOAT, 1006.0)
 *
 * get_by time(100002, FLOAT, 2, now)
 *    returned:
 *       [100002] {1006.0, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 */
static void	cuvc_suite_add1_test3()
{
	zbx_vector_history_record_t	expected;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&expected);

	cuvc_add_record_log(&expected, "1006:000", 10060, "1006-0", 100600, 100601, 1006, 000);
	cuvc_add_record_log(&expected, "1005:700", 10057, "1005-7", 100570, 100571, 1005, 700);

	cuvc_suite_add1_test_type(&expected, ITEM_VALUE_TYPE_LOG);

	zbx_history_record_vector_destroy(&expected, ITEM_VALUE_TYPE_LOG);

	ZBX_CU_LEAK_CHECK_END();
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
 * get_value(100003, UINT64, 0.0)
 *    returned:
 *       [100003] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100003] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * add_value(100003, UINT64, 1006.0)
 *
 * get_by time(100003, UINT64, 2, now)
 *    returned:
 *       [100003] {1006.0, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100003] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 */
static void	cuvc_suite_add1_test4()
{
	zbx_vector_history_record_t	expected;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&expected);

	cuvc_add_record_uint64(&expected, 10060, 1006, 000);
	cuvc_add_record_uint64(&expected, 10057, 1005, 700);

	cuvc_suite_add1_test_type(&expected, ITEM_VALUE_TYPE_UINT64);

	zbx_history_record_vector_destroy(&expected, ITEM_VALUE_TYPE_UINT64);

	ZBX_CU_LEAK_CHECK_END();
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
 * get_value(100004, TEXT, 0.0)
 *    returned:
 *       [100004] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100003] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100004] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * add_value(100004, TEXT, 1006.0)
 *
 * get_by time(100004, TEXT, 2, now)
 *    returned:
 *       [100004] {1006.0, 1005.7}
 *    cached:
 *       [100000] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100002] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100003] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 *       [100004] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7, 1006.0}
 */
static void	cuvc_suite_add1_test5()
{
	zbx_vector_history_record_t	expected;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&expected);

	cuvc_add_record_str(&expected, "1006:0", 1006, 000);
	cuvc_add_record_str(&expected, "1005:7", 1005, 700);

	cuvc_suite_add1_test_type(&expected, ITEM_VALUE_TYPE_TEXT);

	zbx_history_record_vector_destroy(&expected, ITEM_VALUE_TYPE_TEXT);

	ZBX_CU_LEAK_CHECK_END();
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
 * remove(100000)
 * remove(100001)
 * remove(100002)
 * remove(100003)
 * remove(100004)
 *    cached:
 */
static void	cuvc_suite_add1_cleanup()
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
 * value cache test suite: add2
 *
 * This test suite checks if the value is added to cache in the right location -
 * after cached data, inside cached data, before cached data.
 */

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1006.0)
 *    cached:
 *
 */
static void	cuvc_suite_add2_test1()
{
	zbx_timespec_t			ts = {1006, 0};
	history_value_t			value = {.str = "1006:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(FAIL == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1006.0)
 *    returned:
 *       {1005.7}
 *    cached:
 *       [100001] {1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1006.0)
 *    cached:
 *       [100001] {1005.2, 1005.5, 1005.7, 1006.0}
 *
 * remove(100001)
 *   cached:
 *
 */
static void	cuvc_suite_add2_test2()
{
	zbx_timespec_t			ts = {1006, 0};
	history_value_t			value = {.str = "1006:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));
	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1005:200", "1005:500" ,"1005:700", "1006:000", NULL);

	vc_remove_item(item);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1005.0)
 *    cached:
 *       [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1005.0)
 *    cached:
 *       [100001] {1004.2, 1004.5, 1004.7, 1005.0, 1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *   cached:
 *
 */
static void	cuvc_suite_add2_test3()
{
	zbx_timespec_t			ts = {1005, 0};
	history_value_t			value = {.str = "1005:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));
	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:000",
			"1005:200", "1005:500", "1005:700", NULL);

	vc_remove_item(item);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1005.7)
 *    cached:
 *       [100001] {1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1005.0)
 *    cached:
 *       [100001] {1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *   cached:
 *
 */
static void	cuvc_suite_add2_test4()
{
	zbx_timespec_t			ts1 = {1005, 700}, ts2 = {1004, 900};
	history_value_t			value = {.str = "1004:900"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts1, &record));

	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts2, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", NULL);

	vc_remove_item(item);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1000.0)
 *    returned:
 *       {}
 *    cached:
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1000.0)
 *    cached:
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *   cached:
 *
 */
static void	cuvc_suite_add2_test5()
{
	zbx_timespec_t			ts = {1000, 000};
	history_value_t			value = {.str = "1000:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(FAIL == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);

	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(item->status, 0);

	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700",
			"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	vc_remove_item(item);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1001.0)
 *    returned:
 *       {}
 *    cached:
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1001.2)
 *    cached:
 *       [100001] {1001.2, 1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7,
 *                 1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *   cached:
 *
 */
static void	cuvc_suite_add2_test6()
{
	zbx_timespec_t			ts = {1001, 000};
	history_value_t			value = {.str = "1001:200"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(FAIL == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);
	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);

	ts.ns = 200;
	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(item->status, ZBX_ITEM_STATUS_CACHED_ALL);

	cuvc_check_cache_str(item, "1001:200", "1001:200", "1001:500", "1001:700",
			"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	vc_remove_item(item);

	ZBX_CU_LEAK_CHECK_END();
}


/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_by_time(100001, STR, 100, 1005)
 *    returned:
 *       {}
 *    cached:
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1000.0)
 *    cached:
 *       [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *                 1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *   cached:
 *
 */
static void	cuvc_suite_add2_test7()
{
	zbx_timespec_t			ts = {1000, 000};
	history_value_t			value = {.str = "1000:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_vector_history_record_t	records;

	ZBX_CU_LEAK_CHECK_START();

	zbx_history_record_vector_create(&records);

	CU_ASSERT(SUCCEED == zbx_vc_get_value_range(itemid, ITEM_VALUE_TYPE_STR, &records, 100, 0, 1005));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);
	ZBX_CU_ASSERT_INT_EQ(item->db_cached_from, 906);

	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	ZBX_CU_ASSERT_INT_EQ(item->db_cached_from, 1001);

	cuvc_check_cache_str(item, "1001:200", "1001:500", "1001:700",
			"1002:200", "1002:500", "1002:700", "1003:200", "1003:500", "1003:700",
			"1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	vc_remove_item(item);

	zbx_history_record_vector_destroy(&records, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1005.7)
 *    cached:
 *       [100001] {1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1005.0)
 *    cached:
 *       [100001] {}
 *
 * get_value(100001, STR, 1005.7)
 *    cached:
 *       [100001] {1005.0, 1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *   cached:
 *
 */
static void	cuvc_suite_add2_test8()
{
	zbx_timespec_t			ts1 = {1005, 700}, ts2 = {1005, 000};
	history_value_t			value = {.str = "1005:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts1, &record));
	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", NULL);

	CU_ASSERT(SUCCEED == cuvc_add_str(value.str, &ts2));
	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts2, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts1, &record));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1005:000", "1005:200", "1005:500", "1005:700", NULL);

	vc_remove_item(item);

	cuvc_remove_str(value.str, &ts2);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1004.7)
 *    cached:
 *       [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 * add_value(100001, STR, 1004.0)
 *    cached:
 *       [100001] {1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1004.7)
 *    cached:
 *       [100001] {1004.0, 1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *   cached:
 *
 */
static void	cuvc_suite_add2_test9()
{
	zbx_timespec_t			ts1 = {1004, 700}, ts2 = {1004, 000};
	history_value_t			value = {.str = "1004:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts1, &record));
	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700", NULL);

	CU_ASSERT(SUCCEED == cuvc_add_str(value.str, &ts2));
	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts2, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", NULL);

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts1, &record));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1004:000", "1004:200", "1004:500", "1004:700", "1005:200", "1005:500", "1005:700",
			NULL);

	vc_remove_item(item);

	cuvc_remove_str(value.str, &ts2);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_add2_cleanup()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 * value cache test suite: add3
 *
 * This test suite checks if the values are added in low memory situation.
 */

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * set_mode(lowmem)
 *
 * get_value(100001, STR, 1006.0)
 *    cached:
 *
 * set_mode(normal)
 *
 */
static void	cuvc_suite_add3_test1()
{
	int				found = 0;
	zbx_timespec_t			ts = {1006, 0};
	history_value_t			value = {.str = "1006:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;

	ZBX_CU_LEAK_CHECK_START();

	vc_cache->mode = ZBX_VC_MODE_LOWMEM;

	CU_ASSERT(FAIL == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	vc_cache->mode = ZBX_VC_MODE_NORMAL;

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * value cache test suite: add3
 *
 * This test suite checks if the values are added in low memory situation.
 */

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1006.0)
 *   returned:
 *     {1005.7}
 *   cached:
 *     [100001] {1005.2, 1005.5, 1005.7}
 *
 * set_mode(lowmem)
 *
 * add_value(100001, STR, 1006.0)
 *   cached:
 *     [100001] {1005.2, 1005.5, 1005.7, 1006.0}
 *
 * remove(100001)
 *
 * set_mode(normal)
 */
static void	cuvc_suite_add3_test2()
{
	zbx_timespec_t			ts = {1006, 0};
	history_value_t			value = {.str = "1006:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));

	vc_cache->mode = ZBX_VC_MODE_LOWMEM;

	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", "1006:000", NULL);

	vc_remove_item(item);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	vc_cache->mode = ZBX_VC_MODE_NORMAL;

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1005.0)
 *   returned:
 *     {1004.7}
 *   cached:
 *     [100001] {1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 * set_mode(lowmem)
 *
 * add_value(100001, STR, 1005.0)
 *   cached:
 *     [100001] {1004.2, 1004.5, 1004.7, 1005.0, 1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *
 * set_mode(normal)
 */
static void	cuvc_suite_add3_test3()
{
	zbx_timespec_t			ts = {1005, 0};
	history_value_t			value = {.str = "1005:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));

	vc_cache->mode = ZBX_VC_MODE_LOWMEM;

	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1004:200", "1004:500", "1004:700", "1005:000",
			"1005:200", "1005:500", "1005:700", NULL);

	vc_remove_item(item);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	vc_cache->mode = ZBX_VC_MODE_NORMAL;

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1005.7)
 *   returned:
 *     {1005.7}
 *   cached:
 *     [100001] {1005.2, 1005.5, 1005.7}
 *
 * set_mode(lowmem)
 *
 * add_value(100001, STR, 1005.0)
 *   cached:
 *     [100001] {1005.2, 1005.5, 1005.7}
 *
 * remove(100001)
 *
 * set_mode(normal)
 */
static void	cuvc_suite_add3_test4()
{
	zbx_timespec_t			ts1 = {1005, 700}, ts2 = {1004, 900};
	history_value_t			value = {.str = "1004:900"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts1, &record));

	vc_cache->mode = ZBX_VC_MODE_LOWMEM;

	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts2, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", NULL);

	vc_remove_item(item);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	vc_cache->mode = ZBX_VC_MODE_NORMAL;

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_add3_cleanup()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 * value cache test suite: add4
 *
 * This test suite checks if the value type change is handled correctly.
 *
 */

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7}
 *
 * get_value(100001, STR, 1006.0)
 *   returned:
 *     {1005.7}
 *   cached:
 *     [100001] {1005.2, 1005.5, 1005.7}
 *
 */
static void	cuvc_suite_add4_test1()
{
	zbx_timespec_t			ts = {1006, 0};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT_FATAL(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", NULL);

	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7} (STR)
 *
 * add_value(100001, TEXT, 1006.0)
 *   cached:
 *
 */
static void	cuvc_suite_add4_test2()
{
	zbx_timespec_t			ts = {1006, 0};
	history_value_t			value = {.str = "1006:0"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;

	ZBX_CU_LEAK_CHECK_START();

	CU_ASSERT(FAIL == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_TEXT, &ts, &value));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL_FATAL(item);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_add4_cleanup()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

/*
 * value cache test suite: add5
 *
 * This test suite checks if the old data (outside request range) are correctly
 * dropped when current time is advanced and a new value added to cache.
 */

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7} (STR)
 *
 * set_time(1006)
 *
 * get_value(100001, STR, 1004.0)
 *   returned:
 *     {1003.7}
 *   cached:
 *     {1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7, 1005.2, 1005.5, 1005.7}
 *
 */
static void	cuvc_suite_add5_test1()
{
	zbx_timespec_t			ts = {1004, 0};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;
	zbx_history_record_t		record;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_time = 1006;
	vc_time = cuvc_time_func;

	CU_ASSERT_FATAL(SUCCEED == zbx_vc_get_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &record));

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	cuvc_check_cache_str(item, "1003:200", "1003:500", "1003:700", "1004:200", "1004:500", "1004:700",
			"1005:200", "1005:500", "1005:700", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->active_range, VC_MIN_RANGE);
	ZBX_CU_ASSERT_INT_EQ(item->values_total, 9);
	ZBX_CU_ASSERT_INT_EQ(item->db_cached_from, 1003);


	zbx_history_record_clear(&record, ITEM_VALUE_TYPE_STR);

	ZBX_CU_LEAK_CHECK_END();
}

/*
 * database:
 *   [100001] {1001.2, 1001.5, 1001.7, 1002.2, 1002.5, 1002.7, 1003.2, 1003.5, 1003.7, 1004.2, 1004.5, 1004.7,
 *             1005.2, 1005.5, 1005.7} (STR)
 *
 * set_time(1065)
 *
 * add_value(100001, STR, 1065.0)
 *   cached:
 *     {1005.2, 1005.5, 1005.7, 1065.0}
 *
 * remove(100001)
 *
 */
static void	cuvc_suite_add5_test2()
{
	int				found = 0;
	zbx_timespec_t			ts = {1065, 0};
	history_value_t			value = {.str = "1065:000"};
	zbx_uint64_t			itemid = CUVC_ITEMID_STR;
	zbx_vc_item_t			*item;

	ZBX_CU_LEAK_CHECK_START();

	cuvc_time = 1065;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NOT_NULL_FATAL(item);

	CU_ASSERT(SUCCEED == zbx_vc_add_value(itemid, ITEM_VALUE_TYPE_STR, &ts, &value));

	cuvc_check_cache_str(item, "1005:200", "1005:500", "1005:700", "1065:000", NULL);

	ZBX_CU_ASSERT_INT_EQ(item->db_cached_from, 1005);

	vc_remove_item(item);

	ZBX_CU_LEAK_CHECK_END();
}

static void	cuvc_suite_add5_cleanup()
{
	zbx_vc_item_t	*item;
	zbx_uint64_t	itemid = CUVC_ITEMID_STR;

	vc_time = time;

	item = zbx_hashset_search(&vc_cache->items, &itemid);
	CU_ASSERT_PTR_NULL(item);

	ZBX_CU_ASSERT_UINT64_EQ(vc_mem->free_size, cuvc_free_space);
}

