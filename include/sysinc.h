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

#ifndef ZABBIX_SYSINC_H
#define ZABBIX_SYSINC_H

#if !defined(WIN32)
#	include "config.h"
#endif

#include <stdio.h>
#include <stdlib.h>

#ifdef HAVE_ASSERT_H
#	include <assert.h>
#endif

#ifdef HAVE_ERRNO_H
#	include <errno.h>
#endif

#ifdef HAVE_CTYPE_H
#	include <ctype.h>
#endif

#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif

#ifdef HAVE_STRING_H
#	include <string.h>
#endif

#ifdef HAVE_STRINGS_H
#	include <strings.h>
#endif

#ifdef HAVE_SYS_TIME_H
#	include <sys/time.h>
#endif

#ifdef HAVE_LINUX_KERNEL_H
#	include <linux/kernel.h>
#endif

#ifdef HAVE_ARPA_NAMESER_H
#	include <arpa/nameser.h>
#endif

#ifdef HAVE_DIRENT_H
#	include <dirent.h>
#endif

#ifdef HAVE_FCNTL_H
#	include <fcntl.h>
#endif

#ifdef HAVE_KNLIST_H
#	include <knlist.h>
#endif

#ifdef HAVE_KSTAT_H
#	include <kstat.h>
#endif

#ifdef HAVE_LDAP_H
	#include <ldap.h>
#endif

#ifdef HAVE_MACH_HOST_INFO_H
#	include <mach/host_info.h>
#endif

#ifdef HAVE_MACH_MACH_HOST_H
#	include <mach/mach_host.h>
#endif

#ifdef HAVE_MTENT_H
#	include <mtent.h>
#endif

#ifdef HAVE_NETDB_H
#	include <netdb.h>
#endif

#ifdef HAVE_NETINET_IN_H
#	include <netinet/in.h>
#endif

#ifdef HAVE_PWD_H
#	include <pwd.h>
#endif

#ifdef HAVE_SIGNAL_H
#	include <signal.h>
#endif

#ifdef HAVE_STDINT_H
#	include <stdint.h>
#endif

#ifdef HAVE_SYS_LOADAVG_H
#	include <sys/loadavg.h>
#endif

#ifdef HAVE_SYS_PARAM_H
#	include <sys/param.h>
#endif

#ifdef HAVE_SYS_PROC_H
#	include <sys/proc.h>
#endif

#ifdef HAVE_SYS_PROCFS_H
/* This is needed to access the correct procfs.h definitions */
#	define _STRUCTURED_PROC 1
#	include <sys/procfs.h>
#endif

#ifdef HAVE_SYS_PSTAT_H
#	include <sys/pstat.h>
#endif

#ifdef HAVE_SYS_DK_H
#	include <sys/dk.h>
#endif

#ifdef HAVE_RESOLV_H
#	include <resolv.h>
#endif

#ifdef HAVE_SYS_DISK_H
#	include <sys/disk.h>
#endif

#ifdef HAVE_SYS_DKSTAT_H
#	include <sys/dkstat.h>
#endif

#ifdef HAVE_SYS_SOCKET_H
#	include <sys/socket.h>
#endif

#ifdef HAVE_SYS_STAT_H
#	include <sys/stat.h>
#endif

#ifdef HAVE_SYS_STATVFS_H
#	include <sys/statvfs.h>
#endif

#ifdef HAVE_SYS_SWAP_H
#	include <sys/swap.h>
#endif

#ifdef HAVE_SYS_SYSCALL_H
#	include <sys/syscall.h>
#endif

#ifdef HAVE_SYS_SYSCTL_H
#	include <sys/sysctl.h>
#endif

#ifdef HAVE_SYS_SYSINFO_H
#	include <sys/sysinfo.h>
#endif

#ifdef HAVE_SYS_SYSMACROS_H
#	include <sys/sysmacros.h>
#endif

#ifdef HAVE_SYS_VAR_H
#	include <sys/var.h>
#endif

#ifdef HAVE_SYS_VFS_H
#	include <sys/vfs.h>
#endif

#ifdef HAVE_SYS_VMMETER_H
#	include <sys/vmmeter.h>
#endif

#ifdef HAVE_NLIST_H
#	include <nlist.h>
#endif

#ifdef HAVE_NET_IF_H
#	include <net/if.h>
#endif

#ifdef HAVE_KVM_H
#	include <kvm.h>
#endif

#ifdef HAVE_SYSLOG_H
#	include <syslog.h>
#endif

#ifdef HAVE_TIME_H
#	include <time.h>
#endif

#ifdef HAVE_UNISTD_H
#	include <unistd.h>
#endif

#ifdef HAVE_LBER_H
#	include <lber.h>
#endif

#ifdef HAVE_GETOPT_H
#	ifdef HAVE_GETOPT_LONG
#		define _GNU_SOURCE
#		include <getopt.h>
#	endif
#endif

#ifdef HAVE_VM_VM_PARAM_H
#	include <vm/vm_param.h>
#endif

#ifdef HAVE_ARPA_INET_H
#	include <arpa/inet.h>
#endif

#ifdef HAVE_SYS_MOUNT_H
#	include <sys/mount.h>
#endif

#ifdef HAVE_PROCINFO_H
#	undef T_NULL /* to solve definition conflict */
#	include <procinfo.h>
#endif

#endif
