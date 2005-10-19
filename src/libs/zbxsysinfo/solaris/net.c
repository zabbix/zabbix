/*
 * ** ZABBIX
 * ** Copyright (C) 2000-2005 SIA Zabbix
 * **
 * ** This program is free software; you can redistribute it and/or modify
 * ** it under the terms of the GNU General Public License as published by
 * ** the Free Software Foundation; either version 2 of the License, or
 * ** (at your option) any later version.
 * **
 * ** This program is distributed in the hope that it will be useful,
 * ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * ** GNU General Public License for more details.
 * **
 * ** You should have received a copy of the GNU General Public License
 * ** along with this program; if not, write to the Free Software
 * ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * **/

#include "config.h"

#include <errno.h>

#include <string.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/stat.h>
#include <sys/types.h>

#ifdef HAVE_PWD_H
#	include <pwd.h>
#endif

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

#ifdef HAVE_SYS_PROC_H
#   include <sys/proc.h>
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

/* Solaris */
#ifdef HAVE_SYS_SWAP_H
	#include <sys/swap.h>
#endif

#ifdef HAVE_SYS_SYSCALL_H
	#include <sys/syscall.h>
#endif

#ifdef HAVE_KSTAT_H
	#include <kstat.h>
#endif

#ifdef HAVE_LDAP
	#include <ldap.h>
#endif

#include "common.h"
#include "sysinfo.h"

/*
#define FDI(f, m) fprintf(stderr, "DEBUG INFO: " f "\n" , m) // show debug info to stderr
#define SDI(m) FDI("%s", m) // string info
#define IDI(i) FDI("%i", i) // integer info
*/

typedef union value_overlay
{
  union 
  {
    uint64_t ui64;
    uint32_t ui32;
  } u;
} VALUE_OVERLAY;

typedef struct rbytes_data
{
  hrtime_t clock;
  VALUE_OVERLAY rbytes;
} RBYTES_DATA;

typedef struct obytes_data
{
  hrtime_t clock;
  VALUE_OVERLAY obytes;
} OBYTES_DATA;

typedef struct rpackets_data
{
  hrtime_t clock;
  VALUE_OVERLAY rpackets;
} RPACKETS_DATA;

typedef struct opackets_data
{
  hrtime_t clock;
  VALUE_OVERLAY opackets;
} OPACKETS_DATA;

typedef struct network_data
{
  struct network_data *next;
  char 		*name;
  RBYTES_DATA 	rb;
  OBYTES_DATA 	ob;
  RPACKETS_DATA rp;
  OPACKETS_DATA op;
} NETWORK_DATA;

static NETWORK_DATA *interfaces;

static NETWORK_DATA *get_net_data_record(const char *device)
{
    NETWORK_DATA *p;

    p = interfaces;

    while ((p) && (strcmp(p->name, device) != 0))
	p = p->next;  

    if (p == (NETWORK_DATA *) NULL)
    {
	p = (NETWORK_DATA *) calloc(1, sizeof(NETWORK_DATA));
	if (p)
	{
	    p->name = strdup(device);
	    if (p->name)
            {
		p->next = interfaces;
		interfaces = p;
            }
	    else
	    {
		free(p);
		p = NULL;
            }
        }
    }
  return p;
}

static int get_named_field(
    const char *name, 
    const char *field,
    kstat_named_t *returned_data,
    hrtime_t *snaptime
    )
{
    int result = SYSINFO_RET_FAIL;
    
    kstat_ctl_t	  *kc;
    kstat_t       *kp;
    kstat_named_t *kn;
		  
    kc = kstat_open();
    if (kc)
    {
	kp = kstat_lookup(kc, NULL, -1, name);
        if ((kp) && (kstat_read(kc, kp, 0) != -1))
	{
	    kn = (kstat_named_t*) kstat_data_lookup(kp, field);
	    if(kn)
	    {
            	*snaptime = kp->ks_snaptime;
            	*returned_data = *kn;
            	result = SYSINFO_RET_OK;
	    }
        }
	kstat_close(kc);
    }
    return result;
}

int	NET_IN_LOAD(const char *cmd, const char *parameter,double  *value)
{
    int result = SYSINFO_RET_FAIL;
    NETWORK_DATA *p;
    kstat_named_t kn;
    hrtime_t snaptime;
    int interval_seconds;

    p = get_net_data_record(parameter);
    if(p)
    {
	result = get_named_field(parameter, "rbytes64", &kn, &snaptime);
	if (result == SYSINFO_RET_OK)
	{
	    interval_seconds = (snaptime - p->rb.clock) / 1000000000;
	    *value = (double) (kn.value.ui64 - p->rb.rbytes.u.ui64) / interval_seconds;
	    p->rb.rbytes.u.ui64 = kn.value.ui64;
	    p->rb.clock = snaptime;
        }
	else
	{
            result = get_named_field(parameter, "rbytes", &kn, &snaptime);
            if (result == SYSINFO_RET_OK)
	    {
		interval_seconds = (snaptime - p->rb.clock) / 1000000000;
	        *value = (double) (kn.value.ui32 - p->rb.rbytes.u.ui32) / interval_seconds;
                p->rb.rbytes.u.ui32 = kn.value.ui32;
                p->rb.clock = snaptime;
            }
        }
    }
  return result;
}

int	NET_IN_PACKETS(const char *cmd, const char *parameter,double  *value)
{
    int result = SYSINFO_RET_FAIL;
    NETWORK_DATA *p;
    int interval_seconds;
    kstat_named_t kn;
    hrtime_t snaptime;
    
    p = get_net_data_record(parameter);
    if(p)
    {
	result = get_named_field(parameter, "ipackets64", &kn, &snaptime);
        if (result == SYSINFO_RET_OK)
	{
	    interval_seconds = (snaptime - p->rp.clock) / 1000000000;
	    *value = (double) (kn.value.ui64 - p->rp.rpackets.u.ui64) / interval_seconds;
            p->rp.rpackets.u.ui64 = kn.value.ui64;
            p->rp.clock = snaptime;
        }
        else
        {
            result = get_named_field(parameter, "ipacket", &kn, &snaptime);
            if (result == SYSINFO_RET_OK)
	    {
		interval_seconds = (snaptime - p->rp.clock) / 1000000000;
	        *value = (double) (kn.value.ui32 - p->rp.rpackets.u.ui32) / interval_seconds;
                p->rp.rpackets.u.ui32 = kn.value.ui32;
                p->rp.clock = snaptime;
            }
        }
    }
  return result;
}

int	NET_IN_ERRORS(const char *cmd, const char *parameter,double  *value)
{
    int result;
    kstat_named_t kn;
    hrtime_t snaptime;
  
    result = get_named_field(parameter, "ierrors", &kn, &snaptime);

    if (result == SYSINFO_RET_OK)
	*value = (double) kn.value.ui32;

  return result;
}

int	NET_OUT_LOAD(const char *cmd, const char *parameter,double  *value)
{
    int result = SYSINFO_RET_FAIL;
    NETWORK_DATA *p;
    kstat_named_t kn;
    hrtime_t snaptime;
    int interval_seconds;

    p = get_net_data_record(parameter);
    if (p)
    {
        result = get_named_field(parameter, "obytes64", &kn, &snaptime);

        if (result == SYSINFO_RET_OK)
	{
	    interval_seconds = (snaptime - p->ob.clock) / 1000000000;
	    *value = (double) (kn.value.ui64 - p->ob.obytes.u.ui64) / interval_seconds;
            p->ob.obytes.u.ui64 = kn.value.ui64;
            p->ob.clock = snaptime;
        }
        else
        {
	    result = get_named_field(parameter, "obytes", &kn, &snaptime);
            if (result == SYSINFO_RET_OK)
	    {
		interval_seconds = (snaptime - p->ob.clock) / 1000000000;
	        *value = (double) (kn.value.ui32 - p->ob.obytes.u.ui32) / interval_seconds;
                p->ob.obytes.u.ui32 = kn.value.ui32;
                p->ob.clock = snaptime;
            }
        }
    }
    return result;
}

int	NET_OUT_PACKETS(const char *cmd, const char *parameter,double  *value)
{
    int result = SYSINFO_RET_FAIL;
    NETWORK_DATA *p;
    kstat_named_t kn;
    hrtime_t snaptime;
    int interval_seconds;

    p = get_net_data_record(parameter);
    if (p)
    {
	result = get_named_field(parameter, "opackets64", &kn, &snaptime);
        if (result == SYSINFO_RET_OK)
	{
	    interval_seconds = (snaptime - p->op.clock) / 1000000000;
	    *value = (double) (kn.value.ui64 - p->op.opackets.u.ui64) / interval_seconds;
            p->op.opackets.u.ui64 = kn.value.ui64;
            p->op.clock = snaptime;
        }
	else
	{
	    result = get_named_field(parameter, "opacket", &kn, &snaptime);
            if (result == SYSINFO_RET_OK)
	    {
		interval_seconds = (snaptime - p->op.clock) / 1000000000;
	        *value = (double) (kn.value.ui32 - p->op.opackets.u.ui32) / interval_seconds;
                p->op.opackets.u.ui32 = kn.value.ui32;
                p->op.clock = snaptime;
            }
        }
    }
  return result;
}

int	NET_OUT_ERRORS(const char *cmd, const char *parameter,double  *value)
{
    int result;
    kstat_named_t kn;
    hrtime_t snaptime;
  
    result = get_named_field(parameter, "oerrors", &kn, &snaptime);

    if (result == SYSINFO_RET_OK)
	*value = (double) kn.value.ui32;

    return result;
}

int	NET_COLLISIONS(const char *cmd, const char *parameter,double  *value)
{
    int result;
    kstat_named_t kn;
    hrtime_t snaptime;
  
    result = get_named_field(parameter, "collisions", &kn, &snaptime);

    if (result == SYSINFO_RET_OK)
	*value = (double) kn.value.ui32;

    return result;
}

int	NET_TCP_LISTEN(const char *cmd, const char *parameter,double  *value)
{
  char command[MAX_STRING_LEN];
  
  memset(command, '\0', sizeof(command));

  snprintf(command, sizeof(command)-1, "netstat -an | grep '*.%s' | wc -l", parameter);
   
  return EXECUTE(NULL, command, value);
}

