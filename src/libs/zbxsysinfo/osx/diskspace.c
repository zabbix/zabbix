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

/* Definitions of uint32_t under OS/X */
#ifdef HAVE_STDINT_H
	#include <stdint.h>
#endif
#ifdef HAVE_STRINGS_H
	#include <strings.h>
#endif
#ifdef HAVE_FCNTL_H
	#include <fcntl.h>
#endif
#ifdef HAVE_DIRENT_H
	#include <dirent.h>
#endif
/* Linux */
#ifdef HAVE_SYS_VFS_H
	#include <sys/vfs.h>
#endif
#ifdef HAVE_SYS_SYSINFO_H
	#include <sys/sysinfo.h>
#endif
/* Solaris */
#ifdef HAVE_SYS_STATVFS_H
	#include <sys/statvfs.h>
#endif
/* Solaris */
#ifdef HAVE_SYS_PROCFS_H
/* This is needed to access the correct procfs.h definitions */
	#define _STRUCTURED_PROC 1
	#include <sys/procfs.h>
#endif
#ifdef HAVE_SYS_LOADAVG_H
	#include <sys/loadavg.h>
#endif
#ifdef HAVE_SYS_SOCKET_H
	#include <sys/socket.h>
#endif
#ifdef HAVE_NETINET_IN_H
	#include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_INET_H
	#include <arpa/inet.h>
#endif
/* OpenBSD/Solaris */
#ifdef HAVE_SYS_PARAM_H
	#include <sys/param.h>
#endif

#ifdef HAVE_SYS_MOUNT_H
	#include <sys/mount.h>
#endif

/* HP-UX */
#ifdef HAVE_SYS_PSTAT_H
	#include <sys/pstat.h>
#endif

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Solaris */
#ifdef HAVE_SYS_SWAP_H
	#include <sys/swap.h>
#endif

/* FreeBSD */
#ifdef HAVE_SYS_SYSCTL_H
	#include <sys/sysctl.h>
#endif

/* Solaris */
#ifdef HAVE_SYS_SYSCALL_H
	#include <sys/syscall.h>
#endif

/* FreeBSD */
#ifdef HAVE_VM_VM_PARAM_H
	#include <vm/vm_param.h>
#endif
/* FreeBSD */
#ifdef HAVE_SYS_VMMETER_H
	#include <sys/vmmeter.h>
#endif
/* FreeBSD */
#ifdef HAVE_SYS_TIME_H
	#include <sys/time.h>
#endif

#ifdef HAVE_MACH_HOST_INFO_H
	#include <mach/host_info.h>
#endif
#ifdef HAVE_MACH_MACH_HOST_H
	#include <mach/mach_host.h>
#endif


#ifdef HAVE_KSTAT_H
	#include <kstat.h>
#endif

#ifdef HAVE_LDAP
	#include <ldap.h>
#endif

#include "common.h"
#include "sysinfo.h"

int	VFS_FS_PUSED(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
	double	total;
	double	used;

	if(SYSINFO_RET_OK != VFS_FS_TOTAL(cmd, mountPoint, &total, msg, mlen_max))
	{
		return SYSINFO_RET_FAIL;
	}

	if(SYSINFO_RET_OK != VFS_FS_USED(cmd, mountPoint, &used, msg, mlen_max))
	{
		return SYSINFO_RET_FAIL;
	}

	if(total == 0)
	{
		return SYSINFO_RET_FAIL;
	}

	*value = 100*used/total;
	return SYSINFO_RET_OK;
}

int	VFS_FS_PFREE(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
	double	total;
	double	free;

	if(SYSINFO_RET_OK != VFS_FS_TOTAL(cmd, mountPoint, &total, msg, mlen_max))
	{
		return SYSINFO_RET_FAIL;
	}

	if(SYSINFO_RET_OK != VFS_FS_FREE(cmd, mountPoint, &free, msg, mlen_max))
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

int	VFS_FS_FREE(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

/*	return  s.f_bavail * (s.f_bsize / 1024.0);*/
	*value=s.f_bavail * (s.f_frsize / 1024.0);
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
		*value=s.f_bavail * (s.f_bsize / 1024.0);
		return SYSINFO_RET_OK;

	}

	return	SYSINFO_RET_FAIL;
#endif
}

int	VFS_FS_USED(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

/*	return  (s.f_blocks-s.f_bavail) * (s.f_bsize / 1024.0);*/
	*value=(s.f_blocks-s.f_bavail) * (s.f_frsize / 1024.0);
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
		*value=blocks_used * (s.f_bsize / 1024.0);
		return SYSINFO_RET_OK;

	}

	return	SYSINFO_RET_FAIL;
#endif
}

int	VFS_FS_TOTAL(const char *cmd, const char *mountPoint,double  *value, const char *msg, int mlen_max)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;

	if ( statvfs( (char *)mountPoint, &s) != 0 )
	{
		return  SYSINFO_RET_FAIL;
	}

/*	return  s.f_blocks * (s.f_bsize / 1024.0);*/
	*value= s.f_blocks * (s.f_frsize / 1024.0);
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
		*value=s.f_blocks * (s.f_bsize / 1024.0);
		return SYSINFO_RET_OK;

	}

	return	SYSINFO_RET_FAIL;
#endif
}
