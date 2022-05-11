# LIBLDAP_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for ldap.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-ldap or --without-ldap.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_LDAP if a required header files is
# found, and sets @LDAP_CPPFLAGS@, @LDAP_LDFLAGS@ and @LDAP_LIBS@
# to the necessary values.
#
# Users may override the detected values by doing something like:
# LDAP_LIBS="-lldap" LDAP_CPPFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBLDAP_TRY_LINK],
[
_save_ldap_cppflags="$CPPFLAGS"
_save_ldap_cflags="$CFLAGS"
_save_ldap_ldflags="$LDFLAGS"
_save_ldap_libs="$LIBS"

LIBS="$LIBS $1"
LDFLAGS="$LDFLAGS $2"
CPPFLAGS="$CPPFLAGS $3"
CFLAGS="$CFLAGS $4"
ldap_link="no"

AC_TRY_LINK([
#include <stdio.h>
#include <ldap.h>
#include <lber.h>
#include <ldap_schema.h>
],[
printf("%p,%p", ldap_initialize, ldap_str2attributetype);
printf("%p", ber_free);
return 0;
],[
ldap_link="yes"
])

CPPFLAGS="$_save_ldap_cppflags"
CFLAGS="$_save_ldap_cflags"
LDFLAGS="$_save_ldap_ldflags"
LIBS="$_save_ldap_libs"
unset _save_ldap_cppflags
unset _save_ldap_cflags
unset _save_ldap_ldflags
unset _save_ldap_libs
]dnl
[AS_IF([test "x$ldap_link" = "xyes"], [$5], [$6])]dnl
)dnl


AC_DEFUN([LIBLDAP_CHECK_CONFIG],
[
  AC_ARG_WITH(ldap,[
If you want to check LDAP servers:
AC_HELP_STRING([--with-ldap@<:@=DIR@:>@],[Include LDAP support @<:@default=no@:>@. DIR is the LDAP base install directory, default is to search through a number of common places for the LDAP files.])],
     [ if test "$withval" = "no"; then
            want_ldap="no"
            _libldap_with="no"
        elif test "$withval" = "yes"; then
            want_ldap="yes"
            _libldap_with="yes"
        else
            want_ldap="yes"
            _libldap_with=$withval
        fi
     ],[_libldap_with=ifelse([$1],,[no],[$1])])

  if test "x$_libldap_with" != x"no"; then
        AC_MSG_CHECKING([for LDAP support of ldap.h])

        if test "$_libldap_with" = "yes"; then
                if test -f /usr/local/openldap/include/ldap.h; then
                        LDAP_INCDIR=/usr/local/openldap/include/
                        LDAP_LIBDIR=/usr/local/openldap/lib/
                        found_ldap="yes"
                elif test -f /usr/include/ldap.h; then
                        LDAP_INCDIR=/usr/include
                        LDAP_LIBDIR=/usr/lib
                        found_ldap="yes"
                elif test -f /usr/local/include/ldap.h; then
                        LDAP_INCDIR=/usr/local/include
                        LDAP_LIBDIR=/usr/local/lib
                        found_ldap="yes"
                else
                        found_ldap="no"
                        AC_MSG_RESULT(no)
                fi
        else
                if test -f $_libldap_with/include/ldap.h; then
                        LDAP_INCDIR=$_libldap_with/include
                        LDAP_LIBDIR=$_libldap_with/lib
                        _ldap_dir_lib="$LDAP_LIBDIR"

                        found_ldap="yes"
                else
                        found_ldap="no"
                        AC_MSG_RESULT(no)
                fi
        fi

        if test "x$found_ldap" != "xno"; then

                AC_MSG_RESULT(yes)
                LDAP_CPPFLAGS="-I$LDAP_INCDIR"
                LDAP_LDFLAGS="-L$LDAP_LIBDIR"

                ldap_ver=$(strings `find $LDAP_LIBDIR -name libldap.so` | grep OpenLDAP | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+' | tr -d '.')
                if test -n "$ldap_ver" && test "$ldap_ver" -ge 261; then
                        AC_DEFINE(HAVE_LDAP_SOURCEIP, 1, [Define to 1 if SourceIP is supported.])
                fi

                LDAP_LIBS="-lldap -llber $LDAP_LIBS"

                if test "x$enable_static" = "xyes"; then
                        LDAP_LIBS="$LDAP_LIBS -lgnutls -lpthread -lsasl2"
                elif test "x$enable_static_libs" = "xyes"; then
                        AC_MSG_CHECKING([compatibility of static LDAP libs])
                        test "x$static_linking_support" = "xno" -a -z "$_ldap_dir_lib" && AC_MSG_ERROR(["Compiler not support statically linked libs from default folders"])

                        if test "x$static_linking_support" = "xno"; then
                                LDAP_LIBS=`echo "$LDAP_LIBS"|sed "s|-lldap|$_ldap_dir_lib/libldap.a|g"|sed "s|-llber|$_ldap_dir_lib/liblber.a|g"`
                        fi

                        # without SSL and SASL
                        if test "x$static_linking_support" = "xno"; then
                                TRY_LDAP_LIBS="$LDAP_LIBS -lpthread"
                        else
                                TRY_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS ${static_linking_support}dynamic -lpthread"
                        fi
                        LIBLDAP_TRY_LINK([$TRY_LDAP_LIBS], [$LDAP_LDFLAGS], [$LDAP_CPPFLAGS], ,[
                                LDAP_LIBS=$TRY_LDAP_LIBS
                                AC_MSG_RESULT([without SSL])
                        ])

                        # without SSL
                        if test "x$ldap_link" = "xno"; then
                                if test "x$static_linking_support" = "xno"; then
                                        TRY_LDAP_LIBS="$LDAP_LIBS -lpthread -lsasl2"
                                else
                                        TRY_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS ${static_linking_support}dynamic -lpthread -lsasl2"
                                fi
                                LIBLDAP_TRY_LINK([$TRY_LDAP_LIBS], [$LDAP_LDFLAGS], [$LDAP_CPPFLAGS], ,[
                                        LDAP_LIBS=$TRY_LDAP_LIBS
                                        AC_MSG_RESULT([without SSL])
                                ])
                        fi

                        # without SSL for Solaris
                        if test "x$ldap_link" = "xno"; then
                                if test "x$static_linking_support" = "xno"; then
                                        TRY_LDAP_LIBS="$LDAP_LIBS -lpthread -lsasl"
                                else
                                        TRY_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS ${static_linking_support}dynamic -lpthread -lsasl"
                                fi
                                LIBLDAP_TRY_LINK([$TRY_LDAP_LIBS], [$LDAP_LDFLAGS], [$LDAP_CPPFLAGS], ,[
                                        LDAP_LIBS=$TRY_LDAP_LIBS
                                        AC_MSG_RESULT([without SSL and with sasl])
                                ])
                        fi

                        # with system GnuTLS
                        if test "x$ldap_link" = "xno"; then
                                if test "x$static_linking_support" = "xno"; then
                                        TRY_LDAP_LIBS="$LDAP_LIBS -lgnutls -lsasl2 -lgssapi_krb5 -lpthread"
                                else
                                        TRY_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS ${static_linking_support}dynamic -lgnutls -lsasl2 -lgssapi_krb5 -lpthread"
                                fi
                                LIBLDAP_TRY_LINK([$TRY_LDAP_LIBS], [$LDAP_LDFLAGS], [$LDAP_CPPFLAGS], ,[
                                        LDAP_LIBS=$TRY_LDAP_LIBS
                                        AC_MSG_RESULT([with system GnuTLS linking])
                                ])
                        fi

                        # with static OpenSSL and SASL2
                        if test "x$ldap_link" = "xno" -a "x$want_openssl" = "xyes"; then
                                if test "x$static_linking_support" = "xno"; then
                                        OSSL_LDAP_LIBS="$LDAP_LIBS $OPENSSL_LIBS -lsasl2"
                                else
                                        OSSL_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS -lsasl2 ${static_linking_support}dynamic $OPENSSL_LIBS"
                                fi
                                OSSL_LDAP_CPPFLAGS="$LDAP_CPPFLAGS $OPENSSL_CPPFLAGS"
                                OSSL_LDAP_CFLAGS="$LDAP_CPPFLAGS $OPENSSL_CFLAGS"
                                OSSL_LDAP_LDFLAGS="$LDAP_LDFLAGS $OPENSSL_LDFLAGS"
                                LIBLDAP_TRY_LINK([$OSSL_LDAP_LIBS], [$OSSL_LDAP_LDFLAGS], [$OSSL_LDAP_CPPFLAGS], [$OSSL_LDAP_CFLAGS],[
                                        LDAP_LIBS="$OSSL_LDAP_LIBS"
                                        AC_MSG_RESULT([with static OpenSSL and static sasl2])
                                ])
                        fi

                        # with static OpenSSL
                        if test "x$ldap_link" = "xno" -a "x$want_openssl" = "xyes"; then
                                if test "x$static_linking_support" = "xno"; then
                                        OSSL_LDAP_LIBS="$LDAP_LIBS $OPENSSL_LIBS -lsasl2"
                                else
                                        OSSL_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS ${static_linking_support}dynamic $OPENSSL_LIBS -lsasl2"
                                fi
                                OSSL_LDAP_CPPFLAGS="$LDAP_CPPFLAGS $OPENSSL_CPPFLAGS"
                                OSSL_LDAP_CFLAGS="$LDAP_CPPFLAGS $OPENSSL_CFLAGS"
                                OSSL_LDAP_LDFLAGS="$LDAP_LDFLAGS $OPENSSL_LDFLAGS"
                                LIBLDAP_TRY_LINK([$OSSL_LDAP_LIBS], [$OSSL_LDAP_LDFLAGS], [$OSSL_LDAP_CPPFLAGS], [$OSSL_LDAP_CFLAGS],[
                                        LDAP_LIBS="$OSSL_LDAP_LIBS"
                                        AC_MSG_RESULT([with static OpenSSL])
                                ])
                        fi

                        # with static OpenSSL for Solaris
                        if test "x$ldap_link" = "xno" -a "x$want_openssl" = "xyes"; then
                                if test "x$static_linking_support" = "xno"; then
                                        OSSL_LDAP_LIBS="$LDAP_LIBS $OPENSSL_LIBS -lsasl"
                                else
                                        OSSL_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS ${static_linking_support}dynamic $OPENSSL_LIBS -lsasl"
                                fi
                                OSSL_LDAP_CPPFLAGS="$LDAP_CPPFLAGS $OPENSSL_CPPFLAGS"
                                OSSL_LDAP_CFLAGS="$LDAP_CPPFLAGS $OPENSSL_CFLAGS"
                                OSSL_LDAP_LDFLAGS="$LDAP_LDFLAGS $OPENSSL_LDFLAGS"
                                LIBLDAP_TRY_LINK([$OSSL_LDAP_LIBS], [$OSSL_LDAP_LDFLAGS], [$OSSL_LDAP_CPPFLAGS], [$OSSL_LDAP_CFLAGS],[
                                        LDAP_LIBS="$OSSL_LDAP_LIBS"
                                        AC_MSG_RESULT([with static OpenSSL and sasl])
                                ],[
                                        AC_MSG_ERROR([Not compatible with static OpenLDAP libs version of static OpenSSL: "$OPENSSL_LDFLAGS"])
                                ])
                        fi

                        # with system OpenSSL and SASL2
                        if test "x$ldap_link" = "xno"; then
                                if test "x$static_linking_support" = "xno"; then
                                        TRY_LDAP_LIBS="$LDAP_LIBS -lssl -lsasl2 -lcrypto"
                                else
                                        TRY_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS -lsasl2 ${static_linking_support}dynamic -lssl -lcrypto"
                                fi
                                LIBLDAP_TRY_LINK([$TRY_LDAP_LIBS], [$LDAP_LDFLAGS], [$LDAP_CPPFLAGS], ,[
                                        LDAP_LIBS=$TRY_LDAP_LIBS
                                        AC_MSG_RESULT([with system OpenSSL and static sasl2 linking])
                                ])
                        fi

                        # with system OpenSSL
                        if test "x$ldap_link" = "xno"; then
                                if test "x$static_linking_support" = "xno"; then
                                        TRY_LDAP_LIBS="$LDAP_LIBS -lssl -lsasl2 -lcrypto"
                                else
                                        TRY_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS ${static_linking_support}dynamic -lssl -lsasl2 -lcrypto"
                                fi
                                LIBLDAP_TRY_LINK([$TRY_LDAP_LIBS], [$LDAP_LDFLAGS], [$LDAP_CPPFLAGS], ,[
                                        LDAP_LIBS=$TRY_LDAP_LIBS
                                        AC_MSG_RESULT([with system OpenSSL linking])
                                ])
                        fi

                        # with system OpenSSL for Solaris
                        if test "x$ldap_link" = "xno"; then
                                if test "x$static_linking_support" = "xno"; then
                                        TRY_LDAP_LIBS="$LDAP_LIBS -lssl -lsasl -lcrypto"
                                else
                                        TRY_LDAP_LIBS="${static_linking_support}static $LDAP_LIBS ${static_linking_support}dynamic -lssl -lsasl -lcrypto"
                                fi
                                LIBLDAP_TRY_LINK([$TRY_LDAP_LIBS], [$LDAP_LDFLAGS], [$LDAP_CPPFLAGS], ,[
                                        LDAP_LIBS=$TRY_LDAP_LIBS
                                        AC_MSG_RESULT([with system OpenSSL and sasl linking])
                                ])
                        fi

                        if test "x$ldap_link" = "xno"; then
                                AC_MSG_ERROR([Not found compatible version of OpenLDAP static libs])
                        fi
                fi

                found_ldap="yes"
                AC_DEFINE(HAVE_LDAP,1,[Define to 1 if LDAP should be enabled.])
                AC_DEFINE(LDAP_DEPRECATED, 1, [Define to 1 if LDAP deprecated functions is used.])

                if test "x$enable_static" = "xyes"; then
                        AC_CHECK_LIB(gnutls, main, , AC_MSG_ERROR([Not found GnuTLS library]))
                        AC_CHECK_LIB(pthread, main, , AC_MSG_ERROR([Not found Pthread library]))
                        AC_CHECK_LIB(sasl2, main, , AC_MSG_ERROR([Not found SASL2 library]))
                fi
        fi
  fi

  AC_SUBST(LDAP_CPPFLAGS)
  AC_SUBST(LDAP_LDFLAGS)
  AC_SUBST(LDAP_LIBS)

  unset _libldap_with
])dnl
