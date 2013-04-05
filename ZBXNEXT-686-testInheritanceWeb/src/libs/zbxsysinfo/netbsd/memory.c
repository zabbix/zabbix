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

static int			mib[] = {CTL_VM, VM_UVMEXP2};
static size_t			len;
static struct uvmexp_sysctl	uvm;

#define ZBX_SYSCTL(value)				\
							\
	len = sizeof(value);				\
	if (0 != sysctl(mib, 2, &value, &len, NULL, 0))	\
		return SYSINFO_RET_FAIL

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.npages << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_ACTIVE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.active << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_INACTIVE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.inactive << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_WIRED(AGENT_RESULT *result)
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

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, uvm.free << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (uvm.npages - uvm.free) << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	if (0 == uvm.npages)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (uvm.npages - uvm.free) / (double)uvm.npages * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	zbx_uint64_t	available;

	ZBX_SYSCTL(uvm);

	available = uvm.inactive + uvm.execpages + uvm.filepages + uvm.free;

	SET_UI64_RESULT(result, available << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	zbx_uint64_t	available;

	ZBX_SYSCTL(uvm);

	if (0 == uvm.npages)
		return SYSINFO_RET_FAIL;

	available = uvm.inactive + uvm.execpages + uvm.filepages + uvm.free;

	SET_DBL_RESULT(result, available / (double)uvm.npages * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_BUFFERS(AGENT_RESULT *result)
{
	int	mib[] = {CTL_VM, VM_NKMEMPAGES}, pages;

	ZBX_SYSCTL(pages);

	SET_UI64_RESULT(result, (zbx_uint64_t)pages * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_CACHED(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (uvm.execpages + uvm.filepages) << uvm.pageshift);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_SHARED(AGENT_RESULT *result)
{
	int		mib[] = {CTL_VM, VM_METER};
	struct vmtotal	vm;

	ZBX_SYSCTL(vm);

	SET_UI64_RESULT(result, (zbx_uint64_t)(vm.t_vmshr + vm.t_rmshr) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
}

int     VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		ret = VM_MEMORY_TOTAL(result);
	else if (0 == strcmp(mode, "active"))
		ret = VM_MEMORY_ACTIVE(result);
	else if (0 == strcmp(mode, "inactive"))
		ret = VM_MEMORY_INACTIVE(result);
	else if (0 == strcmp(mode, "wired"))
		ret = VM_MEMORY_WIRED(result);
	else if (0 == strcmp(mode, "anon"))
		ret = VM_MEMORY_ANON(result);
	else if (0 == strcmp(mode, "exec"))
		ret = VM_MEMORY_EXEC(result);
	else if (0 == strcmp(mode, "file"))
		ret = VM_MEMORY_FILE(result);
	else if (0 == strcmp(mode, "free"))
		ret = VM_MEMORY_FREE(result);
	else if (0 == strcmp(mode, "used"))
		ret = VM_MEMORY_USED(result);
	else if (0 == strcmp(mode, "pused"))
		ret = VM_MEMORY_PUSED(result);
	else if (0 == strcmp(mode, "available"))
		ret = VM_MEMORY_AVAILABLE(result);
	else if (0 == strcmp(mode, "pavailable"))
		ret = VM_MEMORY_PAVAILABLE(result);
	else if (0 == strcmp(mode, "buffers"))
		ret = VM_MEMORY_BUFFERS(result);
	else if (0 == strcmp(mode, "cached"))
		ret = VM_MEMORY_CACHED(result);
	else if (0 == strcmp(mode, "shared"))
		ret = VM_MEMORY_SHARED(result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}
