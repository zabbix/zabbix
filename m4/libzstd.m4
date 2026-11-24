# ZSTD_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for libzstd.
#
# This macro #defines HAVE_ZSTD if required header files and library are
# found, and sets @ZSTD_LDFLAGS@, @ZSTD_CFLAGS@ and @ZSTD_LIBS@.

AC_DEFUN([ZSTD_TRY_LINK],
[
found_zstd=$1
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <zstd.h>
]], [[
	size_t b = ZSTD_compressBound(1024);
	(void)b;
]])],[found_zstd="yes"],[])
])dnl

AC_DEFUN([ZSTD_CHECK_CONFIG],
[
	AC_ARG_WITH([zstd],[
If you want to specify zstd installation directories:
AS_HELP_STRING([--with-zstd@<:@=DIR@:>@], [use libzstd from given base install directory (DIR), default is to search common places for zstd files.])],
		[
			if test "x$withval" = "xno"; then
				want_zstd="no"
			else
				want_zstd="yes"
				if test "x$withval" != "xyes"; then
					ZSTD_CFLAGS="-I$withval/include"
					ZSTD_LDFLAGS="-L$withval/lib"
					_zstd_dir_set="yes"
				fi
			fi
		],
		[want_zstd="auto"]
	)

	found_zstd="no"

	if test "x$want_zstd" != "xno"; then
		AC_MSG_CHECKING(for zstd support)
		ZSTD_LIBS="-lzstd"

		if test -n "$_zstd_dir_set" -o -f /usr/include/zstd.h; then
			found_zstd="yes"
		elif test -f /usr/local/include/zstd.h; then
			ZSTD_CFLAGS="-I/usr/local/include"
			ZSTD_LDFLAGS="-L/usr/local/lib"
			found_zstd="yes"
		elif test -f /opt/homebrew/opt/zstd/include/zstd.h; then
			ZSTD_CFLAGS="-I/opt/homebrew/opt/zstd/include"
			ZSTD_LDFLAGS="-L/opt/homebrew/opt/zstd/lib"
			found_zstd="yes"
		fi

		if test "x$found_zstd" = "xyes"; then
			am_save_CFLAGS="$CFLAGS"
			am_save_LDFLAGS="$LDFLAGS"
			am_save_LIBS="$LIBS"

			CFLAGS="$CFLAGS $ZSTD_CFLAGS"
			LDFLAGS="$LDFLAGS $ZSTD_LDFLAGS"
			LIBS="$LIBS $ZSTD_LIBS"

			ZSTD_TRY_LINK([no])

			CFLAGS="$am_save_CFLAGS"
			LDFLAGS="$am_save_LDFLAGS"
			LIBS="$am_save_LIBS"
		fi

		if test "x$found_zstd" = "xyes"; then
			AC_DEFINE([HAVE_ZSTD], 1, [Define to 1 if you have the 'zstd' library (-lzstd)])
			AC_MSG_RESULT(yes)
		else
			AC_MSG_RESULT(no)
			if test "x$want_zstd" = "xyes"; then
				AC_MSG_ERROR([Unable to use libzstd (zstd check failed)])
			fi
			ZSTD_CFLAGS=""
			ZSTD_LDFLAGS=""
			ZSTD_LIBS=""
		fi
	fi

	AC_SUBST(ZSTD_CFLAGS)
	AC_SUBST(ZSTD_LDFLAGS)
	AC_SUBST(ZSTD_LIBS)
])dnl
