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

#include "common.h"
#include "db.h"
#include "log.h"
#include "zbxserver.h"

#include "evalfunc.h"

int	cmp_double(double a, double b)
{
	return fabs(a - b) < TRIGGER_EPSILON ? SUCCEED : FAIL;
}

static int	get_function_parameter_uint(zbx_uint64_t hostid, const char *parameters, int Nparam, int *value, int *flag)
{
	const char	*__function_name = "get_function_parameter_uint";
	char		*parameter = NULL;
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __function_name, parameters, Nparam);

	parameter = zbx_malloc(parameter, FUNCTION_PARAMETER_LEN_MAX);

	if (0 != get_param(parameters, Nparam, parameter, FUNCTION_PARAMETER_LEN_MAX))
		goto clean;

	if (SUCCEED == substitute_simple_macros(NULL, &hostid, NULL, NULL, NULL, &parameter, MACRO_TYPE_FUNCTION_PARAMETER, NULL, 0))
	{
		if ('#' == *parameter)
		{
			*flag = ZBX_FLAG_VALUES;
			if (SUCCEED == is_uint(parameter + 1))
			{
				sscanf(parameter + 1, "%u", value);
				res = SUCCEED;
			}
		}
		else if (SUCCEED == is_uint_suffix(parameter, (unsigned int *)value))
		{
			*flag = ZBX_FLAG_SEC;
			res = SUCCEED;
		}
	}

	if (SUCCEED == res)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() flag:%d value:%d", __function_name, *flag, *value);
clean:
	zbx_free(parameter);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

static int	get_function_parameter_str(zbx_uint64_t hostid, const char *parameters, int Nparam, char **value)
{
	const char	*__function_name = "get_function_parameter_str";
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __function_name, parameters, Nparam);

	*value = zbx_malloc(*value, FUNCTION_PARAMETER_LEN_MAX);

	if (0 != get_param(parameters, Nparam, *value, FUNCTION_PARAMETER_LEN_MAX))
		goto clean;

	res = substitute_simple_macros(NULL, &hostid, NULL, NULL, NULL, value, MACRO_TYPE_FUNCTION_PARAMETER, NULL, 0);
clean:
	if (SUCCEED == res)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:'%s'", __function_name, *value);
	else
		zbx_free(*value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_LOGEVENTID                                              *
 *                                                                            *
 * Purpose: evaluate function 'logeventid' for the item                       *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - regex string for event id matching                 *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev, Rudolfs Kreicbergs                               *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGEVENTID(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_LOGEVENTID";
	char		*arg1 = NULL, *arg1_esc;
	int		res = FAIL;
	ZBX_REGEXP	*regexps = NULL;
	int		regexps_alloc = 0, regexps_num = 0;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
		goto clean;

	if (1 < num_param(parameters))
		goto clean;

	if (FAIL == get_function_parameter_str(item->hostid, parameters, 1, &arg1))
		goto clean;

	if ('@' == *arg1)
	{
		DB_RESULT	result;
		DB_ROW		row;

		arg1_esc = DBdyn_escape_string(arg1 + 1);
		result = DBselect("select r.name,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
				" from regexps r,expressions e"
				" where r.regexpid=e.regexpid"
					" and r.name='%s'",
				arg1_esc);
		zbx_free(arg1_esc);

		while (NULL != (row = DBfetch(result)))
		{
			add_regexp_ex(&regexps, &regexps_alloc, &regexps_num,
					row[0], row[1], atoi(row[2]), row[3][0], atoi(row[4]));
		}
		DBfree_result(result);
	}

	if (NULL == item->h_lasteventid)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				0, 0, NULL, "logeventid", 1);

		if (NULL != h_value[0])
		{
			item->h_lasteventid = zbx_strdup(item->h_lasteventid, h_value[0]);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for LOGEVENTID is empty");
		DBfree_history(h_value);
	}
	else
		res = SUCCEED;

	if (SUCCEED == res)
	{
		if (SUCCEED == regexp_match_ex(regexps, regexps_num, item->h_lasteventid, arg1, ZBX_CASE_SENSITIVE))
			zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
		else
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
	}

	if ('@' == *arg1)
		zbx_free(regexps);
	zbx_free(arg1);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_LOGSOURCE                                               *
 *                                                                            *
 * Purpose: evaluate function 'logsource' for the item                        *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - ignored                                            *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGSOURCE(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_LOGSOURCE";
	char		*arg1 = NULL;
	int		res = FAIL;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
		goto clean;

	if (1 < num_param(parameters))
		goto clean;

	if (FAIL == get_function_parameter_str(item->hostid, parameters, 1, &arg1))
		goto clean;

	if (NULL == item->h_lastsource)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				0, 0, NULL, "source", 1);

		if (NULL != h_value[0])
		{
			item->h_lastsource = zbx_strdup(item->h_lastsource, h_value[0]);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for LOGSOURCE is empty");
		DBfree_history(h_value);
	}
	else
		res = SUCCEED;

	if (SUCCEED == res)
	{
		if (0 == strcmp(item->h_lastsource, arg1))
			zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
		else
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
	}

	zbx_free(arg1);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_LOGSEVERITY                                             *
 *                                                                            *
 * Purpose: evaluate function 'logseverity' for the item                      *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - ignored                                            *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGSEVERITY(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_LOGSEVERITY";
	int		res = FAIL;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
		goto clean;

	if (NULL == item->h_lastseverity)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE, 0, 0, NULL, "severity", 1);

		if (NULL != h_value[0])
		{
			item->h_lastseverity = zbx_strdup(item->h_lastseverity, h_value[0]);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for LOGSEVERITY is empty");
		DBfree_history(h_value);
	}
	else
		res = SUCCEED;

	if (SUCCEED == res)
		zbx_strlcpy(value, item->h_lastseverity, MAX_BUFFER_LEN);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

#define OP_EQ	0
#define OP_NE	1
#define OP_GT	2
#define OP_GE	3
#define OP_LT	4
#define OP_LE	5
#define OP_LIKE	6
#define OP_MAX	7

static int	evaluate_COUNT_one(unsigned char value_type, int op, const char *value, const char *arg2)
{
	zbx_uint64_t	value_uint64 = 0, arg2_uint64;
	double		value_double = 0, arg2_double;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			if (SUCCEED != str2uint64(arg2, "KMGTsmhdw", &arg2_uint64))
				return FAIL;
			ZBX_STR2UINT64(value_uint64, value);

			switch (op)
			{
				case OP_EQ:
					if (value_uint64 == arg2_uint64)
						return SUCCEED;
					break;
				case OP_NE:
					if (value_uint64 != arg2_uint64)
						return SUCCEED;
					break;
				case OP_GT:
					if (value_uint64 > arg2_uint64)
						return SUCCEED;
					break;
				case OP_GE:
					if (value_uint64 >= arg2_uint64)
						return SUCCEED;
					break;
				case OP_LT:
					if (value_uint64 < arg2_uint64)
						return SUCCEED;
					break;
				case OP_LE:
					if (value_uint64 <= arg2_uint64)
						return SUCCEED;
					break;
			}

			break;
		case ITEM_VALUE_TYPE_FLOAT:
			if (SUCCEED != is_double_suffix(arg2))
				return FAIL;
			arg2_double = str2double(arg2);
			value_double = atof(value);

			switch (op)
			{
				case OP_EQ:
					if (value_double > arg2_double - TRIGGER_EPSILON &&
							value_double < arg2_double + TRIGGER_EPSILON)
					{
						return SUCCEED;
					}
					break;
				case OP_NE:
					if (!(value_double > arg2_double - TRIGGER_EPSILON &&
								value_double < arg2_double + TRIGGER_EPSILON))
					{
						return SUCCEED;
					}
					break;
				case OP_GT:
					if (value_double >= arg2_double + TRIGGER_EPSILON)
						return SUCCEED;
					break;
				case OP_GE:
					if (value_double > arg2_double - TRIGGER_EPSILON)
						return SUCCEED;
					break;
				case OP_LT:
					if (value_double <= arg2_double - TRIGGER_EPSILON)
						return SUCCEED;
					break;
				case OP_LE:
					if (value_double < arg2_double + TRIGGER_EPSILON)
						return SUCCEED;
					break;
			}

			break;
		default:
			switch (op)
			{
				case OP_EQ:
					if (0 == strcmp(value, arg2))
						return SUCCEED;
					break;
				case OP_NE:
					if (0 != strcmp(value, arg2))
						return SUCCEED;
					break;
				case OP_LIKE:
					if (NULL != strstr(value, arg2))
						return SUCCEED;
					break;
			}
	}

	return FAIL;
}

static int	evaluate_COUNT_local(DB_ITEM *item, int op, int arg1, const char *arg2, int *count)
{
	int	h_num;

	if (2 < arg1)
		return FAIL;

	for (h_num = 0; h_num < arg1; h_num++)
	{
		const char	*lastvalue;

		if (NULL == item->lastvalue[h_num])
			break;

		if (NULL == arg2)
		{
			(*count)++;
			continue;
		}

		if ((ITEM_VALUE_TYPE_TEXT == item->value_type || ITEM_VALUE_TYPE_LOG == item->value_type) &&
				NULL == item->h_lastvalue[h_num] &&
				ITEM_LASTVALUE_LEN == zbx_strlen_utf8(item->lastvalue[h_num]))
		{
			*count = 0;
			return FAIL;
		}

		lastvalue = (NULL == item->h_lastvalue[h_num] ? item->lastvalue[h_num] :
				item->h_lastvalue[h_num]);

		if (SUCCEED == evaluate_COUNT_one(item->value_type, op, lastvalue, arg2))
			(*count)++;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_COUNT                                                   *
 *                                                                            *
 * Purpose: evaluate function 'count' for the item                            *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - up to four comma-separated fields:                *
 *                            (1) number of seconds/values                    *
 *                            (2) value to compare with (optional)            *
 *                            (3) comparison operator (optional)              *
 *                            (4) time shift (optional)                       *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_COUNT(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_COUNT";
	int		arg1, flag, op, numeric_search, nparams, count = 0, h_num, res = FAIL;
	int		time_shift = 0, time_shift_flag;
	char		*arg2 = NULL, *arg3 = NULL;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	numeric_search = (ITEM_VALUE_TYPE_UINT64 == item->value_type || ITEM_VALUE_TYPE_FLOAT == item->value_type);
	op = (numeric_search ? OP_EQ : OP_LIKE);

	if (4 < (nparams = num_param(parameters)))
		goto exit;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag))
		goto exit;

	if (2 <= nparams && FAIL == get_function_parameter_str(item->hostid, parameters, 2, &arg2))
		goto exit;

	if (3 <= nparams)
	{
		int	fail = 2;

		if (FAIL == get_function_parameter_str(item->hostid, parameters, 3, &arg3))
			goto clean;

		if ('\0' == *arg3)
			op = (0 != numeric_search ? OP_EQ : OP_LIKE);
		else if (0 == strcmp(arg3, "eq"))
			op = OP_EQ;
		else if (0 == strcmp(arg3, "ne"))
			op = OP_NE;
		else if (0 == strcmp(arg3, "gt"))
			op = OP_GT;
		else if (0 == strcmp(arg3, "ge"))
			op = OP_GE;
		else if (0 == strcmp(arg3, "lt"))
			op = OP_LT;
		else if (0 == strcmp(arg3, "le"))
			op = OP_LE;
		else if (0 == strcmp(arg3, "like"))
			op = OP_LIKE;
		else
			fail = 1;

		if (1 == fail)
			zabbix_log(LOG_LEVEL_DEBUG, "operator \"%s\" is not supported for function COUNT", arg3);
		else if (0 != numeric_search && OP_LIKE == op)
			zabbix_log(LOG_LEVEL_DEBUG, "operator \"like\" is not supported for counting numeric values");
		else if (0 == numeric_search && OP_LIKE != op && OP_EQ != op && OP_NE != op)
			zabbix_log(LOG_LEVEL_DEBUG, "operator \"%s\" is not supported for counting textual values", arg3);
		else
			fail = 0;

		zbx_free(arg3);

		if (0 != fail)
			goto clean;
	}

	if (4 <= nparams)
	{
		if (FAIL == get_function_parameter_uint(item->hostid, parameters, 4, &time_shift, &time_shift_flag) ||
				ZBX_FLAG_SEC != time_shift_flag)
		{
			goto clean;
		}

		now -= time_shift;
	}

	if (NULL != arg2 && '\0' == *arg2 && (0 != numeric_search || OP_LIKE == op))
		zbx_free(arg2);

	if (ZBX_FLAG_SEC == flag && NULL == arg2)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_COUNT,
				now - arg1, now, NULL, NULL, 0);

		if (NULL == h_value[0])
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
		else
			zbx_snprintf(value, MAX_BUFFER_LEN, "%s", h_value[0]);
		DBfree_history(h_value);
	}
	else
	{
		if (ZBX_FLAG_VALUES == flag)
		{
			if (0 == time_shift && SUCCEED == evaluate_COUNT_local(item, op, arg1, arg2, &count))
				goto skip_get_history;

			h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
					0, now, NULL, NULL, arg1);
		}
		else
		{
			h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
					now - arg1, now, NULL, NULL, 0);
		}

		if (ZBX_FLAG_VALUES == flag && 0 == time_shift &&
				(ITEM_VALUE_TYPE_TEXT == item->value_type || ITEM_VALUE_TYPE_LOG == item->value_type))
		{
			/* only last and prev value will be cached */

			for (h_num = 0; NULL != h_value[h_num] && 2 > h_num; h_num++)
			{
				if (NULL == item->h_lastvalue[h_num] &&
						ITEM_LASTVALUE_LEN <= zbx_strlen_utf8(h_value[h_num]))
				{
					item->h_lastvalue[h_num] = zbx_strdup(NULL, h_value[h_num]);
				}
			}
		}

		for (h_num = 0; NULL != h_value[h_num]; h_num++)
		{
			if (NULL == arg2 || SUCCEED == evaluate_COUNT_one(item->value_type, op, h_value[h_num], arg2))
				count++;
		}
		DBfree_history(h_value);
skip_get_history:
		zbx_snprintf(value, MAX_BUFFER_LEN, "%d", count);
	}

	res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() value:%s", __function_name, value);
clean:
	zbx_free(arg2);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

#undef OP_EQ
#undef OP_NE
#undef OP_GT
#undef OP_GE
#undef OP_LT
#undef OP_LE
#undef OP_LIKE
#undef OP_MAX

/******************************************************************************
 *                                                                            *
 * Function: evaluate_SUM                                                     *
 *                                                                            *
 * Purpose: evaluate function 'sum' for the item                              *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_SUM(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_SUM";
	int		nparams, arg1, flag, h_num, res = FAIL;
	double		sum = 0;
	zbx_uint64_t	l, sum_uint64 = 0;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto clean;

	if (2 < (nparams = num_param(parameters)))
		goto clean;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag))
		goto clean;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (FAIL == get_function_parameter_uint(item->hostid, parameters, 2, &time_shift, &time_shift_flag))
			goto clean;
		if (ZBX_FLAG_SEC != time_shift_flag)
			goto clean;

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_SUM,
			now - arg1, now, NULL, NULL, 0);

		if (NULL != h_value[0])
		{
			zbx_strlcpy(value, h_value[0], MAX_BUFFER_LEN);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for SUM is empty");
		DBfree_history(h_value);
	}
	else if (ZBX_FLAG_VALUES == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE, 0, now, NULL, NULL, arg1);

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			for (h_num = 0; NULL != h_value[h_num]; h_num++)
			{
				ZBX_STR2UINT64(l, h_value[h_num]);
				sum_uint64 += l;
			}
		}
		else
		{
			for (h_num = 0; NULL != h_value[h_num]; h_num++)
				sum += atof(h_value[h_num]);
		}
		DBfree_history(h_value);

		if (0 != h_num)
		{
			if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64, sum_uint64);
			else
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL, sum);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for SUM is empty");
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_AVG                                                     *
 *                                                                            *
 * Purpose: evaluate function 'avg' for the item                              *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_AVG(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_AVG";
	int		nparams, arg1, flag, h_num, res = FAIL;
	double		sum = 0;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto clean;

	if (2 < (nparams = num_param(parameters)))
		goto clean;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag))
		goto clean;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (FAIL == get_function_parameter_uint(item->hostid, parameters, 2, &time_shift, &time_shift_flag))
			goto clean;
		if (ZBX_FLAG_SEC != time_shift_flag)
			goto clean;

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_AVG,
				now - arg1, now, NULL, NULL, 0);

		if (NULL != h_value[0])
		{
			zbx_strlcpy(value, h_value[0], MAX_BUFFER_LEN);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for AVG is empty");
		DBfree_history(h_value);
	}
	else if (ZBX_FLAG_VALUES == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				0, now, NULL, NULL, arg1);

		for (h_num = 0; NULL != h_value[h_num]; h_num++)
			sum += atof(h_value[h_num]);
		DBfree_history(h_value);

		if (0 != h_num)
		{
			zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL, sum / (double)h_num);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for AVG is empty");
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_LAST                                                    *
 *                                                                            *
 * Purpose: evaluate functions 'last' and 'prev' for the item                 *
 *                                                                            *
 * Parameters: value - buffer of size MAX_BUFFER_LEN                          *
 *             item - item (performance metric)                               *
 *             parameters - Nth last value and time shift (optional)          *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LAST(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_LAST";
	int		arg1, flag, time_shift = 0, time_shift_flag, res = FAIL, h_num;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == strcmp(function, "last"))
	{
		if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag) || ZBX_FLAG_VALUES != flag)
		{
			arg1 = 1;
			flag = ZBX_FLAG_VALUES;
		}

		if (2 == num_param(parameters))
		{
			if (SUCCEED == get_function_parameter_uint(item->hostid, parameters, 2, &time_shift, &time_shift_flag) &&
					ZBX_FLAG_SEC == time_shift_flag)
			{
				now -= time_shift;
				time_shift = 1;
			}
			else
				goto clean;
		}
	}
	else if (0 == strcmp(function, "prev"))
	{
		arg1 = 2;
		flag = ZBX_FLAG_VALUES;
	}
	else
		goto clean;

	if (0 == time_shift && 1 == arg1)
	{
		if (NULL != item->lastvalue[0])
		{
			res = SUCCEED;

			switch (item->value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
				case ITEM_VALUE_TYPE_STR:
					zbx_strlcpy(value, item->lastvalue[0], MAX_BUFFER_LEN);
					break;
				default:
					if (NULL == item->h_lastvalue[0])
					{
						zbx_strlcpy(value, item->lastvalue[0], MAX_BUFFER_LEN);
						if (ITEM_LASTVALUE_LEN == zbx_strlen_utf8(item->lastvalue[0]))
							goto history;
					}
					else
						zbx_strlcpy(value, item->h_lastvalue[0], MAX_BUFFER_LEN);
					break;
			}
		}
	}
	else if (0 == time_shift && 2 == arg1)
	{
		if (NULL != item->lastvalue[1])
		{
			res = SUCCEED;

			switch (item->value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
				case ITEM_VALUE_TYPE_STR:
					zbx_strlcpy(value, item->lastvalue[1], MAX_BUFFER_LEN);
					break;
				default:
					if (NULL == item->h_lastvalue[1])
					{
						zbx_strlcpy(value, item->lastvalue[1], MAX_BUFFER_LEN);
						if (ITEM_LASTVALUE_LEN == zbx_strlen_utf8(item->lastvalue[1]))
							goto history;
					}
					else
						zbx_strlcpy(value, item->h_lastvalue[1], MAX_BUFFER_LEN);
					break;
			}
		}
	}
	else
	{
history:
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				0, now, NULL, NULL, arg1);

		if (0 == time_shift && (ITEM_VALUE_TYPE_TEXT == item->value_type || ITEM_VALUE_TYPE_LOG == item->value_type))
		{
			/* only last and prev value will be cached */

			for (h_num = 0; NULL != h_value[h_num] && 2 > h_num; h_num++)
			{
				if (NULL == item->h_lastvalue[h_num] &&
						ITEM_LASTVALUE_LEN <= zbx_strlen_utf8(h_value[h_num]))
				{
					item->h_lastvalue[h_num] = zbx_strdup(NULL, h_value[h_num]);
				}
			}
		}

		for (h_num = 0; NULL != h_value[h_num]; h_num++)
		{
			if (arg1 == h_num + 1)
			{
				zbx_strlcpy(value, h_value[h_num], MAX_BUFFER_LEN);
				res = SUCCEED;
				break;
			}
		}
		DBfree_history(h_value);
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_MIN                                                     *
 *                                                                            *
 * Purpose: evaluate function 'min' for the item                              *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_MIN(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_MIN";
	int		nparams, arg1, flag, h_num, res = FAIL;
	zbx_uint64_t	min_uint64 = 0, l;
	double		min = 0, f;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto clean;

	if (2 < (nparams = num_param(parameters)))
		goto clean;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag))
		goto clean;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (FAIL == get_function_parameter_uint(item->hostid, parameters, 2, &time_shift, &time_shift_flag))
			goto clean;
		if (ZBX_FLAG_SEC != time_shift_flag)
			goto clean;

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_MIN,
				now - arg1, now, NULL, NULL, 0);

		if (NULL != h_value[0])
		{
			zbx_strlcpy(value, h_value[0], MAX_BUFFER_LEN);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for MIN is empty");
		DBfree_history(h_value);
	}
	else if (ZBX_FLAG_VALUES == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				0, now, NULL, NULL, arg1);

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			for (h_num = 0; NULL != h_value[h_num]; h_num++)
			{
				ZBX_STR2UINT64(l, h_value[h_num]);
				if (0 == h_num || l < min_uint64)
					min_uint64 = l;
			}
		}
		else
		{
			for (h_num = 0; NULL != h_value[h_num]; h_num++)
			{
				f = atof(h_value[h_num]);
				if (0 == h_num || f < min)
					min = f;
			}
		}
		DBfree_history(h_value);

		if (0 != h_num)
		{
			if (item->value_type == ITEM_VALUE_TYPE_UINT64)
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64, min_uint64);
			else
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL, min);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for MIN is empty");
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_MAX                                                     *
 *                                                                            *
 * Purpose: evaluate function 'max' for the item                              *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_MAX(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_MAX";
	int		nparams, arg1, flag, h_num, res = FAIL;
	zbx_uint64_t	max_uint64 = 0, l;
	double		max = 0, f;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto clean;

	if (2 < (nparams = num_param(parameters)))
		goto clean;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag))
		goto clean;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (FAIL == get_function_parameter_uint(item->hostid, parameters, 2, &time_shift, &time_shift_flag))
			goto clean;
		if (ZBX_FLAG_SEC != time_shift_flag)
			goto clean;

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_MAX,
				now - arg1, now, NULL, NULL, 0);

		if (NULL != h_value[0])
		{
			zbx_strlcpy(value, h_value[0], MAX_BUFFER_LEN);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for MAX is empty");
		DBfree_history(h_value);
	}
	else if (ZBX_FLAG_VALUES == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				0, now, NULL, NULL, arg1);

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			for (h_num = 0; NULL != h_value[h_num]; h_num++)
			{
				ZBX_STR2UINT64(l, h_value[h_num]);
				if (0 == h_num || l > max_uint64)
					max_uint64 = l;
			}
		}
		else
		{
			for (h_num = 0; NULL != h_value[h_num]; h_num++)
			{
				f = atof(h_value[h_num]);
				if (0 == h_num || f > max)
					max = f;
			}
		}
		DBfree_history(h_value);

		if (0 != h_num)
		{
			if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64, max_uint64);
			else
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL, max);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for MAX is empty");
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_DELTA                                                   *
 *                                                                            *
 * Purpose: evaluate function 'delta' for the item                            *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - number of seconds/values and time shift (optional)*
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_DELTA(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_DELTA";
	int		nparams, arg1, flag, h_num, res = FAIL;
	zbx_uint64_t	min_uint64 = 0, max_uint64 = 0, l;
	double		min = 0, max = 0, f;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto clean;

	if (2 < (nparams = num_param(parameters)))
		goto clean;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag))
		goto clean;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (FAIL == get_function_parameter_uint(item->hostid, parameters, 2, &time_shift, &time_shift_flag))
			goto clean;
		if (ZBX_FLAG_SEC != time_shift_flag)
			goto clean;

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_DELTA,
				now - arg1, now, NULL, NULL, 0);

		if (NULL != h_value[0])
		{
			zbx_strlcpy(value, h_value[0], MAX_BUFFER_LEN);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for DELTA is empty");
		DBfree_history(h_value);
	}
	else if (ZBX_FLAG_VALUES == flag)
	{
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				0, now, NULL, NULL, arg1);

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			for (h_num = 0; NULL != h_value[h_num]; h_num++)
			{
				ZBX_STR2UINT64(l, h_value[h_num]);
				if (0 == h_num || l < min_uint64)
					min_uint64 = l;
				if (0 == h_num || l > max_uint64)
					max_uint64 = l;
			}
		}
		else
		{
			for (h_num = 0; NULL != h_value[h_num]; h_num++)
			{
				f = atof(h_value[h_num]);
				if (0 == h_num || f < min)
					min = f;
				if (0 == h_num || f > max)
					max = f;
			}
		}
		DBfree_history(h_value);

		if (0 != h_num)
		{
			if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64, max_uint64 - min_uint64);
			else
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL, max - min);
			res = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "result for DELTA is empty");
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_NODATA                                                  *
 *                                                                            *
 * Purpose: evaluate function 'nodata' for the item                           *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_NODATA(char *value, DB_ITEM *item, const char *function, const char *parameters)
{
	const char	*__function_name = "evaluate_NODATA";
	int		arg1, flag, now, res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 < num_param(parameters))
		goto clean;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag))
		goto clean;

	if (ZBX_FLAG_SEC != flag)
		goto clean;

	now = (int)time(NULL);

	if (item->lastclock + arg1 > now)
		zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
	else
	{
		if (CONFIG_SERVER_STARTUP_TIME + arg1 > now)
			goto clean;

		zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
	}

	res = SUCCEED;
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: compare_last_and_prev                                            *
 *                                                                            *
 * Purpose: compare lastvalue[0] and lastvalue[1] for an item                 *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *                                                                            *
 * Return value: 0 - values are equal                                         *
 *               non-zero - otherwise                                         *
 *                                                                            *
 * Author: Aleksandrs Saveljevs, Alexander Vladishev                          *
 *                                                                            *
 * Comments: To be used by functions abschange(), change(), and diff().       *
 *                                                                            *
 ******************************************************************************/
static int	compare_last_and_prev(DB_ITEM *item, time_t now)
{
	int	i, res;
	char	**h_value;

	if (NULL != item->h_lastvalue[0] && NULL != item->h_lastvalue[1])
		return strcmp(item->h_lastvalue[0], item->h_lastvalue[1]);

	for (i = 0; '\0' != item->lastvalue[0][i] || '\0' != item->lastvalue[1][i]; i++)
	{
		if (item->lastvalue[0][i] != item->lastvalue[1][i])
			return 1;
	}

	if (ITEM_VALUE_TYPE_STR == item->value_type || ITEM_LASTVALUE_LEN > zbx_strlen_utf8(item->lastvalue[0]))
		return 0;

	res = 0;	/* if values are no longer in history, consider them equal */

	h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
			0, now, NULL, NULL, 2);

	for (i = 0; NULL != h_value[i]; i++)
	{
		if (NULL == item->h_lastvalue[i] && ITEM_LASTVALUE_LEN <= zbx_strlen_utf8(h_value[i]))
			item->h_lastvalue[i] = zbx_strdup(NULL, h_value[i]);
	}

	if (NULL != h_value[0] && NULL != h_value[1])
		res = strcmp(h_value[0], h_value[1]);
	DBfree_history(h_value);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_ABSCHANGE                                               *
 *                                                                            *
 * Purpose: evaluate function 'abschange' for the item                        *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_ABSCHANGE(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_ABSCHANGE";
	history_value_t	lastvalue[2];
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == item->lastvalue[0] || NULL == item->lastvalue[1])
		goto clean;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			lastvalue[0].dbl = atof(item->lastvalue[0]);
			lastvalue[1].dbl = atof(item->lastvalue[1]);
			zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL, fabs(lastvalue[0].dbl - lastvalue[1].dbl));
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(lastvalue[0].ui64, item->lastvalue[0]);
			ZBX_STR2UINT64(lastvalue[1].ui64, item->lastvalue[1]);
			/* to avoid overflow */
			if (lastvalue[0].ui64 >= lastvalue[1].ui64)
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64, lastvalue[0].ui64 - lastvalue[1].ui64);
			else
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64, lastvalue[1].ui64 - lastvalue[0].ui64);
			break;
		default:
			if (0 == compare_last_and_prev(item, now))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
	}
	res = SUCCEED;
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_CHANGE                                                  *
 *                                                                            *
 * Purpose: evaluate function 'change' for the item                           *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_CHANGE(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_CHANGE";
	history_value_t	lastvalue[2];
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == item->lastvalue[0] || NULL == item->lastvalue[1])
		goto clean;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			lastvalue[0].dbl = atof(item->lastvalue[0]);
			lastvalue[1].dbl = atof(item->lastvalue[1]);
			zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL, lastvalue[0].dbl - lastvalue[1].dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(lastvalue[0].ui64, item->lastvalue[0]);
			ZBX_STR2UINT64(lastvalue[1].ui64, item->lastvalue[1]);
			/* to avoid overflow */
			if (lastvalue[0].ui64 >= lastvalue[1].ui64)
			{
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64, lastvalue[0].ui64 - lastvalue[1].ui64);
			}
			else
			{
				zbx_snprintf(value, MAX_BUFFER_LEN, "-" ZBX_FS_UI64,
						lastvalue[1].ui64 - lastvalue[0].ui64);
			}
			break;
		default:
			if (0 == compare_last_and_prev(item, now))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
	}

	res = SUCCEED;
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_DIFF                                                    *
 *                                                                            *
 * Purpose: evaluate function 'diff' for the item                             *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_DIFF(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_DIFF";
	history_value_t	lastvalue[2];
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == item->lastvalue[0] || NULL == item->lastvalue[1])
		goto clean;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			lastvalue[0].dbl = atof(item->lastvalue[0]);
			lastvalue[1].dbl = atof(item->lastvalue[1]);
			if (SUCCEED == cmp_double(lastvalue[0].dbl, lastvalue[1].dbl))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(lastvalue[0].ui64, item->lastvalue[0]);
			ZBX_STR2UINT64(lastvalue[1].ui64, item->lastvalue[1]);
			if (lastvalue[0].ui64 == lastvalue[1].ui64)
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
		default:
			if (0 == compare_last_and_prev(item, now))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
	}

	res = SUCCEED;
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_STR                                                     *
 *                                                                            *
 * Purpose: evaluate function 'str' for the item                              *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameters - <string>[,seconds]                                *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/

#define ZBX_FUNC_STR		1
#define ZBX_FUNC_REGEXP		2
#define ZBX_FUNC_IREGEXP	3

static int	evaluate_STR_one(int func, ZBX_REGEXP *regexps, int regexps_num, const char *value, const char *arg1)
{
	switch (func)
	{
		case ZBX_FUNC_STR:
			if (NULL != strstr(value, arg1))
				return SUCCEED;
			break;
		case ZBX_FUNC_REGEXP:
			return regexp_match_ex(regexps, regexps_num, value, arg1, ZBX_CASE_SENSITIVE);
		case ZBX_FUNC_IREGEXP:
			return regexp_match_ex(regexps, regexps_num, value, arg1, ZBX_IGNORE_CASE);
	}

	return FAIL;
}

static int	evaluate_STR_local(DB_ITEM *item, int func, ZBX_REGEXP *regexps, int regexps_num,
			const char *arg1, int arg2, int *found)
{
	int	h_num;

	for (h_num = 0; h_num < MIN(arg2, 2); h_num++)
	{
		const char	*lastvalue;

		if (NULL == item->lastvalue[h_num])
			return SUCCEED;

		if (ITEM_VALUE_TYPE_STR != item->value_type && NULL == item->h_lastvalue[h_num] &&
				ITEM_LASTVALUE_LEN == zbx_strlen_utf8(item->lastvalue[h_num]))
		{
			return FAIL;
		}

		lastvalue = (NULL == item->h_lastvalue[h_num] ? item->lastvalue[h_num] :
				item->h_lastvalue[h_num]);

		if (SUCCEED == evaluate_STR_one(func, regexps, regexps_num, lastvalue, arg1))
		{
			*found = 1;
			return SUCCEED;
		}
	}

	if (2 >= arg2)
		return SUCCEED;

	return FAIL;
}

static int	evaluate_STR(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_STR";
	DB_RESULT	result;
	DB_ROW		row;
	char		*arg1 = NULL, *arg1_esc;
	int		arg2, flag, func, found = 0, h_num, res = FAIL;
	ZBX_REGEXP	*regexps = NULL;
	int		regexps_alloc = 0, regexps_num = 0;
	char		**h_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_STR != item->value_type && ITEM_VALUE_TYPE_TEXT != item->value_type &&
			ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		goto exit;
	}

	if (0 == strcmp(function, "str"))
		func = ZBX_FUNC_STR;
	else if (0 == strcmp(function, "regexp"))
		func = ZBX_FUNC_REGEXP;
	else if (0 == strcmp(function, "iregexp"))
		func = ZBX_FUNC_IREGEXP;
	else
		goto exit;

	if (2 < num_param(parameters))
		goto exit;

	if (FAIL == get_function_parameter_str(item->hostid, parameters, 1, &arg1))
		goto exit;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 2, &arg2, &flag))
	{
		arg2 = 1;
		flag = ZBX_FLAG_VALUES;
	}

	if ((ZBX_FUNC_REGEXP == func || ZBX_FUNC_IREGEXP == func) && '@' == *arg1)
	{
		arg1_esc = DBdyn_escape_string(arg1 + 1);
		result = DBselect("select r.name,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
				" from regexps r,expressions e"
				" where r.regexpid=e.regexpid"
					" and r.name='%s'",
				arg1_esc);
		zbx_free(arg1_esc);

		while (NULL != (row = DBfetch(result)))
		{
			add_regexp_ex(&regexps, &regexps_alloc, &regexps_num,
					row[0], row[1], atoi(row[2]), row[3][0], atoi(row[4]));
		}
		DBfree_result(result);
	}

	if (ZBX_FLAG_VALUES == flag)
	{
		if (0 == arg2 || NULL == item->lastvalue[0])
		{
			zabbix_log(LOG_LEVEL_DEBUG, "result for STR is empty");
			goto clean;
		}

		if (SUCCEED == evaluate_STR_local(item, func, regexps, regexps_num, arg1, arg2, &found))
			goto skip_get_history;

		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				0, now, NULL, NULL, arg2);
	}
	else
		h_value = DBget_history(item->itemid, item->value_type, ZBX_DB_GET_HIST_VALUE,
				now - arg2, now, NULL, NULL, 0);

	if (ZBX_FLAG_VALUES == flag && ITEM_VALUE_TYPE_STR != item->value_type)
	{
		/* only last and prev value will be cached */

		for (h_num = 0; NULL != h_value[h_num] && 2 > h_num; h_num++)
		{
			if (NULL == item->h_lastvalue[h_num] &&
					ITEM_LASTVALUE_LEN <= zbx_strlen_utf8(h_value[h_num]))
			{
				item->h_lastvalue[h_num] = zbx_strdup(NULL, h_value[h_num]);
			}
		}
	}

	if (NULL == h_value[0])
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for STR is empty");
		DBfree_history(h_value);
		goto clean;
	}

	for (h_num = 0; NULL != h_value[h_num]; h_num++)
	{
		if (SUCCEED == evaluate_STR_one(func, regexps, regexps_num, h_value[h_num], arg1))
		{
			found = 1;
			break;
		}
	}
	DBfree_history(h_value);
skip_get_history:
	if (1 == found)
		zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
	else
		zbx_strlcpy(value, "0", MAX_BUFFER_LEN);

	res = SUCCEED;
clean:
	if ((ZBX_FUNC_REGEXP == func || ZBX_FUNC_IREGEXP == func) && '@' == *arg1)
		zbx_free(regexps);

	zbx_free(arg1);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

#undef ZBX_FUNC_STR
#undef ZBX_FUNC_REGEXP
#undef ZBX_FUNC_IREGEXP

/******************************************************************************
 *                                                                            *
 * Function: evaluate_STRLEN                                                  *
 *                                                                            *
 * Purpose: evaluate function 'strlen' for the item                           *
 *                                                                            *
 * Parameters: value - buffer of size MAX_BUFFER_LEN                          *
 *             item - item (performance metric)                               *
 *             parameters - Nth last value and time shift (optional)          *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_STRLEN(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_STRLEN";
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_STR != item->value_type && ITEM_VALUE_TYPE_TEXT != item->value_type &&
			ITEM_VALUE_TYPE_LOG != item->value_type)
		goto clean;

	if (SUCCEED == evaluate_LAST(value, item, "last", parameters, now))
	{
		zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_SIZE_T, (zbx_fs_size_t)zbx_strlen_utf8(value));
		res = SUCCEED;
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_FUZZYTIME                                               *
 *                                                                            *
 * Purpose: evaluate function 'fuzzytime' for the item                        *
 *                                                                            *
 * Parameters: item - item (performance metric)                               *
 *             parameter - number of seconds                                  *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_FUZZYTIME(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_FUZZYTIME";
	history_value_t	lastvalue;
	int		arg1, flag, fuzlow, fuzhig, res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto clean;

	if (1 < num_param(parameters))
		goto clean;

	if (FAIL == get_function_parameter_uint(item->hostid, parameters, 1, &arg1, &flag))
		goto clean;

	if (ZBX_FLAG_SEC != flag)
		goto clean;

	if (NULL == item->lastvalue[0])
		goto clean;

	fuzlow = (int)(now - arg1);
	fuzhig = (int)(now + arg1);

	if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
	{
		ZBX_STR2UINT64(lastvalue.ui64, item->lastvalue[0]);
		if (lastvalue.ui64 >= fuzlow && lastvalue.ui64 <= fuzhig)
			zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
		else
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
	}
	else
	{
		lastvalue.dbl = atof(item->lastvalue[0]);
		if (lastvalue.dbl >= fuzlow && lastvalue.dbl <= fuzhig)
			zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
		else
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
	}

	res = SUCCEED;
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_function                                                *
 *                                                                            *
 * Purpose: evaluate function                                                 *
 *                                                                            *
 * Parameters: item - item to calculate function for                          *
 *             function - function (for example, 'max')                       *
 *             parameter - parameter of the function                          *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains its value   *
 *               FAIL - evaluation failed                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	evaluate_function(char *value, DB_ITEM *item, const char *function, const char *parameter, time_t now)
{
	const char	*__function_name = "evaluate_function";

	int		ret;
	struct tm	*tm = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:'%s.%s(%s)'", __function_name,
			zbx_host_key_string_by_item(item), function, parameter);

	*value = '\0';

	if (0 == strcmp(function, "last") || 0 == strcmp(function, "prev"))
	{
		ret = evaluate_LAST(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "min"))
	{
		ret = evaluate_MIN(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "max"))
	{
		ret = evaluate_MAX(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "avg"))
	{
		ret = evaluate_AVG(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "sum"))
	{
		ret = evaluate_SUM(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "count"))
	{
		ret = evaluate_COUNT(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "delta"))
	{
		ret = evaluate_DELTA(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "nodata"))
	{
		ret = evaluate_NODATA(value, item, function, parameter);
	}
	else if (0 == strcmp(function, "date"))
	{
		tm = localtime(&now);
		zbx_snprintf(value, MAX_BUFFER_LEN, "%.4d%.2d%.2d", tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "dayofweek"))
	{
		tm = localtime(&now);
		zbx_snprintf(value, MAX_BUFFER_LEN, "%d", 0 == tm->tm_wday ? 7 : tm->tm_wday);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "dayofmonth"))
	{
		tm = localtime(&now);
		zbx_snprintf(value, MAX_BUFFER_LEN, "%d", tm->tm_mday);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "time"))
	{
		tm = localtime(&now);
		zbx_snprintf(value, MAX_BUFFER_LEN, "%.2d%.2d%.2d", tm->tm_hour, tm->tm_min, tm->tm_sec);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "abschange"))
	{
		ret = evaluate_ABSCHANGE(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "change"))
	{
		ret = evaluate_CHANGE(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "diff"))
	{
		ret = evaluate_DIFF(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "str") || 0 == strcmp(function, "regexp") || 0 == strcmp(function, "iregexp"))
	{
		ret = evaluate_STR(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "strlen"))
	{
		ret = evaluate_STRLEN(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "now"))
	{
		zbx_snprintf(value, MAX_BUFFER_LEN, "%d", (int)now);
		ret = SUCCEED;
	}
	else if (0 == strcmp(function, "fuzzytime"))
	{
		ret = evaluate_FUZZYTIME(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "logeventid"))
	{
		ret = evaluate_LOGEVENTID(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "logseverity"))
	{
		ret = evaluate_LOGSEVERITY(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "logsource"))
	{
		ret = evaluate_LOGSOURCE(value, item, function, parameter, now);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "unsupported function:%s", function);
		ret = FAIL;
	}

	if (SUCCEED == ret)
		del_zeroes(value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:'%s'", __function_name, zbx_result_string(ret), value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix_uptime                                          *
 *                                                                            *
 * Purpose: Process suffix 'uptime'                                           *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix_uptime(char *value, size_t max_len)
{
	const char	*__function_name = "add_value_suffix_uptime";

	double	secs, days;
	size_t	offset = 0;
	int	hours, mins;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 > (secs = round(atof(value))))
	{
		offset += zbx_snprintf(value, max_len, "-");
		secs = -secs;
	}

	days = floor(secs / SEC_PER_DAY);
	secs -= days * SEC_PER_DAY;

	hours = (int)(secs / SEC_PER_HOUR);
	secs -= (double)hours * SEC_PER_HOUR;

	mins = (int)(secs / SEC_PER_MIN);
	secs -= (double)mins * SEC_PER_MIN;

	if (0 != days)
	{
		if (1 == days)
			offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) " day, ", days);
		else
			offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) " days, ", days);
	}

	zbx_snprintf(value + offset, max_len - offset, "%02d:%02d:%02d", hours, mins, (int)secs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix_s                                               *
 *                                                                            *
 * Purpose: Process suffix 's'                                                *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix_s(char *value, size_t max_len)
{
	const char	*__function_name = "add_value_suffix_s";

	double	secs, n;
	size_t	offset = 0;
	int	n_unit = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	secs = atof(value);

	if (0 == floor(fabs(secs) * 1000))
	{
		zbx_snprintf(value, max_len, "%s", (0 == secs ? "0s" : "< 1ms"));
		goto clean;
	}

	if (0 > (secs = round(secs * 1000) / 1000))
	{
		offset += zbx_snprintf(value, max_len, "-");
		secs = -secs;
	}
	else
		*value = '\0';

	if (0 != (n = floor(secs / SEC_PER_YEAR)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) "y ", n);
		secs -= n * SEC_PER_YEAR;
		if (0 == n_unit)
			n_unit = 4;
	}

	if (0 != (n = floor(secs / SEC_PER_MONTH)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dm ", (int)n);
		secs -= n * SEC_PER_MONTH;
		if (0 == n_unit)
			n_unit = 3;
	}

	if (0 != (n = floor(secs / SEC_PER_DAY)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dd ", (int)n);
		secs -= n * SEC_PER_DAY;
		if (0 == n_unit)
			n_unit = 2;
	}

	if (4 > n_unit && 0 != (n = floor(secs / SEC_PER_HOUR)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dh ", (int)n);
		secs -= n * SEC_PER_HOUR;
		if (0 == n_unit)
			n_unit = 1;
	}

	if (3 > n_unit && 0 != (n = floor(secs / SEC_PER_MIN)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dm ", (int)n);
		secs -= n * SEC_PER_MIN;
	}

	if (2 > n_unit && 0 != (n = floor(secs)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%ds ", (int)n);
		secs -= n;
	}

	if (1 > n_unit && 0 != (n = round(secs * 1000)))
		offset += zbx_snprintf(value + offset, max_len - offset, "%dms", (int)n);

	if (0 != offset && ' ' == value[--offset])
		value[offset] = '\0';
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix_normal                                          *
 *                                                                            *
 * Purpose: Process normal values and add K,M,G,T                             *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *             units - units (bps, b, B, etc)                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix_normal(char *value, size_t max_len, const char *units)
{
	const char	*__function_name = "add_value_suffix_normal";

	const char	*minus = "";
	char		kmgt[8];
	char		tmp[64];
	double		base;
	double		value_double;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 > (value_double = atof(value)))
	{
		minus = "-";
		value_double = -value_double;
	}

	base = (0 == strcmp(units, "B") || 0 == strcmp(units, "Bps") ? 1024 : 1000);

	if (value_double < base || SUCCEED == str_in_list("%,ms,rpm,RPM", units, ','))
	{
		strscpy(kmgt, "");
	}
	else if (value_double < base * base)
	{
		strscpy(kmgt, "K");
		value_double /= base;
	}
	else if (value_double < base * base * base)
	{
		strscpy(kmgt, "M");
		value_double /= base * base;
	}
	else if (value_double < base * base * base * base)
	{
		strscpy(kmgt, "G");
		value_double /= base * base * base;
	}
	else
	{
		strscpy(kmgt, "T");
		value_double /= base * base * base * base;
	}

	if (SUCCEED != cmp_double((int)(value_double + 0.5), value_double))
	{
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(2), value_double);
		del_zeroes(tmp);
	}
	else
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(0), value_double);

	zbx_snprintf(value, max_len, "%s%s %s%s", minus, tmp, kmgt, units);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix                                                 *
 *                                                                            *
 * Purpose: Add suffix for value                                              *
 *                                                                            *
 * Parameters: value - value for replacing                                    *
 *                                                                            *
 * Return value: SUCCEED - suffix added successfully, value contains new value*
 *               FAIL - adding failed, value contains old value               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP (function convert_units) !!! *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix(char *value, size_t max_len, const char *units, unsigned char value_type)
{
	const char	*__function_name = "add_value_suffix";

	struct tm	*local_time;
	time_t		time;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() value:'%s' units:'%s' value_type:%d",
			__function_name, value, units, (int)value_type);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			if (0 == strcmp(units, "unixtime"))
			{
				time = (time_t)atoi(value);
				local_time = localtime(&time);
				strftime(value, max_len, "%Y.%m.%d %H:%M:%S", local_time);
				break;
			}
		case ITEM_VALUE_TYPE_FLOAT:
			if (0 == strcmp(units, "s"))
				add_value_suffix_s(value, max_len);
			else if (0 == strcmp(units, "uptime"))
				add_value_suffix_uptime(value, max_len);
			else if (0 != strlen(units))
				add_value_suffix_normal(value, max_len, units);
			break;
		default:
			;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:'%s'", __function_name, value);
}

/******************************************************************************
 *                                                                            *
 * Function: replace_value_by_map                                             *
 *                                                                            *
 * Purpose: replace value by mapping value                                    *
 *                                                                            *
 * Parameters: value - value for replacing                                    *
 *             valuemapid - index of value map                                *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains new value   *
 *               FAIL - evaluation failed, value contains old value           *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	replace_value_by_map(char *value, size_t max_len, zbx_uint64_t valuemapid)
{
	const char	*__function_name = "replace_value_by_map";

	DB_RESULT	result;
	DB_ROW		row;
	char		orig_value[MAX_BUFFER_LEN], *value_esc;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() value:'%s' valuemapid:" ZBX_FS_UI64, __function_name, value, valuemapid);

	if (0 == valuemapid)
		goto clean;

	value_esc = DBdyn_escape_string(value);
	result = DBselect(
			"select newvalue"
			" from mappings"
			" where valuemapid=" ZBX_FS_UI64
				" and value='%s'",
			valuemapid, value_esc);
	zbx_free(value_esc);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
	{
		del_zeroes(row[0]);

		strscpy(orig_value, value);

		zbx_snprintf(value, max_len, "%s (%s)", row[0], orig_value);

		ret = SUCCEED;
	}
	DBfree_result(result);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:'%s'", __function_name, value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_format_value                                                 *
 *                                                                            *
 * Purpose: replace value by value mapping or by units                        *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value for replacing                      *
 *             valuemapid - [IN] identificator of value map                   *
 *             units      - [IN] units                                        *
 *             value_type - [IN] value type; ITEM_VALUE_TYPE_*                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type)
{
	const char	*__function_name = "zbx_format_value";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
			replace_value_by_map(value, max_len, valuemapid);
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			del_zeroes(value);
		case ITEM_VALUE_TYPE_UINT64:
			if (SUCCEED != replace_value_by_map(value, max_len, valuemapid))
				add_value_suffix(value, max_len, units, value_type);
			break;
		default:
			;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_macro_function                                          *
 *                                                                            *
 * Purpose: evaluate function used as a macro (e.g., in notifications)        *
 *                                                                            *
 * Parameters: host - host the key belongs to                                 *
 *             key - item's key (for example, 'system.cpu.load[,avg1]')       *
 *             function - function (for example, 'max')                       *
 *             parameter - parameter of the function                          *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains its value   *
 *               FAIL - evaluation failed                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: used for evaluation of notification macros                       *
 *           output buffer size should be MAX_BUFFER_LEN                      *
 *                                                                            *
 ******************************************************************************/
int	evaluate_macro_function(char *value, const char *host, const char *key, const char *function, const char *parameter)
{
	const char	*__function_name = "evaluate_macro_function";

	DB_ITEM		item;
	DB_RESULT	result;
	DB_ROW		row;
	char		*host_esc, *key_esc;
	int		res;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:'%s:%s.%s(%s)'",
			__function_name, host, key, function, parameter);

	host_esc = DBdyn_escape_string(host);
	key_esc = DBdyn_escape_string(key);

	result = DBselect(
			"select %s"
			" where h.host='%s'"
				" and h.hostid=i.hostid"
				" and i.key_='%s'"
				ZBX_SQL_NODE,
			ZBX_SQL_ITEM_SELECT, host_esc, key_esc, DBand_node_local("h.hostid"));

	zbx_free(host_esc);
	zbx_free(key_esc);

	if (NULL == (row = DBfetch(result)))
	{
		DBfree_result(result);
		zabbix_log(LOG_LEVEL_WARNING, "Function [%s:%s.%s(%s)] not found. Query returned empty result",
				host, key, function, parameter);
		return FAIL;
	}

	DBget_item_from_db(&item, row);

	if (SUCCEED == (res = evaluate_function(value, &item, function, parameter, time(NULL))))
	{
		if (SUCCEED == str_in_list("last,prev", function, ','))
		{
			zbx_format_value(value, MAX_BUFFER_LEN, item.valuemapid, item.units, item.value_type);
		}
		else if (SUCCEED == str_in_list("abschange,avg,change,delta,max,min,sum", function, ','))
		{
			switch (item.value_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
					add_value_suffix(value, MAX_BUFFER_LEN, item.units, item.value_type);
					break;
				default:
					;
			}
		}
	}

	DBfree_result(result); /* cannot call DBfree_result until evaluate_FUNC */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:'%s'", __function_name,
			zbx_result_string(res), value);

	return res;
}
