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

#include "common.h"
#include "sysinfo.h"

int	SYSTEM_UPTIME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME
	int		mib[2], now;
	size_t		len;
	struct timeval	uptime;

	mib[0] = CTL_KERN;
	mib[1] = KERN_BOOTTIME;

	len = sizeof(struct timeval);

	if (0 != sysctl(mib, 2, &uptime, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	now = time(NULL);

	SET_UI64_RESULT(result, now - uptime.tv_sec);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME */
}
