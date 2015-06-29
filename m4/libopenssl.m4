# OpenSSL CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
# Derived from libssh2.m4 written by
#    Alexander Vladishev                      Oct-26-2009
#    Dmitry Borovikov                         Feb-13-2010
#
# Checks for OpenSSL library libssl. DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-openssl or --without-openssl.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_OPENSSL if a required header files are
# found, and sets @OPENSSL_LDFLAGS@, @OPENSSL_CFLAGS@ and @OPENSSL_LIBS@
# to the necessary values.
#
# Users may override the detected values by doing something like:
# OPENSSL_LIBS="-lssl" OPENSSL_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBOPENSSL_TRY_LINK],
[
AC_TRY_LINK(
[
#include <openssl/ssl.h>
],
[
	SSL_library_init();
],
found_openssl="yes",)
])dnl

AC_DEFUN([LIBCRYPTO_TRY_LINK],
[
AC_TRY_LINK(
[
#include <openssl/crypto.h>
],
[
	CRYPTO_memcmp("A", "B", 1);
],
found_openssl="yes",)
])dnl

AC_DEFUN([LIBOPENSSL_CHECK_CONFIG],
[
  AC_ARG_WITH(openssl,[
If you want to use encryption provided by OpenSSL libssl and libcrypto libraries:
AC_HELP_STRING([--with-openssl@<:@=DIR@:>@],[use OpenSSL package @<:@default=no@:>@, DIR is the OpenSSL libraries libssl and libcrypto install directory.])],
    [
	if test "$withval" = "no"; then
	    want_openssl="no"
	    _libopenssl_dir="no"
	elif test "$withval" = "yes"; then
	    want_openssl="yes"
	    _libopenssl_dir="no"
	else
	    want_openssl="yes"
	    _libopenssl_dir=$withval
	fi
    ],[want_openssl=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_openssl" = "xyes"; then
     AC_MSG_CHECKING(for OpenSSL support)
     if test "x$_libopenssl_dir" = "xno"; then		# if OpenSSL directory is not specified
       if test -f /usr/include/openssl/ssl.h -a -f /usr/include/openssl/crypto.h; then
         OPENSSL_CFLAGS=-I/usr/include
         OPENSSL_LDFLAGS=-L/usr/lib
         OPENSSL_LIBS="-lssl -lcrypto"
         found_openssl="yes"
       elif test -f /usr/local/include/openssl/ssl.h -a -f /usr/local/include/openssl/crypto.h; then
         OPENSSL_CFLAGS=-I/usr/local/include
         OPENSSL_LDFLAGS=-L/usr/local/lib
         OPENSSL_LIBS="-lssl -lcrypto"
         found_openssl="yes"
       else						# libraries are not found in default directories
         found_openssl="no"
         AC_MSG_RESULT(no)
       fi
     else						# search in the specified OpenSSL directory
       if test -f $_libopenssl_dir/include/openssl/ssl.h -a -f $_libopenssl_dir/include/openssl/crypto.h; then
	 OPENSSL_CFLAGS=-I$_libopenssl_dir/include
         OPENSSL_LDFLAGS=-L$_libopenssl_dir/lib
         OPENSSL_LIBS="-lssl -lcrypto"
         found_openssl="yes"
       else						# libraries are not found in specified directories
         found_libssl="no"
         AC_MSG_RESULT(no)
       fi
     fi
  fi

  if test "x$found_openssl" = "xyes"; then
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $OPENSSL_CFLAGS"
    LDFLAGS="$LDFLAGS $OPENSSL_LDFLAGS"
    LIBS="$LIBS $OPENSSL_LIBS"

    found_openssl="no"
    LIBOPENSSL_TRY_LINK([no])
    LIBCRYPTO_TRY_LINK([no])

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_openssl" = "xyes"; then
      AC_DEFINE([HAVE_OPENSSL], 1, [Define to 1 if you have the 'libssl' library (-lssl)])
      AC_MSG_RESULT(yes)
    else
      AC_MSG_RESULT(no)
      OPENSSL_CFLAGS=""
      OPENSSL_LDFLAGS=""
      OPENSSL_LIBS=""
    fi
  fi

  AC_SUBST(OPENSSL_CFLAGS)
  AC_SUBST(OPENSSL_LDFLAGS)
  AC_SUBST(OPENSSL_LIBS)

])dnl
