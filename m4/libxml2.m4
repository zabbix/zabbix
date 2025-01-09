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

AC_DEFUN([LIBXML2_CHECK_CONFIG],
[
    LIBXML2_CONFIG="no"

    AC_ARG_WITH(libxml2,
        [
If you want to use XML library:
AS_HELP_STRING([--with-libxml2@<:@=ARG@:>@],
    [use libxml2 client library @<:@default=no@:>@, see PKG_CONFIG_PATH environment variable to specify .pc file location]
        )],
        [
        if test "$withval" = "no"; then
            want_libxml2="no"
            _libxml2_with="no"
        elif test "$withval" = "yes"; then
            want_libxml2="yes"
            _libxml2_with="yes"
        else
            want_libxml2="yes"
            _libxml2_with=$withval
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
        if test "$_libxml2_with" != "yes"; then
            XML2_INCDIR=$_libxml2_with/include/libxml2
            XML2_LIBDIR=$_libxml2_with/lib
            LIBXML2_CFLAGS="-I$XML2_INCDIR"
            LIBXML2_LDFLAGS="-L$XML2_LIBDIR"
            _full_libxml2_libs=$LIBXML2_LDFLAGS
            configured_libxml2="yes"
        else
            AC_REQUIRE([PKG_PROG_PKG_CONFIG])
            m4_ifdef([PKG_PROG_PKG_CONFIG], [PKG_PROG_PKG_CONFIG()], [:])

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

                configured_libxml2="yes"
            else
                configured_libxml2="no"
            fi
        fi

        if test "$configured_libxml2" = "yes"; then

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

            if test "$_libxml2_with" != "yes"; then
                if test -f $_libxml2_with/include/libxml2/libxml/xmlversion.h; then
                    LIBXML2_VERSION=`cat $_libxml2_with/include/libxml2/libxml/xmlversion.h \
                                  | grep '#define.*LIBXML_DOTTED_VERSION.*' \
                                  | sed -e 's/#define LIBXML_DOTTED_VERSION  *//' \
                                  | sed -e 's/  *\/\*.*\*\///' \
                                  | sed -e 's/\"//g'`
                else
                    AC_MSG_ERROR([Not found libxml2 library])
                fi
            else
                LIBXML2_VERSION=`$PKG_CONFIG --version libxml-2.0`
            fi

            _save_libxml2_libs="${LIBS}"
            _save_libxml2_ldflags="${LDFLAGS}"
            _save_libxml2_cflags="${CFLAGS}"
            LIBS="${LIBS} ${LIBXML2_LIBS}"
            LDFLAGS="${LDFLAGS} ${LIBXML2_LDFLAGS}"
            CFLAGS="${CFLAGS} ${LIBXML2_CFLAGS}"

            AC_CHECK_LIB(xml2, xmlReadMemory, [
                    LIBXML2_LIBS="${LIBXML2_LIBS} -lxml2"
                    ],[
                    AC_MSG_ERROR([Not found libxml2 library])
                    ])

            LIBS="${_save_libxml2_libs}"
            LDFLAGS="${_save_libxml2_ldflags}"
            CFLAGS="${_save_libxml2_cflags}"
            unset _save_libxml2_libs
            unset _save_libxml2_ldflags
            unset _save_libxml2_cflags

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
