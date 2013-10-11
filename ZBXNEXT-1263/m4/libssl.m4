# OpenSSL CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
# Derived from libssh2.m4 written by
#    Alexander Vladishev                      Oct-26-2009
#    Dmitry Borovikov                         Feb-13-2010
#
# Checks for OpenSSL library libssl.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-libssl or --without-libssl.
# If not supplied, DEFAULT-ACTION is no.
#
# The minimal supported OpenSSL library libssl version is TODO: XXXXX.
#
# This macro #defines HAVE_LIBSSL if a required header files are
# found, and sets @SSL_LDFLAGS@, @SSL_CFLAGS@ and @SSL_LIBS@
# to the necessary values.
#
# Users may override the detected values by doing something like:
# SSL_LIBS="-lssl" SSL_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBSSL_TRY_LINK],
[
AC_TRY_LINK(
[
#include <openssl/ssl.h>
],
[
	SSL_library_init();
],
found_ssl="yes",)
])dnl

AC_DEFUN([LIBSSL_ACCEPT_VERSION],
[
		accept_ssl_version="yes"
])dnl

AC_DEFUN([LIBSSL_CHECK_CONFIG],
[
  AC_ARG_WITH(ssl,[
If you want to use encryption provided by OpenSSL libssl library:
AC_HELP_STRING([--with-ssl@<:@=DIR@:>@],[use SSL package @<:@default=no@:>@, DIR is the SSL library install directory.])],
    [
	if test "$withval" = "no"; then
	    want_ssl="no"
	    _libssl_dir="no"
	elif test "$withval" = "yes"; then
	    want_ssl="yes"
	    _libssl_dir="no"
	else
	    want_ssl="yes"
	    _libssl_dir=$withval
	fi
	accept_ssl_version="no"
    ],[want_ssl=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_ssl" = "xyes"; then
     AC_MSG_CHECKING(for SSL support)
     if test "x$_libssl_dir" = "xno"; then
       if test -f /usr/include/openssl/ssl.h; then
         SSL_CFLAGS=-I/usr/include/openssl
         SSL_LDFLAGS=-L/usr/lib
         SSL_LIBS="-lssl"
         found_ssl="yes"
	 LIBSSL_ACCEPT_VERSION([/usr/include/openssl/ssl.h])
       elif test -f /usr/local/include/openssl/ssl.h; then
         SSL_CFLAGS=-I/usr/local/include/openssl
         SSL_LDFLAGS=-L/usr/local/lib
         SSL_LIBS="-lssl"
         found_ssl="yes"
	 LIBSSL_ACCEPT_VERSION([/usr/local/include/openssl/ssl.h])
       else #libraries are not found in default directories
         found_ssl="no"
         AC_MSG_RESULT(no)
       fi # test -f /usr/include/openssl/ssl.h; then
     else # test "x$_libssl_dir" = "xno"; then
       if test -f $_libssl_dir/include/openssl/ssl.h; then
	 SSL_CFLAGS=-I$_libssl_dir/include/openssl
         SSL_LDFLAGS=-L$_libssl_dir/lib
         SSL_LIBS="-lssl"
         found_ssl="yes"
	 LIBSSL_ACCEPT_VERSION([$_libssl_dir/include/openssl/ssl.h])
       else #if test -f $_libssl_dir/include/openssl/ssl.h; then
         found_ssl="no"
         AC_MSG_RESULT(no)
       fi #test -f $_libssl_dir/include/openssl/ssl.h; then
     fi #if test "x$_libssl_dir" = "xno"; then
  fi # if test "x$want_ssl" != "xno"; then

  if test "x$found_ssl" = "xyes"; then
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $SSL_CFLAGS"
    LDFLAGS="$LDFLAGS $SSL_LDFLAGS"
    LIBS="$LIBS $SSL_LIBS"

    found_ssl="no"
    LIBSSL_TRY_LINK([no])

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_ssl" = "xyes"; then
      AC_DEFINE([HAVE_SSL], 1, [Define to 1 if you have the 'libssl' library (-lssl)])
      AC_MSG_RESULT(yes)
    else
      AC_MSG_RESULT(no)
      SSL_CFLAGS=""
      SSL_LDFLAGS=""
      SSL_LIBS=""
    fi
  fi

  AC_SUBST(SSL_CFLAGS)
  AC_SUBST(SSL_LDFLAGS)
  AC_SUBST(SSL_LIBS)

])dnl
