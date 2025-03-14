# ARES_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for c-ares.
#
# This macro #defines HAVE_ARES_H if required header files are
# found, and sets @ARES_LDFLAGS@ and @ARES_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([ARES_TRY_LINK],
[
found_ares=$1
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <ares.h>
#include "string.h"
]], [[
	struct ares_channeldata		*channel;
	struct ares_addrinfo_hints	hints;

	ares_library_init(ARES_LIB_INIT_ALL);
	ares_getaddrinfo(channel, "localhost", NULL, &hints, NULL, NULL);
]])],[found_ares="yes"],[])
])dnl

AC_DEFUN([ARES_CHECK_CONFIG],
[
	want_ares="no"
	AC_ARG_WITH([ares],[
If you want to use c-ares library:
AS_HELP_STRING([--with-ares@<:@=ARG@:>@], [use c-ares library @<:@default=no@:>@,])],
		[
			if test "x$withval" = "xyes"; then
				want_ares="yes"
			fi
		]
	)

	AC_ARG_WITH([ares-include],
		AS_HELP_STRING([--with-ares-include=DIR],
			[use c-ares include headers from given path.]
		),
		[
			ARES_CFLAGS="-I$withval"
			_ares_dir_set="yes"
			want_ares="yes"
		]
	)

	AC_ARG_WITH([ares-lib],
		AS_HELP_STRING([--with-ares-lib=DIR],
			[use c-ares libraries from given path.]
		),
		[
			ARES_LDFLAGS="-L$withval"
			_ares_dir_set="yes"
			want_ares="yes"
		]
	)

	if test "x$want_ares" != "xno"; then
		AC_MSG_CHECKING(for ares support)

		ARES_LIBS="-lcares"

		if test -n "$_ares_dir_set" -o -f /usr/include/ares.h; then
			found_ares="yes"
		elif test -f /usr/local/include/ares.h; then
			ARES_CFLAGS="-I/usr/local/include"
			ARES_LDFLAGS="-L/usr/local/lib"
			found_ares="yes"
		elif test -f /usr/pkg/include/ares.h; then
			ARES_CFLAGS="-I/usr/pkg/include"
			ARES_LDFLAGS="-L/usr/pkg/lib"
			found_ares="yes"
		else
			found_ares="no"
			AC_MSG_RESULT(no)
		fi

		if test "x$found_ares" = "xyes"; then
			am_save_CFLAGS="$CFLAGS"
			am_save_LDFLAGS="$LDFLAGS"
			am_save_LIBS="$LIBS"

			CFLAGS="$CFLAGS $ARES_CFLAGS"
			LDFLAGS="$LDFLAGS $ARES_LDFLAGS"
			LIBS="$LIBS $ARES_LIBS"

			ARES_TRY_LINK([no])
			AC_CHECK_FUNCS([ares_reinit])

			CFLAGS="$am_save_CFLAGS"
			LDFLAGS="$am_save_LDFLAGS"
			LIBS="$am_save_LIBS"
		fi

		if test "x$found_ares" = "xyes"; then
			AC_DEFINE([HAVE_ARES], 1, [Define to 1 if you have the 'c-ares' library (-lcares)])
			AC_MSG_RESULT(yes)
		else
			ARES_CFLAGS=""
			ARES_LDFLAGS=""
			ARES_LIBS=""
		fi

		AC_SUBST(ARES_CFLAGS)
		AC_SUBST(ARES_LDFLAGS)
		AC_SUBST(ARES_LIBS)
	fi
])dnl
