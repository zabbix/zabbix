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

#include "perfmon.h"
#include "sysinfo.h"

int	SYSTEM_UPTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char counter_path[MAX_COUNTER_PATH];

	zbx_snprintf(counter_path, sizeof(counter_path), "\\%s\\%s",GetCounterName(PCI_SYSTEM),GetCounterName(PCI_SYSTEM_UP_TIME));

	return PERF_MONITOR(cmd, counter_path, flags, result);
}
