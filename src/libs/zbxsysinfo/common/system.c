/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxsysinfo_common.h"

#include "system.h"
#include "zbxtime.h"

#if defined(_WINDOWS) || defined(__MINGW32__)
#	include "zbxsysinfo.h"
#	include "zbxwin32.h"
#	include "../sysinfo.h"
#	pragma comment(lib, "user32.lib")
#endif

/******************************************************************************
 *                                                                            *
 * Comments: Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
int	system_localtime(AGENT_REQUEST *request, AGENT_RESULT *result)
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

		zbx_snprintf(buf, sizeof(buf), "%04d-%02d-%02d,%02d:%02d:%02d.%03ld,%1c%02d:%02d",
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

int	system_users_num(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#if defined(_WINDOWS) || defined(__MINGW32__)
	char		counter_path[64];
	AGENT_REQUEST	request_tmp;
	int		ret;

	ZBX_UNUSED(request);

	zbx_snprintf(counter_path, sizeof(counter_path), "\\%u\\%u",
			(unsigned int)zbx_get_builtin_object_index(PCI_TOTAL_SESSIONS),
			(unsigned int)zbx_get_builtin_counter_index(PCI_TOTAL_SESSIONS));

	request_tmp.nparam = 1;
	request_tmp.params = zbx_malloc(NULL, request_tmp.nparam * sizeof(char *));
	request_tmp.params[0] = counter_path;

	ret = perf_counter(&request_tmp, result);

	zbx_free(request_tmp.params);

	return ret;
#else
	ZBX_UNUSED(request);

	return execute_int("who | wc -l", result, request->timeout);
#endif
}
