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

int	SYSTEM_UPTIME(const char *cmd, const char *parameter,double  *value, const char *msg, int mlen_max)
{
#ifdef HAVE_SYSINFO_UPTIME
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
		*value=(double)info.uptime;
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#else
#ifdef HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME
	int	mib[2],len;
	struct timeval	uptime;
	int	now;

	mib[0]=CTL_KERN;
	mib[1]=KERN_BOOTTIME;

	len=sizeof(uptime);

	if(sysctl(mib,2,&uptime,(size_t *)&len,NULL,0) != 0)
	{
		return	SYSINFO_RET_FAIL;
/*		printf("Errno [%m]\n");*/
	}

	now=time(NULL);

	*value=(double)(now-uptime.tv_sec);
	return SYSINFO_RET_OK;
#else
/* Solaris */
#ifdef HAVE_KSTAT_H
	kstat_ctl_t   *kc;
	kstat_t       *kp;
	kstat_named_t *kn;

	long          hz;
	long          secs;

	hz = sysconf(_SC_CLK_TCK);

	/* open kstat */
	kc = kstat_open();
	if (0 == kc)
	{
		return SYSINFO_RET_FAIL;
	}

	/* read uptime counter */
	kp = kstat_lookup(kc, "unix", 0, "system_misc");
	if (0 == kp)
	{
		kstat_close(kc);
		return SYSINFO_RET_FAIL;
	}

	if(-1 == kstat_read(kc, kp, 0))
	{
		kstat_close(kc);
		return SYSINFO_RET_FAIL;
	}
	kn = (kstat_named_t*)kstat_data_lookup(kp, "clk_intr");
	secs = kn->value.ul / hz;

	/* close kstat */
	kstat_close(kc);
	*value=(double)secs;
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
#endif
#endif
}
