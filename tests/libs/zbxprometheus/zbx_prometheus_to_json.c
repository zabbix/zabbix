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

#include "zbxprometheus.h"
#include "zbxlog.h"
#include "zbxjson.h"

static int	num_params_exp = 0, num_params_rec = 0;
static int	num_metrics_exp = 0;

static void	check_param(const char *name, const char *value, const struct zbx_json_parse *jp)
{
	char		*json_value = NULL;
	size_t		json_value_sz = 0;
	int		ret;

	ret = zbx_json_value_by_name_dyn(jp, name, &json_value, &json_value_sz, NULL);

	if (SUCCEED != ret)
		fail_msg("Expected parameter/label (%s) is not found in JSON output", name);

	zbx_mock_assert_str_eq("Invalid parameter/label value returned by zbx_prometheus_to_json()", value, json_value);
	zbx_free(json_value);
	num_params_exp++;
}

static void	check_metric_param(zbx_mock_handle_t element, const char *name, const struct zbx_json_parse *jp)
{
	const char		*value;
	zbx_mock_handle_t	member;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(element, name, &member))
	{
		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(member, &value)))
			fail_msg("Cannot get string: %s", zbx_mock_error_string(err));

		check_param(name, value, jp);
	}
}

void	zbx_mock_test_entry(void **state)
{
	struct zbx_json_parse	jp, jp_data, jp_label;
	zbx_mock_handle_t	metrics, labels, element;
	const char		*data, *params, *label_name, *label_value, *p = NULL, *output_raw;
	char			*ret_err = NULL, *ret_output = NULL;
	int			ret, expected_ret;
	zbx_mock_error_t	error;

	ZBX_UNUSED(state);

	data = zbx_mock_get_parameter_string("in.data");
	params = zbx_mock_get_parameter_string("in.params");

	if (SUCCEED != (ret = zbx_prometheus_to_json(data, params, &ret_output, &ret_err)))
		zabbix_log(LOG_LEVEL_DEBUG, "Error: %s", ret_err);

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	zbx_mock_assert_result_eq("Invalid zbx_prometheus_to_json() return value", expected_ret, ret);

	if (SUCCEED == ret)
	{
		ret = zbx_json_open(ret_output, &jp);
		zbx_mock_assert_result_eq("Invalid zbx_json_open() return value", SUCCEED, ret);

		/* verify output_raw (optional) */
		if (ZBX_MOCK_SUCCESS == zbx_mock_out_parameter("output_raw", &metrics))
		{
			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(metrics, &output_raw)))
				fail_msg("Cannot get string: %s", zbx_mock_error_string(error));

			zbx_mock_assert_str_eq("Invalid zbx_prometheus_to_json() output", output_raw, ret_output);

			/* metrics parameter is not mandatory if output_raw is set */
			if (ZBX_MOCK_SUCCESS != zbx_mock_out_parameter("metrics", &metrics))
				goto out;
		}
		else
			metrics = zbx_mock_get_parameter_handle("out.metrics");

		/* verify metrics */
		while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(metrics, &element))
		{
			p = zbx_json_next(&jp, p);

			if (NULL == p)
				fail_msg("Less metrics than expected returned by zbx_prometheus_to_json()");

			ret = zbx_json_brackets_open(p, &jp_data);
			zbx_mock_assert_result_eq("Invalid zbx_json_brackets_open() return value", SUCCEED, ret);
			num_params_rec += zbx_json_count(&jp_data);

			check_metric_param(element, "name", &jp_data);
			check_metric_param(element, "value", &jp_data);
			check_metric_param(element, "line_raw", &jp_data);
			check_metric_param(element, "help", &jp_data);
			check_metric_param(element, "type", &jp_data);

			/* verify labels */
			if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(element, "labels", &labels))
			{
				ret = zbx_json_brackets_by_name(&jp_data, "labels", &jp_label);

				if (SUCCEED != ret)
					fail_msg("Expected labels are not found in JSON output");

				num_params_rec += zbx_json_count(&jp_label);
				num_params_exp++;

				while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(labels, &element))
				{
					label_name = zbx_mock_get_object_member_string(element, "name");
					label_value = zbx_mock_get_object_member_string(element, "value");
					check_param(label_name, label_value, &jp_label);
				}
			}
			else
			{
				ret = zbx_json_brackets_by_name(&jp_data, "labels", &jp_label);

				if (SUCCEED == ret)
					fail_msg("Labels found in JSON output while not expected");
			}

			num_metrics_exp++;
		}

		if ((num_params_rec != num_params_exp) || (zbx_json_count(&jp) != num_metrics_exp))
			fail_msg("More metrics/parameters than expected returned by zbx_prometheus_to_json()");
	}

out:
	zbx_free(ret_output);
	zbx_free(ret_err);
}
