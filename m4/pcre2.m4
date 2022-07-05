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
	want_libpcre2=no
	found_libpcre2=no
	libpcre2_dir=""
	libpcre2_include_dir=""
	libpcre2_lib_dir=""

	#
	# process --with-* flags
	#

	AC_ARG_WITH([libpcre2],[
If you want to specify libpcre2 installation directories:
AC_HELP_STRING([--with-libpcre2@<:@=DIR@:>@], [use libpcre2 from given base install directory (DIR), default is to search through a number of common places for the libpcre2 files.])],
	[
		if test "$withval" != "no"; then
			want_libpcre2=yes
			if test "$withval" != "yes"; then
				libpcre2_dir="$withval"
			fi
		fi
	])

	AC_ARG_WITH([libpcre2-include], AC_HELP_STRING([--with-libpcre2-include@<:@=DIR@:>@], [use libpcre2 include headers from given path.]), [
		want_libpcre2="yes"
		libpcre2_include_dir="$withval"
		if ! test -d "$libpcre2_include_dir"; then
			AC_MSG_ERROR([cannot find $libpcre2_include_dir directory])
		fi
		if ! test -f "$libpcre2_include_dir/pcre2.h"; then
			AC_MSG_ERROR([cannot find $libpcre2_include_dir/pcre2.h])
		fi
	])

	AC_ARG_WITH([libpcre2-lib], AC_HELP_STRING([--with-libpcre2-lib@<:@=DIR@:>@], [use libpcre2 libraries from given path.]), [
		want_libpcre2="yes"
		libpcre2_lib_dir="$withval"
		if ! test -d "$libpcre2_lib_dir"; then
			AC_MSG_ERROR([cannot find $libpcre2_lib_dir directory])
		fi
	])


	#
	# find actual compiler flags and include paths
	#

	if test "$1" != "flags-only"; then
		AC_REQUIRE([PKG_PROG_PKG_CONFIG])
		m4_ifdef([PKG_PROG_PKG_CONFIG], [PKG_PROG_PKG_CONFIG()], [:])

		if test -n "$PKG_CONFIG"; then
			#
			# got pkg-config, use that
			#

			m4_pattern_allow([^PKG_CONFIG_LIBDIR$])

			if test -n "$libpcre2_lib_dir"; then
				export PKG_CONFIG_LIBDIR="$libpcre2_lib_dir/pkgconfig"
			elif test -n "$libpcre2_dir"; then
				export PKG_CONFIG_LIBDIR="$libpcre2_dir/lib/pkgconfig"
			fi

			AC_RUN_LOG([$PKG_CONFIG --exists --print-errors libpcre2-8]) || {
				AC_MSG_ERROR([cannot find pkg-config package for libpcre2])
			}

			if test -n "$libpcre2_include_dir"; then
				LIBPCRE2_CFLAGS="-I$libpcre2_include_dir"
			else
				LIBPCRE2_CFLAGS=`$PKG_CONFIG --cflags libpcre2-8`
			fi

			LIBPCRE2_LDFLAGS=`$PKG_CONFIG --libs-only-L libpcre2-8`
			LIBPCRE2_LIBS=`$PKG_CONFIG --libs-only-l libpcre2-8`

			unset PKG_CONFIG_LIBDIR

			found_libpcre2="yes"
		else
			#
			# no pkg-config, trying to guess
			#

			AC_MSG_WARN([proceeding without pkg-config])

			LIBPCRE2_LIBS="-lpcre2-8"

			if test -n "$libpcre2_dir"; then
				#
				# directories are given explicitly
				#

				if test -n "$libpcre2_include_dir"; then
					LIBPCRE2_CFLAGS="-I$libpcre2_include_dir"
				else
					if test -f "$libpcre2_dir/include/pcre2.h"; then
						LIBPCRE2_CFLAGS="-I$libpcre2_dir/include"
					else
						AC_MSG_ERROR([cannot find $libpcre2_dir/include/pcre2.h])
					fi
				fi

				if test -n "$libpcre2_lib_dir"; then
					LIBPCRE2_LDFLAGS="-L$libpcre2_lib_dir"
				else
					if test -d "$libpcre2_dir/lib"; then
						LIBPCRE2_LDFLAGS="-L$libpcre2_dir/lib"
					else
						AC_MSG_ERROR([cannot find $libpcre2_dir/lib])
					fi
				fi

				found_libpcre2="yes"
			elif test -n "$libpcre2_include_dir"; then
				LIBPCRE2_CFLAGS="-I$libpcre2_include_dir"

				if test -n "$libpcre2_lib_dir"; then
					LIBPCRE2_LDFLAGS="-L$libpcre2_lib_dir"
				fi

				found_libpcre2="yes"
			elif test -n "$libpcre2_lib_dir"; then
				LIBPCRE2_LDFLAGS="-L$libpcre2_lib_dir"

				found_libpcre2="yes"
			else
				#
				# search default directories
				#

				if test -f /usr/include/pcre2.h; then
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
				fi
			fi
		fi


		#
		# process --enable-static and --enable_static-libs flags
		#

		if test "x$enable_static" = "xyes"; then
			LIBPCRE2_LIBS=" $LIBPCRE2_LIBS -lpthread"
		elif test "x$enable_static_libs" = "xyes"; then
			if test "x$static_linking_support" == "xno"; then
				AC_MSG_WARN([compiler has no direct suppor for static linkage])

				if test -n "$libpcre2_lib_dir"; then
					if test -f "$libpcre2_lib_dir/libpcre2-8.a"; then
						LIBPCRE2_LIBS="$libpcre2_lib_dir/libpcre2-8.a"
					else
						AC_MSG_ERROR([cannot find $libpcre2_lib_dir/libpcre2-8.a])
					fi
				elif test -n "$libpcre2_dir"; then
					if test -f "$libpcre2_dir/lib/libpcre2-8.a"; then
						LIBPCRE2_LIBS="$libpcre2_dir/lib/libpcre2-8.a"
					else
						AC_MSG_ERROR([cannot find $libpcre2_dir/lib/libpcre2-8.a])
					fi
				else
					AC_MSG_ERROR([libpcre2 directory must be given explicitly in this case])
				fi
			else
				LIBPCRE2_LIBS="$LIBPCRE2_LDFLAGS ${static_linking_support}static $LIBPCRE2_LIBS ${static_linking_support}dynamic"
				LIBPCRE2_LDFLAGS=""
			fi
		fi


		#
		# try building with pcre2
		#

		AC_MSG_CHECKING([for libpcre2 support])

		if test "x$found_libpcre2" = "xyes"; then
			am_save_CFLAGS="$CFLAGS"
			am_save_LDFLAGS="$LDFLAGS"
			am_save_LIBS="$LIBS"

			CFLAGS="$CFLAGS $LIBPCRE2_CFLAGS"
			LDFLAGS="$LDFLAGS $LIBPCRE2_LDFLAGS"
			LIBS="$LIBS $LIBPCRE2_LIBS"

			found_libpcre2="no"
			LIBPCRE2_TRY_LINK([no])

			if test "x$found_libpcre2" = "xyes"; then
				AC_MSG_RESULT(yes)
			else
				AC_MSG_RESULT(no)
				if test "$1" = "mandatory"; then
					AC_MSG_NOTICE([CFLAGS: $CFLAGS])
					AC_MSG_NOTICE([LDFLAGS: $LDFLAGS])
					AC_MSG_NOTICE([LIBS: $LIBS])
					AC_MSG_ERROR([cannot build with libpcre2])
				else
					LIBPCRE2_CFLAGS=""
					LIBPCRE2_LDFLAGS=""
					LIBPCRE2_LIBS=""
				fi
			fi

			CFLAGS="$am_save_CFLAGS"
			LDFLAGS="$am_save_LDFLAGS"
			LIBS="$am_save_LIBS"
		else
			AC_MSG_RESULT(no)
		fi

		AC_SUBST(LIBPCRE2_CFLAGS)
		AC_SUBST(LIBPCRE2_LDFLAGS)
		AC_SUBST(LIBPCRE2_LIBS)
	fi
])dnl
