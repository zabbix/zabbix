AC_DEFUN([AX_LIB_IBM_DB2],
[
    AC_ARG_WITH([ibm-db2-include],
        AC_HELP_STRING([--with-ibm-db2-include=@<:@DIR@:>@],
            [use IBM DB2 headers from given path]
        ),
        [
        if test "$withval" != "no"; then
            want_ibm_db2="yes"
            ibm_db2_include_dir="$withval"
        fi
        ],
        [ibm_db2_include_dir=""]
    )
    AC_ARG_WITH([ibm-db2-lib],
        AC_HELP_STRING([--with-ibm-db2-lib=@<:@DIR@:>@],
            [use IBM DB2 libraries from given path]
        ),
        [
        if test "$withval" != "no"; then
            want_ibm_db2="yes"
            ibm_db2_lib_dir="$withval"
        fi
        ],
        [ibm_db2_lib_dir=""]
    )

    if test "x$want_ibm_db2" = "xyes"; then
        IBM_DB2_CPPFLAGS="-I$ibm_db2_include_dir"
        IBM_DB2_LDFLAGS="-L$ibm_db2_lib_dir"
        IBM_DB2_LIBS="-ldb2"
        AC_DEFINE(HAVE_IBM_DB2, [1], [Define to 1 if IBM DB2 libraries are available])
    fi
])
