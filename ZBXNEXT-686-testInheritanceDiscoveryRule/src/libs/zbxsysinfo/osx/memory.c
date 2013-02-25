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

int	VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"total",	VM_MEMORY_TOTAL},
		{"active",	VM_MEMORY_ACTIVE},
		{"inactive",	VM_MEMORY_INACTIVE},
		{"wired",	VM_MEMORY_WIRED},
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

	if (0 == pagesize)
	{
		if (KERN_SUCCESS != host_page_size(mach_host_self(), &pagesize))
			return SYSINFO_RET_FAIL;
	}

	if (0 != get_param(param, 1, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(result);

	return SYSINFO_RET_FAIL;
}
