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

AC_DEFUN([LIBXML2_CHECK_CONFIG],
[
    LIBXML2_CONFIG="no"

    AC_ARG_WITH(libxml2,
        [
If you want to use XML library:
AC_HELP_STRING([--with-libxml2@<:@=ARG@:>@],
    [use libxml2 client library @<:@default=no@:>@, see PKG_CONFIG_PATH environment variable to specify .pc file location]
        )],
        [
        if test "$withval" = "no"; then
            want_libxml2="no"
        elif test "$withval" = "yes"; then
            want_libxml2="yes"
        else
            want_libxml2="yes"
        fi
        ],
        [want_libxml2="no"]
    )

    LIBXML2_CFLAGS=""
    LIBXML2_LDFLAGS=""
    LIBXML2_LIBS=""
    LIBXML2_VERSION=""

    dnl
    dnl Check libxml2 libraries
    dnl

    if test "$want_libxml2" = "yes"; then
        AC_REQUIRE([PKG_PROG_PKG_CONFIG])
        PKG_PROG_PKG_CONFIG()

        if test -x "$PKG_CONFIG"; then

            LIBXML2_CFLAGS="`$PKG_CONFIG --cflags libxml-2.0`"

            _full_libxml2_libs="`$PKG_CONFIG --libs libxml-2.0`"

            for i in $_full_libxml2_libs; do
                case $i in
                   -lxml2)
                        ;;
                   -L*)
                        LIBXML2_LDFLAGS="${LIBXML2_LDFLAGS} $i"
                        ;;
                   -R*)
                        LIBXML2_LDFLAGS="${LIBXML2_LDFLAGS} -Wl,$i"
                        ;;
                esac
            done

            if test "x$enable_static" = "xyes"; then
                for i in $_full_libxml2_libs; do
                    case $i in
                        -lxml2)
                            ;;
                        -l*)
                            _lib_name="`echo "$i" | cut -b3-`"
                            AC_CHECK_LIB($_lib_name, main, [
                                    LIBXML2_LIBS="$LIBXML2_LIBS $i"
                                    ],[
                                    AC_MSG_ERROR([Not found $_lib_name library])
                                    ])
                            ;;
                    esac
                done
            fi

            _save_libxml2_libs="${LIBS}"
            _save_libxml2_ldflags="${LDFLAGS}"
            _save_libxml2_cflags="${CFLAGS}"
            LIBS="${LIBS} ${LIBXML2_LIBS}"
            LDFLAGS="${LDFLAGS} ${LIBXML2_LDFLAGS}"
            CFLAGS="${CFLAGS} ${LIBXML2_CFLAGS}"

            AC_CHECK_LIB(xml2, xmlReadMemory, [
                    LIBXML2_LIBS="-lxml2 ${LIBXML2_LIBS}"
                    ],[
                    AC_MSG_ERROR([Not found libxml2 library])
                    ])

            LIBS="${_save_libxml2_libs}"
            LDFLAGS="${_save_libxml2_ldflags}"
            CFLAGS="${_save_libxml2_cflags}"
            unset _save_libxml2_libs
            unset _save_libxml2_ldflags
            unset _save_libxml2_cflags

            LIBXML2_VERSION=`$PKG_CONFIG --version libxml-2.0`

            AC_DEFINE([HAVE_LIBXML2], [1], [Define to 1 if libxml2 libraries are available])

            found_libxml2="yes"
        else
            found_libxml2="no"
        fi
    fi

    AC_SUBST([LIBXML2_VERSION])
    AC_SUBST([LIBXML2_CFLAGS])
    AC_SUBST([LIBXML2_LDFLAGS])
    AC_SUBST([LIBXML2_LIBS])
])
