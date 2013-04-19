/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "../common/common.h"
#include "sysinfo.h"
#include "stats.h"
#include "perfstat.h"
#include "zbxjson.h"

static int	get_cpu_num()
{
	SYSTEM_INFO	sysInfo;

	GetSystemInfo(&sysInfo);

	return (int)sysInfo.dwNumberOfProcessors;
}

int	SYSTEM_CPU_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	/* only "online" (default) for parameter "type" is supported */
	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "online"))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, get_cpu_num());

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	cpu_num;
	double	value;

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_FAIL;
	}

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = 0;
	else if (SUCCEED != is_uint_range(tmp, &cpu_num, 0, collector->cpus.count - 1))
		return SYSINFO_RET_FAIL;
	else
		cpu_num++;

	tmp = get_rparam(request, 1);

	/* only "system" (default) for parameter "type" is supported */
	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "system"))
		return SYSINFO_RET_FAIL;

	if (PERF_COUNTER_ACTIVE != collector->cpus.cpu_counter[cpu_num]->status)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 2);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
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

int	SYSTEM_CPU_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*tmp;
	long 		ncpu, i;
	struct zbx_json	j;
	int 		ret = SYSINFO_RET_FAIL;


	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	/* only "online" (default) for parameter "type" is supported */
	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "online"))
		return SYSINFO_RET_FAIL;

	if (-1 == (ncpu = get_cpu_num()))
		return SYSINFO_RET_FAIL;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < ncpu; i++)
	{
		zbx_json_addobject(&j, NULL);

		zbx_json_adduint64(&j, ZBX_MACRO_CPUID, i);
		zbx_json_addstring(&j, ZBX_MACRO_CPU_STATUS, "online", ZBX_JSON_TYPE_STRING);

		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	ret = SYSINFO_RET_OK;

	SET_STR_RESULT(result, zbx_strdup(result->str, j.buffer));

	zbx_json_free(&j);

	return ret;
}

int	SYSTEM_CPU_LOAD(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	double	value;
	int	per_cpu = 1, cpu_num;

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_FAIL;
	}

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		per_cpu = 0;
	else if (0 != strcmp(tmp, "percpu"))
		return SYSINFO_RET_FAIL;

	if (PERF_COUNTER_ACTIVE != collector->cpus.queue_counter->status)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
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
