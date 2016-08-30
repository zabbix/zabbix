/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "zbxalgo.h"

#include "log.h"

/******************************************************************************
 *                                                                            *
 *                     Module for evaluating expressions                      *
 *                  ---------------------------------------                   *
 *                                                                            *
 * Global variables are used for efficiency reasons so that arguments do not  *
 * have to be passed to each of evaluate_termX() functions. For this reason,  *
 * too, this module is isolated into a separate file.                         *
 *                                                                            *
 * The priority of supported operators is as follows:                         *
 *                                                                            *
 *   - (unary)   evaluate_term8()                                             *
 *   not         evaluate_term7()                                             *
 *   * /         evaluate_term6()                                             *
 *   + -         evaluate_term5()                                             *
 *   < <= >= >   evaluate_term4()                                             *
 *   = <>        evaluate_term3()                                             *
 *   and         evaluate_term2()                                             *
 *   or          evaluate_term1()                                             *
 *                                                                            *
 * Function evaluate_term9() is used for parsing tokens on the lowest level:  *
 * those can be suffixed numbers like "12.345K" or parenthesized expressions. *
 *                                                                            *
 ******************************************************************************/

#define ZBX_INFINITY	(1.0 / 0.0)	/* "Positive infinity" value used as a fatal error code */
#define ZBX_UNKNOWN	(-1.0 / 0.0)	/* "Negative infinity" value used as a code for "Unknown" */

static const char	*ptr;		/* character being looked at */
static int		level;		/* expression nesting level  */

static char		*buffer;	/* error message buffer      */
static size_t		max_buffer_len;	/* error message buffer size */

/******************************************************************************
 *                                                                            *
 * Purpose: check whether the character delimits a numeric token              *
 *                                                                            *
 ******************************************************************************/
static int	is_number_delimiter(unsigned char c)
{
	return 0 == isdigit(c) && '.' != c && 0 == isalpha(c) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether the character delimits a symbolic operator token    *
 *                                                                            *
 ******************************************************************************/
static int	is_operator_delimiter(char c)
{
	return ' ' == c || '(' == c || '\r' == c || '\n' == c || '\t' == c || ')' == c || '\0' == c ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate a suffixed number like "12.345K"                         *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_number(int *unknown_idx)
{
	int		digits = 0, dots = 0;
	const char	*iter = ptr;
	double		result;
	zbx_uint64_t	factor = 1;

	/* Is it a special token of unknown value (e.g. ZBX_UNKNOWN0, ZBX_UNKNOWN1) ? */
	if (0 == strncmp(ZBX_UNKNOWN_STR, ptr, ZBX_UNKNOWN_STR_LEN))
	{
		const char	*p0, *p1;

		p0 = ptr + ZBX_UNKNOWN_STR_LEN;
		p1 = p0;

		/* extract the message number which follows after 'ZBX_UNKNOWN' */
		while (0 != isdigit((unsigned char)*p1))
			p1++;

		if (p0 < p1 && SUCCEED == is_number_delimiter(*p1))
		{
			ptr = p1;

			/* return 'unknown' and corresponding message number about its origin */
			*unknown_idx = atoi(p0);
			return ZBX_UNKNOWN;
		}

		ptr = p0;

		return ZBX_INFINITY;
	}

	while (1)
	{
		if (0 != isdigit((unsigned char)*iter))
		{
			iter++;
			digits++;
			continue;
		}

		if ('.' == *iter)
		{
			iter++;
			dots++;
			continue;
		}

		if (1 > digits || 1 < dots)
			return ZBX_INFINITY;

		if (0 != isalpha((unsigned char)*iter))
		{
			if (NULL == strchr("KMGTsmhdw", *iter))
				return ZBX_INFINITY;

			factor = suffix2factor(*iter++);
		}

		if (SUCCEED != is_number_delimiter(*iter))
			return ZBX_INFINITY;

		result = atof(ptr) * (double)factor;

		ptr = iter;

		return result;
	}
}

static double	evaluate_term1(int *unknown_idx);

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate a suffixed number or a parenthesized expression          *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term9(int *unknown_idx)
{
	double	result;

	while (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr)
		ptr++;

	if ('\0' == *ptr)
	{
		zbx_strlcpy(buffer, "Cannot evaluate expression: unexpected end of expression.", max_buffer_len);
		return ZBX_INFINITY;
	}

	if ('(' == *ptr)
	{
		ptr++;

		if (ZBX_INFINITY == (result = evaluate_term1(unknown_idx)))
			return ZBX_INFINITY;

		/* if evaluate_term1() returns ZBX_UNKNOWN then continue as with regular number */

		if (')' != *ptr)
		{
			zbx_snprintf(buffer, max_buffer_len, "Cannot evaluate expression:"
					" expected closing parenthesis at \"%s\".", ptr);
			return ZBX_INFINITY;
		}

		ptr++;
	}
	else
	{
		if (ZBX_INFINITY == (result = evaluate_number(unknown_idx)))
		{
			zbx_snprintf(buffer, max_buffer_len, "Cannot evaluate expression:"
					" expected numeric token at \"%s\".", ptr);
			return ZBX_INFINITY;
		}
	}

	while ('\0' != *ptr && (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr))
		ptr++;

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "-" (unary)                                              *
 *                                                                            *
 * -0.0     -> -0.0                                                           *
 * -1.2     -> -1.2                                                           *
 * -Unknown ->  Unknown                                                       *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term8(int *unknown_idx)
{
	double	result;

	while (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr)
		ptr++;

	if ('-' == *ptr)
	{
		ptr++;

		if (ZBX_UNKNOWN == (result = evaluate_term9(unknown_idx)) || ZBX_INFINITY == result)
			return result;

		result = -result;
	}
	else
		result = evaluate_term9(unknown_idx);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "not"                                                    *
 *                                                                            *
 * not 0.0     ->  1.0                                                        *
 * not 1.2     ->  0.0                                                        *
 * not Unknown ->  Unknown                                                    *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term7(int *unknown_idx)
{
	double	result;

	while (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr)
		ptr++;

	if ('n' == ptr[0] && 'o' == ptr[1] && 't' == ptr[2] && SUCCEED == is_operator_delimiter(ptr[3]))
	{
		ptr += 3;

		if (ZBX_UNKNOWN == (result = evaluate_term8(unknown_idx)) || ZBX_INFINITY == result)
			return result;

		result = (SUCCEED == zbx_double_compare(result, 0.0) ? 1.0 : 0.0);
	}
	else
		result = evaluate_term8(unknown_idx);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "*" and "/"                                              *
 *                                                                            *
 *     0.0 * Unknown  ->  Unknown (yes, not 0 as we don't want to lose        *
 *                        Unknown in arithmetic operations)                   *
 *     1.2 * Unknown  ->  Unknown                                             *
 *     0.0 / 1.2      ->  0.0                                                 *
 *     1.2 / 0.0      ->  error (ZBX_INFINITY)                                *
 * Unknown / 0.0      ->  error (ZBX_INFINITY)                                *
 * Unknown / 1.2      ->  Unknown                                             *
 * Unknown / Unknown  ->  Unknown                                             *
 *     0.0 / Unknown  ->  Unknown                                             *
 *     1.2 / Unknown  ->  Unknown                                             *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term6(int *unknown_idx)
{
	char	op;
	double	result, operand;
	int	res_idx, oper_idx;

	if (ZBX_INFINITY == (result = evaluate_term7(&res_idx)))
		return ZBX_INFINITY;

	if (ZBX_UNKNOWN == result)
		*unknown_idx = res_idx;

	/* if evaluate_term7() returns ZBX_UNKNOWN then continue as with regular number */

	while ('*' == *ptr || '/' == *ptr)
	{
		op = *ptr++;

		/* 'ZBX_UNKNOWN' in multiplication and division produces 'ZBX_UNKNOWN'. */
		/* Even if 1st operand is Unknown we evaluate 2nd operand too to catch fatal errors in it. */

		if (ZBX_INFINITY == (operand = evaluate_term7(&oper_idx)))
			return ZBX_INFINITY;

		if ('*' == op)
		{
			if (ZBX_UNKNOWN == operand)		/* (anything) * Unknown */
			{
				*unknown_idx = oper_idx;
				result = ZBX_UNKNOWN;
			}
			else if (ZBX_UNKNOWN == result)		/* Unknown * known */
			{
				*unknown_idx = res_idx;
				result = ZBX_UNKNOWN;
			}
			else
				result *= operand;
		}
		else
		{
			/* catch division by 0 even if 1st operand is Unknown */

			if (ZBX_UNKNOWN != operand && SUCCEED == zbx_double_compare(operand, 0.0))
			{
				zbx_strlcpy(buffer, "Cannot evaluate expression: division by zero.", max_buffer_len);
				return ZBX_INFINITY;
			}

			if (ZBX_UNKNOWN == operand)		/* (anything) / Unknown */
			{
				*unknown_idx = oper_idx;
				result = ZBX_UNKNOWN;
			}
			else if (ZBX_UNKNOWN == result)		/* Unknown / known */
			{
				*unknown_idx = res_idx;
				result = ZBX_UNKNOWN;
			}
			else
				result /= operand;
		}
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "+" and "-"                                              *
 *                                                                            *
 *     0.0 +/- Unknown  ->  Unknown                                           *
 *     1.2 +/- Unknown  ->  Unknown                                           *
 * Unknown +/- Unknown  ->  Unknown                                           *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term5(int *unknown_idx)
{
	char	op;
	double	result, operand;
	int	res_idx, oper_idx;

	if (ZBX_INFINITY == (result = evaluate_term6(&res_idx)))
		return ZBX_INFINITY;

	if (ZBX_UNKNOWN == result)
		*unknown_idx = res_idx;

	/* if evaluate_term6() returns ZBX_UNKNOWN then continue as with regular number */

	while ('+' == *ptr || '-' == *ptr)
	{
		op = *ptr++;

		/* even if 1st operand is Unknown we evaluate 2nd operand to catch fatal error if any occurrs */

		if (ZBX_INFINITY == (operand = evaluate_term6(&oper_idx)))
			return ZBX_INFINITY;

		if (ZBX_UNKNOWN == operand)		/* (anything) +/- Unknown */
		{
			*unknown_idx = oper_idx;
			result = ZBX_UNKNOWN;
		}
		else if (ZBX_UNKNOWN == result)		/* Unknown +/- known */
		{
			*unknown_idx = res_idx;
			result = ZBX_UNKNOWN;
		}
		else
		{
			if ('+' == op)
				result += operand;
			else
				result -= operand;
		}
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "<", "<=", ">=", ">"                                     *
 *                                                                            *
 *     0.0 < Unknown  ->  Unknown                                             *
 *     1.2 < Unknown  ->  Unknown                                             *
 * Unknown < Unknown  ->  Unknown                                             *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term4(int *unknown_idx)
{
	char	op;
	double	result, operand;
	int	res_idx, oper_idx;

	if (ZBX_INFINITY == (result = evaluate_term5(&res_idx)))
		return ZBX_INFINITY;

	if (ZBX_UNKNOWN == result)
		*unknown_idx = res_idx;

	/* if evaluate_term5() returns ZBX_UNKNOWN then continue as with regular number */

	while (1)
	{
		if ('<' == ptr[0] && '=' == ptr[1])
		{
			op = 'l';
			ptr += 2;
		}
		else if ('>' == ptr[0] && '=' == ptr[1])
		{
			op = 'g';
			ptr += 2;
		}
		else if (('<' == ptr[0] && '>' != ptr[1]) || '>' == ptr[0])
		{
			op = *ptr++;
		}
		else
			break;

		/* even if 1st operand is Unknown we evaluate 2nd operand to catch fatal error if any occurrs */

		if (ZBX_INFINITY == (operand = evaluate_term5(&oper_idx)))
			return ZBX_INFINITY;

		if (ZBX_UNKNOWN == operand)		/* (anything) < Unknown */
		{
			*unknown_idx = oper_idx;
			result = ZBX_UNKNOWN;
		}
		else if (ZBX_UNKNOWN == result)		/* Unknown < known */
		{
			*unknown_idx = res_idx;
			result = ZBX_UNKNOWN;
		}
		else
		{
			if ('<' == op)
				result = (result <= operand - ZBX_DOUBLE_EPSILON);
			else if ('l' == op)
				result = (result < operand + ZBX_DOUBLE_EPSILON);
			else if ('g' == op)
				result = (result > operand - ZBX_DOUBLE_EPSILON);
			else
				result = (result >= operand + ZBX_DOUBLE_EPSILON);
		}
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "=" and "<>"                                             *
 *                                                                            *
 *      0.0 = Unknown  ->  Unknown                                            *
 *      1.2 = Unknown  ->  Unknown                                            *
 *  Unknown = Unknown  ->  Unknown                                            *
 *     0.0 <> Unknown  ->  Unknown                                            *
 *     1.2 <> Unknown  ->  Unknown                                            *
 * Unknown <> Unknown  ->  Unknown                                            *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term3(int *unknown_idx)
{
	char	op;
	double	result, operand;
	int	res_idx, oper_idx;

	if (ZBX_INFINITY == (result = evaluate_term4(&res_idx)))
		return ZBX_INFINITY;

	if (ZBX_UNKNOWN == result)
		*unknown_idx = res_idx;

	/* if evaluate_term4() returns ZBX_UNKNOWN then continue as with regular number */

	while (1)
	{
		if ('=' == *ptr)
		{
			op = *ptr++;
		}
		else if ('<' == ptr[0] && '>' == ptr[1])
		{
			op = '#';
			ptr += 2;
		}
		else
			break;

		/* even if 1st operand is Unknown we evaluate 2nd operand to catch fatal error if any occurrs */

		if (ZBX_INFINITY == (operand = evaluate_term4(&oper_idx)))
			return ZBX_INFINITY;

		if (ZBX_UNKNOWN == operand)		/* (anything) = Unknown, (anything) <> Unknown */
		{
			*unknown_idx = oper_idx;
			result = ZBX_UNKNOWN;
		}
		else if (ZBX_UNKNOWN == result)		/* Unknown = known, Unknown <> known */
		{
			*unknown_idx = res_idx;
			result = ZBX_UNKNOWN;
		}
		else if ('=' == op)
		{
			result = (SUCCEED == zbx_double_compare(result, operand));
		}
		else
			result = (SUCCEED != zbx_double_compare(result, operand));
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "and"                                                    *
 *                                                                            *
 *      0.0 and Unknown  -> 0.0                                               *
 *  Unknown and 0.0      -> 0.0                                               *
 *      1.0 and Unknown  -> Unknown                                           *
 *  Unknown and 1.0      -> Unknown                                           *
 *  Unknown and Unknown  -> Unknown                                           *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term2(int *unknown_idx)
{
	double	result, operand;
	int	res_idx, oper_idx;

	if (ZBX_INFINITY == (result = evaluate_term3(&res_idx)))
		return ZBX_INFINITY;

	if (ZBX_UNKNOWN == result)
		*unknown_idx = res_idx;

	/* if evaluate_term3() returns ZBX_UNKNOWN then continue as with regular number */

	while ('a' == ptr[0] && 'n' == ptr[1] && 'd' == ptr[2] && SUCCEED == is_operator_delimiter(ptr[3]))
	{
		ptr += 3;

		if (ZBX_INFINITY == (operand = evaluate_term3(&oper_idx)))
			return ZBX_INFINITY;

		if (ZBX_UNKNOWN == result)
		{
			if (ZBX_UNKNOWN == operand)				/* Unknown and Unknown */
			{
				*unknown_idx = oper_idx;
				result = ZBX_UNKNOWN;
			}
			else if (SUCCEED == zbx_double_compare(operand, 0.0))	/* Unknown and 0 */
			{
				result = 0.0;
			}
			else							/* Unknown and 1 */
			{
				*unknown_idx = res_idx;
				result = ZBX_UNKNOWN;
			}
		}
		else if (ZBX_UNKNOWN == operand)
		{
			if (SUCCEED == zbx_double_compare(result, 0.0))		/* 0 and Unknown */
			{
				result = 0.0;
			}
			else							/* 1 and Unknown */
			{
				*unknown_idx = oper_idx;
				result = ZBX_UNKNOWN;
			}
		}
		else
		{
			result = (SUCCEED != zbx_double_compare(result, 0.0) &&
					SUCCEED != zbx_double_compare(operand, 0.0));
		}
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "or"                                                     *
 *                                                                            *
 *      1.0 or Unknown  -> 1.0                                                *
 *  Unknown or 1.0      -> 1.0                                                *
 *      0.0 or Unknown  -> Unknown                                            *
 *  Unknown or 0.0      -> Unknown                                            *
 *  Unknown or Unknown  -> Unknown                                            *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term1(int *unknown_idx)
{
	double	result, operand;
	int	res_idx, oper_idx;

	level++;

	if (32 < level)
	{
		zbx_strlcpy(buffer, "Cannot evaluate expression: nesting level is too deep.", max_buffer_len);
		return ZBX_INFINITY;
	}

	if (ZBX_INFINITY == (result = evaluate_term2(&res_idx)))
		return ZBX_INFINITY;

	if (ZBX_UNKNOWN == result)
		*unknown_idx = res_idx;

	/* if evaluate_term2() returns ZBX_UNKNOWN then continue as with regular number */

	while ('o' == ptr[0] && 'r' == ptr[1] && SUCCEED == is_operator_delimiter(ptr[2]))
	{
		ptr += 2;

		if (ZBX_INFINITY == (operand = evaluate_term2(&oper_idx)))
			return ZBX_INFINITY;

		if (ZBX_UNKNOWN == result)
		{
			if (ZBX_UNKNOWN == operand)				/* Unknown or Unknown */
			{
				*unknown_idx = oper_idx;
				result = ZBX_UNKNOWN;
			}
			else if (SUCCEED != zbx_double_compare(operand, 0.0))	/* Unknown or 1 */
			{
				result = 1;
			}
			else							/* Unknown or 0 */
			{
				*unknown_idx = res_idx;
				result = ZBX_UNKNOWN;
			}
		}
		else if (ZBX_UNKNOWN == operand)
		{
			if (SUCCEED != zbx_double_compare(result, 0.0))		/* 1 or Unknown */
			{
				result = 1;
			}
			else							/* 0 or Unknown */
			{
				*unknown_idx = oper_idx;
				result = ZBX_UNKNOWN;
			}
		}
		else
		{
			result = (SUCCEED != zbx_double_compare(result, 0.0) ||
					SUCCEED != zbx_double_compare(operand, 0.0));
		}
	}

	level--;

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate an expression like "(26.416>10) or (0=1)"                *
 *                                                                            *
 ******************************************************************************/
int	evaluate(double *value, const char *expression, char *error, size_t max_error_len,
		zbx_vector_ptr_t *unknown_msgs)
{
	const char	*__function_name = "evaluate";
	int		unknown_idx;			/* index of message in 'unknown_msgs' vector */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, expression);

	ptr = expression;
	level = 0;

	buffer = error;
	max_buffer_len = max_error_len;

	*value = evaluate_term1(&unknown_idx);

	if ('\0' != *ptr && ZBX_INFINITY != *value)
	{
		zbx_snprintf(error, max_error_len, "Cannot evaluate expression: unexpected token at \"%s\".", ptr);
		*value = ZBX_INFINITY;
	}

	if (ZBX_UNKNOWN == *value)
	{
		/* Map Unknown result to error. Callers currently do not operate with ZBX_UNKNOWN. */
		if (NULL != unknown_msgs)
		{
			if (unknown_msgs->values_num > unknown_idx)
			{
				zbx_snprintf(error, max_error_len, "Cannot evaluate expression: \"%s\".",
						unknown_msgs->values[unknown_idx]);
			}
			else
			{
				zbx_snprintf(error, max_error_len, "Cannot evaluate expression: unsupported "
						ZBX_UNKNOWN_STR "%d value.", unknown_idx);
			}
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			/* do not leave garbage in error buffer, write something helpful */
			zbx_snprintf(error, max_error_len, "%s(): internal error: no message for unknown result",
					__function_name);
		}

		*value = ZBX_INFINITY;
	}

	if (ZBX_INFINITY == *value)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() error:'%s'", __function_name, error);
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:" ZBX_FS_DBL, __function_name, *value);

	return SUCCEED;
}
