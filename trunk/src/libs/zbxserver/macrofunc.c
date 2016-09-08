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
#include "zbxregexp.h"

/******************************************************************************
 *                                                                            *
 * Function: macrofunc_regsub                                                 *
 *                                                                            *
 * Purpose: calculates regular expression substitution                        *
 *                                                                            *
 * Parameters: func - [IN] the function data                                  *
 *             out  - [IN/OUT] the input/output value                         *
 *                                                                            *
 * Return value: SUCCEED - the function was calculated successfully.          *
 *               FAIL    - the function calculation failed.                   *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_regsub(zbx_function_t *func, char **out)
{
	char	*value = NULL;

	if (2 != func->nparam)
		return FAIL;

	if (FAIL == zbx_regexp_sub(*out, func->params[0], func->params[1], &value))
		return FAIL;

	if (NULL == value)
		value = zbx_strdup(NULL, "");

	zbx_free(*out);
	*out = value;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: macrofunc_iregsub                                                *
 *                                                                            *
 * Purpose: calculates case insensitive regular expression substitution       *
 *                                                                            *
 * Parameters: func - [IN] the function data                                  *
 *             out  - [IN/OUT] the input/output value                         *
 *                                                                            *
 * Return value: SUCCEED - the function was calculated successfully.          *
 *               FAIL    - the function calculation failed.                   *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_iregsub(zbx_function_t *func, char **out)
{
	char	*value = NULL;

	if (2 != func->nparam)
		return FAIL;

	if (FAIL == zbx_iregexp_sub(*out, func->params[0], func->params[1], &value))
		return FAIL;

	if (NULL == value)
		value = zbx_strdup(NULL, "");

	zbx_free(*out);
	*out = value;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_calculate_macro_function                                     *
 *                                                                            *
 * Purpose: calculates macro function value                                   *
 *                                                                            *
 * Parameters: expression - [IN] the macro function                           *
 *             len        - [IN] the macro function length                    *
 *             out        - [IN/OUT] the input/output value                   *
 *                                                                            *
 * Return value: SUCCEED - the function was calculated successfully.          *
 *               FAIL    - the function calculation failed.                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_calculate_macro_function(const char *expression, size_t len, char **out)
{
	zbx_function_t	func;
	int		ret;

	if (SUCCEED != zbx_function_parse(&func, expression, &len))
		return FAIL;

	if (0 == strcmp(func.name, "regsub"))
		ret = macrofunc_regsub(&func, out);
	else if (0 == strcmp(func.name, "iregsub"))
		ret = macrofunc_iregsub(&func, out);
	else
		ret = FAIL;

	zbx_function_clean(&func);

	return ret;
}
