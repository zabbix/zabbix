/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)info.totalram * info.mem_unit);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)info.freeram * info.mem_unit);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_BUFFERS(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)info.bufferram * info.mem_unit);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_CACHED(AGENT_RESULT *result)
{
	FILE		*f;
	char		*t, c[MAX_STRING_LEN];
	zbx_uint64_t	res = 0;

	if (NULL == (f = fopen("/proc/meminfo", "r")))
		return SYSINFO_RET_FAIL;

	while (NULL != fgets(c, sizeof(c), f))
	{
		if (0 == strncmp(c, "Cached:", 7))
		{
			t = strtok(c, " ");
			t = strtok(NULL, " ");
			sscanf(t, ZBX_FS_UI64, &res);
			t = strtok(NULL, " ");

			if (0 != strcasecmp(t, "kb"))
				res <<= 10;
			else if (0 != strcasecmp(t, "mb"))
				res <<= 20;
			else if (0 != strcasecmp(t, "gb"))
				res <<= 30;
			else if (0 != strcasecmp(t, "tb"))
				res <<= 40;

			break;
		}
	}
	zbx_fclose(f);

	SET_UI64_RESULT(result, res);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)(info.totalram - info.freeram) * info.mem_unit);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info) || 0 == info.totalram)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (info.totalram - info.freeram) / (double)info.totalram * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	struct sysinfo	info;
	AGENT_RESULT	result_tmp;

	if (0 != sysinfo(&info))
		return SYSINFO_RET_FAIL;

	init_result(&result_tmp);

	if (SYSINFO_RET_OK != VM_MEMORY_CACHED(&result_tmp))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)(info.freeram + info.bufferram) * info.mem_unit + result_tmp.ui64);

	free_result(&result_tmp);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	struct sysinfo	info;
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	available, total;

	if (0 != sysinfo(&info))
		return SYSINFO_RET_FAIL;

	init_result(&result_tmp);

	if (SYSINFO_RET_OK != VM_MEMORY_CACHED(&result_tmp))
		return SYSINFO_RET_FAIL;

	available = (zbx_uint64_t)(info.freeram + info.bufferram) * info.mem_unit + result_tmp.ui64;
	total = (zbx_uint64_t)info.totalram * info.mem_unit;

	if (0 == total)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, available / (double)total * 100);

	free_result(&result_tmp);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_SHARED(AGENT_RESULT *result)
{
#ifdef KERNEL_2_4
	struct sysinfo	info;

	if (0 != sysinfo(&info))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)info.sharedram * info.mem_unit);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

int	VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
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
	else if (0 == strcmp(mode, "buffers"))
		ret = VM_MEMORY_BUFFERS(result);
	else if (0 == strcmp(mode, "cached"))
		ret = VM_MEMORY_CACHED(result);
	else if (0 == strcmp(mode, "used"))
		ret = VM_MEMORY_USED(result);
	else if (0 == strcmp(mode, "pused"))
		ret = VM_MEMORY_PUSED(result);
	else if (0 == strcmp(mode, "available"))
		ret = VM_MEMORY_AVAILABLE(result);
	else if (0 == strcmp(mode, "pavailable"))
		ret = VM_MEMORY_PAVAILABLE(result);
	else if (0 == strcmp(mode, "shared"))
		ret = VM_MEMORY_SHARED(result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}
