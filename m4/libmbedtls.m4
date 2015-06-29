# mbed TLS (PolarSSL) LIBMBEDTLS_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
# Derived from libssh2.m4 written by
#    Alexander Vladishev                      Oct-26-2009
#    Dmitry Borovikov                         Feb-13-2010
#
# Checks for mbed TLS (PolarSSL) library libpolarssl.  DEFAULT-ACTION is the
# string yes or no to specify whether to default to --with-mbedtls or
# --without-mbedtls. If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_POLARSSL if a required header files are
# found, and sets @MBEDTLS_LDFLAGS@, @MBEDTLS_CFLAGS@ and @MBEDTLS_LIBS@
# to the necessary values.
#
# Users may override the detected values by doing something like:
# MBEDTLS_LIBS="-lpolarssl" MBEDTLS_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBMBEDTLS_TRY_LINK],
[
AC_TRY_LINK(
[
#include <polarssl/ssl.h>
],
[
	ssl_context	ssl;

	ssl_init(&ssl);
],
found_mbedtls="yes",)
])dnl

AC_DEFUN([LIBMBEDTLS_CHECK_CONFIG],
[
  AC_ARG_WITH(mbedtls,[
If you want to use encryption provided by mbed TLS (PolarSSL) library:
AC_HELP_STRING([--with-mbedtls@<:@=DIR@:>@],[use mbed TLS (PolarSSL) package @<:@default=no@:>@, DIR is the libpolarssl install directory.])],
    [
	if test "$withval" = "no"; then
	    want_mbedtls="no"
	    _libmbedtls_dir="no"
	elif test "$withval" = "yes"; then
	    want_mbedtls="yes"
	    _libmbedtls_dir="no"
	else
	    want_mbedtls="yes"
	    _libmbedtls_dir=$withval
	fi
    ],[want_mbedtls=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_mbedtls" = "xyes"; then
     AC_MSG_CHECKING(for mbed TLS (PolarSSL) support)

     if test "x$_libmbedtls_dir" = "xno"; then
       if test -f /usr/local/include/polarssl/ssl.h; then
         MBEDTLS_CFLAGS=-I/usr/local/include
         MBEDTLS_LDFLAGS=-L/usr/local/lib
         MBEDTLS_LIBS="-lpolarssl"
         found_mbedtls="yes"
       elif test -f /usr/include/polarssl/ssl.h; then
         MBEDTLS_CFLAGS=-I/usr/include
         MBEDTLS_LDFLAGS=-L/usr/lib
         MBEDTLS_LIBS="-lpolarssl"
         found_mbedtls="yes"
       else			# libraries are not found in default directories
         found_mbedtls="no"
         AC_MSG_RESULT(no)
       fi
     else
       if test -f $_libmbedtls_dir/include/polarssl/ssl.h; then
         MBEDTLS_CFLAGS=-I$_libmbedtls_dir/include
         MBEDTLS_LDFLAGS=-L$_libmbedtls_dir/lib
         MBEDTLS_LIBS="-lpolarssl"
         found_mbedtls="yes"
       else
         found_mbedtls="no"
         AC_MSG_RESULT(no)
       fi
     fi
  fi

  if test "x$found_mbedtls" = "xyes"; then
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $MBEDTLS_CFLAGS"
    LDFLAGS="$LDFLAGS $MBEDTLS_LDFLAGS"
    LIBS="$LIBS $MBEDTLS_LIBS"

    found_mbedtls="no"
    LIBMBEDTLS_TRY_LINK([no])

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_mbedtls" = "xyes"; then
      AC_DEFINE([HAVE_POLARSSL], 1, [Define to 1 if you have the 'libpolarssl' library (-lpolarssl)])
      AC_MSG_RESULT(yes)
    else
      AC_MSG_RESULT(no)
      MBEDTLS_CFLAGS=""
      MBEDTLS_LDFLAGS=""
      MBEDTLS_LIBS=""
    fi
  fi

  AC_SUBST(MBEDTLS_CFLAGS)
  AC_SUBST(MBEDTLS_LDFLAGS)
  AC_SUBST(MBEDTLS_LIBS)

])dnl
