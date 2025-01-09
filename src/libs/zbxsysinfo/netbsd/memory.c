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

#include <uvm/uvm_extern.h>

static int			mib[] = {CTL_VM, VM_UVMEXP2};
static size_t			len;
static struct uvmexp_sysctl	uvm;

#define ZBX_SYSCTL(value)										\
													\
	len = sizeof(value);										\
	if (0 != sysctl(mib, 2, &value, &len, NULL, 0))							\
	{												\
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s",	\
				zbx_strerror(errno)));							\
		return SYSINFO_RET_FAIL;								\
	}

static int	vm_memory_total(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.npages << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	vm_memory_active(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.active << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	vm_memory_inactive(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.inactive << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	vm_memory_wired(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.wired << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_ANON(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.anonpages << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_EXEC(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.execpages << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FILE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.filepages << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	vm_memory_free(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.free << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	vm_memory_used(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (uvm.npages - uvm.free) << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pused(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	if (0 == uvm.npages)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, (uvm.npages - uvm.free) / (double)uvm.npages * 100);

	return SYSINFO_RET_OK;
}

static int	vm_memory_available(AGENT_RESULT *result)
{
	zbx_uint64_t	available;

	ZBX_SYSCTL(uvm);

	available = uvm.inactive + uvm.execpages + uvm.filepages + uvm.free;

	SET_UI64_RESULT(result, available << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pavailable(AGENT_RESULT *result)
{
	zbx_uint64_t	available;

	ZBX_SYSCTL(uvm);

	if (0 == uvm.npages)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	available = uvm.inactive + uvm.execpages + uvm.filepages + uvm.free;

	SET_DBL_RESULT(result, available / (double)uvm.npages * 100);

	return SYSINFO_RET_OK;
}

static int	vm_memory_buffers(AGENT_RESULT *result)
{
	int	mib[] = {CTL_VM, VM_NKMEMPAGES}, pages;

	ZBX_SYSCTL(pages);

	SET_UI64_RESULT(result, (zbx_uint64_t)pages * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	vm_memory_cached(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (uvm.execpages + uvm.filepages) << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	vm_memory_shared(AGENT_RESULT *result)
{
	int		mib[] = {CTL_VM, VM_METER};
	struct vmtotal	vm;

	ZBX_SYSCTL(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)(vm.t_vmshr + vm.t_rmshr) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

int     vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
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
	else if (0 == strcmp(mode, "anon"))
		ret = VM_MEMORY_ANON(result);
	else if (0 == strcmp(mode, "exec"))
		ret = VM_MEMORY_EXEC(result);
	else if (0 == strcmp(mode, "file"))
		ret = VM_MEMORY_FILE(result);
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
	else if (0 == strcmp(mode, "buffers"))
		ret = vm_memory_buffers(result);
	else if (0 == strcmp(mode, "cached"))
		ret = vm_memory_cached(result);
	else if (0 == strcmp(mode, "shared"))
		ret = vm_memory_shared(result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		ret = SYSINFO_RET_FAIL;
	}

	return ret;
}
