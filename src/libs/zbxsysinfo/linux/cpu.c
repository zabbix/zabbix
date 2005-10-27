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

#include "config.h"

#include "common.h"
#include "sysinfo.h"

int     OLD_CPU(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat(cmd, flags, result);
}

int	SYSTEM_CPU_IDLE1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[idle1]", flags, result);
}

int	SYSTEM_CPU_IDLE5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[idle5]", flags, result);
}

int	SYSTEM_CPU_IDLE15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[idle15]", flags, result);
}

int	SYSTEM_CPU_NICE1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[nice1]", flags, result);
}

int	SYSTEM_CPU_NICE5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[nice5]", flags, result);
}
int	SYSTEM_CPU_NICE15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[nice15]", flags, result);
}

int	SYSTEM_CPU_USER1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[user1]", flags, result);
}

int	SYSTEM_CPU_USER5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[user5]", flags, result);
}

int	SYSTEM_CPU_USER15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[user15]", flags, result);
}

int	SYSTEM_CPU_SYS1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[system1]", flags, result);
}

int	SYSTEM_CPU_SYS5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[system5]", flags, result);
}

int	SYSTEM_CPU_SYS15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return	get_stat("cpu[system15]", flags, result);
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

int	SYSTEM_CPU_LOAD1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
		
	if(getloadavg(load, 3))
	{
		result->type |= AR_DOUBLE;
		result->dbl = load[0];
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;	
	}
#else
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		result->type |= AR_DOUBLE;
		result->dbl = (double)dyn.psd_avg_1_min;
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

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
	
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
        result->type |= AR_DOUBLE;
	result->dbl = (double)kn->value.ul/256.0;
	return SYSINFO_RET_OK;
#else
#ifdef HAVE_KNLIST_H
	double loadavg[3];

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
		
	if(getloadavg_kmem(loadavg,3) == FAIL)
	{
		return SYSINFO_RET_FAIL;
	}

        result->type |= AR_DOUBLE;
	result->dbl = loadavg[0];
	return SYSINFO_RET_OK;
#else
	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
	return	SYSINFO_RET_FAIL;
#endif
#endif
#endif
#endif
#endif
}

int	SYSTEM_CPU_LOAD5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
		
	if(getloadavg(load, 3))
	{
		result->type |= AR_DOUBLE;
		result->dbl = load[1];
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;	
	}
#else
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
	
	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		result->type |= AR_DOUBLE;
		result->dbl = (double)dyn.psd_avg_5_min;
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

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
		
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
        result->type |= AR_DOUBLE;
	result->dbl = (double)kn->value.ul/256.0;
	return SYSINFO_RET_OK;
#else
#ifdef HAVE_KNLIST_H
	double loadavg[3];

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	if(getloadavg_kmem(loadavg,3) == FAIL)
	{
		return STSINFO_RET_FAIL;
	}

        result->type |= AR_DOUBLE;
	result->dbl = loadavg[1];
	return SYSINFO_RET_OK;
#else
	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
	return	SYSINFO_RET_FAIL;
#endif
#endif
#endif
#endif
#endif
}

int	SYSTEM_CPU_LOAD15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	if(getloadavg(load, 3))
	{
		result->type |= AR_DOUBLE;
		result->dbl = load[2];	
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;	
	}
#else
#ifdef	HAVE_SYS_PSTAT_H
	struct	pst_dynamic dyn;

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
		
	if (pstat_getdynamic(&dyn, sizeof(dyn), 1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		result->type |= AR_DOUBLE;
		result->dbl = (double)dyn.psd_avg_15_min;
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

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
		
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
        result->type |= AR_DOUBLE;
	result->dbl = (double)kn->value.ul/256.0;
	return SYSINFO_RET_OK;
#else
#ifdef HAVE_KNLIST_H
	double loadavg[3];

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
	
	if(getloadavg_kmem(loadavg,3) == FAIL)
	{
		return STSINFO_RET_FAIL;
	}

        result->type |= AR_DOUBLE;
	result->dbl = loadavg[2];
	return SYSINFO_RET_OK;
#else
	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
	return	SYSINFO_RET_FAIL;
#endif
#endif
#endif
#endif
#endif
}

