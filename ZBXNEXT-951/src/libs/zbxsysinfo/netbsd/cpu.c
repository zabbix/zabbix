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
#ifdef HAVE_FUNCTION_SYSCTL_HW_NCPU	/* NetBSD 3.1 i386; NetBSD 4.0 i386 */
	char	tmp[16];
	size_t	len;
	int	mib[] = {CTL_HW, HW_NCPU}, ncpu;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	/* only "online" (default) for parameter "type" is supported */
	if (0 == get_param(param, 1, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strncmp(tmp, "online", sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	len = sizeof(ncpu);

	if (0 != sysctl(mib, 2, &ncpu, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

int     SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_UVM_UVMEXP2	/* NetBSD 3.1 i386; NetBSD 4.0 i386 */
	int			mib[] = {CTL_VM, VM_UVMEXP2};
	size_t			len;
	struct uvmexp_sysctl	v;

	len = sizeof(struct uvmexp_sysctl);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, v.intrs);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

int     SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_UVM_UVMEXP2	/* NetBSD 3.1 i386; NetBSD 4.0 i386 */
	int			mib[] = {CTL_VM, VM_UVMEXP2};
	size_t			len;
	struct uvmexp_sysctl	v;

	len = sizeof(struct uvmexp_sysctl);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, v.swtch);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];
	int	cpu_num, state, mode;

	if (3 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = 0;
	else if (1 > (cpu_num = atoi(tmp) + 1))
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

static int	get_cpuload(double *load1, double *load5, double *load15)
{
#ifdef HAVE_GETLOADAVG	/* NetBSD 3.1 i386; NetBSD 4.0 i386 */
	double	load[3];

	if (-1 == getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	if (NULL != load1)
		*load1 = load[0];
	if (NULL != load5)
		*load5 = load[1];
	if (NULL != load15)
		*load15 = load[2];

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

static int	SYSTEM_CPU_LOAD1(AGENT_RESULT *result)
{
	double	value;

	if (SYSINFO_RET_OK != get_cpuload(&value, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_CPU_LOAD5(AGENT_RESULT *result)
{
	double	value;

	if (SYSINFO_RET_OK != get_cpuload(NULL, &value, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_CPU_LOAD15(AGENT_RESULT *result)
{
	double	value;

	if (SYSINFO_RET_OK != get_cpuload(NULL, NULL, &value))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	static MODE_FUNCTION fl[] =
	{
		{"avg1",	SYSTEM_CPU_LOAD1},
		{"avg5",	SYSTEM_CPU_LOAD5},
		{"avg15",	SYSTEM_CPU_LOAD15},
		{NULL,		NULL}
	};

	char	tmp[16];
	int	i;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	/* only "all" (default) for parameter "cpu" is supported */
	if (0 == get_param(param, 1, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strncmp(tmp, "all", sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp)
		zbx_snprintf(tmp, sizeof(tmp), "avg1");

	for (i = 0; NULL != fl[i].mode; i++)
	{
		if (0 == strncmp(tmp, fl[i].mode, sizeof(tmp)))
			return (fl[i].function)(result);
	}

	return SYSINFO_RET_FAIL;
}
