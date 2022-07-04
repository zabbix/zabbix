# OpenSSL LIBOPENSSL_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
# Derived from libssh2.m4
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

	SSL	*ssl = NULL;

	SSL_connect(ssl);	/* a function from libssl, present in both OpenSSL 1.0.1 and 1.1.0 */
	BIO_new(BIO_s_mem());	/* a function from libcrypto */
],
found_openssl="yes",)
])dnl

AC_DEFUN([LIBOPENSSL_TRY_LINK_PSK],
[
AC_TRY_LINK(
[
#include <openssl/ssl.h>
],
[
	/* check if OPENSSL_NO_PSK is defined */
#ifdef OPENSSL_NO_PSK
#	error "OPENSSL_NO_PSK is defined. PSK support will not be available."
#endif
],
found_openssl_with_psk="yes",)
])dnl

AC_DEFUN([LIBOPENSSL_ACCEPT_VERSION],
[
	# Zabbix minimal supported version of OpenSSL.
	# Version numbering scheme is described in /usr/include/openssl/opensslv.h.

	# Is it OpenSSL 3? Test OPENSSL_VERSION_MAJOR - it is defined only in OpenSSL 3.0.
	found_openssl_version=`grep OPENSSL_VERSION_MAJOR "$1" | head -n 1`
	found_openssl_version=`expr "$found_openssl_version" : '^#.*define.*OPENSSL_VERSION_MAJOR.*\(3\)$'`

	if test "$found_openssl_version" = "3"; then
		# OpenSSL 3.x found
		accept_openssl_version="yes"
	else	# Is it OpenSSL 1.0.1 - 1.1.1 or LibreSSL?
		# These versions use similar version numbering scheme:
		# specify version number without the last byte (status). E.g., version 1.0.1 is 0x1000100f, but without the
		# last byte it is 0x1000100.
		minimal_openssl_version=0x1000100

		found_openssl_version=`grep OPENSSL_VERSION_NUMBER "$1"`
		found_openssl_version=`expr "$found_openssl_version" : '.*\(0x[[0-f]][[0-f]][[0-f]][[0-f]][[0-f]][[0-f]][[0-f]]\).*'`

		# compare versions lexicographically
		openssl_version_check=`expr $found_openssl_version \>\= $minimal_openssl_version`

		if test "$openssl_version_check" = "1"; then
			accept_openssl_version="yes"
		else
			accept_openssl_version="no"
		fi;
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
	    _libopenssl_dir_lib="$withval/lib"
	fi
	accept_openssl_version="no"
    ],[want_openssl=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_openssl" = "xyes"; then

    if test "x$enable_static_libs" = "xyes"; then
        test "x$static_linking_support" = "xno" -a -z "$_libopenssl_dir_lib" && AC_MSG_ERROR(["OpenSSL: Compiler not support statically linked libs from default folders"])
        AC_REQUIRE([PKG_PROG_PKG_CONFIG])
        m4_ifdef([PKG_PROG_PKG_CONFIG], [PKG_PROG_PKG_CONFIG()], [:])
        test -z "$PKG_CONFIG" -a -z "$_libopenssl_dir_lib" && AC_MSG_ERROR([Not found pkg-config library])
        _libopenssl_dir_lib_64="$_libopenssl_dir_lib/64"
        test -d "$_libopenssl_dir_lib_64" && _libopenssl_dir_lib="$_libopenssl_dir_lib_64"
        m4_pattern_allow([^PKG_CONFIG_LIBDIR$])
    fi

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

         if test -d $_libopenssl_dir/lib64; then
           OPENSSL_LDFLAGS=-L$_libopenssl_dir/lib64
         elif test -d $_libopenssl_dir/lib/64; then
           OPENSSL_LDFLAGS=-L$_libopenssl_dir/lib/64
         else
           OPENSSL_LDFLAGS=-L$_libopenssl_dir/lib
         fi

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

    if test "x$enable_static_libs" = "xyes" -a -z "$PKG_CONFIG"; then
      OPENSSL_LIBS="$_libopenssl_dir_lib/libssl.a $_libopenssl_dir_lib/libcrypto.a"
    elif test "x$enable_static_libs" = "xyes"; then
      if test -z "$_libopenssl_dir_lib"; then
        m4_ifdef([PKG_CHECK_EXISTS], [
          PKG_CHECK_EXISTS(openssl,[
            OPENSSL_LIBS=`$PKG_CONFIG --static --libs openssl`
          ],[
            AC_MSG_ERROR([Not found openssl package])
          ])
        ], [:])
      else
        AC_RUN_LOG([PKG_CONFIG_LIBDIR="$_libopenssl_dir_lib/pkgconfig" $PKG_CONFIG --exists --print-errors openssl]) ||
          AC_MSG_ERROR(["Not found openssl package in $_libopenssl_dir_lib/pkgconfig"])
        OPENSSL_LIBS=`PKG_CONFIG_LIBDIR="$_libopenssl_dir_lib/pkgconfig" $PKG_CONFIG --static --libs openssl`
        test -z "$OPENSSL_LIBS" && OPENSSL_LIBS=`PKG_CONFIG_LIBDIR="$_libopenssl_dir_lib/pkgconfig" $PKG_CONFIG --libs openssl`
      fi

      if test "x$static_linking_support" = "xno"; then
        OPENSSL_LIBS=`echo "$OPENSSL_LIBS"|sed "s|-lssl|$_libopenssl_dir_lib/libssl.a|g"|sed "s|-lcrypto|$_libopenssl_dir_lib/libcrypto.a|g"`
      else
        OPENSSL_LIBS=`echo "$OPENSSL_LIBS"|sed "s/-lssl/${static_linking_support}static -lssl ${static_linking_support}dynamic/g"|sed "s/-lcrypto/${static_linking_support}static -lcrypto ${static_linking_support}dynamic/g"`
      fi
    fi

    CFLAGS="$CFLAGS $OPENSSL_CFLAGS"
    LDFLAGS="$LDFLAGS $OPENSSL_LDFLAGS"
    LIBS="$OPENSSL_LIBS $LIBS"

    found_openssl="no"
    LIBOPENSSL_TRY_LINK([no])

    if test "x$found_openssl" = "xyes"; then
      AC_DEFINE([HAVE_OPENSSL], 1, [Define to 1 if you have 'libssl' and 'libcrypto' libraries (-lssl -libcrypto)])
      AC_MSG_RESULT(yes)

      AC_MSG_CHECKING(if OpenSSL supports PSK)
      found_openssl_with_psk="no"
      LIBOPENSSL_TRY_LINK_PSK([no])
      if test "x$found_openssl_with_psk" = "xyes"; then
        AC_DEFINE([HAVE_OPENSSL_WITH_PSK], 1, [Define to 1 if you have OpenSSL with PSK support])
        AC_MSG_RESULT(yes)
        found_openssl="OpenSSL"
      else
        AC_MSG_RESULT(no)
        found_openssl="OpenSSL (PSK not supported)"
      fi

    else
      AC_MSG_RESULT(no)
      OPENSSL_CFLAGS=""
      OPENSSL_LDFLAGS=""
      OPENSSL_LIBS=""
    fi

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"
  fi

  AC_SUBST(OPENSSL_CFLAGS)
  AC_SUBST(OPENSSL_LDFLAGS)
  AC_SUBST(OPENSSL_LIBS)

])dnl
