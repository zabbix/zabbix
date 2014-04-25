/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	struct utsname	name;
	char		*hostname;
	int 		hostbufsize = 0;

	if (-1 == uname(&name))
		return SYSINFO_RET_FAIL;

	/* On HP-UX machines name.nodename can be truncated to 8 bytes, therefore, it is */
	/* recommended to use the host name returned by the gethostname() and query the  */
	/* system for the maximum possible length by using sysconf(_SC_HOST_NAME_MAX).   */
	/* However, _SC_HOST_NAME_MAX might not available on all versions of HP-UX. 	 */
	/* In such case buffer size is set to 256 and explicitly NUll-terminated 	 */

	#ifdef _SC_HOST_NAME_MAX
	hostbufsize = sysconf(_SC_HOST_NAME_MAX) + 1;
	#endif

	if (0 >= hostbufsize)
		hostbufsize = 256;

	hostname = zbx_malloc(NULL, sizeof(char) * hostbufsize);

	if (0 != gethostname(hostname, hostbufsize))
		hostname = name.nodename;
	else
		hostname[hostbufsize - 1] = '\0';

	SET_STR_RESULT(result, zbx_dsprintf(NULL, "%s %s %s %s %s %s", name.sysname, hostname, name.release,
			name.version, name.machine, name.idnumber));

	zbx_free(hostname);

	return SYSINFO_RET_OK;
}
