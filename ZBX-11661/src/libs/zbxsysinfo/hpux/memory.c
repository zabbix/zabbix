/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "log.h"

struct pst_static	pst;
struct pst_dynamic	pdy;

#define ZBX_PSTAT_GETSTATIC()											\
														\
	if (-1 == pstat_getstatic(&pst, sizeof(pst), 1, 0))							\
	{													\
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain static system information: %s",	\
				zbx_strerror(errno)));								\
		return SYSINFO_RET_FAIL;									\
	}

#define ZBX_PSTAT_GETDYNAMIC()											\
														\
	if (-1 == pstat_getdynamic(&pdy, sizeof(pdy), 1, 0))							\
	{													\
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain dynamic system information: %s",	\
				zbx_strerror(errno)));								\
		return SYSINFO_RET_FAIL;									\
	}

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)pst.physical_memory * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)pdy.psd_free * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_ACTIVE(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)pdy.psd_arm * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)(pst.physical_memory - pdy.psd_free) * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	if (0 == pst.physical_memory)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, (pst.physical_memory - pdy.psd_free) / (double)pst.physical_memory * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)pdy.psd_free * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	if (0 == pst.physical_memory)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, pdy.psd_free / (double)pst.physical_memory * 100);

	return SYSINFO_RET_OK;
}

int	VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	*mode;

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
	else if (0 == strcmp(mode, "active"))
		ret = VM_MEMORY_ACTIVE(result);
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
		ret = SYSINFO_RET_FAIL;
	}

	return ret;
}
