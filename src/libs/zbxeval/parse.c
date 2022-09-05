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

#include "zbxeval.h"
#include "eval.h"

ZBX_VECTOR_IMPL(eval_token, zbx_eval_token_t)

static int	is_whitespace(char c)
{
	return 0 != isspace((unsigned char)c) ? SUCCEED : FAIL;
}

/******************************************************************************
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
 * Purpose: check if the character can be a part of a compound number         *
 *          following a macro                                                 *
 *                                                                            *
 ******************************************************************************/
static int	eval_is_compound_number_char(char c, int pos)
{
	if (0 != isdigit((unsigned char)c))
		return SUCCEED;

	switch (c)
	{
		case '.':
		case '{':
			return SUCCEED;
		case 'e':
		case 'E':
			return (0 != pos ? SUCCEED : FAIL);
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse functionid token ({<functionid>})                           *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_functionid(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
{
	zbx_token_t	tok;

	if (SUCCEED == zbx_token_parse_objectid(ctx->expression, ctx->expression + pos, &tok))
	{
		token->type = ZBX_EVAL_TOKEN_FUNCTIONID;
		token->opt = ctx->functionid_index++;
		token->loc = tok.loc;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse macro                                                       *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             tok   - [OUT] the parsed token                                 *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_macro(zbx_eval_context_t *ctx, int pos, zbx_token_t *tok)
{
	if (0 != (ctx->rules & ZBX_EVAL_PARSE_MACRO) &&
			SUCCEED == zbx_token_parse_macro(ctx->expression, ctx->expression + pos, tok))
	{
		return SUCCEED;
	}
	else if (0 != (ctx->rules & ZBX_EVAL_PARSE_USERMACRO) && '$' == ctx->expression[pos + 1] &&
			SUCCEED == zbx_token_parse_user_macro(ctx->expression, ctx->expression + pos, tok))
	{
		return SUCCEED;
	}
	else if (0 != (ctx->rules & ZBX_EVAL_PARSE_LLDMACRO) && '#' == ctx->expression[pos + 1] &&
			SUCCEED == zbx_token_parse_lld_macro(ctx->expression, ctx->expression + pos, tok))
	{
		return SUCCEED;
	}
	else if ('{' == ctx->expression[pos + 1] && SUCCEED == zbx_token_parse_nested_macro(ctx->expression,
			ctx->expression + pos, 0, tok))
	{
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse numeric value                                               *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             tok   - [OUT] the parsed token                                 *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_number(zbx_eval_context_t *ctx, size_t pos, size_t *pos_r)
{
	int		len, offset = 0;
	char		*end;
	double		tmp;

	if ('-' == ctx->expression[pos])
		offset++;

	if (FAIL == zbx_suffixed_number_parse(ctx->expression + pos + offset, &len))
		return FAIL;

	len += offset;

	tmp = strtod(ctx->expression + pos, &end) * (double)suffix2factor(ctx->expression[(int)pos + len - 1]);
	if (HUGE_VAL == tmp || -HUGE_VAL == tmp || EDOM == errno)
		return FAIL;

	*pos_r = pos + (size_t)len - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse constant value                                              *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: A constant is a number or macro depending on parsing rules.      *
 *           Number can be a compound value, consisting of several macros     *
 *           digits etc.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_constant(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token, char **error)
{
	zbx_token_t		tok;
	size_t			offset = pos;
	zbx_token_type_t	type = 0, last_type = 0;

	do
	{
		if ('{' == (ctx->expression[offset]))
		{
			last_type = ZBX_TOKEN_MACRO;

			if (SUCCEED != eval_parse_macro(ctx, (int)offset, &tok))
				break;

			if (pos == offset)
			{
				switch (tok.type)
				{
					case ZBX_TOKEN_MACRO:
					case ZBX_TOKEN_FUNC_MACRO:
					case ZBX_TOKEN_SIMPLE_MACRO:
						type = ZBX_EVAL_TOKEN_VAR_MACRO;
						break;
					case ZBX_TOKEN_USER_MACRO:
						type = ZBX_EVAL_TOKEN_VAR_USERMACRO;
						break;
					case ZBX_TOKEN_LLD_MACRO:
					case ZBX_TOKEN_LLD_FUNC_MACRO:
						type = ZBX_EVAL_TOKEN_VAR_LLDMACRO;
						break;
				}
			}
			else
				type = ZBX_EVAL_TOKEN_VAR_NUM;

			offset = tok.loc.r + 1;

			switch (ctx->expression[offset])
			{
				case 's':
				case 'm':
				case 'h':
				case 'd':
				case 'w':
				case 'K':
				case 'M':
				case 'G':
				case 'T':
					type = ZBX_EVAL_TOKEN_VAR_NUM;
					offset++;
					goto out;
			}
		}
		else if (ZBX_EVAL_TOKEN_VAR_NUM != last_type && SUCCEED == eval_parse_number(ctx, offset, &offset))
		{
			last_type = type = ZBX_EVAL_TOKEN_VAR_NUM;
			offset++;
		}
		else if (SUCCEED == eval_is_compound_number_char(ctx->expression[offset], offset - pos))
			offset++;
		else
			break;
	}
	while (0 != (ctx->rules & ZBX_EVAL_PARSE_COMPOUND_CONST));
out:
	if (0 == type)
	{
		*error = zbx_dsprintf(*error, "invalid token starting with \"%s\"", ctx->expression + pos);
		return FAIL;
	}

	if (ZBX_EVAL_TOKEN_VAR_NUM == type && 0 == (ctx->rules & ZBX_EVAL_PARSE_VAR_NUM))
	{
		*error = zbx_dsprintf(*error, "invalid token starting with \"%s\"", ctx->expression + pos);
		return FAIL;
	}

	if (ZBX_EVAL_TOKEN_VAR_NUM == type || ZBX_EVAL_TOKEN_VAR_USERMACRO == type)
		eval_update_const_variable(ctx, token);

	token->type = type;
	token->loc.l = pos;
	token->loc.r = offset - 1;

	return SUCCEED;
}

/******************************************************************************
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
 * Purpose: parse token starting with  '<'                                    *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *                                                                            *
 * Return value: SUCCEED - the token was parsed successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Tokens starting with '<' are '<', '<=' and '<>'.                 *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_less_character_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
{
	if (0 != (ctx->rules & ZBX_EVAL_PARSE_COMPARE_EQ) && '>' == ctx->expression[pos + 1])
	{
		token->type = ZBX_EVAL_TOKEN_OP_NE;
	}
	else
	{
		if (0 == (ctx->rules & ZBX_EVAL_PARSE_COMPARE_SORT))
			return FAIL;

		if ('=' != ctx->expression[pos + 1])
		{
			eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_LT, token);
			return SUCCEED;
		}

		token->type = ZBX_EVAL_TOKEN_OP_LE;
	}

	token->loc.l = pos;
	token->loc.r = pos + 1;

	return SUCCEED;
}

/******************************************************************************
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
 * Purpose: parse numeric variable token                                      *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             pos   - [IN] the starting position                             *
 *             token - [OUT] the parsed token                                 *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 *  Comments: Time suffixes s,m,h,d,w are supported.                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_number_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
{
	int		len, offset = 0;
	char		*end;
	double		tmp;

	if ('-' == ctx->expression[pos])
		offset++;

	if (FAIL == zbx_suffixed_number_parse(ctx->expression + pos + offset, &len))
		return FAIL;

	len += offset;

	token->type = ZBX_EVAL_TOKEN_VAR_NUM;

	tmp = strtod(ctx->expression + pos, &end) * suffix2factor(ctx->expression[pos + len - 1]);
	if (HUGE_VAL == tmp || -HUGE_VAL == tmp || EDOM == errno)
		return FAIL;

	token->loc.l = pos;
	token->loc.r = pos + len - 1;
	eval_update_const_variable(ctx, token);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse logical operation token                                     *
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
static int	eval_parse_logic_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
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

	while (0 != isalpha((unsigned char)*ptr) || '_' == *ptr || 0 != isdigit((unsigned char)*ptr))
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
 * Purpose: parse item query filter (?[group="xyz"])                          *
 *                                                                            *
 * Parameters: ptr - [IN] - the filter to parse                               *
 *                   [OUT] - a reference to the next character after filter   *
 *                                                                            *
 * Return value: SUCCEED - the filter was parsed successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_parse_query_filter(const char **ptr)
{
	const char	*filter = *ptr;

	if ('[' != *(++filter))
		return FAIL;

	filter++;

	while (']' != *filter)
	{
		if ('\0' == *filter)
			return FAIL;

		if ('"' == *filter)
		{
			while ('"' != *(++filter))
			{
				if ('\0' == *filter)
					return FAIL;

				if ('\\' == *filter && '\0' == *(++filter))
					return FAIL;
			}
		}

		filter++;
	}

	*ptr = ++filter;
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse item query /host/key?[filter] into host, key and filter     *
 *          components                                                        *
 *                                                                            *
 * Parameters: str     - [IN] the item query                                  *
 *             phost   - [OUT] a reference to host or NULL (optional)         *
 *             pkey    - [OUT] a reference to key                             *
 *             pfilter - [OUT] a reference to the filter or NULL              *
 *                                                                            *
 * Return value: The number of parsed characters, 0 if there was an error     *
 *                                                                            *
 ******************************************************************************/
size_t	eval_parse_query(const char *str, const char **phost, const char **pkey, const char **pfilter)
{
#define MVAR_HOST_HOST	"{HOST.HOST"
#define MVAR_ITEM_KEY	"{ITEM.KEY"

	const char	*host = str + 1, *key, *filter, *end;

	key = host;

	if ('*' == *key)
	{
		key++;
	}
	else if ('{' == *key)
	{
		if (0 == strncmp(key, MVAR_HOST_HOST, ZBX_CONST_STRLEN(MVAR_HOST_HOST)))
		{
			size_t	offset = 0;

			if ('}' == key[ZBX_CONST_STRLEN(MVAR_HOST_HOST)])
			{
				offset = 1;
			}
			else if (0 != isdigit((unsigned char)key[ZBX_CONST_STRLEN(MVAR_HOST_HOST)]) &&
				'}' == key[ZBX_CONST_STRLEN(MVAR_HOST_HOST) + 1])
			{
				offset = 2;
			}

			if (0 != offset)
				key += ZBX_CONST_STRLEN(MVAR_HOST_HOST) + offset;
		}
	}
	else if ('/' != *key)
	{
		while (SUCCEED == is_hostname_char(*key))
			key++;
	}

	if ('/' != *key)
		return 0;

	end = ++key;

	if ('*' == *key)
	{
		end++;
	}
	else if ('{' == *key)
	{
		if (0 == strncmp(key, MVAR_ITEM_KEY, ZBX_CONST_STRLEN(MVAR_ITEM_KEY)))
		{
			size_t	offset = 0;

			if ('}' == key[ZBX_CONST_STRLEN(MVAR_ITEM_KEY)])
			{
				offset = 1;
			}
			else if (0 != isdigit((unsigned char)key[ZBX_CONST_STRLEN(MVAR_ITEM_KEY)]) &&
					'}' == key[ZBX_CONST_STRLEN(MVAR_ITEM_KEY) + 1])
			{
				offset = 2;
			}

			if (0 != offset)
				end += ZBX_CONST_STRLEN(MVAR_ITEM_KEY) + offset;
		}
	}
	else if (SUCCEED != parse_key(&end))
		return 0;

	if (*end == '?')
	{
		filter = end;
		if (SUCCEED != eval_parse_query_filter(&end))
			return 0;
		filter += 2;
	}
	else
		filter = NULL;

	if (NULL != phost)
	{
		*phost = host;
		*pkey = key;
		*pfilter = filter;
	}

	return end - str;

#undef MVAR_HOST_HOST
#undef MVAR_ITEM_KEY
}

/******************************************************************************
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
	size_t	len;

	if (0 == (len = eval_parse_query(ctx->expression + pos, NULL, NULL, NULL)))
	{
		*error = zbx_dsprintf(*error, "invalid item query starting at \"%s\"", ctx->expression + pos);
		return FAIL;
	}

	token->type = ZBX_EVAL_TOKEN_ARG_QUERY;
	token->loc.l = pos;
	token->loc.r = pos + len - 1;

	return SUCCEED;
}

/******************************************************************************
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
static int	eval_parse_period_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token, char **error)
{
	size_t	offset = pos;

	for (;'\0' != ctx->expression[offset]; offset++)
	{
		if ('{' == ctx->expression[offset] && 0 != (ctx->rules & ZBX_EVAL_PARSE_COMPOUND_CONST))
		{
			zbx_token_t	tok;

			if (SUCCEED == eval_parse_macro(ctx, offset, &tok))
				offset = tok.loc.r;
			continue;

		}
		if (',' == ctx->expression[offset] || ')' == ctx->expression[offset] ||
				SUCCEED == is_whitespace(ctx->expression[offset]))
		{
			token->type = ZBX_EVAL_TOKEN_ARG_PERIOD;
			token->loc.l = pos;
			token->loc.r = offset - 1;
			zbx_variant_set_none(&token->value);

			return SUCCEED;
		}
	}

	*error = zbx_dsprintf(*error, "unterminated function at \"%s\"", ctx->expression + pos);
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse property token                                              *
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
static int	eval_parse_property_token(zbx_eval_context_t *ctx, size_t pos, zbx_eval_token_t *token)
{
	if (0 != (ctx->rules & ZBX_EVAL_PARSE_PROP_TAG) && 0 == strncmp(ctx->expression + pos, "tag", 3))
	{
		token->loc.r = pos + 2;
		token->type = ZBX_EVAL_TOKEN_PROP_TAG;
	}
	else if (0 != (ctx->rules & ZBX_EVAL_PARSE_PROP_GROUP) && 0 == strncmp(ctx->expression + pos, "group", 5))
	{
		token->loc.r = pos + 4;
		token->type = ZBX_EVAL_TOKEN_PROP_GROUP;
	}
	else
		return FAIL;

	token->loc.l = pos;

	return SUCCEED;
}

/******************************************************************************
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
			if (ZBX_EVAL_TOKEN_COMMA == ctx->last_token_type &&
				ZBX_EVAL_TOKEN_ARG_QUERY == ctx->stack.values[ctx->stack.values_num - 1].type)
			{
				return eval_parse_period_token(ctx, pos, token, error);
			}

			if (0 != (ctx->rules & ZBX_EVAL_PARSE_FUNCTIONID) &&
					SUCCEED == eval_parse_functionid(ctx, pos, token))
			{
				return SUCCEED;
			}
			return eval_parse_constant(ctx, pos, token, error);
		case '+':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_MATH))
			{
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_ADD, token);
				return SUCCEED;
			}
			break;
		case '-':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_VAR))
			{
				if (0 == (ctx->last_token_type & ZBX_EVAL_CLASS_OPERAND) &&
						SUCCEED == eval_parse_number_token(ctx, pos, token))
				{
					return SUCCEED;
				}
			}
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_MATH))
			{
				if (0 == (ctx->last_token_type & ZBX_EVAL_CLASS_OPERAND))
					eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_MINUS, token);
				else
					eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_SUB, token);

				return SUCCEED;
			}
			break;
		case '/':
			if (ZBX_EVAL_TOKEN_GROUP_OPEN == ctx->last_token_type &&
					0 != (ctx->rules & ZBX_EVAL_PARSE_ITEM_QUERY))
			{
				zbx_eval_token_t	*func_token = NULL;

				if (2 <= ctx->ops.values_num)
					func_token = &ctx->ops.values[ctx->ops.values_num - 2];

				if (NULL == func_token || 0 == (func_token->type & ZBX_EVAL_CLASS_FUNCTION))
				{
					*error = zbx_dsprintf(*error, "item query must be first argument of a"
							" historical function at \"%s\"", ctx->expression + pos);
					return FAIL;
				}
				func_token->type = ZBX_EVAL_TOKEN_HIST_FUNCTION;
				return eval_parse_query_token(ctx, pos, token, error);
			}
			else if (0 != (ctx->rules & ZBX_EVAL_PARSE_MATH))
			{
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_DIV, token);
				return SUCCEED;
			}
			break;
		case '*':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_MATH))
			{
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_MUL, token);
				return SUCCEED;
			}
			break;
		case '<':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_COMPARE))
			{
				if (SUCCEED == eval_parse_less_character_token(ctx, pos, token))
					return SUCCEED;
			}
			break;
		case '>':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_COMPARE_SORT))
			{
				eval_parse_greater_character_token(ctx, pos, token);
				return SUCCEED;
			}
			break;
		case '=':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_COMPARE_EQ))
			{
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_OP_EQ, token);
				return SUCCEED;
			}
			break;
		case '(':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_GROUP))
			{
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_GROUP_OPEN, token);
				return SUCCEED;
			}
			break;
		case ')':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_GROUP))
			{
				eval_parse_character_token(pos, ZBX_EVAL_TOKEN_GROUP_CLOSE, token);
				return SUCCEED;
			}
			break;
		case '"':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_VAR_STR))
				return eval_parse_string_token(ctx, pos, token, error);
			break;
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
				return eval_parse_period_token(ctx, pos, token, error);
			}
			ZBX_FALLTHROUGH;
		case '.':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_VAR_NUM))
				return eval_parse_constant(ctx, pos, token, error);
			break;
		case '#':
			if (ZBX_EVAL_TOKEN_COMMA == ctx->last_token_type &&
				ZBX_EVAL_TOKEN_ARG_QUERY == ctx->stack.values[ctx->stack.values_num - 1].type)
			{
				return eval_parse_period_token(ctx, pos, token, error);
			}
			break;
		case ',':
			if (0 != (ctx->rules & ZBX_EVAL_PARSE_FUNCTION_ARGS))
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
				/* logical operation must be separated by whitespace or '(', ')', ',' characters */
				if (0 != (ctx->rules & ZBX_EVAL_PARSE_LOGIC) &&
						(0 != skip || 0 != (ctx->last_token_type & ZBX_EVAL_CLASS_SEPARATOR) ||
						ZBX_EVAL_TOKEN_GROUP_CLOSE == ctx->last_token_type))
				{
					if (SUCCEED == eval_parse_logic_token(ctx, pos, token))
						return SUCCEED;
				}

				if (0 != (ctx->rules & ZBX_EVAL_PARSE_FUNCTION_NAME) &&
						SUCCEED == eval_parse_function_token(ctx, pos, token))
				{
					return SUCCEED;
				}

				if (0 != (ctx->rules & ZBX_EVAL_PARSE_PROPERTY) &&
						SUCCEED == eval_parse_property_token(ctx, pos, token))
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
 * Purpose: add operator/function token to evaluation stack                   *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             token - [IN] the token to add                                  *
 *                                                                            *
 ******************************************************************************/
static int	eval_append_operator(zbx_eval_context_t *ctx, zbx_eval_token_t *token, char **error)
{
	if (0 != (token->type & ZBX_EVAL_CLASS_FUNCTION))
	{
		int	i, params = 0;

		for (i = (int)token->opt; i < ctx->stack.values_num; i++)
		{
			if (0 != (ctx->stack.values[i].type & ZBX_EVAL_CLASS_FUNCTION))
				params -= (int)ctx->stack.values[i].opt - 1;
			else if (0 != (ctx->stack.values[i].type & ZBX_EVAL_CLASS_OPERAND))
				params++;
			else if (0 != (ctx->stack.values[i].type & ZBX_EVAL_CLASS_OPERATOR2))
				params--;
		}

		token->opt = params;
	}

	if (0 != (ctx->rules & ZBX_EVAL_PARSE_PROPERTY))
	{
		zbx_eval_token_t	*prop = NULL, *value = NULL;

		if (0 != (ctx->stack.values[ctx->stack.values_num - 1].type & ZBX_EVAL_CLASS_PROPERTY))
		{
			prop = &ctx->stack.values[ctx->stack.values_num - 1];

			if (2 > ctx->stack.values_num)
			{
				*error = zbx_dsprintf(*error, "missing comparison string for property at \"%s\"",
						ctx->expression + prop->loc.l);
				return FAIL;
			}

			value = &ctx->stack.values[ctx->stack.values_num - 2];
			if (0 == (value->type & ZBX_EVAL_CLASS_OPERAND))
			{
				*error = zbx_dsprintf(*error, "property must be compared with a constant value at"
						" \"%s\"", ctx->expression + prop->loc.l);
				return FAIL;
			}

		}

		if (0 != (ctx->stack.values[ctx->stack.values_num - 1].type & ZBX_EVAL_CLASS_OPERAND) &&
				0 != (token->type & ZBX_EVAL_CLASS_OPERATOR2))
		{
			if (0 != (ctx->stack.values[ctx->stack.values_num - 2].type & ZBX_EVAL_CLASS_PROPERTY))
			{
				prop = &ctx->stack.values[ctx->stack.values_num - 2];
				value = &ctx->stack.values[ctx->stack.values_num - 1];
			}
		}

		if (NULL != prop)
		{
			if ((ZBX_EVAL_TOKEN_VAR_STR != value->type && ZBX_EVAL_TOKEN_VAR_USERMACRO != value->type &&
					ZBX_EVAL_TOKEN_VAR_LLDMACRO != value->type))
			{
				*error = zbx_dsprintf(*error, "invalid value type compared with property at \"%s\"",
						ctx->expression + prop->loc.l);
				return FAIL;
			}

			if (ZBX_EVAL_TOKEN_OP_EQ != token->type && ZBX_EVAL_TOKEN_OP_NE != token->type)
			{
				*error = zbx_dsprintf(*error, "invalid operator used with property at \"%s\"",
						ctx->expression + prop->loc.l);
				return FAIL;
			}
		}
	}

	zbx_vector_eval_token_append_ptr(&ctx->stack, token);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add operand token to evaluation stack                             *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             token - [IN] the token to add                                  *
 *                                                                            *
 ******************************************************************************/
static int	eval_append_operand(zbx_eval_context_t *ctx, zbx_eval_token_t *token, char **error)
{
	if (0 == (ctx->last_token_type & ZBX_EVAL_BEFORE_OPERAND))
	{
		*error = zbx_dsprintf(*error, "operand following another operand at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (0 != (ctx->rules & ZBX_EVAL_PARSE_PROPERTY))
	{
		int			i;
		zbx_eval_token_t	*prop = NULL;

		for (i = ctx->stack.values_num - 1; i >= 0; i--)
		{
			if (0 != (ctx->stack.values[i].type & ZBX_EVAL_CLASS_PROPERTY))
			{
				prop = &ctx->stack.values[i];
				continue;
			}

			if (0 == (ctx->stack.values[i].type & ZBX_EVAL_CLASS_OPERAND))
				break;
		}

		if (0 != (token->type & ZBX_EVAL_CLASS_PROPERTY))
		{
			if (NULL != prop)
			{
				*error = zbx_dsprintf(*error, "property must be compared with a constant value at"
						" \"%s\"", ctx->expression + prop->loc.l);
				return FAIL;
			}
			prop = token;
		}

		if (NULL != prop && 2 < ctx->stack.values_num - i)
		{
			*error = zbx_dsprintf(*error, "property must be compared with a constant value at"
					" \"%s\"", ctx->expression + prop->loc.l);
			return FAIL;
		}
	}

	zbx_vector_eval_token_append_ptr(&ctx->stack, token);

	return SUCCEED;
}

/******************************************************************************
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
 * Purpose: free resources allocated by evaluation context                    *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *                                                                            *
 ******************************************************************************/
static void	eval_clear(zbx_eval_context_t *ctx)
{
	if (NULL != ctx->stack.values)
	{
		int	i;

		for (i = 0; i < ctx->stack.values_num; i++)
			zbx_variant_clear(&ctx->stack.values[i].value);

		zbx_vector_eval_token_destroy(&ctx->stack);
	}
}

/******************************************************************************
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

		/* serialization used for parsed expression caching has limits expression to 0x7fffffff */
		if ((zbx_uint32_t)0x7fffffff < token.loc.r)
		{
			*error = zbx_strdup(*error, "too long expression");
			goto out;
		}

		if (ZBX_EVAL_TOKEN_ARG_QUERY == ctx->last_token_type && ZBX_EVAL_TOKEN_COMMA != token.type &&
				ZBX_EVAL_TOKEN_GROUP_CLOSE != token.type)
		{
			*error = zbx_dsprintf(*error, "invalid expression following query token at \"%s\"",
					ctx->expression + pos);
			goto out;
		}

		if (ZBX_EVAL_TOKEN_GROUP_CLOSE == token.type && ZBX_EVAL_TOKEN_COMMA == ctx->last_token_type)
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
		else if (ZBX_EVAL_TOKEN_COMMA == token.type)
		{
			/* comma must follow and operand, comma or function */
			if (0 == (ctx->last_token_type & ZBX_EVAL_CLASS_OPERAND) &&
					(0 == (ctx->last_token_type & ZBX_EVAL_CLASS_SEPARATOR)))
			{
				*error = zbx_dsprintf(*error, "comma must follow an operand or separator at \"%s\"",
						ctx->expression + pos);
				goto out;
			}

			if (0 != (ctx->last_token_type & ZBX_EVAL_CLASS_SEPARATOR))
				eval_append_arg_null(ctx);

			for (optoken = NULL; 0 < ctx->ops.values_num; ctx->ops.values_num--)
			{
				optoken = &ctx->ops.values[ctx->ops.values_num - 1];

				if (ZBX_EVAL_TOKEN_GROUP_OPEN == optoken->type)
					break;

				if (FAIL == eval_append_operator(ctx, optoken, error))
					goto out;
			}

			if (NULL == optoken)
			{
				*error = zbx_dsprintf(*error, "missing function argument separator for comma at\"%s\"",
						ctx->expression + pos);
				goto out;
			}
		}
		else if (ZBX_EVAL_TOKEN_GROUP_CLOSE == token.type)
		{
			/* right parenthesis must follow and operand, right parenthesis or function */
			if (0 == (ctx->last_token_type & (ZBX_EVAL_CLASS_OPERAND | ZBX_EVAL_CLASS_PROPERTY |
					ZBX_EVAL_CLASS_SEPARATOR)) &&
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

				if (FAIL == eval_append_operator(ctx, optoken, error))
					goto out;
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
					if (FAIL == eval_append_operator(ctx, optoken, error))
						goto out;
					ctx->ops.values_num--;
				}
			}
		}
		else if (0 != (token.type & (ZBX_EVAL_CLASS_OPERAND | ZBX_EVAL_CLASS_PROPERTY)))
		{
			if (FAIL == eval_append_operand(ctx, &token, error))
				goto out;
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

				if (FAIL == eval_append_operator(ctx, optoken, error))
					goto out;
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

	if (ZBX_EVAL_TOKEN_COMMA == ctx->last_token_type)
	{
		*error = zbx_strdup(*error, "expression ends with comma");
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

		if (FAIL == eval_append_operator(ctx, optoken, error))
			goto out;
	}

	if (0 == ctx->stack.values_num)
	{
		*error = zbx_strdup(*error, "empty expression");
		goto out;
	}

	if (0 != (ctx->rules & ZBX_EVAL_PARSE_PROPERTY) && 1 == ctx->stack.values_num)
	{
		if (0 != (ctx->stack.values[ctx->stack.values_num - 1].type & ZBX_EVAL_CLASS_PROPERTY))
		{
			zbx_eval_token_t	*prop = &ctx->stack.values[ctx->stack.values_num - 1];

			*error = zbx_dsprintf(*error, "missing comparison string for property at \"%s\"",
						ctx->expression + prop->loc.l);
			goto out;
		}
	}

	ret = SUCCEED;
out:
	zbx_vector_eval_token_destroy(&ctx->ops);

	if (SUCCEED != ret)
		eval_clear(ctx);

	return ret;
}

/******************************************************************************
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
 * Purpose: initialize context so it can be cleared without parsing           *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_init(zbx_eval_context_t *ctx)
{
	memset(ctx, 0, sizeof(zbx_eval_context_t));
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by evaluation context                    *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_clear(zbx_eval_context_t *ctx)
{
	eval_clear(ctx);
}

/******************************************************************************
 *                                                                            *
 * Purpose: return evaluation context status                                  *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *                                                                            *
 * Return value: SUCCEED - contains parsed expression                         *
 *               FAIL    - empty, either parsing failed or was initialized    *
 *                         without parsing                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_status(const zbx_eval_context_t *ctx)
{
	return (NULL == ctx->expression ? FAIL : SUCCEED);
}
