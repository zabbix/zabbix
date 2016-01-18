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

#define ZBX_INFINITY	(1.0 / 0.0)	/* value used as an error code */

static const char	*ptr;		/* character being looked at */
static int		level;		/* expression nesting level  */

static char		*buffer;	/* error message buffer      */
static int		max_buffer_len;	/* error message buffer size */

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
static double	evaluate_number()
{
	int		digits = 0, dots = 0;
	const char	*iter = ptr;
	double		result;
	zbx_uint64_t	factor = 1;

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

		result = atof(ptr) * factor;

		ptr = iter;

		return result;
	}
}

static double	evaluate_term1();

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate a suffixed number or a parenthesized expression          *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term9()
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

		if (ZBX_INFINITY == (result = evaluate_term1()))
			return ZBX_INFINITY;

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
		if (ZBX_INFINITY == (result = evaluate_number()))
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
 ******************************************************************************/
static double	evaluate_term8()
{
	double	result;

	while (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr)
		ptr++;

	if ('-' == *ptr)
	{
		ptr++;

		if (ZBX_INFINITY == (result = evaluate_term9()))
			return ZBX_INFINITY;

		result = -result;
	}
	else
		result = evaluate_term9();

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "not"                                                    *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term7()
{
	double	result;

	while (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr)
		ptr++;

	if ('n' == ptr[0] && 'o' == ptr[1] && 't' == ptr[2] && SUCCEED == is_operator_delimiter(ptr[3]))
	{
		ptr += 3;

		if (ZBX_INFINITY == (result = evaluate_term8()))
			return ZBX_INFINITY;

		result = (SUCCEED == zbx_double_compare(result, 0) ? 1 : 0);
	}
	else
		result = evaluate_term8();

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "*" and "/"                                              *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term6()
{
	char	op;
	double	result, operand;

	if (ZBX_INFINITY == (result = evaluate_term7()))
		return ZBX_INFINITY;

	while ('*' == *ptr || '/' == *ptr)
	{
		op = *ptr++;

		if (ZBX_INFINITY == (operand = evaluate_term7()))
			return ZBX_INFINITY;

		if ('*' == op)
		{
			result *= operand;
		}
		else
		{
			if (SUCCEED == zbx_double_compare(operand, 0))
			{
				zbx_strlcpy(buffer, "Cannot evaluate expression: division by zero.", max_buffer_len);
				return ZBX_INFINITY;
			}

			result /= operand;
		}
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "+" and "-"                                              *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term5()
{
	char	op;
	double	result, operand;

	if (ZBX_INFINITY == (result = evaluate_term6()))
		return ZBX_INFINITY;

	while ('+' == *ptr || '-' == *ptr)
	{
		op = *ptr++;

		if (ZBX_INFINITY == (operand = evaluate_term6()))
			return ZBX_INFINITY;

		if ('+' == op)
			result += operand;
		else
			result -= operand;
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "<", "<=", ">=", ">"                                     *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term4()
{
	char	op;
	double	result, operand;

	if (ZBX_INFINITY == (result = evaluate_term5()))
		return ZBX_INFINITY;

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

		if (ZBX_INFINITY == (operand = evaluate_term5()))
			return ZBX_INFINITY;

		if ('<' == op)
			result = (result <= operand - ZBX_DOUBLE_EPSILON);
		else if ('l' == op)
			result = (result < operand + ZBX_DOUBLE_EPSILON);
		else if ('g' == op)
			result = (result > operand - ZBX_DOUBLE_EPSILON);
		else
			result = (result >= operand + ZBX_DOUBLE_EPSILON);
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "=" and "<>"                                             *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term3()
{
	char	op;
	double	result, operand;

	if (ZBX_INFINITY == (result = evaluate_term4()))
		return ZBX_INFINITY;

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

		if (ZBX_INFINITY == (operand = evaluate_term4()))
			return ZBX_INFINITY;

		if ('=' == op)
			result = (SUCCEED == zbx_double_compare(result, operand));
		else
			result = (SUCCEED != zbx_double_compare(result, operand));
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "and"                                                    *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term2()
{
	double	result, operand;

	if (ZBX_INFINITY == (result = evaluate_term3()))
		return ZBX_INFINITY;

	while ('a' == ptr[0] && 'n' == ptr[1] && 'd' == ptr[2] && SUCCEED == is_operator_delimiter(ptr[3]))
	{
		ptr += 3;

		if (ZBX_INFINITY == (operand = evaluate_term3()))
			return ZBX_INFINITY;

		result = (SUCCEED != zbx_double_compare(result, 0) && SUCCEED != zbx_double_compare(operand, 0));
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate "or"                                                     *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_term1()
{
	double	result, operand;

	level++;

	if (32 < level)
	{
		zbx_strlcpy(buffer, "Cannot evaluate expression: nesting level is too deep.", max_buffer_len);
		return ZBX_INFINITY;
	}

	if (ZBX_INFINITY == (result = evaluate_term2()))
		return ZBX_INFINITY;

	while ('o' == ptr[0] && 'r' == ptr[1] && SUCCEED == is_operator_delimiter(ptr[2]))
	{
		ptr += 2;

		if (ZBX_INFINITY == (operand = evaluate_term2()))
			return ZBX_INFINITY;

		result = (SUCCEED != zbx_double_compare(result, 0) || SUCCEED != zbx_double_compare(operand, 0));
	}

	level--;

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate an expression like "(26.416>10) or (0=1)"                *
 *                                                                            *
 ******************************************************************************/
int	evaluate(double *value, const char *expression, char *error, int max_error_len)
{
	const char	*__function_name = "evaluate";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, expression);

	ptr = expression;
	level = 0;

	buffer = error;
	max_buffer_len = max_error_len;

	*value = evaluate_term1();

	if (ZBX_INFINITY != *value && '\0' != *ptr)
	{
		zbx_snprintf(error, max_error_len, "Cannot evaluate expression: unexpected token at \"%s\".", ptr);
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
