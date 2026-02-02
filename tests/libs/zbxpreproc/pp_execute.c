/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "libs/zbxpreproc/pp_execute.h"

#include "pp_mock.h"

static zbx_pp_item_preproc_t	*read_pp_item_preproc(void)
{
	zbx_pp_item_preproc_t	*preproc;

	int	item_type = zbx_mock_str_to_item_type(zbx_mock_get_parameter_string("in.item_type"));
	int	value_type = zbx_mock_str_to_value_type(zbx_mock_get_parameter_string("in.value.value_type"));

	preproc = zbx_pp_item_preproc_create(0, item_type, value_type, 0);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.steps"))
	{
		zbx_mock_error_t		err;
		zbx_mock_handle_t		step, steps = zbx_mock_get_parameter_handle("in.steps");
		zbx_vector_pp_step_ptr_t	step_vec;

		zbx_vector_pp_step_ptr_create(&step_vec);

		while (ZBX_MOCK_END_OF_VECTOR != (err = zbx_mock_vector_element(steps, &step)))
		{
			zbx_pp_step_t	*pp_step = (zbx_pp_step_t *)zbx_malloc(NULL, sizeof(zbx_pp_step_t));

			mock_pp_read_step(step, pp_step);

			zbx_vector_pp_step_ptr_append(&step_vec, pp_step);
		}

		preproc->steps = zbx_malloc(NULL, (size_t)step_vec.values_num * sizeof(zbx_pp_step_t));
		for (int i = 0; i < step_vec.values_num; i++)
			preproc->steps[i] = *step_vec.values[i];
		preproc->steps_num = step_vec.values_num;

		zbx_vector_pp_step_ptr_destroy(&step_vec);
	}

	return preproc;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_pp_context_t	ctx = {0};
	zbx_pp_item_preproc_t	*preproc;
	zbx_pp_cache_t		*cache;
	unsigned char		value_type;
	zbx_variant_t		value_in = {0}, value_out = {0};
	zbx_timespec_t		ts_in;
	zbx_pp_result_t		*results_out = NULL;
	int			results_num = 0;

	ZBX_UNUSED(state);

	pp_context_init(&ctx);
	zbx_variant_clear(&value_in);
	zbx_variant_clear(&value_out);
	preproc = read_pp_item_preproc();

	mock_pp_read_value(zbx_mock_get_parameter_handle("in.value"), &value_type, &value_in, &ts_in);

	cache = pp_cache_create(preproc, &value_in);
	pp_execute(&ctx, preproc, cache, NULL, &value_in, ts_in, get_zbx_config_source_ip(), &value_out,
			&results_out, &results_num);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.results"))
	{
		zbx_mock_handle_t	results;
		zbx_mock_error_t	err;

		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.results", &results))
		{
			int			result_num = 0;
			zbx_mock_handle_t	result;

			while (ZBX_MOCK_END_OF_VECTOR != (err = zbx_mock_vector_element(results, &result)))
			{
				zbx_variant_t	value_res = {0};

				if (result_num >= results_num) {
					fail_msg("Not enough results from preprocessing");
					break;
				}

				zbx_pp_result_t	*pp_result = &results_out[result_num];

				mock_pp_read_variant(result, &value_res);

				if (0 != zbx_variant_compare(&pp_result->value, &value_res))
				{
					fail_msg("preprocessing result %d differs: '%s'/'%s'", result_num,
							zbx_variant_value_desc(&pp_result->value),
							zbx_variant_value_desc(&value_res));
				}

				result_num++;
			}
		}
	}

	pp_context_destroy(&ctx);
}
