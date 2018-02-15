# LIBRESOLV_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Alexander Vladishev                      Dec-16-2009
#
# Checks for DNS functions.

AC_DEFUN([LIBRESOLV_TRY_LINK],
[
am_save_LIBS="$LIBS"
LIBS="$LIBS $1"
AC_TRY_LINK(
[
#ifdef HAVE_SYS_TYPES_H
#	include <sys/types.h>
#endif
#ifdef HAVE_NETINET_IN_H
#	include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_NAMESER_H
#	include <arpa/nameser.h>
#endif
#ifdef HAVE_RESOLV_H
#	include <resolv.h>
#endif
#ifndef C_IN
#	define C_IN	ns_c_in
#endif	/* C_IN */
#ifndef T_SOA
#	define T_SOA	ns_t_soa
#endif	/* T_SOA */
],
[
	char	*buf;

	res_init();
	res_query("", C_IN, T_SOA, (unsigned char *)buf, 0);
],
found_resolv="yes"
RESOLV_LIBS="$1")
LIBS="$am_save_LIBS"
])dnl

AC_DEFUN([LIBRESOLV_CHECK_CONFIG],
[
	AC_MSG_CHECKING(for DNS lookup functions)

	LIBRESOLV_TRY_LINK([])

	if test "x$found_resolv" != "xyes"; then
		LIBRESOLV_TRY_LINK([-lresolv])
	fi
	if test "x$found_resolv" != "xyes"; then
		LIBRESOLV_TRY_LINK([-lbind])
	fi
	if test "x$found_resolv" != "xyes"; then
		LIBRESOLV_TRY_LINK([-lsocket])
	fi

	if test "x$found_resolv" = "xyes"; then
		AC_DEFINE([HAVE_RES_QUERY], 1, [Define to 1 if you have the DNS functions])
	else
		AC_MSG_RESULT(no)
	fi

	AC_MSG_RESULT($found_resolv)

	AC_SUBST(RESOLV_LIBS)
])dnl
