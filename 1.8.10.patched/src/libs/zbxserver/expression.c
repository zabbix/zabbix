/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

static DB_MACROS	*macros = NULL;

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
static int	DBget_host_profile_value(DB_TRIGGER *trigger, char **replace_to, int N_functionid, const char *fieldname)
{
	const char	*__function_name = "DBget_host_profile_value";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	functionid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	result = DBselect("select distinct p.%s from hosts_profiles p,items i,functions f"
			" where p.hostid=i.hostid and i.itemid=f.itemid and f.functionid=" ZBX_FS_UI64,
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
			zbxmacros_get_value(macros, &hostid, 1, m - 1, &replace_to);

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
#define ZBX_REQUEST_HOST_IPADDRESS	1
#define ZBX_REQUEST_HOST_DNS		2
#define ZBX_REQUEST_HOST_CONN		3
#define ZBX_REQUEST_ITEM_NAME		4
#define ZBX_REQUEST_ITEM_KEY		5
#define ZBX_REQUEST_ITEM_KEY_ORIG	6
#define ZBX_REQUEST_PROXY_NAME		7
static int	DBget_trigger_value(DB_TRIGGER *trigger, char **replace_to, int N_functionid, int request)
{
	const char	*__function_name = "DBget_trigger_value";
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	zbx_uint64_t	functionid, proxy_hostid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	result = DBselect(
			"select h.hostid,h.host,h.useip,h.ip,h.dns,h.proxy_hostid,i.description,i.key_"
			" from hosts h,items i,functions f"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.functionid=" ZBX_FS_UI64,
			functionid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(item.hostid, row[0]);
		item.host_name = row[1];
		item.useip = atoi(row[2]);
		item.host_ip = row[3];
		item.host_dns = row[4];
		item.description = row[6];
		item.key_orig = row[7];
		item.key = NULL;

		switch (request)
		{
			case ZBX_REQUEST_HOST_NAME:
				*replace_to = zbx_strdup(*replace_to, item.host_name);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_HOST_IPADDRESS:
				*replace_to = zbx_strdup(*replace_to, item.host_ip);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_HOST_DNS:
				*replace_to = zbx_strdup(*replace_to, item.host_dns);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_HOST_CONN:
				*replace_to = zbx_strdup(*replace_to, item.useip == 1 ? item.host_ip : item.host_dns);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_NAME:
				item.key = zbx_strdup(item.key, item.key_orig);
				substitute_simple_macros(NULL, &item, NULL, NULL, NULL, &item.key,
						MACRO_TYPE_ITEM_KEY, NULL, 0);

				*replace_to = zbx_strdup(*replace_to, item.description);
				item_description(replace_to, item.key, item.hostid);

				zbx_free(item.key);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_KEY:
				*replace_to = zbx_strdup(*replace_to, item.key_orig);
				substitute_simple_macros(NULL, &item, NULL, NULL, NULL, replace_to,
						MACRO_TYPE_ITEM_KEY, NULL, 0);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_ITEM_KEY_ORIG:
				*replace_to = zbx_strdup(*replace_to, item.key_orig);
				ret = SUCCEED;
				break;
			case ZBX_REQUEST_PROXY_NAME:
				ZBX_STR2UINT64(proxy_hostid, row[5]);

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
		int N_functionid, const char *field_name)
{
	const char	*__function_name = "DBget_history_log_value";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	functionid;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == trigger_get_N_functionid(trigger, N_functionid, &functionid))
		goto fail;

	result = DBselect("select i.itemid,i.value_type"
			" from items i,functions f"
			" where i.itemid=f.itemid"
				" and f.functionid=" ZBX_FS_UI64,
			functionid);

	if (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;
		unsigned char	value_type;
		char		**h_value;

		if (ITEM_VALUE_TYPE_LOG == (value_type = (unsigned char)atoi(row[1])))
		{
			ZBX_STR2UINT64(itemid, row[0]);

			h_value = DBget_history(itemid, value_type, ZBX_DB_GET_HIST_VALUE, 0, 0, field_name, 1);

			if (NULL != h_value[0])
			{
				*replace_to = zbx_strdup(*replace_to, h_value[0]);
				ret = SUCCEED;
			}
			DBfree_history(h_value);
		}
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
	zbx_uint64_t	functionid;
	int		ret = FAIL;

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
		zbx_uint64_t	itemid, valuemapid;
		unsigned char	value_type;
		char		**h_value;
		char		tmp[MAX_STRING_LEN];

		ZBX_STR2UINT64(itemid, row[0]);
		value_type = atoi(row[1]);
		ZBX_STR2UINT64(valuemapid, row[2]);

		switch (value_type)
		{
			case ITEM_VALUE_TYPE_LOG:
			case ITEM_VALUE_TYPE_TEXT:
				h_value = DBget_history(itemid, value_type, ZBX_DB_GET_HIST_VALUE, 0, 0, NULL, 1);

				if (NULL != h_value[0])
					*lastvalue = zbx_strdup(*lastvalue, h_value[0]);
				else
					*lastvalue = zbx_strdup(*lastvalue, row[4]);

				DBfree_history(h_value);
				break;
			case ITEM_VALUE_TYPE_STR:
				zbx_strlcpy(tmp, row[4], sizeof(tmp));

				replace_value_by_map(tmp, sizeof(tmp), valuemapid);

				*lastvalue = zbx_strdup(*lastvalue, tmp);
				break;
			default:
				zbx_strlcpy(tmp, row[4], sizeof(tmp));

				if (ITEM_VALUE_TYPE_FLOAT == value_type)
					del_zeroes(tmp);
				if (SUCCEED != replace_value_by_map(tmp, sizeof(tmp), valuemapid))
					add_value_suffix(tmp, sizeof(tmp), row[3], value_type);

				*lastvalue = zbx_strdup(*lastvalue, tmp);
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
static int	DBget_item_value(DB_TRIGGER *trigger, char **value, int N_functionid, int clock)
{
	const char	*__function_name = "DBget_item_value";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	functionid;
	int		ret = FAIL;

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
		zbx_uint64_t	itemid, valuemapid;
		unsigned char	value_type;
		char		**h_value;
		char		tmp[MAX_STRING_LEN];

		ZBX_STR2UINT64(itemid, row[0]);
		value_type = (unsigned char)atoi(row[1]);
		ZBX_STR2UINT64(valuemapid, row[2]);

		h_value = DBget_history(itemid, value_type, ZBX_DB_GET_HIST_VALUE, 0, clock, NULL, 1);

		if (NULL != h_value[0])
		{
			switch (value_type)
			{
				case ITEM_VALUE_TYPE_LOG:
				case ITEM_VALUE_TYPE_TEXT:
					*value = zbx_strdup(*value, h_value[0]);
					break;
				case ITEM_VALUE_TYPE_STR:
					strscpy(tmp, h_value[0]);

					replace_value_by_map(tmp, sizeof(tmp), valuemapid);

					*value = zbx_strdup(*value, tmp);
					break;
				default:
					strscpy(tmp, h_value[0]);

					if (ITEM_VALUE_TYPE_FLOAT == value_type)
						del_zeroes(tmp);
					if (SUCCEED != replace_value_by_map(tmp, sizeof(tmp), valuemapid))
						add_value_suffix(tmp, sizeof(tmp), row[3], value_type);

					*value = zbx_strdup(*value, tmp);
					break;
			}
			ret = SUCCEED;
		}
		DBfree_history(h_value);
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
			" left join media_type m on m.mediatypeid = a.mediatypeid"
			" where a.eventid=" ZBX_FS_UI64 " and a.alerttype=%d order by a.clock",
			escalation != NULL ? escalation->eventid : event->eventid,
			ALERT_TYPE_MESSAGE);

	while (NULL != (row = DBfetch(result))) {
		now		= atoi(row[0]);
		status		= atoi(row[1]);
		esc_step	= atoi(row[5]);
		ZBX_STR2UINT64(userid, row[6]);

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
#define MVAR_HOSTNAME			"{HOSTNAME}"
#define MVAR_PROXY_NAME			"{PROXY.NAME}"
#define MVAR_IPADDRESS			"{IPADDRESS}"
#define MVAR_HOST_DNS			"{HOST.DNS}"
#define MVAR_HOST_CONN			"{HOST.CONN}"
#define MVAR_TIME			"{TIME}"
#define MVAR_ITEM_LASTVALUE		"{ITEM.LASTVALUE}"
#define MVAR_ITEM_VALUE			"{ITEM.VALUE}"
#define MVAR_ITEM_NAME			"{ITEM.NAME}"
#define MVAR_ITEM_LOG_DATE		"{ITEM.LOG.DATE}"
#define MVAR_ITEM_LOG_TIME		"{ITEM.LOG.TIME}"
#define MVAR_ITEM_LOG_AGE		"{ITEM.LOG.AGE}"
#define MVAR_ITEM_LOG_SOURCE		"{ITEM.LOG.SOURCE}"
#define MVAR_ITEM_LOG_SEVERITY		"{ITEM.LOG.SEVERITY}"
#define MVAR_ITEM_LOG_NSEVERITY		"{ITEM.LOG.NSEVERITY}"
#define MVAR_ITEM_LOG_EVENTID		"{ITEM.LOG.EVENTID}"
#define MVAR_TRIGGER_COMMENT		"{TRIGGER.COMMENT}"
#define MVAR_TRIGGER_ID			"{TRIGGER.ID}"
#define MVAR_TRIGGER_KEY		"{TRIGGER.KEY}"
#define MVAR_TRIGGER_NAME		"{TRIGGER.NAME}"
#define MVAR_TRIGGER_SEVERITY		"{TRIGGER.SEVERITY}"
#define MVAR_TRIGGER_NSEVERITY		"{TRIGGER.NSEVERITY}"
#define MVAR_TRIGGER_STATUS		"{TRIGGER.STATUS}"
#define MVAR_TRIGGER_STATUS_OLD		"{STATUS}"
#define MVAR_TRIGGER_VALUE		"{TRIGGER.VALUE}"
#define MVAR_TRIGGER_URL		"{TRIGGER.URL}"

#define MVAR_TRIGGER_EVENTS_ACK		"{TRIGGER.EVENTS.ACK}"
#define MVAR_TRIGGER_EVENTS_UNACK	"{TRIGGER.EVENTS.UNACK}"
#define MVAR_TRIGGER_EVENTS_PROBLEM_ACK		"{TRIGGER.EVENTS.PROBLEM.ACK}"
#define MVAR_TRIGGER_EVENTS_PROBLEM_UNACK	"{TRIGGER.EVENTS.PROBLEM.UNACK}"

#define MVAR_PROFILE_DEVICETYPE		"{PROFILE.DEVICETYPE}"
#define MVAR_PROFILE_NAME		"{PROFILE.NAME}"
#define MVAR_PROFILE_OS			"{PROFILE.OS}"
#define MVAR_PROFILE_SERIALNO		"{PROFILE.SERIALNO}"
#define MVAR_PROFILE_TAG		"{PROFILE.TAG}"
#define MVAR_PROFILE_MACADDRESS		"{PROFILE.MACADDRESS}"
#define MVAR_PROFILE_HARDWARE		"{PROFILE.HARDWARE}"
#define MVAR_PROFILE_SOFTWARE		"{PROFILE.SOFTWARE}"
#define MVAR_PROFILE_CONTACT		"{PROFILE.CONTACT}"
#define MVAR_PROFILE_LOCATION		"{PROFILE.LOCATION}"
#define MVAR_PROFILE_NOTES		"{PROFILE.NOTES}"

#define MVAR_NODE_ID			"{NODE.ID}"
#define MVAR_NODE_NAME			"{NODE.NAME}"

#define MVAR_DISCOVERY_RULE_NAME	"{DISCOVERY.RULE.NAME}"
#define MVAR_DISCOVERY_SERVICE_NAME	"{DISCOVERY.SERVICE.NAME}"
#define MVAR_DISCOVERY_SERVICE_PORT	"{DISCOVERY.SERVICE.PORT}"
#define MVAR_DISCOVERY_SERVICE_STATUS	"{DISCOVERY.SERVICE.STATUS}"
#define MVAR_DISCOVERY_SERVICE_UPTIME	"{DISCOVERY.SERVICE.UPTIME}"
#define MVAR_DISCOVERY_DEVICE_IPADDRESS	"{DISCOVERY.DEVICE.IPADDRESS}"
#define MVAR_DISCOVERY_DEVICE_STATUS	"{DISCOVERY.DEVICE.STATUS}"
#define MVAR_DISCOVERY_DEVICE_UPTIME	"{DISCOVERY.DEVICE.UPTIME}"

#define STR_UNKNOWN_VARIABLE		"*UNKNOWN*"

static const char	*ex_macros[] = {MVAR_PROFILE_DEVICETYPE, MVAR_PROFILE_NAME, MVAR_PROFILE_OS, MVAR_PROFILE_SERIALNO,
				MVAR_PROFILE_TAG, MVAR_PROFILE_MACADDRESS, MVAR_PROFILE_HARDWARE, MVAR_PROFILE_SOFTWARE,
				MVAR_PROFILE_CONTACT, MVAR_PROFILE_LOCATION, MVAR_PROFILE_NOTES,
				MVAR_ITEM_NAME,
				MVAR_HOSTNAME, MVAR_PROXY_NAME,
				MVAR_TRIGGER_KEY,
				MVAR_HOST_CONN, MVAR_HOST_DNS, MVAR_IPADDRESS,
				MVAR_ITEM_LASTVALUE,
				MVAR_ITEM_VALUE,
				MVAR_ITEM_LOG_DATE, MVAR_ITEM_LOG_TIME, MVAR_ITEM_LOG_AGE, MVAR_ITEM_LOG_SOURCE,
				MVAR_ITEM_LOG_SEVERITY, MVAR_ITEM_LOG_NSEVERITY, MVAR_ITEM_LOG_EVENTID,
				MVAR_NODE_ID, MVAR_NODE_NAME,
				NULL};

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
 * Comments: example: " {Zabbix server:{TRIGGER.KEY1}.last(0)} " to " 1.34 "  *
 *                      ^ - bl                                ^ - br          *
 *                                                                            *
 ******************************************************************************/
static void	get_trigger_function_value(DB_TRIGGER *trigger, char **replace_to, char *bl, char **br)
{
	char	*p, *host = NULL, *key = NULL, *function = NULL, *parameter = NULL;
	int	N_functionid, res = SUCCEED;
	size_t	sz;

	p = bl + 1;
	sz = sizeof(MVAR_HOSTNAME) - 2;

	if (0 == strncmp(p, MVAR_HOSTNAME, sz) && ('}' == p[sz] ||
			('}' == p[sz + 1] && '1' <= p[sz] && p[sz] <= '9')))
	{
		N_functionid = ('}' == p[sz] ? 1 : p[sz] - '0');
		p += sz + ('}' == p[sz] ? 1 : 2);
		DBget_trigger_value(trigger, &host, N_functionid, ZBX_REQUEST_HOST_NAME);
	}
	else
		res = parse_host(&p, &host);

	if (SUCCEED != res || ':' != *p++)
		goto fail;

	sz = sizeof(MVAR_TRIGGER_KEY) - 2;

	if (0 == strncmp(p, MVAR_TRIGGER_KEY, sz) && ('}' == p[sz] ||
			('}' == p[sz + 1] && '1' <= p[sz] && p[sz] <= '9')))
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
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	substitute_simple_macros(DB_EVENT *event, DB_ITEM *item, DC_HOST *dc_host,
		DC_ITEM *dc_item, DB_ESCALATION *escalation,
		char **data, int macro_type, char *error, int maxerrlen)
{
	const char	*__function_name = "substitute_simple_macros";

	char		*p, *bl, *br, c, *replace_to = NULL, sql[64];
	const char	*m;
	int		N_functionid, ret, res = SUCCEED;
	size_t		data_alloc, data_len;

	if (NULL == macros)
		zbxmacros_init(&macros);

	if (NULL == data || NULL == *data || '\0' == **data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:NULL", __function_name);
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
						substitute_simple_macros(event, item, dc_host, dc_item, escalation, &replace_to,
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
				else if (0 == strcmp(m, MVAR_PROFILE_DEVICETYPE))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "devicetype");
				else if (0 == strcmp(m, MVAR_PROFILE_NAME))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "name");
				else if (0 == strcmp(m, MVAR_PROFILE_OS))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "os");
				else if (0 == strcmp(m, MVAR_PROFILE_SERIALNO))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "serialno");
				else if (0 == strcmp(m, MVAR_PROFILE_TAG))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "tag");
				else if (0 == strcmp(m, MVAR_PROFILE_MACADDRESS))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "macaddress");
				else if (0 == strcmp(m, MVAR_PROFILE_HARDWARE))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "hardware");
				else if (0 == strcmp(m, MVAR_PROFILE_SOFTWARE))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "software");
				else if (0 == strcmp(m, MVAR_PROFILE_CONTACT))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "contact");
				else if (0 == strcmp(m, MVAR_PROFILE_LOCATION))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "location");
				else if (0 == strcmp(m, MVAR_PROFILE_NOTES))
					ret = DBget_host_profile_value(&event->trigger, &replace_to, N_functionid, "notes");
				else if (0 == strcmp(m, MVAR_HOSTNAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_ITEM_NAME);
				else if (0 == strcmp(m, MVAR_TRIGGER_KEY))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_ITEM_KEY);
				else if (0 == strcmp(m, MVAR_IPADDRESS))
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
					ret = DBget_item_value(&event->trigger, &replace_to, N_functionid, event->clock);
				else if (0 == strcmp(m, MVAR_ITEM_LOG_DATE))
				{
					if (SUCCEED == (ret = DBget_history_log_value(&event->trigger, &replace_to,
									N_functionid, "timestamp")))
						replace_to = zbx_strdup(replace_to, zbx_date2str((time_t)atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_TIME))
				{
					if (SUCCEED == (ret = DBget_history_log_value(&event->trigger, &replace_to,
									N_functionid, "timestamp")))
						replace_to = zbx_strdup(replace_to, zbx_time2str((time_t)atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_AGE))
				{
					if (SUCCEED == (ret = DBget_history_log_value(&event->trigger, &replace_to,
									N_functionid, "timestamp")))
						replace_to = zbx_strdup(replace_to, zbx_age2str(time(NULL) - atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_SOURCE))
					ret = DBget_history_log_value(&event->trigger, &replace_to, N_functionid, "source");
				else if (0 == strcmp(m, MVAR_ITEM_LOG_SEVERITY))
				{
					if (SUCCEED == (ret = DBget_history_log_value(&event->trigger, &replace_to,
									N_functionid, "severity")))
						replace_to = zbx_strdup(replace_to,
								zbx_item_logtype_string((zbx_item_logtype_t)atoi(replace_to)));
				}
				else if (0 == strcmp(m, MVAR_ITEM_LOG_NSEVERITY))
					ret = DBget_history_log_value(&event->trigger, &replace_to, N_functionid, "severity");
				else if (0 == strcmp(m, MVAR_ITEM_LOG_EVENTID))
					ret = DBget_history_log_value(&event->trigger, &replace_to, N_functionid, "logeventid");
				else if (0 == strcmp(m, MVAR_DATE))
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL)));
				else if (0 == strcmp(m, MVAR_TIME))
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL)));
				else if (0 == strcmp(m, MVAR_TRIGGER_STATUS) || 0 == strcmp(m, MVAR_TRIGGER_STATUS_OLD))
					replace_to = zbx_strdup(replace_to, event->value == TRIGGER_VALUE_TRUE ? "PROBLEM" : "OK");
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
				else if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
					replace_to = zbx_dsprintf(replace_to, "%d", event->value);
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					if (0 != event->trigger.triggerid)
					{
						replace_to = zbx_strdup(replace_to, event->trigger.url);
						substitute_simple_macros(event, item, dc_host, dc_item, escalation, &replace_to,
								MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
					}
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
				{
					if (0 != event->trigger.triggerid)
						replace_to = zbx_strdup(replace_to,
								zbx_trigger_severity_string(event->trigger.priority));
					else
						ret = FAIL;
				}
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

						ZBX_STR2UINT64(proxy_hostid, replace_to);

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
				else if (0 == strcmp(m, MVAR_HOSTNAME))
					ret = get_autoreg_value_by_event(event, &replace_to, "host");
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = get_autoreg_value_by_event(event, &replace_to, "proxy_hostid")))
					{
						zbx_uint64_t	proxy_hostid;

						ZBX_STR2UINT64(proxy_hostid, replace_to);

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
				if (0 == strcmp(m, MVAR_HOSTNAME))
					ret = DBget_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
					ret = DBget_item_lastvalue(&event->trigger, &replace_to, N_functionid);
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
					ret = DBget_item_value(&event->trigger, &replace_to, N_functionid, event->clock);
				else if (0 == strncmp(m, "{$", 2))	/* user defined macros */
					zbxmacros_get_value_by_triggerid(macros, event->objectid, m, &replace_to);
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
					zbxmacros_get_value_by_triggerid(macros, event->objectid, m, &replace_to);
					if (NULL != replace_to && FAIL == (res = is_double_prefix(replace_to)) && NULL != error)
						zbx_snprintf(error, maxerrlen, "Macro '%s' value is not numeric", m);
				}
			}
		}
		else if (macro_type & MACRO_TYPE_TRIGGER_URL)
		{
			if (EVENT_SOURCE_TRIGGERS == event->source)
			{
				if (0 == strcmp(m, MVAR_TRIGGER_ID))
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
			}
		}
		else if (macro_type & (MACRO_TYPE_ITEM_KEY | MACRO_TYPE_HOST_IPMI_IP))
		{
			if (NULL != item)
			{
				if (0 == strcmp(m, MVAR_HOSTNAME))
					replace_to = zbx_strdup(replace_to, item->host_name);
				else if (0 == strcmp(m, MVAR_IPADDRESS))
					replace_to = zbx_strdup(replace_to, item->host_ip);
				else if	(0 == strcmp(m, MVAR_HOST_DNS))
					replace_to = zbx_strdup(replace_to, item->host_dns);
				else if (0 == strcmp(m, MVAR_HOST_CONN))
					replace_to = zbx_strdup(replace_to, item->useip ? item->host_ip : item->host_dns);
				else if (0 == strncmp(m, "{$", 2))	/* user defined macros */
					zbxmacros_get_value(macros, &item->hostid, 1, m, &replace_to);
			}
			else if (NULL != dc_item)
			{
				if (0 == strcmp(m, MVAR_HOSTNAME))
					replace_to = zbx_strdup(replace_to, dc_item->host.host);
				else if (0 == strcmp(m, MVAR_IPADDRESS))
					replace_to = zbx_strdup(replace_to, dc_item->host.ip);
				else if	(0 == strcmp(m, MVAR_HOST_DNS))
					replace_to = zbx_strdup(replace_to, dc_item->host.dns);
				else if (0 == strcmp(m, MVAR_HOST_CONN))
					replace_to = zbx_strdup(replace_to,
							dc_item->host.useip ? dc_item->host.ip : dc_item->host.dns);
				else if (0 == strncmp(m, "{$", 2))	/* user defined macros */
					zbxmacros_get_value(macros, &dc_item->host.hostid, 1, m, &replace_to);
			}
		}
		else if (macro_type & MACRO_TYPE_ITEM_FIELD)
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
			{
				if (NULL == dc_item)
					zbxmacros_get_value(macros, NULL, 0, m, &replace_to);
				else
					zbxmacros_get_value(macros, &dc_item->host.hostid, 1, m, &replace_to);
			}
		}
		else if (macro_type & MACRO_TYPE_ITEM_EXPRESSION)
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
			{
				zbxmacros_get_value(macros, &dc_item->host.hostid, 1, m, &replace_to);
				if (NULL != replace_to && FAIL == (res = is_double_prefix(replace_to)) && NULL != error)
					zbx_snprintf(error, maxerrlen, "Macro '%s' value is not numeric", m);
			}
		}
		else if (macro_type & MACRO_TYPE_FUNCTION_PARAMETER)
		{
			if (0 == strncmp(m, "{$", 2))	/* user defined macros */
				zbxmacros_get_value(macros, &item->hostid, 1, m, &replace_to);
		}
		else if (macro_type & MACRO_TYPE_SCRIPT)
		{
			if (0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_IPADDRESS))
				replace_to = zbx_strdup(replace_to, dc_host->ip);
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
				replace_to = zbx_strdup(replace_to, dc_host->dns);
			else if (0 == strcmp(m, MVAR_HOST_CONN))
				replace_to = zbx_strdup(replace_to,
						dc_host->useip ? dc_host->ip : dc_host->dns);
		}

		if (FAIL == ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "No %s in %s(). Triggerid [" ZBX_FS_UI64 "]",
					bl, __function_name, event->objectid);
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

static void	zbx_extract_functionids(zbx_vector_uint64_t *functionids, DB_TRIGGER_UPDATE *tr, int tr_num)
{
	const char	*__function_name = "zbx_extract_functionids";

	int		i, values_num_save;
	char		*bl, *br;
	zbx_uint64_t	functionid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __function_name, tr_num);

	for (i = 0; i < tr_num; i++)
	{
		if (NULL != tr[i].new_error)
			continue;

		values_num_save = functionids->values_num;

		for (bl = strchr(tr[i].expression, '{'); NULL != bl; bl = strchr(bl, '{'))
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
			tr[i].new_error = zbx_dsprintf(tr[i].new_error, "Invalid expression [%s]", tr[i].expression);
			tr[i].new_value = TRIGGER_VALUE_UNKNOWN;
			functionids->values_num = values_num_save;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() functionids_num:%d", __function_name, functionids->values_num);
}

typedef struct
{
	zbx_uint64_t		triggerid;
	char			function[FUNCTION_FUNCTION_LEN_MAX];
	char			parameter[FUNCTION_PARAMETER_LEN_MAX];
	int			lastchange;
	char			*value;
	char			*error;
	zbx_vector_uint64_t	functionids;
}
zbx_func_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_vector_ptr_t	functions;
}
zbx_ifunc_t;

static void	zbx_populate_function_items(zbx_vector_uint64_t *functionids, zbx_vector_ptr_t *ifuncs,
		DB_TRIGGER_UPDATE *tr, int tr_num)
{
	const char	*__function_name = "zbx_populate_function_items";

	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		sql_alloc = 2 * ZBX_KIBIBYTE, sql_offset = 0, i;
	zbx_uint64_t	itemid, triggerid, functionid;
	zbx_ifunc_t	*ifunc = NULL;
	zbx_func_t	*func = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() functionids_num:%d", __function_name, functionids->values_num);

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 75,
			"select itemid,triggerid,function,parameter,functionid"
			" from functions"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "functionid",
			functionids->values, functionids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 57,
			" order by itemid,triggerid,function,parameter,functionid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UINT64(triggerid, row[1]);
		ZBX_STR2UINT64(functionid, row[4]);

		if (NULL == ifunc || ifunc->itemid != itemid)
		{
			ifunc = zbx_malloc(NULL, sizeof(zbx_ifunc_t));
			ifunc->itemid = itemid;
			zbx_vector_ptr_create(&ifunc->functions);

			zbx_vector_ptr_append(ifuncs, ifunc);

			func = NULL;
		}

		if (NULL == func || triggerid != func->triggerid ||
				0 != strcmp(func->function, row[2]) || 0 != strcmp(func->parameter, row[3]))
		{
			func = zbx_malloc(NULL, sizeof(zbx_func_t));
			func->triggerid = triggerid;
			strscpy(func->function, row[2]);
			strscpy(func->parameter, row[3]);
			func->lastchange = 0;
			func->value = NULL;
			func->error = NULL;
			zbx_vector_uint64_create(&func->functionids);

			for (i = 0; i < tr_num; i++)
			{
				if (func->triggerid != tr[i].triggerid)
					continue;

				func->lastchange = tr[i].lastchange;
				break;
			}

			zbx_vector_ptr_append(&ifunc->functions, func);
		}

		zbx_vector_uint64_append(&func->functionids, functionid);
	}
	DBfree_result(result);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ifuncs_num:%d", __function_name, ifuncs->values_num);
}

static void	zbx_evaluate_item_functions(zbx_vector_ptr_t *ifuncs)
{
	const char	*__function_name = "zbx_evaluate_item_functions";

	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	char		*sql = NULL, value[MAX_BUFFER_LEN];
	int		sql_alloc = 2 * ZBX_KIBIBYTE, sql_offset = 0, i;
	zbx_ifunc_t	*ifunc = NULL;
	zbx_func_t	*func;
	zbx_uint64_t	*itemids = NULL;
	unsigned char	host_status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ifuncs_num:%d", __function_name, ifuncs->values_num);

	sql = zbx_malloc(sql, sql_alloc);
	itemids = zbx_malloc(itemids, ifuncs->values_num * sizeof(zbx_uint64_t));

	for (i = 0; i < ifuncs->values_num; i++)
		itemids[i] = ((zbx_ifunc_t *)ifuncs->values[i])->itemid;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			49 + sizeof(ZBX_SQL_ITEM_FIELDS) + sizeof(ZBX_SQL_ITEM_TABLES),
			"select %s,h.status"
			" from %s"
			" where i.hostid=h.hostid"
				" and",
			ZBX_SQL_ITEM_FIELDS, ZBX_SQL_ITEM_TABLES);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", itemids, ifuncs->values_num);

	zbx_free(itemids);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		DBget_item_from_db(&item, row);

		host_status = (unsigned char)atoi(row[ZBX_SQL_ITEM_FIELDS_NUM]);

		if (FAIL == (i = zbx_vector_ptr_bsearch(ifuncs, &item.itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ifunc = (zbx_ifunc_t *)ifuncs->values[i];

		for (i = 0; i < ifunc->functions.values_num; i++)
		{
			func = (zbx_func_t *)ifunc->functions.values[i];

			if (ITEM_STATUS_DISABLED == item.status)
			{
				func->error = zbx_dsprintf(func->error, "Item disabled for function: {%s:%s.%s(%s)}",
						item.host_name, item.key, func->function, func->parameter);
			}
			else if (ITEM_STATUS_NOTSUPPORTED == item.status)
			{
				func->error = zbx_dsprintf(func->error, "Item not supported for function: {%s:%s.%s(%s)}",
						item.host_name, item.key, func->function, func->parameter);
			}
			else if (HOST_STATUS_NOT_MONITORED == host_status)
			{
				func->error = zbx_dsprintf(func->error, "Host disabled for function: {%s:%s.%s(%s)}",
						item.host_name, item.key, func->function, func->parameter);
			}

			if (NULL != func->error)
				continue;

			if (SUCCEED != evaluate_function(value, &item, func->function, func->parameter, func->lastchange))
			{
				func->error = zbx_dsprintf(func->error, "Evaluation failed for function: {%s:%s.%s(%s)}",
						item.host_name, item.key, func->function, func->parameter);
			}
			else
				func->value = zbx_strdup(func->value, value);
		}
		DBfree_item_from_db(&item);	/* free cached historical fields item.h_* */
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static zbx_func_t	*zbx_get_func_by_functionid(zbx_vector_ptr_t *ifuncs, zbx_uint64_t functionid)
{
	zbx_ifunc_t	*ifunc;
	zbx_func_t	*func;
	int		i, j;

	for (i = 0; i < ifuncs->values_num; i++)
	{
		ifunc = (zbx_ifunc_t *)ifuncs->values[i];

		for (j = 0; j < ifunc->functions.values_num; j++)
		{
			func = (zbx_func_t *)ifunc->functions.values[j];

			if (FAIL != zbx_vector_uint64_bsearch(&func->functionids, functionid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				return func;
			}
		}
	}

	return NULL;
}

static void	zbx_substitute_functions_results(zbx_vector_ptr_t *ifuncs, DB_TRIGGER_UPDATE *tr, int tr_num)
{
	const char	*__function_name = "zbx_substitute_functions_results";

	char		*out = NULL, *br, *bl;
	int		out_alloc = TRIGGER_EXPRESSION_LEN_MAX, out_offset = 0, i;
	zbx_uint64_t	functionid;
	zbx_func_t	*func;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ifuncs_num:%d tr_num:%d", __function_name, ifuncs->values_num, tr_num);

	out = zbx_malloc(out, out_alloc);

	for (i = 0; i < tr_num; i++)
	{
		if (NULL != tr[i].new_error)
			continue;

		out_offset = 0;

		for (br = tr[i].expression, bl = strchr(tr[i].expression, '{'); NULL != bl; bl = strchr(bl, '{'))
		{
			*bl = '\0';
			zbx_strcpy_alloc(&out, &out_alloc, &out_offset, br);
			*bl = '{';

			if (NULL == (br = strchr(bl, '}')))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				break;
			}

			*br = '\0';

			ZBX_STR2UINT64(functionid, bl + 1);

			*br++ = '}';
			bl = br;

			if (NULL == (func = zbx_get_func_by_functionid(ifuncs, functionid)))
			{
				tr[i].new_error = zbx_dsprintf(tr[i].new_error, "Could not obtain function"
						" and item for functionid: " ZBX_FS_UI64, functionid);
				tr[i].new_value = TRIGGER_VALUE_UNKNOWN;
				break;
			}

			if (NULL != func->error)
			{
				tr[i].new_error = zbx_strdup(tr[i].new_error, func->error);
				tr[i].new_value = TRIGGER_VALUE_UNKNOWN;
				break;
			}

			zbx_strcpy_alloc(&out, &out_alloc, &out_offset, func->value);
		}

		if (NULL == tr[i].new_error)
		{
			zbx_strcpy_alloc(&out, &out_alloc, &out_offset, br);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() expression[%d]:'%s' => '%s'", __function_name, i, tr[i].expression, out);

			tr[i].expression = zbx_strdup(tr[i].expression, out);
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

			zbx_free(func->value);
			zbx_free(func->error);
			zbx_vector_uint64_destroy(&func->functionids);
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
 * Parameters: exp - expression string                                        *
 *             error - place error message here if any                        *
 *             maxerrlen - max length of error msg                            *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev, Aleksandrs Saveljevs        *
 *                                                                            *
 * Comments: example: "({15}>10)|({123}=0)" => "(6.456>10)|(0=0)              *
 *                                                                            *
 ******************************************************************************/
static void	substitute_functions(DB_TRIGGER_UPDATE *tr, int tr_num)
{
	const char		*__function_name = "substitute_functions";

	zbx_vector_uint64_t	functionids;
	zbx_vector_ptr_t	ifuncs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&functionids);
	zbx_extract_functionids(&functionids, tr, tr_num);

	if (0 == functionids.values_num)
		goto empty;

	zbx_vector_ptr_create(&ifuncs);
	zbx_populate_function_items(&functionids, &ifuncs, tr, tr_num);

	if (0 != ifuncs.values_num)
	{
		zbx_evaluate_item_functions(&ifuncs);
		zbx_substitute_functions_results(&ifuncs, tr, tr_num);
	}

	zbx_free_item_functions(&ifuncs);
	zbx_vector_ptr_destroy(&ifuncs);
empty:
	zbx_vector_uint64_destroy(&functionids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_expression                                              *
 *                                                                            *
 * Purpose: evaluate trigger expression                                       *
 *                                                                            *
 * Parameters: triggerid     - [IN] trigger identificator from database       *
 *             expression    - [IN] short trigger expression string           *
 *             value         - [IN] current trigger value                     *
 *                                  TRIGGER_VALUE_(FALSE or TRUE)             *
 *             new_value     - [OUT] evaluated value                          *
 *                                   TRIGGER_VALUE_(FALSE, TRUE or UNKNOWN)   *
 *             new_error     - [OUT] place error message for UNKNOWN results  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	evaluate_expressions(DB_TRIGGER_UPDATE *tr, int tr_num)
{
	const char	*__function_name = "evaluate_expressions";

	DB_EVENT	event;
	int		i;
	double		expr_result;
	char		err[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __function_name, tr_num);

	memset(&event, 0, sizeof(DB_EVENT));
	event.source = EVENT_SOURCE_TRIGGERS;
	event.object = EVENT_OBJECT_TRIGGER;

	for (i = 0; i < tr_num; i++)
	{
		event.objectid = tr[i].triggerid;
		event.value = tr[i].value;

		zbx_remove_spaces(tr[i].expression);

		if (SUCCEED != substitute_simple_macros(&event, NULL, NULL, NULL, NULL, &tr[i].expression,
				MACRO_TYPE_TRIGGER_EXPRESSION, err, sizeof(err)))
		{
			tr[i].new_error = zbx_strdup(tr[i].new_error, err);
			tr[i].new_value = TRIGGER_VALUE_UNKNOWN;
		}
	}

	substitute_functions(tr, tr_num);

	for (i = 0; i < tr_num; i++)
	{
		if (NULL != tr[i].new_error)
			continue;

		if (SUCCEED != evaluate(&expr_result, tr[i].expression, err, sizeof(err)))
		{
			tr[i].new_error = zbx_strdup(tr[i].new_error, err);
			tr[i].new_value = TRIGGER_VALUE_UNKNOWN;
		}
		else if (SUCCEED == cmp_double(expr_result, 0))
		{
			tr[i].new_value = TRIGGER_VALUE_FALSE;
		}
		else
			tr[i].new_value = TRIGGER_VALUE_TRUE;
	}

	for (i = 0; i < tr_num; i++)
	{
		if (NULL != tr[i].new_error)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s():expression [%s] cannot be evaluated: %s",
					__function_name, tr[i].expression, tr[i].new_error);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
