/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxnum.h"
#include "zbxcachevalue.h"
#include "valuecache_test.h"
#include "mocks/valuecache/valuecache_mock.h"

#include "zbx_vc_common.h"

void	zbx_vc_test_get_value_setup(zbx_mock_handle_t *handle, zbx_uint64_t *itemid, unsigned char *value_type,
		zbx_timespec_t *ts, int *err, zbx_vector_history_record_t *expected,
		zbx_vector_history_record_t *returned)
{
	/* perform request */

	*handle = zbx_mock_get_parameter_handle("in.test");
	zbx_vcmock_set_time(*handle, "time");
	zbx_vcmock_set_mode(*handle, "cache mode");

	if (FAIL == zbx_is_uint64(zbx_mock_get_object_member_string(*handle, "itemid"), itemid))
		fail_msg("Invalid itemid value");

	*value_type = zbx_mock_str_to_value_type(zbx_mock_get_object_member_string(*handle, "value type"));
	zbx_strtime_to_timespec(zbx_mock_get_object_member_string(*handle, "end"), ts);

	zbx_vector_history_record_reserve(returned, 1);
	*err = zbx_vc_get_value(*itemid, *value_type, ts, &(*returned).values[0]);
	zbx_vc_flush_stats();
	returned->values_num = 1;
	zbx_mock_assert_result_eq("zbx_vc_get_values() return value", SUCCEED, *err);

	/* validate results */

	zbx_vcmock_read_values(zbx_mock_get_parameter_handle("out.values"), *value_type, expected);
	zbx_vcmock_check_records("Returned values", *value_type,  expected, returned);

	zbx_history_record_vector_clean(returned, *value_type);
	zbx_history_record_vector_clean(expected, *value_type);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_vc_common_test_func(state, NULL, zbx_vc_test_get_value_setup, NULL, 1);
}
