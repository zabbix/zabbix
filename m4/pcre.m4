# LIBPCRE_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for Perl Compatible Regular Expressions.

AC_DEFUN([LIBPCRE_TRY_LINK],
[
am_save_LIBS="$LIBS"
LIBS="$LIBS $1"
AC_TRY_LINK(
[
#include <pcreposix.h>
],
[
	regex_t	re = {0};

	regcomp(&re, "test", 0);
	regfree(&re);
],
found_pcre="yes"
PCRE_LIBS="$1")
LIBS="$am_save_LIBS"
])dnl

AC_DEFUN([LIBPCRE_CHECK_CONFIG],
[
	AC_MSG_CHECKING(for PCRE functions)

	LIBPCRE_TRY_LINK([-lpcreposix -lpcre])

	AC_MSG_RESULT($found_resolv)

	AC_SUBST(PCRE_LIBS)
])dnl
