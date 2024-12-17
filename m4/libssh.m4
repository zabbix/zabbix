# LIBSSH_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for ssh.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-ssh or --without-ssh.
# If not supplied, DEFAULT-ACTION is no.
#
# The minimal supported SSH library version is 0.6.0.
#
# This macro #defines HAVE_SSH if a required header files are
# found, and sets @SSH_LDFLAGS@, @SSH_CFLAGS@ and @SSH_LIBS@
# to the necessary values.
#
# Users may override the detected values by doing something like:
# SSH_LIBS="-lssh" SSH_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBSSH_TRY_LINK],
[
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <libssh/libssh.h>
]], [[
	ssh_session my_ssh_session;
	my_ssh_session = ssh_new();
]])],[found_ssh="yes"],[])
])dnl

AC_DEFUN([LIBSSH_ACCEPT_VERSION],
[
	# Zabbix minimal major supported version of libssh:
	minimal_libssh_major_version=0
	minimal_libssh_minor_version=6

	# get the major version
	found_ssh_version_major=`cat $1 | $EGREP \#define.*'LIBSSH_VERSION_MAJOR ' | $AWK '{print @S|@3;}'`
	found_ssh_version_minor=`cat $1 | $EGREP \#define.*'LIBSSH_VERSION_MINOR ' | $AWK '{print @S|@3;}'`

	if test $((found_ssh_version_major)) -gt $((minimal_libssh_major_version)); then
		accept_ssh_version="yes"
	elif test $((found_ssh_version_major)) -lt $((minimal_libssh_major_version)); then
		accept_ssh_version="no"
	elif test $((found_ssh_version_minor)) -ge $((minimal_libssh_minor_version)); then
		accept_ssh_version="yes"
	else
		accept_ssh_version="no"
	fi;
])dnl

AC_DEFUN([LIBSSH_CHECK_CONFIG],
[
  AC_ARG_WITH(ssh,[
If you want to use SSH based checks:
AS_HELP_STRING([--with-ssh@<:@=DIR@:>@],[use SSH package @<:@default=no@:>@, DIR is the SSH library install directory.])],
    [
	if test "$withval" = "no"; then
	    want_ssh="no"
	    _libssh_dir="no"
	elif test "$withval" = "yes"; then
	    want_ssh="yes"
	    _libssh_dir="no"
	else
	    want_ssh="yes"
	    _libssh_dir=$withval
	fi
	accept_ssh_version="no"
    ],[want_ssh=ifelse([$1],,[no],[$1])]
  )

  if test "x$want_ssh" = "xyes"; then
     AC_MSG_CHECKING(for SSH support)
     if test "x$_libssh_dir" = "xno"; then
       if test -f /usr/include/libssh/libssh_version.h; then
         SSH_CFLAGS=-I/usr/include
         SSH_LDFLAGS=-L/usr/lib
         SSH_LIBS="-lssh"
         found_ssh="yes"
	 LIBSSH_ACCEPT_VERSION([/usr/include/libssh/libssh_version.h])
       fi

       if test "x$accept_ssh_version" == xno && test -f /usr/include/libssh/libssh.h; then
         SSH_CFLAGS=-I/usr/include
         SSH_LDFLAGS=-L/usr/lib
         SSH_LIBS="-lssh"
         found_ssh="yes"
	 LIBSSH_ACCEPT_VERSION([/usr/include/libssh/libssh.h])
       fi

       if test "x$accept_ssh_version" == xno && test -f /usr/local/include/libssh/libssh.h; then
         SSH_CFLAGS=-I/usr/local/include
         SSH_LDFLAGS=-L/usr/local/lib
         SSH_LIBS="-lssh"
         found_ssh="yes"
	 LIBSSH_ACCEPT_VERSION([/usr/local/include/libssh/libssh.h])
       fi

       if test "x$accept_ssh_version" == xno; then
         found_ssh="no"
         AC_MSG_RESULT(no)
       fi
     else # test "x$_libssh_dir" = "xno"; then
       if test -f $_libssh_dir/include/libssh/libssh_version.h; then
         SSH_CFLAGS=-I$_libssh_dir/include
         SSH_LDFLAGS=-L$_libssh_dir/lib
         SSH_LIBS="-lssh"
         found_ssh="yes"
         LIBSSH_ACCEPT_VERSION([$_libssh_dir/include/libssh/libssh_version.h])
       fi

       if test "x$accept_ssh_version" == xno && test -f $_libssh_dir/include/libssh/libssh.h; then
	 SSH_CFLAGS=-I$_libssh_dir/include
         SSH_LDFLAGS=-L$_libssh_dir/lib
         SSH_LIBS="-lssh"
         found_ssh="yes"
	 LIBSSH_ACCEPT_VERSION([$_libssh_dir/include/libssh/libssh.h])
       fi

       if test "x$accept_ssh_version" == xno; then
         found_ssh="no"
         AC_MSG_RESULT(no)
       fi
     fi #if test "x$_libssh_dir" = "xno"; then
  fi # if test "x$want_ssh" != "xno"; then

  if test "x$found_ssh" = "xyes"; then
    am_save_cflags="$CFLAGS"
    am_save_ldflags="$LDFLAGS"
    am_save_libs="$LIBS"

    CFLAGS="$CFLAGS $SSH_CFLAGS"
    LDFLAGS="$LDFLAGS $SSH_LDFLAGS"
    LIBS="$LIBS $SSH_LIBS"

    found_ssh="no"
    LIBSSH_TRY_LINK([no])

    CFLAGS="$am_save_cflags"
    LDFLAGS="$am_save_ldflags"
    LIBS="$am_save_libs"

    if test "x$found_ssh" = "xyes"; then
      AC_DEFINE([HAVE_SSH], 1, [Define to 1 if you have the 'libssh' library (-lssh)])
      AC_MSG_RESULT(yes)

      ENUM_CHECK([SSH_OPTIONS_KEY_EXCHANGE],[libssh/libssh.h])
      ENUM_CHECK([SSH_OPTIONS_HOSTKEYS],[libssh/libssh.h])
      ENUM_CHECK([SSH_OPTIONS_CIPHERS_C_S],[libssh/libssh.h])
      ENUM_CHECK([SSH_OPTIONS_CIPHERS_S_C],[libssh/libssh.h])
      ENUM_CHECK([SSH_OPTIONS_HMAC_C_S],[libssh/libssh.h])
      ENUM_CHECK([SSH_OPTIONS_HMAC_S_C],[libssh/libssh.h])
      ENUM_CHECK([SSH_OPTIONS_PUBLICKEY_ACCEPTED_TYPES],[libssh/libssh.h])
    else
      AC_MSG_RESULT(no)
      SSH_CFLAGS=""
      SSH_LDFLAGS=""
      SSH_LIBS=""
    fi
  fi

  AC_SUBST(SSH_CFLAGS)
  AC_SUBST(SSH_LDFLAGS)
  AC_SUBST(SSH_LIBS)

])dnl
