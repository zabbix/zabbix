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

static u_int	pagesize = 0;

#define ZBX_SYSCTLBYNAME(name, value)				\
								\
	len = sizeof(value);					\
	if (0 != sysctlbyname(name, &value, &len, NULL, 0))	\
		return SYSINFO_RET_FAIL

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	unsigned long	totalbytes;
	size_t		len;

	ZBX_SYSCTLBYNAME("hw.physmem", totalbytes);

	SET_UI64_RESULT(result, (zbx_uint64_t)totalbytes);

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
	u_int	activepages, wiredpages, cachedpages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_active_count", activepages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_wire_count", wiredpages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_cache_count", cachedpages);

	SET_UI64_RESULT(result, (zbx_uint64_t)(activepages + wiredpages + cachedpages) * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	u_int	activepages, wiredpages, cachedpages, totalpages;
	size_t	len;

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_active_count", activepages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_wire_count", wiredpages);
	ZBX_SYSCTLBYNAME("vm.stats.vm.v_cache_count", cachedpages);

	ZBX_SYSCTLBYNAME("vm.stats.vm.v_page_count", totalpages);

	if (0 == totalpages)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (activepages + wiredpages + cachedpages) / (double)totalpages * 100);

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

	SET_DBL_RESULT(result, (inactivepages + cachedpages + freepages) / (double)totalpages * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_BUFFERS(AGENT_RESULT *result)
{
	u_int	bufspace;
	size_t	len;

	ZBX_SYSCTLBYNAME("vfs.bufspace", bufspace);

	SET_UI64_RESULT(result, bufspace);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_SHARED(AGENT_RESULT *result)
{
	struct vmtotal	vm;
	size_t		len = sizeof(vm);
	int		mib[] = {CTL_VM, VM_METER};

	if (0 != sysctl(mib, 2, &vm, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)(vm.t_vmshr + vm.t_rmshr) * pagesize);

	return SYSINFO_RET_OK;
}

int     VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	if (0 == pagesize)
	{
		size_t	len;

		ZBX_SYSCTLBYNAME("vm.stats.vm.v_page_size", pagesize);
	}

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		VM_MEMORY_TOTAL(result);
	else if (0 == strcmp(mode, "active"))
		VM_MEMORY_ACTIVE(result);
	else if (0 == strcmp(mode, "inactive"))
		VM_MEMORY_INACTIVE(result);
	else if (0 == strcmp(mode, "wired"))
		VM_MEMORY_WIRED(result);
	else if (0 == strcmp(mode, "cached"))
		VM_MEMORY_CACHED(result);
	else if (0 == strcmp(mode, "free"))
		VM_MEMORY_FREE(result);
	else if (0 == strcmp(mode, "used"))
		VM_MEMORY_USED(result);
	else if (0 == strcmp(mode, "pused"))
		VM_MEMORY_PUSED(result);
	else if (0 == strcmp(mode, "available"))
		VM_MEMORY_AVAILABLE(result);
	else if (0 == strcmp(mode, "pavailable"))
		VM_MEMORY_PAVAILABLE(result);
	else if (0 == strcmp(mode, "buffers"))
		VM_MEMORY_BUFFERS(result);
	else if (0 == strcmp(mode, "shared"))
		VM_MEMORY_SHARED(result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}
