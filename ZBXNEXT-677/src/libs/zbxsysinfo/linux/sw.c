/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include <sys/utsname.h>

int	SYSTEM_SW_ARCH(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct utsname	name;
	if (-1 == uname(&name))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, strdup(name.machine));
	return SYSINFO_RET_OK;
}

int     SYSTEM_SW_OS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define SW_OS_NAME	"/etc/issue.net"
#define SW_OS_SHORT	"/proc/version_signature"
#define SW_OS_FULL	"/proc/version"
	char		type[MAX_STRING_LEN], line[MAX_STRING_LEN];
	int		ret = SYSINFO_RET_FAIL;
	FILE		*f = NULL;

	if (1 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, type, sizeof(type)))
		*type = '\0';

	if (0 == strcmp(type, "name")  || '\0' == *type)
		f = fopen(SW_OS_NAME, "r");
	else if (0 == strcmp(type, "short"))
		f = fopen(SW_OS_SHORT, "r");
	else if (0 == strcmp(type, "full"))
		f = fopen(SW_OS_FULL, "r");

	if (NULL == f)
		return ret;

	if (NULL != fgets(line, sizeof(line), f))
	{
		zbx_rtrim(line, " \r\n");
		ret = SYSINFO_RET_OK;
		SET_STR_RESULT(result, strdup(line));
	}
	zbx_fclose(f);

	return ret;
}

int     SYSTEM_SW_PACKAGES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define SW_PACKAGES_FILE	"/var/lib/dpkg/status"
	int			ret = SYSINFO_RET_FAIL, offset = 0;
	char			line[MAX_STRING_LEN], package[MAX_STRING_LEN], status[MAX_STRING_LEN], buffer[MAX_BUFFER_LEN];
	FILE			*f;

	if (NULL == (f = fopen(SW_PACKAGES_FILE, "r")))
		return ret;

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (1 != sscanf(line, "Package: %s", package))
			continue;
next_line: /* find "Status:" line, might not be the next one */
		if (NULL == fgets(line, sizeof(line), f))
			break;
		if (1 != sscanf(line, "Status: %[^\n]", status))
			goto next_line;

		if (0 == strcmp(status, "install ok installed"))
			offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%s, ", package);
	}

	zbx_fclose(f);

	if (0 < offset)
	{
		buffer[offset - 2] = '\0'; /* remove ", " */
		ret = SYSINFO_RET_OK;
		SET_TEXT_RESULT(result, strdup(buffer));
	}

	return ret;
}
