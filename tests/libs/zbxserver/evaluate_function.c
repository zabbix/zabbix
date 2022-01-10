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

#include "common.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "valuecache.h"
#include "zbxserver.h"

#include "mocks/valuecache/valuecache_mock.h"

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm,
		const DB_SERVICE *service, const char *tz, char **data, int macro_type, char *error,
		int maxerrlen);

int __wrap_DCget_data_expected_from(zbx_uint64_t itemid, int *seconds);

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, const zbx_service_alarm_t *service_alarm,
		const DB_SERVICE *service, const char *tz, char **data, int macro_type, char *error,
		int maxerrlen)
{
	ZBX_UNUSED(actionid);
	ZBX_UNUSED(event);
	ZBX_UNUSED(r_event);
	ZBX_UNUSED(userid);
	ZBX_UNUSED(hostid);
	ZBX_UNUSED(dc_host);
	ZBX_UNUSED(dc_item);
	ZBX_UNUSED(alert);
	ZBX_UNUSED(ack);
	ZBX_UNUSED(tz);
	ZBX_UNUSED(data);
	ZBX_UNUSED(macro_type);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);
	ZBX_UNUSED(service_alarm);
	ZBX_UNUSED(service);

	return SUCCEED;
}

int __wrap_DCget_data_expected_from(zbx_uint64_t itemid, int *seconds)
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
	DC_ITEM			item;
	zbx_vcmock_ds_item_t	*ds_item;
	zbx_timespec_t		ts;
	zbx_mock_handle_t	handle;
	zbx_variant_t		returned_value;

	ZBX_DOUBLE_EPSILON = 0.000001;

	err = zbx_vc_init(&error);
	zbx_mock_assert_result_eq("Value cache initialization failed", SUCCEED, err);

	zbx_vc_enable();

	zbx_vcmock_ds_init();

	memset(&item, 0, sizeof(DC_ITEM));

	ds_item = zbx_vcmock_ds_first_item();
	item.itemid = ds_item->itemid;
	item.value_type = ds_item->value_type;

	function = zbx_mock_get_parameter_string("in.function");
	params = zbx_mock_get_parameter_string("in.params");

	handle = zbx_mock_get_parameter_handle("in");
	zbx_vcmock_set_time(handle, "time");
	ts = zbx_vcmock_get_ts();

	if (SUCCEED != (returned_ret = evaluate_function2(&returned_value, &item, function, params, &ts, &error)))
	{
		printf("evaluate_function returned error: %s\n", error);
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
				if (SUCCEED != is_uint64(expected_value, &expected_ui64))
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
