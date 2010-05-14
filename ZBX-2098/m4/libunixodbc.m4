# LIBUNIXODBC_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Eugene Grigorjev <eugene@zabbix.com>   June-07-2007
#
# Checks for unixodbc.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-unixodbc
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_UNIXODBC if a required header files is
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
    [AC_HELP_STRING([--with-unixodbc@<:@=ARG@:>@],
		[use odbc driver against unixODBC package @<:@default=no@:>@, optionally specify path to odbc_config.])
    ],[ if test "$withval" = "no"; then
            want_unixodbc="no"
        elif test "$withval" = "yes"; then
            want_unixodbc="yes"
        else
            want_unixodbc="yes"
            _libodbc_config=$withval
        fi
     ],[want_unixodbc=ifelse([$1],,[no],[$1])])

  if test "x$want_unixodbc" != x"no"; then
	
	if test -z "$_libodbc_config" -o test; then
		AC_PATH_PROG([_libodbc_config], [odbc_config], [no])
	fi

	if test -f $_libodbc_config; then
		UNIXODBC_CFLAGS="-I`${_libodbc_config} --include-prefix`"
		UNIXODBC_LDFLAGS="$LDFLAGS -L`${_libodbc_config} --lib-prefix`"
		found_unixodbc="yes"
	else
		found_unixodbc="no"
	fi

	if test "$found_unixodbc" = "yes"; then

		_save_unixodbc_libs="${LIBS}"
		_save_unixodbc_ldflags="${LDFLAGS}"
		_save_unixodbc_cflags="${CFLAGS}"
		LIBS="${LIBS} ${UNIXODBC_LIBS}"
		LDFLAGS="${LDFLAGS} ${UNIXODBC_LDFLAGS}"
		CFLAGS="${CFLAGS} ${UNIXODBC_CFLAGS}"

		AC_CHECK_LIB(odbc, main,[
				UNIXODBC_LIBS="-lodbc $UNIXODBC_LIBS"
			],[
				AC_MSG_ERROR([Not found libodbc library])
			])

		LIBS="${LIBS} ${UNIXODBC_LIBS}"

		AC_CACHE_CHECK([whether unixodbc is usable],
			[_libunixodbc_usable],
			[AC_LINK_IFELSE(AC_LANG_PROGRAM([
				#include <sql.h>
				#include <sqlext.h>
				#include <sqltypes.h>
			],[
			/* Try and use a few common options to force a failure if we are
			   missing symbols or can't link. */
			SQLRETURN       retcode;
			retcode = SQLAllocHandle(SQL_HANDLE_ENV, SQL_NULL_HANDLE, (SQLHENV*)0);
			]),_libunixodbc_usable=yes,_libunixodbc_usable=no)
		])

		LIBS="${_save_unixodbc_libs}"
		LDFLAGS="${_save_unixodbc_ldflags}"
		CFLAGS="${_save_unixodbc_cflags}"
		unset _save_unixodbc_libs
		unset _save_unixodbc_ldflags
		unset _save_unixodbc_cflags

		if test "$_libunixodbc_usable" != "yes"; then
			AC_MSG_ERROR([Can't use libodbc library])
		fi

		AC_DEFINE(HAVE_UNIXODBC,1,[Define to 1 if unixUNIXODBC Driver Manager should be used.])
	fi
  fi

  AC_SUBST(UNIXODBC_LDFLAGS)
  AC_SUBST(UNIXODBC_CFLAGS)
  AC_SUBST(UNIXODBC_LIBS)

  unset _libunixodbc_with
  unset _libunixodbc_usable
])dnl
