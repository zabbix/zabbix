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
#include "sysinfo.h"
#include "threads.h"
#include "perfstat.h"
#include "log.h"

int	USER_PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char		*__function_name = "USER_PERF_COUNTER";
	PERF_COUNTER_DATA	*perfs = NULL;
	int			ret = SYSINFO_RET_FAIL;
	double			value;
	char			*counter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		return SYSINFO_RET_FAIL;
	}

	counter = get_rparam(request, 0);

	if (NULL == counter || '\0' == *counter)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED != perf_collector_started())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	for (perfs = ppsd.pPerfCounterList; NULL != perfs; perfs = perfs->next)
	{
		if (NULL != perfs->name && 0 == strcmp(perfs->name, counter))
		{
			if (PERF_COUNTER_ACTIVE == perfs->status)
			{
				SET_DBL_RESULT(result, compute_average_value(perfs, USE_DEFAULT_INTERVAL));
				ret = SYSINFO_RET_OK;
			}

			break;
		}
	}

	if (SYSINFO_RET_OK != ret && NULL != perfs)
	{
		if (ERROR_SUCCESS == calculate_counter_value(__function_name, perfs->counterpath, &value))
		{
			perfs->status = PERF_COUNTER_INITIALIZED;
			SET_DBL_RESULT(result, value);
			ret = SYSINFO_RET_OK;
		}
	}

	if (SYSINFO_RET_FAIL == ret)
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain performance information from collector."));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

int	PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char		*__function_name = "PERF_COUNTER";
	char			counterpath[PDH_MAX_COUNTER_PATH], *tmp;
	int			interval, ret = SYSINFO_RET_FAIL;
	double			value;
	PERF_COUNTER_DATA	*perfs = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	strscpy(counterpath, tmp);

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp)
		interval = 1;
	else if (FAIL == is_uint31(tmp, &interval))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (FAIL == check_counter_path(counterpath))
		goto clean;

	if (1 < interval)
	{
		if (SUCCEED != perf_collector_started())
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
			goto clean;
		}

		for (perfs = ppsd.pPerfCounterList; NULL != perfs; perfs = perfs->next)
		{
			if (0 == strcmp(perfs->counterpath, counterpath) && perfs->interval == interval)
			{
				if (PERF_COUNTER_ACTIVE != perfs->status)
					break;

				SET_DBL_RESULT(result, compute_average_value(perfs, USE_DEFAULT_INTERVAL));
				ret = SYSINFO_RET_OK;
				goto clean;
			}
		}

		if (NULL == perfs && NULL == (perfs = add_perf_counter(NULL, counterpath, interval)))
			goto clean;
	}

	if (ERROR_SUCCESS == calculate_counter_value(__function_name, counterpath, &value))
	{
		if (NULL != perfs)
			perfs->status = PERF_COUNTER_INITIALIZED;

		SET_DBL_RESULT(result, value);
		ret = SYSINFO_RET_OK;
	}
clean:
	if (SYSINFO_RET_FAIL == ret && !ISSET_MSG(result))
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain performance information from collector."));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}
