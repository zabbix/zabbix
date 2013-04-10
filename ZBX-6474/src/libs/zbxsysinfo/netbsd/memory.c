/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

static int	get_vm_stat(zbx_uint64_t *total, zbx_uint64_t *free, zbx_uint64_t *used, double *pfree, double *pused, zbx_uint64_t *cached)
{
#if defined(HAVE_UVM_UVMEXP2)
	/* NetBSD 3.1 i386; NetBSD 4.0 i386 */
	int			mib[] = {CTL_VM, VM_UVMEXP2};
	size_t			len;
	struct uvmexp_sysctl	v;

	len = sizeof(struct uvmexp_sysctl);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	if (total)
		*total = v.npages << v.pageshift;
	if (free)
		*free = v.free << v.pageshift;
	if (used)
		*used = (v.npages << v.pageshift) - (v.free << v.pageshift);
	if (pfree)
		*pfree = (double)(100.0 * v.free) / v.npages;
	if (pused)
		*pused = (double)(100.0 * (v.npages - v.free)) / v.npages;
	if (cached)
		*cached = (v.filepages << v.pageshift) + (v.execpages << v.pageshift);

	return	SYSINFO_RET_OK;
#else
	return	SYSINFO_RET_FAIL;
#endif /* HAVE_UVM_UVMEXP2 */
}

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_vm_stat(&value, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_vm_stat(NULL, &value, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_vm_stat(NULL, NULL, &value, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PFREE(AGENT_RESULT *result)
{
	double	value = 0;

	if (SYSINFO_RET_OK != get_vm_stat(NULL, NULL, NULL, &value, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	double	value = 0;

	if (SYSINFO_RET_OK != get_vm_stat(NULL, NULL, NULL, NULL, &value, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_BUFFERS(AGENT_RESULT *result)
{
	/* NetBSD 3.1 i386; NetBSD 4.0 i386 */
	int		mib[] = {CTL_VM, VM_NKMEMPAGES}, pages;
	size_t		len;
	zbx_uint64_t	value;

	len = sizeof(pages);

	if (0 != sysctl(mib, 2, &pages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	value = (zbx_uint64_t)pages * sysconf(_SC_PAGESIZE);

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_SHARED(AGENT_RESULT *result)
{
#if defined(HAVE_SYS_VMMETER_VMTOTAL)
	/* NetBSD 3.1 i386; NetBSD 4.0 i386 */
	int		mib[] = {CTL_VM, VM_METER};
	struct vmtotal	v;
	size_t		len;
	zbx_uint64_t	value;

	len = sizeof(v);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	value = (zbx_uint64_t)(v.t_vmshr + v.t_rmshr) * sysconf(_SC_PAGESIZE);

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_SYS_VMMETER_VMTOTAL */
}

static int	VM_MEMORY_CACHED(AGENT_RESULT *result)
{
	/* NetBSD 3.1 i386; NetBSD 4.0 i386 */
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_vm_stat(NULL, NULL, NULL, NULL, NULL, &value))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"total",	VM_MEMORY_TOTAL},
		{"free",	VM_MEMORY_FREE},
		{"used",	VM_MEMORY_USED},
		{"pfree",	VM_MEMORY_PFREE},
		{"pused",	VM_MEMORY_PUSED},
		{"shared",	VM_MEMORY_SHARED},
		{"buffers",	VM_MEMORY_BUFFERS},
		{"cached",	VM_MEMORY_CACHED},
		{0,		0}
	};

	char	mode[MAX_STRING_LEN];
	int	i;

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "total");

	for (i = 0; fl[i].mode != 0; i++)
		if (0 == strncmp(mode, fl[i].mode, MAX_STRING_LEN))
			return (fl[i].function)(result);

	return SYSINFO_RET_FAIL;
}
