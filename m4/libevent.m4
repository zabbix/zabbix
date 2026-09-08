# LIBEVENT_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for libevent.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-libevent or --without-libevent.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_LIBEVENT if a required header files is
# found, and sets @LIBEVENT_LDFLAGS@ and @LIBEVENT_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBEVENT_TRY_LINK],
[
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <event2/event.h>
#include <event2/thread.h>
]], [[
	struct event_base *evb;

	evb = event_base_new();

	evthread_use_pthreads();
	event_base_free(evb);
]])],[found_libevent="yes"],[])
])dnl

AC_DEFUN([LIBEVENT_CHECK_CONFIG],
[
	AC_ARG_WITH([libevent],[
If you want to specify libevent installation directories:
AS_HELP_STRING([--with-libevent@<:@=DIR@:>@], [use libevent from given base install directory (DIR), default is to search through a number of common places for the libevent files.])],
		[
			if test "x$withval" = "xyes"; then
				if test -f /usr/local/include/event2/event.h; then withval=/usr/local; else withval=/usr; fi
			fi

			LIBEVENT_CFLAGS="-I$withval/include"
			LIBEVENT_LDFLAGS="-L$withval/lib"
			_libevent_dir_set="yes"
		]
	)

	AC_ARG_WITH([libevent-include],
		AS_HELP_STRING([--with-libevent-include@<:@=DIR@:>@],
			[use libevent include headers from given path.]
		),
		[
			LIBEVENT_CFLAGS="-I$withval"
			_libevent_dir_set="yes"
		]
	)

	AC_ARG_WITH([libevent-lib],
		AS_HELP_STRING([--with-libevent-lib@<:@=DIR@:>@],
			[use libevent libraries from given path.]
		),
		[
			LIBEVENT_LDFLAGS="-L$withval"
			_libevent_dir_set="yes"
		]
	)

	AC_MSG_CHECKING(for libevent support)

	LIBEVENT_LIBS="-levent_core -levent_extra -levent_pthreads"

	if test -n "$_libevent_dir_set" -o -f /usr/include/event2/event.h; then
		found_libevent="yes"
	elif test -f /usr/local/include/event2/event.h; then
		LIBEVENT_CFLAGS="-I/usr/local/include"
		LIBEVENT_LDFLAGS="-L/usr/local/lib"
		found_libevent="yes"
	else
		m4_ifdef([PKG_PROG_PKG_CONFIG], [
			PKG_PROG_PKG_CONFIG()
			if test -z "$PKG_CONFIG"; then
				AC_MSG_ERROR([pkg-config is required but not found. Please install pkg-config.])
			fi
		], [
			AC_MSG_WARN([pkg-config not found, skipping libevent detection via pkg-config])
		])

		if test -n "$PKG_CONFIG"; then
			LIBEVENT_CFLAGS=`$PKG_CONFIG --cflags libevent`
			LIBEVENT_LDFLAGS=`$PKG_CONFIG --libs-only-L libevent`
			found_libevent="yes"
		fi
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
