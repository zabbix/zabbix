/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxsysinfo.h"
#include "../sysinfo.h"
#include "../common/zbxsysinfo_common.h"

static int	vm_memory_total(AGENT_RESULT *result)
{
	return execute_int("vmstat -s | awk 'BEGIN{pages=0}{gsub(\"[()]\",\"\");if($4==\"pagesize\")pgsize=($6);if(($2==\"inactive\"||$2==\"active\"||$2==\"wired\")&&$3==\"pages\")pages+=$1}END{printf (pages*pgsize)}'", result);
}

static int	vm_memory_free(AGENT_RESULT *result)
{
	return execute_int("vmstat -s | awk '{gsub(\"[()]\",\"\");if($4==\"pagesize\")pgsize=($6);if($2==\"free\"&&$3==\"pages\")pages=($1)}END{printf (pages*pgsize)}'", result);
}

static int	vm_memory_used(AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	free, total;

	zbx_init_agent_result(&result_tmp);

	if (SYSINFO_RET_OK != vm_memory_free(&result_tmp))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, result_tmp.msg));
		goto clean;
	}

	free = result_tmp.ui64;

	if (SYSINFO_RET_OK != vm_memory_total(&result_tmp))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, result_tmp.msg));
		goto clean;
	}

	total = result_tmp.ui64;

	SET_UI64_RESULT(result, total - free);

	ret = SYSINFO_RET_OK;
clean:
	zbx_free_agent_result(&result_tmp);

	return ret;
}

static int	vm_memory_pused(AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	free, total;

	zbx_init_agent_result(&result_tmp);

	if (SYSINFO_RET_OK != vm_memory_free(&result_tmp))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, result_tmp.msg));
		goto clean;
	}

	free = result_tmp.ui64;

	if (SYSINFO_RET_OK != vm_memory_total(&result_tmp))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, result_tmp.msg));
		goto clean;
	}

	total = result_tmp.ui64;

	if (0 == total)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		goto clean;
	}

	SET_UI64_RESULT(result, (total - free) / (double)total * 100);

	ret = SYSINFO_RET_OK;
clean:
	zbx_free_agent_result(&result_tmp);

	return ret;
}

static int	vm_memory_available(AGENT_RESULT *result)
{
	return vm_memory_free(result);
}

static int	vm_memory_pavailable(AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	free, total;

	zbx_init_agent_result(&result_tmp);

	if (SYSINFO_RET_OK != vm_memory_free(&result_tmp))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, result_tmp.msg));
		goto clean;
	}

	free = result_tmp.ui64;

	if (SYSINFO_RET_OK != vm_memory_total(&result_tmp))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, result_tmp.msg));
		goto clean;
	}

	total = result_tmp.ui64;

	if (0 == total)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		goto clean;
	}

	SET_UI64_RESULT(result, free / (double)total * 100);

	ret = SYSINFO_RET_OK;
clean:
	zbx_free_agent_result(&result_tmp);

	return ret;
}

int	vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		ret = vm_memory_total(result);
	else if (0 == strcmp(mode, "free"))
		ret = vm_memory_free(result);
	else if (0 == strcmp(mode, "used"))
		ret = vm_memory_used(result);
	else if (0 == strcmp(mode, "pused"))
		ret = vm_memory_pused(result);
	else if (0 == strcmp(mode, "available"))
		ret = vm_memory_available(result);
	else if (0 == strcmp(mode, "pavailable"))
		ret = vm_memory_pavailable(result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}
