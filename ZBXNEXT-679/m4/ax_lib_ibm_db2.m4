AC_DEFUN([AX_LIB_IBM_DB2],
[
    AC_ARG_WITH([ibm-db2],
        AC_HELP_STRING([--with-ibm-db2=@<:@ARG@:>@],
            [use IBM DB2 CLI from given sqllib directory (ARG=path); use /home/db2inst1/sqllib (ARG=yes); disable IBM DB2 support (ARG=no)]
        ),
        [
        if test "$withval" != "no"; then
            want_ibm_db2="yes"
            if test "$withval" != "yes"; then
                ibm_db2_include_dir="$withval/include"
                ibm_db2_lib_dir="$withval/lib"
            else
                ibm_db2_include_dir=/home/db2inst1/sqllib/include
                ibm_db2_lib_dir=/home/db2inst1/sqllib/lib
            fi
        fi
        ]
    )
    AC_ARG_WITH([ibm-db2-include],
        AC_HELP_STRING([--with-ibm-db2-include=@<:@DIR@:>@],
            [use IBM DB2 CLI headers from given path]
        ),
        [
        if test "$withval" != "no"; then
            want_ibm_db2="yes"
            ibm_db2_include_dir="$withval"
        fi
        ]
    )
    AC_ARG_WITH([ibm-db2-lib],
        AC_HELP_STRING([--with-ibm-db2-lib=@<:@DIR@:>@],
            [use IBM DB2 CLI libraries from given path]
        ),
        [
        if test "$withval" != "no"; then
            want_ibm_db2="yes"
            ibm_db2_lib_dir="$withval"
        fi
        ]
    )

    if test "x$want_ibm_db2" = "xyes"; then
        IBM_DB2_CPPFLAGS="-I$ibm_db2_include_dir"
        IBM_DB2_LDFLAGS="-L$ibm_db2_lib_dir"
        IBM_DB2_LIBS="-ldb2"

        saved_CPPFLAGS="$CPPFLAGS"
        saved_LDFLAGS="$LDFLAGS"
        saved_LIBS="$LIBS"
        CPPFLAGS="$CPPFLAGS $IBM_DB2_CPPFLAGS"
        LDFLAGS="$LDFLAGS $IBM_DB2_LDFLAGS"
        LIBS="$LIBS $IBM_DB2_LIBS"

        AC_MSG_CHECKING([for IBM DB2 CLI libraries])
        AC_TRY_LINK([#include <sqlcli1.h>],
                [SQLHANDLE hdbc;
                SQLRETURN sqlr;
                sqlr = SQLDriverConnect(hdbc, 0, "", SQL_NTS, 0, 0, 0, SQL_DRIVER_NOPROMPT);
                ],
                AC_DEFINE(HAVE_IBM_DB2, [1], [Define to 1 if IBM DB2 CLI libraries are available])
                found_ibm_db2="yes"
                AC_MSG_RESULT(yes),
                AC_MSG_RESULT(no))

        CPPFLAGS="$saved_CPPFLAGS"
        LDFLAGS="$saved_LDFLAGS"
        LIBS="$saved_LIBS"
    fi
])
