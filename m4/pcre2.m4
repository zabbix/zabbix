# LIBPCRE2_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for pcre2.
#
# This macro #defines HAVE_PCRE2_H if required header files are
# found, and sets @LIBPCRE2_LDFLAGS@ and @LIBPCRE2_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBPCRE2_TRY_LINK],
[
AC_TRY_LINK(
[
#define PCRE2_CODE_UNIT_WIDTH 8
#include <pcre2.h>
],
[
	int error = 0;
	PCRE2_SIZE error_offset = 0;
	pcre2_code *regexp = pcre2_compile("test", PCRE2_ZERO_TERMINATED, PCRE2_UTF, &error, &error_offset, NULL);
	pcre2_code_free(regexp);
],
found_libpcre2="yes")
])dnl

AC_DEFUN([LIBPCRE2_CHECK_CONFIG],
[
	AC_ARG_WITH([libpcre2],[
If you want to specify libpcre2 installation directories:
AC_HELP_STRING([--with-libpcre2@<:@=DIR@:>@], [use libpcre2 from given base install directory (DIR), default is to search through a number of common places for the libpcre2 files.])],
		[
			if test "$withval" = "yes"; then
				want_libpcre2=yes
				if test -f /usr/local/include/pcre2.h; then
					withval="/usr/local"
				else
					withval="/usr"
				fi
			else
				want_libpcre2=no
				_libpcre2_dir_lib="$withval/lib"
			fi
			_libpcre2_dir="$withval"
			test "x$withval" = "xyes" && withval=/usr
			LIBPCRE2_CFLAGS="-I$withval/include"
			LIBPCRE2_LDFLAGS="-L$withval/lib"
			_libpcre2_dir_set="yes"
		]
	)

	AC_ARG_WITH([libpcre2-include],
		AC_HELP_STRING([--with-libpcre2-include@<:@=DIR@:>@],
			[use libpcre2 include headers from given path.]
		),
		[
			LIBPCRE2_CFLAGS="-I$withval"
			_libpcre2_dir_set="yes"
		]
	)

	AC_ARG_WITH([libpcre2-lib],
		AC_HELP_STRING([--with-libpcre2-lib@<:@=DIR@:>@],
			[use libpcre2 libraries from given path.]
		),
		[
			_libpcre2_dir="$withval"
			_libpcre2_dir_lib="$withval"
			LIBPCRE2_LDFLAGS="-L$withval"
			_libpcre2_dir_set="yes"
		]
	)

	if test "x$enable_static_libs" = "xyes"; then
		AC_REQUIRE([PKG_PROG_PKG_CONFIG])
		PKG_PROG_PKG_CONFIG()
		test -z "$PKG_CONFIG" -a -z "$_libpcre2_dir_lib" && AC_MSG_ERROR([Not found pkg-config library])
		m4_pattern_allow([^PKG_CONFIG_LIBDIR$])
	fi

	AC_MSG_CHECKING(for libpcre2 support)

	LIBPCRE2_LIBS="-lpcre2-8"

	if test "x$enable_static" = "xyes"; then
		LIBPCRE2_LIBS=" $LIBPCRE2_LIBS -lpthread"
	elif test "x$enable_static_libs" = "xyes" -a -z "$PKG_CONFIG"; then
		LIBPCRE2_LIBS="$_libpcre2_dir_lib/libpcre2.a"
	elif test "x$enable_static_libs" = "xyes"; then

		test "x$static_linking_support" = "xno" -a -z "$_libpcre2_dir_lib" && AC_MSG_ERROR(["Compiler not support statically linked libs from default folders"])

		if test -z "$_libpcre2_dir_lib"; then
			PKG_CHECK_EXISTS(libpcre2,[
				LIBPCRE2_LIBS=`$PKG_CONFIG --static --libs libpcre2`
			],[
				AC_MSG_ERROR([Not found libpcre2 package])
			])
		else
			AC_RUN_LOG([PKG_CONFIG_LIBDIR="$_libpcre2_dir_lib/pkgconfig" $PKG_CONFIG --exists --print-errors libpcre2]) || AC_MSG_ERROR(["Not found libpcre2 package in $_libpcre2_dir/lib/pkgconfig"])
			LIBPCRE2_LIBS=`PKG_CONFIG_LIBDIR="$_libpcre2_dir_lib/pkgconfig" $PKG_CONFIG --static --libs libpcre2`
			test -z "$LIBPCRE2_LIBS" && LIBPCRE2_LIBS=`PKG_CONFIG_LIBDIR="$_libpcre2_dir_lib/pkgconfig" $PKG_CONFIG --libs libpcre2`
		fi

		if test "x$static_linking_support" = "xno"; then
			LIBPCRE2_LIBS=`echo "$LIBPCRE2_LIBS"|sed "s|-lpcre2-8|$_libpcre2_dir_lib/libpcre2.a|g"`
		else
			LIBPCRE2_LIBS=`echo "$LIBPCRE2_LIBS"|sed "s/-lpcre2-8/${static_linking_support}static -lpcre2-8 ${static_linking_support}dynamic/g"`
		fi
	fi

	if test -n "$_libpcre2_dir_set" -o -f /usr/include/pcre2.h; then
		found_libpcre2="yes"
	elif test -f /usr/local/include/pcre2.h; then
		LIBPCRE2_CFLAGS="-I/usr/local/include"
		LIBPCRE2_LDFLAGS="-L/usr/local/lib"
		found_libpcre2="yes"
	elif test -f /usr/pkg/include/pcre2.h; then
		LIBPCRE2_CFLAGS="-I/usr/pkg/include"
		LIBPCRE2_LDFLAGS="-L/usr/pkg/lib"
		LIBPCRE2_LDFLAGS="$LIBPCRE2_LDFLAGS -Wl,-R/usr/pkg/lib"
		found_libpcre2="yes"
	elif test -f /opt/csw/include/pcre2.h; then
		LIBPCRE2_CFLAGS="-I/opt/csw/include"
		LIBPCRE2_LDFLAGS="-L/opt/csw/lib"
		if $(echo "$CFLAGS"|grep -q -- "-m64") ; then
			LIBPCRE2_LDFLAGS="$LIBPCRE2_LDFLAGS/64 -Wl,-R/opt/csw/lib/64"
		else
			LIBPCRE2_LDFLAGS="$LIBPCRE2_LDFLAGS -Wl,-R/opt/csw/lib"
		fi
		found_libpcre2="yes"
	else
		found_libpcre2="no"
		AC_MSG_RESULT(no)
	fi

	if test "x$found_libpcre2" = "xyes"; then
		am_save_CFLAGS="$CFLAGS"
		am_save_LDFLAGS="$LDFLAGS"
		am_save_LIBS="$LIBS"

		CFLAGS="$CFLAGS $LIBPCRE2_CFLAGS"
		LDFLAGS="$LDFLAGS $LIBPCRE2_LDFLAGS"
		LIBS="$LIBS $LIBPCRE2_LIBS"

		found_libpcre2="no"
		LIBPCRE2_TRY_LINK([no])

		CFLAGS="$am_save_CFLAGS"
		LDFLAGS="$am_save_LDFLAGS"
		LIBS="$am_save_LIBS"
	fi

	if test "x$found_libpcre2" = "xyes"; then
		AC_MSG_RESULT(yes)
	else
		LIBPCRE2_CFLAGS=""
		LIBPCRE2_LDFLAGS=""
		LIBPCRE2_LIBS=""
	fi

	AC_SUBST(LIBPCRE2_CFLAGS)
	AC_SUBST(LIBPCRE2_LDFLAGS)
	AC_SUBST(LIBPCRE2_LIBS)
])dnl
