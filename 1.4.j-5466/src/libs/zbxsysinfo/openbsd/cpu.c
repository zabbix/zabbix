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
#ifdef HAVE_FUNCTION_SYSCTL_HW_NCPU
	size_t	len;
	int	mib[2], ncpu;
	char	mode[MAX_STRING_LEN];

	assert(result);

	init_result(result);

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 == get_param(param, 1, mode, sizeof(mode))) {
		if (*mode != '\0') {
			if (0 != strcmp(mode, "online"))
				return SYSINFO_RET_FAIL;
		}
	}

	mib[0] = CTL_HW;
	mib[1] = HW_NCPU;

	len = sizeof(ncpu);
	if (-1 == sysctl(mib, 2, &ncpu, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTL_HW_NCPU */
}

static int get_cpu_data(unsigned long long *idle,
			unsigned long long *user,
			unsigned long long *nice,
			unsigned long long *system,
			unsigned long long *intr)
{
	u_int64_t value[CPUSTATES];
	int ret = SYSINFO_RET_FAIL;
	int mib[2];
	size_t l; 

	mib[0] = CTL_KERN;
	mib[1] = KERN_CLOCKRATE;

	l = sizeof(value);

	if (sysctl(mib, 2, value, &l, NULL, 0) == 0 ) 
	{
		(*idle)	= value[CP_IDLE];
		(*user)	= value[CP_USER];
		(*nice)	= value[CP_NICE];
		(*system) = value[CP_SYS];
		(*intr)	= value[CP_INTR];
		ret = SYSINFO_RET_OK;
 	}

	return ret;
}

#define CPU_I 0
#define CPU_U 1
#define CPU_N 2
#define CPU_S 3
#define CPU_T 4

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define CPU_PARAMLIST struct cpy_paramlist_s
CPU_PARAMLIST
{
	char 	*mode;
	int 	id;
};

	CPU_PARAMLIST pl[] = 
	{
		{"idle" ,	CPU_I},
		{"user" ,	CPU_U},
		{"nice",	CPU_N},
		{"system",	CPU_S},
		{"intr",	CPU_T},
		{0,		0}
	};

    unsigned long long cpu_val[5];
    unsigned long long interval_size;

    char cpuname[MAX_STRING_LEN];
    char mode[MAX_STRING_LEN];

    int i;

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
        strscpy(mode, pl[0].mode);
    }

	for(i=0; pl[i].mode!=0; i++)
	{
		if(strncmp(mode, pl[i].mode, MAX_STRING_LEN)==0)
		{
			ret = get_cpu_data(
				&cpu_val[CPU_I], 
				&cpu_val[CPU_U], 
				&cpu_val[CPU_N], 
				&cpu_val[CPU_S], 
				&cpu_val[CPU_T]);
			
			if(ret == SYSINFO_RET_OK)
			{
				interval_size = 
					cpu_val[CPU_I] + 
					cpu_val[CPU_U] + 
					cpu_val[CPU_N] + 
					cpu_val[CPU_S] + 
					cpu_val[CPU_T];

				if (interval_size > 0)
				{
					SET_DBL_RESULT(result, ((double)cpu_val[pl[i].id] * 100.0)/(double)interval_size);
		
					ret = SYSINFO_RET_OK;
				}
			}
			break;
		}
	}

	return ret;
}

int	SYSTEM_CPU_LOAD1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double	load[3];
	int ret = SYSINFO_RET_FAIL;

	assert(result);

        init_result(result);
		
	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[0]);
		ret = SYSINFO_RET_OK;
	}
	
	return ret;
}

int	SYSTEM_CPU_LOAD5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double	load[3];
	int ret = SYSINFO_RET_FAIL;

	assert(result);

        init_result(result);
		
	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[1]);
		ret = SYSINFO_RET_OK;
	}
	
	return ret;
}

int	SYSTEM_CPU_LOAD15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double	load[3];
	int ret = SYSINFO_RET_FAIL;

	assert(result);

        init_result(result);
		
	if(getloadavg(load, 3))
	{
		SET_DBL_RESULT(result, load[2]);
		ret = SYSINFO_RET_OK;
	}
	
	return ret;
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
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, param, flags, result);
		}
	}
	
	return SYSINFO_RET_FAIL;
}

int     SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        assert(result);

        init_result(result);
	
	return SYSINFO_RET_FAIL;
}

int     SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        assert(result);

        init_result(result);
	
	return SYSINFO_RET_FAIL;
}

