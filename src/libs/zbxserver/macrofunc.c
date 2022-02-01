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

#include "macrofunc.h"

#include "zbxregexp.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Purpose: calculates regular expression substitution                        *
 *                                                                            *
 * Parameters: params - [IN] the function parameters                          *
 *             nparam - [IN] the function parameter count                     *
 *             out    - [IN/OUT] the input/output value                       *
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
 * Purpose: calculates case insensitive regular expression substitution       *
 *                                                                            *
 * Parameters: params - [IN] the function parameters                          *
 *             nparam - [IN] the function parameter count                     *
 *             out    - [IN/OUT] the input/output value                       *
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
 * Purpose: time formatting macro function                                    *
 *                                                                            *
 * Parameters: params - [IN] the function parameters                          *
 *             nparam - [IN] the function parameter count                     *
 *             out    - [IN/OUT] the input/output value                       *
 *                                                                            *
 * Return value: SUCCEED - the function was calculated successfully.          *
 *               FAIL    - the function calculation failed.                   *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_fmttime(char **params, size_t nparam, char **out)
{
	struct tm	local_time;
	time_t		time_new;
	char		*buf = NULL;

	if (0 == nparam || 2 < nparam)
		return FAIL;

	time_new = time(&time_new);
	localtime_r(&time_new, &local_time);

	if (2 == nparam)
	{
		char	*p = params[1];
		size_t	len;

		while ('\0' != *p)
		{
			zbx_time_unit_t	unit;

			if ('/' == *p)
			{
				if (ZBX_TIME_UNIT_UNKNOWN == (unit = zbx_tm_str_to_unit(++p)))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "unexpected character starting with \"%s\"", p);
					return FAIL;
				}

				zbx_tm_round_down(&local_time, unit);

				p++;
			}
			else if ('+' == *p || '-' == *p)
			{
				int	num;
				char	op, *error = NULL;

				op = *(p++);

				if (FAIL == zbx_tm_parse_period(p, &len, &num, &unit, &error))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "failed to parse time period: %s", error);
					zbx_free(error);
					return FAIL;
				}

				if ('+' == op)
					zbx_tm_add(&local_time, num, unit);
				else
					zbx_tm_sub(&local_time, num, unit);

				p += len;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "unexpected character starting with \"%s\"", p);
				return FAIL;
			}
		}
	}

	buf = zbx_malloc(NULL, MAX_STRING_LEN);

	if (0 == strftime(buf, MAX_STRING_LEN, params[0], &local_time))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "invalid first parameter \"%s\"", params[0]);
		zbx_free(buf);
		return FAIL;
	}

	zbx_free(*out);
	*out = buf;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: number formatting macro function                                  *
 *                                                                            *
 * Parameters: params - [IN] the function data                                *
 *             nparam - [IN] parameter count                                  *
 *             out    - [IN/OUT] the input/output value                       *
 *                                                                            *
 * Return value: SUCCEED - the function was calculated successfully.          *
 *               FAIL    - the function calculation failed.                   *
 *                                                                            *
 ******************************************************************************/
static int	macrofunc_fmtnum(char **params, size_t nparam, char **out)
{
	double	value;
	int	precision;

	if (1 != nparam)
		return FAIL;

	if (SUCCEED == is_uint32(*out, &value))
		return SUCCEED;

	if (FAIL == is_double(*out, &value))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "macro \"%s\" is not a number", *out);
		return FAIL;
	}

	if (FAIL == is_uint32(params[0], &precision))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "invalid parameter \"%s\"", params[0]);
		return FAIL;
	}

	*out = zbx_dsprintf(*out, "%.*f", precision, value);

	return SUCCEED;
}

/******************************************************************************
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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ptr = expression + func_macro->func.l;
	len = func_macro->func_param.l - func_macro->func.l;

	if (ZBX_CONST_STRLEN("regsub") == len && 0 == strncmp(ptr, "regsub", len))
		macrofunc = macrofunc_regsub;
	else if (ZBX_CONST_STRLEN("iregsub") == len && 0 == strncmp(ptr, "iregsub", len))
		macrofunc = macrofunc_iregsub;
	else if (ZBX_CONST_STRLEN("fmttime") == len && 0 == strncmp(ptr, "fmttime", len))
		macrofunc = macrofunc_fmttime;
	else if (ZBX_CONST_STRLEN("fmtnum") == len && 0 == strncmp(ptr, "fmtnum", len))
		macrofunc = macrofunc_fmtnum;
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), ret: %s", __func__, zbx_result_string(ret));

	return ret;
}
