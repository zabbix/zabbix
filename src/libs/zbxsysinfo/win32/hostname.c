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

static void	retrieve_hostname(char *buffer, int len, char **error)
{
	if (SUCCEED != gethostname(buffer, len))
	{
		zabbix_log(LOG_LEVEL_ERR, "gethostname() failed: %s", strerror_from_system(WSAGetLastError()));
		*error = zbx_dsprintf(NULL, "Cannot obtain host name: %s", strerror_from_system(WSAGetLastError()));
	}
}

int	SYSTEM_HOSTNAME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	DWORD		dwSize = 256;
	wchar_t		computerName[256];
	char		*ptype, *ptransform, buffer[256], *name, *error = NULL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	ptype = get_rparam(request, 0);
	ptransform = get_rparam(request, 1);

	if (NULL != ptype && '\0' != *ptype && 0 != strcmp(ptype, "host"))
	{
		if (0 == strcmp(ptype, "shorthost"))
		{
			retrieve_hostname(&buffer, sizeof(buffer), &error);
			if (NULL != error)
			{
				SET_MSG_RESULT(result, error);
				return SYSINFO_RET_FAIL;
			}

			char	*dot;

			if (NULL != (dot = strchr(buffer, '.')))
				*dot = '\0';

			name = buffer;
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
	else
	{
		retrieve_hostname(&buffer, sizeof(buffer), &error);
		if (NULL != error)
		{
			SET_MSG_RESULT(result, error);
			return SYSINFO_RET_FAIL;
		}

		name = buffer;
	}

	if (NULL != ptransform && '\0' != *ptransform && 0 != strcmp(ptransform, "none"))
	{
		if (0 == strcmp(ptransform, "lower"))
		{
			zbx_strlower(name);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, name));

	return SYSINFO_RET_OK;
}
