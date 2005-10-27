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

int	VFS_FS_INODE_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;
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

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

	result->type |= AR_DOUBLE;
	result->dbl = (double)s.f_favail;
	return SYSINFO_RET_OK;
#else
	struct statfs   s;
	long            blocks_used;
	long            blocks_percent_used;
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

	if ( statfs( (char *)mountPoint, &s) != 0 ) 
	{
		return	SYSINFO_RET_FAIL;
	}
        
	if ( s.f_blocks > 0 ) {
		blocks_used = s.f_blocks - s.f_bfree;
		blocks_percent_used = (long)
		(blocks_used * 100.0 / (blocks_used + s.f_bavail) + 0.5);

/*		printf(
		"%7.0f %7.0f  %7.0f  %5ld%%   %s\n"
		,s.f_blocks * (s.f_bsize / 1024.0)
		,(s.f_blocks - s.f_bfree)  * (s.f_bsize / 1024.0)
		,s.f_bavail * (s.f_bsize / 1024.0)
		,blocks_percent_used
		,mountPoint);
*/
		result->type |= AR_DOUBLE;
		result->dbl = (double)(s.f_ffree);
		return SYSINFO_RET_OK;

	}
	return	SYSINFO_RET_FAIL;
#endif
}

int	VFS_FS_INODE_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;
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

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

	result->type |= AR_DOUBLE;
	result->dbl = (double)(s.f_files);
	return SYSINFO_RET_OK;
#else
	struct statfs   s;
	long            blocks_used;
	long            blocks_percent_used;
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

	if ( statfs( (char *)mountPoint, &s) != 0 ) 
	{
		return	SYSINFO_RET_FAIL;
	}
        
	if ( s.f_blocks > 0 ) {
		blocks_used = s.f_blocks - s.f_bfree;
		blocks_percent_used = (long)
		(blocks_used * 100.0 / (blocks_used + s.f_bavail) + 0.5);

/*		printf(
		"%7.0f %7.0f  %7.0f  %5ld%%   %s\n"
		,s.f_blocks * (s.f_bsize / 1024.0)
		,(s.f_blocks - s.f_bfree)  * (s.f_bsize / 1024.0)
		,s.f_bavail * (s.f_bsize / 1024.0)
		,blocks_percent_used
		,mountPoint);
*/
		result->type |= AR_DOUBLE;
		result->dbl = (double)(s.f_files);
		return SYSINFO_RET_OK;

	}
	return	SYSINFO_RET_FAIL;
#endif
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

