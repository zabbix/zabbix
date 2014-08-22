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
#include "zbxserver.h"
#include "evalfunc.h"
#include "db.h"
#include "log.h"
#include "zbxalgo.h"
#include "valuecache.h"

/* The following definitions are used to identify the request field */
/* for various value getters grouped by their scope:                */

/* DBget_item_value(), DBget_interface_value() */
#define ZBX_REQUEST_HOST_IP		1
#define ZBX_REQUEST_HOST_DNS		2
#define ZBX_REQUEST_HOST_CONN		3
#define ZBX_REQUEST_HOST_PORT		4

/* DBget_item_value() */
#define ZBX_REQUEST_HOST_HOST		101
#define ZBX_REQUEST_HOST_NAME		102
#define ZBX_REQUEST_ITEM_ID		103
#define ZBX_REQUEST_ITEM_NAME		104
#define ZBX_REQUEST_ITEM_NAME_ORIG	105
#define ZBX_REQUEST_ITEM_KEY		106
#define ZBX_REQUEST_ITEM_KEY_ORIG	107
#define ZBX_REQUEST_ITEM_DESCRIPTION	108
#define ZBX_REQUEST_PROXY_NAME		109

/* DBget_history_log_value() */
#define ZBX_REQUEST_ITEM_LOG_DATE	201
#define ZBX_REQUEST_ITEM_LOG_TIME	202
#define ZBX_REQUEST_ITEM_LOG_AGE	203
#define ZBX_REQUEST_ITEM_LOG_SOURCE	204
#define ZBX_REQUEST_ITEM_LOG_SEVERITY	205
#define ZBX_REQUEST_ITEM_LOG_NSEVERITY	206
#define ZBX_REQUEST_ITEM_LOG_EVENTID	207

/* DBget_node_value() */
#define ZBX_REQUEST_NODE_ID		301
#define ZBX_REQUEST_NODE_NAME		302

/******************************************************************************
 *                                                                            *
 * Function: get_N_functionid                                                 *
 *                                                                            *
 * Parameters: expression   - [IN] null terminated trigger expression         *
 *                            '{11}=1 & {2346734}>5'                          *
 *             N_functionid - [IN] number of function in trigger expression   *
 *             functionid   - [OUT] ID of an N-th function in expression      *
 *             end          - [OUT] a pointer to text following the extracted *
 *                            function id (can be NULL)                       *
 *                                                                            *
 ******************************************************************************/
static int	get_N_functionid(const char *expression, int N_functionid, zbx_uint64_t *functionid, const char **end)
{
	const char			*__function_name = "get_N_functionid";

	enum state_t {NORMAL, ID}	state = NORMAL;
	int				num = 0, ret = FAIL;
	const char			*c, *p_functionid = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s' N_functionid:%d",
			__function_name, expression, N_functionid);

	for (c = expression; '\0' != *c; c++)
	{
		if ('{' == *c)
		{
			state = ID;
			p_functionid = c + 1;
		}
		else if ('}' == *c && ID == state && NULL != p_functionid)
		{
			if (SUCCEED == is_uint64_n(p_functionid, c - p_functionid, functionid))
			{
				if (++num == N_functionid)
				{
					zabbix_log(LOG_LEVEL_DEBUG, "%s() functionid:" ZBX_FS_UI64,
							__function_name, *functionid);

					if (NULL != end)
						*end = c + 1;

					ret = SUCCEED;
					break;
				}
			}

			state = NORMAL;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_functionids                                                  *
 *                                                                            *
 * Purpose: get identifiers of the functions used in expression               *
 *                                                                            *
 * Parameters: expression   - [IN] null terminated trigger expression         *
 *                            '{11}=1 & {2346734}>5'                          *
 *             count        - [IN] the maximum number of functions to parse   *
 *             functionids  - [OUT] the resulting vector of function ids      *
 *                                                                            *
 ******************************************************************************/
static void	get_functionids(zbx_vector_uint64_t *functionids, const char *expression)
{
	const char	*start = expression;
	zbx_uint64_t	functionid;

	while (SUCCEED == get_N_functionid(start, 1, &functionid, &start))
		zbx_vector_uint64_append(functionids, functionid);

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: get_N_itemid                                                     *
 *                                                                            *
 * Parameters: expression   - [IN] null terminated trigger expression         *
 *                            '{11}=1 & {2346734}>5'                          *
 *             N_functionid - [IN] number of function in trigger expression   *
 *             itemid       - [OUT] ID of an item of N-th function in         *
 *                            expression                                      *
 *                                                                            *
 ******************************************************************************/
static int	get_N_itemid(const char *expression, int N_functionid, zbx_uint64_t *itemid)
{
	const char	*__function_name = "get_N_itemid";

	zbx_uint64_t	functionid;
	DC_FUNCTION	function;
	int		errcode, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s' N_functionid:%d",
			__function_name, expression, N_functionid);

	if (SUCCEED == get_N_functionid(expression, N_functionid, &functionid, NULL))
	{
		DCconfig_get_functions_by_functionids(&function, &functionid, &errcode, 1);

		if (SUCCEED == errcode)
		{
			*itemid = function.itemid;
			ret = SUCCEED;
		}

		DCconfig_clean_functions(&function, &errcode, 1);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

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
			*result = (SUCCEED != cmp_double(value1, 0) || SUCCEED != cmp_double(value2, 0));
			break;
		case '&':
			*result = (SUCCEED != cmp_double(value1, 0) && SUCCEED != cmp_double(value2, 0));
			break;
		case '=':
			*result = (SUCCEED == cmp_double(value1, value2));
			break;
		case '#':
			*result = (SUCCEED != cmp_double(value1, value2));
			break;
		case '>':
			*result = (value1 >= value2 + TRIGGER_EPSILON);
			break;
		case '<':
			*result = (value1 <= value2 - TRIGGER_EPSILON);
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
			if (SUCCEED == cmp_double(value2, 0))
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
 * Comments: example: ({15}>10)|({123}=1)                                     *
 *                                                                            *
 ******************************************************************************/
int	evaluate(double *value, char *expression, char *error, int maxerrlen)
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

/******************************************************************************
 *                                                                            *
 * Function: extract_numbers                                                  *
 *                                                                            *
 * Purpose: Extract from string numbers with prefixes (A-Z)                   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *           Use zbx_free_numbers to free allocated memory                    *
 *                                                                            *
 ******************************************************************************/
static char 	**extract_numbers(const char *str, int *count)
{
	char		**result = NULL;
	const char	*s, *e;
	int		dot_found;
	size_t		len;

	assert(count);

	*count = 0;

	for (s = str; '\0' != *s; s++)	/* find start of number */
	{
		if (!isdigit(*s))
			continue;

		if (s != str && '{' == *(s - 1))
		{
			/* skip functions '{65432}' */
			s = strchr(s, '}');
			continue;
		}

		dot_found = 0;

		for (e = s; '\0' != *e; e++)	/* find end of number */
		{
			if (isdigit(*e))
				continue;

			if ('.' == *e && 0 == dot_found)
			{
				dot_found = 1;
				continue;
			}

			if ('A' <= *e && *e <= 'Z')
				e++;

			break;
		}

		/* number found */

		len = e - s;
		(*count)++;
		result = zbx_realloc(result, sizeof(char *) * (*count));
		result[(*count) - 1] = zbx_malloc(NULL, len + 1);
		memcpy(result[(*count) - 1], s, len);
		result[(*count) - 1][len] = '\0';

		if ('\0' == *(s = e))
			break;
	}

	return result;
}

static void	zbx_free_numbers(char ***numbers, int count)
{
	int	i;

	if (NULL == numbers || NULL == *numbers)
		return;

	for (i = 0; i < count; i++)
		zbx_free((*numbers)[i]);

	zbx_free(*numbers);
}

/******************************************************************************
 *                                                                            *
 * Function: expand_trigger_description_constants                             *
 *                                                                            *
 * Purpose: substitute simple macros in data string with real values          *
 *                                                                            *
 * Parameters: data - trigger description                                     *
 *                                                                            *
 ******************************************************************************/
static void	expand_trigger_description_constants(char **data, const char *expression)
{
	char	**numbers = NULL, *new_str = NULL, replace[3] = "$0";
	int	numbers_cnt = 0, i = 0;

	numbers = extract_numbers(expression, &numbers_cnt);

	for (i = 0; i < 9; i++)
	{
		replace[1] = '0' + i + 1;
		new_str = string_replace(*data, replace, i < numbers_cnt ?  numbers[i] : "");
		zbx_free(*data);
		*data = new_str;
	}

	zbx_free_numbers(&numbers, numbers_cnt);
}

static void	DCexpand_trigger_expression(char **expression)
{
	const char	*__function_name = "DCexpand_trigger_expression";

	char		*tmp = NULL;
	size_t		tmp_alloc = 256, tmp_offset = 0, l, r;
	DC_FUNCTION	function;
	DC_ITEM		item;
	zbx_uint64_t	functionid;
	int		errcode[2];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, *expression);

	tmp = zbx_malloc(tmp, tmp_alloc);

	for (l = 0; '\0' != (*expression)[l]; l++)
	{
		if ('{' != (*expression)[l])
		{
			zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, (*expression)[l]);
			continue;
		}

		for (r = l + 1; 0 != isdigit((*expression)[r]); r++)
			;

		if ('}' != (*expression)[r])
		{
			zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, (*expression)[l]);
			continue;
		}

		(*expression)[r] = '\0';

		if (SUCCEED == is_uint64(&(*expression)[l + 1], &functionid))
		{
			DCconfig_get_functions_by_functionids(&function, &functionid, &errcode[0], 1);

			if (SUCCEED == errcode[0])
			{
				DCconfig_get_items_by_itemids(&item, &function.itemid, &errcode[1], 1);

				if (SUCCEED == errcode[1])
				{
					zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, '{');
					zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, item.host.host);
					zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ':');
					zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, item.key_orig);
					zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, '.');
					zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, function.function);
					zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, '(');
					zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, function.parameter);
					zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ")}");
				}

				DCconfig_clean_items(&item, &errcode[1], 1);
			}

			DCconfig_clean_functions(&function, &errcode[0], 1);

			if (SUCCEED != errcode[0] || SUCCEED != errcode[1])
				zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "*ERROR*");

			l = r;
		}
		else
			zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, (*expression)[l]);

		(*expression)[r] = '}';
	}

	zbx_free(*expression);
	*expression = tmp;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:'%s'", __function_name, *expression);
}

/******************************************************************************
 *                                                                            *
 * Function: item_description                                                 *
 *                                                                            *
 * Purpose: substitute key parameters and user macros in                      *
 *          the item description string with real values                      *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	item_description(char **data, const char *key, zbx_uint64_t hostid)
{
	char	c, *p, *m, *n, *str_out = NULL, *replace_to = NULL, params[MAX_STRING_LEN], param[MAX_STRING_LEN];

	switch (parse_command(key, NULL, 0, params, sizeof(params)))
	{
		case ZBX_COMMAND_ERROR:
			return;
		case ZBX_COMMAND_WITHOUT_PARAMS:
			*params = '\0';
		case ZBX_COMMAND_WITH_PARAMS:
			/* do nothing */
			;
	}

	p = *data;

	while (NULL != (m = strchr(p, '$')))
	{
		if (m > p && '{' == *(m - 1) && NULL != (n = strchr(m + 1, '}')))	/* user defined macros */
		{
			c = *++n;
			*n = '\0';
			DCget_user_macro(&hostid, 1, m - 1, &replace_to);

			if (NULL != replace_to)
			{
				*(m - 1) = '\0';
				str_out = zbx_strdcat(str_out, p);
				*(m - 1) = '{';

				str_out = zbx_strdcat(str_out, replace_to);
				zbx_free(replace_to);
			}
			else
				str_out = zbx_strdcat(str_out, p);

			*n = c;
			p = n;
		}
		else if ('1' <= *(m + 1) && *(m + 1) <= '9' && '\0' != *params)		/* macros $1, $2, ... */
		{
			*m = '\0';
			str_out = zbx_strdcat(str_out, p);
			*m++ = '$';

			if (0 != get_param(params, *m - '0', param, sizeof(param)))
				*param = '\0';

			str_out = zbx_strdcat(str_out, param);
			p = m + 1;
		}
		else									/* just a dollar sign */
		{
			c = *++m;
			*m = '\0';
			str_out = zbx_strdcat(str_out, p);
			*m = c;
			p = m;
		}
	}

	if (NULL != str_out)
	{
		str_out = zbx_strdcat(str_out, p);
		zbx_free(*data);
		*data = str_out;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_host_name_by_hostid                                        *
 *                                                                            *
 * Purpose: request host name by hostid                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_host_name_by_hostid(zbx_uint64_t hostid, char **replace_to)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect(
			"select host"
			" from hosts"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_templateid_by_triggerid                                    *
 *                                                                            *
 * Purpose: get template trigger ID from which the trigger is inherited       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_templateid_by_triggerid(zbx_uint64_t triggerid, zbx_uint64_t *templateid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect(
			"select templateid"
			" from triggers"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(*templateid, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_template_name                                      *
 *                                                                            *
 * Purpose: get comma-space separated trigger template names in which         *
 *          the trigger is defined                                            *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Comments: based on the patch submitted by Hmami Mohamed                    *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_template_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to)
{
	const char	*__function_name = "DBget_trigger_template_name";

	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	zbx_uint64_t	templateid;
	char		*sql = NULL;
	size_t		replace_to_alloc = 64, replace_to_offset = 0,
			sql_alloc = 256, sql_offset = 0;
	int		user_type = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != userid)
	{
		result = DBselect("select type from users where userid=" ZBX_FS_UI64, *userid);

		if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
			user_type = atoi(row[0]);
		DBfree_result(result);

		if (-1 == user_type)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot check permissions", __function_name);
			goto out;
		}
	}

	/* use parent trigger ID for lld generated triggers */
	result = DBselect(
			"select parent_triggerid"
			" from trigger_discovery"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(triggerid, row[0]);
	DBfree_result(result);

	if (SUCCEED != DBget_templateid_by_triggerid(triggerid, &templateid) || 0 == templateid)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigger not found or not templated", __function_name);
		goto out;
	}

	do
	{
		triggerid = templateid;
	}
	while (SUCCEED == (ret = DBget_templateid_by_triggerid(triggerid, &templateid)) && 0 != templateid);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigger not found", __function_name);
		goto out;
	}

	*replace_to = zbx_realloc(*replace_to, replace_to_alloc);
	**replace_to = '\0';

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct h.name"
			" from hosts h,items i,functions f"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);
	if (NULL != userid && USER_TYPE_SUPER_ADMIN != user_type)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and exists("
					"select null"
					" from hosts_groups hg,rights r,users_groups ug"
					" where h.hostid=hg.hostid"
						" and hg.groupid=r.id"
						" and r.groupid=ug.usrgrpid"
						" and ug.userid=" ZBX_FS_UI64
					" group by hg.hostid"
					" having min(r.permission)>=%d"
				")",
				*userid, PERM_READ);
	}
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by h.name");

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 != replace_to_offset)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");
		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, row[0]);
	}
	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_hostgroup_name                                     *
 *                                                                            *
 * Purpose: get comma-space separated host group names in which the trigger   *
 *          is defined                                                        *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_hostgroup_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to)
{
	const char	*__function_name = "DBget_trigger_hostgroup_name";

	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	char		*sql = NULL;
	size_t		replace_to_alloc = 64, replace_to_offset = 0,
			sql_alloc = 256, sql_offset = 0;
	int		user_type = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != userid)
	{
		result = DBselect("select type from users where userid=" ZBX_FS_UI64, *userid);

		if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
			user_type = atoi(row[0]);
		DBfree_result(result);

		if (-1 == user_type)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot check permissions", __function_name);
			goto out;
		}
	}

	*replace_to = zbx_realloc(*replace_to, replace_to_alloc);
	**replace_to = '\0';

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.name"
			" from groups g,hosts_groups hg,items i,functions f"
			" where g.groupid=hg.groupid"
				" and hg.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);
	if (NULL != userid && USER_TYPE_SUPER_ADMIN != user_type)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and exists("
					"select null"
					" from rights r,users_groups ug"
					" where g.groupid=r.id"
						" and r.groupid=ug.usrgrpid"
						" and ug.userid=" ZBX_FS_UI64
					" group by r.id"
					" having min(r.permission)>=%d"
				")",
				*userid, PERM_READ);
	}
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by g.name");

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 != replace_to_offset)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");
		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_interface_value                                            *
 *                                                                            *
 * Purpose: request interface value by hostid                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_interface_value(zbx_uint64_t hostid, char **replace_to, int request, unsigned char agent_only)
{
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	type, useip, pr, last_pr = INTERFACE_TYPE_COUNT;
	char		sql[14];
	int		ret = FAIL;

	if (0 == agent_only)
	{
		zbx_snprintf(sql, sizeof(sql), " in (%d,%d,%d,%d)",
				INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX);
	}
	else
		zbx_snprintf(sql, sizeof(sql), "=%d", INTERFACE_TYPE_AGENT);

	result = DBselect(
			"select type,useip,ip,dns,port"
			" from interface"
			" where hostid=" ZBX_FS_UI64
				" and type%s"
				" and main=1",
			hostid, sql);

	while (NULL != (row = DBfetch(result)))
	{
		type = (unsigned char)atoi(row[0]);

		for (pr = 0; INTERFACE_TYPE_COUNT > pr && INTERFACE_TYPE_PRIORITY[pr] != type; pr++)
			;

		if (pr >= last_pr)
			continue;

		last_pr = pr;

		switch (request)
		{
			case ZBX_REQUEST_HOST_IP:
				*replace_to = zbx_strdup(*replace_to, row[2]);
				break;
			case ZBX_REQUEST_HOST_DNS:
				*replace_to = zbx_strdup(*replace_to, row[3]);
				break;
			case ZBX_REQUEST_HOST_CONN:
				useip = (unsigned char)atoi(row[1]);
				*replace_to = zbx_strdup(*replace_to, 1 == useip ? row[2] : row[3]);
				break;
			case ZBX_REQUEST_HOST_PORT:
				*replace_to = zbx_strdup(*replace_to, row[4]);
				break;
		}
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_item_value                                                 *
 *                                                                            *
 * Purpose: retrieve a particular value associated with the item              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_item_value(zbx_uint64_t itemid, char **replace_to, int request)
{
	const char	*__function_name = "DBget_item_value";
	DB_RESULT	result;
	DB_ROW		row;
	DC_ITEM		dc_item;
	char		*key = NULL, *addr = NULL;
	zbx_uint64_t	proxy_hostid, hostid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select h.hostid,h.proxy_hostid,h.host,h.name,i.itemid,i.name,i.key_,i.description"
				",ii.ip,ii.dns,ii.useip,ii.type,ii.main"
			" from items i"
				" join hosts h on h.hostid=i.hostid"
				" left join interface ii on ii.interfaceid=i.interfaceid"
			" where i.itemid=" ZBX_FS_UI64, itemid);

	if (NULL != (row = DBfetch(result)))
	{
		switch (request)
		{
			case ZBX_REQUEST_HOST_HOST:
				*replace_to = zbx_strdup(*replace_to, row[2]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_HOST_NAME:
				*replace_to = zbx_strdup(*replace_to, row[3]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_HOST_IP:
			case ZBX_REQUEST_HOST_DNS:
			case ZBX_REQUEST_HOST_CONN:
			case ZBX_REQUEST_HOST_PORT:
				ZBX_STR2UINT64(hostid, row[0]);
				ret = DBget_interface_value(hostid, replace_to, request, 0);
				break;
			case ZBX_REQUEST_ITEM_ID:
				*replace_to = zbx_strdup(*replace_to, row[4]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_NAME:
			case ZBX_REQUEST_ITEM_KEY:
				memset(&dc_item, 0, sizeof(dc_item));
				ZBX_STR2UINT64(dc_item.host.hostid, row[0]);
				strscpy(dc_item.host.host, row[2]);
				strscpy(dc_item.host.name, row[3]);

				if (SUCCEED != DBis_null(row[11]))	/* interface type */
				{
					dc_item.interface.type = (unsigned char)atoi(row[11]);
					dc_item.interface.addr = ('1' == *row[10] ? dc_item.interface.ip_orig :
							dc_item.interface.dns_orig);

					if ('1' != *row[12] || INTERFACE_TYPE_AGENT == dc_item.interface.type)
					{
						addr = zbx_strdup(addr, row[8]);	/* ip */
						substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &dc_item.host,
								NULL, &addr, MACRO_TYPE_INTERFACE_ADDR_DB, NULL,
								0);
						strscpy(dc_item.interface.ip_orig, addr);
						zbx_free(addr);

						addr = zbx_strdup(addr, row[9]);	/* dns */
						substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &dc_item.host,
								NULL, &addr, MACRO_TYPE_INTERFACE_ADDR_DB, NULL,
								0);
						strscpy(dc_item.interface.dns_orig, addr);
						zbx_free(addr);
					}
					else
					{
						strscpy(dc_item.interface.ip_orig, row[8]);
						strscpy(dc_item.interface.dns_orig, row[9]);
					}
				}
				else
					dc_item.interface.type = INTERFACE_TYPE_UNKNOWN;

				key = zbx_strdup(key, row[6]);
				substitute_key_macros(&key, NULL, &dc_item, NULL, MACRO_TYPE_ITEM_KEY, NULL, 0);

				if (ZBX_REQUEST_ITEM_NAME == request)
				{
					*replace_to = zbx_strdup(*replace_to, row[5]);
					item_description(replace_to, key, dc_item.host.hostid);
					zbx_free(key);
				}
				else	/* ZBX_REQUEST_ITEM_KEY */
				{
					zbx_free(*replace_to);
					*replace_to = key;
				}
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_NAME_ORIG:
				*replace_to = zbx_strdup(*replace_to, row[5]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_KEY_ORIG:
				*replace_to = zbx_strdup(*replace_to, row[6]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_DESCRIPTION:
				*replace_to = zbx_strdup(*replace_to, row[7]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_PROXY_NAME:
				ZBX_DBROW2UINT64(proxy_hostid, row[1]);

				if (0 == proxy_hostid)
				{
					*replace_to = zbx_strdup(*replace_to, "");
					ret = SUCCEED;
				}
				else
					ret = DBget_host_name_by_hostid(proxy_hostid, replace_to);
				break;
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_value                                              *
 *                                                                            *
 * Purpose: retrieve a particular value associated with the trigger's         *
 *          N_functionid'th function                                          *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_value(const char *expression, char **replace_to, int N_functionid, int request)
{
	const char	*__function_name = "DBget_trigger_value";

	zbx_uint64_t	itemid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED == get_N_itemid(expression, N_functionid, &itemid))
		ret = DBget_item_value(itemid, replace_to, request);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_event_count                                        *
 *                                                                            *
 * Purpose: retrieve number of events (acknowledged or unacknowledged) for a  *
 *          trigger (in an OK or PROBLEM state) which generated an event      *
 *                                                                            *
 * Parameters: triggerid - trigger identifier from database                   *
 *             replace_to - pointer to result buffer                          *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev, Aleksandrs Saveljevs                          *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_event_count(zbx_uint64_t triggerid, char **replace_to, int problem_only, int acknowledged)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		value[4];
	int		ret = FAIL;

	if (problem_only)
		zbx_snprintf(value, sizeof(value), "%d", TRIGGER_VALUE_PROBLEM);
	else
		zbx_snprintf(value, sizeof(value), "%d,%d", TRIGGER_VALUE_PROBLEM, TRIGGER_VALUE_OK);

	result = DBselect(
			"select count(*)"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
				" and value in (%s)"
				" and acknowledged=%d",
			EVENT_SOURCE_TRIGGERS,
			EVENT_OBJECT_TRIGGER,
			triggerid,
			value,
			acknowledged);

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_dhost_value_by_event                                       *
 *                                                                            *
 * Purpose: retrieve discovered host value by event and field name            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_dhost_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	char		sql[MAX_STRING_LEN];

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
			zbx_snprintf(sql, sizeof(sql),
					"select %s"
					" from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid"
						" and h.dhostid=s.dhostid"
						" and h.dhostid=" ZBX_FS_UI64
					" order by s.dserviceid",
					fieldname,
					event->objectid);
			break;
		case EVENT_OBJECT_DSERVICE:
			zbx_snprintf(sql, sizeof(sql),
					"select %s"
					" from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid"
						" and h.dhostid=s.dhostid"
						" and s.dserviceid=" ZBX_FS_UI64,
					fieldname,
					event->objectid);
			break;
		default:
			return ret;
	}

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_dservice_value_by_event                                    *
 *                                                                            *
 * Purpose: retrieve discovered service value by event and field name         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_dservice_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DSERVICE:
			result = DBselect("select %s from dservices s where s.dserviceid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		default:
			return ret;
	}

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}

	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_drule_value_by_event                                       *
 *                                                                            *
 * Purpose: retrieve discovery rule value by event and field name             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_drule_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	if (EVENT_SOURCE_DISCOVERY != event->source)
		return FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
			result = DBselect("select r.%s from drules r,dhosts h"
					" where r.druleid=r.druleid and h.dhostid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		case EVENT_OBJECT_DSERVICE:
			result = DBselect("select r.%s from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid and h.dhostid=s.dhostid and s.dserviceid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		default:
			return ret;
	}

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_history_log_value                                          *
 *                                                                            *
 * Purpose: retrieve a particular attribute of a log value                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_history_log_value(zbx_uint64_t itemid, char **replace_to, int request, int clock, int ns)
{
	const char		*__function_name = "DBget_history_log_value";

	DC_ITEM			item;
	int			ret = FAIL, found, errcode = FAIL;
	zbx_timespec_t		ts = {clock, ns};
	zbx_history_record_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED != errcode || ITEM_VALUE_TYPE_LOG != item.value_type)
		goto out;

	if (SUCCEED != zbx_vc_get_value(itemid, item.value_type, &ts, &value, &found) || 1 != found)
		goto out;

	switch (request)
	{
		case ZBX_REQUEST_ITEM_LOG_DATE:
			*replace_to = zbx_strdup(*replace_to, zbx_date2str((time_t)value.value.log->timestamp));
			goto success;
		case ZBX_REQUEST_ITEM_LOG_TIME:
			*replace_to = zbx_strdup(*replace_to, zbx_time2str((time_t)value.value.log->timestamp));
			goto success;
		case ZBX_REQUEST_ITEM_LOG_AGE:
			*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - value.value.log->timestamp));
			goto success;
	}

	/* the following attributes are set only for windows eventlog items */
	if (0 != strncmp(item.key_orig, "eventlog[", 9))
		goto clean;

	switch (request)
	{
		case ZBX_REQUEST_ITEM_LOG_SOURCE:
			*replace_to = zbx_strdup(*replace_to, (NULL == value.value.log->source ? "" :
					value.value.log->source));
			break;
		case ZBX_REQUEST_ITEM_LOG_SEVERITY:
			*replace_to = zbx_strdup(*replace_to,
					zbx_item_logtype_string((unsigned char)value.value.log->severity));
			break;
		case ZBX_REQUEST_ITEM_LOG_NSEVERITY:
			*replace_to = zbx_dsprintf(*replace_to, "%d", value.value.log->severity);
			break;
		case ZBX_REQUEST_ITEM_LOG_EVENTID:
			*replace_to = zbx_dsprintf(*replace_to, "%d", value.value.log->logeventid);
			break;
	}
success:
	ret = SUCCEED;
clean:
	zbx_history_record_clear(&value, ITEM_VALUE_TYPE_LOG);
out:
	DCconfig_clean_items(&item, &errcode, 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_history_log_value                                            *
 *                                                                            *
 * Purpose: retrieve a particular attribute of a log value                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_history_log_value(const char *expression, char **replace_to, int N_functionid,
		int request, int clock, int ns)
{
	const char	*__function_name = "get_history_log_value";

	zbx_uint64_t	itemid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED == get_N_itemid(expression, N_functionid, &itemid))
		ret = DBget_history_log_value(itemid, replace_to, request, clock, ns);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBitem_lastvalue                                                 *
 *                                                                            *
 * Purpose: retrieve item lastvalue by trigger expression                     *
 *          and number of function                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBitem_lastvalue(const char *expression, char **lastvalue, int N_functionid)
{
	const char	*__function_name = "DBitem_lastvalue";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == get_N_itemid(expression, N_functionid, &itemid))
		goto out;

	result = DBselect(
			"select value_type,valuemapid,units"
			" from items"
			" where itemid=" ZBX_FS_UI64,
			itemid);

	if (NULL != (row = DBfetch(result)))
	{
		unsigned char		value_type;
		zbx_uint64_t		valuemapid;
		zbx_history_record_t	vc_value;
		zbx_timespec_t		ts;
		int			found;

		ts.sec = time(NULL);
		ts.ns = 999999999;

		value_type = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(valuemapid, row[1]);

		if (SUCCEED == zbx_vc_get_value(itemid, value_type, &ts, &vc_value, &found) && 1 == found)
		{
			char	tmp[MAX_STRING_LEN];

			zbx_vc_history_value2str(tmp, sizeof(tmp), &vc_value.value, value_type);
			zbx_history_record_clear(&vc_value, value_type);
			zbx_format_value(tmp, sizeof(tmp), valuemapid, row[2], value_type);
			*lastvalue = zbx_strdup(*lastvalue, tmp);

			ret = SUCCEED;
		}
	}
	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBitem_value                                                     *
 *                                                                            *
 * Purpose: retrieve item value by trigger expression and number of function  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBitem_value(const char *expression, char **value, int N_functionid, int clock, int ns)
{
	const char	*__function_name = "DBitem_value";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;
	int		ret = FAIL, found;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == get_N_itemid(expression, N_functionid, &itemid))
		goto out;

	result = DBselect(
			"select value_type,valuemapid,units"
			" from items"
			" where itemid=" ZBX_FS_UI64,
			itemid);

	if (NULL != (row = DBfetch(result)))
	{
		unsigned char		value_type;
		zbx_uint64_t		valuemapid;
		zbx_timespec_t		ts = {clock, ns};
		zbx_history_record_t	vc_value;

		value_type = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(valuemapid, row[1]);

		if (SUCCEED == zbx_vc_get_value(itemid, value_type, &ts, &vc_value, &found) && 1 == found)
		{
			char	tmp[MAX_BUFFER_LEN];

			zbx_vc_history_value2str(tmp, sizeof(tmp), &vc_value.value, value_type);
			zbx_history_record_clear(&vc_value, value_type);
			zbx_format_value(tmp, sizeof(tmp), valuemapid, row[2], value_type);
			*value = zbx_strdup(*value, tmp);

			ret = SUCCEED;
		}
	}
	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_escalation_history                                           *
 *                                                                            *
 * Purpose: retrieve escalation history                                       *
 *                                                                            *
 ******************************************************************************/
static void	get_escalation_history(const DB_EVENT *event, const DB_EVENT *r_event, char **replace_to)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*buf = NULL, *p;
	size_t		buf_alloc = ZBX_KIBIBYTE, buf_offset = 0;
	int		esc_step;
	unsigned char	type, status;
	time_t		now;
	zbx_uint64_t	userid;

	buf = zbx_malloc(buf, buf_alloc);

	zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Problem started: %s %s Age: %s\n",
			zbx_date2str(event->clock), zbx_time2str(event->clock),
			zbx_age2str(time(NULL) - event->clock));

	result = DBselect("select a.clock,a.alerttype,a.status,mt.description,a.sendto"
				",a.error,a.esc_step,a.userid,a.message"
			" from alerts a"
			" left join media_type mt"
				" on mt.mediatypeid=a.mediatypeid"
			" where a.eventid=" ZBX_FS_UI64
			" order by a.clock",
			event->eventid);

	while (NULL != (row = DBfetch(result)))
	{
		now = atoi(row[0]);
		type = (unsigned char)atoi(row[1]);
		status = (unsigned char)atoi(row[2]);
		esc_step = atoi(row[6]);
		ZBX_DBROW2UINT64(userid, row[7]);

		if (0 != esc_step)
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%d. ", esc_step);

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%s %s %-7s %-11s",
				zbx_date2str(now), zbx_time2str(now),	/* date, time */
				zbx_alert_type_string(type),		/* alert type */
				zbx_alert_status_string(type, status));	/* alert status */

		if (ALERT_TYPE_COMMAND == type)
		{
			if (NULL != (p = strchr(row[8], ':')))
			{
				*p = '\0';
				zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " \"%s\"", row[8]);	/* host */
				*p = ':';
			}
		}
		else
		{
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s %s \"%s\"",
					SUCCEED == DBis_null(row[3]) ? "" : row[3],	/* media type description */
					row[4],						/* send to */
					zbx_user_string(userid));			/* alert user */
		}

		if (ALERT_STATUS_FAILED == status)
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s", row[5]);	/* alert error */

		zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, '\n');
	}
	DBfree_result(result);

	if (NULL != r_event)
	{
		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Problem ended: %s %s\n",
				zbx_date2str(r_event->clock), zbx_time2str(r_event->clock));
	}

	if (0 != buf_offset)
		buf[--buf_offset] = '\0';

	*replace_to = buf;
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_ack_history                                            *
 *                                                                            *
 * Purpose: retrieve event acknowledges history                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	get_event_ack_history(const DB_EVENT *event, char **replace_to)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*buf = NULL;
	size_t		buf_alloc = ZBX_KIBIBYTE, buf_offset = 0;
	time_t		now;
	zbx_uint64_t	userid;

	if (0 == event->acknowledged)
	{
		*replace_to = zbx_strdup(*replace_to, "");
		return;
	}

	buf = zbx_malloc(buf, buf_alloc);
	*buf = '\0';

	result = DBselect("select clock,userid,message"
			" from acknowledges"
			" where eventid=" ZBX_FS_UI64 " order by clock",
			event->eventid);

	while (NULL != (row = DBfetch(result)))
	{
		now = atoi(row[0]);
		ZBX_STR2UINT64(userid, row[1]);

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
				"%s %s \"%s\"\n%s\n\n",
				zbx_date2str(now),
				zbx_time2str(now),
				zbx_user_string(userid),
				row[2]);
	}

	DBfree_result(result);

	if (0 != buf_offset)
	{
		buf_offset -= 2;
		buf[buf_offset] = '\0';
	}

	*replace_to = buf;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_node_value                                                 *
 *                                                                            *
 * Purpose: request node value by object identificator                        *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED, otherwise FAIL    *
 *                                                                            *
 ******************************************************************************/
static int	DBget_node_value(zbx_uint64_t objectid, char **replace_to, int request)
{
	const char	*__function_name = "DBget_node_value";

	DB_RESULT	result;
	DB_ROW		row;
	int		nodeid, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	nodeid = get_nodeid_by_id(objectid);

	switch (request)
	{
		case ZBX_REQUEST_NODE_ID:
			*replace_to = zbx_dsprintf(*replace_to, "%d", nodeid);
			ret = SUCCEED;
			break;
		case ZBX_REQUEST_NODE_NAME:
			result = DBselect("select name from nodes where nodeid=%d", nodeid);

			if (NULL != (row = DBfetch(result)))
			{
				*replace_to = zbx_strdup(*replace_to, row[0]);
				ret = SUCCEED;
			}
			DBfree_result(result);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_node_value                                                   *
 *                                                                            *
 * Purpose: request node value by trigger expression and number of function   *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED, otherwise FAIL    *
 *                                                                            *
 ******************************************************************************/
static int	get_node_value(const char *expression, char **replace_to, int N_functionid, int request)
{
	zbx_uint64_t	functionid;

	if (FAIL == get_N_functionid(expression, N_functionid, &functionid, NULL))
		return FAIL;

	return DBget_node_value(functionid, replace_to, request);
}

/******************************************************************************
 *                                                                            *
 * Function: get_autoreg_value_by_event                                       *
 *                                                                            *
 * Purpose: request value from autoreg_host table by event                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_autoreg_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect(
			"select %s"
			" from autoreg_host"
			" where autoreg_hostid=" ZBX_FS_UI64, fieldname, event->objectid);

	if (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED == DBis_null(row[0]))
		{
			zbx_free(*replace_to);
		}
		else
			*replace_to = zbx_strdup(*replace_to, row[0]);

		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

#define MVAR_ACTION			"{ACTION."			/* a prefix for all action macros */
#define MVAR_ACTION_ID			MVAR_ACTION "ID}"
#define MVAR_ACTION_NAME		MVAR_ACTION "NAME}"
#define MVAR_DATE			"{DATE}"
#define MVAR_EVENT			"{EVENT."			/* a prefix for all event macros */
#define MVAR_EVENT_ACK_HISTORY		MVAR_EVENT "ACK.HISTORY}"
#define MVAR_EVENT_ACK_STATUS		MVAR_EVENT "ACK.STATUS}"
#define MVAR_EVENT_AGE			MVAR_EVENT "AGE}"
#define MVAR_EVENT_DATE			MVAR_EVENT "DATE}"
#define MVAR_EVENT_ID			MVAR_EVENT "ID}"
#define MVAR_EVENT_STATUS		MVAR_EVENT "STATUS}"
#define MVAR_EVENT_TIME			MVAR_EVENT "TIME}"
#define MVAR_EVENT_VALUE		MVAR_EVENT "VALUE}"
#define MVAR_EVENT_RECOVERY		MVAR_EVENT "RECOVERY."		/* a prefix for all recovery event macros */
#define MVAR_EVENT_RECOVERY_DATE	MVAR_EVENT_RECOVERY "DATE}"
#define MVAR_EVENT_RECOVERY_ID		MVAR_EVENT_RECOVERY "ID}"
#define MVAR_EVENT_RECOVERY_STATUS	MVAR_EVENT_RECOVERY "STATUS}"
#define MVAR_EVENT_RECOVERY_TIME	MVAR_EVENT_RECOVERY "TIME}"
#define MVAR_EVENT_RECOVERY_VALUE	MVAR_EVENT_RECOVERY "VALUE}"
#define MVAR_ESC_HISTORY		"{ESC.HISTORY}"
#define MVAR_PROXY_NAME			"{PROXY.NAME}"
#define MVAR_HOST_DNS			"{HOST.DNS}"
#define MVAR_HOST_CONN			"{HOST.CONN}"
#define MVAR_HOST_HOST			"{HOST.HOST}"
#define MVAR_HOST_IP			"{HOST.IP}"
#define MVAR_IPADDRESS			"{IPADDRESS}"			/* deprecated */
#define MVAR_HOST_METADATA		"{HOST.METADATA}"
#define MVAR_HOST_NAME			"{HOST.NAME}"
#define MVAR_HOSTNAME			"{HOSTNAME}"			/* deprecated */
#define MVAR_HOST_PORT			"{HOST.PORT}"
#define MVAR_TIME			"{TIME}"
#define MVAR_ITEM_LASTVALUE		"{ITEM.LASTVALUE}"
#define MVAR_ITEM_VALUE			"{ITEM.VALUE}"
#define MVAR_ITEM_ID			"{ITEM.ID}"
#define MVAR_ITEM_NAME			"{ITEM.NAME}"
#define MVAR_ITEM_NAME_ORIG		"{ITEM.NAME.ORIG}"
#define MVAR_ITEM_KEY			"{ITEM.KEY}"
#define MVAR_ITEM_KEY_ORIG		"{ITEM.KEY.ORIG}"
#define MVAR_ITEM_STATE			"{ITEM.STATE}"
#define MVAR_TRIGGER_KEY		"{TRIGGER.KEY}"			/* deprecated */
#define MVAR_ITEM_DESCRIPTION		"{ITEM.DESCRIPTION}"
#define MVAR_ITEM_LOG_DATE		"{ITEM.LOG.DATE}"
#define MVAR_ITEM_LOG_TIME		"{ITEM.LOG.TIME}"
#define MVAR_ITEM_LOG_AGE		"{ITEM.LOG.AGE}"
#define MVAR_ITEM_LOG_SOURCE		"{ITEM.LOG.SOURCE}"
#define MVAR_ITEM_LOG_SEVERITY		"{ITEM.LOG.SEVERITY}"
#define MVAR_ITEM_LOG_NSEVERITY		"{ITEM.LOG.NSEVERITY}"
#define MVAR_ITEM_LOG_EVENTID		"{ITEM.LOG.EVENTID}"
#define MVAR_TRIGGER_DESCRIPTION	"{TRIGGER.DESCRIPTION}"
#define MVAR_TRIGGER_COMMENT		"{TRIGGER.COMMENT}"		/* deprecated */
#define MVAR_TRIGGER_ID			"{TRIGGER.ID}"
#define MVAR_TRIGGER_NAME		"{TRIGGER.NAME}"
#define MVAR_TRIGGER_NAME_ORIG		"{TRIGGER.NAME.ORIG}"
#define MVAR_TRIGGER_EXPRESSION		"{TRIGGER.EXPRESSION}"
#define MVAR_TRIGGER_SEVERITY		"{TRIGGER.SEVERITY}"
#define MVAR_TRIGGER_NSEVERITY		"{TRIGGER.NSEVERITY}"
#define MVAR_TRIGGER_STATUS		"{TRIGGER.STATUS}"
#define MVAR_TRIGGER_STATE		"{TRIGGER.STATE}"
#define MVAR_TRIGGER_TEMPLATE_NAME	"{TRIGGER.TEMPLATE.NAME}"
#define MVAR_TRIGGER_HOSTGROUP_NAME	"{TRIGGER.HOSTGROUP.NAME}"
#define MVAR_STATUS			"{STATUS}"			/* deprecated */
#define MVAR_TRIGGER_VALUE		"{TRIGGER.VALUE}"
#define MVAR_TRIGGER_URL		"{TRIGGER.URL}"

#define MVAR_TRIGGER_EVENTS_ACK			"{TRIGGER.EVENTS.ACK}"
#define MVAR_TRIGGER_EVENTS_UNACK		"{TRIGGER.EVENTS.UNACK}"
#define MVAR_TRIGGER_EVENTS_PROBLEM_ACK		"{TRIGGER.EVENTS.PROBLEM.ACK}"
#define MVAR_TRIGGER_EVENTS_PROBLEM_UNACK	"{TRIGGER.EVENTS.PROBLEM.UNACK}"

#define MVAR_LLDRULE_DESCRIPTION		"{LLDRULE.DESCRIPTION}"
#define MVAR_LLDRULE_ID				"{LLDRULE.ID}"
#define MVAR_LLDRULE_KEY			"{LLDRULE.KEY}"
#define MVAR_LLDRULE_KEY_ORIG			"{LLDRULE.KEY.ORIG}"
#define MVAR_LLDRULE_NAME			"{LLDRULE.NAME}"
#define MVAR_LLDRULE_NAME_ORIG			"{LLDRULE.NAME.ORIG}"
#define MVAR_LLDRULE_STATE			"{LLDRULE.STATE}"

#define MVAR_INVENTORY				"{INVENTORY."			/* a prefix for all inventory macros */
#define MVAR_INVENTORY_TYPE			MVAR_INVENTORY "TYPE}"
#define MVAR_INVENTORY_TYPE_FULL		MVAR_INVENTORY "TYPE.FULL}"
#define MVAR_INVENTORY_NAME			MVAR_INVENTORY "NAME}"
#define MVAR_INVENTORY_ALIAS			MVAR_INVENTORY "ALIAS}"
#define MVAR_INVENTORY_OS			MVAR_INVENTORY "OS}"
#define MVAR_INVENTORY_OS_FULL			MVAR_INVENTORY "OS.FULL}"
#define MVAR_INVENTORY_OS_SHORT			MVAR_INVENTORY "OS.SHORT}"
#define MVAR_INVENTORY_SERIALNO_A		MVAR_INVENTORY "SERIALNO.A}"
#define MVAR_INVENTORY_SERIALNO_B		MVAR_INVENTORY "SERIALNO.B}"
#define MVAR_INVENTORY_TAG			MVAR_INVENTORY "TAG}"
#define MVAR_INVENTORY_ASSET_TAG		MVAR_INVENTORY "ASSET.TAG}"
#define MVAR_INVENTORY_MACADDRESS_A		MVAR_INVENTORY "MACADDRESS.A}"
#define MVAR_INVENTORY_MACADDRESS_B		MVAR_INVENTORY "MACADDRESS.B}"
#define MVAR_INVENTORY_HARDWARE			MVAR_INVENTORY "HARDWARE}"
#define MVAR_INVENTORY_HARDWARE_FULL		MVAR_INVENTORY "HARDWARE.FULL}"
#define MVAR_INVENTORY_SOFTWARE			MVAR_INVENTORY "SOFTWARE}"
#define MVAR_INVENTORY_SOFTWARE_FULL		MVAR_INVENTORY "SOFTWARE.FULL}"
#define MVAR_INVENTORY_SOFTWARE_APP_A		MVAR_INVENTORY "SOFTWARE.APP.A}"
#define MVAR_INVENTORY_SOFTWARE_APP_B		MVAR_INVENTORY "SOFTWARE.APP.B}"
#define MVAR_INVENTORY_SOFTWARE_APP_C		MVAR_INVENTORY "SOFTWARE.APP.C}"
#define MVAR_INVENTORY_SOFTWARE_APP_D		MVAR_INVENTORY "SOFTWARE.APP.D}"
#define MVAR_INVENTORY_SOFTWARE_APP_E		MVAR_INVENTORY "SOFTWARE.APP.E}"
#define MVAR_INVENTORY_CONTACT			MVAR_INVENTORY "CONTACT}"
#define MVAR_INVENTORY_LOCATION			MVAR_INVENTORY "LOCATION}"
#define MVAR_INVENTORY_LOCATION_LAT		MVAR_INVENTORY "LOCATION.LAT}"
#define MVAR_INVENTORY_LOCATION_LON		MVAR_INVENTORY "LOCATION.LON}"
#define MVAR_INVENTORY_NOTES			MVAR_INVENTORY "NOTES}"
#define MVAR_INVENTORY_CHASSIS			MVAR_INVENTORY "CHASSIS}"
#define MVAR_INVENTORY_MODEL			MVAR_INVENTORY "MODEL}"
#define MVAR_INVENTORY_HW_ARCH			MVAR_INVENTORY "HW.ARCH}"
#define MVAR_INVENTORY_VENDOR			MVAR_INVENTORY "VENDOR}"
#define MVAR_INVENTORY_CONTRACT_NUMBER		MVAR_INVENTORY "CONTRACT.NUMBER}"
#define MVAR_INVENTORY_INSTALLER_NAME		MVAR_INVENTORY "INSTALLER.NAME}"
#define MVAR_INVENTORY_DEPLOYMENT_STATUS	MVAR_INVENTORY "DEPLOYMENT.STATUS}"
#define MVAR_INVENTORY_URL_A			MVAR_INVENTORY "URL.A}"
#define MVAR_INVENTORY_URL_B			MVAR_INVENTORY "URL.B}"
#define MVAR_INVENTORY_URL_C			MVAR_INVENTORY "URL.C}"
#define MVAR_INVENTORY_HOST_NETWORKS		MVAR_INVENTORY "HOST.NETWORKS}"
#define MVAR_INVENTORY_HOST_NETMASK		MVAR_INVENTORY "HOST.NETMASK}"
#define MVAR_INVENTORY_HOST_ROUTER		MVAR_INVENTORY "HOST.ROUTER}"
#define MVAR_INVENTORY_OOB_IP			MVAR_INVENTORY "OOB.IP}"
#define MVAR_INVENTORY_OOB_NETMASK		MVAR_INVENTORY "OOB.NETMASK}"
#define MVAR_INVENTORY_OOB_ROUTER		MVAR_INVENTORY "OOB.ROUTER}"
#define MVAR_INVENTORY_HW_DATE_PURCHASE		MVAR_INVENTORY "HW.DATE.PURCHASE}"
#define MVAR_INVENTORY_HW_DATE_INSTALL		MVAR_INVENTORY "HW.DATE.INSTALL}"
#define MVAR_INVENTORY_HW_DATE_EXPIRY		MVAR_INVENTORY "HW.DATE.EXPIRY}"
#define MVAR_INVENTORY_HW_DATE_DECOMM		MVAR_INVENTORY "HW.DATE.DECOMM}"
#define MVAR_INVENTORY_SITE_ADDRESS_A		MVAR_INVENTORY "SITE.ADDRESS.A}"
#define MVAR_INVENTORY_SITE_ADDRESS_B		MVAR_INVENTORY "SITE.ADDRESS.B}"
#define MVAR_INVENTORY_SITE_ADDRESS_C		MVAR_INVENTORY "SITE.ADDRESS.C}"
#define MVAR_INVENTORY_SITE_CITY		MVAR_INVENTORY "SITE.CITY}"
#define MVAR_INVENTORY_SITE_STATE		MVAR_INVENTORY "SITE.STATE}"
#define MVAR_INVENTORY_SITE_COUNTRY		MVAR_INVENTORY "SITE.COUNTRY}"
#define MVAR_INVENTORY_SITE_ZIP			MVAR_INVENTORY "SITE.ZIP}"
#define MVAR_INVENTORY_SITE_RACK		MVAR_INVENTORY "SITE.RACK}"
#define MVAR_INVENTORY_SITE_NOTES		MVAR_INVENTORY "SITE.NOTES}"
#define MVAR_INVENTORY_POC_PRIMARY_NAME		MVAR_INVENTORY "POC.PRIMARY.NAME}"
#define MVAR_INVENTORY_POC_PRIMARY_EMAIL	MVAR_INVENTORY "POC.PRIMARY.EMAIL}"
#define MVAR_INVENTORY_POC_PRIMARY_PHONE_A	MVAR_INVENTORY "POC.PRIMARY.PHONE.A}"
#define MVAR_INVENTORY_POC_PRIMARY_PHONE_B	MVAR_INVENTORY "POC.PRIMARY.PHONE.B}"
#define MVAR_INVENTORY_POC_PRIMARY_CELL		MVAR_INVENTORY "POC.PRIMARY.CELL}"
#define MVAR_INVENTORY_POC_PRIMARY_SCREEN	MVAR_INVENTORY "POC.PRIMARY.SCREEN}"
#define MVAR_INVENTORY_POC_PRIMARY_NOTES	MVAR_INVENTORY "POC.PRIMARY.NOTES}"
#define MVAR_INVENTORY_POC_SECONDARY_NAME	MVAR_INVENTORY "POC.SECONDARY.NAME}"
#define MVAR_INVENTORY_POC_SECONDARY_EMAIL	MVAR_INVENTORY "POC.SECONDARY.EMAIL}"
#define MVAR_INVENTORY_POC_SECONDARY_PHONE_A	MVAR_INVENTORY "POC.SECONDARY.PHONE.A}"
#define MVAR_INVENTORY_POC_SECONDARY_PHONE_B	MVAR_INVENTORY "POC.SECONDARY.PHONE.B}"
#define MVAR_INVENTORY_POC_SECONDARY_CELL	MVAR_INVENTORY "POC.SECONDARY.CELL}"
#define MVAR_INVENTORY_POC_SECONDARY_SCREEN	MVAR_INVENTORY "POC.SECONDARY.SCREEN}"
#define MVAR_INVENTORY_POC_SECONDARY_NOTES	MVAR_INVENTORY "POC.SECONDARY.NOTES}"

/* PROFILE.* is deprecated, use INVENTORY.* instead */
#define MVAR_PROFILE			"{PROFILE."			/* prefix for profile macros */
#define MVAR_PROFILE_DEVICETYPE		MVAR_PROFILE "DEVICETYPE}"
#define MVAR_PROFILE_NAME		MVAR_PROFILE "NAME}"
#define MVAR_PROFILE_OS			MVAR_PROFILE "OS}"
#define MVAR_PROFILE_SERIALNO		MVAR_PROFILE "SERIALNO}"
#define MVAR_PROFILE_TAG		MVAR_PROFILE "TAG}"
#define MVAR_PROFILE_MACADDRESS		MVAR_PROFILE "MACADDRESS}"
#define MVAR_PROFILE_HARDWARE		MVAR_PROFILE "HARDWARE}"
#define MVAR_PROFILE_SOFTWARE		MVAR_PROFILE "SOFTWARE}"
#define MVAR_PROFILE_CONTACT		MVAR_PROFILE "CONTACT}"
#define MVAR_PROFILE_LOCATION		MVAR_PROFILE "LOCATION}"
#define MVAR_PROFILE_NOTES		MVAR_PROFILE "NOTES}"

#define MVAR_NODE_ID			"{NODE.ID}"
#define MVAR_NODE_NAME			"{NODE.NAME}"

#define MVAR_DISCOVERY_RULE_NAME	"{DISCOVERY.RULE.NAME}"
#define MVAR_DISCOVERY_SERVICE_NAME	"{DISCOVERY.SERVICE.NAME}"
#define MVAR_DISCOVERY_SERVICE_PORT	"{DISCOVERY.SERVICE.PORT}"
#define MVAR_DISCOVERY_SERVICE_STATUS	"{DISCOVERY.SERVICE.STATUS}"
#define MVAR_DISCOVERY_SERVICE_UPTIME	"{DISCOVERY.SERVICE.UPTIME}"
#define MVAR_DISCOVERY_DEVICE_IPADDRESS	"{DISCOVERY.DEVICE.IPADDRESS}"
#define MVAR_DISCOVERY_DEVICE_DNS	"{DISCOVERY.DEVICE.DNS}"
#define MVAR_DISCOVERY_DEVICE_STATUS	"{DISCOVERY.DEVICE.STATUS}"
#define MVAR_DISCOVERY_DEVICE_UPTIME	"{DISCOVERY.DEVICE.UPTIME}"

#define STR_UNKNOWN_VARIABLE		"*UNKNOWN*"

static const char	*ex_macros[] =
{
	MVAR_INVENTORY_TYPE, MVAR_INVENTORY_TYPE_FULL,
	MVAR_INVENTORY_NAME, MVAR_INVENTORY_ALIAS, MVAR_INVENTORY_OS, MVAR_INVENTORY_OS_FULL, MVAR_INVENTORY_OS_SHORT,
	MVAR_INVENTORY_SERIALNO_A, MVAR_INVENTORY_SERIALNO_B, MVAR_INVENTORY_TAG,
	MVAR_INVENTORY_ASSET_TAG, MVAR_INVENTORY_MACADDRESS_A, MVAR_INVENTORY_MACADDRESS_B,
	MVAR_INVENTORY_HARDWARE, MVAR_INVENTORY_HARDWARE_FULL, MVAR_INVENTORY_SOFTWARE, MVAR_INVENTORY_SOFTWARE_FULL,
	MVAR_INVENTORY_SOFTWARE_APP_A, MVAR_INVENTORY_SOFTWARE_APP_B, MVAR_INVENTORY_SOFTWARE_APP_C,
	MVAR_INVENTORY_SOFTWARE_APP_D, MVAR_INVENTORY_SOFTWARE_APP_E, MVAR_INVENTORY_CONTACT, MVAR_INVENTORY_LOCATION,
	MVAR_INVENTORY_LOCATION_LAT, MVAR_INVENTORY_LOCATION_LON, MVAR_INVENTORY_NOTES, MVAR_INVENTORY_CHASSIS,
	MVAR_INVENTORY_MODEL, MVAR_INVENTORY_HW_ARCH, MVAR_INVENTORY_VENDOR, MVAR_INVENTORY_CONTRACT_NUMBER,
	MVAR_INVENTORY_INSTALLER_NAME, MVAR_INVENTORY_DEPLOYMENT_STATUS, MVAR_INVENTORY_URL_A, MVAR_INVENTORY_URL_B,
	MVAR_INVENTORY_URL_C, MVAR_INVENTORY_HOST_NETWORKS, MVAR_INVENTORY_HOST_NETMASK, MVAR_INVENTORY_HOST_ROUTER,
	MVAR_INVENTORY_OOB_IP, MVAR_INVENTORY_OOB_NETMASK, MVAR_INVENTORY_OOB_ROUTER, MVAR_INVENTORY_HW_DATE_PURCHASE,
	MVAR_INVENTORY_HW_DATE_INSTALL, MVAR_INVENTORY_HW_DATE_EXPIRY, MVAR_INVENTORY_HW_DATE_DECOMM,
	MVAR_INVENTORY_SITE_ADDRESS_A, MVAR_INVENTORY_SITE_ADDRESS_B, MVAR_INVENTORY_SITE_ADDRESS_C,
	MVAR_INVENTORY_SITE_CITY, MVAR_INVENTORY_SITE_STATE, MVAR_INVENTORY_SITE_COUNTRY, MVAR_INVENTORY_SITE_ZIP,
	MVAR_INVENTORY_SITE_RACK, MVAR_INVENTORY_SITE_NOTES, MVAR_INVENTORY_POC_PRIMARY_NAME,
	MVAR_INVENTORY_POC_PRIMARY_EMAIL, MVAR_INVENTORY_POC_PRIMARY_PHONE_A, MVAR_INVENTORY_POC_PRIMARY_PHONE_B,
	MVAR_INVENTORY_POC_PRIMARY_CELL, MVAR_INVENTORY_POC_PRIMARY_SCREEN, MVAR_INVENTORY_POC_PRIMARY_NOTES,
	MVAR_INVENTORY_POC_SECONDARY_NAME, MVAR_INVENTORY_POC_SECONDARY_EMAIL, MVAR_INVENTORY_POC_SECONDARY_PHONE_A,
	MVAR_INVENTORY_POC_SECONDARY_PHONE_B, MVAR_INVENTORY_POC_SECONDARY_CELL, MVAR_INVENTORY_POC_SECONDARY_SCREEN,
	MVAR_INVENTORY_POC_SECONDARY_NOTES,
	/* PROFILE.* is deprecated, use INVENTORY.* instead */
	MVAR_PROFILE_DEVICETYPE, MVAR_PROFILE_NAME, MVAR_PROFILE_OS, MVAR_PROFILE_SERIALNO,
	MVAR_PROFILE_TAG, MVAR_PROFILE_MACADDRESS, MVAR_PROFILE_HARDWARE, MVAR_PROFILE_SOFTWARE,
	MVAR_PROFILE_CONTACT, MVAR_PROFILE_LOCATION, MVAR_PROFILE_NOTES,
	MVAR_HOST_HOST, MVAR_HOST_NAME, MVAR_HOSTNAME, MVAR_PROXY_NAME,
	MVAR_HOST_CONN, MVAR_HOST_DNS, MVAR_HOST_IP, MVAR_HOST_PORT, MVAR_IPADDRESS,
	MVAR_ITEM_ID, MVAR_ITEM_NAME, MVAR_ITEM_NAME_ORIG, MVAR_ITEM_DESCRIPTION,
	MVAR_ITEM_KEY, MVAR_ITEM_KEY_ORIG, MVAR_TRIGGER_KEY,
	MVAR_ITEM_LASTVALUE,
	MVAR_ITEM_STATE,
	MVAR_ITEM_VALUE,
	MVAR_ITEM_LOG_DATE, MVAR_ITEM_LOG_TIME, MVAR_ITEM_LOG_AGE, MVAR_ITEM_LOG_SOURCE,
	MVAR_ITEM_LOG_SEVERITY, MVAR_ITEM_LOG_NSEVERITY, MVAR_ITEM_LOG_EVENTID,
	MVAR_NODE_ID, MVAR_NODE_NAME,
	NULL
};

typedef struct
{
	const char	*macro;
	const char	*field_name;
} inventory_field_t;

static inventory_field_t	inventory_fields[] =
{
	{MVAR_INVENTORY_TYPE, "type"},
	{MVAR_PROFILE_DEVICETYPE, "type"},	/* deprecated */
	{MVAR_INVENTORY_TYPE_FULL, "type_full"},
	{MVAR_INVENTORY_NAME, "name"},
	{MVAR_PROFILE_NAME, "name"},	/* deprecated */
	{MVAR_INVENTORY_ALIAS, "alias"},
	{MVAR_INVENTORY_OS, "os"},
	{MVAR_PROFILE_OS, "os"},	/* deprecated */
	{MVAR_INVENTORY_OS_FULL, "os_full"},
	{MVAR_INVENTORY_OS_SHORT, "os_short"},
	{MVAR_INVENTORY_SERIALNO_A, "serialno_a"},
	{MVAR_PROFILE_SERIALNO, "serialno_a"},	/* deprecated */
	{MVAR_INVENTORY_SERIALNO_B, "serialno_b"},
	{MVAR_INVENTORY_TAG, "tag"},
	{MVAR_PROFILE_TAG, "tag"},	/* deprecated */
	{MVAR_INVENTORY_ASSET_TAG, "asset_tag"},
	{MVAR_INVENTORY_MACADDRESS_A, "macaddress_a"},
	{MVAR_PROFILE_MACADDRESS, "macaddress_a"},	/* deprecated */
	{MVAR_INVENTORY_MACADDRESS_B, "macaddress_b"},
	{MVAR_INVENTORY_HARDWARE, "hardware"},
	{MVAR_PROFILE_HARDWARE, "hardware"},	/* deprecated */
	{MVAR_INVENTORY_HARDWARE_FULL, "hardware_full"},
	{MVAR_INVENTORY_SOFTWARE, "software"},
	{MVAR_PROFILE_SOFTWARE, "software"},	/* deprecated */
	{MVAR_INVENTORY_SOFTWARE_FULL, "software_full"},
	{MVAR_INVENTORY_SOFTWARE_APP_A, "software_app_a"},
	{MVAR_INVENTORY_SOFTWARE_APP_B, "software_app_b"},
	{MVAR_INVENTORY_SOFTWARE_APP_C, "software_app_c"},
	{MVAR_INVENTORY_SOFTWARE_APP_D, "software_app_d"},
	{MVAR_INVENTORY_SOFTWARE_APP_E, "software_app_e"},
	{MVAR_INVENTORY_CONTACT, "contact"},
	{MVAR_PROFILE_CONTACT, "contact"},	/* deprecated */
	{MVAR_INVENTORY_LOCATION, "location"},
	{MVAR_PROFILE_LOCATION, "location"},	/* deprecated */
	{MVAR_INVENTORY_LOCATION_LAT, "location_lat"},
	{MVAR_INVENTORY_LOCATION_LON, "location_lon"},
	{MVAR_INVENTORY_NOTES, "notes"},
	{MVAR_PROFILE_NOTES, "notes"},	/* deprecated */
	{MVAR_INVENTORY_CHASSIS, "chassis"},
	{MVAR_INVENTORY_MODEL, "model"},
	{MVAR_INVENTORY_HW_ARCH, "hw_arch"},
	{MVAR_INVENTORY_VENDOR, "vendor"},
	{MVAR_INVENTORY_CONTRACT_NUMBER, "contract_number"},
	{MVAR_INVENTORY_INSTALLER_NAME, "installer_name"},
	{MVAR_INVENTORY_DEPLOYMENT_STATUS, "deployment_status"},
	{MVAR_INVENTORY_URL_A, "url_a"},
	{MVAR_INVENTORY_URL_B, "url_b"},
	{MVAR_INVENTORY_URL_C, "url_c"},
	{MVAR_INVENTORY_HOST_NETWORKS, "host_networks"},
	{MVAR_INVENTORY_HOST_NETMASK, "host_netmask"},
	{MVAR_INVENTORY_HOST_ROUTER, "host_router"},
	{MVAR_INVENTORY_OOB_IP, "oob_ip"},
	{MVAR_INVENTORY_OOB_NETMASK, "oob_netmask"},
	{MVAR_INVENTORY_OOB_ROUTER, "oob_router"},
	{MVAR_INVENTORY_HW_DATE_PURCHASE, "date_hw_purchase"},
	{MVAR_INVENTORY_HW_DATE_INSTALL, "date_hw_install"},
	{MVAR_INVENTORY_HW_DATE_EXPIRY, "date_hw_expiry"},
	{MVAR_INVENTORY_HW_DATE_DECOMM, "date_hw_decomm"},
	{MVAR_INVENTORY_SITE_ADDRESS_A, "site_address_a"},
	{MVAR_INVENTORY_SITE_ADDRESS_B, "site_address_b"},
	{MVAR_INVENTORY_SITE_ADDRESS_C, "site_address_c"},
	{MVAR_INVENTORY_SITE_CITY, "site_city"},
	{MVAR_INVENTORY_SITE_STATE, "site_state"},
	{MVAR_INVENTORY_SITE_COUNTRY, "site_country"},
	{MVAR_INVENTORY_SITE_ZIP, "site_zip"},
	{MVAR_INVENTORY_SITE_RACK, "site_rack"},
	{MVAR_INVENTORY_SITE_NOTES, "site_notes"},
	{MVAR_INVENTORY_POC_PRIMARY_NAME, "poc_1_name"},
	{MVAR_INVENTORY_POC_PRIMARY_EMAIL, "poc_1_email"},
	{MVAR_INVENTORY_POC_PRIMARY_PHONE_A, "poc_1_phone_a"},
	{MVAR_INVENTORY_POC_PRIMARY_PHONE_B, "poc_1_phone_b"},
	{MVAR_INVENTORY_POC_PRIMARY_CELL, "poc_1_cell"},
	{MVAR_INVENTORY_POC_PRIMARY_SCREEN, "poc_1_screen"},
	{MVAR_INVENTORY_POC_PRIMARY_NOTES, "poc_1_notes"},
	{MVAR_INVENTORY_POC_SECONDARY_NAME, "poc_2_name"},
	{MVAR_INVENTORY_POC_SECONDARY_EMAIL, "poc_2_email"},
	{MVAR_INVENTORY_POC_SECONDARY_PHONE_A, "poc_2_phone_a"},
	{MVAR_INVENTORY_POC_SECONDARY_PHONE_B, "poc_2_phone_b"},
	{MVAR_INVENTORY_POC_SECONDARY_CELL, "poc_2_cell"},
	{MVAR_INVENTORY_POC_SECONDARY_SCREEN, "poc_2_screen"},
	{MVAR_INVENTORY_POC_SECONDARY_NOTES, "poc_2_notes"},
	{NULL}
};

/******************************************************************************
 *                                                                            *
 * Function: get_action_value                                                 *
 *                                                                            *
 * Purpose: request action value by macro                                     *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_action_value(const char *macro, zbx_uint64_t actionid, char **replace_to)
{
	int	ret = SUCCEED;

	if (0 == strcmp(macro, MVAR_ACTION_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, actionid);
	}
	else if (0 == strcmp(macro, MVAR_ACTION_NAME))
	{
		DB_RESULT	result;
		DB_ROW		row;

		result = DBselect("select name from actions where actionid=" ZBX_FS_UI64, actionid);

		if (NULL != (row = DBfetch(result)))
			*replace_to = zbx_strdup(*replace_to, row[0]);
		else
			ret = FAIL;

		DBfree_result(result);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_host_inventory_value                                       *
 *                                                                            *
 * Purpose: request host inventory value by itemid and field name             *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_host_inventory_value(zbx_uint64_t itemid, char **replace_to, const char *field_name)
{
	const char	*__function_name = "DBget_host_inventory_value";

	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select hi.%s"
			" from host_inventory hi,items i"
			" where hi.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64, field_name, itemid);

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_host_inventory                                               *
 *                                                                            *
 * Purpose: request host inventory value by macro and trigger                 *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_host_inventory(const char *macro, const char *expression, char **replace_to,
		int N_functionid)
{
	int	i;

	for (i = 0; NULL != inventory_fields[i].macro; i++)
	{
		if (0 == strcmp(macro, inventory_fields[i].macro))
		{
			zbx_uint64_t	itemid;
			int		ret = FAIL;

			if (SUCCEED == get_N_itemid(expression, N_functionid, &itemid))
				ret = DBget_host_inventory_value(itemid, replace_to, inventory_fields[i].field_name);

			return ret;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_host_inventory_by_itemid                                     *
 *                                                                            *
 * Purpose: request host inventory value by macro and itemid                  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_host_inventory_by_itemid(const char *macro, zbx_uint64_t itemid, char **replace_to)
{
	int	i;

	for (i = 0; NULL != inventory_fields[i].macro; i++)
	{
		if (0 == strcmp(macro, inventory_fields[i].macro))
			return DBget_host_inventory_value(itemid, replace_to, inventory_fields[i].field_name);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_recovery_event_value                                         *
 *                                                                            *
 * Purpose: request recovery event value by macro                             *
 *                                                                            *
 ******************************************************************************/
static void	get_recovery_event_value(const char *macro, DB_EVENT *r_event, char **replace_to)
{
	if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_DATE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_date2str(r_event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, r_event->eventid);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_STATUS))
	{
		*replace_to = zbx_strdup(*replace_to,
				zbx_event_value_string(r_event->source, r_event->object, r_event->value));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_TIME))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_time2str(r_event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_VALUE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", r_event->value);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_value                                                  *
 *                                                                            *
 * Purpose: request event value by macro                                      *
 *                                                                            *
 ******************************************************************************/
static void	get_event_value(const char *macro, const DB_EVENT *event, char **replace_to)
{
	if (0 == strcmp(macro, MVAR_EVENT_AGE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_DATE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_date2str(event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, event->eventid);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_TIME))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_time2str(event->clock));
	}
	else if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_STATUS))
		{
			*replace_to = zbx_strdup(*replace_to,
					zbx_event_value_string(event->source, event->object, event->value));
		}
		else if (0 == strcmp(macro, MVAR_EVENT_VALUE))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%d", event->value);
		}
		else if (EVENT_SOURCE_TRIGGERS == event->source)
		{
			if (0 == strcmp(macro, MVAR_EVENT_ACK_HISTORY))
			{
				get_event_ack_history(event, replace_to);
			}
			else if (0 == strcmp(macro, MVAR_EVENT_ACK_STATUS))
			{
				*replace_to = zbx_strdup(*replace_to, event->acknowledged ? "Yes" : "No");
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: get_trigger_function_value                                       *
 *                                                                            *
 * Purpose: trying to evaluate a trigger function                             *
 *                                                                            *
 * Parameters: triggerid  - [IN] the trigger identificator from a database    *
 *             replace_to - [IN] the pointer to a result buffer               *
 *             bl         - [IN] the pointer to a left curly bracket          *
 *             br         - [OUT] the pointer to a next char, after a right   *
 *                          curly bracket                                     *
 *                                                                            *
 * Return value: (1) *replace_to = NULL - invalid function format;            *
 *                    'br' pointer remains unchanged                          *
 *               (2) *replace_to = "*UNKNOWN*" - invalid hostname, key or     *
 *                    function, or a function cannot be evaluated             *
 *               (3) *replace_to = "<value>" - a function successfully        *
 *                    evaluated                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: example: " {Zabbix server:{ITEM.KEY1}.last(0)} " to " 1.34 "     *
 *                      ^ - bl                             ^ - br             *
 *                                                                            *
 ******************************************************************************/
static void	get_trigger_function_value(const char *expression, char **replace_to, char *bl, char **br)
{
	char	*p, *host = NULL, *key = NULL, *function = NULL, *parameter = NULL;
	int	N_functionid, ret = FAIL;
	size_t	sz;

	p = bl + 1;

	if (0 == strncmp(p, MVAR_HOSTNAME, sz = sizeof(MVAR_HOSTNAME) - 2) ||
			0 == strncmp(p, MVAR_HOST_HOST, sz = sizeof(MVAR_HOST_HOST) - 2))
	{
		ret = SUCCEED;
	}

	if (SUCCEED == ret && ('}' == p[sz] || ('}' == p[sz + 1] && '1' <= p[sz] && p[sz] <= '9')))
	{
		N_functionid = ('}' == p[sz] ? 1 : p[sz] - '0');
		p += sz + ('}' == p[sz] ? 1 : 2);
		DBget_trigger_value(expression, &host, N_functionid, ZBX_REQUEST_HOST_HOST);
	}
	else
		ret = parse_host(&p, &host);

	if (SUCCEED != ret || ':' != *p++)
		goto fail;

	if ((0 == strncmp(p, MVAR_ITEM_KEY, sz = sizeof(MVAR_ITEM_KEY) - 2) ||
				0 == strncmp(p, MVAR_TRIGGER_KEY, sz = sizeof(MVAR_TRIGGER_KEY) - 2)) &&
			('}' == p[sz] || ('}' == p[sz + 1] && '1' <= p[sz] && p[sz] <= '9')))
	{
		N_functionid = ('}' == p[sz] ? 1 : p[sz] - '0');
		p += sz + ('}' == p[sz] ? 1 : 2);
		DBget_trigger_value(expression, &key, N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG);
	}
	else
		ret = parse_key(&p, &key);

	if (SUCCEED != ret || '.' != *p++)
		goto fail;

	if (SUCCEED != parse_function(&p, &function, &parameter) || '}' != *p++)
		goto fail;

	/* function 'evaluate_macro_function' requires 'replace_to' with size 'MAX_BUFFER_LEN' */
	*replace_to = zbx_realloc(*replace_to, MAX_BUFFER_LEN);

	if (NULL == host || NULL == key ||
			SUCCEED != evaluate_macro_function(*replace_to, host, key, function, parameter))
		zbx_strlcpy(*replace_to, STR_UNKNOWN_VARIABLE, MAX_BUFFER_LEN);

	*br = p;
fail:
	zbx_free(host);
	zbx_free(key);
	zbx_free(function);
	zbx_free(parameter);
}

/******************************************************************************
 *                                                                            *
 * Function: cache_expression_hostids                                         *
 *                                                                            *
 * Purpose: cache host identifiers referenced by trigger expression           *
 *                                                                            *
 * Parameters: hostids    - [OUT] the host identifier cache                   *
 *             expression - [IN] the trigger expression                       *
 *                                                                            *
 ******************************************************************************/
static void	cache_trigger_hostids(zbx_vector_uint64_t *hostids, const char *expression)
{
	if (0 == hostids->values_num)
	{
		zbx_vector_uint64_t	functionids;

		zbx_vector_uint64_create(&functionids);
		get_functionids(&functionids, expression);
		DCget_functions_hostids(hostids, &functionids);
		zbx_vector_uint64_destroy(&functionids);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_simple_macros                                         *
 *                                                                            *
 * Purpose: substitute simple macros in data string with real values          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, DB_EVENT *r_event, zbx_uint64_t *userid,
		zbx_uint64_t *hostid, DC_HOST *dc_host, DC_ITEM *dc_item, char **data, int macro_type,
		char *error, int maxerrlen)
{
	const char		*__function_name = "substitute_simple_macros";

	char			*p, *bl, *br, c, *replace_to = NULL, sql[64];
	const char		*m;
	int			N_functionid, ret, res = SUCCEED;
	size_t			data_alloc, data_len;
	DC_INTERFACE		interface;
	zbx_vector_uint64_t	hostids;

	if (NULL == data || NULL == *data || '\0' == **data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:EMPTY", __function_name);
		return res;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	if (0 != (macro_type & MACRO_TYPE_TRIGGER_DESCRIPTION))
		expand_trigger_description_constants(data, event->trigger.expression);

	p = *data;
	if (NULL == (m = bl = strchr(p, '{')))
		return res;

	zbx_vector_uint64_create(&hostids);

	data_alloc = data_len = strlen(*data) + 1;

	for (; NULL != bl && SUCCEED == res; m = bl = strchr(p, '{'))
	{
		if (NULL == (br = strchr(bl, '}')))
			break;

		c = *++br;
		*br = '\0';

		ret = SUCCEED;
		N_functionid = 1;

		if ('1' <= *(br - 2) && *(br - 2) <= '9')
		{
			int	i, diff;

			for (i = 0; NULL != ex_macros[i]; i++)
			{
				diff = zbx_mismatch(ex_macros[i], bl);

				if ('}' == ex_macros[i][diff] && bl + diff == br - 2)
				{
					N_functionid = *(br - 2) - '0';
					m = ex_macros[i];
					break;
				}
			}
		}

		if (0 != (macro_type & (MACRO_TYPE_MESSAGE_NORMAL | MACRO_TYPE_MESSAGE_RECOVERY)))
		{
			const DB_EVENT	*c_event;

			c_event = (0 != (macro_type & MACRO_TYPE_MESSAGE_RECOVERY) ? r_event : event);

			if (EVENT_SOURCE_TRIGGERS == c_event->source)
			{
				if (0 == strncmp(m, MVAR_ACTION, sizeof(MVAR_ACTION) - 1))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(event, r_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, sizeof(MVAR_EVENT_RECOVERY) - 1))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_RECOVERY))
						get_recovery_event_value(m, r_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT, sizeof(MVAR_EVENT) - 1))
				{
					get_event_value(m, event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, sizeof(MVAR_INVENTORY) - 1) ||
						0 == strncmp(m, MVAR_PROFILE, sizeof(MVAR_PROFILE) - 1))
				{
					ret = get_host_inventory(m, c_event->trigger.expression, &replace_to,
							N_functionid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_ID);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = DBitem_lastvalue(c_event->trigger.expression, &replace_to, N_functionid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_AGE))
				{
					ret = get_history_log_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_LOG_AGE, c_event->clock,
							c_event->ns);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_DATE))
				{
					ret = get_history_log_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_LOG_DATE, c_event->clock,
							c_event->ns);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_EVENTID))
				{
					ret = get_history_log_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_LOG_EVENTID, c_event->clock,
							c_event->ns);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_NSEVERITY))
				{
					ret = get_history_log_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_LOG_NSEVERITY, c_event->clock,
							c_event->ns);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_SEVERITY))
				{
					ret = get_history_log_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_LOG_SEVERITY, c_event->clock,
							c_event->ns);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_SOURCE))
				{
					ret = get_history_log_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_LOG_SOURCE, c_event->clock,
							c_event->ns);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_TIME))
				{
					ret = get_history_log_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_LOG_TIME, c_event->clock,
							c_event->ns);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = DBitem_value(c_event->trigger.expression, &replace_to, N_functionid,
							c_event->clock, c_event->ns);
				}
				else if (0 == strcmp(m, MVAR_NODE_ID))
				{
					ret = get_node_value(c_event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_NODE_ID);
				}
				else if (0 == strcmp(m, MVAR_NODE_NAME))
				{
					ret = get_node_value(c_event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_NODE_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_DESCRIPTION) ||
						0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.comments);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL,
							&replace_to, MACRO_TYPE_TRIGGER_COMMENTS, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_ACK))
				{
					ret = DBget_trigger_event_count(c_event->objectid, &replace_to, 0, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_PROBLEM_ACK))
				{
					ret = DBget_trigger_event_count(c_event->objectid, &replace_to, 1, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_PROBLEM_UNACK))
				{
					ret = DBget_trigger_event_count(c_event->objectid, &replace_to, 1, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_UNACK))
				{
					ret = DBget_trigger_event_count(c_event->objectid, &replace_to, 0, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.expression);
					DCexpand_trigger_expression(&replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_HOSTGROUP_NAME))
				{
					ret = DBget_trigger_hostgroup_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL,
							&replace_to, MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME_ORIG))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NSEVERITY))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", (int)c_event->trigger.priority);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_STATUS) || 0 == strcmp(m, MVAR_STATUS))
				{
					replace_to = zbx_strdup(replace_to, zbx_trigger_value_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_SEVERITY))
				{
					ret = DCget_trigger_severity_name(c_event->trigger.priority, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_TEMPLATE_NAME))
				{
					ret = DBget_trigger_template_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.url);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL,
							&replace_to, MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", c_event->value);
				}
				else
				{
					*br = c;
					get_trigger_function_value(c_event->trigger.expression, &replace_to, bl, &br);
					c = *br;
					*br = '\0';
				}
			}
			else if (EVENT_SOURCE_INTERNAL == c_event->source && EVENT_OBJECT_TRIGGER == c_event->object)
			{
				if (0 == strncmp(m, MVAR_ACTION, sizeof(MVAR_ACTION) - 1))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(event, r_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, sizeof(MVAR_EVENT_RECOVERY) - 1))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_RECOVERY))
						get_recovery_event_value(m, r_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT, sizeof(MVAR_EVENT) - 1))
				{
					get_event_value(m, event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, sizeof(MVAR_INVENTORY) - 1) ||
						0 == strncmp(m, MVAR_PROFILE, sizeof(MVAR_PROFILE) - 1))
				{
					ret = get_host_inventory(m, c_event->trigger.expression, &replace_to,
							N_functionid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_ID);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_NODE_ID))
				{
					ret = get_node_value(c_event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_NODE_ID);
				}
				else if (0 == strcmp(m, MVAR_NODE_NAME))
				{
					ret = get_node_value(c_event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_NODE_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_DESCRIPTION) ||
						0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.comments);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL,
							&replace_to, MACRO_TYPE_TRIGGER_COMMENTS, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.expression);
					DCexpand_trigger_expression(&replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_HOSTGROUP_NAME))
				{
					ret = DBget_trigger_hostgroup_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL,
							&replace_to, MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME_ORIG))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NSEVERITY))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", (int)c_event->trigger.priority);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_SEVERITY))
				{
					ret = DCget_trigger_severity_name(c_event->trigger.priority, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_STATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_trigger_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_TEMPLATE_NAME))
				{
					ret = DBget_trigger_template_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.url);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL,
							&replace_to, MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
				}
			}
			else if (EVENT_SOURCE_DISCOVERY == c_event->source)
			{
				if (0 == strncmp(m, MVAR_ACTION, sizeof(MVAR_ACTION) - 1))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strncmp(m, MVAR_EVENT, sizeof(MVAR_EVENT) - 1))
				{
					get_event_value(m, event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_IPADDRESS))
				{
					ret = DBget_dhost_value_by_event(c_event, &replace_to, "s.ip");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_DNS))
				{
					ret = DBget_dhost_value_by_event(c_event, &replace_to, "s.dns");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_STATUS))
				{
					if (SUCCEED == (ret = DBget_dhost_value_by_event(c_event, &replace_to,
							"h.status")))
					{
						replace_to = zbx_strdup(replace_to,
								DOBJECT_STATUS_UP == atoi(replace_to) ? "UP" : "DOWN");
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_UPTIME))
				{
					zbx_snprintf(sql, sizeof(sql),
							"case when h.status=%d then h.lastup else h.lastdown end",
							DOBJECT_STATUS_UP);
					if (SUCCEED == (ret = DBget_dhost_value_by_event(c_event, &replace_to, sql)))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_age2str(time(NULL) - atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_RULE_NAME))
				{
					ret = DBget_drule_value_by_event(c_event, &replace_to, "name");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_NAME))
				{
					if (SUCCEED == (ret = DBget_dservice_value_by_event(c_event, &replace_to,
							"s.type")))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_dservice_type_string(atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_PORT))
				{
					ret = DBget_dservice_value_by_event(c_event, &replace_to, "s.port");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_STATUS))
				{
					if (SUCCEED == (ret = DBget_dservice_value_by_event(c_event, &replace_to,
							"s.status")))
					{
						replace_to = zbx_strdup(replace_to,
								DOBJECT_STATUS_UP == atoi(replace_to) ? "UP" : "DOWN");
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_UPTIME))
				{
					zbx_snprintf(sql, sizeof(sql),
							"case when s.status=%d then s.lastup else s.lastdown end",
							DOBJECT_STATUS_UP);
					if (SUCCEED == (ret = DBget_dservice_value_by_event(c_event, &replace_to, sql)))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_age2str(time(NULL) - atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_NODE_ID))
				{
					ret = DBget_node_value(c_event->objectid, &replace_to, ZBX_REQUEST_NODE_ID);
				}
				else if (0 == strcmp(m, MVAR_NODE_NAME))
				{
					ret = DBget_node_value(c_event->objectid, &replace_to, ZBX_REQUEST_NODE_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = DBget_dhost_value_by_event(c_event, &replace_to,
							"r.proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
							replace_to = zbx_strdup(replace_to, "");
						else
							ret = DBget_host_name_by_hostid(proxy_hostid, &replace_to);
					}
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
			}
			else if (EVENT_SOURCE_AUTO_REGISTRATION == c_event->source)
			{
				if (0 == strncmp(m, MVAR_ACTION, sizeof(MVAR_ACTION) - 1))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strncmp(m, MVAR_EVENT, sizeof(MVAR_EVENT) - 1))
				{
					get_event_value(m, event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_HOST_METADATA))
				{
					ret = get_autoreg_value_by_event(c_event, &replace_to, "host_metadata");
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = get_autoreg_value_by_event(c_event, &replace_to, "host");
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = get_autoreg_value_by_event(c_event, &replace_to, "listen_ip");
				}
				else if (0 == strcmp(m, MVAR_NODE_ID))
				{
					ret = DBget_node_value(c_event->objectid, &replace_to, ZBX_REQUEST_NODE_ID);
				}
				else if (0 == strcmp(m, MVAR_NODE_NAME))
				{
					ret = DBget_node_value(c_event->objectid, &replace_to, ZBX_REQUEST_NODE_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = get_autoreg_value_by_event(c_event, &replace_to, "listen_port");
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = get_autoreg_value_by_event(c_event, &replace_to,
							"proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
							replace_to = zbx_strdup(replace_to, "");
						else
							ret = DBget_host_name_by_hostid(proxy_hostid, &replace_to);
					}
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
			}
			else if (EVENT_SOURCE_INTERNAL == c_event->source && EVENT_OBJECT_ITEM == c_event->object)
			{
				if (0 == strncmp(m, MVAR_ACTION, sizeof(MVAR_ACTION) - 1))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(event, r_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, sizeof(MVAR_EVENT_RECOVERY) - 1))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_RECOVERY))
						get_recovery_event_value(m, r_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT, sizeof(MVAR_EVENT) - 1))
				{
					get_event_value(m, event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, sizeof(MVAR_INVENTORY) - 1) ||
						0 == strncmp(m, MVAR_PROFILE, sizeof(MVAR_PROFILE) - 1))
				{
					ret = get_host_inventory_by_itemid(m, c_event->objectid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_STATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_item_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_NODE_ID))
				{
					ret = DBget_node_value(c_event->objectid, &replace_to, ZBX_REQUEST_NODE_ID);
				}
				else if (0 == strcmp(m, MVAR_NODE_NAME))
				{
					ret = DBget_node_value(c_event->objectid, &replace_to, ZBX_REQUEST_NODE_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
			}
			else if (EVENT_SOURCE_INTERNAL == c_event->source && EVENT_OBJECT_LLDRULE == c_event->object)
			{
				if (0 == strncmp(m, MVAR_ACTION, sizeof(MVAR_ACTION) - 1))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(event, r_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, sizeof(MVAR_EVENT_RECOVERY) - 1))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_RECOVERY))
						get_recovery_event_value(m, r_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT, sizeof(MVAR_EVENT) - 1))
				{
					get_event_value(m, event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, sizeof(MVAR_INVENTORY) - 1) ||
						0 == strncmp(m, MVAR_PROFILE, sizeof(MVAR_PROFILE) - 1))
				{
					ret = get_host_inventory_by_itemid(m, c_event->objectid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_KEY))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_KEY_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_NAME_ORIG))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_STATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_item_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_NODE_ID))
				{
					ret = DBget_node_value(c_event->objectid, &replace_to, ZBX_REQUEST_NODE_ID);
				}
				else if (0 == strcmp(m, MVAR_NODE_NAME))
				{
					ret = DBget_node_value(c_event->objectid, &replace_to, ZBX_REQUEST_NODE_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
			}
		}
		else if (0 != (macro_type & (MACRO_TYPE_TRIGGER_DESCRIPTION | MACRO_TYPE_TRIGGER_COMMENTS)))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = DBitem_lastvalue(event->trigger.expression, &replace_to, N_functionid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = DBitem_value(event->trigger.expression, &replace_to, N_functionid,
							event->clock, event->ns);
				}
				else if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				{
					cache_trigger_hostids(&hostids, event->trigger.expression);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
				}
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_TRIGGER_EXPRESSION))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
					replace_to = zbx_dsprintf(replace_to, "%d", event->value);
				else if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				{
					cache_trigger_hostids(&hostids, event->trigger.expression);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);

					if (NULL != replace_to && FAIL == (res = is_double_suffix(replace_to)) &&
							NULL != error)
					{
						zbx_snprintf(error, maxerrlen, "Macro '%s' value is not numeric", m);
					}
				}
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_TRIGGER_URL))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (0 == strcmp(m, MVAR_TRIGGER_ID))
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
			}
		}
		else if (0 != (macro_type & (MACRO_TYPE_ITEM_KEY | MACRO_TYPE_PARAMS_FIELD)))
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				DCget_user_macro(&dc_item->host.hostid, 1, m, &replace_to);
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_item->host.host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_item->host.name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
					replace_to = zbx_strdup(replace_to, dc_item->interface.ip_orig);
				else
					ret = FAIL;
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
					replace_to = zbx_strdup(replace_to, dc_item->interface.dns_orig);
				else
					ret = FAIL;
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
					replace_to = zbx_strdup(replace_to, dc_item->interface.addr);
				else
					ret = FAIL;
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_INTERFACE_ADDR))
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface_by_type(&interface,
						dc_host->hostid, INTERFACE_TYPE_AGENT)))
				{
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
				}
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface_by_type(&interface,
						dc_host->hostid, INTERFACE_TYPE_AGENT)))
				{
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = DCconfig_get_interface_by_type(&interface,
						dc_host->hostid, INTERFACE_TYPE_AGENT)))
				{
					replace_to = zbx_strdup(replace_to, interface.addr);
				}
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_INTERFACE_ADDR_DB))
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_IP, 1);
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_DNS, 1);
			else if (0 == strcmp(m, MVAR_HOST_CONN))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_CONN, 1);
		}
		else if (0 != (macro_type & (MACRO_TYPE_COMMON | MACRO_TYPE_SNMP_OID)))
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
			{
				if (NULL != hostid)
					DCget_user_macro(hostid, 1, m, &replace_to);
				else
					DCget_user_macro(NULL, 0, m, &replace_to);
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_ITEM_EXPRESSION))
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				if (NULL != replace_to && FAIL == (res = is_double_suffix(replace_to)) && NULL != error)
					zbx_snprintf(error, maxerrlen, "Macro '%s' value is not numeric", m);
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_SCRIPT))
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_IP, 0);
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_DNS, 0);
			else if (0 == strcmp(m, MVAR_HOST_CONN))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_CONN, 0);
		}
		else if (0 != (macro_type & MACRO_TYPE_HTTPTEST_FIELD))
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_IP, 0);
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_DNS, 0);
			else if (0 == strcmp(m, MVAR_HOST_CONN))
				ret = DBget_interface_value(dc_host->hostid, &replace_to, ZBX_REQUEST_HOST_CONN, 0);
		}

		if (FAIL == ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot resolve macro '%s'", bl);
			replace_to = zbx_strdup(replace_to, STR_UNKNOWN_VARIABLE);
		}

		*br = c;

		if (NULL != replace_to)
		{
			size_t	sz_m, sz_r;

			sz_m = br - bl;
			sz_r = strlen(replace_to);

			if (sz_m != sz_r)
			{
				data_len += sz_r - sz_m;

				if (data_len > data_alloc)
				{
					char	*old_data = *data;

					while (data_len > data_alloc)
						data_alloc *= 2;
					*data = zbx_realloc(*data, data_alloc);
					bl += *data - old_data;
				}

				memmove(bl + sz_r, bl + sz_m, data_len - (bl - *data) - sz_r);
			}

			memcpy(bl, replace_to, sz_r);
			p = bl + sz_r;

			zbx_free(replace_to);
		}
		else
			p = bl + 1;
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End %s() data:'%s'", __function_name, *data);

	return res;
}

static void	zbx_extract_functionids(zbx_vector_uint64_t *functionids, zbx_vector_ptr_t *triggers)
{
	const char	*__function_name = "zbx_extract_functionids";

	DC_TRIGGER	*tr;
	int		i, values_num_save;
	char		*bl, *br;
	zbx_uint64_t	functionid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __function_name, triggers->values_num);

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		values_num_save = functionids->values_num;

		for (bl = strchr(tr->expression, '{'); NULL != bl; bl = strchr(bl, '{'))
		{
			if (NULL == (br = strchr(bl, '}')))
				break;

			*br = '\0';

			if (SUCCEED != is_uint64(bl + 1, &functionid))
			{
				*br = '}';
				break;
			}

			zbx_vector_uint64_append(functionids, functionid);

			bl = br + 1;
			*br = '}';
		}

		if (NULL != bl)
		{
			tr->new_error = zbx_dsprintf(tr->new_error, "Invalid expression [%s]", tr->expression);
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
			functionids->values_num = values_num_save;
		}
	}

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() functionids_num:%d", __function_name, functionids->values_num);
}

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	triggerid;
	char		*function;
	char		*parameter;
	zbx_timespec_t	timespec;
	char		*value;
	char		*error;
}
zbx_func_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_vector_ptr_t	functions;
}
zbx_ifunc_t;

static void	zbx_populate_function_items(zbx_vector_uint64_t *functionids, zbx_vector_ptr_t *ifuncs,
		zbx_vector_ptr_t *triggers)
{
	const char	*__function_name = "zbx_populate_function_items";

	int		i, j;
	DC_TRIGGER	*tr;
	DC_FUNCTION	*functions = NULL;
	int		*errcodes = NULL;
	zbx_ifunc_t	*ifunc = NULL;
	zbx_func_t	*func;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() functionids_num:%d", __function_name, functionids->values_num);

	functions = zbx_malloc(functions, sizeof(DC_FUNCTION) * functionids->values_num);
	errcodes = zbx_malloc(errcodes, sizeof(int) * functionids->values_num);

	DCconfig_get_functions_by_functionids(functions, functionids->values, errcodes, functionids->values_num);

	for (i = 0; i < functionids->values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		for (j = 0; j < ifuncs->values_num; j++)
		{
			ifunc = (zbx_ifunc_t *)ifuncs->values[j];

			if (ifunc->itemid == functions[i].itemid)
				break;
		}

		if (j == ifuncs->values_num)
		{
			ifunc = zbx_malloc(NULL, sizeof(zbx_ifunc_t));
			ifunc->itemid = functions[i].itemid;
			zbx_vector_ptr_create(&ifunc->functions);

			zbx_vector_ptr_append(ifuncs, ifunc);
		}

		func = zbx_malloc(NULL, sizeof(zbx_func_t));

		func->functionid = functions[i].functionid;
		func->triggerid = functions[i].triggerid;
		func->function = zbx_strdup(NULL, functions[i].function);
		func->parameter = zbx_strdup(NULL, functions[i].parameter);
		func->timespec.sec = 0;
		func->timespec.ns = 0;
		func->value = NULL;
		func->error = NULL;

		if (FAIL != (j = zbx_vector_ptr_bsearch(triggers, &func->triggerid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			tr = (DC_TRIGGER *)triggers->values[j];
			func->timespec = tr->timespec;
		}

		zbx_vector_ptr_append(&ifunc->functions, func);
	}

	DCconfig_clean_functions(functions, errcodes, functionids->values_num);

	zbx_free(errcodes);
	zbx_free(functions);

	zbx_vector_ptr_sort(ifuncs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < ifuncs->values_num; i++)
	{
		ifunc = (zbx_ifunc_t *)ifuncs->values[i];
		zbx_vector_ptr_sort(&ifunc->functions, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ifuncs_num:%d", __function_name, ifuncs->values_num);
}

static void	zbx_evaluate_item_functions(zbx_vector_ptr_t *ifuncs)
{
	const char	*__function_name = "zbx_evaluate_item_functions";

	DC_ITEM		*items = NULL;
	char		value[MAX_BUFFER_LEN];
	int		i, k;
	zbx_ifunc_t	*ifunc = NULL;
	zbx_func_t	*func;
	zbx_uint64_t	*itemids = NULL;
	int		*errcodes = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ifuncs_num:%d", __function_name, ifuncs->values_num);

	itemids = zbx_malloc(itemids, ifuncs->values_num * sizeof(zbx_uint64_t));

	for (i = 0; i < ifuncs->values_num; i++)
		itemids[i] = ((zbx_ifunc_t *)ifuncs->values[i])->itemid;

	items = zbx_malloc(items, sizeof(DC_ITEM) * ifuncs->values_num);
	errcodes = zbx_malloc(errcodes, sizeof(int) * ifuncs->values_num);

	DCconfig_get_items_by_itemids(items, itemids, errcodes, ifuncs->values_num);

	zbx_free(itemids);

	for (i = 0; i < ifuncs->values_num; i++)
	{
		ifunc = (zbx_ifunc_t *)ifuncs->values[i];

		for (k = 0; k < ifunc->functions.values_num; k++)
		{
			func = (zbx_func_t *)ifunc->functions.values[k];

			if (SUCCEED != errcodes[i])
			{
				func->error = zbx_dsprintf(func->error, "Cannot evaluate function \"%s(%s)\":"
						" item does not exist.",
						func->function, func->parameter);
				continue;
			}

			if (ITEM_STATUS_ACTIVE != items[i].status)
			{
				func->error = zbx_dsprintf(func->error, "Cannot evaluate function \"%s:%s.%s(%s)\":"
						" item is disabled.",
						items[i].host.host, items[i].key_orig, func->function, func->parameter);
				continue;
			}

			if (HOST_STATUS_MONITORED != items[i].host.status)
			{
				func->error = zbx_dsprintf(func->error, "Cannot evaluate function \"%s:%s.%s(%s)\":"
						" item belongs to a disabled host.",
						items[i].host.host, items[i].key_orig, func->function, func->parameter);
				continue;
			}

			if (ITEM_STATE_NOTSUPPORTED == items[i].state)
			{
				func->error = zbx_dsprintf(func->error, "Cannot evaluate function \"%s:%s.%s(%s)\":"
						" item is not supported.",
						items[i].host.host, items[i].key_orig, func->function, func->parameter);
				continue;
			}

			if (SUCCEED != evaluate_function(value, &items[i], func->function, func->parameter, func->timespec.sec))
			{
				func->error = zbx_dsprintf(func->error, "Cannot evaluate function \"%s:%s.%s(%s)\".",
						items[i].host.host, items[i].key_orig, func->function, func->parameter);
			}
			else
				func->value = zbx_strdup(func->value, value);
		}
	}

	DCconfig_clean_items(items, errcodes, ifuncs->values_num);

	zbx_free(errcodes);
	zbx_free(items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static zbx_func_t	*zbx_get_func_by_functionid(zbx_vector_ptr_t *ifuncs, zbx_uint64_t functionid)
{
	zbx_ifunc_t	*ifunc;
	int		i, j;

	for (i = 0; i < ifuncs->values_num; i++)
	{
		ifunc = (zbx_ifunc_t *)ifuncs->values[i];

		if (FAIL != (j = zbx_vector_ptr_bsearch(&ifunc->functions, &functionid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			return (zbx_func_t *)ifunc->functions.values[j];
		}
	}

	return NULL;
}

static void	zbx_substitute_functions_results(zbx_vector_ptr_t *ifuncs, zbx_vector_ptr_t *triggers)
{
	const char	*__function_name = "zbx_substitute_functions_results";

	DC_TRIGGER	*tr;
	char		*out = NULL, *br, *bl;
	size_t		out_alloc = TRIGGER_EXPRESSION_LEN_MAX, out_offset = 0;
	int		i;
	zbx_uint64_t	functionid;
	zbx_func_t	*func;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ifuncs_num:%d tr_num:%d",
			__function_name, ifuncs->values_num, triggers->values_num);

	out = zbx_malloc(out, out_alloc);

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		out_offset = 0;

		for (br = tr->expression, bl = strchr(tr->expression, '{'); NULL != bl; bl = strchr(bl, '{'))
		{
			*bl = '\0';
			zbx_strcpy_alloc(&out, &out_alloc, &out_offset, br);
			*bl = '{';

			if (NULL == (br = strchr(bl, '}')))
			{
				tr[i].new_error = zbx_strdup(tr[i].new_error, "Invalid trigger expression");
				tr[i].new_value = TRIGGER_VALUE_UNKNOWN;
				THIS_SHOULD_NEVER_HAPPEN;
				break;
			}

			*br = '\0';

			ZBX_STR2UINT64(functionid, bl + 1);

			*br++ = '}';
			bl = br;

			if (NULL == (func = zbx_get_func_by_functionid(ifuncs, functionid)))
			{
				tr->new_error = zbx_dsprintf(tr->new_error, "Cannot obtain function"
						" and item for functionid: " ZBX_FS_UI64, functionid);
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				break;
			}

			if (NULL != func->error)
			{
				tr->new_error = zbx_strdup(tr->new_error, func->error);
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				break;
			}

			if (NULL == func->value)
			{
				tr->new_error = zbx_strdup(tr->new_error, "Unexpected error while"
						" processing a trigger expression");
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				break;
			}

			zbx_strcpy_alloc(&out, &out_alloc, &out_offset, func->value);
		}

		if (NULL == tr->new_error)
		{
			zbx_strcpy_alloc(&out, &out_alloc, &out_offset, br);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() expression[%d]:'%s' => '%s'",
					__function_name, i, tr->expression, out);

			tr->expression = zbx_strdup(tr->expression, out);
		}
	}

	zbx_free(out);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	zbx_free_item_functions(zbx_vector_ptr_t *ifuncs)
{
	int		i, j;
	zbx_ifunc_t	*ifunc;
	zbx_func_t	*func;

	for (i = 0; i < ifuncs->values_num; i++)
	{
		ifunc = (zbx_ifunc_t *)ifuncs->values[i];

		for (j = 0; j < ifunc->functions.values_num; j++)
		{
			func = (zbx_func_t *)ifunc->functions.values[j];

			zbx_free(func->function);
			zbx_free(func->parameter);
			zbx_free(func->value);
			zbx_free(func->error);
			zbx_free(func);
		}
		zbx_vector_ptr_destroy(&ifunc->functions);
		zbx_free(ifunc);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_functions                                             *
 *                                                                            *
 * Purpose: substitute expression functions with their values                 *
 *                                                                            *
 * Parameters: triggers - array of DC_TRIGGER structures                      *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev, Aleksandrs Saveljevs        *
 *                                                                            *
 * Comments: example: "({15}>10)|({123}=0)" => "(6.456>10)|(0=0)"             *
 *                                                                            *
 ******************************************************************************/
static void	substitute_functions(zbx_vector_ptr_t *triggers)
{
	const char		*__function_name = "substitute_functions";

	zbx_vector_uint64_t	functionids;
	zbx_vector_ptr_t	ifuncs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&functionids);
	zbx_extract_functionids(&functionids, triggers);

	if (0 == functionids.values_num)
		goto empty;

	zbx_vector_ptr_create(&ifuncs);
	zbx_populate_function_items(&functionids, &ifuncs, triggers);

	if (0 != ifuncs.values_num)
	{
		zbx_evaluate_item_functions(&ifuncs);
		zbx_substitute_functions_results(&ifuncs, triggers);
	}

	zbx_free_item_functions(&ifuncs);
	zbx_vector_ptr_destroy(&ifuncs);
empty:
	zbx_vector_uint64_destroy(&functionids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_expressions                                             *
 *                                                                            *
 * Purpose: evaluate trigger expressions                                      *
 *                                                                            *
 * Parameters: triggers - [IN] array of DC_TRIGGER structures                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	evaluate_expressions(zbx_vector_ptr_t *triggers)
{
	const char	*__function_name = "evaluate_expressions";

	DB_EVENT	event;
	DC_TRIGGER	*tr;
	int		i;
	double		expr_result;
	char		err[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __function_name, triggers->values_num);

	memset(&event, 0, sizeof(DB_EVENT));
	event.source = EVENT_SOURCE_TRIGGERS;
	event.object = EVENT_OBJECT_TRIGGER;

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		event.objectid = tr->triggerid;
		event.value = tr->value;

		tr->expression = zbx_strdup(NULL, tr->expression_orig);

		/* the trigger expression is used to parse function ids for referenced hostid caching */
		/* when evaluating trigger expression                                                 */
		event.trigger.expression = tr->expression_orig;

		zbx_remove_whitespace(tr->expression);

		if (SUCCEED != substitute_simple_macros(NULL, &event, NULL, NULL, NULL, NULL, NULL,
				&tr->expression, MACRO_TYPE_TRIGGER_EXPRESSION, err, sizeof(err)))
		{
			tr->new_error = zbx_strdup(tr->new_error, err);
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
		}
	}

	substitute_functions(triggers);

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		if (SUCCEED != evaluate(&expr_result, tr->expression, err, sizeof(err)))
		{
			tr->new_error = zbx_strdup(tr->new_error, err);
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
		}
		else if (SUCCEED == cmp_double(expr_result, 0))
		{
			tr->new_value = TRIGGER_VALUE_OK;
		}
		else
			tr->new_value = TRIGGER_VALUE_PROBLEM;
	}

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s():expression [%s] cannot be evaluated: %s",
					__function_name, tr->expression, tr->new_error);
		}

		zbx_free(tr->expression);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_discovery_macros_simple                               *
 *                                                                            *
 * Purpose: trying to resolve the discovery macros in item key parameters     *
 *          in simple macros like {host:key[].func()}                         *
 *                                                                            *
 ******************************************************************************/
static int	substitute_discovery_macros_simple(char *data, char **replace_to, size_t *replace_to_alloc,
		size_t *pos, struct zbx_json_parse *jp_row)
{
	char	*pl, *pr;
	char	*key = NULL;
	size_t	sz, replace_to_offset = 0;

	pl = pr = data + *pos;
	if ('{' != *pr++)
		return FAIL;

	/* check for macros {HOST.HOST<1-9>} and {HOSTNAME<1-9>} */
	if ((0 == strncmp(pr, MVAR_HOST_HOST, sz = sizeof(MVAR_HOST_HOST) - 2) ||
			0 == strncmp(pr, MVAR_HOSTNAME, sz = sizeof(MVAR_HOSTNAME) - 2)) &&
			('}' == pr[sz] || ('}' == pr[sz + 1] && '1' <= pr[sz] && pr[sz] <= '9')))
	{
		pr += sz + ('}' == pr[sz] ? 1 : 2);
	}
	else if (SUCCEED != parse_host(&pr, NULL))	/* a simple host name; e.g. "Zabbix server" */
		return FAIL;

	if (':' != *pr++)
		return FAIL;

	if (0 == *replace_to_alloc)
	{
		*replace_to_alloc = 128;
		*replace_to = zbx_malloc(*replace_to, *replace_to_alloc);
	}

	zbx_strncpy_alloc(replace_to, replace_to_alloc, &replace_to_offset, pl, pr - pl);

	/* an item key */
	if (SUCCEED != parse_key(&pr, &key))
		return FAIL;

	substitute_key_macros(&key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);
	zbx_strcpy_alloc(replace_to, replace_to_alloc, &replace_to_offset, key);

	zbx_free(key);

	pl = pr;

	/* a trigger function with parameters */
	if ('.' != *pr++ || SUCCEED != parse_function(&pr, NULL, NULL) || '}' != *pr++)
		return FAIL;

	zbx_strncpy_alloc(replace_to, replace_to_alloc, &replace_to_offset, pl, pr - pl);

	*pos = pr - data - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_discovery_macros                                      *
 *                                                                            *
 * Parameters: data   - [IN/OUT] pointer to a buffer                          *
 *             jp_row - [IN] discovery data                                   *
 *             flags  - [IN] ZBX_MACRO_ANY - all LLD macros will be resolved  *
 *                            without validation of the value type            *
 *                           ZBX_MACRO_NUMERIC - values for LLD macros should *
 *                            be numeric                                      *
 *                           ZBX_MACRO_SIMPLE - LLD macros, located in the    *
 *                            item key parameters in simple macros will be    *
 *                            resolved considering quotes.                    *
 *                            Flag ZBX_MACRO_NUMERIC doesn't affect these     *
 *                            macros.                                         *
 *             error  - [OUT] should be not NULL if ZBX_MACRO_NUMERIC flag is *
 *                            set                                             *
 *                                                                            *
 * Return value: Always SUCCEED if numeric flag is not set, otherwise SUCCEED *
 *               if all discovery macros resolved to numeric values,          *
 *               otherwise FAIL with an error message.                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	substitute_discovery_macros(char **data, struct zbx_json_parse *jp_row, int flags,
		char *error, size_t max_error_len)
{
	const char	*__function_name = "substitute_discovery_macros";

	char		*replace_to = NULL, c;
	size_t		l, r, replace_to_alloc = 0;
	int		rc, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	for (l = 0; '\0' != (*data)[l]; l++)
	{
		if ('{' != (*data)[l])
			continue;

		r = l;

		/* substitute discovery macros, e.g. {#FSNAME} */
		if ('#' == (*data)[l + 1])
		{
			for (r += 2; SUCCEED == is_macro_char((*data)[r]); r++)
				;

			if ('}' != (*data)[r])
				continue;

			c = (*data)[r + 1];
			(*data)[r + 1] = '\0';

			if (SUCCEED != (rc = zbx_json_value_by_name_dyn(jp_row, &(*data)[l], &replace_to, &replace_to_alloc)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot substitute macro \"%s\": not found in value set",
						__function_name, *data + l);

				if (0 != (flags & ZBX_MACRO_NUMERIC))
				{
					zbx_snprintf(error, max_error_len, "no value for macro \"%s\"", *data + l);
					ret = FAIL;
				}
			}
			else if (0 != (flags & ZBX_MACRO_NUMERIC))
			{
				if (SUCCEED != is_double_suffix(replace_to))
				{
					zbx_snprintf(error, max_error_len, "macro \"%s\" value is not numeric", *data + l);
					ret = FAIL;
				}
			}

			(*data)[r + 1] = c;

			if (SUCCEED != ret)
				break;

			if (SUCCEED == rc)
				zbx_replace_string(data, l, &r, replace_to);
		}
		/* substitute LLD macros, located in the item key parameters in simple macros */
		/* e.g. {Zabbix server:ifAlias[{#SNMPINDEX}].last(0)}                         */
		else if (0 != (flags & ZBX_MACRO_SIMPLE))
		{
			if (SUCCEED != substitute_discovery_macros_simple(*data, &replace_to, &replace_to_alloc,
					&r, jp_row))
			{
				continue;
			}

			zbx_replace_string(data, l, &r, replace_to);
		}

		l = r;
	}

	zbx_free(replace_to);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s data:'%s'", __function_name, zbx_result_string(ret), *data);

	return ret;
}

typedef struct
{
	zbx_uint64_t		*hostid;
	DC_ITEM			*dc_item;
	struct zbx_json_parse	*jp_row;
	int			macro_type;
}
replace_key_param_data_t;

/******************************************************************************
 *                                                                            *
 * Function: replace_key_param                                                *
 *                                                                            *
 * Comments: auxiliary function for substitute_key_macros()                   *
 *                                                                            *
 ******************************************************************************/
static char	*replace_key_param(const char *data, int key_type, int level, int num, int quoted, void *cb_data)
{
	replace_key_param_data_t	*replace_key_param_data = (replace_key_param_data_t *)cb_data;
	zbx_uint64_t			*hostid = replace_key_param_data->hostid;
	DC_ITEM				*dc_item = replace_key_param_data->dc_item;
	struct zbx_json_parse		*jp_row = replace_key_param_data->jp_row;
	int				macro_type = replace_key_param_data->macro_type;
	char				*param;

	if (ZBX_KEY_TYPE_ITEM == key_type && 0 == level)
		return NULL;

	if (NULL == strchr(data, '{'))
		return NULL;

	param = zbx_strdup(NULL, data);

	if (0 != level)
		unquote_key_param(param);

	if (NULL == jp_row)
		substitute_simple_macros(NULL, NULL, NULL, NULL, hostid, NULL, dc_item, &param, macro_type, NULL, 0);
	else
		substitute_discovery_macros(&param, jp_row, ZBX_MACRO_ANY, NULL, 0);

	if (0 != level)
		quote_key_param(&param, quoted);

	return param;
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_key_macros                                            *
 *                                                                            *
 * Purpose: safely substitutes macros in parameters of an item key and OID    *
 *                                                                            *
 * Example:  key                     | macro       | result                   *
 *          -------------------------+-------------+-----------------         *
 *           echo.sh[{$MACRO}]       | a           | echo.sh[a]               *
 *           echo.sh["{$MACRO}"]     | a           | echo.sh["a"]             *
 *           echo.sh[{$MACRO}]       | "a"         | echo.sh["\"a\""]         *
 *           echo.sh["{$MACRO}"]     | "a"         | echo.sh["\"a\""]         *
 *           echo.sh[{$MACRO}]       | a,b         | echo.sh["a,b"]           *
 *           echo.sh["{$MACRO}"]     | a,b         | echo.sh["a,b"]           *
 *           ifInOctets.{#SNMPINDEX} | 1           | ifInOctets.1             *
 *                                                                            *
 ******************************************************************************/
int	substitute_key_macros(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item, struct zbx_json_parse *jp_row,
		int macro_type, char *error, size_t maxerrlen)
{
	const char			*__function_name = "substitute_key_macros";
	replace_key_param_data_t	replace_key_param_data;
	int				key_type, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	replace_key_param_data.hostid = hostid;
	replace_key_param_data.dc_item = dc_item;
	replace_key_param_data.jp_row = jp_row;
	replace_key_param_data.macro_type = macro_type;

	switch (macro_type)
	{
		case MACRO_TYPE_ITEM_KEY:
			key_type = ZBX_KEY_TYPE_ITEM;
			break;
		case MACRO_TYPE_SNMP_OID:
			key_type = ZBX_KEY_TYPE_OID;
			break;
		default:
			assert(0);
	}

	ret = replace_key_params_dyn(data, key_type, replace_key_param, &replace_key_param_data, error, maxerrlen);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s data:'%s'", __function_name, zbx_result_string(ret), *data);

	return ret;
}
