# LIBUNIXODBC_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Eugene Grigorjev <eugene@zabbix.com>   June-07-2007
#
# Checks for unixodbc.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-unixodbc
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_UNIXODBC if required header files are
# found, and sets @UNIXODBC_LDFLAGS@ and @UNIXODBC_CFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# UNIXODBC_LDFLAGS="-lunixodbc" UNIXODBC_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBUNIXODBC_CHECK_CONFIG],
[
  AC_ARG_WITH(unixodbc,
     [
If you want to use unixODBC library:
AC_HELP_STRING([--with-unixodbc@<:@=ARG@:>@],
		[use ODBC driver against unixODBC package @<:@default=no@:>@, optionally specify full path to odbc_config binary.])
    ],[ if test "x$withval" = "xno"; then
            want_unixodbc="no"
        elif test "x$withval" = "xyes"; then
            want_unixodbc="yes"
        else
            want_unixodbc="yes"
            specified_unixodbc="yes"
            ODBC_CONFIG=$withval
        fi
     ],[want_unixodbc=ifelse([$1],,[no],[$1])])

  if test "x$want_unixodbc" != "xno"; then
	AC_PATH_PROG([ODBC_CONFIG], [odbc_config], [])

	found_unixodbc="yes"

	UNIXODBC_LIBS="-lodbc"

	if test -x "$ODBC_CONFIG"; then
		UNIXODBC_CFLAGS="-I`$ODBC_CONFIG --include-prefix`"
		UNIXODBC_LDFLAGS="-L`$ODBC_CONFIG --lib-prefix`"
	elif test "x$specified_unixodbc" = "xyes"; then
		found_unixodbc="file $ODBC_CONFIG not found or not executable"
	fi

	if test "x$found_unixodbc" = "xyes"; then
		_save_unixodbc_cflags="${CFLAGS}"
		_save_unixodbc_ldflags="${LDFLAGS}"
		_save_unixodbc_libs="${LIBS}"
		CFLAGS="${CFLAGS} ${UNIXODBC_CFLAGS}"
		LDFLAGS="${LDFLAGS} ${UNIXODBC_LDFLAGS}"
		LIBS="${LIBS} ${UNIXODBC_LIBS}"

		AC_CHECK_LIB(odbc, main, , [found_unixodbc="unixODBC library not found"])

		if test "x$found_unixodbc" = "xyes"; then
			AC_CACHE_CHECK([whether unixodbc is usable],
				[libunixodbc_cv_usable],
				[AC_LINK_IFELSE([AC_LANG_PROGRAM([
#include <sql.h>
#include <sqlext.h>
#include <sqltypes.h>
				],[
/* try and use a few common options to force a failure if we are missing symbols or can't link */
SQLRETURN	retcode;

retcode = SQLAllocHandle(SQL_HANDLE_ENV, SQL_NULL_HANDLE, (SQLHENV*)0);
				])],libunixodbc_cv_usable=yes,libunixodbc_cv_usable=no)
			])

			CFLAGS="${_save_unixodbc_cflags}"
			LDFLAGS="${_save_unixodbc_ldflags}"
			LIBS="${_save_unixodbc_libs}"
			unset _save_unixodbc_cflags
			unset _save_unixodbc_ldflags
			unset _save_unixodbc_libs

			if test "$libunixodbc_cv_usable" != "yes"; then
				found_unixodbc="cannot use unixODBC library"
			else
				AC_DEFINE(HAVE_UNIXODBC,1,[Define to 1 if unixUNIXODBC Driver Manager should be used.])
			fi
		fi
	fi
  fi

  AC_SUBST(UNIXODBC_LDFLAGS)
  AC_SUBST(UNIXODBC_CFLAGS)
  AC_SUBST(UNIXODBC_LIBS)

])dnl
