# LIBSQLORA8_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Eugene Grigorjev <eugene@zabbix.com>   Feb-02-2007
#
# Checks for Sqlora8.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-sqlora8 or --without-sqlora8
# or --with-oracle or --without-oracle. If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_SQLORA8 if a required header files is
# found, and sets @SQLORA8_LDFLAGS@ and @SQLORA8_CPPFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# SQLORA8_LDFLAGS="-lsqlora8" SQLORA8_CPPFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBSQLORA8_CHECK_CONFIG],
[
  AC_ARG_WITH(oracle,
    [
What DBMS do you want to use (please select only one):
AC_HELP_STRING([--with-oracle@<:@=ARG@:>@],
		[use Sqlora8 library @<:@default=no@:>@, default is to search through a number of common places for the Sqlora8 files.])],[ if test "$withval" = "no"; then
            want_sqlora8="no"
            _libsqlora8_with="no"
        elif test "$withval" = "yes"; then
            want_sqlora8="yes"
            _libsqlora8_with="yes"
        else
            want_sqlora8="yes"
            _libsqlora8_with=$withval
        fi
     ],[_libsqlora8_with=ifelse([$1],,[no],[$1])])

  if test "x$want_sqlora8" = "x" ; then
	  AC_ARG_WITH(sqlora8,
	    [AC_HELP_STRING([--with-sqlora8@<:@=ARG@:>@],
			[use Sqlora8 library @<:@default=no@:>@, same as --with-oracle.])],[ if test "$withval" = "no"; then
		    want_sqlora8="no"
		    _libsqlora8_with="no"
		elif test "$withval" = "yes"; then
		    want_sqlora8="yes"
		    _libsqlora8_with="yes"
		else
		    want_sqlora8="yes"
		    _libsqlora8_with=$withval
		fi
	     ],[_libsqlora8_with=ifelse([$1],,[no],[$1])])
  fi

  if test "x$_libsqlora8_with" != x"no"; then
	AC_MSG_CHECKING([for Oracle support])

	if test "$_libsqlora8_with" = "yes"; then
		if test -f /usr/include/sqlora.h; then
			SQLORA8_INCDIR=/usr
			SQLORA8_LIBDIR=/usr/lib
		elif test -f /usr/lib/libsqlora8/include/sqlora.h; then
			SQLORA8_INCDIR=/usr/lib/libsqlora8
			SQLORA8_LIBDIR=/usr/lib
		elif test -f $SQLORA8_HOME/lib/sqlora8/include/sqlora.h; then
			SQLORA8_INCDIR=$SQLORA8_HOME/lib/sqlora8
			SQLORA8_LIBDIR=$SQLORA8_HOME/lib
		elif test -f $SQLORA8_HOME/include/sqlora.h; then
			SQLORA8_INCDIR=${SQLORA8_HOME}
			SQLORA8_LIBDIR=${SQLORA8_HOME}/lib
		else
			found_sqlora8="no"
			AC_MSG_RESULT([no])
		fi
	else
		if test -f $_libsqlora8_with/include/sqlora.h; then
			SQLORA8_INCDIR=$_libsqlora8_with
			SQLORA8_LIBDIR=$_libsqlora8_with/lib
		elif test -f $_libsqlora8_with/lib/libsqlora8/include/sqlora.h; then
			SQLORA8_INCDIR=$_libsqlora8_with/lib/libsqlora8
			SQLORA8_LIBDIR=$_libsqlora8_with/lib
		elif test -f $_libsqlora8_with/libsqlora8/include/sqlora.h; then
			SQLORA8_INCDIR=$_libsqlora8_with/libsqlora8
			SQLORA8_LIBDIR=$_libsqlora8_with
		else
			found_sqlora8="no"
			AC_MSG_RESULT([no])
		fi
	fi

	if test "x$found_sqlora8" != "xno" ; then
		found_sqlora8="yes"
		AC_MSG_RESULT([yes])

		SQLORA8_CPPFLAGS="-I$SQLORA8_INCDIR/include -I$SQLORA8_INCDIR/lib/libsqlora8/include"
		SQLORA8_LDFLAGS="-L$SQLORA8_LIBDIR -lsqlora8"

		AC_DEFINE(HAVE_SQLORA8,1,[Define to 1 if SQLORA8 library should be enabled.])
	fi
  fi

  AC_SUBST(SQLORA8_CPPFLAGS)
  AC_SUBST(SQLORA8_LDFLAGS)

  unset _libsqlora8_with
])dnl
