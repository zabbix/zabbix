# LIBICONV_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Aleksander Vladishev <eugene@zabbix.com>   Feb-02-2007
#
# Checks for ldap.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-ldap or --without-ldap.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_ICONV if a required header files is
# found, and sets @ICONV_LDFLAGS@ and @ICONV_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBICONV_CHECK_CONFIG],
[
	AC_MSG_CHECKING(for ICONV support)

	if test -f /usr/include/iconv.h; then
		ICONV_INCDIR=/usr/include
		ICONV_LIBDIR=/usr/lib
		found_iconv="yes"
	elif test -f /usr/local/include/iconv.h; then
		ICONV_INCDIR=/usr/local/include
		ICONV_LIBDIR=/usr/local/lib
		found_iconv="yes"
	else
		found_iconv="no"
		AC_MSG_RESULT(no)
	fi

	if test "x$found_iconv" != "xno" ; then
		ICONV_CFLAGS=-I$ICONV_INCDIR
		ICONV_LDFLAGS="-L$ICONV_LIBDIR"

		found_iconv="yes"
		AC_DEFINE(HAVE_ICONV,1,[Define to 1 if ICONV should be enabled.])
		AC_DEFINE(ICONV_DEPRECATED, 1, [Define to 1 if ICONV depricated functions is used.])
		AC_MSG_RESULT(yes)
	fi

	AC_SUBST(ICONV_CFLAGS)
	AC_SUBST(ICONV_LDFLAGS)
])dnl
