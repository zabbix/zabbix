##### http://autoconf-archive.cryp.to/ax_lib_mysql.html
#
# SYNOPSIS
#
#   AX_LIB_MYSQL([MINIMUM-VERSION])
#
# DESCRIPTION
#
#   This macro provides tests of availability of MySQL client library
#   of particular version or newer.
#
#   AX_LIB_MYSQL macro takes only one argument which is optional. If
#   there is no required version passed, then macro does not run
#   version test.
#
#   The --with-mysql option takes one of three possible values:
#
#   no - do not check for MySQL client library
#
#   yes - do check for MySQL library in standard locations
#   (mysql_config should be in the PATH)
#
#   path - complete path to mysql_config utility, use this option if
#   mysql_config can't be found in the PATH
#
#   This macro calls:
#
#     AC_SUBST(MYSQL_CFLAGS)
#     AC_SUBST(MYSQL_LDFLAGS)
#     AC_SUBST(MYSQL_LIBS)
#     AC_SUBST(MYSQL_VERSION)
#
#   And sets:
#
#     HAVE_MYSQL
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

AC_DEFUN([LIBMYSQL_OPTIONS_TRY],
[
	AC_MSG_CHECKING([for MySQL init options function])
	AC_TRY_LINK(
[
#include <mysql.h>
],
[
	MYSQL		*mysql;

	mysql_options(mysql, MYSQL_INIT_COMMAND, "set @@session.auto_increment_offset=1");
],
	AC_DEFINE_UNQUOTED([MYSQL_OPTIONS], [mysql_options], [Define mysql options])
	AC_DEFINE_UNQUOTED([MYSQL_OPTIONS_ARGS_VOID_CAST], [], [Define void cast for mysql options args])
	found_mysql_options="yes"
	AC_MSG_RESULT(yes),
	AC_MSG_RESULT(no))
])

AC_DEFUN([LIBMARIADB_OPTIONS_TRY],
[
	AC_MSG_CHECKING([for MariaDB init options function])
	AC_TRY_LINK(
[
#include <mysql.h>
],
[
	MYSQL	*mysql;

	mysql_optionsv(mysql, MYSQL_INIT_COMMAND, (void *)"set @@session.auto_increment_offset=1");
],
	AC_DEFINE_UNQUOTED([MYSQL_OPTIONS], [mysql_optionsv], [Define mysql options])
	AC_DEFINE_UNQUOTED([MYSQL_OPTIONS_ARGS_VOID_CAST], [(void *)], [Define void cast for mysql options args])
	found_mariadb_options="yes"
	AC_MSG_RESULT(yes),
	AC_MSG_RESULT(no))
])

AC_DEFUN([LIBMYSQL_TLS_TRY_LINK],
[
	AC_MSG_CHECKING([for TLS support in MySQL library])
	AC_TRY_LINK(
[
#include <mysql.h>
],
[
	unsigned int	mysql_tls_mode;
	MYSQL		*mysql;

	mysql_tls_mode = SSL_MODE_REQUIRED;
	mysql_options(mysql, MYSQL_OPT_SSL_MODE, &mysql_tls_mode);

	mysql_tls_mode = SSL_MODE_VERIFY_CA;
	mysql_options(mysql, MYSQL_OPT_SSL_MODE, &mysql_tls_mode);

	mysql_tls_mode = SSL_MODE_VERIFY_IDENTITY;
	mysql_options(mysql, MYSQL_OPT_SSL_MODE, &mysql_tls_mode);

	mysql_options(mysql, MYSQL_OPT_SSL_CA, "");
	mysql_options(mysql, MYSQL_OPT_SSL_KEY, "");
	mysql_options(mysql, MYSQL_OPT_SSL_CERT, "");
	mysql_options(mysql, MYSQL_OPT_SSL_CIPHER, "");
],
	AC_DEFINE([HAVE_MYSQL_TLS], [1], [Define to 1 if TLS is supported in MySQL library])
	found_mysql_tls="yes"
	AC_MSG_RESULT(yes),
	AC_MSG_RESULT(no))
])

AC_DEFUN([LIBMYSQL_TLS_CIPHERS_TRY_LINK],
[
	AC_MSG_CHECKING([for TLS ciphersuites in MySQL library])
	AC_TRY_LINK(
[
#include <mysql.h>
],
[
	MYSQL	*mysql;

	mysql_options(mysql, MYSQL_OPT_TLS_CIPHERSUITES, "");
],
	AC_DEFINE([HAVE_MYSQL_TLS_CIPHERSUITES], [1], [Define to 1 if TLS ciphersuites are supported in MySQL library])
	AC_MSG_RESULT(yes),
	AC_MSG_RESULT(no))
])

AC_DEFUN([LIBMARIADB_TLS_TRY_LINK],
[
	AC_MSG_CHECKING([for TLS support in MariaDB library])
	AC_TRY_LINK(
[
#include <mysql.h>
],
[
	MYSQL	*mysql;

	mysql_optionsv(mysql, MYSQL_OPT_SSL_ENFORCE, (void *)"");
	mysql_optionsv(mysql, MYSQL_OPT_SSL_VERIFY_SERVER_CERT, (void *)"");
	mysql_optionsv(mysql, MYSQL_OPT_SSL_CA, (void *)"");
	mysql_optionsv(mysql, MYSQL_OPT_SSL_KEY, (void *)"");
	mysql_optionsv(mysql, MYSQL_OPT_SSL_CERT, (void *)"");
	mysql_optionsv(mysql, MYSQL_OPT_SSL_CIPHER, (void *)"");
],
	AC_DEFINE([HAVE_MARIADB_TLS], [1], [Define to 1 if TLS is supported in MariaDB library])
	found_mariadb_tls="yes"
	AC_MSG_RESULT(yes),
	AC_MSG_RESULT(no))
])

AC_DEFUN([AX_LIB_MYSQL],
[
    MYSQL_CONFIG="no"

    AC_ARG_WITH([mysql],
        AC_HELP_STRING([--with-mysql@<:@=ARG@:>@],
            [use MySQL client library @<:@default=no@:>@, optionally specify path to mysql_config]
        ),
        [
        if test "$withval" = "no"; then
            want_mysql="no"
        elif test "$withval" = "yes"; then
            want_mysql="yes"
        else
            want_mysql="yes"
            MYSQL_CONFIG="$withval"
        fi
        ],
        [want_mysql="no"]
    )

    MYSQL_CFLAGS=""
    MYSQL_LDFLAGS=""
    MYSQL_LIBS=""
    MYSQL_VERSION=""

    dnl
    dnl Check MySQL libraries
    dnl

    if test "$want_mysql" = "yes"; then

        AC_PATH_PROGS(MYSQL_CONFIG, mysql_config mariadb_config)

        if test -x "$MYSQL_CONFIG"; then
            MYSQL_CFLAGS="`$MYSQL_CONFIG --cflags`"
            _full_libmysql_libs="`$MYSQL_CONFIG --libs`"

            _save_mysql_ldflags="${LDFLAGS}"
            _save_mysql_cflags="${CFLAGS}"
            _save_mysql_libs="${LIBS}"
            LDFLAGS="${LDFLAGS} ${_full_libmysql_libs}"
            CFLAGS="${CFLAGS} ${MYSQL_CFLAGS}"

            for i in $_full_libmysql_libs; do
                case $i in
                    -lmysqlclient|-lperconaserverclient|-lmariadbclient|-lmariadb)

                        _lib_name="`echo "$i" | cut -b3-`"
                        AC_CHECK_LIB($_lib_name, main, [
                        	MYSQL_LIBS="-l${_lib_name} ${MYSQL_LIBS}"
                        	],[
                        	AC_MSG_ERROR([Not found $_lib_name library])
                        	])
                ;;
                    -L*)

                        MYSQL_LDFLAGS="${MYSQL_LDFLAGS} $i"
                ;;
                    -R*)

                        MYSQL_LDFLAGS="${MYSQL_LDFLAGS} -Wl,$i"
                ;;
                    -l*)

                        _lib_name="`echo "$i" | cut -b3-`"
                        AC_CHECK_LIB($_lib_name, main, [
                        	MYSQL_LIBS="${MYSQL_LIBS} ${i}"
                        	],[
                        	AC_MSG_ERROR([Not found $i library])
                        	])
                ;;
                esac
            done
            LDFLAGS="${_save_mysql_ldflags}"
            CFLAGS="${_save_mysql_cflags}"

            CFLAGS="${CFLAGS} ${MYSQL_CFLAGS}"
            LDFLAGS="${LDFLAGS} ${MYSQL_LDFLAGS}"
            LIBS="${LIBS} ${MYSQL_LIBS}"
            LIBMYSQL_TLS_TRY_LINK([no])
            if test "$found_mysql_tls" == "yes"; then
                LIBMYSQL_TLS_CIPHERS_TRY_LINK([no])
            else
                LIBMARIADB_TLS_TRY_LINK([no])
            fi

            LIBMARIADB_OPTIONS_TRY([no])
            if test "$found_mariadb_options" != "yes"; then
                LIBMYSQL_OPTIONS_TRY([no])
                if test "$found_mysql_options" != "yes"; then
                    AC_MSG_RESULT([no])
                    AC_MSG_ERROR([Could not find the options function for mysql init])
                fi
            fi

            LDFLAGS="${_save_mysql_ldflags}"
            CFLAGS="${_save_mysql_cflags}"
            LIBS="${_save_mysql_libs}"
            unset _save_mysql_ldflags
            unset _save_mysql_cflags
            MYSQL_VERSION=`$MYSQL_CONFIG --version`

            AC_DEFINE([HAVE_MYSQL], [1],
                [Define to 1 if MySQL libraries are available])

            found_mysql="yes"
        else
            found_mysql="no"
        fi
    fi

    dnl
    dnl Check if required version of MySQL is available
    dnl

    mysql_version_req=ifelse([$1], [], [], [$1])

    if test "$found_mysql" = "yes" -a -n "$mysql_version_req"; then

        AC_MSG_CHECKING([if MySQL version is >= $mysql_version_req])

        dnl Decompose required version string of MySQL
        dnl and calculate its number representation
        mysql_version_req_major=`expr $mysql_version_req : '\([[0-9]]*\)'`
        mysql_version_req_minor=`expr $mysql_version_req : '[[0-9]]*\.\([[0-9]]*\)'`
        mysql_version_req_micro=`expr $mysql_version_req : '[[0-9]]*\.[[0-9]]*\.\([[0-9]]*\)'`
        if test "x$mysql_version_req_micro" = "x"; then
            mysql_version_req_micro="0"
        fi

        mysql_version_req_number=`expr $mysql_version_req_major \* 1000000 \
                                   \+ $mysql_version_req_minor \* 1000 \
                                   \+ $mysql_version_req_micro`

        dnl Decompose version string of installed MySQL
        dnl and calculate its number representation
        mysql_version_major=`expr $MYSQL_VERSION : '\([[0-9]]*\)'`
        mysql_version_minor=`expr $MYSQL_VERSION : '[[0-9]]*\.\([[0-9]]*\)'`
        mysql_version_micro=`expr $MYSQL_VERSION : '[[0-9]]*\.[[0-9]]*\.\([[0-9]]*\)'`
        if test "x$mysql_version_micro" = "x"; then
            mysql_version_micro="0"
        fi

        mysql_version_number=`expr $mysql_version_major \* 1000000 \
                                   \+ $mysql_version_minor \* 1000 \
                                   \+ $mysql_version_micro`

        mysql_version_check=`expr $mysql_version_number \>\= $mysql_version_req_number`
        if test "$mysql_version_check" = "1"; then
            AC_MSG_RESULT([yes])
        else
            AC_MSG_RESULT([no])
        fi
    fi

    AC_SUBST([MYSQL_VERSION])
    AC_SUBST([MYSQL_CFLAGS])
    AC_SUBST([MYSQL_LDFLAGS])
    AC_SUBST([MYSQL_LIBS])
])
