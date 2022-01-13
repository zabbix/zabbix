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
#include "zbxserver.h"
#include "zbxvariant.h"
#include "mock_eval.h"

zbx_uint64_t	mock_eval_read_rules(const char *path)
{
	zbx_uint64_t		rules = 0;
	zbx_mock_handle_t	hrules, hflag;
	zbx_mock_error_t	err;
	int			rules_num = 0;

	hrules = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hrules, &hflag))))
	{
		const char	*flag;

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hflag, &flag)))
			fail_msg("Cannot read flag #%d: %s", rules_num, zbx_mock_error_string(err));

		if (0 == strcmp(flag, "ZBX_EVAL_PARSE_FUNCTIONID"))
			rules |= ZBX_EVAL_PARSE_FUNCTIONID;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_FUNCTION"))
			rules |= ZBX_EVAL_PARSE_FUNCTION;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_ITEM_QUERY"))
			rules |= ZBX_EVAL_PARSE_ITEM_QUERY;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_MACRO"))
			rules |= ZBX_EVAL_PARSE_MACRO;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_USERMACRO"))
			rules |= ZBX_EVAL_PARSE_USERMACRO;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_LLDMACRO"))
			rules |= ZBX_EVAL_PARSE_LLDMACRO;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_CONST_INDEX"))
			rules |= ZBX_EVAL_PARSE_CONST_INDEX;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_MATH"))
			rules |= ZBX_EVAL_PARSE_MATH;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_COMPARE_EQ"))
			rules |= ZBX_EVAL_PARSE_COMPARE_EQ;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_LOGIC"))
			rules |= ZBX_EVAL_PARSE_LOGIC;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_VAR_NUM"))
			rules |= ZBX_EVAL_PARSE_VAR_NUM;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_VAR_STR"))
			rules |= ZBX_EVAL_PARSE_VAR_STR;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_GROUP"))
			rules |= ZBX_EVAL_PARSE_GROUP;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_PROP_TAG"))
			rules |= ZBX_EVAL_PARSE_PROP_TAG;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_PROP_GROUP"))
			rules |= ZBX_EVAL_PARSE_PROP_GROUP;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_COMPARE"))
			rules |= ZBX_EVAL_PARSE_COMPARE;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_VAR"))
			rules |= ZBX_EVAL_PARSE_VAR;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_PROPERTY"))
			rules |= ZBX_EVAL_PARSE_PROPERTY;
		else if (0 == strcmp(flag, "ZBX_EVAL_COMPOSE_LLD"))
			rules |= ZBX_EVAL_COMPOSE_LLD;
		else if (0 == strcmp(flag, "ZBX_EVAL_COMPOSE_FUNCTIONID"))
			rules |= ZBX_EVAL_COMPOSE_FUNCTIONID;
		else if (0 == strcmp(flag, "ZBX_EVAL_PROCESS_ERROR"))
			rules |= ZBX_EVAL_PROCESS_ERROR;
		else if (0 == strcmp(flag, "ZBX_EVAL_PARSE_COMPOUND_CONST"))
			rules |= ZBX_EVAL_PARSE_COMPOUND_CONST;
		else
			fail_msg("Unsupported flag: %s", flag);

		rules_num++;
	}

	return rules;
}

void	mock_eval_read_values(zbx_eval_context_t *ctx, const char *path)
{
	zbx_mock_handle_t	htokens, htoken, hdata;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS != zbx_mock_parameter(path, &htokens))
		return;

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(htokens, &htoken))))
	{
		const char	*data, *value = NULL, *error = NULL;
		int		i;
		size_t		data_len;

		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("cannot read token contents");

		data = zbx_mock_get_object_member_string(htoken, "token");
		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(htoken, "value", &hdata))
		{
			if (ZBX_MOCK_SUCCESS != zbx_mock_string(hdata, &value))
				fail_msg("invalid token value");
		}
		else if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(htoken, "error", &hdata))
		{
			if (ZBX_MOCK_SUCCESS != zbx_mock_string(hdata, &error))
				fail_msg("invalid token error");
		}
		else
			fail_msg("invalid token contents");

		data_len = strlen(data);

		for (i = 0; i < ctx->stack.values_num; i++)
		{
			zbx_eval_token_t	*token = &ctx->stack.values[i];

			if (data_len == token->loc.r - token->loc.l + 1 &&
					0 == memcmp(data, ctx->expression + token->loc.l, data_len))
			{
				if (NULL != value)
					zbx_variant_set_str(&token->value, zbx_strdup(NULL, value));
				else
					zbx_variant_set_error(&token->value, zbx_strdup(NULL, error));
				break;
			}
		}
	}
}

static const char	*mock_token_type2str(zbx_uint32_t type)
{
#define ZBX_MOCK_TOKEN_CASE(x)	case ZBX_EVAL_TOKEN_##x: return "ZBX_EVAL_TOKEN_" #x;

	switch(type)
	{
		ZBX_MOCK_TOKEN_CASE(OP_ADD)
		ZBX_MOCK_TOKEN_CASE(OP_SUB)
		ZBX_MOCK_TOKEN_CASE(OP_MUL)
		ZBX_MOCK_TOKEN_CASE(OP_DIV)
		ZBX_MOCK_TOKEN_CASE(OP_MINUS)
		ZBX_MOCK_TOKEN_CASE(OP_EQ)
		ZBX_MOCK_TOKEN_CASE(OP_LT)
		ZBX_MOCK_TOKEN_CASE(OP_GT)
		ZBX_MOCK_TOKEN_CASE(OP_LE)
		ZBX_MOCK_TOKEN_CASE(OP_GE)
		ZBX_MOCK_TOKEN_CASE(OP_NE)
		ZBX_MOCK_TOKEN_CASE(OP_AND)
		ZBX_MOCK_TOKEN_CASE(OP_OR)
		ZBX_MOCK_TOKEN_CASE(OP_NOT)
		ZBX_MOCK_TOKEN_CASE(VAR_NUM)
		ZBX_MOCK_TOKEN_CASE(VAR_STR)
		ZBX_MOCK_TOKEN_CASE(VAR_MACRO)
		ZBX_MOCK_TOKEN_CASE(VAR_USERMACRO)
		ZBX_MOCK_TOKEN_CASE(VAR_LLDMACRO)
		ZBX_MOCK_TOKEN_CASE(FUNCTIONID)
		ZBX_MOCK_TOKEN_CASE(FUNCTION)
		ZBX_MOCK_TOKEN_CASE(HIST_FUNCTION)
		ZBX_MOCK_TOKEN_CASE(GROUP_OPEN)
		ZBX_MOCK_TOKEN_CASE(GROUP_CLOSE)
		ZBX_MOCK_TOKEN_CASE(COMMA)
		ZBX_MOCK_TOKEN_CASE(ARG_QUERY)
		ZBX_MOCK_TOKEN_CASE(ARG_PERIOD)
		ZBX_MOCK_TOKEN_CASE(ARG_NULL)
		ZBX_MOCK_TOKEN_CASE(PROP_TAG)
		ZBX_MOCK_TOKEN_CASE(PROP_GROUP)
		ZBX_MOCK_TOKEN_CASE(NOP)
	}

	fail_msg("unknown token type: %d", type);
	return NULL;

#undef ZBX_MOCK_TOKEN_CASE
}

static zbx_uint32_t	mock_token_str2type(const char *str)
{
#define ZBX_MOCK_TOKEN_IF(x)	if (0 == strcmp(str, "ZBX_EVAL_TOKEN_" #x)) return ZBX_EVAL_TOKEN_##x;

	ZBX_MOCK_TOKEN_IF(OP_ADD)
	ZBX_MOCK_TOKEN_IF(OP_SUB)
	ZBX_MOCK_TOKEN_IF(OP_MUL)
	ZBX_MOCK_TOKEN_IF(OP_DIV)
	ZBX_MOCK_TOKEN_IF(OP_MINUS)
	ZBX_MOCK_TOKEN_IF(OP_EQ)
	ZBX_MOCK_TOKEN_IF(OP_LT)
	ZBX_MOCK_TOKEN_IF(OP_GT)
	ZBX_MOCK_TOKEN_IF(OP_LE)
	ZBX_MOCK_TOKEN_IF(OP_GE)
	ZBX_MOCK_TOKEN_IF(OP_NE)
	ZBX_MOCK_TOKEN_IF(OP_AND)
	ZBX_MOCK_TOKEN_IF(OP_OR)
	ZBX_MOCK_TOKEN_IF(OP_NOT)
	ZBX_MOCK_TOKEN_IF(VAR_NUM)
	ZBX_MOCK_TOKEN_IF(VAR_STR)
	ZBX_MOCK_TOKEN_IF(VAR_MACRO)
	ZBX_MOCK_TOKEN_IF(VAR_USERMACRO)
	ZBX_MOCK_TOKEN_IF(VAR_LLDMACRO)
	ZBX_MOCK_TOKEN_IF(FUNCTIONID)
	ZBX_MOCK_TOKEN_IF(FUNCTION)
	ZBX_MOCK_TOKEN_IF(HIST_FUNCTION)
	ZBX_MOCK_TOKEN_IF(GROUP_OPEN)
	ZBX_MOCK_TOKEN_IF(GROUP_CLOSE)
	ZBX_MOCK_TOKEN_IF(COMMA)
	ZBX_MOCK_TOKEN_IF(ARG_QUERY)
	ZBX_MOCK_TOKEN_IF(ARG_PERIOD)
	ZBX_MOCK_TOKEN_IF(ARG_NULL)
	ZBX_MOCK_TOKEN_IF(PROP_TAG)
	ZBX_MOCK_TOKEN_IF(PROP_GROUP)
	ZBX_MOCK_TOKEN_IF(NOP)

	fail_msg("unknown token type %s", str);
	return 0;

#undef ZBX_MOCK_TOKEN_IF
}

void	mock_compare_stack(const zbx_eval_context_t *ctx, const char *path)
{
	int			token_num = 0, len;
	zbx_mock_handle_t	htokens, htoken;
	zbx_mock_error_t	err;
	zbx_uint32_t		expected_type, expected_opt;
	const char		*expected_token;
	const zbx_eval_token_t	*token;

	htokens = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(htokens, &htoken))))
	{
		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("cannot read token #%d: %s", token_num, zbx_mock_error_string(err));

		if (token_num == ctx->stack.values_num)
		{
			mock_dump_stack(ctx);
			fail_msg("expected more than %d tokens", token_num);
		}

		token = &ctx->stack.values[token_num++];

		expected_type = mock_token_str2type(zbx_mock_get_object_member_string(htoken, "type"));
		expected_token = zbx_mock_get_object_member_string(htoken, "token");
		expected_opt = (zbx_uint32_t)zbx_mock_get_object_member_uint64(htoken, "opt");

		if (expected_type != token->type)
		{
			mock_dump_stack(ctx);
			fail_msg( "expected token #%d type %s while got %s", token_num,
					mock_token_type2str(expected_type), mock_token_type2str(token->type));
		}

		if (ZBX_EVAL_TOKEN_NOP == token->type)
			continue;

		if (expected_opt != token->opt)
		{
			mock_dump_stack(ctx);
			fail_msg("expected token optional data %d while got %d", expected_opt, token->opt);
		}

		len = token->loc.r - token->loc.l + 1;
		if (ZBX_EVAL_TOKEN_ARG_NULL != token->type &&
				0 != strncmp(expected_token, ctx->expression + token->loc.l, len))
		{
			mock_dump_stack(ctx);
			fail_msg("expected token %s while got %.*s", expected_token, len,
					ctx->expression + token->loc.l);
		}
	}

	if (token_num != ctx->stack.values_num)
	{
		mock_dump_stack(ctx);
		fail_msg("expected %d tokens while got more", token_num);
	}
}


static void	dump_token(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token)
{
	if (ZBX_EVAL_TOKEN_ARG_NULL == token->type)
	{
		printf("\t(null)");
	}
	if (ZBX_EVAL_TOKEN_OP_MINUS == token->type)
	{
		printf("\t'-'");
	}
	if (ZBX_EVAL_TOKEN_NOP == token->type)
	{
		printf("\t'.'");
	}
	else
	{
		if (ZBX_VARIANT_NONE == token->value.type)
			printf("\t%.*s", (int)(token->loc.r - token->loc.l + 1), ctx->expression + token->loc.l);
		else
			printf("\t'%s'", zbx_variant_value_desc(&token->value));
	}

	printf(" : %s (%u)\n", mock_token_type2str(token->type), (unsigned int)token->opt);
}

void	mock_dump_stack(const zbx_eval_context_t *ctx)
{
	int	i;

	printf("STACK:\n");

	for (i = 0; i < ctx->stack.values_num; i++)
		dump_token(ctx, &ctx->stack.values[i]);
}
