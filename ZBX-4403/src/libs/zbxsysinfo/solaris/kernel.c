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

int	KERNEL_MAXPROC(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	kstat_ctl_t	*kc;
	kstat_t		*kt;
	struct var	*v;

	kc = kstat_open();
	if(kc)
	{
		kt = kstat_lookup(kc, "unix", 0, "var");
		if(kt)
		{
			if((kt->ks_type == KSTAT_TYPE_RAW) &&
				(kstat_read(kc, kt, NULL) != -1))
			{
				v = (struct var *) kt->ks_data;

				/* int	v_proc;	    Max processes system wide */
				SET_UI64_RESULT(result, v->v_proc);
				ret = SYSINFO_RET_OK;
			}
		}
		kstat_close(kc);
	}

	return ret;
}
