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

#include "config.h"

#include "common.h"
#include "sysinfo.h"

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#if defined(HAVE_GETPERFORMANCEINFO)
	PERFORMANCE_INFORMATION pfi;
#endif /* HAVE_GETPERFORMANCEINFO */

#if defined(HAVE_GLOBALMEMORYSTATUSEX)
	MEMORYSTATUSEX ms;
#else /* HAVE_GLOBALMEMORYSTATUSEX */
	MEMORYSTATUS ms;
#endif
	char	mode[10];

	if(num_param(param) > 1)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}
	if(mode[0] == '\0')
	{
		/* default parameter */
		sprintf(mode, "total");
	}

#if defined(HAVE_GETPERFORMANCEINFO)

	if (strcmp(mode,"cached") == 0)
	{

		GetPerformanceInfo(&pfi,sizeof(PERFORMANCE_INFORMATION));

		SET_UI64_RESULT(result, (zbx_uint64_t)pfi.SystemCache * (zbx_uint64_t)pfi.PageSize);

		return SYSINFO_RET_OK;
	}

#endif /* HAVE_GETPERFORMANCEINFO */

#if defined(HAVE_GLOBALMEMORYSTATUSEX)

	ms.dwLength = sizeof(MEMORYSTATUSEX);

	GlobalMemoryStatusEx(&ms);

	if (strcmp(mode, "total") == 0)
	{
		SET_UI64_RESULT(result, ms.ullTotalPhys);
		return SYSINFO_RET_OK;
	}
	else if (strcmp(mode, "free") == 0)
	{
		SET_UI64_RESULT(result, ms.ullAvailPhys);
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

#else /* not HAVE_GLOBALMEMORYSTATUSEX */

	GlobalMemoryStatus(&ms);

	if (strcmp(mode,"total") == 0)
	{
		SET_UI64_RESULT(result, ms.dwTotalPhys);
		return SYSINFO_RET_OK;
	}
	else if (strcmp(mode,"free") == 0)
	{
		SET_UI64_RESULT(result, ms.dwAvailPhys);
		return SYSINFO_RET_OK;
	}

#endif /* HAVE_GLOBALMEMORYSTATUSEX */

	return SYSINFO_RET_FAIL;

}

