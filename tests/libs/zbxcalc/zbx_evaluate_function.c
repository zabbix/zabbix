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

#include "zbxcachevalue.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxcalc.h"

#include "zbxnum.h"

#include "mocks/valuecache/valuecache_mock.h"

int	__wrap_zbx_substitute_macros_args(zbx_token_search_t search, char **data, char *error, size_t maxerrlen,
		zbx_macro_resolv_func_t resolver, va_list args);

int	__wrap_zbx_substitute_macros_args(zbx_token_search_t search, char **data, char *error, size_t maxerrlen,
		zbx_macro_resolv_func_t resolver, va_list args)
{
	ZBX_UNUSED(search);
	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);
	ZBX_UNUSED(resolver);
	ZBX_UNUSED(args);

	return SUCCEED;
}

int __wrap_zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds);

int __wrap_zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds)
{
	ZBX_UNUSED(itemid);
	*seconds = zbx_vcmock_get_ts().sec - 600;
	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	int			err, expected_ret, returned_ret;
	char			*error = NULL;
	const char		*function, *params;
	zbx_dc_item_t		item;
	zbx_vcmock_ds_item_t	*ds_item;
	zbx_timespec_t		ts;
	zbx_mock_handle_t	handle;
	zbx_variant_t		returned_value;
	zbx_dc_evaluate_item_t	evaluate_item;

	zbx_update_epsilon_to_float_precision();

	err = zbx_vc_init(get_zbx_config_value_cache_size(), &error);
	zbx_mock_assert_result_eq("Value cache initialization failed", SUCCEED, err);

	zbx_vc_enable();

	zbx_vcmock_ds_init();

	memset(&item, 0, sizeof(zbx_dc_item_t));

	ds_item = zbx_vcmock_ds_first_item();
	item.itemid = ds_item->itemid;
	item.value_type = ds_item->value_type;

	function = zbx_mock_get_parameter_string("in.function");
	params = zbx_mock_get_parameter_string("in.params");

	handle = zbx_mock_get_parameter_handle("in");
	zbx_vcmock_set_time(handle, "time");
	ts = zbx_vcmock_get_ts();

	evaluate_item.itemid = item.itemid;
	evaluate_item.value_type = item.value_type;
	evaluate_item.proxyid = item.host.proxyid;
	evaluate_item.host = item.host.host;
	evaluate_item.key_orig = item.key_orig;

	if (SUCCEED != (returned_ret = zbx_evaluate_function(&returned_value, &evaluate_item, function, params, &ts,
			&error)))
	{
		printf("zbx_evaluate_function returned error: %s\n", error);
		zbx_free(error);
	}

	zbx_vc_flush_stats();

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		const char		*expected_value;
		zbx_uint64_t		expected_ui64;

		handle = zbx_mock_get_parameter_handle("out.value");

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string_ex(handle, &expected_value)))
			fail_msg("Cannot read output value: %s", zbx_mock_error_string(err));

		switch (returned_value.type)
		{
			case ZBX_VARIANT_DBL:
				zbx_mock_assert_double_eq("function result", atof(expected_value),
						returned_value.data.dbl);
				break;
			case ZBX_VARIANT_UI64:
				if (SUCCEED != zbx_is_uint64(expected_value, &expected_ui64))
				{
					fail_msg("function result '" ZBX_FS_UI64
							"' does not match expected result '%s'",
							returned_value.data.ui64, expected_value);
				}
				zbx_mock_assert_uint64_eq("function result", expected_ui64, returned_value.data.ui64);
				break;
			case ZBX_VARIANT_STR:
				zbx_mock_assert_str_eq("function result", expected_value, returned_value.data.str);
				break;
			default:
				fail_msg("function result '%s' has unexpected type '%s'",
						zbx_variant_value_desc(&returned_value),
						zbx_variant_type_desc(&returned_value));
				break;
		}
	}
	if (SUCCEED == returned_ret)
		zbx_variant_clear(&returned_value);

	zbx_vcmock_ds_destroy();

	ZBX_UNUSED(state);
}
