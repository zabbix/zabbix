# LIBICONV_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Alexander Vladishev                      Feb-02-2007
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

AC_DEFUN([LIBICONV_TRY_LINK],
[
AC_TRY_LINK(
[
#include <stdlib.h>
#include <iconv.h>
],
[
	iconv_t cd = iconv_open("","");
	iconv(cd, NULL, NULL, NULL, NULL);
	iconv_close(cd);
],
found_iconv="yes")
])dnl

AC_DEFUN([LIBICONV_CHECK_CONFIG],
[
	AC_MSG_CHECKING(for ICONV support)

	if test -f /usr/include/iconv.h; then
		found_iconv="yes"
	elif test -f /usr/local/include/iconv.h; then
		ICONV_CFLAGS="-I/usr/local/include"
		ICONV_LDFLAGS="-L/usr/local/lib"
		found_iconv="yes"
	else
		found_iconv="no"
		AC_MSG_RESULT(no)
	fi

	if test "x$found_iconv" = "xyes"; then
		am_save_CFLAGS="$CFLAGS"
		am_save_LDFLAGS="$LDFLAGS"

		CFLAGS="$CFLAGS $ICONV_CFLAGS"
		LDFLAGS="$LDFLAGS $ICONV_LDFLAGS"

		found_iconv="no"
		LIBICONV_TRY_LINK([no])

		if test "x$found_iconv" = "xno"; then
			am_save_LIBS="$LIBS"
			LIBS="$LIBS -liconv"
			LIBICONV_TRY_LINK([no])
			LIBS="$am_save_LIBS"

			if test "x$found_iconv" = "xyes"; then
				ICONV_LIBS="-liconv"
			fi
		fi

		CFLAGS="$am_save_CFLAGS"
		LDFLAGS="$am_save_LDFLAGS"
	fi

	if test "x$found_iconv" = "xyes"; then
		AC_DEFINE([HAVE_ICONV], 1, [Define to 1 if you have the 'libiconv' library (-liconv)])
		AC_MSG_RESULT(yes)
	else
		ICONV_CFLAGS=""
		ICONV_LDFLAGS=""
	fi

	AC_SUBST(ICONV_CFLAGS)
	AC_SUBST(ICONV_LDFLAGS)
])dnl
