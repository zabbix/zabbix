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
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxmockhelper.h"

#include "zbxcommon.h"
#include "zbxdbhigh.h"

static void	fill_itemparam(zbx_vector_item_param_ptr_t *v, int values_num, zbx_vector_str_t *names,
		zbx_vector_str_t *values)
{
	zbx_item_param_t	*item_param;

	for (int i = 0; values_num > i; i++)
	{
		item_param = zbx_item_param_create(names->values[i], values->values[i]);
		zbx_vector_item_param_ptr_append(v, item_param);
	}
}

static void	fill_itemparam_buff(zbx_vector_item_param_ptr_t *v, const char *name_buf, const char *value_buf)
{
#define TEST_ID	123
	zbx_item_param_t	*item_param;

	item_param = zbx_item_param_create(name_buf, value_buf);
	zbx_vector_item_param_ptr_append(v, item_param);

	if (SUCCEED == zbx_mock_parameter_exists("in.rollback"))
		item_param->item_parameterid = TEST_ID;
#undef TEST_ID
}

static int	compare_results(zbx_vector_item_param_ptr_t *item_params, zbx_vector_str_t *names,
		zbx_vector_str_t *values)
{
	if (item_params->values_num != names->values_num)
		return FAIL;

	for (int i = 0; item_params->values_num > i; i++)
	{
		if (0 != strcmp(item_params->values[i]->name, names->values[i]) ||
				0 != strcmp(item_params->values[i]->value, values->values[i]))
		{
			return FAIL;
		}

	}

	return SUCCEED;
}

static void	clear_param_vector(zbx_vector_item_param_ptr_t *item_params)
{
	for (int i = 0; item_params->values_num > i; i++)
		zbx_item_param_free(item_params->values[i]);
}

void	zbx_mock_test_entry(void **state)
{
	int				result, exp_result;
	zbx_vector_item_param_ptr_t	item_params_src, item_params_dst;
	char				*src_name_buf, *src_value_buf, *dst_name_buf, *dst_value_buf, *error = NULL;
	zbx_vector_str_t		values_src, names_src, values_dst, names_dst, exp_names, exp_values;
	zbx_mock_handle_t		param_handle;
	const char			*expected_error_msg = NULL;
	zbx_mock_error_t		mock_ret_code;

	ZBX_UNUSED(state);

	zbx_vector_item_param_ptr_create(&item_params_src);
	zbx_vector_item_param_ptr_create(&item_params_dst);

	if (SUCCEED == zbx_mock_parameter_exists("in.used_buffer"))
	{
		size_t	src_name_buffer_len = zbx_mock_get_parameter_uint64("in.src_name_buf_length"),
			src_value_buffer_len = zbx_mock_get_parameter_uint64("in.src_value_buf_length"),
			dst_name_buffer_len = zbx_mock_get_parameter_uint64("in.dst_name_buf_length"),
			dst_value_buffer_len = zbx_mock_get_parameter_uint64("in.dst_value_buf_length");

		src_name_buf = zbx_yaml_assemble_binary_sequence("in.src_name_buf", &src_name_buffer_len);
		src_value_buf = zbx_yaml_assemble_binary_sequence("in.src_value_buf", &src_value_buffer_len);
		dst_name_buf = zbx_yaml_assemble_binary_sequence("in.dst_name_buf", &dst_name_buffer_len);
		dst_value_buf = zbx_yaml_assemble_binary_sequence("in.dst_value_buf", &dst_value_buffer_len);

		fill_itemparam_buff(&item_params_src, src_name_buf, src_value_buf);
		fill_itemparam_buff(&item_params_dst, dst_name_buf, dst_value_buf);
	}
	else
	{
		zbx_vector_str_create(&values_src);
		zbx_vector_str_create(&names_src);
		zbx_vector_str_create(&values_dst);
		zbx_vector_str_create(&names_dst);

		zbx_mock_extract_yaml_values_str("in.values_src", &values_src);
		zbx_mock_extract_yaml_values_str("in.names_src", &names_src);
		zbx_mock_extract_yaml_values_str("in.values_dst", &values_dst);
		zbx_mock_extract_yaml_values_str("in.names_dst", &names_dst);

		fill_itemparam(&item_params_src, values_src.values_num, &names_src, &values_src);
		fill_itemparam(&item_params_dst, values_dst.values_num, &names_dst, &values_dst);
	}

	exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	if (FAIL == exp_result)
	{
		if (ZBX_MOCK_SUCCESS != (mock_ret_code = zbx_mock_out_parameter("error_msg", &param_handle)) ||
				ZBX_MOCK_SUCCESS != (mock_ret_code = zbx_mock_string(param_handle,
				&expected_error_msg)))
		{
			fail_msg("Cannot get expected 'error_msg' parameters from test case data: %s",
					zbx_mock_error_string(mock_ret_code));
		}
	}

	result = zbx_merge_item_params(&item_params_dst, &item_params_src, &error);

	zbx_mock_assert_int_eq("return value", exp_result, result);

	if (FAIL == exp_result)
	{
		if (0 != strcmp(expected_error_msg, error))
		{
			fail_msg("zbx_merge_item_params() error message: expected \"%s\", got \"%s\"",
					expected_error_msg, error);

			goto out;
		}
	}

	zbx_vector_str_create(&exp_names);
	zbx_vector_str_create(&exp_values);

	if (SUCCEED == exp_result || SUCCEED == zbx_mock_parameter_exists("in.rollback"))
	{
		zbx_mock_extract_yaml_values_str("out.exp_names", &exp_names);
		zbx_mock_extract_yaml_values_str("out.exp_values", &exp_values);

		zbx_mock_assert_int_eq("vector compare", SUCCEED, compare_results(&item_params_dst, &exp_names,
				&exp_values));
	}

	if (SUCCEED == zbx_mock_parameter_exists("in.used_buffer"))
	{
		zbx_free(src_name_buf);
		zbx_free(src_value_buf);
		zbx_free(dst_name_buf);
		zbx_free(dst_value_buf);
	}
	else
	{
		zbx_vector_str_clear_ext(&values_src, zbx_str_free);
		zbx_vector_str_clear_ext(&names_src, zbx_str_free);
		zbx_vector_str_clear_ext(&values_dst, zbx_str_free);
		zbx_vector_str_clear_ext(&names_dst, zbx_str_free);

		zbx_vector_str_destroy(&values_src);
		zbx_vector_str_destroy(&names_src);
		zbx_vector_str_destroy(&values_dst);
		zbx_vector_str_destroy(&names_dst);
	}
out:
	zbx_vector_str_clear_ext(&exp_names, zbx_str_free);
	zbx_vector_str_clear_ext(&exp_values, zbx_str_free);

	zbx_vector_str_destroy(&exp_names);
	zbx_vector_str_destroy(&exp_values);

	clear_param_vector(&item_params_src);
	clear_param_vector(&item_params_dst);
	zbx_vector_item_param_ptr_clear(&item_params_src);
	zbx_vector_item_param_ptr_clear(&item_params_dst);
	zbx_vector_item_param_ptr_destroy(&item_params_src);
	zbx_vector_item_param_ptr_destroy(&item_params_dst);

	if (NULL != error)
		zbx_free(error);
}
