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

static int	get_swap_size(zbx_uint64_t *total, zbx_uint64_t *free, zbx_uint64_t *used, double *pfree, double *pused)
{
	int		mib[2];
	size_t		len;
	struct uvmexp	v;

	mib[0] = CTL_VM;
	mib[1] = VM_UVMEXP;

	len = sizeof(v);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	/* int pagesize;	size of a page (PAGE_SIZE): must be power of 2 */
	/* int swpages;		number of PAGE_SIZE'ed swap pages */
	/* int swpginuse;	number of swap pages in use */

	if (total)
		*total = (zbx_uint64_t)v.swpages * v.pagesize;
	if (free)
		*free = (zbx_uint64_t)(v.swpages - v.swpginuse) * v.pagesize;
	if (used)
		*used = (zbx_uint64_t)v.swpginuse * v.pagesize;
	if (pfree)
		*pfree = v.swpages ? (double)(100.0 * (v.swpages - v.swpginuse)) / v.swpages : 100;
	if (pused)
		*pused = v.swpages ? (double)(100.0 * v.swpginuse) / v.swpages : 0;

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_TOTAL(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_swap_size(&value, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_FREE(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_swap_size(NULL, &value, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_USED(AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_swap_size(NULL, NULL, &value, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PFREE(AGENT_RESULT *result)
{
	double	value;

	if (SYSINFO_RET_OK != get_swap_size(NULL, NULL, NULL, &value, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PUSED(AGENT_RESULT *result)
{
	double	value;

	if (SYSINFO_RET_OK != get_swap_size(NULL, NULL, NULL, NULL, &value))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"total",	SYSTEM_SWAP_TOTAL},
		{"free",	SYSTEM_SWAP_FREE},
		{"used",	SYSTEM_SWAP_USED},
		{"pfree",	SYSTEM_SWAP_PFREE},
		{"pused",	SYSTEM_SWAP_PUSED},
		{NULL,		0}
	};

	char	swapdev[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];
	int	i;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, swapdev, sizeof(swapdev)))
		*swapdev = '\0';

	/* default parameter */
	if (*swapdev == '\0')
		zbx_snprintf(swapdev, sizeof(swapdev), "all");

	if (0 != strcmp(swapdev, "all"))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "free");

	for (i = 0; fl[i].mode != 0; i++)
		if (0 == strncmp(mode, fl[i].mode, MAX_STRING_LEN))
			return (fl[i].function)(result);

	return SYSINFO_RET_FAIL;
}

static int	get_swap_io(zbx_uint64_t *icount, zbx_uint64_t *ipages, zbx_uint64_t *ocount, zbx_uint64_t *opages)
{
	int		mib[2];
	size_t		len;
	struct uvmexp	v;

	mib[0] = CTL_VM;
	mib[1] = VM_UVMEXP;

	len = sizeof(v);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	/* int swapins;		swapins */
	/* int swapouts;	swapouts */
	/* int pgswapin;	pages swapped in */
	/* int pgswapout;	pages swapped out */

#if OpenBSD < 201311		/* swapins and swapouts are not supported starting from OpenBSD 5.4 */
	if (NULL != icount)
		*icount = (zbx_uint64_t)v.swapins;
	if (NULL != ocount)
		*ocount = (zbx_uint64_t)v.swapouts;
#else
	if (NULL != icount || NULL != ocount)
		return SYSINFO_RET_FAIL;
#endif
	if (NULL != ipages)
		*ipages = (zbx_uint64_t)v.pgswapin;
	if (NULL != opages)
		*opages = (zbx_uint64_t)v.pgswapout;

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		swapdev[MAX_STRING_LEN];
	char		mode[MAX_STRING_LEN];
	zbx_uint64_t	value = 0;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, swapdev, sizeof(swapdev)))
		*swapdev = '\0';

	/* default parameter */
	if (*swapdev == '\0')
		zbx_snprintf(swapdev, sizeof(swapdev), "all");

	if (0 != strcmp(swapdev, "all"))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "count");

	if (0 == strcmp(mode, "count"))
	{
		if (SYSINFO_RET_OK != get_swap_io(&value, NULL, NULL, NULL))
			return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(mode, "pages"))
	{
		if (SYSINFO_RET_OK != get_swap_io(NULL, &value, NULL, NULL))
			return SYSINFO_RET_FAIL;
	}
	else
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		swapdev[MAX_STRING_LEN];
	char		mode[MAX_STRING_LEN];
	zbx_uint64_t	value = 0;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, swapdev, sizeof(swapdev)))
		*swapdev = '\0';

	/* default parameter */
	if (*swapdev == '\0')
		zbx_snprintf(swapdev, sizeof(swapdev), "all");

	if (0 != strcmp(swapdev, "all"))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "count");

	if (0 == strcmp(mode, "count"))
	{
		if (SYSINFO_RET_OK != get_swap_io(NULL, NULL, &value, NULL))
			return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(mode, "pages"))
	{
		if (SYSINFO_RET_OK != get_swap_io(NULL, NULL, NULL, &value))
			return SYSINFO_RET_FAIL;
	}
	else
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}
