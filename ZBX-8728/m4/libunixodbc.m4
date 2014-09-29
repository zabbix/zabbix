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

	unixodbc_error=""

	UNIXODBC_LIBS="-lodbc"

	if test -x "$ODBC_CONFIG"; then
		UNIXODBC_CFLAGS="-I`$ODBC_CONFIG --include-prefix`"
		UNIXODBC_LDFLAGS="-L`$ODBC_CONFIG --lib-prefix`"
	elif test "x$specified_unixodbc" = "xyes"; then
		unixodbc_error="file $ODBC_CONFIG not found or not executable"
	fi

	if test "x$unixodbc_error" = "x"; then
		_save_unixodbc_cflags="${CFLAGS}"
		_save_unixodbc_ldflags="${LDFLAGS}"
		_save_unixodbc_libs="${LIBS}"
		CFLAGS="${CFLAGS} ${UNIXODBC_CFLAGS}"
		LDFLAGS="${LDFLAGS} ${UNIXODBC_LDFLAGS}"
		LIBS="${LIBS} ${UNIXODBC_LIBS}"

		AC_CHECK_LIB(odbc, SQLAllocHandle, ,[unixodbc_error="unixODBC library not found"])

		if test "x$unixodbc_error" = "x"; then
			AC_DEFINE(HAVE_UNIXODBC,1,[Define to 1 if unixUNIXODBC Driver Manager should be used.])
		fi
	fi
  fi

  AC_SUBST(UNIXODBC_LDFLAGS)
  AC_SUBST(UNIXODBC_CFLAGS)
  AC_SUBST(UNIXODBC_LIBS)

])dnl
