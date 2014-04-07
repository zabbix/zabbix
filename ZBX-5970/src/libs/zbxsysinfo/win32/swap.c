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

/***************************************************************************
 *                                                                         *
 * Due to the way Windows API functions report memory metrics,             *
 * it is impossible to accurately retrieve swap (virtual memory)           *
 * sizes as Windows either does not report them properly or                *
 * reports swap along with actual physical memory as page file             *
 * sizes. The only resolution that could be applied was calculatively      *
 * deducting the swap sizes knowing the page and physical memory           *
 * sizes.                                                                  *
 *                                                                         *
 * While developing this solution, it was found that in virtualized        *
 * environments Windows reports incorrect values for various memory        *
 * types. For example, the free memory size may be larger than the         *
 * total memory size of the system, or, under certain circumstances,       *
 * these functions may return negative values.                             *
 *                                                                         *
 * Taking these fallacious conditions into account, these calculations     *
 * guarantee that the available swap size is never larger than the total   *
 * available (if it is reported as such, it is lowered to the total        *
 * as there is a higher probability of that number staying consistent      *
 * than the other way around). In case of the system reporting negative    *
 * values, the appropriate metric is returned as 0.                        *
 *                                                                         *
 * The returned values are not guaranteed to be accurate and may report    *
 * inaccurate results which may depend on the system and environment.      *
 * In a proper system environment, this should logically work just as any  *
 * arithmetic calculation, but the problem lies within the way Windows     *
 * itself operates. Although this guestimate should work most of the time, *
 * there are no guarantees that it will.                                   *
 *                                                                         *
 ***************************************************************************/
int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MEMORYSTATUSEX	ms_ex;
	MEMORYSTATUS	ms;
	zbx_uint64_t	real_swap_total, real_swap_avail;

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

		real_swap_total = ms_ex.ullTotalPageFile > ms_ex.ullTotalPhys ?
				ms_ex.ullTotalPageFile - ms_ex.ullTotalPhys : 0;
		real_swap_avail = ms_ex.ullAvailPageFile > ms_ex.ullAvailPhys ?
				ms_ex.ullAvailPageFile - ms_ex.ullAvailPhys : 0;
	}
	else
	{
		GlobalMemoryStatus(&ms);

		real_swap_total = ms.dwTotalPageFile > ms.dwTotalPhys ?
				ms.dwTotalPageFile - ms.dwTotalPhys : 0;
		real_swap_avail = ms.dwAvailPageFile > ms.dwAvailPhys ?
				ms.dwAvailPageFile - ms.dwAvailPhys : 0;
	}

	if (real_swap_avail > real_swap_total)
		real_swap_avail = real_swap_total;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, real_swap_total);
	else if (0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, real_swap_avail);
	else
		return SYSINFO_RET_FAIL;


	return SYSINFO_RET_OK;
}
