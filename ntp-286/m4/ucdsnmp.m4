# LIBSNMP_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Eugene Grigorjev <eugene@zabbix.com>   Feb-02-2007
#
# Checks for snmp.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-ucd-snmp or --without-ucd-snmp.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_SNMP and HAVE_UCDSNMP if a required header files is
# found, and sets @SNMP_LDFLAGS@ and @SNMP_CPPFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# SNMP_LDFLAGS="-lsnmp" SNMP_CPPFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBSNMP_CHECK_CONFIG],
[
  AC_ARG_WITH(ucd-snmp,
    [AC_HELP_STRING([--with-ucd-snmp@<:@=ARG@:>@],
		[use UCD-SNMP package @<:@default=no@:>@, default is to search through a number of common places for the UCD-SNMP files.])
    ],[ if test "$withval" = "no"; then
            want_snmp="no"
            _libsnmp_with="no"
        elif test "$withval" = "yes"; then
            want_snmp="yes"
            _libsnmp_with="yes"
        else
            want_snmp="yes"
            _libsnmp_with=$withval
        fi
     ],[_libsnmp_with=ifelse([$1],,[no],[$1])])

  if test "x$_libsnmp_with" != x"no"; then
       AC_MSG_CHECKING(for UCD-SNMP support)

  	if test "$_libsnmp_with" = "yes"; then
		if test -f /usr/local/ucd-snmp/include/ucd-snmp-config.h; then
			SNMP_INCDIR=/usr/local/ucd-snmp/include/
			SNMP_LIBDIR=/usr/local/ucd-snmp/lib/
		elif test -f /usr/include/ucd-snmp/ucd-snmp-config.h; then
			SNMP_INCDIR=/usr/include
			SNMP_LIBDIR=/usr/lib
		elif test -f /usr/include/ucd-snmp-config.h; then
			SNMP_INCDIR=/usr/include
			SNMP_LIBDIR=/usr/lib
		elif test -f /usr/local/include/ucd-snmp/ucd-snmp-config.h; then
			SNMP_INCDIR=/usr/local/include
			SNMP_LIBDIR=/usr/local/lib
		elif test -f /usr/local/include/ucd-snmp-config.h; then
			SNMP_INCDIR=/usr/local/include
			SNMP_LIBDIR=/usr/local/lib
		else
			found_snmp="no"
                        AC_MSG_RESULT(no)
		fi
   	else
		if test -f $withval/include/ucd-snmp/ucd-snmp-config.h; then
   			SNMP_INCDIR=$withval/include
   			SNMP_LIBDIR=$withval/lib
		elif test -f $withval/include/ucd-snmp-config.h; then
   			SNMP_INCDIR=$withval/include
   			SNMP_LIBDIR=$withval/lib
		else
			found_snmp="no"
                        AC_MSG_RESULT(no)
		fi
   	fi

	if test "x$found_snmp" != "xno" ; then
		found_snmp="yes"
                AC_MSG_RESULT(yes)

                AC_CHECK_LIB(crypto, main,  SNMP_LIBS="$SNMP_LIBS -lcrypto")

                SNMP_CPPFLAGS=-I$SNMP_INCDIR
                SNMP_LDFLAGS="-L$SNMP_LIBDIR $SNMP_LFLAGS -lsnmp $SNMP_LIBS"

                AC_DEFINE(HAVE_UCDSNMP,1,[Define to 1 if UCD-SNMP should be enabled.])
                AC_DEFINE(HAVE_SNMP,1,[Define to 1 if SNMP should be enabled.])
	fi
  fi

  AC_SUBST(SNMP_LDFLAGS)
  AC_SUBST(SNMP_CPPFLAGS)

  unset _libsnmp_with
])dnl
