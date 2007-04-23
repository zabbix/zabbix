# LIBNETSNMP_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Eugene Grigorjev <eugene@zabbix.com>   Feb-02-2007
#
# Checks for net-snmp.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-net-snmp or --without-net-snmp.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_SNMP and HAVE_NETSNMP if a required header files is
# found, and sets @SNMP_LDFLAGS@ and @SNMP_CPPFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# SNMP_LDFLAGS="-lsnmp" SNMP_CPPFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBNETSNMP_CHECK_CONFIG],
[
  AC_ARG_WITH(net-snmp,
[
What SNMP package do you want to use (please select only one):
AC_HELP_STRING([--with-net-snmp@<:@=ARG@:>@],
		[use NET-SNMP package @<:@default=no@:>@, default is to search through a number of common places for the NET-SNMP files.])],[ if test "$withval" = "no"; then
            want_netsnmp="no"
            _libnetsnmp_with="no"
        elif test "$withval" = "yes"; then
            want_netsnmp="yes"
            _libnetsnmp_with="yes"
        else
            want_netsnmp="yes"
            _libnetsnmp_with=$withval
        fi
     ],[_libnetsnmp_with=ifelse([$1],,[no],[$1])])

  if test "x$_libnetsnmp_with" != x"no"; then

        if test -d "$_libnetsnmp_with" ; then
           SNMP_INCDIR="-I$withval/include"
           _libnetsnmp_ldflags="-L$_libnetsnmp_with/lib"
           AC_PATH_PROG([_libnetsnmp_config],["$_libnetsnmp_with/bin/net-snmp-config"])
        else
   	   AC_PATH_PROG([_libnetsnmp_config],[net-snmp-config])
        fi

	if test "x$_libnetsnmp_config" != "x" ; then
		_full_libnetsnmp_libs=`$_libnetsnmp_config --libs`
		for i in $_full_libnetsnmp_libs; do
			case $i in
				-L*)
					SNMP_LIBDIRS="$SNMP_LIBDIRS $i"

			;;
			esac
		done

		if test "x$enable_static" = "xyes"; then

			for i in $_full_libnetsnmp_libs; do
				case $i in
					-lnetsnmp)
				;;
					-l*)
						_lib_name=`echo "$i" | cut -b3-`
						AC_CHECK_LIB($_lib_name , main, , AC_MSG_ERROR([Not found $_lib_name library]))
						SNMP_LIBS="$SNMP_LIBS $i"

				;;
				esac
			done
		fi

		AC_CHECK_LIB(netsnmp, main, , AC_MSG_ERROR([Not found netsnmp library]))
		SNMP_LIBS="$SNMP_LIBS -lcrypto -lnetsnmp"

		_full_libnetsnmp_cflags=`$_libnetsnmp_config --cflags`
		for i in $_full_libnetsnmp_cflags; do
			case $i in
				-I*)
					SNMP_INCDIRS="$SNMP_INCDIRS $i"

			;;
			esac
		done

		_libnetsnmp_libdir=`$_libnetsnmp_config --libdir`

		if test "x$found_netsnmp" != "xno"; then
			found_netsnmp="yes"

			SNMP_CPPFLAGS="$SNMP_INCDIRS"
			SNMP_LDFLAGS="$SNMP_LIBDIRS $SNMP_LFLAGS $SNMP_LIBS"

			AC_DEFINE(HAVE_NETSNMP,1,[Define to 1 if NET-SNMP should be enabled.])
			AC_DEFINE(HAVE_SNMP,1,[Define to 1 if SNMP should be enabled.])
		fi
	else
		found_netsnmp="no"
	fi
  fi

  AC_SUBST(SNMP_CPPFLAGS)
  AC_SUBST(SNMP_LDFLAGS)

  unset _libnetsnmp_with
])dnl
