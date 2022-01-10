# GnuTLS LIBGNUTLS_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
# Derived from libssh2.m4
#
# Checks for GnuTLS library libgnutls.  DEFAULT-ACTION is the string
# yes or no to specify whether to default to --with-gnutls or
# --without-gnutls. If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_GNUTLS if a required header files are
# found, and sets @GNUTLS_LDFLAGS@, @GNUTLS_CFLAGS@ and @GNUTLS_LIBS@
# to the necessary values.
#
# Users may override the detected values by doing something like:
# GNUTLS_LIBS="-lgnutls" GNUTLS_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBGNUTLS_TRY_LINK],
[
AC_TRY_LINK(
[
#include <gnutls/gnutls.h>
],
[
	gnutls_global_init();
],
found_gnutls="yes",)
])dnl

AC_DEFUN([LIBGNUTLS_ACCEPT_VERSION],
[
	# Zabbix minimal supported version of libgnutls:
	minimal_gnutls_version_major=3
	minimal_gnutls_version_minor=1
	minimal_gnutls_version_patch=18

	# get version
	found_gnutls_version_major=`cat $1 | $EGREP \#define.*GNUTLS_VERSION_MAJOR | $AWK '{print @S|@3;}'`
	found_gnutls_version_minor=`cat $1 | $EGREP \#define.*GNUTLS_VERSION_MINOR | $AWK '{print @S|@3;}'`
	found_gnutls_version_patch=`cat $1 | $EGREP \#define.*GNUTLS_VERSION_PATCH | $AWK '{print @S|@3;}'`

	if test $((found_gnutls_version_major)) -gt $((minimal_gnutls_version_major)); then
		accept_gnutls_version="yes"
	elif test $((found_gnutls_version_major)) -lt $((minimal_gnutls_version_major)); then
		accept_gnutls_version="no"
	elif test $((found_gnutls_version_minor)) -gt $((minimal_gnutls_version_minor)); then
		accept_gnutls_version="yes"
	elif test $((found_gnutls_version_minor)) -lt $((minimal_gnutls_version_minor)); then
		accept_gnutls_version="no"
	elif test $((found_gnutls_version_patch)) -ge $((minimal_gnutls_version_patch)); then
		accept_gnutls_version="yes"
	else
		accept_gnutls_version="no"
	fi;
])dnl

AC_DEFUN([LIBGNUTLS_CHECK_CONFIG],
[
  AC_ARG_WITH(gnutls,[
If you want to use encryption provided by GnuTLS library:
AC_HELP_STRING([--with-gnutls@<:@=DIR@:>@],[use GnuTLS package @<:@default=no@:>@, DIR is the libgnutls install directory.])],
    [
	if test "$withval" = "no"; then
	    want_gnutls="no"
	    _libgnutls_dir="no"
	elif test "$withval" = "yes"; then
	    want_gnutls="yes"
	    _libgnutls_dir="no"
	else
	    want_gnutls="yes"
	    _libgnutls_dir=$withval
	fi
	accept_gnutls_version="no"
    ],[want_gnutls=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_gnutls" = "xyes"; then
     AC_MSG_CHECKING(for GnuTLS support)
     if test "x$_libgnutls_dir" = "xno"; then
       if test -f /usr/local/include/gnutls/gnutls.h; then
         GNUTLS_CFLAGS=-I/usr/local/include
         GNUTLS_LDFLAGS=-L/usr/local/lib
         GNUTLS_LIBS="-lgnutls"
         found_gnutls="yes"
         LIBGNUTLS_ACCEPT_VERSION([/usr/local/include/gnutls/gnutls.h])
       elif test -f /usr/include/gnutls/gnutls.h; then
         GNUTLS_CFLAGS=-I/usr/include
         GNUTLS_LDFLAGS=-L/usr/lib
         GNUTLS_LIBS="-lgnutls"
         found_gnutls="yes"
         LIBGNUTLS_ACCEPT_VERSION([/usr/include/gnutls/gnutls.h])
       else #libraries are not found in default directories
         found_gnutls="no"
         AC_MSG_RESULT(no)
       fi
     else
       if test -f $_libgnutls_dir/include/gnutls/gnutls.h; then
         GNUTLS_CFLAGS=-I$_libgnutls_dir/include
         GNUTLS_LDFLAGS=-L$_libgnutls_dir/lib
         GNUTLS_LIBS="-lgnutls"
         found_gnutls="yes"
         LIBGNUTLS_ACCEPT_VERSION([$_libgnutls_dir/include/gnutls/gnutls.h])
       else
         found_gnutls="no"
         AC_MSG_RESULT(no)
       fi
     fi
  fi

  if test "x$found_gnutls" = "xyes"; then
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $GNUTLS_CFLAGS"
    LDFLAGS="$LDFLAGS $GNUTLS_LDFLAGS"
    LIBS="$LIBS $GNUTLS_LIBS"

    found_gnutls="no"
    LIBGNUTLS_TRY_LINK([no])

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_gnutls" = "xyes"; then
      AC_DEFINE([HAVE_GNUTLS], 1, [Define to 1 if you have the 'libgnutls' library (-lgnutls)])
      AC_MSG_RESULT(yes)
    else
      AC_MSG_RESULT(no)
      GNUTLS_CFLAGS=""
      GNUTLS_LDFLAGS=""
      GNUTLS_LIBS=""
    fi
  fi

  AC_SUBST(GNUTLS_CFLAGS)
  AC_SUBST(GNUTLS_LDFLAGS)
  AC_SUBST(GNUTLS_LIBS)

])dnl
