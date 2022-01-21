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
#include "zbxeval.h"
#include "mock_eval.h"

typedef struct
{
	const char	*name;
	int		args_num;
	zbx_variant_t	retval;
	const char	*error;
}
zbx_mock_callback_t;

zbx_vector_ptr_t	callbacks;

static void	mock_callback_free(zbx_mock_callback_t *cb)
{
	zbx_variant_clear(&cb->retval);
	zbx_free(cb);
}

static void	mock_read_callbacks(const char *path)
{
	zbx_mock_handle_t	hcbs, hcb, hdata, hvalue;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS != zbx_mock_parameter(path, &hcbs))
		return;

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hcbs, &hcb))))
	{
		zbx_mock_callback_t	*cb;
		const char		*value;

		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("cannot read callback contents");

		cb = (zbx_mock_callback_t *)zbx_malloc(NULL, sizeof(zbx_mock_callback_t));
		memset(cb, 0, sizeof(zbx_mock_callback_t));
		cb->name = zbx_mock_get_object_member_string(hcb, "name");
		cb->args_num = zbx_mock_get_object_member_uint64(hcb, "args_num");

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hcb, "retval", &hdata))
		{
			if (ZBX_MOCK_SUCCESS == zbx_mock_string(hdata, &value))
			{
				zbx_variant_set_dbl(&cb->retval, atof(value));
			}
			else
			{
				zbx_vector_dbl_t	*values;

				values = (zbx_vector_dbl_t *)zbx_malloc(NULL, sizeof(zbx_vector_dbl_t));
				zbx_vector_dbl_create(values);

				while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hdata, &hvalue))))
				{
					if (ZBX_MOCK_SUCCESS != err)
						fail_msg("cannot read callback retval contents");

					if (ZBX_MOCK_SUCCESS != zbx_mock_string(hvalue, &value))
						fail_msg("cannot read callback retval");

					zbx_vector_dbl_append(values, atof(value));
				}

				zbx_variant_set_dbl_vector(&cb->retval, values);
			}
		}
		else if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hcb, "error", &hdata))
		{
			if (ZBX_MOCK_SUCCESS != zbx_mock_string(hdata, &cb->error))
				fail_msg("invalid token error");
		}
		else if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hcb, "reterr", &hdata))
		{
			const char	*errmsg;

			if (ZBX_MOCK_SUCCESS != zbx_mock_string(hdata, &errmsg))
				fail_msg("invalid token error");

			zbx_variant_set_error(&cb->retval, zbx_strdup(NULL, errmsg));
		}
		else
			fail_msg("invalid token contents");

		zbx_vector_ptr_append(&callbacks, cb);
	}
}

static int 	callback_cb(const char *name, size_t len, int args_num, const zbx_variant_t *args, void *data,
		const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	int	i;

	ZBX_UNUSED(args);
	ZBX_UNUSED(data);
	ZBX_UNUSED(ts);

	for (i = 0; i < callbacks.values_num; i++)
	{
		zbx_mock_callback_t	*cb = (zbx_mock_callback_t *)callbacks.values[i];

		if (len == strlen(cb->name) && 0 == memcmp(name, cb->name, len))
		{
			zbx_mock_assert_int_eq("callback argument count", cb->args_num, args_num);
			if (NULL != cb->error)
			{
				*error = zbx_strdup(*error, cb->error);
				return FAIL;
			}
			zbx_variant_copy(value, &cb->retval);
			return SUCCEED;
		}
	}
	*error = zbx_strdup(*error, "unknown callback");

	return FAIL;
}


void	zbx_mock_test_entry(void **state)
{
	zbx_eval_context_t	ctx;
	char			*error = NULL;
	zbx_uint64_t		rules;
	int			expected_ret, returned_ret;
	zbx_variant_t		value;

	ZBX_UNUSED(state);

	zbx_vector_ptr_create(&callbacks);

	rules = mock_eval_read_rules("in.rules");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	if (SUCCEED != zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"), rules, &error))
	{
		if (SUCCEED != expected_ret)
			goto out;

		fail_msg("failed to parse expression: %s", error);
	}

	mock_eval_read_values(&ctx, "in.replace");
	mock_read_callbacks("in.callbacks");

	returned_ret = zbx_eval_execute_ext(&ctx, NULL, callback_cb, callback_cb, NULL, &value, &error);

	if (SUCCEED != returned_ret)
		printf("ERROR: %s\n", error);

	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		zbx_mock_assert_str_eq("output value", zbx_mock_get_parameter_string("out.value"),
				zbx_variant_value_desc(&value));
		zbx_variant_clear(&value);
	}
	else
		zbx_free(error);

out:
	zbx_free(error);
	zbx_vector_ptr_clear_ext(&callbacks, (zbx_clean_func_t)mock_callback_free);
	zbx_vector_ptr_destroy(&callbacks);
	zbx_eval_clear(&ctx);
}
