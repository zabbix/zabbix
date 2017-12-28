# LIBPCRE_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for pcre.
#
# This macro #defines HAVE_PCREPOSIX_H if required header files are
# found, and sets @LIBPCRE_LDFLAGS@ and @LIBPCRE_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBPCRE_TRY_LINK],
[
AC_TRY_LINK(
[
#include <pcreposix.h>
],
[
	regex_t	re = {0};

	regcomp(&re, "test", 0);
	regfree(&re);
],
found_libpcre="yes")
])dnl

AC_DEFUN([LIBPCRE_CHECK_CONFIG],
[
	AC_ARG_WITH([libpcre],[
If you want to specify libpcre installation directories:
AC_HELP_STRING([--with-libpcre@<:@=DIR@:>@], [use libpcre from given base install directory (DIR), default is to search through a number of common places for the libpcre files.])],
		[
			LIBPCRE_CFLAGS="-I$withval/include"
			LIBPCRE_LDFLAGS="-L$withval/lib"
			_libpcre_dir_set="yes"
		]
	)

	AC_ARG_WITH([libpcre-include],
		AC_HELP_STRING([--with-libpcre-include@<:@=DIR@:>@],
			[use libpcre include headers from given path.]
		),
		[
			LIBPCRE_CFLAGS="-I$withval"
			_libpcre_dir_set="yes"
		]
	)

	AC_ARG_WITH([libpcre-lib],
		AC_HELP_STRING([--with-libpcre-lib@<:@=DIR@:>@],
			[use libpcre libraries from given path.]
		),
		[
			LIBPCRE_LDFLAGS="-L$withval"
			_libpcre_dir_set="yes"
		]
	)

	AC_MSG_CHECKING(for libpcre support)

	LIBPCRE_LIBS="-lpcreposix -lpcre"

	if test "x$enable_static" = "xyes"; then
		LIBPCRE_LIBS=" $LIBPCRE_LIBS -lpthread"
	fi

	if test -n "$_libpcre_dir_set" -o -f /usr/include/pcreposix.h; then
		found_libpcre="yes"
	elif test -f /usr/local/include/pcreposix.h; then
		LIBPCRE_CFLAGS="-I/usr/local/include"
		LIBPCRE_LDFLAGS="-L/usr/local/lib"
		found_libpcre="yes"
	elif test -f /usr/pkg/include/pcreposix.h; then
		LIBPCRE_CFLAGS="-I/usr/pkg/include"
		LIBPCRE_LDFLAGS="-L/usr/pkg/lib"
		found_libpcre="yes"
	else
		found_libpcre="no"
		AC_MSG_RESULT(no)
	fi

	if test "x$found_libpcre" = "xyes"; then
		am_save_CFLAGS="$CFLAGS"
		am_save_LDFLAGS="$LDFLAGS"
		am_save_LIBS="$LIBS"

		CFLAGS="$CFLAGS $LIBPCRE_CFLAGS"
		LDFLAGS="$LDFLAGS $LIBPCRE_LDFLAGS"
		LIBS="$LIBS $LIBPCRE_LIBS"

		found_libpcre="no"
		LIBPCRE_TRY_LINK([no])

		CFLAGS="$am_save_CFLAGS"
		LDFLAGS="$am_save_LDFLAGS"
		LIBS="$am_save_LIBS"
	fi

	if test "x$found_libpcre" = "xyes"; then
		AC_DEFINE([HAVE_PCREPOSIX_H], 1, [Define to 1 if you have the 'libpcre' library (-lpcreposix -lpcre)])
		AC_MSG_RESULT(yes)
	else
		LIBPCRE_CFLAGS=""
		LIBPCRE_LDFLAGS=""
		LIBPCRE_LIBS=""
	fi

	AC_SUBST(LIBPCRE_CFLAGS)
	AC_SUBST(LIBPCRE_LDFLAGS)
	AC_SUBST(LIBPCRE_LIBS)
])dnl
