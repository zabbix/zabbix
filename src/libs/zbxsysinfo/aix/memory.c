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

#ifdef HAVE_LIBPERFSTAT

static perfstat_memory_total_t	m;

#define ZBX_PERFSTAT_PAGE_SHIFT	12	/* 4 KB */

#define ZBX_PERFSTAT_MEMORY_TOTAL()									\
													\
	if (-1 == perfstat_memory_total(NULL, &m, sizeof(m), 1))					\
	{												\
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s",	\
				zbx_strerror(errno)));							\
		return SYSINFO_RET_FAIL;								\
	}

static int	vm_memory_total(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	/* total real memory in pages */
	SET_UI64_RESULT(result, m.real_total << ZBX_PERFSTAT_PAGE_SHIFT);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pinned(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	/* real memory which is pinned in pages */
	SET_UI64_RESULT(result, m.real_pinned << ZBX_PERFSTAT_PAGE_SHIFT);

	return SYSINFO_RET_OK;
}

static int	vm_memory_free(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	/* free real memory in pages */
	SET_UI64_RESULT(result, m.real_free << ZBX_PERFSTAT_PAGE_SHIFT);

	return SYSINFO_RET_OK;
}

static int	vm_memory_used(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	/* real memory which is in use in pages */
	SET_UI64_RESULT(result, m.real_inuse << ZBX_PERFSTAT_PAGE_SHIFT);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pused(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	if (0 == m.real_total)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, m.real_inuse / (double)m.real_total * 100);

	return SYSINFO_RET_OK;
}

static int	vm_memory_available(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	SET_UI64_RESULT(result, (m.real_free + m.numperm) << ZBX_PERFSTAT_PAGE_SHIFT);

	return SYSINFO_RET_OK;
}

static int	vm_memory_pavailable(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	if (0 == m.real_total)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, (m.real_free + m.numperm) / (double)m.real_total * 100);

	return SYSINFO_RET_OK;
}

static int	vm_memory_cached(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	SET_UI64_RESULT(result, m.numperm << ZBX_PERFSTAT_PAGE_SHIFT);	/* number of pages used for files */

	return SYSINFO_RET_OK;
}

#endif

int	vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_LIBPERFSTAT
	int	ret;
	char	*mode;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		ret = vm_memory_total(result);
	else if (0 == strcmp(mode, "pinned"))
		ret = vm_memory_pinned(result);
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
	else if (0 == strcmp(mode, "cached"))
		ret = vm_memory_cached(result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif
}
