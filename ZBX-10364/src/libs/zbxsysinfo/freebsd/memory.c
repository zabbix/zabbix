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

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
#if defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	u_int		totalpages, pagesize;
	size_t		len;

	len = sizeof(totalpages);

	if (0 != sysctlbyname("vm.stats.vm.v_page_count", &totalpages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	len = sizeof(pagesize);

	if (0 != sysctlbyname("vm.stats.vm.v_page_size", &pagesize, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)totalpages * pagesize);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTLBYNAME */
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
#if defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	u_int		freepages, pagesize;
	size_t		len;

	len = sizeof(freepages);

	if (0 != sysctlbyname("vm.stats.vm.v_free_count", &freepages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	len = sizeof(pagesize);

	if (0 != sysctlbyname("vm.stats.vm.v_page_size", &pagesize, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)freepages * pagesize);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTLBYNAME */
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
#if defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	u_int		totalpages, freepages, pagesize;
	size_t		len;

	len = sizeof(totalpages);

	if (0 != sysctlbyname("vm.stats.vm.v_page_count", &totalpages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	len = sizeof(freepages);

	if (0 != sysctlbyname("vm.stats.vm.v_free_count", &freepages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	len = sizeof(pagesize);

	if (0 != sysctlbyname("vm.stats.vm.v_page_size", &pagesize, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)(totalpages - freepages) * pagesize);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTLBYNAME */
}

static int	VM_MEMORY_PFREE(AGENT_RESULT *result)
{
#if defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	u_int		totalpages, freepages;
	size_t		len;

	len = sizeof(totalpages);

	if (0 != sysctlbyname("vm.stats.vm.v_page_count", &totalpages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	len = sizeof(freepages);

	if (0 != sysctlbyname("vm.stats.vm.v_free_count", &freepages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (double)(100.0 * freepages) / totalpages);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTLBYNAME */
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
#if defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	u_int		totalpages, freepages;
	size_t		len;

	len = sizeof(totalpages);

	if (0 != sysctlbyname("vm.stats.vm.v_page_count", &totalpages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	len = sizeof(freepages);

	if (0 != sysctlbyname("vm.stats.vm.v_free_count", &freepages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (double)(100.0 * (totalpages - freepages)) / totalpages);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTLBYNAME */
}

static int	VM_MEMORY_CACHED(AGENT_RESULT *result)
{
#if defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	u_int		cachepages, pagesize;
	size_t		len;

	len = sizeof(cachepages);

	if (0 != sysctlbyname("vm.stats.vm.v_cache_count", &cachepages, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	len = sizeof(pagesize);

	if (0 != sysctlbyname("vm.stats.vm.v_page_size", &pagesize, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)cachepages * pagesize);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_FUNCTION_SYSCTLBYNAME */
}

static int	VM_MEMORY_SHARED(AGENT_RESULT *result)
{
#if defined(HAVE_SYS_VMMETER_VMTOTAL)
	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	int	mib[] = {CTL_VM, VM_METER};
	size_t	len;
	struct	vmtotal v;

	len = sizeof(v);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, (zbx_uint64_t)(v.t_vmshr + v.t_rmshr) * sysconf(_SC_PAGESIZE));

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
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
