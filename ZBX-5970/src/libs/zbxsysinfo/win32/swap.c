/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MEMORYSTATUSEX	ms_ex;
	MEMORYSTATUS	ms;
	DWORDLONG	real_swap_total_ex, real_swap_avail_ex;
	SIZE_T		real_swap_total, real_swap_avail;

	char swapdev[10];
	char mode[10];

	if(num_param(param) > 2)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, swapdev, sizeof(swapdev)) != 0)
	{
		swapdev[0] = '\0';
	}
	if(swapdev[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(swapdev, sizeof(swapdev), "all");
	}
	if(strncmp(swapdev, "all", sizeof(swapdev)))
	{  /* only 'all' parameter supported */
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}
	if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "total");
	}

	if(NULL != zbx_GlobalMemoryStatusEx)
	{
		ms_ex.dwLength = sizeof(MEMORYSTATUSEX);

		zbx_GlobalMemoryStatusEx(&ms_ex);

		real_swap_total_ex = ((ms_ex.ullTotalPageFile - ms_ex.ullTotalPhys) >= 0) ?
				ms_ex.ullTotalPageFile - ms_ex.ullTotalPhys : 0;
		real_swap_avail_ex = ((ms_ex.ullAvailPageFile - ms_ex.ullAvailPhys) < real_swap_total_ex) ?
				ms_ex.ullAvailPageFile - ms_ex.ullAvailPhys : real_swap_total_ex;

		if (real_swap_avail_ex < 0)
			real_swap_avail_ex = 0;

		if (strcmp(mode, "total") == 0)
		{
			SET_UI64_RESULT(result, real_swap_total_ex);
			return SYSINFO_RET_OK;
		}
		else if (strcmp(mode, "free") == 0)
		{
			SET_UI64_RESULT(result, real_swap_avail_ex);
			return SYSINFO_RET_OK;
		}
		else
		{
			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		GlobalMemoryStatus(&ms);

		real_swap_total = ((ms.dwTotalPageFile - ms.dwTotalPhys) >= 0) ?
				ms.dwTotalPageFile - ms.dwTotalPhys : 0;
		real_swap_avail = ((ms.dwAvailPageFile - ms.dwAvailPhys) < real_swap_total) ?
				ms.dwAvailPageFile - ms.dwAvailPhys : real_swap_total;

		if (real_swap_avail < 0)
			real_swap_avail = 0;

		if (strcmp(mode,"total") == 0)
		{
			SET_UI64_RESULT(result, real_swap_total);
			return SYSINFO_RET_OK;
		}
		else if (strcmp(mode,"free") == 0)
		{
			SET_UI64_RESULT(result, real_swap_avail);
			return SYSINFO_RET_OK;
		}
	}

	return SYSINFO_RET_OK;
}
