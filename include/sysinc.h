/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_SYSINC_H
#define ZABBIX_SYSINC_H

#include "config.h"

#ifdef HAVE_STDIO_H
#	include <stdio.h>
#endif

#ifdef HAVE_STDLIB_H
#	include <stdlib.h>
#endif

#ifdef HAVE_ASSERT_H
#	include <assert.h>
#endif

#ifdef HAVE_ERRNO_H
#	include <errno.h>
#endif

#ifdef HAVE_WINSOCK2_H
#	include <winsock2.h>
#endif

#ifdef HAVE_WS2TCPIP_H
#	include <ws2tcpip.h>
#endif

#ifdef HAVE_WSPIAPI_H
#	include "Wspiapi.h"
#endif

#ifdef HAVE_IPHLPAPI_H
#	include <Iphlpapi.h>
#endif

#ifdef HAVE_WINDOWS_H
/* to speed build process and reduce size of the Win32 header files */
#	define WIN32_LEAN_AND_MEAN	1
#	include <windows.h>
#endif

#ifdef HAVE_PROCESS_H
#	include <process.h>
#endif

#ifdef HAVE_CONIO_H
#	include <conio.h>
#endif

#ifdef HAVE_PDH_H
#	include <pdh.h>
#	ifndef PDH_MAX_COUNTER_NAME
#		define PDH_MAX_COUNTER_NAME	1024	/* maximum counter name length */
#	endif
#	ifndef PDH_MAX_COUNTER_PATH
#		define PDH_MAX_COUNTER_PATH	2048	/* maximum full counter path length */
#	endif
#endif

#ifdef HAVE_PDHMSG_H
#	include <pdhmsg.h>
#endif

#ifdef HAVE_PSAPI_H
#	include <psapi.h>
#endif

#ifdef HAVE_STDARG_H
#	include <stdarg.h>
#endif

#ifdef HAVE_CTYPE_H
#	include <ctype.h>
#endif

#ifdef HAVE_GRP_H
#	include <grp.h>
#endif

#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif

#ifdef HAVE_SYS_SYSTEMCFG_H
#	include <sys/systemcfg.h>
#endif

#ifdef HAVE_INTTYPES_H
#	include <inttypes.h>
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

#ifdef HAVE_SYS_TIMES_H
#	include <sys/times.h>
#endif

#ifdef HAVE_LINUX_KERNEL_H
#	include <linux/kernel.h>
#endif

#ifdef HAVE_ARPA_NAMESER_H
#	ifdef MAC_OS_X
#		define BIND_8_COMPAT 1
#	endif
#	include <arpa/nameser.h>
#endif

#ifdef HAVE_DIRENT_H
#	include <dirent.h>
#endif

#ifdef HAVE_SYS_DKSTAT_H
#	include <sys/dkstat.h>
#endif

#ifdef HAVE_DEVSTAT_H
#	include <devstat.h>
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

#ifdef HAVE_LDAP
#	include <ldap.h>
#endif

#ifdef HAVE_WINLDAP_H
#	undef SEC_I_INCOMPLETE_CREDENTIALS
#	undef SEC_E_INCOMPLETE_CREDENTIALS
#	undef SEC_I_RENEGOTIATE
#	include <Winldap.h>
#endif

#ifdef HAVE_WINBER_H
#	include <Winber.h>
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

#ifdef HAVE_SYS_WAIT_H
#	include <sys/wait.h>
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

#ifdef HAVE_SYS_PSTAT_H
#	include <sys/pstat.h>
#endif

#ifdef HAVE_PTHREAD_H
#	include <pthread.h>
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

#ifdef HAVE_LIBPERFSTAT
#	include <libperfstat.h>
#endif

#ifdef HAVE_SYS_RESOURCE_H
#	include <sys/resource.h>
#endif

#ifdef HAVE_NLIST_H
#	include <nlist.h>
#endif

#ifdef HAVE_NET_IF_H
#	include <net/if.h>
#endif

#ifdef HAVE_NET_IF_MIB_H
#	include <net/if_mib.h>
#endif

#ifdef HAVE_SYS_USER_H
#	include <sys/user.h>
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

#ifdef HAVE_SYS_IPC_H
#	include <sys/ipc.h>
#endif

#ifdef HAVE_SYS_SEM_H
#	include <sys/sem.h>
#endif

#ifdef HAVE_SYS_SHM_H
#	include <sys/shm.h>
#endif

#ifdef HAVE_SYS_FILE_H
#	include <sys/file.h>
#endif

#ifdef HAVE_MATH_H
#	include <math.h>
#endif

#ifdef HAVE_REGEX_H
#	include <regex.h>
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

#ifdef HAVE_SYS_TIMEB_H
#	include <sys/timeb.h>
#endif

#ifdef HAVE_PROCINFO_H
#	undef T_NULL /* to solve definition conflict */
#	include <procinfo.h>
#endif

#ifdef HAVE_LIBCURL
#	include <curl/curl.h>
#	if !defined(HAVE_FUNCTION_CURL_EASY_ESCAPE)
#		define curl_easy_escape(handle, string, length) curl_escape(string, length)
#	endif
#	if 0x071004 >= LIBCURL_VERSION_NUM	/* version 7.16.4 */
#		define CURLOPT_KEYPASSWD	CURLOPT_SSLKEYPASSWD
#	endif
#	if 0x071400 <= LIBCURL_VERSION_NUM	/* version 7.20.0 */
#		define HAVE_SMTP_AUTHENTICATION	1
#	endif
#	define ZBX_CURLOPT_MAXREDIRS	10L
#endif

/* Net-SNMP is used */
#ifdef HAVE_NETSNMP
#	define SNMP_NO_DEBUGGING		/* disabling debugging messages from Net-SNMP library */
#	include <net-snmp/net-snmp-config.h>
#	include <net-snmp/net-snmp-includes.h>
#endif

/* LIBXML2 is used */
#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/tree.h>
#	include <libxml/xpath.h>
#endif

/* Required for advanced sigaction */
#ifdef HAVE_SYS_UCONTEXT_H
#	include <sys/ucontext.h>
#endif

#ifdef HAVE_ICONV
#	include <iconv.h>
#endif

#ifdef HAVE_SSH2
#	include <libssh2.h>
#endif

#ifdef HAVE_IO_H
#	include <io.h>
#endif

#ifdef HAVE_SYS_MNTTAB_H
#	include <sys/mnttab.h>
#endif

#ifdef HAVE_MNTENT_H
#	include <mntent.h>
#endif

#ifdef HAVE_DLFCN_H
#	include <dlfcn.h>
#endif

#endif
