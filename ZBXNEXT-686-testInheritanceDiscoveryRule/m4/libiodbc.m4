# LIBIODBC_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Eugene Grigorjev <eugene@zabbix.com>   June-07-2007
#
# Checks for iodbc.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-iodbc
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_IODBC if a required header files is
# found, and sets @IODBC_LDFLAGS@ and @IODBC_CFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# IODBC_LDFLAGS="-liodbc" IODBC_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBIODBC_CHECK_CONFIG],
[
  AC_ARG_WITH(iodbc,[
What ODBC driver do you want to use (please select only one):
AC_HELP_STRING([--with-iodbc@<:@=ARG@:>@],
		[use odbc driver against iODBC package @<:@default=no@:>@, default is to search through a number of common places for the IODBC files.])
    ],[ if test "$withval" = "no"; then
            want_iodbc="no"
            _libiodbc_with="no"
        elif test "$withval" = "yes"; then
            want_iodbc="yes"
            _libiodbc_with="yes"
        else
            want_iodbc="yes"
            _libiodbc_with=$withval
        fi
     ],[_libiodbc_with=ifelse([$1],,[no],[$1])])

  if test "x$_libiodbc_with" != x"no"; then
	AC_MSG_CHECKING(for iODBC support)

	if test "$_libiodbc_with" = "yes"; then
		if test -f /usr/include/sql.h; then
			_libiodbc_with="/usr"
		else
			_libiodbc_with="/usr/local"
		fi
	else
		if echo "$_libiodbc_with" | grep -v '^/'; then
			_libiodbc_with="$PWD/$_libiodbc_with"
		fi
	fi

	if test -f "$_libiodbc_with/include/sql.h"; then
		IODBC_CFLAGS="-I$_libiodbc_with/include"
		IODBC_LDFLAGS="-L$_libiodbc_with/lib"
		IODBC_LIBS="-liodbc"

		_save_iodbc_cflags="$CFLAGS"
		_save_iodbc_ldflags="$LDFLAGS"
		_save_iodbc_libs="$LIBS"
		CFLAGS="$CFLAGS $IODBC_CFLAGS"
		LDFLAGS="$LDFLAGS $IODBC_LDFLAGS"
		LIBS="$LIBS $IODBC_LIBS"

		AC_CHECK_LIB(iodbc, main, , [AC_MSG_ERROR([Not found libiodbc library])])

		CFLAGS="$_save_iodbc_cflags"
		LDFLAGS="$_save_iodbc_ldflags"
		LIBS="$_save_iodbc_libs"
		unset _save_iodbc_cflags
		unset _save_iodbc_ldflags
		unset _save_iodbc_libs

		AC_DEFINE(HAVE_IODBC, 1, [Define to 1 if iODBC Driver Manager should be used.])

		found_iodbc="yes"
		AC_MSG_RESULT(yes)

	else
		found_iodbc="no"
		AC_MSG_RESULT(no)
	fi
  fi

  AC_SUBST(IODBC_LDFLAGS)
  AC_SUBST(IODBC_CFLAGS)
  AC_SUBST(IODBC_LIBS)

  unset _libiodbc_with
])dnl
