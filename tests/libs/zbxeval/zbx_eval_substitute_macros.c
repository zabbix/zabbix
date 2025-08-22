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

static int	macro_resolver(zbx_token_type_t token_type, char **value, char **error, va_list args)
{
	const char	*macro = va_arg(args, const char *);
	char		*macro_data = va_arg(args, char *);
	int		ret = SUCCEED;

	ZBX_UNUSED(error);

	switch(token_type)
	{
		case ZBX_EVAL_TOKEN_VAR_STR:
		case ZBX_EVAL_TOKEN_VAR_MACRO:
		case ZBX_EVAL_TOKEN_ARG_PERIOD:
		case ZBX_EVAL_TOKEN_VAR_NUM:
			if (0 == strcmp(*value, macro))
			{
				zbx_free(*value);
				*value = zbx_strdup(NULL, macro_data);
			}
			break;
		case ZBX_EVAL_PARSE_ITEM_QUERY:
			ret = zbx_eval_query_subtitute_user_macros(*value, strlen(*value), value, error,
					query_macro_resolver, macro, macro_data);
			break;
		default:
			return ret;
			break;
	}

	return ret;
}

static void	set_variant_error(zbx_eval_context_t ctx, const char *variant_data)
{
	for (int i = 0; i < ctx.stack.values_num; i++)
	{
		if (ZBX_EVAL_TOKEN_VAR_STR == ctx.stack.values[i].type)
			zbx_variant_set_error(&ctx.stack.values[i].value, zbx_strdup(NULL, variant_data));
	}
}

static void 	set_variant_ui64(zbx_eval_context_t ctx, zbx_uint64_t variant_data)
{
	for (int i = 0; i < ctx.stack.values_num; i++)
	{
		if (ZBX_EVAL_TOKEN_VAR_STR == ctx.stack.values[i].type)
			zbx_variant_set_ui64(&ctx.stack.values[i].value, variant_data);
	}
}

void	zbx_mock_test_entry(void **state)
{
	int			returned_ret, ret,
				exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	zbx_eval_context_t	ctx;
	char			*error = NULL,
				*macro_data = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.macro_data"));
	const char		*macro = zbx_mock_get_parameter_string("in.macro");
	zbx_uint64_t		rules = mock_eval_read_rules("in.rules");

	ZBX_UNUSED(state);

	returned_ret = zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"), rules, &error);

	if (SUCCEED != returned_ret)
		printf("ERROR: %s\n", error);
	else
		mock_dump_stack(&ctx);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.variant"))
	{
		if (0 == strcmp("ERROR", zbx_mock_get_parameter_string("in.variant")))
			set_variant_error(ctx, zbx_mock_get_parameter_string("in.variant_data"));
		if (0 == strcmp("UI64", zbx_mock_get_parameter_string("in.variant")))
			set_variant_ui64(ctx, zbx_mock_get_parameter_uint64("in.variant_data"));
	}

	ret = zbx_eval_substitute_macros(&ctx, &error, macro_resolver, macro, macro_data);

	zbx_mock_assert_int_eq("returned value:", exp_result, ret);

	zbx_eval_clear(&ctx);
	zbx_free(error);
	zbx_free(macro_data);
}
