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

#include "sysinfo.h"
#include <sys/utsname.h>
#include "zbxalgo.h"
#include "zbxexec.h"
#include "cfg.h"

int	SYSTEM_SW_ARCH(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct utsname	name;

	if (-1 == uname(&name))
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, zbx_strdup(NULL, name.machine));

	return SYSINFO_RET_OK;
}

int     SYSTEM_SW_OS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define SW_OS_NAME	"/etc/issue.net"
#define SW_OS_SHORT	"/proc/version_signature"
#define SW_OS_FULL	"/proc/version"
	char		type[8], line[MAX_STRING_LEN];
	int		ret = SYSINFO_RET_FAIL;
	FILE		*f = NULL;

	if (1 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, type, sizeof(type)))
		*type = '\0';

	if ('\0' == *type || 0 == strcmp(type, "name"))
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
		SET_STR_RESULT(result, zbx_strdup(NULL, line));
	}
	zbx_fclose(f);

	return ret;
}

int     SYSTEM_SW_PACKAGES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define DPKG_PACKAGES_FILE	"/var/lib/dpkg/status"
#define SLACKWARE_PACKAGES_DIR	"/var/log/packages/"
#define RPM_PACKAGES_CMD	"rpm -qa | sort"
#define RPM_TEST_CMD		"rpm --version"
	int			offset = 0, i;
	char			line[MAX_STRING_LEN], package[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
				buffer[MAX_BUFFER_LEN], regex[MAX_STRING_LEN], *cmdbuf = NULL, *c;
	FILE			*f;
	zbx_vector_str_t	packages;
	DIR			*dir;
	struct dirent		*entry;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, regex, sizeof(regex)))
		*regex = '\0';

	zbx_vector_str_create(&packages);

	if (NULL != (f = fopen(DPKG_PACKAGES_FILE, "r")))
	{
		/* DPKG package manager */

		while (NULL != fgets(line, sizeof(line), f))
		{
			if (1 != sscanf(line, "Package: %s", package))
				continue;

			if ('\0' != *regex && NULL == zbx_regexp_match(package, regex, NULL))
				continue;

			/* find "Status:" line, might not be the next one */
next_line:
			if (NULL == fgets(line, sizeof(line), f))
				break;
			if (1 != sscanf(line, "Status: %[^\n]", tmp))
				goto next_line;

			if (0 == strcmp(tmp, "install ok installed"))
				zbx_vector_str_append(&packages, zbx_strdup(NULL, package));
		}

		zbx_fclose(f);
	}
	else if (NULL != (dir = opendir(SLACKWARE_PACKAGES_DIR)))
	{
		/* check SLACKWARE_PACKAGES_DIR before using RPM since RPM can also be used on slackware */

		while (NULL != (entry = readdir(dir)))
		{
			if (0 == strcmp(entry->d_name, ".") || 0 == strcmp(entry->d_name, ".."))
				continue;

			if ('\0' == *regex || NULL != zbx_regexp_match(entry->d_name, regex, NULL))
				zbx_vector_str_append(&packages, zbx_strdup(NULL, entry->d_name));
		}

		closedir(dir);
	}
	else if (SUCCEED == zbx_execute(RPM_TEST_CMD, &cmdbuf, tmp, sizeof(tmp), CONFIG_TIMEOUT) &&
			0 < strlen(cmdbuf))
	{
		/* RPM package manager */

		zbx_free(cmdbuf);
		if (SUCCEED == zbx_execute(RPM_PACKAGES_CMD, &cmdbuf, tmp, sizeof(tmp), CONFIG_TIMEOUT))
		{
			c = strtok(cmdbuf, "\n");
			while (NULL != c)
			{
				if ('\0' == *regex || NULL != zbx_regexp_match(c, regex, NULL))
					zbx_vector_str_append(&packages, zbx_strdup(NULL, c));
				c = strtok(NULL, "\n");
			}

			zbx_free(cmdbuf);
		}
	}
	else
	{
		/* unsupported package manager */

		zbx_free(cmdbuf);
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_str_sort(&packages, ZBX_DEFAULT_STR_COMPARE_FUNC);

	for (i = 0; i < packages.values_num; i++)
	{
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%s, ", packages.values[i]);
		zbx_free(packages.values[i]);
	}

	zbx_vector_str_destroy(&packages);

	if (0 < offset)
		zbx_rtrim(buffer, ", ");

	SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));

	return SYSINFO_RET_OK;
}
