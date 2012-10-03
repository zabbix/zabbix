##### http://autoconf-archive.cryp.to/ax_lib_postgresql.html
#
# SYNOPSIS
#
#   AX_LIB_POSTGRESQL([MINIMUM-VERSION])
#
# DESCRIPTION
#
#   This macro provides tests of availability of PostgreSQL 'libpq'
#   library of particular version or newer.
#
#   AX_LIB_POSTGRESQL macro takes only one argument which is optional.
#   If there is no required version passed, then macro does not run
#   version test.
#
#   The --with-postgresql option takes one of three possible values:
#
#   no - do not check for PostgreSQL client library
#
#   yes - do check for PostgreSQL library in standard locations
#   (pg_config should be in the PATH)
#
#   path - complete path to pg_config utility, use this option if
#   pg_config can't be found in the PATH
#
#   This macro calls:
#
#     AC_SUBST(POSTGRESQL_CPPFLAGS)
#     AC_SUBST(POSTGRESQL_LDFLAGS)
#     AC_SUBST(POSTGRESQL_LIBS)
#     AC_SUBST(POSTGRESQL_VERSION)
#
#   And sets:
#
#     HAVE_POSTGRESQL
#
# LAST MODIFICATION
#
#   2006-07-16
#
# COPYLEFT
#
#   Copyright (c) 2006 Mateusz Loskot <mateusz@loskot.net>
#
#   Copying and distribution of this file, with or without
#   modification, are permitted in any medium without royalty provided
#   the copyright notice and this notice are preserved.

AC_DEFUN([AX_LIB_POSTGRESQL],
[
    PG_CONFIG="no"

    AC_ARG_WITH([postgresql],
        AC_HELP_STRING([--with-postgresql@<:@=ARG@:>@],
            [use PostgreSQL library @<:@default=no@:>@, optionally specify path to pg_config]
        ),
        [
        if test "x$withval" = "xno"; then
            want_postgresql="no"
        elif test "x$withval" = "xyes"; then
            want_postgresql="yes"
        else
            want_postgresql="yes"
            PG_CONFIG="$withval"
        fi
        ],
        [want_postgresql="no"]
    )

    POSTGRESQL_CPPFLAGS=""
    POSTGRESQL_LDFLAGS=""
    POSTGRESQL_LIBS=""
    POSTGRESQL_VERSION=""

    dnl
    dnl Check PostgreSQL libraries (libpq)
    dnl

    if test "x$want_postgresql" = "xyes"; then

        AC_PATH_PROG([PG_CONFIG], [pg_config], [])

        if test -x "$PG_CONFIG"; then
            AC_MSG_CHECKING([for PostgreSQL libraries])

            POSTGRESQL_CPPFLAGS="-I`$PG_CONFIG --includedir`"
            POSTGRESQL_LDFLAGS="-L`$PG_CONFIG --libdir`"
            POSTGRESQL_LIBS="-lpq"

            POSTGRESQL_VERSION=`$PG_CONFIG --version | sed -e 's#PostgreSQL ##'`

            AC_DEFINE([HAVE_POSTGRESQL], [1],
                [Define to 1 if PostgreSQL libraries are available])

            found_postgresql="yes"
            AC_MSG_RESULT([yes])
        else
            found_postgresql="no"
            AC_MSG_RESULT([no])
        fi
    fi

    dnl
    dnl Check for function PQserverVersion()
    dnl

    _save_postgresql_cflags="${CFLAGS}"
    _save_postgresql_ldflags="${LDFLAGS}"
    _save_postgresql_libs="${LIBS}"
    CFLAGS="${CFLAGS} ${POSTGRESQL_CPPFLAGS}"
    LDFLAGS="${LDFLAGS} ${POSTGRESQL_LDFLAGS}"
    LIBS="${LIBS} ${POSTGRESQL_LIBS}"

    AC_MSG_CHECKING(for function PQserverVersion())
    AC_TRY_LINK(
[
#include <libpq-fe.h>
],
[
PGconn	*conn = NULL;
PQserverVersion(conn);
],
    AC_DEFINE(HAVE_FUNCTION_PQSERVERVERSION,1,[Define to 1 if 'PQserverVersion' exist.])
    AC_MSG_RESULT(yes),
    AC_MSG_RESULT(no))

    CFLAGS="${_save_postgresql_cflags}"
    LDFLAGS="${_save_postgresql_ldflags}"
    LIBS="${_save_postgresql_libs}"
    unset _save_postgresql_cflags
    unset _save_postgresql_ldflags
    unset _save_postgresql_libs

    dnl
    dnl Check if required version of PostgreSQL is available
    dnl


    postgresql_version_req=ifelse([$1], [], [], [$1])

    if test "x$found_postgresql" = "xyes"; then

        dnl Decompose version string of installed PostgreSQL
        dnl and calculate its number representation
        postgresql_version_major=`expr $POSTGRESQL_VERSION : '\([[0-9]]*\)'`
        postgresql_version_minor=`expr $POSTGRESQL_VERSION : '[[0-9]]*\.\([[0-9]]*\)'`
        postgresql_version_micro=`expr $POSTGRESQL_VERSION : '[[0-9]]*\.[[0-9]]*\.\([[0-9]]*\)'`
        if test "x$postgresql_version_micro" = "x"; then
            postgresql_version_micro="0"
        fi

        postgresql_version_number=`expr $postgresql_version_major \* 1000000 \
                                   \+ $postgresql_version_minor \* 1000 \
                                   \+ $postgresql_version_micro`

        if test -n "$postgresql_version_req"; then

            AC_MSG_CHECKING([if PostgreSQL version is >= $postgresql_version_req])

            dnl Decompose required version string of PostgreSQL
            dnl and calculate its number representation
            postgresql_version_req_major=`expr $postgresql_version_req : '\([[0-9]]*\)'`
            postgresql_version_req_minor=`expr $postgresql_version_req : '[[0-9]]*\.\([[0-9]]*\)'`
            postgresql_version_req_micro=`expr $postgresql_version_req : '[[0-9]]*\.[[0-9]]*\.\([[0-9]]*\)'`
            if test "x$postgresql_version_req_micro" = "x"; then
                postgresql_version_req_micro="0"
            fi

            postgresql_version_req_number=`expr $postgresql_version_req_major \* 1000000 \
                                       \+ $postgresql_version_req_minor \* 1000 \
                                       \+ $postgresql_version_req_micro`

            postgresql_version_check=`expr $postgresql_version_number \>\= $postgresql_version_req_number`
            if test "$postgresql_version_check" = "1"; then
                AC_MSG_RESULT([yes])
            else
                AC_MSG_RESULT([no])
            fi

	fi

    fi

    AC_SUBST([POSTGRESQL_CPPFLAGS])
    AC_SUBST([POSTGRESQL_LDFLAGS])
    AC_SUBST([POSTGRESQL_LIBS])
    AC_SUBST([POSTGRESQL_VERSION])
])
