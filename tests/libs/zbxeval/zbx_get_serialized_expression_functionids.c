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

void	zbx_mock_test_entry(void **state)
{
	zbx_eval_context_t	ctx;
	unsigned char		*data;
	char			*error = NULL;
	zbx_uint64_t		rules;
	int			returned_ret;
	zbx_vector_uint64_t	functionids, functionids_out;

	ZBX_UNUSED(state);

	rules = mock_eval_read_rules("in.rules");
	returned_ret = zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"), rules, &error);

	if (SUCCEED != returned_ret)
		fail_msg("ERROR: %s\n", error);
	else
		mock_dump_stack(&ctx);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.variant"))
	{
		if (0 == strcmp("ZBX_VARIANT_STR", zbx_mock_get_parameter_string("in.variant")))
		{
			for (int i = 0; i < ctx.stack.values_num; i++)
			{
				zbx_variant_set_str(&ctx.stack.values[i].value, zbx_strdup(NULL,
						zbx_mock_get_parameter_string("in.variant")));
			}
		}
		else if (0 == strcmp("ZBX_VARIANT_UI64", zbx_mock_get_parameter_string("in.variant")))
		{
			for (int i = 0; i < ctx.stack.values_num; i++)
			{
				zbx_variant_set_ui64(&ctx.stack.values[i].value,
						zbx_mock_get_parameter_uint64("in.variant_ui64_data"));
			}
		}
		else if (0 == strcmp("ZBX_VARIANT_DBL", zbx_mock_get_parameter_string("in.variant")))
		{
			for (int i = 0; i < ctx.stack.values_num; i++)
			{
				zbx_variant_set_dbl(&ctx.stack.values[i].value,
						zbx_mock_get_parameter_float("in.variant_dbl_data"));
			}
		}
	}

	zbx_eval_serialize(&ctx, NULL, &data);
	zbx_vector_uint64_create(&functionids);
	zbx_get_serialized_expression_functionids(zbx_mock_get_parameter_string("in.expression"), data, &functionids);
	zbx_vector_uint64_create(&functionids_out);
	zbx_mock_extract_yaml_values_uint64(zbx_mock_get_parameter_handle("out.ids"), &functionids_out);

	zbx_mock_assert_int_eq("return value:", SUCCEED, compare_vectors_uint64(&functionids, &functionids_out));
	zbx_vector_uint64_destroy(&functionids);
	zbx_vector_uint64_destroy(&functionids_out);
	zbx_free(data);
	zbx_eval_clear(&ctx);
}
