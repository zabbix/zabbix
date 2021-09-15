/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "sysinfo.h"
#include "log.h"

ZBX_METRIC	parameter_hostname =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
	{"system.hostname",     CF_HAVEPARAMS,  SYSTEM_HOSTNAME,        NULL};

int	SYSTEM_HOSTNAME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*ptype, *ptransform, *hostname;
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

	ptype = get_rparam(request, 0);
	ptransform = get_rparam(request, 1);

	if (NULL != ptype && '\0' != *ptype && 0 != strcmp(ptype, "host"))
	{
		if (0 == strcmp(ptype, "shorthost"))
		{
			char	*dot;

			if (NULL != (dot = strchr(hostname, '.')))
				*dot = '\0';
		}
		else if (0 == strcmp(ptype, "netbios"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "NetBIOS is not supported on the current platform."));
			return SYSINFO_RET_FAIL;
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
			return SYSINFO_RET_FAIL;
		}
	}

	if (NULL != ptransform && '\0' != *ptransform && 0 != strcmp(ptransform, "none"))
	{
		if (0 == strcmp(ptransform, "lower"))
		{
			zbx_strlower(hostname);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, hostname));

	return SYSINFO_RET_OK;
}
