# LIBOPENIPMI_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#    Alexander Vladishev                      Sep-10-2008
#
# Checks for openipmi.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-openipmi or --without-openipmi.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_OPENIPMI if a required header files is
# found, and sets @OPENIPMI_LDFLAGS@ and @OPENIPMI_CFLAGS@ to the necessary
# values.
#
# Users may override the detected values by doing something like:
# OPENIPMI_LDFLAGS="-lOpenIPMI" OPENIPMI_CFLAGS="-I/usr/myinclude" ./configure
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBOPENIPMI_CHECK_CONFIG],
[
  AC_ARG_WITH(openipmi,[
If you want to check IPMI devices:
AC_HELP_STRING([--with-openipmi@<:@=DIR@:>@],[Include OPENIPMI support @<:@default=no@:>@. DIR is the OPENIPMI base install directory, default is to search through a number of common places for the OPENIPMI files.])],
     [ if test "$withval" = "no"; then
            want_openipmi="no"
            _libopenipmi_with="no"
        elif test "$withval" = "yes"; then
            want_openipmi="yes"
            _libopenipmi_with="yes"
        else
            want_openipmi="yes"
            _libopenipmi_with=$withval
        fi
     ],[_libopenipmi_with=ifelse([$1],,[no],[$1])])

  if test "x$_libopenipmi_with" != x"no"; then
       AC_MSG_CHECKING(for OPENIPMI support)

       if test "$_libopenipmi_with" = "yes"; then
               if test -f /usr/local/include/OpenIPMI/ipmiif.h; then
                       OPENIPMI_INCDIR=/usr/local/include
                       OPENIPMI_LIBDIR=/usr/local/lib
		       found_openipmi="yes"
               elif test -f /usr/include/OpenIPMI/ipmiif.h; then
                       OPENIPMI_INCDIR=/usr/include
                       OPENIPMI_LIBDIR=/usr/lib
		       found_openipmi="yes"
               else
                       found_openipmi="no"
                       AC_MSG_RESULT(no)
               fi
       else
               if test -f $_libopenipmi_with/include/OpenIPMI/ipmiif.h; then
                       OPENIPMI_INCDIR=$_libopenipmi_with/include
                       OPENIPMI_LIBDIR=$_libopenipmi_with/lib
		       found_openipmi="yes"
               else
                       found_openipmi="no"
                       AC_MSG_RESULT(no)
               fi
       fi

       if test "x$found_openipmi" != "xno" ; then
               OPENIPMI_CFLAGS="-I$OPENIPMI_INCDIR"
               OPENIPMI_LDFLAGS="-L$OPENIPMI_LIBDIR"
               OPENIPMI_LIBS="-lOpenIPMI -lOpenIPMIposix"

               found_openipmi="yes"
               AC_DEFINE(HAVE_OPENIPMI,1,[Define to 1 if OPENIPMI should be enabled.])
	       AC_DEFINE(OPENIPMI_DEPRECATED, 1, [Define to 1 if OPENIPMI depricated functions is used.])
               AC_MSG_RESULT(yes)
	fi
  fi

  AC_SUBST(OPENIPMI_CFLAGS)
  AC_SUBST(OPENIPMI_LDFLAGS)
  AC_SUBST(OPENIPMI_LIBS)

  unset _libopenipmi_with
])dnl
