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
#include "symbols.h"

int	SYSTEM_SWAP_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	MEMORYSTATUSEX	ms_ex;
	MEMORYSTATUS	ms;
	char		*swapdev, *mode;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	/* only 'all' parameter supported */
	if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
		return SYSINFO_RET_FAIL;

	if (NULL != zbx_GlobalMemoryStatusEx)
	{
		ms_ex.dwLength = sizeof(MEMORYSTATUSEX);

		zbx_GlobalMemoryStatusEx(&ms_ex);

		if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
			SET_UI64_RESULT(result, ms_ex.ullTotalPageFile);
		else if (0 == strcmp(mode, "free"))
			SET_UI64_RESULT(result, ms_ex.ullAvailPageFile);
		else if (0 == strcmp(mode, "used"))
			SET_UI64_RESULT(result, ms_ex.ullTotalPageFile - ms_ex.ullAvailPageFile);
		else
			return SYSINFO_RET_FAIL;
	}
	else
	{
		GlobalMemoryStatus(&ms);

		if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
			SET_UI64_RESULT(result, ms.dwTotalPageFile);
		else if (0 == strcmp(mode, "free"))
			SET_UI64_RESULT(result, ms.dwAvailPageFile);
		else if (0 == strcmp(mode, "used"))
			SET_UI64_RESULT(result, ms.dwTotalPageFile - ms.dwAvailPageFile);
		else
			return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}
