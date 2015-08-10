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

static int		mib[] = {CTL_VM, VM_UVMEXP};
static size_t		len;
static struct uvmexp	uvm;

#define ZBX_SYSCTL(value)				\
							\
	len = sizeof(value);				\
	if (0 != sysctl(mib, 2, &value, &len, NULL, 0))	\
		return SYSINFO_RET_FAIL

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (zbx_uint64_t)uvm.npages * uvm.pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_ACTIVE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (zbx_uint64_t)uvm.active * uvm.pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_INACTIVE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (zbx_uint64_t)uvm.inactive * uvm.pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_WIRED(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (zbx_uint64_t)uvm.wired * uvm.pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (zbx_uint64_t)uvm.free * uvm.pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (zbx_uint64_t)(uvm.active + uvm.wired) * uvm.pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	if (0 == uvm.npages)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (zbx_uint64_t)(uvm.active + uvm.wired) / (double)uvm.npages * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	SET_UI64_RESULT(result, (zbx_uint64_t)(uvm.inactive + uvm.free + uvm.vnodepages + uvm.vtextpages) * uvm.pagesize);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	ZBX_SYSCTL(uvm);

	if (0 == uvm.npages)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (zbx_uint64_t)(uvm.inactive + uvm.free + uvm.vnodepages + uvm.vtextpages) / (double)uvm.npages * 100);

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

	SET_UI64_RESULT(result, (zbx_uint64_t)(uvm.vnodepages + uvm.vtextpages) * uvm.pagesize);

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

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
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
		{"buffers",	VM_MEMORY_BUFFERS},
		{"cached",	VM_MEMORY_CACHED},
		{"shared",	VM_MEMORY_SHARED},
		{NULL,		0}
	};

	char    mode[MAX_STRING_LEN];
	int 	i;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(result);

	return SYSINFO_RET_FAIL;
}
