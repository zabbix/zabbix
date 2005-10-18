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

/* AIX CPU */
#ifdef HAVE_KNLIST_H
	#include <knlist.h>
#endif

#ifdef HAVE_KSTAT_H
	#include <kstat.h>
#endif

#ifdef HAVE_LDAP
	#include <ldap.h>
#endif

#include "common.h"
#include "sysinfo.h"

int	SYSTEM_CPU_IDLE1(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[idle1]",value);
}

int	SYSTEM_CPU_IDLE5(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[idle5]",value);
}

int	SYSTEM_CPU_IDLE15(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[idle15]",value);
}

int	SYSTEM_CPU_NICE1(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[nice1]",value);
}

int	SYSTEM_CPU_NICE5(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[nice5]",value);
}
int	SYSTEM_CPU_NICE15(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[nice15]",value);
}

int	SYSTEM_CPU_USER1(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[user1]",value);
}

int	SYSTEM_CPU_USER5(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[user5]",value);
}

int	SYSTEM_CPU_USER15(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[user15]",value);
}

int	SYSTEM_CPU_SYS1(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[system1]",value);
}

int	SYSTEM_CPU_SYS5(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[system5]",value);
}

int	SYSTEM_CPU_SYS15(const char *cmd, const char *param,double  *value)
{
	return	get_stat("cpu[system15]",value);
}

static int get_cpu_data(unsigned long long *idle,
                        unsigned long long *system,
                        unsigned long long *user,
                        unsigned long long *iowait)
{
    kstat_ctl_t	*kc;
    kstat_t	*k;
    cpu_stat_t	*cpu;
       	
    int cpu_count = 0;
    
    *idle = 0LL;
    *system = 0LL;
    *user = 0LL;
    *iowait = 0LL;

    kc = kstat_open();
    if (kc)
    {
    	k = kc->kc_chain;
	while (k)
	{
	    if ((strncmp(k->ks_name, "cpu_stat", 8) == 0)
		&& (kstat_read(kc, k, NULL) != -1)
	    )
	    {
		cpu = (cpu_stat_t *) k->ks_data;

		*idle	+=  cpu->cpu_sysinfo.cpu[CPU_IDLE];
		*system +=  cpu->cpu_sysinfo.cpu[CPU_KERNEL];
		*iowait +=  cpu->cpu_sysinfo.cpu[CPU_WAIT];
		*user	+=  cpu->cpu_sysinfo.cpu[CPU_USER];

		cpu_count += 1;
  	    }
	    k = k->ks_next;
        }
	kstat_close(kc);
    }
    return cpu_count;
}

#define CPU_I 0
#define CPU_U 1
#define CPU_K 2
#define CPU_W 3

int	SYSTEM_CPU_UTILIZATION(const char *cmd, const char *param,double  *value)
{
    unsigned long long cpu_val[4];
    unsigned long long interval_size;

    char cpu_info[MAX_STRING_LEN];
    
    int info_id = 0;
    
    int result = SYSINFO_RET_FAIL;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, cpu_info, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }
    else
    {
        strscpy(cpu_info, "idle");
    }
    
    if(strcmp(cpu_info,"idle") == 0)
    {
        info_id = CPU_I;
    }
    else if(strcmp(cpu_info,"user") == 0)
    {
        info_id = CPU_U;
    }
    else if(strcmp(cpu_info,"kernel") == 0)
    {
        info_id = CPU_K;
    }
    else if(strcmp(cpu_info,"wait") == 0)
    {
	info_id = CPU_W;
    }
    else
    {
	return SYSINFO_RET_FAIL;
    }
    
    if (get_cpu_data(&cpu_val[CPU_I], &cpu_val[CPU_K], &cpu_val[CPU_U], &cpu_val[CPU_W]))
    {
        interval_size =	cpu_val[CPU_I] + cpu_val[CPU_K] + cpu_val[CPU_U] + cpu_val[CPU_W];
        
	if (interval_size > 0)
	{
	    *value = (cpu_val[info_id] * 100.0)/interval_size;

            result = SYSINFO_RET_OK;
        }
    }
    return result;
}

int	SYSTEM_CPU_LOAD1(const char *cmd, const char *parameter,double  *value)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		*value=load[0];
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

int	SYSTEM_CPU_LOAD5(const char *cmd, const char *parameter,double  *value)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		*value=load[1];
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
	       
int	SYSTEM_CPU_LOAD15(const char *cmd, const char *parameter,double  *value)
{
#ifdef HAVE_GETLOADAVG
	double	load[3];

	if(getloadavg(load, 3))
	{
		*value=load[2];	
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

int	SYSTEM_CPU_SWITCHES(const char *cmd, const char *parameter, double *value)
{
    kstat_ctl_t	    *kc;
    kstat_t	    *k;
    cpu_stat_t	    *cpu;
    
    int	    cpu_count = 0;
    double  swt_count = 0.0;
    
    kc = kstat_open();

    if(kc != NULL)
    {    
	k = kc->kc_chain;
  	while (k != NULL)
	{
	    if( (strncmp(k->ks_name, "cpu_stat", 8) == 0) &&
		(kstat_read(kc, k, NULL) != -1) )
	    {
		cpu = (cpu_stat_t*) k->ks_data;
		swt_count += (double) cpu->cpu_sysinfo.pswitch;
		cpu_count += 1;
  	    }
	    k = k->ks_next;
        }
	kstat_close(kc);
    }

    *value = swt_count;
    
    if(cpu_count == 0)
    {
	return SYSINFO_RET_FAIL;
    }

    return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_INTR(const char *cmd, const char *parameter, double *value)
{
    kstat_ctl_t	    *kc;
    kstat_t	    *k;
    cpu_stat_t	    *cpu;
    
    int	    cpu_count = 0;
    double  intr_count = 0.0;
    
    kc = kstat_open();

    if(kc != NULL)
    {    
	k = kc->kc_chain;
  	while (k != NULL)
	{
	    if( (strncmp(k->ks_name, "cpu_stat", 8) == 0) &&
		(kstat_read(kc, k, NULL) != -1) )
	    {
		cpu = (cpu_stat_t*) k->ks_data;
		intr_count += (double) cpu->cpu_sysinfo.intr;
		cpu_count += 1;
  	    }
	    k = k->ks_next;
        }
	kstat_close(kc);
    }

    *value = intr_count;
    
    if(cpu_count == 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
    return SYSINFO_RET_OK;
}

