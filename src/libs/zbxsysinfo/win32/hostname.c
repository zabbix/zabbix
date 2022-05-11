/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	char		*type, *transform, buffer[256], *name, *error = NULL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	type = get_rparam(request, 0);
	transform = get_rparam(request, 1);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "netbios"))
	{
		/* Buffer size is chosen large enough to contain any DNS name, not just                      */
		/* MAX_COMPUTERNAME_LENGTH + 1 characters. MAX_COMPUTERNAME_LENGTH is usually less than 32,  */
		/* but it varies among systems, so we cannot use the constant in a precompiled Windows agent,*/
		/* which is expected to work on any system.                                                  */
		if (0 == GetComputerName(computerName, &dwSize))
		{
			zabbix_log(LOG_LEVEL_ERR, "GetComputerName() failed: %s",
					strerror_from_system(GetLastError()));

			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain computer name: %s",
					strerror_from_system(GetLastError())));

			return SYSINFO_RET_FAIL;
		}

		name = zbx_unicode_to_utf8(computerName);
	}
	else
	{
		if (0 == strcmp(type, "shorthost"))
		{
			retrieve_hostname(buffer, sizeof(buffer), &error);
			if (NULL != error)
			{
				SET_MSG_RESULT(result, error);
				return SYSINFO_RET_FAIL;
			}

			char	*dot;

			if (NULL != (dot = strchr(buffer, '.')))
				*dot = '\0';

			name = zbx_strdup(NULL, buffer);
		}
		else if (0 == strcmp(type, "host"))
		{
			retrieve_hostname(buffer, sizeof(buffer), &error);
			if (NULL != error)
			{
				SET_MSG_RESULT(result, error);
				return SYSINFO_RET_FAIL;
			}

			name = zbx_strdup(NULL, buffer);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
			return SYSINFO_RET_FAIL;
		}
	}

	if (NULL != transform && '\0' != *transform && 0 != strcmp(transform, "none"))
	{
		if (0 == strcmp(transform, "lower"))
		{
			zbx_strlower(name);
		}
		else
		{
			zbx_free(name);
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}

	SET_STR_RESULT(result, name);

	return SYSINFO_RET_OK;
}
