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
#include "stats.h"

int	SYSTEM_CPU_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char			tmp[16];
	struct pst_dynamic	dyn;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	/* only "online" (default) for parameter "type" is supported */
	if (0 == get_param(param, 1, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strcmp(tmp, "online"))
		return SYSINFO_RET_FAIL;

	if (-1 == pstat_getdynamic(&dyn, sizeof(dyn), 1, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, dyn.psd_proc_cnt);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];
	int	cpu_num, state, mode;

	if (3 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = 0;
	else if (SUCCEED != is_uint(tmp) || 1 > (cpu_num = atoi(tmp) + 1))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "user"))
		state = ZBX_CPU_STATE_USER;
	else if (0 == strcmp(tmp, "nice"))
		state = ZBX_CPU_STATE_NICE;
	else if (0 == strcmp(tmp, "system"))
		state = ZBX_CPU_STATE_SYSTEM;
	else if (0 == strcmp(tmp, "idle"))
		state = ZBX_CPU_STATE_IDLE;
	else
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 3, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	return get_cpustat(result, cpu_num, state, mode);
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char			tmp[16];
	struct pst_dynamic	dyn;
	double			value;
	int			per_cpu = 1;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		per_cpu = 0;
	else if (0 != strcmp(tmp, "percpu"))
		return SYSINFO_RET_FAIL;

	if (-1 == pstat_getdynamic(&dyn, sizeof(dyn), 1, 0))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		value = dyn.psd_avg_1_min;
	else if (0 == strcmp(tmp, "avg5"))
		value = dyn.psd_avg_5_min;
	else if (0 == strcmp(tmp, "avg15"))
		value = dyn.psd_avg_15_min;
	else
		return SYSINFO_RET_FAIL;

	if (1 == per_cpu)
	{
		if (0 >= dyn.psd_proc_cnt)
			return SYSINFO_RET_FAIL;
		value /= dyn.psd_proc_cnt;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}
