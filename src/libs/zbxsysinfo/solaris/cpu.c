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

static int get_cpu_data(unsigned long long *idle,
                        unsigned long long *system,
                        unsigned long long *user,
                        unsigned long long *iowait)
{
    kstat_ctl_t	*kc;
    kstat_t	*k;
    cpu_stat_t	*cpu;
       	
    int cpu_count = 0;
    
    *idle = 0LL;
    *system = 0LL;
    *user = 0LL;
    *iowait = 0LL;

    kc = kstat_open();
    if (kc)
    {
    	k = kc->kc_chain;
	while (k)
	{
	    if ((strncmp(k->ks_name, "cpu_stat", 8) == 0)
		&& (kstat_read(kc, k, NULL) != -1)
	    )
	    {
		cpu = (cpu_stat_t *) k->ks_data;

		*idle	+=  cpu->cpu_sysinfo.cpu[CPU_IDLE];
		*system +=  cpu->cpu_sysinfo.cpu[CPU_KERNEL];
		*iowait +=  cpu->cpu_sysinfo.cpu[CPU_WAIT];
		*user	+=  cpu->cpu_sysinfo.cpu[CPU_USER];

		cpu_count += 1;
  	    }
	    k = k->ks_next;
        }
	kstat_close(kc);
    }
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
    if(strncmp(cpuname, "all", MAX_STRING_LEN))
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
    
    if (get_cpu_data(&cpu_val[CPU_I], &cpu_val[CPU_K], &cpu_val[CPU_U], &cpu_val[CPU_W]))
    {
        interval_size =	cpu_val[CPU_I] + cpu_val[CPU_K] + cpu_val[CPU_U] + cpu_val[CPU_W];
        
	if (interval_size > 0)
	{
		SET_DBL_RESULT(result, (cpu_val[info_id] * 100.0)/interval_size);

            ret = SYSINFO_RET_OK;
        }
    }
    return ret;
}

static int	SYSTEM_CPU_LOAD1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double	load[3];

	assert(result);

        init_result(result);
		
	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[0]);
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;	
	}
}

static int	SYSTEM_CPU_LOAD5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double	load[3];

	assert(result);

        init_result(result);
		
	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[1]);
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;	
	}
}
	       
static int	SYSTEM_CPU_LOAD15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double	load[3];

	assert(result);

        init_result(result);
		
	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[2]);
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;	
	}
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define CPU_FNCLIST struct cpu_fnclist_s
CPU_FNCLIST
{
	char *mode;
	int (*function)();
};

	CPU_FNCLIST fl[] = 
	{
		{"avg1" ,	SYSTEM_CPU_LOAD1},
		{"avg5" ,	SYSTEM_CPU_LOAD5},
		{"avg15",	SYSTEM_CPU_LOAD15},
		{0,		0}
	};

	char cpuname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;
	
        assert(result);

        init_result(result);
	
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
		zbx_snprintf(cpuname, sizeof(spuname), "all");
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
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, param, flags, result);
		}
	}
	
	return SYSINFO_RET_FAIL;
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

