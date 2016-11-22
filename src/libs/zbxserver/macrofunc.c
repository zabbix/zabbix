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
static int	macrofunc_regsub(char **params, int nparam, char **out)
{
	char	*value = NULL;

	if (2 != nparam)
		return FAIL;

	if (FAIL == zbx_regexp_sub(*out, params[0], params[1], &value))
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
static int	macrofunc_iregsub(char **params, size_t nparam, char **out)
{
	char	*value = NULL;

	if (2 != nparam)
		return FAIL;

	if (FAIL == zbx_iregexp_sub(*out, params[0], params[1], &value))
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
	typedef enum
	{
		MACRO_FUNC_REGSUB,
		MACRO_FUNC_IREGSUB,
		MACRO_FUNC_UNKNOWN
	}
	zbx_macro_func_t;

	char			**params, *buf = NULL;
	const char		*ptr;
	int			ret;
	size_t			nparam = 0, param_alloc = 8, buf_alloc = 0, buf_offset = 0, par_l, par_r, sep_pos;
	zbx_macro_func_t	macro_func = MACRO_FUNC_UNKNOWN;

	if (SUCCEED != zbx_function_validate(expression, &par_l, &par_r) || len != par_r + 1)
		return FAIL;

	zbx_strncpy_alloc(&buf, &buf_alloc, &buf_offset, expression, par_l);

	if (0 == strcmp(buf, "regsub"))
		macro_func = MACRO_FUNC_REGSUB;
	else if (0 == strcmp(buf, "iregsub"))
		macro_func = MACRO_FUNC_IREGSUB;

	buf_offset = 0;
	zbx_strncpy_alloc(&buf, &buf_alloc, &buf_offset, expression + par_l + 1, par_r - par_l - 1);
	params = (char **)zbx_malloc(NULL, sizeof(char *) * param_alloc);

	for (ptr = buf; ptr - buf < buf_offset; ptr += sep_pos + 1)
	{
		size_t	param_pos, param_len;
		int	quoted;

		if (nparam == param_alloc)
		{
			param_alloc *= 2;
			params = (char **)zbx_realloc(params, sizeof(char *) * param_alloc);
		}

		zbx_function_param_parse(ptr, strlen(ptr), &param_pos, &param_len, &sep_pos);
		params[nparam++] = zbx_function_param_unquote_dyn(ptr + param_pos, param_len, &quoted);
	}

	switch (macro_func)
	{
		case MACRO_FUNC_REGSUB:
			ret = macrofunc_regsub(params, nparam, out);
			break;
		case MACRO_FUNC_IREGSUB:
			ret = macrofunc_iregsub(params, nparam, out);
			break;
		default:
			ret = FAIL;
	}

	while (0 < nparam--)
		zbx_free(params[nparam]);

	zbx_free(params);
	zbx_free(buf);

	return ret;
}

