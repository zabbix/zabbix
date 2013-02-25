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
#include "sysinfo.h"
#include "stats.h"
#include "perfstat.h"

static int	get_cpu_num()
{
	SYSTEM_INFO	sysInfo;

	GetSystemInfo(&sysInfo);

	return (int)sysInfo.dwNumberOfProcessors;
}

int	SYSTEM_CPU_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	/* only "online" (default) for parameter "type" is supported */
	if (0 == get_param(param, 1, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strcmp(tmp, "online"))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, get_cpu_num());

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];
	int	cpu_num;
	double	value;

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_FAIL;
	}

	if (3 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = 0;
	else if (SUCCEED != is_uint(tmp) || 1 > (cpu_num = atoi(tmp) + 1) || cpu_num > collector->cpus.count)
		return SYSINFO_RET_FAIL;

	/* only "system" (default) for parameter "type" is supported */
	if (0 == get_param(param, 2, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strcmp(tmp, "system"))
		return SYSINFO_RET_FAIL;

	if (PERF_COUNTER_ACTIVE != collector->cpus.cpu_counter[cpu_num]->status)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 3, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		value = compute_average_value(collector->cpus.cpu_counter[cpu_num], 1 * SEC_PER_MIN);
	else if (0 == strcmp(tmp, "avg5"))
		value = compute_average_value(collector->cpus.cpu_counter[cpu_num], 5 * SEC_PER_MIN);
	else if (0 == strcmp(tmp, "avg15"))
		value = compute_average_value(collector->cpus.cpu_counter[cpu_num], USE_DEFAULT_INTERVAL);
	else
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];
	double	value;
	int	per_cpu = 1, cpu_num;

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_FAIL;
	}

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		per_cpu = 0;
	else if (0 != strcmp(tmp, "percpu"))
		return SYSINFO_RET_FAIL;

	if (PERF_COUNTER_ACTIVE != collector->cpus.queue_counter->status)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		value = compute_average_value(collector->cpus.queue_counter, 1 * SEC_PER_MIN);
	else if (0 == strcmp(tmp, "avg5"))
		value = compute_average_value(collector->cpus.queue_counter, 5 * SEC_PER_MIN);
	else if (0 == strcmp(tmp, "avg15"))
		value = compute_average_value(collector->cpus.queue_counter, USE_DEFAULT_INTERVAL);
	else
		return SYSINFO_RET_FAIL;

	if (1 == per_cpu)
	{
		if (0 >= (cpu_num = get_cpu_num()))
			return SYSINFO_RET_FAIL;
		value /= cpu_num;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}
