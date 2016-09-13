# JABBER_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Eugene Grigorjev <eugene@zabbix.com>   Feb-02-2007
#
# Checks for iksemel.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-jabber or --without-jabber.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_JABBER and HAVE_IKSEMEL if a required header files is
# found, and sets @JABBER_LDFLAGS@ and @JABBER_CPPFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# JABBER_LDFLAGS="-liksemel" JABBER_CPPFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([JABBER_CHECK_CONFIG],
[
  AC_ARG_WITH(jabber,
[
If you want to use Jabber protocol for messaging:
AC_HELP_STRING([--with-jabber@<:@=DIR@:>@],[Include Jabber support @<:@default=no@:>@. DIR is the iksemel library install directory.])],[
	if test "$withval" = "no"; then
            want_jabber="no"
            _libiksemel_with="no"
        elif test "$withval" = "yes"; then
            want_jabber="yes"
            _libiksemel_with="yes"
        else
            want_jabber="yes"
            _libiksemel_with=$withval
        fi
     ],[_libiksemel_with=ifelse([$1],,[no],[$1])])

  if test "x$_libiksemel_with" != x"no"; then
       if test "$_libiksemel_with" = "yes"; then
       	m4_ifdef([PKG_CHECK_MODULES], [
       		PKG_CHECK_MODULES(IKSEMEL,iksemel,
       			[
       				JABBER_INCDIR="$IKSEMEL_CPPFLAGS"
       				JABBER_LIBDIR="$IKSEMEL_LDFLAGS"
       				JABBER_LIBS="-liksemel"
                ],[
                	found_iksemel="no"
                	found_jabber="no"
                ])
			],
			[
				found_iksemel="no"
				found_jabber="no"
			])
       else
	       AC_MSG_CHECKING(for iksemel support)

               if test -f $_libiksemel_with/include/iksemel.h; then
                       JABBER_INCDIR="-I$_libiksemel_with/include"
                       JABBER_LIBDIR="-L$_libiksemel_with/lib"
                       JABBER_LIBS="-liksemel"
		       AC_MSG_RESULT(yes)
               else
                       found_iksemel="no"
                       found_jabber="no"
                       AC_MSG_RESULT(no)
               fi
       fi

       if test "x$found_iksemel" != "xno" ; then

               AC_CHECK_FUNCS(getaddrinfo)

               JABBER_CPPFLAGS=$JABBER_INCDIR
               JABBER_LDFLAGS="$JABBER_LIBDIR"

		if test "x$enable_static" = "xyes"; then
			for i in -liksemel -lgnutls -ltasn1 -lgcrypt -lgpg-error; do
				case $i in
					-liksemel)
				;;
					-l*)
						_lib_name=`echo "$i" | cut -b3-`
						AC_CHECK_LIB($_lib_name , main,[
							JABBER_LIBS="$JABBER_LIBS $i"
						],[
							AC_MSG_ERROR([Not found $_lib_name library])
						])
				;;
				esac
			done
		fi

               found_iksemel="yes"
               found_jabber="yes"
               AC_DEFINE(HAVE_IKSEMEL,1,[Define to 1 if Iksemel library should be enabled.])
               AC_DEFINE(HAVE_JABBER,1,[Define to 1 if Jabber should be enabled.])
       fi
  fi

  AC_SUBST(JABBER_CPPFLAGS)
  AC_SUBST(JABBER_LDFLAGS)
  AC_SUBST(JABBER_LIBS)

  unset _libiksemel_with
])dnl
