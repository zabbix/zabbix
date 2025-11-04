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

static int	compare_ctx_no_rules(zbx_eval_context_t *ctx1, zbx_eval_context_t *ctx2)
{
	if (0 != zbx_strcmp_natural(ctx1->expression, ctx2->expression))
		return FAIL;

	if (ctx1->stack.values_num != ctx2->stack.values_num)
		return FAIL;

	for (int i = 0; i < ctx1->stack.values_num; i++)
	{
		if (ctx1->stack.values[i].loc.l != ctx2->stack.values[i].loc.l)
			return FAIL;

		if (ctx1->stack.values[i].loc.r != ctx2->stack.values[i].loc.r)
			return FAIL;

		if (ctx1->stack.values[i].opt != ctx2->stack.values[i].opt)
			return FAIL;

		if (ctx1->stack.values[i].type != ctx2->stack.values[i].type)
			return FAIL;
	}
	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_eval_context_t	ctx1, *ctx2;
	unsigned char		*data;
	char			*error = NULL;
	zbx_uint64_t		rules;
	int			returned_ret;

	ZBX_UNUSED(state);

	rules = mock_eval_read_rules("in.rules");
	returned_ret = zbx_eval_parse_expression(&ctx1, zbx_mock_get_parameter_string("in.expression"), rules, &error);

	if (SUCCEED != returned_ret)
		fail_msg("ERROR: %s\n", error);
	else
		mock_dump_stack(&ctx1);

	zbx_eval_serialize(&ctx1, NULL, &data);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.skip_ids"))
		ctx2 = zbx_eval_deserialize_dyn(data, zbx_mock_get_parameter_string("in.expression"),
				ZBX_EVAL_EXTRACT_VAR_STR);
	else
		ctx2 = zbx_eval_deserialize_dyn(data, zbx_mock_get_parameter_string("in.expression"),
				ZBX_EVAL_EXTRACT_ALL);

	zbx_mock_assert_int_eq("return value:", SUCCEED, compare_ctx_no_rules(&ctx1, ctx2));
	zbx_free(data);
	zbx_eval_clear(&ctx1);
	zbx_eval_clear(ctx2);
	zbx_free(ctx2);
}
