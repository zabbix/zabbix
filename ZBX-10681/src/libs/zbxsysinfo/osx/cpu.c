/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

static int	get_cpu_num(int online)
{
	int	mib[2], cpu_num;
	size_t	len = sizeof(cpu_num);

	mib[0] = CTL_HW;
	mib[1] = (1 == online ? HW_AVAILCPU : HW_NCPU);

	if (0 != sysctl(mib, 2, &cpu_num, &len, NULL, 0))
		return -1;

	return cpu_num;
}

int	SYSTEM_CPU_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];
	int	cpu_num, online = 0;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "online"))
		online = 1;
	else if (0 != strcmp(tmp, "max"))
		return SYSINFO_RET_FAIL;

	if (-1 == (cpu_num = get_cpu_num(online)))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, cpu_num);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];
	int	mode, per_cpu = 1, cpu_num;
	double	load[ZBX_AVG_COUNT], value;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		per_cpu = 0;
	else if (0 != strcmp(tmp, "percpu"))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
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
		if (0 >= (cpu_num = get_cpu_num(1)))
			return SYSINFO_RET_FAIL;
		value /= cpu_num;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}
