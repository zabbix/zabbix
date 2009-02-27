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
	char	mode[128];
	int	sysinfo_name = -1;
	long	ncpu = 0;
	
        assert(result);

        init_result(result);
	
        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }
        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "online");
	}

	if(0 == strncmp(mode, "online", sizeof(mode)))
	{
		sysinfo_name = _SC_NPROCESSORS_ONLN;
	}
	else if(0 == strncmp(mode, "max", sizeof(mode)))
	{
		sysinfo_name = _SC_NPROCESSORS_CONF;
	}

	if ( -1 == sysinfo_name || (-1 == (ncpu = sysconf(sysinfo_name)) && EINVAL == errno) )
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);
	
	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char				cpuname[8], type[8], mode[8];
	int				cpu_num;
	ZBX_SINGLE_CPU_STAT_DATA	*cpu;

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, cpuname, sizeof(cpuname)))
		*cpuname = '\0';

	if ('\0' == *cpuname || 0 == strcmp(cpuname, "all"))	/* default parameter */
		cpu_num = 0;
	else
	{
		if (FAIL == is_uint(cpuname))
			return SYSINFO_RET_FAIL;

		cpu_num = atoi(cpuname) + 1;
		if (cpu_num < 1 || cpu_num > collector->cpus.count)
			return SYSINFO_RET_FAIL;
	}

	if (0 != get_param(param, 2, type, sizeof(type)))
		*type = '\0';

	if (0 != get_param(param, 3, mode, sizeof(mode)))
		*mode = '\0';

	cpu = &collector->cpus.cpu[cpu_num];

	if ('\0' == *type || 0 == strcmp(type, "idle"))	/* default parameter */
	{
		if ('\0' == *mode || 0 == strcmp(mode, "avg1"))	SET_DBL_RESULT(result, cpu->idle1)
		else if (0 == strcmp(mode, "avg5"))		SET_DBL_RESULT(result, cpu->idle5)
		else if (0 == strcmp(mode, "avg15"))		SET_DBL_RESULT(result, cpu->idle15)
		else return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(type, "user"))
	{
		if ('\0' == *mode || 0 == strcmp(mode, "avg1"))	SET_DBL_RESULT(result, cpu->user1)
		else if (0 == strcmp(mode, "avg5"))		SET_DBL_RESULT(result, cpu->user5)
		else if (0 == strcmp(mode, "avg15"))		SET_DBL_RESULT(result, cpu->user15)
		else return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(type, "kernel"))
	{
		if ('\0' == *mode || 0 == strcmp(mode, "avg1"))	SET_DBL_RESULT(result, cpu->system1)
		else if (0 == strcmp(mode, "avg5"))		SET_DBL_RESULT(result, cpu->system5)
		else if (0 == strcmp(mode, "avg15"))		SET_DBL_RESULT(result, cpu->system15)
		else return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(type, "wait"))
	{
		if ('\0' == *mode || 0 == strcmp(mode, "avg1"))	SET_DBL_RESULT(result, cpu->nice1)
		else if (0 == strcmp(mode, "avg5"))		SET_DBL_RESULT(result, cpu->nice5)
		else if (0 == strcmp(mode, "avg15"))		SET_DBL_RESULT(result, cpu->nice15)
		else return SYSINFO_RET_FAIL;
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

#ifdef HAVE_KSTAT_H
static int	get_kstat_system_misc(char *s, int *value)
{
	kstat_ctl_t	*kc;
	kstat_t		*kp;
	kstat_named_t	*kn = NULL;
	int		n, i;

	if (NULL == (kc = kstat_open()))
		return FAIL;

	if (NULL == (kp = kstat_lookup(kc, "unix", 0, "system_misc")))
	{
		kstat_close(kc);
		return FAIL;
	}

	if (-1 == kstat_read(kc, kp, NULL))
	{
		kstat_close(kc);
		return FAIL;
	}

	if (NULL == (kn = (kstat_named_t*)kstat_data_lookup(kp, s)))
	{
		kstat_close(kc);
		return FAIL;
	}

	kstat_close(kc);

	*value = kn->value.ul;

	return SUCCEED;
}
#endif

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(HAVE_GETLOADAVG)
	double	load[3];
#elif defined(HAVE_KSTAT_H)
	char	*key;
	int	value;
#endif
	char	tmp[MAX_STRING_LEN];

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if ('\0' != *tmp && 0 != strcmp(tmp, "all"))	/* default parameter */
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

#if defined(HAVE_GETLOADAVG)
	if (-1 == getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
	{
		SET_DBL_RESULT(result, load[0]);
	}
	else if ('\0' == *tmp || 0 == strcmp(tmp, "avg5"))
	{
		SET_DBL_RESULT(result, load[1]);
	}
	else if ('\0' == *tmp || 0 == strcmp(tmp, "avg15"))
	{
		SET_DBL_RESULT(result, load[2]);
	}
	else
		return SYSINFO_RET_FAIL;
#elif defined(HAVE_KSTAT_H)
	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
		key = "avenrun_1min";
	else if ('\0' == *tmp || 0 == strcmp(tmp, "avg5"))
		key = "avenrun_5min";
	else if ('\0' == *tmp || 0 == strcmp(tmp, "avg15"))
		key = "avenrun_15min";
	else
		return SYSINFO_RET_FAIL;

	if (FAIL == get_kstat_system_misc(key, &value))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (double)value/FSCALE);
#else
	return SYSINFO_RET_FAIL;
#endif

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_ctl_t	    *kc;
    kstat_t	    *k;
    cpu_stat_t	    *cpu;
    
    int	    cpu_count = 0;
    double  swt_count = 0.0;
    
    assert(result);

    init_result(result);
		
    kc = kstat_open();

    if(kc != NULL)
    {    
	k = kc->kc_chain;
  	while (k != NULL)
	{
	    if( (strncmp(k->ks_name, "cpu_stat", 8) == 0) &&
		(kstat_read(kc, k, NULL) != -1) )
	    {
		cpu = (cpu_stat_t*) k->ks_data;
		swt_count += (double) cpu->cpu_sysinfo.pswitch;
		cpu_count += 1;
  	    }
	    k = k->ks_next;
        }
	kstat_close(kc);
    }

    if(cpu_count == 0)
    {
	return SYSINFO_RET_FAIL;
    }

	SET_UI64_RESULT(result, swt_count);
    
    return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_ctl_t	    *kc;
    kstat_t	    *k;
    cpu_stat_t	    *cpu;
    
    int	    cpu_count = 0;
    double  intr_count = 0.0;
    
    assert(result);

    init_result(result);
	
    kc = kstat_open();

    if(kc != NULL)
    {    
	k = kc->kc_chain;
  	while (k != NULL)
	{
	    if( (strncmp(k->ks_name, "cpu_stat", 8) == 0) &&
		(kstat_read(kc, k, NULL) != -1) )
	    {
		cpu = (cpu_stat_t*) k->ks_data;
		intr_count += (double) cpu->cpu_sysinfo.intr;
		cpu_count += 1;
  	    }
	    k = k->ks_next;
        }
	kstat_close(kc);
    }

    if(cpu_count == 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
	SET_UI64_RESULT(result, intr_count);
    
    return SYSINFO_RET_OK;
}

