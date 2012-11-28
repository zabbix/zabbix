/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"

static void	get_swapinfo(double *total, double *fr)
{
	register int cnt, i, page_size;
/* Support for >2Gb */
/*	register int t, f;*/
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

int	SYSTEM_SWAP_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double	swaptotal, swapfree;

	get_swapinfo(&swaptotal, &swapfree);

	SET_UI64_RESULT(result, swapfree);
	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double	swaptotal, swapfree;

	get_swapinfo(&swaptotal, &swapfree);

	SET_UI64_RESULT(result, swaptotal);
	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PFREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	tot_val = 0;
	zbx_uint64_t	free_val = 0;

        init_result(&result_tmp);

	if(SYSTEM_SWAP_TOTAL(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check for division by zero */
	if(tot_val == 0)
	{
		free_result(&result_tmp);
		return SYSINFO_RET_FAIL;
	}

	if(SYSTEM_SWAP_FREE(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	free_val = result_tmp.ui64;

	free_result(&result_tmp);

	SET_DBL_RESULT(result, (100.0 * (double)free_val) / (double)tot_val);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PUSED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	tot_val = 0;
	zbx_uint64_t	free_val = 0;

        init_result(&result_tmp);

	if(SYSTEM_SWAP_TOTAL(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check for division by zero */
	if(tot_val == 0)
	{
		free_result(&result_tmp);
		return SYSINFO_RET_FAIL;
	}

	if(SYSTEM_SWAP_FREE(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	free_val = result_tmp.ui64;

	free_result(&result_tmp);

	SET_DBL_RESULT(result, 100.0 - (100.0 * (double)free_val) / (double)tot_val);

        return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"total",	SYSTEM_SWAP_TOTAL},
		{"free",	SYSTEM_SWAP_FREE},
		{"pfree",       SYSTEM_SWAP_PFREE},
		{"pused",       SYSTEM_SWAP_PUSED},
		{NULL,		0}
	};

	char swapdev[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, swapdev, sizeof(swapdev)) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

        if(swapdev[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(swapdev, sizeof(swapdev), "all");
	}

	if(strncmp(swapdev, "all", sizeof(swapdev)))
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
        {
                mode[0] = '\0';
        }

        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "free");
	}

	for(i=0; fl[i].mode!=0; i++)
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
			return (fl[i].function)(cmd, param, flags, result);

	return SYSINFO_RET_FAIL;
}

#define	DO_SWP_IN	1
#define DO_PG_IN	2
#define	DO_SWP_OUT	3
#define DO_PG_OUT	4

static int	get_swap_io(double *swapin, double *pgswapin, double *swapout, double *pgswapout)
{
    kstat_ctl_t	    *kc;
    kstat_t	    *k;
    cpu_stat_t	    *cpu;

    int	    cpu_count = 0;

    kc = kstat_open();

    if(kc != NULL)
    {
	k = kc->kc_chain;
  	while (k != NULL)
	{
	    if( (strncmp(k->ks_name, "cpu_stat", 8) == 0) &&
		(kstat_read(kc, k, NULL) != -1) )
	    {
		cpu = (cpu_stat_t*) k->ks_data;
		if(swapin)
		{
		   /* uint_t   swapin;	    	*/ /* swapins */
		   (*swapin) += (double) cpu->cpu_vminfo.swapin;
		}
		if(pgswapin)
		{
		   /* uint_t   pgswapin;	*/ /* pages swapped in */
		  (*pgswapin) += (double) cpu->cpu_vminfo.pgswapin;
		}
		if(swapout)
		{
		   /* uint_t   swapout;	    	*/ /* swapout */
		   (*swapout) += (double) cpu->cpu_vminfo.swapout;
		}
		if(pgswapout)
		{
		   /* uint_t   pgswapout;	*/ /* pages swapped out */
		  (*pgswapout) += (double) cpu->cpu_vminfo.pgswapout;
		}
		cpu_count += 1;
  	    }
	    k = k->ks_next;
        }
	kstat_close(kc);
    }

    if(cpu_count == 0)
    {
	return SYSINFO_RET_FAIL;
    }

    return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    int	    ret = SYSINFO_RET_FAIL;
    char    swapdev[MAX_STRING_LEN];
    char    mode[MAX_STRING_LEN];
    double  value = 0;

    if(num_param(param) > 2)
    {
        return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, swapdev, sizeof(swapdev)) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if(swapdev[0] == '\0')
    {
	/* default parameter */
	zbx_snprintf(swapdev, sizeof(swapdev), "all");
    }

    if(strncmp(swapdev, "all", sizeof(swapdev)))
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 2, mode, sizeof(mode)) != 0)
    {
	mode[0] = '\0';
    }

    if(mode[0] == '\0')
    {
        zbx_snprintf(mode, sizeof(mode), "count");
    }

    if(strcmp(mode,"count") == 0)
    {
	ret = get_swap_io(&value, NULL, NULL, NULL);
    }
    else if(strcmp(mode,"pages") == 0)
    {
	ret = get_swap_io(NULL, &value, NULL, NULL);
    }
    else
    {
	return SYSINFO_RET_FAIL;
    }

    if(ret != SYSINFO_RET_OK)
	return ret;

    SET_UI64_RESULT(result, value);
    return ret;
}

int	SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    int	    ret = SYSINFO_RET_FAIL;
    char    swapdev[MAX_STRING_LEN];
    char    mode[MAX_STRING_LEN];
    double  value = 0;

    if(num_param(param) > 2)
    {
        return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 1, swapdev, sizeof(swapdev)) != 0)
    {
	return SYSINFO_RET_FAIL;
    }

    if(swapdev[0] == '\0')
    {
	/* default parameter */
	zbx_snprintf(swapdev, sizeof(swapdev), "all");
    }

    if(strncmp(swapdev, "all", sizeof(swapdev)))
    {
	return SYSINFO_RET_FAIL;
    }

    if(get_param(param, 2, mode, sizeof(mode)) != 0)
    {
	mode[0] = '\0';
    }

    if(mode[0] == '\0')
    {
        zbx_snprintf(mode, sizeof(mode), "count");
    }

    if(strcmp(mode,"count") == 0)
    {
	ret = get_swap_io(NULL, NULL, &value, NULL);
    }
    else if(strcmp(mode,"pages") == 0)
    {
	ret = get_swap_io(NULL, NULL, NULL, &value);
    }
    else
    {
	return SYSINFO_RET_FAIL;
    }

    if(ret != SYSINFO_RET_OK)
	return ret;

    SET_UI64_RESULT(result, value);
    return ret;
}
