# LDAP_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#	Eugene Grigorjev <eugene@zabbix.com>   Feb-02-2007
#
# Checks for ldap.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-ldap or --without-ldap.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_LDAP if a required header files is
# found, and sets @LDAP_LDFLAGS@ and @LDAP_CPPFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# LDAP_LDFLAGS="-lldap" LDAP_CPPFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LDAP_CHECK_CONFIG],
[
	AC_ARG_WITH(ldap,
		[If you want to check LDAP servers:
AC_HELP_STRING([--with-ldap@<:@=DIR@:>@], [Include LDAP support @<:@default=no@:>@. DIR is the LDAP base install directory, default is to search through a number of common places for the LDAP files.])],
		[
			if test "$withval" = "no"; then
				want_ldap="no"
				_ldap_with="no"
			elif test "$withval" = "yes"; then
				want_ldap="yes"
				_ldap_with="yes"
			else
				want_ldap="yes"
				_ldap_with=$withval
			fi
		],
		[_ldap_with=ifelse([$1],,[no],[$1])])

	if test "x$_ldap_with" != x"no"; then
		AC_MSG_CHECKING(for LDAP support)

		if test "$_ldap_with" = "yes"; then
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
			if test -f $_ldap_with/include/ldap.h; then
				LDAP_INCDIR=$_ldap_with/include
				LDAP_LIBDIR=$_ldap_with/lib
				found_ldap="yes"
			else
				found_ldap="no"
				AC_MSG_RESULT(no)
			fi
		fi

		if test "x$found_ldap" != "xno"; then
			if test "x$enable_static" = "xyes"; then
				LDAP_LIBS="-lgnutls -lpthread -lsasl2 $LDAP_LIBS"
			fi

			LDAP_CPPFLAGS=-I$LDAP_INCDIR
			LDAP_LDFLAGS="-L$LDAP_LIBDIR"
			LDAP_LIBS="-lldap -llber $LDAP_LIBS"

			found_ldap="yes"
			AC_DEFINE(HAVE_LDAP, 1, [Define to 1 if LDAP should be enabled.])
			AC_DEFINE(LDAP_DEPRECATED, 1, [Define to 1 if LDAP depricated functions is used.])
			AC_MSG_RESULT(yes)

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

	unset _ldap_with
])dnl
