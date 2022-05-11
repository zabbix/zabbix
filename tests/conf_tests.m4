#
# Zabbix
# Copyright (C) 2001-2022 Zabbix SIA
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#

AC_DEFUN([CONF_TESTS],
[
	AM_COND_IF([ZBXCMOCKA],[
		AC_CONFIG_FILES([
		tests/Makefile
		tests/libs/Makefile
		tests/libs/zbxalgo/Makefile
		tests/libs/zbxcommon/Makefile
		tests/libs/zbxcomms/Makefile
		tests/libs/zbxcommshigh/Makefile
		tests/libs/zbxconf/Makefile
		tests/libs/zbxdbcache/Makefile
		tests/libs/zbxdbhigh/Makefile
		tests/libs/zbxeval/Makefile
		tests/libs/zbxhistory/Makefile
		tests/libs/zbxjson/Makefile
		tests/libs/zbxprometheus/Makefile
		tests/libs/zbxregexp/Makefile
		tests/libs/zbxserver/Makefile
		tests/libs/zbxsysinfo/Makefile
		tests/libs/zbxsysinfo/common/Makefile
		tests/libs/zbxtrends/Makefile
		tests/zabbix_server/Makefile
		tests/zabbix_server/preprocessor/Makefile
		tests/zabbix_server/service/Makefile
		tests/zabbix_server/trapper/Makefile
		tests/mocks/Makefile
		tests/mocks/configcache/Makefile
		tests/mocks/valuecache/Makefile
		])
		AC_DEFINE([HAVE_TESTS], [1], ["Define to 1 if tests directory is present"])
	])

	case "$ARCH" in
	linux)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/linux/Makefile])
		;;
	aix)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/aix/Makefile])
		;;
	osx)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/osx/Makefile])
		;;
	solaris)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/solaris/Makefile])
		;;
	hpux)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/hpux/Makefile])
		;;
	freebsd)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/freebsd/Makefile])
		;;
	netbsd)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/netbsd/Makefile])
		;;
	osf)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/osf/Makefile])
		;;
	openbsd)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/openbsd/Makefile])
		;;
	unknown)
		AC_CONFIG_FILES([tests/libs/zbxsysinfo/unknown/Makefile])
		;;
	esac


AC_TRY_LINK(
[
#include <stdlib.h>
],
[
	__fxstat(0, 0, NULL);
],
AC_DEFINE([HAVE_FXSTAT], [1], [Define to 1 if fxstat function is available]))

])
