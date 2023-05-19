#
# Zabbix
# Copyright (C) 2001-2023 Zabbix SIA
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#

AC_DEFUN([CONF_TESTS],
[
	AM_COND_IF([USE_TESTS],[
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
			]])],[found_cmocka="yes"],[AC_MSG_FAILURE([error])])
		])dnl

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
				if test -x "$PKG_CONFIG"; then
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
				AC_DEFINE([HAVE_TESTS], [1], ["Define to 1 if tests directory is present"])
				AC_MSG_RESULT(yes)

				AC_CONFIG_FILES([
				tests/Makefile
				tests/libs/Makefile
				tests/libs/zbxalgo/Makefile
				tests/libs/zbxcommon/Makefile
				tests/libs/zbxcomms/Makefile
				tests/libs/zbxcommshigh/Makefile
				tests/libs/zbxconf/Makefile
				tests/libs/zbxdbcache/Makefile
				tests/libs/zbxdbhigh/Makefile
				tests/libs/zbxeval/Makefile
				tests/libs/zbxhistory/Makefile
				tests/libs/zbxjson/Makefile
				tests/libs/zbxprometheus/Makefile
				tests/libs/zbxregexp/Makefile
				tests/libs/zbxserver/Makefile
				tests/libs/zbxsysinfo/Makefile
				tests/libs/zbxsysinfo/common/Makefile
				tests/libs/zbxtagfilter/Makefile
				tests/libs/zbxtrends/Makefile
				tests/libs/zbxtime/Makefile
				tests/zabbix_server/Makefile
				tests/zabbix_server/pinger/Makefile
				tests/zabbix_server/poller/Makefile
				tests/zabbix_server/preprocessor/Makefile
				tests/zabbix_server/service/Makefile
				tests/zabbix_server/trapper/Makefile
				tests/mocks/Makefile
				tests/mocks/configcache/Makefile
				tests/mocks/valuecache/Makefile
				])

				case "$ARCH" in
				linux)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/linux/Makefile])
					;;
				aix)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/aix/Makefile])
					;;
				osx)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/osx/Makefile])
					;;
				solaris)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/solaris/Makefile])
					;;
				hpux)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/hpux/Makefile])
					;;
				freebsd)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/freebsd/Makefile])
					;;
				netbsd)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/netbsd/Makefile])
					;;
				osf)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/osf/Makefile])
					;;
				openbsd)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/openbsd/Makefile])
					;;
				unknown)
					AC_CONFIG_FILES([tests/libs/zbxsysinfo/unknown/Makefile])
					;;
				esac


				AC_LINK_IFELSE([AC_LANG_PROGRAM([[
				#include <stdlib.h>
				]], [[
					__fxstat(0, 0, NULL);
				]])],[AC_DEFINE(HAVE_FXSTAT, 1, Define to 1 if fxstat function is available)],[])

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
])dnl
