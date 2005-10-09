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

int	VM_MEMORY_CACHED(const char *cmd, const char *parameter,double  *value)
{
#ifdef HAVE_PROC
/* Get CACHED memory in bytes */
/*	return getPROC("/proc/meminfo",8,2);*/
/* It does not work for both 2.4 and 2.6 */
/*	return getPROC("/proc/meminfo",2,7);*/
	FILE	*f;
	char	*t;
	char	c[MAX_STRING_LEN];
	double	result = SYSINFO_RET_FAIL;

	f=fopen("/proc/meminfo","r");
	if(NULL == f)
	{
		return	SYSINFO_RET_FAIL;
	}
	while(NULL!=fgets(c,MAX_STRING_LEN,f))
	{
		if(strncmp(c,"Cached:",7) == 0)
		{
			t=(char *)strtok(c," ");
			t=(char *)strtok(NULL," ");
			sscanf(t, "%lf", &result );
			break;
		}
	}
	fclose(f);

	*value=result;
	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

int	VM_MEMORY_BUFFERS(const char *cmd, const char *parameter,double  *value)
{
#ifdef HAVE_SYSINFO_BUFFERRAM
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		*value=(double)info.bufferram * (double)info.mem_unit;
#else
		*value=(double)info.bufferram;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int	VM_MEMORY_SHARED(const char *cmd, const char *parameter,double  *value)
{
#ifdef HAVE_SYSINFO_SHAREDRAM
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		*value=(double)info.sharedram * (double)info.mem_unit;
#else
		*value=(double)info.sharedram;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#else
#ifdef HAVE_SYS_VMMETER_VMTOTAL
	int mib[2],len;
	struct vmtotal v;

	len=sizeof(struct vmtotal);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	sysctl(mib,2,&v,&len,NULL,0);

	*value=(double)(v.t_armshr<<2);
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
#endif
}

int	VM_MEMORY_TOTAL(const char *cmd, const char *parameter,double  *value)
{
/* Solaris */
#ifdef HAVE_UNISTD_SYSCONF
	*value=(double)sysconf(_SC_PHYS_PAGES)*sysconf(_SC_PAGESIZE);
	return SYSINFO_RET_OK;
#else
#ifdef HAVE_SYS_PSTAT_H
	struct	pst_static pst;
	long	page;

	if(pstat_getstatic(&pst, sizeof(pst), (size_t)1, 0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	else
	{
		/* Get page size */	
		page = pst.page_size;
		/* Total physical memory in bytes */	
		*value=(double)page*pst.physical_memory;
		return SYSINFO_RET_OK;
	}
#else
#ifdef HAVE_SYSINFO_TOTALRAM
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		*value=(double)info.totalram * (double)info.mem_unit;
#else
		*value=(double)info.totalram;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
#else
#ifdef HAVE_SYS_VMMETER_VMTOTAL
	int mib[2],len;
	struct vmtotal v;

	len=sizeof(struct vmtotal);
	mib[0]=CTL_VM;
	mib[1]=VM_METER;

	sysctl(mib,2,&v,&len,NULL,0);

	*value=(double)(v.t_rm<<2);
	return SYSINFO_RET_OK;
#else
#ifdef HAVE_SYS_SYSCTL_H
	static int mib[] = { CTL_HW, HW_PHYSMEM };
	size_t len;
	unsigned int memory;
	int ret;
	
	len=sizeof(memory);

	if(0==sysctl(mib,2,&memory,&len,NULL,0))
	{
		*value=(double)memory;
		ret=SYSINFO_RET_OK;
	}
	else
	{
		ret=SYSINFO_RET_FAIL;
	}
	return ret;
#else
	return	SYSINFO_RET_FAIL;
#endif
#endif
#endif
#endif
#endif
}

int	VM_MEMORY_FREE(const char *cmd, const char *parameter,double  *value)
{
	return	SYSINFO_RET_FAIL;
}
