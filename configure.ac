dnl
dnl Copyright (C) 2001-2025 Zabbix SIA
dnl
dnl This program is free software: you can redistribute it and/or modify it under the terms of
dnl the GNU Affero General Public License as published by the Free Software Foundation, version 3.
dnl
dnl This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
dnl without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
dnl See the GNU Affero General Public License for more details.
dnl
dnl You should have received a copy of the GNU Affero General Public License along with this program.
dnl If not, see <https://www.gnu.org/licenses/>.
dnl

dnl Process this file with autoconf to produce a configure script.

AC_INIT([Zabbix], [8.0.0alpha2])

AC_MSG_NOTICE([Configuring $PACKAGE_NAME $PACKAGE_VERSION])

AC_CONFIG_MACRO_DIR([m4])
AC_CONFIG_SRCDIR([src/zabbix_server/server.c])
AC_CONFIG_HEADERS([include/common/config.h])

AC_SUBST(DEFAULT_INCLUDES, ['-I$(top_srcdir)/include/common -I$(top_srcdir)/include'])

AC_CANONICAL_BUILD
AC_CANONICAL_HOST

AM_INIT_AUTOMAKE([subdir-objects tar-pax])

dnl *****************************************************************
dnl *                                                               *
dnl *                     Checks for programs                       *
dnl *                                                               *
dnl *****************************************************************

AC_PROG_EGREP
AC_PROG_AWK
AC_PROG_MAKE_SET
AC_PROG_CC
AC_PROG_CXX

dnl **
dnl * Program capability tests
dnl *

AC_MSG_CHECKING(whether compiler continues when given unrecognized flags)
cc_rejects_unknown_flags=
dnl Be mindful of the flag you're passing.
dnl Depending on the compiler's argument parsing, this may be interpreted
dnl as another flag. Be very explicit.
saved_CFLAGS="$CFLAGS"
CFLAGS="--ThisFlagShouldCauseACompilerInvocationFailure"
AC_COMPILE_IFELSE([
    dnl The program should be legal C99 to avoid unrelated failures.

    dnl As the test harness wraps the body in a main() with a
    dnl successful return, we don't need to provide any code
    dnl for the compiler to succeed at the test.
    AC_LANG_PROGRAM([[
    /* empty preamble */
    ]], [[
    /* empty body */
    ]])], [
        cc_rejects_unknown_flags="no"
        AC_MSG_RESULT(yes)
    ], [
        cc_rejects_unknown_flags="yes"
        AC_MSG_RESULT(no)
    ]
)
CFLAGS="$saved_CFLAGS"

dnl The -Werror flag is _not_ supported by compilers such as
dnl xlc (IBM's C/C++ compiler suite on AIX), where the flag
dnl `-qhalt` enables the same behavior.
AC_MSG_CHECKING(whether compiler supports -Werror)
cc_accepts_werror=
dnl XXX: The flag to be tested gets _appended_ in case the
dnl user has provided CFLAGS containing -Wno-error, which would
dnl negate the effect of -Werror.
saved_CFLAGS="$CFLAGS"
CFLAGS="$CFLAGS -Werror"
AC_COMPILE_IFELSE([
    dnl The program should be legal C99 to avoid unrelated failures.

    dnl As the test harness wraps the body in a main() with a
    dnl successful return, we don't really need to provide
    dnl any code to get the compiler to succeed at the test.
    dnl The expectation is when the compiler will not recognize
    dnl the flag and consequently fail the compilation
    dnl (as opposed to issuing a warning and continuing).
    dnl If this is not the case, the variable
    dnl $compiler_rejects_unknown_flags can be tested for this
    dnl behavior, as set by a previous test.
    AC_LANG_PROGRAM([[
    /* empty preamble */
    ]], [[
    /* empty body */
    ]])], [
        AS_IF([test $cc_rejects_unknown_flags = yes],
            [
                cc_accepts_werror="yes"
                AC_MSG_RESULT(yes)
            ],
            [
                dnl The compilation failed, but as the compiler
                dnl does not reject unrecognized flags, we can't
                dnl differentiate between success and the compiler
                dnl just ignoring it.

                dnl XXX We probably can, by resorting to pretty
                dnl gnarly output processing; keep it simple for now.
                cc_accepts_werror="unknown"
                AC_MSG_RESULT(unknown)
            ]
        )
    ], [
        cc_accepts_werror="no"
        AC_MSG_RESULT(no)
    ]
)
CFLAGS="$saved_CFLAGS"

AC_MSG_CHECKING(whether compiler supports -Werror=cpp)
checked_werror_cpp_CFLAGS=
AS_IF([test $cc_accepts_werror = yes],
    [
        saved_CFLAGS="$CFLAGS"
        CFLAGS="$CFLAGS -Werror=cpp"
        AC_COMPILE_IFELSE([
            dnl XXX: We're forcing a compiler failure here,
            dnl thus the result setting is inverted, because
            dnl we're providing a test case that should _fail_
            dnl compilation. Thus, if it succeeds, the compiler
            dnl does not support the flag.
            dnl
            dnl We've already established whether the compiler
            dnl continues with invocation errors, and whether
            dnl it accepts -Werror at all.
            AC_LANG_PROGRAM([[
            #warning This preprocessor warning is intentional
            ]], [[
            /* empty body */
            ]])], [
                dnl Set this explicitly here, regardless of the initial value
                checked_werror_cpp_CFLAGS=""
                AC_MSG_RESULT(no)
            ], [
                checked_werror_cpp_CFLAGS="-Werror=cpp"
                AC_MSG_RESULT(yes)
            ]
        )
        CFLAGS="$saved_CFLAGS"
    ],
    dnl Default
    [
        AC_MSG_RESULT(skipping)
    ]
)

dnl A C99 compliant compiler must support this.
dnl
dnl The implementations differ across compilers and platforms.
dnl GCC allocates the storage blocks in bss during compilation,
dnl while others elect for the runtime loader to handle the allocation.
dnl
dnl This means the proper test would be to run the executable,
dnl but that would make no sense when cross-compiling,
dnl so the best we can do is just reject compilers that don't even
dnl recognize the keyword.
AC_MSG_CHECKING(whether compiler supports thread-local storage)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
    #include <stdio.h>
    ]], [[
    static __thread int a = 0;
    (void)a;
    ]])],
    [
        AC_MSG_RESULT(yes)
    ], [
        AC_MSG_RESULT(no)
        AC_MSG_ERROR([compiler does not recognize the __thread keyword])
    ]
)

dnl *****************************************************************
dnl *                                                               *
dnl *                   Checks for header files                     *
dnl *                                                               *
dnl *****************************************************************

AC_HEADER_MAJOR
AC_HEADER_RESOLV

dnl ISO C Standard Library
dnl
AC_CHECK_HEADERS([assert.h ctype.h dirent.h dlfcn.h errno.h float.h          \
                  inttypes.h limits.h math.h signal.h stdarg.h stddef.h      \
                  stdint.h stdio.h stdlib.h string.h time.h                  \
])

dnl POSIX C Standard Library
dnl
AC_CHECK_HEADERS([sys/ipc.h sys/resource.h sys/sem.h sys/shm.h sys/socket.h  \
                  sys/stat.h sys/statfs.h sys/statvfs.h sys/time.h           \
                  sys/times.h sys/types.h sys/un.h sys/utsname.h sys/wait.h  \
                  arpa/inet.h fcntl.h grp.h netdb.h netinet/in.h poll.h      \
                  pthread.h pwd.h strings.h syslog.h termios.h unistd.h      \
                  utmpx.h libgen.h                                           \
])

dnl X/Open and SUS
AC_CHECK_HEADERS([sys/timeb.h])

dnl Non-standardized and obsolete non-platform headers
dnl XXX: sys/syscall.h is not standardized; obsoleted by unistd.h in POSIX;
dnl      required on AIX to work around a defect in netinet/in6_var.h
dnl      process.h is a non-standard header shipped by various compilers;
dnl      effectively obsoleted by unistd.h in POSIX.
AC_CHECK_HEADERS([sys/syscall.h malloc.h process.h])

dnl GNU C extensions
dnl
AC_CHECK_HEADERS([execinfo.h mntent.h sys/sysinfo.h])

dnl AIX
AC_CHECK_HEADERS([sys/protosw.h sys/systemcfg.h sys/var.h procinfo.h])

dnl BSDs
dnl XXX: sys/param.h is needed by duktape; shipped everywhere _except_ Linux.
dnl      sys/vfs.h is a Solaris thing that's spread to BSDs and Linux as part
dnl      of the ZFS compat layer.
AC_CHECK_HEADERS([sys/disk.h sys/dkstat.h sys/ioctl.h sys/param.h            \
                  sys/sched.h sys/vfs.h sys/vmmeter.h arpa/nameser.h kvm.h   \
])

dnl HP-UX
AC_CHECK_HEADERS([sys/pstat.h])

dnl Linux
AC_CHECK_HEADERS([linux/version.h])

dnl MacOS
AC_CHECK_HEADERS([mach/host_info.h mach/mach_host.h vm/vm_param.h nlist.h])
dnl maybe MacOS
AC_CHECK_HEADERS([kstat.h])

dnl Solaris
dnl
dnl XXX: sys/loadavg.h declares getloadavg(), which glibc, musl c and
dnl      BSD libc's have merged into stdlib.h.
AC_CHECK_HEADERS([sys/dk.h sys/loadavg.h sys/mnttab.h sys/sysmacros.h zone.h])

dnl Windows
AC_CHECK_HEADERS([Winber.h Winldap.h conio.h pdh.h psapi.h windows.h         \
                  winsock2.h ws2tcpip.h                                      \
])

dnl 3rd-Party Libraries
AC_CHECK_HEADERS([lber.h])

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
#if defined(_AIX)       /* AIX 5.1 needs this to get hstrerror() declaration */
#define _USE_IRS
#endif
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

saved_CFLAGS="$CFLAGS"
CFLAGS="$CFLAGS $checked_werror_cpp_CFLAGS"
AC_CHECK_HEADERS(sys/mount.h sys/proc.h sys/sysctl.h sys/user.h, [], [], [
#ifdef HAVE_SYS_TYPES_H
#  include <sys/types.h>
#endif
#ifdef HAVE_SYS_PARAM_H
#  include <sys/param.h>
#endif
])
CFLAGS="$saved_CFLAGS"

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
AC_CHECK_HEADERS(linux/netlink.h, [
	AC_CHECK_HEADERS(linux/inet_diag.h, [
		AC_DEFINE([HAVE_INET_DIAG], 1, [Define to 1 if you have NETLINK INET_DIAG support.])
	])
], [], [
#ifdef HAVE_SYS_SOCKET_H
#  include <sys/socket.h>
#endif
])
AC_CHECK_HEADERS(libperfstat.h, [], [], [
#ifdef HAVE_SYS_PROTOSW_H
#  include <sys/protosw.h>
#endif
])

AC_TYPE_INT16_T
AC_TYPE_INT32_T
AC_TYPE_INT64_T
AC_TYPE_INT8_T
AC_TYPE_MODE_T
AC_TYPE_OFF_T
AC_TYPE_PID_T
AC_TYPE_SIZE_T
AC_TYPE_SSIZE_T
AC_TYPE_UID_T
AC_TYPE_UINT16_T
AC_TYPE_UINT32_T
AC_TYPE_UINT64_T
AC_TYPE_UINT8_T

AC_CHECK_TYPES([ptrdiff_t])

AC_STRUCT_ST_BLOCKS

AC_CHECK_MEMBERS([struct stat.st_rdev])

dnl Kluge for building wihout pkg-config
m4_ifdef([PKG_PROG_PKG_CONFIG], [], [ AC_DEFUN([PKG_PROG_PKG_CONFIG], []) ])

dnl *****************************************************************
dnl *                                                               *
dnl *                     Checks for libraries                      *
dnl *                                                               *
dnl *****************************************************************

AC_SEARCH_LIBS(socket, socket)
AC_SEARCH_LIBS(kstat_open, kstat)
AC_SEARCH_LIBS(inet_ntoa, nsl)
AC_SEARCH_LIBS(clock_gettime, rt)
AC_SEARCH_LIBS(dlopen, dl)

dnl AIX
AC_SEARCH_LIBS(perfstat_memory_total, perfstat, [AC_DEFINE([HAVE_LIBPERFSTAT], 1, [Define to 1 if you have the 'libperfstat' library (-lperfstat)])])
AC_SEARCH_LIBS(devstat_getdevs, devstat, [AC_DEFINE([HAVE_LIBDEVSTAT], 1, [Define to 1 if you have the 'libdevstat' library (-ldevstat)])])
AC_SEARCH_LIBS(getdevs, devstat, [AC_DEFINE([HAVE_LIBDEVSTAT], 1, [Define to 1 if you have the 'libdevstat' library (-ldevstat)])])

dnl on FreeBSD we have to link with -lexecinfo to get backtraces and also check jail_getname() availability (available after __FreeBSD_version >= 800099)
AC_SEARCH_LIBS(backtrace_symbols, execinfo, [AC_DEFINE([HAVE_LIBEXECINFO], 1, [Define to 1 if you have the 'libexecinfo' library (-lexecinfo)])])
AC_SEARCH_LIBS(jail_getname, jail, [AC_DEFINE([HAVE_LIBJAIL], 1, [Define to 1 if you have 'libjail' library (-ljail)])])

AC_CHECK_LIB(m, main)
AC_CHECK_LIB(kvm, main)
AC_CHECK_LIB(pthread, main)

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

AC_MSG_CHECKING(for socklen_t)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <sys/types.h>
#include <unistd.h>
#include <sys/socket.h>
]], [[socklen_t s;]])],[AC_MSG_RESULT(yes)],[AC_DEFINE(socklen_t, int, [Define socklen_t type.])
AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for actual socklen_t parameter type in socket functions)
zbx_socklen_t=
for arg2 in "struct sockaddr" void; do
  for arg3 in socklen_t size_t int; do
    AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#  include <sys/types.h>
#endif
#ifdef HAVE_SYS_SOCKET_H
#  include <sys/socket.h>
#endif
      extern int getpeername(int sockfd, $arg2 *addr, $arg3 *addrlen);
    ]], [[
      $arg3 addrlen;
      getpeername(0, 0, &addrlen);
    ]])],[
      zbx_socklen_t="$arg3"
      break 2
    ],[])
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
  AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SIGNAL_H
#  include <signal.h>
#endif
  ]], [[
    siginfo_t siginfo;
    siginfo.si_value.$field = 0;
  ]])],[
    zbx_sival_int="$field"
    break
  ],[])
done
if test "x$zbx_sival_int" != "x"; then
  AC_MSG_RESULT($zbx_sival_int)
  AC_DEFINE_UNQUOTED(ZBX_SIVAL_INT, $zbx_sival_int, [Define integer field name in union 'sigval' of struct 'siginfo_t'])
else
  AC_MSG_ERROR(Unable to find integer field name in union sigval of struct siginfo_t)
fi

AC_MSG_CHECKING(for res_ninit)
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_NETINET_IN_H
#	include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_NAMESER_H
#	include <arpa/nameser.h>
#endif
#ifdef HAVE_RESOLV_H
#	include <resolv.h>
#endif
#ifndef C_IN
#	define C_IN	ns_c_in
#endif	/* C_IN */
#ifndef T_SOA
#	define T_SOA	ns_t_soa
#endif	/* T_SOA */
]], [[
	struct __res_state	res_state_local;

	res_ninit(&res_state_local);
]])],[AC_DEFINE(HAVE_RES_NINIT,1,Define to 1 if 'res_ninit' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for res_ndestroy)
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_NETINET_IN_H
#	include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_NAMESER_H
#	include <arpa/nameser.h>
#endif
#ifdef HAVE_RESOLV_H
#	include <resolv.h>
#endif
#ifndef C_IN
#	define C_IN	ns_c_in
#endif	/* C_IN */
#ifndef T_SOA
#	define T_SOA	ns_t_soa
#endif	/* T_SOA */
]], [[
	struct __res_state	res_state_local;

	res_ninit(&res_state_local);
	res_ndestroy(&res_state_local);
]])],[AC_DEFINE(HAVE_RES_NDESTROY,1,Define to 1 if 'res_ndestroy' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for _res._u._ext.nsaddrs[])
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_NETINET_IN_H
#	include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_NAMESER_H
#	include <arpa/nameser.h>
#endif
#ifdef HAVE_RESOLV_H
#	include <resolv.h>
#endif
#ifndef C_IN
#	define C_IN	ns_c_in
#endif	/* C_IN */
#ifndef T_SOA
#	define T_SOA	ns_t_soa
#endif	/* T_SOA */
]], [[
	struct sockaddr_in6	*sockaddrin6;

	sockaddrin6 = _res._u._ext.nsaddrs[0];
]])],[AC_DEFINE(HAVE_RES_U_EXT,1,Define to 1 if '_res._u._ext.nsaddrs[]' exists. /Linux/)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for _res._u._ext.ext)
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_NETINET_IN_H
#	include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_NAMESER_H
#	include <arpa/nameser.h>
#endif
#ifdef HAVE_RESOLV_H
#	include <resolv.h>
#endif
#ifndef C_IN
#	define C_IN	ns_c_in
#endif	/* C_IN */
#ifndef T_SOA
#	define T_SOA	ns_t_soa
#endif	/* T_SOA */
]], [[
	struct __res_state_ext	*ext;

	ext = _res._u._ext.ext;
]])],[AC_DEFINE(HAVE_RES_U_EXT_EXT,1,Define to 1 if '_res._u._ext.ext' exists. /BSD/)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for _res._ext.ext.nsaddrs[])
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_NETINET_IN_H
#	include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_NAMESER_H
#	include <arpa/nameser.h>
#endif
#ifdef HAVE_RESOLV_H
#	include <resolv.h>
#endif
#ifndef C_IN
#	define C_IN	ns_c_in
#endif	/* C_IN */
#ifndef T_SOA
#	define T_SOA	ns_t_soa
#endif	/* T_SOA */
]], [[
	union res_sockaddr_union	*na;

	na = &_res._ext.ext.nsaddrs[0];
]])],[AC_DEFINE(HAVE_RES_EXT_EXT,1,Define to 1 if '_res._ext.ext.nsaddrs[]' exists. /AIX/)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for struct sockaddr_in6.sin6_len)
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_NETINET_IN_H
#	include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_NAMESER_H
#	include <arpa/nameser.h>
#endif
#ifdef HAVE_RESOLV_H
#	include <resolv.h>
#endif
#ifndef C_IN
#	define C_IN	ns_c_in
#endif	/* C_IN */
#ifndef T_SOA
#	define T_SOA	ns_t_soa
#endif	/* T_SOA */
]], [[
	struct sockaddr_in6	sin6;
	unsigned int		len;

	len = sin6.sin6_len;
]])],[AC_DEFINE(HAVE_RES_SIN6_LEN,1,Define to 1 if 'sockaddr_in6.sin6_len' exists. /BSD/)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for union semun)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <sys/types.h>
#include <sys/ipc.h>
#include <sys/sem.h>
]], [[union semun foo;]])],[AC_DEFINE(HAVE_SEMUN, 1, Define to 1 if union 'semun' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for struct swaptable in sys/swap.h)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <stdlib.h>
#include <sys/types.h>
#include <sys/syscall.h>
#include <sys/swap.h>

#ifndef NULL
#define NULL (void *)0
#endif
]], [[
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

]])],[AC_DEFINE(HAVE_SYS_SWAP_SWAPTABLE,1,Define to 1 if struct 'swaptable' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for struct sensordev in sys/sensors.h)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <stdlib.h>
#include <sys/queue.h>
#include <sys/sensors.h>]], [[struct sensordev sensordev;
sensordev.xname[0]='\0';
sensordev.maxnumt[0]=0;
]])],[AC_DEFINE(HAVE_SENSORDEV,1,Define to 1 if struct 'sensordev' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for struct statvfs64 in sys/statvfs.h)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_SYS_STATVFS_H
#	include <sys/statvfs.h>
#endif
]], [[
	struct statvfs64	s;
	s.f_frsize = s.f_blocks = s.f_bfree = s.f_bavail = 0;
	statvfs64("/", &s);
]])],[AC_DEFINE(HAVE_SYS_STATVFS64, 1, Define to 1 if struct 'statvfs64' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for struct statfs64 in sys/statfs.h)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_SYS_STATFS_H
#	include <sys/statfs.h>
#endif
]], [[
	struct statfs64	s;
	s.f_bsize = s.f_blocks = s.f_bfree = s.f_bavail = 0;
	statfs64("/", &s);
]])],[AC_DEFINE(HAVE_SYS_STATFS64, 1, Define to 1 if struct 'statfs64' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field ss_family in struct sockaddr_storage)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/socket.h>]], [[struct sockaddr_storage ss;
ss.ss_family = 0;
]])],[AC_DEFINE(HAVE_SOCKADDR_STORAGE_SS_FAMILY, 1, Define to 1 if 'sockaddr_storage.ss_family' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field mem_unit in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.mem_unit=0;
]])],[AC_DEFINE(HAVE_SYSINFO_MEM_UNIT,1,Define to 1 if 'sysinfo.mem_unit' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field freeswap in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.freeswap=0;
]])],[AC_DEFINE(HAVE_SYSINFO_FREESWAP,1,Define to 1 if 'sysinfo.freeswap' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field totalswap in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.totalswap=0;
]])],[AC_DEFINE(HAVE_SYSINFO_TOTALSWAP,1,Define to 1 if 'sysinfo.totalswap' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field totalram in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.totalram=0;
]])],[AC_DEFINE(HAVE_SYSINFO_TOTALRAM,1,Define to 1 if 'sysinfo.totalram' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field sharedram in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.sharedram=0;
]])],[AC_DEFINE(HAVE_SYSINFO_SHAREDRAM,1,Define to 1 if 'sysinfo.sharedram' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field bufferram in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.bufferram=0;
]])],[AC_DEFINE(HAVE_SYSINFO_BUFFERRAM,1,Define to 1 if 'sysinfo.bufferram' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field freeram in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.freeram=0;
]])],[AC_DEFINE(HAVE_SYSINFO_FREERAM,1,Define to 1 if 'sysinfo.freeram' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field uptime in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.uptime=0;
]])],[AC_DEFINE(HAVE_SYSINFO_UPTIME,1,Define to 1 if 'sysinfo.uptime' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field procs in struct sysinfo)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/sysinfo.h>]], [[struct sysinfo sysinfo;
sysinfo.procs=0;
]])],[AC_DEFINE(HAVE_SYSINFO_PROCS,1,Define to 1 if 'sysinfo.procs' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for field tm_gmtoff in struct tm)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TIME_H
#include <sys/time.h>
#endif	/* HAVE_SYS_TIME_H */

#ifdef HAVE_TIME_H
#include <time.h>
#endif	/* HAVE_TIME_H */
]], [[
	struct tm tm;
	tm.tm_gmtoff;
]])],[AC_DEFINE(HAVE_TM_TM_GMTOFF,1,Define to 1 if 'tm.tm_gmtoff' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for linker options --start-group/--end-group)
saved_LDFLAGS="$LDFLAGS"
LDFLAGS="-Wl,--start-group -Wl,--end-group"
AC_LINK_IFELSE([AC_LANG_PROGRAM([[]], [[]])],[HAVE_START_GROUP="yes"
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)
])
LDFLAGS="$saved_LDFLAGS"

AC_MSG_CHECKING(for field updates in struct vminfo_t)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <sys/sysinfo.h>
]], [[
	vminfo_t vminfo;
	vminfo.updates;
]])],[AC_DEFINE(HAVE_VMINFO_T_UPDATES,1,Define to 1 if 'vminfo.updates' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

dnl *****************************************************************
dnl *                                                               *
dnl *                     Checks for functions                      *
dnl *                                                               *
dnl *****************************************************************

AC_MSG_CHECKING(for function sysconf() in unistd.h)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <unistd.h>
]], [[	int i;

	i=sysconf(_SC_PHYS_PAGES)*sysconf(_SC_PHYS_PAGES);
	i=sysconf(_SC_AVPHYS_PAGES)*sysconf(_SC_PHYS_PAGES);
]])],[AC_DEFINE(HAVE_UNISTD_SYSCONF,1,Define to 1 if function 'sysconf' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function initgroups())
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <sys/types.h>
#include <grp.h>
]], [[
	char	*user = "zabbix";
	initgroups(user, 0);
]])],[AC_DEFINE(HAVE_FUNCTION_INITGROUPS,1,Define to 1 if function 'initgroups' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for functions seteuid() and setegid())
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <sys/types.h>
#include <unistd.h>
]], [[
	seteuid(0);
	setegid(0);
]])],[AC_DEFINE(HAVE_FUNCTION_SETEUID,1,Define to 1 if functions 'seteuid' and 'setegid' exist.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function setproctitle())
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <sys/types.h>
#include <unistd.h>
]], [[
	setproctitle("Test %d", 1);
]])],[AC_DEFINE(HAVE_FUNCTION_SETPROCTITLE,1,Define to 1 if function 'setproctitle' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function sysctlbyname())
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
]], [[
	sysctlbyname("", 0, 0, 0, 0);
]])],[AC_DEFINE(HAVE_FUNCTION_SYSCTLBYNAME,1,Define to 1 if 'sysctlbyname' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function sysctl (KERN_BOOTTIME))
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
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
]], [[
	size_t		len;
	struct timeval	uptime;
        int		mib[2];

        mib[0] = CTL_KERN;
        mib[1] = KERN_BOOTTIME;

        len = sizeof(uptime);
        sysctl(mib, 2, &uptime, &len, 0, 0);
]])],[AC_DEFINE(HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME,1,Define to 1 if 'KERN_BOOTTIME' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function sysctl (HW_NCPU))
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
]], [[
	size_t	len;
	int	mib[2], ncpu;

	mib[0] = CTL_HW;
	mib[1] = HW_NCPU;

	len = sizeof(ncpu);
	sysctl(mib, 2, &ncpu, &len, 0, 0);
]])],[AC_DEFINE(HAVE_FUNCTION_SYSCTL_HW_NCPU,1,Define to 1 if 'HW_NCPU' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function sysctl (KERN_MAXFILES))
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
]], [[
	size_t	len;
        int	mib[2], maxfiles;

        mib[0] = CTL_KERN;
        mib[1] = KERN_MAXFILES;

        len = sizeof(maxfiles);
        sysctl(mib, 2, &maxfiles, &len, 0, 0);
]])],[AC_DEFINE(HAVE_FUNCTION_SYSCTL_KERN_MAXFILES,1,Define to 1 if 'KERN_MAXFILES' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function sysctl (KERN_MAXPROC))
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_SYS_TYPES_H
#include <sys/types.h>
#endif /* HAVE_SYS_TYPES_H */

#ifdef HAVE_SYS_PARAM_H
#include <sys/param.h>
#endif /* HAVE_SYS_PARAM_H */

#include <sys/sysctl.h>
]], [[
	size_t	len;
	int	mib[2], maxproc;

        mib[0] = CTL_KERN;
        mib[1] = KERN_MAXPROC;

        len = sizeof(maxproc);
        sysctl(mib, 2, &maxproc, &len, 0, 0);
]])],[AC_DEFINE(HAVE_FUNCTION_SYSCTL_KERN_MAXPROC,1,Define to 1 if 'KERN_MAXPROC' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function sysctl (KERN_CPTIME,KERN_CPTIME2))
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <sys/param.h>
#include <sys/sysctl.h>
#if defined(HAVE_SYS_DKSTAT_H)
#	include <sys/dkstat.h>
#elif defined(HAVE_SYS_SCHED_H)
#	include <sys/sched.h>
#endif
]], [[
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
]])],[AC_DEFINE(HAVE_FUNCTION_SYSCTL_KERN_CPTIME,1,Define to 1 if 'KERN_CPTIME,KERN_CPTIME2' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function clock_gettime in time.h)
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#ifdef HAVE_TIME_H
#	include <time.h>
#elif HAVE_SYS_TIME_H
#	include <sys/time.h>
#endif	/* HAVE_SYS_TIME_H */
]], [[struct timespec tp;
clock_gettime(CLOCK_REALTIME, &tp);
]])],[AC_DEFINE(HAVE_TIME_CLOCK_GETTIME,1,Define to 1 if function 'clock_gettime' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for function getrlimit in resource.h)
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <sys/resource.h>
]], [[struct rlimit rlim;
getrlimit(RLIMIT_NOFILE, &rlim);
]])],[AC_DEFINE(HAVE_GETRLIMIT,1,Define to 1 if function 'getrlimit' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

dnl *****************************************************************
dnl *                                                               *
dnl *                      Checks for macros                        *
dnl *                                                               *
dnl *****************************************************************

AC_MSG_CHECKING(for macro __va_copy() in stdarg.h)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#include <stdarg.h>
]], [[
	va_list	src,dst;

	__va_copy(dst,src);
]])],[AC_DEFINE(HAVE___VA_COPY,1,Define to 1 if macro '__va_copy' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for macro __VA_ARGS__)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[
#define ZBX_CONST_STRING(str)   str
int test(const char *fmt, ...) { return 0; }
]], [[
#define TEST(fmt, ...) test(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
TEST("%s","test");
TEST("test");
]])],[AC_DEFINE(HAVE___VA_ARGS__,1,Define to 1 if macro '__VA_ARGS__' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

dnl *****************************************************************
dnl *                                                               *
dnl *                 Checks for library functions                  *
dnl *                                                               *
dnl *****************************************************************

AC_FUNC_FORK
AC_FUNC_GETMNTENT
AC_FUNC_LSTAT_FOLLOWS_SLASHED_SYMLINK
dnl Macros AC_FUNC_MALLOC and AC_FUNC_REALLOC are commented out because their checks
dnl whether malloc() and realloc() are GNU libc compatible break compilation on AIX.
dnl zbx_malloc2() and zbx_realloc2() provide a protection similar to rpl_malloc() and
dnl rpl_realloc() required by AC_FUNC_MALLOC and AC_FUNC_REALLOC on AIX.
dnl AC_FUNC_MALLOC
AC_FUNC_MKTIME
AC_FUNC_MMAP
dnl AC_FUNC_REALLOC
AC_FUNC_STRTOD

AC_CHECK_FUNCS([alarm atexit clock_gettime dup2 fesetround floor getcwd       \
                getenv gethostname getmntent getmntinfo getpagesize           \
                gettimeofday hstrerror inet_ntoa localtime_r malloc_trim      \
                memchr memmove memset mkdir modf munmap pow pstat_getdynamic  \
                realpath round select setenv sigqueue socket sqrt strcasecmp  \
                strchr strcspn strdup strerror strpbrk strrchr strstr strtol  \
                strtoul sysinfo tzset uname unsetenv])

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
AC_RUN_IFELSE([AC_LANG_SOURCE([[
#include <sys/types.h>
int main()
{
        uint64_t i;

        sscanf("200000000010020", "%qu", &i);

        if (i == 200000000010020) return 0;
        else return -1;
}
]])],[AC_DEFINE(HAVE_LONG_LONG_QU, 1 ,Define to 1 if format '%qu' exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)],[AC_MSG_RESULT(no)])

dnl option -rdynamic is needed for readable backtraces
AC_MSG_CHECKING(for -rdynamic linking option)
saved_LDFLAGS="$LDFLAGS"
LDFLAGS="-rdynamic $LDFLAGS"
AC_LINK_IFELSE([AC_LANG_PROGRAM([[#include <execinfo.h>]], [[void *bcktrc[6];
(void)backtrace(bcktrc, 6);
]])],[LDFLAGS="-rdynamic $saved_LDFLAGS"
AC_MSG_RESULT(yes)],[LDFLAGS="$saved_LDFLAGS"
AC_MSG_RESULT(no)])

dnl *****************************************************************
dnl *                                                               *
dnl *                 Checks for operating systems                  *
dnl *                                                               *
dnl *****************************************************************

AC_MSG_CHECKING(for libperfstat 5.2.0.40 fileset)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/protosw.h>
#include <libperfstat.h>]], [[perfstat_memory_total_t	memstats;
memstats.virt_active = 0;
]])],[AC_DEFINE(HAVE_AIXOSLEVEL_520004,1,Define to 1 if libperfstat 5.2.0.40 fileset exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

AC_MSG_CHECKING(for libperfstat 5.3.0.60 fileset)
AC_COMPILE_IFELSE([AC_LANG_PROGRAM([[#include <sys/protosw.h>
#include <libperfstat.h>]], [[perfstat_partition_total_t	lparstats;
lparstats.type.b.donate_enabled = 0;
lparstats.idle_donated_purr = 0;
lparstats.busy_donated_purr = 0;
lparstats.idle_stolen_purr = 0;
lparstats.busy_stolen_purr = 0;
]])],[AC_DEFINE(HAVE_AIXOSLEVEL_530006,1,Define to 1 if libperfstat 5.3.0.60 fileset exists.)
AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

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
openbsd*)
	ARCH="openbsd"
	;;
*)
	ARCH="unknown"
	AC_DEFINE([ZBX_UNKNOWN_ARCH], 1, [Define to 1 if unsupported architecture was detected.])
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
	AC_RUN_IFELSE([AC_LANG_SOURCE([[int main(void) {return 0;}]])],[CFLAGS="-m64 $saved_CFLAGS"
	AC_MSG_RESULT(yes)],[CFLAGS="$saved_CFLAGS"
	AC_MSG_RESULT(no)],[])
	CFLAGS="${CFLAGS} -DZBX_OLD_SOLARIS"
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
	AC_RUN_IFELSE([AC_LANG_SOURCE([[
#ifdef HAVE_DLFCN_H
#	include <dlfcn.h>
#endif
#ifdef HAVE_SYS_UTSNAME_H
#	include <sys/utsname.h>
#endif

int main(void)
{
	void		*p1, *p2;
	struct utsname	name;

	/* check that the compiler (e.g., GCC 4.3.0 and above) supports function-level versioning */

	p1 = uname;
	p2 = dlsym(RTLD_DEFAULT, "uname{20040821}");

	if (p1 != p2)
		return 1;

	/* uname() fails with EFAULT on HP-UX systems that were only partially upgraded to this API level */

	return -1 == uname(&name) ? 1 : 0;
}
]])],[CFLAGS="-D_HPUX_API_LEVEL=20040821 $saved_CFLAGS"
	AC_MSG_RESULT(yes)],[CFLAGS="$saved_CFLAGS"
	AC_MSG_RESULT(no)],[])
fi

AC_DEFINE_UNQUOTED([ARCH], "${ARCH}", [Define to OS name for code managing])
AC_SUBST(ARCH)

AC_CHECK_SIZEOF([void *])
AC_CHECK_SIZEOF([size_t])

dnl *****************************************************************
dnl *                                                               *
dnl *         Checks for options given on the command line          *
dnl *                                                               *
dnl *****************************************************************

AC_ARG_ENABLE(static,[  --enable-static         Build statically linked binaries],
[case "${enableval}" in
  yes)
    enable_static=yes
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

AC_ARG_ENABLE(static-libs,[  --enable-static-libs    Build statically linked binaries with selected libs from default folders],
[case "${enableval}" in
  yes)
    enable_static_libs=yes
    saved_LIBS="$LIBS"
    LIBS="${saved_LIBS} -Wl,-bstatic -Wl,-bdynamic"
    AC_MSG_CHECKING([if libs static linking with "-Wl,-b" is possible])
    AC_LINK_IFELSE([AC_LANG_PROGRAM(,)],
      [AC_MSG_RESULT([yes])
        static_linking_support="-Wl,-b"],
      [AC_MSG_RESULT([no])
        static_linking_support=no])
        LIBS="$saved_LIBS"

    if test "x$static_linking_support" = "xno"; then
      LIBS="${saved_LIBS} -Wl,-Bstatic -Wl,-Bdynamic"
      AC_MSG_CHECKING([if libs static linking with "-Wl,-B" is possible])
      AC_LINK_IFELSE([AC_LANG_PROGRAM(,)],
        [AC_MSG_RESULT([yes])
          static_linking_support="-Wl,-B"],
        [AC_MSG_RESULT([no])
          static_linking_support=no])
          LIBS="$saved_LIBS"
    fi

    if test "x$static_linking_support" = "xno"; then
      AC_MSG_ERROR([static linking with selected libs is not possible on this system])
    fi
    ;;
  no) ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-static-libs]) ;;
esac])

if test "x$enable_static" = "xyes" && test "x$enable_static_libs" = "xyes"; then
  AC_MSG_ERROR([cannot use --enable-static and --enable-static-libs at the same time])
fi

if test "x$enable_static" = "xyes" || test "x$enable_static_libs" = "xyes"; then
  AC_DEFINE(STATIC_LINKING,1,[Define to 1 if libraries are linked statically.])
fi

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

AC_ARG_ENABLE(agent2,[  --enable-agent2         Turn on build of Zabbix agent 2],
[case "${enableval}" in
  yes) agent2=yes ;;
  no)  agent2=no ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-agent2]) ;;
esac
],
[agent2=no])
AM_CONDITIONAL(AGENT2, test "x$agent2" = "xyes")

AC_ARG_ENABLE(webservice,[  --enable-webservice         Turn on build of Zabbix web service],
[case "${enableval}" in
  yes) webservice=yes ;;
  no)  webservice=no ;;
  *) AC_MSG_ERROR([bad value ${enableval} for --enable-webservice]) ;;
esac
],
[webservice=no])
AM_CONDITIONAL(WEBSERVICE, test "x$webservice" = "xyes")

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
AM_CONDITIONAL(IPV6, test "x$ipv6" = "xyes")

AM_CONDITIONAL([DBSCHEMA], [test -d create])

AM_CONDITIONAL([USE_TESTS], [(test -f m4/conf_tests.m4) && (test "x$server" = "xyes" || test "x$proxy" = "xyes" || test "x$agent" = "xyes")])

have_db="no"
have_unixodbc="no"
have_web_monitoring="no"
have_snmp="no"
have_ipmi="no"
have_ipv6="no"
have_ssh="no"
have_tls="no"
have_libmodbus="no"


if test "x$ipv6" = "xyes"; then
	AC_DEFINE(HAVE_IPV6,1,[Define to 1 if IPv6 should be enabled.])
	have_ipv6="yes"
fi

if test "x$server" = "xyes" || test "x$proxy" = "xyes"; then


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

	dnl Checking for PostgreSQL support
	AX_LIB_POSTGRESQL("9.2")
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
	if test "x$want_sqlite3" = "xyes"; then
		if test "x$server" = "xyes"; then
			AC_MSG_ERROR([SQLite is not supported as a main Zabbix database backend.])
		fi

		if test "x$have_db" != "xno"; then
			AC_MSG_ERROR([You can configure for only one database.])
		fi

		if test "x$found_sqlite3" = "xyes"; then
			have_db="SQLite v3.x"

			saved_CPPFLAGS="$CPPFLAGS"
			saved_LDFLAGS="$LDFLAGS"
			saved_LIBS="$LIBS"

			CPPFLAGS="$CPPFLAGS $SQLITE3_CPPFLAGS"
			LDFLAGS="$LDFLAGS $SQLITE3_LDFLAGS"
			LIBS="$LIBS $SQLITE3_LIBS"

			AC_MSG_CHECKING([for function sqlite3_open_v2() in sqlite3.h])
			AC_LINK_IFELSE([AC_LANG_PROGRAM([[#include <sqlite3.h>]], [[sqlite3 *conn = 0;
				sqlite3_open_v2("dbname", &conn, SQLITE_OPEN_READWRITE, 0);
				]])],[AC_DEFINE(HAVE_FUNCTION_SQLITE3_OPEN_V2,1,Define to 1 if function 'sqlite3_open_v2' exists.)
				AC_MSG_RESULT(yes)],[AC_MSG_RESULT(no)])

			CPPFLAGS="$saved_CPPFLAGS"
			LDFLAGS="$saved_LDFLAGS"
			LIBS="$saved_LIBS"
		else
			AC_MSG_ERROR([SQLite3 library not found])
		fi
	fi

	AC_MSG_CHECKING(for Zabbix server/proxy database selection)
	if test "x$have_db" = "xno"; then
		AC_MSG_RESULT(error)
		AC_MSG_ERROR([No database selected for Zabbix server/proxy. Use --with-mysql or --with-postgresql or --with-sqlite3.])
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

	DB_CFLAGS="$MYSQL_CFLAGS $POSTGRESQL_CFLAGS $SQLITE3_CPPFLAGS"
	DB_LDFLAGS="$MYSQL_LDFLAGS $POSTGRESQL_LDFLAGS $SQLITE3_LDFLAGS"
	DB_LIBS="$MYSQL_LIBS $POSTGRESQL_LIBS $SQLITE3_LIBS"

	AC_SUBST(DB_CFLAGS)
	AC_SUBST(DB_LDFLAGS)
	AC_SUBST(DB_LIBS)

	SERVER_LDFLAGS="${SERVER_LDFLAGS} ${DB_LDFLAGS}"
	SERVER_LIBS="${SERVER_LIBS} ${DB_LIBS}"

	PROXY_LDFLAGS="${PROXY_LDFLAGS} ${DB_LDFLAGS}"
	PROXY_LIBS="${PROXY_LIBS} ${DB_LIBS}"

	dnl Check for LIBXML2 [by default - skip]
	LIBXML2_CHECK_CONFIG([no])
	if test "x$want_libxml2" = "xyes"; then
		if test "x$found_libxml2" != "xyes"; then
			AC_MSG_ERROR([LIBXML2 library not found])
		else
			have_libxml2="yes"
		fi
	fi

	SERVER_LDFLAGS="$SERVER_LDFLAGS $LIBXML2_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $LIBXML2_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $LIBXML2_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $LIBXML2_LIBS"

	AC_SUBST(LIBXML2_CFLAGS)

	dnl Checking for unixODBC support
	LIBUNIXODBC_CHECK_CONFIG([no])
	if test "x$want_unixodbc" = "xyes"; then
		if test "x$unixodbc_error" != "x"; then
			AC_MSG_ERROR($unixodbc_error)
		fi
		have_unixodbc="yes"
	fi
	SERVER_LDFLAGS="$SERVER_LDFLAGS $UNIXODBC_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $UNIXODBC_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $UNIXODBC_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $UNIXODBC_LIBS"

	AC_SUBST(UNIXODBC_CFLAGS)

	dnl Check for Net-SNMP [by default - skip]
	LIBNETSNMP_CHECK_CONFIG([no], "5.3.0")

	if test "x$want_netsnmp" = "xyes"; then
		if test "x$found_netsnmp" != "xyes"; then
			AC_MSG_ERROR([Invalid Net-SNMP directory - unable to find net-snmp-config])
		else
			have_snmp="yes"
		fi
	fi
	SERVER_LDFLAGS="$SNMP_LDFLAGS $SERVER_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $SNMP_LIBS"

	PROXY_LDFLAGS="$SNMP_LDFLAGS $PROXY_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $SNMP_LIBS"


	AC_SUBST(SNMP_CFLAGS)

	dnl Check for LIBSSH2 [by default - skip]
	LIBSSH2_CHECK_CONFIG([no],[1.8.0])
	LIBSSH_CHECK_CONFIG([no])
	if test "x$want_ssh2" = "xyes"; then
		if test "x$want_ssh" != "xno"; then
			AC_MSG_ERROR([You can configure for only one SSH library (--with-ssh or --with-ssh2).])
		fi

		if test "x$found_ssh2" != "xyes"; then
			AC_MSG_ERROR([SSH2 library not found])
		elif test "x$accept_ssh2_version" != "xyes"; then
			AC_MSG_ERROR([SSH2 library version requirement not met (>= 1.8.0)])
		else
			have_ssh="yes (libssh2)"
		fi
	fi
	SERVER_LDFLAGS="$SERVER_LDFLAGS $SSH2_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $SSH2_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $SSH2_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $SSH2_LIBS"

	AC_SUBST(SSH2_CFLAGS)

	dnl Check for LIBSSH [by default - skip] at least of version 0.7.0.
	if test "x$want_ssh" = "xyes"; then
		if test "x$found_ssh" != "xyes"; then
			AC_MSG_ERROR([SSH library not found])
		elif test "x$accept_ssh_version" != "xyes"; then
			AC_MSG_ERROR([SSH library version requirement not met (>= 0.6.0)])
		else
			have_ssh="yes (libssh)"
		fi
	fi
	SERVER_LDFLAGS="$SERVER_LDFLAGS $SSH_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $SSH_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $SSH_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $SSH_LIBS"

	AC_SUBST(SSH_CFLAGS)

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

	SERVER_LDFLAGS="$SERVER_LDFLAGS $OPENIPMI_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $OPENIPMI_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $OPENIPMI_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $OPENIPMI_LIBS"

	AC_SUBST(OPENIPMI_CFLAGS)

	dnl Check for zlib, used by Zabbix server-proxy communications
	ZLIB_CHECK_CONFIG([no])
	if test "x$found_zlib" != "xyes"; then
		AC_MSG_ERROR([Unable to use zlib (zlib check failed)])
	fi

	AC_SUBST(ZLIB_CFLAGS)

	dnl Checking for c-ares support
	ARES_CHECK_CONFIG([no])
	if test "x$want_ares" = "xyes"; then
		if test "x$found_ares" != "xyes"; then
			AC_MSG_ERROR([Unable to use ares (ares check failed)])
		fi
		have_ares="yes"
	fi
	SERVER_LDFLAGS="$SERVER_LDFLAGS $ARES_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $ARES_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $ARES_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $ARES_LIBS"

	AC_SUBST(ARES_CFLAGS)

	dnl Check for 'libpthread' library that supports PTHREAD_PROCESS_SHARED flag
	LIBPTHREAD_CHECK_CONFIG([no])
	if test "x$found_libpthread" != "xyes"; then
		AC_MSG_ERROR([Unable to use libpthread (libpthread check failed)])
	fi
fi

SERVER_LDFLAGS="$SERVER_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
SERVER_LIBS="$SERVER_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

PROXY_LDFLAGS="$PROXY_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
PROXY_LIBS="$PROXY_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

AGENT_LDFLAGS="$AGENT_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
AGENT_LIBS="$AGENT_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

AGENT2_LDFLAGS="$AGENT2_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
AGENT2_LIBS="$AGENT2_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

ZBXGET_LDFLAGS="$ZBXGET_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
ZBXGET_LIBS="$ZBXGET_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

SENDER_LDFLAGS="$SENDER_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
SENDER_LIBS="$SENDER_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

ZBXJS_LDFLAGS="$ZBXJS_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
ZBXJS_LIBS="$ZBXJS_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

AM_CONDITIONAL(HAVE_IPMI, [test "x$have_ipmi" = "xyes"])
AM_CONDITIONAL(HAVE_LIBXML2, test "x$have_libxml2" = "xyes")
AM_CONDITIONAL(HAVE_UNIXODBC, test "x$have_unixodbc" = "xyes")

AM_CONDITIONAL(HAVE_SSH, [test "x$have_ssh" = "xyes (libssh)"])
AM_CONDITIONAL(HAVE_SSH2, [test "x$have_ssh" = "xyes (libssh2)"])

dnl Check if Zabbix internal IPC services are used
have_ipcservice="no"
if test "x$have_ipmi" = "xyes"; then
	have_ipcservice="yes"
fi

if test "x$server" = "xyes"; then
	have_ipcservice="yes"
fi

if test "x$proxy" = "xyes"; then
	have_ipcservice="yes"
fi

AM_CONDITIONAL(PROXY_IPCSERVICE, [test "x$have_ipmi" = "xyes"])

dnl Check for libevent, used by Zabbix IPC services
if test "x$have_ipcservice" = "xyes"; then
	AC_DEFINE([HAVE_IPCSERVICE], 1, [Define to 1 if Zabbix IPC services are used])

	LIBEVENT_CHECK_CONFIG([no])
	if test "x$found_libevent" != "xyes"; then
		AC_MSG_ERROR([Unable to use libevent (libevent check failed)])
	fi

	SERVER_LDFLAGS="$SERVER_LDFLAGS $LIBEVENT_LDFLAGS"
	SERVER_LIBS="$SERVER_LIBS $LIBEVENT_LIBS"

	PROXY_LDFLAGS="$PROXY_LDFLAGS $LIBEVENT_LDFLAGS"
	PROXY_LIBS="$PROXY_LIBS $LIBEVENT_LIBS"
fi

dnl Check multhithread SSL libs requirements [by default - skip]
mt_required="no"
if test "x$server" = "xyes"; then
	mt_required="yes"
fi

if test "x$proxy" = "xyes"; then
	mt_required="yes"
fi

dnl Check for GnuTLS libgnutls [by default - skip]
LIBGNUTLS_CHECK_CONFIG([no])
if test "x$want_gnutls" = "xyes"; then
	if test "x$have_tls" != "xno"; then
		AC_MSG_ERROR([You can configure for only one TLS library (--with-gnutls or --with-openssl).])
	fi

	if test "x$found_gnutls" != "xyes"; then
		AC_MSG_ERROR([GnuTLS library libgnutls not found])
	elif test "x$accept_gnutls_version" != "xyes"; then
		AC_MSG_ERROR([GnuTLS library version requirement not met (>= 3.1.18)])
	else
		have_tls="GnuTLS"
	fi
	TLS_CFLAGS="$GNUTLS_CFLAGS"
	TLS_LDFLAGS="$GNUTLS_LDFLAGS"
	TLS_LIBS="$GNUTLS_LIBS"
	AC_SUBST(TLS_CFLAGS)
	AC_SUBST(TLS_LDFLAGS)
	AC_SUBST(TLS_LIBS)
fi

dnl Check for OpenSSL libssl and libcrypto [by default - skip]
LIBOPENSSL_CHECK_CONFIG([no], [$mt_required])
if test "x$want_openssl" = "xyes"; then
	if test "x$have_tls" != "xno"; then
		AC_MSG_ERROR([You can configure for only one TLS library (--with-gnutls or --with-openssl).])
	fi

	if test "x$found_openssl" != "xOpenSSL" -a "x$found_openssl" != "xOpenSSL (PSK not supported)"; then
		AC_MSG_ERROR([OpenSSL library libssl or libcrypto not found])
	elif test "x$accept_openssl_version" != "xyes" -a "$mt_required" = "yes"; then
		AC_MSG_ERROR([OpenSSL library version requirement for server or proxy not met (>= 1.1.0)])
	elif test "x$accept_openssl_version" != "xyes"; then
		AC_MSG_ERROR([OpenSSL library version requirement not met (>= 1.0.1)])
	else
		have_tls=$found_openssl
	fi
	TLS_CFLAGS="$OPENSSL_CFLAGS"
	TLS_LDFLAGS="$OPENSSL_LDFLAGS"
	TLS_LIBS="$OPENSSL_LIBS"
fi
AC_SUBST(TLS_CFLAGS)

AM_CONDITIONAL(HAVE_OPENSSL, [test "x$have_tls" = "xOpenSSL" || test "x$have_tls" = "xOpenSSL (PSK not supported)"])
AM_CONDITIONAL(HAVE_GNUTLS, [test "x$have_tls" = "xGnuTLS"])

SERVER_LDFLAGS="$SERVER_LDFLAGS $TLS_LDFLAGS"
SERVER_LIBS="$SERVER_LIBS $TLS_LIBS"

PROXY_LDFLAGS="$PROXY_LDFLAGS $TLS_LDFLAGS"
PROXY_LIBS="$PROXY_LIBS $TLS_LIBS"

AGENT_LDFLAGS="$AGENT_LDFLAGS $TLS_LDFLAGS"
AGENT_LIBS="$AGENT_LIBS $TLS_LIBS"

AGENT2_LDFLAGS="$AGENT2_LDFLAGS $TLS_LDFLAGS"
AGENT2_LIBS="$AGENT2_LIBS $TLS_LIBS"

ZBXGET_LDFLAGS="$ZBXGET_LDFLAGS $TLS_LDFLAGS"
ZBXGET_LIBS="$ZBXGET_LIBS $TLS_LIBS"

SENDER_LDFLAGS="$SENDER_LDFLAGS $TLS_LDFLAGS"
SENDER_LIBS="$SENDER_LIBS $TLS_LIBS"

ZBXJS_LDFLAGS="$ZLIB_LDFLAGS $TLS_LDFLAGS"
ZBXJS_LIBS="$ZBXJS_LIBS $TLS_LIBS"

dnl Check for libmodbus [by default - skip]
if test "x$agent" = "xyes"; then
	LIBMODBUS_CHECK_CONFIG([3.0.0])
	if test "x$want_libmodbus" = "xyes"; then
		if test "x$found_libmodbus" = "xyes"; then
			have_libmodbus="yes"

			AGENT_CFLAGS="$AGENT_CFLAGS $LIBMODBUS_CFLAGS"
			AGENT_LDFLAGS="$AGENT_LDFLAGS $LIBMODBUS_LDFLAGS"
			AGENT_LIBS="$AGENT_LIBS $LIBMODBUS_LIBS"
		fi
	fi
fi
AM_CONDITIONAL(HAVE_LIBMODBUS, [test "x$have_libmodbus" = "xyes"])

if test "x$agent2" = "xyes"; then
	AC_DEFINE(ZBX_BUILD_AGENT2,1,[Define to 1 if Agent2 is being built.])
fi

if test "x$webservice" = "xyes"; then
	AC_DEFINE(ZBX_BUILD_WEBSERVICE,1,[Define to 1 if Web Service is being built.])
fi

if test "x$agent2" = "xyes" || test "x$webservice" = "xyes"; then
	AC_CHECK_PROGS([GO], [go], [no])
	if test "x$GO" = "xno"; then
		AC_MSG_ERROR([Unable to find "go" executable in path])
	fi
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

found_ldap="no"
dnl Check for libLDAP [by default - skip]
LIBLDAP_CHECK_CONFIG([no])
if test "x$want_ldap" = "xyes"; then
	if test "x$found_ldap" != "xyes"; then
		AC_MSG_ERROR([Invalid LDAP directory - unable to find ldap.h])
	fi
fi
SERVER_LDFLAGS="$SERVER_LDFLAGS $LDAP_LDFLAGS"
SERVER_LIBS="$SERVER_LIBS $LDAP_LIBS"

PROXY_LDFLAGS="$PROXY_LDFLAGS $LDAP_LDFLAGS"
PROXY_LIBS="$PROXY_LIBS $LDAP_LIBS"

AGENT_LDFLAGS="$AGENT_LDFLAGS $LDAP_LDFLAGS"
AGENT_LIBS="$AGENT_LIBS $LDAP_LIBS"

AC_SUBST(LDAP_CPPFLAGS)

dnl Currently required cURL library version is 7.19.1 . When it is
dnl increased all the functionality in zbxcurl.h must be revised.
found_curl="no"
dnl Checking for libCurl [by default - skip]
LIBCURL_CHECK_CONFIG(, [7.19.1], [],[])
if test "x$want_curl" = "xyes"; then
	if test "x$found_curl" != "xyes"; then
		AC_MSG_ERROR([Curl library not found])
	fi
fi
if test "x$found_curl" = "xyes"; then
	have_web_monitoring="cURL"
fi
CFLAGS="$LIBCURL_CFLAGS $CFLAGS"

SERVER_LDFLAGS="$SERVER_LDFLAGS $LIBCURL_LDFLAGS"
SERVER_LIBS="$SERVER_LIBS $LIBCURL_LIBS"

PROXY_LDFLAGS="$PROXY_LDFLAGS $LIBCURL_LDFLAGS"
PROXY_LIBS="$PROXY_LIBS $LIBCURL_LIBS"

AM_CONDITIONAL(HAVE_LIBCURL, test "x$found_curl" = "xyes")

dnl Starting from 2.0 agent can do web monitoring
AGENT_LDFLAGS="$AGENT_LDFLAGS $LIBCURL_LDFLAGS"
AGENT_LIBS="$AGENT_LIBS $LIBCURL_LIBS"

ZBXGET_LDFLAGS="$ZBXGET_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
ZBXGET_LIBS="$ZBXGET_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

SENDER_LDFLAGS="$SENDER_LDFLAGS $ZLIB_LDFLAGS $LIBPTHREAD_LDFLAGS"
SENDER_LIBS="$SENDER_LIBS $ZLIB_LIBS $LIBPTHREAD_LIBS"

ZBXJS_LDFLAGS="$ZBXJS_LDFLAGS $LIBCURL_LDFLAGS"
ZBXJS_LIBS="$ZBXJS_LIBS $LIBCURL_LIBS"

if test "x$server" = "xyes" || test "x$proxy" = "xyes" || test "x$agent" = "xyes" || test "x$agent2" = "xyes"; then
	LIBPCRE2_CHECK_CONFIG([mandatory])

	if test "x$found_libpcre2" != "xyes"; then
	    AC_MSG_ERROR([unable to use libpcre2 (libpcre2 check failed)])
	fi

	CFLAGS="$CFLAGS $LIBPCRE2_CFLAGS"
	LDFLAGS="$LDFLAGS $LIBPCRE2_LDFLAGS"
	if test "x$ARCH" = "xosx"; then
		LIBS="$LIBPCRE2_LIBS $LIBS"
	else
		LIBS="$LIBS $LIBPCRE2_LIBS"
	fi

	AC_DEFINE([HAVE_PCRE2_H], 1, [Define to 1 if you have the 'libpcre2' library (-lpcre2-8)])
	have_pcre2=1
fi

AM_CONDITIONAL([HAVE_PCRE2], [test "x$have_pcre2" = "x1"])

found_iconv="no"
dnl Check for libiconv [by default - skip]
LIBICONV_CHECK_CONFIG([no])
if test "x$found_iconv" != "xyes"; then
	AC_MSG_ERROR([Unable to use iconv (libiconv check failed)])
fi
LDFLAGS="$LDFLAGS $ICONV_LDFLAGS"
LIBS="$LIBS $ICONV_LIBS"

TIMES_CHECK_NULL_ARG([no])

have_stacksize="no"

AC_ARG_WITH(stacksize,[
Override default thread stack size:
AS_HELP_STRING([--with-stacksize=STACKSIZE], [Set default thread stack size in kilobytes.])],
[case "${withval}" in
  [[0-9]][[0-9]][[0-9]]|[[0-9]][[0-9]][[0-9]][[0-9]]|[[0-9]][[0-9]][[0-9]][[0-9]][[0-9]])
    AC_DEFINE_UNQUOTED(HAVE_STACKSIZE, ${withval}, [Override default thread stack size.]) ;;
  *) AC_MSG_ERROR([bad value ${withval} for --with-stacksize]) ;;
esac])

RANLIB="ranlib"
AC_SUBST(RANLIB)

SERVER_CONFIG_FILE="${sysconfdir}/zabbix_server.conf"
PROXY_CONFIG_FILE="${sysconfdir}/zabbix_proxy.conf"
AGENT_CONFIG_FILE="${sysconfdir}/zabbix_agentd.conf"
AGENT2_CONFIG_FILE="${sysconfdir}/zabbix_agent2.conf"
AGENT2_CONFIG_DIR="${sysconfdir}/zabbix_agent2.d"
AGENT2_PLUGIN_CONFIG_DIR="${AGENT2_CONFIG_DIR}/plugins.d"
WEBSERVICE_CONFIG_FILE="${sysconfdir}/zabbix_web_service.conf"

EXTERNAL_SCRIPTS_PATH="${datadir}/zabbix/externalscripts"
ALERT_SCRIPTS_PATH="${datadir}/zabbix/alertscripts"

CURL_SSL_CERT_LOCATION="${datadir}/zabbix/ssl/certs"
CURL_SSL_KEY_LOCATION="${datadir}/zabbix/ssl/keys"

LOAD_MODULE_PATH="${libdir}/modules"

AC_SUBST(SERVER_LDFLAGS)
AC_SUBST(SERVER_LIBS)

AC_SUBST(PROXY_LDFLAGS)
AC_SUBST(PROXY_LIBS)

AC_SUBST(AGENT_LDFLAGS)
AC_SUBST(AGENT_LIBS)

AC_SUBST(AGENT2_LDFLAGS)
AC_SUBST(AGENT2_LIBS)

if test "x$HAVE_START_GROUP" = "xyes"; then
	LD_START_GROUP=-Wl,--start-group
	LD_END_GROUP=-Wl,--end-group
fi

if test "x$agent2" = "xyes"; then
	CGO_CFLAGS="$TLS_CFLAGS $LIBPCRE2_CFLAGS"
	CGO_LDFLAGS="$AGENT2_LDFLAGS $LD_START_GROUP \
		\${abs_top_builddir}/src/libs/zbxalgo/libzbxalgo.a \
		\${abs_top_builddir}/src/libs/zbxinterface/libzbxinterface.a \
		\${abs_top_builddir}/src/libs/zbxcommon/libzbxcommon.a \
		\${abs_top_builddir}/src/libs/zbxcomms/libzbxcomms.a \
		\${abs_top_builddir}/src/libs/zbxcompress/libzbxcompress.a \
		\${abs_top_builddir}/src/libs/zbxcrypto/libzbxcrypto.a \
		\${abs_top_builddir}/src/libs/zbxexec/libzbxexec.a \
		\${abs_top_builddir}/src/libs/zbxhash/libzbxhash.a \
		\${abs_top_builddir}/src/libs/zbxjson/libzbxjson.a \
		\${abs_top_builddir}/src/libs/zbxprof/libzbxprof.a \
		\${abs_top_builddir}/src/libs/zbxnix/libzbxnix.a \
		\${abs_top_builddir}/src/libs/zbxregexp/libzbxregexp.a \
		\${abs_top_builddir}/src/libs/zbxnum/libzbxnum.a \
		\${abs_top_builddir}/src/libs/zbxstr/libzbxstr.a \
		\${abs_top_builddir}/src/libs/zbxfile/libzbxfile.a \
		\${abs_top_builddir}/src/libs/zbxparam/libzbxparam.a \
		\${abs_top_builddir}/src/libs/zbxexpr/libzbxexpr.a \
		\${abs_top_builddir}/src/libs/zbxtime/libzbxtime.a \
		\${abs_top_builddir}/src/libs/zbxip/libzbxip.a \
		\${abs_top_builddir}/src/libs/zbxsysinfo/"$ARCH"/libspecsysinfo.a \
		\${abs_top_builddir}/src/libs/zbxsysinfo/alias/libalias.a \
		\${abs_top_builddir}/src/libs/zbxsysinfo/libzbxagent2sysinfo.a \
		\${abs_top_builddir}/src/libs/zbxsysinfo/simple/libsimplesysinfo.a \
		\${abs_top_builddir}/src/libs/zbxthreads/libzbxthreads.a \
		\${abs_top_builddir}/src/libs/zbxvariant/libzbxvariant.a \
		\${abs_top_builddir}/src/zabbix_agent/logfiles/libzbxlogfiles.a \
		\${abs_top_builddir}/src/zabbix_agent/active_checks/libzbxactive_checks.a \
		\${abs_top_builddir}/src/zabbix_agent/metrics/libzbxmetrics.a \
		$LIBS $AGENT2_LIBS $LD_END_GROUP"

	AC_SUBST(CGO_CFLAGS)
	AC_SUBST(CGO_LDFLAGS)
fi

AC_SUBST(ZBXGET_LDFLAGS)
AC_SUBST(ZBXGET_LIBS)

AC_SUBST(SENDER_LDFLAGS)
AC_SUBST(SENDER_LIBS)

AC_SUBST(ZBXJS_LDFLAGS)
AC_SUBST(ZBXJS_LIBS)

AC_SUBST(SERVER_CONFIG_FILE)
AC_SUBST(PROXY_CONFIG_FILE)
AC_SUBST(AGENT_CONFIG_FILE)
AC_SUBST(AGENT2_CONFIG_FILE)
AC_SUBST(AGENT2_CONFIG_DIR)
AC_SUBST(AGENT2_PLUGIN_CONFIG_DIR)
AC_SUBST(WEBSERVICE_CONFIG_FILE)

AC_SUBST(EXTERNAL_SCRIPTS_PATH)
AC_SUBST(ALERT_SCRIPTS_PATH)

AC_SUBST(CURL_SSL_CERT_LOCATION)
AC_SUBST(CURL_SSL_KEY_LOCATION)

AC_SUBST(LOAD_MODULE_PATH)

AC_SUBST(GO)

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

dnl Check if process statistics collector should be enabled
case "x$ARCH" in
	xlinux|xsolaris)
		AC_DEFINE(ZBX_PROCSTAT_COLLECTOR, 1 , [Define to 1 on linux and solaris platforms])
		;;
esac

found_cmocka="no"
found_yaml="no"

dnl *****************************************************************
dnl *                                                               *
dnl *                 Output configuration results                  *
dnl *                                                               *
dnl *****************************************************************

m4_ifdef([CONF_TESTS],[CONF_TESTS])
AM_CONDITIONAL([TESTS],[test "x$found_cmocka" = "xyes"])

AC_CONFIG_FILES([
	Makefile
	include/Makefile
	database/Makefile
	database/mysql/Makefile
	database/mysql/option-patches/Makefile
	database/postgresql/Makefile
	database/postgresql/option-patches/Makefile
	database/postgresql/timescaledb/Makefile
	database/postgresql/timescaledb/option-patches/Makefile
	database/postgresql/timescaledb/option-patches/with-compression/Makefile
	database/postgresql/timescaledb/option-patches/without-compression/Makefile
	database/sqlite3/Makefile
	misc/Makefile
	src/Makefile
	src/go/Makefile
	src/libs/Makefile
	src/libs/zbxalgo/Makefile
	src/libs/zbxasyncpoller/Makefile
	src/libs/zbxasynchttppoller/Makefile
	src/libs/zbxaudit/Makefile
	src/libs/zbxautoreg/Makefile
	src/libs/zbxavailability/Makefile
	src/libs/zbxconnector/Makefile
	src/libs/zbxcommon/Makefile
	src/libs/zbxbincommon/Makefile
	src/libs/zbxcomms/Makefile
	src/libs/zbxcommshigh/Makefile
	src/libs/zbxcompress/Makefile
	src/libs/zbxcalc/Makefile
	src/libs/zbxcfg/Makefile
	src/libs/zbxcrypto/Makefile
	src/libs/zbxcurl/Makefile
	src/libs/zbxdb/Makefile
	src/libs/zbxdbsyncer/Makefile
	src/libs/zbxcacheconfig/Makefile
	src/libs/zbxcachehistory/Makefile
	src/libs/zbxcachevalue/Makefile
	src/libs/zbxdbhigh/Makefile
	src/libs/zbxdbwrap/Makefile
	src/libs/zbxdbschema/Makefile
	src/libs/zbxdbupgrade/Makefile
	src/libs/zbxdiag/Makefile
	src/libs/zbxdiscoverer/Makefile
	src/libs/zbxdiscovery/Makefile
	src/libs/zbxembed/Makefile
	src/libs/zbxeval/Makefile
	src/libs/zbxevent/Makefile
	src/libs/zbxexec/Makefile
	src/libs/zbxexport/Makefile
	src/libs/zbxexpr/Makefile
	src/libs/zbxfile/Makefile
	src/libs/zbxgetopt/Makefile
	src/libs/zbxhash/Makefile
	src/libs/zbxhistory/Makefile
	src/libs/zbxhttp/Makefile
	src/libs/zbxicmpping/Makefile
	src/libs/zbxip/Makefile
	src/libs/zbxinterface/Makefile
	src/libs/zbxipcservice/Makefile
	src/libs/zbxipmi/Makefile
	src/libs/zbxjson/Makefile
	src/libs/zbxkvs/Makefile
	src/libs/zbxlog/Makefile
	src/libs/zbxmedia/Makefile
	src/libs/zbxmodules/Makefile
	src/libs/zbxmutexs/Makefile
	src/libs/zbxpgservice/Makefile
	src/libs/zbxprof/Makefile
	src/libs/zbxnix/Makefile
	src/libs/zbxnum/Makefile
	src/libs/zbxodbc/Makefile
	src/libs/zbxparam/Makefile
	src/libs/zbxpoller/Makefile
	src/libs/zbxagentget/Makefile
	src/libs/zbxpreproc/Makefile
	src/libs/zbxpreprocbase/Makefile
	src/libs/zbxprometheus/Makefile
	src/libs/zbxproxybuffer/Makefile
	src/libs/zbxregexp/Makefile
	src/libs/zbxrtc/Makefile
	src/libs/zbxself/Makefile
	src/libs/zbxserialize/Makefile
	src/libs/zbxscripts/Makefile
	src/libs/zbxservice/Makefile
	src/libs/zbxshmem/Makefile
	src/libs/zbxsnmptrapper/Makefile
	src/libs/zbxstr/Makefile
	src/libs/zbxvmware/Makefile
	src/libs/zbxsysinfo/Makefile
	src/libs/zbxsysinfo/agent/Makefile
	src/libs/zbxsysinfo/aix/Makefile
	src/libs/zbxsysinfo/alias/Makefile
	src/libs/zbxsysinfo/common/Makefile
	src/libs/zbxsysinfo/freebsd/Makefile
	src/libs/zbxsysinfo/hpux/Makefile
	src/libs/zbxsysinfo/linux/Makefile
	src/libs/zbxsysinfo/netbsd/Makefile
	src/libs/zbxsysinfo/openbsd/Makefile
	src/libs/zbxsysinfo/osx/Makefile
	src/libs/zbxsysinfo/simple/Makefile
	src/libs/zbxsysinfo/solaris/Makefile
	src/libs/zbxsysinfo/unknown/Makefile
	src/libs/zbxstats/Makefile
	src/libs/zbxtagfilter/Makefile
	src/libs/zbxtasks/Makefile
	src/libs/zbxhttppoller/Makefile
	src/libs/zbxthreads/Makefile
	src/libs/zbxtime/Makefile
	src/libs/zbxtimekeeper/Makefile
	src/libs/zbxtrapper/Makefile
	src/libs/zbxtrends/Makefile
	src/libs/zbxvariant/Makefile
	src/libs/zbxvault/Makefile
	src/libs/zbxversion/Makefile
	src/libs/zbxxml/Makefile
	src/libs/zbxalerter/Makefile
	src/libs/zbxpinger/Makefile
	src/libs/zbxescalations/Makefile
	src/zabbix_agent/Makefile
	src/zabbix_agent/logfiles/Makefile
	src/zabbix_agent/active_checks/Makefile
	src/zabbix_agent/agent_conf/Makefile
	src/zabbix_agent/listener/Makefile
	src/zabbix_agent/metrics/Makefile
	src/zabbix_get/Makefile
	src/zabbix_java/Makefile
	src/zabbix_js/Makefile
	src/zabbix_proxy/Makefile
	src/zabbix_proxy/autoreg/Makefile
	src/zabbix_proxy/cachehistory/Makefile
	src/zabbix_proxy/datasender/Makefile
	src/zabbix_proxy/diag/Makefile
	src/zabbix_proxy/discovery/Makefile
	src/zabbix_proxy/housekeeper/Makefile
	src/zabbix_proxy/proxyconfig/Makefile
	src/zabbix_proxy/rtc/Makefile
	src/zabbix_proxy/poller/Makefile
	src/zabbix_proxy/preproc/Makefile
	src/zabbix_proxy/proxyconfigwrite/Makefile
	src/zabbix_proxy/stats/Makefile
	src/zabbix_proxy/taskmanager/Makefile
	src/zabbix_proxy/trapper/Makefile
	src/zabbix_sender/Makefile
	src/zabbix_server/Makefile
	src/zabbix_server/audit/Makefile
	src/zabbix_server/autoreg/Makefile
	src/zabbix_server/actions/Makefile
	src/zabbix_server/postinit/Makefile
	src/zabbix_server/cachehistory/Makefile
	src/zabbix_server/connector/Makefile
	src/zabbix_server/dbconfigworker/Makefile
	src/zabbix_server/dbconfig/Makefile
	src/zabbix_server/diag/Makefile
	src/zabbix_server/discovery/Makefile
	src/zabbix_server/escalator/Makefile
	src/zabbix_server/events/Makefile
	src/zabbix_server/ha/Makefile
	src/zabbix_server/housekeeper/Makefile
	src/zabbix_server/lld/Makefile
	src/zabbix_server/pgmanager/Makefile
	src/zabbix_server/operations/Makefile
	src/zabbix_server/poller/Makefile
	src/zabbix_server/preproc/Makefile
	src/zabbix_server/proxyconfigread/Makefile
	src/zabbix_server/proxypoller/Makefile
	src/zabbix_server/reporter/Makefile
	src/zabbix_server/rtc/Makefile
	src/zabbix_server/service/Makefile
	src/zabbix_server/stats/Makefile
	src/zabbix_server/taskmanager/Makefile
	src/zabbix_server/timer/Makefile
	src/zabbix_server/trapper/Makefile
	man/Makefile
	])
AC_OUTPUT

dnl The following variables use autoconf variables such as
dnl ${sysconfdir} and ${datadir} . Which in turn contain
dnl variables such as ${prefix}. In order to completely
dnl substitute all variables and output end-value in
dnl Configuration output below it is required to perform 2
dnl substitutions.

SERVER_CONFIG_FILE=`eval echo "${SERVER_CONFIG_FILE}"`
SERVER_CONFIG_FILE=`eval echo "${SERVER_CONFIG_FILE}"`

PROXY_CONFIG_FILE=`eval echo "${PROXY_CONFIG_FILE}"`
PROXY_CONFIG_FILE=`eval echo "${PROXY_CONFIG_FILE}"`

AGENT_CONFIG_FILE=`eval echo "${AGENT_CONFIG_FILE}"`
AGENT_CONFIG_FILE=`eval echo "${AGENT_CONFIG_FILE}"`

AGENT2_CONFIG_FILE=`eval echo "${AGENT2_CONFIG_FILE}"`
AGENT2_CONFIG_FILE=`eval echo "${AGENT2_CONFIG_FILE}"`

AGENT2_CONFIG_DIR=`eval echo "${AGENT2_CONFIG_DIR}"`
AGENT2_CONFIG_DIR=`eval echo "${AGENT2_CONFIG_DIR}"`

AGENT2_PLUGIN_CONFIG_DIR=`eval echo "${AGENT2_PLUGIN_CONFIG_DIR}"`
AGENT2_PLUGIN_CONFIG_DIR=`eval echo "${AGENT2_PLUGIN_CONFIG_DIR}"`

WEBSERVICE_CONFIG_FILE=`eval echo "${WEBSERVICE_CONFIG_FILE}"`
WEBSERVICE_CONFIG_FILE=`eval echo "${WEBSERVICE_CONFIG_FILE}"`

EXTERNAL_SCRIPTS_PATH=`eval echo "${EXTERNAL_SCRIPTS_PATH}"`
EXTERNAL_SCRIPTS_PATH=`eval echo "${EXTERNAL_SCRIPTS_PATH}"`

ALERT_SCRIPTS_PATH=`eval echo "${ALERT_SCRIPTS_PATH}"`
ALERT_SCRIPTS_PATH=`eval echo "${ALERT_SCRIPTS_PATH}"`

CURL_SSL_CERT_LOCATION=`eval echo "${CURL_SSL_CERT_LOCATION}"`
CURL_SSL_CERT_LOCATION=`eval echo "${CURL_SSL_CERT_LOCATION}"`

CURL_SSL_KEY_LOCATION=`eval echo "${CURL_SSL_KEY_LOCATION}"`
CURL_SSL_KEY_LOCATION=`eval echo "${CURL_SSL_KEY_LOCATION}"`

LOAD_MODULE_PATH=`eval echo "${LOAD_MODULE_PATH}"`
LOAD_MODULE_PATH=`eval echo "${LOAD_MODULE_PATH}"`

echo "

Configuration:

  Detected OS:           ${host_os}
  Install path:          ${prefix}
  Compilation arch:      ${ARCH}

  Compiler:              ${CC}
  Compiler flags:        ${CFLAGS}

  Library-specific flags:"

if test "x$DB_CFLAGS" != "x"; then
	echo "    database:              ${DB_CFLAGS}"
fi

if test "x$LIBPCRE2_CFLAGS" != "x"; then
	echo "    libpcre2:              ${LIBPCRE2_CFLAGS}"
fi

if test "x$LIBXML2_CFLAGS" != "x"; then
	echo "    libXML2:               ${LIBXML2_CFLAGS}"
fi

if test "x$UNIXODBC_CFLAGS" != "x"; then
	echo "    unixODBC:              ${UNIXODBC_CFLAGS}"
fi

if test "x$ARES_CFLAGS" != "x"; then
	echo "    c-ares:              ${ARES_CFLAGS}"
fi

if test "x$SNMP_CFLAGS" != "x"; then
	echo "    Net-SNMP:              ${SNMP_CFLAGS}"
fi

if test "x$OPENIPMI_CFLAGS" != "x"; then
	echo "    OpenIPMI:              ${OPENIPMI_CFLAGS}"
fi

if test "x$SSH2_CFLAGS" != "x"; then
	echo "    libssh2:               ${SSH2_CFLAGS}"
fi

if test "x$SSH_CFLAGS" != "x"; then
	echo "    libssh:                ${SSH_CFLAGS}"
fi

if test "x$LIBMODBUS_CFLAGS" != "x"; then
	echo "    libmodbus:                ${LIBMODBUS_CFLAGS}"
fi

if test "x$TLS_CFLAGS" != "x"; then
	echo "    TLS:                   ${TLS_CFLAGS}"
fi

if test "x$LDAP_CPPFLAGS" != "x"; then
	echo "    LDAP:                  ${LDAP_CPPFLAGS}"
fi

if test "x$ICONV_CFLAGS" != "x"; then
	echo "    iconv:                 ${ICONV_CFLAGS}"
fi

if test "x$LIBEVENT_CFLAGS" != "x"; then
	echo "    libevent:              ${LIBEVENT_CFLAGS}"
fi

echo "
  Enable server:         ${server}"

if test "x$server" != "xno"; then

echo "  Server details:
    With database:         ${have_db}
    WEB Monitoring:        ${have_web_monitoring}"

if test "x$have_web_monitoring" = "xcURL"; then
echo "      SSL certificates:      ${CURL_SSL_CERT_LOCATION}
      SSL keys:              ${CURL_SSL_KEY_LOCATION}"
fi

echo "    SNMP:                  ${have_snmp}
    IPMI:                  ${have_ipmi}
    SSH:                   ${have_ssh}
    TLS:                   ${have_tls}
    ODBC:                  ${have_unixodbc}
    Linker flags:          ${SERVER_LDFLAGS} ${LDFLAGS}
    Libraries:             ${SERVER_LIBS} ${LIBS}
    Configuration file:    ${SERVER_CONFIG_FILE}
    External scripts:      ${EXTERNAL_SCRIPTS_PATH}
    Alert scripts:         ${ALERT_SCRIPTS_PATH}
    Modules:               ${LOAD_MODULE_PATH}"

fi

echo "
  Enable proxy:          ${proxy}"

if test "x$proxy" != "xno"; then

echo "  Proxy details:
    With database:         ${have_db}
    WEB Monitoring:        ${have_web_monitoring}"

if test "x$have_web_monitoring" = "xcURL"; then
echo "      SSL certificates:      ${CURL_SSL_CERT_LOCATION}
      SSL keys:              ${CURL_SSL_KEY_LOCATION}"
fi

echo "    SNMP:                  ${have_snmp}
    IPMI:                  ${have_ipmi}
    SSH:                   ${have_ssh}
    TLS:                   ${have_tls}
    ODBC:                  ${have_unixodbc}
    Linker flags:          ${PROXY_LDFLAGS} ${LDFLAGS}
    Libraries:             ${PROXY_LIBS} ${LIBS}
    Configuration file:    ${PROXY_CONFIG_FILE}
    External scripts:      ${EXTERNAL_SCRIPTS_PATH}
    Modules:               ${LOAD_MODULE_PATH}"

fi

echo "
  Enable agent:          ${agent}"

if test "x$agent" != "xno"; then

echo "  Agent details:
    TLS:                   ${have_tls}
    Modbus:                ${have_libmodbus}
    Linker flags:          ${AGENT_LDFLAGS} ${LDFLAGS}
    Libraries:             ${AGENT_LIBS} ${LIBS}
    Configuration file:    ${AGENT_CONFIG_FILE}
    Modules:               ${LOAD_MODULE_PATH}"

fi

echo "
  Enable agent 2:        ${agent2}"

echo "
  Enable web service:    ${webservice}"

echo "
  Enable Java gateway:   ${java}"

if test "x$java" != "xno"; then

echo "  Java gateway details:
    Java compiler:         ${JAVAC}
    Java archiver:         ${JAR}"

fi

echo "
  LDAP support:          ${found_ldap}
  IPv6 support:          ${have_ipv6}
  cmocka support:        ${found_cmocka}"

if test "x$found_cmocka" != "xno"; then

echo "  cmocka library details:
    Compiler flags:      ${CMOCKA_CFLAGS}
    Linker flags:        ${CMOCKA_LDFLAGS}
    Libraries:           ${CMOCKA_LIBS}
    Library location:    ${CMOCKA_LIBRARY_PATH}"

fi

echo "
  yaml support:          ${found_yaml}"

if test "x$found_yaml" != "xno"; then

echo "  yaml library details:
    Compiler flags:      ${YAML_CFLAGS}
    Linker flags:        ${YAML_LDFLAGS}
    Libraries:           ${YAML_LIBS}
    Library location:    ${YAML_LIBRARY_PATH}"

fi

echo
echo "***********************************************************"
echo "*            Now run '${am_make} install'                       *"
echo "*                                                         *"
echo "*            Thank you for using Zabbix!                  *"
echo "*              <https://www.zabbix.com>                   *"
echo "***********************************************************"
echo
