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

static int	get_vmmemory_stat(zbx_uint64_t *total, zbx_uint64_t *free, zbx_uint64_t *used, zbx_uint64_t *shared, double *pfree, double *pused)
{
#if defined(HAVE_SYS_VMMETER_VMTOTAL)
	int		mib[] = {CTL_VM, VM_METER};
	struct vmtotal	v;
	size_t		len;

	len = sizeof(v);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	if (total)
		*total = (zbx_uint64_t)(v.t_rm + v.t_free) * sysconf(_SC_PAGESIZE);
	if (free)
		*free = (zbx_uint64_t)v.t_free * sysconf(_SC_PAGESIZE);
	if (used)
		*used = (zbx_uint64_t)v.t_rm * sysconf(_SC_PAGESIZE);
	if (shared)
		*shared = (zbx_uint64_t)(v.t_vmshr + v.t_rmshr) * sysconf(_SC_PAGESIZE);
	if (pfree)
		*pfree = (double)(100.0 * v.t_free) / (v.t_rm + v.t_free);
	if (pused)
		*pused = (double)(100.0 * v.t_rm) / (v.t_rm + v.t_free);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_SYS_VMMETER_VMTOTAL */
}

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_vmmemory_stat(&value, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_vmmemory_stat(NULL, &value, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_vmmemory_stat(NULL, NULL, &value, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_SHARED(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_vmmemory_stat(NULL, NULL, NULL, &value, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PFREE(AGENT_RESULT *result)
{
	double	value;

	if (SYSINFO_RET_OK != get_vmmemory_stat(NULL, NULL, NULL, NULL, &value, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	double	value;

	if (SYSINFO_RET_OK != get_vmmemory_stat(NULL, NULL, NULL, NULL, NULL, &value))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_BUFFERS(AGENT_RESULT *result)
{
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

static int	VM_MEMORY_CACHED(AGENT_RESULT *result)
{
	int		mib[] = {CTL_VM, VM_UVMEXP};
	struct uvmexp	v;
	size_t		len;
	zbx_uint64_t	value;

	len = sizeof(v);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	value = (zbx_uint64_t)(v.vnodepages + v.vtextpages) * v.pagesize;

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
		{"shared",	VM_MEMORY_SHARED},
		{"pfree",	VM_MEMORY_PFREE},
		{"pused",	VM_MEMORY_PUSED},
		{"buffers",	VM_MEMORY_BUFFERS},
		{"cached",	VM_MEMORY_CACHED},
		{0,		0}
	};
	char    mode[MAX_STRING_LEN];
	int 	i;

	if(num_param(param) > 1)
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
