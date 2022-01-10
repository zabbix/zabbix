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

#include "common.h"
#include "sysinfo.h"

static int	read_uint64_from_procfs(const char *path, int first_num, zbx_uint64_t *value)
{
	int	ret = SYSINFO_RET_FAIL;
	char	line[MAX_STRING_LEN];
	FILE	*f;

	if (NULL != (f = fopen(path, "r")))
	{
		if (NULL != fgets(line, sizeof(line), f))
		{
			if (1 == first_num)
			{
				if (1 == sscanf(line, ZBX_FS_UI64 "\t", value))
					ret = SYSINFO_RET_OK;
			}
			else
			{
				if (1 == sscanf(line, ZBX_FS_UI64 "\n", value))
					ret = SYSINFO_RET_OK;
			}
		}
		zbx_fclose(f);
	}

	return ret;
}

int	KERNEL_MAXFILES(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	ZBX_UNUSED(request);

	if (SYSINFO_RET_FAIL == read_uint64_from_procfs("/proc/sys/fs/file-max", 0, &value))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain data from /proc/sys/fs/file-max."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);
	return SYSINFO_RET_OK;
}

int	KERNEL_MAXPROC(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	ZBX_UNUSED(request);

	if (SYSINFO_RET_FAIL == read_uint64_from_procfs("/proc/sys/kernel/pid_max", 0, &value))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain data from /proc/sys/kernel/pid_max."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);
	return SYSINFO_RET_OK;
}

int	KERNEL_OPENFILES(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	ZBX_UNUSED(request);

	if (SYSINFO_RET_FAIL == read_uint64_from_procfs("/proc/sys/fs/file-nr", 1, &value))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain data from /proc/sys/fs/file-nr."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);
	return SYSINFO_RET_OK;
}
