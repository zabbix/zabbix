# LIBNETSNMP_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for net-snmp.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-net-snmp or --without-net-snmp.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_NETSNMP if required header files are
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
[If you want to use Net-SNMP library:
AC_HELP_STRING([--with-net-snmp@<:@=ARG@:>@],
		[use Net-SNMP package @<:@default=no@:>@, optionally specify path to net-snmp-config])
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

	if test "x$_libnetsnmp_config" = "xno"; then
		AC_PATH_PROG([_libnetsnmp_config], [net-snmp-config], [])
		want_static_netsnmp="no"
		test "x$enable_static_libs" = "xyes" && want_static_netsnmp="yes"
	else
		want_static_netsnmp="yes"
	fi

	if test -d "$_libnetsnmp_config"; then
		AC_PATH_PROG([_libnetsnmp_config], [net-snmp-config], [], [$_libnetsnmp_config])
	fi

	if test -x "$_libnetsnmp_config"; then

		netsnmp_version_req=$2

		if test -n "$netsnmp_version_req"; then
			AC_MSG_CHECKING(version of netsnmp library)
			LIBNETSNMP_CONFIG_VERSION=`$_libnetsnmp_config --version`
			netsnmp_version_major=`expr $LIBNETSNMP_CONFIG_VERSION : '\([[0-9]]*\)'`
			netsnmp_version_minor=`expr $LIBNETSNMP_CONFIG_VERSION : '[[0-9]]*\.\([[0-9]]*\)'`
			netsnmp_version_micro=`expr $LIBNETSNMP_CONFIG_VERSION : '[[0-9]]*\.[[0-9]]*\.\([[0-9]]*\)'`

			if test "x$netsnmp_version_micro" = "x"; then
				netsnmp_version_micro="0"
			fi

			netsnmp_version_number=`expr $netsnmp_version_major \* 1000000 \
					\+ $netsnmp_version_minor \* 1000 \
					\+ $netsnmp_version_micro`

			netsnmp_version_req_major=`expr $netsnmp_version_req : '\([[0-9]]*\)'`
			netsnmp_version_req_minor=`expr $netsnmp_version_req : '[[0-9]]*\.\([[0-9]]*\)'`
			netsnmp_version_req_micro=`expr $netsnmp_version_req : '[[0-9]]*\.[[0-9]]*\.\([[0-9]]*\)'`

			if test "x$netsnmp_version_req_micro" = "x"; then
				netsnmp_version_req_micro="0"
			fi

			netsnmp_version_req_number=`expr $netsnmp_version_req_major \* 1000000 \
					\+ $netsnmp_version_req_minor \* 1000 \
					\+ $netsnmp_version_req_micro`

			netsnmp_version_check=`expr $netsnmp_version_number \>\= $netsnmp_version_req_number`

			if test "$netsnmp_version_check" != "1"; then
				AC_MSG_ERROR([Net-SNMP version mismatch])
			else
				AC_MSG_RESULT([yes])
			fi
		fi

		_full_libnetsnmp_cflags="`$_libnetsnmp_config --cflags`"
		for i in $_full_libnetsnmp_cflags; do
			case $i in
				-I*)
					SNMP_CFLAGS="$SNMP_CFLAGS $i"

			;;
			esac
		done

		_full_libnetsnmp_libs="`$_libnetsnmp_config --netsnmp-libs`"
		for i in $_full_libnetsnmp_libs; do
			case $i in
				-L*)
					SNMP_LDFLAGS="${SNMP_LDFLAGS} $i"
			;;
				-R*)
					SNMP_LDFLAGS="${SNMP_LDFLAGS} -Wl,$i"
			;;
				-l*)
					SNMP_LIBS="${SNMP_LIBS} $i"
			;;
			esac
		done

		if test "x$enable_static" = "xyes"; then
			_full_libnetsnmp_libs="`$_libnetsnmp_config --libs`"
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
		elif test "x$want_static_netsnmp" = "xyes"; then
			_libsnmp_dir_lib="`$_libnetsnmp_config --libdir | cut -b3-`"

			test [ "x$static_linking_support" = "xno" -o -z "$static_linking_support" ] -a -z "$_libsnmp_dir_lib" && AC_MSG_ERROR(["Compiler not support statically linked libs from default folders"])

			if test "x$static_linking_support" = "xno" -o -z "$static_linking_support"; then
				test -f $_libsnmp_dir_lib/libnetsnmp.a || AC_MSG_ERROR(["libnetsnmp.a static library was not found in $_libsnmp_dir_lib"])
				SNMP_LIBS=`echo "$SNMP_LIBS"|sed "s|-lnetsnmp|$_libsnmp_dir_lib/libnetsnmp.a|g"`
			else
				SNMP_LIBS=`echo "$SNMP_LIBS"|sed "s/-lnetsnmp/${static_linking_support}static -lnetsnmp ${static_linking_support}dynamic/g"`
			fi
		fi

		_save_netsnmp_cflags="$CFLAGS"
		_save_netsnmp_ldflags="$LDFLAGS"
		_save_netsnmp_libs="$LIBS"
		CFLAGS="$CFLAGS $SNMP_CFLAGS"
		LDFLAGS="$LDFLAGS $SNMP_LDFLAGS"
		LIBS="$LIBS $SNMP_LIBS"

		AC_CHECK_LIB(netsnmp, main, , [AC_MSG_ERROR([Not found Net-SNMP library])])

		dnl Check for SHA224/256/384/512 protocol support for auth
		AC_MSG_CHECKING(for strong SHA auth protocol support)
		AC_TRY_LINK([
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>
		],[
struct snmp_session session;
session.securityAuthProto = usmHMAC384SHA512AuthProtocol;
		],[
		AC_DEFINE(HAVE_NETSNMP_STRONG_AUTH, 1, [Define to 1 if strong SHA auth protocols are supported.])
		AC_MSG_RESULT(yes)
		],[
		AC_MSG_RESULT(no)
		])

		dnl Check for AES192/256 protocol support for privacy
		AC_MSG_CHECKING(for strong AES privacy protocol support)
		AC_TRY_LINK([
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>
		],[
struct snmp_session session;
session.securityPrivProto = usmAES256PrivProtocol;
		],[
		AC_DEFINE(HAVE_NETSNMP_STRONG_PRIV, 1, [Define to 1 if strong AES priv protocols are supported.])
		AC_MSG_RESULT(yes)
		],[
		AC_MSG_RESULT(no)
		])

		dnl Check for localname in struct snmp_session
		AC_MSG_CHECKING(for localname in struct snmp_session)
		AC_TRY_COMPILE([
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>],
		[
struct snmp_session session;
session.localname = "";
		],
		AC_DEFINE(HAVE_NETSNMP_SESSION_LOCALNAME, 1, [Define to 1 if 'session.localname' exist.])
		AC_MSG_RESULT(yes),
		AC_MSG_RESULT(no))

		AC_TRY_COMPILE([
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>],
		[
struct snmp_session session;

session.securityPrivProto = usmDESPrivProtocol;
		],
		AC_DEFINE(HAVE_NETSNMP_SESSION_DES, 1, [Define to 1 if 'usmDESPrivProtocol' exist.])
		AC_MSG_RESULT(yes),
		AC_MSG_RESULT(no))

		CFLAGS="$_save_netsnmp_cflags"
		LDFLAGS="$_save_netsnmp_ldflags"
		LIBS="$_save_netsnmp_libs"
		unset _save_netsnmp_cflags
		unset _save_netsnmp_ldflags
		unset _save_netsnmp_libs

		AC_DEFINE(HAVE_NETSNMP, 1, [Define to 1 if Net-SNMP should be enabled.])

		found_netsnmp="yes"
	else
		found_netsnmp="no"
	fi
  fi

  AC_SUBST(SNMP_CFLAGS)
  AC_SUBST(SNMP_LDFLAGS)
  AC_SUBST(SNMP_LIBS)
])dnl
