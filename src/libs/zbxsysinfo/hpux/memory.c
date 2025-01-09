/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxsysinfo.h"
#include "../sysinfo.h"

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

static int	vm_memory_total(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)pst.physical_memory * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	vm_memory_free(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)pdy.psd_free * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	vm_memory_active(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)pdy.psd_arm * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	vm_memory_used(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)(pst.physical_memory - pdy.psd_free) * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pused(AGENT_RESULT *result)
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

static int	vm_memory_available(AGENT_RESULT *result)
{
	ZBX_PSTAT_GETSTATIC();
	ZBX_PSTAT_GETDYNAMIC();

	SET_UI64_RESULT(result, (zbx_uint64_t)pdy.psd_free * pst.page_size);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pavailable(AGENT_RESULT *result)
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

int	vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result)
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
		ret = vm_memory_total(result);
	else if (0 == strcmp(mode, "free"))
		ret = vm_memory_free(result);
	else if (0 == strcmp(mode, "active"))
		ret = vm_memory_active(result);
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
		ret = SYSINFO_RET_FAIL;
	}

	return ret;
}
