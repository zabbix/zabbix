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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "zbxserver.h"
#include "evalfunc.h"
#include "db.h"
#include "log.h"
#include "zbxalgo.h"

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_severity_name                                      *
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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_severity_name(DB_TRIGGER *trigger, char **replace_to)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	if (0 == trigger->triggerid)
		return res;

	if (TRIGGER_SEVERITY_COUNT <= trigger->priority)
		return res;

	result = DBselect(
			"select severity_name_%d"
			" from config"
			" where 1=1" DB_NODE,
			trigger->priority, DBnode_local("configid"));

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		res = SUCCEED;
	}
	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_macro_value_by_triggerid                                   *
 *                                                                            *
 * Purpose: get value of a user macro                                         *
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
static void	DBget_macro_value_by_triggerid(zbx_uint64_t triggerid, const char *macro, char **replace_to)
{
	const char		*__function_name = "DBget_macro_value_by_triggerid";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64, __function_name, triggerid);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_reserve(&hostids, 8);

	result = DBselect(
			"select distinct i.hostid"
			" from items i,functions f"
			" where f.itemid=i.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		zbx_vector_uint64_append(&hostids, hostid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DCget_user_macro(hostids.values, hostids.values_num, macro, replace_to);

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: trigger_get_N_functionid                                         *
 *                                                                            *
 * Purpose: explode short trigger expression to normal mode                   *
 *          {11}=1 explode to {hostX:keyY.functionZ(parameterN)}=1            *
 *                                                                            *
 * Parameters: trigger - [IN] trigger data with null terminated trigger       *
 *                            expression '{11}=1 & {2346734}>5'               *
 *             N_functionid - [IN] number of function in trigger expression   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	trigger_get_N_functionid(DB_TRIGGER *trigger, int N_functionid, zbx_uint64_t *functionid)
{
	const char	*__function_name = "trigger_get_N_functionid";

	typedef enum
	{
		EXP_NONE,
		EXP_FUNCTIONID
	}
	parsing_state_t;

	parsing_state_t	state = EXP_NONE;
	int		num = 0, ret = FAIL;
	char		*p_functionid = NULL;
	register char	*c;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s' N_functionid:%d",
			__function_name, trigger->expression, N_functionid);

	if (0 == trigger->triggerid)
		goto fail;

	for (c = trigger->expression; '\0' != *c && ret != SUCCEED; c++)
	{
		if ('{' == *c)
		{
			state = EXP_FUNCTIONID;
			p_functionid = c + 1;
		}
		else if ('}' == *c && EXP_FUNCTIONID == state && p_functionid)
		{
			*c = '\0';

			if (SUCCEED == is_uint64(p_functionid, functionid))
			{
				if (++num == N_functionid)
				{
					zabbix_log(LOG_LEVEL_DEBUG, "%s() functionid:" ZBX_FS_UI64,
							__function_name, *functionid);
					ret = SUCCEED;
				}
			}

			*c = '}';
			state = EXP_NONE;
		}
	}
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_simple                                                  *
 *                                                                            *
 * Purpose: evaluate simple expression                                        *
 *                                                                            *
 * Parameters: exp - expression string                                        *
 *                                                                            *
 * Return value:  SUCCEED - evaluated successfully, result - value of the exp *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: format: <double> or <double> <operator> <double>                 *
 *                                                                            *
 *           It is recursive function!                                        *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_simple(double *result, char *exp, char *error, int maxerrlen)
{
	double	value1, value2;
	char	*p, c;

	/* remove left and right spaces */
	lrtrim_spaces(exp);

	/* compress repeating - and + and add prefix N to negative numbers */
	compress_signs(exp);

	/* we should process negative prefix, i.e. N123 == -123 */
	if ('N' == *exp && SUCCEED == is_double_prefix(exp + 1))
	{
		/* str2double supports suffixes */
		*result = -str2double(exp + 1);
		return SUCCEED;
	}
	else if ('N' != *exp && SUCCEED == is_double_prefix(exp))
	{
		/* str2double supports suffixes */
		*result = str2double(exp);
		return SUCCEED;
	}

	/* operators with lowest priority come first */
	/* HIGHEST / * - + < > # = & | LOWEST */
	if (NULL != (p = strchr(exp, '|')) || NULL != (p = strchr(exp, '&')) ||
			NULL != (p = strchr(exp, '=')) || NULL != (p = strchr(exp, '#')) ||
			NULL != (p = strchr(exp, '>')) || NULL != (p = strchr(exp, '<')) ||
			NULL != (p = strchr(exp, '+')) || NULL != (p = strrchr(exp, '-')) ||
			NULL != (p = strchr(exp, '*')) || NULL != (p = strrchr(exp, '/')))
	{
		c = *p;
		*p = '\0';

		if (SUCCEED != evaluate_simple(&value1, exp, error, maxerrlen) ||
				SUCCEED != evaluate_simple(&value2, p + 1, error, maxerrlen))
		{
			*p = c;
			return FAIL;
		}

		*p = c;
	}
	else
	{
		zbx_snprintf(error, maxerrlen, "Format error or unsupported operator. Exp: [%s]", exp);
		return FAIL;
	}

	switch (c)
	{
		case '|':
			if (0 != cmp_double(value1, 0) || 0 != cmp_double(value2, 0))
				*result = 1;
			else
				*result = 0;
			break;
		case '&':
			if (0 != cmp_double(value1, 0) && 0 != cmp_double(value2, 0))
				*result = 1;
			else
				*result = 0;
			break;
		case '=':
			if (0 == cmp_double(value1, value2))
				*result = 1;
			else
				*result = 0;
			break;
		case '#':
			if (0 != cmp_double(value1, value2))
				*result = 1;
			else
				*result = 0;
			break;
		case '>':
			*result = (value1 > value2);
			break;
		case '<':
			*result = (value1 < value2);
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
			if (0 == cmp_double(value2, 0))
			{
				zbx_snprintf(error, maxerrlen, "Division by zero. Cannot evaluate expression [%s]", exp);
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
 * Parameters: exp - expression string                                        *
 *                                                                            *
 * Return value:  SUCCEED - evaluated successfully, result - value of the exp *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: example: ({15}>10)|({123}=1)                                     *
 *                                                                            *
 ******************************************************************************/
int	evaluate(double *value, char *exp, char *error, int maxerrlen)
{
	const char	*__function_name = "evaluate";
	char		*res, simple[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
			value_str[MAX_STRING_LEN], c;
	int		i, l, r;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, exp);

	res = NULL;

	strscpy(tmp, exp);

	while (NULL != strchr(tmp, ')'))
	{
		l=-1;
		r=strchr(tmp,')')-tmp;
		for(i=r;i>=0;i--)
		{
			if( tmp[i] == '(' )
			{
				l=i;
				break;
			}
		}
		if( l == -1 )
		{
			zbx_snprintf(error, maxerrlen, "Cannot find left bracket [(]. Expression:[%s]", tmp);
			return FAIL;
		}
		for(i=l+1;i<r;i++)
		{
			simple[i-l-1]=tmp[i];
		}
		simple[r-l-1]=0;

		if (SUCCEED != evaluate_simple(value, simple, error, maxerrlen))
			return FAIL;

		/* res = first+simple+second */
		c=tmp[l]; tmp[l]='\0';
		res = zbx_strdcat(res, tmp);
		tmp[l]=c;

		zbx_snprintf(value_str, sizeof(value_str), ZBX_FS_DBL, *value);
		res = zbx_strdcat(res, value_str);
		res = zbx_strdcat(res, tmp+r+1);

		zbx_remove_spaces(res);
		strscpy(tmp,res);

		zbx_free(res); res = NULL;
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
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *           Use zbx_free_numbers to free allocated memory                    *
 *                                                                            *
 ******************************************************************************/
static char**	extract_numbers(char *str, int *count)
{
	char *s = NULL;
	char *e = NULL;

	char **result = NULL;

	int	dot_founded = 0;
	int	len = 0;

	assert(count);

	*count = 0;

	/* find start of number */
	for ( s = str; *s; s++)
	{
		if ( !isdigit(*s) ) {
			continue; /* for s */
		}

		if ( s != str && '{' == *(s-1) ) {
			/* skip functions '{65432}' */
			s = strchr(s, '}');
			continue; /* for s */
		}

		dot_founded = 0;
		/* find end of number */
		for ( e = s; *e; e++ )
		{
			if ( isdigit(*e) ) {
				continue; /* for e */
			}
			else if ( '.' == *e && !dot_founded ) {
				dot_founded = 1;
				continue; /* for e */
			}
			else if ( *e >= 'A' && *e <= 'Z' )
			{
				e++;
			}
			break; /* for e */
		}

		/* number found */
		len = e - s;
		(*count)++;
		result = zbx_realloc(result, sizeof(char*) * (*count));
		result[(*count)-1] = zbx_malloc(NULL, len + 1);
		memcpy(result[(*count)-1], s, len);
		result[(*count)-1][len] = '\0';

		s = e;
		if (*s == '\0')
			break;
	}

	return result;
}

static void	zbx_free_numbers(char ***numbers, int count)
{
	register int i = 0;

	if ( !numbers ) return;
	if ( !*numbers ) return;

	for ( i = 0; i < count; i++ )
	{
		zbx_free((*numbers)[i]);
	}

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
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *           replace ONLY $1-9 macros NOT {HOSTNAME}                          *
 *                                                                            *
 ******************************************************************************/
static void	expand_trigger_description_constants(char **data, zbx_uint64_t triggerid)
{
	DB_RESULT	result;
	DB_ROW		row;

	char	**numbers = NULL;
	int	numbers_cnt = 0, i = 0;
	char	*new_str = NULL;
	char	replace[3] = "$0";

	result = DBselect("select expression from triggers where triggerid=" ZBX_FS_UI64, triggerid);

	if (NULL != (row = DBfetch(result)))
	{
		numbers = extract_numbers(row[0], &numbers_cnt);

		for (i = 0; i < 9; i++)
		{
			replace[1] = '0' + i + 1;
			new_str = string_replace(*data, replace, i < numbers_cnt ?  numbers[i] : "");
			zbx_free(*data);
			*data = new_str;
		}

		zbx_free_numbers(&numbers, numbers_cnt);
	}
	DBfree_result(result);
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
		case 0:
			return;
		case 1:
			params[0] = '\0';
		case 2:
			/* do nothing */;
	}

	p = *data;
	while (NULL != (m = strchr(p, '$')))
	{
		if (m > p && *(m - 1) == '{' && (n = strchr(m + 1, '}')) != NULL)	/* user defined macros */
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
		else if (*(m + 1) >= '1' && *(m + 1) <= '9' && params[0] != '\0')	/* macros $1, $2, ... */
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
 * Function: DBget_host_profile_value                                         *
 *                                                                            *
 * Purpose: request host profile value by triggerid and field name            *
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
static int	DBget_host_profile_value(DB_TRIGGER *trigger, char **replace_to,
		int N_functionid, const char *fieldname)
{
	const char	*__function_name = "DBget_host_profile_value";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	functionid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	result = DBselect(
			"select p.%s"
			" from host_profile p,items i,functions f"
			" where p.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.functionid=" ZBX_FS_UI64,
			fieldname,
			functionid);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 * Function: DBget_interface_value_by_hostid                                  *
 *                                                                            *
 * Purpose: request interface value by hostid and request                     *
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
#define ZBX_REQUEST_HOST_IPADDRESS	1
#define ZBX_REQUEST_HOST_DNS		2
#define ZBX_REQUEST_HOST_CONN		3
static int	DBget_interface_value_by_hostid(zbx_uint64_t hostid, char **replace_to, int request)
{
#define MAX_INTERFACE_COUNT	4
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	type, useip, pr, last_pr = MAX_INTERFACE_COUNT,
			priority[MAX_INTERFACE_COUNT] = {
					INTERFACE_TYPE_AGENT,
					INTERFACE_TYPE_SNMP,
					INTERFACE_TYPE_JMX,
					INTERFACE_TYPE_IPMI};
	int		ret = FAIL;

	result = DBselect(
			"select type,useip,ip,dns"
			" from interface"
			" where hostid=" ZBX_FS_UI64
				" and type in (%d,%d,%d,%d)"
				" and main=1",
			hostid, INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX);

	while (NULL != (row = DBfetch(result)))
	{
		type = (unsigned char)atoi(row[0]);

		for (pr = 0; pr < MAX_INTERFACE_COUNT && priority[pr] != type; pr++)
			;

		if (pr >= last_pr)
			continue;

		last_pr = pr;

		switch (request)
		{
			case ZBX_REQUEST_HOST_IPADDRESS:
				*replace_to = zbx_strdup(*replace_to, row[2]);
				break;
			case ZBX_REQUEST_HOST_DNS:
				*replace_to = zbx_strdup(*replace_to, row[3]);
				break;
			case ZBX_REQUEST_HOST_CONN:
				useip = (unsigned char)atoi(row[1]);
				*replace_to = zbx_strdup(*replace_to, useip ? row[2] : row[3]);
				break;
		}
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_value                                              *
 *                                                                            *
 * Purpose: retrieve a particular value associated with the trigger's         *
 *          N_functionid'th function                                          *
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
#define ZBX_REQUEST_HOST_NAME		0
#define ZBX_REQUEST_ITEM_NAME		4
#define ZBX_REQUEST_ITEM_KEY		5
#define ZBX_REQUEST_ITEM_KEY_ORIG	6
#define ZBX_REQUEST_ITEM_DESCRIPTION	7
#define ZBX_REQUEST_PROXY_NAME		8
#define ZBX_REQUEST_HOST_HOST		9
static int	DBget_trigger_value(DB_TRIGGER *trigger, char **replace_to, int N_functionid, int request)
{
	const char	*__function_name = "DBget_trigger_value";
	DB_RESULT	result;
	DB_ROW		row;
	DC_HOST		dc_host;
	char		*key = NULL;
	zbx_uint64_t	functionid, proxy_hostid, hostid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	result = DBselect(
			"select i.name,i.key_,i.description,"
				"h.hostid,h.host,h.proxy_hostid,h.name"
			" from hosts h,items i,functions f"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.functionid=" ZBX_FS_UI64,
			functionid);

	if (NULL != (row = DBfetch(result)))
	{
		switch (request)
		{
			case ZBX_REQUEST_HOST_HOST:
				*replace_to = zbx_strdup(*replace_to, row[4]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_HOST_NAME:
				*replace_to = zbx_strdup(*replace_to, row[6]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_HOST_IPADDRESS:
			case ZBX_REQUEST_HOST_DNS:
			case ZBX_REQUEST_HOST_CONN:
				ZBX_STR2UINT64(hostid, row[3]);
				ret = DBget_interface_value_by_hostid(hostid, replace_to, request);
				break;
			case ZBX_REQUEST_ITEM_NAME:
			case ZBX_REQUEST_ITEM_KEY:
				memset(&dc_host, 0, sizeof(dc_host));
				ZBX_STR2UINT64(dc_host.hostid, row[3]);
				strscpy(dc_host.host, row[4]);

				key = zbx_strdup(key, row[1]);
				substitute_simple_macros(NULL, NULL, &dc_host, NULL, &key, MACRO_TYPE_ITEM_KEY, NULL, 0);

				if (ZBX_REQUEST_ITEM_NAME == request)
				{
					*replace_to = zbx_strdup(*replace_to, row[0]);
					item_description(replace_to, key, dc_host.hostid);
					zbx_free(key);
				}
				else /* ZBX_REQUEST_ITEM_KEY */
				{
					zbx_free(*replace_to);
					*replace_to = key;
				}
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_KEY_ORIG:
				*replace_to = zbx_strdup(*replace_to, row[1]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_DESCRIPTION:
				*replace_to = zbx_strdup(*replace_to, row[2]);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_PROXY_NAME:
				ZBX_DBROW2UINT64(proxy_hostid, row[5]);

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
fail:
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
		zbx_snprintf(value, sizeof(value), "%d", TRIGGER_VALUE_TRUE);
	else
		zbx_snprintf(value, sizeof(value), "%d,%d", TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE);

	result = DBselect(
			"select count(*)"
			" from events"
			" where object=%d"
				" and objectid=" ZBX_FS_UI64
				" and value in (%s)"
				" and acknowledged=%d",
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
static int	DBget_dhost_value_by_event(DB_EVENT *event, char **replace_to, const char *fieldname)
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
static int	DBget_dservice_value_by_event(DB_EVENT *event, char **replace_to, const char *fieldname)
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
static int	DBget_drule_value_by_event(DB_EVENT *event, char **replace_to, const char *fieldname)
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
 * Function: DBget_history_value                                              *
 *                                                                            *
 * Purpose: retrieve value by clock                                           *
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
static int	DBget_history_value(zbx_uint64_t itemid, char **replace_to,
		const char *tablename, const char *fieldname, int clock, int ns)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		sql[MAX_STRING_LEN];
	int		max_clock = 0, ret = FAIL;

	if (0 == CONFIG_NS_SUPPORT)
	{
		zbx_snprintf(sql, sizeof(sql),
				"select %s"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock<=%d"
				" order by itemid,clock desc",
				fieldname, tablename, itemid, clock);

		result = DBselectN(sql, 1);

		if (NULL != (row = DBfetch(result)))
		{
			*replace_to = zbx_strdup(*replace_to, row[0]);
			ret = SUCCEED;
		}

		DBfree_result(result);

		return ret;
	}

	zbx_snprintf(sql, sizeof(sql),
			"select %s"
			" from %s"
			" where itemid=" ZBX_FS_UI64
				" and clock=%d"
				" and ns=%d",
			fieldname, tablename, itemid, clock, ns);

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}

	DBfree_result(result);

	if (SUCCEED == ret)
		return ret;

	result = DBselect(
			"select distinct clock"
			" from %s"
			" where itemid=" ZBX_FS_UI64
				" and clock=%d"
				" and ns<%d",
			tablename, itemid, clock, ns);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
		max_clock = atoi(row[0]);

	DBfree_result(result);

	if (0 == max_clock)
	{
		result = DBselect(
				"select max(clock)"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock<%d",
				tablename, itemid, clock);

		if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
			max_clock = atoi(row[0]);

		DBfree_result(result);
	}

	if (0 == max_clock)
		return ret;

	if (clock == max_clock)
	{
		zbx_snprintf(sql, sizeof(sql),
				"select %s"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock=%d"
					" and ns<%d"
				" order by itemid,clock desc,ns desc",
				fieldname, tablename, itemid, clock, ns);
	}
	else
	{
		zbx_snprintf(sql, sizeof(sql),
				"select %s"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock=%d"
				" order by itemid,clock desc,ns desc",
				fieldname, tablename, itemid, max_clock);
	}

	result = DBselectN(sql, 1);

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
 * Function: DBget_history_log_value                                          *
 *                                                                            *
 * Purpose: retrieve a particular attribute of a log value                    *
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
static int	DBget_history_log_value(DB_TRIGGER *trigger, char **replace_to,
		int N_functionid, const char *fieldname, int clock, int ns)
{
	const char	*__function_name = "DBget_history_log_value";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	functionid, itemid;
	int		value_type, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	result = DBselect("select i.itemid,i.value_type from items i,functions f"
			" where i.itemid=f.itemid and f.functionid=" ZBX_FS_UI64,
			functionid);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		value_type = atoi(row[1]);

		if (value_type == ITEM_VALUE_TYPE_LOG)
			ret = DBget_history_value(itemid, replace_to, "history_log", fieldname, clock, ns);
	}
	DBfree_result(result);
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_item_lastvalue                                             *
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
static int	DBget_item_lastvalue(DB_TRIGGER *trigger, char **lastvalue, int N_functionid)
{
	const char	*__function_name = "DBget_item_lastvalue";
	DB_RESULT	result;
	DB_ROW		row;
	DB_RESULT	h_result;
	DB_ROW		h_row;
	zbx_uint64_t	valuemapid, functionid;
	int		value_type, ret = FAIL;
	char		tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	result = DBselect(
			"select i.itemid,i.value_type,i.valuemapid,i.units,i.lastvalue"
			" from items i,functions f"
			" where i.itemid=f.itemid"
				" and f.functionid=" ZBX_FS_UI64,
			functionid);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[4]))
	{
		value_type = atoi(row[1]);
		ZBX_DBROW2UINT64(valuemapid, row[2]);

		switch (value_type)
		{
			case ITEM_VALUE_TYPE_LOG:
			case ITEM_VALUE_TYPE_TEXT:
				zbx_snprintf(tmp, sizeof(tmp), "select value from %s where itemid=%s order by id desc",
						value_type == ITEM_VALUE_TYPE_LOG ? "history_log" : "history_text",
						row[0]);

				h_result = DBselectN(tmp, 1);

				if (NULL != (h_row = DBfetch(h_result)))
					ZBX_STRDUP(*lastvalue, h_row[0]);
				else
					ZBX_STRDUP(*lastvalue, row[4]);

				DBfree_result(h_result);
				break;
			default:
				zbx_strlcpy(tmp, row[4], sizeof(tmp));
				zbx_format_value(tmp, sizeof(tmp), valuemapid, row[3], value_type);
				ZBX_STRDUP(*lastvalue, tmp);
				break;
		}
		ret = SUCCEED;
	}
	DBfree_result(result);
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_item_value                                                 *
 *                                                                            *
 * Purpose: retrieve item value by trigger expression and number of function  *
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
static int	DBget_item_value(DB_TRIGGER *trigger, char **value, int N_functionid, int clock, int ns)
{
	const char	*__function_name = "DBget_item_value";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	functionid, itemid, valuemapid;
	int		value_type, ret = FAIL;
	char		tmp[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	result = DBselect(
			"select i.itemid,i.value_type,i.valuemapid,i.units"
			" from items i,functions f"
			" where i.itemid=f.itemid"
				" and f.functionid=" ZBX_FS_UI64,
			functionid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		value_type = atoi(row[1]);
		ZBX_DBROW2UINT64(valuemapid, row[2]);

		if (SUCCEED == (ret = DBget_history_value(itemid, value, get_table_by_value_type(value_type), "value", clock, ns)))
		{
			switch (value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
				case ITEM_VALUE_TYPE_STR:
					zbx_strlcpy(tmp, *value, sizeof(tmp));
					zbx_format_value(tmp, sizeof(tmp), valuemapid, row[3], value_type);
					ZBX_STRDUP(*value, tmp);
					break;
				default:
					;
			}
		}
	}
	DBfree_result(result);
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_escalation_history                                           *
 *                                                                            *
 * Purpose: retrieve escalation history                                       *
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
static int	get_escalation_history(DB_EVENT *event, DB_ESCALATION *escalation, char **replace_to)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*buf = NULL;
	int		buf_offset, buf_allocated = 1024;
	int		status, esc_step;
	time_t		now;
	zbx_uint64_t	userid;

	buf = zbx_malloc(buf, buf_allocated);
	buf_offset = 0;
	*buf = '\0';

	if (escalation != NULL && escalation->eventid == event->eventid)
	{
		zbx_snprintf_alloc(&buf, &buf_allocated, &buf_offset, 64,
				"Problem started: %s %s Age: %s\n",
				zbx_date2str(event->clock),
				zbx_time2str(event->clock),
				zbx_age2str(time(NULL) - event->clock));
	}
	else
	{
		result = DBselect("select clock from events where eventid=" ZBX_FS_UI64,
				escalation != NULL ? escalation->eventid : event->eventid);

		if (NULL != (row = DBfetch(result)))
		{
			now = (time_t)atoi(row[0]);
			zbx_snprintf_alloc(&buf, &buf_allocated, &buf_offset, 64,
					"Problem started: %s %s Age: %s\n",
					zbx_date2str(now),
					zbx_time2str(now),
					zbx_age2str(time(NULL) - now));
		}

		DBfree_result(result);
	}

	result = DBselect("select a.clock,a.status,m.description,a.sendto,a.error,a.esc_step,a.userid"
			" from alerts a"
			" left join media_type m on m.mediatypeid=a.mediatypeid"
			" where a.eventid=" ZBX_FS_UI64 " and a.alerttype=%d order by a.clock",
			escalation != NULL ? escalation->eventid : event->eventid,
			ALERT_TYPE_MESSAGE);

	while (NULL != (row = DBfetch(result)))
	{
		now		= atoi(row[0]);
		status		= atoi(row[1]);
		esc_step	= atoi(row[5]);
		ZBX_DBROW2UINT64(userid, row[6]);

		if (esc_step != 0)
			zbx_snprintf_alloc(&buf, &buf_allocated, &buf_offset, 16, "%d. ", esc_step);

		zbx_snprintf_alloc(&buf, &buf_allocated, &buf_offset, 256,
				"%s %s %-11s %s %s \"%s\" %s\n",
				zbx_date2str(now),
				zbx_time2str(now),
				(status == ALERT_STATUS_NOT_SENT ? "in progress" :
					(status == ALERT_STATUS_SENT ? "sent" : "failed")),
				SUCCEED == DBis_null(row[2]) ? "" : row[2],
				row[3],
				zbx_user_string(userid),
				row[4]);
	}

	DBfree_result(result);

	if (escalation != NULL && escalation->r_eventid == event->eventid)
	{
		now = (time_t)event->clock;
		zbx_snprintf_alloc(&buf, &buf_allocated, &buf_offset, 64,
				"Problem ended: %s %s\n",
				zbx_date2str(now),
				zbx_time2str(now));
	}

	if (0 != buf_offset)
		buf[--buf_offset] = '\0';

	*replace_to = buf;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_ack_history                                            *
 *                                                                            *
 * Purpose: retrieve event acknowledges history                               *
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
static int	get_event_ack_history(DB_EVENT *event, char **replace_to)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*buf = NULL;
	int		buf_offset, buf_allocated = 1024;
	time_t		now;
	zbx_uint64_t	userid;

	if (0 == event->acknowledged)
	{
		*replace_to = zbx_strdup(*replace_to, "");
		return SUCCEED;
	}

	buf = zbx_malloc(buf, buf_allocated);
	buf_offset = 0;
	*buf = '\0';

	result = DBselect("select clock,userid,message"
			" from acknowledges"
			" where eventid=" ZBX_FS_UI64 " order by clock",
			event->eventid);

	while (NULL != (row = DBfetch(result)))
	{
		now = atoi(row[0]);
		ZBX_STR2UINT64(userid, row[1]);

		zbx_snprintf_alloc(&buf, &buf_allocated, &buf_offset, 256,
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

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_node_value                                                 *
 *                                                                            *
 * Purpose: request node value by trigger expression and number of function   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: returns requested host profile value                         *
 *                      or *UNKNOWN* if profile is not defined                *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_node_value(DB_TRIGGER *trigger, char **replace_to, int N_functionid, const char *fieldname)
{
	const char	*__function_name = "DBget_node_value";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	functionid;
	int		nodeid, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	nodeid = get_nodeid_by_id(functionid);

	if (0 == strcmp(fieldname, "nodeid"))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", nodeid);
		ret = SUCCEED;
	}
	else
	{
		result = DBselect("select distinct %s from nodes where nodeid=%d", fieldname, nodeid);

		if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
		{
			*replace_to = zbx_strdup(*replace_to, row[0]);
			ret = SUCCEED;
		}
		DBfree_result(result);
	}
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_node_value_by_event                                          *
 *                                                                            *
 * Purpose: request node value by event                                       *
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
static int	get_node_value_by_event(DB_EVENT *event, char **replace_to, const char *fieldname)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		nodeid, ret = FAIL;

	nodeid = get_nodeid_by_id(event->objectid);

	if (0 == strcmp(fieldname, "nodeid"))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", nodeid);

		ret = SUCCEED;
	}
	else
	{
		result = DBselect("select distinct %s from nodes where nodeid=%d", fieldname, nodeid);

		if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
		{
			*replace_to = zbx_strdup(*replace_to, row[0]);

			ret = SUCCEED;
		}

		DBfree_result(result);
	}

	return ret;
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
static int	get_autoreg_value_by_event(DB_EVENT *event, char **replace_to, const char *fieldname)
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

#define MVAR_DATE			"{DATE}"
#define MVAR_EVENT_ID			"{EVENT.ID}"
#define MVAR_EVENT_DATE			"{EVENT.DATE}"
#define MVAR_EVENT_TIME			"{EVENT.TIME}"
#define MVAR_EVENT_AGE			"{EVENT.AGE}"
#define MVAR_EVENT_ACK_STATUS		"{EVENT.ACK.STATUS}"
#define MVAR_EVENT_ACK_HISTORY		"{EVENT.ACK.HISTORY}"
#define MVAR_ESC_HISTORY		"{ESC.HISTORY}"
#define MVAR_PROXY_NAME			"{PROXY.NAME}"
#define MVAR_HOST_DNS			"{HOST.DNS}"
#define MVAR_HOST_CONN			"{HOST.CONN}"
#define MVAR_HOST_HOST			"{HOST.HOST}"
#define MVAR_HOST_IP			"{HOST.IP}"
#define MVAR_IPADDRESS			"{IPADDRESS}"			/* deprecated */
#define MVAR_HOST_NAME			"{HOST.NAME}"
#define MVAR_HOSTNAME			"{HOSTNAME}"			/* deprecated */
#define MVAR_HOST_PORT			"{HOST.PORT}"
#define MVAR_TIME			"{TIME}"
#define MVAR_ITEM_LASTVALUE		"{ITEM.LASTVALUE}"
#define MVAR_ITEM_VALUE			"{ITEM.VALUE}"
#define MVAR_ITEM_NAME			"{ITEM.NAME}"
#define MVAR_ITEM_KEY			"{ITEM.KEY}"
#define MVAR_TRIGGER_KEY		"{TRIGGER.KEY}"			/* deprecated */
#define MVAR_ITEM_DESCRIPTION		"{ITEM.DESCRIPTION}"
#define MVAR_ITEM_LOG_DATE		"{ITEM.LOG.DATE}"
#define MVAR_ITEM_LOG_TIME		"{ITEM.LOG.TIME}"
#define MVAR_ITEM_LOG_AGE		"{ITEM.LOG.AGE}"
#define MVAR_ITEM_LOG_SOURCE		"{ITEM.LOG.SOURCE}"
#define MVAR_ITEM_LOG_SEVERITY		"{ITEM.LOG.SEVERITY}"
#define MVAR_ITEM_LOG_NSEVERITY		"{ITEM.LOG.NSEVERITY}"
#define MVAR_ITEM_LOG_EVENTID		"{ITEM.LOG.EVENTID}"
#define MVAR_TRIGGER_COMMENT		"{TRIGGER.COMMENT}"
#define MVAR_TRIGGER_ID			"{TRIGGER.ID}"
#define MVAR_TRIGGER_NAME		"{TRIGGER.NAME}"
#define MVAR_TRIGGER_SEVERITY		"{TRIGGER.SEVERITY}"
#define MVAR_TRIGGER_NSEVERITY		"{TRIGGER.NSEVERITY}"
#define MVAR_TRIGGER_STATUS		"{TRIGGER.STATUS}"
#define MVAR_STATUS			"{STATUS}"			/* deprecated */
#define MVAR_TRIGGER_VALUE		"{TRIGGER.VALUE}"
#define MVAR_TRIGGER_URL		"{TRIGGER.URL}"

#define MVAR_TRIGGER_EVENTS_ACK		"{TRIGGER.EVENTS.ACK}"
#define MVAR_TRIGGER_EVENTS_UNACK	"{TRIGGER.EVENTS.UNACK}"
#define MVAR_TRIGGER_EVENTS_PROBLEM_ACK		"{TRIGGER.EVENTS.PROBLEM.ACK}"
#define MVAR_TRIGGER_EVENTS_PROBLEM_UNACK	"{TRIGGER.EVENTS.PROBLEM.UNACK}"

#define MVAR_PROFILE				"{PROFILE."			/* a prefix for all profile macros */
#define MVAR_PROFILE_TYPE			MVAR_PROFILE "TYPE}"
#define MVAR_PROFILE_DEVICETYPE			MVAR_PROFILE "DEVICETYPE}"	/* deprecated */
#define MVAR_PROFILE_TYPE_FULL			MVAR_PROFILE "TYPE.FULL}"
#define MVAR_PROFILE_NAME			MVAR_PROFILE "NAME}"
#define MVAR_PROFILE_ALIAS			MVAR_PROFILE "ALIAS}"
#define MVAR_PROFILE_OS				MVAR_PROFILE "OS}"
#define MVAR_PROFILE_OS_FULL			MVAR_PROFILE "OS.FULL}"
#define MVAR_PROFILE_OS_SHORT			MVAR_PROFILE "OS.SHORT}"
#define MVAR_PROFILE_SERIALNO_A			MVAR_PROFILE "SERIALNO.A}"
#define MVAR_PROFILE_SERIALNO			MVAR_PROFILE "SERIALNO}"	/* deprecated */
#define MVAR_PROFILE_SERIALNO_B			MVAR_PROFILE "SERIALNO.B}"
#define MVAR_PROFILE_TAG			MVAR_PROFILE "TAG}"
#define MVAR_PROFILE_ASSET_TAG			MVAR_PROFILE "ASSET.TAG}"
#define MVAR_PROFILE_MACADDRESS_A		MVAR_PROFILE "MACADDRESS.A}"
#define MVAR_PROFILE_MACADDRESS			MVAR_PROFILE "MACADDRESS}"	/* deprecated */
#define MVAR_PROFILE_MACADDRESS_B		MVAR_PROFILE "MACADDRESS.B}"
#define MVAR_PROFILE_HARDWARE			MVAR_PROFILE "HARDWARE}"
#define MVAR_PROFILE_HARDWARE_FULL		MVAR_PROFILE "HARDWARE.FULL}"
#define MVAR_PROFILE_SOFTWARE			MVAR_PROFILE "SOFTWARE}"
#define MVAR_PROFILE_SOFTWARE_FULL		MVAR_PROFILE "SOFTWARE.FULL}"
#define MVAR_PROFILE_SOFTWARE_APP_A		MVAR_PROFILE "SOFTWARE.APP.A}"
#define MVAR_PROFILE_SOFTWARE_APP_B		MVAR_PROFILE "SOFTWARE.APP.B}"
#define MVAR_PROFILE_SOFTWARE_APP_C		MVAR_PROFILE "SOFTWARE.APP.C}"
#define MVAR_PROFILE_SOFTWARE_APP_D		MVAR_PROFILE "SOFTWARE.APP.D}"
#define MVAR_PROFILE_SOFTWARE_APP_E		MVAR_PROFILE "SOFTWARE.APP.E}"
#define MVAR_PROFILE_CONTACT			MVAR_PROFILE "CONTACT}"
#define MVAR_PROFILE_LOCATION			MVAR_PROFILE "LOCATION}"
#define MVAR_PROFILE_LOCATION_LAT		MVAR_PROFILE "LOCATION.LAT}"
#define MVAR_PROFILE_LOCATION_LON		MVAR_PROFILE "LOCATION.LON}"
#define MVAR_PROFILE_NOTES			MVAR_PROFILE "NOTES}"
#define MVAR_PROFILE_CHASSIS			MVAR_PROFILE "CHASSIS}"
#define MVAR_PROFILE_MODEL			MVAR_PROFILE "MODEL}"
#define MVAR_PROFILE_HW_ARCH			MVAR_PROFILE "HW.ARCH}"
#define MVAR_PROFILE_VENDOR			MVAR_PROFILE "VENDOR}"
#define MVAR_PROFILE_CONTRACT_NUMBER		MVAR_PROFILE "CONTRACT.NUMBER}"
#define MVAR_PROFILE_INSTALLER_NAME		MVAR_PROFILE "INSTALLER.NAME}"
#define MVAR_PROFILE_DEPLOYMENT_STATUS		MVAR_PROFILE "DEPLOYMENT.STATUS}"
#define MVAR_PROFILE_URL_A			MVAR_PROFILE "URL.A}"
#define MVAR_PROFILE_URL_B			MVAR_PROFILE "URL.B}"
#define MVAR_PROFILE_URL_C			MVAR_PROFILE "URL.C}"
#define MVAR_PROFILE_HOST_NETWORKS		MVAR_PROFILE "HOST.NETWORKS}"
#define MVAR_PROFILE_HOST_NETMASK		MVAR_PROFILE "HOST.NETMASK}"
#define MVAR_PROFILE_HOST_ROUTER		MVAR_PROFILE "HOST.ROUTER}"
#define MVAR_PROFILE_OOB_IP			MVAR_PROFILE "OOB.IP}"
#define MVAR_PROFILE_OOB_NETMASK		MVAR_PROFILE "OOB.NETMASK}"
#define MVAR_PROFILE_OOB_ROUTER			MVAR_PROFILE "OOB.ROUTER}"
#define MVAR_PROFILE_HW_DATE_PURCHASE		MVAR_PROFILE "HW.DATE.PURCHASE}"
#define MVAR_PROFILE_HW_DATE_INSTALL		MVAR_PROFILE "HW.DATE.INSTALL}"
#define MVAR_PROFILE_HW_DATE_EXPIRY		MVAR_PROFILE "HW.DATE.EXPIRY}"
#define MVAR_PROFILE_HW_DATE_DECOMM		MVAR_PROFILE "HW.DATE.DECOMM}"
#define MVAR_PROFILE_SITE_ADDRESS_A		MVAR_PROFILE "SITE.ADDRESS.A}"
#define MVAR_PROFILE_SITE_ADDRESS_B		MVAR_PROFILE "SITE.ADDRESS.B}"
#define MVAR_PROFILE_SITE_ADDRESS_C		MVAR_PROFILE "SITE.ADDRESS.C}"
#define MVAR_PROFILE_SITE_CITY			MVAR_PROFILE "SITE.CITY}"
#define MVAR_PROFILE_SITE_STATE			MVAR_PROFILE "SITE.STATE}"
#define MVAR_PROFILE_SITE_COUNTRY		MVAR_PROFILE "SITE.COUNTRY}"
#define MVAR_PROFILE_SITE_ZIP			MVAR_PROFILE "SITE.ZIP}"
#define MVAR_PROFILE_SITE_RACK			MVAR_PROFILE "SITE.RACK}"
#define MVAR_PROFILE_SITE_NOTES			MVAR_PROFILE "SITE.NOTES}"
#define MVAR_PROFILE_POC_PRIMARY_NAME		MVAR_PROFILE "POC.PRIMARY.NAME}"
#define MVAR_PROFILE_POC_PRIMARY_EMAIL		MVAR_PROFILE "POC.PRIMARY.EMAIL}"
#define MVAR_PROFILE_POC_PRIMARY_PHONE_A	MVAR_PROFILE "POC.PRIMARY.PHONE.A}"
#define MVAR_PROFILE_POC_PRIMARY_PHONE_B	MVAR_PROFILE "POC.PRIMARY.PHONE.B}"
#define MVAR_PROFILE_POC_PRIMARY_CELL		MVAR_PROFILE "POC.PRIMARY.CELL}"
#define MVAR_PROFILE_POC_PRIMARY_SCREEN		MVAR_PROFILE "POC.PRIMARY.SCREEN}"
#define MVAR_PROFILE_POC_PRIMARY_NOTES		MVAR_PROFILE "POC.PRIMARY.NOTES}"
#define MVAR_PROFILE_POC_SECONDARY_NAME		MVAR_PROFILE "POC.SECONDARY.NAME}"
#define MVAR_PROFILE_POC_SECONDARY_EMAIL	MVAR_PROFILE "POC.SECONDARY.EMAIL}"
#define MVAR_PROFILE_POC_SECONDARY_PHONE_A	MVAR_PROFILE "POC.SECONDARY.PHONE.A}"
#define MVAR_PROFILE_POC_SECONDARY_PHONE_B	MVAR_PROFILE "POC.SECONDARY.PHONE.B}"
#define MVAR_PROFILE_POC_SECONDARY_CELL		MVAR_PROFILE "POC.SECONDARY.CELL}"
#define MVAR_PROFILE_POC_SECONDARY_SCREEN	MVAR_PROFILE "POC.SECONDARY.SCREEN}"
#define MVAR_PROFILE_POC_SECONDARY_NOTES	MVAR_PROFILE "POC.SECONDARY.NOTES}"

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
	MVAR_PROFILE_TYPE, MVAR_PROFILE_DEVICETYPE, MVAR_PROFILE_TYPE_FULL,
	MVAR_PROFILE_NAME, MVAR_PROFILE_ALIAS, MVAR_PROFILE_OS, MVAR_PROFILE_OS_FULL, MVAR_PROFILE_OS_SHORT,
	MVAR_PROFILE_SERIALNO_A, MVAR_PROFILE_SERIALNO, MVAR_PROFILE_SERIALNO_B, MVAR_PROFILE_TAG,
	MVAR_PROFILE_ASSET_TAG, MVAR_PROFILE_MACADDRESS_A, MVAR_PROFILE_MACADDRESS, MVAR_PROFILE_MACADDRESS_B,
	MVAR_PROFILE_HARDWARE, MVAR_PROFILE_HARDWARE_FULL, MVAR_PROFILE_SOFTWARE, MVAR_PROFILE_SOFTWARE_FULL,
	MVAR_PROFILE_SOFTWARE_APP_A, MVAR_PROFILE_SOFTWARE_APP_B, MVAR_PROFILE_SOFTWARE_APP_C,
	MVAR_PROFILE_SOFTWARE_APP_D, MVAR_PROFILE_SOFTWARE_APP_E, MVAR_PROFILE_CONTACT, MVAR_PROFILE_LOCATION,
	MVAR_PROFILE_LOCATION_LAT, MVAR_PROFILE_LOCATION_LON, MVAR_PROFILE_NOTES, MVAR_PROFILE_CHASSIS,
	MVAR_PROFILE_MODEL, MVAR_PROFILE_HW_ARCH, MVAR_PROFILE_VENDOR, MVAR_PROFILE_CONTRACT_NUMBER,
	MVAR_PROFILE_INSTALLER_NAME, MVAR_PROFILE_DEPLOYMENT_STATUS, MVAR_PROFILE_URL_A, MVAR_PROFILE_URL_B,
	MVAR_PROFILE_URL_C, MVAR_PROFILE_HOST_NETWORKS, MVAR_PROFILE_HOST_NETMASK, MVAR_PROFILE_HOST_ROUTER,
	MVAR_PROFILE_OOB_IP, MVAR_PROFILE_OOB_NETMASK, MVAR_PROFILE_OOB_ROUTER, MVAR_PROFILE_HW_DATE_PURCHASE,
	MVAR_PROFILE_HW_DATE_INSTALL, MVAR_PROFILE_HW_DATE_EXPIRY, MVAR_PROFILE_HW_DATE_DECOMM,
	MVAR_PROFILE_SITE_ADDRESS_A, MVAR_PROFILE_SITE_ADDRESS_B, MVAR_PROFILE_SITE_ADDRESS_C,
	MVAR_PROFILE_SITE_CITY, MVAR_PROFILE_SITE_STATE, MVAR_PROFILE_SITE_COUNTRY, MVAR_PROFILE_SITE_ZIP,
	MVAR_PROFILE_SITE_RACK, MVAR_PROFILE_SITE_NOTES, MVAR_PROFILE_POC_PRIMARY_NAME,
	MVAR_PROFILE_POC_PRIMARY_EMAIL, MVAR_PROFILE_POC_PRIMARY_PHONE_A, MVAR_PROFILE_POC_PRIMARY_PHONE_B,
	MVAR_PROFILE_POC_PRIMARY_CELL, MVAR_PROFILE_POC_PRIMARY_SCREEN, MVAR_PROFILE_POC_PRIMARY_NOTES,
	MVAR_PROFILE_POC_SECONDARY_NAME, MVAR_PROFILE_POC_SECONDARY_EMAIL, MVAR_PROFILE_POC_SECONDARY_PHONE_A,
	MVAR_PROFILE_POC_SECONDARY_PHONE_B, MVAR_PROFILE_POC_SECONDARY_CELL, MVAR_PROFILE_POC_SECONDARY_SCREEN,
	MVAR_PROFILE_POC_SECONDARY_NOTES,
	MVAR_HOST_HOST, MVAR_HOST_NAME, MVAR_HOSTNAME, MVAR_PROXY_NAME,
	MVAR_HOST_CONN, MVAR_HOST_DNS, MVAR_HOST_IP, MVAR_IPADDRESS,
	MVAR_ITEM_NAME, MVAR_ITEM_DESCRIPTION,
	MVAR_ITEM_KEY, MVAR_TRIGGER_KEY,
	MVAR_ITEM_LASTVALUE,
	MVAR_ITEM_VALUE,
	MVAR_ITEM_LOG_DATE, MVAR_ITEM_LOG_TIME, MVAR_ITEM_LOG_AGE, MVAR_ITEM_LOG_SOURCE,
	MVAR_ITEM_LOG_SEVERITY, MVAR_ITEM_LOG_NSEVERITY, MVAR_ITEM_LOG_EVENTID,
	MVAR_NODE_ID, MVAR_NODE_NAME,
	NULL
};

/******************************************************************************
 *                                                                            *
 * Function: get_host_profile                                                 *
 *                                                                            *
 * Purpose: request host profile value by macro and triggerid                 *
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
static int	get_host_profile(const char *macro, DB_TRIGGER *trigger, char **replace_to, int N_functionid)
{
	if (0 == strcmp(macro, MVAR_PROFILE_TYPE) || 0 == strcmp(macro, MVAR_PROFILE_DEVICETYPE))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "type");
	else if (0 == strcmp(macro, MVAR_PROFILE_TYPE_FULL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "type_full");
	else if (0 == strcmp(macro, MVAR_PROFILE_NAME))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "name");
	else if (0 == strcmp(macro, MVAR_PROFILE_ALIAS))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "alias");
	else if (0 == strcmp(macro, MVAR_PROFILE_OS))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "os");
	else if (0 == strcmp(macro, MVAR_PROFILE_OS_FULL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "os_full");
	else if (0 == strcmp(macro, MVAR_PROFILE_OS_SHORT))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "os_short");
	else if (0 == strcmp(macro, MVAR_PROFILE_SERIALNO_A))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "serialno_a");
	else if (0 == strcmp(macro, MVAR_PROFILE_SERIALNO) || 0 == strcmp(macro, MVAR_PROFILE_SERIALNO_B))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "serialno_b");
	else if (0 == strcmp(macro, MVAR_PROFILE_TAG))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "tag");
	else if (0 == strcmp(macro, MVAR_PROFILE_ASSET_TAG))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "asset_tag");
	else if (0 == strcmp(macro, MVAR_PROFILE_MACADDRESS_A))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "macaddress_a");
	else if (0 == strcmp(macro, MVAR_PROFILE_MACADDRESS) || 0 == strcmp(macro, MVAR_PROFILE_MACADDRESS_B))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "macaddress_b");
	else if (0 == strcmp(macro, MVAR_PROFILE_HARDWARE))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "hardware");
	else if (0 == strcmp(macro, MVAR_PROFILE_HARDWARE_FULL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "hardware_full");
	else if (0 == strcmp(macro, MVAR_PROFILE_SOFTWARE))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "software");
	else if (0 == strcmp(macro, MVAR_PROFILE_SOFTWARE_FULL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "software_full");
	else if (0 == strcmp(macro, MVAR_PROFILE_SOFTWARE_APP_A))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "software_app_a");
	else if (0 == strcmp(macro, MVAR_PROFILE_SOFTWARE_APP_B))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "software_app_b");
	else if (0 == strcmp(macro, MVAR_PROFILE_SOFTWARE_APP_C))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "software_app_c");
	else if (0 == strcmp(macro, MVAR_PROFILE_SOFTWARE_APP_D))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "software_app_d");
	else if (0 == strcmp(macro, MVAR_PROFILE_SOFTWARE_APP_E))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "software_app_e");
	else if (0 == strcmp(macro, MVAR_PROFILE_CONTACT))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "contact");
	else if (0 == strcmp(macro, MVAR_PROFILE_LOCATION))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "location");
	else if (0 == strcmp(macro, MVAR_PROFILE_LOCATION_LAT))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "location_lat");
	else if (0 == strcmp(macro, MVAR_PROFILE_LOCATION_LON))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "location_lon");
	else if (0 == strcmp(macro, MVAR_PROFILE_NOTES))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "notes");
	else if (0 == strcmp(macro, MVAR_PROFILE_CHASSIS))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "chassis");
	else if (0 == strcmp(macro, MVAR_PROFILE_MODEL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "model");
	else if (0 == strcmp(macro, MVAR_PROFILE_HW_ARCH))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "hw_arch");
	else if (0 == strcmp(macro, MVAR_PROFILE_VENDOR))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "vendor");
	else if (0 == strcmp(macro, MVAR_PROFILE_CONTRACT_NUMBER))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "contract_number");
	else if (0 == strcmp(macro, MVAR_PROFILE_INSTALLER_NAME))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "installer_name");
	else if (0 == strcmp(macro, MVAR_PROFILE_DEPLOYMENT_STATUS))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "deployment_status");
	else if (0 == strcmp(macro, MVAR_PROFILE_URL_A))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "url_a");
	else if (0 == strcmp(macro, MVAR_PROFILE_URL_B))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "url_b");
	else if (0 == strcmp(macro, MVAR_PROFILE_URL_C))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "url_c");
	else if (0 == strcmp(macro, MVAR_PROFILE_HOST_NETWORKS))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "host_networks");
	else if (0 == strcmp(macro, MVAR_PROFILE_HOST_NETMASK))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "host_netmask");
	else if (0 == strcmp(macro, MVAR_PROFILE_HOST_ROUTER))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "host_router");
	else if (0 == strcmp(macro, MVAR_PROFILE_OOB_IP))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "oob_ip");
	else if (0 == strcmp(macro, MVAR_PROFILE_OOB_NETMASK))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "oob_netmask");
	else if (0 == strcmp(macro, MVAR_PROFILE_OOB_ROUTER))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "oob_router");
	else if (0 == strcmp(macro, MVAR_PROFILE_HW_DATE_PURCHASE))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "date_hw_purchase");
	else if (0 == strcmp(macro, MVAR_PROFILE_HW_DATE_INSTALL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "date_hw_install");
	else if (0 == strcmp(macro, MVAR_PROFILE_HW_DATE_EXPIRY))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "date_hw_expiry");
	else if (0 == strcmp(macro, MVAR_PROFILE_HW_DATE_DECOMM))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "date_hw_decomm");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_ADDRESS_A))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_address_a");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_ADDRESS_B))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_address_b");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_ADDRESS_C))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_address_c");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_CITY))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_city");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_STATE))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_state");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_COUNTRY))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_country");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_ZIP))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_zip");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_RACK))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_rack");
	else if (0 == strcmp(macro, MVAR_PROFILE_SITE_NOTES))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "site_notes");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_PRIMARY_NAME))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_1_name");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_PRIMARY_EMAIL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_1_email");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_PRIMARY_PHONE_A))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_1_phone_a");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_PRIMARY_PHONE_B))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_1_phone_b");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_PRIMARY_CELL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_1_cell");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_PRIMARY_SCREEN))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_1_screen");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_PRIMARY_NOTES))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_1_notes");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_SECONDARY_NAME))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_2_name");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_SECONDARY_EMAIL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_2_email");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_SECONDARY_PHONE_A))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_2_phone_a");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_SECONDARY_PHONE_B))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_2_phone_b");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_SECONDARY_CELL))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_2_cell");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_SECONDARY_SCREEN))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_2_screen");
	else if (0 == strcmp(macro, MVAR_PROFILE_POC_SECONDARY_NOTES))
		return DBget_host_profile_value(trigger, replace_to, N_functionid, "poc_2_notes");

	return SUCCEED;
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
static void	get_trigger_function_value(DB_TRIGGER *trigger, char **replace_to, char *bl, char **br)
{
	char	*p, *host = NULL, *key = NULL, *function = NULL, *parameter = NULL;
	int	N_functionid, res = FAIL;
	size_t	sz;

	p = bl + 1;

	if (0 == strncmp(p, MVAR_HOSTNAME, sz = sizeof(MVAR_HOSTNAME) - 2) ||
			0 == strncmp(p, MVAR_HOST_HOST, sz = sizeof(MVAR_HOST_HOST) - 2))
		res = SUCCEED;

	if (SUCCEED == res && ('}' == p[sz] || ('}' == p[sz + 1] && '1' <= p[sz] && p[sz] <= '9')))
	{
		N_functionid = ('}' == p[sz] ? 1 : p[sz] - '0');
		p += sz + ('}' == p[sz] ? 1 : 2);
		DBget_trigger_value(trigger, &host, N_functionid, ZBX_REQUEST_HOST_HOST);
	}
	else
		res = parse_host(&p, &host);

	if (SUCCEED != res || ':' != *p++)
		goto fail;

	if ((0 == strncmp(p, MVAR_ITEM_KEY, sz = sizeof(MVAR_ITEM_KEY) - 2) ||
				0 == strncmp(p, MVAR_TRIGGER_KEY, sz = sizeof(MVAR_TRIGGER_KEY) - 2)) &&
			('}' == p[sz] || ('}' == p[sz + 1] && '1' <= p[sz] && p[sz] <= '9')))
	{
		N_functionid = ('}' == p[sz] ? 1 : p[sz] - '0');
		p += sz + ('}' == p[sz] ? 1 : 2);
		DBget_trigger_value(trigger, &key, N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG);
	}
	else
		res = parse_key(&p, &key);

	if (SUCCEED != res || '.' != *p++)
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
 * Function: substitute_simple_macros                                         *
 *                                                                            *
 * Purpose: substitute simple macros in data string with real values          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	substitute_simple_macros(DB_EVENT *event, zbx_uint64_t *hostid, DC_HOST *dc_host,
		DB_ESCALATION *escalation, char **data, int macro_type, char *error, int maxerrlen)
{
	const char	*__function_name = "substitute_simple_macros";

	char		*p, *bl, *br, c, *replace_to = NULL, sql[64];
	const char	*m;
	int		N_functionid, ret, res = SUCCEED;
	size_t		data_alloc, data_len;
	DC_INTERFACE	interface;

	if (NULL == data || NULL == *data || '\0' == **data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:EMPTY", __function_name);
		return res;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	if (macro_type & MACRO_TYPE_TRIGGER_DESCRIPTION)
		expand_trigger_description_constants(data, event->objectid);

	p = *data;
	if (NULL == (m = bl = strchr(p, '{')))
		return res;

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

		if (macro_type & MACRO_TYPE_MESSAGE)
		{
			if (EVENT_SOURCE_TRIGGERS == event->source)
			{
				if (0 == strcmp(m, MVAR_TRIGGER_NAME))
				{
					if (0 != event->trigger.triggerid)
					{
						replace_to = zbx_strdup(replace_to, event->trigger.description);
						substitute_simple_macros(event, hostid, dc_host, escalation, &replace_to,
								MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
					}
					else
						ret = FAIL;
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					if (0 != event->trigger.triggerid)
						replace_to = zbx_strdup(replace_to, event->trigger.comments);
					else
						ret = FAIL;
				}
				else if (0 == strncmp(m, MVAR_PROFILE, sizeof(MVAR_PROFILE) - 1))
					ret = get_host_profile(m, &event->trigger, &replace_to, N_functionid);
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				else if (0 == strcmp(m, MVAR_HOST_NAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_ITEM_NAME);
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_ITEM_KEY);
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IPADDRESS);
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_ITEM_DESCRIPTION);
				else if (0 == strcmp(m, MVAR_HOST_DNS))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				else if (0 == strcmp(m, MVAR_HOST_CONN))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
					ret = DBget_item_lastvalue(&event->trigger, &replace_to, N_functionid);
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
					ret = DBget_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns);
				else if (0 == strcmp(m, MVAR_ITEM_LOG_DATE))
				{
					if (SUCCEED == (ret = DBget_history_log_value(&event->trigger, &replace_to,
									N_functionid, "timestamp", event->clock, event->ns)))
						replace_to = zbx_strdup(replace_to, zbx_date2str((time_t)atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_TIME))
				{
					if (SUCCEED == (ret = DBget_history_log_value(&event->trigger, &replace_to,
									N_functionid, "timestamp", event->clock, event->ns)))
						replace_to = zbx_strdup(replace_to, zbx_time2str((time_t)atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_AGE))
				{
					if (SUCCEED == (ret = DBget_history_log_value(&event->trigger, &replace_to,
									N_functionid, "timestamp", event->clock, event->ns)))
						replace_to = zbx_strdup(replace_to, zbx_age2str(time(NULL) - atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_SOURCE))
					ret = DBget_history_log_value(&event->trigger, &replace_to, N_functionid,
							"source", event->clock, event->ns);
				else if (0 == strcmp(m, MVAR_ITEM_LOG_SEVERITY))
				{
					if (SUCCEED == (ret = DBget_history_log_value(&event->trigger, &replace_to,
									N_functionid, "severity", event->clock, event->ns)))
						replace_to = zbx_strdup(replace_to,
								zbx_item_logtype_string((zbx_item_logtype_t)atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_NSEVERITY))
					ret = DBget_history_log_value(&event->trigger, &replace_to, N_functionid,
							"severity", event->clock, event->ns);
				else if (0 == strcmp(m, MVAR_ITEM_LOG_EVENTID))
					ret = DBget_history_log_value(&event->trigger, &replace_to, N_functionid,
							"logeventid", event->clock, event->ns);
				else if (0 == strcmp(m, MVAR_DATE))
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				else if (0 == strcmp(m, MVAR_TIME))
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				else if (0 == strcmp(m, MVAR_TRIGGER_STATUS) || 0 == strcmp(m, MVAR_STATUS))
					replace_to = zbx_strdup(replace_to, event->value == TRIGGER_VALUE_TRUE ? "PROBLEM" : "OK");
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
				else if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
					replace_to = zbx_dsprintf(replace_to, "%d", event->value);
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					if (0 != event->trigger.triggerid)
						replace_to = zbx_strdup(replace_to, event->trigger.url);
					else
						ret = FAIL;
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_ACK))
					ret = DBget_trigger_event_count(event->objectid, &replace_to, 0, 1);
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_UNACK))
					ret = DBget_trigger_event_count(event->objectid, &replace_to, 0, 0);
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_PROBLEM_ACK))
					ret = DBget_trigger_event_count(event->objectid, &replace_to, 1, 1);
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_PROBLEM_UNACK))
					ret = DBget_trigger_event_count(event->objectid, &replace_to, 1, 0);
				else if (0 == strcmp(m, MVAR_EVENT_ID))
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->eventid);
				else if (0 == strcmp(m, MVAR_EVENT_DATE))
					replace_to = zbx_strdup(replace_to, zbx_date2str(event->clock));
				else if (0 == strcmp(m, MVAR_EVENT_TIME))
					replace_to = zbx_strdup(replace_to, zbx_time2str(event->clock));
				else if (0 == strcmp(m, MVAR_EVENT_AGE))
					replace_to = zbx_strdup(replace_to, zbx_age2str(time(NULL) - event->clock));
				else if (0 == strcmp(m, MVAR_EVENT_ACK_STATUS))
					replace_to = zbx_strdup(replace_to, event->acknowledged ? "Yes" : "No");
				else if (0 == strcmp(m, MVAR_EVENT_ACK_HISTORY))
					ret = get_event_ack_history(event, &replace_to);
				else if (0 == strcmp(m, MVAR_ESC_HISTORY))
					ret = get_escalation_history(event, escalation, &replace_to);
				else if (0 == strcmp(m, MVAR_TRIGGER_SEVERITY))
					ret = DBget_trigger_severity_name(&event->trigger, &replace_to);
				else if (0 == strcmp(m, MVAR_TRIGGER_NSEVERITY))
				{
					if (0 != event->trigger.triggerid)
						replace_to = zbx_dsprintf(replace_to, "%d", (int)event->trigger.priority);
					else
						ret = FAIL;
				}
				else if (0 == strcmp(m, MVAR_NODE_ID))
					ret = DBget_node_value(&event->trigger, &replace_to, N_functionid, "nodeid");
				else if (0 == strcmp(m, MVAR_NODE_NAME))
					ret = DBget_node_value(&event->trigger, &replace_to, N_functionid, "name");
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_PROXY_NAME);
				else
				{
					*br = c;
					get_trigger_function_value(&event->trigger, &replace_to, bl, &br);
					c = *br;
					*br = '\0';
				}
			}
			else if (EVENT_SOURCE_DISCOVERY == event->source)
			{
				if (0 == strcmp(m, MVAR_DATE))
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				else if (0 == strcmp(m, MVAR_TIME))
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				else if (0 == strcmp(m, MVAR_EVENT_ID))
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->eventid);
				else if (0 == strcmp(m, MVAR_EVENT_DATE))
					replace_to = zbx_strdup(replace_to, zbx_date2str(event->clock));
				else if (0 == strcmp(m, MVAR_EVENT_TIME))
					replace_to = zbx_strdup(replace_to, zbx_time2str(event->clock));
				else if (0 == strcmp(m, MVAR_EVENT_AGE))
					replace_to = zbx_strdup(replace_to, zbx_age2str(time(NULL) - event->clock));
				else if (0 == strcmp(m, MVAR_NODE_ID))
					ret = get_node_value_by_event(event, &replace_to, "nodeid");
				else if (0 == strcmp(m, MVAR_NODE_NAME))
					ret = get_node_value_by_event(event, &replace_to, "name");
				else if (0 == strcmp(m, MVAR_DISCOVERY_RULE_NAME))
					ret = DBget_drule_value_by_event(event, &replace_to, "name");
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_IPADDRESS))
					ret = DBget_dhost_value_by_event(event, &replace_to, "s.ip");
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_DNS))
					ret = DBget_dhost_value_by_event(event, &replace_to, "s.dns");
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_STATUS))
				{
					if (SUCCEED == (ret = DBget_dhost_value_by_event(event, &replace_to, "h.status")))
						replace_to = zbx_strdup(replace_to,
								(DOBJECT_STATUS_UP == atoi(replace_to)) ? "UP" : "DOWN");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_UPTIME))
				{
					zbx_snprintf(sql, sizeof(sql), "case when h.status=%d then h.lastup else h.lastdown end",
							DOBJECT_STATUS_UP);
					if (SUCCEED == (ret = DBget_dhost_value_by_event(event, &replace_to, sql)))
						replace_to = zbx_strdup(replace_to, zbx_age2str(time(NULL) - atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_NAME))
				{
					if (SUCCEED == (ret = DBget_dservice_value_by_event(event, &replace_to, "s.type")))
						replace_to = zbx_strdup(replace_to,
								zbx_dservice_type_string(atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_PORT))
					ret = DBget_dservice_value_by_event(event, &replace_to, "s.port");
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_STATUS))
				{
					if (SUCCEED == (ret = DBget_dservice_value_by_event(event, &replace_to, "s.status")))
						replace_to = zbx_strdup(replace_to,
								(DOBJECT_STATUS_UP == atoi(replace_to)) ? "UP" : "DOWN");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_UPTIME))
				{
					zbx_snprintf(sql, sizeof(sql), "case when s.status=%d then s.lastup else s.lastdown end",
							DOBJECT_STATUS_UP);
					if (SUCCEED == (ret = DBget_dservice_value_by_event(event, &replace_to, sql)))
						replace_to = zbx_strdup(replace_to, zbx_age2str(time(NULL) - atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = DBget_dhost_value_by_event(event, &replace_to, "r.proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
							replace_to = zbx_strdup(replace_to, "");
						else
							ret = DBget_host_name_by_hostid(proxy_hostid, &replace_to);
					}
				}
			}
			else if (EVENT_SOURCE_AUTO_REGISTRATION == event->source)
			{
				if (0 == strcmp(m, MVAR_DATE))
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				else if (0 == strcmp(m, MVAR_TIME))
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				else if (0 == strcmp(m, MVAR_EVENT_ID))
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->eventid);
				else if (0 == strcmp(m, MVAR_EVENT_DATE))
					replace_to = zbx_strdup(replace_to, zbx_date2str(event->clock));
				else if (0 == strcmp(m, MVAR_EVENT_TIME))
					replace_to = zbx_strdup(replace_to, zbx_time2str(event->clock));
				else if (0 == strcmp(m, MVAR_EVENT_AGE))
					replace_to = zbx_strdup(replace_to, zbx_age2str(time(NULL) - event->clock));
				else if (0 == strcmp(m, MVAR_NODE_ID))
					ret = get_node_value_by_event(event, &replace_to, "nodeid");
				else if (0 == strcmp(m, MVAR_NODE_NAME))
					ret = get_node_value_by_event(event, &replace_to, "name");
				else if (0 == strcmp(m, MVAR_HOST_HOST))
					ret = get_autoreg_value_by_event(event, &replace_to, "host");
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
					ret = get_autoreg_value_by_event(event, &replace_to, "listen_ip");
				else if (0 == strcmp(m, MVAR_HOST_PORT))
					ret = get_autoreg_value_by_event(event, &replace_to, "listen_port");
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = get_autoreg_value_by_event(event, &replace_to, "proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_DBROW2UINT64(proxy_hostid, replace_to);

						if (0 == proxy_hostid)
							replace_to = zbx_strdup(replace_to, "");
						else
							ret = DBget_host_name_by_hostid(proxy_hostid, &replace_to);
					}
				}
			}
		}
		else if (macro_type & MACRO_TYPE_TRIGGER_DESCRIPTION)
		{
			if (EVENT_SOURCE_TRIGGERS == event->source)
			{
				if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				else if (0 == strcmp(m, MVAR_HOST_NAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IPADDRESS);
				else if (0 == strcmp(m, MVAR_HOST_DNS))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				else if (0 == strcmp(m, MVAR_HOST_CONN))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
					ret = DBget_item_lastvalue(&event->trigger, &replace_to, N_functionid);
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
					ret = DBget_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns);
				else if (0 == strncmp(m, "{$", 2))	/* user defined macros */
					DBget_macro_value_by_triggerid(event->objectid, m, &replace_to);
			}
		}
		else if (macro_type & MACRO_TYPE_TRIGGER_EXPRESSION)
		{
			if (EVENT_SOURCE_TRIGGERS == event->source)
			{
				if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
					replace_to = zbx_dsprintf(replace_to, "%d", event->value);
				else if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				{
					DBget_macro_value_by_triggerid(event->objectid, m, &replace_to);
					if (NULL != replace_to && FAIL == (res = is_double_prefix(replace_to)) && NULL != error)
						zbx_snprintf(error, maxerrlen, "Macro '%s' value is not numeric", m);
				}
			}
		}
		else if (macro_type & (MACRO_TYPE_ITEM_KEY | MACRO_TYPE_INTERFACE_ADDR))
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (SUCCEED == (ret = DCconfig_get_interface_by_type(&interface, dc_host->hostid, INTERFACE_TYPE_AGENT)))
			{
				if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
				else if	(0 == strcmp(m, MVAR_HOST_DNS))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
				else if (0 == strcmp(m, MVAR_HOST_CONN))
					replace_to = zbx_strdup(replace_to,
							interface.useip ? interface.ip_orig : interface.dns_orig);
			}
		}
		else if (macro_type & MACRO_TYPE_INTERFACE_PORT)
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
			{
				if (NULL != hostid)
					DCget_user_macro(hostid, 1, m, &replace_to);
				else
					DCget_user_macro(NULL, 0, m, &replace_to);
			}
		}
		else if (macro_type & MACRO_TYPE_ITEM_FIELD)
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
			{
				if (NULL == dc_host)
					DCget_user_macro(NULL, 0, m, &replace_to);
				else
					DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
			}
		}
		else if (macro_type & MACRO_TYPE_ITEM_EXPRESSION)
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
			{
				DCget_user_macro(&dc_host->hostid, 1, m, &replace_to);
				if (NULL != replace_to && FAIL == (res = is_double_prefix(replace_to)) && NULL != error)
					zbx_snprintf(error, maxerrlen, "Macro '%s' value is not numeric", m);
			}
		}
		else if (macro_type & MACRO_TYPE_FUNCTION_PARAMETER)
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				DCget_user_macro(hostid, 1, m, &replace_to);
		}
		else if (macro_type & MACRO_TYPE_SCRIPT)
		{
			if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (SUCCEED == (ret = DCconfig_get_interface_by_type(&interface, dc_host->hostid, INTERFACE_TYPE_AGENT)))
			{
				if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
				else if	(0 == strcmp(m, MVAR_HOST_DNS))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
				else if (0 == strcmp(m, MVAR_HOST_CONN))
					replace_to = zbx_strdup(replace_to,
							interface.useip ? interface.ip_orig : interface.dns_orig);
			}
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

	zabbix_log(LOG_LEVEL_DEBUG, "End %s() data:'%s'", __function_name, *data);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: substitute_functions                                             *
 *                                                                            *
 * Purpose: substitute expression functions with their values                 *
 *                                                                            *
 * Parameters: exp - expression string                                        *
 *             error - place error message here if any                        *
 *             maxerrlen - max length of error msg                            *
 *                                                                            *
 * Return value:  SUCCEED - evaluated successfully, exp - updated expression  *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev, Aleksandrs Saveljevs        *
 *                                                                            *
 * Comments: example: "({15}>10)|({123}=0)" => "(6.456>10)|(0=0)"             *
 *                                                                            *
 ******************************************************************************/
static int	substitute_functions(char **exp, time_t now, char *error, int maxerrlen)
{
	const char	*__function_name = "substitute_functions";

	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	char		functionid[MAX_ID_LEN], value[MAX_BUFFER_LEN], *e, *f, *out = NULL;
	int		out_alloc = 64, out_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, *exp);

	if ('\0' == **exp)
		goto empty;

	*error = '\0';

	out = zbx_malloc(out, out_alloc);

	for (e = *exp; '\0' != *e;)
	{
		if ('{' == *e)
		{
			e++;	/* '{' */
			f = functionid;

			while ('}' != *e && '\0' != *e)
			{
				if (MAX_ID_LEN == functionid - f)
					break;
				if (*e < '0' || *e > '9')
					break;

				*f++ = *e++;
			}

			if ('}' != *e || f == functionid)
			{
				zbx_snprintf(error, maxerrlen, "invalid expression [%s]", *exp);
				goto error;
			}

			*f = '\0';
			e++;	/* '}' */

			result = DBselect(
					"select distinct %s,f.function,f.parameter,h.status from %s,functions f"
					" where i.hostid=h.hostid and i.itemid=f.itemid and f.functionid=%s",
					ZBX_SQL_ITEM_FIELDS,
					ZBX_SQL_ITEM_TABLES,
					functionid);

			if (NULL == (row = DBfetch(result)))
			{
				zbx_snprintf(error, maxerrlen, "cannot obtain function and item for functionid: %s",
						functionid);
			}
			else
			{
				const char	*function, *parameter;
				unsigned char	host_status;

				DBget_item_from_db(&item, row);

				function = row[ZBX_SQL_ITEM_FIELDS_NUM];
				parameter = row[ZBX_SQL_ITEM_FIELDS_NUM + 1];
				host_status = (unsigned char)atoi(row[ZBX_SQL_ITEM_FIELDS_NUM + 2]);

				if (ITEM_STATUS_DISABLED == item.status)
					zbx_snprintf(error, maxerrlen, "Item disabled for function: {%s:%s.%s(%s)}",
							item.host_name, item.key, function, parameter);
				else if (ITEM_STATUS_NOTSUPPORTED == item.status)
					zbx_snprintf(error, maxerrlen, "Item not supported for function: {%s:%s.%s(%s)}",
							item.host_name, item.key, function, parameter);

				if ('\0' == *error && HOST_STATUS_NOT_MONITORED == host_status)
					zbx_snprintf(error, maxerrlen, "Host disabled for function: {%s:%s.%s(%s)}",
							item.host_name, item.key, function, parameter);

				if ('\0' == *error && SUCCEED != evaluate_function(value, &item, function, parameter, now))
					zbx_snprintf(error, maxerrlen, "Evaluation failed for function: {%s:%s.%s(%s)}",
							item.host_name, item.key, function, parameter);
			}
			DBfree_result(result);

			if ('\0' != *error)
				goto error;

			zbx_strcpy_alloc(&out, &out_alloc, &out_offset, value);
		}
		else
			zbx_chrcpy_alloc(&out, &out_alloc, &out_offset, *e++);
	}
	zbx_free(*exp);

	*exp = out;
empty:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:'%s'", __function_name, *exp);

	return SUCCEED;
error:
	zbx_free(out);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() error:'%s'", __function_name, error);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_expression                                              *
 *                                                                            *
 * Purpose: evaluate trigger expression                                       *
 *                                                                            *
 * Parameters: expression    - [IN] short trigger expression string           *
 *             triggerid     - [IN] trigger identificator from database       *
 *             trigger_value - [IN] current trigger value                     *
 *             error         - [OUT] place error message if any               *
 *             maxerrlen     - [IN] max length of error message               *
 *                                                                            *
 * Return value:  SUCCEED - evaluated successfully, result - value of the exp *
 *                FAIL - otherwise                                            *
 *                error - error message                                       *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	evaluate_expression(int *result, char **expression, time_t now,
		zbx_uint64_t triggerid, int trigger_value, char *error, int maxerrlen)
{
	const char	*__function_name = "evaluate_expression";

	DB_EVENT	event;
	int		ret = FAIL;
	double		value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, *expression);

	memset(&event, 0, sizeof(DB_EVENT));
	event.source = EVENT_SOURCE_TRIGGERS;
	event.object = EVENT_OBJECT_TRIGGER;
	event.objectid = triggerid;
	event.value = trigger_value;

	if (SUCCEED == substitute_simple_macros(&event, NULL, NULL, NULL, expression, MACRO_TYPE_TRIGGER_EXPRESSION,
				error, maxerrlen))
	{
		zbx_remove_spaces(*expression);

		if (SUCCEED == substitute_functions(expression, now, error, maxerrlen) &&
				SUCCEED == evaluate(&value, *expression, error, maxerrlen))
		{
			if (0 == cmp_double(value, 0))
				*result = TRIGGER_VALUE_FALSE;
			else
				*result = TRIGGER_VALUE_TRUE;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() result:%d", __function_name, *result);
			ret = SUCCEED;
			goto out;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

void	substitute_discovery_macros(char **data, struct zbx_json_parse *jp_row)
{
	const char	*__function_name = "substitute_discovery_macros";

	char		*src, *dst, *replace_to = NULL, c;
	size_t		l, r, sz_data, sz_macro, sz_value,
			replace_to_alloc = 0, data_alloc;
	int		res;

	assert(data);
	assert(*data);
	assert(jp_row);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	sz_data = strlen(*data);
	data_alloc = sz_data + 1;

	for (l = 0; l < sz_data; l++)
	{
		if ((*data)[l] != '{' || (*data)[l + 1] != '#')
			continue;

		for (r = l + 2; r < sz_data && (*data)[r] != '}'; r++)
			;

		if (r == sz_data)
			break;

		c = (*data)[r + 1];
		(*data)[r + 1] = '\0';

		res = zbx_json_value_by_name_dyn(jp_row, &(*data)[l], &replace_to, &replace_to_alloc);

		(*data)[r + 1] = c;

		sz_macro = r - l + 1;

		if (SUCCEED == res)
		{
			sz_value = strlen(replace_to);

			sz_data += sz_value - sz_macro;

			while (data_alloc <= sz_data)
			{
				data_alloc *= 2;
				*data = realloc(*data, data_alloc);
			}

			src = *data + l + sz_macro;
			dst = *data + l + sz_value;

			memmove(dst, src, sz_data - l - sz_value + 1);

			memcpy(&(*data)[l], replace_to, sz_value);
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot substitute macro: \"%.*s\" is not found in value set",
					__function_name, (int)sz_macro, *data + l);
		}
	}

	zbx_free(replace_to);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() data:'%s'", __function_name, *data);
}
