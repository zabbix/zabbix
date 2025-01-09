#
# Copyright (C) 2001-2025 Zabbix SIA
#
# This program is free software: you can redistribute it and/or modify it under the terms of
# the GNU Affero General Public License as published by the Free Software Foundation, version 3.
#
# This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
# without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License along with this program.
# If not, see <https://www.gnu.org/licenses/>.
#

AC_DEFUN([CMOCKA_TRY_LINK],
[
	AC_LINK_IFELSE([AC_LANG_PROGRAM([[
		#include <stdint.h>
		#include <stdarg.h>
		#include <stddef.h>
		#include <setjmp.h>

		#include <cmocka.h>
	]],[[
		cmocka_run_group_tests(NULL, NULL, NULL);
	]])],[found_cmocka="yes"],[])
])dnl

AC_DEFUN([CMOCKA_CHECK_CONFIG],
[
	AC_ARG_WITH([cmocka],[
If you want to specify cmocka installation directories:
AS_HELP_STRING([--with-cmocka@<:@=DIR@:>@],[use specific cmocka library @<:@default=yes@:>@, DIR is the cmocka library install directory.])],
		[
		if test "$withval" = "no"; then
			want_cmocka="no"
			_libcmocka_dir="no"
		elif test "$withval" = "yes"; then
			want_cmocka="yes"
			_libcmocka_dir="no"
		else
			want_cmocka="yes"
			_libcmocka_dir=$withval
		fi
		],
		[
			want_cmocka=ifelse([$1],,[yes],[$1])
			_libcmocka_dir="no"
		]
	)dnl

	if test "x$want_cmocka" = "xyes"; then
		AC_REQUIRE([PKG_PROG_PKG_CONFIG])
		m4_ifdef([PKG_PROG_PKG_CONFIG], [PKG_PROG_PKG_CONFIG()], [:])

		AC_MSG_CHECKING(for cmocka support)
		if test "x$_libcmocka_dir" = "xno"; then
			if test -x "$PKG_CONFIG" && `$PKG_CONFIG --exists cmocka`; then
				CMOCKA_CFLAGS="`$PKG_CONFIG --cflags cmocka`"
				CMOCKA_LDFLAGS="`$PKG_CONFIG --libs cmocka`"
				CMOCKA_LIBRARY_PATH=""
				CMOCKA_LIBS="`$PKG_CONFIG --libs cmocka`"
				found_cmocka="yes"
			else
				found_cmocka="no"
				AC_MSG_RESULT(no)
			fi
		else
			if test -f $_libcmocka_dir/include/cmocka.h; then
				CMOCKA_CFLAGS=-I$_libcmocka_dir/include
				CMOCKA_LDFLAGS=-L$_libcmocka_dir/lib
				CMOCKA_LIBRARY_PATH="$_libcmocka_dir/lib"
				CMOCKA_LIBS="-lcmocka"
				found_cmocka="yes"
			else
				found_cmocka="no"
				AC_MSG_RESULT(no)
			fi
		fi
	fi

	if test "x$found_cmocka" = "xyes"; then
		am_save_cflags="$CFLAGS"
		am_save_ldflags="$LDFLAGS"
		am_save_libs="$LIBS"

		CFLAGS="$CFLAGS $CMOCKA_CFLAGS"
		LDFLAGS="$LDFLAGS $CMOCKA_LDFLAGS"
		LIBS="$LIBS $CMOCKA_LIBS"

		found_cmocka="no"
		CMOCKA_TRY_LINK([no])

		CFLAGS="$am_save_cflags"
		LDFLAGS="$am_save_ldflags"
		LIBS="$am_save_libs"

		if test "x$found_cmocka" = "xyes"; then
			AC_MSG_RESULT(yes)
		else
			AC_MSG_RESULT(no)
		fi # if test "x$found_cmocka" = "xyes"; then
	else
		CMOCKA_CFLAGS=""
		CMOCKA_LDFLAGS=""
		CMOCKA_LIBRARY_PATH=""
		CMOCKA_LIBS=""
	fi # if test "x$found_cmocka" = "xyes"; then

	AC_SUBST(CMOCKA_CFLAGS)
	AC_SUBST(CMOCKA_LDFLAGS)
	AC_SUBST(CMOCKA_LIBRARY_PATH)
	AC_SUBST(CMOCKA_LIBS)
])dnl
