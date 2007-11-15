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
	char cpuname[MAX_STRING_LEN];
	char type[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	
	int cpu_num = 0;

        assert(result);

        init_result(result);
	
        if(num_param(param) > 3)
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

	if(get_param(param, 2, type, sizeof(type)) != 0)
        {
                type[0] = '\0';
        }
        if(type[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(type, sizeof(type), "user");
	}
	
	if(get_param(param, 3, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }
	
        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "avg1");
	}

	if ( !CPU_COLLECTOR_STARTED(collector) )
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if(strcmp(cpuname,"all") == 0)
	{
		cpu_num = 0;
	}
	else
	{
		cpu_num = atoi(cpuname);
		if ((cpu_num < 1) || (cpu_num > collector->cpus.count))
			return SYSINFO_RET_FAIL;
	}


	if( 0 == strcmp(type,"idle"))
	{
		if( 0 == strcmp(mode,"avg1"))		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].idle1)
		else if( 0 == strcmp(mode,"avg5"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].idle5)
		else if( 0 == strcmp(mode,"avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].idle15)
		else return SYSINFO_RET_FAIL;

	}
	else if( 0 == strcmp(type,"nice"))
	{
		if( 0 == strcmp(mode,"avg1")) 		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].nice1)
		else if( 0 == strcmp(mode,"avg5")) 	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].nice5)
		else if( 0 == strcmp(mode,"avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].nice15)
		else return SYSINFO_RET_FAIL;

	}
	else if( 0 == strcmp(type,"user"))
	{
		if( 0 == strcmp(mode,"avg1")) 		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].user1)
		else if( 0 == strcmp(mode,"avg5")) 	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].user5)
		else if( 0 == strcmp(mode,"avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].user15)
		else return SYSINFO_RET_FAIL;
	}
	else if( 0 == strcmp(type,"system"))
	{
		if( 0 == strcmp(mode,"avg1")) 		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].system1)
		else if( 0 == strcmp(mode,"avg5")) 	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].system5)
		else if( 0 == strcmp(mode,"avg15"))	SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].system15)
		else return SYSINFO_RET_FAIL;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_LOAD1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
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

int	SYSTEM_CPU_LOAD5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
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

int	SYSTEM_CPU_LOAD15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
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
		if(strncmp(mode, fl[i].mode, sizeof(mode))==0)
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
	int ret = SYSINFO_RET_FAIL;
	char line[MAX_STRING_LEN];

	char name[MAX_STRING_LEN];
	zbx_uint64_t value = 0;
	
	FILE *f;

        assert(result);

        init_result(result);

	if(NULL != ( f = fopen("/proc/stat","r") ))
	{
		while(fgets(line,sizeof(line),f) != NULL)
		{
			if(sscanf(line,"%s\t" ZBX_FS_UI64 "\n", name, &value) != 2) 
				continue;
		
			if(strncmp(name, "intr", sizeof(name)) == 0)
			{
				SET_UI64_RESULT(result, value);
				ret = SYSINFO_RET_OK;
				break;
			}
		}
		zbx_fclose(f);
	}

	return ret;
}

