/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
static int	macrofunc_regsub(char **params, size_t nparam, char **out)
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
 * Parameters: expression - [IN] expression containing macro function         *
 *             func_macro - [IN] information about macro function token       *
 *             out        - [IN/OUT] the input/output value                   *
 *                                                                            *
 * Return value: SUCCEED - the function was calculated successfully.          *
 *               FAIL    - the function calculation failed.                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_calculate_macro_function(const char *expression, const zbx_token_func_macro_t *func_macro, char **out)
{
	char			**params, *buf = NULL;
	const char		*ptr;
	size_t			nparam = 0, param_alloc = 8, buf_alloc = 0, buf_offset = 0, len, sep_pos;
	int			(*macrofunc)(char **params, size_t nparam, char **out), ret;

	ptr = expression + func_macro->func.l;
	len = func_macro->func_param.l - func_macro->func.l;

	if (ZBX_CONST_STRLEN("regsub") == len && 0 == strncmp(ptr, "regsub", len))
		macrofunc = macrofunc_regsub;
	else if (ZBX_CONST_STRLEN("iregsub") == len && 0 == strncmp(ptr, "iregsub", len))
		macrofunc = macrofunc_iregsub;
	else
		return FAIL;

	zbx_strncpy_alloc(&buf, &buf_alloc, &buf_offset, expression + func_macro->func_param.l + 1,
			func_macro->func_param.r - func_macro->func_param.l - 1);
	params = (char **)zbx_malloc(NULL, sizeof(char *) * param_alloc);

	for (ptr = buf; ptr < buf + buf_offset; ptr += sep_pos + 1)
	{
		size_t	param_pos, param_len;
		int	quoted;

		if (nparam == param_alloc)
		{
			param_alloc *= 2;
			params = (char **)zbx_realloc(params, sizeof(char *) * param_alloc);
		}

		zbx_function_param_parse(ptr, &param_pos, &param_len, &sep_pos);
		params[nparam++] = zbx_function_param_unquote_dyn(ptr + param_pos, param_len, &quoted);
	}

	ret = macrofunc(params, nparam, out);

	while (0 < nparam--)
		zbx_free(params[nparam]);

	zbx_free(params);
	zbx_free(buf);

	return ret;
}

