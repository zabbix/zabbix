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
       AC_MSG_CHECKING(for NET-SNMP support)
  	if test "$_libnetsnmp_with" = "yes"; then
		if test -f /usr/local/net-snmp/include/net-snmp-includes.h; then
			SNMP_INCDIR=/usr/local/net-snmp/include/
			SNMP_LIBDIR=/usr/local/net-snmp/lib/
		elif test -f /usr/include/net-snmp/net-snmp-includes.h; then
			SNMP_INCDIR=/usr/include
			SNMP_LIBDIR=/usr/lib
		elif test -f /usr/include/net-snmp-includes.h; then
			SNMP_INCDIR=/usr/include
			SNMP_LIBDIR=/usr/lib
		elif test -f /usr/local/include/net-snmp/net-snmp-includes.h; then
			SNMP_INCDIR=/usr/local/include
			SNMP_LIBDIR=/usr/local/lib
		elif test -f /usr/local/include/net-snmp-includes.h; then
			SNMP_INCDIR=/usr/local/include
			SNMP_LIBDIR=/usr/local/lib
		else
			found_netsnmp="no"
			AC_MSG_RESULT(no)
		fi
   	else
		if test -f $_libnetsnmp_with/include/net-snmp/net-snmp-includes.h; then
   			SNMP_INCDIR=$_libnetsnmp_with/include
   			SNMP_LIBDIR=$_libnetsnmp_with/lib
		elif test -f $withval/include/net-snmp-includes.h; then
   			SNMP_INCDIR=$_libnetsnmp_with/include
   			SNMP_LIBDIR=$_libnetsnmp_with/lib
		else
			found_netsnmp="no"
			AC_MSG_RESULT(no)
		fi
   	fi

	if test "x$found_netsnmp" != "xno"; then
		found_netsnmp="yes"
                AC_MSG_RESULT(yes)

                AC_CHECK_LIB(crypto, main,  SNMP_LIBS="$SNMP_LIBS -lcrypto")

                SNMP_CPPFLAGS=-I$SNMP_INCDIR
                SNMP_LDFLAGS="-L$SNMP_LIBDIR $SNMP_LFLAGS -lnetsnmp $SNMP_LIBS"

                AC_DEFINE(HAVE_NETSNMP,1,[Define to 1 if NET-SNMP should be enabled.])
                AC_DEFINE(HAVE_SNMP,1,[Define to 1 if SNMP should be enabled.])
	fi
  fi

  AC_SUBST(SNMP_CPPFLAGS)
  AC_SUBST(SNMP_LDFLAGS)

  unset _libnetsnmp_with
])dnl
