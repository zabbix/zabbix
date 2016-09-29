# OpenSSL LIBOPENSSL_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
# Derived from libssh2.m4 written by
#    Alexander Vladishev                      Oct-26-2009
#    Dmitry Borovikov                         Feb-13-2010
#
# Checks for OpenSSL library libssl.  DEFAULT-ACTION is the string yes or
# no to specify whether to default to --with-openssl or --without-openssl.
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
#include <openssl/bio.h>
],
[
	/* check that both libssl and libcrypto are available */

#if OPENSSL_VERSION_NUMBER >= 0x1010000fL	/* OpenSSL 1.1.0 or newer */
	OPENSSL_init_ssl(0, NULL);	/* a function from libssl */
#else
	SSL_library_init();	/* a function from libssl */
#endif
	BIO_new(BIO_s_mem());	/* a function from libcrypto */
],
found_openssl="yes",)
])dnl

AC_DEFUN([LIBOPENSSL_ACCEPT_VERSION],
[
	# Zabbix minimal supported version of OpenSSL.
	# Version numbering scheme is described in /usr/include/openssl/opensslv.h. Specify version number without the
	# last byte (status). E.g., version 1.0.1 is 0x1000100f, but without the last byte it is 0x1000100.
	minimal_openssl_version=0x1000100

	# get version
	found_openssl_version=`grep OPENSSL_VERSION_NUMBER "$1"`
	found_openssl_version=`expr "$found_openssl_version" : '.*\(0x[[0-f]][[0-f]][[0-f]][[0-f]][[0-f]][[0-f]][[0-f]]\).*'`

	# compare versions lexicographically
	openssl_version_check=`expr $found_openssl_version \>\= $minimal_openssl_version`
	if test "$openssl_version_check" = "1"; then
		accept_openssl_version="yes"
	else
		accept_openssl_version="no"
	fi;
])dnl

AC_DEFUN([LIBOPENSSL_CHECK_CONFIG],
[
  AC_ARG_WITH(openssl,[
If you want to use encryption provided by OpenSSL library:
AC_HELP_STRING([--with-openssl@<:@=DIR@:>@],[use OpenSSL package @<:@default=no@:>@, DIR is the libssl and libcrypto install directory.])],
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
	accept_openssl_version="no"
    ],[want_openssl=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_openssl" = "xyes"; then
     AC_MSG_CHECKING(for OpenSSL support)
     if test "x$_libopenssl_dir" = "xno"; then		# if OpenSSL directory is not specified
       if test -f /usr/local/include/openssl/ssl.h -a -f /usr/local/include/openssl/crypto.h; then
         OPENSSL_CFLAGS=-I/usr/local/include
         OPENSSL_LDFLAGS=-L/usr/local/lib
         OPENSSL_LIBS="-lssl -lcrypto"
         found_openssl="yes"
         LIBOPENSSL_ACCEPT_VERSION([/usr/local/include/openssl/opensslv.h])
       elif test -f /usr/include/openssl/ssl.h -a -f /usr/include/openssl/crypto.h; then
         OPENSSL_CFLAGS=-I/usr/include
         OPENSSL_LDFLAGS=-L/usr/lib
         OPENSSL_LIBS="-lssl -lcrypto"
         found_openssl="yes"
         LIBOPENSSL_ACCEPT_VERSION([/usr/include/openssl/opensslv.h])
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
         LIBOPENSSL_ACCEPT_VERSION([$_libopenssl_dir/include/openssl/opensslv.h])
       else						# libraries are not found in specified directories
         found_openssl="no"
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

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_openssl" = "xyes"; then
      AC_DEFINE([HAVE_OPENSSL], 1, [Define to 1 if you have 'libssl' and 'libcrypto' libraries (-lssl -libcrypto)])
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
