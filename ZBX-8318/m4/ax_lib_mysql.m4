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

        AC_PATH_PROG([MYSQL_CONFIG], [mysql_config], [])

        if test -x "$MYSQL_CONFIG"; then

            MYSQL_CFLAGS="`$MYSQL_CONFIG --cflags`"

            _full_libmysql_libs="`$MYSQL_CONFIG --libs`"

            for i in $_full_libmysql_libs; do
                case $i in
		    -lmysqlclient)
		        _client_lib_name="mysqlclient"
	     ;;
		    -lperconaserverclient)
		        _client_lib_name="perconaserverclient"
	        
             ;;
                   -L*)
                        MYSQL_LDFLAGS="${MYSQL_LDFLAGS} $i"
                ;;
                esac
            done

            if test "x$enable_static" = "xyes"; then
               for i in $_full_libmysql_libs; do
                   case $i in
           	      -lmysqlclient|-lperconaserverclient)
           	    ;;
                      -l*)
				_lib_name="`echo "$i" | cut -b3-`"
				AC_CHECK_LIB($_lib_name, main, [
						MYSQL_LIBS="$MYSQL_LIBS $i"
					],[
						AC_MSG_ERROR([Not found $_lib_name library])
					])
                   ;;
                   esac
               done
            fi

		_save_mysql_libs="${LIBS}"
		_save_mysql_ldflags="${LDFLAGS}"
		_save_mysql_cflags="${CFLAGS}"
		LIBS="${LIBS} ${MYSQL_LIBS}"
		LDFLAGS="${LDFLAGS} ${MYSQL_LDFLAGS}"
		CFLAGS="${CFLAGS} ${MYSQL_CFLAGS}"

		AC_CHECK_LIB($_client_lib_name, main, [
			MYSQL_LIBS="-l${_client_lib_name} ${MYSQL_LIBS}"
			],[
			AC_MSG_ERROR([Not found mysqlclient library])
			])

		LIBS="${_save_mysql_libs}"
		LDFLAGS="${_save_mysql_ldflags}"
		CFLAGS="${_save_mysql_cflags}"
		unset _save_mysql_libs
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
