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
	return EXECUTE_INT("vmstat -s | awk 'BEGIN{pages=0}{gsub(\"[()]\",\"\");if($4==\"pagesize\")pgsize=($6);if(($2==\"inactive\"||$2==\"active\"||$2==\"wired\")&&$3==\"pages\")pages+=$1}END{printf (pages*pgsize)}'", result);
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	return EXECUTE_INT("vmstat -s | awk '{gsub(\"[()]\",\"\");if($4==\"pagesize\")pgsize=($6);if($2==\"free\"&&$3==\"pages\")pages=($1)}END{printf (pages*pgsize)}'", result);
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

int     VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		ret = VM_MEMORY_TOTAL(result);
	else if (0 == strcmp(mode, "free"))
		ret = VM_MEMORY_FREE(result);
	else if (0 == strcmp(mode, "used"))
		ret = VM_MEMORY_USED(result);
	else if (0 == strcmp(mode, "pused"))
		ret = VM_MEMORY_PUSED(result);
	else if (0 == strcmp(mode, "available"))
		ret = VM_MEMORY_AVAILABLE(result);
	else if (0 == strcmp(mode, "pavailable"))
		ret = VM_MEMORY_PAVAILABLE(result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}
