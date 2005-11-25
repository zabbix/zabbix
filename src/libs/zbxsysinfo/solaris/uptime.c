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

#include "config.h"

#include "common.h"
#include "sysinfo.h"

int	SYSTEM_UPTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    kstat_ctl_t   *kc;
    kstat_t       *kp;
    kstat_named_t *kn;

    time_t now;
    
    int ret = SYSINFO_RET_FAIL;

    assert(result);

    init_result(result);

    kc = kstat_open();

    if (kc)
    {
	kp = kstat_lookup(kc, "unix", 0, "system_misc");
        if ((kp) && (kstat_read(kc, kp, 0) != -1))
	{
		kn = (kstat_named_t*) kstat_data_lookup(kp, "boot_time");
		time(&now);
		SET_UI64_RESULT(result, difftime(now, (time_t) kn->value.ul));
		ret = SYSINFO_RET_OK;
        }
	kstat_close(kc);
    }
    return ret;
}

