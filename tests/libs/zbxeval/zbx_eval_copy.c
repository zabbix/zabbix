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
	int			returned_ret;
	zbx_eval_context_t	ctx, dst;
	char			*error = NULL;
	zbx_uint64_t		rules;
	const char		*expression;

	ZBX_UNUSED(state);

	expression = zbx_mock_get_parameter_string("in.expression");
	rules = mock_eval_read_rules("in.rules");
	returned_ret = zbx_eval_parse_expression(&ctx, expression, rules, &error);

	if (SUCCEED != returned_ret)
		printf("ERROR: %s\n", error);
	else
		mock_dump_stack(&ctx);

	if (SUCCEED == zbx_mock_parameter_exists("in.variant"))
	{
		for (int i = 0; i < ctx.stack.values_num; i++)			{
				zbx_variant_set_str(&ctx.stack.values[i].value, zbx_strdup(NULL,
						zbx_mock_get_parameter_string("in.variant")));
		}
	}

	zbx_eval_copy(&dst, &ctx, expression);

	zbx_mock_assert_int_eq("return value:", SUCCEED, compare_ctx(&ctx, &dst));
	zbx_eval_clear(&ctx);
	zbx_eval_clear(&dst);
	zbx_free(error);
}
