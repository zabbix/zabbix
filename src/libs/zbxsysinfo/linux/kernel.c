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

int	KERNEL_MAXFILES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int ret = SYSINFO_RET_FAIL;
	char line[MAX_STRING_LEN];

	zbx_uint64_t value = 0;
	
	FILE 	*f;

	assert(result);

        init_result(result);	

	if(NULL != ( f = fopen("/proc/sys/fs/file-max","r") ))
	{
		if(fgets(line,MAX_STRING_LEN,f) != NULL);
		{
			if(sscanf(line,ZBX_FS_UI64 "\n", &value) == 1)
			{
				SET_UI64_RESULT(result, value);
				ret = SYSINFO_RET_OK;
			}
		}
		zbx_fclose(f);
	}

	return ret;
}

int	KERNEL_MAXPROC(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_FUNCTION_SYSCTL_KERN_MAXPROC
	int	mib[2],len;
	int	maxproc;

	assert(result);

        init_result(result);	
	
	mib[0]=CTL_KERN;
	mib[1]=KERN_MAXPROC;

	len=sizeof(maxproc);

	if(sysctl(mib,2,&maxproc,(size_t *)&len,NULL,0) != 0)
	{
		return	SYSINFO_RET_FAIL;
/*		printf("Errno [%m]");*/
	}

	SET_UI64_RESULT(result, maxproc);
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int     OLD_KERNEL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char    key[MAX_STRING_LEN];
        int     ret;

        assert(result);

        init_result(result);	

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, key, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

        if(strcmp(key,"maxfiles") == 0)
        {
                ret = KERNEL_MAXFILES(cmd, param, flags, result);
        }
        else if(strcmp(key,"maxproc") == 0)
        {
                ret = KERNEL_MAXPROC(cmd, param, flags, result);
        }
        else
        {
                ret = SYSINFO_RET_FAIL;
        }

        return ret;
}

