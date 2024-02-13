/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, char **data, int macro_type, char *error, int maxerrlen);

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, char **data, int macro_type, char *error, int maxerrlen)
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
	ZBX_UNUSED(data);
	ZBX_UNUSED(macro_type);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	return SUCCEED;
}

int	__wrap_DCget_data_expected_from(zbx_uint64_t itemid, int *seconds);

int	__wrap_DCget_data_expected_from(zbx_uint64_t itemid, int *seconds)
{
	ZBX_UNUSED(itemid);
	*seconds = zbx_vcmock_get_ts().sec - 600;
	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	int			err, expected_ret, returned_ret;
	char			*error = NULL, *returned_value = NULL;
	const char		*function, *params;
	DC_ITEM			item;
	zbx_vcmock_ds_item_t	*ds_item;
	zbx_timespec_t		ts;
	zbx_mock_handle_t	handle;
	DC_EVALUATE_ITEM	evaluate_item;

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

	evaluate_item.itemid = item.itemid;
	evaluate_item.value_type = item.value_type;
	evaluate_item.proxy_hostid = item.host.proxy_hostid;
	evaluate_item.host = item.host.host;
	evaluate_item.key_orig = item.key_orig;

	if (SUCCEED != (returned_ret = evaluate_function(&returned_value, &evaluate_item, function, params, &ts, &error)))
		printf("evaluate_function returned error: %s\n", error);
	zbx_vc_flush_stats();

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		zbx_mock_handle_t	handle;
		const char		*expected_value;
		char			*ptr;
		double			expected_dbl;

		handle = zbx_mock_get_parameter_handle("out.value");
		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string_ex(handle, &expected_value)))
			fail_msg("Cannot read output value: %s", zbx_mock_error_string(err));

		expected_dbl = strtod(expected_value, &ptr);

		if (ptr != expected_value && '\0' == *ptr)
			zbx_mock_assert_double_eq("function result", expected_dbl, atof(returned_value));
		else
			zbx_mock_assert_str_eq("function result", expected_value, returned_value);
	}
	zbx_free(returned_value);

	ZBX_UNUSED(state);
}
