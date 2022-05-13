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

#include "common.h"
#include "log.h"
#include "zbxalgo.h"
#include "zbxeval.h"
#include "zbxvariant.h"

#include "eval.h"

/* exit code in addition to SUCCEED/FAIL */
#define UNKNOWN		1

/* bit function types */
typedef enum
{
	FUNCTION_OPTYPE_BIT_AND = 0,
	FUNCTION_OPTYPE_BIT_OR,
	FUNCTION_OPTYPE_BIT_XOR,
	FUNCTION_OPTYPE_BIT_LSHIFT,
	FUNCTION_OPTYPE_BIT_RSHIFT
}
zbx_function_bit_optype_t;

/* trim function types */
typedef enum
{
	FUNCTION_OPTYPE_TRIM_ALL = 0,
	FUNCTION_OPTYPE_TRIM_LEFT,
	FUNCTION_OPTYPE_TRIM_RIGHT
}
zbx_function_trim_optype_t;

/******************************************************************************
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
	double	result;

	if (ZBX_VARIANT_STR != value_num->type)
		return FAIL;

	if (SUCCEED != eval_suffixed_number_parse(value_num->data.str, &suffix))
		return FAIL;

	result = atof(value_num->data.str) * suffix2factor(suffix);

	if (FP_ZERO != fpclassify(result) && FP_NORMAL != fpclassify(result))
		return FAIL;

	zbx_variant_set_dbl(value, result);

	return SUCCEED;
}

/******************************************************************************
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
		*error = zbx_dsprintf(*error, "unary operator operand \"%s\" is not a numeric value at \"%s\"",
				zbx_variant_value_desc(right), ctx->expression + token->loc.l);
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

	if (FP_ZERO != fpclassify(value) && FP_NORMAL != fpclassify(value))
	{
		*error = zbx_dsprintf(*error, "calculation resulted in NaN or Infinity at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	zbx_variant_clear(right);
	zbx_variant_set_dbl(right, value);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate logical or/and operator with one operand being error     *
 *                                                                            *
 * Parameters: token  - [IN] the operator token                               *
 *             value  - [IN] the other operand                                *
 *             result - [OUT] the resulting value                             *
 *                                                                            *
 * Return value: SUCCEED - the operator was evaluated successfully            *
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
 * Purpose: evaluate binary operator                                          *
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

	if (ZBX_EVAL_TOKEN_OP_EQ == token->type || ZBX_EVAL_TOKEN_OP_NE == token->type)
	{
		if (ZBX_VARIANT_DBL_VECTOR == left->type || ZBX_VARIANT_DBL_VECTOR == right->type)
		{
			*error = zbx_dsprintf(*error, "vector cannot be used with comparison operator at \"%s\"",
					ctx->expression + token->loc.l);
			return FAIL;
		}

		switch (token->type)
		{
			case ZBX_EVAL_TOKEN_OP_EQ:
				value = (0 == eval_variant_compare(left, right) ? 1 : 0);
				goto finish;
			case ZBX_EVAL_TOKEN_OP_NE:
				value = (0 == eval_variant_compare(left, right) ? 0 : 1);
				goto finish;
		}
	}

	/* check arithmetic operators */

	if (SUCCEED != zbx_variant_convert(left, ZBX_VARIANT_DBL))
	{
		*error = zbx_dsprintf(*error, "left operand \"%s\" is not a numeric value for operator at \"%s\"",
				zbx_variant_value_desc(left), ctx->expression + token->loc.l);
		return FAIL;
	}

	if (SUCCEED != zbx_variant_convert(right, ZBX_VARIANT_DBL))
	{
		*error = zbx_dsprintf(*error, "right operand \"%s\" is not a numeric value for operator at \"%s\"",
				zbx_variant_value_desc(right), ctx->expression + token->loc.l);
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

	if (FP_ZERO != fpclassify(value) && FP_NORMAL != fpclassify(value))
	{
		*error = zbx_dsprintf(*error, "calculation resulted in NaN or Infinity at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
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

	for (i = output->values_num - (int)args_num; i < output->values_num; i++)
		zbx_variant_clear(&output->values[i]);

	output->values_num -= (int)args_num;

	zbx_vector_var_append_ptr(output, value);
}

/******************************************************************************
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

static const char	*eval_type_desc(unsigned char type)
{
	switch (type)
	{
		case ZBX_VARIANT_DBL:
			return "a numeric";
		case ZBX_VARIANT_UI64:
			return "an unsigned integer";
		case ZBX_VARIANT_STR:
			return "a string";
		default:
			return zbx_get_variant_type_desc(type);
	}
}

/******************************************************************************
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

	*error = zbx_dsprintf(*error, "function argument \"%s\" is not %s value at \"%s\"",
			zbx_variant_value_desc(arg),  eval_type_desc(type), ctx->expression + token->loc.l);

	return FAIL;
}

/******************************************************************************
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

	if (0 == token->opt)
	{
		*error = zbx_dsprintf(*error, "no arguments for function at \"%s\"", ctx->expression + token->loc.l);
		return FAIL;
	}

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
			*error = zbx_dsprintf(*error, "no input data for function at \"%s\"",
					ctx->expression + token->loc.l);
			return FAIL;
		}
	}

	return UNKNOWN;
}

/******************************************************************************
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

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error))
		return FAIL;

	zbx_variant_set_dbl(&value, zbx_strlen_utf8(arg->data.str));
	eval_function_return(1, &value, output);

	return SUCCEED;
}

/******************************************************************************
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
 * Purpose: evaluate bitand(), bitor(), bitxor(), bitlshift(),                *
 *          bitrshift() functions                                             *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             type   - [IN] the function type                                *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_bitwise(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_function_bit_optype_t type, zbx_vector_var_t *output, char **error)
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

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, left, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, right, error))
	{
		return FAIL;
	}

	switch (type)
	{
		case FUNCTION_OPTYPE_BIT_AND:
			zbx_variant_set_ui64(&value, left->data.ui64 & right->data.ui64);
			break;
		case FUNCTION_OPTYPE_BIT_OR:
			zbx_variant_set_ui64(&value, left->data.ui64 | right->data.ui64);
			break;
		case FUNCTION_OPTYPE_BIT_XOR:
			zbx_variant_set_ui64(&value, left->data.ui64 ^ right->data.ui64);
			break;
		case FUNCTION_OPTYPE_BIT_LSHIFT:
			zbx_variant_set_ui64(&value, left->data.ui64 << right->data.ui64);
			break;
		case FUNCTION_OPTYPE_BIT_RSHIFT:
			zbx_variant_set_ui64(&value, left->data.ui64 >> right->data.ui64);
	}

	eval_function_return(2, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate bitnot() function                                        *
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
static int	eval_execute_function_bitnot(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	value, *arg;
	int		ret;

	if (1 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	arg = &output->values[output->values_num - 1];

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, arg, error))
		return FAIL;

	zbx_variant_set_ui64(&value, ~arg->data.ui64);
	eval_function_return(1, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate left() function                                          *
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
static int	eval_execute_function_left(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*arg, *len, value;
	size_t		sz;
	char		*strval;

	if (2 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	arg = &output->values[output->values_num - 2];
	len = &output->values[output->values_num - 1];

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, len, error))
	{
		return FAIL;
	}

	sz = zbx_strlen_utf8_nchars(arg->data.str, (size_t)len->data.ui64) + 1;
	strval = zbx_malloc(NULL, sz);
	zbx_strlcpy_utf8(strval, arg->data.str, sz);

	zbx_variant_set_str(&value, strval);
	eval_function_return(2, &value, output);

	return SUCCEED;
}

static int	eval_validate_statistical_function_args(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int	i, ret;

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	if (1 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	i = output->values_num - 1;

	if (ZBX_VARIANT_DBL_VECTOR != output->values[i].type)
	{
		*error = zbx_dsprintf(*error, "invalid argument type \"%s\" for function at \"%s\"",
				zbx_variant_type_desc(&output->values[i]), ctx->expression + token->loc.l);
		return FAIL;
	}

	if (0 == output->values[i].data.dbl_vector->values_num)
	{
		*error = zbx_dsprintf(*error, "empty vector argument for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	return UNKNOWN;
}

/******************************************************************************
 *                                                                            *
 * Purpose: common operations for aggregate function calculation              *
 *                                                                            *
 * Parameters: ctx       - [IN] the evaluation context                        *
 *             token     - [IN] the function token                            *
 *             stat_func - [IN] pointer to aggregate function to be called    *
 *             output    - [IN/OUT] the output value stack                    *
 *             error     - [OUT] the error message in the case of failure     *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_statistical_function(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_statistical_func_t stat_func, zbx_vector_var_t *output, char **error)
{
	int			ret;
	double			result;
	zbx_variant_t		value;
	zbx_vector_dbl_t	*dbl_vector;

	if (UNKNOWN != (ret = eval_validate_statistical_function_args(ctx, token, output, error)))
		return ret;

	dbl_vector = output->values[output->values_num - (int)token->opt].data.dbl_vector;

	if (FAIL == stat_func(dbl_vector, &result, error))
		return FAIL;

	zbx_variant_set_dbl(&value, result);
	eval_function_return((int)token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate right() function                                         *
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
static int	eval_execute_function_right(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*arg, *len, value;
	size_t		sz, srclen;
	char		*strval, *p;

	if (2 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	arg = &output->values[output->values_num - 2];
	len = &output->values[output->values_num - 1];

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, len, error))
	{
		return FAIL;
	}

	srclen = zbx_strlen_utf8(arg->data.str);

	if (len->data.ui64 < srclen)
	{
		p = zbx_strshift_utf8(arg->data.str, srclen - len->data.ui64);
		sz = zbx_strlen_utf8_nchars(p, (size_t)len->data.ui64) + 1;
		strval = zbx_malloc(NULL, sz);
		zbx_strlcpy_utf8(strval, p, sz);
	}
	else
		strval = zbx_strdup(NULL, arg->data.str);

	zbx_variant_set_str(&value, strval);
	eval_function_return(2, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate mid() function                                           *
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
static int	eval_execute_function_mid(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*arg, *start, *len, value;
	size_t		sz, srclen;
	char		*strval, *p;

	if (3 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	arg = &output->values[output->values_num - 3];
	start = &output->values[output->values_num - 2];
	len = &output->values[output->values_num - 1];

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, start, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, len, error))
	{
		return FAIL;
	}

	srclen = zbx_strlen_utf8(arg->data.str);

	if (0 == start->data.ui64 || start->data.ui64 > srclen)
	{
		*error = zbx_dsprintf(*error, "invalid function second argument at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	p = zbx_strshift_utf8(arg->data.str, start->data.ui64 - 1);

	if (srclen >= start->data.ui64 + len->data.ui64)
	{
		sz = zbx_strlen_utf8_nchars(p, len->data.ui64) + 1;
		strval = zbx_malloc(NULL, sz);
		zbx_strlcpy_utf8(strval, p, sz);
	}
	else
		strval = zbx_strdup(NULL, p);

	zbx_variant_set_str(&value, strval);
	eval_function_return(3, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate trim(), rtrim(), ltrim() functions                       *
 *                                                                            *
 * Parameters: ctx    - [IN] the evaluation context                           *
 *             token  - [IN] the function token                               *
 *             type   - [IN] the function type                                *
 *             output - [IN/OUT] the output value stack                       *
 *             error  - [OUT] the error message in the case of failure        *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_function_trim(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_function_trim_optype_t type, zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*sym, *arg, value, sym_val;

	if (1 > token->opt || 2 < token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	if (2 == token->opt)
	{
		arg = &output->values[output->values_num - 2];
		sym = &output->values[output->values_num - 1];

		if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, sym, error))
			return FAIL;
	}
	else
	{
		arg = &output->values[output->values_num - 1];
		zbx_variant_set_str(&sym_val, zbx_strdup(NULL, ZBX_WHITESPACE));
		sym = &sym_val;
	}

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error))
		return FAIL;

	switch (type)
	{
		case FUNCTION_OPTYPE_TRIM_ALL:
			zbx_ltrim_utf8(arg->data.str, sym->data.str);
			zbx_rtrim_utf8(arg->data.str, sym->data.str);
			break;
		case FUNCTION_OPTYPE_TRIM_RIGHT:
			zbx_rtrim_utf8(arg->data.str, sym->data.str);
			break;
		case FUNCTION_OPTYPE_TRIM_LEFT:
			zbx_ltrim_utf8(arg->data.str, sym->data.str);
			break;
	}

	if (2 != token->opt)
		zbx_variant_clear(&sym_val);

	zbx_variant_set_str(&value, zbx_strdup(NULL, arg->data.str));
	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate concat() function                                        *
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
static int	eval_execute_function_concat(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		i, ret;
	zbx_variant_t	value;
	char		*result = NULL;

	if (2 > token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	for (i = output->values_num - (int)token->opt; i < output->values_num; i++)
	{
		zbx_variant_t	*arg;

		arg = &output->values[i];

		if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error))
		{
			zbx_free(result);
			return FAIL;
		}

		result = zbx_strdcat(result, zbx_variant_value_desc(arg));
	}

	zbx_variant_set_str(&value, result);
	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate insert() function                                        *
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
static int	eval_execute_function_insert(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*arg, *start, *len, *replacement, value;
	char		*strval, *p;
	size_t		str_alloc, str_len, sz, src_len;

	if (4 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	arg = &output->values[output->values_num - 4];
	start = &output->values[output->values_num - 3];
	len = &output->values[output->values_num - 2];
	replacement = &output->values[output->values_num - 1];

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, start, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, len, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, replacement, error))
	{
		return FAIL;
	}

	src_len = zbx_strlen_utf8(arg->data.str);

	if (0 == start->data.ui64 || start->data.ui64 > src_len)
	{
		*error = zbx_dsprintf(*error, "invalid function second argument at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (src_len < start->data.ui64 - 1 + len->data.ui64)
	{
		*error = zbx_dsprintf(*error, "invalid function third argument at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	strval = zbx_strdup(NULL, arg->data.str);
	p = zbx_strshift_utf8(strval, start->data.ui64 - 1);
	sz = zbx_strlen_utf8_nchars(p, len->data.ui64);

	str_alloc = str_len = strlen(strval) + 1;
	zbx_replace_mem_dyn(&strval, &str_alloc, &str_len, (size_t)(p - strval), sz, replacement->data.str,
			strlen(replacement->data.str));

	zbx_variant_set_str(&value, strval);
	eval_function_return(4, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate replace() function                                       *
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
static int	eval_execute_function_replace(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*arg, *pattern, *replacement, value;
	char		*strval, *p;
	size_t		pattern_len, replacement_len;

	if (3 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	arg = &output->values[output->values_num - 3];
	pattern = &output->values[output->values_num - 2];
	replacement = &output->values[output->values_num - 1];

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, pattern, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, replacement, error))
	{
		return FAIL;
	}

	strval = zbx_strdup(NULL, arg->data.str);
	pattern_len = strlen(pattern->data.str);

	if (0 < pattern_len)
	{
		replacement_len = strlen(replacement->data.str);

		while (NULL != (p = strstr(strval, pattern->data.str)))
		{
			size_t	str_alloc, str_len;

			str_alloc = str_len = strlen(strval) + 1;
			zbx_replace_mem_dyn(&strval, &str_alloc, &str_len, (size_t)(p - strval), pattern_len,
					replacement->data.str, replacement_len);
		}
	}

	zbx_variant_set_str(&value, strval);
	eval_function_return(3, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate repeat() function                                        *
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
static int	eval_execute_function_repeat(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		ret;
	zbx_variant_t	*str, *num, value;
	char		*strval = NULL;
	zbx_uint64_t	i;
	size_t		len_utf8;

	if (2 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	str = &output->values[output->values_num - 2];
	num = &output->values[output->values_num - 1];

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, str, error) ||
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, num, error))
	{
		return FAIL;
	}

	len_utf8 = zbx_strlen_utf8(str->data.str);

	if (num->data.ui64 * len_utf8 >= MAX_STRING_LEN)
	{
		*error = zbx_dsprintf(*error, "maximum allowed string length (%d) exceeded: " ZBX_FS_UI64,
				MAX_STRING_LEN, num->data.ui64 * len_utf8);
		return FAIL;
	}

	for (i = num->data.ui64; i > 0; i--)
		strval = zbx_strdcat(strval, str->data.str);

	if (NULL == strval)
		strval = zbx_strdup(NULL, "");

	zbx_variant_set_str(&value, strval);
	eval_function_return(2, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate bytelength() function                                    *
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
static int	eval_execute_function_bytelength(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
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

	if (SUCCEED == zbx_variant_convert(arg, ZBX_VARIANT_UI64))
	{
		zbx_uint64_t	byte = __UINT64_C(0xFF00000000000000);
		int		i;

		for (i = 8; i > 0; i--)
		{
			if (byte & arg->data.ui64)
				break;

			byte = byte >> 8;
		}

		zbx_variant_set_dbl(&value, i);
	}
	else if (SUCCEED != zbx_variant_convert(arg, ZBX_VARIANT_STR))
	{
		*error = zbx_dsprintf(*error, "invalid function argument at \"%s\"", ctx->expression + token->loc.l);
		return FAIL;
	}
	else
		zbx_variant_set_dbl(&value, strlen(arg->data.str));

	eval_function_return(1, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate bitlength() function                                     *
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
static int	eval_execute_function_bitlength(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
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

	if (SUCCEED == zbx_variant_convert(arg, ZBX_VARIANT_UI64))
	{
		int	i, bits;

		bits = sizeof(uint64_t) * 8;

		for (i = bits - 1; i >= 0; i--)
		{
			if (__UINT64_C(1) << i & arg->data.ui64)
				break;
		}

		zbx_variant_set_dbl(&value, ++i);
	}
	else if (SUCCEED != zbx_variant_convert(arg, ZBX_VARIANT_STR))
	{
		*error = zbx_dsprintf(*error, "invalid function argument at \"%s\"", ctx->expression + token->loc.l);
		return FAIL;
	}
	else
		zbx_variant_set_dbl(&value, strlen(arg->data.str) * 8);

	eval_function_return(1, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate char() function                                          *
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
static int	eval_execute_function_char(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
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

	if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_UI64, arg, error))
		return FAIL;

	if (255 < arg->data.ui64)
	{
		*error = zbx_dsprintf(*error, "function argument \"%s\" is out of allowed range at \"%s\"",
				zbx_variant_value_desc(arg), ctx->expression + token->loc.l);
		return FAIL;
	}

	zbx_variant_set_str(&value, zbx_dsprintf(NULL, "%c", (char)arg->data.ui64));
	eval_function_return(1, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate ascii() function                                         *
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
static int	eval_execute_function_ascii(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
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

	if (SUCCEED != zbx_variant_convert(arg, ZBX_VARIANT_STR) || 1 != zbx_utf8_char_len(arg->data.str))
	{
		*error = zbx_dsprintf(*error, "invalid function argument at \"%s\"", ctx->expression + token->loc.l);
		return FAIL;
	}

	zbx_variant_set_ui64(&value, (zbx_uint64_t)*arg->data.str);
	eval_function_return(1, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate between() function                                       *
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
static int	eval_execute_function_between(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		i, ret;
	double		between;
	zbx_variant_t	value;

	if (3 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_prepare_math_function_args(ctx, token, output, error)))
		return ret;

	i = output->values_num - token->opt;
	between = output->values[i++].data.dbl;

	if (output->values[i++].data.dbl <= between && between <= output->values[i].data.dbl)
		zbx_variant_set_dbl(&value, 1);
	else
		zbx_variant_set_dbl(&value, 0);

	eval_function_return(3, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate in() function                                            *
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
static int	eval_execute_function_in(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int		i, arg_idx, found = 0, ret;
	zbx_variant_t	value, *arg, *ref_str = NULL;
	double		ref = 0;

	if (2 > token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	zbx_variant_set_dbl(&value, 0);

	for (i = arg_idx = output->values_num - token->opt; i < output->values_num; i++)
	{
		zbx_variant_t	val_copy;

		if (SUCCEED != variant_convert_suffixed_num(&val_copy, &output->values[i]))
		{
			zbx_variant_copy(&val_copy, &output->values[i]);

			if (SUCCEED != zbx_variant_convert(&val_copy, ZBX_VARIANT_DBL))
			{
				zbx_variant_clear(&val_copy);
				break;
			}
		}

		if (i == arg_idx)
		{
			ref = val_copy.data.dbl;
			continue;
		}

		if (0 == found && SUCCEED == zbx_double_compare(ref, val_copy.data.dbl))
			found = 1;
	}

	if (i == output->values_num)
	{
		if (1 == found)
			zbx_variant_set_dbl(&value, 1);

		goto out;
	}

	for (i = arg_idx; i < output->values_num; i++)
	{
		arg = &output->values[i];

		if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_STR, arg, error))
			return FAIL;

		if (i == arg_idx)
		{
			ref_str = arg;
			continue;
		}

		if (0 == strcmp(ref_str->data.str, arg->data.str))
		{
			zbx_variant_set_dbl(&value, 1);
			break;
		}
	}
out:
	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate histogram_quantile() function                            *
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
static int	eval_execute_function_histogram_quantile(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	int			i, ret;
	zbx_variant_t		value;
	zbx_vector_dbl_t	values, *v = NULL;
	double			q, result;
	const char		*err_fn = ctx->expression + token->loc.l;

	if (2 > token->opt || (2 < token->opt && (token->opt % 2 == 0)))
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"", err_fn);
		return FAIL;
	}

	if (UNKNOWN != (ret = eval_validate_function_args(ctx, token, output, error)))
		return ret;

	i = output->values_num - (int)token->opt + 1;

	if (2 == token->opt)
	{
		if (ZBX_VARIANT_DBL_VECTOR != output->values[i].type)
		{
			*error = zbx_dsprintf(*error, "invalid type of second argument for function at \"%s\"", err_fn);
			return FAIL;
		}

		if (output->values[i].data.dbl_vector->values_num % 2 != 0)
		{
			*error = zbx_dsprintf(*error, "invalid values number of second argument for function at \"%s\"",
					err_fn);
			return FAIL;
		}
	}
	else
	{
		if ((output->values_num - i) % 2 != 0)
		{
			*error = zbx_dsprintf(*error, "invalid number of histogram arguments for function at \"%s\"",
					err_fn);
			return FAIL;
		}

		for (; i < output->values_num; i++)
		{
			if (ZBX_VARIANT_STR == output->values[i].type )
			{
				zbx_strupper(output->values[i].data.str);

				if (0 == strcmp(output->values[i].data.str, "+INF") ||
						0 == strcmp(output->values[i].data.str, "INF"))
				{
					zbx_variant_clear(&output->values[i]);
					zbx_variant_set_dbl(&output->values[i], ZBX_INFINITY);
				}
				else
				{
					*error = zbx_dsprintf(*error, "invalid string values of backet"
							" for function at \"%s\"", err_fn);
					return FAIL;
				}
			}
			else if (SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_DBL, &output->values[i],
					error))
			{
				return FAIL;
			}
		}
	}

	i = output->values_num - (int)token->opt;

	if (ZBX_VARIANT_DBL != output->values[i].type &&
			SUCCEED != eval_convert_function_arg(ctx, token, ZBX_VARIANT_DBL, &output->values[i], error))
	{
		return FAIL;
	}

	q = output->values[i].data.dbl;

	if (0 > q || 1 < q)
	{
		*error = zbx_dsprintf(*error, "invalid value of quantile for function at \"%s\"", err_fn);
		return FAIL;
	}

	i = output->values_num - (int)token->opt + 1;

	if (2 == token->opt)
	{
		v = output->values[i].data.dbl_vector;
	}
	else
	{
		zbx_vector_dbl_create(&values);

		while (i < output->values_num)
		{
			zbx_vector_dbl_append(&values, output->values[i++].data.dbl);
		}
	}

	if (SUCCEED == (ret = zbx_eval_calc_histogram_quantile(q, NULL != v ? v : &values, err_fn, &result, error)))
	{
		zbx_variant_set_dbl(&value, result);
		eval_function_return(token->opt, &value, output);
	}

	if (NULL == v)
		zbx_vector_dbl_destroy(&values);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function by calling custom callback (if configured)      *
 *                                                                            *
 * Parameters: ctx         - [IN] the evaluation context                      *
 *             token       - [IN] the function token                          *
 *             function_cb - [IN] the callback function                       *
 *             output      - [IN/OUT] the output value stack                  *
 *             error       - [OUT] the error message in the case of failure   *
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

#define ZBX_MATH_CONST_PI	3.141592653589793238463
#define ZBX_MATH_CONST_E	2.7182818284590452354
#define ZBX_MATH_RANDOM		0

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

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate mathematical function by calling passed function         *
 *          with 1 double argument                                            *
 *                                                                            *
 * Parameters: ctx        - [IN] the evaluation context                       *
 *             token      - [IN] the function token                           *
 *             output     - [IN/OUT] the output value stack                   *
 *             error      - [OUT] the error message in the case of failure    *
 *             func       - [IN] the pointer to math function                 *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_math_function_single_param(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error, double (*func)(double))
{
	int		ret;
	double		result;
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

	if (((log == func || log10 == func) && 0 >= arg->data.dbl) || (sqrt == func && 0 > arg->data.dbl) ||
			(eval_math_func_cot == func && 0 == arg->data.dbl))
	{
		*error = zbx_dsprintf(*error, "invalid argument for function at \"%s\"",
				ctx->expression + token->loc.l);

		return FAIL;
	}

	result = func(arg->data.dbl);

	if (FP_ZERO != fpclassify(result) && FP_NORMAL != fpclassify(result))
	{
		*error = zbx_dsprintf(*error, "calculation resulted in NaN or Infinity at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	zbx_variant_set_dbl(&value, result);

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

	if (0 > n)
		multiplier = -multiplier;

	return floor(multiplier * n) / multiplier;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate mathematical function by calling passed function         *
 *          with 2 double arguments                                           *
 *                                                                            *
 * Parameters: ctx        - [IN] the evaluation context                       *
 *             token      - [IN] the function token                           *
 *             output     - [IN/OUT] the output value stack                   *
 *             error      - [OUT] the error message in the case of failure    *
 *             func       - [IN] the pointer to math function                 *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_math_function_double_param(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error, double (*func)(double, double))
{
	int		ret;
	double		result;
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

	if (((eval_math_func_round == func || eval_math_func_truncate == func) && (0 > arg2->data.dbl ||
			0.0 != fmod(arg2->data.dbl, 1))) || (fmod == func && 0.0 == arg2->data.dbl))
	{
		*error = zbx_dsprintf(*error, "invalid second argument for function at \"%s\"",
				ctx->expression + token->loc.l);

		return FAIL;
	}

	if (atan2 == func && 0.0 == arg1->data.dbl && 0.0 == arg2->data.dbl)
	{
		*error = zbx_dsprintf(*error, "undefined result for arguments (0,0) for function 'atan2' at \"%s\"",
				ctx->expression + token->loc.l);

		return FAIL;
	}

	result = func(arg1->data.dbl, arg2->data.dbl);

	if (FP_ZERO != fpclassify(result) && FP_NORMAL != fpclassify(result))
	{
		*error = zbx_dsprintf(*error, "calculation resulted in NaN or Infinity at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	zbx_variant_set_dbl(&value, result);

	eval_function_return(token->opt, &value, output);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate mathematical function that returns constant value        *
 *                                                                            *
 * Parameters: ctx        - [IN] the evaluation context                       *
 *             token      - [IN] the function token                           *
 *             output     - [IN/OUT] the output value stack                   *
 *             error      - [OUT] the error message in the case of failure    *
 *             value      - [IN] the value to be returned                     *
 *                                                                            *
 * Return value: SUCCEED - function evaluation succeeded                      *
 *                                                                            *
 ******************************************************************************/
static int	eval_execute_math_return_value(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error, double value)
{
	zbx_variant_t	ret_value;

	if (0 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	if (ZBX_MATH_RANDOM == value)
	{
		struct timespec ts;

		if (SUCCEED != clock_gettime(CLOCK_MONOTONIC, &ts))
		{
			*error = zbx_strdup(*error, "failed to generate seed for random number generator");
			return FAIL;
		}
		else
		{
			srandom((unsigned int)(ts.tv_nsec ^ ts.tv_sec));
			zbx_variant_set_dbl(&ret_value, random());
		}
	}
	else
		zbx_variant_set_dbl(&ret_value, value);

	eval_function_return(0, &ret_value, output);

	return SUCCEED;
}

static int	eval_execute_function_count(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		zbx_vector_var_t *output, char **error)
{
	zbx_variant_t	*arg, ret_value;

	if (1 != token->opt)
	{
		*error = zbx_dsprintf(*error, "invalid number of arguments for function at \"%s\"",
				ctx->expression + token->loc.l);
		return FAIL;
	}

	arg = &output->values[output->values_num - 1];

	if (ZBX_VARIANT_DBL_VECTOR != arg->type)
	{
		*error = zbx_dsprintf(*error, "invalid type of argument for function at \"%s\"",
				ctx->expression + token->loc.l);
	}

	zbx_variant_set_ui64(&ret_value, (zbx_uint64_t)arg->data.dbl_vector->values_num);

	eval_function_return(token->opt, &ret_value, output);

	return SUCCEED;
}

/******************************************************************************
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
		return eval_execute_function_bitwise(ctx, token, FUNCTION_OPTYPE_BIT_AND, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "bitor", ZBX_CONST_STRLEN("bitor")))
		return eval_execute_function_bitwise(ctx, token, FUNCTION_OPTYPE_BIT_OR, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "bitxor", ZBX_CONST_STRLEN("bitxor")))
		return eval_execute_function_bitwise(ctx, token, FUNCTION_OPTYPE_BIT_XOR, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "bitlshift", ZBX_CONST_STRLEN("bitlshift")))
		return eval_execute_function_bitwise(ctx, token, FUNCTION_OPTYPE_BIT_LSHIFT, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "bitrshift", ZBX_CONST_STRLEN("bitrshift")))
		return eval_execute_function_bitwise(ctx, token, FUNCTION_OPTYPE_BIT_RSHIFT, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "bitnot", ZBX_CONST_STRLEN("bitnot")))
		return eval_execute_function_bitnot(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "between", ZBX_CONST_STRLEN("between")))
		return eval_execute_function_between(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "in", ZBX_CONST_STRLEN("in")))
		return eval_execute_function_in(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "ascii", ZBX_CONST_STRLEN("ascii")))
		return eval_execute_function_ascii(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "char", ZBX_CONST_STRLEN("char")))
		return eval_execute_function_char(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "left", ZBX_CONST_STRLEN("left")))
		return eval_execute_function_left(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "right", ZBX_CONST_STRLEN("right")))
		return eval_execute_function_right(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "mid", ZBX_CONST_STRLEN("mid")))
		return eval_execute_function_mid(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "bitlength", ZBX_CONST_STRLEN("bitlength")))
		return eval_execute_function_bitlength(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "bytelength", ZBX_CONST_STRLEN("bytelength")))
		return eval_execute_function_bytelength(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "concat", ZBX_CONST_STRLEN("concat")))
		return eval_execute_function_concat(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "insert", ZBX_CONST_STRLEN("insert")))
		return eval_execute_function_insert(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "replace", ZBX_CONST_STRLEN("replace")))
		return eval_execute_function_replace(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "repeat", ZBX_CONST_STRLEN("repeat")))
		return eval_execute_function_repeat(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "ltrim", ZBX_CONST_STRLEN("ltrim")))
		return eval_execute_function_trim(ctx, token, FUNCTION_OPTYPE_TRIM_LEFT, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "rtrim", ZBX_CONST_STRLEN("rtrim")))
		return eval_execute_function_trim(ctx, token, FUNCTION_OPTYPE_TRIM_RIGHT, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "trim", ZBX_CONST_STRLEN("trim")))
		return eval_execute_function_trim(ctx, token, FUNCTION_OPTYPE_TRIM_ALL, output, error);
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
		return eval_execute_math_return_value(ctx, token, output, error, ZBX_MATH_RANDOM);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "kurtosis", ZBX_CONST_STRLEN("kurtosis")))
		return eval_execute_statistical_function(ctx, token, zbx_eval_calc_kurtosis, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "mad", ZBX_CONST_STRLEN("mad")))
		return eval_execute_statistical_function(ctx, token, zbx_eval_calc_mad, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "skewness", ZBX_CONST_STRLEN("skewness")))
		return eval_execute_statistical_function(ctx, token, zbx_eval_calc_skewness, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "stddevpop", ZBX_CONST_STRLEN("stddevpop")))
		return eval_execute_statistical_function(ctx, token, zbx_eval_calc_stddevpop, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "stddevsamp", ZBX_CONST_STRLEN("stddevsamp")))
		return eval_execute_statistical_function(ctx, token, zbx_eval_calc_stddevsamp, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "sumofsquares", ZBX_CONST_STRLEN("sumofsquares")))
		return eval_execute_statistical_function(ctx, token, zbx_eval_calc_sumofsquares, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "varpop", ZBX_CONST_STRLEN("varpop")))
		return eval_execute_statistical_function(ctx, token, zbx_eval_calc_varpop, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "varsamp", ZBX_CONST_STRLEN("varsamp")))
		return eval_execute_statistical_function(ctx, token, zbx_eval_calc_varsamp, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "count", ZBX_CONST_STRLEN("count")))
		return eval_execute_function_count(ctx, token, output, error);
	if (SUCCEED == eval_compare_token(ctx, &token->loc, "histogram_quantile", ZBX_CONST_STRLEN("histogram_quantile")))
		return eval_execute_function_histogram_quantile(ctx, token, output, error);

	if (NULL != ctx->common_func_cb)
		return eval_execute_cb_function(ctx, token, ctx->common_func_cb, output, error);

	*error = zbx_dsprintf(*error, "Unknown function at \"%s\".", ctx->expression + token->loc.l);
	return FAIL;
}

/******************************************************************************
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
	char			*errmsg = NULL;

	zbx_vector_var_create(&output);

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR1))
		{
			if (SUCCEED != eval_execute_op_unary(ctx, token, &output, &errmsg))
				goto out;
		}
		else if (0 != (token->type & ZBX_EVAL_CLASS_OPERATOR2))
		{
			if (SUCCEED != eval_execute_op_binary(ctx, token, &output, &errmsg))
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
					if (SUCCEED != eval_execute_push_value(ctx, token, &output, &errmsg))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_ARG_QUERY:
				case ZBX_EVAL_TOKEN_ARG_PERIOD:
					if (SUCCEED != eval_execute_push_value(ctx, token, &output, &errmsg))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_ARG_NULL:
					eval_execute_push_null(&output);
					break;
				case ZBX_EVAL_TOKEN_FUNCTION:
					if (SUCCEED != eval_execute_common_function(ctx, token, &output, &errmsg))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_HIST_FUNCTION:
					if (SUCCEED != eval_execute_history_function(ctx, token, &output, &errmsg))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_FUNCTIONID:
					if (ZBX_VARIANT_NONE == token->value.type)
					{
						errmsg = zbx_strdup(errmsg, "trigger history functions must be"
								" pre-calculated");
						goto out;
					}
					if (SUCCEED != eval_execute_push_value(ctx, token, &output, &errmsg))
						goto out;
					break;
				case ZBX_EVAL_TOKEN_EXCEPTION:
					eval_throw_exception(&output, &errmsg);
					goto out;
				default:
					errmsg = zbx_dsprintf(errmsg, "unknown token at \"%s\"",
							ctx->expression + token->loc.l);
					goto out;
			}
		}
	}

	if (1 != output.values_num)
	{
		errmsg = zbx_strdup(errmsg, "output stack after expression execution must contain one value");
		goto out;
	}

	if (ZBX_VARIANT_ERR == output.values[0].type)
	{
		errmsg = zbx_strdup(errmsg, output.values[0].data.err);
		goto out;
	}

	*value = output.values[0];
	output.values_num = 0;

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		if (0 != islower(*errmsg))
		{
			*error = zbx_dsprintf(NULL, "Cannot evaluate expression: %s", errmsg);
			zbx_free(errmsg);
		}
		else
			*error = errmsg;
	}

	for (i = 0; i < output.values_num; i++)
		zbx_variant_clear(&output.values[i]);

	zbx_vector_var_destroy(&output);

	return ret;
}

/******************************************************************************
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
