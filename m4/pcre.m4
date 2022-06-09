# LIBPCRE_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for pcre.
#
# This macro #defines HAVE_PCRE2_H if required header files are
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
	want_libpcre=no
	found_libpcre=no
	libpcre_dir=""
	libpcre_include_dir=""
	libpcre_lib_dir=""

	#
	# process --with-* flags
	#

	AC_ARG_WITH([libpcre],[
If you want to specify libpcre installation directories:
AC_HELP_STRING([--with-libpcre@<:@=DIR@:>@], [use libpcre from given base install directory (DIR), default is to search through a number of common places for the libpcre files.])],
	[
		if test "$withval" != "no"; then
			want_libpcre=yes
			if test "$withval" != "yes"; then
				libpcre_dir="$withval"
			fi
		fi
	])

	AC_ARG_WITH([libpcre-include], AC_HELP_STRING([--with-libpcre-include@<:@=DIR@:>@], [use libpcre include headers from given path.]), [
		want_libpcre="yes"
		libpcre_include_dir="$withval"
		if ! test -d "$libpcre_include_dir"; then
			AC_MSG_ERROR([cannot find $libpcre_include_dir directory])
		fi
		if ! test -f "$libpcre_include_dir/pcre.h"; then
			AC_MSG_ERROR([cannot find $libpcre_include_dir/pcre.h])
		fi
	])

	AC_ARG_WITH([libpcre-lib], AC_HELP_STRING([--with-libpcre-lib@<:@=DIR@:>@], [use libpcre libraries from given path.]), [
		want_libpcre="yes"
		libpcre_lib_dir="$withval"
		if ! test -d "$libpcre_lib_dir"; then
			AC_MSG_ERROR([cannot find $libpcre_lib_dir directory])
		fi
	])


	#
	# find actual compiler flags and include paths
	#

	if test "$1" != "flags-only"; then
		#
		# try using pkg-config if available
		#

		AC_REQUIRE([PKG_PROG_PKG_CONFIG])
		m4_ifdef([PKG_PROG_PKG_CONFIG], [PKG_PROG_PKG_CONFIG()], [:])

		if test -n "$PKG_CONFIG"; then
			m4_pattern_allow([^PKG_CONFIG_LIBDIR$])

			if test -n "$libpcre_dir"; then
				export PKG_CONFIG_LIBDIR="$libpcre_dir/lib/pkgconfig"
			elif test -n "$libpcre_lib_dir"; then
				export PKG_CONFIG_LIBDIR="$libpcre_lib_dir/pkgconfig"
			fi

			if test -n "$libpcre_dir"; then
				AC_RUN_LOG([$PKG_CONFIG --exists --print-errors libpcre]) || {
					AC_MSG_ERROR([cannot find libpcre pkg-config package in $libpcre_dir/lib/pkgconfig])
				}
			fi

			if test -z "$libpcre_include_dir"; then
				LIBPCRE_CFLAGS=`$PKG_CONFIG --cflags libpcre`
			fi

			if test "x$enable_static_libs" = "xyes" && test "x$static_linking_support" = "xno"; then
				if test -z "$libpcre_dir"; then
					AC_MSG_ERROR([libpcre directory must be given explicitly if static libs are used])
				fi
				LIBPCRE_LIBS="$libpcre_dir/lib/libpcre.a"
			else
				LIBPCRE_LDFLAGS=`$PKG_CONFIG --libs-only-L libpcre`
				LIBPCRE_LIBS=`$PKG_CONFIG --libs-only-l libpcre`
			fi

			unset PKG_CONFIG_LIBDIR

			found_libpcre="yes"
		else
			#
			# no pkg-config, trying to guess
			#

			AC_MSG_WARN([proceeding without pkg-config])

			LIBPCRE_LIBS="-lpcre"

			if test -n "$libpcre_lib_dir"; then
				LIBPCRE_LDFLAGS="-L$libpcre_lib_dir"
				found_libpcre="yes"
			elif test -n "$libpcre_dir"; then
				if ! test -d "$libpcre_dir/include"; then
					AC_MSG_ERROR([cannot find "$libpcre_dir/include" directory])
				fi

				if test -z "$libpcre_include_dir"; then
					if ! test -f "$libpcre_dir/include/pcre.h"; then
						AC_MSG_ERROR([cannot find "$libpcre_dir/include/pcre.h" file])
					fi
				fi

				if ! test -d "$libpcre_dir/lib"; then
					AC_MSG_ERROR([cannot find "$libpcre_dir/lib" directory])
				fi

				LIBPCRE_CFLAGS="-I$libpcre_dir/include"
				LIBPCRE_LDFLAGS="-L$libpcre_dir/lib"

				found_libpcre="yes"
			elif test -f /usr/include/pcre.h; then
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
			fi
		fi

		if test -n "$libpcre_include_dir"; then
			LIBPCRE_CFLAGS="-I$libpcre_include_dir"
		fi

		if test "x$enable_static" = "xyes"; then
			LIBPCRE_LIBS=" $LIBPCRE_LIBS -lpthread"
		elif test "x$enable_static_libs" = "xyes" && test "x$static_linking_support" != "xno"; then
			LIBPCRE_LIBS="$LIBPCRE_LDFLAGS ${static_linking_support}static $LIBPCRE_LIBS ${static_linking_support}dynamic"
			LIBPCRE_LDFLAGS=""
		fi

		AC_MSG_CHECKING([for libpcre support])

		if test "x$found_libpcre" = "xyes"; then
			am_save_CFLAGS="$CFLAGS"
			am_save_LDFLAGS="$LDFLAGS"
			am_save_LIBS="$LIBS"

			CFLAGS="$CFLAGS $LIBPCRE_CFLAGS"
			LDFLAGS="$LDFLAGS $LIBPCRE_LDFLAGS"
			LIBS="$LIBS $LIBPCRE_LIBS"

			found_libpcre="no"
			LIBPCRE_TRY_LINK([no])

			if test "x$found_libpcre" = "xyes"; then
				AC_MSG_RESULT(yes)
			else
				AC_MSG_RESULT(no)
				if test "$1" = "mandatory"; then
					AC_MSG_NOTICE([CFLAGS: $CFLAGS])
					AC_MSG_NOTICE([LDFLAGS: $LDFLAGS])
					AC_MSG_NOTICE([LIBS: $LIBS])
					AC_MSG_ERROR([cannot build with libpcre])
				else
					LIBPCRE_CFLAGS=""
					LIBPCRE_LDFLAGS=""
					LIBPCRE_LIBS=""
				fi
			fi

			CFLAGS="$am_save_CFLAGS"
			LDFLAGS="$am_save_LDFLAGS"
			LIBS="$am_save_LIBS"
		else
			AC_MSG_RESULT(no)
		fi

		AC_SUBST(LIBPCRE_CFLAGS)
		AC_SUBST(LIBPCRE_LDFLAGS)
		AC_SUBST(LIBPCRE_LIBS)
	fi
])dnl
