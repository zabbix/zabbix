/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "checks_calculated.h"
#include "zbxserver.h"
#include "log.h"

typedef struct
{
	int	functionid;
	char	*host;
	char	*key;
	char	*func;
	char	*params;
	char	*value;
}
function_t;

typedef struct
{
	char		*exp;
	function_t	*functions;
	int		functions_alloc;
	int		functions_num;
}
expression_t;

static void	free_expression(expression_t *exp)
{
	function_t	*f;
	int		i;

	for (i = 0; i < exp->functions_num; i++)
	{
		f = &exp->functions[i];
		zbx_free(f->host);
		zbx_free(f->key);
		zbx_free(f->func);
		zbx_free(f->params);
		zbx_free(f->value);
	}

	zbx_free(exp->exp);
	zbx_free(exp->functions);
	exp->functions_alloc = 0;
	exp->functions_num = 0;
}

static int	calcitem_add_function(expression_t *exp, char *host, char *key, char *func, char *params)
{
	function_t	*f;

	if (exp->functions_alloc == exp->functions_num)
	{
		exp->functions_alloc += 8;
		exp->functions = zbx_realloc(exp->functions, exp->functions_alloc * sizeof(function_t));
	}

	f = &exp->functions[exp->functions_num++];
	f->functionid = exp->functions_num;
	f->host = host;
	f->key = key;
	f->func = func;
	f->params = params;
	f->value = NULL;

	return f->functionid;
}

static int	calcitem_parse_expression(DC_ITEM *dc_item, expression_t *exp, char *error, int max_error_len)
{
	const char	*__function_name = "calcitem_parse_expression";

	char		*e, *buf = NULL;
	size_t		exp_alloc = 128, exp_offset = 0, f_pos, par_l, par_r;
	int		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, dc_item->params);

	exp->exp = zbx_malloc(exp->exp, exp_alloc);

	for (e = dc_item->params; SUCCEED == zbx_function_find(e, &f_pos, &par_l, &par_r); e += par_r + 1)
	{
		char	*func, *params, *host = NULL, *key = NULL;
		size_t	param_pos, param_len, sep_pos;
		int	functionid, quoted;

		/* copy the part of the string preceding function */
		zbx_strncpy_alloc(&exp->exp, &exp_alloc, &exp_offset, e, f_pos);

		/* extract the first function parameter and <host:>key reference from it */

		e[par_r] = '\0';
		zbx_function_param_parse(e + par_l + 1, &param_pos, &param_len, &sep_pos);
		e[par_r] = ')';

		zbx_free(buf);
		buf = zbx_function_param_unquote_dyn(e + par_l + 1 + param_pos, param_len, &quoted);

		if (SUCCEED != parse_host_key(buf, &host, &key))
		{
			zbx_snprintf(error, max_error_len, "Invalid first parameter in function [%.*s].",
					par_r - f_pos + 1, e + f_pos);
			goto out;
		}
		if (NULL == host)
			host = zbx_strdup(NULL, dc_item->host.host);

		/* extract function name and remaining parameters */

		e[par_l] = '\0';
		func = zbx_strdup(NULL, e + f_pos);
		e[par_l] = '(';

		if (')' != e[par_l + 1 + sep_pos]) /* first parameter is not the only one */
		{
			e[par_r] = '\0';
			params = zbx_strdup(NULL, e + par_l + 1 + sep_pos + 1);
			e[par_r] = ')';
		}
		else	/* the only parameter of the function was <host:>key reference */
			params = zbx_strdup(NULL, "");

		functionid = calcitem_add_function(exp, host, key, func, params);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() functionid:%d function:'%s:%s.%s(%s)'",
				__function_name, functionid, host, key, func, params);

		/* substitute function with id in curly brackets */
		zbx_snprintf_alloc(&exp->exp, &exp_alloc, &exp_offset, "{%d}", functionid);
	}

	/* copy the remaining part */
	zbx_strcpy_alloc(&exp->exp, &exp_alloc, &exp_offset, e);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() expression:'%s'", __function_name, exp->exp);

	if (SUCCEED == substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &dc_item->host, NULL, NULL, &exp->exp,
			MACRO_TYPE_ITEM_EXPRESSION, error, max_error_len))
	{
		ret = SUCCEED;
	}
out:
	zbx_free(buf);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	calcitem_evaluate_expression(expression_t *exp, char *error, int max_error_len)
{
	const char	*__function_name = "calcitem_evaluate_expression";
	function_t	*f = NULL;
	char		*buf, replace[16], *errstr = NULL;
	int		i, ret = SUCCEED;
	time_t		now;
	zbx_host_key_t	*keys = NULL;
	DC_ITEM		*items = NULL;
	int		*errcodes = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == exp->functions_num)
		return ret;

	keys = zbx_malloc(keys, sizeof(zbx_host_key_t) * exp->functions_num);
	items = zbx_malloc(items, sizeof(DC_ITEM) * exp->functions_num);
	errcodes = zbx_malloc(errcodes, sizeof(int) * exp->functions_num);

	for (i = 0; i < exp->functions_num; i++)
	{
		keys[i].host = exp->functions[i].host;
		keys[i].key = exp->functions[i].key;
	}

	DCconfig_get_items_by_keys(items, keys, errcodes, exp->functions_num);

	now = time(NULL);

	for (i = 0; i < exp->functions_num; i++)
	{
		f = &exp->functions[i];

		if (SUCCEED != errcodes[i])
		{
			zbx_snprintf(error, max_error_len,
					"Cannot evaluate function \"%s(%s)\":"
					" item \"%s:%s\" does not exist.",
					f->func, f->params, f->host, f->key);
			ret = NOTSUPPORTED;
			break;
		}

		if (ITEM_STATUS_ACTIVE != items[i].status)
		{
			zbx_snprintf(error, max_error_len,
					"Cannot evaluate function \"%s(%s)\":"
					" item \"%s:%s\" is disabled.",
					f->func, f->params, f->host, f->key);
			ret = NOTSUPPORTED;
			break;
		}

		if (HOST_STATUS_MONITORED != items[i].host.status)
		{
			zbx_snprintf(error, max_error_len,
					"Cannot evaluate function \"%s(%s)\":"
					" item \"%s:%s\" belongs to a disabled host.",
					f->func, f->params, f->host, f->key);
			ret = NOTSUPPORTED;
			break;
		}

		if (ITEM_STATE_NOTSUPPORTED == items[i].state)
		{
			zbx_snprintf(error, max_error_len,
					"Cannot evaluate function \"%s(%s)\": item \"%s:%s\" not supported.",
					f->func, f->params, f->host, f->key);
			ret = NOTSUPPORTED;
			break;
		}

		f->value = zbx_malloc(f->value, MAX_BUFFER_LEN);

		if (SUCCEED != evaluate_function(f->value, &items[i], f->func, f->params, now, &errstr))
		{
			if (NULL != errstr)
			{
				zbx_snprintf(error, max_error_len, "Cannot evaluate function \"%s(%s)\": %s.",
						f->func, f->params, errstr);
				zbx_free(errstr);
			}
			else
			{
				zbx_snprintf(error, max_error_len, "Cannot evaluate function \"%s(%s)\".",
						f->func, f->params);
			}

			ret = NOTSUPPORTED;
			break;
		}

		if (SUCCEED != is_double_suffix(f->value, ZBX_FLAG_DOUBLE_SUFFIX) || '-' == *f->value)
		{
			char	*wrapped;

			wrapped = zbx_dsprintf(NULL, "(%s)", f->value);

			zbx_free(f->value);
			f->value = wrapped;
		}
		else
			f->value = zbx_realloc(f->value, strlen(f->value) + 1);

		zbx_snprintf(replace, sizeof(replace), "{%d}", f->functionid);
		buf = string_replace(exp->exp, replace, f->value);
		zbx_free(exp->exp);
		exp->exp = buf;
	}

	DCconfig_clean_items(items, errcodes, exp->functions_num);

	zbx_free(errcodes);
	zbx_free(items);
	zbx_free(keys);

	return ret;
}

int	get_value_calculated(DC_ITEM *dc_item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_calculated";
	expression_t	exp;
	int		ret;
	char		error[MAX_STRING_LEN];
	double		value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' expression:'%s'", __function_name,
			dc_item->key_orig, dc_item->params);

	memset(&exp, 0, sizeof(exp));

	if (SUCCEED != (ret = calcitem_parse_expression(dc_item, &exp, error, sizeof(error))))
	{
		SET_MSG_RESULT(result, strdup(error));
		goto clean;
	}

	if (SUCCEED != (ret = calcitem_evaluate_expression(&exp, error, sizeof(error))))
	{
		SET_MSG_RESULT(result, strdup(error));
		goto clean;
	}

	if (SUCCEED != evaluate(&value, exp.exp, error, sizeof(error)))
	{
		SET_MSG_RESULT(result, strdup(error));
		ret = NOTSUPPORTED;
		goto clean;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() value:" ZBX_FS_DBL, __function_name, value);

	if (ITEM_VALUE_TYPE_UINT64 == dc_item->value_type && 0 > value)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Received value [" ZBX_FS_DBL "]"
				" is not suitable for value type [%s].",
				value, zbx_item_value_type_string(dc_item->value_type)));
		ret = NOTSUPPORTED;
		goto clean;
	}

	SET_DBL_RESULT(result, value);
clean:
	free_expression(&exp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
