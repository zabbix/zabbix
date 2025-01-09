/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "funcparam.h"
#include "datafunc.h"

#include "zbxtrends.h"
#include "zbxnum.h"
#include "zbxexpr.h"
#include "zbxtime.h"

int	get_function_parameter_uint64(const char *parameters, int Nparam, zbx_uint64_t *value)
{
	char	*parameter;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (parameter = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	if (SUCCEED == (ret = zbx_is_uint64(parameter, value)))
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:" ZBX_FS_UI64, __func__, *value);

	zbx_free(parameter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	get_function_parameter_float(const char *parameters, int Nparam, unsigned char flags, double *value)
{
	char	*parameter;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (parameter = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	if (SUCCEED == (ret = zbx_is_double_suffix(parameter, flags)))
	{
		*value = zbx_str2double(parameter);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:" ZBX_FS_DBL, __func__, *value);
	}

	zbx_free(parameter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	get_function_parameter_str(const char *parameters, int Nparam, char **value)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (*value = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() value:'%s'", __func__, *value);
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the value of sec|num + timeshift trigger function parameter.  *
 *                                                                            *
 * Parameters: from       - [IN] function calculation time                    *
 *             parameters - [IN] trigger function parameters                  *
 *             Nparam     - [IN] specifies which parameter to extract         *
 *             value      - [OUT] parameter value (preserved as is if the     *
 *                                parameter is optional and empty)            *
 *             type       - [OUT] parameter value type (number of seconds     *
 *                                or number of values)                        *
 *             timeshift  - [OUT] timeshift value (0 if absent)               *
 *                                                                            *
 * Return value: SUCCEED - parameter is valid                                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	get_function_parameter_hist_range(int from, const char *parameters, int Nparam, int *value,
		zbx_value_type_t *type, int *timeshift)
{
	char	*parameter = NULL, *shift;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (parameter = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	if (NULL != (shift = strchr(parameter, ':')))
		*shift++ = '\0';

	if ('\0' == *parameter)
	{
		*value = 0;
		*type = ZBX_VALUE_NONE;
	}
	else if ('#' != *parameter)
	{
		if (SUCCEED != zbx_is_time_suffix(parameter, value, ZBX_LENGTH_UNLIMITED) || 0 > *value)
			goto out;

		*type = ZBX_VALUE_SECONDS;
	}
	else
	{
		if (SUCCEED != zbx_is_uint31(parameter + 1, value) || 0 >= *value)
			goto out;
		*type = ZBX_VALUE_NVALUES;
	}

	if (NULL != shift)
	{
		struct tm	tm;
		char		*error = NULL;
		int		end;

		if (SUCCEED != zbx_trends_parse_timeshift(from, shift, &tm, &error))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() timeshift error:%s", __func__, error);
			zbx_free(error);
			goto out;
		}

		if (-1 == (end = (int)mktime(&tm)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() invalid timeshift value:%s", __func__,
					zbx_strerror(errno));
			goto out;
		}

		*timeshift = from - end;
	}
	else
		*timeshift = 0;

	ret = SUCCEED;
	zabbix_log(LOG_LEVEL_DEBUG, "%s() type:%s value:%d timeshift:%d", __func__, zbx_type_string(*type),
			*value, *timeshift);
out:
	zbx_free(parameter);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the value of sec|#num trigger function parameter.             *
 *                                                                            *
 * Parameters: parameters - [IN] trigger function parameters                  *
 *             Nparam     - [IN] specifies which parameter to extract         *
 *             value      - [OUT] parameter value (preserved as is if the     *
 *                                parameter is optional and empty)            *
 *             type       - [OUT] parameter value type (number of seconds     *
 *                                or number of values, preserved as is if the *
 *                                parameter is optional and empty)            *
 *                                                                            *
 * Return value: SUCCEED - parameter is valid                                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	get_function_parameter_period(const char *parameters, int Nparam, int *value, zbx_value_type_t *type)
{
	char	*parameter;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() parameters:'%s' Nparam:%d", __func__, parameters, Nparam);

	if (NULL == (parameter = zbx_function_get_param_dyn(parameters, Nparam)))
		goto out;

	if ('\0' != *parameter)
	{
		if ('#' == *parameter)
		{
			*type = ZBX_VALUE_NVALUES;
			if (SUCCEED == zbx_is_uint31(parameter + 1, value) && 0 < *value)
				ret = SUCCEED;
		}
		else if ('-' == *parameter)
		{
			if (SUCCEED == zbx_is_time_suffix(parameter + 1, value, ZBX_LENGTH_UNLIMITED))
			{
				*value = -(*value);
				*type = ZBX_VALUE_SECONDS;
				ret = SUCCEED;
			}
		}
		else if (SUCCEED == zbx_is_time_suffix(parameter, value, ZBX_LENGTH_UNLIMITED))
		{
			*type = ZBX_VALUE_SECONDS;
			ret = SUCCEED;
		}
	}

	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() type:%s value:%d", __func__, zbx_type_string(*type), *value);

	zbx_free(parameter);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
