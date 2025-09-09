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

static int	compare_vectors_str(zbx_vector_str_t *v1, zbx_vector_str_t *v2)
{
	if (v1->values_num != v2->values_num)
		return FAIL;

	for (int i = 0; i < v1->values_num; i++)
	{
		if (0 != strcmp(v1->values[i],v2->values[i]))
			return FAIL;
	}

	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	int			returned_ret;
	zbx_eval_context_t	ctx;
	char			*error = NULL;
	zbx_uint64_t		rules;
	zbx_vector_str_t	refs, exp_refs, parameters;

	ZBX_UNUSED(state);

	zbx_vector_str_create(&refs);
	zbx_vector_str_create(&parameters);

	rules = mock_eval_read_rules("in.rules");
	returned_ret = zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"), rules, &error);

	if (SUCCEED != returned_ret)
		fail_msg("ERROR: %s\n", error);
	else
		mock_dump_stack(&ctx);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.variant_text"))
	{
		for (int i = 0; i < ctx.stack.values_num; i++)
		{
			zbx_variant_set_str(&ctx.stack.values[i].value, zbx_strdup(NULL,
					zbx_mock_get_parameter_string("in.variant_text")));
		}
	}

	zbx_eval_extract_item_refs(&ctx, &refs);
	zbx_vector_str_create(&exp_refs);
	zbx_mock_extract_yaml_values_str("out.refs", &exp_refs);

	zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_str(&refs,&exp_refs));
	zbx_vector_str_clear_ext(&exp_refs, zbx_str_free);
	zbx_vector_str_destroy(&exp_refs);
	zbx_vector_str_clear_ext(&refs, zbx_str_free);
	zbx_vector_str_destroy(&refs);
	zbx_eval_clear(&ctx);
	zbx_free(error);
}
