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

int	VM_VIRTUAL_MEMORY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	MEMORYSTATUSEX	ms_ex;
	MEMORYSTATUS	ms;
	zbx_uint64_t	ullTotalVirtual, ullAvailVirtual;
	char		*mode;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	mode = get_rparam(request, 0);

	if (NULL != zbx_GlobalMemoryStatusEx)
	{
		ms_ex.dwLength = sizeof(MEMORYSTATUSEX);

		zbx_GlobalMemoryStatusEx(&ms_ex);

		ullTotalVirtual = ms_ex.ullTotalVirtual;
		ullAvailVirtual = ms_ex.ullAvailVirtual;
	}
	else
	{
		GlobalMemoryStatus(&ms);

		ullTotalVirtual = ms.dwTotalVirtual;
		ullAvailVirtual = ms.dwAvailVirtual;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, ullTotalVirtual);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, ullTotalVirtual - ullAvailVirtual);
	else if (0 == strcmp(mode, "available"))
		SET_UI64_RESULT(result, ullAvailVirtual);
	else if (0 == strcmp(mode, "pavailable"))
		SET_DBL_RESULT(result, (ullAvailVirtual / (double)ullTotalVirtual) * 100.0);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, (double)(ullTotalVirtual - ullAvailVirtual) / ullTotalVirtual * 100);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	MEMORYSTATUSEX	ms_ex;
	MEMORYSTATUS	ms;
	zbx_uint64_t	real_swap_total, real_swap_avail;
	char		*swapdev, *mode;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	/* only 'all' parameter supported */
	if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	/***************************************************************************
	 *                                                                         *
	 * Due to the way Windows API functions report memory metrics,             *
	 * it is impossible to accurately retrieve swap (virtual memory)           *
	 * sizes as Windows reports the total commit memory which is physical      *
	 * memory plus swap file sizes. The only resolution that could be applied  *
	 * was calculatively deducting the swap sizes knowing the page and         *
	 * physical memory sizes.                                                  *
	 *                                                                         *
	 * While developing this solution, it was found that sometimes (in         *
	 * virtualized environments or when page file is disabled) the calculated  *
	 * swap values are outside of possible bounds. For example, the available  *
	 * swap size may appear to be larger than the total swap memory size of    *
	 * the system, or even the calculated swap sizes may result in negative    *
	 * values (in system with disabled page file).                             *
	 *                                                                         *
	 * Taking these fallacious conditions into account, these calculations     *
	 * guarantee that the available swap size is never larger than the total   *
	 * available (if it is reported as such, it is lowered to the total        *
	 * as there is a higher probability of that number staying consistent      *
	 * than the other way around). In case of swap size calculations resulting *
	 * in negative values, the corresponding values are set to 0.              *
	 *                                                                         *
	 * As the result the returned values might not be exactly accurate         *
	 * depending on the system and environment, but they ought to be close     *
	 * enough.                                                                 *
	 *                                                                         *
	 * NB: The reason why GlobalMemoryStatus[Ex] are used is their             *
	 * availability on Windows 2000 and later, as opposed to other functions   *
	 * of a similar nature (like GetPerformanceInfo) that are not supported    *
	 * on some versions of Windows.                                            *
	 *                                                                         *
	 ***************************************************************************/

	if (NULL != zbx_GlobalMemoryStatusEx)
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
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, (real_swap_avail / (double)real_swap_total) * 100.0);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, real_swap_total - real_swap_avail);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}
