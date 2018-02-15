/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "log.h"

int	SYSTEM_BOOTTIME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	FILE		*f;
	char		buf[MAX_STRING_LEN];
	int		ret = SYSINFO_RET_FAIL;
	unsigned long	value;

	ZBX_UNUSED(request);

	if (NULL == (f = fopen("/proc/stat", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/stat: %s", zbx_strerror(errno)));
		return ret;
	}

	/* find boot time entry "btime [boot time]" */
	while (NULL != fgets(buf, MAX_STRING_LEN, f))
	{
		if (1 == sscanf(buf, "btime %lu", &value))
		{
			SET_UI64_RESULT(result, value);

			ret = SYSINFO_RET_OK;

			break;
		}
	}
	zbx_fclose(f);

	if (SYSINFO_RET_FAIL == ret)
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot find a line with \"btime\" in /proc/stat."));

	return ret;
}
