AC_DEFUN([AX_LIB_CASSANDRA],
[
    AC_ARG_WITH([cassandra],
        AC_HELP_STRING([--cassandra=@<:@ARG@:>@],
            [use Cassandra for storing historical data (ARG=yes); disable Cassandra support (ARG=no)]
        ),
        [
        if test "$withval" != "no"; then
            want_cassandra="yes"
        fi
        ]
    )

    if test "x$want_cassandra" = "xyes"; then
        AC_MSG_CHECKING([for Cassandra support])

        CASSANDRA_CPPFLAGS="-I/usr/lib/glib-2.0/include -I/usr/include/glib-2.0 -I/usr/local/include/thrift"
        CASSANDRA_LDFLAGS=""
        CASSANDRA_LIBS="-lglib-2.0 -lgobject-2.0 -lthrift -lthrift_c_glib"

        AC_SUBST(CASSANDRA_CPPFLAGS)
        AC_SUBST(CASSANDRA_LDFLAGS)
        AC_SUBST(CASSANDRA_LIBS)

        AC_DEFINE(HAVE_CASSANDRA, [1], [Define to 1 if Cassandra should be used for storing historical data])

        AC_MSG_RESULT(yes)
    fi
])
