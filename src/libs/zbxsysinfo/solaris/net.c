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

#if 0
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
#endif

static int get_kstat_named_field(
    const char *name, 
    const char *field,
    kstat_named_t *returned_data
    )
{
    int result = SYSINFO_RET_FAIL;
    
    kstat_ctl_t	  *kc;
    kstat_t       *kp;
    kstat_named_t *kn;
		  
    kc = kstat_open();
    if (kc)
    {
	kp = kstat_lookup(kc, NULL, -1, (char*) name);
        if ((kp) && (kstat_read(kc, kp, 0) != -1))
	{
	    kn = (kstat_named_t*) kstat_data_lookup(kp, (char*) field);
	    if(kn)
	    {
            	*returned_data = *kn;
            	result = SYSINFO_RET_OK;
	    }
        }
	kstat_close(kc);
    }
    return result;
}

int	NET_IF_IN_BYTES(const char *cmd, const char *param,double  *value)
{
    kstat_named_t kn;
    char    interface[MAX_STRING_LEN];
    int	    result;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, interface, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
    result = get_kstat_named_field(interface, "rbytes64", &kn);
    if (result == SYSINFO_RET_OK)
    {
	*value = (double)kn.value.ui64;
    }
    else
    {
	result = get_kstat_named_field(interface, "rbytes", &kn);
	*value = (double)kn.value.ui32;
    }
    
    return result;
}

int	NET_IF_IN_PACKETS(const char *cmd, const char *param, double  *value)
{
    kstat_named_t kn;
    char    interface[MAX_STRING_LEN];
    int	    result;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, interface, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
    result = get_kstat_named_field(interface, "ipackets64", &kn);
    if (result == SYSINFO_RET_OK)
    {
	*value = (double)kn.value.ui64;
    }
    else
    {
	result = get_kstat_named_field(interface, "ipackets", &kn);
	*value = (double)kn.value.ui32;
    }
    
    return result;
}

int	NET_IF_IN_ERRORS(const char *cmd, const char *param, double  *value)
{
    kstat_named_t kn;
    char    interface[MAX_STRING_LEN];
    int	    result;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, interface, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
    result = get_kstat_named_field(interface, "ierrors", &kn);

    *value = (double)kn.value.ui32;
    
    return result;
}

int	NET_IF_OUT_BYTES(const char *cmd, const char *param,double  *value)
{
    kstat_named_t kn;
    char    interface[MAX_STRING_LEN];
    int	    result;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, interface, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
    result = get_kstat_named_field(interface, "obytes64", &kn);
    if (result == SYSINFO_RET_OK)
    {
	*value = (double)kn.value.ui64;
    }
    else
    {
	result = get_kstat_named_field(interface, "obytes", &kn);
	*value = (double)kn.value.ui32;
    }
    
    return result;
}

int	NET_IF_OUT_PACKETS(const char *cmd, const char *param,double  *value)
{
    kstat_named_t kn;
    char    interface[MAX_STRING_LEN];
    int	    result;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, interface, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
    result = get_kstat_named_field(interface, "opackets64", &kn);
    if (result == SYSINFO_RET_OK)
    {
	*value = (double)kn.value.ui64;
    }
    else
    {
	result = get_kstat_named_field(interface, "opackets", &kn);
	*value = (double)kn.value.ui32;
    }
    
    return result;
}

int	NET_IF_OUT_ERRORS(const char *cmd, const char *param,double  *value)
{
    kstat_named_t kn;
    char    interface[MAX_STRING_LEN];
    int	    result;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, interface, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
    result = get_kstat_named_field(interface, "oerrors", &kn);

    *value = (double)kn.value.ui32;
    
    return result;
}

int	NET_IF_COLLISIONS(const char *cmd, const char *param,double  *value)
{
    kstat_named_t kn;
    char    interface[MAX_STRING_LEN];
    int	    result;

    if(num_param(param) > 1)
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, interface, MAX_STRING_LEN) != 0)
    {
	return SYSINFO_RET_FAIL;
    }
    
    result = get_kstat_named_field(interface, "collisions", &kn);

    *value = (double)kn.value.ui32;
    
    return result;
}

int	NET_TCP_LISTEN(const char *cmd, const char *param,double  *value)
{
  char command[MAX_STRING_LEN];
  
  memset(command, '\0', sizeof(command));

  snprintf(command, sizeof(command)-1, "netstat -an | grep '*.%s' | wc -l", param);
   
  return EXECUTE(NULL, command, value);
}

