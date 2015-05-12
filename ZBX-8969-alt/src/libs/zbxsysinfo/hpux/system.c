/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "sysinfo.h"

#ifdef HAVE_SYS_UTSNAME_H
#	include <sys/utsname.h>
#endif

int	SYSTEM_UNAME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#if defined(HAVE_SYS_UTSNAME_HPUX_V1) && defined(GCC_VERSION) && 4003 > GCC_VERSION	/* version 4.3.0 */
	struct utsname_hpux_v1	name;
#else
	struct utsname		name;
#endif
	if (-1 == uname((struct utsname *)&name))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, zbx_dsprintf(NULL, "%s %s %s %s %s %s", name.sysname, name.nodename, name.release,
			name.version, name.machine, name.idnumber));

	return SYSINFO_RET_OK;
}
