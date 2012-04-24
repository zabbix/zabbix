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
	int	cpu_num, mode, state;

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "all"))	/* default parameter */
		cpu_num = 0;
	else if (1 > (cpu_num = atoi(tmp) + 1))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "user"))	/* default parameter */
		state = ZBX_CPU_STATE_USER;
	else if (0 == strcmp(tmp, "nice"))
		state = ZBX_CPU_STATE_NICE;
	else if (0 == strcmp(tmp, "system"))
		state = ZBX_CPU_STATE_SYSTEM;
	else if (0 == strcmp(tmp, "idle"))
		state = ZBX_CPU_STATE_IDLE;
	else
		return SYSINFO_RET_FAIL;

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

	return get_cpustat(result, cpu_num, state, mode);
}

/* AIX CPU info */
#ifdef HAVE_KNLIST_H
static int getloadavg_kmem(double loadavg[], int nelem)
{
	struct nlist nl;
	int kmem, i;
	long avenrun[3];

	nl.n_name = "avenrun";
	nl.n_value = 0;

	if(knlist(&nl, 1, sizeof(nl)))
	{
		return FAIL;
	}
	if((kmem = open("/dev/kmem", 0, 0)) <= 0)
	{
		return FAIL;
	}

	if(pread(kmem, avenrun, sizeof(avenrun), nl.n_value) <
				sizeof(avenrun))
	{
		return FAIL;
	}

	for(i=0;i<nelem;i++)
	{
		loadavg[i] = (double) avenrun[i] / 65535;
	}
	return SUCCEED;
}
#endif

static int	SYSTEM_CPU_LOAD1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[0]);
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
#else
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		SET_DBL_RESULT(result, dyn.psd_avg_1_min);
		return SYSINFO_RET_OK;
	}
#else
#ifdef HAVE_PROC_LOADAVG
	return	getPROC("/proc/loadavg",1,1, flags, result);
#else
#ifdef HAVE_KSTAT_H
	static kstat_ctl_t *kc = NULL;
	kstat_t *ks;
	kstat_named_t *kn;

	if (!kc && !(kc = kstat_open()))
	{
		return SYSINFO_RET_FAIL;
	}
	if (!(ks = kstat_lookup(kc, "unix", 0, "system_misc")) ||
		kstat_read(kc, ks, 0) == -1 ||
		!(kn = kstat_data_lookup(ks,"avenrun_1min")))
	{
		return SYSINFO_RET_FAIL;
	}
	SET_DBL_RESULT(result, ((double)kn->value.ul)/256.0);
	return SYSINFO_RET_OK;
#else
#ifdef HAVE_KNLIST_H
	double loadavg[3];

	if(getloadavg_kmem(loadavg,3) == FAIL)
	{
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, loadavg[0]);
	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
#endif
#endif
#endif
#endif
}

static int	SYSTEM_CPU_LOAD5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[1]);
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#else
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		SET_DBL_RESULT(result, dyn.psd_avg_5_min);
		return SYSINFO_RET_OK;
	}
#else
#ifdef	HAVE_PROC_LOADAVG
	return	getPROC("/proc/loadavg",1,2, flags, result);
#else
#ifdef HAVE_KSTAT_H
	static kstat_ctl_t *kc = NULL;
	kstat_t *ks;
	kstat_named_t *kn;

	if (!kc && !(kc = kstat_open()))
	{
		return SYSINFO_RET_FAIL;
	}
	if (!(ks = kstat_lookup(kc, "unix", 0, "system_misc")) ||
		kstat_read(kc, ks, 0) == -1 ||
		!(kn = kstat_data_lookup(ks,"avenrun_5min")))
	{
		return SYSINFO_RET_FAIL;
	}
	SET_DBL_RESULT(result, ((double)kn->value.ul)/256.0);
	return SYSINFO_RET_OK;
#else
#ifdef HAVE_KNLIST_H
	double loadavg[3];

	if(getloadavg_kmem(loadavg,3) == FAIL)
	{
		return STSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, loadavg[1]);
	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
#endif
#endif
#endif
#endif
}

static int	SYSTEM_CPU_LOAD15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[2]);
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#else
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		SET_DBL_RESULT(result, dyn.psd_avg_15_min);
		return SYSINFO_RET_OK;
	}
#else
#ifdef	HAVE_PROC_LOADAVG
	return	getPROC("/proc/loadavg",1,3, flags, result);
#else
#ifdef HAVE_KSTAT_H
	static kstat_ctl_t *kc = NULL;
	kstat_t *ks;
	kstat_named_t *kn;

	if (!kc && !(kc = kstat_open()))
	{
		return SYSINFO_RET_FAIL;
	}
	if (!(ks = kstat_lookup(kc, "unix", 0, "system_misc")) ||
		kstat_read(kc, ks, 0) == -1 ||
		!(kn = kstat_data_lookup(ks,"avenrun_15min")))
	{
		return SYSINFO_RET_FAIL;
	}
	SET_DBL_RESULT(result, ((double)kn->value.ul)/256.0);
	return SYSINFO_RET_OK;
#else
#ifdef HAVE_KNLIST_H
	double loadavg[3];

	if(getloadavg_kmem(loadavg,3) == FAIL)
	{
		return STSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, loadavg[2]);
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
#endif
#endif
#endif
#endif
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"avg1" ,	SYSTEM_CPU_LOAD1},
		{"avg5" ,	SYSTEM_CPU_LOAD5},
		{"avg15",	SYSTEM_CPU_LOAD15},
		{0,		0}
	};

	char	cpuname[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];
	int	i;

        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, cpuname, sizeof(cpuname)) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	if(cpuname[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(cpuname, sizeof(cpuname), "all");
	}
	if(strncmp(cpuname, "all", sizeof(cpuname)))
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }
        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "avg1");
	}
	for(i=0; fl[i].mode!=0; i++)
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
			return (fl[i].function)(cmd, param, flags, result);

	return SYSINFO_RET_FAIL;
}
