/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#ifdef HAVE_SYS_PSTAT_H

struct pst_static	pst;
struct pst_dynamic	pdy;

#define ZBX_PSTAT_GETSTATIC()					\
								\
	if (-1 == pstat_getstatic(&pst, sizeof(pst), 1, 0))	\
		return SYSINFO_RET_FAIL

#define ZBX_PSTAT_GETDYNAMIC()					\
								\
	if (-1 == pstat_getdynamic(&pdy, sizeof(pdy), 1, 0))	\
		return SYSINFO_RET_FAIL

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
		return SYSINFO_RET_FAIL;

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
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, pdy.psd_free / (double)pst.physical_memory * 100);

	return SYSINFO_RET_OK;
}

#endif

int	VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYS_PSTAT_H
	const MODE_FUNCTION	fl[] =
	{
		{"total",	VM_MEMORY_TOTAL},
		{"free",	VM_MEMORY_FREE},
		{"active",	VM_MEMORY_ACTIVE},
		{"used",	VM_MEMORY_USED},
		{"pused",	VM_MEMORY_PUSED},
		{"available",	VM_MEMORY_AVAILABLE},
		{"pavailable",	VM_MEMORY_PAVAILABLE},
		{NULL,		0}
	};

	char	mode[MAX_STRING_LEN];
	int	i;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(result);
#endif
	return SYSINFO_RET_FAIL;
}
