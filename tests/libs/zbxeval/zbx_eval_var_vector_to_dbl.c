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

#include "zbxcommon.h"
#include "zbxeval.h"
#include "mock_eval.h"
#include "zbxdbwrap.h"

static int	compare_dbl_vectors(zbx_vector_dbl_t *v1, zbx_vector_dbl_t *v2)
{
	if (v1->values_num != v2->values_num)
		return FAIL;

	for (int i = 0; i < v1->values_num; i++)
	{
		if (fabs(v1->values[i] - v2->values[i]) > 1e-9) // Compare two doubles with a small tolerance (epsilon)
			return FAIL;
	}

	return SUCCEED;
}

static void	extract_yaml_values_dbl(zbx_mock_handle_t hdata, zbx_vector_dbl_t *values)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	hvalue;

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hdata, &hvalue))
	{
		double	value;

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_float(hvalue, &value)))
			fail_msg("Cannot read vector member: %s", zbx_mock_error_string(err));

		zbx_vector_dbl_append(values, value);
	}
}

static int	upend_var_vector(zbx_vector_str_t *str_vector, zbx_vector_var_t *input_vector)
{
	const char		*type = zbx_mock_get_parameter_string("in.variant_type");
	zbx_variant_t		variant;

	if (SUCCEED == strcmp(type, "STR"))
	{
		zbx_mock_extract_yaml_values_str("in.data", str_vector);

		for(int i = 0; i < str_vector->values_num; i++)
		{
			zbx_variant_set_str(&variant, str_vector->values[i]);
			zbx_vector_var_append(input_vector, variant);
		}
	}

	if (SUCCEED == strcmp(type, "UI64"))
	{
		zbx_vector_uint64_t	ui64_vector;

		zbx_vector_uint64_create(&ui64_vector);
		zbx_mock_extract_yaml_values_uint64(zbx_mock_get_parameter_handle("in.data"), &ui64_vector);

		for(int i = 0; i < ui64_vector.values_num; i++)
		{
			zbx_variant_set_ui64(&variant, ui64_vector.values[i]);
			zbx_vector_var_append(input_vector, variant);
		}
	}
}

void	zbx_mock_test_entry(void **state)
{
	zbx_vector_var_t	input_vector;
	zbx_vector_dbl_t	output_vector, exp_vector;
	zbx_vector_str_t	str_vector;
	char			*error = NULL;
	zbx_variant_t		variant;
	int			result,
				exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	ZBX_UNUSED(state);

	zbx_vector_var_create(&input_vector);
	zbx_vector_str_create(&str_vector);
	zbx_vector_dbl_create(&output_vector);

	upend_var_vector(&str_vector, &input_vector);
printf("input count %d\n",input_vector.values_num);
	result = zbx_eval_var_vector_to_dbl(&input_vector, &output_vector, &error);
printf("output count %d\n",output_vector.values_num);
	for (int i = 0; i < output_vector.values_num; i ++)
	{
		printf("output %f\n", output_vector.values[i]);
	}
	zbx_mock_assert_int_eq("return value:", exp_result, result);

	if (SUCCEED == result)
	{
		zbx_vector_dbl_create(&exp_vector);
		extract_yaml_values_dbl(zbx_mock_get_parameter_handle("out.result"), &exp_vector);
		zbx_mock_assert_int_eq("return vector compare return value:", SUCCEED,
				compare_dbl_vectors(&output_vector,&exp_vector));
	}

	zbx_vector_str_clear_ext(&str_vector, zbx_str_free);
}
