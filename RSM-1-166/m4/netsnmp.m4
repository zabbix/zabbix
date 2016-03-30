# LIBNETSNMP_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Eugene Grigorjev <eugene@zabbix.com>   Feb-02-2007
#
# Checks for net-snmp.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-net-snmp or --without-net-snmp.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_SNMP and HAVE_NETSNMP if a required header files is
# found, and sets @SNMP_LDFLAGS@ and @SNMP_CFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# SNMP_LDFLAGS="-lsnmp" SNMP_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBNETSNMP_CHECK_CONFIG],
[
  _libnetsnmp_config="no"

  AC_ARG_WITH(net-snmp,
[What SNMP package do you want to use (please select only one):
AC_HELP_STRING([--with-net-snmp@<:@=ARG@:>@],
		[use NET-SNMP package @<:@default=no@:>@, optionally specify path to net-snmp-config])
	],[ if test "$withval" = "no"; then
            want_netsnmp="no"
        elif test "$withval" = "yes"; then
            want_netsnmp="yes"
        else
            want_netsnmp="yes"
            _libnetsnmp_config=$withval
        fi
     ],[want_netsnmp=ifelse([$1],,[no],[$1])])

  SNMP_CFLAGS=""
  SNMP_LDFLAGS=""
  SNMP_LIBS=""

  if test "x$want_netsnmp" != "xno"; then

        AC_PATH_PROG([_libnetsnmp_config], [net-snmp-config], [])

	if test -x "$_libnetsnmp_config"; then

		_full_libnetsnmp_cflags="`$_libnetsnmp_config --cflags`"
		for i in $_full_libnetsnmp_cflags; do
			case $i in
				-I*)
					SNMP_CFLAGS="$SNMP_CFLAGS $i"

			;;
			esac
		done

		_full_libnetsnmp_libs="`$_libnetsnmp_config --libs` -lcrypto"
		for i in $_full_libnetsnmp_libs; do
			case $i in
				-L*)
					SNMP_LDFLAGS="${SNMP_LDFLAGS} $i"
			;;
				-l*)
					SNMP_LIBS="${SNMP_LIBS} $i"
			;;
			esac
		done

		if test "x$enable_static" = "xyes"; then
			for i in $_full_libnetsnmp_libs; do
				case $i in
					-lnetsnmp)
				;;
					-l*)
						_lib_name="`echo "$i" | cut -b3-`"
						AC_CHECK_LIB($_lib_name, main, [
								SNMP_LIBS="$SNMP_LIBS $i"
							],[
								AC_MSG_ERROR([Not found $_lib_name library])
							])

				;;
				esac
			done
		fi

		_save_netsnmp_cflags="$CFLAGS"
		_save_netsnmp_ldflags="$LDFLAGS"
		_save_netsnmp_libs="$LIBS"
		CFLAGS="$CFLAGS $SNMP_CFLAGS"
		LDFLAGS="$LDFLAGS $SNMP_LDFLAGS"
		LIBS="$LIBS $SNMP_LIBS"

		AC_CHECK_LIB(netsnmp, main, , [AC_MSG_ERROR([Not found NET-SNMP library])])

		dnl Check for localname in struct snmp_session
		AC_MSG_CHECKING(for localname in struct snmp_session)
		AC_TRY_COMPILE([
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>],
		[
struct snmp_session session;
session.localname = "";
		],
		AC_DEFINE(HAVE_SNMP_SESSION_LOCALNAME, 1, [Define to 1 if 'session.localname' exist.])
		AC_MSG_RESULT(yes),
		AC_MSG_RESULT(no))

		CFLAGS="$_save_netsnmp_cflags"
		LDFLAGS="$_save_netsnmp_ldflags"
		LIBS="$_save_netsnmp_libs"
		unset _save_netsnmp_cflags
		unset _save_netsnmp_ldflags
		unset _save_netsnmp_libs

		AC_DEFINE(HAVE_NETSNMP, 1, [Define to 1 if NET-SNMP should be enabled.])
		AC_DEFINE(HAVE_SNMP, 1, [Define to 1 if SNMP should be enabled.])
		AC_DEFINE([SNMP_NO_DEBUGGING], [], [Disabling debugging messages from NET-SNMP library])

		found_netsnmp="yes"
	else
		found_netsnmp="no"
	fi
  fi

  AC_SUBST(SNMP_CFLAGS)
  AC_SUBST(SNMP_LDFLAGS)
  AC_SUBST(SNMP_LIBS)
])dnl
