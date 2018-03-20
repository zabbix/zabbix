# ZLIB_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for zlib.
#
# This macro #defines HAVE_ZLIB_H if required header files are
# found, and sets @ZLIB_LDFLAGS@ and @ZLIB_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([ZLIB_TRY_LINK],
[
AC_TRY_LINK(
[
#include <zlib.h>
],
[
	z_stream	defstream;
	deflateInit(&defstream, Z_BEST_COMPRESSION);
],
found_zlib="yes")
])dnl

AC_DEFUN([ZLIB_CHECK_CONFIG],
[
	AC_ARG_WITH([zlib],[
If you want to specify zlib installation directories:
AC_HELP_STRING([--with-zlib=DIR], [use zlib from given base install directory (DIR), default is to search through a number of common places for the zlib files.])],
		[
			ZLIB_CFLAGS="-I$withval/include"
			ZLIB_LDFLAGS="-L$withval/lib"
			_zlib_dir_set="yes"
		]
	)

	AC_ARG_WITH([zlib-include],
		AC_HELP_STRING([--with-zlib-include=DIR],
			[use zlib include headers from given path.]
		),
		[
			ZLIB_CFLAGS="-I$withval"
			_zlib_dir_set="yes"
		]
	)

	AC_ARG_WITH([zlib-lib],
		AC_HELP_STRING([--with-zlib-lib=DIR],
			[use zlib libraries from given path.]
		),
		[
			ZLIB_LDFLAGS="-L$withval"
			_zlib_dir_set="yes"
		]
	)

	AC_MSG_CHECKING(for zlib support)

	ZLIB_LIBS="-lz"

	if test -n "$_zlib_dir_set" -o -f /usr/include/zlib.h; then
		found_zlib="yes"
	elif test -f /usr/local/include/zlib.h; then
		ZLIB_CFLAGS="-I/usr/local/include"
		ZLIB_LDFLAGS="-L/usr/local/lib"
		found_zlib="yes"
	elif test -f /usr/pkg/include/zlib.h; then
		ZLIB_CFLAGS="-I/usr/pkg/include"
		ZLIB_LDFLAGS="-L/usr/pkg/lib"
		found_zlib="yes"
	else
		found_zlib="no"
		AC_MSG_RESULT(no)
	fi

	if test "x$found_zlib" = "xyes"; then
		am_save_CFLAGS="$CFLAGS"
		am_save_LDFLAGS="$LDFLAGS"
		am_save_LIBS="$LIBS"

		CFLAGS="$CFLAGS $ZLIB_CFLAGS"
		LDFLAGS="$LDFLAGS $ZLIB_LDFLAGS"
		LIBS="$LIBS $ZLIB_LIBS"

		found_zlib="no"
		ZLIB_TRY_LINK([no])

		CFLAGS="$am_save_CFLAGS"
		LDFLAGS="$am_save_LDFLAGS"
		LIBS="$am_save_LIBS"
	fi

	if test "x$found_zlib" = "xyes"; then
		AC_DEFINE([HAVE_ZLIB_H], 1, [Define to 1 if you have the 'zlib' library (-lz)])
		AC_MSG_RESULT(yes)
	else
		ZLIB_CFLAGS=""
		ZLIB_LDFLAGS=""
		ZLIB_LIBS=""
	fi

	AC_SUBST(ZLIB_CFLAGS)
	AC_SUBST(ZLIB_LDFLAGS)
	AC_SUBST(ZLIB_LIBS)
])dnl
