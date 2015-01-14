/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "sysinfo.h"
#include "threads.h"
#include "perfstat.h"
#include "log.h"

int	USER_PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char		*__function_name = "USER_PERF_COUNTER";
	int			ret = SYSINFO_RET_FAIL;
	char			*counter, *error = NULL;
	double			value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	if (NULL == (counter = get_rparam(request, 0)) || '\0' == *counter)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto out;
	}

	if (SUCCEED != get_perf_counter_value_by_name(counter, &value, &error))
	{
		SET_MSG_RESULT(result, error != NULL ? error :
				zbx_strdup(NULL, "Cannot obtain performance information from collector."));
		goto out;
	}

	SET_DBL_RESULT(result, value);
	ret = SYSINFO_RET_OK;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char		*__function_name = "PERF_COUNTER";
	char			counterpath[PDH_MAX_COUNTER_PATH], *tmp, *error = NULL;
	int			interval, ret = SYSINFO_RET_FAIL;
	double			value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto out;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto out;
	}

	strscpy(counterpath, tmp);

	if (NULL == (tmp = get_rparam(request, 1)) || '\0' == *tmp)
	{
		interval = 1;
	}
	else if (FAIL == is_uint31(tmp, &interval))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if (1 > interval || MAX_COLLECTOR_PERIOD < interval)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Interval out of range."));
		goto out;
	}

	if (FAIL == check_counter_path(counterpath))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid performance counter path."));
		goto out;
	}

	if (SUCCEED != get_perf_counter_value_by_path(counterpath, interval, &value, &error))
	{
		SET_MSG_RESULT(result, error != NULL ? error :
				zbx_strdup(NULL, "Cannot obtain performance information from collector."));
		goto out;
	}

	ret = SYSINFO_RET_OK;
	SET_DBL_RESULT(result, value);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
