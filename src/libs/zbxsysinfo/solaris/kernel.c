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

#ifdef HAVE_SYS_VAR_H
#   include <sys/var.h>
#endif

#include "common.h"
#include "sysinfo.h"


int	SYSTEM_KERNEL_MAXPROC(const char *cmd, const char *parameter, double  *value, const char *msg, int mlen_max)
{
    int result = SYSINFO_RET_FAIL;
    kstat_ctl_t *kc;
    kstat_t	*kt;
    struct var	*v;
    int		proc_count = 0;
    
    kc = kstat_open();
    if(kc)
    {
	kt = kstat_lookup(kc, "unix", 0, "var");
	if(kt)
	{
	    if(	(kt->ks_type == KSTAT_TYPE_RAW) &&
                (kstat_read(kc, kt, NULL) != -1)
	      )
            {
		v = (struct var *) kt->ks_data;

		/* int	v_proc;	    Max processes system wide */
		proc_count = v->v_proc;
		result = SYSINFO_RET_OK;
            }
        }
	kstat_close(kc);
    }

    *value = (double) proc_count;
    
    return result;
}

