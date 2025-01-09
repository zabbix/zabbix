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

static int	get_swap_size(zbx_uint64_t *total, zbx_uint64_t *free, zbx_uint64_t *used, double *pfree, double *pused,
		char **error)
{
	int		mib[2];
	size_t		len;
	struct uvmexp	v;

	mib[0] = CTL_VM;
	mib[1] = VM_UVMEXP;

	len = sizeof(v);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

	/* int pagesize;	size of a page (PAGE_SIZE): must be power of 2 */
	/* int swpages;		number of PAGE_SIZE'ed swap pages */
	/* int swpginuse;	number of swap pages in use */

	if (NULL != total)
		*total = (zbx_uint64_t)v.swpages * v.pagesize;
	if (NULL != free)
		*free = (zbx_uint64_t)(v.swpages - v.swpginuse) * v.pagesize;
	if (NULL != used)
		*used = (zbx_uint64_t)v.swpginuse * v.pagesize;
	if (NULL != pfree)
		*pfree = 0 != v.swpages ? (double)(100.0 * (v.swpages - v.swpginuse)) / v.swpages : 100;
	if (NULL != pused)
		*pused = 0 != v.swpages ? (double)(100.0 * v.swpginuse) / v.swpages : 0;

	return SYSINFO_RET_OK;
}

static int	system_swap_total(AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_swap_size(&value, NULL, NULL, NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	system_swap_free(AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_swap_size(NULL, &value, NULL, NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	system_swap_used(AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_swap_size(NULL, NULL, &value, NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	system_swap_pfree(AGENT_RESULT *result)
{
	double	value;
	char	*error;

	if (SYSINFO_RET_OK != get_swap_size(NULL, NULL, NULL, &value, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	system_swap_pused(AGENT_RESULT *result)
{
	double	value;
	char	*error;

	if (SYSINFO_RET_OK != get_swap_size(NULL, NULL, NULL, NULL, &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	system_swap_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*swapdev, *mode;
	int	ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || *mode == '\0' || 0 == strcmp(mode, "free"))
	{
		ret = system_swap_free(result);
	}
	else if (0 == strcmp(mode, "used"))
	{
		ret = system_swap_used(result);
	}
	else if (0 == strcmp(mode, "total"))
	{
		ret = system_swap_total(result);
	}
	else if (0 == strcmp(mode, "pfree"))
	{
		ret = system_swap_pfree(result);
	}
	else if (0 == strcmp(mode, "pused"))
	{
		ret = system_swap_pused(result);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

static int	get_swap_io(zbx_uint64_t *icount, zbx_uint64_t *ipages, zbx_uint64_t *ocount, zbx_uint64_t *opages,
		char **error)
{
	int		mib[2];
	size_t		len;
	struct uvmexp	v;

	mib[0] = CTL_VM;
	mib[1] = VM_UVMEXP;

	len = sizeof(v);

	if (0 != sysctl(mib, 2, &v, &len, NULL, 0))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

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
	{
		*error = zbx_dsprintf(NULL, "Not supported by the system starting from OpenBSD 5.4.");
		return SYSINFO_RET_FAIL;
	}
#endif
	if (NULL != ipages)
		*ipages = (zbx_uint64_t)v.pgswapin;
	if (NULL != opages)
		*opages = (zbx_uint64_t)v.pgswapout;

	return SYSINFO_RET_OK;
}

int	system_swap_in(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		ret;
	char		*swapdev, *mode, *error;
	zbx_uint64_t	value;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	/* the only supported parameter */
	if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* default parameter */
	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "count"))
	{
		ret = get_swap_io(&value, NULL, NULL, NULL, &error);
	}
	else if (0 == strcmp(mode, "pages"))
	{
		ret = get_swap_io(NULL, &value, NULL, NULL, &error);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_OK == ret)
		SET_UI64_RESULT(result, value);
	else
		SET_MSG_RESULT(result, error);

	return ret;
}

int	system_swap_out(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		ret;
	char		*swapdev, *mode, *error;
	zbx_uint64_t	value;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	/* the only supported parameter */
	if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* default parameter */
	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "count"))
	{
		ret = get_swap_io(NULL, NULL, &value, NULL, &error);
	}
	else if (0 == strcmp(mode, "pages"))
	{
		ret = get_swap_io(NULL, NULL, NULL, &value, &error);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_OK == ret)
		SET_UI64_RESULT(result, value);
	else
		SET_MSG_RESULT(result, error);

	return ret;
}
