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

#ifdef HAVE_LIBPERFSTAT

static perfstat_memory_total_t	m;

#define ZBX_PERFSTAT_PAGE_SHIFT	12	/* 4 KB */

#define ZBX_PERFSTAT_MEMORY_TOTAL()					\
									\
	if (-1 == perfstat_memory_total(NULL, &m, sizeof(m), 1))	\
		return SYSINFO_RET_FAIL

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	SET_UI64_RESULT(result, m.real_total << ZBX_PERFSTAT_PAGE_SHIFT);	/* total real memory in pages */

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PINNED(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	SET_UI64_RESULT(result, m.real_pinned << ZBX_PERFSTAT_PAGE_SHIFT);	/* real memory which is pinned in pages */

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	SET_UI64_RESULT(result, m.real_free << ZBX_PERFSTAT_PAGE_SHIFT);	/* free real memory in pages */

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	SET_UI64_RESULT(result, m.real_inuse << ZBX_PERFSTAT_PAGE_SHIFT);	/* real memory which is in use in pages */

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	if (0 == m.real_total)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, m.real_inuse / (double)m.real_total * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	SET_UI64_RESULT(result, m.real_free << ZBX_PERFSTAT_PAGE_SHIFT);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	if (0 == m.real_total)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, m.real_free / (double)m.real_total * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_CACHED(AGENT_RESULT *result)
{
	ZBX_PERFSTAT_MEMORY_TOTAL();

	SET_UI64_RESULT(result, m.numperm << ZBX_PERFSTAT_PAGE_SHIFT);	/* number of pages used for files */

	return SYSINFO_RET_OK;
}

#endif

int	VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_LIBPERFSTAT
	const MODE_FUNCTION	fl[] =
	{
		{"total",	VM_MEMORY_TOTAL},
		{"pinned",	VM_MEMORY_PINNED},
		{"free",	VM_MEMORY_FREE},
		{"used",	VM_MEMORY_USED},
		{"pused",	VM_MEMORY_PUSED},
		{"available",	VM_MEMORY_AVAILABLE},
		{"pavailable",	VM_MEMORY_PAVAILABLE},
		{"cached",	VM_MEMORY_CACHED},
		{NULL,		0}
	};

	char	mode[MAX_STRING_LEN];
	int	i;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(result);
#endif
	return SYSINFO_RET_FAIL;
}
