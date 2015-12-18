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

#include "common.h"
#include "sysinfo.h"
#include "../common/common.h"

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];

	if (3 < num_param(param))
		return SYSINFO_RET_FAIL;

	/* only "all" (default) for parameter "cpu" is supported */
	if (0 == get_param(param, 1, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strcmp(tmp, "all"))
		return SYSINFO_RET_FAIL;

	/* only "avg1" (default) for parameter "mode" is supported */
	if (0 == get_param(param, 3, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strcmp(tmp, "avg1"))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "user"))
		return EXECUTE_DBL(cmd, "iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-3))}'", flags, result);
	else if (0 == strcmp(tmp, "nice"))
		return EXECUTE_DBL(cmd, "iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-2))}'", flags, result);
	else if (0 == strcmp(tmp, "system"))
		return EXECUTE_DBL(cmd, "iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-1))}'", flags, result);
	else if (0 == strcmp(tmp, "idle"))
		return EXECUTE_DBL(cmd, "iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF))}'", flags, result);

	return SYSINFO_RET_FAIL;
}

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	/* only "all" (default) for parameter "cpu" is supported */
	if (0 == get_param(param, 1, tmp, sizeof(tmp)) && '\0' != *tmp && 0 != strcmp(tmp, "all"))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		return EXECUTE_DBL(cmd, "uptime | awk '{printf(\"%s\", $(NF))}' | sed 's/[ ,]//g'", flags, result);
	else if (0 == strcmp(tmp, "avg5"))
		return EXECUTE_DBL(cmd, "uptime | awk '{printf(\"%s\", $(NF-1))}' | sed 's/[ ,]//g'", flags, result);
	else if (0 == strcmp(tmp, "avg15"))
		return EXECUTE_DBL(cmd, "uptime | awk '{printf(\"%s\", $(NF-2))}' | sed 's/[ ,]//g'", flags, result);

	return SYSINFO_RET_FAIL;
}
