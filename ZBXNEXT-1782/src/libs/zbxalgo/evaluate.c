/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Function: evaluate_simple                                                  *
 *                                                                            *
 * Purpose: evaluate simple expression                                        *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result - value of the      *
 *                         expression                                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: format: <double> or <double> <operator> <double>                 *
 *                                                                            *
 *           It is recursive function!                                        *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_simple(double *result, char *expression, char *error, int maxerrlen)
{
	double	value1, value2;
	char	*p, c;

	zbx_lrtrim(expression, " ");

	/* compress repeating - and + and add prefix N to negative numbers */
	compress_signs(expression);

	/* we should process negative prefix, i.e. N123 == -123 */
	if ('N' == *expression && SUCCEED == is_double_suffix(expression + 1))
	{
		/* str2double supports suffixes */
		*result = -str2double(expression + 1);
		return SUCCEED;
	}
	else if ('N' != *expression && SUCCEED == is_double_suffix(expression))
	{
		/* str2double supports suffixes */
		*result = str2double(expression);
		return SUCCEED;
	}

	/* operators with lowest priority come first */
	/* HIGHEST / * - + < > # = & | LOWEST */
	if (NULL != (p = strchr(expression, '|')) || NULL != (p = strchr(expression, '&')) ||
			NULL != (p = strchr(expression, '=')) || NULL != (p = strchr(expression, '#')) ||
			NULL != (p = strchr(expression, '>')) || NULL != (p = strchr(expression, '<')) ||
			NULL != (p = strchr(expression, '+')) || NULL != (p = strrchr(expression, '-')) ||
			NULL != (p = strchr(expression, '*')) || NULL != (p = strrchr(expression, '/')))
	{
		c = *p;
		*p = '\0';

		if (SUCCEED != evaluate_simple(&value1, expression, error, maxerrlen) ||
				SUCCEED != evaluate_simple(&value2, p + 1, error, maxerrlen))
		{
			*p = c;
			return FAIL;
		}

		*p = c;
	}
	else
	{
		zbx_snprintf(error, maxerrlen, "Format error or unsupported operator. Exp: [%s]", expression);
		return FAIL;
	}

	switch (c)
	{
		case '|':
			*result = (SUCCEED != zbx_double_compare(value1, 0) || SUCCEED != zbx_double_compare(value2, 0));
			break;
		case '&':
			*result = (SUCCEED != zbx_double_compare(value1, 0) && SUCCEED != zbx_double_compare(value2, 0));
			break;
		case '=':
			*result = (SUCCEED == zbx_double_compare(value1, value2));
			break;
		case '#':
			*result = (SUCCEED != zbx_double_compare(value1, value2));
			break;
		case '>':
			*result = (value1 >= value2 + ZBX_DOUBLE_EPSILON);
			break;
		case '<':
			*result = (value1 <= value2 - ZBX_DOUBLE_EPSILON);
			break;
		case '+':
			*result = value1 + value2;
			break;
		case '-':
			*result = value1 - value2;
			break;
		case '*':
			*result = value1 * value2;
			break;
		case '/':
			if (SUCCEED == zbx_double_compare(value2, 0))
			{
				zbx_snprintf(error, maxerrlen, "Division by zero. Cannot evaluate expression [%s]", expression);
				return FAIL;
			}

			*result = value1 / value2;
			break;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate                                                         *
 *                                                                            *
 * Purpose: evaluate simplified expression                                    *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result - value of the      *
 *                         expression                                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: example: "(26.416>10) or (0=1)"                                  *
 *                                                                            *
 ******************************************************************************/
int	evaluate(double *value, const char *expression, char *error, int maxerrlen)
{
	const char	*__function_name = "evaluate";
	char		*res = NULL, simple[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
			value_str[MAX_STRING_LEN], c;
	int		i, l, r;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, expression);

	strscpy(tmp, expression);

	while (NULL != strchr(tmp, ')'))
	{
		l = -1;
		r = strchr(tmp, ')') - tmp;

		for (i = r; i >= 0; i--)
		{
			if ('(' == tmp[i])
			{
				l = i;
				break;
			}
		}

		if (-1 == l)
		{
			zbx_snprintf(error, maxerrlen, "Cannot find left bracket [(]. Expression:[%s]", tmp);
			return FAIL;
		}

		for (i = l + 1; i < r; i++)
			simple[i - l - 1] = tmp[i];

		simple[r - l - 1] = '\0';

		if (SUCCEED != evaluate_simple(value, simple, error, maxerrlen))
			return FAIL;

		c = tmp[l];
		tmp[l] = '\0';
		res = zbx_strdcat(res, tmp);
		tmp[l] = c;

		zbx_snprintf(value_str, sizeof(value_str), ZBX_FS_DBL, *value);
		res = zbx_strdcat(res, value_str);
		res = zbx_strdcat(res, tmp + r + 1);

		zbx_remove_spaces(res);
		strscpy(tmp, res);

		zbx_free(res);
	}

	if (SUCCEED != evaluate_simple(value, tmp, error, maxerrlen))
		return FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:" ZBX_FS_DBL, __function_name, *value);

	return SUCCEED;
}
