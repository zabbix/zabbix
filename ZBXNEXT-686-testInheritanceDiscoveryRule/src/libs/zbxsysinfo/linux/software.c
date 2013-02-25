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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "sysinfo.h"
#include <sys/utsname.h>
#include "zbxalgo.h"
#include "zbxexec.h"
#include "cfg.h"
#include "software.h"

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
	char	type[8], line[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL;
	FILE	*f = NULL;

	if (1 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, type, sizeof(type)))
		*type = '\0';

	if ('\0' == *type || 0 == strcmp(type, "full"))
		f = fopen(SW_OS_FULL, "r");
	else if (0 == strcmp(type, "short"))
		f = fopen(SW_OS_SHORT, "r");
	else if (0 == strcmp(type, "name"))
		f = fopen(SW_OS_NAME, "r");

	if (NULL == f)
		return ret;

	if (NULL != fgets(line, sizeof(line), f))
	{
		ret = SYSINFO_RET_OK;
		zbx_rtrim(line, ZBX_WHITESPACE);
		SET_STR_RESULT(result, zbx_strdup(NULL, line));
	}
	zbx_fclose(f);

	return ret;
}

static int	dpkg_parser(const char *line, char *package, size_t max_package_len)
{
	char	fmt[32], tmp[32];

	zbx_snprintf(fmt, sizeof(fmt), "%%" ZBX_FS_SIZE_T "s %%" ZBX_FS_SIZE_T "s",
			(zbx_fs_size_t)max_package_len, (zbx_fs_size_t)sizeof(tmp));

	if (2 != sscanf(line, fmt, package, tmp) || 0 != strcmp(tmp, "install"))
		return FAIL;

	return SUCCEED;
}

static size_t	print_packages(char *buffer, size_t size, zbx_vector_str_t *packages, const char *manager)
{
	size_t	offset = 0;
	int	i;

	if (NULL != manager)
		offset += zbx_snprintf(buffer + offset, size - offset, "[%s]", manager);

	if (0 < packages->values_num)
	{
		if (NULL != manager)
			offset += zbx_snprintf(buffer + offset, size - offset, " ");

		zbx_vector_str_sort(packages, ZBX_DEFAULT_STR_COMPARE_FUNC);

		for (i = 0; i < packages->values_num; i++)
			offset += zbx_snprintf(buffer + offset, size - offset, "%s, ", packages->values[i]);

		offset -= 2;
	}

	buffer[offset] = '\0';

	return offset;
}

static ZBX_PACKAGE_MANAGER	package_managers[] =
/*	NAME		TEST_CMD					LIST_CMD			PARSER */
{
	{"dpkg",	"dpkg --version 2> /dev/null",			"dpkg --get-selections",	dpkg_parser},
	{"pkgtools",	"[ -d /var/log/packages ] && echo true",	"ls /var/log/packages",		NULL},
	{"rpm",		"rpm --version 2> /dev/null",			"rpm -qa",			NULL},
	{"pacman",	"pacman --version 2> /dev/null",		"pacman -Q",			NULL},
	{0}
};

int     SYSTEM_SW_PACKAGES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	size_t			offset = 0;
	int			ret = SYSINFO_RET_FAIL, show_pm, i, j;
	char			buffer[MAX_BUFFER_LEN], regex[MAX_STRING_LEN], manager[MAX_STRING_LEN],
				tmp[MAX_STRING_LEN], *buf = NULL, *package;
	zbx_vector_str_t	packages;
	ZBX_PACKAGE_MANAGER	*mng;

	if (3 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, regex, sizeof(regex)) || 0 == strcmp(regex, "all"))
		*regex = '\0';

	if (0 != get_param(param, 2, manager, sizeof(manager)) || 0 == strcmp(manager, "all"))
		*manager = '\0';

	if (0 != get_param(param, 3, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "full"))
		show_pm = 1;	/* show package managers' names */
	else if (0 == strcmp(tmp, "short"))
		show_pm = 0;
	else
		return ret;

	*buffer = '\0';
	zbx_vector_str_create(&packages);

	for (i = 0; NULL != package_managers[i].name; i++)
	{
		mng = &package_managers[i];

		if ('\0' != *manager && 0 != strcmp(manager, mng->name))
			continue;

		if (SUCCEED == zbx_execute(mng->test_cmd, &buf, tmp, sizeof(tmp), CONFIG_TIMEOUT) &&
				'\0' != *buf)	/* consider PMS present, if test_cmd outputs anything to stdout */
		{
			if (SUCCEED != zbx_execute(mng->list_cmd, &buf, tmp, sizeof(tmp), CONFIG_TIMEOUT))
				continue;

			ret = SYSINFO_RET_OK;

			package = strtok(buf, "\n");

			while (NULL != package)
			{
				if (NULL != mng->parser)	/* check if the package name needs to be parsed */
				{
					if (SUCCEED == mng->parser(package, tmp, sizeof(tmp)))
						package = tmp;
					else
						goto next;
				}

				if ('\0' != *regex && NULL == zbx_regexp_match(package, regex, NULL))
					goto next;

				zbx_vector_str_append(&packages, zbx_strdup(NULL, package));
next:
				package = strtok(NULL, "\n");
			}

			if (1 == show_pm)
			{
				offset += print_packages(buffer + offset, sizeof(buffer) - offset, &packages, mng->name);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\n");

				/* deallocate memory used for string vector elements */
				for (j = 0; j < packages.values_num; j++)
					zbx_free(packages.values[j]);
				packages.values_num = 0;
			}
		}
	}

	zbx_free(buf);

	if (0 == show_pm)
	{
		offset += print_packages(buffer + offset, sizeof(buffer) - offset, &packages, NULL);

		/* deallocate memory used for string vector elements */
		for (j = 0; j < packages.values_num; j++)
			zbx_free(packages.values[j]);
		packages.values_num = 0;
	}
	else if (0 != offset)
		buffer[--offset] = '\0';

	zbx_vector_str_destroy(&packages);

	if (SYSINFO_RET_OK == ret)
		SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));

	return ret;
}
