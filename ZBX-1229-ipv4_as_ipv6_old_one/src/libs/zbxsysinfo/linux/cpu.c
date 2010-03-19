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
	char	mode[32];
	int	sysinfo_name;
	long	ncpu = 0;

	assert(result);

	init_result(result);

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)))
		*mode = '\0';

	if ('\0' == *mode || 0 == strcmp(mode, "online"))	/* default parameter */
		sysinfo_name = _SC_NPROCESSORS_ONLN;
	else if(0 == strcmp(mode, "max"))
		sysinfo_name = _SC_NPROCESSORS_CONF;
	else
		return SYSINFO_RET_FAIL;

	if (-1 == (ncpu = sysconf(sysinfo_name)) && EINVAL == errno)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[32], type[32];
	int	cpu_num, mode;

	assert(result);

	init_result(result);

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "all"))	/* default parameter */
		cpu_num = 0;
	else
	{
		cpu_num = atoi(tmp) + 1;
		if (cpu_num < 1 || cpu_num > collector->cpus.count)
			return SYSINFO_RET_FAIL;
	}

	if (0 != get_param(param, 2, type, sizeof(type)))
		*type = '\0';

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if ('\0' == *type || 0 == strcmp(type, "user"))	/* default parameter */
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].user[mode])
	else if (0 == strcmp(type, "nice"))
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].nice[mode])
	else if (0 == strcmp(type, "system"))
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].system[mode])
	else if (0 == strcmp(type, "idle"))
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].idle[mode])
	else if (0 == strcmp(type, "iowait"))
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].iowait[mode])
	else if (0 == strcmp(type, "interrupt"))
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].interrupt[mode])
	else if (0 == strcmp(type, "softirq"))
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].softirq[mode])
	else if (0 == strcmp(type, "steal"))
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].steal[mode])
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[32];
	int	mode;
	double	load[ZBX_AVGMAX];

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' != *tmp && 0 != strcmp(tmp, "all"))	/* default parameter */
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if (mode >= getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, load[mode]);

	return SYSINFO_RET_OK;
}

int     SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN], name[32];
	zbx_uint64_t	value = 0;
	FILE		*f;

	assert(result);

	init_result(result);

	if (NULL == (f = fopen("/proc/stat", "r")))
		return SYSINFO_RET_FAIL;

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (2 != sscanf(line, "%s " ZBX_FS_UI64, name, &value))
			continue;

		if (0 == strcmp(name, "ctxt"))
		{
			SET_UI64_RESULT(result, value);
			ret = SYSINFO_RET_OK;
			break;
		}
	}
	zbx_fclose(f);

	return ret;
}

int     SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN], name[32];
	zbx_uint64_t	value = 0;
	FILE		*f;

	assert(result);

	init_result(result);

	if (NULL == (f = fopen("/proc/stat", "r")))
		return SYSINFO_RET_FAIL;

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (2 != sscanf(line, "%s " ZBX_FS_UI64, name, &value))
			continue;

		if (0 == strcmp(name, "intr"))
		{
			SET_UI64_RESULT(result, value);
			ret = SYSINFO_RET_OK;
			break;
		}
	}
	zbx_fclose(f);

	return ret;
}
