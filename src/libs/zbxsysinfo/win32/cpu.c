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
	return SYSINFO_RET_FAIL;
}

int     OLD_CPU(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	/* SKIP REALIZATION */

	return SYSINFO_RET_FAIL;
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

	char cpuname[MAX_STRING_LEN];
	char type[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];

	int cpu_num = 0;

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
		zbx_snprintf(type, sizeof(type), "system");
	}
	if(strncmp(type, "system", sizeof(type)))
	{	/* only 'system' parameter supported */
		return SYSINFO_RET_FAIL;
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
		cpu_num = atoi(cpuname)+1;
		if ((cpu_num < 1) || (cpu_num > collector->cpus.count))
			return SYSINFO_RET_FAIL;
	}

	if(strcmp(type,"system"))
	{
		return SYSINFO_RET_FAIL;
	}

	if(strcmp(mode,"avg1") == 0)
	{
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].util1);
	}
	else	if(strcmp(mode,"avg5") == 0)
	{
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].util5);
	}
	else	if(strcmp(mode,"avg15") == 0)
	{
		SET_DBL_RESULT(result, collector->cpus.cpu[cpu_num].util15);
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}



int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	cpuname[10],
		mode[10];

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


	if(get_param(param, 2, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}

	if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "avg1");
	}

	if(strcmp(cpuname,"all") != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if ( !CPU_COLLECTOR_STARTED(collector) )
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if(strcmp(mode,"avg1") == 0)
	{
		SET_DBL_RESULT(result, collector->cpus.load1);
	}
	else	if(strcmp(mode,"avg5") == 0)
	{
		SET_DBL_RESULT(result, collector->cpus.load5);
	}
	else	if(strcmp(mode,"avg15") == 0)
	{
		SET_DBL_RESULT(result, collector->cpus.load15);
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;

}

int     SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function SYSTEM_CPU_SWITCHES!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;

}

int     SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function SYSTEM_CPU_INTR!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;

}

