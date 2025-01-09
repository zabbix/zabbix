/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxsysinfo.h"
#include "../sysinfo.h"

static vm_size_t	pagesize = 0;

static struct vm_statistics	vm;
static mach_msg_type_number_t	count;

#define ZBX_HOST_STATISTICS(value)										\
														\
	count = HOST_VM_INFO_COUNT;										\
	if (KERN_SUCCESS != host_statistics(mach_host_self(), HOST_VM_INFO, (host_info_t)&value, &count))	\
	{													\
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain host statistics."));			\
		return SYSINFO_RET_FAIL;									\
	}

static int		mib[] = {CTL_HW, HW_MEMSIZE};
static size_t		len;
static zbx_uint64_t	memsize;

#define ZBX_SYSCTL(value)											\
														\
	len = sizeof(value);											\
	if (0 != sysctl(mib, 2, &value, &len, NULL, 0))								\
	{													\
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s",		\
				zbx_strerror(errno)));								\
		return SYSINFO_RET_FAIL;									\
	}

static int	vm_memory_total(AGENT_RESULT *result)
{
	ZBX_SYSCTL(memsize);

	SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

static int	vm_memory_active(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)vm.active_count * pagesize);

	return SYSINFO_RET_OK;
}

static int	vm_memory_inactive(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)vm.inactive_count * pagesize);

	return SYSINFO_RET_OK;
}

static int	vm_memory_wired(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)vm.wire_count * pagesize);

	return SYSINFO_RET_OK;
}

static int	vm_memory_free(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)vm.free_count * pagesize);

	return SYSINFO_RET_OK;
}

static int	vm_memory_used(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)(vm.active_count + vm.wire_count) * pagesize);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pused(AGENT_RESULT *result)
{
	zbx_uint64_t	used;

	ZBX_SYSCTL(memsize);

	if (0 == memsize)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	ZBX_HOST_STATISTICS(vm);

	used = (zbx_uint64_t)(vm.active_count + vm.wire_count) * pagesize;

	SET_DBL_RESULT(result, used / (double)memsize * 100);

	return SYSINFO_RET_OK;
}

static int	vm_memory_available(AGENT_RESULT *result)
{
	ZBX_HOST_STATISTICS(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)(vm.inactive_count + vm.free_count) * pagesize);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pavailable(AGENT_RESULT *result)
{
	zbx_uint64_t	available;

	ZBX_SYSCTL(memsize);

	if (0 == memsize)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	ZBX_HOST_STATISTICS(vm);

	available = (zbx_uint64_t)(vm.inactive_count + vm.free_count) * pagesize;

	SET_DBL_RESULT(result, available / (double)memsize * 100);

	return SYSINFO_RET_OK;
}

int	vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (0 == pagesize)
	{
		if (KERN_SUCCESS != host_page_size(mach_host_self(), &pagesize))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain host page size."));
			return SYSINFO_RET_FAIL;
		}
	}

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		ret = vm_memory_total(result);
	else if (0 == strcmp(mode, "active"))
		ret = vm_memory_active(result);
	else if (0 == strcmp(mode, "inactive"))
		ret = vm_memory_inactive(result);
	else if (0 == strcmp(mode, "wired"))
		ret = vm_memory_wired(result);
	else if (0 == strcmp(mode, "free"))
		ret = vm_memory_free(result);
	else if (0 == strcmp(mode, "used"))
		ret = vm_memory_used(result);
	else if (0 == strcmp(mode, "pused"))
		ret = vm_memory_pused(result);
	else if (0 == strcmp(mode, "available"))
		ret = vm_memory_available(result);
	else if (0 == strcmp(mode, "pavailable"))
		ret = vm_memory_pavailable(result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}
