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

#include "eval.h"

/* The tag expression token is virtual token used during item query filter processing. */
#define ZBX_EVAL_TOKEN_TAG_EXPRESSION		(1000)

/******************************************************************************
 *                                                                            *
 * Purpose: parse item query /host/key?[filter] into host, key and filter     *
 *          components                                                        *
 *                                                                            *
 * Parameters: str   - [IN] the item query                                    *
 *             len   - [IN] the query length                                  *
 *             query - [IN] the parsed item query                             *
 *                                                                            *
 * Return value: The number of parsed characters.                             *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_eval_parse_query(const char *str, size_t len, zbx_item_query_t *query)
{
	size_t		n;
	const char	*host, *key, *filter;

	if (0 == (n = eval_parse_query(str, &host, &key, &filter)) || n != len)
		return 0;

	query->host = (host != key - 1 ? zbx_substr(host, 0, key - host - 2) : NULL);

	if (NULL != filter)
	{
		query->key = zbx_substr(key, 0, (filter - key) - 3);
		query->filter = zbx_substr(filter, 0, (str + len - filter) - 2);
	}
	else
	{
		query->key = zbx_substr(key, 0, (str + len - key) - 1);
		query->filter = NULL;
	}

	return n;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated by item reference                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_clear_query(zbx_item_query_t *query)
{
	zbx_free(query->host);
	zbx_free(query->key);
	zbx_free(query->filter);
}

/******************************************************************************
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

	if (ZBX_EVAL_TOKEN_TAG_EXPRESSION == left->type || ZBX_EVAL_TOKEN_TAG_EXPRESSION == right->type)
	{
		switch (token->type)
		{
			case ZBX_EVAL_TOKEN_OP_AND:
				if (ZBX_EVAL_TOKEN_TAG_EXPRESSION == left->type)
				{
					memmove(left, right, (stack->values_num - ri) * sizeof(zbx_eval_token_t));
					stack->values_num -= (ri - li);
				}
				else
					stack->values_num--;
				break;
			default:
				left->type = ZBX_EVAL_TOKEN_TAG_EXPRESSION;
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
 * Purpose: apply unary operation to stack                                    *
 *                                                                            *
 * Parameters: token - [IN] the operation token                               *
 *             stack - [IN/OUT] the target stack                              *
 *             index - [IN/OUT] the stack index                               *
 *                                                                            *
 ******************************************************************************/
static void	eval_filter_apply_op1(zbx_eval_token_t *token, zbx_vector_eval_token_t *stack,
		const zbx_vector_uint64_t *index)
{
	zbx_eval_token_t	*right;
	int			ri;

	ri = (int)index->values[index->values_num - 1];
	right = &stack->values[ri];

	if (ZBX_EVAL_TOKEN_TAG_EXPRESSION != right->type)
		zbx_vector_eval_token_append_ptr(stack, token);
}

/******************************************************************************
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
		zbx_vector_eval_token_t *stack, const zbx_vector_uint64_t *index)
{
	zbx_eval_token_t	*left;
	int			li;

	if (ZBX_CONST_STRLEN("tag") == token->loc.r - token->loc.l + 1 &&
			0 == memcmp(ctx->expression + token->loc.l, "tag", ZBX_CONST_STRLEN("tag")))
	{
		li = (int)index->values[index->values_num - 1];
		left = &stack->values[li];

		left->type = ZBX_EVAL_TOKEN_TAG_EXPRESSION;
		stack->values_num = li + 1;
	}
	else
		zbx_vector_eval_token_append_ptr(stack, token);
}

/******************************************************************************
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
static int	eval_generate_filter(const zbx_eval_context_t *ctx, const zbx_vector_eval_token_t *stack,
		zbx_vector_str_t *groups, char **filter, char **error)
{
	zbx_vector_str_t	out;
	int			i, ret = FAIL;
	char			*tmp;

	if (0 == stack->values_num)
	{
		*error = zbx_strdup(NULL, "invalid filter expression");
		return FAIL;
	}

	if (ZBX_EVAL_TOKEN_TAG_EXPRESSION == stack->values[0].type)
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
 * Purpose: generate group SQL filter expression from item filter             *
 *                                                                            *
 * Parameters: ctx    - [IN] the filter expression evaluation context         *
 *             groups - [OUT] the group values to match                       *
 *             filter - [OUT] the generated filter                            *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the filter expression was successfully generated   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The filter SQL is generated in two steps.                        *
 *           1) The filter expression token stack is simplified by removing   *
 *              tag related expression parts, so tags do not affect selection *
 *              item candidates.                                              *
 *              This is done by 'evaluating' the original filter expression   *
 *              token stack according to the following rules:                 *
 *                * group comparisons are copied without changes              *
 *                * tag comparisons are replaced with TAG_EXPRESSION token    *
 *                * TAG_EXPRESSION and <expression> is replaced with          *
 *                  <expression>                                              *
 *                * TAG_EXPRESSION or <expression> is replaced with           *
 *                  TAG_EXPRESSION                                            *
 *                * not TAG_EXPRESSION is replaced with TAG_EXPRESSION        *
 *                                                                            *
 *           2) At this point the simplified stack will contain either only   *
 *              group comparisons/logical operators or TAG_EXPRESSION token.  *
 *              In the first case the filter is generated by replacing group  *
 *              comparisons with {N}, where N is the index of value to        *
 *              compare in groups vector.                                     *
 *              In the second case it means that selection of item candidates *
 *              cannot be restricted by groups and the filter must be NULL.   *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_get_group_filter(zbx_eval_context_t *ctx, zbx_vector_str_t *groups, char **filter, char **error)
{
	zbx_vector_eval_token_t	stack;	/* simplified filter expression token stack */
	zbx_vector_uint64_t	output;	/* pseudo output stack, containing indexes of expression     */
					/* fragments in the simplified filter expression token stack */
	int			i, ret = FAIL;

	zbx_vector_eval_token_create(&stack);
	zbx_vector_uint64_create(&output);

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR2))
		{
			if (2 > output.values_num)
			{
				*error = zbx_strdup(NULL, "not enough values on stack for binary operation");
				goto out;
			}

			eval_filter_apply_op2(token, &stack, &output);
		}
		else if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR1))
		{
			if (1 > output.values_num)
			{
				*error = zbx_strdup(NULL, "not enough values on stack for unary operation");
				goto out;
			}

			eval_filter_apply_op1(token, &stack, &output);
		}
		else if (ZBX_EVAL_TOKEN_FUNCTION == token->type)
		{
			eval_filter_apply_func(ctx, token, &stack, &output);
		}
		else if (ZBX_EVAL_TOKEN_NOP != token->type)
		{
			zbx_vector_uint64_append(&output, stack.values_num);
			zbx_vector_eval_token_append_ptr(&stack, token);
		}
	}

	ret = eval_generate_filter(ctx, &stack, groups, filter, error);
out:
	zbx_vector_uint64_destroy(&output);
	zbx_vector_eval_token_destroy(&stack);

	return ret;
}
