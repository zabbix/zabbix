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

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, (zbx_uint64_t)sysconf(_SC_PHYS_PAGES) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, (zbx_uint64_t)sysconf(_SC_AVPHYS_PAGES) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	zbx_uint64_t	used;

	used = sysconf(_SC_PHYS_PAGES) - sysconf(_SC_AVPHYS_PAGES);

	SET_UI64_RESULT(result, used * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	zbx_uint64_t	used, total;

	if (0 == (total = sysconf(_SC_PHYS_PAGES)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	used = total - sysconf(_SC_AVPHYS_PAGES);

	SET_DBL_RESULT(result, used / (double)total * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, (zbx_uint64_t)sysconf(_SC_AVPHYS_PAGES) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	zbx_uint64_t	total;

	if (0 == (total = sysconf(_SC_PHYS_PAGES)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, sysconf(_SC_AVPHYS_PAGES) / (double)total * 100);

	return SYSINFO_RET_OK;
}

int     VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

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
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}
