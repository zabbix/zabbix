/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#define ZBX_PERFSTAT_PAGE_SHIFT	12	/* 4 KB */

int	SYSTEM_SWAP_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_LIBPERFSTAT
	perfstat_memory_total_t	mem;
	char			*swapdev, *mode;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
		return SYSINFO_RET_FAIL;

	if(1 != perfstat_memory_total(NULL, &mem, sizeof(perfstat_memory_total_t), 1))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, mem.pgsp_free << ZBX_PERFSTAT_PAGE_SHIFT);
	else if (0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, mem.pgsp_total << ZBX_PERFSTAT_PAGE_SHIFT);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, (mem.pgsp_total - mem.pgsp_free) << ZBX_PERFSTAT_PAGE_SHIFT);
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, mem.pgsp_total ? 100.0 * (mem.pgsp_free / (double)mem.pgsp_total) : 0.0);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, mem.pgsp_total ? 100.0 - 100.0 * (mem.pgsp_free / (double)mem.pgsp_total) : 0.0);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
#endif
	return SYSINFO_RET_FAIL;
}
