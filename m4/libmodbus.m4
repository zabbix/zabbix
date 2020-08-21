# LIBMODBUS_CHECK_CONFIG ([DEFAULT-ACTION])
#
# Zabbix
# Copyright (C) 2001-2020 Zabbix SIA
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

AC_DEFUN([LIBMODBUS_TRY_LINK],
[
AC_TRY_LINK(
[
#include <modbus/modbus.h>
],
[
	modbus_t	*mdb_ctx;
	mdb_ctx = modbus_new_tcp("127.0.0.1", 502);
],
found_libmodbus="yes",)
])dnl

AC_DEFUN([LIBMODBUS_ACCEPT_VERSION],
[
	# Zabbix minimal major supported version of libmodbus:
	minimal_libmodbus_major_version=3
	minimal_libmodbus_minor_version=0

	# get the major version
	found_libmodbus_version_major=`cat $1 | $EGREP \#define.*'LIBMODBUS_VERSION_MAJOR ' | $AWK '{print @S|@3;}' | $EGREP -o "[0-9]+"`
	found_libmodbus_version_minor=`cat $1 | $EGREP \#define.*'LIBMODBUS_VERSION_MINOR ' | $AWK '{print @S|@3;}' | $EGREP -o "[0-9]+"`

	if test $((found_libmodbus_version_major)) -gt $((minimal_libmodbus_major_version)); then
		accept_libmodbus_version="yes"
	elif test $((found_libmodbus_version_major)) -lt $((minimal_libmodbus_major_version)); then
		accept_libmodbus_version="no"
	elif test $((found_libmodbus_version_minor)) -ge $((minimal_libmodbus_minor_version)); then
		accept_libmodbus_version="yes"
	else
		accept_libmodbus_version="no"
	fi;
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
	accept_libmodbus_version="no"
    ],[want_libmodbus=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_libmodbus" = "xyes"; then
     AC_MSG_CHECKING(for LIBMODBUS support)
     if test "x$_libmodbus_dir" = "xno"; then
       if test -f /usr/include/modbus/modbus.h; then
         LIBMODBUS_CFLAGS=-I/usr/include
         LIBMODBUS_LDFLAGS=-L/usr/lib
         LIBMODBUS_LIBS="-lmodbus"
         found_libmodbus="yes"
	 LIBMODBUS_ACCEPT_VERSION([/usr/include/modbus/modbus-version.h])
       elif test -f /usr/local/include/modbus/modbus.h; then
         LIBMODBUS_CFLAGS=-I/usr/local/include
         LIBMODBUS_LDFLAGS=-L/usr/local/lib
         LIBMODBUS_LIBS="-lmodbus"
         found_libmodbus="yes"
	 LIBMODBUS_ACCEPT_VERSION([/usr/local/include/modbus/modbus-version.h])
       else #libraries are not found in default directories
         found_libmodbus="no"
         AC_MSG_RESULT(no)
       fi # test -f /usr/include/modbus/modbus.h; then
     else # test "x$_libmodbus_dir" = "xno"; then
       if test -f $_libmodbus_dir/include/modbus/modbus.h; then
	 LIBMODBUS_CFLAGS=-I$_libmodbus_dir/include
         LIBMODBUS_LDFLAGS=-L$_libmodbus_dir/lib
         LIBMODBUS_LIBS="-lmodbus"
         found_libmodbus="yes"
	 LIBMODBUS_ACCEPT_VERSION([$_libmodbus_dir/include/modbus/modbus-version.h])
       else #if test -f $_libmodbus_dir/include/modbus/modbus.h; then
         found_libmodbus="no"
         AC_MSG_RESULT(no)
       fi #test -f $_libmodbus_dir/include/modbus/modbus.h; then
     fi #if test "x$_libmodbus_dir" = "xno"; then--with-libmodbu
  fi # if test "x$want_libmodbus" != "xno"; then

  if test "x$found_libmodbus" = "xyes"; then
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $LIBMODBUS_CFLAGS"
    LDFLAGS="$LDFLAGS $LIBMODBUS_LDFLAGS"
    LIBS="$LIBS $LIBMODBUS_LIBS"

    found_libmodbus="no"
    LIBMODBUS_TRY_LINK([no])

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_libmodbus" = "xyes"; then
      AC_DEFINE([HAVE_LIBMODBUS], 1, [Define to 1 if you have the 'libmodbus' library (-lmodbus)])
      AC_MSG_RESULT(yes)
      if test $((found_libmodbus_version_major)) = 3 &&  test $((found_libmodbus_version_minor)) = 0; then
        AC_DEFINE([HAVE_LIBMODBUS_3_0], 1, [Define to 1 if you have the 'libmodbus' library version 3.0.x (-lmodbus)])
        AC_MSG_RESULT(yes)
      elif test $((found_libmodbus_version_major)) = 3 &&  test $((found_libmodbus_version_minor)) = 1; then
        AC_DEFINE([HAVE_LIBMODBUS_3_1], 1, [Define to 1 if you have the 'libmodbus' library version 3.1.x (-lmodbus)])
        AC_MSG_RESULT(yes)
      fi
    else
      AC_MSG_RESULT(no)
      LIBMODBUS_CFLAGS=""
      LIBMODBUS_LDFLAGS=""
      LIBMODBUS_LIBS=""
    fi
  fi

  AC_SUBST(LIBMODBUS_CFLAGS)
  AC_SUBST(LIBMODBUS_LDFLAGS)
  AC_SUBST(LIBMODBUS_LIBS)

])dnl
