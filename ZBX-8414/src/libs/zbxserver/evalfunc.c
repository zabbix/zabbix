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
#include "db.h"
#include "log.h"
#include "zbxserver.h"
#include "valuecache.h"
#include "evalfunc.h"
#include "zbxregexp.h"

int	cmp_double(double a, double b)
{
	return fabs(a - b) < TRIGGER_EPSILON ? SUCCEED : FAIL;
}

static int	__get_function_parameter_uint31(zbx_uint64_t hostid, const char *parameters, int Nparam,
		int *value, int *flag, int defaults_on_empty, int def_value, int def_flag)
{
	const char	*__function_name = "__get_function_parameter_uint31";
	char		*parameter;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __function_name, parameters, Nparam);

	if (NULL == (parameter = get_param_dyn(parameters, Nparam)))
		goto out;

	if (SUCCEED == substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL,
			&parameter, MACRO_TYPE_COMMON, NULL, 0))
	{
		if (1 == defaults_on_empty && '\0' == *parameter)
		{
			*value = def_value;
			*flag = def_flag;
			ret = SUCCEED;
		}
		else if ('#' == *parameter)
		{
			*flag = ZBX_FLAG_VALUES;
			if (SUCCEED == is_uint31(parameter + 1, (uint32_t *)value) && 0 < *value)
				ret = SUCCEED;
		}
		else if (SUCCEED == is_uint_suffix(parameter, (unsigned int *)value) && 0 <= *value)
		{
			*flag = ZBX_FLAG_SEC;
			ret = SUCCEED;
		}
	}

	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() flag:%d value:%d", __function_name, *flag, *value);

	zbx_free(parameter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	get_function_parameter_uint31(zbx_uint64_t hostid, const char *parameters, int Nparam, int *value,
		int *flag)
{
	return __get_function_parameter_uint31(hostid, parameters, Nparam, value, flag, 0, 0, ZBX_FLAG_SEC);
}

static int	get_function_parameter_uint31_default(zbx_uint64_t hostid, const char *parameters, int Nparam,
		int *value, int *flag, int def_value, int def_flag)
{
	return __get_function_parameter_uint31(hostid, parameters, Nparam, value, flag, 1, def_value, def_flag);
}

static int	get_function_parameter_uint64(zbx_uint64_t hostid, const char *parameters, int Nparam,
		zbx_uint64_t *value, int *flag)
{
	const char	*__function_name = "get_function_parameter_uint64";
	char		*parameter;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __function_name, parameters, Nparam);

	if (NULL == (parameter = get_param_dyn(parameters, Nparam)))
		goto out;

	if (SUCCEED == substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL,
			&parameter, MACRO_TYPE_COMMON, NULL, 0))
	{
		if (SUCCEED == is_uint64(parameter, value))
		{
			*flag = ZBX_FLAG_SEC;
			ret = SUCCEED;
		}
	}

	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() flag:%d value:" ZBX_FS_UI64, __function_name, *flag, *value);

	zbx_free(parameter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	get_function_parameter_str(zbx_uint64_t hostid, const char *parameters, int Nparam, char **value)
{
	const char	*__function_name = "get_function_parameter_str";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __function_name, parameters, Nparam);

	if (NULL == (*value = get_param_dyn(parameters, Nparam)))
		goto out;

	ret = substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL,
			value, MACRO_TYPE_COMMON, NULL, 0);

	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:'%s'", __function_name, *value);
	else
		zbx_free(*value);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_LOGEVENTID(char *value, DC_ITEM *item, const char *function, const char *parameters,
		time_t now)
{
	const char		*__function_name = "evaluate_LOGEVENTID";

	char			*arg1 = NULL;
	int			ret = FAIL;
	zbx_vector_ptr_t	regexps;
	zbx_history_record_t	vc_value;
	zbx_timespec_t		ts = {now, 999999999};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&regexps);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
		goto out;

	if (1 < num_param(parameters))
		goto out;

	if (SUCCEED != get_function_parameter_str(item->host.hostid, parameters, 1, &arg1))
		goto out;

	if ('@' == *arg1)
		DCget_expressions_by_name(&regexps, arg1 + 1);

	if (SUCCEED == zbx_vc_get_value(item->itemid, item->value_type, &ts, &vc_value))
	{
		char	logeventid[16];

		zbx_snprintf(logeventid, sizeof(logeventid), "%d", vc_value.value.log->logeventid);
		if (SUCCEED == regexp_match_ex(&regexps, logeventid, arg1, ZBX_CASE_SENSITIVE))
			zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
		else
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
		zbx_history_record_clear(&vc_value, item->value_type);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGEVENTID is empty");

	zbx_free(arg1);
out:
	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_LOGSOURCE(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char		*__function_name = "evaluate_LOGSOURCE";

	char			*arg1 = NULL;
	int			ret = FAIL;
	zbx_history_record_t	vc_value;
	zbx_timespec_t		ts = {now, 999999999};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
		goto out;

	if (1 < num_param(parameters))
		goto out;

	if (SUCCEED != get_function_parameter_str(item->host.hostid, parameters, 1, &arg1))
		goto out;

	if (SUCCEED == zbx_vc_get_value(item->itemid, item->value_type, &ts, &vc_value))
	{
		if (0 == strcmp(NULL == vc_value.value.log->source ? "" : vc_value.value.log->source, arg1))
			zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
		else
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
		zbx_history_record_clear(&vc_value, item->value_type);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGSOURCE is empty");

	zbx_free(arg1);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_LOGSEVERITY(char *value, DC_ITEM *item, const char *function, const char *parameters,
		time_t now)
{
	const char		*__function_name = "evaluate_LOGSEVERITY";

	int			ret = FAIL;
	zbx_history_record_t	vc_value;
	zbx_timespec_t		ts = {now, 999999999};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
		goto out;

	if (SUCCEED == zbx_vc_get_value(item->itemid, item->value_type, &ts, &vc_value))
	{
		zbx_snprintf(value, MAX_BUFFER_LEN, "%d", vc_value.value.log->severity);
		zbx_history_record_clear(&vc_value, item->value_type);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGSEVERITY is empty");
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#define OP_EQ	0
#define OP_NE	1
#define OP_GT	2
#define OP_GE	3
#define OP_LT	4
#define OP_LE	5
#define OP_LIKE	6
#define OP_BAND	7
#define OP_MAX	8

static int	evaluate_COUNT_one(unsigned char value_type, int op, history_value_t *value, const char *arg2,
		const char *arg2_2)
{
	zbx_uint64_t	 arg2_uint64, arg2_2_uint64;
	double		 arg2_double;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			if (SUCCEED != str2uint64(arg2, "KMGTsmhdw", &arg2_uint64))
				return FAIL;

			switch (op)
			{
				case OP_EQ:
					if (value->ui64 == arg2_uint64)
						return SUCCEED;
					break;
				case OP_NE:
					if (value->ui64 != arg2_uint64)
						return SUCCEED;
					break;
				case OP_GT:
					if (value->ui64 > arg2_uint64)
						return SUCCEED;
					break;
				case OP_GE:
					if (value->ui64 >= arg2_uint64)
						return SUCCEED;
					break;
				case OP_LT:
					if (value->ui64 < arg2_uint64)
						return SUCCEED;
					break;
				case OP_LE:
					if (value->ui64 <= arg2_uint64)
						return SUCCEED;
					break;
				case OP_BAND:
					if (NULL != arg2_2)
					{
						if (SUCCEED != is_uint64(arg2_2, &arg2_2_uint64))
							return FAIL;
					}
					else
						arg2_2_uint64 = arg2_uint64;

					if (arg2_uint64 == (value->ui64 & arg2_2_uint64))
						return SUCCEED;
					break;
			}

			break;
		case ITEM_VALUE_TYPE_FLOAT:
			if (SUCCEED != is_double_suffix(arg2))
				return FAIL;
			arg2_double = str2double(arg2);

			switch (op)
			{
				case OP_EQ:
					if (value->dbl > arg2_double - TRIGGER_EPSILON &&
							value->dbl < arg2_double + TRIGGER_EPSILON)
					{
						return SUCCEED;
					}
					break;
				case OP_NE:
					if (!(value->dbl > arg2_double - TRIGGER_EPSILON &&
							value->dbl < arg2_double + TRIGGER_EPSILON))
					{
						return SUCCEED;
					}
					break;
				case OP_GT:
					if (value->dbl >= arg2_double + TRIGGER_EPSILON)
						return SUCCEED;
					break;
				case OP_GE:
					if (value->dbl > arg2_double - TRIGGER_EPSILON)
						return SUCCEED;
					break;
				case OP_LT:
					if (value->dbl <= arg2_double - TRIGGER_EPSILON)
						return SUCCEED;
					break;
				case OP_LE:
					if (value->dbl < arg2_double + TRIGGER_EPSILON)
						return SUCCEED;
					break;
			}

			break;
		case ITEM_VALUE_TYPE_LOG:
			switch (op)
			{
				case OP_EQ:
					if (0 == strcmp(value->log->value, arg2))
						return SUCCEED;
					break;
				case OP_NE:
					if (0 != strcmp(value->log->value, arg2))
						return SUCCEED;
					break;
				case OP_LIKE:
					if (NULL != strstr(value->log->value, arg2))
						return SUCCEED;
					break;
			}

			break;
		default:
			switch (op)
			{
				case OP_EQ:
					if (0 == strcmp(value->str, arg2))
						return SUCCEED;
					break;
				case OP_NE:
					if (0 != strcmp(value->str, arg2))
						return SUCCEED;
					break;
				case OP_LIKE:
					if (NULL != strstr(value->str, arg2))
						return SUCCEED;
					break;
			}
	}

	return FAIL;
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
 *                                Exception is for comparison operator "band".*
 *                                With "band" this parameter is mandatory and *
 *                                can take one of 2 forms:                    *
 *                                  - value_to_compare_with/mask              *
 *                                  - mask                                    *
 *                            (3) comparison operator (optional)              *
 *                            (4) time shift (optional)                       *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_COUNT(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_COUNT";
	int				arg1, flag, op, numeric_search, nparams, count = 0, i, ret = FAIL;
	int				seconds = 0, nvalues = 0;
	char				*arg2 = NULL, *arg2_2 = NULL, *arg3 = NULL;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	numeric_search = (ITEM_VALUE_TYPE_UINT64 == item->value_type || ITEM_VALUE_TYPE_FLOAT == item->value_type);

	if (4 < (nparams = num_param(parameters)))
		goto out;

	if (SUCCEED != get_function_parameter_uint31(item->host.hostid, parameters, 1, &arg1, &flag) || 0 == arg1)
		goto out;

	if (2 <= nparams && SUCCEED != get_function_parameter_str(item->host.hostid, parameters, 2, &arg2))
		goto out;

	if (3 <= nparams)
	{
		int	fail = 2;

		if (SUCCEED != get_function_parameter_str(item->host.hostid, parameters, 3, &arg3))
			goto out;

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
		else if (0 == strcmp(arg3, "band"))
		{
			op = OP_BAND;

			if (NULL != (arg2_2 = strchr(arg2, '/')))
			{
				*arg2_2 = '\0';	/* end of the 1st part of the 2nd parameter (number to compare with) */
				arg2_2++;	/* start of the 2nd part of the 2nd parameter (mask) */
			}
		}
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
			goto out;
	}
	else
		op = (0 != numeric_search ? OP_EQ : OP_LIKE);

	if (4 <= nparams)
	{
		int	time_shift, time_shift_flag;

		if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 4, &time_shift,
				&time_shift_flag, 0, ZBX_FLAG_SEC) || ZBX_FLAG_SEC != time_shift_flag)
		{
			goto out;
		}

		now -= time_shift;
	}

	if (NULL != arg2 && '\0' == *arg2 && (0 != numeric_search || OP_LIKE == op))
		zbx_free(arg2);

	if (ZBX_FLAG_SEC == flag)
		seconds = arg1;
	else
		nvalues = arg1;

	if (FAIL == zbx_vc_get_value_range(item->itemid, item->value_type, &values, seconds, nvalues, now))
		goto out;

	for (i = 0; i < values.values_num; i++)
	{
		if (NULL == arg2 || SUCCEED == evaluate_COUNT_one(item->value_type, op, &values.values[i].value, arg2,
				arg2_2))
		{
			count++;
		}
	}

	zbx_snprintf(value, MAX_BUFFER_LEN, "%d", count);

	ret = SUCCEED;
out:
	zbx_free(arg2);

	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#undef OP_EQ
#undef OP_NE
#undef OP_GT
#undef OP_GE
#undef OP_LT
#undef OP_LE
#undef OP_LIKE
#undef OP_BAND
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
 ******************************************************************************/
static int	evaluate_SUM(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_SUM";
	int				nparams, arg1, flag, i, ret = FAIL, seconds = 0, nvalues = 0;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto out;

	if (2 < (nparams = num_param(parameters)))
		goto out;

	if (SUCCEED != get_function_parameter_uint31(item->host.hostid, parameters, 1, &arg1, &flag) || 0 == arg1)
		goto out;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 2, &time_shift,
				&time_shift_flag, 0, ZBX_FLAG_SEC) || ZBX_FLAG_SEC != time_shift_flag)
		{
			goto out;
		}

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
		seconds = arg1;
	else
		nvalues = arg1;

	if (FAIL == zbx_vc_get_value_range(item->itemid, item->value_type, &values, seconds, nvalues, now))
		goto out;

	if (0 < values.values_num)
	{
		history_value_t	result = {0};

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
				result.dbl += values.values[i].value.dbl;
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
				result.ui64 += values.values[i].value.ui64;
		}
		zbx_vc_history_value2str(value, MAX_BUFFER_LEN, &result, item->value_type);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for SUM is empty");
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_AVG(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_AVG";
	int				nparams, arg1, flag, ret = FAIL, i, seconds = 0, nvalues = 0;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto out;

	if (2 < (nparams = num_param(parameters)))
		goto out;

	if (SUCCEED != get_function_parameter_uint31(item->host.hostid, parameters, 1, &arg1, &flag) || 0 == arg1)
		goto out;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 2, &time_shift,
				&time_shift_flag, 0, ZBX_FLAG_SEC) || ZBX_FLAG_SEC != time_shift_flag)
		{
			goto out;
		}

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
		seconds = arg1;
	else
		nvalues = arg1;

	if (FAIL == zbx_vc_get_value_range(item->itemid, item->value_type, &values, seconds, nvalues, now))
		goto out;

	if (0 < values.values_num)
	{
		double	sum = 0;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
				sum += values.values[i].value.dbl;
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
				sum += values.values[i].value.ui64;
		}
		zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL, sum / values.values_num);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for AVG is empty");
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_LAST(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_LAST";
	int				arg1, flag, ret = FAIL;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 1, &arg1, &flag,
			1, ZBX_FLAG_VALUES))
	{
		goto out;
	}

	if (ZBX_FLAG_VALUES != flag)
	{
		arg1 = 1;
		flag = ZBX_FLAG_VALUES;
	}

	if (2 == num_param(parameters))
	{
		int	time_shift, time_shift_flag;

		if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 2, &time_shift,
				&time_shift_flag, 0, ZBX_FLAG_SEC) || ZBX_FLAG_SEC != time_shift_flag)
		{
			goto out;
		}

		now -= time_shift;
	}

	zbx_history_record_vector_create(&values);

	if (SUCCEED == zbx_vc_get_value_range(item->itemid, item->value_type, &values, 0, arg1, now))
	{
		if (arg1 <= values.values_num)
		{
			zbx_vc_history_value2str(value, MAX_BUFFER_LEN, &values.values[arg1 - 1].value,
					item->value_type);
			ret = SUCCEED;
		}
	}

	zbx_history_record_vector_destroy(&values, item->value_type);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_MIN(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_MIN";
	int				nparams, arg1, flag, i, ret = FAIL, seconds = 0, nvalues = 0;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto out;

	if (2 < (nparams = num_param(parameters)))
		goto out;

	if (SUCCEED != get_function_parameter_uint31(item->host.hostid, parameters, 1, &arg1, &flag) || 0 == arg1)
		goto out;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 2, &time_shift,
				&time_shift_flag, 0, ZBX_FLAG_SEC) || ZBX_FLAG_SEC != time_shift_flag)
		{
			goto out;
		}

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
		seconds = arg1;
	else
		nvalues = arg1;

	if (FAIL == zbx_vc_get_value_range(item->itemid, item->value_type, &values, seconds, nvalues, now))
		goto out;

	if (0 < values.values_num)
	{
		int	index = 0;

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			for (i = 1; i < values.values_num; i++)
			{
				if (values.values[i].value.ui64 < values.values[index].value.ui64)
					index = i;
			}
		}
		else
		{
			for (i = 1; i < values.values_num; i++)
			{
				if (values.values[i].value.dbl < values.values[index].value.dbl)
					index = i;
			}
		}
		zbx_vc_history_value2str(value, MAX_BUFFER_LEN, &values.values[index].value, item->value_type);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for MIN is empty");
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_MAX(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_MAX";
	int				nparams, arg1, flag, ret = FAIL, i, seconds = 0, nvalues = 0;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto out;

	if (2 < (nparams = num_param(parameters)))
		goto out;

	if (SUCCEED != get_function_parameter_uint31(item->host.hostid, parameters, 1, &arg1, &flag) || 0 == arg1)
		goto out;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 2, &time_shift,
				&time_shift_flag, 0, ZBX_FLAG_SEC) || ZBX_FLAG_SEC != time_shift_flag)
		{
			goto out;
		}

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
		seconds = arg1;
	else
		nvalues = arg1;

	if (FAIL == zbx_vc_get_value_range(item->itemid, item->value_type, &values, seconds, nvalues, now))
		goto out;

	if (0 < values.values_num)
	{
		int	index = 0;

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			for (i = 1; i < values.values_num; i++)
			{
				if (values.values[i].value.ui64 > values.values[index].value.ui64)
					index = i;
			}
		}
		else
		{
			for (i = 1; i < values.values_num; i++)
			{
				if (values.values[i].value.dbl > values.values[index].value.dbl)
					index = i;
			}
		}
		zbx_vc_history_value2str(value, MAX_BUFFER_LEN, &values.values[index].value, item->value_type);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for MAX is empty");
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_DELTA(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_DELTA";
	int				nparams, arg1, flag, ret = FAIL, i, seconds = 0, nvalues = 0;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto out;

	if (2 < (nparams = num_param(parameters)))
		goto out;

	if (SUCCEED != get_function_parameter_uint31(item->host.hostid, parameters, 1, &arg1, &flag) || 0 == arg1)
		goto out;

	if (2 == nparams)
	{
		int	time_shift, time_shift_flag;

		if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 2, &time_shift,
				&time_shift_flag, 0, ZBX_FLAG_SEC) || ZBX_FLAG_SEC != time_shift_flag)
		{
			goto out;
		}

		now -= time_shift;
	}

	if (ZBX_FLAG_SEC == flag)
		seconds = arg1;
	else
		nvalues = arg1;

	if (FAIL == zbx_vc_get_value_range(item->itemid, item->value_type, &values, seconds, nvalues, now))
		goto out;

	if (0 < values.values_num)
	{
		history_value_t		result;
		int 			index_min = 0, index_max = 0;

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			for (i = 1; i < values.values_num; i++)
			{
				if (values.values[i].value.ui64 > values.values[index_max].value.ui64)
					index_max = i;

				if (values.values[i].value.ui64 < values.values[index_min].value.ui64)
					index_min = i;
			}

			result.ui64 = values.values[index_max].value.ui64 - values.values[index_min].value.ui64;
		}
		else
		{
			for (i = 1; i < values.values_num; i++)
			{
				if (values.values[i].value.dbl > values.values[index_max].value.dbl)
					index_max = i;

				if (values.values[i].value.dbl < values.values[index_min].value.dbl)
					index_min = i;
			}

			result.dbl = values.values[index_max].value.dbl - values.values[index_min].value.dbl;
		}

		zbx_vc_history_value2str(value, MAX_BUFFER_LEN, &result, item->value_type);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for DELTA is empty");
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_NODATA(char *value, DC_ITEM *item, const char *function, const char *parameters)
{
	const char			*__function_name = "evaluate_NODATA";
	int				arg1, flag, now, ret = FAIL;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (1 < num_param(parameters))
		goto out;

	if (SUCCEED != get_function_parameter_uint31(item->host.hostid, parameters, 1, &arg1, &flag))
		goto out;

	if (ZBX_FLAG_SEC != flag)
		goto out;

	now = (int)time(NULL);

	if (SUCCEED == zbx_vc_get_value_range(item->itemid, item->value_type, &values, 0, 1, now) &&
			1 == values.values_num && values.values[0].timestamp.sec + arg1 > now)
	{
		zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
	}
	else
	{
		int	seconds;

		if (SUCCEED != DCget_data_expected_from(item->itemid, &seconds) || seconds + arg1 > now)
			goto out;

		zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_ABSCHANGE(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_ABSCHANGE";
	int				ret = FAIL;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != zbx_vc_get_value_range(item->itemid, item->value_type, &values, 0, 2, now) ||
			2 > values.values_num)
		goto out;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL,
					fabs(values.values[0].value.dbl - values.values[1].value.dbl));
			break;
		case ITEM_VALUE_TYPE_UINT64:
			/* to avoid overflow */
			if (values.values[0].value.ui64 >= values.values[1].value.ui64)
			{
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64,
						values.values[0].value.ui64 - values.values[1].value.ui64);
			}
			else
			{
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64,
						values.values[1].value.ui64 - values.values[0].value.ui64);
			}
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (0 == strcmp(values.values[0].value.log->value, values.values[1].value.log->value))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;

		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (0 == strcmp(values.values[0].value.str, values.values[1].value.str))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
		default:
			goto out;
	}
	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_CHANGE(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_CHANGE";
	int				ret = FAIL;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != zbx_vc_get_value_range(item->itemid, item->value_type, &values, 0, 2, now) ||
			2 > values.values_num)
		goto out;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_DBL,
					values.values[0].value.dbl - values.values[1].value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			/* to avoid overflow */
			if (values.values[0].value.ui64 >= values.values[1].value.ui64)
				zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64,
						values.values[0].value.ui64 - values.values[1].value.ui64);
			else
				zbx_snprintf(value, MAX_BUFFER_LEN, "-" ZBX_FS_UI64,
						values.values[1].value.ui64 - values.values[0].value.ui64);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (0 == strcmp(values.values[0].value.log->value, values.values[1].value.log->value))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;

		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (0 == strcmp(values.values[0].value.str, values.values[1].value.str))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
		default:
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_DIFF(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_DIFF";
	int				ret = FAIL;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != zbx_vc_get_value_range(item->itemid, item->value_type, &values, 0, 2, now) ||
			2 > values.values_num)
		goto out;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (SUCCEED == cmp_double(values.values[0].value.dbl, values.values[1].value.dbl))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (values.values[0].value.ui64 == values.values[1].value.ui64)
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (0 == strcmp(values.values[0].value.log->value, values.values[1].value.log->value))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;

		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (0 == strcmp(values.values[0].value.str, values.values[1].value.str))
				zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
			else
				zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
			break;
		default:
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/

#define ZBX_FUNC_STR		1
#define ZBX_FUNC_REGEXP		2
#define ZBX_FUNC_IREGEXP	3

static int	evaluate_STR_one(int func, zbx_vector_ptr_t *regexps, const char *value, const char *arg1)
{
	switch (func)
	{
		case ZBX_FUNC_STR:
			if (NULL != strstr(value, arg1))
				return SUCCEED;
			break;
		case ZBX_FUNC_REGEXP:
			return regexp_match_ex(regexps, value, arg1, ZBX_CASE_SENSITIVE);
		case ZBX_FUNC_IREGEXP:
			return regexp_match_ex(regexps, value, arg1, ZBX_IGNORE_CASE);
	}

	return FAIL;
}

static int	evaluate_STR(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char			*__function_name = "evaluate_STR";
	char				*arg1 = NULL;
	int				arg2, flag, func, found = 0, i, ret = FAIL, seconds = 0, nvalues = 0, nparams;
	zbx_vector_ptr_t		regexps;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&regexps);
	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_STR != item->value_type && ITEM_VALUE_TYPE_TEXT != item->value_type &&
			ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		goto out;
	}

	if (0 == strcmp(function, "str"))
		func = ZBX_FUNC_STR;
	else if (0 == strcmp(function, "regexp"))
		func = ZBX_FUNC_REGEXP;
	else if (0 == strcmp(function, "iregexp"))
		func = ZBX_FUNC_IREGEXP;
	else
		goto out;

	if (2 < (nparams = num_param(parameters)))
		goto out;

	if (SUCCEED != get_function_parameter_str(item->host.hostid, parameters, 1, &arg1))
		goto out;

	if (2 == nparams)
	{
		if (SUCCEED != get_function_parameter_uint31_default(item->host.hostid, parameters, 2, &arg2, &flag,
				1, ZBX_FLAG_VALUES) || 0 == arg2)
		{
			goto out;
		}
	}
	else
	{
		arg2 = 1;
		flag = ZBX_FLAG_VALUES;
	}

	if ((ZBX_FUNC_REGEXP == func || ZBX_FUNC_IREGEXP == func) && '@' == *arg1)
		DCget_expressions_by_name(&regexps, arg1 + 1);

	if (ZBX_FLAG_SEC == flag)
		seconds = arg2;
	else
		nvalues = arg2;

	if (FAIL == zbx_vc_get_value_range(item->itemid, item->value_type, &values, seconds, nvalues, now))
		goto out;

	if (0 == values.values_num)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for STR is empty");
		goto out;
	}

	/* at this point the value type can be only str, tex or log */
	if (ITEM_VALUE_TYPE_LOG == item->value_type)
	{
		for (i = 0; i < values.values_num; i++)
		{
			if (SUCCEED == evaluate_STR_one(func, &regexps, values.values[i].value.log->value, arg1))
			{
				found = 1;
				break;
			}
		}
	}
	else
	{
		for (i = 0; i < values.values_num; i++)
		{
			if (SUCCEED == evaluate_STR_one(func, &regexps, values.values[i].value.str, arg1))
			{
				found = 1;
				break;
			}
		}
	}

	zbx_snprintf(value, MAX_BUFFER_LEN, "%d", found);
	ret = SUCCEED;
out:
	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zbx_history_record_vector_destroy(&values, item->value_type);

	zbx_free(arg1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_STRLEN(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_STRLEN";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_STR != item->value_type && ITEM_VALUE_TYPE_TEXT != item->value_type &&
			ITEM_VALUE_TYPE_LOG != item->value_type)
		goto clean;

	if (SUCCEED == evaluate_LAST(value, item, "last", parameters, now))
	{
		zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_SIZE_T, (zbx_fs_size_t)zbx_strlen_utf8(value));
		ret = SUCCEED;
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
static int	evaluate_FUZZYTIME(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char		*__function_name = "evaluate_FUZZYTIME";

	int			arg1, flag, ret = FAIL;
	zbx_history_record_t	vc_value;
	zbx_uint64_t		fuzlow, fuzhig;
	zbx_timespec_t		ts = {now, 999999999};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto out;

	if (1 < num_param(parameters))
		goto out;

	if (SUCCEED != get_function_parameter_uint31(item->host.hostid, parameters, 1, &arg1, &flag))
		goto out;

	if (ZBX_FLAG_SEC != flag || now <= arg1)
		goto out;

	if (SUCCEED != zbx_vc_get_value(item->itemid, item->value_type, &ts, &vc_value))
		goto out;

	fuzlow = (int)(now - arg1);
	fuzhig = (int)(now + arg1);

	if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
	{
		if (vc_value.value.ui64 >= fuzlow && vc_value.value.ui64 <= fuzhig)
			zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
		else
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
	}
	else
	{
		if (vc_value.value.dbl >= fuzlow && vc_value.value.dbl <= fuzhig)
			zbx_strlcpy(value, "1", MAX_BUFFER_LEN);
		else
			zbx_strlcpy(value, "0", MAX_BUFFER_LEN);
	}

	zbx_history_record_clear(&vc_value, item->value_type);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_BAND                                                    *
 *                                                                            *
 * Purpose: evaluate logical bitwise function 'and' for the item              *
 *                                                                            *
 * Parameters: value - buffer of size MAX_BUFFER_LEN                          *
 *             item - item (performance metric)                               *
 *             parameters - up to 3 comma-separated fields:                   *
 *                            (1) same as the 1st parameter for function      *
 *                                evaluate_LAST() (see documentation of       *
 *                                trigger function last()),                   *
 *                            (2) mask to bitwise AND with (mandatory),       *
 *                            (3) same as the 2nd parameter for function      *
 *                                evaluate_LAST() (see documentation of       *
 *                                trigger function last()).                   *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_BAND(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now)
{
	const char	*__function_name = "evaluate_BAND";
	char		*last_parameters = NULL;
	int		mask_flag, nparams, ret = FAIL;
	zbx_uint64_t	last_uint64, mask;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ITEM_VALUE_TYPE_UINT64 != item->value_type)
		goto clean;

	if (3 < (nparams = num_param(parameters)))
		goto clean;

	if (SUCCEED != get_function_parameter_uint64(item->host.hostid, parameters, 2, &mask, &mask_flag) ||
			ZBX_FLAG_SEC != mask_flag)
	{
		goto clean;
	}

	/* prepare the 1st and the 3rd parameter for passing to evaluate_LAST() */
	last_parameters = zbx_strdup(NULL, parameters);
	remove_param(last_parameters, 2);

	if (SUCCEED == evaluate_LAST(value, item, "last", last_parameters, now))
	{
		ZBX_STR2UINT64(last_uint64, value);
		zbx_snprintf(value, MAX_BUFFER_LEN, ZBX_FS_UI64, last_uint64 & (zbx_uint64_t)mask);
		ret = SUCCEED;
	}

	zbx_free(last_parameters);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
 ******************************************************************************/
int	evaluate_function(char *value, DC_ITEM *item, const char *function, const char *parameter, time_t now)
{
	const char	*__function_name = "evaluate_function";

	int		ret;
	struct tm	*tm = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:'%s:%s.%s(%s)'", __function_name,
			item->host.host, item->key_orig, function, parameter);

	*value = '\0';

	if (0 == strcmp(function, "last"))
	{
		ret = evaluate_LAST(value, item, function, parameter, now);
	}
	else if (0 == strcmp(function, "prev"))
	{
		ret = evaluate_LAST(value, item, "last", "#2", now);
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
	else if (0 == strcmp(function, "band"))
	{
		ret = evaluate_BAND(value, item, function, parameter, now);
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
 * Comments: used for evaluation of notification macros                       *
 *           output buffer size should be MAX_BUFFER_LEN                      *
 *                                                                            *
 ******************************************************************************/
int	evaluate_macro_function(char *value, const char *host, const char *key, const char *function, const char *parameter)
{
	const char	*__function_name = "evaluate_macro_function";

	zbx_host_key_t	host_key = {host, key};
	DC_ITEM		item;
	int		ret = FAIL, errcode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:'%s:%s.%s(%s)'", __function_name, host, key, function, parameter);

	DCconfig_get_items_by_keys(&item, &host_key, &errcode, 1);

	if (SUCCEED != errcode)
	{
		zabbix_log(LOG_LEVEL_DEBUG,
				"cannot evaluate function \"%s:%s.%s(%s)\": item does not exist",
				host, key, function, parameter);
		goto out;
	}

	if (SUCCEED == (ret = evaluate_function(value, &item, function, parameter, time(NULL))))
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
out:
	DCconfig_clean_items(&item, &errcode, 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:'%s'", __function_name, zbx_result_string(ret), value);

	return ret;
}
