/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"

int	SYSTEM_UPTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_ctl_t	*kc;
	kstat_t		*kp;
	kstat_named_t	*kn;
	time_t		now;
	int		ret = SYSINFO_RET_FAIL;

	if (NULL == (kc = kstat_open()))
		return ret;

	if (NULL != (kp = kstat_lookup(kc, "unix", 0, "system_misc")))
	{
		if (-1 != kstat_read(kc, kp, 0))
		{
			if (NULL != (kn = (kstat_named_t*)kstat_data_lookup(kp, "boot_time")))
			{
				time(&now);
				SET_UI64_RESULT(result, difftime(now, (time_t) kn->value.ul));
				ret = SYSINFO_RET_OK;
			}
		}
	}
	kstat_close(kc);

	return ret;
}
