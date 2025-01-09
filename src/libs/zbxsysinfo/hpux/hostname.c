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

int	system_hostname(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		rc;
	char		*hostname;
	long 		hostbufsize = 0;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

#ifdef _SC_HOST_NAME_MAX
	hostbufsize = sysconf(_SC_HOST_NAME_MAX) + 1;
#endif
	if (0 == hostbufsize)
		hostbufsize = 256;

	hostname = zbx_malloc(NULL, hostbufsize);

	if (0 != gethostname(hostname, hostbufsize))
	{
		zbx_free(hostname);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (FAIL == (rc = hostname_handle_params(request, result, &hostname)))
		zbx_free(hostname);

	return rc;
}
