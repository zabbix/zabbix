/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

#include "common.h"
#include "log.h"

#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"
#include "zbxvariant.h"
#include "zbxserialize.h"
#include "zbxserver.h"

ZBX_VECTOR_IMPL(eval_token, zbx_eval_token_t)

static int	is_whitespace(unsigned char c)
{
	return 0 != isspace(c) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_get_whitespace_len                                          *
 *                                                                            *
 * Purpose: find the number of following whitespace characters                *
 *                                                                            *
 * Parameters: ctx - [IN] the evaluation context                              *
 *             pos - [IN] the starting position                               *
 *                                                                            *
 * Return value: The number of whitespace characters found.                   *
 *                                                                            *
 ******************************************************************************/
static size_t	eval_get_whitespace_len(zbx_eval_context_t *ctx, size_t pos)
{
	const char	*ptr = ctx->expression + pos;

	while (SUCCEED == is_whitespace(*ptr))
		ptr++;

	return ptr - ctx->expression - pos;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_update_const_variable                                       *
 *                                                                            *
 * Purpose: update constant variable index in the trigger expression          *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             token - [IN] the variable token                                *
 *                                                                            *
 * Comments: The index is used to refer constant values by using $<N> in      *
 *           trigger names. Function arguments are excluded.                  *
 *                                                                            *
 ******************************************************************************/
static void	eval_update_const_variable(zbx_eval_context_t *ctx, zbx_eval_token_t *token)
{
	zbx_variant_set_none(&token->value);

	if (0 != (ctx->rules & ZBX_EVAL_PARSE_CONST_INDEX))
	{
		int	i;

		for (i = 0; i < ctx->ops.values_num; i++)
		{
			if (0 != (ctx->ops.values[i].type & ZBX_EVAL_CLASS_FUNCTION))
				return;
		}

		token->opt = ctx->const_index++;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_macro                                                 *
 *                                                                            *
 * Purpose: parse stand-alone macro token                                     *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *             error - [OUT] the error message in the case of failure         *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Stand-alone macro tokens are either expression operands or       *
 *           function arguments. If macro is embedded in other token (string  *
 *           variable, user macro context etc), then this macro is not parsed *
 *           separately and is stored within the other token.                 *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_macro(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token, char **error)
{
	zbx_token_t	tok;

	if (0 != (ctx->rules & ZBX_EVAL_PARSE_FUNCTIONID) &&
			SUCCEED == zbx_token_parse_objectid(ctx->expression, ctx->expression + pos, &tok))
	{
		token->type = ZBX_EVAL_TOKEN_FUNCTIONID;
		token->opt = ctx->functionid_index++;
	}
	else if (0 != (ctx->rules & ZBX_EVAL_PARSE_MACRO) &&
			SUCCEED == zbx_token_parse_macro(ctx->expression, ctx->expression + pos, &tok))
	{
		token->type = ZBX_EVAL_TOKEN_VAR_MACRO;
		eval_update_const_variable(ctx, token);
	}
	else if (0 != (ctx->rules & ZBX_EVAL_PARSE_USERMACRO) && '$' == ctx->expression[pos + 1] &&
			SUCCEED == zbx_token_parse_user_macro(ctx->expression, ctx->expression + pos, &tok))
	{
		token->type = ZBX_EVAL_TOKEN_VAR_USERMACRO;
		eval_update_const_variable(ctx, token);
	}
	else if (0 != (ctx->rules & ZBX_EVAL_PARSE_LLDMACRO) && '#' == ctx->expression[pos + 1] &&
			SUCCEED == zbx_token_parse_lld_macro(ctx->expression, ctx->expression + pos, &tok))
	{
		token->type = ZBX_EVAL_TOKEN_VAR_LLDMACRO;
	}
	else
	{
		*error = zbx_dsprintf(*error, "invalid token starting with \"%s\"", ctx->expression + pos);
		return FAIL;
	}

	token->loc = tok.loc;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_character_token                                       *
 *                                                                            *
 * Purpose: parse single character token                                      *
 *                                                                            *
 * Parameters: pos   - [IN] the starting position                             *
 *             type  - [IN] the token type                                    *
 *             token - [OUT] the parsed token                                 *
 *                                                                            *
 ******************************************************************************/
static void	eval_parse_character_token(size_t pos, zbx_token_type_t type, zbx_eval_token_t *token)
{
	token->type = type;
	token->loc.l = pos;
	token->loc.r = pos;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_less_character_token                                  *
 *                                                                            *
 * Purpose: parse token starting with  '<'                                    *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *                                                                            *
 * Comments: Tokens starting with '<' are '<', '<=' and '<>'.                 *
 *                                                                            *
 ******************************************************************************/
static void	eval_parse_less_character_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
{
	switch (ctx->expression[pos + 1])
	{
		case '>':
			token->type = ZBX_EVAL_TOKEN_OP_NE;
			break;
		case '=':
			token->type = ZBX_EVAL_TOKEN_OP_LE;
			break;
		default:
			eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_LT, token);
			return;
	}
	token->loc.l = pos;
	token->loc.r = pos + 1;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_greater_character_token                               *
 *                                                                            *
 * Purpose: parse token starting with  '>'                                    *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *                                                                            *
 * Comments: Tokens starting with '>' are '>' and '>='.                       *
 *                                                                            *
 ******************************************************************************/
static void	eval_parse_greater_character_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
{
	if ('=' == ctx->expression[pos + 1])
	{
		token->type = ZBX_EVAL_TOKEN_OP_GE;
		token->loc.l = pos;
		token->loc.r = pos + 1;
	}
	else
		eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_GT, token);
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_string_token                                          *
 *                                                                            *
 * Purpose: parse string variable token                                       *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *             error - [OUT] the error message in the case of failure         *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: String variable token is token starting with '"'.                *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_string_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token, char **error)
{
	const char	*ptr = ctx->expression + pos + 1;

	for (; '\0' != *ptr; ptr++)
	{
		if (*ptr == '"')
		{
			token->type = ZBX_EVAL_TOKEN_VAR_STR;
			token->loc.l = pos;
			token->loc.r = ptr - ctx->expression;
			eval_update_const_variable(ctx, token);
			return SUCCEED;
		}

		if ('\\' == *ptr)
		{
			if ('"' != ptr[1] && '\\' != ptr[1] )
			{
				*error = zbx_dsprintf(*error, "invalid escape sequence in string starting with \"%s\"",
						ptr);
				return FAIL;
			}
			ptr++;
		}
	}

	*error = zbx_dsprintf(*error, "unterminated string at \"%s\"", ctx->expression + pos);
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_number_token                                          *
 *                                                                            *
 * Purpose: parse numeric variable token                                      *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *             error - [OUT] the error message in the case of failure         *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 *  Comments: Time suffixes s,m,h,d,w are supported.                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_number_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token, char **error)
{
	int		len;
	char		*end;
	double		tmp;

	if (FAIL == zbx_number_parse(ctx->expression + pos, &len))
		goto error;

	if (SUCCEED == is_time_suffix(ctx->expression + pos, NULL, len + 1))
	{
		len++;
		token->type = ZBX_EVAL_TOKEN_VAR_TIME;
	}
	else
	{
		tmp = strtod(ctx->expression + pos, &end);

		if (ctx->expression + pos + len != end || HUGE_VAL == tmp || -HUGE_VAL == tmp || EDOM == errno)
			goto error;

		token->type = ZBX_EVAL_TOKEN_VAR_NUM;
	}

	token->loc.l = pos;
	token->loc.r = pos + len - 1;
	eval_update_const_variable(ctx, token);

	return SUCCEED;
error:
	*error = zbx_dsprintf(*error, "invalid numeric value at \"%s\"", ctx->expression + pos);
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_keyword_token                                         *
 *                                                                            *
 * Purpose: parse keyword token                                               *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Keywords are 'and', 'or' and 'not', followed by separator        *
 *           character (whitespace or '(').                                   *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_keyword_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
{
	if (0 == strncmp(ctx->expression + pos, "and", 3))
	{
		token->loc.r = pos + 2;
		token->type = ZBX_EVAL_TOKEN_OP_AND;
	}
	else if (0 == strncmp(ctx->expression + pos, "or", 2))
	{
		token->loc.r = pos + 1;
		token->type = ZBX_EVAL_TOKEN_OP_OR;
	}
	else if (0 == strncmp(ctx->expression + pos, "not", 3))
	{
		token->loc.r = pos + 2;
		token->type = ZBX_EVAL_TOKEN_OP_NOT;
	}
	else
		return FAIL;

	/* keyword must be followed by whitespace or opening parenthesis */
	if ('(' != ctx->expression[token->loc.r + 1] && SUCCEED != is_whitespace(ctx->expression[token->loc.r + 1]))
		return FAIL;

	token->loc.l = pos;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_function_token                                        *
 *                                                                            *
 * Purpose: parse function token                                              *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Function token is non-keyword alpha characters followed by '('.  *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_function_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
{
	const char	*ptr = ctx->expression + pos;

	while (0 != isalpha((unsigned char)*ptr) || '_' == *ptr)
		ptr++;

	if ('(' == *ptr)
	{
		token->type = ZBX_EVAL_TOKEN_FUNCTION;
		token->loc.l = pos;
		token->loc.r = ptr - ctx->expression - 1;
		token->opt = ctx->stack.values_num;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_query_token                                           *
 *                                                                            *
 * Purpose: parse history query token                                         *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *             error - [OUT] the error message in the case of failure         *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: History query token is the first argument of history functions   *
 *           to specify item(s) in format /host/key                           *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_query_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token, char **error)
{
#define MVAR_HOST_HOST	"{HOST.HOST}"

	const char	*ptr = ctx->expression + pos + 1, *key;

	switch (*ptr)
	{
		case '*':
			ptr++;
			break;
		case '{':
			if (0 == strncmp(ptr, MVAR_HOST_HOST, ZBX_CONST_STRLEN(MVAR_HOST_HOST)))
				ptr += ZBX_CONST_STRLEN(MVAR_HOST_HOST);
			break;
		default:
			while (SUCCEED == is_hostname_char(*ptr))
				ptr++;
	}

	if ('/' != *ptr)
	{
		*error = zbx_dsprintf(*error, "invalid host name in query starting at \"%s\"", ctx->expression + pos);
		return FAIL;
	}

	key = ++ptr;
	if (SUCCEED != parse_key(&ptr))
	{
		*error = zbx_dsprintf(*error, "invalid item key at \"%s\"", key);
		return FAIL;

	}

	token->type = ZBX_EVAL_TOKEN_ARG_QUERY;
	token->loc.l = pos;
	token->loc.r = ptr - ctx->expression - 1;

	return SUCCEED;

#undef MVAR_HOST_HOST
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_time_token                                            *
 *                                                                            *
 * Purpose: parse time period token                                           *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *             error - [OUT] the error message in the case of failure         *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Time period token is the second argument of history functions    *
 *           to specify the history range in format <period>[:<timeshift>]    *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_time_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token, char **error)
{
	const char	*ptr = ctx->expression + pos;

	for (;0 != *ptr; ptr++)
	{
		if (',' == *ptr || ')' == *ptr || SUCCEED == is_whitespace(*ptr))
		{
			token->type = ZBX_EVAL_TOKEN_ARG_TIME;
			token->loc.l = pos;
			token->loc.r = ptr - ctx->expression - 1;
			zbx_variant_set_none(&token->value);

			return SUCCEED;
		}
	}

	*error = zbx_dsprintf(*error, "unterminated function at \"%s\"", ctx->expression + pos);
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_token                                                 *
 *                                                                            *
 * Purpose: parse token                                                       *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *             error - [OUT] the error message in the case of failure         *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token, char **error)
{
	size_t	skip;

	skip = eval_get_whitespace_len(ctx, pos);
	pos += skip;

	switch (ctx->expression[pos])
	{
		case '{':
			return eval_parse_macro(ctx, pos, token, error);
		case '+':
			eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_ADD, token);
			return SUCCEED;
		case '-':
			if (0 == (ctx->last_token_type & ZBX_EVAL_CLASS_OPERAND))
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_MINUS, token);
			else
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_SUB, token);
			return SUCCEED;
		case '/':
			if (ZBX_EVAL_TOKEN_GROUP_OPEN == ctx->last_token_type &&
					0 != (ctx->rules & ZBX_EVAL_PARSE_ITEM_QUERY))
			{
				zbx_eval_token_t	*func_token = NULL;

				if (2 <= ctx->ops.values_num)
					func_token = &ctx->ops.values[ctx->ops.values_num - 2];

				if (NULL == func_token && 0 == (func_token->type & ZBX_EVAL_CLASS_FUNCTION))
				{
					*error = zbx_dsprintf(*error, "item query must be first argument of a"
							" historical function at \"%s\"", ctx->expression + pos);
					return FAIL;
				}
				func_token->type = ZBX_EVAL_TOKEN_HIST_FUNCTION;
				return eval_parse_query_token(ctx, pos, token, error);
			}
			else
			{
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_DIV, token);
				return SUCCEED;
			}
		case '*':
			eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_MUL, token);
			return SUCCEED;
		case '<':
			eval_parse_less_character_token(ctx, pos, token);
			return SUCCEED;
		case '>':
			eval_parse_greater_character_token(ctx, pos, token);
			return SUCCEED;
		case '=':
			eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_EQ, token);
			return SUCCEED;
		case '(':
			eval_parse_character_token(pos, ZBX_EVAL_TOKEN_GROUP_OPEN, token);
			return SUCCEED;
		case ')':
			eval_parse_character_token(pos, ZBX_EVAL_TOKEN_GROUP_CLOSE, token);
			return SUCCEED;
		case '"':
			return eval_parse_string_token(ctx, pos, token, error);
		case '0':
		case '1':
		case '2':
		case '3':
		case '4':
		case '5':
		case '6':
		case '7':
		case '8':
		case '9':
			/* after ',' there will be at least one value on the stack */
			if (ZBX_EVAL_TOKEN_COMMA == ctx->last_token_type &&
				ZBX_EVAL_TOKEN_ARG_QUERY == ctx->stack.values[ctx->stack.values_num - 1].type)
			{
				return eval_parse_time_token(ctx, pos, token, error);
			}
			else
				return eval_parse_number_token(ctx, pos, token, error);
		case ',':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_FUNCTION))
			{
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_COMMA, token);
				return SUCCEED;
			}
			break;
		case '\0':
			return SUCCEED;
		default:
			if (0 != isalpha((unsigned char)ctx->expression[pos]))
			{
				/* keyword must be separated by whitespace or '(', ')', ',' characters */
				if ((0 != skip || 0 != (ctx->last_token_type & ZBX_EVAL_CLASS_SEPARATOR) ||
						ZBX_EVAL_TOKEN_GROUP_CLOSE == ctx->last_token_type))
				{
					if (SUCCEED == eval_parse_keyword_token(ctx, pos, token))
						return SUCCEED;
				}

				if (0 != (ctx->rules & ZBX_EVAL_PARSE_FUNCTION) &&
						SUCCEED == eval_parse_function_token(ctx, pos, token))
				{
					return SUCCEED;
				}
			}
			break;
	}

	*error = zbx_dsprintf(*error, "invalid token starting with \"%s\"", ctx->expression + pos);
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_append_operator                                             *
 *                                                                            *
 * Purpose: add operator/function token to evaluation stack                   *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             token - [IN] the token to add                                  *
 *                                                                            *
 ******************************************************************************/
static void	eval_append_operator(zbx_eval_context_t *ctx, zbx_eval_token_t *token)
{
	if (0 != (token->type & ZBX_EVAL_CLASS_FUNCTION))
	{
		int	i, params = 0;

		for (i = token->opt; i < ctx->stack.values_num; i++)
		{
			if (0 != (ctx->stack.values[i].type & ZBX_EVAL_CLASS_FUNCTION))
				params -= ctx->stack.values[i].opt - 1;
			else if (0 != (ctx->stack.values[i].type & ZBX_EVAL_CLASS_OPERAND))
				params++;
			else if (0 != (ctx->stack.values[i].type & ZBX_EVAL_CLASS_OPERATOR2))
				params--;
		}

		token->opt = params;
	}

	zbx_vector_eval_token_append_ptr(&ctx->stack, token);
}

/******************************************************************************
 *                                                                            *
 * Function: eval_append_arg_null                                             *
 *                                                                            *
 * Purpose: add null argument token to evaluation stack                       *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *                                                                            *
 ******************************************************************************/
static void	eval_append_arg_null(zbx_eval_context_t *ctx)
{
	zbx_eval_token_t	null_token = {.type = ZBX_EVAL_TOKEN_ARG_NULL};

	zbx_vector_eval_token_append_ptr(&ctx->stack, &null_token);
}

/******************************************************************************
 *                                                                            *
 * Function: eval_clean                                                       *
 *                                                                            *
 * Purpose: free resources allocated by evaluation context                    *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *                                                                            *
 ******************************************************************************/
static void	eval_clean(zbx_eval_context_t *ctx)
{
	int	i;

	for (i = 0; i < ctx->stack.values_num; i++)
		zbx_variant_clear(&ctx->stack.values[i].value);

	zbx_vector_eval_token_destroy(&ctx->stack);
}

/******************************************************************************
 *                                                                            *
 * Function: eval_parse_expression                                            *
 *                                                                            *
 * Purpose: parse expression into tokens in postfix notation order            *
 *                                                                            *
 * Parameters: ctx        - [OUT] the evaluation context                      *
 *             expression - [IN] the expression to parse                      *
 *             rules      - [IN] the parsing rules                            *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - expression was parsed successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_expression(zbx_eval_context_t *ctx, const char *expression, zbx_uint64_t rules, char **error)
{
	size_t			pos = 0;
	int			ret = FAIL;
	zbx_eval_token_t	*optoken;

	ctx->expression = expression;
	ctx->rules = rules;
	ctx->last_token_type = ZBX_EVAL_CLASS_SEPARATOR;
	ctx->const_index = 0;
	ctx->functionid_index = 0;
	zbx_vector_eval_token_create(&ctx->stack);
	zbx_vector_eval_token_reserve(&ctx->stack, 16);
	zbx_vector_eval_token_create(&ctx->ops);
	zbx_vector_eval_token_reserve(&ctx->ops, 16);

	while ('\0' != expression[pos])
	{
		zbx_eval_token_t	token = {0};

		if (SUCCEED != eval_parse_token(ctx, pos, &token, error))
			goto out;

		if (0 == token.type)
			break;

		/* serialization used to for parsed expression caching has limits expression to 0x7fffffff */
		if ((zbx_uint32_t)0x7fffffff < token.loc.r)
		{
			*error = zbx_strdup(*error, "too long expression");
			goto out;
		}

		if (ZBX_EVAL_TOKEN_ARG_QUERY == ctx->last_token_type && ZBX_EVAL_TOKEN_COMMA != token.type)
		{
			*error = zbx_dsprintf(*error, "invalid expression following query token at \"%s\"",
					ctx->expression + pos);
			goto out;
		}

		if (ZBX_EVAL_TOKEN_GROUP_CLOSE == token.type && ZBX_EVAL_TOKEN_COMMA == ctx->last_token_type)
			eval_append_arg_null(ctx);


		if (ZBX_EVAL_TOKEN_COMMA == token.type && 0 != (ctx->last_token_type & ZBX_EVAL_CLASS_SEPARATOR))
			eval_append_arg_null(ctx);

		if (ZBX_EVAL_TOKEN_GROUP_OPEN == token.type)
		{
			if (0 == (ctx->last_token_type & (ZBX_EVAL_BEFORE_OPERAND | ZBX_EVAL_CLASS_FUNCTION)))
			{
				*error = zbx_dsprintf(*error, "opening parenthesis must follow operator or function"
						" at \"%s\"", ctx->expression + pos);
				goto out;
			}

			zbx_vector_eval_token_append_ptr(&ctx->ops, &token);
		}
		else if (0 != (token.type & ZBX_EVAL_CLASS_FUNCTION))
		{
			if (0 == (ctx->last_token_type & ZBX_EVAL_BEFORE_OPERAND))
			{
				*error = zbx_dsprintf(*error, "function must follow operand or unary operator"
						" at \"%s\"", ctx->expression + pos);
				goto out;
			}
			zbx_vector_eval_token_append_ptr(&ctx->ops, &token);
		}
		else if (ZBX_EVAL_TOKEN_GROUP_CLOSE == token.type)
		{
			/* right parenthesis must follow and operand, right parenthesis or function */
			if (0 == (ctx->last_token_type & ZBX_EVAL_CLASS_OPERAND) &&
					0 == (ctx->last_token_type & ZBX_EVAL_CLASS_SEPARATOR) &&
					(ctx->ops.values_num < 2 ||
					ZBX_EVAL_TOKEN_FUNCTION != ctx->ops.values[ctx->ops.values_num - 2].type))
			{
				*error = zbx_dsprintf(*error, "right parenthesis must follow an operand or left"
						" parenthesis at \"%s\"", ctx->expression + pos);
				goto out;
			}

			for (optoken = NULL; 0 < ctx->ops.values_num; ctx->ops.values_num--)
			{
				optoken = &ctx->ops.values[ctx->ops.values_num - 1];

				if (ZBX_EVAL_TOKEN_GROUP_OPEN == optoken->type)
				{
					ctx->ops.values_num--;
					break;
				}

				eval_append_operator(ctx, optoken);
			}

			if (NULL == optoken)
			{
				*error = zbx_dsprintf(*error, "missing left parenthesis for right parenthesis at \"%s\"",
						ctx->expression + pos);
				goto out;
			}

			if (0 != ctx->ops.values_num)
			{
				optoken = &ctx->ops.values[ctx->ops.values_num - 1];

				if (0 != (optoken->type & ZBX_EVAL_CLASS_FUNCTION))
				{
					eval_append_operator(ctx, optoken);
					ctx->ops.values_num--;
				}
			}
		}
		else if (0 != (token.type & ZBX_EVAL_CLASS_OPERAND))
		{
			if (0 == (ctx->last_token_type & ZBX_EVAL_BEFORE_OPERAND))
			{
				*error = zbx_dsprintf(*error, "operand following another operand at \"%s\"",
						ctx->expression + pos);
				goto out;
			}
			zbx_vector_eval_token_append_ptr(&ctx->stack, &token);
		}
		else if (0 != (token.type & ZBX_EVAL_CLASS_OPERATOR))
		{
			/* binary operator cannot be used after operator */
			if (0 != (token.type & ZBX_EVAL_CLASS_OPERATOR2) &&
					0 == (ctx->last_token_type & ZBX_EVAL_BEFORE_OPERATOR))
			{
				*error = zbx_dsprintf(*error, "binary operator must be used after operand at \"%s\"",
						ctx->expression + pos);
				goto out;
			}

			/* unary !,- operators cannot follow an operand */
			if (0 != (token.type & ZBX_EVAL_CLASS_OPERATOR1) &&
					0 == (ctx->last_token_type & ZBX_EVAL_BEFORE_OPERAND))
			{
				*error = zbx_dsprintf(*error, "unary operator cannot follow an operand at \"%s\"",
						ctx->expression + pos);
				goto out;
			}

			for (; 0 < ctx->ops.values_num; ctx->ops.values_num--)
			{
				optoken = &ctx->ops.values[ctx->ops.values_num - 1];

				if ((optoken->type & ZBX_EVAL_OP_PRIORITY) > (token.type & ZBX_EVAL_OP_PRIORITY) ||
						0 != (token.type & ZBX_EVAL_CLASS_OPERATOR1))
					break;

				if (ZBX_EVAL_TOKEN_GROUP_OPEN == optoken->type)
					break;

				eval_append_operator(ctx, optoken);
			}

			zbx_vector_eval_token_append_ptr(&ctx->ops, &token);
		}

		ctx->last_token_type = token.type;
		pos = token.loc.r + 1;
	}

	if (0 != (ctx->last_token_type & ZBX_EVAL_CLASS_OPERATOR))
	{
		*error = zbx_strdup(*error, "expression ends with operator");
		goto out;
	}

	for (; 0 < ctx->ops.values_num; ctx->ops.values_num--)
	{
		optoken = &ctx->ops.values[ctx->ops.values_num - 1];

		if (ZBX_EVAL_TOKEN_GROUP_OPEN == optoken->type)
		{
			*error = zbx_dsprintf(*error, "mismatched () brackets in expression: %s", ctx->expression);
			goto out;
		}

		eval_append_operator(ctx, optoken);
	}

	if (0 == ctx->stack.values_num)
	{
		*error = zbx_strdup(*error, "empty expression");
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_vector_eval_token_destroy(&ctx->ops);

	if (SUCCEED != ret)
		eval_clean(ctx);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: serialize_uint31_compact                                         *
 *                                                                            *
 * Purpose: serialize 31 bit unsigned integer into utf-8 like byte stream     *
 *                                                                            *
 * Parameters: ptr   - [OUT] the output buffer                                *
 *             value - [IN] the value to serialize                            *
 *                                                                            *
 * Return value: The number of bytes written to the buffer.                   *
 *                                                                            *
 * Comments: This serialization method should be used with variables usually  *
 *           having small value while still supporting larger values.         *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	serialize_uint31_compact(unsigned char *ptr, zbx_uint32_t value)
{
	if (0x7f >= value)
	{
		ptr[0] = (unsigned char)value;
		return 1;
	}
	else
	{
		unsigned char	buf[6];
		int		pos = sizeof(buf) - 1;
		zbx_uint32_t	len;

		while (value > (zbx_uint32_t)(0x3f >> (sizeof(buf) - pos)))
		{
			buf[pos] = 0x80 | (value & 0x3f);
			value >>= 6;
			pos--;
		}

		buf[pos] = value | (0xfe << (pos + 1));

		len = sizeof(buf) - pos;
		memcpy(ptr, buf + pos, len);
		return len;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: deserialize_uint31_compact                                       *
 *                                                                            *
 * Purpose: deserialize 31 bit unsigned integer from utf-8 like byte stream   *
 *                                                                            *
 * Parameters: ptr   - [IN] the byte strem                                    *
 *             value - [OUT] the deserialized value                           *
 *                                                                            *
 * Return value: The number of bytes read from byte strean.                   *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	deserialize_uint31_compact(const unsigned char *ptr, zbx_uint32_t *value)
{
	if (0 == (*ptr & 0x80))
	{
		*value = *ptr;
		return 1;
	}
	else
	{
		int	pos = 2, i;

		while (0 != (*ptr & (0x80 >> pos)))
			pos++;

		*value = *ptr & (0xff >> (pos + 1));

		for (i = 1; i < pos; i++)
		{
			*value <<= 6;
			*value |= (*(++ptr)) & 0x3f;
		}

		return pos;
	}
}

#define ZBX_EVAL_STATIC_BUFFER_SIZE	4096

/******************************************************************************
 *                                                                            *
 * Function: reserve_buffer                                                   *
 *                                                                            *
 * Purpose: reserve number of bytes in the specified buffer, reallocating if  *
 *          necessary                                                         *
 *                                                                            *
 * Parameters: buffer      - [IN/OUT] the buffer                              *
 *             buffer_size - [INT/OUT] the deserialized value                 *
 *             reserve     - [IN] the number of bytes to reserve              *
 *             ptr         - [IN/OUT] a pointer to an offset in buffer        *
 *                                                                            *
 * Comments: Initially static buffer is used, allocating dynamic buffer when  *
 *           static buffer is too small.                                      *
 *                                                                            *
 ******************************************************************************/
static	void	reserve_buffer(unsigned char **buffer, size_t *buffer_size, size_t reserve, unsigned char **ptr)
{
	size_t		offset = *ptr - *buffer, new_size;

	if (offset + reserve <= *buffer_size)
		return;

	new_size = *buffer_size * 1.5;

	if (ZBX_EVAL_STATIC_BUFFER_SIZE == *buffer_size)
	{
		unsigned char	*old = *buffer;

		*buffer = zbx_malloc(NULL, new_size);
		memcpy(*buffer, old, offset);
	}
	else
		*buffer = zbx_realloc(*buffer, new_size);

	*buffer_size = new_size;
	*ptr = *buffer + offset;
}

static void	serialize_variant(unsigned char **buffer, size_t *size, const zbx_variant_t *value,
		unsigned char **ptr)
{
	size_t		len;

	reserve_buffer(buffer, size, 1, ptr);
	**ptr = value->type;
	(*ptr)++;

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			reserve_buffer(buffer, size, sizeof(value->data.ui64), ptr);
			*ptr += zbx_serialize_uint64(*ptr, value->data.ui64);
			break;
		case ZBX_VARIANT_DBL:
			reserve_buffer(buffer, size, sizeof(value->data.dbl), ptr);
			*ptr += zbx_serialize_double(*ptr, value->data.dbl) + 1;
			break;
		case ZBX_VARIANT_STR:
			len = strlen(value->data.str) + 1;
			reserve_buffer(buffer, size, len, ptr);
			memcpy(*ptr, value->data.str, len);
			*ptr += len;
			break;
		case ZBX_VARIANT_NONE:
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			(*ptr)[-1] = ZBX_VARIANT_NONE;
			break;
	}
}

static zbx_uint32_t	deserialize_variant(const unsigned char *ptr,  zbx_variant_t *value)
{
	const unsigned char	*start = ptr;
	unsigned char		type;
	zbx_uint64_t		ui64;
	double			dbl;
	char			*str;
	size_t			len;

	ptr += zbx_deserialize_char(ptr, &type);

	switch (type)
	{
		case ZBX_VARIANT_UI64:
			ptr += zbx_deserialize_uint64(ptr, &ui64);
			zbx_variant_set_ui64(value, ui64);
			break;
		case ZBX_VARIANT_DBL:
			ptr += zbx_deserialize_uint64(ptr, &dbl);
			zbx_variant_set_dbl(value, dbl);
			break;
		case ZBX_VARIANT_STR:
			len = strlen((const char *)ptr) + 1;
			str = zbx_malloc(NULL, len);
			memcpy(str, ptr, len);
			zbx_variant_set_str(value, str);
			ptr += len;
			break;
		case ZBX_VARIANT_NONE:
			zbx_variant_set_none(value);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_variant_set_none(value);
			break;
	}

	return ptr - start;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_parse_expression                                        *
 *                                                                            *
 * Purpose: parse expression into tokens in postfix notation order            *
 *                                                                            *
 * Parameters: ctx        - [OUT] the evaluation context                      *
 *             expression - [IN] the expression to parse                      *
 *             rules      - [IN] the parsing rules                            *
 *             error      - [OUT] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - expression was parsed successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_parse_expression(zbx_eval_context_t *ctx, const char *expression, zbx_uint64_t rules, char **error)
{
	return eval_parse_expression(ctx, expression, rules, error);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_expression_eval_clean                                        *
 *                                                                            *
 * Purpose: free resources allocated by evaluation context                    *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_clean(zbx_eval_context_t *ctx)
{
	eval_clean(ctx);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_serialize                                               *
 *                                                                            *
 * Purpose: serialize evaluation context into buffer                          *
 *                                                                            *
 * Parameters: ctx         - [IN] the evaluation context                      *
 *             malloc_func - [IN] the buffer memory allocation function,      *
 *                                optional (by default the buffer is          *
 *                                allocated in heap)                          *
 *             data  - [OUT] the buffer with serialized evaluation context    *
 *                                                                            *
 * Comments: Location of the replaced tokens (with token.value set) are not   *
 *           serialized, making it impossible to reconstruct the expression   *
 *           text with replaced tokens.                                       *
 *           Context serialization/deserialization must be used for           *
 *           context caching.                                                 *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_serialize(const zbx_eval_context_t *ctx, zbx_mem_malloc_func_t malloc_func,
		unsigned char **data)
{
	int		i;
	unsigned char	buffer_static[ZBX_EVAL_STATIC_BUFFER_SIZE], *buffer = buffer_static, *ptr = buffer;
	size_t		buffer_size = ZBX_EVAL_STATIC_BUFFER_SIZE;
	zbx_uint32_t	len;

	if (NULL == malloc_func)
		malloc_func = ZBX_DEFAULT_MEM_MALLOC_FUNC;

	ptr += serialize_uint31_compact(ptr, ctx->stack.values_num);

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		const zbx_eval_token_t	*token = &ctx->stack.values[i];

		reserve_buffer(&buffer, &buffer_size, 20, &ptr);

		ptr += zbx_serialize_value(ptr, token->type);
		ptr += serialize_uint31_compact(ptr, token->opt);

		serialize_variant(&buffer, &buffer_size, &token->value, &ptr);

		if (ZBX_VARIANT_NONE == token->value.type)
		{
			ptr += serialize_uint31_compact(ptr, token->loc.l);
			ptr += serialize_uint31_compact(ptr, token->loc.r);
		}
	}

	len = ptr - buffer;
	*data = malloc_func(NULL, len);
	memcpy(*data, buffer, len);

	if (buffer != buffer_static)
		zbx_free(buffer);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_deserialize                                             *
 *                                                                            *
 * Purpose: deserialize evaluation context from buffer                        *
 *                                                                            *
 * Parameters: ctx        - [OUT] the evaluation context                      *
 *             expression - [IN] the expression the evaluation context was    *
 *                               created from                                 *
 *             rules      - [IN] the composition and evaluation rules         *
 *             data       - [IN] the buffer with serialized context           *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_deserialize(zbx_eval_context_t *ctx, const char *expression, zbx_uint64_t rules,
		const unsigned char *data)
{
	zbx_uint32_t	i, tokens_num;

	memset(ctx, 0, sizeof(zbx_eval_context_t));
	ctx->expression = expression;
	ctx->rules = rules;

	data += deserialize_uint31_compact(data, &tokens_num);
	zbx_vector_eval_token_create(&ctx->stack);
	zbx_vector_eval_token_reserve(&ctx->stack, tokens_num);
	ctx->stack.values_num = tokens_num;

	for (i = 0; i < tokens_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		data += zbx_deserialize_value(data, &token->type);
		data += deserialize_uint31_compact(data, &token->opt);
		data += deserialize_variant(data, &token->value);

		if (ZBX_VARIANT_NONE == token->value.type)
		{
			zbx_uint32_t	pos;

			data += deserialize_uint31_compact(data, &pos);
			token->loc.l = pos;
			data += deserialize_uint31_compact(data, &pos);
			token->loc.r = pos;
		}
		else
			token->loc.l = token->loc.r = 0;
	}
}

static int	compare_tokens_by_loc(const void *d1, const void *d2)
{
	const zbx_eval_token_t	*t1 = *(const zbx_eval_token_t **)d1;
	const zbx_eval_token_t	*t2 = *(const zbx_eval_token_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(t1->loc.l, t2->loc.l);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_token_print_alloc                                           *
 *                                                                            *
 * Purpose: print token into string quoting/escaping if necessary             *
 *                                                                            *
 * Parameters: ctx        - [IN] the evaluation context                       *
 *             str        - [IN/OUT] the output buffer                        *
 *             str_alloc  - [IN/OUT] the output buffer size                   *
 *             str_offset - [IN/OUT] the output buffer offset                 *
 *             token      - [IN] the token to print                           *
 *                                                                            *
 ******************************************************************************/
static void	eval_token_print_alloc(const zbx_eval_context_t *ctx, char **str, size_t *str_alloc, size_t *str_offset,
		const zbx_eval_token_t *token)
{
	int		quoted = 0, len, check_value = 0;
	const char	*src, *value_str;
	char		*dst;
	size_t		size;

	if (ZBX_VARIANT_NONE == token->value.type)
		return;

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_VAR_STR:
			quoted = 1;
			break;
		case ZBX_EVAL_TOKEN_VAR_MACRO:
			if (0 != (ctx->rules & ZBX_EVAL_QUOTE_MACRO))
				check_value = 1;
			break;
		case ZBX_EVAL_TOKEN_VAR_USERMACRO:
			if (0 != (ctx->rules & ZBX_EVAL_QUOTE_USERMACRO))
				check_value = 1;
			break;
		case ZBX_EVAL_TOKEN_VAR_LLDMACRO:
			if (0 != (ctx->rules & ZBX_EVAL_QUOTE_LLDMACRO))
				check_value = 1;
			break;
	}

	if (0 != check_value)
	{
		if (ZBX_VARIANT_STR == token->value.type && (SUCCEED != zbx_number_parse(token->value.data.str, &len) ||
				strlen(token->value.data.str) != (size_t)len))
		{
			quoted = 1;
		}
	}

	value_str = zbx_variant_value_desc(&token->value);

	if (0 == quoted)
	{
		zbx_strcpy_alloc(str, str_alloc, str_offset, value_str);
		return;
	}

	for (size = 2, src = value_str; '\0' != *src; src++)
	{
		switch (*src)
		{
			case '\\':
			case '"':
				size++;
		}
		size++;
	}

	if (*str_alloc - *str_offset <= size)
	{
		do
		{
			*str_alloc *= 2;
		}
		while (*str_alloc - *str_offset <= size);

		*str = zbx_realloc(*str, *str_alloc);
	}

	dst = *str + *str_offset;
	*dst++ = '"';

	for (src = value_str; '\0' != *src; src++, dst++)
	{
		switch (*src)
		{
			case '\\':
			case '"':
				*dst++ = '\\';
				break;
		}

		*dst = *src;
	}

	*dst++ = '"';
	*dst = '\0';
	*str_offset += size;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_compose_expression                                      *
 *                                                                            *
 * Purpose: compose expression by replacing processed tokens (with values) in *
 *          the original expression                                           *
 *                                                                            *
 * Parameters: ctx        - [IN] the evaluation context                       *
 *             expression - [OUT] the composed expression                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_compose_expression(const zbx_eval_context_t *ctx, char **expression)
{
	zbx_vector_ptr_t	tokens;
	const zbx_eval_token_t	*token;
	int			i;
	size_t			pos = 0, expression_alloc = 0, expression_offset = 0;

	zbx_vector_ptr_create(&tokens);

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		if (ZBX_VARIANT_NONE != ctx->stack.values[i].value.type)
			zbx_vector_ptr_append(&tokens, &ctx->stack.values[i]);
	}

	zbx_vector_ptr_sort(&tokens, compare_tokens_by_loc);

	for (i = 0; i < tokens.values_num; i++)
	{
		token = (const zbx_eval_token_t *)tokens.values[i];

		if (0 != token->loc.l)
		{
			zbx_strncpy_alloc(expression, &expression_alloc, &expression_offset, ctx->expression + pos,
					token->loc.l - pos);
		}
		pos = token->loc.r + 1;
		eval_token_print_alloc(ctx, expression, &expression_alloc, &expression_offset, token);
	}

	if ('\0' != ctx->expression[pos])
		zbx_strcpy_alloc(expression, &expression_alloc, &expression_offset, ctx->expression + pos);

	zbx_vector_ptr_destroy(&tokens);
}


