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

#include "zbxsysinfo.h"
#include "../sysinfo.h"

int	system_uptime(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#if defined(HAVE_SYSINFO_UPTIME)
	struct sysinfo info;

	if (0 == sysinfo(&info))
	{
		SET_UI64_RESULT(result, info.uptime);
		return SYSINFO_RET_OK;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}
#elif defined(HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME)
	int		mib[2], now;
	size_t		len;
	struct timeval	uptime;

	mib[0] = CTL_KERN;
	mib[1] = KERN_BOOTTIME;

	len = sizeof(uptime);

	if (0 != sysctl(mib, 2, &uptime, &len, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	now = time(NULL);

	SET_UI64_RESULT(result, now - uptime.tv_sec);

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for uptime information."));

	return SYSINFO_RET_FAIL;
#endif
}
