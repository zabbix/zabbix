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
#include "sysinfo.h"
#include "stats.h"

int	SYSTEM_CPU_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	SYSTEM_INFO	sysInfo;
	char		mode[128];

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)))
		*mode = '\0';

	/* only 'online' parameter supported */
	if ('\0' != *mode && 0 != strcmp(mode, "online"))
		return SYSINFO_RET_FAIL;

	GetSystemInfo(&sysInfo);

	SET_UI64_RESULT(result, sysInfo.dwNumberOfProcessors);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const char	*__function_name = "SYSTEM_CPU_UTIL";
	char		tmp[32];
	int		cpu_num;
	double		value;

	if (3 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = 0;
	else
	{
		cpu_num = atoi(tmp) + 1;
		if (1 > cpu_num || cpu_num > collector->cpus.count)
			return SYSINFO_RET_FAIL;
	}

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	/* only 'system' parameter supported */
	if ('\0' != *tmp && 0 != strcmp(tmp, "system"))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if (PERF_COUNTER_ACTIVE != collector->cpus.cpu_counter[cpu_num]->status)
		return SYSINFO_RET_FAIL;

	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		value = compute_average_value(__function_name, collector->cpus.cpu_counter[cpu_num], 1 * SEC_PER_MIN);
	else if (0 == strcmp(tmp, "avg5"))
		value = compute_average_value(__function_name, collector->cpus.cpu_counter[cpu_num], 5 * SEC_PER_MIN);
	else if (0 == strcmp(tmp, "avg15"))
		value = compute_average_value(__function_name, collector->cpus.cpu_counter[cpu_num], USE_DEFAULT_INTERVAL);
	else
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const char	*__function_name = "SYSTEM_CPU_LOAD";
	char		cpuname[10], mode[10];
	double		value;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, cpuname, sizeof(cpuname)))
		*cpuname = '\0';

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* only 'all' parameter supported */
	if ('\0' != *cpuname && 0 != strcmp(cpuname, "all"))
		return SYSINFO_RET_FAIL;

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if (PERF_COUNTER_ACTIVE != collector->cpus.queue_counter->status)
		return SYSINFO_RET_FAIL;

	if ('\0' == *mode || 0 == strcmp(mode, "avg1"))
		value = compute_average_value(__function_name, collector->cpus.queue_counter, 1 * SEC_PER_MIN);
	else if (0 == strcmp(mode, "avg5"))
		value = compute_average_value(__function_name, collector->cpus.queue_counter, 5 * SEC_PER_MIN);
	else if (0 == strcmp(mode, "avg15"))
		value = compute_average_value(__function_name, collector->cpus.queue_counter, USE_DEFAULT_INTERVAL);
	else
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}
