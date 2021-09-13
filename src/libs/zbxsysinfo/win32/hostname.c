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
	DWORD		dwSize = 256;
	wchar_t		computerName[256];
	char		*tmp, buffer[256];
	unsigned char	param_type, param_transform;
	int		netbios;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "host"))
	{
		param_type = ZBX_SYSTEM_HOSTNAME_TYPE_HOST;
	}
	else if (0 == strcmp(tmp, "shorthost"))
	{
		param_type = ZBX_SYSTEM_HOSTNAME_TYPE_SHORTHOST;
	}
	else if (0 == strcmp(tmp, "netbios"))
	{
		param_type = ZBX_SYSTEM_HOSTNAME_TYPE_NETBIOS;
		return SYSINFO_RET_FAIL;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp)
	{
		param_transform = ZBX_SYSTEM_HOSTNAME_TRANSFORM_NONE;
	}
	else if (0 == strcmp(tmp, "lower"))
	{
		param_transform = ZBX_SYSTEM_HOSTNAME_TRANSFORM_LOWER;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (param_type == ZBX_SYSTEM_HOSTNAME_TYPE_NETBIOS)
	{
		/* Buffer size is chosen large enough to contain any DNS name, not just MAX_COMPUTERNAME_LENGTH + 1 */
		/* characters. MAX_COMPUTERNAME_LENGTH is usually less than 32, but it varies among systems, so we  */
		/* cannot use the constant in a precompiled Windows agent, which is expected to work on any system. */
		if (0 == GetComputerName(computerName, &dwSize))
		{
			zabbix_log(LOG_LEVEL_ERR, "GetComputerName() failed: %s", strerror_from_system(GetLastError()));
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain computer name: %s",
					strerror_from_system(GetLastError())));
			return SYSINFO_RET_FAIL;
		}

		SET_STR_RESULT(result, zbx_unicode_to_utf8(computerName));
	}
	else
	{
		if (SUCCEED != gethostname(buffer, sizeof(buffer)))
		{
			zabbix_log(LOG_LEVEL_ERR, "gethostname() failed: %s", strerror_from_system(WSAGetLastError()));
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain host name: %s",
					strerror_from_system(WSAGetLastError())));
			return SYSINFO_RET_FAIL;
		}

		if (param_type == ZBX_SYSTEM_HOSTNAME_TYPE_SHORTHOST)
		{
			char	*dot;

			if (NULL != (dot = strchr(buffer, '.')))
				*dot = '\0';
		}

		SET_STR_RESULT(result, zbx_strdup(NULL, buffer));
	}

	if (ZBX_SYSTEM_HOSTNAME_TRANSFORM_LOWER == param_transform)
	{
		int	i;

		for (i = 0; result->str[i] != '\0'; i++)
		{
			result->str[i] = tolower(result->str[i]);
		}
	}

	return SYSINFO_RET_OK;
}
