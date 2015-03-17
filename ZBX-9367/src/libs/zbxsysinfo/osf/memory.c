/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "../common/common.h"

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	return EXECUTE_INT(NULL, "vmstat -s | awk 'BEGIN{pages=0}{gsub(\"[()]\",\"\");if($4==\"pagesize\")pgsize=($6);if(($2==\"inactive\"||$2==\"active\"||$2==\"wired\")&&$3==\"pages\")pages+=$1}END{printf (pages*pgsize)}'", 0, result);
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	return EXECUTE_INT(NULL, "vmstat -s | awk '{gsub(\"[()]\",\"\");if($4==\"pagesize\")pgsize=($6);if($2==\"free\"&&$3==\"pages\")pages=($1)}END{printf (pages*pgsize)}'", 0, result);
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	free, total;

	init_result(&result_tmp);

	if (SYSINFO_RET_OK != VM_MEMORY_FREE(&result_tmp))
		goto clean;

	free = result_tmp.ui64;

	if (SYSINFO_RET_OK != VM_MEMORY_TOTAL(&result_tmp))
		goto clean;

	total = result_tmp.ui64;

	SET_UI64_RESULT(result, total - free);

	ret = SYSINFO_RET_OK;
clean:
	free_result(&result_tmp);

	return ret;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	free, total;

	init_result(&result_tmp);

	if (SYSINFO_RET_OK != VM_MEMORY_FREE(&result_tmp))
		goto clean;

	free = result_tmp.ui64;

	if (SYSINFO_RET_OK != VM_MEMORY_TOTAL(&result_tmp))
		goto clean;

	total = result_tmp.ui64;

	if (0 == total)
		goto clean;

	SET_UI64_RESULT(result, (total - free) / (double)total * 100);

	ret = SYSINFO_RET_OK;
clean:
	free_result(&result_tmp);

	return ret;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	return VM_MEMORY_FREE(result);
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	free, total;

	init_result(&result_tmp);

	if (SYSINFO_RET_OK != VM_MEMORY_FREE(&result_tmp))
		goto clean;

	free = result_tmp.ui64;

	if (SYSINFO_RET_OK != VM_MEMORY_TOTAL(&result_tmp))
		goto clean;

	total = result_tmp.ui64;

	if (0 == total)
		goto clean;

	SET_UI64_RESULT(result, free / (double)total * 100);

	ret = SYSINFO_RET_OK;
clean:
	free_result(&result_tmp);

	return ret;
}

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"total",	VM_MEMORY_TOTAL},
		{"free",	VM_MEMORY_FREE},
		{"used",	VM_MEMORY_USED},
		{"pused",	VM_MEMORY_PUSED},
		{"available",	VM_MEMORY_AVAILABLE},
		{"pavailable",	VM_MEMORY_PAVAILABLE},
		{NULL,		0}
	};

	char	mode[MAX_STRING_LEN];
	int	i;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(result);

	return SYSINFO_RET_FAIL;
}
