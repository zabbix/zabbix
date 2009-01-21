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

static int get_cpu_data(
	const char* cpuname,
	unsigned long long *idle,
	unsigned long long *system,
	unsigned long long *user,
	unsigned long long *iowait)
{
	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;

	static int			first_run = 1;
	static unsigned long long	old_cpu[CPU_STATES];
	unsigned long long		new_cpu[CPU_STATES];

	char ks_name[MAX_STRING_LEN];

	int cpu_count = 0, i;

	assert(cpuname);
	assert(idle);
	assert(system);
	assert(user);
	assert(iowait);

	if(first_run)    for(i = 0; i < CPU_STATES; old_cpu[i++] = 0LL);

	for(i = 0; i < CPU_STATES; new_cpu[i++] = 0LL);

        zbx_snprintf(ks_name, sizeof(ks_name), "cpu_stat%s", cpuname);

	kc = kstat_open();
	if (kc)
	{
		k = kc->kc_chain;
		while (k)
		{
			if ((strncmp(k->ks_name, ks_name, strlen(ks_name)) == 0)
				&& (kstat_read(kc, k, NULL) != -1)
				)
			{
				cpu = (cpu_stat_t *) k->ks_data;

				for(i = 0; i < CPU_STATES; i++)
					new_cpu[i] += cpu->cpu_sysinfo.cpu[i];

				cpu_count += 1;
			}
			k = k->ks_next;
		}
		kstat_close(kc);
	}
    
	if(first_run)
	{
		*idle = *system = *iowait = *user = 0LL;
		first_run = 0;
	}
	else
	{
		*idle	=  new_cpu[CPU_IDLE]	- old_cpu[CPU_IDLE];
		*system =  new_cpu[CPU_KERNEL]	- old_cpu[CPU_KERNEL];
		*iowait =  new_cpu[CPU_WAIT]	- old_cpu[CPU_WAIT];
		*user	=  new_cpu[CPU_USER]	- old_cpu[CPU_USER];
	}
	
	for(i = 0; i < CPU_STATES; i++)	old_cpu[i] = new_cpu[i];
	
	return cpu_count;
}

#define CPU_I 0
#define CPU_U 1
#define CPU_K 2
#define CPU_W 3

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    unsigned long long cpu_val[4];
    unsigned long long interval_size;

    char cpuname[MAX_STRING_LEN];
    char mode[MAX_STRING_LEN];
    
    int info_id = 0;
    
    int ret = SYSINFO_RET_FAIL;

    if(num_param(param) > 2)
    {
        return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, cpuname, sizeof(cpuname)) != 0)
    {
	cpuname[0] = '\0';
    }
    if(cpuname[0] == '\0')
    {
	/* default parameter */
        zbx_snprintf(cpuname, sizeof(cpuname), "all");
    }

    if(0 == strncmp(cpuname, "all", MAX_STRING_LEN))
    {
	    cpuname[0] = '\0';
    }
    
    if(get_param(param, 2, mode, sizeof(mode)) != 0)
    {
	mode[0] = '\0';
    }
    
    if(mode[0] == '\0')
    {
	/* default parameter */
        zbx_snprintf(mode, sizeof(mode),"idle");
    }
    
    if(strcmp(mode,"idle") == 0)
    {
        info_id = CPU_I;
    }
    else if(strcmp(mode,"user") == 0)
    {
        info_id = CPU_U;
    }
    else if(strcmp(mode,"kernel") == 0)
    {
        info_id = CPU_K;
    }
    else if(strcmp(mode,"wait") == 0)
    {
	info_id = CPU_W;
    }
    else
    {
	return SYSINFO_RET_FAIL;
    }
    
    if (get_cpu_data(cpuname,&cpu_val[CPU_I], &cpu_val[CPU_K], &cpu_val[CPU_U], &cpu_val[CPU_W]))
    {
        interval_size =	cpu_val[CPU_I] + cpu_val[CPU_K] + cpu_val[CPU_U] + cpu_val[CPU_W];
        
	if (interval_size > 0)
	{
		SET_DBL_RESULT(result, (cpu_val[info_id] * 100.0)/interval_size);
        }
	else
	{
		SET_DBL_RESULT(result, 0);
	}
    ret = SYSINFO_RET_OK;
    }
    return ret;
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

