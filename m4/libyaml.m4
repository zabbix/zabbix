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

AC_DEFUN([YAML_TRY_LINK],
[
	AC_LINK_IFELSE([AC_LANG_PROGRAM([[
		#include <assert.h>
		#include <yaml.h>
	]],[[
		yaml_parser_t parser;

		assert(yaml_parser_initialize(&parser));
	]])],[found_yaml="yes"],[AC_MSG_FAILURE([error])])
])dnl

AC_DEFUN([LIBYAML_CHECK_CONFIG],
[
	AC_ARG_WITH([libyaml],[
If you want to specify YAML installation directories:
AS_HELP_STRING([--with-libyaml@<:@=DIR@:>@],[use specific YAML library @<:@default=yes@:>@, DIR is the YAML library install directory.])],
		[
		if test "$withval" = "no"; then
			want_yaml="no"
			_libyaml_dir="no"
		elif test "$withval" = "yes"; then
			want_yaml="yes"
			_libyaml_dir="no"
		else
			want_yaml="yes"
			_libyaml_dir=$withval
		fi
		],
		[
			want_yaml=ifelse([$1],,[yes],[$1])
			_libyaml_dir="no"
		]
	)dnl

	if test "x$want_yaml" = "xyes"; then
		AC_REQUIRE([PKG_PROG_PKG_CONFIG])
		m4_ifdef([PKG_PROG_PKG_CONFIG], [PKG_PROG_PKG_CONFIG()], [:])

		AC_MSG_CHECKING(for yaml-0.1 support)
		if test "x$_libyaml_dir" = "xno"; then
			if test -x "$PKG_CONFIG" && `$PKG_CONFIG --exists yaml-0.1`; then
				YAML_CFLAGS="`$PKG_CONFIG --cflags yaml-0.1`"
				YAML_LDFLAGS="`$PKG_CONFIG --libs yaml-0.1`"
				YAML_LIBRARY_PATH=""
				YAML_LIBS="`$PKG_CONFIG --libs yaml-0.1`"
				found_yaml="yes"
			else
				found_yaml="no"
				AC_MSG_RESULT(no)
			fi
		else
			if test -f $_libyaml_dir/include/yaml.h; then
				YAML_CFLAGS=-I$_libyaml_dir/include
				YAML_LDFLAGS=-L$_libyaml_dir/lib
				YAML_LIBRARY_PATH="$_libyaml_dir/lib"
				YAML_LIBS="-lyaml"
				found_yaml="yes"
			else
				found_yaml="no"
				AC_MSG_RESULT(no)
			fi
		fi
	fi

	if test "x$found_yaml" = "xyes"; then
		am_save_cflags="$CFLAGS"
		am_save_ldflags="$LDFLAGS"
		am_save_libs="$LIBS"

		CFLAGS="$CFLAGS $YAML_CFLAGS"
		LDFLAGS="$LDFLAGS $YAML_LDFLAGS"
		LIBS="$LIBS $YAML_LIBS"

		found_yaml="no"
		YAML_TRY_LINK([no])

		CFLAGS="$am_save_cflags"
		LDFLAGS="$am_save_ldflags"
		LIBS="$am_save_libs"

		if test "x$found_yaml" = "xyes"; then
			AC_MSG_RESULT(yes)
		else
			AC_MSG_RESULT(no)
		fi # if test "x$found_yaml" = "xyes"; then
	else
		YAML_CFLAGS=""
		YAML_LDFLAGS=""
		YAML_LIBRARY_PATH=""
		YAML_LIBS=""
	fi # if test "x$found_yaml" = "xyes"; then

	AC_SUBST(YAML_CFLAGS)
	AC_SUBST(YAML_LDFLAGS)
	AC_SUBST(YAML_LIBRARY_PATH)
	AC_SUBST(YAML_LIBS)
])dnl
