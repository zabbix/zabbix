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

static zbx_variant_t	var_zero = {.type = ZBX_VARIANT_DBL, .data = {.dbl = 0}};

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_op_unary                                            *
 *                                                                            *
 * Purpose: evaluate unary operator                                           *
 *                                                                            *
 * Parameters: ctx      - [IN] the evaluation context                         *
 *             token    - [IN] the operator token                             *
 *             output   - [IN/OUT] the output value stack                     *
 *             error    - [OUT] the error message in the case of failure      *
 *                                                                            *
 * Return value: SUCCEED - the oeprator was evaluated successfully            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_op_unary(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	*right;
	double		value;

	if (1 > output->values_num)
	{
		*error = zbx_dsprintf(*error, "unary operator requires one operand at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	right = &output->values[output->values_num - 1];

	if (ZBX_VARIANT_ERR == right->type)
		return SUCCEED;

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_MINUS:
			if (SUCCEED != zbx_variant_convert(right, ZBX_VARIANT_DBL))
			{
				*error = zbx_dsprintf(*error, "invalid value \"%s\" of type \"%s\" for unary minus"
						" operator at \"%s\"", zbx_variant_value_desc(right),
						zbx_variant_type_desc(right), ctx->expression + token->loc.l);
				return FAIL;
			}
			value = -right->data.dbl;
			break;
		case ZBX_EVAL_TOKEN_OP_NOT:
			value = (SUCCEED == zbx_variant_compare(right, &var_zero) ? 1 : 0);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			*error = zbx_dsprintf(*error, "unknown unary operator at \"%s\"",
					ctx->expression + token->loc.l);
			return FAIL;
	}

	zbx_variant_clear(right);
	zbx_variant_set_dbl(right, value);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_op_logic_err                                        *
 *                                                                            *
 * Purpose: evaluate logical or/and operator with one operand being error     *
 *                                                                            *
 * Parameters: token  - [IN] the operator token                               *
 *             value  - [IN] the other operand                                *
 *             result - [OUT] the resulting value                             *
 *                                                                            *
 * Return value: SUCCEED - the oeprator was evaluated successfully            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_op_logic_err(const zbx_eval_token_t *token, const zbx_variant_t *value, double *result)
{
	if (ZBX_VARIANT_ERR == value->type)
		return FAIL;

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_AND:
			if (SUCCEED == zbx_variant_compare(value, &var_zero))
			{
				*result = 0;
				return SUCCEED;
			}
			break;
		case ZBX_EVAL_TOKEN_OP_OR:
			if (SUCCEED != zbx_variant_compare(value, &var_zero))
			{
				*result = 1;
				return SUCCEED;
			}
			break;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_op_binary                                           *
 *                                                                            *
 * Purpose: evaluate binary operator                                          *
 *                                                                            *
 * Parameters: ctx      - [IN] the evaluation context                         *
 *             token    - [IN] the operator token                             *
 *             output   - [IN/OUT] the output value stack                     *
 *             error    - [OUT] the error message in the case of failure      *
 *                                                                            *
 * Return value: SUCCEED - the oeprator was evaluated successfully            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_op_binary(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	*left, *right;
	double		value;

	if (2 > output->values_num)
	{
		*error = zbx_dsprintf(*error, "binary operator requires two operands at \"%s\"",
				ctx->expression + token->loc.l);

		return FAIL;
	}

	left = &output->values[output->values_num - 2];
	right = &output->values[output->values_num - 1];

	/* process error operands */

	if (ZBX_VARIANT_ERR == left->type)
	{
		if (ZBX_EVAL_TOKEN_OP_AND == token->type || ZBX_EVAL_TOKEN_OP_OR == token->type)
		{
			if (SUCCEED == eval_execute_op_logic_err(token, right, &value))
				goto finish;
		}

		zbx_variant_clear(right);
		output->values_num--;

		return SUCCEED;
	}
	else if (ZBX_VARIANT_ERR == right->type)
	{
		if (ZBX_EVAL_TOKEN_OP_AND == token->type || ZBX_EVAL_TOKEN_OP_OR == token->type)
		{
			if (SUCCEED == eval_execute_op_logic_err(token, left, &value))
				goto finish;
		}
		zbx_variant_clear(left);
		*left = *right;
		output->values_num--;

		return SUCCEED;
	}

	/* check logical operators */

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_EQ:
			value = (0 == zbx_variant_compare(left, right) ? 1 : 0);
			goto finish;
		case ZBX_EVAL_TOKEN_OP_NE:
			value = (0 == zbx_variant_compare(left, right) ? 0 : 1);
			goto finish;
		case ZBX_EVAL_TOKEN_OP_LT:
			value = (0 > zbx_variant_compare(left, right) ? 1 : 0);
			goto finish;
		case ZBX_EVAL_TOKEN_OP_LE:
			value = (0 >= zbx_variant_compare(left, right) ? 1 : 0);
			goto finish;
		case ZBX_EVAL_TOKEN_OP_GT:
			value = (0 < zbx_variant_compare(left, right) ? 1 : 0);
			goto finish;
		case ZBX_EVAL_TOKEN_OP_GE:
			value = (0 <= zbx_variant_compare(left, right) ? 1 : 0);
			goto finish;
	}

	/* check logical operators */

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_AND:
			if (SUCCEED == zbx_variant_compare(left, &var_zero) ||
					SUCCEED == zbx_variant_compare(right, &var_zero))
			{
				value = 0;
			}
			else
				value = 1;
			goto finish;
		case ZBX_EVAL_TOKEN_OP_OR:
			if (SUCCEED != zbx_variant_compare(left, &var_zero) ||
					SUCCEED != zbx_variant_compare(right, &var_zero))
			{
				value = 1;
			}
			else
				value = 0;
			goto finish;
	}

	/* check arithmetic operators */

	if (SUCCEED != zbx_variant_convert(left, ZBX_VARIANT_DBL))
	{
		*error = zbx_dsprintf(*error, "invalid left operand value \"%s\" of type \"%s\"",
				zbx_variant_value_desc(left), zbx_variant_type_desc(left));
		return FAIL;
	}

	if (SUCCEED != zbx_variant_convert(right, ZBX_VARIANT_DBL))
	{
		*error = zbx_dsprintf(*error, "invalid right operand value \"%s\" of type \"%s\"",
				zbx_variant_value_desc(right), zbx_variant_type_desc(right));
		return FAIL;
	}

	/* check arithmetic operators */

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_ADD:
			value = left->data.dbl + right->data.dbl;
			break;
		case ZBX_EVAL_TOKEN_OP_SUB:
			value = left->data.dbl - right->data.dbl;
			break;
		case ZBX_EVAL_TOKEN_OP_MUL:
			value = left->data.dbl * right->data.dbl;
			break;
		case ZBX_EVAL_TOKEN_OP_DIV:
			if (SUCCEED == zbx_double_compare(right->data.dbl, 0))
			{
				*error = zbx_dsprintf(*error, "division by zero at \"%s\"",
						ctx->expression + token->loc.l);
				return FAIL;
			}
			value = left->data.dbl / right->data.dbl;
			break;

	}

finish:
	zbx_variant_clear(left);
	zbx_variant_clear(right);
	zbx_variant_set_dbl(left, value);
	output->values_num--;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_push_value                                          *
 *                                                                            *
 * Purpose: push value in output stack                                        *
 *                                                                            *
 * Parameters: ctx      - [IN] the evaluation context                         *
 *             token    - [IN] the value token                                *
 *             output   - [IN/OUT] the output value stack                     *
 *             error    - [OUT] the error message in the case of failure      *
 *                                                                            *
 * Return value: SUCCEED - the value was pushed successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_push_value(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value;
	char		*dst;
	const char	*src;

	if (ZBX_VARIANT_NONE == token->value.type)
	{
		dst = zbx_malloc(NULL, token->loc.r - token->loc.l + 2);
		zbx_variant_set_str(&value, dst);

		if (ZBX_EVAL_TOKEN_VAR_STR == token->type)
		{
			for (src = ctx->expression + token->loc.l + 1; src < ctx->expression + token->loc.r; src++)
			{
				if ('\\' == *src)
					src++;
				*dst++ = *src;
			}
		}
		else
		{
			memcpy(dst, ctx->expression + token->loc.l, token->loc.r - token->loc.l + 1);
			dst += token->loc.r - token->loc.l + 1;
		}

		*dst = '\0';
	}
	else
	{
		if (ZBX_VARIANT_ERR == token->value.type && 0 == (ctx->rules & ZBX_EVAL_PROCESS_ERROR))
		{
			*error = zbx_strdup(*error, token->value.data.err);
			return FAIL;
		}

		zbx_variant_copy(&value, &token->value);
	}

	zbx_vector_var_append_ptr(output, &value);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_compare_token                                               *
 *                                                                            *
 * Purpose: check if expression fragment matches the specified text           *
 *                                                                            *
 * Parameters: ctx  - [IN] the evaluation context                             *
 *             loc  - [IN] the expression fragment location                   *
 *             text - [IN] the text to compare with                           *
 *             len  - [IN] the text length                                    *
 *                                                                            *
 * Return value: SUCCEED - the expression fragment matches the text           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_compare_token(const zbx_eval_context_t *ctx, const zbx_strloc_t *loc, const char *text,
		size_t len)
{
	if (loc->r - loc->l + 1 != len)
		return FAIL;

	if (0 != memcmp(ctx->expression + loc->l, text, len))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_function_return                                             *
 *                                                                            *
 * Purpose: handle function return                                            *
 *                                                                            *
 * Parameters: args_num - [IN] the number of function arguments               *
 *             value    - [IN] the return value                               *
 *             output   - [IN/OUT] the output value stack                     *
 *                                                                            *
 * Comments: The function arguments on output stack are replaced with the     *
 *           return value.                                                    *
 *                                                                            *
 ******************************************************************************/
static void	eval_function_return(int args_num, zbx_variant_t *value, zbx_vector_var_t *output)
{
	int	i;

	for (i = output->values_num - args_num; i < output->values_num; i++)
		zbx_variant_clear(&output->values[i]);
	output->values_num -= args_num;

	zbx_vector_var_append_ptr(output, value);
}

/******************************************************************************
 *                                                                            *
 * Function: eval_validate_function_args                                      *
 *                                                                            *
 * Purpose: validate function arguments                                       *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function arguments contain error values - the      *
 *                         first error is returned as function value without  *
 *                         evaluating the function                            *
 *               FAIL    - argument validation failed                         *
 *               UNKNOWN - argument validation succeeded, function result is  *
 *                         unknown at the moment, function must be evaluated  *
 *                         with the prepared arguments                        *
 *                                                                            *
 ******************************************************************************/
static int	eval_validate_function_args(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int	i;

	if (0 == token->opt)
	{
		*error = zbx_dsprintf(*error, "not enough arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	for (i = output->values_num - token->opt; i < output->values_num; i++)
	{
		if (ZBX_VARIANT_ERR == output->values[i].type)
		{
			zbx_variant_t	value = value = output->values[i];

			/* first error argument is used as function return value */
			zbx_variant_set_none(&output->values[i]);
			eval_function_return(token->opt, &value, output);

			return SUCCEED;
		}
	}

	return UNKNOWN;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_prepare_math_function_args                                  *
 *                                                                            *
 * Purpose: validate and prepare (convert to floating values) math function   *
 *          arguments                                                         *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function arguments contain error values - the      *
 *                         first error is returned as function value without  *
 *                         evaluating the function                            *
 *               FAIL    - argument validation/conversion failed              *
 *               UNKNOWN - argument conversion succeeded, function result is  *
 *                         unknown at the moment, function must be evaluated  *
 *                         with the prepared arguments                        *
 *                                                                            *
 * Comments: Math function accepts either 1+ arguments that can be converted  *
 *           to floating values or a single argument of non-zero length       *
 *           floating value vector.                                           *
 *                                                                            *
 ******************************************************************************/
static int	eval_prepare_math_function_args(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int	i, ret;

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	i = output->values_num - token->opt;

	if (ZBX_VARIANT_DBL_VECTOR != output->values[i].type)
	{
		for (; i < output->values_num; i++)
		{
			if (SUCCEED != zbx_variant_convert(&output->values[i], ZBX_VARIANT_DBL))
			{
				*error = zbx_dsprintf(*error, "invalid value \"%s\" of type \"%s\" for function at"
						" \"%s\"", zbx_variant_value_desc(&output->values[i]),
						zbx_variant_type_desc(&output->values[i]),
						ctx->expression + token->loc.l);
				return FAIL;
			}
		}
	}
	else
	{
		if (1 != token->opt)
		{
			*error = zbx_dsprintf(*error, "too many arguments for function at \"%s\"",
					ctx->expression + token->loc.l);
			return FAIL;
		}

		if (0 == output->values[i].data.dbl_vector->values_num)
		{
			*error = zbx_dsprintf(*error, "empty vector argument for function at \"%s\"",
					ctx->expression + token->loc.l);
			return FAIL;
		}
	}

	return UNKNOWN;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_min                                        *
 *                                                                            *
 * Purpose: evaluate min() function                                           *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_min(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		i, ret;
	double		min;
	zbx_variant_t	value;

	if (UNKNOWN != (ret = eval_prepare_math_function_args(ctx, token, output, error)))
		return ret;

	i = output->values_num - token->opt;

	if (ZBX_VARIANT_DBL_VECTOR != output->values[i].type)
	{
		min = output->values[i++].data.dbl;

		for (; i < output->values_num; i++)
		{
			if (min > output->values[i].data.dbl)
				min = output->values[i].data.dbl;
		}
	}
	else
	{
		zbx_vector_dbl_t	*dbl_vector = output->values[i].data.dbl_vector;

		min = dbl_vector->values[0];

		for (i = 1; i < dbl_vector->values_num; i++)
		{
			if (min > dbl_vector->values[i])
				min = dbl_vector->values[i];
		}
	}

	zbx_variant_set_dbl(&value, min);
	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_max                                        *
 *                                                                            *
 * Purpose: evaluate max() function                                           *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_max(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		i, ret;
	double		max;
	zbx_variant_t	value;

	if (UNKNOWN != (ret = eval_prepare_math_function_args(ctx, token, output, error)))
		return ret;

	i = output->values_num - token->opt;

	if (ZBX_VARIANT_DBL_VECTOR != output->values[i].type)
	{
		max = output->values[i++].data.dbl;

		for (; i < output->values_num; i++)
		{
			if (max < output->values[i].data.dbl)
				max = output->values[i].data.dbl;
		}
	}
	else
	{
		zbx_vector_dbl_t	*dbl_vector = output->values[i].data.dbl_vector;

		max = dbl_vector->values[0];

		for (i = 1; i < dbl_vector->values_num; i++)
		{
			if (max < dbl_vector->values[i])
				max = dbl_vector->values[i];
		}
	}

	zbx_variant_set_dbl(&value, max);
	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_sum                                        *
 *                                                                            *
 * Purpose: evaluate sum() function                                           *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_sum(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		i, ret;
	double		sum;
	zbx_variant_t	value;

	if (UNKNOWN != (ret = eval_prepare_math_function_args(ctx, token, output, error)))
		return ret;

	i = output->values_num - token->opt;

	if (ZBX_VARIANT_DBL_VECTOR != output->values[i].type)
	{
		for (; i < output->values_num; i++)
			sum += output->values[i].data.dbl;
	}
	else
	{
		zbx_vector_dbl_t	*dbl_vector = output->values[i].data.dbl_vector;

		for (i = 0; i < dbl_vector->values_num; i++)\
			sum += dbl_vector->values[i];
	}

	zbx_variant_set_dbl(&value, sum);
	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_avg                                        *
 *                                                                            *
 * Purpose: evaluate avg() function                                           *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_avg(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		i, ret;
	double		avg;
	zbx_variant_t	value;

	if (UNKNOWN != (ret = eval_prepare_math_function_args(ctx, token, output, error)))
		return ret;

	i = output->values_num - token->opt;

	if (ZBX_VARIANT_DBL_VECTOR != output->values[i].type)
	{
		for (; i < output->values_num; i++)
			avg += output->values[i].data.dbl;

		avg /= token->opt;
	}
	else
	{
		zbx_vector_dbl_t	*dbl_vector = output->values[i].data.dbl_vector;

		for (i = 0; i < dbl_vector->values_num; i++)\
			avg += dbl_vector->values[i];

		avg /= dbl_vector->values_num;
	}

	zbx_variant_set_dbl(&value, avg);
	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_abs                                     *
 *                                                                            *
 * Purpose: evaluate abs() function                                        *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_abs(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*arg, value;

	if (1 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_prepare_math_function_args(ctx, token, output, error)))
		return ret;

	arg = &output->values[output->values_num - 1];
	zbx_variant_set_dbl(&value, fabs(arg->data.dbl));
	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_length                                     *
 *                                                                            *
 * Purpose: evaluate length() function                                        *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_length(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*arg, value;

	if (1 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	arg = &output->values[output->values_num - 1];

	if (SUCCEED != zbx_variant_convert(arg, ZBX_VARIANT_STR))
	{
		*error = zbx_dsprintf(*error, "invalid function argument at \"%s\"", ctx->expression + token->loc.l);
		return FAIL;
	}

	zbx_variant_set_dbl(&value, strlen(arg->data.str));
	eval_function_return(1, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_date                                       *
 *                                                                            *
 * Purpose: evaluate date() function                                          *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_date(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value;
	struct tm	*tm;
	time_t		now;

	if (0 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	now = ctx->ts.sec;
	tm = localtime(&now);
	zbx_variant_set_str(&value, zbx_dsprintf(NULL, "%.4d%.2d%.2d", tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday));
	eval_function_return(0, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_time                                       *
 *                                                                            *
 * Purpose: evaluate time() function                                          *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_time(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value;
	struct tm	*tm;
	time_t		now;

	if (0 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	now = ctx->ts.sec;
	tm = localtime(&now);
	zbx_variant_set_str(&value, zbx_dsprintf(NULL, "%.2d%.2d%.2d", tm->tm_hour, tm->tm_min, tm->tm_sec));
	eval_function_return(0, &value, output);

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_now                                        *
 *                                                                            *
 * Purpose: evaluate now() function                                           *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_now(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value;

	if (0 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	zbx_variant_set_str(&value, zbx_dsprintf(NULL, "%d", ctx->ts.sec));
	eval_function_return(0, &value, output);

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_dayofweek                                  *
 *                                                                            *
 * Purpose: evaluate dayofweek() function                                     *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_dayofweek(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value;
	struct tm	*tm;
	time_t		now;

	if (0 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	now = ctx->ts.sec;
	tm = localtime(&now);
	zbx_variant_set_str(&value, zbx_dsprintf(NULL, "%d", 0 == tm->tm_wday ? 7 : tm->tm_wday));
	eval_function_return(0, &value, output);

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_dayofmonth                                 *
 *                                                                            *
 * Purpose: evaluate dayofmonth() function                                    *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_dayofmonth(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value;
	struct tm	*tm;
	time_t		now;

	if (0 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	now = ctx->ts.sec;
	tm = localtime(&now);
	zbx_variant_set_str(&value, zbx_dsprintf(NULL, "%d", tm->tm_mday));
	eval_function_return(0, &value, output);

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: eval_execute_cb_function                                         *
 *                                                                            *
 * Purpose: evaluate function by calling custom callback (if configured)      *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_cb_function(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value, *args;

	if (NULL == ctx->function_cb)
	{
		*error = zbx_dsprintf(*error, "unknown function at \"%s\"", ctx->expression + token->loc.l);
		return FAIL;
	}

	args = (0 == token->opt ? NULL : &output->values[output->values_num - token->opt]);

	if (SUCCEED != ctx->function_cb(ctx->expression + token->loc.l, token->loc.r - token->loc.l + 1,
			token->opt, args, &value, error))
	{
		return FAIL;
	}

	if (ZBX_VARIANT_ERR == value.type && 0 == (ctx->rules & ZBX_EVAL_PROCESS_ERROR))
	{
		*error = zbx_dsprintf(*error, "%s at \"%s\"", value.data.err, ctx->expression + token->loc.l);
		zbx_variant_clear(&value);
		return FAIL;
	}

	eval_function_return(token->opt, &value, output);
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function                                            *
 *                                                                            *
 * Purpose: evaluate normal (non history) function                            *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	char	*errmsg = NULL;

	if ((zbx_uint32_t)output->values_num < token->opt)
	{
		*error = zbx_dsprintf(*error, "not enough arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (SUCCEED == eval_compare_token(ctx, &token->loc, "min", ZBX_CONST_STRLEN("min")))
		return eval_execute_function_min(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "max", ZBX_CONST_STRLEN("max")))
		return eval_execute_function_max(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "sum", ZBX_CONST_STRLEN("sum")))
		return eval_execute_function_sum(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "avg", ZBX_CONST_STRLEN("avg")))
		return eval_execute_function_avg(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "abs", ZBX_CONST_STRLEN("abs")))
		return eval_execute_function_abs(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "length", ZBX_CONST_STRLEN("length")))
		return eval_execute_function_length(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "date", ZBX_CONST_STRLEN("date")))
		return eval_execute_function_date(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "time", ZBX_CONST_STRLEN("time")))
		return eval_execute_function_time(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "now", ZBX_CONST_STRLEN("now")))
		return eval_execute_function_now(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "dayofweek", ZBX_CONST_STRLEN("dayofweek")))
		return eval_execute_function_dayofweek(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "dayofmonth", ZBX_CONST_STRLEN("dayofmonth")))
		return eval_execute_function_dayofmonth(ctx, token, output, error);

	if (FAIL == eval_execute_cb_function(ctx, token, output, &errmsg))
	{
		*error = zbx_dsprintf(*error, "%s at \"%s\"", errmsg, ctx->expression + token->loc.l);
		zbx_free(errmsg);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_hist_function                                       *
 *                                                                            *
 * Purpose: evaluate history function                                         *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_hist_function(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	char	*errmsg = NULL;

	if (FAIL == eval_execute_cb_function(ctx, token, output, &errmsg))
	{
		*error = zbx_dsprintf(*error, "%s at \"%s\"", errmsg, ctx->expression + token->loc.l);
		zbx_free(errmsg);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute                                                     *
 *                                                                            *
 * Purpose: evaluate pre-parsed expression                                    *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             value - [OUT] the resulting value                              *
 *             error - [OUT] the error message in the case of failure         *
 *                                                                            *
 * Return value: SUCCEED - the expression was evaluated successfully          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute(const zbx_eval_context_t *ctx, zbx_variant_t *value, char **error)
{
	zbx_vector_var_t	output;
	int			i, ret = FAIL;

	zbx_vector_var_create(&output);

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR1))
		{
			if (SUCCEED != eval_execute_op_unary(ctx, token, &output, error))
				goto out;
		}
		else if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR2))
		{
			if (SUCCEED != eval_execute_op_binary(ctx, token, &output, error))
				goto out;
		}
		else
		{
			switch (token->type)
			{
				case ZBX_EVAL_TOKEN_VAR_NUM:
				case ZBX_EVAL_TOKEN_VAR_STR:
				case ZBX_EVAL_TOKEN_VAR_MACRO:
				case ZBX_EVAL_TOKEN_VAR_USERMACRO:
					if (SUCCEED != eval_execute_push_value(ctx, token, &output, error))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_ARG_QUERY:
				case ZBX_EVAL_TOKEN_ARG_TIME:
					if (SUCCEED != eval_execute_push_value(ctx, token, &output, error))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_FUNCTION:
					if (SUCCEED != eval_execute_function(ctx, token, &output, error))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_HIST_FUNCTION:
					if (SUCCEED != eval_execute_hist_function(ctx, token, &output, error))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_FUNCTIONID:
					if (ZBX_VARIANT_NONE == token->value.type)
					{
						*error = zbx_strdup(*error, "trigger history functions must be"
								" pre-calculated");
						goto out;
					}
					if (SUCCEED != eval_execute_push_value(ctx, token, &output, error))
						goto out;
					break;
				default:
					*error = zbx_dsprintf(*error, "unknown token at \"%s\"",
							ctx->expression + token->loc.l);

			}
		}
	}

	if (1 != output.values_num)
	{
		*error = zbx_strdup(*error, "output stack after expression execution must contain one value");
		goto out;
	}

	if (ZBX_VARIANT_ERR == output.values[0].type)
	{
		*error = zbx_strdup(*error, output.values[0].data.err);
		goto out;
	}

	*value = output.values[0];
	output.values_num = 0;

	ret = SUCCEED;
out:
	for (i = 0; i < output.values_num; i++)
		zbx_variant_clear(&output.values[i]);

	zbx_vector_var_destroy(&output);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_init_execute_context                                        *
 *                                                                            *
 * Purpose: initialize execution context                                      *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             ts    - [IN] the timestamp of the execution time               *
 *             function_cb - [IN] the callback for function processing        *
 *                                                                            *
 ******************************************************************************/
static void	eval_init_execute_context(zbx_eval_context_t *ctx, const zbx_timespec_t *ts,
		zbx_eval_function_cb_t function_cb)
{
	ctx->function_cb = function_cb;

	if (NULL == ts)
		ctx->ts.sec = ctx->ts.ns = 0;
	else
		ctx->ts = *ts;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_execute                                                 *
 *                                                                            *
 * Purpose: evaluate parsed expression                                        *
 *                                                                            *
 * Parameters: ctx   - [IN] the evaluation context                            *
 *             ts    - [IN] the timestamp of the execution time               *
 *             value - [OUT] the resulting value                              *
 *             error - [OUT] the error message in the case of failure         *
 *                                                                            *
 * Return value: SUCCEED - the expression was evaluated successfully          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_execute(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	eval_init_execute_context(ctx, ts, NULL);

	return eval_execute(ctx, value, error);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_execute_ext                                             *
 *                                                                            *
 * Purpose: evaluate parsed expression with callback for custom function      *
 *          processing                                                        *
 *                                                                            *
 * Parameters: ctx         - [IN] the evaluation context                      *
 *             ts    - [IN] the timestamp of the execution time               *
 *             function_cb - [IN] the callback for function processing        *
 *             value       - [OUT] the resulting value                        *
 *             error       - [OUT] the error message in the case of failure   *
 *                                                                            *
 * Return value: SUCCEED - the expression was evaluated successfully          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The callback will be called for unsupported math and all history *
 *           functions.                                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_execute_ext(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, zbx_eval_function_cb_t function_cb,
		zbx_variant_t *value, char **error)
{
	eval_init_execute_context(ctx, ts, function_cb);

	return eval_execute(ctx, value, error);
}
