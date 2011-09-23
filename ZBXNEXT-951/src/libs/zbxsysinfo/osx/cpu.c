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

#ifdef HAVE_KNLIST_H
static int getloadavg_kmem(double loadavg[], int nelem)
{
	struct nlist	nl;
	int		kmem, i;
	long		avenrun[3];

	nl.n_name = "avenrun";
	nl.n_value = 0;

	if (knlist(&nl, 1, sizeof(nl)))
		return FAIL;

	if (0 >= (kmem = open("/dev/kmem", 0, 0)))
		return FAIL;

	if (pread(kmem, avenrun, sizeof(avenrun), nl.n_value) < sizeof(avenrun))
		return FAIL;

	for (i = 0; i < nelem; i++)
	{
		loadavg[i] = (double) avenrun[i] / 65535;
	}

	return SUCCEED;
}
#endif

static int	SYSTEM_CPU_LOAD1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(HAVE_GETLOADAVG)
	double	load[3];

	if (0 >= getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, load[0]);

	return SYSINFO_RET_OK;
#elif defined(HAVE_SYS_PSTAT_H)
	struct	pst_dynamic dyn;

	if (-1 == pstat_getdynamic(&dyn, sizeof(dyn), 1, 0))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, dyn.psd_avg_1_min);

	return SYSINFO_RET_OK;
#elif defined(HAVE_PROC_LOADAVG)
	return	getPROC("/proc/loadavg", 1, 1, flags, result);
#elif defined(HAVE_KSTAT_H)
	static kstat_ctl_t	*kc = NULL;
	kstat_t			*ks;
	kstat_named_t		*kn;

	if (!kc && !(kc = kstat_open()))
		return SYSINFO_RET_FAIL;

	if (!(ks = kstat_lookup(kc, "unix", 0, "system_misc")) || -1 == kstat_read(kc, ks, 0) ||
		!(kn = kstat_data_lookup(ks, "avenrun_1min")))
	{
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, ((double)kn->value.ul) / 256.0);

	return SYSINFO_RET_OK;
#elif defined(HAVE_KNLIST_H)
	double loadavg[3];

	if (FAIL == getloadavg_kmem(loadavg, 3))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, loadavg[0]);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

static int	SYSTEM_CPU_LOAD5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(HAVE_GETLOADAVG)
	double	load[3];

	if (1 >= getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, load[1]);

	return SYSINFO_RET_OK;
#elif defined(HAVE_SYS_PSTAT_H)
	struct pst_dynamic	dyn;

	if (-1 == pstat_getdynamic(&dyn, sizeof(dyn), 1, 0))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, dyn.psd_avg_5_min);

	return SYSINFO_RET_OK;
#elif defined(HAVE_PROC_LOADAVG)
	return	getPROC("/proc/loadavg", 1, 2, flags, result);
#elif defined(HAVE_KSTAT_H)
	static kstat_ctl_t	*kc = NULL;
	kstat_t			*ks;
	kstat_named_t		*kn;

	if (!kc && !(kc = kstat_open()))
		return SYSINFO_RET_FAIL;

	if (!(ks = kstat_lookup(kc, "unix", 0, "system_misc")) || -1 == kstat_read(kc, ks, 0) ||
		!(kn = kstat_data_lookup(ks, "avenrun_5min")))
	{
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, ((double)kn->value.ul) / 256.0);

	return SYSINFO_RET_OK;
#elif defined(HAVE_KNLIST_H)
	double loadavg[3];

	if (FAIL == getloadavg_kmem(loadavg,3))
		return STSINFO_RET_FAIL;

	SET_DBL_RESULT(result, loadavg[1]);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

static int	SYSTEM_CPU_LOAD15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(HAVE_GETLOADAVG)
	double	load[3];

	if (2 >= getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, load[2]);

	return SYSINFO_RET_OK;
#elif defined(HAVE_SYS_PSTAT_H)
	struct pst_dynamic	dyn;

	if (-1 == pstat_getdynamic(&dyn, sizeof(dyn), 1, 0))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, dyn.psd_avg_15_min);

	return SYSINFO_RET_OK;
#elif defined(HAVE_PROC_LOADAVG)
	return	getPROC("/proc/loadavg", 1, 3, flags, result);
#elif defined(HAVE_KSTAT_H)
	static kstat_ctl_t	*kc = NULL;
	kstat_t			*ks;
	kstat_named_t		*kn;

	if (!kc && !(kc = kstat_open()))
		return SYSINFO_RET_FAIL;

	if (!(ks = kstat_lookup(kc, "unix", 0, "system_misc")) || -1 == kstat_read(kc, ks, 0) ||
		!(kn = kstat_data_lookup(ks, "avenrun_15min")))
	{
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, ((double)kn->value.ul) / 256.0);

	return SYSINFO_RET_OK;
#elif defined(HAVE_KNLIST_H)
	double loadavg[3];

	if (FAIL == getloadavg_kmem(loadavg,3))
		return STSINFO_RET_FAIL;

	SET_DBL_RESULT(result, loadavg[2]);

	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
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

	/* only "online" (default) for parameter "type" is supported */
	if (0 == get_param(param, 1, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strncmp(tmp, "online", sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp)
		zbx_snprintf(tmp, sizeof(tmp), "avg1");

	for (i = 0; NULL != fl[i].mode; i++)
	{
		if (0 == strncmp(tmp, fl[i].mode, sizeof(tmp)))
			return (fl[i].function)(cmd, param, flags, result);
	}

	return SYSINFO_RET_FAIL;
}
