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
	assert(result);

        init_result(result);

	return EXECUTE_INT(cmd,"vmstat -s | awk 'BEGIN{pages=0}{gsub(\"[()]\",\"\");if($4==\"pagesize\")pgsize=($6);if(($2==\"inactive\"||$2==\"active\"||$2==\"wired\")&&$3==\"pages\")pages+=$1}END{printf (pages*pgsize)}'", flags, result);
}

static int	VM_MEMORY_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
/*
	host_paging_info_data_t page_info;
	vm_size_t pagesize;
	mach_msg_type_number_t count;
	kern_return_t kret;
	int ret;

	assert(result);

        init_result(result);

	pagesize = 0;
	kret = host_page_size (mach_host_self(), &pagesize);

	count = HOST_VM_INFO_COUNT;
	kret = host_statistics (mach_host_self(), HOST_VM_INFO,
	(host_info_t)&page_info, &count);
	if (kret == KERN_SUCCESS)
	{
		double pw, pa, pi, pf, pu;

		pw = (double)page_info.wire_count*pagesize;
		pa = (double)page_info.active_count*pagesize;
		pi = (double)page_info.inactive_count*pagesize;
		pf = (double)page_info.free_count*pagesize;

		pu = pw+pa+pi;

		SET_UI64_RESULT(result, pf);
		ret = SYSINFO_RET_OK;
	}
	else
	{
		ret = SYSINFO_RET_FAIL;
	}
	return ret;
*/
	assert(result);

        init_result(result);

	return EXECUTE_INT(cmd, "vmstat -s | awk '{gsub(\"[()]\",\"\");if($4==\"pagesize\")pgsize=($6);if($2==\"free\"&&$3==\"pages\")pages=($1)}END{printf (pages*pgsize)}'", flags, result);
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
