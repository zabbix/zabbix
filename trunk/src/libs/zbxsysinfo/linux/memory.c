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

static int	VM_MEMORY_CACHED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	FILE	*f;
	char	*t;
	char	c[MAX_STRING_LEN];
	zbx_uint64_t	res = 0;

	assert(result);

        init_result(result);
		
	if(NULL == (f = fopen("/proc/meminfo","r") ))
	{
		return	SYSINFO_RET_FAIL;
	}
	while(NULL!=fgets(c,MAX_STRING_LEN,f))
	{
		if(strncmp(c,"Cached:",7) == 0)
		{
			t=(char *)strtok(c," ");
			t=(char *)strtok(NULL," ");
			sscanf(t, ZBX_FS_UI64, &res );
			t=(char *)strtok(NULL," ");
			
			if(strcasecmp(t,"kb"))		res <<= 10;
			else if(strcasecmp(t, "mb")) 	res <<= 20;
			else if(strcasecmp(t, "gb")) 	res <<= 30;
			else if(strcasecmp(t, "tb")) 	res <<= 40;

			break;
		}
	}
	zbx_fclose(f);

	SET_UI64_RESULT(result, res);
	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_BUFFERS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	assert(result);

        init_result(result);
		
	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.bufferram * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.bufferram);
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
}

static int	VM_MEMORY_SHARED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	assert(result);

        init_result(result);
		
	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.sharedram * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.sharedram);
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
}

static int	VM_MEMORY_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	assert(result);

        init_result(result);
	
	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.totalram * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.totalram);
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
}

static int	VM_MEMORY_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	assert(result);

        init_result(result);
		
	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.freeram * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.freeram);
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
}

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define MEM_FNCLIST struct mem_fnclist_s
MEM_FNCLIST
{
	char *mode;
	int (*function)();
};

	MEM_FNCLIST fl[] = 
	{
		{"free",	VM_MEMORY_FREE},
		{"shared",	VM_MEMORY_SHARED},
		{"total",	VM_MEMORY_TOTAL},
		{"buffers",	VM_MEMORY_BUFFERS},
		{"cached",	VM_MEMORY_CACHED},
		{0,	0}
	};
        char    mode[MAX_STRING_LEN];
	int i;

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
		zbx_snprintf(mode, sizeof(mode), "total");
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

