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

/*
static int get_disk_stats(const char *device, struct diskstats *result)
{
	int ret = SYSINFO_RET_FAIL;
	int mib[2];
	int drive_count;
	size_t l; 
	struct diskstats *stats;
	int i;

	mib[0] = CTL_HW;
	mib[1] = HW_DISKCOUNT;

	l = sizeof(drive_count);

	if (sysctl(mib, 2, &drive_count, &l, NULL, 0) == 0 ) 
	{
		l = (drive_count * sizeof(struct diskstats));
		stats = calloc(drive_count, l);
		if (stats)
		{
			mib[0] = CTL_HW;
			mib[1] = HW_DISKSTATS;
 
			if (sysctl(mib, 2, stats, &l, NULL, 0) == 0)
			{
				for (i = 0; i < drive_count; i++)
				{
					if (strcmp(device, stats[i].ds_name) == 0)
					{
						memmove(result, &stats[i], sizeof(struct diskstats));
						ret = SYSINFO_RET_OK;
						break;
					}
				}
			}

			free(stats);
		}
	}
	return ret;
}
*/

static int	VFS_DEV_READ_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
/*	char devname[MAX_STRING_LEN];
	struct diskstats ds;*/
	int ret = SYSINFO_RET_FAIL;
        
	assert(result);

        init_result(result);
/*	
        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
	if(get_disk_stats(devname, &ds) == SYSINFO_RET_OK)
	{
		SET_UI64_RESULT(result,  ds.ds_rbytes);
		ret = SYSINFO_RET_OK;
	}
*/
	return ret;
}

static int	VFS_DEV_READ_OPERATIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
/*	char devname[MAX_STRING_LEN];
	struct diskstats ds;*/
	int ret = SYSINFO_RET_FAIL;
        
	assert(result);

        init_result(result);
/*	
        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
	if(get_disk_stats(devname, &ds) == SYSINFO_RET_OK)
	{
		SET_UI64_RESULT(result, ds.ds_rxfer);
		ret = SYSINFO_RET_OK;
	}
*/	
	return ret;
}

static int	VFS_DEV_WRITE_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
/*	char devname[MAX_STRING_LEN];
	struct diskstats ds;*/
	int ret = SYSINFO_RET_FAIL;
        
	assert(result);

        init_result(result);
/*	
        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
	if(get_disk_stats(devname, &ds) == SYSINFO_RET_OK)
	{
		SET_UI64_RESULT(result, ds.ds_wbytes);
		ret = SYSINFO_RET_OK;
	}
*/	
	return ret;
}

static int	VFS_DEV_WRITE_OPERATIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
/*	char devname[MAX_STRING_LEN];
	struct diskstats ds; */
	int ret = SYSINFO_RET_FAIL;
/*       
	assert(result);

        init_result(result);
	
        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
	if(get_disk_stats(devname, &ds) == SYSINFO_RET_OK)
	{
		SET_UI64_RESULT(result, ds.ds_wxfer);
		ret = SYSINFO_RET_OK;
	}
*/	
	return ret;
}

int	VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define DEV_FNCLIST struct dev_fnclist_s
DEV_FNCLIST
{
	char *mode;
	int (*function)();
};

	DEV_FNCLIST fl[] = 
	{
		{"bytes",	VFS_DEV_WRITE_BYTES},
		{"operations",	VFS_DEV_WRITE_OPERATIONS},
		{0,		0}
	};

	char devname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;
	
        assert(result);

        init_result(result);
	
        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, sizeof(devname)) != 0)
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
		zbx_snprintf(mode, sizeof(mode), "%s", fl[0].mode);
	}
	
	for(i=0; fl[i].mode!=0; i++)
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, devname, flags, result);
		}
	}
	
	return SYSINFO_RET_FAIL;
}

int	VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define DEV_FNCLIST struct dev_fnclist_s
DEV_FNCLIST
{
	char *mode;
	int (*function)();
};

	DEV_FNCLIST fl[] = 
	{
		{"bytes",	VFS_DEV_READ_BYTES},
		{"operations",	VFS_DEV_READ_OPERATIONS},
		{0,		0}
	};

	char devname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;
	
        assert(result);

        init_result(result);
	
        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, devname, sizeof(devname)) != 0)
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
		zbx_snprintf(mode, sizeof(mode), "%s", fl[0].mode);
	}
	
	for(i=0; fl[i].mode!=0; i++)
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, devname, flags, result);
		}
	}
	
	return SYSINFO_RET_FAIL;
}

static int	DISK_IO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",2,2, flags, result);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

static int	DISK_RIO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",3,2, flags, result);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

static int	DISK_WIO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",4,2, flags, result);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

static int	DISK_RBLK(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",5,2, flags, result);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

static int	DISK_WBLK(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef	HAVE_PROC
	return	getPROC("/proc/stat",6,2, flags, result);
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int	OLD_IO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char    key[MAX_STRING_LEN];
	int 	ret;

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

	if(strcmp(key,"disk_io") == 0)
	{
		ret = DISK_IO(cmd, param, flags, result);
	}
	else if(strcmp(key,"disk_rio") == 0)
	{
		ret = DISK_RIO(cmd, param, flags, result);
	}
	else if(strcmp(key,"disk_wio") == 0)
	{
		ret = DISK_WIO(cmd, param, flags, result);
	}
    	else if(strcmp(key,"disk_rblk") == 0)
	{
		ret = DISK_RBLK(cmd, param, flags, result);
	}
    	else if(strcmp(key,"disk_wblk") == 0)
	{
		ret = DISK_WBLK(cmd, param, flags, result);
	}
	else
	{
		ret = SYSINFO_RET_FAIL;
	}
    
	return ret;
}

