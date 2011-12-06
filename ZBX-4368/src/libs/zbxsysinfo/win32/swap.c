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

#include "common.h"
#include "sysinfo.h"
#include "symbols.h"

int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MEMORYSTATUSEX	ms_ex;
	MEMORYSTATUS	ms;

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

		if (strcmp(mode, "total") == 0)
		{
			SET_UI64_RESULT(result, ms_ex.ullTotalPageFile);
			return SYSINFO_RET_OK;
		}
		else if (strcmp(mode, "free") == 0)
		{
			SET_UI64_RESULT(result, ms_ex.ullAvailPageFile);
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

		if (strcmp(mode,"total") == 0)
		{
			SET_UI64_RESULT(result, ms.dwTotalPageFile);
			return SYSINFO_RET_OK;
		}
		else if (strcmp(mode,"free") == 0)
		{
			SET_UI64_RESULT(result, ms.dwAvailPageFile);
			return SYSINFO_RET_OK;
		}
	}

	return SYSINFO_RET_OK;
}
