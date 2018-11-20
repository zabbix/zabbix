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
#include "log.h"
#include "sysinfo.h"
#include "stats.h"
#include "perfstat.h"

static int	get_cpu_num(void)
{
	/* Define a function pointer type for the GetActiveProcessorCount API */
	typedef DWORD (WINAPI *GETACTIVEPC) (WORD);

	GETACTIVEPC	get_act;
	SYSTEM_INFO	sysInfo;

	/* The rationale for checking dynamically if the GetActiveProcessorCount is implemented */
	/* in kernel32.lib, is because the function is implemented only on 64 bit versions of Windows */
	/* from Windows 7 onward. Windows Vista 64 bit doesn't have it and also Windows XP does */
	/* not. We can't resolve this using conditional compilation unless we release multiple agents */
	/* targeting different sets of Windows APIs. */
	get_act = (GETACTIVEPC)GetProcAddress(GetModuleHandle(TEXT("kernel32.dll")), "GetActiveProcessorCount");

	if (NULL != get_act)
	{
		return (int)get_act(ALL_PROCESSOR_GROUPS);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find address of GetActiveProcessorCount function");

		GetNativeSystemInfo(&sysInfo);

		return (int)sysInfo.dwNumberOfProcessors;
	}
}

int	SYSTEM_CPU_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	/* only "online" (default) for parameter "type" is supported */
	if (NULL != (tmp = get_rparam(request, 0)) && '\0' != *tmp && 0 != strcmp(tmp, "online"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, get_cpu_num());

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp, *error = NULL;
	int	cpu_num, interval;
	double	value;

	if (0 == CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (tmp = get_rparam(request, 0)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = ZBX_CPUNUM_ALL;
	else if (SUCCEED != is_uint_range(tmp, &cpu_num, 0, collector->cpus.count - 1))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* only "system" (default) for parameter "type" is supported */
	if (NULL != (tmp = get_rparam(request, 1)) && '\0' != *tmp && 0 != strcmp(tmp, "system"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (tmp = get_rparam(request, 2)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
	{
		interval = 1 * SEC_PER_MIN;
	}
	else if (0 == strcmp(tmp, "avg5"))
	{
		interval = 5 * SEC_PER_MIN;
	}
	else if (0 == strcmp(tmp, "avg15"))
	{
		interval = 15 * SEC_PER_MIN;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED == get_cpu_perf_counter_value(cpu_num, interval, &value, &error))
	{
		SET_DBL_RESULT(result, value);
		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, NULL != error ? error :
			zbx_strdup(NULL, "Cannot obtain performance information from collector."));

	return SYSINFO_RET_FAIL;
}

int	SYSTEM_CPU_LOAD(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp, *error = NULL;
	double	value;
	int	cpu_num, ret = FAIL;

	if (0 == CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (tmp = get_rparam(request, 0)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
	{
		cpu_num = 1;
	}
	else if (0 == strcmp(tmp, "percpu"))
	{
		if (0 >= (cpu_num = get_cpu_num()))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain number of CPUs."));
			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (tmp = get_rparam(request, 1)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
	{
		ret = get_perf_counter_value(collector->cpus.queue_counter, 1 * SEC_PER_MIN, &value, &error);
	}
	else if (0 == strcmp(tmp, "avg5"))
	{
		ret = get_perf_counter_value(collector->cpus.queue_counter, 5 * SEC_PER_MIN, &value, &error);
	}
	else if (0 == strcmp(tmp, "avg15"))
	{
		ret = get_perf_counter_value(collector->cpus.queue_counter, 15 * SEC_PER_MIN, &value, &error);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED == ret)
	{
		SET_DBL_RESULT(result, value / cpu_num);
		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, NULL != error ? error :
			zbx_strdup(NULL, "Cannot obtain performance information from collector."));

	return SYSINFO_RET_FAIL;
}
