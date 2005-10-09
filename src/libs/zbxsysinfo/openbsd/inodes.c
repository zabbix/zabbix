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

#include <errno.h>

#include <string.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/stat.h>
#include <sys/types.h>

/* Linux */
#ifdef HAVE_SYS_VFS_H
	#include <sys/vfs.h>
#endif
/* Solaris */
#ifdef HAVE_SYS_STATVFS_H
	#include <sys/statvfs.h>
#endif

/* FreeBSD/MacOS/OpenBSD/Solaris */
#ifdef HAVE_SYS_PARAM_H
        #include <sys/param.h>
#endif

#ifdef HAVE_SYS_MOUNT_H
        #include <sys/mount.h>
#endif

#include "common.h"
#include "sysinfo.h"

#include "md5.h"

int	VFS_FS_INODE_FREE(const char *cmd, const char *mountPoint,double  *value)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

	*value=s.f_favail;
	return SYSINFO_RET_OK;
#else
	struct statfs   s;
	long            blocks_used;
	long            blocks_percent_used;

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
		*value=s.f_ffree;
		return SYSINFO_RET_OK;

	}
	return	SYSINFO_RET_FAIL;
#endif
}

int	VFS_FS_INODE_TOTAL(const char *cmd, const char *mountPoint,double  *value)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

	*value=s.f_files;
	return SYSINFO_RET_OK;
#else
	struct statfs   s;
	long            blocks_used;
	long            blocks_percent_used;

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
		*value=s.f_files;
		return SYSINFO_RET_OK;

	}
	return	SYSINFO_RET_FAIL;
#endif
}

int	VFS_FS_INODE_PFREE(const char *cmd, const char *mountPoint,double  *value)
{
	double	total;
	double	free;

	if(SYSINFO_RET_OK != VFS_FS_INODE_TOTAL(cmd, mountPoint, &total))
	{
		return SYSINFO_RET_FAIL;
	}

	if(SYSINFO_RET_OK != VFS_FS_INODE_FREE(cmd, mountPoint, &free))
	{
		return SYSINFO_RET_FAIL;
	}

	if(total == 0)
	{
		return SYSINFO_RET_FAIL;
	}

	*value = 100*free/total;
	return SYSINFO_RET_OK;
}
