dnl
dnl Zabbix
dnl Copyright (C) 2001-2014 Zabbix SIA
dnl
dnl This program is free software; you can redistribute it and/or modify
dnl it under the terms of the GNU General Public License as published by
dnl the Free Software Foundation; either version 2 of the License, or
dnl (at your option) any later version.
dnl
dnl This program is distributed in the hope that it will be useful,
dnl but WITHOUT ANY WARRANTY; without even the implied warranty of
dnl MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
dnl GNU General Public License for more details.
dnl
dnl You should have received a copy of the GNU General Public License
dnl along with this program; if not, write to the Free Software
dnl Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
dnl

dnl Process this file with autoconf to produce a configure script.

AC_INIT([Zabbix],[2.2.5rc1])
AC_CONFIG_SRCDIR(src/zabbix_server/server.c)
AM_INIT_AUTOMAKE([subdir-objects])

AC_MSG_NOTICE([Configuring $PACKAGE_NAME $PACKAGE_VERSION])

AC_PROG_MAKE_SET

AM_CONFIG_HEADER(include/config.h)

AC_CANONICAL_HOST

dnl *****************************************************************
dnl *                                                               *
dnl *                     Checks for programs                       *
dnl *                                                               *
dnl *****************************************************************

AC_PROG_CC
AM_PROG_CC_C_O

dnl *****************************************************************
dnl *                                                               *
dnl *                   Checks for header files                     *
dnl *                                                               *
dnl *****************************************************************

AC_HEADER_STDC
AC_CHECK_HEADERS(stdio.h stdlib.h string.h unistd.h netdb.h signal.h \
  syslog.h time.h errno.h sys/types.h sys/stat.h netinet/in.h \
  math.h sys/socket.h dirent.h ctype.h \
  mtent.h fcntl.h sys/param.h arpa/inet.h \
  sys/vfs.h sys/pstat.h sys/sysinfo.h sys/statvfs.h \
  sys/socket.h sys/loadavg.h arpa/inet.h \
  sys/vmmeter.h strings.h vm/vm_param.h \
  sys/time.h kstat.h sys/syscall.h sys/sysmacros.h sys/procfs.h \
  stdint.h mach/host_info.h mach/mach_host.h knlist.h pwd.h \
  sys/var.h arpa/nameser.h assert.h sys/dkstat.h sys/disk.h \
  nlist.h kvm.h linux/kernel.h procinfo.h sys/dk.h \
  sys/resource.h pthread.h windows.h process.h conio.h sys/wait.h regex.h \
  stdarg.h winsock2.h pdh.h psapi.h sys/sem.h sys/ipc.h sys/shm.h Winldap.h \
  sys/timeb.h Winber.h lber.h ws2tcpip.h inttypes.h sys/file.h grp.h \
  execinfo.h libperfstat.h sys/systemcfg.h sys/mnttab.h mntent.h sys/times.h \
  dlfcn.h sys/utsname.h)
AC_CHECK_HEADERS(resolv.h, [], [], [
#ifdef HAVE_SYS_TYPES_H
#  include <sys/types.h>
#endif
#ifdef HAVE_NETINET_IN_H
#  include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_NAMESER_H
#  include <arpa/nameser.h>
#endif
#ifdef HAVE_NETDB_H
#  include <netdb.h>
#endif
])
AC_CHECK_HEADERS(net/if.h net/if_mib.h, [], [], [
#include <stdio.h>
#ifdef STDC_HEADERS
#  include <stdlib.h>
#  include <stddef.h>
#else
#  ifdef HAVE_STDLIB_H
#    include <stdlib.h>
#  endif
#endif
#ifdef HAVE_SYS_TYPES_H
#  include <sys/types.h>
#endif
#ifdef HAVE_SYS_SOCKET_H
#  include <sys/socket.h>
#endif
/* for net/if_mib.h */
#ifdef HAVE_NET_IF_H
#  include <net/if.h>
#endif
])
AC_CHECK_HEADERS(sys/mount.h sys/proc.h sys/sysctl.h sys/user.h, [], [], [
#ifdef HAVE_SYS_TYPES_H
#  include <sys/types.h>
#endif
#ifdef HAVE_SYS_PARAM_H
#  include <sys/param.h>
#endif
])
AC_CHECK_HEADERS(sys/swap.h, [], [], [
#ifdef HAVE_SYS_PARAM_H
#  include <sys/param.h>
#endif
])
AC_CHECK_HEADERS(sys/ucontext.h, [], [], [
#ifdef HAVE_SIGNAL_H
#  include <signal.h>
#endif
])
AC_CHECK_HEADERS(devstat.h, [], [], [
#ifdef HAVE_SYS_DKSTAT_H
#  include <sys/dkstat.h>
#endif
])

dnl *****************************************************************
dnl *                                                               *
dnl *                     Checks for libraries                      *
dnl *                                                               *
dnl *****************************************************************

AC_SEARCH_LIBS(socket, socket)
AC_SEARCH_LIBS(kstat_open, kstat)
AC_SEARCH_LIBS(gethostbyname, nsl)
AC_SEARCH_LIBS(clock_gettime, rt)
AC_SEARCH_LIBS(dlopen, dl)

dnl AIX
AC_SEARCH_LIBS(perfstat_memory_total, perfstat, [AC_DEFINE([HAVE_LIBPERFSTAT], 1, [Define to 1 if you have the 'libperfstat' library (-lperfstat)])])
AC_SEARCH_LIBS(devstat_getdevs, devstat, [AC_DEFINE([HAVE_LIBDEVSTAT], 1, [Define to 1 if you have the 'libdevstat' library (-ldevstat)])])
AC_SEARCH_LIBS(getdevs, devstat, [AC_DEFINE([HAVE_LIBDEVSTAT], 1, [Define to 1 if you have the 'libdevstat' library (-ldevstat)])])

dnl on FreeBSD we have to link with -lexecinfo to get backtraces
AC_SEARCH_LIBS(backtrace_symbols, execinfo, [AC_DEFINE([HAVE_LIBEXECINFO], 1, [Define to 1 if you have the 'libexecinfo' library (-lexecinfo)])])

AC_CHECK_LIB(m, main)
AC_CHECK_LIB(kvm, main)

dnl check for DNS lookup functions
found_resolv="no"
LIBRESOLV_CHECK_CONFIG([no])
if test "x$found_resolv" != "xyes"; then
	AC_MSG_ERROR([Unable to do DNS lookups (libresolv check failed)])
fi
LIBS="${LIBS} ${RESOLV_LIBS}"

dnl *****************************************************************
dnl *                                                               *
dnl *          Checks for type definitions and structures           *
dnl *                                                               *
dnl *****************************************************************

dnl large file support
largefile=yes

dnl disable large file support on 32-bit Solaris as it's incompatible with procfs and swapctl
case "${host_os}" in
	solaris*)
		largefile=no
		;;
esac

if test "x$largefile" = "xyes"; then
	AC_SYS_LARGEFILE
fi

AC_C_CONST
AC_TYPE_PID_T

AC_MSG_CHECKING(for socklen_t)
AC_TRY_COMPILE([
#include <sys/types.h>
#include <unistd.h>
#include <sys/socket.h>
],[socklen_t s;],
AC_MSG_RESULT(yes),
[AC_DEFINE(socklen_t, int, [Define socklen_t type.])
AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for actual socklen_t parameter type in socket functions)
zbx_socklen_t=
for arg2 in "struct sockaddr" void; do
  for arg3 in socklen_t size_t int; do
    AC_TRY_COMPILE([
#ifdef HAVE_SYS_TYPES_H
#  include <sys/types.h>
#endif
#ifdef HAVE_SYS_SOCKET_H
#  include <sys/socket.h>
#endif
      extern int getpeername(int sockfd, $arg2 *addr, $arg3 *addrlen);
    ],[
      $arg3 addrlen;
      getpeername(0, 0, &addrlen);
    ],[
      zbx_socklen_t="$arg3"
      break 2
    ])
  done
done
if test "x$zbx_socklen_t" != "x"; then
  AC_MSG_RESULT($zbx_socklen_t)
  AC_DEFINE_UNQUOTED(ZBX_SOCKLEN_T, $zbx_socklen_t, [Define actual socklen_t parameter type in socket functions.])
else
  AC_MSG_RESULT(leaving undefined)
fi

AC_MSG_CHECKING(for integer field name in union sigval of struct siginfo_t)
zbx_sival_int=
for field in sival_int sigval_int; do
  AC_TRY_COMPILE([
#ifdef HAVE_SIGNAL_H
#  include <signal.h>
#endif
  ],[
    siginfo_t siginfo;
    siginfo.si_value.$field = 0;
  ],[
    zbx_sival_int="$field"
    break
  ])
done
if test "x$zbx_sival_int" != "x"; then
  AC_MSG_RESULT($zbx_sival_int)
  AC_DEFINE_UNQUOTED(ZBX_SIVAL_INT, $zbx_sival_int, [Define integer field name in union 'sigval' of struct 'siginfo_t'])
else
  AC_MSG_ERROR(Unable to find integer field name in union sigval of struct siginfo_t)
fi

AC_MSG_CHECKING(for union semun)
AC_TRY_COMPILE(
[
#include <sys/types.h>
#include <sys/ipc.h>
#include <sys/sem.h>
],
[union semun foo;],
AC_DEFINE(HAVE_SEMUN, 1, [Define to 1 if union 'semun' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for struct swaptable in sys/swap.h)
AC_TRY_COMPILE(
[
#include <stdlib.h>
#include <sys/types.h>
#include <sys/syscall.h>
#include <sys/swap.h>

#ifndef NULL
#define NULL (void *)0
#endif
],
[
    register int cnt, i;
    register int t, f;
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
  return;
    }
    swt->swt_n = cnt;

    /* fill in ste_path pointers: we do not care about the paths, so we
point
       them all to the same buffer */
    ste = &(swt->swt_ent[0]);
    i = cnt;
    while (--i >= 0)
    {
  ste++->ste_path = path;
    }

    /* grab all swap info */
    swapctl(SC_LIST, swt);

    /* walk through the structs and sum up the fields */
    t = f = 0;
    ste = &(swt->swt_ent[0]);
    i = cnt;
    while (--i >= 0)
    {
  /* do not count slots being deleted */
  if (!(ste->ste_flags & ST_INDEL) &&
      !(ste->ste_flags & ST_DOINGDEL))
  {
      t += ste->ste_pages;
      f += ste->ste_free;
  } ste++;
    }

    /* fill in the results */
    free(swt);

],
AC_DEFINE(HAVE_SYS_SWAP_SWAPTABLE,1,[Define to 1 if struct 'swaptable' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for struct sensordev in sys/sensors.h)
AC_TRY_COMPILE([
#include <stdlib.h>
#include <sys/queue.h>
#include <sys/sensors.h>],
[struct sensordev sensordev;
sensordev.xname[0]='\0';
sensordev.maxnumt[0]=0;
],
AC_DEFINE(HAVE_SENSORDEV,1,[Define to 1 if struct 'sensordev' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for struct statvfs64 in sys/statvfs.h)
AC_TRY_COMPILE(
[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_SYS_STATVFS_H
#	include <sys/statvfs.h>
#endif
],
[
	struct statvfs64	s;
	s.f_frsize = s.f_blocks = s.f_bfree = s.f_bavail = 0;
	statvfs64("/", &s);
],
AC_DEFINE(HAVE_SYS_STATVFS64, 1, [Define to 1 if struct 'statvfs64' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field ss_family in struct sockaddr_storage)
AC_TRY_COMPILE([#include <sys/socket.h>],
[struct sockaddr_storage ss;
ss.ss_family = 0;
],
AC_DEFINE(HAVE_SOCKADDR_STORAGE_SS_FAMILY, 1, [Define to 1 if 'sockaddr_storage.ss_family' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field mem_unit in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.mem_unit=0;
],
AC_DEFINE(HAVE_SYSINFO_MEM_UNIT,1,[Define to 1 if 'sysinfo.mem_unit' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field freeswap in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.freeswap=0;
],
AC_DEFINE(HAVE_SYSINFO_FREESWAP,1,[Define to 1 if 'sysinfo.freeswap' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field totalswap in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.totalswap=0;
],
AC_DEFINE(HAVE_SYSINFO_TOTALSWAP,1,[Define to 1 if 'sysinfo.totalswap' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field totalram in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.totalram=0;
],
AC_DEFINE(HAVE_SYSINFO_TOTALRAM,1,[Define to 1 if 'sysinfo.totalram' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field sharedram in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.sharedram=0;
],
AC_DEFINE(HAVE_SYSINFO_SHAREDRAM,1,[Define to 1 if 'sysinfo.sharedram' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field bufferram in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.bufferram=0;
],
AC_DEFINE(HAVE_SYSINFO_BUFFERRAM,1,[Define to 1 if 'sysinfo.bufferram' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field freeram in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.freeram=0;
],
AC_DEFINE(HAVE_SYSINFO_FREERAM,1,[Define to 1 if 'sysinfo.freeram' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field uptime in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.uptime=0;
],
AC_DEFINE(HAVE_SYSINFO_UPTIME,1,[Define to 1 if 'sysinfo.uptime' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field procs in struct sysinfo)
AC_TRY_COMPILE([#include <sys/sysinfo.h>],
[struct sysinfo sysinfo;
sysinfo.procs=0;
],
AC_DEFINE(HAVE_SYSINFO_PROCS,1,[Define to 1 if 'sysinfo.procs' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for field tm_gmtoff in struct tm)
AC_TRY_COMPILE([
#ifdef HAVE_SYS_TIME_H
#include <sys/time.h>
#endif	/* HAVE_SYS_TIME_H */

#ifdef HAVE_TIME_H
#include <time.h>
#endif	/* HAVE_TIME_H */
],
[
	struct tm tm;
	tm.tm_gmtoff;
],
AC_DEFINE(HAVE_TM_TM_GMTOFF,1,[Define to 1 if 'tm.tm_gmtoff' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

dnl *****************************************************************
dnl *                                                               *
dnl *                     Checks for functions                      *
dnl *                                                               *
dnl *****************************************************************

AC_MSG_CHECKING(for function sysconf() in unistd.h)
AC_TRY_COMPILE(
[
#include <unistd.h>
],
[	int i;

	i=sysconf(_SC_PHYS_PAGES)*sysconf(_SC_PHYS_PAGES);
	i=sysconf(_SC_AVPHYS_PAGES)*sysconf(_SC_PHYS_PAGES);
],
AC_DEFINE(HAVE_UNISTD_SYSCONF,1,[Define to 1 if function 'sysconf' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function initgroups())
AC_TRY_LINK(
[
#include <sys/types.h>
#include <grp.h>
],
[
	char	*user = "zabbix";
	initgroups(user, 0);
],
AC_DEFINE(HAVE_FUNCTION_INITGROUPS,1,[Define to 1 if function 'initgroups' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for functions seteuid() and setegid())
AC_TRY_LINK(
[
#include <sys/types.h>
#include <unistd.h>
],
[
	seteuid(0);
	setegid(0);
],
AC_DEFINE(HAVE_FUNCTION_SETEUID,1,[Define to 1 if functions 'seteuid' and 'setegid' exist.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function setproctitle())
AC_TRY_LINK(
[
#include <sys/types.h>
#include <unistd.h>
],
[
	setproctitle("Test %d", 1);
],
AC_DEFINE(HAVE_FUNCTION_SETPROCTITLE,1,[Define to 1 if function 'setproctitle' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function sysctlbyname())
AC_TRY_LINK(
[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
],
[
	sysctlbyname("", 0, 0, 0, 0);
],
AC_DEFINE(HAVE_FUNCTION_SYSCTLBYNAME,1,[Define to 1 if 'sysctlbyname' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function sysctl (KERN_BOOTTIME))
AC_TRY_COMPILE(
[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
#include <unistd.h>
#include <time.h>
#include <sys/time.h>
],
[
	size_t		len;
	struct timeval	uptime;
        int		mib[2];

        mib[0] = CTL_KERN;
        mib[1] = KERN_BOOTTIME;

        len = sizeof(uptime);
        sysctl(mib, 2, &uptime, &len, 0, 0);
],
AC_DEFINE(HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME,1,[Define to 1 if 'KERN_BOOTTIME' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function sysctl (HW_NCPU))
AC_TRY_COMPILE(
[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
],
[
	size_t	len;
	int	mib[2], ncpu;

	mib[0] = CTL_HW;
	mib[1] = HW_NCPU;

	len = sizeof(ncpu);
	sysctl(mib, 2, &ncpu, &len, 0, 0);
],
AC_DEFINE(HAVE_FUNCTION_SYSCTL_HW_NCPU,1,[Define to 1 if 'HW_NCPU' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function sysctl (KERN_MAXFILES))
AC_TRY_COMPILE(
[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
],
[
	size_t	len;
        int	mib[2], maxfiles;

        mib[0] = CTL_KERN;
        mib[1] = KERN_MAXFILES;

        len = sizeof(maxfiles);
        sysctl(mib, 2, &maxfiles, &len, 0, 0);
],
AC_DEFINE(HAVE_FUNCTION_SYSCTL_KERN_MAXFILES,1,[Define to 1 if 'KERN_MAXFILES' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function sysctl (KERN_MAXPROC))
AC_TRY_COMPILE(
[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
],
[
	size_t	len;
	int	mib[2], maxproc;

        mib[0] = CTL_KERN;
        mib[1] = KERN_MAXPROC;

        len = sizeof(maxproc);
        sysctl(mib, 2, &maxproc, &len, 0, 0);
],
AC_DEFINE(HAVE_FUNCTION_SYSCTL_KERN_MAXPROC,1,[Define to 1 if 'KERN_MAXPROC' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function sysctl (KERN_CPTIME,KERN_CPTIME2))
AC_TRY_COMPILE(
[
#include <sys/param.h>
#include <sys/sysctl.h>
#include <sys/dkstat.h>
],
[
size_t	sz;
int	i[] = {CP_USER, CP_NICE, CP_SYS, CP_INTR, CP_IDLE};

{
	long	states[CPUSTATES];
	int	mib[2] = {CTL_KERN, KERN_CPTIME};

	sz = sizeof(states);
	sysctl(mib, 2, &states, &sz, NULL, 0);
}

{
	u_int64_t	states[CPUSTATES];
	int		mib[3] = {CTL_KERN, KERN_CPTIME2, 0};

	sz = sizeof(states);
	sysctl(mib, 3, &states, &sz, NULL, 0);
}
],
AC_DEFINE(HAVE_FUNCTION_SYSCTL_KERN_CPTIME,1,[Define to 1 if 'KERN_CPTIME,KERN_CPTIME2' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for function clock_gettime in time.h)
AC_TRY_LINK([
#ifdef HAVE_TIME_H
#	include <time.h>
#elif HAVE_SYS_TIME_H
#	include <sys/time.h>
#endif	/* HAVE_SYS_TIME_H */
],
[struct timespec tp;
clock_gettime(CLOCK_REALTIME, &tp);
],
AC_DEFINE(HAVE_TIME_CLOCK_GETTIME,1,[Define to 1 if function 'clock_gettime' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

dnl *****************************************************************
dnl *                                                               *
dnl *                      Checks for macros                        *
dnl *                                                               *
dnl *****************************************************************

AC_MSG_CHECKING(for macro __va_copy() in stdarg.h)
AC_TRY_COMPILE(
[
#include <stdarg.h>
],
[
	va_list	src,dst;

	__va_copy(dst,src);
],
AC_DEFINE(HAVE___VA_COPY,1,[Define to 1 if macro '__va_copy' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for macro __VA_ARGS__)
AC_TRY_COMPILE(
[
#define ZBX_CONST_STRING(str)   str
int test(const char *fmt, ...) { return 0; }
],
[
#define TEST(fmt, ...) test(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
TEST("%s","test");
TEST("test");
],
AC_DEFINE(HAVE___VA_ARGS__,1,[Define to 1 if macro '__VA_ARGS__' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

dnl *****************************************************************
dnl *                                                               *
dnl *                 Checks for library functions                  *
dnl *                                                               *
dnl *****************************************************************

AC_TYPE_SIGNAL
AC_REPLACE_FUNCS(getloadavg)
AC_CHECK_FUNCS(hstrerror)
AC_CHECK_FUNCS(getenv)
AC_CHECK_FUNCS(putenv)
AC_CHECK_FUNCS(sigqueue)

dnl *****************************************************************
dnl *                                                               *
dnl *            Checks for file system characteristics             *
dnl *                                                               *
dnl *****************************************************************

AC_MSG_CHECKING(for /proc filesystem)
if test -d /proc; then
	AC_MSG_RESULT(yes)
	AC_DEFINE(HAVE_PROC,1,[Define to 1 if '/proc' file system should be used.])
else
	AC_MSG_RESULT(no)
fi

AC_MSG_CHECKING(for file /proc/stat)
if test -r /proc/stat; then
	AC_MSG_RESULT(yes)
	AC_DEFINE(HAVE_PROC_STAT,1,[Define to 1 if file '/proc/stat' should be used.])
else
	AC_MSG_RESULT(no)
fi

AC_MSG_CHECKING(for file /proc/cpuinfo)
if test -r /proc/cpuinfo; then
	AC_MSG_RESULT(yes)
	AC_DEFINE(HAVE_PROC_CPUINFO,1,[Define to 1 if file '/proc/cpuinfo' should be used.])
else
	AC_MSG_RESULT(no)
fi

dnl Solaris
AC_MSG_CHECKING(for file /proc/0/psinfo)
if test -r /proc/0/psinfo; then
	AC_MSG_RESULT(yes)
	AC_DEFINE(HAVE_PROC_0_PSINFO,1,[Define to 1 if file '/proc/0/psinfo' should be used.])
else
	AC_MSG_RESULT(no)
fi

AC_MSG_CHECKING(for file /proc/loadavg)
if test -r /proc/loadavg; then
	AC_MSG_RESULT(yes)
	AC_DEFINE(HAVE_PROC_LOADAVG,1,[Define to 1 if file '/proc/loadavg' should be used.])
else
	AC_MSG_RESULT(no)
fi

AC_MSG_CHECKING(for file /proc/net/dev)
if test -r /proc/net/dev; then
	AC_MSG_RESULT(yes)
	AC_DEFINE(HAVE_PROC_NET_DEV,1,[Define to 1 if file '/proc/net/dev' should be used.])
else
	AC_MSG_RESULT(no)
fi

dnl *****************************************************************
dnl *                                                               *
dnl *              Checks for compiler characteristics              *
dnl *                                                               *
dnl *****************************************************************

dnl Check for %qu format (FreeBSD 4.x)
dnl FreeBSD 4.x does not support %llu
AC_MSG_CHECKING(for long long format)
AC_TRY_RUN(
[
#include <sys/types.h>
int main()
{
        uint64_t i;

        sscanf("200000000010020", "%qu", &i);

        if (i == 200000000010020) return 0;
        else return -1;
}
],
AC_DEFINE(HAVE_LONG_LONG_QU, 1 ,[Define to 1 if format '%qu' exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

dnl option -rdynamic is needed for readable backtraces
AC_MSG_CHECKING(for -rdynamic linking option)
saved_LDFLAGS="$LDFLAGS"
LDFLAGS="-rdynamic $LDFLAGS"
AC_TRY_LINK([#include <execinfo.h>],
[void *bcktrc[6];
(void)backtrace(bcktrc, 6);
],
LDFLAGS="-rdynamic $saved_LDFLAGS"
AC_MSG_RESULT(yes),
LDFLAGS="$saved_LDFLAGS"
AC_MSG_RESULT(no))

dnl *****************************************************************
dnl *                                                               *
dnl *                 Checks for operating systems                  *
dnl *                                                               *
dnl *****************************************************************

AC_MSG_CHECKING(for libperfstat 5.2.0.40 fileset)
AC_TRY_COMPILE([#include <libperfstat.h>],
[perfstat_memory_total_t	memstats;
memstats.virt_active = 0;
],
AC_DEFINE(HAVE_AIXOSLEVEL_520004,1,[Define to 1 if libperfstat 5.2.0.40 fileset exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

AC_MSG_CHECKING(for libperfstat 5.3.0.60 fileset)
AC_TRY_COMPILE([#include <libperfstat.h>],
[perfstat_partition_total_t	lparstats;
lparstats.type.b.donate_enabled = 0;
lparstats.idle_donated_purr = 0;
lparstats.busy_donated_purr = 0;
lparstats.idle_stolen_purr = 0;
lparstats.busy_stolen_purr = 0;
],
AC_DEFINE(HAVE_AIXOSLEVEL_530006,1,[Define to 1 if libperfstat 5.3.0.60 fileset exists.])
AC_MSG_RESULT(yes),
AC_MSG_RESULT(no))

case "$host_os" in
linux*)
	ARCH="linux"
	;;
aix*)
	ARCH="aix"
	;;
darwin*|rhapsody*)
	ARCH="osx"
	AC_DEFINE([MAC_OS_X], 1, [Define to 1 if you are using Mac OS X.])
	;;
*solaris*)
	ARCH="solaris"
	;;
hpux*)
	ARCH="hpux"
	;;
freebsd*)
	ARCH="freebsd"
	;;
netbsd*)
	ARCH="netbsd"
	;;
osf*)
	ARCH="osf"
	;;
openbsd*)
	ARCH="openbsd"
	;;
*)
	ARCH="unknown"
	;;
esac

AC_MSG_CHECKING(for architecture)
AC_MSG_RESULT([$ARCH ($host_os)])

if test "x$ARCH" = "xlinux"; then
	AC_MSG_CHECKING([for the linux kernel version])

	kernel=`uname -r`

	case "${kernel}" in
		2.6.*)
			AC_MSG_RESULT([2.6 family (${kernel})])
			AC_DEFINE([KERNEL_2_6], 1, [Define to 1 if you are using Linux 2.6.x])
			;;
		2.4.*)
			AC_MSG_RESULT([2.4 family (${kernel})])
			AC_DEFINE([KERNEL_2_4], 1, [Define to 1 if you are using Linux 2.4.x])
			;;
		*)
			AC_MSG_RESULT([unknown family (${kernel})])
			;;
	esac
fi

if test "x$ARCH" = "xsolaris"; then
	dnl Forcing a 64-bit application for a 64-bit Solaris
	dnl A 32-bit program that uses /proc is able to look at 32-bit processes,
	dnl but is not able to understand all attributes of a 64-bit process.
	AC_MSG_CHECKING(for -m64 compilation flag)
	saved_CFLAGS="$CFLAGS"
	CFLAGS="-m64"
	AC_TRY_RUN(
	[int main(void) {return 0;}],
	CFLAGS="-m64 $saved_CFLAGS"
	AC_MSG_RESULT(yes),
	CFLAGS="$saved_CFLAGS"
	AC_MSG_RESULT(no))
fi

if test "x$ARCH" = "xhpux"; then
	dnl Low Level Discovery needs a way to get the list of network
	dnl interfaces available on the monitored system. On HP-UX systems
	dnl that way depends on the OS version.
	hpux_version=${host_os#hpux}
	hpux_major=${hpux_version%.*}
	hpux_minor=${hpux_version#*.}

	AC_DEFINE_UNQUOTED([HPUX_VERSION], $hpux_major$hpux_minor, [Define to HP-UX version])

	dnl This API level is needed so that "utsname.nodename" is not truncated.
	AC_MSG_CHECKING(for -D_HPUX_API_LEVEL=20040821 compilation flag)
	saved_CFLAGS="$CFLAGS"
	CFLAGS="-D_HPUX_API_LEVEL=20040821"
	AC_TRY_RUN(
	[int main(void) {return 0;}],
	CFLAGS="-D_HPUX_API_LEVEL=20040821 $saved_CFLAGS"
	AC_MSG_RESULT(yes),
	CFLAGS="$saved_CFLAGS"
	AC_MSG_RESULT(no))
fi

AC_DEFINE_UNQUOTED([ARCH], "${ARCH}", [Define to OS name for code managing])
AC_SUBST(ARCH)

AC_CHECK_SIZEOF([void *])

dnl *****************************************************************
dnl *                                                               *
dnl *         Checks for options given on the command line          *
dnl *                                                               *
dnl *****************************************************************

AC_ARG_ENABLE(static,[  --enable-static         Build statically linked binaries],
[case "${enableval}" in
  yes)
    LDFLAGS="${LDFLAGS} -static"
    AC_MSG_CHECKING(if static linking is possible)
    AC_LINK_IFELSE([AC_LANG_PROGRAM(,)],
      [AC_MSG_RESULT([yes])
        static_linking=yes],
      [AC_MSG_RESULT([no])
        static_linking=no])
      if test "x$static_linking" = "xno"; then
        AC_MSG_ERROR([static linking is not possible on this system])
      fi
    ;;
  no) ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-static]) ;;
esac])

AC_ARG_ENABLE(server,[  --enable-server         Turn on build of Zabbix server],
[case "${enableval}" in
  yes) server=yes ;;
  no)  server=no ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-server]) ;;
esac],
[server=no])
AM_CONDITIONAL(SERVER, test "x$server" = "xyes")

AC_ARG_ENABLE(proxy,[  --enable-proxy          Turn on build of Zabbix proxy],
[case "${enableval}" in
  yes) proxy=yes ;;
  no)  proxy=no ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-proxy]) ;;
esac],
[proxy=no])
AM_CONDITIONAL(PROXY, test "x$proxy" = "xyes")

AC_ARG_ENABLE(agent,[  --enable-agent          Turn on build of Zabbix agent and client utilities],
[case "${enableval}" in
  yes) agent=yes ;;
  no)  agent=no ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-agent]) ;;
esac],
[agent=no])
AM_CONDITIONAL(AGENT, test "x$agent" = "xyes")

AC_ARG_ENABLE(java,[  --enable-java           Turn on build of Zabbix Java gateway],
[case "${enableval}" in
  yes) java=yes ;;
  no)  java=no ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-java]) ;;
esac],
[java=no])
AM_CONDITIONAL(JAVA, test "x$java" = "xyes")

AC_ARG_ENABLE(ipv6,[  --enable-ipv6           Turn on support of IPv6],
[case "${enableval}" in
  yes) ipv6=yes ;;
  no)  ipv6=no ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-ipv6]) ;;
esac],
[ipv6=no])

have_db="no"
have_unixodbc="no"
have_web_monitoring="no"
have_jabber="no"
have_snmp="no"
have_ipmi="no"
have_ipv6="no"
have_ssh2="no"

if test "x$ipv6" = "xyes"; then
	AC_DEFINE(HAVE_IPV6,1,[Define to 1 if IPv6 should be enabled.])
	have_ipv6="yes"
fi

if test "x$server" = "xyes" || test "x$proxy" = "xyes"; then

	have_multirow_insert="no"

	dnl Checking for IBM DB2 support
	AX_LIB_IBM_DB2()
	if test "x$want_ibm_db2" = "xyes"; then
		if test "x$have_db" != "xno"; then
			AC_MSG_ERROR([You can configure for only one database.])
		fi

		if test "x$found_ibm_db2" = "xyes"; then
			have_db="IBM DB2"
			have_multirow_insert="yes"
		else
			AC_MSG_ERROR([IBM DB2 library not found])
		fi
	fi

	dnl Checking for MySQL support
	AX_LIB_MYSQL()
	if test "x$want_mysql" = "xyes"; then
		if test "x$have_db" != "xno"; then
			AC_MSG_ERROR([You can configure for only one database.])
		fi

		if test "x$found_mysql" = "xyes"; then
			have_db="MySQL"
			have_multirow_insert="yes"
		else
			AC_MSG_ERROR([MySQL library not found])
		fi
	fi

	dnl Checking for Oracle support
	AX_LIB_ORACLE_OCI([10.0])
	if test "x$WANT_ORACLE_OCI" = "xyes"; then
		if test "x$have_db" != "xno"; then
			AC_MSG_ERROR([You can configure for only one database.])
		fi

		if test "x$HAVE_ORACLE_OCI" = "xyes"; then
			have_db="Oracle"

			ORACLE_CPPFLAGS="$ORACLE_OCI_CFLAGS"
			ORACLE_LDFLAGS="$ORACLE_OCI_LDFLAGS"
			ORACLE_LIBS="$ORACLE_OCI_LIBS"

			AC_SUBST(ORACLE_CPPFLAGS)
			AC_SUBST(ORACLE_LDFLAGS)
			AC_SUBST(ORACLE_LIBS)

			AC_DEFINE(HAVE_ORACLE,1,[Define to 1 if Oracle should be enabled.])
		else
			AC_MSG_ERROR([Oracle OCI library not found])
		fi
	fi

	dnl Checking for PostgreSQL support
	AX_LIB_POSTGRESQL("8.1")
	if test "x$want_postgresql" = "xyes"; then
		if test "x$have_db" != "xno"; then
			AC_MSG_ERROR([You can configure for only one database.])
		fi

		if test "x$found_postgresql" = "xyes"; then
			if test "$postgresql_version_check" != "1"; then
				AC_MSG_ERROR([PostgreSQL version mismatch])
			fi

			have_db="PostgreSQL"

			if test "$postgresql_version_number" -ge 8002000; then
				have_multirow_insert="yes"
			fi

		else
			AC_MSG_ERROR([PostgreSQL library not found])
		fi
	fi

	dnl Checking for SQLite3 support
	AX_LIB_SQLITE3()
	if test "x$WANT_SQLITE3" = "xyes"; then
		if test "x$have_db" != "xno"; then
			AC_MSG_ERROR([You can configure for only one database.])
		fi

		if test "x$found_sqlite3" = "xyes"; then
			have_db="SQLite v3.x"

			saved_CPPFLAGS="$CPPFLAGS"
			CPPFLAGS="$CPPFLAGS $SQLITE3_CPPFLAGS $SQLITE3_LDFLAGS"

			AC_MSG_CHECKING([for function sqlite3_open_v2() in sqlite3.h])
			AC_TRY_LINK([#include <sqlite3.h>],
				[sqlite3 *conn = 0;
				sqlite3_open_v2("dbname", &conn, SQLITE_OPEN_READWRITE, 0);
				],
				AC_DEFINE(HAVE_FUNCTION_SQLITE3_OPEN_V2,1,[Define to 1 if function 'sqlite3_open_v2' exists.])
				AC_MSG_RESULT(yes),
				AC_MSG_RESULT(no))

			CPPFLAGS="$saved_CPPFLAGS"
		else
			AC_MSG_ERROR([SQLite3 library not found])
		fi
	fi

	AC_MSG_CHECKING(for Zabbix server/proxy database selection)
	if test "x$have_db" = "xno"; then
		AC_MSG_RESULT(error)
		AC_MSG_ERROR([No database selected for Zabbix server/proxy. Use --with-ibm-db2 or --with-mysql or --with-oracle or --with-postgresql or --with-sqlite3.])
	else
		AC_MSG_RESULT(ok)
	fi

	AC_MSG_CHECKING(for multirow insert statements)
	if test "x$have_multirow_insert" = "xyes"; then
		AC_DEFINE(HAVE_MULTIROW_INSERT,1,[Define to 1 if database supports multirow insert statements.])
		AC_MSG_RESULT(yes)
	else
		AC_MSG_RESULT(no)
	fi

	DB_CPPFLAGS="$IBM_DB2_CPPFLAGS $MYSQL_CFLAGS $ORACLE_CPPFLAGS $POSTGRESQL_CFLAGS $SQLITE3_CPPFLAGS"
	DB_LDFLAGS="$IBM_DB2_LDFLAGS $MYSQL_LDFLAGS $ORACLE_LDFLAGS $POSTGRESQL_LDFLAGS $SQLITE3_LDFLAGS"
	DB_LIBS="$IBM_DB2_LIBS $MYSQL_LIBS $ORACLE_LIBS $POSTGRESQL_LIBS $SQLITE3_LIBS"

	AC_SUBST(DB_CPPFLAGS)
	AC_SUBST(DB_LDFLAGS)
	AC_SUBST(DB_LIBS)

	CFLAGS="${CFLAGS} ${DB_CPPFLAGS}"
	SERVER_LDFLAGS="${SERVER_LDFLAGS} ${DB_LDFLAGS}"
	SERVER_LIBS="${SERVER_LIBS} ${DB_LIBS}"

	PROXY_LDFLAGS="${PROXY_LDFLAGS} ${DB_LDFLAGS}"
	PROXY_LIBS="${PROXY_LIBS} ${DB_LIBS}"

	dnl Checking for Jabber libraries
	JABBER_CHECK_CONFIG()
	if test "x$want_jabber" = "xyes"; then
		if test "x$found_jabber" != "xyes"; then
			AC_MSG_ERROR([Jabber library not found])
		else
			have_jabber="yes"
		fi
	fi
	CFLAGS="$CFLAGS $JABBER_CPPFLAGS"
	SERVER_LDFLAGS="$SERVER_LDFLAGS $JABBER_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $JABBER_LIBS"

	dnl Check for LIBXML2 [by default - skip]
	LIBXML2_CHECK_CONFIG([no])
	if test "x$want_libxml2" = "xyes"; then
		if test "x$found_libxml2" != "xyes"; then
			AC_MSG_ERROR([LIBXML2 library not found])
		else
			have_libxml2="yes"
		fi
	fi
	CFLAGS="$CFLAGS $LIBXML2_CFLAGS"
	SERVER_LDFLAGS="$SERVER_LDFLAGS $LIBXML2_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $LIBXML2_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $LIBXML2_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $LIBXML2_LIBS"

	dnl Checking for unixODBC support
	LIBUNIXODBC_CHECK_CONFIG([no])
	if test "x$want_unixodbc" = "xyes"; then
		if test "x$found_unixodbc" != "xyes"; then
			AC_MSG_ERROR($found_unixodbc)
		fi
		have_unixodbc="yes"
	fi
	CFLAGS="$CFLAGS $UNIXODBC_CFLAGS"
	SERVER_LDFLAGS="$SERVER_LDFLAGS $UNIXODBC_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $UNIXODBC_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $UNIXODBC_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $UNIXODBC_LIBS"

	dnl Check for Net-SNMP [by default - skip]
	LIBNETSNMP_CHECK_CONFIG([no])
	if test "x$want_netsnmp" = "xyes"; then
		if test "x$found_netsnmp" != "xyes"; then
			AC_MSG_ERROR([Invalid Net-SNMP directory - unable to find net-snmp-config])
		else
			have_snmp="yes"
		fi
	fi
	CFLAGS="$CFLAGS $SNMP_CFLAGS"
	SERVER_LDFLAGS="$SERVER_LDFLAGS $SNMP_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $SNMP_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $SNMP_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $SNMP_LIBS"

	dnl Check for LIBSSH2 [by default - skip] at least of version 1.0.0.
	LIBSSH2_CHECK_CONFIG([no])
	if test "x$want_ssh2" = "xyes"; then
		if test "x$found_ssh2" != "xyes"; then
			AC_MSG_ERROR([SSH2 library not found])
		elif test "x$accept_ssh2_version" != "xyes"; then
			AC_MSG_ERROR([SSH2 library version requirement not met (>= 1.0.0)])
		else
			have_ssh2="yes"
		fi
	fi
	CFLAGS="$CFLAGS $SSH2_CFLAGS"
	SERVER_LDFLAGS="$SERVER_LDFLAGS $SSH2_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $SSH2_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $SSH2_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $SSH2_LIBS"

	found_openipmi="no"
	dnl Check for libOpenIPMI [by default - skip]
	LIBOPENIPMI_CHECK_CONFIG([no])
	if test "x$want_openipmi" = "xyes"; then
		if test "x$found_openipmi" != "xyes"; then
			AC_MSG_ERROR([Invalid OPENIPMI directory - unable to find ipmiif.h])
		else
			have_ipmi="yes"
		fi
	fi
	CFLAGS="$CFLAGS $OPENIPMI_CFLAGS"
	SERVER_LDFLAGS="$SERVER_LDFLAGS $OPENIPMI_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $OPENIPMI_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $OPENIPMI_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $OPENIPMI_LIBS"
fi

if test "x$java" = "xyes"; then
	AC_CHECK_PROGS([JAVAC], [javac], [no])
	if test "x$JAVAC" = "xno"; then
		AC_MSG_ERROR([Unable to find "javac" executable in path])
	fi
	AC_CHECK_PROGS([JAR], [jar], [no])
	if test "x$JAR" = "xno"; then
		AC_MSG_ERROR([Unable to find "jar" executable in path])
	fi
fi

AM_CONDITIONAL(JABBER, test "x$found_jabber" = "xyes")
AM_CONDITIONAL(WITH_ODBC, test "x$have_unixodbc" = "xyes")

found_ldap="no"
dnl Check for libLDAP [by default - skip]
LIBLDAP_CHECK_CONFIG([no])
if test "x$want_ldap" = "xyes"; then
	if test "x$found_ldap" != "xyes"; then
		AC_MSG_ERROR([Invalid LDAP directory - unable to find ldap.h])
	fi
fi
CFLAGS="$CFLAGS $LDAP_CPPFLAGS"

SERVER_LDFLAGS="$SERVER_LDFLAGS $LDAP_LDFLAGS"
SERVER_LIBS="$SERVER_LIBS $LDAP_LIBS"

PROXY_LDFLAGS="$PROXY_LDFLAGS $LDAP_LDFLAGS"
PROXY_LIBS="$PROXY_LIBS $LDAP_LIBS"

AGENT_LDFLAGS="$AGENT_LDFLAGS $LDAP_LDFLAGS"
AGENT_LIBS="$AGENT_LIBS $LDAP_LIBS"

found_curl="no"
dnl Checking for libCurl [by default - skip]
LIBCURL_CHECK_CONFIG(, [7.13.1], [],[])
if test "x$want_curl" = "xyes"; then
	if test "x$found_curl" != "xyes"; then
		AC_MSG_ERROR([Curl library not found])
	fi
fi
if test "x$found_curl" = "xyes"; then
	have_web_monitoring="cURL"
fi
CFLAGS="$CFLAGS $LIBCURL_CPPFLAGS"

SERVER_LDFLAGS="$SERVER_LDFLAGS $LIBCURL_LDFLAGS"
SERVER_LIBS="$SERVER_LIBS $LIBCURL_LIBS"

PROXY_LDFLAGS="$PROXY_LDFLAGS $LIBCURL_LDFLAGS"
PROXY_LIBS="$PROXY_LIBS $LIBCURL_LIBS"

dnl Starting from 2.0 agent can do web monitoring
AGENT_LDFLAGS="$AGENT_LDFLAGS $LIBCURL_LDFLAGS"
AGENT_LIBS="$AGENT_LIBS $LIBCURL_LIBS"

found_iconv="no"
dnl Check for libiconv [by default - skip]
LIBICONV_CHECK_CONFIG([no])
if test "x$found_iconv" != "xyes"; then
	AC_MSG_ERROR([Unable to use iconv (libiconv check failed)])
fi
CFLAGS="$CFLAGS $ICONV_CFLAGS"
LDFLAGS="$LDFLAGS $ICONV_LDFLAGS"
LIBS="$LIBS $ICONV_LIBS"

RANLIB="ranlib"
AC_SUBST(RANLIB)

AC_SUBST(SERVER_LDFLAGS)
AC_SUBST(SERVER_LIBS)

AC_SUBST(PROXY_LDFLAGS)
AC_SUBST(PROXY_LIBS)

AC_SUBST(AGENT_LDFLAGS)
AC_SUBST(AGENT_LIBS)

dnl *****************************************************************
dnl *                                                               *
dnl *                         Other checks                          *
dnl *                                                               *
dnl *****************************************************************

dnl Automake 1.8 to 1.9.6 sets mkdir_p macro (lower-cased).
AC_MSG_CHECKING(for mkdir -p candidate)
if test "x${MKDIR_P}" = "x"; then
        if test "x${mkdir_p}" = "x"; then
                AC_MSG_ERROR([No suitable "mkdir -p" candidate found.])
        fi
        AC_SUBST([MKDIR_P], ${mkdir_p})
fi
AC_MSG_RESULT([ok (${MKDIR_P})])

dnl *****************************************************************
dnl *                                                               *
dnl *                 Output configuration results                  *
dnl *                                                               *
dnl *****************************************************************

AC_OUTPUT([
	Makefile
	database/Makefile
	misc/Makefile
	src/Makefile
	src/libs/Makefile
	src/libs/zbxlog/Makefile
	src/libs/zbxalgo/Makefile
	src/libs/zbxmemory/Makefile
	src/libs/zbxcrypto/Makefile
	src/libs/zbxconf/Makefile
	src/libs/zbxdbcache/Makefile
	src/libs/zbxdbhigh/Makefile
	src/libs/zbxmedia/Makefile
	src/libs/zbxsysinfo/Makefile
	src/libs/zbxcommon/Makefile
	src/libs/zbxsysinfo/agent/Makefile
	src/libs/zbxsysinfo/common/Makefile
	src/libs/zbxsysinfo/simple/Makefile
	src/libs/zbxsysinfo/linux/Makefile
	src/libs/zbxsysinfo/aix/Makefile
	src/libs/zbxsysinfo/freebsd/Makefile
	src/libs/zbxsysinfo/hpux/Makefile
	src/libs/zbxsysinfo/openbsd/Makefile
	src/libs/zbxsysinfo/osx/Makefile
	src/libs/zbxsysinfo/solaris/Makefile
	src/libs/zbxsysinfo/osf/Makefile
	src/libs/zbxsysinfo/netbsd/Makefile
	src/libs/zbxsysinfo/unknown/Makefile
	src/libs/zbxnix/Makefile
	src/libs/zbxsys/Makefile
	src/libs/zbxcomms/Makefile
	src/libs/zbxcommshigh/Makefile
	src/libs/zbxdb/Makefile
	src/libs/zbxdbupgrade/Makefile
	src/libs/zbxjson/Makefile
	src/libs/zbxserver/Makefile
	src/libs/zbxicmpping/Makefile
	src/libs/zbxexec/Makefile
	src/libs/zbxself/Makefile
	src/libs/zbxmodules/Makefile
	src/libs/zbxregexp/Makefile
	src/zabbix_agent/Makefile
	src/zabbix_get/Makefile
	src/zabbix_sender/Makefile
	src/zabbix_server/Makefile
	src/zabbix_server/alerter/Makefile
	src/zabbix_server/dbsyncer/Makefile
	src/zabbix_server/dbconfig/Makefile
	src/zabbix_server/discoverer/Makefile
	src/zabbix_server/housekeeper/Makefile
	src/zabbix_server/httppoller/Makefile
	src/zabbix_server/nodewatcher/Makefile
	src/zabbix_server/pinger/Makefile
	src/zabbix_server/poller/Makefile
	src/zabbix_server/snmptrapper/Makefile
	src/zabbix_server/timer/Makefile
	src/zabbix_server/trapper/Makefile
	src/zabbix_server/utils/Makefile
	src/zabbix_server/watchdog/Makefile
	src/zabbix_server/escalator/Makefile
	src/zabbix_server/proxypoller/Makefile
	src/zabbix_server/selfmon/Makefile
	src/zabbix_server/vmware/Makefile
	src/zabbix_proxy/Makefile
	src/zabbix_proxy/heart/Makefile
	src/zabbix_proxy/housekeeper/Makefile
	src/zabbix_proxy/proxyconfig/Makefile
	src/zabbix_proxy/datasender/Makefile
	src/zabbix_java/Makefile
	upgrades/Makefile
	man/Makefile
	])

echo "

Configuration:

  Detected OS:           ${host_os}
  Install path:          ${prefix}
  Compilation arch:      ${ARCH}

  Compiler:              ${CC}
  Compiler flags:        ${CFLAGS}

  Enable server:         ${server}"

if test "x$server" != "xno"; then

echo "  Server details:
    With database:         ${have_db}
    WEB Monitoring:        ${have_web_monitoring}
    Native Jabber:         ${have_jabber}
    SNMP:                  ${have_snmp}
    IPMI:                  ${have_ipmi}
    SSH:                   ${have_ssh2}
    ODBC:                  ${have_unixodbc}
    Linker flags:          ${LDFLAGS} ${SERVER_LDFLAGS}
    Libraries:             ${LIBS} ${SERVER_LIBS}"

fi

echo "
  Enable proxy:          ${proxy}"

if test "x$proxy" != "xno"; then

echo "  Proxy details:
    With database:         ${have_db}
    WEB Monitoring:        ${have_web_monitoring}
    SNMP:                  ${have_snmp}
    IPMI:                  ${have_ipmi}
    SSH:                   ${have_ssh2}
    ODBC:                  ${have_unixodbc}
    Linker flags:          ${LDFLAGS} ${PROXY_LDFLAGS}
    Libraries:             ${LIBS} ${PROXY_LIBS}"

fi

echo "
  Enable agent:          ${agent}"

if test "x$agent" != "xno"; then

echo "  Agent details:
    Linker flags:          ${LDFLAGS} ${AGENT_LDFLAGS}
    Libraries:             ${LIBS} ${AGENT_LIBS}"

fi

echo "
  Enable Java gateway:   ${java}"

if test "x$java" != "xno"; then

echo "  Java gateway details:
    Java compiler:         ${JAVAC}
    Java archiver:         ${JAR}"

fi

echo "
  LDAP support:          ${found_ldap}
  IPv6 support:          ${have_ipv6}"

echo
echo "***********************************************************"
echo "*            Now run '${am_make} install'                       *"
echo "*                                                         *"
echo "*            Thank you for using Zabbix!                  *"
echo "*              <http://www.zabbix.com>                    *"
echo "***********************************************************"
echo
