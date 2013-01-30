/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
	int		functionid;
	char		*host;
	char		*key;
	char		*func;
	char		*params;
	char		*value;
	unsigned char	found;
}
function_t;

typedef struct
{
	char		*exp;
	function_t	*functions;
	int		functions_alloc, functions_num;
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

static int	calcitem_add_function(expression_t *exp, char *func, char *params)
{
	function_t	*f;

	if (exp->functions_alloc == exp->functions_num)
	{
		exp->functions_alloc += 8;
		exp->functions = zbx_realloc(exp->functions,
				exp->functions_alloc * sizeof(function_t));
	}

	f = &exp->functions[exp->functions_num++];
	f->functionid = exp->functions_num;
	f->host = NULL;
	f->key = NULL;
	f->func = func;
	f->params = params;
	f->value = NULL;
	f->found = 0;

	return f->functionid;
}

static int	calcitem_parse_expression(DC_ITEM *dc_item, expression_t *exp,
		char *error, int max_error_len)
{
	const char	*__function_name = "calcitem_parse_expression";
	char		*e, *f, *func = NULL, *params = NULL;
	size_t		exp_alloc = 128, exp_offset = 0;
	int		functionid, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, dc_item->params);

	assert(dc_item);
	assert(exp);

	exp->exp = zbx_malloc(exp->exp, exp_alloc);

	for (e = dc_item->params; '\0' != *e; e++)
	{
		if (NULL != strchr(" \t\r\n", *e))
			continue;

		f = e;
		if (FAIL == parse_function(&e, &func, &params))
		{
			e = f;
			zbx_chrcpy_alloc(&exp->exp, &exp_alloc, &exp_offset, *f);

			continue;
		}
		else
			e--;

		functionid = calcitem_add_function(exp, func, params);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() functionid:%d function:'%s(%s)'",
				__function_name, functionid, func, params);

		func = NULL;
		params = NULL;

		zbx_snprintf_alloc(&exp->exp, &exp_alloc, &exp_offset, "{%d}", functionid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() expression:'%s'", __function_name, exp->exp);

	if (FAIL == (ret = substitute_simple_macros(NULL, NULL, &dc_item->host, NULL, NULL,
				&exp->exp, MACRO_TYPE_ITEM_EXPRESSION, error, max_error_len)))
		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	calcitem_evaluate_expression(DC_ITEM *dc_item, expression_t *exp,
		char *error, int max_error_len)
{
	const char	*__function_name = "calcitem_evaluate_expression";
	function_t	*f = NULL;
	char		*sql = NULL, *host_esc, *key_esc,
			*buf, replace[16];
	size_t		sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;
	int		i, ret = SUCCEED;
	time_t		now;
	DB_RESULT	db_result;
	DB_ROW		db_row;
	DB_ITEM		item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == exp->functions_num)
		return ret;

	for (i = 0; i < exp->functions_num; i++)
	{
		f = &exp->functions[i];

		buf = get_param_dyn(f->params, 1);	/* for first parameter result is not NULL */

		if (SUCCEED != parse_host_key(buf, &f->host, &f->key))
		{
			zbx_snprintf(error, max_error_len,
					"Invalid first parameter in function [%s(%s)]",
					f->func, f->params);
			ret = NOTSUPPORTED;
		}

		zbx_free(buf);

		if (SUCCEED != ret)
			break;

		if (NULL == f->host)
			f->host = strdup(dc_item->host.host);

		remove_param(f->params, 1);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() function:'%s:%s.%s(%s)'",
				__function_name, f->host, f->key, f->func, f->params);
	}

	if (SUCCEED != ret)
		return ret;

	now = time(NULL);
	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select %s"
			" where h.hostid=i.hostid"
				" and h.status=%d"
				" and i.status=%d"
				" and (",
			ZBX_SQL_ITEM_SELECT,
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE);

	for (i = 0; i < exp->functions_num; i++)
	{
		f = &exp->functions[i];

		if (i != 0)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " or ");

		host_esc = DBdyn_escape_string(f->host);
		key_esc = DBdyn_escape_string(f->key);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "(h.host='%s' and i.key_='%s')", host_esc, key_esc);

		zbx_free(key_esc);
		zbx_free(host_esc);
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ")" ZBX_SQL_NODE, DBand_node_local("h.hostid"));

	db_result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (db_row = DBfetch(db_result)))
	{
		DBget_item_from_db(&item, db_row);

		for (i = 0; i < exp->functions_num; i++)
		{
			f = &exp->functions[i];

			if (0 != strcmp(f->key, item.key))
				continue;

			if (0 != strcmp(f->host, item.host_name))
				continue;

			f->found = 1;
			f->value = zbx_malloc(f->value, MAX_BUFFER_LEN);

			if (SUCCEED != evaluate_function(f->value, &item, f->func, f->params, now))
			{
				zbx_snprintf(error, max_error_len, "Cannot evaluate function [%s(%s)]",
						f->func, f->params);

				ret = NOTSUPPORTED;
				break;
			}
			else
				f->value = zbx_realloc(f->value, strlen(f->value) + 1);
		}

		if (SUCCEED != ret)
			break;
	}
	DBfree_result(db_result);

	if (SUCCEED != ret)
		return ret;

	for (i = 0; i < exp->functions_num; i ++)
	{
		f = &exp->functions[i];

		if (0 == f->found)
		{
			zbx_snprintf(error, max_error_len,
				"Cannot evaluate function [%s(%s)]"
					": item [%s:%s] not found",
					f->func, f->params, f->host, f->key);
			ret = NOTSUPPORTED;
			break;
		}

		zbx_snprintf(replace, sizeof(replace), "{%d}", f->functionid);
		buf = string_replace(exp->exp, replace, f->value);
		zbx_free(exp->exp);
		exp->exp = buf;
	}

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

	if (SUCCEED != (ret = calcitem_evaluate_expression(dc_item, &exp, error, sizeof(error))))
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

	SET_DBL_RESULT(result, value);
clean:
	free_expression(&exp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
