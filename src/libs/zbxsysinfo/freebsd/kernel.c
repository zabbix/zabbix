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

int	kernel_maxfiles(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_FUNCTION_SYSCTL_KERN_MAXFILES
	int	mib[2];
	size_t	len;
	int	maxfiles;

	mib[0] = CTL_KERN;
	mib[1] = KERN_MAXFILES;

	len = sizeof(maxfiles);

	if (0 != sysctl(mib, 2, &maxfiles, &len, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, maxfiles);

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for \"kern.maxfiles\" system"
			" parameter."));

	return SYSINFO_RET_FAIL;
#endif
}

int	kernel_maxproc(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_FUNCTION_SYSCTL_KERN_MAXPROC
	int	mib[2];
	size_t	len;
	int	maxproc;

	mib[0] = CTL_KERN;
	mib[1] = KERN_MAXPROC;

	len = sizeof(maxproc);

	if (0 != sysctl(mib, 2, &maxproc, &len, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, maxproc);

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for \"kern.maxproc\" system"
			" parameter."));
	return SYSINFO_RET_FAIL;
#endif
}
