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

static int	VM_MEMORY_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int 	mib[2];
	size_t len;
	struct vmtotal v;
	int ret=SYSINFO_RET_FAIL;
	
	assert(result);

        init_result(result);
		
	len=sizeof(v);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	if(0==sysctl(mib,2,&v,&len,NULL,0))
	{
		SET_UI64_RESULT(result, (zbx_uint64_t)(v.t_rm+v.t_free) * (zbx_uint64_t)sysconf(_SC_PAGESIZE));
		ret=SYSINFO_RET_OK;
	}
	return ret;
}

static int	VM_MEMORY_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int 	mib[2];
	size_t len;
	struct vmtotal v;
	int ret=SYSINFO_RET_FAIL;
	
	assert(result);

        init_result(result);
		
	len=sizeof(v);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	if(0==sysctl(mib,2,&v,&len,NULL,0))
	{
		SET_UI64_RESULT(result, (zbx_uint64_t)v.t_free * (zbx_uint64_t)sysconf(_SC_PAGESIZE));
		ret=SYSINFO_RET_OK;
	}
	return ret;
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
		{"total",	VM_MEMORY_TOTAL},
		{0,	0}
	};
        char    mode[MAX_STRING_LEN];
	int 	ret = SYSINFO_RET_FAIL;
	int 	i;

        assert(result);

        init_result(result);

        if(num_param(param) > 1)
        {
                ret = SYSINFO_RET_FAIL;
        }
	else
	{
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
				ret = (fl[i].function)(cmd, param, flags, result);
			}
		}
	}
	return ret;
}

