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

#include "md5.h"

static int	get_sensor(const char *name, unsigned flags, AGENT_RESULT *result)
{
	DIR	*dir;
	struct	dirent *entries;
	struct	stat buf;
	char	filename[MAX_STRING_LEN];
	char	line[MAX_STRING_LEN];
	double	d1,d2,d3;

	FILE	*f;

        assert(result);

        init_result(result);	
	
	dir=opendir("/proc/sys/dev/sensors");
	if(NULL == dir)
	{
		return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		strscpy(filename,"/proc/sys/dev/sensors/");	
		strncat(filename,entries->d_name,MAX_STRING_LEN);
		strncat(filename,name,MAX_STRING_LEN);

		if(stat(filename,&buf)==0)
		{
			if(NULL == (f = fopen(filename,"r"))
			{
				continue;
			}
			fgets(line,MAX_STRING_LEN,f);
			zbx_fclose(f);

			if(sscanf(line,"%lf\t%lf\t%lf\n",&d1, &d2, &d3) == 3)
			{
				closedir(dir);
				SET_DBL_RESULT(result, d3);
				return  SYSINFO_RET_OK;
			}
			else
			{
				closedir(dir);
				return  SYSINFO_RET_FAIL;
			}
		}
	}
	closedir(dir);
	return	SYSINFO_RET_FAIL;
}

int     OLD_SENSOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
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

        if(strcmp(key,"temp1") == 0)
        {
                ret = get_sensor("temp1", flags, result);
        }
        else if(strcmp(key,"temp2") == 0)
        {
                ret = get_sensor("temp2", flags, result);
        }
        else if(strcmp(key,"temp3") == 0)
        {
                ret = get_sensor("temp3", flags, result);
        }
        else
        {
                ret = SYSINFO_RET_FAIL;
        }

        return ret;
}

