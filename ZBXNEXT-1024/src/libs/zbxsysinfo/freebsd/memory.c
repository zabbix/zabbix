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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"

static u_int	pagesize = 0;

#define ZBX_SYSCTLBYNAME(name, value)				\
								\
	len = sizeof(value);					\
	if (0 != sysctlbyname(name, &value, &len, NULL, 0))	\
		return SYSINFO_RET_FAIL

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	u_int	totalpages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_page_count", totalpages);

	SET_UI64_RESULT(result, (zbx_uint64_t)totalpages * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_ACTIVE(AGENT_RESULT *result)
{
	u_int	activepages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_active_count", activepages);

	SET_UI64_RESULT(result, (zbx_uint64_t)activepages * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_INACTIVE(AGENT_RESULT *result)
{
	u_int	inactivepages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_inactive_count", inactivepages);

	SET_UI64_RESULT(result, (zbx_uint64_t)inactivepages * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_WIRED(AGENT_RESULT *result)
{
	u_int	wiredpages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_wire_count", wiredpages);

	SET_UI64_RESULT(result, (zbx_uint64_t)wiredpages * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_CACHED(AGENT_RESULT *result)
{
	u_int	cachedpages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_cache_count", cachedpages);

	SET_UI64_RESULT(result, (zbx_uint64_t)cachedpages * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	u_int	freepages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_free_count", freepages);

	SET_UI64_RESULT(result, (zbx_uint64_t)freepages * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	u_int	activepages, wiredpages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_active_count", activepages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_wire_count", wiredpages);

	SET_UI64_RESULT(result, (zbx_uint64_t)(activepages + wiredpages) * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	u_int	activepages, wiredpages, totalpages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_active_count", activepages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_wire_count", wiredpages);

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_page_count", totalpages);

	if (0 == totalpages)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, 100.0 * (activepages + wiredpages) / totalpages);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	u_int	inactivepages, cachedpages, freepages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_inactive_count", inactivepages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_cache_count", cachedpages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_free_count", freepages);

	SET_UI64_RESULT(result, (zbx_uint64_t)(inactivepages + cachedpages + freepages) * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	u_int	inactivepages, cachedpages, freepages, totalpages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_inactive_count", inactivepages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_cache_count", cachedpages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_free_count", freepages);

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_page_count", totalpages);

	if (0 == totalpages)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, 100.0 * (inactivepages + cachedpages + freepages) / totalpages);

	return SYSINFO_RET_OK;
}

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"total",	VM_MEMORY_TOTAL},
		{"active",	VM_MEMORY_ACTIVE},
		{"inactive",	VM_MEMORY_INACTIVE},
		{"wired",	VM_MEMORY_WIRED},
		{"cached",	VM_MEMORY_CACHED},
		{"free",	VM_MEMORY_FREE},
		{"used",	VM_MEMORY_USED},
		{"pused",	VM_MEMORY_PUSED},
		{"available",	VM_MEMORY_AVAILABLE},
		{"pavailable",	VM_MEMORY_PAVAILABLE},
		{NULL,		0}
	};

	int	i;
	char	mode[MAX_STRING_LEN];

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 == pagesize)
	{
		size_t	len;

		ZBX_SYSCTLBYNAME("vm.stats.vm.v_page_size", pagesize);
	}

	if (0 != get_param(param, 1, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(result);

	return SYSINFO_RET_FAIL;
}
