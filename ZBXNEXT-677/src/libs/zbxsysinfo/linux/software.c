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
		zbx_rtrim(line, " \r\n");
		ret = SYSINFO_RET_OK;
		SET_STR_RESULT(result, zbx_strdup(NULL, line));
	}
	zbx_fclose(f);

	return ret;
}

int	dpkg_parser(char *line, char *package)
{
	char tmp[20];

	if (2 != sscanf(line, "%s %s", package, tmp) || 0 != strcmp(tmp, "install"))
		return FAIL;

	return SUCCEED;
}

int     SYSTEM_SW_PACKAGES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int			ret = SYSINFO_RET_FAIL, show_pm, offset = 0, i;
	char			tmp[MAX_STRING_LEN], buffer[MAX_BUFFER_LEN], regex[MAX_STRING_LEN],
				*buf = NULL, *c, *package;
	zbx_vector_str_t	packages;
	ZBX_PACKAGE_MANAGER	*mng;

	if (2 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, regex, sizeof(regex)))
		*regex = '\0';

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "onlylist"))
		show_pm = 0;
	else if (0 == strcmp(tmp, "showPM"))
		show_pm = 1;
	else
		return ret;

	zbx_vector_str_create(&packages);

	for (i = 0; NULL != package_managers[i].name; i++)
	{
		mng = &package_managers[i];

		if (SUCCEED == (ret = zbx_execute(mng->test_cmd, &buf, NULL, 0, CONFIG_TIMEOUT)) &&
				0 < strlen(buf))
		{
			/* package management system is present */

			ret = SYSINFO_RET_OK;
			zbx_free(buf);

			ret = zbx_execute(mng->list_cmd, &buf, NULL, 0, CONFIG_TIMEOUT);

			c = strtok(buf, "\n");

			while (NULL != c)
			{
				if (NULL != mng->parser)
				{
					if (SUCCEED == mng->parser(c, tmp))
						c = tmp;
					else
						goto next;
				}

				if ('\0' != *regex && NULL == zbx_regexp_match(c, regex, NULL))
					goto next;

				if (1 == show_pm)
					package = zbx_dsprintf(NULL, "[%s]%s", mng->name, c);
				else
					package = zbx_strdup(NULL, c);

				zbx_vector_str_append(&packages, package);
next:
				c = strtok(NULL, "\n");
			}
		}
		zbx_free(buf);
	}


	if (SYSINFO_RET_OK == ret)
	{
		zbx_vector_str_sort(&packages, ZBX_DEFAULT_STR_COMPARE_FUNC);

		for (i = 0; i < packages.values_num; i++)
		{
			offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%s, ", packages.values[i]);
			zbx_free(packages.values[i]);
		}


		if (0 < offset)
			zbx_rtrim(buffer, ", ");

		SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));
	}

	zbx_vector_str_destroy(&packages);

	return ret;
}
