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
#include "zbxserver.h"
#include "evalfunc.h"
#include "db.h"
#include "log.h"
#include "zbxalgo.h"
#include "valuecache.h"
#include "macrofunc.h"
#include "zbxregexp.h"
#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/tree.h>
#	include <libxml/xpath.h>
#	include <libxml/xmlerror.h>

typedef struct
{
	char	*buf;
	size_t	len;
}
zbx_libxml_error_t;
#endif

/* The following definitions are used to identify the request field */
/* for various value getters grouped by their scope:                */

/* DBget_item_value(), get_interface_value() */
#define ZBX_REQUEST_HOST_IP		1
#define ZBX_REQUEST_HOST_DNS		2
#define ZBX_REQUEST_HOST_CONN		3
#define ZBX_REQUEST_HOST_PORT		4

/* DBget_item_value() */
#define ZBX_REQUEST_HOST_ID		101
#define ZBX_REQUEST_HOST_HOST		102
#define ZBX_REQUEST_HOST_NAME		103
#define ZBX_REQUEST_HOST_DESCRIPTION	104
#define ZBX_REQUEST_ITEM_ID		105
#define ZBX_REQUEST_ITEM_NAME		106
#define ZBX_REQUEST_ITEM_NAME_ORIG	107
#define ZBX_REQUEST_ITEM_KEY		108
#define ZBX_REQUEST_ITEM_KEY_ORIG	109
#define ZBX_REQUEST_ITEM_DESCRIPTION	110
#define ZBX_REQUEST_PROXY_NAME		111
#define ZBX_REQUEST_PROXY_DESCRIPTION	112

/* DBget_history_log_value() */
#define ZBX_REQUEST_ITEM_LOG_DATE	201
#define ZBX_REQUEST_ITEM_LOG_TIME	202
#define ZBX_REQUEST_ITEM_LOG_AGE	203
#define ZBX_REQUEST_ITEM_LOG_SOURCE	204
#define ZBX_REQUEST_ITEM_LOG_SEVERITY	205
#define ZBX_REQUEST_ITEM_LOG_NSEVERITY	206
#define ZBX_REQUEST_ITEM_LOG_EVENTID	207

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
int	get_N_functionid(const char *expression, int N_functionid, zbx_uint64_t *functionid, const char **end)
{
	enum state_t {NORMAL, ID}	state = NORMAL;
	int				num = 0, ret = FAIL;
	const char			*c, *p_functionid = NULL;

	for (c = expression; '\0' != *c; c++)
	{
		if ('{' == *c)
		{
			/* skip user macros */
			if ('$' == c[1])
			{
				int	macro_r, context_l, context_r;

				if (SUCCEED == zbx_user_macro_parse(c, &macro_r, &context_l, &context_r))
					c += macro_r;
				else
					c++;

				continue;
			}

			state = ID;
			p_functionid = c + 1;
		}
		else if ('}' == *c && ID == state && NULL != p_functionid)
		{
			if (SUCCEED == is_uint64_n(p_functionid, c - p_functionid, functionid))
			{
				if (++num == N_functionid)
				{
					if (NULL != end)
						*end = c + 1;

					ret = SUCCEED;
					break;
				}
			}

			state = NORMAL;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_functionids                                                  *
 *                                                                            *
 * Purpose: get identifiers of the functions used in expression               *
 *                                                                            *
 * Parameters: functionids - [OUT] the resulting vector of function ids       *
 *             expression  - [IN] null terminated trigger expression          *
 *                           '{11}=1 & {2346734}>5'                           *
 *                                                                            *
 ******************************************************************************/
void	get_functionids(zbx_vector_uint64_t *functionids, const char *expression)
{
	zbx_token_t	token;
	int		pos = 0;
	zbx_uint64_t	functionid;

	if ('\0' == *expression)
		return;

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
				is_uint64_n(expression + token.token.l + 1, token.token.r - token.token.l - 1,
						&functionid);
				zbx_vector_uint64_append(functionids, functionid);
				/* break; is not missing here */
			case ZBX_TOKEN_USER_MACRO:
			case ZBX_TOKEN_SIMPLE_MACRO:
			case ZBX_TOKEN_MACRO:
				pos = token.token.r;
				break;
		}
	}

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
 * Function: get_expanded_expression                                          *
 *                                                                            *
 * Purpose: get trigger expression with expanded user macros                  *
 *                                                                            *
 * Comments: removes ' ', '\r', '\n' and '\t' for easier number search        *
 *                                                                            *
 ******************************************************************************/
static char	*get_expanded_expression(const char *expression)
{
	char	*expression_ex;

	if (NULL != (expression_ex = DCexpression_expand_user_macros(expression, NULL)))
		zbx_remove_whitespace(expression_ex);

	return expression_ex;
}


/******************************************************************************
 *                                                                            *
 * Function: get_trigger_expression_constant                                  *
 *                                                                            *
 * Purpose: get constant from a trigger expression corresponding a given      *
 *          reference from trigger name                                       *
 *                                                                            *
 * Parameters: expression - [IN] trigger expression, source of constants      *
 *             reference  - [IN] reference from a trigger name ($1, $2, ...)  *
 *             constant   - [OUT] pointer to the constant's location in       *
 *                            trigger expression or empty string if there is  *
 *                            no corresponding constant                       *
 *             length     - [OUT] length of constant                          *
 *                                                                            *
 ******************************************************************************/
static void	get_trigger_expression_constant(const char *expression, const zbx_token_reference_t *reference,
		const char **constant, size_t *length)
{
	size_t		pos;
	zbx_strloc_t	number;
	int		index;

	for (pos = 0, index = 1; SUCCEED == zbx_number_find(expression, pos, &number); pos = number.r + 1, index++)
	{
		if (index < reference->index)
			continue;

		*length = number.r - number.l + 1;
		*constant = expression + number.l;
		return;
	}

	*length = 0;
	*constant = "";
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

	tmp = (char *)zbx_malloc(tmp, tmp_alloc);

	for (l = 0; '\0' != (*expression)[l]; l++)
	{
		if ('{' != (*expression)[l])
		{
			zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, (*expression)[l]);
			continue;
		}

		/* skip user macros */
		if ('$' == (*expression)[l + 1])
		{
			int	macro_r, context_l, context_r;

			if (SUCCEED == zbx_user_macro_parse(*expression + l, &macro_r, &context_l, &context_r))
			{
				zbx_strncpy_alloc(&tmp, &tmp_alloc, &tmp_offset, *expression + l, macro_r + 1);
				l += macro_r;
				continue;
			}

			zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, '{');
			zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, '$');
			l++;
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
 ******************************************************************************/
static void	item_description(char **data, const char *key, zbx_uint64_t hostid)
{
	AGENT_REQUEST	request;
	const char	*param;
	char		c, *p, *m, *n, *str_out = NULL, *replace_to = NULL;
	int		macro_r, context_l, context_r;

	init_request(&request);

	if (SUCCEED != parse_item_key(key, &request))
		goto out;

	p = *data;

	while (NULL != (m = strchr(p, '$')))
	{
		if (m > p && '{' == *(m - 1) && FAIL != zbx_user_macro_parse(m - 1, &macro_r, &context_l, &context_r))
		{
			/* user macros */

			n = m + macro_r;
			c = *n;
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
		else if ('1' <= *(m + 1) && *(m + 1) <= '9')
		{
			/* macros $1, $2, ... */

			*m = '\0';
			str_out = zbx_strdcat(str_out, p);
			*m++ = '$';

			if (NULL != (param = get_rparam(&request, *m - '0' - 1)))
				str_out = zbx_strdcat(str_out, param);

			p = m + 1;
		}
		else
		{
			/* just a dollar sign */

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
out:
	free_request(&request);
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_host_value                                                 *
 *                                                                            *
 * Purpose: request host name by hostid                                       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_host_value(zbx_uint64_t hostid, char **replace_to, const char *field_name)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect(
			"select %s"
			" from hosts"
			" where hostid=" ZBX_FS_UI64,
			field_name, hostid);

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

	*replace_to = (char *)zbx_realloc(*replace_to, replace_to_alloc);
	**replace_to = '\0';

	sql = (char *)zbx_malloc(sql, sql_alloc);

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

	*replace_to = (char *)zbx_realloc(*replace_to, replace_to_alloc);
	**replace_to = '\0';

	sql = (char *)zbx_malloc(sql, sql_alloc);

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
 * Function: get_interface_value                                              *
 *                                                                            *
 * Purpose: retrieve a particular value associated with the interface         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_interface_value(zbx_uint64_t hostid, zbx_uint64_t itemid, char **replace_to, int request)
{
	int		res;
	DC_INTERFACE	interface;

	if (SUCCEED != (res = DCconfig_get_interface(&interface, hostid, itemid)))
		return res;

	switch (request)
	{
		case ZBX_REQUEST_HOST_IP:
			*replace_to = zbx_strdup(*replace_to, interface.ip_orig);
			break;
		case ZBX_REQUEST_HOST_DNS:
			*replace_to = zbx_strdup(*replace_to, interface.dns_orig);
			break;
		case ZBX_REQUEST_HOST_CONN:
			*replace_to = zbx_strdup(*replace_to, interface.addr);
			break;
		case ZBX_REQUEST_HOST_PORT:
			*replace_to = zbx_strdup(*replace_to, interface.port_orig);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			res = FAIL;
	}

	return res;
}

static int	get_host_value(zbx_uint64_t itemid, char **replace_to, int request)
{
	int	ret;
	DC_HOST	host;

	DCconfig_get_hosts_by_itemids(&host, &itemid, &ret, 1);

	if (FAIL == ret)
		return FAIL;

	switch (request)
	{
		case ZBX_REQUEST_HOST_ID:
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, host.hostid);
			break;
		case ZBX_REQUEST_HOST_HOST:
			*replace_to = zbx_strdup(*replace_to, host.host);
			break;
		case ZBX_REQUEST_HOST_NAME:
			*replace_to = zbx_strdup(*replace_to, host.name);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_substitute_item_name_macros                                  *
 *                                                                            *
 * Purpose: substitute key macros and use it to substitute item name macros if*
 *          item name is specified                                            *
 *                                                                            *
 * Parameters: dc_item    - [IN] item information used in substitution        *
 *             name       - [IN] optional item name to substitute             *
 *             replace_to - [OUT] expanded item name or key if name is absent *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_item_name_macros(DC_ITEM *dc_item, const char *name, char **replace_to)
{
	int	ret;
	char	*key;

	if (INTERFACE_TYPE_UNKNOWN == dc_item->interface.type)
		ret = DCconfig_get_interface(&dc_item->interface, dc_item->host.hostid, 0);
	else
		ret = SUCCEED;

	if (ret == FAIL)
		return FAIL;

	key = zbx_strdup(NULL, dc_item->key_orig);
	substitute_key_macros(&key, NULL, dc_item, NULL, MACRO_TYPE_ITEM_KEY,
			NULL, 0);

	if (NULL != name)
	{
		*replace_to = zbx_strdup(*replace_to, name);
		item_description(replace_to, key, dc_item->host.hostid);
		zbx_free(key);
	}
	else	/* ZBX_REQUEST_ITEM_KEY */
	{
		zbx_free(*replace_to);
		*replace_to = key;
	}

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
	zbx_uint64_t	proxy_hostid;
	int		ret = FAIL, errcode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	switch (request)
	{
		case ZBX_REQUEST_HOST_IP:
		case ZBX_REQUEST_HOST_DNS:
		case ZBX_REQUEST_HOST_CONN:
		case ZBX_REQUEST_HOST_PORT:
			return get_interface_value(0, itemid, replace_to, request);
		case ZBX_REQUEST_HOST_ID:
		case ZBX_REQUEST_HOST_HOST:
		case ZBX_REQUEST_HOST_NAME:
			return get_host_value(itemid, replace_to, request);
	}

	result = DBselect(
			"select h.proxy_hostid,h.description,i.itemid,i.name,i.key_,i.description"
			" from items i"
				" join hosts h on h.hostid=i.hostid"
			" where i.itemid=" ZBX_FS_UI64, itemid);

	if (NULL != (row = DBfetch(result)))
	{
		switch (request)
		{
			case ZBX_REQUEST_HOST_DESCRIPTION:
				*replace_to = zbx_strdup(*replace_to, row[1]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_ID:
				*replace_to = zbx_strdup(*replace_to, row[2]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_NAME:
				DCconfig_get_items_by_itemids(&dc_item, &itemid, &errcode, 1);

				if (SUCCEED == errcode)
					ret = zbx_substitute_item_name_macros(&dc_item, row[3], replace_to);

				DCconfig_clean_items(&dc_item, &errcode, 1);
				break;
			case ZBX_REQUEST_ITEM_KEY:
				DCconfig_get_items_by_itemids(&dc_item, &itemid, &errcode, 1);

				if (SUCCEED == errcode)
					ret = zbx_substitute_item_name_macros(&dc_item, NULL, replace_to);

				DCconfig_clean_items(&dc_item, &errcode, 1);
				break;
			case ZBX_REQUEST_ITEM_NAME_ORIG:
				*replace_to = zbx_strdup(*replace_to, row[3]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_KEY_ORIG:
				*replace_to = zbx_strdup(*replace_to, row[4]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_DESCRIPTION:
				*replace_to = zbx_strdup(*replace_to, row[5]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_PROXY_NAME:
				ZBX_DBROW2UINT64(proxy_hostid, row[0]);

				if (0 == proxy_hostid)
				{
					*replace_to = zbx_strdup(*replace_to, "");
					ret = SUCCEED;
				}
				else
					ret = DBget_host_value(proxy_hostid, replace_to, "host");
				break;
			case ZBX_REQUEST_PROXY_DESCRIPTION:
				ZBX_DBROW2UINT64(proxy_hostid, row[0]);

				if (0 == proxy_hostid)
				{
					*replace_to = zbx_strdup(*replace_to, "");
					ret = SUCCEED;
				}
				else
					ret = DBget_host_value(proxy_hostid, replace_to, "description");
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
 * Parameters: triggerid    - [IN] trigger identifier from database           *
 *             replace_to   - [IN/OUT] pointer to result buffer               *
 *             problem_only - [IN] selected trigger status:                   *
 *                             0 - TRIGGER_VALUE_PROBLEM and TRIGGER_VALUE_OK *
 *                             1 - TRIGGER_VALUE_PROBLEM                      *
 *             acknowledged - [IN] acknowledged event or not                  *
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
 * Function: DBget_dchecks_value_by_event                                     *
 *                                                                            *
 * Purpose: retrieve discovery rule check value by event and field name       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	DBget_dchecks_value_by_event(const DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DSERVICE:
			result = DBselect("select %s from dchecks c,dservices s"
					" where c.dcheckid=s.dcheckid and s.dserviceid=" ZBX_FS_UI64,
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
					" where r.druleid=h.druleid and h.dhostid=" ZBX_FS_UI64,
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
	int			ret = FAIL, errcode = FAIL;
	zbx_timespec_t		ts = {clock, ns};
	zbx_history_record_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED != errcode || ITEM_VALUE_TYPE_LOG != item.value_type)
		goto out;

	if (SUCCEED != zbx_vc_get_value(itemid, item.value_type, &ts, &value))
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
static int	DBitem_lastvalue(const char *expression, char **lastvalue, int N_functionid, int raw)
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

		ts.sec = time(NULL);
		ts.ns = 999999999;

		value_type = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(valuemapid, row[1]);

		if (SUCCEED == zbx_vc_get_value(itemid, value_type, &ts, &vc_value))
		{
			char	tmp[MAX_BUFFER_LEN];

			zbx_history_value2str(tmp, sizeof(tmp), &vc_value.value, value_type);
			zbx_history_record_clear(&vc_value, value_type);

			if (0 == raw)
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
static int	DBitem_value(const char *expression, char **value, int N_functionid, int clock, int ns, int raw)
{
	const char	*__function_name = "DBitem_value";

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
		zbx_timespec_t		ts = {clock, ns};
		zbx_history_record_t	vc_value;

		value_type = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(valuemapid, row[1]);

		if (SUCCEED == zbx_vc_get_value(itemid, value_type, &ts, &vc_value))
		{
			char	tmp[MAX_BUFFER_LEN];

			zbx_history_value2str(tmp, sizeof(tmp), &vc_value.value, value_type);
			zbx_history_record_clear(&vc_value, value_type);

			if (0 == raw)
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
static void	get_escalation_history(zbx_uint64_t actionid, const DB_EVENT *event, const DB_EVENT *r_event,
			char **replace_to, const zbx_uint64_t *recipient_userid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*buf = NULL, *p;
	size_t		buf_alloc = ZBX_KIBIBYTE, buf_offset = 0;
	int		esc_step;
	unsigned char	type, status;
	time_t		now;
	zbx_uint64_t	userid;

	buf = (char *)zbx_malloc(buf, buf_alloc);

	zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Problem started: %s %s Age: %s\n",
			zbx_date2str(event->clock), zbx_time2str(event->clock),
			zbx_age2str(time(NULL) - event->clock));

	result = DBselect("select a.clock,a.alerttype,a.status,mt.description,a.sendto"
				",a.error,a.esc_step,a.userid,a.message"
			" from alerts a"
			" left join media_type mt"
				" on mt.mediatypeid=a.mediatypeid"
			" where a.eventid=" ZBX_FS_UI64
				" and a.actionid=" ZBX_FS_UI64
			" order by a.clock",
			event->eventid, actionid);

	while (NULL != (row = DBfetch(result)))
	{
		int	user_permit;

		now = atoi(row[0]);
		type = (unsigned char)atoi(row[1]);
		status = (unsigned char)atoi(row[2]);
		esc_step = atoi(row[6]);
		ZBX_DBROW2UINT64(userid, row[7]);
		user_permit = zbx_check_user_permissions(&userid, recipient_userid);

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
			const char	*description, *send_to, *user_name;

			description = (SUCCEED == DBis_null(row[3]) ? "" : row[3]);

			if (SUCCEED == user_permit)
			{
				send_to = row[4];
				user_name = zbx_user_string(userid);
			}
			else
			{
				send_to = "\"Inaccessible recipient details\"";
				user_name = "Inaccessible user";
			}

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s %s \"%s\"",
					description,	/* media type description */
					send_to,	/* historical recipient */
					user_name);	/* alert user full name */
		}

		if (ALERT_STATUS_FAILED == status)
		{
			/* alert error can be generated by SMTP Relay or other media and contain sensitive details */
			if (SUCCEED == user_permit)
				zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s", row[5]);
			else
				zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, " \"Inaccessible error message\"");
		}

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
 * Function: acknowledge_expand_action_names                                  *
 *                                                                            *
 * Purpose: expand acknowledge action flags into user readable list           *
 *                                                                            *
 * Parameters: str        - [IN/OUT]                                          *
 *             str_alloc  - [IN/OUT]                                          *
 *             str_offset - [IN/OUT]                                          *
 *             action       [IN] the acknowledge action flags                 *
 *                               (see ZBX_ACKNOWLEDGE_ACTION_* defines)       *
 *                                                                            *
 ******************************************************************************/
static void	acknowledge_expand_action_names(char **str, size_t *str_alloc, size_t *str_offset, int action)
{
	if (0 != (action & ZBX_ACKNOWLEDGE_ACTION_CLOSE_PROBLEM))
		zbx_strcpy_alloc(str, str_alloc, str_offset, "Close problem");
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
static void	get_event_ack_history(const DB_EVENT *event, char **replace_to, const zbx_uint64_t *recipient_userid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*buf = NULL;
	size_t		buf_alloc = ZBX_KIBIBYTE, buf_offset = 0;
	time_t		now;
	zbx_uint64_t	userid;
	int		action;

	if (0 == event->acknowledged)
	{
		*replace_to = zbx_strdup(*replace_to, "");
		return;
	}

	buf = (char *)zbx_malloc(buf, buf_alloc);
	*buf = '\0';

	result = DBselect("select clock,userid,message,action"
			" from acknowledges"
			" where eventid=" ZBX_FS_UI64 " order by clock",
			event->eventid);

	while (NULL != (row = DBfetch(result)))
	{
		const char	*user_name;

		now = atoi(row[0]);
		ZBX_STR2UINT64(userid, row[1]);
		action = atoi(row[3]);

		if (SUCCEED == zbx_check_user_permissions(&userid, recipient_userid))
			user_name = zbx_user_string(userid);
		else
			user_name = "Inaccessible user";

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
				"%s %s \"%s\"\n",
				zbx_date2str(now),
				zbx_time2str(now),
				user_name);

		if (ZBX_ACKNOWLEDGE_ACTION_NONE != action)
		{
			zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "Action: ");
			acknowledge_expand_action_names(&buf, &buf_alloc, &buf_offset, action);
			zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, '\n');
		}

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%s\n\n", row[2]);
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
#define MVAR_EVENT_NAME		MVAR_EVENT "NAME}"
#define MVAR_EVENT_TAGS			MVAR_EVENT "TAGS}"
#define MVAR_EVENT_RECOVERY		MVAR_EVENT "RECOVERY."		/* a prefix for all recovery event macros */
#define MVAR_EVENT_RECOVERY_DATE	MVAR_EVENT_RECOVERY "DATE}"
#define MVAR_EVENT_RECOVERY_ID		MVAR_EVENT_RECOVERY "ID}"
#define MVAR_EVENT_RECOVERY_STATUS	MVAR_EVENT_RECOVERY "STATUS}"	/* deprecated */
#define MVAR_EVENT_RECOVERY_TIME	MVAR_EVENT_RECOVERY "TIME}"
#define MVAR_EVENT_RECOVERY_VALUE	MVAR_EVENT_RECOVERY "VALUE}"	/* deprecated */
#define MVAR_EVENT_RECOVERY_TAGS	MVAR_EVENT_RECOVERY "TAGS}"

#define MVAR_ESC_HISTORY		"{ESC.HISTORY}"
#define MVAR_PROXY_NAME			"{PROXY.NAME}"
#define MVAR_PROXY_DESCRIPTION		"{PROXY.DESCRIPTION}"
#define MVAR_HOST_DNS			"{HOST.DNS}"
#define MVAR_HOST_CONN			"{HOST.CONN}"
#define MVAR_HOST_HOST			"{HOST.HOST}"
#define MVAR_HOST_ID			"{HOST.ID}"
#define MVAR_HOST_IP			"{HOST.IP}"
#define MVAR_IPADDRESS			"{IPADDRESS}"			/* deprecated */
#define MVAR_HOST_METADATA		"{HOST.METADATA}"
#define MVAR_HOST_NAME			"{HOST.NAME}"
#define MVAR_HOSTNAME			"{HOSTNAME}"			/* deprecated */
#define MVAR_HOST_DESCRIPTION		"{HOST.DESCRIPTION}"
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

#define MVAR_TRIGGER_DESCRIPTION		"{TRIGGER.DESCRIPTION}"
#define MVAR_TRIGGER_COMMENT			"{TRIGGER.COMMENT}"		/* deprecated */
#define MVAR_TRIGGER_ID				"{TRIGGER.ID}"
#define MVAR_TRIGGER_NAME			"{TRIGGER.NAME}"
#define MVAR_TRIGGER_NAME_ORIG			"{TRIGGER.NAME.ORIG}"
#define MVAR_TRIGGER_EXPRESSION			"{TRIGGER.EXPRESSION}"
#define MVAR_TRIGGER_EXPRESSION_RECOVERY	"{TRIGGER.EXPRESSION.RECOVERY}"
#define MVAR_TRIGGER_SEVERITY			"{TRIGGER.SEVERITY}"
#define MVAR_TRIGGER_NSEVERITY			"{TRIGGER.NSEVERITY}"
#define MVAR_TRIGGER_STATUS			"{TRIGGER.STATUS}"
#define MVAR_TRIGGER_STATE			"{TRIGGER.STATE}"
#define MVAR_TRIGGER_TEMPLATE_NAME		"{TRIGGER.TEMPLATE.NAME}"
#define MVAR_TRIGGER_HOSTGROUP_NAME		"{TRIGGER.HOSTGROUP.NAME}"
#define MVAR_STATUS				"{STATUS}"			/* deprecated */
#define MVAR_TRIGGER_VALUE			"{TRIGGER.VALUE}"
#define MVAR_TRIGGER_URL			"{TRIGGER.URL}"

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

#define MVAR_DISCOVERY_RULE_NAME	"{DISCOVERY.RULE.NAME}"
#define MVAR_DISCOVERY_SERVICE_NAME	"{DISCOVERY.SERVICE.NAME}"
#define MVAR_DISCOVERY_SERVICE_PORT	"{DISCOVERY.SERVICE.PORT}"
#define MVAR_DISCOVERY_SERVICE_STATUS	"{DISCOVERY.SERVICE.STATUS}"
#define MVAR_DISCOVERY_SERVICE_UPTIME	"{DISCOVERY.SERVICE.UPTIME}"
#define MVAR_DISCOVERY_DEVICE_IPADDRESS	"{DISCOVERY.DEVICE.IPADDRESS}"
#define MVAR_DISCOVERY_DEVICE_DNS	"{DISCOVERY.DEVICE.DNS}"
#define MVAR_DISCOVERY_DEVICE_STATUS	"{DISCOVERY.DEVICE.STATUS}"
#define MVAR_DISCOVERY_DEVICE_UPTIME	"{DISCOVERY.DEVICE.UPTIME}"

#define MVAR_ALERT_SENDTO		"{ALERT.SENDTO}"
#define MVAR_ALERT_SUBJECT		"{ALERT.SUBJECT}"
#define MVAR_ALERT_MESSAGE		"{ALERT.MESSAGE}"

#define MVAR_ACK_MESSAGE                "{ACK.MESSAGE}"
#define MVAR_ACK_TIME	                "{ACK.TIME}"
#define MVAR_ACK_DATE	                "{ACK.DATE}"
#define MVAR_USER_FULLNAME          	"{USER.FULLNAME}"

#define STR_UNKNOWN_VARIABLE		"*UNKNOWN*"

/* macros that can be indexed */
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
	MVAR_HOST_HOST, MVAR_HOSTNAME, MVAR_HOST_NAME, MVAR_HOST_DESCRIPTION, MVAR_PROXY_NAME, MVAR_PROXY_DESCRIPTION,
	MVAR_HOST_CONN, MVAR_HOST_DNS, MVAR_HOST_IP, MVAR_HOST_PORT, MVAR_IPADDRESS, MVAR_HOST_ID,
	MVAR_ITEM_ID, MVAR_ITEM_NAME, MVAR_ITEM_NAME_ORIG, MVAR_ITEM_DESCRIPTION,
	MVAR_ITEM_KEY, MVAR_ITEM_KEY_ORIG, MVAR_TRIGGER_KEY,
	MVAR_ITEM_LASTVALUE,
	MVAR_ITEM_STATE,
	MVAR_ITEM_VALUE,
	MVAR_ITEM_LOG_DATE, MVAR_ITEM_LOG_TIME, MVAR_ITEM_LOG_AGE, MVAR_ITEM_LOG_SOURCE,
	MVAR_ITEM_LOG_SEVERITY, MVAR_ITEM_LOG_NSEVERITY, MVAR_ITEM_LOG_EVENTID,
	NULL
};

/* macros that are supported as simple macro host and item key */
static const char	*simple_host_macros[] = {MVAR_HOST_HOST, MVAR_HOSTNAME, NULL};
static const char	*simple_key_macros[] = {MVAR_ITEM_KEY, MVAR_TRIGGER_KEY, NULL};

/* macros that can be modified using macro functions */
static const char	*mod_macros[] = {MVAR_ITEM_VALUE, MVAR_ITEM_LASTVALUE, NULL};

typedef struct
{
	const char	*macro;
	int		idx;
} inventory_field_t;

static inventory_field_t	inventory_fields[] =
{
	{MVAR_INVENTORY_TYPE, 0},
	{MVAR_PROFILE_DEVICETYPE, 0},	/* deprecated */
	{MVAR_INVENTORY_TYPE_FULL, 1},
	{MVAR_INVENTORY_NAME, 2},
	{MVAR_PROFILE_NAME, 2},	/* deprecated */
	{MVAR_INVENTORY_ALIAS, 3},
	{MVAR_INVENTORY_OS, 4},
	{MVAR_PROFILE_OS, 4},	/* deprecated */
	{MVAR_INVENTORY_OS_FULL, 5},
	{MVAR_INVENTORY_OS_SHORT, 6},
	{MVAR_INVENTORY_SERIALNO_A, 7},
	{MVAR_PROFILE_SERIALNO, 7},	/* deprecated */
	{MVAR_INVENTORY_SERIALNO_B, 8},
	{MVAR_INVENTORY_TAG, 9},
	{MVAR_PROFILE_TAG, 9},	/* deprecated */
	{MVAR_INVENTORY_ASSET_TAG, 10},
	{MVAR_INVENTORY_MACADDRESS_A, 11},
	{MVAR_PROFILE_MACADDRESS, 11},	/* deprecated */
	{MVAR_INVENTORY_MACADDRESS_B, 12},
	{MVAR_INVENTORY_HARDWARE, 13},
	{MVAR_PROFILE_HARDWARE, 13},	/* deprecated */
	{MVAR_INVENTORY_HARDWARE_FULL, 14},
	{MVAR_INVENTORY_SOFTWARE, 15},
	{MVAR_PROFILE_SOFTWARE, 15},	/* deprecated */
	{MVAR_INVENTORY_SOFTWARE_FULL, 16},
	{MVAR_INVENTORY_SOFTWARE_APP_A, 17},
	{MVAR_INVENTORY_SOFTWARE_APP_B, 18},
	{MVAR_INVENTORY_SOFTWARE_APP_C, 19},
	{MVAR_INVENTORY_SOFTWARE_APP_D, 20},
	{MVAR_INVENTORY_SOFTWARE_APP_E, 21},
	{MVAR_INVENTORY_CONTACT, 22},
	{MVAR_PROFILE_CONTACT, 22},	/* deprecated */
	{MVAR_INVENTORY_LOCATION, 23},
	{MVAR_PROFILE_LOCATION, 23},	/* deprecated */
	{MVAR_INVENTORY_LOCATION_LAT, 24},
	{MVAR_INVENTORY_LOCATION_LON, 25},
	{MVAR_INVENTORY_NOTES, 26},
	{MVAR_PROFILE_NOTES, 26},	/* deprecated */
	{MVAR_INVENTORY_CHASSIS, 27},
	{MVAR_INVENTORY_MODEL, 28},
	{MVAR_INVENTORY_HW_ARCH, 29},
	{MVAR_INVENTORY_VENDOR, 30},
	{MVAR_INVENTORY_CONTRACT_NUMBER, 31},
	{MVAR_INVENTORY_INSTALLER_NAME, 32},
	{MVAR_INVENTORY_DEPLOYMENT_STATUS, 33},
	{MVAR_INVENTORY_URL_A, 34},
	{MVAR_INVENTORY_URL_B, 35},
	{MVAR_INVENTORY_URL_C, 36},
	{MVAR_INVENTORY_HOST_NETWORKS, 37},
	{MVAR_INVENTORY_HOST_NETMASK, 38},
	{MVAR_INVENTORY_HOST_ROUTER, 39},
	{MVAR_INVENTORY_OOB_IP, 40},
	{MVAR_INVENTORY_OOB_NETMASK, 41},
	{MVAR_INVENTORY_OOB_ROUTER, 42},
	{MVAR_INVENTORY_HW_DATE_PURCHASE, 43},
	{MVAR_INVENTORY_HW_DATE_INSTALL, 44},
	{MVAR_INVENTORY_HW_DATE_EXPIRY, 45},
	{MVAR_INVENTORY_HW_DATE_DECOMM, 46},
	{MVAR_INVENTORY_SITE_ADDRESS_A, 47},
	{MVAR_INVENTORY_SITE_ADDRESS_B, 48},
	{MVAR_INVENTORY_SITE_ADDRESS_C, 49},
	{MVAR_INVENTORY_SITE_CITY, 50},
	{MVAR_INVENTORY_SITE_STATE, 51},
	{MVAR_INVENTORY_SITE_COUNTRY, 52},
	{MVAR_INVENTORY_SITE_ZIP, 53},
	{MVAR_INVENTORY_SITE_RACK, 54},
	{MVAR_INVENTORY_SITE_NOTES, 55},
	{MVAR_INVENTORY_POC_PRIMARY_NAME, 56},
	{MVAR_INVENTORY_POC_PRIMARY_EMAIL, 57},
	{MVAR_INVENTORY_POC_PRIMARY_PHONE_A, 58},
	{MVAR_INVENTORY_POC_PRIMARY_PHONE_B, 59},
	{MVAR_INVENTORY_POC_PRIMARY_CELL, 60},
	{MVAR_INVENTORY_POC_PRIMARY_SCREEN, 61},
	{MVAR_INVENTORY_POC_PRIMARY_NOTES, 62},
	{MVAR_INVENTORY_POC_SECONDARY_NAME, 63},
	{MVAR_INVENTORY_POC_SECONDARY_EMAIL, 64},
	{MVAR_INVENTORY_POC_SECONDARY_PHONE_A, 65},
	{MVAR_INVENTORY_POC_SECONDARY_PHONE_B, 66},
	{MVAR_INVENTORY_POC_SECONDARY_CELL, 67},
	{MVAR_INVENTORY_POC_SECONDARY_SCREEN, 68},
	{MVAR_INVENTORY_POC_SECONDARY_NOTES, 69},
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

			if (SUCCEED != get_N_itemid(expression, N_functionid, &itemid))
				return FAIL;

			return DCget_host_inventory_value_by_itemid(itemid, replace_to, inventory_fields[i].idx);
		}
	}

	return FAIL;
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
			return DCget_host_inventory_value_by_itemid(itemid, replace_to, inventory_fields[i].idx);
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: compare_tags                                                     *
 *                                                                            *
 * Purpose: comparison function to sort tags by tag/value                     *
 *                                                                            *
 ******************************************************************************/
static int	compare_tags(const void *d1, const void *d2)
{
	int	ret;

	const zbx_tag_t	*tag1 = *(const zbx_tag_t **)d1;
	const zbx_tag_t	*tag2 = *(const zbx_tag_t **)d2;

	if (0 == (ret = zbx_strcmp_natural(tag1->tag, tag2->tag)))
		ret = zbx_strcmp_natural(tag1->value, tag2->value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_tags                                                   *
 *                                                                            *
 * Purpose: format event tags string in format <tag1>[:<value1>], ...         *
 *                                                                            *
 * Parameters: event        [IN] the event                                    *
 *             replace_to - [OUT] replacement string                          *
 *                                                                            *
 ******************************************************************************/
static void	get_event_tags(const DB_EVENT *event, char **replace_to)
{
	size_t			replace_to_offset = 0, replace_to_alloc = 0;
	int			i;
	zbx_vector_ptr_t	tags;

	if (0 == event->tags.values_num)
	{
		*replace_to = zbx_strdup(*replace_to, "");
		return;
	}

	zbx_free(*replace_to);

	/* copy tags to temporary vector for sorting */

	zbx_vector_ptr_create(&tags);
	zbx_vector_ptr_reserve(&tags, event->tags.values_num);

	for (i = 0; i < event->tags.values_num; i++)
		zbx_vector_ptr_append(&tags, event->tags.values[i]);

	zbx_vector_ptr_sort(&tags, compare_tags);

	for (i = 0; i < tags.values_num; i++)
	{
		const zbx_tag_t	*tag = (const zbx_tag_t *)tags.values[i];

		if (0 != i)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");

		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, tag->tag);

		if ('\0' != *tag->value)
		{
			zbx_chrcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ':');
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, tag->value);
		}
	}

	zbx_vector_ptr_destroy(&tags);
}

/******************************************************************************
 *                                                                            *
 * Function: get_recovery_event_value                                         *
 *                                                                            *
 * Purpose: request recovery event value by macro                             *
 *                                                                            *
 ******************************************************************************/
static void	get_recovery_event_value(const char *macro, const DB_EVENT *r_event, char **replace_to)
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
	else if (EVENT_SOURCE_TRIGGERS == r_event->source && 0 == strcmp(macro, MVAR_EVENT_RECOVERY_TAGS))
	{
		get_event_tags(r_event, replace_to);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: get_current_event_value                                          *
 *                                                                            *
 * Purpose: request current event value by macro                              *
 *                                                                            *
 ******************************************************************************/
static void	get_current_event_value(const char *macro, const DB_EVENT *event, char **replace_to)
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
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_value                                                  *
 *                                                                            *
 * Purpose: request event value by macro                                      *
 *                                                                            *
 ******************************************************************************/
static void	get_event_value(const char *macro, const DB_EVENT *event, char **replace_to,
			const zbx_uint64_t *recipient_userid)
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
	else if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_ACK_HISTORY))
		{
			get_event_ack_history(event, replace_to, recipient_userid);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_ACK_STATUS))
		{
			*replace_to = zbx_strdup(*replace_to, event->acknowledged ? "Yes" : "No");
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGS))
		{
			get_event_tags(event, replace_to);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: is_indexed_macro                                                 *
 *                                                                            *
 * Purpose: check if a token contains indexed macro                           *
 *                                                                            *
 ******************************************************************************/
static int	is_indexed_macro(const char *str, const zbx_token_t *token)
{
	const char	*p;

	switch (token->type)
	{
		case ZBX_TOKEN_MACRO:
			p = str + token->token.r - 1;
			break;
		case ZBX_TOKEN_FUNC_MACRO:
			p = str + token->data.func_macro.macro.r - 1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	return '1' <= *p && *p <= '9' ? 1 : 0;
}

/******************************************************************************
 *                                                                            *
 * Function: macro_in_list                                                    *
 *                                                                            *
 * Purpose: check if a macro in string is one of the list and extract index   *
 *                                                                            *
 * Parameters: str          - [IN] string containing potential macro          *
 *             strloc       - [IN] part of the string to check                *
 *             macros       - [IN] list of allowed macros (without indices)   *
 *             N_functionid - [OUT] index of the macro in string (if valid)   *
 *                                                                            *
 * Return value: unindexed macro from the allowed list or NULL                *
 *                                                                            *
 * Comments: example: N_functionid is untouched if function returns NULL, for *
 *           a valid unindexed macro N_function is 1.                         *
 *                                                                            *
 ******************************************************************************/
static const char	*macro_in_list(const char *str, zbx_strloc_t strloc, const char **macros, int *N_functionid)
{
	const char	**macro, *m;
	size_t		i;

	for (macro = macros; NULL != *macro; macro++)
	{
		for (m = *macro, i = strloc.l; '\0' != *m && i <= strloc.r && str[i] == *m; m++, i++)
			;

		/* check whether macro has ended while strloc hasn't or vice-versa */
		if (('\0' == *m && i <= strloc.r) || ('\0' != *m && i > strloc.r))
			continue;

		/* strloc either fully matches macro... */
		if ('\0' == *m)
		{
			if (NULL != N_functionid)
				*N_functionid = 1;

			break;
		}

		/* ...or there is a mismatch, check if it's in a pre-last character and it's an index */
		if (i == strloc.r - 1 && '1' <= str[i] && str[i] <= '9' && str[i + 1] == *m && '\0' == *(m + 1))
		{
			if (NULL != N_functionid)
				*N_functionid = str[i] - '0';

			break;
		}
	}

	return *macro;
}

/******************************************************************************
 *                                                                            *
 * Function: get_trigger_function_value                                       *
 *                                                                            *
 * Purpose: trying to evaluate a trigger function                             *
 *                                                                            *
 * Parameters: expression - [IN] trigger expression, source of hostnames and  *
 *                            item keys for {HOST.HOST} and {ITEM.KEY} macros *
 *             replace_to - [OUT] evaluation result                           *
 *             data       - [IN] string containing simple macro               *
 *             macro      - [IN] simple macro token location in string        *
 *                                                                            *
 * Return value: SUCCEED - successfully evaluated or invalid macro(s) in host *
 *                           and/or item key positions (in the latter case    *
 *                           replace_to remains unchanged and simple macro    *
 *                           shouldn't be replaced with anything)             *
 *               FAIL    - evaluation failed and macro has to be replaced     *
 *                           with STR_UNKNOWN_VARIABLE ("*UNKNOWN*")          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: example: " {Zabbix server:{ITEM.KEY1}.last(0)} " to " 1.34 "     *
 *                                                                            *
 ******************************************************************************/
static int	get_trigger_function_value(const char *expression, char **replace_to, char *data,
		const zbx_token_simple_macro_t *simple_macro)
{
	char	*host = NULL, *key = NULL;
	int	N_functionid, ret = FAIL;

	if (NULL != macro_in_list(data, simple_macro->host, simple_host_macros, &N_functionid))
	{
		if (SUCCEED != DBget_trigger_value(expression, &host, N_functionid, ZBX_REQUEST_HOST_HOST))
			goto out;
	}

	if (NULL != macro_in_list(data, simple_macro->key, simple_key_macros, &N_functionid))
	{
		if (SUCCEED != DBget_trigger_value(expression, &key, N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG))
			goto out;
	}

	data[simple_macro->host.r + 1] = '\0';
	data[simple_macro->key.r + 1] = '\0';
	data[simple_macro->func_param.l] = '\0';
	data[simple_macro->func_param.r] = '\0';

	ret = evaluate_macro_function(replace_to, (NULL == host ? data + simple_macro->host.l : host),
			(NULL == key ? data + simple_macro->key.l : key), data + simple_macro->func.l,
			data + simple_macro->func_param.l + 1);

	data[simple_macro->host.r + 1] = ':';
	data[simple_macro->key.r + 1] = '.';
	data[simple_macro->func_param.l] = '(';
	data[simple_macro->func_param.r] = ')';
out:
	zbx_free(host);
	zbx_free(key);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: cache_trigger_hostids                                            *
 *                                                                            *
 * Purpose: cache host identifiers referenced by trigger expression           *
 *                                                                            *
 * Parameters: hostids             - [OUT] the host identifier cache          *
 *             expression          - [IN] the trigger expression              *
 *             recovery_expression - [IN] the trigger recovery expression     *
 *                                        (can be empty)                      *
 *                                                                            *
 ******************************************************************************/
static void	cache_trigger_hostids(zbx_vector_uint64_t *hostids, const char *expression,
		const char *recovery_expression)
{
	if (0 == hostids->values_num)
	{
		zbx_vector_uint64_t	functionids;

		zbx_vector_uint64_create(&functionids);
		get_functionids(&functionids, expression);
		get_functionids(&functionids, recovery_expression);
		DCget_hostids_by_functionids(&functionids, hostids);
		zbx_vector_uint64_destroy(&functionids);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: cache_item_hostid                                                *
 *                                                                            *
 * Purpose: cache host identifier referenced by an item or a lld-rule         *
 *                                                                            *
 * Parameters: hostids - [OUT] the host identifier cache                      *
 *             itemid  - [IN]  the item identifier                            *
 *                                                                            *
 ******************************************************************************/
static void	cache_item_hostid(zbx_vector_uint64_t *hostids, zbx_uint64_t itemid)
{
	if (0 == hostids->values_num)
	{
		DC_ITEM	item;
		int	errcode;

		DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

		if (SUCCEED == errcode)
			zbx_vector_uint64_append(hostids, item.host.hostid);

		DCconfig_clean_items(&item, &errcode, 1);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: get_trigger_severity_name                                        *
 *                                                                            *
 * Purpose: get trigger severity name                                         *
 *                                                                            *
 * Parameters: trigger    - [IN] a trigger data with priority field;          *
 *                               TRIGGER_SEVERITY_*                           *
 *             replace_to - [OUT] pointer to a buffer that will receive       *
 *                          a null-terminated trigger severity string         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_trigger_severity_name(unsigned char priority, char **replace_to)
{
	zbx_config_t	cfg;

	if (TRIGGER_SEVERITY_COUNT <= priority)
		return FAIL;

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SEVERITY_NAME);

	*replace_to = zbx_strdup(*replace_to, cfg.severity_name[priority]);

	zbx_config_clean(&cfg);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: wrap_negative_double_suffix                                      *
 *                                                                            *
 * Purpose: wrap a replacement string that represents a negative number in    *
 *          parentheses (for instance, turn "-123.456M" into "(-123.456M)")   *
 *                                                                            *
 * Parameters: replace_to       - [IN/OUT] replacement string                 *
 *             replace_to_alloc - [IN/OUT] number of allocated bytes          *
 *                                                                            *
 ******************************************************************************/
static void	wrap_negative_double_suffix(char **replace_to, size_t *replace_to_alloc)
{
	size_t	replace_to_len;

	if ('-' != (*replace_to)[0])
		return;

	replace_to_len = strlen(*replace_to);

	if (NULL != replace_to_alloc && *replace_to_alloc >= replace_to_len + 3)
	{
		memmove(*replace_to + 1, *replace_to, replace_to_len);
	}
	else
	{
		char	*buffer;

		if (NULL != replace_to_alloc)
			*replace_to_alloc = replace_to_len + 3;

		buffer = (char *)zbx_malloc(NULL, replace_to_len + 3);

		memcpy(buffer + 1, *replace_to, replace_to_len);

		zbx_free(*replace_to);
		*replace_to = buffer;
	}

	(*replace_to)[0] = '(';
	(*replace_to)[replace_to_len + 1] = ')';
	(*replace_to)[replace_to_len + 2] = '\0';
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
int	substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, const DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, char **data, int macro_type, char *error, int maxerrlen)
{
	const char		*__function_name = "substitute_simple_macros";

	char			c, *replace_to = NULL, sql[64];
	const char		*m, *replace = NULL;
	int			N_functionid, indexed_macro, require_numeric, ret, res = SUCCEED, pos = 0, found,
				raw_value;
	size_t			data_alloc, data_len, replace_len;
	DC_INTERFACE		interface;
	zbx_vector_uint64_t	hostids;
	zbx_token_t		token;
	zbx_token_search_t	token_search;
	char			*expression = NULL;

	if (NULL == data || NULL == *data || '\0' == **data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:EMPTY", __function_name);
		return res;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	if (0 != (macro_type & MACRO_TYPE_TRIGGER_DESCRIPTION))
		token_search = ZBX_TOKEN_SEARCH_REFERENCES;
	else
		token_search = ZBX_TOKEN_SEARCH_BASIC;

	if (SUCCEED != zbx_token_find(*data, pos, &token, token_search))
		return res;

	zbx_vector_uint64_create(&hostids);

	data_alloc = data_len = strlen(*data) + 1;

	for (found = SUCCEED; SUCCEED == res && SUCCEED == found;
			found = zbx_token_find(*data, pos, &token, token_search))
	{
		indexed_macro = 0;
		require_numeric = 0;
		N_functionid = 1;
		raw_value = 0;

		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
			case ZBX_TOKEN_LLD_MACRO:
				/* neither lld or {123123} macros are processed by this function, skip them */
				pos = token.token.r + 1;
				continue;
			case ZBX_TOKEN_MACRO:
				if (0 == (indexed_macro = is_indexed_macro(*data, &token)))
				{
					/* Theoretically we could do m = macro_in_list() here as well to validate */
					/* token and get unindexed macro equivalent, but it will be a double work */
					/* since we will pass m through a lot of strcmp(m, MVAR_*) checks anyway, */
					/* plus ex_macros is a long list. For now, we rely on this surgery. */
					m = *data + token.token.l;
					c = (*data)[token.token.r + 1];
					(*data)[token.token.r + 1] = '\0';
				}
				else if (NULL == (m = macro_in_list(*data, token.token, ex_macros, &N_functionid)))
				{
					pos = token.token.r + 1;
					continue;
				}
				break;
			case ZBX_TOKEN_FUNC_MACRO:
				raw_value = 1;
				indexed_macro = is_indexed_macro(*data, &token);
				if (NULL == (m = macro_in_list(*data, token.data.func_macro.macro, mod_macros,
						&N_functionid)))
				{
					/* Ignore functions with macros not supporting them, but do not skip the */
					/* whole token, nested macro should be resolved in this case. */
					pos++;
					continue;
				}
				break;
			case ZBX_TOKEN_USER_MACRO:
				/* To avoid *data modification DCget_user_macro() should be replaced with a function */
				/* that takes initial *data string and token.data.user_macro instead of m as params. */
				m = *data + token.token.l;
				c = (*data)[token.token.r + 1];
				(*data)[token.token.r + 1] = '\0';
				break;
			case ZBX_TOKEN_SIMPLE_MACRO:
				if (0 == (macro_type & (MACRO_TYPE_MESSAGE_NORMAL | MACRO_TYPE_MESSAGE_RECOVERY |
							MACRO_TYPE_MESSAGE_ACK)) ||
						EVENT_SOURCE_TRIGGERS != ((NULL != r_event) ? r_event : event)->source)
				{
					pos++;
					continue;
				}
				/* These macros (and probably all other in the future) must be resolved using only */
				/* information stored in token.data union. For now, force crash if they rely on m. */
				m = NULL;
				break;
			case ZBX_TOKEN_REFERENCE:
				/* These macros (and probably all other in the future) must be resolved using only */
				/* information stored in token.data union. For now, force crash if they rely on m. */
				m = NULL;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				res = FAIL;
				continue;
		}

		ret = SUCCEED;

		if (0 != (macro_type & (MACRO_TYPE_MESSAGE_NORMAL | MACRO_TYPE_MESSAGE_RECOVERY |
				MACRO_TYPE_MESSAGE_ACK)))
		{
			const DB_EVENT	*c_event;

			c_event = ((NULL != r_event) ? r_event : event);

			if (EVENT_SOURCE_TRIGGERS == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_trigger_hostids(&hostids, c_event->trigger.expression,
							c_event->trigger.recovery_expression);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.token.r;
				}
				else if (ZBX_TOKEN_SIMPLE_MACRO == token.type)
				{
					ret = get_trigger_function_value(c_event->trigger.expression, &replace_to,
							*data, &token.data.simple_macro);
				}
				else if (0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid);
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
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DESCRIPTION);
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
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
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
					ret = DBitem_lastvalue(c_event->trigger.expression, &replace_to, N_functionid,
							raw_value);
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
							c_event->clock, c_event->ns, raw_value);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_DESCRIPTION) ||
						0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.comments);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, &replace_to, MACRO_TYPE_TRIGGER_COMMENTS, error,
							maxerrlen);
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
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						replace_to = zbx_strdup(replace_to,
								c_event->trigger.recovery_expression);
						DCexpand_trigger_expression(&replace_to);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
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
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, &replace_to, MACRO_TYPE_TRIGGER_DESCRIPTION, error,
							maxerrlen);
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
					replace_to = zbx_strdup(replace_to,
							zbx_trigger_value_string(c_event->trigger.value));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_SEVERITY))
				{
					ret = get_trigger_severity_name(c_event->trigger.priority, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_TEMPLATE_NAME))
				{
					ret = DBget_trigger_template_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.url);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, &replace_to, MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", c_event->trigger.value);
				}
				else if (0 == strcmp(m, MVAR_ACK_MESSAGE))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_ACK) && NULL != ack)
						replace_to = zbx_strdup(replace_to, ack->message);
				}
				else if (0 == strcmp(m, MVAR_ACK_TIME))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_ACK) && NULL != ack)
						replace_to = zbx_strdup(replace_to, zbx_time2str(ack->clock));
				}
				else if (0 == strcmp(m, MVAR_ACK_DATE))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_ACK) && NULL != ack)
						replace_to = zbx_strdup(replace_to, zbx_date2str(ack->clock));
				}
				else if (0 == strcmp(m, MVAR_USER_FULLNAME))
				{
					if (0 != (macro_type & MACRO_TYPE_MESSAGE_ACK) && NULL != ack)
					{
						const char	*user_name;

						if (SUCCEED == zbx_check_user_permissions(&ack->userid, userid))
							user_name = zbx_user_string(ack->userid);
						else
							user_name = "Inaccessible user";

						replace_to = zbx_strdup(replace_to, user_name);
					}
				}
			}
			else if (EVENT_SOURCE_INTERNAL == c_event->source && EVENT_OBJECT_TRIGGER == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_trigger_hostids(&hostids, c_event->trigger.expression,
							c_event->trigger.recovery_expression);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.token.r;
				}
				else if (0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid);
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
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DESCRIPTION);
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
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
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
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = DBget_trigger_value(c_event->trigger.expression, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_DESCRIPTION) ||
						0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.comments);
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, &replace_to, MACRO_TYPE_TRIGGER_COMMENTS, error,
							maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.expression);
					DCexpand_trigger_expression(&replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						replace_to = zbx_strdup(replace_to,
								c_event->trigger.recovery_expression);
						DCexpand_trigger_expression(&replace_to);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
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
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, &replace_to, MACRO_TYPE_TRIGGER_DESCRIPTION, error,
							maxerrlen);
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
					ret = get_trigger_severity_name(c_event->trigger.priority, &replace_to);
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
					substitute_simple_macros(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, &replace_to, MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_DISCOVERY == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					DCget_user_macro(NULL, 0, m, &replace_to);
					pos = token.token.r;
				}
				else if (0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid);
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
					if (SUCCEED == (ret = DBget_dchecks_value_by_event(c_event, &replace_to,
							"c.type")))
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
							ret = DBget_host_value(proxy_hostid, &replace_to, "host");
					}
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					if (SUCCEED == (ret = DBget_dhost_value_by_event(c_event, &replace_to,
							"r.proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
						{
							replace_to = zbx_strdup(replace_to, "");
						}
						else
						{
							ret = DBget_host_value(proxy_hostid, &replace_to,
									"description");
						}
					}
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_AUTO_REGISTRATION == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					DCget_user_macro(NULL, 0, m, &replace_to);
					pos = token.token.r;
				}
				else if (0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid);
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
							ret = DBget_host_value(proxy_hostid, &replace_to, "host");
					}
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					if (SUCCEED == (ret = get_autoreg_value_by_event(c_event, &replace_to,
							"proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
						{
							replace_to = zbx_strdup(replace_to, "");
						}
						else
						{
							ret = DBget_host_value(proxy_hostid, &replace_to,
									"description");
						}
					}
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_INTERNAL == c_event->source &&
					EVENT_OBJECT_ITEM == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_item_hostid(&hostids, c_event->objectid);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.token.r;
				}
				else if (0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_DESCRIPTION);
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
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
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
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_INTERNAL == c_event->source &&
					EVENT_OBJECT_LLDRULE == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_item_hostid(&hostids, c_event->objectid);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.token.r;
				}
				else if (0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				}
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					get_escalation_history(*actionid, event, r_event, &replace_to, userid);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					get_event_value(m, event, &replace_to, userid);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_DESCRIPTION);
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
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
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
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = DBget_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_PROXY_DESCRIPTION);
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
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_trigger_hostids(&hostids, event->trigger.expression,
							event->trigger.recovery_expression);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.token.r;
				}
				else if (ZBX_TOKEN_REFERENCE == token.type)
				{
					/* try to expand trigger expression if it hasn't been done yet */
					if (NULL == expression && NULL == (expression =
							get_expanded_expression(event->trigger.expression)))
					{
						/* expansion failed, reference substitution is impossible */
						token_search = ZBX_TOKEN_SEARCH_BASIC;
						continue;
					}

					get_trigger_expression_constant(expression, &token.data.reference, &replace,
							&replace_len);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
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
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = DBitem_value(event->trigger.expression, &replace_to, N_functionid,
							event->clock, event->ns, raw_value);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = DBitem_lastvalue(event->trigger.expression, &replace_to, N_functionid,
							raw_value);
				}
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_TRIGGER_EXPRESSION))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					/* When processing trigger expressions the user macros are already expanded. */
					/* An unexpanded user macro means either unknown macro or macro value        */
					/* validation failure.                                                       */

					if (NULL != error)
					{
						zbx_snprintf(error, maxerrlen, "Invalid macro '%.*s' value",
								token.token.r - token.token.l + 1,
								*data + token.token.l);
					}

					res = FAIL;
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
					replace_to = zbx_dsprintf(replace_to, "%d", event->value);
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_TRIGGER_URL))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_trigger_hostids(&hostids, event->trigger.expression,
							event->trigger.recovery_expression);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.token.r;
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP))
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
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
				}
			}
		}
		else if (0 == indexed_macro &&
				0 != (macro_type & (MACRO_TYPE_ITEM_KEY | MACRO_TYPE_PARAMS_FIELD | MACRO_TYPE_LLD_FILTER)))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_item->host.hostid, 1, m, &replace_to);
				pos = token.token.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_item->host.host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_item->host.name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.ip_orig);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_IP);
				}
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.dns_orig);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_DNS);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.addr);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_CONN);
				}
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_INTERFACE_ADDR))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.token.r;
			}
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
		else if (0 != (macro_type & (MACRO_TYPE_COMMON | MACRO_TYPE_SNMP_OID)))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				if (NULL != hostid)
					DCget_user_macro(hostid, 1, m, &replace_to);
				else
					DCget_user_macro(NULL, 0, m, &replace_to);

				pos = token.token.r;
			}
		}
		else if (0 != (macro_type & MACRO_TYPE_ITEM_EXPRESSION))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				require_numeric = 1;
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.token.r;
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_SCRIPT))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.token.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.addr);
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_HTTPTEST_FIELD))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.token.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.addr);
			}
		}
		else if (0 == indexed_macro && (0 != (macro_type & (MACRO_TYPE_HTTP_RAW | MACRO_TYPE_HTTP_JSON |
				MACRO_TYPE_HTTP_XML))))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				pos = token.token.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
			{
				replace_to = zbx_strdup(replace_to, dc_host->host);
			}
			else if (0 == strcmp(m, MVAR_HOST_NAME))
			{
				replace_to = zbx_strdup(replace_to, dc_host->name);
			}
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = DCconfig_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.addr);
			}
			else if (0 == strcmp(m, MVAR_ITEM_ID))
			{
				replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, dc_item->itemid);
			}
			else if (0 == strcmp(m, MVAR_ITEM_KEY))
			{
				replace_to = zbx_strdup(replace_to, dc_item->key);
			}
			else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
			{
				replace_to = zbx_strdup(replace_to, dc_item->key_orig);
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_ALERT))
		{
			if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				replace_to = zbx_strdup(replace_to, alert->sendto);
			else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				replace_to = zbx_strdup(replace_to, alert->subject);
			else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				replace_to = zbx_strdup(replace_to, alert->message);
		}
		else if (0 == indexed_macro && 0 != (macro_type & MACRO_TYPE_JMX_ENDPOINT))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type)
			{
				DCget_user_macro(&dc_item->host.hostid, 1, m, &replace_to);
				pos = token.token.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_item->host.host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_item->host.name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.ip_orig);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_IP);
				}
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.dns_orig);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_DNS);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_strdup(replace_to, dc_item->interface.addr);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_CONN);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_PORT))
			{
				if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
				{
					replace_to = zbx_dsprintf(replace_to, "%u", dc_item->interface.port);
				}
				else
				{
					ret = get_interface_value(dc_item->host.hostid, dc_item->itemid, &replace_to,
							ZBX_REQUEST_HOST_PORT);
				}
			}
		}
		else if (macro_type & MACRO_TYPE_TRIGGER_TAG)
		{
			if (EVENT_SOURCE_TRIGGERS == event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type)
				{
					cache_trigger_hostids(&hostids, event->trigger.expression,
							event->trigger.recovery_expression);
					DCget_user_macro(hostids.values, hostids.values_num, m, &replace_to);
					pos = token.token.r;
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = DBget_trigger_value(event->trigger.expression, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP))
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
					ret = DBitem_lastvalue(event->trigger.expression, &replace_to, N_functionid,
							raw_value);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = DBitem_value(event->trigger.expression, &replace_to, N_functionid,
							event->clock, event->ns, raw_value);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
				{
					ret = get_host_inventory(m, event->trigger.expression, &replace_to,
							N_functionid);
				}
			}
		}

		if (0 != (macro_type & MACRO_TYPE_HTTP_JSON) && NULL != replace_to)
			zbx_json_escape(&replace_to);

		if (0 != (macro_type & MACRO_TYPE_HTTP_XML) && NULL != replace_to)
		{
			char	*replace_to_esc;

			replace_to_esc = xml_escape_dyn(replace_to);
			zbx_free(replace_to);
			replace_to = replace_to_esc;
		}

		if (ZBX_TOKEN_FUNC_MACRO == token.type && NULL != replace_to)
		{
			if (SUCCEED != (ret = zbx_calculate_macro_function(*data, &token.data.func_macro, &replace_to)))
				zbx_free(replace_to);
		}

		if (1 == require_numeric && NULL != replace_to)
		{
			if (SUCCEED == (res = is_double_suffix(replace_to, ZBX_FLAG_DOUBLE_SUFFIX)))
			{
				wrap_negative_double_suffix(&replace_to, NULL);
			}
			else if (NULL != error)
			{
				zbx_snprintf(error, maxerrlen, "Macro '%.*s' value is not numeric",
						token.token.r - token.token.l + 1, *data + token.token.l);
			}
		}

		if (FAIL == ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot resolve macro '%.*s'", token.token.r - token.token.l + 1,
					*data + token.token.l);
			replace_to = zbx_strdup(replace_to, STR_UNKNOWN_VARIABLE);
		}

		if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_MACRO == token.type && 0 == indexed_macro))
			(*data)[token.token.r + 1] = c;

		if (NULL != replace_to)
		{
			pos = token.token.r;

			pos += zbx_replace_mem_dyn(data, &data_alloc, &data_len, token.token.l,
					token.token.r - token.token.l + 1, replace_to, strlen(replace_to));
			zbx_free(replace_to);
		}
		else if (NULL != replace)
		{
			pos = token.token.r;

			pos += zbx_replace_mem_dyn(data, &data_alloc, &data_len, token.token.l,
					token.token.r - token.token.l + 1, replace, replace_len);

			replace = NULL;
		}

		pos++;
	}

	zbx_free(expression);
	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End %s() data:'%s'", __function_name, *data);

	return res;
}

static int	extract_expression_functionids(zbx_vector_uint64_t *functionids, const char *expression)
{
	const char	*bl, *br;
	zbx_uint64_t	functionid;

	for (bl = strchr(expression, '{'); NULL != bl; bl = strchr(bl, '{'))
	{
		if (NULL == (br = strchr(bl, '}')))
			break;

		if (SUCCEED != is_uint64_n(bl + 1, br - bl - 1, &functionid))
			break;

		zbx_vector_uint64_append(functionids, functionid);

		bl = br + 1;
	}

	return (NULL == bl ? SUCCEED : FAIL);
}

static void	zbx_extract_functionids(zbx_vector_uint64_t *functionids, zbx_vector_ptr_t *triggers)
{
	const char	*__function_name = "zbx_extract_functionids";

	DC_TRIGGER	*tr;
	int		i, values_num_save;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __function_name, triggers->values_num);

	for (i = 0; i < triggers->values_num; i++)
	{
		const char	*error_expression = NULL;

		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		values_num_save = functionids->values_num;

		if (SUCCEED != extract_expression_functionids(functionids, tr->expression))
		{
			error_expression = tr->expression;
		}
		else if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode &&
				SUCCEED != extract_expression_functionids(functionids, tr->recovery_expression))
		{
			error_expression = tr->recovery_expression;
		}

		if (NULL != error_expression)
		{
			tr->new_error = zbx_dsprintf(tr->new_error, "Invalid expression [%s]", error_expression);
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
	DC_TRIGGER	*trigger;
	int		start_index;
	int		count;
}
zbx_trigger_func_position_t;

/******************************************************************************
 *                                                                            *
 * Function: expand_trigger_macros                                            *
 *                                                                            *
 * Purpose: expand macros in a trigger expression                             *
 *                                                                            *
 * Parameters: event - The trigger event structure                            *
 *             trigger - The trigger where to expand macros in                *
 *                                                                            *
 * Author: Andrea Biscuola                                                    *
 *                                                                            *
 ******************************************************************************/
static int	expand_trigger_macros(DB_EVENT *event, DC_TRIGGER *trigger, char *error, size_t maxerrlen)
{
	if (FAIL == substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			&trigger->expression, MACRO_TYPE_TRIGGER_EXPRESSION, error, maxerrlen))
	{
		return FAIL;
	}

	if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == trigger->recovery_mode)
	{
		if (FAIL == substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&trigger->recovery_expression, MACRO_TYPE_TRIGGER_EXPRESSION, error, maxerrlen))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_link_triggers_with_functions                                 *
 *                                                                            *
 * Purpose: triggers links with functions                                     *
 *                                                                            *
 * Parameters: triggers_func_pos - [IN/OUT] pointer to the list of triggers   *
 *                                 with functions position in functionids     *
 *                                 array                                      *
 *             functionids       - [IN/OUT] array of function IDs             *
 *             trigger_order     - [IN] array of triggers                     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_link_triggers_with_functions(zbx_vector_ptr_t *triggers_func_pos, zbx_vector_uint64_t *functionids,
		zbx_vector_ptr_t *trigger_order)
{
	const char		*__function_name = "zbx_link_triggers_with_functions";

	zbx_vector_uint64_t	funcids;
	DC_TRIGGER		*tr;
	DB_EVENT		ev;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trigger_order_num:%d", __function_name, trigger_order->values_num);

	zbx_vector_uint64_create(&funcids);
	zbx_vector_uint64_reserve(&funcids, functionids->values_num);

	ev.object = EVENT_OBJECT_TRIGGER;

	for (i = 0; i < trigger_order->values_num; i++)
	{
		zbx_trigger_func_position_t	*tr_func_pos;

		tr = (DC_TRIGGER *)trigger_order->values[i];

		if (NULL != tr->new_error)
			continue;

		ev.value = tr->value;

		expand_trigger_macros(&ev, tr, NULL, 0);

		if (SUCCEED == extract_expression_functionids(&funcids, tr->expression))
		{
			tr_func_pos = (zbx_trigger_func_position_t *)zbx_malloc(NULL, sizeof(zbx_trigger_func_position_t));
			tr_func_pos->trigger = tr;
			tr_func_pos->start_index = functionids->values_num;
			tr_func_pos->count = funcids.values_num;

			zbx_vector_uint64_append_array(functionids, funcids.values, funcids.values_num);
			zbx_vector_ptr_append(triggers_func_pos, tr_func_pos);
		}

		zbx_vector_uint64_clear(&funcids);
	}

	zbx_vector_uint64_destroy(&funcids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() triggers_func_pos_num:%d", __function_name,
			triggers_func_pos->values_num);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_determine_items_in_expressions                               *
 *                                                                            *
 * Purpose: mark triggers that use one of the items in problem expression     *
 *          with ZBX_DC_TRIGGER_PROBLEM_EXPRESSION flag                       *
 *                                                                            *
 * Parameters: trigger_order - [IN/OUT] pointer to the list of triggers       *
 *             itemids       - [IN] array of item IDs                         *
 *             item_num      - [IN] number of items                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_determine_items_in_expressions(zbx_vector_ptr_t *trigger_order, const zbx_uint64_t *itemids, int item_num)
{
	zbx_vector_ptr_t	triggers_func_pos;
	zbx_vector_uint64_t	functionids, itemids_sorted;
	DC_FUNCTION		*functions = NULL;
	int			*errcodes = NULL, t, f;

	zbx_vector_uint64_create(&itemids_sorted);
	zbx_vector_uint64_append_array(&itemids_sorted, itemids, item_num);
	zbx_vector_uint64_sort(&itemids_sorted, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&triggers_func_pos);
	zbx_vector_ptr_reserve(&triggers_func_pos, trigger_order->values_num);

	zbx_vector_uint64_create(&functionids);
	zbx_vector_uint64_reserve(&functionids, item_num);

	zbx_link_triggers_with_functions(&triggers_func_pos, &functionids, trigger_order);

	functions = (DC_FUNCTION *)zbx_malloc(functions, sizeof(DC_FUNCTION) * functionids.values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * functionids.values_num);

	DCconfig_get_functions_by_functionids(functions, functionids.values, errcodes, functionids.values_num);

	for (t = 0; t < triggers_func_pos.values_num; t++)
	{
		zbx_trigger_func_position_t	*func_pos = (zbx_trigger_func_position_t *)triggers_func_pos.values[t];

		for (f = func_pos->start_index; f < func_pos->start_index + func_pos->count; f++)
		{
			if (FAIL != zbx_vector_uint64_bsearch(&itemids_sorted, functions[f].itemid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				func_pos->trigger->flags |= ZBX_DC_TRIGGER_PROBLEM_EXPRESSION;
				break;
			}
		}
	}

	DCconfig_clean_functions(functions, errcodes, functionids.values_num);
	zbx_free(errcodes);
	zbx_free(functions);

	zbx_vector_ptr_clear_ext(&triggers_func_pos, zbx_ptr_free);
	zbx_vector_ptr_destroy(&triggers_func_pos);

	zbx_vector_uint64_clear(&functionids);
	zbx_vector_uint64_destroy(&functionids);

	zbx_vector_uint64_clear(&itemids_sorted);
	zbx_vector_uint64_destroy(&itemids_sorted);
}

typedef struct
{
	/* input data */
	zbx_uint64_t	itemid;
	char		*function;
	char		*parameter;
	zbx_timespec_t	timespec;

	/* output data */
	char		*value;
	char		*error;
}
zbx_func_t;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_func_t	*func;
}
zbx_ifunc_t;

static zbx_hash_t	func_hash_func(const void *data)
{
	const zbx_func_t	*func = (const zbx_func_t *)data;
	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&func->itemid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(func->function, strlen(func->function), hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(func->parameter, strlen(func->parameter), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&func->timespec.sec, sizeof(func->timespec.sec), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&func->timespec.ns, sizeof(func->timespec.ns), hash);

	return hash;
}

static int	func_compare_func(const void *d1, const void *d2)
{
	const zbx_func_t	*func1 = (const zbx_func_t *)d1;
	const zbx_func_t	*func2 = (const zbx_func_t *)d2;
	int			ret;

	ZBX_RETURN_IF_NOT_EQUAL(func1->itemid, func2->itemid);

	if (0 != (ret = strcmp(func1->function, func2->function)))
		return ret;

	if (0 != (ret = strcmp(func1->parameter, func2->parameter)))
		return ret;

	ZBX_RETURN_IF_NOT_EQUAL(func1->timespec.sec, func2->timespec.sec);
	ZBX_RETURN_IF_NOT_EQUAL(func1->timespec.ns, func2->timespec.ns);

	return 0;
}

static void	func_clean(void *ptr)
{
	zbx_func_t	*func = (zbx_func_t *)ptr;

	zbx_free(func->function);
	zbx_free(func->parameter);
	zbx_free(func->value);
	zbx_free(func->error);
}

static void	zbx_populate_function_items(zbx_vector_uint64_t *functionids, zbx_hashset_t *funcs,
		zbx_hashset_t *ifuncs, zbx_vector_ptr_t *triggers)
{
	const char	*__function_name = "zbx_populate_function_items";

	int		i, j;
	DC_TRIGGER	*tr;
	DC_FUNCTION	*functions = NULL;
	int		*errcodes = NULL;
	zbx_ifunc_t	ifunc_local;
	zbx_func_t	*func, func_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() functionids_num:%d", __function_name, functionids->values_num);

	func_local.value = NULL;
	func_local.error = NULL;

	functions = (DC_FUNCTION *)zbx_malloc(functions, sizeof(DC_FUNCTION) * functionids->values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * functionids->values_num);

	DCconfig_get_functions_by_functionids(functions, functionids->values, errcodes, functionids->values_num);

	for (i = 0; i < functionids->values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		func_local.itemid = functions[i].itemid;

		if (FAIL != (j = zbx_vector_ptr_bsearch(triggers, &functions[i].triggerid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			tr = (DC_TRIGGER *)triggers->values[j];
			func_local.timespec = tr->timespec;
		}
		else
		{
			func_local.timespec.sec = 0;
			func_local.timespec.ns = 0;
		}

		func_local.function = functions[i].function;
		func_local.parameter = functions[i].parameter;

		if (NULL == (func = (zbx_func_t *)zbx_hashset_search(funcs, &func_local)))
		{
			func = (zbx_func_t *)zbx_hashset_insert(funcs, &func_local, sizeof(func_local));
			func->function = zbx_strdup(NULL, func_local.function);
			func->parameter = zbx_strdup(NULL, func_local.parameter);
		}

		ifunc_local.functionid = functions[i].functionid;
		ifunc_local.func = func;
		zbx_hashset_insert(ifuncs, &ifunc_local, sizeof(ifunc_local));
	}

	DCconfig_clean_functions(functions, errcodes, functionids->values_num);

	zbx_free(errcodes);
	zbx_free(functions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ifuncs_num:%d", __function_name, ifuncs->num_data);
}

static void	zbx_evaluate_item_functions(zbx_hashset_t *funcs, zbx_vector_ptr_t *unknown_msgs)
{
	const char	*__function_name = "zbx_evaluate_item_functions";

	DC_ITEM			*items = NULL;
	char			value[MAX_BUFFER_LEN], *error = NULL;
	int			i;
	zbx_func_t		*func;
	zbx_vector_uint64_t	itemids;
	int			*errcodes = NULL;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() funcs_num:%d", __function_name, funcs->num_data);

	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_reserve(&itemids, funcs->num_data);

	zbx_hashset_iter_reset(funcs, &iter);
	while (NULL != (func = (zbx_func_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_append(&itemids, func->itemid);

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	items = (DC_ITEM *)zbx_malloc(items, sizeof(DC_ITEM) * (size_t)itemids.values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * (size_t)itemids.values_num);

	DCconfig_get_items_by_itemids(items, itemids.values, errcodes, itemids.values_num);

	zbx_hashset_iter_reset(funcs, &iter);
	while (NULL != (func = (zbx_func_t *)zbx_hashset_iter_next(&iter)))
	{
		int	ret_unknown = 0;	/* flag raised if current function evaluates to ZBX_UNKNOWN */
		char	*unknown_msg;

		i = zbx_vector_uint64_bsearch(&itemids, func->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (SUCCEED != errcodes[i])
		{
			func->error = zbx_dsprintf(func->error, "Cannot evaluate function \"%s(%s)\":"
					" item does not exist.",
					func->function, func->parameter);
			continue;
		}

		/* do not evaluate if the item is disabled or belongs to a disabled host */

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

		/* If the item is NOTSUPPORTED then evaluation is allowed for:   */
		/*   - time-based functions and nodata(). Their values can be    */
		/*     evaluated to regular numbers even for NOTSUPPORTED items. */
		/*   - other functions. Result of evaluation is ZBX_UNKNOWN.     */

		if (ITEM_STATE_NOTSUPPORTED == items[i].state && FAIL == evaluatable_for_notsupported(func->function))
		{
			/* compose and store 'unknown' message for future use */
			unknown_msg = zbx_dsprintf(NULL,
					"Cannot evaluate function \"%s:%s.%s(%s)\": item is not supported.",
					items[i].host.host, items[i].key_orig, func->function, func->parameter);

			zbx_free(func->error);
			zbx_vector_ptr_append(unknown_msgs, unknown_msg);
			ret_unknown = 1;
		}

		if (0 == ret_unknown && SUCCEED != evaluate_function(value, &items[i], func->function,
				func->parameter, func->timespec.sec, &error))
		{
			/* compose and store error message for future use */
			if (NULL != error)
			{
				unknown_msg = zbx_dsprintf(NULL,
						"Cannot evaluate function \"%s:%s.%s(%s)\": %s.",
						items[i].host.host, items[i].key_orig, func->function,
						func->parameter, error);

				zbx_free(func->error);
				zbx_free(error);
			}
			else
			{
				unknown_msg = zbx_dsprintf(NULL,
						"Cannot evaluate function \"%s:%s.%s(%s)\".",
						items[i].host.host, items[i].key_orig,
						func->function, func->parameter);

				zbx_free(func->error);
			}

			zbx_vector_ptr_append(unknown_msgs, unknown_msg);
			ret_unknown = 1;
		}

		if (0 == ret_unknown)
		{
			func->value = zbx_strdup(func->value, value);
		}
		else
		{
			/* write a special token of unknown value with 'unknown' message number, like */
			/* ZBX_UNKNOWN0, ZBX_UNKNOWN1 etc. not wrapped in () */
			func->value = zbx_dsprintf(func->value, ZBX_UNKNOWN_STR "%d",
					unknown_msgs->values_num - 1);
		}
	}

	DCconfig_clean_items(items, errcodes, itemids.values_num);
	zbx_vector_uint64_destroy(&itemids);

	zbx_free(errcodes);
	zbx_free(items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	substitute_expression_functions_results(zbx_hashset_t *ifuncs, char *expression, char **out,
		size_t *out_alloc, char **error)
{
	char			*br, *bl;
	size_t			out_offset = 0;
	zbx_uint64_t		functionid;
	zbx_func_t		*func;
	zbx_ifunc_t		*ifunc;

	for (br = expression, bl = strchr(expression, '{'); NULL != bl; bl = strchr(bl, '{'))
	{
		*bl = '\0';
		zbx_strcpy_alloc(out, out_alloc, &out_offset, br);
		*bl = '{';

		if (NULL == (br = strchr(bl, '}')))
		{
			*error = zbx_strdup(*error, "Invalid trigger expression");
			return FAIL;
		}

		*br = '\0';

		ZBX_STR2UINT64(functionid, bl + 1);

		*br++ = '}';
		bl = br;

		if (NULL == (ifunc = (zbx_ifunc_t *)zbx_hashset_search(ifuncs, &functionid)))
		{
			*error = zbx_dsprintf(*error, "Cannot obtain function"
					" and item for functionid: " ZBX_FS_UI64, functionid);
			return FAIL;
		}

		func = ifunc->func;

		if (NULL != func->error)
		{
			*error = zbx_strdup(*error, func->error);
			return FAIL;
		}

		if (NULL == func->value)
		{
			*error = zbx_strdup(*error, "Unexpected error while processing a trigger expression");
			return FAIL;
		}

		if (SUCCEED != is_double_suffix(func->value, ZBX_FLAG_DOUBLE_SUFFIX) || '-' == *func->value)
		{
			zbx_chrcpy_alloc(out, out_alloc, &out_offset, '(');
			zbx_strcpy_alloc(out, out_alloc, &out_offset, func->value);
			zbx_chrcpy_alloc(out, out_alloc, &out_offset, ')');
		}
		else
			zbx_strcpy_alloc(out, out_alloc, &out_offset, func->value);
	}

	zbx_strcpy_alloc(out, out_alloc, &out_offset, br);

	return SUCCEED;
}

static void	zbx_substitute_functions_results(zbx_hashset_t *ifuncs, zbx_vector_ptr_t *triggers)
{
	const char		*__function_name = "zbx_substitute_functions_results";

	DC_TRIGGER		*tr;
	char			*out = NULL;
	size_t			out_alloc = TRIGGER_EXPRESSION_LEN_MAX;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ifuncs_num:%d tr_num:%d",
			__function_name, ifuncs->num_data, triggers->values_num);

	out = (char *)zbx_malloc(out, out_alloc);

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		if( SUCCEED != substitute_expression_functions_results(ifuncs, tr->expression, &out, &out_alloc,
				&tr->new_error))
		{
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
			continue;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() expression[%d]:'%s' => '%s'", __function_name, i,
				tr->expression, out);

		tr->expression = zbx_strdup(tr->expression, out);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode)
		{
			if (SUCCEED != substitute_expression_functions_results(ifuncs,
					tr->recovery_expression, &out, &out_alloc, &tr->new_error))
			{
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				continue;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "%s() recovery_expression[%d]:'%s' => '%s'", __function_name, i,
					tr->recovery_expression, out);

			tr->recovery_expression = zbx_strdup(tr->recovery_expression, out);
		}
	}

	zbx_free(out);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_functions                                             *
 *                                                                            *
 * Purpose: substitute expression functions with their values                 *
 *                                                                            *
 * Parameters: triggers - array of DC_TRIGGER structures                      *
 *             unknown_msgs - vector for storing messages for NOTSUPPORTED    *
 *                            items and failed functions                      *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev, Aleksandrs Saveljevs        *
 *                                                                            *
 * Comments: example: "({15}>10) or ({123}=1)" => "(26.416>10) or (0=1)"      *
 *                                                                            *
 ******************************************************************************/
static void	substitute_functions(zbx_vector_ptr_t *triggers, zbx_vector_ptr_t *unknown_msgs)
{
	const char		*__function_name = "substitute_functions";

	zbx_vector_uint64_t	functionids;
	zbx_hashset_t		ifuncs, funcs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&functionids);
	zbx_extract_functionids(&functionids, triggers);

	if (0 == functionids.values_num)
		goto empty;

	zbx_hashset_create(&ifuncs, triggers->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create_ext(&funcs, triggers->values_num, func_hash_func, func_compare_func, func_clean,
				ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_populate_function_items(&functionids, &funcs, &ifuncs, triggers);

	if (0 != ifuncs.num_data)
	{
		zbx_evaluate_item_functions(&funcs, unknown_msgs);
		zbx_substitute_functions_results(&ifuncs, triggers);
	}

	zbx_hashset_destroy(&ifuncs);
	zbx_hashset_destroy(&funcs);
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

	DB_EVENT		event;
	DC_TRIGGER		*tr;
	int			i;
	double			expr_result;
	zbx_vector_ptr_t	unknown_msgs;	    /* pointers to messages about origins of 'unknown' values */
	char			err[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __function_name, triggers->values_num);

	event.object = EVENT_OBJECT_TRIGGER;

	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		event.value = tr->value;

		if (SUCCEED != expand_trigger_macros(&event, tr, err, sizeof(err)))
		{
			tr->new_error = zbx_dsprintf(tr->new_error, "Cannot evaluate expression: %s", err);
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
		}
	}

	/* Assumption: most often there will be no NOTSUPPORTED items and function errors. */
	/* Therefore initialize error messages vector but do not reserve any space. */
	zbx_vector_ptr_create(&unknown_msgs);

	substitute_functions(triggers, &unknown_msgs);

	/* calculate new trigger values based on their recovery modes and expression evaluations */
	for (i = 0; i < triggers->values_num; i++)
	{
		tr = (DC_TRIGGER *)triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		if (SUCCEED != evaluate(&expr_result, tr->expression, err, sizeof(err), &unknown_msgs))
		{
			tr->new_error = zbx_strdup(tr->new_error, err);
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
			continue;
		}

		/* trigger expression evaluates to true, set PROBLEM value */
		if (SUCCEED != zbx_double_compare(expr_result, 0.0))
		{
			if (0 == (tr->flags & ZBX_DC_TRIGGER_PROBLEM_EXPRESSION))
			{
				/* trigger value should remain unchanged and no PROBLEM events should be generated if */
				/* problem expression evaluates to true, but trigger recalculation was initiated by a */
				/* time-based function or a new value of an item in recovery expression */
				tr->new_value = TRIGGER_VALUE_NONE;
			}
			else
				tr->new_value = TRIGGER_VALUE_PROBLEM;

			continue;
		}

		/* otherwise try to recover trigger by setting OK value */
		if (TRIGGER_VALUE_PROBLEM == tr->value && TRIGGER_RECOVERY_MODE_NONE != tr->recovery_mode)
		{
			if (TRIGGER_RECOVERY_MODE_EXPRESSION == tr->recovery_mode)
			{
				tr->new_value = TRIGGER_VALUE_OK;
				continue;
			}

			/* processing recovery expression mode */
			if (SUCCEED != evaluate(&expr_result, tr->recovery_expression, err, sizeof(err), &unknown_msgs))
			{
				tr->new_error = zbx_strdup(tr->new_error, err);
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				continue;
			}

			if (SUCCEED != zbx_double_compare(expr_result, 0.0))
			{
				tr->new_value = TRIGGER_VALUE_OK;
				continue;
			}
		}

		/* no changes, keep the old value */
		tr->new_value = TRIGGER_VALUE_NONE;
	}

	zbx_vector_ptr_clear_ext(&unknown_msgs, zbx_ptr_free);
	zbx_vector_ptr_destroy(&unknown_msgs);

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
	{
		for (i = 0; i < triggers->values_num; i++)
		{
			tr = (DC_TRIGGER *)triggers->values[i];

			if (NULL != tr->new_error)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s():expression [%s] cannot be evaluated: %s",
						__function_name, tr->expression, tr->new_error);
			}
		}

		zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: process_simple_macro_token                                       *
 *                                                                            *
 * Purpose: trying to resolve the discovery macros in item key parameters     *
 *          in simple macros like {host:key[].func()}                         *
 *                                                                            *
 ******************************************************************************/
static int	process_simple_macro_token(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row,
		char *error, size_t max_error_len)
{
	char	*key = NULL, *replace_to = NULL, *dot, *params;
	size_t	replace_to_offset = 0, replace_to_alloc = 128, lld_start, lld_end;
	int	ret = FAIL;

	if ('{' == (*data)[token->data.simple_macro.host.l] &&
			NULL == macro_in_list(*data, token->data.simple_macro.host, simple_host_macros, NULL))
	{
		goto out;
	}

	replace_to = (char *)zbx_malloc(NULL, replace_to_alloc);

	lld_start = token->data.simple_macro.key.l;
	lld_end = token->data.simple_macro.func_param.r - 1;
	dot = *data + token->data.simple_macro.key.r + 1;
	params = *data + token->data.simple_macro.func_param.l + 1;

	/* extract key and substitute macros */
	*dot = '\0';
	key = zbx_strdup(key, *data + token->data.simple_macro.key.l);
	substitute_key_macros(&key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);
	*dot = '.';

	zbx_strcpy_alloc(&replace_to, &replace_to_alloc, &replace_to_offset, key);
	zbx_strncpy_alloc(&replace_to, &replace_to_alloc, &replace_to_offset, dot, params - dot);

	/* substitute macros in function parameters */
	if (SUCCEED != substitute_function_lld_param(params, *data + lld_end - params + 1, 0, &replace_to,
			&replace_to_alloc, &replace_to_offset, jp_row, error, max_error_len))
	{
		goto out;
	}

	/* replace LLD part in original string and adjust token boundary */
	zbx_replace_string(data, lld_start, &lld_end, replace_to);
	token->token.r += lld_end - (token->data.simple_macro.func_param.r - 1);

	ret = SUCCEED;
out:
	zbx_free(replace_to);
	zbx_free(key);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_lld_macro_token                                          *
 *                                                                            *
 * Purpose: expand discovery macro in expression                              *
 *                                                                            *
 * Parameters: data      - [IN/OUT] the expression containing lld macro       *
 *             token     - [IN/OUT] the token with lld macro location data    *
 *             flags     - [IN] the flags passed to                           *
 *                                  subtitute_discovery_macros() function     *
 *             jp_row    - [IN] discovery data                                *
 *             error     - [OUT] should be not NULL if                        *
 *                               ZBX_MACRO_NUMERIC flag is set                *
 *             error_len - [IN] the size of error buffer                      *
 *                                                                            *
 * Return value: Always SUCCEED if numeric flag is not set, otherwise SUCCEED *
 *               if all discovery macros resolved to numeric values,          *
 *               otherwise FAIL with an error message.                        *
 *                                                                            *
 ******************************************************************************/
static int	process_lld_macro_token(char **data, zbx_token_t *token, int flags,
		const struct zbx_json_parse *jp_row, char *error, size_t error_len)
{
	char	c, *replace_to = NULL;
	int	ret = SUCCEED;
	size_t	replace_to_alloc = 0;

	c = (*data)[token->token.r + 1];
	(*data)[token->token.r + 1] = '\0';

	if (SUCCEED != zbx_json_value_by_name_dyn(jp_row, *data + token->token.l, &replace_to, &replace_to_alloc))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot substitute macro \"%s\": not found in value set",
				*data + token->token.l);

		if (0 != (flags & ZBX_TOKEN_NUMERIC))
		{
			zbx_snprintf(error, error_len, "no value for macro \"%s\"", *data + token->token.l);
			ret = FAIL;
		}

		zbx_free(replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_NUMERIC))
	{
		if (SUCCEED == (ret = is_double_suffix(replace_to, ZBX_FLAG_DOUBLE_SUFFIX)))
		{
			wrap_negative_double_suffix(&replace_to, &replace_to_alloc);
		}
		else
		{
			zbx_free(replace_to);
			zbx_snprintf(error, error_len, "macro \"%s\" value is not numeric", *data + token->token.l);
			ret = FAIL;
		}
	}
	else if (0 != (flags & ZBX_TOKEN_JSON))
	{
		zbx_json_escape(&replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_XML))
	{
		char	*replace_to_esc;

		replace_to_esc = xml_escape_dyn(replace_to);
		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}
	else if (0 != (flags & ZBX_TOKEN_REGEXP))
	{
		zbx_regexp_escape(&replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_XPATH))
	{
		xml_escape_xpath(&replace_to);
	}

	(*data)[token->token.r + 1] = c;

	if (NULL != replace_to)
	{
		zbx_replace_string(data, token->token.l, &token->token.r, replace_to);
		zbx_free(replace_to);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_user_macro_token                                         *
 *                                                                            *
 * Purpose: expand discovery macro in user macro context                      *
 *                                                                            *
 * Parameters: data      - [IN/OUT] the expression containing lld macro       *
 *             token     - [IN/OUT] the token with user macro location data   *
 *             jp_row    - [IN] discovery data                                *
 *                                                                            *
 ******************************************************************************/
static void	process_user_macro_token(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row)
{
	int			force_quote;
	size_t			context_r;
	char			*context, *context_esc;
	zbx_token_user_macro_t	*macro = &token->data.user_macro;

	/* user macro without context, nothing to replace */
	if (0 == token->data.user_macro.context.l)
		return;

	force_quote = ('"' == (*data)[macro->context.l]);
	context = zbx_user_macro_unquote_context_dyn(*data + macro->context.l, macro->context.r - macro->context.l + 1);

	/* substitute_lld_macros() can't fail with only ZBX_TOKEN_LLD_MACRO flag set */
	substitute_lld_macros(&context, jp_row, ZBX_TOKEN_LLD_MACRO, NULL, 0);

	context_esc = zbx_user_macro_quote_context_dyn(context, force_quote);

	context_r = macro->context.r;
	zbx_replace_string(data, macro->context.l, &context_r, context_esc);

	token->token.r += context_r - macro->context.r;

	zbx_free(context_esc);
	zbx_free(context);
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_func_macro                                            *
 *                                                                            *
 * Purpose: substitute lld macros in function macro parameters                *
 *                                                                            *
 * Parameters: data   - [IN/OUT] pointer to a buffer                          *
 *             token  - [IN/OUT] the token with funciton macro location data  *
 *             jp_row - [IN] discovery data                                   *
 *             error  - [OUT] error message                                   *
 *             max_error_len - [IN] the size of error buffer                  *
 *                                                                            *
 * Return value: SUCCEED - the lld macros were resolved successfully          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	substitute_func_macro(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row,
		char *error, size_t max_error_len)
{
	int	ret;
	char	*exp = NULL;
	size_t	exp_alloc = 0, exp_offset = 0;
	size_t	par_l = token->data.func_macro.func_param.l, par_r = token->data.func_macro.func_param.r;

	ret = substitute_function_lld_param(*data + par_l + 1, par_r - (par_l + 1), 0, &exp, &exp_alloc, &exp_offset,
			jp_row, error, max_error_len);

	if (SUCCEED == ret)
	{
		/* copy what is left including closing parenthesis and replace function parameters */
		zbx_strncpy_alloc(&exp, &exp_alloc, &exp_offset, *data + par_r, token->token.r - (par_r - 1));
		zbx_replace_string(data, par_l + 1, &token->token.r, exp);
	}

	zbx_free(exp);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_lld_macros                                            *
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
 *                           ZBX_MACRO_FUNC - function macros will be         *
 *                            skipped (lld macros inside function macros will *
 *                            be ignored) for macros specified in func_macros *
 *                            array                                           *
 *             error  - [OUT] should be not NULL if ZBX_MACRO_NUMERIC flag is *
 *                            set                                             *
 *             max_error_len - [IN] the size of error buffer                  *
 *                                                                            *
 * Return value: Always SUCCEED if numeric flag is not set, otherwise SUCCEED *
 *               if all discovery macros resolved to numeric values,          *
 *               otherwise FAIL with an error message.                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	substitute_lld_macros(char **data, const struct zbx_json_parse *jp_row, int flags, char *error,
		size_t max_error_len)
{
	const char	*__function_name = "substitute_lld_macros";

	int		ret = SUCCEED, pos = 0;
	zbx_token_t	token;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	while (SUCCEED == ret && SUCCEED == zbx_token_find(*data, pos, &token, ZBX_TOKEN_SEARCH_BASIC))
	{
		if (0 != (token.type & flags))
		{
			switch (token.type)
			{
				case ZBX_TOKEN_LLD_MACRO:
					ret = process_lld_macro_token(data, &token, flags, jp_row, error,
							max_error_len);
					pos = token.token.r;
					break;
				case ZBX_TOKEN_USER_MACRO:
					process_user_macro_token(data, &token, jp_row);
					pos = token.token.r;
					break;
				case ZBX_TOKEN_SIMPLE_MACRO:
					process_simple_macro_token(data, &token, jp_row, error, max_error_len);
					pos = token.token.r;
					break;
				case ZBX_TOKEN_FUNC_MACRO:
					if (NULL != macro_in_list(*data, token.data.func_macro.macro, mod_macros, NULL))
					{
						ret = substitute_func_macro(data, &token, jp_row, error, max_error_len);
						pos = token.token.r;
					}
					break;
			}
		}

		pos++;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s data:'%s'", __function_name, zbx_result_string(ret), *data);

	return ret;
}

typedef struct
{
	zbx_uint64_t			*hostid;
	DC_ITEM				*dc_item;
	const struct zbx_json_parse	*jp_row;
	int				macro_type;
}
replace_key_param_data_t;

/******************************************************************************
 *                                                                            *
 * Function: replace_key_param                                                *
 *                                                                            *
 * Comments: auxiliary function for substitute_key_macros()                   *
 *                                                                            *
 ******************************************************************************/
static int	replace_key_param_cb(const char *data, int key_type, int level, int num, int quoted, void *cb_data,
			char **param)
{
	replace_key_param_data_t	*replace_key_param_data = (replace_key_param_data_t *)cb_data;
	zbx_uint64_t			*hostid = replace_key_param_data->hostid;
	DC_ITEM				*dc_item = replace_key_param_data->dc_item;
	const struct zbx_json_parse	*jp_row = replace_key_param_data->jp_row;
	int				macro_type = replace_key_param_data->macro_type, ret = SUCCEED;

	ZBX_UNUSED(num);

	if (ZBX_KEY_TYPE_ITEM == key_type && 0 == level)
		return ret;

	if (NULL == strchr(data, '{'))
		return ret;

	*param = zbx_strdup(NULL, data);

	if (0 != level)
		unquote_key_param(*param);

	if (NULL == jp_row)
		substitute_simple_macros(NULL, NULL, NULL, NULL, hostid, NULL, dc_item, NULL, NULL,
				param, macro_type, NULL, 0);
	else
		substitute_lld_macros(param, jp_row, ZBX_MACRO_ANY, NULL, 0);

	if (0 != level)
	{
		if (FAIL == (ret = quote_key_param(param, quoted)))
			zbx_free(*param);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_key_macros                                            *
 *                                                                            *
 * Purpose: safely substitutes macros in parameters of an item key and OID    *
 *                                                                            *
 * Example:  key                     | macro  | result            | return    *
 *          -------------------------+--------+-------------------+---------  *
 *           echo.sh[{$MACRO}]       | a      | echo.sh[a]        | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a\     | echo.sh[a\]       | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a      | echo.sh["a"]      | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a\     | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       |  a     | echo.sh[" a"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       |  a\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     |  a     | echo.sh[" a"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     |  a\    | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | "a"    | echo.sh["\"a\""]  | SUCCEED   *
 *           echo.sh[{$MACRO}]       | "a"\   | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | "a"    | echo.sh["\"a\""]  | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | "a"\   | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | a,b    | echo.sh["a,b"]    | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a,b\   | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | a,b    | echo.sh["a,b"]    | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a,b\   | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | a]     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a]\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | a]     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a]\    | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | [a     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       | [a\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | [a     | echo.sh["[a"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | [a\    | undefined         | FAIL      *
 *           ifInOctets.{#SNMPINDEX} | 1      | ifInOctets.1      | SUCCEED   *
 *                                                                            *
 ******************************************************************************/
int	substitute_key_macros(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item, const struct zbx_json_parse *jp_row,
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
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	ret = replace_key_params_dyn(data, key_type, replace_key_param_cb, &replace_key_param_data, error, maxerrlen);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s data:'%s'", __function_name, zbx_result_string(ret), *data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_function_lld_param                                    *
 *                                                                            *
 * Purpose: substitute lld macros in function parameters                      *
 *                                                                            *
 * Parameters: e            - [IN] the function parameter list without        *
 *                                 enclosing parentheses:                     *
 *                                       <p1>, <p2>, ...<pN>                  *
 *             len          - [IN] the length of function parameter list      *
 *             key_in_param - [IN] 1 - the first parameter must be host:key   *
 *                                 0 - otherwise                              *
 *             exp          - [IN/OUT] output buffer                          *
 *             exp_alloc    - [IN/OUT] the size of output buffer              *
 *             exp_offset   - [IN/OUT] the current position in output buffer  *
 *             jp_row - [IN] discovery data                                   *
 *             error  - [OUT] error message                                   *
 *             max_error_len - [IN] the size of error buffer                  *
 *                                                                            *
 * Return value: SUCCEED - the lld macros were resolved successfully          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	substitute_function_lld_param(const char *e, size_t len, unsigned char key_in_param,
		char **exp, size_t *exp_alloc, size_t *exp_offset, const struct zbx_json_parse *jp_row,
		char *error, size_t max_error_len)
{
	const char	*__function_name = "substitute_function_lld_param";
	int		ret = SUCCEED;
	size_t		sep_pos;
	char		*param = NULL;
	const char	*p;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == len)
	{
		zbx_strcpy_alloc(exp, exp_alloc, exp_offset, "");
		goto out;
	}

	for (p = e; p < len + e ; p += sep_pos + 1)
	{
		size_t	param_pos, param_len, rel_len = len - (p - e);
		int	quoted;

		zbx_function_param_parse(p, &param_pos, &param_len, &sep_pos);

		/* copy what was before the parameter */
		zbx_strncpy_alloc(exp, exp_alloc, exp_offset, p, param_pos);

		/* prepare the parameter (macro substitutions and quoting) */

		zbx_free(param);
		param = zbx_function_param_unquote_dyn(p + param_pos, param_len, &quoted);

		if (1 == key_in_param && p == e)
		{
			char	*key = NULL, *host = NULL;

			if (SUCCEED != parse_host_key(param, &host, &key) ||
					SUCCEED != substitute_key_macros(&key, NULL, NULL, jp_row,
							MACRO_TYPE_ITEM_KEY, NULL, 0))
			{
				zbx_snprintf(error, max_error_len, "Invalid first parameter \"%s\"", param);
				zbx_free(host);
				zbx_free(key);
				ret = FAIL;
				goto out;
			}

			zbx_free(param);
			if (NULL != host)
			{
				param = zbx_dsprintf(NULL, "%s:%s", host, key);
				zbx_free(host);
				zbx_free(key);
			}
			else
				param = key;
		}
		else
			substitute_lld_macros(&param, jp_row, ZBX_MACRO_ANY, NULL, 0);

		if (SUCCEED != zbx_function_param_quote(&param, quoted))
		{
			zbx_snprintf(error, max_error_len, "Cannot quote parameter \"%s\"", param);
			ret = FAIL;
			goto out;
		}

		/* copy the parameter */
		zbx_strcpy_alloc(exp, exp_alloc, exp_offset, param);

		/* copy what was after the parameter (including separator) */
		if (sep_pos < rel_len)
			zbx_strncpy_alloc(exp, exp_alloc, exp_offset, p + param_pos + param_len,
					sep_pos - param_pos - param_len + 1);
	}
out:
	zbx_free(param);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

#ifdef HAVE_LIBXML2
/******************************************************************************
 *                                                                            *
 * Function: substitute_macros_in_xml_elements                                *
 *                                                                            *
 * Comments: auxiliary function for substitute_macros_xml()                   *
 *                                                                            *
 ******************************************************************************/
static void	substitute_macros_in_xml_elements(const DC_ITEM *item, const struct zbx_json_parse *jp_row,
		xmlNode *node)
{
	xmlChar	*value;
	xmlAttr	*attr;
	char	*value_tmp;

	for (;NULL != node; node = node->next)
	{
		switch (node->type)
		{
			case XML_TEXT_NODE:
				if (NULL == (value = xmlNodeGetContent(node)))
					break;

				value_tmp = zbx_strdup(NULL, (const char *)value);

				if (NULL != item)
				{
					substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &item->host, item, NULL,
							NULL, &value_tmp, MACRO_TYPE_HTTP_XML, NULL, 0);
				}
				else
					substitute_lld_macros(&value_tmp, jp_row, ZBX_MACRO_XML, NULL, 0);

				xmlNodeSetContent(node, (xmlChar *)value_tmp);

				zbx_free(value_tmp);
				xmlFree(value);
				break;
			case XML_CDATA_SECTION_NODE:
				if (NULL == (value = xmlNodeGetContent(node)))
					break;

				value_tmp = zbx_strdup(NULL, (const char *)value);

				if (NULL != item)
				{
					substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &item->host, item, NULL,
							NULL, &value_tmp, MACRO_TYPE_HTTP_RAW, NULL, 0);
				}
				else
					substitute_lld_macros(&value_tmp, jp_row, ZBX_MACRO_ANY, NULL, 0);

				xmlNodeSetContent(node, (xmlChar *)value_tmp);

				zbx_free(value_tmp);
				xmlFree(value);
				break;
			case XML_ELEMENT_NODE:
				for (attr = node->properties; NULL != attr; attr = attr->next)
				{
					if (NULL == attr->name || NULL == (value = xmlGetProp(node, attr->name)))
						continue;

					value_tmp = zbx_strdup(NULL, (const char *)value);

					if (NULL != item)
					{
						substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &item->host,
								item, NULL, NULL, &value_tmp, MACRO_TYPE_HTTP_XML,
								NULL, 0);
					}
					else
						substitute_lld_macros(&value_tmp, jp_row, ZBX_MACRO_XML, NULL, 0);

					xmlSetProp(node, attr->name, (xmlChar *)value_tmp);

					zbx_free(value_tmp);
					xmlFree(value);
				}
				break;
			default:
				break;
		}

		substitute_macros_in_xml_elements(item, jp_row, node->children);
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: substitute_macros_xml                                            *
 *                                                                            *
 * Purpose: substitute simple or LLD macros in XML text nodes, attributes of  *
 *          a node or in CDATA section, validate XML                          *
 *                                                                            *
 * Parameters: data   - [IN/OUT] pointer to a buffer that contains XML        *
 *             item   - [IN] item for simple macro substitution               *
 *             jp_row - [IN] discovery data for LLD macro substitution        *
 *             error  - [OUT] reason for XML parsing failure                  *
 *             maxerrlen - [IN] the size of error buffer                      *
 *                                                                            *
 * Return value: SUCCEED or FAIL if XML validation has failed                 *
 *                                                                            *
 ******************************************************************************/
int	substitute_macros_xml(char **data, const DC_ITEM *item, const struct zbx_json_parse *jp_row, char *error,
		int maxerrlen)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(data);
	ZBX_UNUSED(item);
	ZBX_UNUSED(jp_row);
	zbx_snprintf(error, maxerrlen, "Support for XML was not compiled in");
	return FAIL;
#else
	const char	*__function_name = "substitute_macros_xml";
	xmlDoc		*doc;
	xmlErrorPtr	pErr;
	xmlNode		*root_element;
	xmlChar		*mem;
	int		size, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == (doc = xmlReadMemory(*data, strlen(*data), "noname.xml", NULL, 0)))
	{
		if (NULL != (pErr = xmlGetLastError()))
			zbx_snprintf(error, maxerrlen, "Cannot parse XML value: %s", pErr->message);
		else
			zbx_snprintf(error, maxerrlen, "Cannot parse XML value");

		goto exit;
	}

	if (NULL == (root_element = xmlDocGetRootElement(doc)))
	{
		zbx_snprintf(error, maxerrlen, "Cannot parse XML root");
		goto clean;
	}

	substitute_macros_in_xml_elements(item, jp_row, root_element);
	xmlDocDumpMemory(doc, &mem, &size);

	if (NULL == mem)
	{
		if (NULL != (pErr = xmlGetLastError()))
			zbx_snprintf(error, maxerrlen, "Cannot save XML: %s", pErr->message);
		else
			zbx_snprintf(error, maxerrlen, "Cannot save XML");

		goto clean;
	}

	zbx_free(*data);
	*data = zbx_malloc(NULL, size + 1);
	memcpy(*data, (const char *)mem, size + 1);
	xmlFree(mem);
	ret = SUCCEED;
clean:
	xmlFreeDoc(doc);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
#endif
}

#ifdef HAVE_LIBXML2
/******************************************************************************
 *                                                                            *
 * Function: libxml_handle_error                                              *
 *                                                                            *
 * Purpose: libxml2 callback function for error handle                        *
 *                                                                            *
 * Parameters: user_data - [IN/OUT] the user context                          *
 *             err       - [IN] the libxml2 error message                     *
 *                                                                            *
 ******************************************************************************/
static void libxml_handle_error(void *user_data, xmlErrorPtr err)
{
	if (NULL == user_data)
		return;
	zbx_libxml_error_t * err_ctx = (zbx_libxml_error_t *)user_data;
	zbx_strlcat(err_ctx->buf, err->message, err_ctx->len);
	if (NULL != err->str1)
		zbx_strlcat(err_ctx->buf, err->str1, err_ctx->len);
	if (NULL != err->str2)
		zbx_strlcat(err_ctx->buf, err->str2, err_ctx->len);
	if (NULL != err->str3)
		zbx_strlcat(err_ctx->buf, err->str3, err_ctx->len);
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: xml_xpath_check                                                  *
 *                                                                            *
 * Purpose: validate xpath string                                             *
 *                                                                            *
 * Parameters: xpath  - [IN] the xpath value                                  *
 *             error  - [OUT] the error message buffer                        *
 *             errlen - [IN] the size of error message buffer                 *
 *                                                                            *
 * Return value: SUCCEED - the xpath component was parsed successfully        *
 *               FAIL    - xpath parsing error                                *
 *                                                                            *
 ******************************************************************************/
int	xml_xpath_check(const char *xpath, char *error, size_t errlen)
{
#ifdef HAVE_LIBXML2
	zbx_libxml_error_t err;

	err.buf = error;
	err.len = errlen;
	xmlXPathContextPtr ctx = xmlXPathNewContext(NULL);
	xmlSetStructuredErrorFunc(&err, &libxml_handle_error);
	xmlXPathCompExprPtr p = xmlXPathCtxtCompile(ctx, (xmlChar *)xpath);
	xmlSetStructuredErrorFunc(NULL, NULL);

	if (NULL == p)
	{
		xmlXPathFreeContext(ctx);
		return FAIL;
	}

	xmlXPathFreeCompExpr(p);
	xmlXPathFreeContext(ctx);
#endif
	return SUCCEED;
}
