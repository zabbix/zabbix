# PolarSSL CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
# Derived from libssh2.m4 written by
#    Alexander Vladishev                      Oct-26-2009
#    Dmitry Borovikov                         Feb-13-2010
#
# Checks for PolarSSL library libpolarssl.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-polarssl or --without-polarssl.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_POLARSSL if a required header files are
# found, and sets @POLARSSL_LDFLAGS@, @POLARSSL_CFLAGS@ and @POLARSSL_LIBS@
# to the necessary values.
#
# Users may override the detected values by doing something like:
# POLARSSL_LIBS="-lpolarssl" POLARSSL_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBPOLARSSL_TRY_LINK],
[
AC_TRY_LINK(
[
#include <polarssl/ssl.h>
],
[
	ssl_context	ssl;

	ssl_init(&ssl);
],
found_polarssl="yes",)
])dnl

AC_DEFUN([LIBPOLARSSL_CHECK_CONFIG],
[
  AC_ARG_WITH(polarssl,[
If you want to use encryption provided by PolarSSL libpolarssl library:
AC_HELP_STRING([--with-polarssl@<:@=DIR@:>@],[use PolarSSL package @<:@default=no@:>@, DIR is the PolarSSL library libpolarssl install directory.])],
    [
	if test "$withval" = "no"; then
	    want_polarssl="no"
	    _libpolarssl_dir="no"
	elif test "$withval" = "yes"; then
	    want_polarssl="yes"
	    _libpolarssl_dir="no"
	else
	    want_polarssl="yes"
	    _libpolarssl_dir=$withval
	fi
    ],[want_polarssl=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_polarssl" = "xyes"; then
     AC_MSG_CHECKING(for PolarSSL support)
     if test "x$_libpolarssl_dir" = "xno"; then
       if test -f /usr/include/polarssl/ssl.h; then
         POLARSSL_CFLAGS=-I/usr/include/polarssl
         POLARSSL_LDFLAGS=-L/usr/lib
         POLARSSL_LIBS="-lpolarssl"
         found_polarssl="yes"
       elif test -f /usr/local/include/polarssl/ssl.h; then
         POLARSSL_CFLAGS=-I/usr/local/include/polarssl
         POLARSSL_LDFLAGS=-L/usr/local/lib
         POLARSSL_LIBS="-lpolarssl"
         found_polarssl="yes"
       else #libraries are not found in default directories
         found_polarssl="no"
         AC_MSG_RESULT(no)
       fi # test -f /usr/include/polarssl/ssl.h; then
     else # test "x$_libpolarssl_dir" = "xno"; then
       if test -f $_libpolarssl_dir/include/polarssl/ssl.h; then
	 POLARSSL_CFLAGS=-I$_libpolarssl_dir/include/polarssl
         POLARSSL_LDFLAGS=-L$_libpolarssl_dir/lib
         POLARSSL_LIBS="-lpolarssl"
         found_polarssl="yes"
       else #if test -f $_libpolarssl_dir/include/polarssl/ssl.h; then
         found_polarssl="no"
         AC_MSG_RESULT(no)
       fi #test -f $_libpolarssl_dir/include/polarssl/ssl.h; then
     fi #if test "x$_libpolarssl_dir" = "xno"; then
  fi # if test "x$want_polarssl" = "xyes"; then

  if test "x$found_polarssl" = "xyes"; then
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $POLARSSL_CFLAGS"
    LDFLAGS="$LDFLAGS $POLARSSL_LDFLAGS"
    LIBS="$LIBS $POLARSSL_LIBS"

    found_polarssl="no"
    LIBPOLARSSL_TRY_LINK([no])

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_polarssl" = "xyes"; then
      AC_DEFINE([HAVE_POLARSSL], 1, [Define to 1 if you have the 'libpolarssl' library (-lpolarssl)])
      AC_MSG_RESULT(yes)
    else
      AC_MSG_RESULT(no)
      POLARSSL_CFLAGS=""
      POLARSSL_LDFLAGS=""
      POLARSSL_LIBS=""
    fi
  fi

  AC_SUBST(POLARSSL_CFLAGS)
  AC_SUBST(POLARSSL_LDFLAGS)
  AC_SUBST(POLARSSL_LIBS)

])dnl
