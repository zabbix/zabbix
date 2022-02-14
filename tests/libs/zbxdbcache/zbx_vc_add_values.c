/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "mocks/valuecache/valuecache_mock.h"

extern zbx_uint64_t	CONFIG_VALUE_CACHE_SIZE;

#include "zbx_vc_common.h"

void	zbx_vc_test_add_values_setup(zbx_mock_handle_t *handle, zbx_vector_ptr_t *history, int *err, const char **data,
		int *ret_flush)
{
	/* execute request */

	*handle = zbx_mock_get_parameter_handle("in.test");
	zbx_vcmock_set_time(*handle, "time");
	zbx_vcmock_set_mode(*handle, "cache mode");
	zbx_vcmock_set_cache_size(*handle, "cache size");

	zbx_vector_ptr_create(history);
	zbx_vcmock_get_dc_history(zbx_mock_get_object_member_handle(*handle, "values"), history);

	*err = zbx_vc_add_values(history, ret_flush);
	*data = zbx_mock_get_parameter_string("out.return");
	zbx_mock_assert_int_eq("zbx_vc_add_values()", zbx_mock_str_to_return_code(*data), *err);

	zbx_vector_ptr_clear_ext(history, zbx_vcmock_free_dc_history);
	zbx_vector_ptr_destroy(history);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_vc_common_test_func(state, zbx_vc_test_add_values_setup, NULL, NULL, 0);
}
