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

/******************************************************************************
 *                                                                            *
 * Function: get_swapinfo                                                     *
 *                                                                            *
 * Purpose: get swap usage statistics                                         *
 *                                                                            *
 * Return value: SUCCEED if swap usage statistics retrieved successfully      *
 *               FAIL otherwise                                               *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments: we make calculations the same way swap -s works:                 *
 *           total = total swap memory                                        *
 *           used = allocated + reserved                                      *
 *           free = total - used                                              *
 *                                                                            *
 ******************************************************************************/
static int	get_swapinfo(zbx_uint64_t *total, zbx_uint64_t *used)
{
	static int	pagesize = 0;

	struct anoninfo	ai;

	if (-1 == swapctl(SC_AINFO, &ai))
		return FAIL;

	if (0 == pagesize)
		pagesize = getpagesize();

	*total = ai.ani_max * pagesize;
	*used = ai.ani_resv * pagesize;

	return SUCCEED;
}

static int	SYSTEM_SWAP_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, used;

	if (SUCCEED != get_swapinfo(&total, &used))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, total);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_USED(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, used;

	if (SUCCEED != get_swapinfo(&total, &used))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, used);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_FREE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, used;

	if (SUCCEED != get_swapinfo(&total, &used))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, total - used);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PUSED(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, used;

	if (SUCCEED != get_swapinfo(&total, &used))
		return SYSINFO_RET_FAIL;

	if (0 == total)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, 100.0 * (double)used / (double)total);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PFREE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, used;

	if (SUCCEED != get_swapinfo(&total, &used))
		return SYSINFO_RET_FAIL;

	if (0 == total)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, 100.0 * (double)(total - used) / (double)total);

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	ret;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "all"))	/* default parameter */
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "free"))	/* default parameter */
		ret = SYSTEM_SWAP_FREE(request, result);
	else if (0 == strcmp(tmp, "total"))
		ret = SYSTEM_SWAP_TOTAL(request, result);
	else if (0 == strcmp(tmp, "used"))
		ret = SYSTEM_SWAP_USED(request, result);
	else if (0 == strcmp(tmp, "pfree"))
		ret = SYSTEM_SWAP_PFREE(request, result);
	else if (0 == strcmp(tmp, "pused"))
		ret = SYSTEM_SWAP_PUSED(request, result);
	else
		return SYSINFO_RET_FAIL;

	return ret;
}

#define	DO_SWP_IN	1
#define DO_PG_IN	2
#define	DO_SWP_OUT	3
#define DO_PG_OUT	4

static int	get_swap_io(double *swapin, double *pgswapin, double *swapout, double *pgswapout)
{
	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;
	int		cpu_count = 0;

	if (NULL != (kc = kstat_open()))
	{
		while (NULL != (k = kc->kc_chain))
		{
			if (0 == strncmp(k->ks_name, "cpu_stat", 8) && -1 != kstat_read(kc, k, NULL))
			{
				cpu = (cpu_stat_t*)k->ks_data;

				if (NULL != swapin)
				{
					/* uint_t   swapin;	*/ /* swapins */
					(*swapin) += (double)cpu->cpu_vminfo.swapin;
				}

				if (NULL != pgswapin)
				{
					/* uint_t   pgswapin;	*/ /* pages swapped in */
					(*pgswapin) += (double)cpu->cpu_vminfo.pgswapin;
				}

				if (NULL != swapout)
				{
					/* uint_t   swapout;	*/ /* swapout */
					(*swapout) += (double)cpu->cpu_vminfo.swapout;
				}

				if (NULL != pgswapout)
				{
					/* uint_t   pgswapout;	*/ /* pages swapped out */
					(*pgswapout) += (double)cpu->cpu_vminfo.pgswapout;
				}
				cpu_count += 1;
			}
			k = k->ks_next;
		}
		kstat_close(kc);
	}

	if (0 == cpu_count)
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	*tmp;
	double	value = 0;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "all"))
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "count"))
		ret = get_swap_io(&value, NULL, NULL, NULL);
	else if (0 == strcmp(tmp, "pages"))
		ret = get_swap_io(NULL, &value, NULL, NULL);
	else
		ret =  SYSINFO_RET_FAIL;

	if (ret != SYSINFO_RET_OK)
		return ret;

	SET_UI64_RESULT(result, value);
	return ret;
}

int	SYSTEM_SWAP_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	*tmp;
	double	value = 0;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "all"))
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "count"))
		ret = get_swap_io(NULL, NULL, &value, NULL);
	else if (0 == strcmp(tmp, "pages"))
		ret = get_swap_io(NULL, NULL, NULL, &value);
	else
		ret = SYSINFO_RET_FAIL;

	if (ret != SYSINFO_RET_OK)
		return ret;

	SET_UI64_RESULT(result, value);
	return ret;
}
