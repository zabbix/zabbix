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

#include "zbxexpression.h"
#include "zbxcachevalue.h"
#include "mocks/valuecache/valuecache_mock.h"
#include "../../../src/libs/zbxexpression/anomalystl.h"

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const zbx_dc_host_t *dc_host,
		const zbx_dc_item_t *dc_item, zbx_db_alert *alert, const zbx_db_acknowledge *ack,
		const zbx_service_alarm_t *service_alarm, const zbx_db_service *service, const char *tz, char **data,
		int macro_type, char *error, int maxerrlen);

int	__wrap_zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds);

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const zbx_dc_host_t *dc_host,
		const zbx_dc_item_t *dc_item, zbx_db_alert *alert, const zbx_db_acknowledge *ack,
		const zbx_service_alarm_t *service_alarm, const zbx_db_service *service, const char *tz, char **data,
		int macro_type, char *error, int maxerrlen)
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

int	__wrap_zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds)
{
	ZBX_UNUSED(itemid);
	*seconds = zbx_vcmock_get_ts().sec - 600;

	return SUCCEED;
}

static void	zbx_vcmock_history_dump(unsigned char value_type, const zbx_vector_history_record_t *values)
{
	int	i;
	char	buffer[256];

	for (i = 0; i < values->values_num; i++)
	{
		const zbx_history_record_t	*rec = &values->values[i];

		zbx_timespec_to_strtime(&rec->timestamp, buffer, sizeof(buffer));
		printf("  - %s\n", buffer);
		zbx_history_value2str(buffer, sizeof(buffer), &rec->value, value_type);
		printf("    %s\n", buffer);
	}
}

static void	read_values_stl(zbx_mock_handle_t hdata, unsigned char value_type, zbx_vector_history_record_t *values)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	hvalue;
	zbx_history_record_t	rec;
	const char		*data;

	ZBX_UNUSED(value_type);

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hdata, &hvalue))))
	{
		rec.value.dbl = atof(zbx_mock_get_object_member_string(hvalue, "value"));
		data = zbx_mock_get_object_member_string(hvalue, "ts");

		if (ZBX_MOCK_SUCCESS != (err = zbx_strtime_to_timespec(data, &rec.timestamp)))
			fail_msg("Invalid value timestamp \"%s\": %s", data, zbx_mock_error_string(err));

		zbx_vector_history_record_append_ptr(values, &rec);
	}
}

static void	check_records_stl(const char *prefix, unsigned char value_type,
		const zbx_vector_history_record_t *expected_values, const zbx_vector_history_record_t *returned_values)
{
	int				i;
	const zbx_history_record_t	*expected, *returned;

	printf("Expected %s:\n", prefix);
	zbx_vcmock_history_dump(value_type, expected_values);

	printf("Returned %s:\n", prefix);
	zbx_vcmock_history_dump(value_type, returned_values);

	zbx_mock_assert_int_eq(prefix, expected_values->values_num, returned_values->values_num);

	for (i = 0; i < expected_values->values_num; i++)
	{
		expected = &expected_values->values[i];
		returned = &returned_values->values[i];

		zbx_mock_assert_timespec_eq(prefix, &expected->timestamp, &returned->timestamp);
		zbx_mock_assert_double_eq(prefix, expected->value.dbl, returned->value.dbl);
	}
}

void	zbx_mock_test_entry(void **state)
{
	int				err, expected_ret, returned_ret, nvalues = 0;
	zbx_uint64_t			s_window, season, seconds = 0;
	char				*error = NULL;
	zbx_dc_item_t			item;
	zbx_vcmock_ds_item_t		*ds_item;
	zbx_timespec_t			ts;
	zbx_mock_handle_t		handle;
	zbx_vector_history_record_t	values_in, trend_values_received, trend_values_expected,
					seasonal_values_received, seasonal_values_expected, remainder_values_received,
					remainder_values_expected;

	/*ZBX_DOUBLE_EPSILON = 0.000001; results into output that is different from python test case output */
	zbx_update_epsilon_to_python_compatible_precision();

	zbx_history_record_vector_create(&values_in);
	zbx_history_record_vector_create(&trend_values_received);
	zbx_history_record_vector_create(&trend_values_expected);
	zbx_history_record_vector_create(&seasonal_values_received);
	zbx_history_record_vector_create(&seasonal_values_expected);
	zbx_history_record_vector_create(&remainder_values_received);
	zbx_history_record_vector_create(&remainder_values_expected);

	err = zbx_vc_init(get_zbx_config_value_cache_size(), &error);
	zbx_mock_assert_result_eq("Value cache initialization failed", SUCCEED, err);
	zbx_vc_enable();
	zbx_vcmock_ds_init();
	memset(&item, 0, sizeof(zbx_dc_item_t));
	ds_item = zbx_vcmock_ds_first_item();
	item.itemid = ds_item->itemid;
	item.value_type = ds_item->value_type;

	season = zbx_mock_get_parameter_uint64("in.season");
	s_window = zbx_mock_get_parameter_uint64("in.s_window");
	seconds = zbx_mock_get_parameter_uint64("in.seconds");
	handle = zbx_mock_get_parameter_handle("in");
	zbx_vcmock_set_time(handle, "time");
	ts = zbx_vcmock_get_ts();

	if (FAIL == zbx_vc_get_values(item.itemid, item.value_type, &values_in, (int)seconds, nvalues, &ts))
	{
		error = zbx_strdup(error, "cannot get values from value cache");
		printf("%s\n", error);
		zbx_free(error);
		goto out;
	}

	if (SUCCEED != (returned_ret = zbx_STL(&values_in, (int)season, ROBUST_DEF, (int)s_window, S_DEGREE_DEF,
			T_WINDOW_DEF, T_DEGREE_DEF, L_WINDOW_DEF, L_DEGREE_DEF, S_JUMP_DEF, T_JUMP_DEF, L_JUMP_DEF,
			INNER_DEF, OUTER_DEF, &trend_values_received, &seasonal_values_received,
			&remainder_values_received, &error)))
	{
		printf("zbx_STL returned error: %s\n", error);
		zbx_free(error);
	}

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	zbx_vc_flush_stats();

	if (SUCCEED == expected_ret)
	{
		read_values_stl(zbx_mock_get_parameter_handle("out.trend"), item.value_type, &trend_values_expected);
		read_values_stl(zbx_mock_get_parameter_handle("out.seasonal"), item.value_type,
				&seasonal_values_expected);
		read_values_stl(zbx_mock_get_parameter_handle("out.remainder"), item.value_type,
				&remainder_values_expected);
		check_records_stl("trend values", item.value_type, &trend_values_expected, &trend_values_received);
		check_records_stl("seasonal values", item.value_type, &seasonal_values_expected,
				&seasonal_values_received);
		check_records_stl("remainder values", item.value_type, &remainder_values_expected,
				&remainder_values_received);
	}
out:
	zbx_history_record_vector_destroy(&trend_values_received, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&trend_values_expected, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&seasonal_values_received, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&seasonal_values_expected, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&remainder_values_received, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&remainder_values_expected, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&values_in, item.value_type);

	zbx_vcmock_ds_destroy();

	ZBX_UNUSED(state);
}
