# LIBPCRE_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for pthread.
#
# This macro #defines HAVE_PCREPOSIX_H if required header files are
# found, and sets @LIBPCRE_LDFLAGS@ and @LIBPCRE_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBPTHREAD_TRY_LINK],
[
AC_TRY_RUN(
[
#include <pthread.h>

int	main()
{
	pthread_mutexattr_t	mta;
	pthread_rwlockattr_t	rwa;
	pthread_mutex_t		mutex;
	pthread_rwlock_t	rwlock;

	if (0 != pthread_mutexattr_init(&mta))
		return 1;

	if (0 != pthread_mutexattr_setpshared(&mta, PTHREAD_PROCESS_SHARED))
		return 2;

	if (0 != pthread_mutex_init(&mutex, &mta))
		return 3;

	if (0 != pthread_rwlockattr_init(&rwa))
		return 4;

	if (0 != pthread_rwlockattr_setpshared(&rwa, PTHREAD_PROCESS_SHARED))
		return 5;

	if (0 != pthread_rwlock_init(&rwlock, &rwa))
		return 6;

	return 0;
}
],
found_libpthread="yes")
])dnl

AC_DEFUN([LIBPTHREAD_CHECK_CONFIG],
[
	AC_ARG_WITH([libpthread],[
If you want to specify pthread installation directories:
AC_HELP_STRING([--with-libpthread@<:@=DIR@:>@], [use libpthread from given base install directory (DIR), default is to search through a number of common places for the libpthread files.])],
		[
			LIBPTHREAD_CFLAGS="-I$withval/include"
			LIBPTHREAD_LDFLAGS="-L$withval/lib"
			_libpthread_dir_set="yes"
		]
	)

	AC_ARG_WITH([libpthread-include],
		AC_HELP_STRING([--with-libpthread-include@<:@=DIR@:>@],
			[use libpthread include headers from given path.]
		),
		[
			LIBPTHREAD_CFLAGS="-I$withval"
			_libpthread_dir_set="yes"
		]
	)

	AC_ARG_WITH([libpthread-lib],
		AC_HELP_STRING([--with-libpthread-lib@<:@=DIR@:>@],
			[use libpthread libraries from given path.]
		),
		[
			LIBPTHREAD_LDFLAGS="-L$withval"
			_libpthread_dir_set="yes"
		]
	)

	AC_MSG_CHECKING(for libpthread support)

	LIBPTHREAD_LIBS="-lpthread"

	if test -n "$_libpthread_dir_set" -o -f /usr/include/pthread.h; then
		found_libpthread="yes"
	elif test -f /usr/local/include/pthread.h; then
		LIBPTHREAD_CFLAGS="-I/usr/local/include"
		LIBPTHREAD_LDFLAGS="-L/usr/local/lib"
		found_libpthread="yes"
	elif test -f /usr/pkg/include/pthread.h; then
		LIBPTHREAD_CFLAGS="-I/usr/pkg/include"
		LIBPTHREAD_LDFLAGS="-L/usr/pkg/lib"
		LIBPTHREAD_LDFLAGS="$LIBPTHREAD_LDFLAGS -Wl,-R/usr/pkg/lib"
		found_libpthread="yes"
	elif test -f /opt/csw/include/pthread.h; then
		LIBPTHREAD_CFLAGS="-I/opt/csw/include"
		LIBPTHREAD_LDFLAGS="-L/opt/csw/lib"
		if $(echo "$CFLAGS"|grep -q -- "-m64") ; then
			LIBPTHREAD_LDFLAGS="$LIBPTHREAD_LDFLAGS/64 -Wl,-R/opt/csw/lib/64"
		else
			LIBPTHREAD_LDFLAGS="$LIBPTHREAD_LDFLAGS -Wl,-R/opt/csw/lib"
		fi
		found_libpthread="yes"
	else
		found_libpthread="no"
		AC_MSG_RESULT(no)
	fi

	if test "x$found_libpthread" = "xyes"; then
		am_save_CFLAGS="$CFLAGS"
		am_save_LDFLAGS="$LDFLAGS"
		am_save_LIBS="$LIBS"

		CFLAGS="$CFLAGS $LIBPTHREAD_CFLAGS"
		LDFLAGS="$LDFLAGS $LIBPTHREAD_LDFLAGS"
		LIBS="$LIBS $LIBPTHREAD_LIBS"

		found_libpthread="no"
		LIBPTHREAD_TRY_LINK([no])

		CFLAGS="$am_save_CFLAGS"
		LDFLAGS="$am_save_LDFLAGS"
		LIBS="$am_save_LIBS"
	fi

	if test "x$found_libpthread" = "xyes"; then
		AC_DEFINE([HAVE_PTHREAD_PROCESS_SHARED], 1, [Define to 1 if you have the 'libpthread' library that supports PTHREAD_PROCESS_SHARED flag (-lpthread)])
		AC_MSG_RESULT(yes)
	else
		LIBPTHREAD_CFLAGS=""
		LIBPTHREAD_LDFLAGS=""
		LIBPTHREAD_LIBS=""
	fi

	AC_SUBST(LIBPTHREAD_CFLAGS)
	AC_SUBST(LIBPTHREAD_LDFLAGS)
	AC_SUBST(LIBPTHREAD_LIBS)
])dnl
