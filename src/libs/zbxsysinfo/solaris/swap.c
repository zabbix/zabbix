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

#include "md5.h"


#if 1

/* Solaris. */

#ifndef HAVE_SYSINFO_FREESWAP
#ifdef HAVE_SYS_SWAP_SWAPTABLE
void get_swapinfo(double *total, double *fr)
{
	register int cnt, i, page_size;
/* Support for >2Gb */
/*	register int t, f; */
	double	t, f;
	struct swaptable *swt;
	struct swapent *ste;
	static char path[256];

	/* get total number of swap entries */
	cnt = swapctl(SC_GETNSWP, 0);

	/* allocate enough space to hold count + n swapents */
	swt = (struct swaptable *)malloc(sizeof(int) +
		cnt * sizeof(struct swapent));

	if (swt == NULL)
	{
		*total = 0;
		*fr = 0;
		return;
	}
	swt->swt_n = cnt;

/* fill in ste_path pointers: we don't care about the paths, so we
 point them all to the same buffer */
	ste = &(swt->swt_ent[0]);
	i = cnt;
	while (--i >= 0)
	{
		ste++->ste_path = path;
	}

	/* grab all swap info */
	swapctl(SC_LIST, swt);

	/* walk thru the structs and sum up the fields */
	t = f = 0;
	ste = &(swt->swt_ent[0]);
	i = cnt;
	while (--i >= 0)
	{
		/* dont count slots being deleted */
		if (!(ste->ste_flags & ST_INDEL) &&
		!(ste->ste_flags & ST_DOINGDEL))
		{
			t += ste->ste_pages;
			f += ste->ste_free;
		}
		ste++;
	}

	page_size=getpagesize();

	/* fill in the results */
	*total = page_size*t;
	*fr = page_size*f;
	free(swt);
}
#endif
#endif

int	SYSTEM_SWAP_FREE(const char *cmd, const char *parameter,double  *value)
{
#ifdef HAVE_SYSINFO_FREESWAP
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		*value=(double)info.freeswap * (double)info.mem_unit;
#else
		*value=(double)info.freeswap;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
/* Solaris */
#else
#ifdef HAVE_SYS_SWAP_SWAPTABLE
	double swaptotal,swapfree;

	get_swapinfo(&swaptotal,&swapfree);

	*value=swapfree;
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
#endif
}



int	SYSTEM_SWAP_TOTAL(const char *cmd, const char *parameter,double  *value)
{
#ifdef HAVE_SYSINFO_TOTALSWAP
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		*value=(double)info.totalswap * (double)info.mem_unit;
#else
		*value=(double)info.totalswap;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
/* Solaris */
#else
#ifdef HAVE_SYS_SWAP_SWAPTABLE
	double swaptotal,swapfree;

	get_swapinfo(&swaptotal,&swapfree);

	*value=(double)swaptotal;
	return SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif
#endif
}
#endif

#if 0
static int get_swap_data(
    uint64_t *resv,
    uint64_t *avail,
    uint64_t *free
    )
{
  kstat_ctl_t	*kc;
  kstat_t	*ksp;
  vminfo_t	*vm;

  uint64_t  oresv;
  uint64_t  ofree;
  uint64_t  oavail;

  int	result = SYSINFO_RET_FAIL;
  int	i;

  kc = kstat_open();
  if (kc)
    {
      ksp = kstat_lookup(kc, "unix", 0, "vminfo");
      if ((ksp) && (kstat_read(kc, ksp, NULL) != -1))
	{
           vm = (vminfo_t *) ksp->ks_data;
#if 0
	   *resv = vm->swap_resv;
           *free = vm->swap_free;
           *avail = vm->swap_avail;
                        
           result = SYSINFO_RET_OK;
#else
           oresv = vm->swap_resv;
           ofree = vm->swap_free;
           oavail = vm->swap_avail;

           for (i = 0; i < 12; i++)
              {
                 usleep(100000);
                 if (kstat_read(kc, ksp, NULL) != -1)
		   {
                      vm = (vminfo_t *) ksp->ks_data;
                      if ((oresv != vm->swap_resv) || (ofree != vm->swap_free) || (oavail != vm->swap_avail))
	                 {  
			    *resv = vm->swap_resv - oresv;
                            *free = vm->swap_free - ofree;
                            *avail = vm->swap_avail - oavail;
                          
                             result = SYSINFO_RET_OK;
			     break;
                         }
		   }
              }
#endif
        }
      kstat_close(kc);
    }
  return result;
}
	  
int	SYSTEM_SWAP_FREE(const char *cmd, const char *param, double  *value)
{
  int      result;
  uint64_t resv = 0;
  uint64_t avail =0;
  uint64_t free = 0;

  result = get_swap_data(&resv, &avail, &free);

  if (result == SYSINFO_RET_OK)
    {
      *value = free * sysconf(_SC_PAGESIZE);
    }
   return result;
}

int	SYSTEM_SWAP_TOTAL(const char *cmd, const char *param, double  *value)
{
  int      result;
  uint64_t resv = 0;
  uint64_t avail =0;
  uint64_t free = 0;
  uint64_t swap_total_bytes = 0;

  result = get_swap_data(&resv, &avail, &free);

  if (result == SYSINFO_RET_OK)
    {
      swap_total_bytes = (resv + avail) * sysconf(_SC_PAGESIZE);
      *value = (double) swap_total_bytes;
    }
   return result;
}
#endif 

#define	DO_SWP_IN	1
#define DO_PG_IN	2
#define	DO_SWP_OUT	3
#define DO_PG_OUT	4

static int	SYSTEM_SWAP(const char *cmd, const char *param, double *value)
{
    kstat_ctl_t	    *kc;
    kstat_t	    *k;
    cpu_stat_t	    *cpu;
    
    int	    cpu_count = 0;
    double  swapin= 0.0;
    
    int	    do_info;
    char    swp_info[MAX_STRING_LEN];
    
    if(num_param(param) > 1)
    {
        return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, swp_info, MAX_STRING_LEN) != 0)
    {
        return SYSINFO_RET_FAIL;
    }
    
    if(strcmp(swp_info,"swapin") == 0)
    {
        do_info = DO_SWP_IN;
    }
    else if(strcmp(swp_info,"pgswapin") == 0)
    {
        do_info = DO_PG_IN;
    }
    else if(strcmp(swp_info,"swapout") == 0)
    {
        do_info = DO_SWP_OUT;
    }
    else if(strcmp(swp_info,"pgswapout") == 0)
    {
        do_info = DO_PG_OUT;
    }
    else
    {
	return SYSINFO_RET_FAIL;
    }
    
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
		if(do_info ==  DO_SWP_IN)
		{
		   /* uint_t   swapin;	    	// swapins */
		   swapin += (double) cpu->cpu_vminfo.swapin;
		}
		else if(do_info ==  DO_PG_IN)
		{
		   /* uint_t   pgswapin;	// pages swapped in */
		   swapin += (double) cpu->cpu_vminfo.pgswapin;
		}
		else if(do_info ==  DO_SWP_OUT)
		{
		   /* uint_t   swapout;	    	// swapout */
		   swapin += (double) cpu->cpu_vminfo.swapin;
		}
		else if(do_info ==  DO_PG_OUT)
		{
		   /* uint_t   pgswapout;	// pages swapped out */
		   swapin += (double) cpu->cpu_vminfo.swapin;
		}
		cpu_count += 1;
  	    }
	    k = k->ks_next;
        }
	kstat_close(kc);
    }

    *value = swapin;
    
    if(cpu_count == 0)
    {
	return SYSINFO_RET_FAIL;
    }

    return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_IN_NUM(const char *cmd, const char *param, double *value)
{
	return SYSTEM_SWAP(cmd, "swapin", value);
}

int	SYSTEM_SWAP_IN_PAGES(const char *cmd, const char *param, double *value)
{
	return SYSTEM_SWAP(cmd, "pgswapin", value);
}

int	SYSTEM_SWAP_OUT_NUM(const char *cmd, const char *param, double *value)
{
	return SYSTEM_SWAP(cmd, "swapout", value);
}

int	SYSTEM_SWAP_OUT_PAGES(const char *cmd, const char *param, double *value)
{
	return SYSTEM_SWAP(cmd, "pgswapout", value);
}

