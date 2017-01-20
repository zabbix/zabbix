/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#ifdef _WINDOWS
#	include "perfmon.h"
#	pragma comment(lib, "user32.lib")
#endif

int	SYSTEM_LOCALTIME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*type, buf[32];
	long		milliseconds;
	struct tm	tm;
	zbx_timezone_t	tz;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	type = get_rparam(request, 0);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "utc"))
	{
		SET_UI64_RESULT(result, time(NULL));
	}
	else if (0 == strcmp(type, "local"))
	{
		zbx_get_time(&tm, &milliseconds, &tz);

		zbx_snprintf(buf, sizeof(buf), "%04d-%02d-%02d,%02d:%02d:%02d.%03ld,%01c%02d:%02d",
				1900 + tm.tm_year, 1 + tm.tm_mon, tm.tm_mday,
				tm.tm_hour, tm.tm_min, tm.tm_sec, milliseconds,
				tz.tz_sign, tz.tz_hour, tz.tz_min);

		SET_STR_RESULT(result, strdup(buf));
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	SYSTEM_USERS_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef _WINDOWS
	char		counter_path[64];
	AGENT_REQUEST	request_tmp;
	int		ret;

	zbx_snprintf(counter_path, sizeof(counter_path), "\\%d\\%d", PCI_TERMINAL_SERVICES, PCI_TOTAL_SESSIONS);

	request_tmp.nparam = 1;
	request_tmp.params = zbx_malloc(NULL, request_tmp.nparam * sizeof(char *));
	request_tmp.params[0] = counter_path;

	ret = PERF_COUNTER(&request_tmp, result);

	zbx_free(request_tmp.params);

	return ret;
#else
	return EXECUTE_INT("who | wc -l", result);
#endif
}
