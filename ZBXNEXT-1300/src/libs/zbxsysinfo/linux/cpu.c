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
#include "../common/common.h"
#include "sysinfo.h"
#include "hardware.h"
#include "stats.h"
#include "zbxjson.h"

int	SYSTEM_CPU_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*type;
	int	name;
	long	ncpu;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	type = get_rparam(request, 0);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "online"))
		name = _SC_NPROCESSORS_ONLN;
	else if (0 == strcmp(type, "max"))
		name = _SC_NPROCESSORS_CONF;
	else
		return SYSINFO_RET_FAIL;

	if (-1 == (ncpu = sysconf(name)))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	cpu_num, state, mode;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = 0;
	else if (SUCCEED != is_uint31_1(tmp, &cpu_num))
		return SYSINFO_RET_FAIL;
	else
		cpu_num++;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "user"))
		state = ZBX_CPU_STATE_USER;
	else if (0 == strcmp(tmp, "nice"))
		state = ZBX_CPU_STATE_NICE;
	else if (0 == strcmp(tmp, "system"))
		state = ZBX_CPU_STATE_SYSTEM;
	else if (0 == strcmp(tmp, "idle"))
		state = ZBX_CPU_STATE_IDLE;
	else if (0 == strcmp(tmp, "iowait"))
		state = ZBX_CPU_STATE_IOWAIT;
	else if (0 == strcmp(tmp, "interrupt"))
		state = ZBX_CPU_STATE_INTERRUPT;
	else if (0 == strcmp(tmp, "softirq"))
		state = ZBX_CPU_STATE_SOFTIRQ;
	else if (0 == strcmp(tmp, "steal"))
		state = ZBX_CPU_STATE_STEAL;
	else
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 2);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	return get_cpustat(result, cpu_num, state, mode);
}

int SYSTEM_CPU_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char *type, line[MAX_STRING_LEN], tmp[MAX_STRING_LEN];
	int stype, cpuids[2];
	long ncpu, i;
	struct zbx_json j;
	FILE	*f;
	int ret = SYSINFO_RET_FAIL;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	type = get_rparam(request, 0);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "online"))
		stype = _SC_NPROCESSORS_ONLN;
	else if (0 == strcmp(type, "all"))
		stype = _SC_NPROCESSORS_CONF;
	else
		return SYSINFO_RET_FAIL;

	if (-1 == (ncpu = sysconf(_SC_NPROCESSORS_CONF)))
		return SYSINFO_RET_FAIL;

	if (NULL == (f = fopen(HW_STAT_FILE, "r")))
		return SYSINFO_RET_FAIL;

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (1 == sscanf(line, "cpu%[0-9]", tmp))
			cpuids[strtol(tmp, NULL, 10)] = 1;
	}

	for (i = 0; i < ncpu; i++)
	{
		if (0 < cpuids[i] || stype == _SC_NPROCESSORS_CONF)
		{
			zbx_json_addobject(&j, NULL);

			zbx_json_adduint64(&j, ZBX_MACRO_CPUID, i);
			zbx_json_addstring(&j, ZBX_MACRO_CPU_STATUS, (0 < cpuids[i] ? "online" : "offline"), ZBX_JSON_TYPE_STRING);

			zbx_json_close(&j);
		}
	}

	zbx_json_close(&j);

	ret = SYSINFO_RET_OK;

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	return ret;
}

int	SYSTEM_CPU_LOAD(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	mode, per_cpu = 1, cpu_num;
	double	load[ZBX_AVG_COUNT], value;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		per_cpu = 0;
	else if (0 != strcmp(tmp, "percpu"))
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if (mode >= getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	value = load[mode];

	if (1 == per_cpu)
	{
		if (0 >= (cpu_num = sysconf(_SC_NPROCESSORS_ONLN)))
			return SYSINFO_RET_FAIL;
		value /= cpu_num;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int     SYSTEM_CPU_SWITCHES(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN];
	zbx_uint64_t	value = 0;
	FILE		*f;

	if (NULL == (f = fopen("/proc/stat", "r")))
		return SYSINFO_RET_FAIL;

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (0 != strncmp(line, "ctxt", 4))
			continue;

		if (1 != sscanf(line, "%*s " ZBX_FS_UI64, &value))
			continue;

		SET_UI64_RESULT(result, value);
		ret = SYSINFO_RET_OK;
		break;
	}
	zbx_fclose(f);

	return ret;
}

int     SYSTEM_CPU_INTR(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN];
	zbx_uint64_t	value = 0;
	FILE		*f;

	if (NULL == (f = fopen("/proc/stat", "r")))
		return SYSINFO_RET_FAIL;

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (0 != strncmp(line, "intr", 4))
			continue;

		if (1 != sscanf(line, "%*s " ZBX_FS_UI64, &value))
			continue;

		SET_UI64_RESULT(result, value);
		ret = SYSINFO_RET_OK;
		break;
	}
	zbx_fclose(f);

	return ret;
}
