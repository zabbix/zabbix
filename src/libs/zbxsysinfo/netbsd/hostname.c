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

#ifdef HAVE_SYS_UTSNAME_H
#	include <sys/utsname.h>
#endif

ZBX_METRIC	parameter_hostname =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
	{"system.hostname",     CF_HAVEPARAMS,  SYSTEM_HOSTNAME,        NULL};

int	SYSTEM_HOSTNAME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*tmp;
	unsigned char	param_type, param_transform;
	struct utsname	name;

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
		SET_MSG_RESULT(result, zbx_strdup(NULL, "NetBIOS is not supported on the current platform."));
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

	if (-1 == uname(&name))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (ZBX_SYSTEM_HOSTNAME_TYPE_SHORTHOST == param_type)
	{
		char	*dot;

		if (NULL != (dot = strchr(name.nodename, '.')))
			*dot = '\0';
	}

	if (ZBX_SYSTEM_HOSTNAME_TRANSFORM_LOWER == param_transform)
	{
		int	i;

		for (i = 0; name.nodename[i] != '\0'; i++)
		{
			name.nodename[i] = tolower((unsigned char)name.nodename[i]);
		}
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, name.nodename));

	return SYSINFO_RET_OK;
}

