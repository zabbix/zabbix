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

int	VFS_FS_INODE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define FS_FNCLIST struct fs_fnclist_s
FS_FNCLIST
{
	char *mode;
	int (*function)();
};

	FS_FNCLIST fl[] = 
	{
		{"free" ,	VFS_FS_INODE_FREE},
		{"total" ,	VFS_FS_INODE_TOTAL},
		{"used",	VFS_FS_INODE_USED},
		{"pfree" ,	VFS_FS_INODE_PFREE},
		{"pused" ,	VFS_FS_INODE_PUSED},
		{0,		0}
	};

	char fsname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;
	
        assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));
	
        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, fsname, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }
	
	if(get_param(param, 2, mode, MAX_STRING_LEN) != 0)
        {
                mode[0] = '\0';
        }
        if(mode[0] == '\0')
	{
		/* default parameter */
		sprintf(mode, "total");
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

int	VFS_FS_INODE_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char 	mountPoint[MAX_STRING_LEN];
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;
#else
	struct statfs   s;
#endif
	
	assert(result);

	memset(result, 0, sizeof(AGENT_RESULT));

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }	

#ifdef HAVE_SYS_STATVFS_H
	if ( statvfs( (char *)mountPoint, &s) != 0 ) 
#else
	if ( statfs( (char *)mountPoint, &s) != 0 ) 
#endif
	{
		return	SYSINFO_RET_FAIL;
	}
        
	if ( s.f_blocks > 0 ) {
		result->type |= AR_DOUBLE;
#ifdef HAVE_SYS_STATVFS_H
		result->dbl = (double)(s.f_favail);
#else
		result->dbl = (double)(s.f_ffree);
#endif
		return SYSINFO_RET_OK;
	}
	return	SYSINFO_RET_FAIL;
}

int	VFS_FS_INODE_USED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char 	mountPoint[MAX_STRING_LEN];
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;
#else
	struct statfs   s;
#endif
	
	assert(result);

	memset(result, 0, sizeof(AGENT_RESULT));

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }	

#ifdef HAVE_SYS_STATVFS_H
	if ( statvfs( (char *)mountPoint, &s) != 0 ) 
#else
	if ( statfs( (char *)mountPoint, &s) != 0 ) 
#endif
	{
		return	SYSINFO_RET_FAIL;
	}
        
	if ( s.f_blocks > 0 ) {
		result->type |= AR_DOUBLE;
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;
		result->dbl = (double)(s.f_files - s.f_favail);
#else
		result->dbl = (double)(s.f_files - s.f_ffree);
#endif
		return SYSINFO_RET_OK;
	}
	return	SYSINFO_RET_FAIL;
}

int	VFS_FS_INODE_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char 	mountPoint[MAX_STRING_LEN];
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;
#else
	struct statfs   s;
#endif
	
	assert(result);

	memset(result, 0, sizeof(AGENT_RESULT));

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }	

#ifdef HAVE_SYS_STATVFS_H
	if ( statvfs( (char *)mountPoint, &s) != 0 ) 
#else
	if ( statfs( (char *)mountPoint, &s) != 0 ) 
#endif
	{
		return	SYSINFO_RET_FAIL;
	}
        
	if ( s.f_blocks > 0 ) {
		result->type |= AR_DOUBLE;
		result->dbl = (double)(s.f_files);
		return SYSINFO_RET_OK;
	}
	return	SYSINFO_RET_FAIL;
}

int	VFS_FS_INODE_PFREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	AGENT_RESULT	total_result;
	AGENT_RESULT	free_result;
	char 	mountPoint[MAX_STRING_LEN];
	
	assert(result);

	memset(result, 0, sizeof(AGENT_RESULT));

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }	

	if(SYSINFO_RET_OK != VFS_FS_INODE_TOTAL(cmd, mountPoint, flags, &total_result))
	{
		memcpy(result, &total_result, sizeof(AGENT_RESULT));
		return SYSINFO_RET_FAIL;
	}

	if(SYSINFO_RET_OK != VFS_FS_INODE_FREE(cmd, mountPoint, flags, &free_result))
	{
		memcpy(result, &free_result, sizeof(AGENT_RESULT));
		return SYSINFO_RET_FAIL;
	}

	if(total_result.dbl == 0)
	{
		return SYSINFO_RET_FAIL;
	}
	
	result->type |= AR_DOUBLE;
	result->dbl = 100.0 * free_result.dbl / total_result.dbl;
	return SYSINFO_RET_OK;
}

int	VFS_FS_INODE_PUSED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	AGENT_RESULT	total_result;
	AGENT_RESULT	used_result;
	char 	mountPoint[MAX_STRING_LEN];
	
	assert(result);

	memset(result, 0, sizeof(AGENT_RESULT));

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }	

	if(SYSINFO_RET_OK != VFS_FS_INODE_TOTAL(cmd, mountPoint, flags, &total_result))
	{
		memcpy(result, &total_result, sizeof(AGENT_RESULT));
		return SYSINFO_RET_FAIL;
	}

	if(SYSINFO_RET_OK != VFS_FS_INODE_USED(cmd, mountPoint, flags, &used_result))
	{
		memcpy(result, &used_result, sizeof(AGENT_RESULT));
		return SYSINFO_RET_FAIL;
	}

	if(total_result.dbl == 0)
	{
		return SYSINFO_RET_FAIL;
	}
	
	result->type |= AR_DOUBLE;
	result->dbl = 100.0 * used_result.dbl / total_result.dbl;
	return SYSINFO_RET_OK;
}

