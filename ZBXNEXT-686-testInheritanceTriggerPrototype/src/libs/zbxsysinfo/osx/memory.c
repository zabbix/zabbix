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

static vm_size_t	pagesize = 0;

static struct vm_statistics	vm;
static mach_msg_type_number_t	count;

#define ZBX_HOST_STATISTICS(value)										\
														\
	count = HOST_VM_INFO_COUNT;										\
	if (KERN_SUCCESS != host_statistics(mach_host_self(), HOST_VM_INFO, (host_info_t)&value, &count))	\
		return SYSINFO_RET_FAIL

static int		mib[] = {CTL_HW, HW_MEMSIZE};
static size_t		len;
static zbx_uint64_t	memsize;

#define ZBX_SYSCTL(value)											\
														\
	len = sizeof(value);											\
	if (0 != sysctl(mib, 2, &value, &len, NULL, 0))								\
		return SYSINFO_RET_FAIL

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	ZBX_SYSCTL(memsize);

	SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_ACTIVE(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)vm.active_count * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_INACTIVE(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)vm.inactive_count * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_WIRED(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)vm.wire_count * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)vm.free_count * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)(vm.active_count + vm.wire_count) * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	zbx_uint64_t	used;

	ZBX_SYSCTL(memsize);

	if (0 == memsize)
		return SYSINFO_RET_FAIL;

	ZBX_HOST_STATISTICS(vm);

	used = (zbx_uint64_t)(vm.active_count + vm.wire_count) * pagesize;

	SET_DBL_RESULT(result, used / (double)memsize * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)(vm.inactive_count + vm.free_count) * pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	zbx_uint64_t	available;

	ZBX_SYSCTL(memsize);

	if (0 == memsize)
		return SYSINFO_RET_FAIL;

	ZBX_HOST_STATISTICS(vm);

	available = (zbx_uint64_t)(vm.inactive_count + vm.free_count) * pagesize;

	SET_DBL_RESULT(result, available / (double)memsize * 100);

	return SYSINFO_RET_OK;
}

int	VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	if (0 == pagesize)
	{
		if (KERN_SUCCESS != host_page_size(mach_host_self(), &pagesize))
			return SYSINFO_RET_FAIL;
	}

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		ret = VM_MEMORY_TOTAL(mode);
	else if (0 == strcmp(mode, "active"))
		ret = VM_MEMORY_ACTIVE(mode);
	else if (0 == strcmp(mode, "inactive"))
		ret = VM_MEMORY_INACTIVE(mode);
	else if (0 == strcmp(mode, "wired"))
		ret = VM_MEMORY_WIRED(mode);
	else if (0 == strcmp(mode, "free"))
		ret = VM_MEMORY_FREE(mode);
	else if (0 == strcmp(mode, "used"))
		ret = VM_MEMORY_USED(mode);
	else if (0 == strcmp(mode, "pused"))
		ret = VM_MEMORY_PUSED(mode);
	else if (0 == strcmp(mode, "available"))
		ret = VM_MEMORY_AVAILABLE(mode);
	else if (0 == strcmp(mode, "pavailable"))
		ret = VM_MEMORY_PAVAILABLE(mode);

	return ret;
}
