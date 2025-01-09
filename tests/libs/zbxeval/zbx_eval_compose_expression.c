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

static void	replace_values(zbx_eval_context_t *ctx, const char *path)
{
	zbx_mock_handle_t	htokens, htoken;
	zbx_mock_error_t	err;

	htokens = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(htokens, &htoken))))
	{
		const char	*data, *value;
		int		i;
		size_t		data_len;

		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("cannot read token value");

		data = zbx_mock_get_object_member_string(htoken, "token");
		value = zbx_mock_get_object_member_string(htoken, "value");
		data_len = strlen(data);

		for (i = 0; i < ctx->stack.values_num; i++)
		{
			zbx_eval_token_t	*token = &ctx->stack.values[i];

			if (data_len == token->loc.r - token->loc.l + 1 &&
					0 == memcmp(data, ctx->expression + token->loc.l, data_len))
			{
				zbx_variant_set_str(&token->value, zbx_strdup(NULL, value));
				break;
			}
		}
	}
}

void	zbx_mock_test_entry(void **state)
{
	zbx_eval_context_t	ctx;
	char			*error = NULL, *ret_expression = NULL;
	zbx_uint64_t		rules;
	const char		*exp_expression;

	ZBX_UNUSED(state);

	rules = mock_eval_read_rules("in.rules");

	if (SUCCEED != zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"), rules, &error))
			fail_msg("failed to parse expression: %s", error);

	replace_values(&ctx, "in.replace");

	exp_expression = zbx_mock_get_parameter_string("out.expression");

	zbx_eval_compose_expression(&ctx, &ret_expression);

	zbx_mock_assert_str_eq("invalid composed expression", exp_expression, ret_expression);

	zbx_free(ret_expression);
	zbx_eval_clear(&ctx);
}
