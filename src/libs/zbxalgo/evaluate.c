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
#include "zbxalgo.h"
#include "zbxvariant.h"
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

static const char	*ptr;		/* character being looked at */
static int		level;		/* expression nesting level  */

static char		*buffer;	/* error message buffer      */
static size_t		max_buffer_len;	/* error message buffer size */

/******************************************************************************
 *                                                                            *
 * Purpose: check whether the character delimits a numeric token              *
 *                                                                            *
 ******************************************************************************/
static int	is_number_delimiter(char c)
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
 * Purpose: evaluate a quoted string like "/etc/passwd"                       *
 * Characters '\' and '"' are expected to be escaped or parsing fails         *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_string(zbx_variant_t *res)
{
	const char	*start;
	char		*res_temp = NULL, *dst;
	int		str_len = 0;

	for (start = ptr; '"' != *ptr; ptr++)
	{
		if ('\\' == *ptr)
		{
			ptr++;

			if ('\\' != *ptr && '\"' != *ptr && '\0' != *ptr)
			{
				zbx_snprintf(buffer, max_buffer_len, "Cannot evaluate expression:"
						" invalid escape sequence at \"%s\".", ptr - 1);
				zbx_variant_set_dbl(res, ZBX_INFINITY);
				return;
			}

		}

		if ('\0' == *ptr)
		{
			zbx_variant_set_dbl(res, ZBX_INFINITY);
			zbx_snprintf(buffer, max_buffer_len, "Cannot evaluate expression:"
					" unterminated string at \"%s\".", start);
			return;
		}
	}

	str_len = ptr - start;
	res_temp = zbx_malloc(NULL, str_len + 1);

	for (dst = res_temp; start != ptr; start++)
	{
		switch (*start)
		{
			case '\\':
				start++;
				break;
			case '\r':
				continue;
		}
		*dst++ = *start;
	}
	*dst = '\0';
	zbx_variant_set_str(res, res_temp);
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate a suffixed number like 12.345K                           *
 *                                                                            *
 ******************************************************************************/
static double	evaluate_number(int *unknown_idx)
{
	double		result;
	int		len;

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

	if (SUCCEED == zbx_suffixed_number_parse(ptr, &len) && SUCCEED == is_number_delimiter(*(ptr + len)))
	{
		result = atof(ptr) * suffix2factor(*(ptr + len - 1));
		ptr += len;
	}
	else
		result = ZBX_INFINITY;

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cast string variant to a double variant                           *
 *                                                                            *
 * Parameters: var - [IN/OUT] the variant to cast                             *
 *                                                                            *
 ******************************************************************************/
static void	variant_convert_to_double(zbx_variant_t *var)
{
	if (ZBX_VARIANT_STR == var->type)
	{
		double	var_double_value = evaluate_string_to_double(var->data.str);
		if (ZBX_INFINITY == var_double_value)
		{
			zbx_snprintf(buffer, max_buffer_len, "Cannot evaluate expression:"
					" value \"%s\" is not a numeric operand.", var->data.str);
		}
		zbx_variant_clear(var);
		zbx_variant_set_dbl(var, var_double_value);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get variant value in double (float64) format                      *
 *                                                                            *
 * Parameters: var - [IN] the input variant                                   *
 *                                                                            *
 * Return value: Depending on variant type:                                   *
 *    DBL - the variant value                                                 *
 *    STR - if variant value contains valid float64 string (with supported    *
 *          Zabbix suffixes) the converted value is returned. Otherwise       *
 *          ZBX_INFINITY is returned.                                         *
 *    other types - ZBX_INFINITY
 *                                                                            *
 ******************************************************************************/
static double	variant_get_double(const zbx_variant_t *var)
{
	switch (var->type)
	{
		case ZBX_VARIANT_DBL:
			return var->data.dbl;
		case ZBX_VARIANT_STR:
			return evaluate_string_to_double(var->data.str);
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return ZBX_INFINITY;
	}
}

static zbx_variant_t	evaluate_term1(int *unknown_idx);

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate a suffixed number or a parenthesized expression          *
 *                                                                            *
 ******************************************************************************/
static zbx_variant_t	evaluate_term9(int *unknown_idx)
{
	zbx_variant_t	res;

	while (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr)
		ptr++;

	if ('\0' == *ptr)
	{
		zbx_strlcpy(buffer, "Cannot evaluate expression: unexpected end of expression.", max_buffer_len);
		zbx_variant_set_dbl(&res, ZBX_INFINITY);
		return res;
	}

	if ('(' == *ptr)
	{
		ptr++;

		res = evaluate_term1(unknown_idx);

		if (ZBX_VARIANT_DBL == res.type && ZBX_INFINITY == res.data.dbl)
			return res;

		/* if evaluate_term1() returns ZBX_UNKNOWN then continue as with regular number */

		if (')' != *ptr)
		{
			zbx_snprintf(buffer, max_buffer_len, "Cannot evaluate expression:"
					" expected closing parenthesis at \"%s\".", ptr);

			zbx_variant_clear(&res);
			zbx_variant_set_dbl(&res, ZBX_INFINITY);

			return res;
		}

		ptr++;
	}
	else
	{
		if ('"' == *ptr)
		{
			ptr++;
			evaluate_string(&res);

			if (ZBX_VARIANT_DBL == res.type && ZBX_INFINITY == res.data.dbl)
				return res;

			ptr++;

			/* We do not really need to do this check. */
			/* The only reason we do it is to keep it consistent with */
			/* numeric tokens, where operators are not allowed after them: 123and. */
			/* Check below ensures that '"123"and' expression will fail as well. */
			if (FAIL == is_operator_delimiter(*ptr) && FAIL == is_number_delimiter(*ptr))
			{
				zbx_variant_clear(&res);
				zbx_variant_set_dbl(&res, ZBX_INFINITY);
				zbx_snprintf(buffer, max_buffer_len, "Cannot evaluate expression:"
						" unexpected token at \"%s\".", ptr);
			}

		}
		else
		{
			zbx_variant_set_dbl(&res, evaluate_number(unknown_idx));

			if (ZBX_INFINITY == res.data.dbl)
			{
				zbx_snprintf(buffer, max_buffer_len, "Cannot evaluate expression:"
						" expected numeric token at \"%s\".", ptr);
				return res;
			}
		}
	}

	while ('\0' != *ptr && (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr))
		ptr++;

	return res;
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
static zbx_variant_t	evaluate_term8(int *unknown_idx)
{
	while (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr)
		ptr++;

	if ('-' == *ptr)
	{
		zbx_variant_t	res;
		ptr++;
		res = evaluate_term9(unknown_idx);
		variant_convert_to_double(&res);

		if (ZBX_UNKNOWN == res.data.dbl || ZBX_INFINITY == res.data.dbl)
			return res;

		res.data.dbl = -res.data.dbl;
		return res;
	}
	else
		return evaluate_term9(unknown_idx);
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
static zbx_variant_t	evaluate_term7(int *unknown_idx)
{
	while (' ' == *ptr || '\r' == *ptr || '\n' == *ptr || '\t' == *ptr)
		ptr++;

	if ('n' == ptr[0] && 'o' == ptr[1] && 't' == ptr[2] && SUCCEED == is_operator_delimiter(ptr[3]))
	{
		zbx_variant_t	res;
		ptr += 3;
		res = evaluate_term8(unknown_idx);
		variant_convert_to_double(&res);

		if (ZBX_UNKNOWN == res.data.dbl || ZBX_INFINITY == res.data.dbl)
			return res;

		res.data.dbl = (SUCCEED == zbx_double_compare(res.data.dbl, 0.0) ? 1.0 : 0.0);
		return res;
	}
	else
		return evaluate_term8(unknown_idx);
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
static zbx_variant_t	evaluate_term6(int *unknown_idx)
{
	char		op;
	int		res_idx = -1, oper_idx = -2;	/* set invalid values to catch errors */
	zbx_variant_t	res;

	res = evaluate_term7(&res_idx);

	if (ZBX_VARIANT_DBL == res.type)
	{
		if (ZBX_INFINITY == res.data.dbl)
			return res;

		if (ZBX_UNKNOWN == res.data.dbl)
			*unknown_idx = res_idx;
	}

	/* if evaluate_term7() returns ZBX_UNKNOWN then continue as with regular number */

	while ('*' == *ptr || '/' == *ptr)
	{
		zbx_variant_t	operand;

		variant_convert_to_double(&res);

		if (ZBX_INFINITY == res.data.dbl)
			return res;

		op = *ptr++;

		/* 'ZBX_UNKNOWN' in multiplication and division produces 'ZBX_UNKNOWN'. */
		/* Even if 1st operand is Unknown we evaluate 2nd operand too to catch fatal errors in it. */

		operand = evaluate_term7(&oper_idx);
		variant_convert_to_double(&operand);

		if (ZBX_INFINITY == operand.data.dbl)
		{
			zbx_variant_clear(&res);
			zbx_variant_set_dbl(&res, ZBX_INFINITY);
			zbx_variant_clear(&operand);
			return res;
		}

		if ('*' == op)
		{
			if (ZBX_UNKNOWN == operand.data.dbl)		/* (anything) * Unknown */
			{
				*unknown_idx = oper_idx;
				res_idx = oper_idx;
				res.data.dbl = ZBX_UNKNOWN;
			}
			else if (ZBX_UNKNOWN == res.data.dbl)		/* Unknown * known */
				*unknown_idx = res_idx;
			else
				res.data.dbl *= operand.data.dbl;
		}
		else
		{
			/* catch division by 0 even if 1st operand is Unknown */

			if (ZBX_UNKNOWN != operand.data.dbl && SUCCEED == zbx_double_compare(operand.data.dbl, 0.0))
			{
				zbx_strlcpy(buffer, "Cannot evaluate expression: division by zero.", max_buffer_len);
				zbx_variant_clear(&res);
				zbx_variant_set_dbl(&res, ZBX_INFINITY);
				zbx_variant_clear(&operand);
				return res;
			}

			if (ZBX_UNKNOWN == operand.data.dbl)		/* (anything) / Unknown */
			{
				*unknown_idx = oper_idx;
				res_idx = oper_idx;
				res.data.dbl = ZBX_UNKNOWN;
			}
			else if (ZBX_UNKNOWN == res.data.dbl)		/* Unknown / known */
			{
				*unknown_idx = res_idx;
			}
			else
				res.data.dbl /= operand.data.dbl;
		}

		zbx_variant_clear(&operand);
	}
	return res;
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
static zbx_variant_t	evaluate_term5(int *unknown_idx)
{
	char		op;
	zbx_variant_t	res, operand;
	int		res_idx = -3, oper_idx = -4;	/* set invalid values to catch errors */

	res = evaluate_term6(&res_idx);

	if (ZBX_VARIANT_DBL == res.type)
	{
		if (ZBX_INFINITY == res.data.dbl)
			return res;

		if (ZBX_UNKNOWN == res.data.dbl)
			*unknown_idx = res_idx;
	}

	/* if evaluate_term6() returns ZBX_UNKNOWN then continue as with regular number */

	while ('+' == *ptr || '-' == *ptr)
	{
		variant_convert_to_double(&res);

		if (ZBX_INFINITY == res.data.dbl)
			return res;

		op = *ptr++;

		/* even if 1st operand is Unknown we evaluate 2nd operand to catch fatal error if any occurs */

		operand = evaluate_term6(&oper_idx);
		variant_convert_to_double(&operand);

		if (ZBX_INFINITY == operand.data.dbl)
		{
			zbx_variant_clear(&res);
			zbx_variant_set_dbl(&res, ZBX_INFINITY);
			zbx_variant_clear(&operand);
			return res;
		}

		if (ZBX_UNKNOWN == operand.data.dbl)		/* (anything) +/- Unknown */
		{
			*unknown_idx = oper_idx;
			res_idx = oper_idx;
			res.data.dbl = ZBX_UNKNOWN;
		}
		else if (ZBX_UNKNOWN == res.data.dbl)		/* Unknown +/- known */
		{
			*unknown_idx = res_idx;
		}
		else
		{
			if ('+' == op)
				res.data.dbl += operand.data.dbl;
			else
				res.data.dbl -= operand.data.dbl;
		}

		zbx_variant_clear(&operand);
	}

	return res;
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
static zbx_variant_t	evaluate_term4(int *unknown_idx)
{
	char		op;
	zbx_variant_t	res, operand;
	int		res_idx = -5, oper_idx = -6;	/* set invalid values to catch errors */

	res = evaluate_term5(&res_idx);

	if (ZBX_VARIANT_DBL == res.type)
	{
		if (ZBX_INFINITY == res.data.dbl)
			return res;

		if (ZBX_UNKNOWN == res.data.dbl)
			*unknown_idx = res_idx;
	}

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

		variant_convert_to_double(&res);

		if (ZBX_INFINITY == res.data.dbl)
			return res;

		/* even if 1st operand is Unknown we evaluate 2nd operand to catch fatal error if any occurs */

		operand = evaluate_term5(&oper_idx);

		variant_convert_to_double(&operand);

		if (ZBX_INFINITY == operand.data.dbl)
		{
			zbx_variant_clear(&res);
			zbx_variant_set_dbl(&res, ZBX_INFINITY);
			zbx_variant_clear(&operand);
			return res;
		}

		if (ZBX_UNKNOWN == operand.data.dbl)		/* (anything) < Unknown */
		{
			*unknown_idx = oper_idx;
			res_idx = oper_idx;
			res.data.dbl = ZBX_UNKNOWN;
		}
		else if (ZBX_UNKNOWN == res.data.dbl)		/* Unknown < known */
		{
			*unknown_idx = res_idx;
		}
		else
		{
			if ('<' == op)
				res.data.dbl = (res.data.dbl < operand.data.dbl - ZBX_DOUBLE_EPSILON);
			else if ('l' == op)
				res.data.dbl = (res.data.dbl <= operand.data.dbl + ZBX_DOUBLE_EPSILON);
			else if ('g' == op)
				res.data.dbl = (res.data.dbl >= operand.data.dbl - ZBX_DOUBLE_EPSILON);
			else
				res.data.dbl = (res.data.dbl > operand.data.dbl + ZBX_DOUBLE_EPSILON);
		}

		zbx_variant_clear(&operand);
	}

	return res;
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
static zbx_variant_t	evaluate_term3(int *unknown_idx)
{
	char		op;
	int		res_idx = -7, oper_idx = -8;	/* set invalid values to catch errors */
	zbx_variant_t	res, operand;
	double		left, right, value;

	res = evaluate_term4(&res_idx);

	if (ZBX_VARIANT_DBL == res.type)
	{
		if (ZBX_INFINITY == res.data.dbl)
			return res;

		if (ZBX_UNKNOWN == res.data.dbl)
			*unknown_idx = res_idx;
	}

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

		/* even if 1st operand is Unknown we evaluate 2nd operand to catch fatal error if any occurs */

		operand = evaluate_term4(&oper_idx);

		if (ZBX_VARIANT_DBL == operand.type && ZBX_INFINITY == operand.data.dbl)
		{
			zbx_variant_clear(&res);
			return operand;
		}

		if (ZBX_VARIANT_DBL == res.type && ZBX_UNKNOWN == res.data.dbl)
		{
			zbx_variant_clear(&operand);
			continue;
		}

		if (ZBX_VARIANT_DBL == operand.type && ZBX_UNKNOWN == operand.data.dbl)
		{
			zbx_variant_clear(&res);
			res = operand;
			*unknown_idx = oper_idx;
			continue;
		}

		left = variant_get_double(&res);
		right = variant_get_double(&operand);

		if (ZBX_INFINITY != left && ZBX_INFINITY != right)
		{
			/* both operands either are of double type or could be cast to it - */
			/* compare them as double values                                    */
			value = (SUCCEED == zbx_double_compare(left, right) ? 1 : 0);
		}
		else if (ZBX_VARIANT_DBL == res.type || ZBX_VARIANT_DBL == operand.type)
		{
			/* if one of operands has double type and the other */
			/* cannot be cast to double - they cannot be equal  */
			value = 0;
		}
		else
		{
			/* at this point both operands should be strings and should be */
			/* compared as such but check for their types just in case     */
			if (ZBX_VARIANT_STR != res.type || ZBX_VARIANT_STR != operand.type)
			{
				zbx_strlcpy(buffer, "Cannot evaluate expression: unsupported value type found.",
						max_buffer_len);
				value = ZBX_INFINITY;
				THIS_SHOULD_NEVER_HAPPEN;
			}
			else
				value = !strcmp(res.data.str, operand.data.str);
		}

		if ('#' == op)
			value = (SUCCEED == zbx_double_compare(value, 0.0) ? 1.0 : 0.0);

		zbx_variant_clear(&res);
		zbx_variant_clear(&operand);
		zbx_variant_set_dbl(&res, value);
	}

	return res;
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
static zbx_variant_t	evaluate_term2(int *unknown_idx)
{
	zbx_variant_t	res, operand;
	int		res_idx = -9, oper_idx = -10;	/* set invalid values to catch errors */

	res = evaluate_term3(&res_idx);

	if (ZBX_VARIANT_DBL == res.type)
	{
		if (ZBX_INFINITY == res.data.dbl)
			return res;

		if (ZBX_UNKNOWN == res.data.dbl)
			*unknown_idx = res_idx;
	}

	/* if evaluate_term3() returns ZBX_UNKNOWN then continue as with regular number */

	while ('a' == ptr[0] && 'n' == ptr[1] && 'd' == ptr[2] && SUCCEED == is_operator_delimiter(ptr[3]))
	{
		ptr += 3;
		variant_convert_to_double(&res);

		if (ZBX_INFINITY == res.data.dbl)
			return res;

		operand = evaluate_term3(&oper_idx);
		variant_convert_to_double(&operand);

		if (ZBX_INFINITY == operand.data.dbl)
		{
			zbx_variant_clear(&res);
			zbx_variant_set_dbl(&res, ZBX_INFINITY);
			zbx_variant_clear(&operand);
			return res;
		}

		if (ZBX_UNKNOWN == res.data.dbl)
		{
			if (ZBX_UNKNOWN == operand.data.dbl)				/* Unknown and Unknown */
			{
				*unknown_idx = oper_idx;
				res_idx = oper_idx;
				res.data.dbl = ZBX_UNKNOWN;
			}
			else if (SUCCEED == zbx_double_compare(operand.data.dbl, 0.0))	/* Unknown and 0 */
			{
				res.data.dbl = 0.0;
			}
			else							/* Unknown and 1 */
				*unknown_idx = res_idx;
		}
		else if (ZBX_UNKNOWN == operand.data.dbl)
		{
			if (SUCCEED == zbx_double_compare(res.data.dbl, 0.0))		/* 0 and Unknown */
			{
				res.data.dbl = 0.0;
			}
			else							/* 1 and Unknown */
			{
				*unknown_idx = oper_idx;
				res_idx = oper_idx;
				res.data.dbl = ZBX_UNKNOWN;
			}
		}
		else
		{
			res.data.dbl = (SUCCEED != zbx_double_compare(res.data.dbl, 0.0) &&
					SUCCEED != zbx_double_compare(operand.data.dbl, 0.0));
		}

		zbx_variant_clear(&operand);
	}

	return res;
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
static zbx_variant_t	evaluate_term1(int *unknown_idx)
{
	int		res_idx = -11, oper_idx = -12;	/* set invalid values to catch errors */
	zbx_variant_t	res, operand;

	level++;

	if (32 < level)
	{
		zbx_strlcpy(buffer, "Cannot evaluate expression: nesting level is too deep.", max_buffer_len);
		zbx_variant_set_dbl(&res, ZBX_INFINITY);
		return res;
	}

	res = evaluate_term2(&res_idx);

	if (ZBX_VARIANT_DBL == res.type)
	{
		if (ZBX_INFINITY == res.data.dbl)
			return res;

		if (ZBX_UNKNOWN == res.data.dbl)
			*unknown_idx = res_idx;
	}

	/* if evaluate_term2() returns ZBX_UNKNOWN then continue as with regular number */

	while ('o' == ptr[0] && 'r' == ptr[1] && SUCCEED == is_operator_delimiter(ptr[2]))
	{
		ptr += 2;
		variant_convert_to_double(&res);

		if (ZBX_INFINITY == res.data.dbl)
			return res;

		operand = evaluate_term2(&oper_idx);

		variant_convert_to_double(&operand);

		if (ZBX_INFINITY == operand.data.dbl)
		{
			zbx_variant_clear(&res);
			zbx_variant_set_dbl(&res, ZBX_INFINITY);
			zbx_variant_clear(&operand);
			return res;
		}

		if (ZBX_UNKNOWN == res.data.dbl)
		{
			if (ZBX_UNKNOWN == operand.data.dbl)				/* Unknown or Unknown */
			{
				*unknown_idx = oper_idx;
				res_idx = oper_idx;
				res.data.dbl = ZBX_UNKNOWN;
			}
			else if (SUCCEED != zbx_double_compare(operand.data.dbl, 0.0))	/* Unknown or 1 */
			{
				res.data.dbl = 1;
			}
			else							/* Unknown or 0 */
				*unknown_idx = res_idx;
		}
		else if (ZBX_UNKNOWN == operand.data.dbl)
		{
			if (SUCCEED != zbx_double_compare(res.data.dbl, 0.0))		/* 1 or Unknown */
			{
				res.data.dbl = 1;
			}
			else							/* 0 or Unknown */
			{
				*unknown_idx = oper_idx;
				res_idx = oper_idx;
				res.data.dbl = ZBX_UNKNOWN;
			}
		}
		else
		{
			res.data.dbl = (SUCCEED != zbx_double_compare(res.data.dbl, 0.0) ||
					SUCCEED != zbx_double_compare(operand.data.dbl, 0.0));
		}
		zbx_variant_clear(&operand);
	}

	level--;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate an expression like "(26.416>10) or (0=1)"                *
 *                                                                            *
 ******************************************************************************/
int	evaluate(double *value, const char *expression, char *error, size_t max_error_len,
		zbx_vector_ptr_t *unknown_msgs)
{
	int		unknown_idx = -13;	/* index of message in 'unknown_msgs' vector, set to invalid value */
						/* to catch errors */
	zbx_variant_t	res;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __func__, expression);

	ptr = expression;
	level = 0;
	buffer = error;
	max_buffer_len = max_error_len;
	res = evaluate_term1(&unknown_idx);

	if (ZBX_VARIANT_STR == res.type)
	{
		if (0 == strlen(res.data.str))
		{
			zbx_strlcpy(buffer, "Cannot evaluate expression: unexpected end of expression.",
					max_buffer_len);
			zbx_variant_clear(&res);
			zbx_variant_set_dbl(&res, ZBX_INFINITY);
		}
		else
		{
			variant_convert_to_double(&res);
		}
	}

	*value = res.data.dbl;
	zbx_variant_clear(&res);

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
			if (0 > unknown_idx)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				zabbix_log(LOG_LEVEL_WARNING, "%s() internal error: " ZBX_UNKNOWN_STR " index:%d"
						" expression:'%s'", __func__, unknown_idx, expression);
				zbx_snprintf(error, max_error_len, "Internal error: " ZBX_UNKNOWN_STR " index %d."
						" Please report this to Zabbix developers.", unknown_idx);
			}
			else if (unknown_msgs->values_num > unknown_idx)
			{
				zbx_snprintf(error, max_error_len, "Cannot evaluate expression: \"%s\".",
						(char *)(unknown_msgs->values[unknown_idx]));
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
					__func__);
		}

		*value = ZBX_INFINITY;
	}

	if (ZBX_INFINITY == *value)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() error:'%s'", __func__, error);
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:" ZBX_FS_DBL, __func__, *value);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate an expression like "(26.416>10) and not(0=ZBX_UNKNOWN0)" *
 *                                                                            *
 * Parameters: expression    - [IN]  expression to evaluate                   *
 *             value         - [OUT] expression evaluation result             *
 *             error         - [OUT] error message buffer                     *
 *             max_error_len - [IN]  error buffer size                        *
 *                                                                            *
 * Return value: SUCCEED - expression evaluated successfully,                 *
 *                         or evaluation result is undefined (ZBX_UNKNOWN)    *
 *               FAIL    - expression evaluation failed                       *
 *                                                                            *
 ******************************************************************************/
int	evaluate_unknown(const char *expression, double *value, char *error, size_t max_error_len)
{
	const char	*__function_name = "evaluate_with_unknown";
	zbx_variant_t	res;
	int		unknown_idx = -13;	/* index of message in 'unknown_msgs' vector, set to invalid value */
						/* to catch errors */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, expression);

	ptr = expression;
	level = 0;

	buffer = error;
	max_buffer_len = max_error_len;
	res = evaluate_term1(&unknown_idx);
	variant_convert_to_double(&res);
	*value = res.data.dbl;
	zbx_variant_clear(&res);

	if ('\0' != *ptr && ZBX_INFINITY != *value)
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

/******************************************************************************
 *                                                                            *
 * Purpose: cast string to a double, expand suffixes and parse negative sign  *
 *                                                                            *
 * Parameters: in - [IN] the input string                                     *
 * Return value:  -  the resulting double                                     *
 *                                                                            *
 ******************************************************************************/
double	evaluate_string_to_double(const char *in)
{
	int		len;
	double		result_double_value;
	const char	*tmp_ptr = in;

	if (1 < strlen(in) && '-' == in[0])
		tmp_ptr++;

	if (SUCCEED == zbx_suffixed_number_parse(tmp_ptr, &len) && '\0' == *(tmp_ptr + len))
	{
		result_double_value = atof(tmp_ptr) * suffix2factor(*(tmp_ptr + len - 1));

		/* negative sign detected */
		if (tmp_ptr != in)
			result_double_value = -(result_double_value);
	}
	else
	{
		result_double_value = ZBX_INFINITY;
	}

	return result_double_value;
}
