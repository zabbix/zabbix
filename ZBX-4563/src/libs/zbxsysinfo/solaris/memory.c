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

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, (zbx_uint64_t)sysconf(_SC_PHYS_PAGES) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, (zbx_uint64_t)sysconf(_SC_AVPHYS_PAGES) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	zbx_uint64_t	used;

	used = sysconf(_SC_PHYS_PAGES) - sysconf(_SC_AVPHYS_PAGES);

	SET_UI64_RESULT(result, used * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	zbx_uint64_t	used, total;

	if (0 == (total = sysconf(_SC_PHYS_PAGES)))
		return SYSINFO_RET_FAIL;

	used = total - sysconf(_SC_AVPHYS_PAGES);

	SET_DBL_RESULT(result, used / (double)total * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, sysconf(_SC_AVPHYS_PAGES) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	zbx_uint64_t	total;

	if (0 == (total = sysconf(_SC_PHYS_PAGES)))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, sysconf(_SC_AVPHYS_PAGES) / (double)total * 100);

	return SYSINFO_RET_OK;
}

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"total",	VM_MEMORY_TOTAL},
		{"free",	VM_MEMORY_FREE},
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

	return SYSINFO_RET_FAIL;
}
