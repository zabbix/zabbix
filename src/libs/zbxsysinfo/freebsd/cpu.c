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
#if defined(_SC_NPROCESSORS_ONLN)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	int	name;
	long	ncpu;
	char	mode[16];

	assert(result);

	init_result(result);

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)))
		*mode = '\0';

	if ('\0' == *mode || 0 == strcmp(mode, "online"))	/* default parameter */
		name = _SC_NPROCESSORS_ONLN;
	else if(0 == strcmp(mode, "max"))
		name = _SC_NPROCESSORS_CONF;
	else
		return SYSINFO_RET_FAIL;

	if (-1 == (ncpu = sysconf(name)) && EINVAL == errno)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
#elif defined(HAVE_FUNCTION_SYSCTL_HW_NCPU)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	size_t	len;
	int	mib[] = {CTL_HW, HW_NCPU}, ncpu;
	char	mode[16];

	assert(result);

	init_result(result);

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)))
		*mode = '\0';

	if ('\0' != *mode && 0 != strcmp(mode, "online"))	/* default parameter */
		return SYSINFO_RET_FAIL;

	len = sizeof(ncpu);

	if (0 != sysctl(mib, 2, &ncpu, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTL_HW_NCPU */
}

int     SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	u_int	v_intr;
	size_t	len;

	assert(result);

	init_result(result);
	
	len = sizeof(v_intr);

	if (0 != sysctlbyname("vm.stats.sys.v_intr", &v_intr, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, v_intr);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTLBYNAME */
}

int     SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	u_int	v_swtch;
	size_t	len;

	assert(result);

	init_result(result);
	
	len = sizeof(v_swtch);

	if (0 != sysctlbyname("vm.stats.sys.v_swtch", &v_swtch, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, v_swtch);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTLBYNAME */
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	cpuname[MAX_STRING_LEN],
		type[MAX_STRING_LEN],
		mode[MAX_STRING_LEN];
	int	cpu_num;

	assert(result);

	init_result(result);

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, cpuname, sizeof(cpuname)))
		*cpuname = '\0';

	/* default parameter */
	if (*cpuname == '\0')
		zbx_snprintf(cpuname, sizeof(cpuname), "all");

	if (0 == strcmp(cpuname, "all"))
		cpu_num = 0;
	else {
		cpu_num = atoi(cpuname) + 1;

		if (cpu_num < 1 || cpu_num > collector->cpus.count)
			return SYSINFO_RET_FAIL;
	}

	if (0 != get_param(param, 2, type, sizeof(type)))
		*type = '\0';

	/* default parameter */
	if (*type == '\0')
		zbx_snprintf(type, sizeof(type), "user");

	if (0 != get_param(param, 3, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "avg1");

	if (!CPU_COLLECTOR_STARTED(collector)) {
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if (0 == strcmp(type, "idle")) {
		if (0 == strcmp(mode, "avg1"))		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].idle1)
		else if (0 == strcmp(mode, "avg5"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].idle5)
		else if (0 == strcmp(mode, "avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].idle15)
		else return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(type, "nice"))
	{
		if (0 == strcmp(mode, "avg1")) 		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].nice1)
		else if (0 == strcmp(mode, "avg5")) 	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].nice5)
		else if (0 == strcmp(mode, "avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].nice15)
		else return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(type, "user"))
	{
		if (0 == strcmp(mode, "avg1")) 		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].user1)
		else if (0 == strcmp(mode, "avg5")) 	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].user5)
		else if (0 == strcmp(mode, "avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].user15)
		else return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(type, "system"))
	{
		if (0 == strcmp(mode, "avg1")) 		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].system1)
		else if (0 == strcmp(mode, "avg5")) 	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].system5)
		else if (0 == strcmp(mode, "avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].system15)
		else return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(type, "interrupt"))
	{
		if (0 == strcmp(mode, "avg1")) 		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].interrupt1)
		else if (0 == strcmp(mode, "avg5")) 	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].interrupt5)
		else if (0 == strcmp(mode, "avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].interrupt15)
		else return SYSINFO_RET_FAIL;
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

static int	get_cpuload(double *load1, double *load5, double *load15)
{
#ifdef HAVE_GETLOADAVG
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	double	load[3];

	if (-1 == getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	if (load1)
		*load1 = load[0];
	if (load5)
		*load5 = load[1];
	if (load15)
		*load15 = load[2];

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_GETLOADAVG */
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
#define CPU_FNCLIST struct cpu_fnclist_s
CPU_FNCLIST
{
	char	*mode;
	int	(*function)();
};

	CPU_FNCLIST fl[] = 
	{
		{"avg1",	SYSTEM_CPU_LOAD1},
		{"avg5",	SYSTEM_CPU_LOAD5},
		{"avg15",	SYSTEM_CPU_LOAD15},
		{0,		0}
	};

	char	cpuname[MAX_STRING_LEN],
		mode[MAX_STRING_LEN];
	int	i;

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, cpuname, sizeof(cpuname)))
		*cpuname = '\0';

	/* default parameter */
	if (*cpuname == '\0')
		zbx_snprintf(cpuname, sizeof(cpuname), "all");

	if (0 != strncmp(cpuname, "all", sizeof(cpuname)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "avg1");

	for (i = 0; fl[i].mode != 0; i++)
		if (0 == strncmp(mode, fl[i].mode, MAX_STRING_LEN))
			return (fl[i].function)(result);

	return SYSINFO_RET_FAIL;
}
