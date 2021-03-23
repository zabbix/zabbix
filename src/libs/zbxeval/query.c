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
#include "zbxserver.h"
#include "zbxeval.h"
#include "eval.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_parse_filter                                            *
 *                                                                            *
 * Purpose: parse item query /host/key?[filter] into host, key and filter     *
 *          components                                                        *
 *                                                                            *
 * Parameters: str   - [IN] the item query                                    *
 *             len   - [IN] the query length                                  *
 *             query - [IN] the parsed item query                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_parse_query(const char *str, size_t len, zbx_item_query_t *query)
{
	const char	*ptr = str, *key;

	if ('/' != *ptr || NULL == (key = strchr(++ptr, '/')))
	{
		query->type = ZBX_ITEM_QUERY_UNKNOWN;
		return;
	}

	if (ptr != key)
		query->host = zbx_substr(ptr, 0, key - ptr - 1);
	else
		query->host = NULL;

	query->key = zbx_substr(key, 1, len - (key - str) - 1);
	query->type = ZBX_ITEM_QUERY_SINGLE;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_clear_filter                                            *
 *                                                                            *
 * Purpose: frees resources allocated by item reference                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_clear_query(zbx_item_query_t *query)
{
	zbx_free(query->host);
	zbx_free(query->key);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_prepare_filter                                          *
 *                                                                            *
 * Purpose: prepare filter expression by converting property comparisons      *
 *          prop =/<> "value" to prop("value")/not prop("value") function     *
 *          calls.                                                            *
 *                                                                            *
 * Parameters: ctx - [IN] the evaluation context                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_prepare_filter(zbx_eval_context_t *ctx)
{
	int	i, j;

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*prop = &ctx->stack.values[i];

		if (0 == (prop->type & ZBX_EVAL_CLASS_PROPERTY))
			continue;

		for (j = i + 1; j < ctx->stack.values_num; j++)
		{
			zbx_eval_token_t	*op = &ctx->stack.values[j];

			if (ZBX_EVAL_TOKEN_OP_EQ == op->type || ZBX_EVAL_TOKEN_OP_NE == op->type)
			{
				zbx_eval_token_t	*func;

				func = &ctx->stack.values[j - 1];

				if (i != j - 1)
				{
					zbx_strloc_t	loc;

					loc = prop->loc;
					*prop = *func;
					func->loc = loc;
				}

				func->opt = 1;
				func->type = ZBX_EVAL_TOKEN_FUNCTION;


				if (ZBX_EVAL_TOKEN_OP_NE == op->type)
					op->type = ZBX_EVAL_TOKEN_OP_NOT;
				else
					op->type = ZBX_EVAL_TOKEN_NOP;

				break;
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: eval_filter_apply_op2                                            *
 *                                                                            *
 * Purpose: apply binary operation to stack                                   *
 *                                                                            *
 * Parameters: token - [IN] the operation token                               *
 *             stack - [IN/OUT] the target stack                              *
 *             index - [IN/OUT] the stack index                               *
 *                                                                            *
 ******************************************************************************/
static void	eval_filter_apply_op2(zbx_eval_token_t *token, zbx_vector_eval_token_t *stack,
		zbx_vector_uint64_t *index)
{
	zbx_eval_token_t	*left, *right;
	int			li, ri;

	li = (int)index->values[index->values_num - 2];
	left = &stack->values[li];
	ri = (int)index->values[index->values_num - 1];
	right = &stack->values[ri];

	if (ZBX_EVAL_TOKEN_JOKER == left->type || ZBX_EVAL_TOKEN_JOKER == right->type)
	{
		switch (token->type)
		{
			case ZBX_EVAL_TOKEN_OP_AND:
				if (ZBX_EVAL_TOKEN_JOKER == left->type)
				{
					memmove(left, right, (stack->values_num - ri) * sizeof(zbx_eval_token_t));
					stack->values_num -= (ri - li);
				}
				else
					stack->values_num--;
				break;
			default:
				left->type = ZBX_EVAL_TOKEN_JOKER;
				stack->values_num = li + 1;
				break;
		}

		index->values_num--;
		return;
	}

	zbx_vector_eval_token_append_ptr(stack, token);
	index->values_num--;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_filter_apply_op1                                            *
 *                                                                            *
 * Purpose: apply unary operation to stack                                    *
 *                                                                            *
 * Parameters: token - [IN] the operation token                               *
 *             stack - [IN/OUT] the target stack                              *
 *             index - [IN/OUT] the stack index                               *
 *                                                                            *
 ******************************************************************************/
static void	eval_filter_apply_op1(zbx_eval_token_t *token, zbx_vector_eval_token_t *stack,
		zbx_vector_uint64_t *index)
{
	zbx_eval_token_t	*right;
	int			ri;

	ri = (int)index->values[index->values_num - 1];
	right = &stack->values[ri];

	if (ZBX_EVAL_TOKEN_JOKER != right->type)
		zbx_vector_eval_token_append_ptr(stack, token);
}

/******************************************************************************
 *                                                                            *
 * Function: eval_filter_apply_func                                           *
 *                                                                            *
 * Purpose: apply function to stack                                           *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             token - [IN] the function token                                *
 *             stack - [IN/OUT] the target stack                              *
 *             index - [IN/OUT] the stack index                               *
 *                                                                            *
 ******************************************************************************/
static void	eval_filter_apply_func(zbx_eval_context_t *ctx, zbx_eval_token_t *token,
		zbx_vector_eval_token_t *stack, zbx_vector_uint64_t *index)
{
	zbx_eval_token_t	*left;
	int			li;

	if (ZBX_CONST_STRLEN("tag") == token->loc.r - token->loc.l + 1 &&
			0 == memcmp(ctx->expression + token->loc.l, "tag", ZBX_CONST_STRLEN("tag")))
	{
		li = (int)index->values[index->values_num - 1];
		left = &stack->values[li];

		left->type = ZBX_EVAL_TOKEN_JOKER;
		stack->values_num = li + 1;
	}
	else
		zbx_vector_eval_token_append_ptr(stack, token);
}

/******************************************************************************
 *                                                                            *
 * Function: eval_op_str                                                      *
 *                                                                            *
 * Purpose: get operator in text format                                       *
 *                                                                            *
 * Parameters: op - [IN] the operator type                                    *
 *                                                                            *
 * Return value: The operator in text format.                                 *
 *                                                                            *
 * Comments: This function will return 'unsupported operator' for unsupported *
 *           operators, causing the expression evaluation to fail. However    *
 *           this should not happen as the supported operators are verified   *
 *           during expression parsing.                                       *
 *                                                                            *
 ******************************************************************************/
static const char	*eval_op_str(zbx_token_type_t op)
{
	switch (op)
	{
		case ZBX_EVAL_TOKEN_OP_EQ:
			return "=";
		case ZBX_EVAL_TOKEN_OP_NE:
			return "<>";
		case ZBX_EVAL_TOKEN_OP_AND:
			return " and ";
		case ZBX_EVAL_TOKEN_OP_OR:
			return " or ";
		case ZBX_EVAL_TOKEN_OP_NOT:
			return "not ";
		default:
			return "unsupported operator";
	}
}

/******************************************************************************
 *                                                                            *
 * Function: eval_unquote_str                                                 *
 *                                                                            *
 * Purpose: unquote string                                                    *
 *                                                                            *
 * Parameters: str - [IN] the string to unquote                               *
 *                                                                            *
 * Return value: The unquoted string.                                         *
 *                                                                            *
 * Comments: The string is unquoted in the same buffer.                       *
 *                                                                            *
 ******************************************************************************/
static char	*eval_unquote_str(char *str)
{
	char	*dst, *src;

	if ('\"' != *str)
		return str;

	src = str;
	dst = src++;

	while ('\0' != *src && '"' != *src)
	{
		if ('\\' == *src)
		{

			if ('\0' == *(++src))
				break;
		}

		*dst++ = *src++;
	}

	*dst = '\0';

	return str;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_generate_filter                                             *
 *                                                                            *
 * Purpose: generate filter expression from the specified stack               *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             stack  - [IN] the expression stack                             *
 *             groups - [OUT] the group values to match                       *
 *             filter - [OUT] the generated filter                            *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the filter expression was successfully generated   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_generate_filter(zbx_eval_context_t *ctx, zbx_vector_eval_token_t *stack, zbx_vector_str_t *groups,
		char **filter, char **error)
{
	zbx_vector_str_t	out;
	int			i, ret = FAIL;
	char			*tmp;

	if (0 == stack->values_num)
	{
		*error = zbx_strdup(NULL, "invalid filter expression");
		return FAIL;
	}

	if (ZBX_EVAL_TOKEN_JOKER == stack->values[0].type)
	{
		*filter = NULL;
		return SUCCEED;
	}

	zbx_vector_str_create(&out);

	for (i = 0; i < stack->values_num; i++)
	{
		zbx_eval_token_t	*token = &stack->values[i];

		if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR2))
		{
			if (2 > out.values_num)
			{
				*error = zbx_strdup(NULL, "not enough values on stack for binary operation");
				goto out;
			}

			tmp = zbx_dsprintf(NULL, "(%s%s%s)", out.values[out.values_num - 2], eval_op_str(token->type),
					out.values[out.values_num - 1]);

			zbx_free(out.values[out.values_num - 2]);
			zbx_free(out.values[out.values_num - 1]);
			out.values_num -= 1;
			out.values[out.values_num - 1] = tmp;
		}
		else if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR1))
		{
			if (1 > out.values_num)
			{
				*error = zbx_strdup(NULL, "not enough values on stack for unary operation");
				goto out;
			}

			tmp = zbx_dsprintf(NULL, "%s%s", eval_op_str(token->type), out.values[out.values_num - 1]);

			zbx_free(out.values[out.values_num - 1]);
			out.values[out.values_num - 1] = tmp;
		}
		else if (ZBX_EVAL_TOKEN_FUNCTION == token->type)
		{
			if (1 > out.values_num)
			{
				*error = zbx_strdup(NULL, "not enough values on stack for property comparison");
				goto out;
			}

			tmp = zbx_dsprintf(NULL, "{%d}", groups->values_num);
			zbx_vector_str_append(groups, eval_unquote_str(out.values[out.values_num - 1]));
			out.values[out.values_num - 1] = tmp;
		}
		else if (ZBX_EVAL_TOKEN_NOP != token->type)
			zbx_vector_str_append(&out, zbx_substr(ctx->expression, token->loc.l, token->loc.r));
	}

	if (1 != out.values_num)
	{
		*error = zbx_strdup(NULL, "too many values left on stack after generating filter expression");
		goto out;
	}

	*filter = out.values[0];
	out.values_num = 0;

	ret = SUCCEED;
out:
	zbx_vector_str_clear_ext(&out, zbx_str_free);
	zbx_vector_str_destroy(&out);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_get_group_filter                                        *
 *                                                                            *
 * Purpose: generate group filter expression from item filter                 *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             groups - [OUT] the group values to match                       *
 *             filter - [OUT] the generated filter                            *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the filter expression was successfully generated   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The filter expression is generated by replacing group comparison *
 *           calls with {N} where N is the index of value to compare in       *
 *           groups vector.                                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_get_group_filter(zbx_eval_context_t *ctx, zbx_vector_str_t *groups, char **filter, char **error)
{
	zbx_vector_eval_token_t	stack;
	zbx_vector_uint64_t	index;
	int			i, ret = FAIL;

	zbx_vector_eval_token_create(&stack);
	zbx_vector_uint64_create(&index);

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR2))
		{
			if (2 > index.values_num)
			{
				*error = zbx_strdup(NULL, "not enough values on stack for binary operation");
				goto out;
			}

			eval_filter_apply_op2(token, &stack, &index);
		}
		else if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR1))
		{
			if (1 > index.values_num)
			{
				*error = zbx_strdup(NULL, "not enough values on stack for unary operation");
				goto out;
			}

			eval_filter_apply_op1(token, &stack, &index);
		}
		else if (ZBX_EVAL_TOKEN_FUNCTION == token->type)
		{
			eval_filter_apply_func(ctx, token, &stack, &index);
		}
		else if (ZBX_EVAL_TOKEN_NOP != token->type)
		{
			zbx_vector_uint64_append(&index, stack.values_num);
			zbx_vector_eval_token_append_ptr(&stack, token);
		}
	}

	ret = eval_generate_filter(ctx, &stack, groups, filter, error);
out:
	zbx_vector_uint64_destroy(&index);
	zbx_vector_eval_token_destroy(&stack);

	return ret;
}
