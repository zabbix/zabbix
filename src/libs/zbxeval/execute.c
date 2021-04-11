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
#include "eval.h"

/* exit code in addition to SUCCEED/FAIL */
#define UNKNOWN		1

/******************************************************************************
 *                                                                            *
 * Function: variant_convert_suffixed_num                                     *
 *                                                                            *
 * Purpose: convert variant string value containing suffixed number to        *
 *          floating point variant value                                      *
 *                                                                            *
 * Parameters: value     - [OUT] the output value                             *
 *             value_num - [IN] the value to convert                          *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	variant_convert_suffixed_num(zbx_variant_t *value, const zbx_variant_t *value_num)
{
	char	suffix;

	if (ZBX_VARIANT_STR != value_num->type)
		return FAIL;

	if (SUCCEED != eval_suffixed_number_parse(value_num->data.str, &suffix))
		return FAIL;

	zbx_variant_set_dbl(value, atof(value_num->data.str) * suffix2factor(suffix));

	return SUCCEED;
}

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
 * Return value: SUCCEED - the operator was evaluated successfully            *
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

	if (SUCCEED != zbx_variant_convert(right, ZBX_VARIANT_DBL))
	{
		*error = zbx_dsprintf(*error, "invalid value \"%s\" of type \"%s\" for unary"
				" operator at \"%s\"", zbx_variant_value_desc(right),
				zbx_variant_type_desc(right), ctx->expression + token->loc.l);
		return FAIL;
	}

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_MINUS:
			value = -right->data.dbl;
			break;
		case ZBX_EVAL_TOKEN_OP_NOT:
			value = (SUCCEED == zbx_double_compare(right->data.dbl, 0) ? 1 : 0);
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
	zbx_variant_t	value_dbl;

	if (ZBX_VARIANT_ERR == value->type)
		return FAIL;

	zbx_variant_copy(&value_dbl, value);
	if (SUCCEED != zbx_variant_convert(&value_dbl, ZBX_VARIANT_DBL))
	{
		zbx_variant_clear(&value_dbl);
		return FAIL;
	}

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_AND:
			if (SUCCEED == zbx_double_compare(value_dbl.data.dbl, 0))
			{
				*result = 0;
				return SUCCEED;
			}
			break;
		case ZBX_EVAL_TOKEN_OP_OR:
			if (SUCCEED != zbx_double_compare(value_dbl.data.dbl, 0))
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
 * Function: eval_variant_compare                                             *
 *                                                                            *
 * Purpose: compare two variant values supporting suffixed numbers            *
 *                                                                            *
 * Return value: <0 - the first value is less than the second                 *
 *               >0 - the first value is greater than the second              *
 *               0  - the values are equal                                    *
 *                                                                            *
 ******************************************************************************/
static int	eval_variant_compare(const zbx_variant_t *left, const zbx_variant_t *right)
{
	zbx_variant_t	val_l, val_r;
	int		ret;

	zbx_variant_set_none(&val_l);
	zbx_variant_set_none(&val_r);

	if (SUCCEED == variant_convert_suffixed_num(&val_l, left))
		left = &val_l;

	if (SUCCEED == variant_convert_suffixed_num(&val_r, right))
		right = &val_r;

	ret = zbx_variant_compare(left, right);

	zbx_variant_clear(&val_l);
	zbx_variant_clear(&val_r);

	return ret;
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

	/* check logical equal, not equal operators */

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_EQ:
			value = (0 == eval_variant_compare(left, right) ? 1 : 0);
			goto finish;
		case ZBX_EVAL_TOKEN_OP_NE:
			value = (0 == eval_variant_compare(left, right) ? 0 : 1);
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

	/* check logical operators */

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_AND:
			if (SUCCEED == zbx_double_compare(left->data.dbl, 0) ||
					SUCCEED == zbx_double_compare(right->data.dbl, 0))
			{
				value = 0;
			}
			else
				value = 1;
			goto finish;
		case ZBX_EVAL_TOKEN_OP_OR:
			if (SUCCEED != zbx_double_compare(left->data.dbl, 0) ||
					SUCCEED != zbx_double_compare(right->data.dbl, 0))
			{
				value = 1;
			}
			else
				value = 0;
			goto finish;
	}

	/* check arithmetic operators */

	switch (token->type)
	{
		case ZBX_EVAL_TOKEN_OP_LT:
			value = (0 > zbx_variant_compare(left, right) ? 1 : 0);
			break;
		case ZBX_EVAL_TOKEN_OP_LE:
			value = (0 >= zbx_variant_compare(left, right) ? 1 : 0);
			break;
		case ZBX_EVAL_TOKEN_OP_GT:
			value = (0 < zbx_variant_compare(left, right) ? 1 : 0);
			break;
		case ZBX_EVAL_TOKEN_OP_GE:
			value = (0 <= zbx_variant_compare(left, right) ? 1 : 0);
			break;
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
 * Function: eval_suffixed_number_parse                                       *
 *                                                                            *
 * Purpose: check if the value is suffixed number and return the suffix if    *
 *          exists                                                            *
 *                                                                            *
 * Parameters: value  - [IN] the value to check                               *
 *             suffix - [OUT] the suffix or 0 if number does not have suffix  *
 *                            (optional)                                      *
 *                                                                            *
 * Return value: SUCCEED - the value is suffixed number                       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	 eval_suffixed_number_parse(const char *value, char *suffix)
{
	int	len, num_len;

	if ('-' == *value)
		value++;

	len = strlen(value);

	if (SUCCEED != zbx_suffixed_number_parse(value, &num_len) || num_len != len)
		return FAIL;

	if (NULL != suffix)
		*suffix = value[len - 1];

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
		if (ZBX_EVAL_TOKEN_VAR_NUM == token->type)
		{
			zbx_uint64_t	ui64;

			if (SUCCEED == is_uint64_n(ctx->expression + token->loc.l, token->loc.r - token->loc.l + 1,
					&ui64))
			{
				zbx_variant_set_ui64(&value, ui64);
			}
			else
			{
				zbx_variant_set_dbl(&value, atof(ctx->expression + token->loc.l) *
						suffix2factor(ctx->expression[token->loc.r]));
			}
		}
		else
		{
			dst = zbx_malloc(NULL, token->loc.r - token->loc.l + 2);
			zbx_variant_set_str(&value, dst);

			if (ZBX_EVAL_TOKEN_VAR_STR == token->type)
			{
				for (src = ctx->expression + token->loc.l + 1; src < ctx->expression + token->loc.r;
						src++)
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
	}
	else
	{
		if (ZBX_VARIANT_ERR == token->value.type && 0 == (ctx->rules & ZBX_EVAL_PROCESS_ERROR))
		{
			*error = zbx_strdup(*error, token->value.data.err);
			return FAIL;
		}

		/* Expanded user macro token variables can contain suffixed numbers. */
		/* Try to convert them and just copy the expanded value if failed.   */
		if (ZBX_EVAL_TOKEN_VAR_USERMACRO != token->type ||
				SUCCEED != variant_convert_suffixed_num(&value, &token->value))
		{
			zbx_variant_copy(&value, &token->value);
		}

	}

	zbx_vector_var_append_ptr(output, &value);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_push_null                                           *
 *                                                                            *
 * Purpose: push null value in output stack                                   *
 *                                                                            *
 * Parameters: output   - [IN/OUT] the output value stack                     *
 *                                                                            *
 ******************************************************************************/
static void	eval_execute_push_null(zbx_vector_var_t *output)
{
	zbx_variant_t	value;

	zbx_variant_set_none(&value);
	zbx_vector_var_append_ptr(output, &value);
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
int	eval_compare_token(const zbx_eval_context_t *ctx, const zbx_strloc_t *loc, const char *text,
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
static void	eval_function_return(zbx_uint32_t args_num, zbx_variant_t *value, zbx_vector_var_t *output)
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

	if (output->values_num < (int)token->opt)
	{
		*error = zbx_dsprintf(*error, "not enough arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	for (i = output->values_num - token->opt; i < output->values_num; i++)
	{
		if (ZBX_VARIANT_ERR == output->values[i].type)
		{
			zbx_variant_t	value = output->values[i];

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
 * Function: eval_convert_function_arg                                        *
 *                                                                            *
 * Purpose: convert function argument to the specified type                   *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             type   - [IN] the required type                                *
 *             arg    - [IN/OUT] the argument to convert                      *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - argument was converted successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_convert_function_arg(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		unsigned char type, zbx_variant_t *arg, char **error)
{
	zbx_variant_t	value;

	if (ZBX_VARIANT_DBL == type && SUCCEED == variant_convert_suffixed_num(&value, arg))
	{
		zbx_variant_clear(arg);
		*arg = value;
		return SUCCEED;
	}

	if (SUCCEED == zbx_variant_convert(arg, type))
		return SUCCEED;

	*error = zbx_dsprintf(*error, "invalid arg \"%s\" of type \"%s\" for function at \"%s\"",
			zbx_variant_value_desc(arg), zbx_variant_type_desc(arg), ctx->expression + token->loc.l);

	return FAIL;
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
			if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_DBL, &output->values[i], error))
				return FAIL;
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
	double		sum = 0;
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

		for (i = 0; i < dbl_vector->values_num; i++)
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
	double		avg = 0;
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

		for (i = 0; i < dbl_vector->values_num; i++)
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

	zbx_variant_set_dbl(&value, zbx_strlen_utf8(arg->data.str));
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
	if (NULL == (tm = localtime(&now)))
	{
		*error = zbx_dsprintf(*error, "cannot convert time for function at \"%s\": %s",
				ctx->expression + token->loc.l, zbx_strerror(errno));
		return FAIL;
	}
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
	if (NULL == (tm = localtime(&now)))
	{
		*error = zbx_dsprintf(*error, "cannot convert time for function at \"%s\": %s",
				ctx->expression + token->loc.l, zbx_strerror(errno));
		return FAIL;
	}
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
	if (NULL == (tm = localtime(&now)))
	{
		*error = zbx_dsprintf(*error, "cannot convert time for function at \"%s\": %s",
				ctx->expression + token->loc.l, zbx_strerror(errno));
		return FAIL;
	}
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
	if (NULL == (tm = localtime(&now)))
	{
		*error = zbx_dsprintf(*error, "cannot convert time for function at \"%s\": %s",
				ctx->expression + token->loc.l, zbx_strerror(errno));
		return FAIL;
	}
	zbx_variant_set_str(&value, zbx_dsprintf(NULL, "%d", tm->tm_mday));
	eval_function_return(0, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_function_bitand                                     *
 *                                                                            *
 * Purpose: evaluate bitand() function                                        *
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
static int	eval_execute_function_bitand(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value, *left, *right;
	int		ret;

	if (2 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	left = &output->values[output->values_num - 2];
	right = &output->values[output->values_num - 1];

	if (SUCCEED != zbx_variant_convert(left, ZBX_VARIANT_UI64))
	{
		*error = zbx_dsprintf(*error, "invalid left operand value \"%s\" of type \"%s\"",
				zbx_variant_value_desc(left), zbx_variant_type_desc(left));
		return FAIL;
	}

	if (SUCCEED != zbx_variant_convert(right, ZBX_VARIANT_UI64))
	{
		*error = zbx_dsprintf(*error, "invalid right operand value \"%s\" of type \"%s\"",
				zbx_variant_value_desc(right), zbx_variant_type_desc(right));
		return FAIL;
	}

	zbx_variant_set_ui64(&value, left->data.ui64 & right->data.ui64);
	eval_function_return(2, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_cb_function                                         *
 *                                                                            *
 * Purpose: evaluate function by calling custom callback (if configured)      *
 *                                                                            *
 * parameters: ctx        - [in] the evaluation context                       *
 *             token      - [in] the function token                           *
 *             functio_cb - [in] the callback function                        *
 *             output     - [in/out] the output value stack                   *
 *             error      - [out] the error message in the case of failure    *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_cb_function(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_eval_function_cb_t function_cb, zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value, *args;
	char		*errmsg = NULL;

	args = (0 == token->opt ? NULL : &output->values[output->values_num - token->opt]);

	if (SUCCEED != function_cb(ctx->expression + token->loc.l, token->loc.r - token->loc.l + 1,
			token->opt, args, ctx->data_cb, &ctx->ts, &value, &errmsg))
	{
		*error = zbx_dsprintf(*error, "%s at \"%s\".", errmsg, ctx->expression + token->loc.l);
		zbx_free(errmsg);

		if (0 == (ctx->rules & ZBX_EVAL_PROCESS_ERROR))
			return FAIL;

		zbx_variant_set_error(&value, *error);
		*error = NULL;
	}

	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_math_function_single_param                          *
 *                                                                            *
 * Purpose: evaluate mathematical function by calling passed function         *
 *          with 1 double argument                                            *
 *                                                                            *
 * parameters: ctx        - [in] the evaluation context                       *
 *             token      - [in] the function token                           *
 *             output     - [in/out] the output value stack                   *
 *             error      - [out] the error message in the case of failure    *
 *             func       - [in] the pointer to math function                 *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_math_function_single_param(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error, double (*func)(double))
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

	if ((log == func || log10 == func || sqrt == func) && 0 >= arg->data.dbl)
	{
		*error = zbx_strdup(*error, "Mathematical error, wrong value was passed");
		return FAIL;
	}

	zbx_variant_set_dbl(&value, func(arg->data.dbl));

	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

static double	eval_math_func_round(double n, double decimal_points)
{
	double	multiplier;

	multiplier = pow(10.0, decimal_points);

	return round(n * multiplier ) / multiplier;
}

static double	eval_math_func_truncate(double n, double decimal_points)
{
	double	multiplier = 1;

	if (0 < decimal_points)
		multiplier = pow(10, decimal_points);
	else if (0 == decimal_points)
		multiplier = 1;

	if (0 > n)
		multiplier = -multiplier;

	return floor(multiplier * n) / multiplier;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_math_function_double_param                          *
 *                                                                            *
 * Purpose: evaluate mathematical function by calling passed function         *
 *          with 2 double arguments                                           *
 *                                                                            *
 * parameters: ctx        - [in] the evaluation context                       *
 *             token      - [in] the function token                           *
 *             output     - [in/out] the output value stack                   *
 *             error      - [out] the error message in the case of failure    *
 *             func       - [in] the pointer to math function                 *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_math_function_double_param(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error, double (*func)(double, double))
{
	int		ret;
	zbx_variant_t	*arg1, *arg2, value;

	if (2 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_prepare_math_function_args(ctx, token, output, error)))
		return ret;

	arg1 = &output->values[output->values_num - 2];
	arg2 = &output->values[output->values_num - 1];

	if ((eval_math_func_round == func || eval_math_func_truncate == func) && 0 > arg2->data.dbl)
	{
		*error = zbx_strdup(*error, "Mathematical error, wrong value was passed");
		return FAIL;
	}

	zbx_variant_set_dbl(&value, func(arg1->data.dbl, arg2->data.dbl));

	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

#define ZBX_MATH_CONST_PI	3.141592653589793238463
#define ZBX_MATH_CONST_E	2.718281828459045

static double	eval_math_func_degrees(double radians)
{
	return radians * (180.0 / ZBX_MATH_CONST_PI);
}

static double	eval_math_func_radians(double degrees)
{
	return degrees * (ZBX_MATH_CONST_PI / 180);
}

static double	eval_math_func_cot(double x)
{
	return cos(x) / sin(x);
}

static double	eval_math_func_signum(double x)
{
	if (0 > x)
		return -1;
	if (0 == x)
		return 0;
	return 1;
}

static double	eval_math_func_rand()
{
	double	rand_val;

	srand((unsigned int)time(NULL));
	rand_val = (double)rand();

	return rand_val;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_math_function_return_value                          *
 *                                                                            *
 * Purpose: evaluate mathematical function that returns constant value        *
 *                                                                            *
 * parameters: ctx        - [in] the evaluation context                       *
 *             token      - [in] the function token                           *
 *             output     - [in/out] the output value stack                   *
 *             error      - [out] the error message in the case of failure    *
 *             value      - [in] the constant value to return                 *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_math_return_value(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error, double value)
{
	zbx_variant_t	ret_value;

	zbx_variant_set_dbl(&ret_value, value);
	eval_function_return(0, &ret_value, output);

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Function: eval_execute_common_function                                     *
 *                                                                            *
 * Purpose: evaluate common function                                          *
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
static int	eval_execute_common_function(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
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
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "bitand", ZBX_CONST_STRLEN("bitand")))
		return eval_execute_function_bitand(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "cbrt", ZBX_CONST_STRLEN("cbrt")))
		return eval_execute_math_function_single_param(ctx, token, output, error, cbrt);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "ceil", ZBX_CONST_STRLEN("ceil")))
		return eval_execute_math_function_single_param(ctx, token, output, error, ceil);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "exp", ZBX_CONST_STRLEN("exp")))
		return eval_execute_math_function_single_param(ctx, token, output, error, exp);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "expm1", ZBX_CONST_STRLEN("expm1")))
		return eval_execute_math_function_single_param(ctx, token, output, error, expm1);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "floor", ZBX_CONST_STRLEN("floor")))
		return eval_execute_math_function_single_param(ctx, token, output, error, floor);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "signum", ZBX_CONST_STRLEN("signum")))
		return eval_execute_math_function_single_param(ctx, token, output, error, eval_math_func_signum);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "degrees", ZBX_CONST_STRLEN("degrees")))
		return eval_execute_math_function_single_param(ctx, token, output, error, eval_math_func_degrees);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "radians", ZBX_CONST_STRLEN("radians")))
		return eval_execute_math_function_single_param(ctx, token, output, error, eval_math_func_radians);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "acos", ZBX_CONST_STRLEN("acos")))
		return eval_execute_math_function_single_param(ctx, token, output, error, acos);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "asin", ZBX_CONST_STRLEN("asin")))
		return eval_execute_math_function_single_param(ctx, token, output, error, asin);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "atan", ZBX_CONST_STRLEN("atan")))
		return eval_execute_math_function_single_param(ctx, token, output, error, atan);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "cos", ZBX_CONST_STRLEN("cos")))
		return eval_execute_math_function_single_param(ctx, token, output, error, cos);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "cosh", ZBX_CONST_STRLEN("cosh")))
		return eval_execute_math_function_single_param(ctx, token, output, error, cosh);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "cot", ZBX_CONST_STRLEN("cot")))
		return eval_execute_math_function_single_param(ctx, token, output, error, eval_math_func_cot);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "sin", ZBX_CONST_STRLEN("sin")))
		return eval_execute_math_function_single_param(ctx, token, output, error, sin);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "sinh", ZBX_CONST_STRLEN("sinh")))
		return eval_execute_math_function_single_param(ctx, token, output, error, sinh);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "tan", ZBX_CONST_STRLEN("tan")))
		return eval_execute_math_function_single_param(ctx, token, output, error, tan);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "log", ZBX_CONST_STRLEN("log")))
		return eval_execute_math_function_single_param(ctx, token, output, error, log);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "log10", ZBX_CONST_STRLEN("log10")))
		return eval_execute_math_function_single_param(ctx, token, output, error, log10);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "sqrt", ZBX_CONST_STRLEN("sqrt")))
		return eval_execute_math_function_single_param(ctx, token, output, error, sqrt);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "power", ZBX_CONST_STRLEN("power")))
		return eval_execute_math_function_double_param(ctx, token, output, error, pow);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "round", ZBX_CONST_STRLEN("round")))
		return eval_execute_math_function_double_param(ctx, token, output, error, eval_math_func_round);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "mod", ZBX_CONST_STRLEN("mod")))
		return eval_execute_math_function_double_param(ctx, token, output, error, fmod);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "truncate", ZBX_CONST_STRLEN("truncate")))
		return eval_execute_math_function_double_param(ctx, token, output, error, eval_math_func_truncate);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "atan2", ZBX_CONST_STRLEN("atan2")))
		return eval_execute_math_function_double_param(ctx, token, output, error, atan2);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "pi", ZBX_CONST_STRLEN("pi")))
		return eval_execute_math_return_value(ctx, token, output, error, ZBX_MATH_CONST_PI);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "e", ZBX_CONST_STRLEN("e")))
		return eval_execute_math_return_value(ctx, token, output, error, ZBX_MATH_CONST_E);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "rand", ZBX_CONST_STRLEN("rand")))
		return eval_execute_math_return_value(ctx, token, output, error, eval_math_func_rand());

	if (NULL != ctx->common_func_cb)
		return eval_execute_cb_function(ctx, token, ctx->common_func_cb, output, error);

	*error = zbx_dsprintf(*error, "Unknown function at \"%s\".", ctx->expression + token->loc.l);
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_execute_history_function                                    *
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
static int	eval_execute_history_function(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	if ((zbx_uint32_t)output->values_num < token->opt)
	{
		*error = zbx_dsprintf(*error, "not enough arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (NULL != ctx->history_func_cb)
		return eval_execute_cb_function(ctx, token, ctx->history_func_cb, output, error);

	*error = zbx_dsprintf(*error, "Unknown function at \"%s\".", ctx->expression + token->loc.l);
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: eval_throw_exception                                             *
 *                                                                            *
 * Purpose: throw exception by returning the specified error                  *
 *                                                                            *
 * Parameters: output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 ******************************************************************************/
static void	eval_throw_exception(zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	*arg;

	if (0 == output->values_num)
	{
		*error = zbx_strdup(*error, "exception must have one argument");
		return;
	}

	arg = &output->values[output->values_num - 1];
	zbx_variant_convert(arg, ZBX_VARIANT_STR);
	*error = arg->data.str;
	zbx_variant_set_none(arg);
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
				case ZBX_EVAL_TOKEN_NOP:
					break;
				case ZBX_EVAL_TOKEN_VAR_NUM:
				case ZBX_EVAL_TOKEN_VAR_STR:
				case ZBX_EVAL_TOKEN_VAR_MACRO:
				case ZBX_EVAL_TOKEN_VAR_USERMACRO:
					if (SUCCEED != eval_execute_push_value(ctx, token, &output, error))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_ARG_QUERY:
				case ZBX_EVAL_TOKEN_ARG_PERIOD:
					if (SUCCEED != eval_execute_push_value(ctx, token, &output, error))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_ARG_NULL:
					eval_execute_push_null(&output);
					break;
				case ZBX_EVAL_TOKEN_FUNCTION:
					if (SUCCEED != eval_execute_common_function(ctx, token, &output, error))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_HIST_FUNCTION:
					if (SUCCEED != eval_execute_history_function(ctx, token, &output, error))
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
				case ZBX_EVAL_TOKEN_EXCEPTION:
					eval_throw_exception(&output, error);
					goto out;
				default:
					*error = zbx_dsprintf(*error, "unknown token at \"%s\"",
							ctx->expression + token->loc.l);
					goto out;
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
 * Parameters: ctx             - [IN] the evaluation context                  *
 *             ts              - [IN] the timestamp of the execution time     *
 *             common_func_cb  - [IN] the common function callback (optional) *
 *             history_func_cb - [IN] the history function callback (optional)*
 *             data_cb         - [IN] the caller data to be passed to callback*
 *                                    functions                               *
 *                                                                            *
 ******************************************************************************/
static void	eval_init_execute_context(zbx_eval_context_t *ctx, const zbx_timespec_t *ts,
		zbx_eval_function_cb_t common_func_cb, zbx_eval_function_cb_t history_func_cb, void *data_cb)
{
	ctx->common_func_cb = common_func_cb;
	ctx->history_func_cb = history_func_cb;
	ctx->data_cb = data_cb;

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
	eval_init_execute_context(ctx, ts, NULL, NULL, NULL);

	return eval_execute(ctx, value, error);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_execute_ext                                             *
 *                                                                            *
 * Purpose: evaluate parsed expression with callback for custom function      *
 *          processing                                                        *
 *                                                                            *
 * Parameters: ctx             - [IN] the evaluation context                  *
 *             ts              - [IN] the timestamp of the execution time     *
 *             common_func_cb  - [IN] the common function callback (optional) *
 *             history_func_cb - [IN] the history function callback (optional)*
 *             value           - [OUT] the resulting value                    *
 *             error           - [OUT] the error message                      *
 *                                                                            *
 * Return value: SUCCEED - the expression was evaluated successfully          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The callback will be called for unsupported math and all history *
 *           functions.                                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_execute_ext(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, zbx_eval_function_cb_t common_func_cb,
		zbx_eval_function_cb_t history_func_cb, void *data, zbx_variant_t *value, char **error)
{
	eval_init_execute_context(ctx, ts, common_func_cb, history_func_cb, data);

	return eval_execute(ctx, value, error);
}
