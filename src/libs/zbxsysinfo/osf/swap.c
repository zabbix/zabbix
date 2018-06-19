/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

/* Solaris. */
#if !defined(HAVE_SYSINFO_FREESWAP)
#ifdef HAVE_SYS_SWAP_SWAPTABLE
static void	get_swapinfo(double *total, double *fr)
{
	int	cnt, i, page_size;
/* Support for >2Gb */
/*	int t, f;*/
	double	t, f;
	struct swaptable *swt;
	struct swapent *ste;
	static char path[256];

	/* get total number of swap entries */
	cnt = swapctl(SC_GETNSWP, 0);

	/* allocate enough space to hold count + n swapents */
	swt = (struct swaptable *)malloc(sizeof(int) +
		cnt * sizeof(struct swapent));

	if (swt == NULL)
	{
		*total = 0;
		*fr = 0;
		return;
	}
	swt->swt_n = cnt;

/* fill in ste_path pointers: we don't care about the paths, so we
point them all to the same buffer */
	ste = &(swt->swt_ent[0]);
	i = cnt;
	while (--i >= 0)
	{
		ste++->ste_path = path;
	}

	/* grab all swap info */
	swapctl(SC_LIST, swt);

	/* walk through the structs and sum up the fields */
	t = f = 0;
	ste = &(swt->swt_ent[0]);
	i = cnt;
	while (--i >= 0)
	{
		/* don't count slots being deleted */
		if (!(ste->ste_flags & ST_INDEL) &&
		!(ste->ste_flags & ST_DOINGDEL))
		{
			t += ste->ste_pages;
			f += ste->ste_free;
		}
		ste++;
	}

	page_size=getpagesize();

	/* fill in the results */
	*total = page_size*t;
	*fr = page_size*f;
	free(swt);
}
#endif
#endif

static int	SYSTEM_SWAP_USED(AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_FREESWAP
	struct sysinfo	info;

	if (0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, ((zbx_uint64_t)info.totalswap - (zbx_uint64_t)info.freeswap) *
				(zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.totalswap - info.freeswap);
#endif
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
/* Solaris */
#else
#ifdef HAVE_SYS_SWAP_SWAPTABLE
	double	swaptotal, swapfree;

	get_swapinfo(&swaptotal, &swapfree);

	SET_UI64_RESULT(result, swaptotal - swapfree);
	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
#endif
}

static int	SYSTEM_SWAP_FREE(AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_FREESWAP
	struct sysinfo info;

	if (0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.freeswap * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.freeswap);
#endif
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
/* Solaris */
#else
#ifdef HAVE_SYS_SWAP_SWAPTABLE
	double swaptotal,swapfree;

	get_swapinfo(&swaptotal,&swapfree);

	SET_UI64_RESULT(result, swapfree);
	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
#endif
}

static int	SYSTEM_SWAP_TOTAL(AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_TOTALSWAP
	struct sysinfo info;

	if (0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.totalswap * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.totalswap);
#endif
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
/* Solaris */
#else
#ifdef HAVE_SYS_SWAP_SWAPTABLE
	double swaptotal,swapfree;

	get_swapinfo(&swaptotal,&swapfree);

	SET_UI64_RESULT(result, swaptotal);
	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
#endif
}

static int	SYSTEM_SWAP_PFREE(AGENT_RESULT *result)
{
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	tot_val = 0;
	zbx_uint64_t	free_val = 0;

	init_result(&result_tmp);

	if (SYSINFO_RET_OK != SYSTEM_SWAP_TOTAL(cmd, param, flags, &result_tmp) || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check for division by zero */
	if (0 == tot_val)
	{
		free_result(&result_tmp);
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_OK != SYSTEM_SWAP_FREE(cmd, param, flags, &result_tmp) || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	free_val = result_tmp.ui64;

	free_result(&result_tmp);

	SET_DBL_RESULT(result, (100.0 * (double)free_val) / (double)tot_val);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PUSED(AGENT_RESULT *result)
{
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	tot_val = 0;
	zbx_uint64_t	free_val = 0;

	init_result(&result_tmp);

	if (SYSINFO_RET_OK != SYSTEM_SWAP_TOTAL(cmd, param, flags, &result_tmp) || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check for division by zero */
	if (0 == tot_val)
	{
		free_result(&result_tmp);
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_OK != SYSTEM_SWAP_FREE(cmd, param, flags, &result_tmp) || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	free_val = result_tmp.ui64;

	free_result(&result_tmp);

	SET_DBL_RESULT(result, 100.0 - (100.0 * (double)free_val) / (double)tot_val);

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*swapdev, *mode;
	int	ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "free"))
		ret = SYSTEM_SWAP_FREE(result);
	else if (0 == strcmp(mode, "used"))
		ret = SYSTEM_SWAP_USED(result);
	else if (0 == strcmp(mode, "total"))
		ret = SYSTEM_SWAP_TOTAL(result);
	else if (0 == strcmp(mode, "pfree"))
		ret = SYSTEM_SWAP_PFREE(result);
	else if (0 == strcmp(mode, "pused"))
		ret = SYSTEM_SWAP_PUSED(result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}
