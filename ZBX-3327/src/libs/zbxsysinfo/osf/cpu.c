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

static int	SYSTEM_CPU_IDLE1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return EXECUTE_DBL(cmd, "iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF))}'", flags, result);
}

static int	SYSTEM_CPU_SYS1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return EXECUTE_DBL(cmd, "iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-1))}'", flags, result);
}

static int	SYSTEM_CPU_NICE1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return EXECUTE_DBL(cmd, "iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-2))}'", flags, result);
}

static int	SYSTEM_CPU_USER1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return EXECUTE_DBL(cmd, "iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-3))}'", flags, result);
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	TYPE_MODE_FUNCTION fl[] =
	{
		{"idle",	"avg1" ,	SYSTEM_CPU_IDLE1},
		{"nice",	"avg1" ,	SYSTEM_CPU_NICE1},
		{"user",	"avg1" ,	SYSTEM_CPU_USER1},
		{"system",	"avg1" ,	SYSTEM_CPU_SYS1},
		{0,		0,		0}
	};

	char cpuname[MAX_STRING_LEN];
	char type[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

        if(num_param(param) > 3)
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

	for(i=0; fl[i].type!=0; i++)
		if(strncmp(type, fl[i].type, MAX_STRING_LEN)==0)
			if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
				return (fl[i].function)(cmd, param, flags, result);

	return SYSINFO_RET_FAIL;
}

static int	SYSTEM_CPU_LOAD1(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return EXECUTE_DBL(cmd, "uptime | awk '{printf(\"%s\", $(NF))}' | sed 's/[ ,]//g'", flags, result);
}

static int	SYSTEM_CPU_LOAD5(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return EXECUTE_DBL(cmd, "uptime | awk '{printf(\"%s\", $(NF-1))}' | sed 's/[ ,]//g'", flags, result);
}

static int	SYSTEM_CPU_LOAD15(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return EXECUTE_DBL(cmd, "uptime | awk '{printf(\"%s\", $(NF-2))}' | sed 's/[ ,]//g'", flags, result);
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

	char cpuname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

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
		zbx_snprintf(mode, sizeof(mode), "avg1");
	}
	for(i=0; fl[i].mode!=0; i++)
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
			return (fl[i].function)(cmd, param, flags, result);

	return SYSINFO_RET_FAIL;
}
