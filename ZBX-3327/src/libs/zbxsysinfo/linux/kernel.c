/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

static int	read_uint64_from_procfs(const char *path, zbx_uint64_t *value)
{
	int	ret = SYSINFO_RET_FAIL;
	char	line[MAX_STRING_LEN];

	zbx_uint64_t	tmp;

	FILE	*f;

	if (NULL != (f = fopen(path, "r")))
	{
		if (NULL != fgets(line, sizeof(line), f))
		{
			if (sscanf(line, ZBX_FS_UI64"\n", &tmp) == 1)
			{
				*value = tmp;
				ret = SYSINFO_RET_OK;
			}
		}
		zbx_fclose(f);
	}

	return ret;
}

int	KERNEL_MAXFILES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK == read_uint64_from_procfs("/proc/sys/fs/file-max", &value))
	{
		SET_UI64_RESULT(result, value);
		ret = SYSINFO_RET_OK;
	}

	return ret;
}

int	KERNEL_MAXPROC(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK == read_uint64_from_procfs("/proc/sys/kernel/pid_max", &value))
	{
		SET_UI64_RESULT(result, value);
		ret = SYSINFO_RET_OK;
	}

	return ret;
}
