# LIBlibevent_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Alexander Vladishev                      Feb-02-2007
#
# Checks for ldap.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-ldap or --without-ldap.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_libevent if a required header files is
# found, and sets @LIBEVENT_LDFLAGS@ and @LIBEVENT_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBEVENT_TRY_LINK],
[
AC_TRY_LINK(
[
#include <stdlib.h>
#include <event.h>
],
[
	event_init();
],
found_libevent="yes")
])dnl

AC_DEFUN([LIBEVENT_CHECK_CONFIG],
[
	AC_ARG_WITH([libevent],[
If you want to specify libevent installation directories:
AC_HELP_STRING([--with-libevent@<:@=DIR@:>@], [use libevent from given base install directory (DIR), default is to search through a number of common places for the libevent files.])],
		[
			LIBEVENT_CFLAGS="-I$withval/include"
			LIBEVENT_LDFLAGS="-L$withval/lib"
			_libevent_dir_set="yes"
		]
	)

	AC_ARG_WITH([libevent-include],
		AC_HELP_STRING([--with-libevent-include@<:@=DIR@:>@],
			[use libevent include headers from given path.]
		),
		[
			LIBEVENT_CFLAGS="-I$withval"
			_libevent_dir_set="yes"
		]
	)

	AC_ARG_WITH([libevent-lib],
		AC_HELP_STRING([--with-libevent-lib@<:@=DIR@:>@],
			[use libevent libraries from given path.]
		),
		[
			LIBEVENT_LDFLAGS="-L$withval"
			_libevent_dir_set="yes"
		]
	)

	AC_MSG_CHECKING(for libevent support)

	LIBEVENT_LIBS="-levent"

	if test -n "$_libevent_dir_set" -o -f /usr/include/event.h; then
		found_libevent="yes"
	elif test -f /usr/local/include/event.h; then
		LIBEVENT_CFLAGS="-I/usr/local/include"
		LIBEVENT_LDFLAGS="-L/usr/local/lib"
		found_libevent="yes"
	else
		found_libevent="no"
		AC_MSG_RESULT(no)
	fi

	if test "x$found_libevent" = "xyes"; then
		am_save_CFLAGS="$CFLAGS"
		am_save_LDFLAGS="$LDFLAGS"
		am_save_LIBS="$LIBS"

		CFLAGS="$CFLAGS $LIBEVENT_CFLAGS"
		LDFLAGS="$LDFLAGS $LIBEVENT_LDFLAGS"
		LIBS="$LIBS $LIBEVENT_LIBS"

		found_libevent="no"
		LIBEVENT_TRY_LINK([no])

		CFLAGS="$am_save_CFLAGS"
		LDFLAGS="$am_save_LDFLAGS"
		LIBS="$am_save_LIBS"
	fi

	if test "x$found_libevent" = "xyes"; then
		AC_DEFINE([HAVE_LIBEVENT], 1, [Define to 1 if you have the 'libevent' library (-levent)])
		AC_MSG_RESULT(yes)
	else
		LIBEVENT_CFLAGS=""
		LIBEVENT_LDFLAGS=""
		LIBEVENT_LIBS=""
	fi

	AC_SUBST(LIBEVENT_CFLAGS)
	AC_SUBST(LIBEVENT_LDFLAGS)
	AC_SUBST(LIBEVENT_LIBS)
])dnl
