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
#include "zbxexpression.h"
#include "zbxtrends.h"
#include "zbxparam.h"

#include "mocks/valuecache/valuecache_mock.h"

#include "../../../src/libs/zbxexpression/anomalystl.h"
#include "../../../src/libs/zbxexpression/funcparam.h"

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

void	zbx_mock_test_entry(void **state)
{
	time_t				start_evaluate_period, end_evaluate_period;
	int				start_detect_period, end_detect_period, detect_period_season_shift, err,
					detect_period, evaluate_seconds = 0,
					evaluate_nvalues = 0;
	double				deviations_count, result;
	char				*error = NULL, *evaluate_period = NULL;
	const char			*params, *dev_alg = NULL;
	zbx_dc_item_t			item;
	zbx_vcmock_ds_item_t		*ds_item;
	zbx_timespec_t			ts, ts_evaluate_end;
	zbx_mock_handle_t		handle;
	zbx_vector_history_record_t	values_in;
	zbx_value_type_t		detect_period_season_type;

	/* ZBX_DOUBLE_EPSILON = 0.000001; results into output that is different from python test case output */
	zbx_update_epsilon_to_python_compatible_precision();

	zbx_history_record_vector_create(&values_in);

	err = zbx_vc_init(get_zbx_config_value_cache_size(), &error);
	zbx_mock_assert_result_eq("Value cache initialization failed", SUCCEED, err);
	zbx_vc_enable();
	zbx_vcmock_ds_init();
	memset(&item, 0, sizeof(zbx_dc_item_t));
	ds_item = zbx_vcmock_ds_first_item();
	item.itemid = ds_item->itemid;
	item.value_type = ds_item->value_type;

	deviations_count = zbx_mock_get_parameter_float("in.deviations_count");
	dev_alg = zbx_mock_get_parameter_string("in.dev_alg");
	handle = zbx_mock_get_parameter_handle("in");
	zbx_vcmock_set_time(handle, "time");
	ts = zbx_vcmock_get_ts();
	ts_evaluate_end = ts;

	params = zbx_mock_get_parameter_string("in.params");

	if (2 != zbx_function_param_parse_count(params))
	{
		fail_msg("invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_str(params, 1, &evaluate_period))
	{
		fail_msg("invalid second parameter");
		goto out;
	}

	if (SUCCEED != zbx_trends_parse_range(ts.sec, evaluate_period, &start_evaluate_period, &end_evaluate_period,
			&error))
	{
		fail_msg("failed to parse seconds parameter: %s", error);
		goto out;
	}

	ts_evaluate_end.sec = end_evaluate_period;
	evaluate_seconds = end_evaluate_period - start_evaluate_period;

	if (SUCCEED != get_function_parameter_hist_range(ts.sec, params, 2, &detect_period, &detect_period_season_type,
			&detect_period_season_shift))
	{
		fail_msg("invalid third parameter");
		goto out;
	}

	start_detect_period = ts_evaluate_end.sec - detect_period;
	end_detect_period = ts_evaluate_end.sec;

	if (FAIL == zbx_vc_get_values(item.itemid, item.value_type, &values_in, evaluate_seconds, evaluate_nvalues,
			&ts_evaluate_end))
	{
		fail_msg("cannot get values from value cache");
		goto out;
	}

	zbx_vc_flush_stats();

	if (0 >= values_in.values_num)
	{
		fail_msg("not enough data");
		goto out;
	}

	if (SUCCEED != zbx_get_percentage_of_deviations_in_stl_remainder(&values_in, deviations_count, dev_alg,
			start_detect_period, end_detect_period, &result, &error))
	{
		fail_msg("zbx_get_percentage_of_deviations_in_stl_remainder returned error: %s\n", error);
		zbx_free(error);
	}
	else
	{
		const char	*expected_value;

		handle = zbx_mock_get_parameter_handle("out.value");

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string_ex(handle, &expected_value)))
			fail_msg("Cannot read output value: %s", zbx_mock_error_string(err));

		zbx_mock_assert_double_eq("function result", atof(expected_value), result);
	}
out:
	zbx_history_record_vector_destroy(&values_in, item.value_type);

	zbx_vcmock_ds_destroy();
	zbx_free(evaluate_period);
	ZBX_UNUSED(state);
}
