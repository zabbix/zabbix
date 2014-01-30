/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"

int	SYSTEM_BOOTTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME
	size_t		len;
	int		mib[2];
	struct timeval	boottime;

	mib[0] = CTL_KERN;
	mib[1] = KERN_BOOTTIME;

	len = sizeof(struct timeval);

	if (-1 == sysctl(mib, 2, &boottime, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, boottime.tv_sec);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME */
}
