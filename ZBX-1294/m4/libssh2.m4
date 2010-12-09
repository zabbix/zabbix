# LIBSSH2_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Alexander Vladishev                      Oct-26-2009
#    Dmitry Borovikov                         Feb-13-2010
#          --version control added (1.0.0)
#
# Checks for ssh2.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-ssh2 or --without-ssh2.
# If not supplied, DEFAULT-ACTION is no.
#
# The minimal supported SSH2 library version is 1.0.0.
#
# This macro #defines HAVE_SSH2 if a required header files are
# found, and sets @SSH2_LDFLAGS@, @SSH2_CFLAGS@ and @SSH2_LIBS@
# to the necessary values.
#
# Users may override the detected values by doing something like:
# SSH2_LIBS="-lssh2" SSH_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBSSH2_TRY_LINK],
[
AC_TRY_LINK(
[
#include <libssh2.h>
],
[
	LIBSSH2_SESSION	*session;
	session = libssh2_session_init();
],
found_ssh2="yes",)
])dnl

AC_DEFUN([LIBSSH2_ACCEPT_VERSION],
[
	# Zabbix minimal major supported version of libssh2:
	minimal_libssh2_major_version=1
	
	# get the major version
	found_ssh2_version_major=`cat $1 | $EGREP \#define.*LIBSSH2_VERSION_MAJOR | $AWK '{print @S|@3;}'`
	
	accept_ssh2_version="no"
	
	if test $found_ssh2_version_major -ge $minimal_libssh2_major_version; then
		accept_ssh2_version="yes"
	fi;
])dnl

AC_DEFUN([LIBSSH2_CHECK_CONFIG],
[
  AC_ARG_WITH(ssh2,[
If you want to use SSH2 based checks:
AC_HELP_STRING([--with-ssh2@<:@=DIR@:>@],[use SSH2 package @<:@default=no@:>@, DIR is the SSH2 library install directory.])],
    [
	if test "$withval" = "no"; then
	    want_ssh2="no"
	    _libssh2_dir="no"
	elif test "$withval" = "yes"; then
	    want_ssh2="yes"
	    _libssh2_dir="no"
	else
	    want_ssh2="yes"
	    _libssh2_dir=$withval
	fi
	accept_ssh2_version="no"
    ],[want_ssh2=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_ssh2" = "xyes"; then
     AC_MSG_CHECKING(for SSH2 support)
     if test "x$_libssh2_dir" = "xno"; then
       if test -f /usr/include/libssh2.h; then
         SSH2_CFLAGS=-I/usr/include
         SSH2_LDFLAGS=-L/usr/lib
         SSH2_LIBS="-lssh2"
         found_ssh2="yes"
	 LIBSSH2_ACCEPT_VERSION([/usr/include/libssh2.h])
       elif test -f /usr/local/include/libssh2.h; then
         SSH2_CFLAGS=-I/usr/local/include
         SSH2_LDFLAGS=-L/usr/local/lib
         SSH2_LIBS="-lssh2"
         found_ssh2="yes"
	 LIBSSH2_ACCEPT_VERSION([/usr/local/include/libssh2.h])
       else #libraries are not found in default directories
         found_ssh2="no"
         AC_MSG_RESULT(no)
       fi # test -f /usr/include/libssh2.h; then
     else # test "x$_libssh2_dir" = "xno"; then
       if test -f $_libssh2_dir/include/libssh2.h; then
	 SSH2_CFLAGS=-I$_libssh2_dir/include
         SSH2_LDFLAGS=-L$_libssh2_dir/lib
         SSH2_LIBS="-lssh2"
         found_ssh2="yes"
	 LIBSSH2_ACCEPT_VERSION([$_libssh2_dir/include/libssh2.h])
       else #if test -f $_libssh2_dir/include/libssh2.h; then
         found_ssh2="no"
         AC_MSG_RESULT(no)
       fi #test -f $_libssh2_dir/include/libssh2.h; then
     fi #if test "x$_libssh2_dir" = "xno"; then
  fi # if test "x$want_ssh2" != "xno"; then

  if test "x$found_ssh2" = "xyes"; then
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $SSH2_CFLAGS"
    LDFLAGS="$LDFLAGS $SSH2_LDFLAGS"
    LIBS="$LIBS $SSH2_LIBS"

    found_ssh2="no"
    LIBSSH2_TRY_LINK([no])

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_ssh2" = "xyes"; then
      AC_DEFINE([HAVE_SSH2], 1, [Define to 1 if you have the 'libssh2' library (-lssh2)])
      AC_MSG_RESULT(yes)
    else
      AC_MSG_RESULT(no)
      SSH2_CFLAGS=""
      SSH2_LDFLAGS=""
      SSH2_LIBS=""
    fi
  fi

  AC_SUBST(SSH2_CFLAGS)
  AC_SUBST(SSH2_LDFLAGS)
  AC_SUBST(SSH2_LIBS)

])dnl
