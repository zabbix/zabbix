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

int	get_fs_size_stat(char *fs, double *total, double *free, double *usage)
{
#ifdef HAVE_SYS_STATVFS_H
        struct statvfs   s;
#else
        struct statfs   s;
#endif

        assert(fs);

#ifdef HAVE_SYS_STATVFS_H
        if ( statvfs( fs, &s) != 0 )
#else
        if ( statfs( fs, &s) != 0 )
#endif
        {
                return  SYSINFO_RET_FAIL;
        }

#ifdef HAVE_SYS_STATVFS_H
        if(total)
                (*total) = (double)(s.f_blocks * (s.f_frsize / 1024.0));
        if(free)
                (*free)  = (double)(s.f_bavail * (s.f_frsize / 1024.0));
        if(usage)
                (*usage) = (double)((s.f_blocks - s.f_bavail) * (s.f_frsize / 1024.0));
#else
        if(total)
                (*total) = (double)(s.f_blocks * (s.f_bsize / 1024.0));
        if(free)
                (*free)  = (double)(s.f_bfree * (s.f_bsize / 1024.0));
        if(usage)
                (*usage) = (double)((s.f_blocks - s.f_bfree) * (s.f_bsize / 1024.0));
#endif
        return SYSINFO_RET_OK;
}

static int	VFS_FS_USED(const char *cmd, char *param, unsigned flags, AGENT_RESULT *result)
{
/*        char    mountPoint[MAX_STRING_LEN];*/
        double  value = 0;

        assert(result);

        init_result(result);

/*        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
                return SYSINFO_RET_FAIL;*/

        if(get_fs_size_stat(param, NULL, NULL, &value) != SYSINFO_RET_OK)
                return  SYSINFO_RET_FAIL;

        SET_UI64_RESULT(result, value);

        return SYSINFO_RET_OK;
}

static int	VFS_FS_FREE(const char *cmd, char *param, unsigned flags, AGENT_RESULT *result)
{
/*        char    mountPoint[MAX_STRING_LEN];*/
        double  value = 0;

        assert(result);

        init_result(result);

/*        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
                return SYSINFO_RET_FAIL;*/

        if(get_fs_size_stat(param, NULL, &value, NULL) != SYSINFO_RET_OK)
                return  SYSINFO_RET_FAIL;

        SET_UI64_RESULT(result, value);

        return SYSINFO_RET_OK;
}

static int	VFS_FS_TOTAL(const char *cmd, char *param, unsigned flags, AGENT_RESULT *result)
{
/*        char    mountPoint[MAX_STRING_LEN];*/
        double  value = 0;

        assert(result);

        init_result(result);

/*        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }*/

        if(get_fs_size_stat(param, &value, NULL, NULL) != SYSINFO_RET_OK)
                return  SYSINFO_RET_FAIL;

        SET_UI64_RESULT(result, value);

        return SYSINFO_RET_OK;

}

static int	VFS_FS_PFREE(const char *cmd, char *param, unsigned flags, AGENT_RESULT *result)
{
/*        char    mountPoint[MAX_STRING_LEN];*/
        double  tot_val = 0;
        double  free_val = 0;

        assert(result);

        init_result(result);

/*        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
                return SYSINFO_RET_FAIL;*/

        if(get_fs_size_stat(param, &tot_val, &free_val, NULL) != SYSINFO_RET_OK)
                return  SYSINFO_RET_FAIL;

        SET_DBL_RESULT(result, (100.0 * free_val) / tot_val);

        return SYSINFO_RET_OK;
}

static int	VFS_FS_PUSED(const char *cmd, char *param, unsigned flags, AGENT_RESULT *result)
{
/*        char    mountPoint[MAX_STRING_LEN];*/
        double  tot_val = 0;
        double  usg_val = 0;

        assert(result);

        init_result(result);

/*        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
                return SYSINFO_RET_FAIL;*/

        if(get_fs_size_stat(param, &tot_val, NULL, &usg_val) != SYSINFO_RET_OK)
                return  SYSINFO_RET_FAIL;

        SET_DBL_RESULT(result, (100.0 * usg_val) / tot_val);

        return SYSINFO_RET_OK;
}

int	VFS_FS_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define FS_FNCLIST struct fs_fnclist_s
FS_FNCLIST
{
	char *mode;
	int (*function)();
};

	FS_FNCLIST fl[] = 
	{
		{"free" ,	VFS_FS_FREE},
		{"total" ,	VFS_FS_TOTAL},
		{"used",	VFS_FS_USED},
		{"pfree" ,	VFS_FS_PFREE},
		{"pused" ,	VFS_FS_PUSED},
		{0,		0}
	};

	char fsname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;
	
        assert(result);

        init_result(result);
	
        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, fsname, sizeof(fsname)) != 0)
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
		zbx_snprintf(mode, sizeof(mode), "total");
	}
	
	for(i=0; fl[i].mode!=0; i++)
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, fsname, flags, result);
		}
	}
	
	return SYSINFO_RET_FAIL;
}

