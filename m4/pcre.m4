# LIBPCRE_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for pcre.
#
# This macro #defines HAVE_PCRE_H if required header files are
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
#include <pcre.h>
],
[
	const char* error = NULL;
	int error_offset = -1;
	pcre *regexp = pcre_compile("test", PCRE_UTF8, &error, &error_offset, NULL);
	pcre_free(regexp);
],
found_libpcre="yes")
])dnl

AC_DEFUN([LIBPCRE_CHECK_CONFIG],
[
	AC_ARG_WITH([libpcre],[
If you want to specify libpcre installation directories:
AC_HELP_STRING([--with-libpcre@<:@=DIR@:>@], [use libpcre from given base install directory (DIR), default is to search through a number of common places for the libpcre files.])],
		[
			if test "$withval" = "yes"; then
				want_libpcre=yes
				if test -f /usr/local/include/pcre.h; then
					withval="/usr/local"
				else
					withval="/usr"
				fi
			else
				want_libpcre=no
				_libpcre_dir_lib="$withval/lib"
			fi
			_libpcre_dir="$withval"
			test "x$withval" = "xyes" && withval=/usr
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
			_libpcre_dir="$withval"
			_libpcre_dir_lib="$withval"
			LIBPCRE_LDFLAGS="-L$withval"
			_libpcre_dir_set="yes"
		]
	)

	if test "x$enable_static_libs" = "xyes"; then
		AC_REQUIRE([PKG_PROG_PKG_CONFIG])
		PKG_PROG_PKG_CONFIG()
		test -z "$PKG_CONFIG" -a -z "$_libpcre_dir_lib" && AC_MSG_ERROR([Not found pkg-config library])
		m4_pattern_allow([^PKG_CONFIG_LIBDIR$])
	fi

	AC_MSG_CHECKING(for libpcre support)

	LIBPCRE_LIBS="-lpcre"

	if test "x$enable_static" = "xyes"; then
		LIBPCRE_LIBS=" $LIBPCRE_LIBS -lpthread"
	elif test "x$enable_static_libs" = "xyes" -a -z "$PKG_CONFIG"; then
		LIBPCRE_LIBS="$_libpcre_dir_lib/libpcre.a"
	elif test "x$enable_static_libs" = "xyes"; then

		test "x$static_linking_support" = "xno" -a -z "$_libpcre_dir_lib" && AC_MSG_ERROR(["Compiler not support statically linked libs from default folders"])

		if test -z "$_libpcre_dir_lib"; then
			PKG_CHECK_EXISTS(libpcre,[
				LIBPCRE_LIBS=`$PKG_CONFIG --static --libs libpcre`
			],[
				AC_MSG_ERROR([Not found libpcre package])
			])
		else
			AC_RUN_LOG([PKG_CONFIG_LIBDIR="$_libpcre_dir_lib/pkgconfig" $PKG_CONFIG --exists --print-errors libpcre]) || AC_MSG_ERROR(["Not found libpcre package in $_libpcre_dir/lib/pkgconfig"])
			LIBPCRE_LIBS=`PKG_CONFIG_LIBDIR="$_libpcre_dir_lib/pkgconfig" $PKG_CONFIG --static --libs libpcre`
			test -z "$LIBPCRE_LIBS" && LIBPCRE_LIBS=`PKG_CONFIG_LIBDIR="$_libpcre_dir_lib/pkgconfig" $PKG_CONFIG --libs libpcre`
		fi

		if test "x$static_linking_support" = "xno"; then
			LIBPCRE_LIBS=`echo "$LIBPCRE_LIBS"|sed "s|-lpcre|$_libpcre_dir_lib/libpcre.a|g"`
		else
			LIBPCRE_LIBS=`echo "$LIBPCRE_LIBS"|sed "s/-lpcre/${static_linking_support}static -lpcre ${static_linking_support}dynamic/g"`
		fi
	fi

	if test -n "$_libpcre_dir_set" -o -f /usr/include/pcre.h; then
		found_libpcre="yes"
	elif test -f /usr/local/include/pcre.h; then
		LIBPCRE_CFLAGS="-I/usr/local/include"
		LIBPCRE_LDFLAGS="-L/usr/local/lib"
		found_libpcre="yes"
	elif test -f /usr/pkg/include/pcre.h; then
		LIBPCRE_CFLAGS="-I/usr/pkg/include"
		LIBPCRE_LDFLAGS="-L/usr/pkg/lib"
		LIBPCRE_LDFLAGS="$LIBPCRE_LDFLAGS -Wl,-R/usr/pkg/lib"
		found_libpcre="yes"
	elif test -f /opt/csw/include/pcre.h; then
		LIBPCRE_CFLAGS="-I/opt/csw/include"
		LIBPCRE_LDFLAGS="-L/opt/csw/lib"
		if $(echo "$CFLAGS"|grep -q -- "-m64") ; then
			LIBPCRE_LDFLAGS="$LIBPCRE_LDFLAGS/64 -Wl,-R/opt/csw/lib/64"
		else
			LIBPCRE_LDFLAGS="$LIBPCRE_LDFLAGS -Wl,-R/opt/csw/lib"
		fi
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
