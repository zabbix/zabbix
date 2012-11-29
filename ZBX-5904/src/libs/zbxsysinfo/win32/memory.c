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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "symbols.h"

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	PERFORMANCE_INFORMATION pfi;
	MEMORYSTATUSEX		ms_ex;
	MEMORYSTATUS		ms;
	char			mode[16];

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	if (0 == strcmp(mode, "cached"))
	{
		if (NULL == zbx_GetPerformanceInfo)
			return SYSINFO_RET_FAIL;

		zbx_GetPerformanceInfo(&pfi, sizeof(PERFORMANCE_INFORMATION));

		SET_UI64_RESULT(result, (zbx_uint64_t)pfi.SystemCache * pfi.PageSize);

		return SYSINFO_RET_OK;
	}

	if (NULL != zbx_GlobalMemoryStatusEx)
	{
		ms_ex.dwLength = sizeof(MEMORYSTATUSEX);

		zbx_GlobalMemoryStatusEx(&ms_ex);

		if (0 == strcmp(mode, "total"))
			SET_UI64_RESULT(result, ms_ex.ullTotalPhys);
		else if (0 == strcmp(mode, "free"))
			SET_UI64_RESULT(result, ms_ex.ullAvailPhys);
		else if (0 == strcmp(mode, "used"))
			SET_UI64_RESULT(result, ms_ex.ullTotalPhys - ms_ex.ullAvailPhys);
		else if (0 == strcmp(mode, "pused") && 0 != ms_ex.ullTotalPhys)
			SET_DBL_RESULT(result, (ms_ex.ullTotalPhys - ms_ex.ullAvailPhys) / (double)ms_ex.ullTotalPhys * 100);
		else if (0 == strcmp(mode, "available"))
			SET_UI64_RESULT(result, ms_ex.ullAvailPhys);
		else if (0 == strcmp(mode, "pavailable") && 0 != ms_ex.ullTotalPhys)
			SET_DBL_RESULT(result, ms_ex.ullAvailPhys / (double)ms_ex.ullTotalPhys * 100);
		else
			return SYSINFO_RET_FAIL;
	}
	else
	{
		GlobalMemoryStatus(&ms);

		if (0 == strcmp(mode, "total"))
			SET_UI64_RESULT(result, ms.dwTotalPhys);
		else if (0 == strcmp(mode, "free"))
			SET_UI64_RESULT(result, ms.dwAvailPhys);
		else if (0 == strcmp(mode, "used"))
			SET_UI64_RESULT(result, ms.dwTotalPhys - ms.dwAvailPhys);
		else if (0 == strcmp(mode, "pused") && 0 != ms.dwTotalPhys)
			SET_DBL_RESULT(result, (ms.dwTotalPhys - ms.dwAvailPhys) / (double)ms.dwTotalPhys * 100);
		else if (0 == strcmp(mode, "available"))
			SET_UI64_RESULT(result, ms.dwAvailPhys);
		else if (0 == strcmp(mode, "pavailable") && 0 != ms.dwTotalPhys)
			SET_DBL_RESULT(result, ms.dwAvailPhys / (double)ms.dwTotalPhys * 100);
		else
			return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}
