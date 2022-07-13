# LIBMODBUS_CHECK_CONFIG ([DEFAULT-ACTION])
#
# Zabbix
# Copyright (C) 2001-2022 Zabbix SIA
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

AC_DEFUN([LIBMODBUS30_TRY_LINK],
[
AC_TRY_LINK(
[
#include "modbus.h"
],
[
  modbus_t  *mdb_ctx;
  mdb_ctx = modbus_new_tcp("127.0.0.1", 502);
  modbus_set_response_timeout(mdb_ctx, NULL);
],
found_libmodbus="30",)
])dnl

AC_DEFUN([LIBMODBUS31_TRY_LINK],
[
AC_TRY_LINK(
[
#include "modbus.h"
],
[
  modbus_t  *mdb_ctx;
  mdb_ctx = modbus_new_tcp("127.0.0.1", 502);
  modbus_set_response_timeout(mdb_ctx, 1, 0)
],
found_libmodbus="31",)
])dnl

AC_DEFUN([LIBMODBUS_ACCEPT_VERSION],
[
    AC_PROG_AWK

  _lib_version_parse="eval $AWK '{split(\$NF,A,\".\"); X=256*256*A[[1]]+256*A[[2]]+A[[3]]; print X;}'"
  _lib_version=`echo ifelse([$1],,[0],[$1]) | $_lib_version_parse`
  _lib_wanted=`echo ifelse([$2],,[0],[$2]) | $_lib_version_parse`

  if test $_lib_wanted -gt 0; then
    AC_CACHE_CHECK([for libmodbus $1 >= version $2],
      [libmodbus_cv_version_ok],[
        if test $_lib_version -lt $_lib_wanted; then
          AC_MSG_ERROR([libmodbus version mismatch])
        else
          libmodbus_cv_version_ok="yes"
        fi
      ]
    )
  fi
])dnl

AC_DEFUN([LIBMODBUS_CHECK_CONFIG],
[
  AC_ARG_WITH(libmodbus,[
If you want to use MODBUS based checks:
AC_HELP_STRING([--with-libmodbus@<:@=DIR@:>@],[use MODBUS package @<:@default=no@:>@, DIR is the MODBUS library install directory.])],
    [
      if test "$withval" = "no"; then
        want_libmodbus="no"
        _libmodbus_dir="no"
      elif test "$withval" = "yes"; then
        want_libmodbus="yes"
        _libmodbus_dir="no"
      else
        want_libmodbus="yes"
        _libmodbus_dir=$withval
      fi
      _libmodbus_version_wanted=ifelse([$1],,[3.0.0],[$1])
    ],[
      want_libmodbus="no"
    ]
  )

  if test "x$want_libmodbus" = "xyes"; then
    AC_REQUIRE([PKG_PROG_PKG_CONFIG])
    m4_ifdef([PKG_PROG_PKG_CONFIG], [PKG_PROG_PKG_CONFIG()], [:])
    test -z "$PKG_CONFIG" && AC_MSG_ERROR([Not found pkg-config library])
    m4_pattern_allow([^PKG_CONFIG_LIBDIR$])

    if test "x$_libmodbus_dir" = "xno"; then
      m4_ifdef([PKG_CHECK_EXISTS], [
        PKG_CHECK_EXISTS(libmodbus,[
          LIBMODBUS_LIBS=`$PKG_CONFIG --libs libmodbus`
        ],[
          AC_MSG_ERROR([Not found libmodbus package])
        ])
      ], [:])
      LIBMODBUS_CFLAGS=`$PKG_CONFIG --cflags libmodbus`
      LIBMODBUS_LDFLAGS=""
      _libmodbus_version=`$PKG_CONFIG --modversion libmodbus`
    else
      AC_RUN_LOG([PKG_CONFIG_LIBDIR="$_libmodbus_dir/lib/pkgconfig" $PKG_CONFIG --exists --print-errors libmodbus]) || AC_MSG_ERROR(["Not found libmodbus package in $_libmodbus_dir/lib/pkgconfig"])
      LIBMODBUS_LDFLAGS="-L$_libmodbus_dir/lib"
      LIBMODBUS_LIBS=`PKG_CONFIG_LIBDIR="$_libmodbus_dir/lib/pkgconfig" $PKG_CONFIG --libs libmodbus`
      LIBMODBUS_CFLAGS=`PKG_CONFIG_LIBDIR="$_libmodbus_dir/lib/pkgconfig" $PKG_CONFIG --cflags libmodbus`
      _libmodbus_version=`PKG_CONFIG_LIBDIR="$_libmodbus_dir/lib/pkgconfig" $PKG_CONFIG --modversion libmodbus`
    fi

    LIBMODBUS_ACCEPT_VERSION($_libmodbus_version,$_libmodbus_version_wanted)
    if test "x$enable_static_libs" = "xyes"; then
      if test "x$static_linking_support" = "xno"; then
        LIBMODBUS_LIBS=`echo "$LIBMODBUS_LIBS"|sed "s|-lmodbus|$_libmodbus_dir/lib/libmodbus.a|g"`
      else
        LIBMODBUS_LIBS=`echo "$LIBMODBUS_LIBS"|sed "s/-lmodbus/${static_linking_support}static -lmodbus ${static_linking_support}dynamic/g"`
      fi
    fi
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $LIBMODBUS_CFLAGS"
    LDFLAGS="$LDFLAGS $LIBMODBUS_LDFLAGS"
    LIBS="$LIBS $LIBMODBUS_LIBS"

    found_libmodbus="no"
    LIBMODBUS31_TRY_LINK([no])
    if test "x$found_libmodbus" = "xno"; then
      LIBMODBUS30_TRY_LINK([no])
    fi
    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    AC_MSG_CHECKING(for libmodbus support)
    if test "x$found_libmodbus" != "xno"; then
      AC_DEFINE([HAVE_LIBMODBUS], 1, [Define to 1 if you have the 'libmodbus' library (-lmodbus)])
      if test "x$found_libmodbus" = "x30"; then
        AC_DEFINE([HAVE_LIBMODBUS_3_0], 1, [Define to 1 if you have the 'libmodbus' library version 3.0.x (-lmodbus)])
      elif test "x$found_libmodbus" = "x31"; then
        AC_DEFINE([HAVE_LIBMODBUS_3_1], 1, [Define to 1 if you have the 'libmodbus' library version 3.1.x (-lmodbus)])
      fi
      found_libmodbus="yes"
      AC_MSG_RESULT(yes)
    else
      AC_MSG_ERROR([Not compatible libmodbus library])
    fi
  fi

  AC_SUBST(LIBMODBUS_CFLAGS)
  AC_SUBST(LIBMODBUS_LDFLAGS)
  AC_SUBST(LIBMODBUS_LIBS)

])dnl
