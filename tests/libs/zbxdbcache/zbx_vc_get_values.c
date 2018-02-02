/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "valuecache.h"
#include "valuecache_test.h"
#include "valuecache_mock.h"

extern zbx_uint64_t	CONFIG_VALUE_CACHE_SIZE;

/******************************************************************************
 *                                                                            *
 * Function: zbx_mock_test_entry                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_mock_test_entry(void **state)
{
	char				*error = NULL;
	const char			*data;
	int				err,  seconds, count, item_status, item_active_range, item_db_cached_from,
					item_values_total, cache_mode;
	zbx_vector_history_record_t	expected, returned;
	zbx_timespec_t			ts;
	zbx_uint64_t			itemid, cache_hits, cache_misses, expected_hits, expected_misses;
	unsigned char			value_type;
	zbx_mock_handle_t		handle, hitems, hitem, hstatus;
	zbx_mock_error_t		mock_err;

	ZBX_UNUSED(state);

	/* set small cache size to force smaller cache free request size (5% of cache size) */
	CONFIG_VALUE_CACHE_SIZE = ZBX_KIBIBYTE;

	err = zbx_vc_init(&error);
	zbx_mock_assert_result_eq("Value cache initialization failed", SUCCEED, err);

	zbx_vc_enable();

	zbx_vcmock_ds_init();
	zbx_history_record_vector_create(&expected);
	zbx_history_record_vector_create(&returned);

	/* precache values */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.precache", &handle))
	{
		while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(handle, &hitem))))
		{
			zbx_vcmock_set_time(hitem, "time");
			zbx_vcmock_set_mode(hitem, "cache mode");
			zbx_vcmock_set_cache_size(hitem, "cache size");

			zbx_vcmock_get_request_params(hitem, &itemid, &value_type, &seconds, &count, &ts);
			zbx_vc_precache_values(itemid, value_type, seconds, count, &ts);
		}
	}

	/* perform request */

	handle = zbx_mock_get_parameter_handle("in.test");
	zbx_vcmock_set_time(handle, "time");
	zbx_vcmock_set_mode(handle, "cache mode");

	zbx_vcmock_get_request_params(handle, &itemid, &value_type, &seconds, &count, &ts);
	err = zbx_vc_get_values(itemid, value_type, &returned, seconds, count, &ts);
	zbx_mock_assert_result_eq("zbx_vc_get_value_range() return value", SUCCEED, err);

	/* validate results */

	zbx_vcmock_read_values(zbx_mock_get_parameter_handle("out.values"), value_type, &expected);
	zbx_vcmock_check_records("Returned values", value_type,  &expected, &returned);

	zbx_history_record_vector_clean(&returned, value_type);
	zbx_history_record_vector_clean(&expected, value_type);

	/* validate cache contents */

	hitems = zbx_mock_get_parameter_handle("out.cache.items");

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(hitems, &hitem))))
	{
		if (ZBX_MOCK_NOT_A_VECTOR == mock_err)
			fail_msg("out.cache.items parameter is not a vector");

		data = zbx_mock_get_object_member_string(hitem, "itemid");
		if (SUCCEED != is_uint64(data, &itemid))
			fail_msg("Invalid itemid \"%s\"", data);

		err = zbx_vc_get_item_state(itemid, &item_status, &item_active_range, &item_values_total,
						&item_db_cached_from);

		mock_err = zbx_mock_object_member(hitem, "status", &hstatus);

		if (ZBX_MOCK_SUCCESS == mock_err)
		{
			zbx_mock_assert_result_eq("zbx_vc_get_item_state() return value", SUCCEED, err);

			if (ZBX_MOCK_SUCCESS != (mock_err = zbx_mock_string(hstatus, &data)))
				fail_msg("Cannot read item status: %s", zbx_mock_error_string(mock_err));

			zbx_mock_assert_int_eq("item.status", zbx_vcmock_str_to_item_status(data), item_status);

			data = zbx_mock_get_object_member_string(hitem, "active_range");
			zbx_mock_assert_int_eq("item.active_range", atoi(data), item_active_range);

			data = zbx_mock_get_object_member_string(hitem, "values_total");
			zbx_mock_assert_int_eq("item.values_total", atoi(data), item_values_total);

			if (ZBX_MOCK_SUCCESS != (mock_err = zbx_strtime_to_timespec(
					zbx_mock_get_object_member_string(hitem, "db_cached_from"), &ts)))
			{
				fail_msg("Cannot read out.item.db_cached_from timestamp: %s",
						zbx_mock_error_string(mock_err));
			}

			zbx_mock_assert_time_eq("item.db_cached_from", ts.sec, item_db_cached_from);

			value_type = zbx_mock_str_to_value_type(zbx_mock_get_object_member_string(hitem, "value type"));

			zbx_vcmock_read_values(zbx_mock_get_object_member_handle(hitem, "data"), value_type, &expected);
			zbx_vc_get_cached_values(itemid, value_type, &returned);

			zbx_vcmock_check_records("Cached values", value_type, &expected, &returned);

			zbx_history_record_vector_clean(&expected, value_type);
			zbx_history_record_vector_clean(&returned, value_type);
		}
		else
			zbx_mock_assert_result_eq("zbx_vc_get_item_state() return value", FAIL, err);
	}

	/* validate cache state */

	zbx_vc_get_cache_state(&cache_mode, &cache_hits, &cache_misses);
	zbx_mock_assert_int_eq("cache.mode", zbx_vcmock_str_to_cache_mode(zbx_mock_get_parameter_string("out.cache.mode")),
			cache_mode);

	if (FAIL == is_uint64(zbx_mock_get_parameter_string("out.cache.hits"), &expected_hits))
		fail_msg("Invalid out.cache.hits value");
	zbx_mock_assert_uint64_eq("cache.hits", expected_hits, cache_hits);

	if (FAIL == is_uint64(zbx_mock_get_parameter_string("out.cache.misses"), &expected_misses))
		fail_msg("Invalid out.cache.misses value");
	zbx_mock_assert_uint64_eq("cache.misses", expected_misses, cache_misses);

	/* cleanup */

	zbx_vector_history_record_destroy(&returned);
	zbx_vector_history_record_destroy(&expected);

	zbx_vcmock_ds_destroy();

	zbx_vc_reset();
	zbx_vc_destroy();
}

