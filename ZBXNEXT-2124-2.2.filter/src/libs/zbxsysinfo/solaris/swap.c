/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Comments: we try to imitate "swap -l".                                     *
 *                                                                            *
 ******************************************************************************/
static int	get_swapinfo(zbx_uint64_t *total, zbx_uint64_t *free1)
{
	int			i, cnt, cnt2, page_size, ret = SUCCEED;
	struct swaptable	*swt = NULL;
	struct swapent		*ste;
	static char		path[256];

	/* get total number of swap entries */
	if (-1 == (cnt = swapctl(SC_GETNSWP, 0)))
		return FAIL;

	if (0 == cnt)
	{
		*total = *free1 = 0;
		return SUCCEED;
	}

	/* allocate space to hold count + n swapents */
	swt = (struct swaptable *)zbx_malloc(swt, sizeof(struct swaptable) + (cnt - 1) * sizeof(struct swapent));

	swt->swt_n = cnt;

	/* fill in ste_path pointers: we don't care about the paths, so we point them all to the same buffer */
	ste = &(swt->swt_ent[0]);
	i = cnt;
	while (--i >= 0)
	{
		ste++->ste_path = path;
	}

	/* grab all swap info */
	if (-1 == (cnt2 = swapctl(SC_LIST, swt)) || cnt != cnt2)
	{
		ret = FAIL;
		goto finish;
	}

	/* walk through the structs and sum up the fields */
	*total = *free1 = 0;
	ste = &(swt->swt_ent[0]);
	i = cnt;
	while (--i >= 0)
	{
		/* don't count slots being deleted */
		if (0 == (ste->ste_flags & (ST_INDEL | ST_DOINGDEL)))
		{
			*total += ste->ste_pages;
			*free1 += ste->ste_free;
		}
		ste++;
	}

	page_size = getpagesize();

	/* fill in the results */
	*total *= page_size;
	*free1 *= page_size;
finish:
	zbx_free(swt);

	return ret;
}

static int	SYSTEM_SWAP_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, free1;

	if (SUCCEED != get_swapinfo(&total, &free1))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, total);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_USED(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, free1;

	if (SUCCEED != get_swapinfo(&total, &free1))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, total - free1);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_FREE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, free1;

	if (SUCCEED != get_swapinfo(&total, &free1))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, free1);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PUSED(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, free1;

	if (SUCCEED != get_swapinfo(&total, &free1))
		return SYSINFO_RET_FAIL;

	if (0 == total)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, 100.0 * (double)(total - free1) / (double)total);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PFREE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_uint64_t	total, free1;

	if (SUCCEED != get_swapinfo(&total, &free1))
		return SYSINFO_RET_FAIL;

	if (0 == total)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, 100.0 * (double)free1 / (double)total);

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

static int	get_swap_io(zbx_uint64_t *swapin, zbx_uint64_t *pgswapin, zbx_uint64_t *swapout, zbx_uint64_t *pgswapout)
{
	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;
	int		cpu_count = 0;

	if (NULL != (kc = kstat_open()))
	{
		k = kc->kc_chain;

		while (NULL != k)
		{
			if (0 == strncmp(k->ks_name, "cpu_stat", 8) && -1 != kstat_read(kc, k, NULL))
			{
				cpu = (cpu_stat_t *)k->ks_data;

				if (NULL != swapin)
				{
					/* uint_t   swapin;	*/ /* swapins */
					*swapin += cpu->cpu_vminfo.swapin;
				}

				if (NULL != pgswapin)
				{
					/* uint_t   pgswapin;	*/ /* pages swapped in */
					*pgswapin += cpu->cpu_vminfo.pgswapin;
				}

				if (NULL != swapout)
				{
					/* uint_t   swapout;	*/ /* swapout */
					*swapout += cpu->cpu_vminfo.swapout;
				}

				if (NULL != pgswapout)
				{
					/* uint_t   pgswapout;	*/ /* pages swapped out */
					*pgswapout += cpu->cpu_vminfo.pgswapout;
				}

				cpu_count++;
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
	int		ret = SYSINFO_RET_FAIL;
	char		*tmp;
	zbx_uint64_t	value = 0;

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
	int		ret = SYSINFO_RET_FAIL;
	char		*tmp;
	zbx_uint64_t	value = 0;

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
