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

#include "md5.h"


static int get_swap_size(double *total, double *free)
{
	int	mib[2];
        size_t  len;
        struct uvmexp vm;

	mib[0]=CTL_VM;
	mib[1]=VM_UVMEXP;

	len=sizeof vm;

	if(sysctl(mib,2,&vm,&len,NULL,0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}

	if(total)
	{
		/* int swpages;    number of PAGE_SIZE'ed swap pages */
		/* int pagesize;   size of a page (PAGE_SIZE): must be power of 2 */
		(*total) = (double)(((long) vm.swpages) * vm.pagesize);
	}
	if(free)
	{
		/* int swpages;    number of PAGE_SIZE'ed swap pages */
		/* int swpginuse;  number of swap pages in use */
		/* int pagesize;   size of a page (PAGE_SIZE): must be power of 2 */
		(*free) = (double)(((long) (vm.swpages - vm.swpginuse)) * vm.pagesize);
	}

	return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double value;
	int ret = SYSINFO_RET_FAIL;

        assert(result);

        init_result(result);
	
	ret = get_swap_size(NULL, &value);
	
	if(ret != SYSINFO_RET_OK)
		return ret;

	SET_UI64_RESULT(result, value);
	return ret;
}

static int	SYSTEM_SWAP_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	double value;
	int ret = SYSINFO_RET_FAIL;

        assert(result);

        init_result(result);
	
	ret = get_swap_size(&value, NULL);
	
	if(ret != SYSINFO_RET_OK)
		return ret;

	SET_UI64_RESULT(result, value);
	return ret;
}

static int	SYSTEM_SWAP_PFREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	AGENT_RESULT	result_tmp;
        zbx_uint64_t  tot_val = 0;
        zbx_uint64_t  free_val = 0;

        assert(result);

        init_result(result);
        init_result(&result_tmp);

	if(SYSTEM_SWAP_TOTAL(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK ||
		!(result_tmp.type & AR_UINT64))
	                return  SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check fot division by zero */
	if(tot_val == 0)
	{
		free_result(&result_tmp);
                return  SYSINFO_RET_FAIL;
	}

	if(SYSTEM_SWAP_FREE(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK ||
		!(result_tmp.type & AR_UINT64))
                	return  SYSINFO_RET_FAIL;
	free_val = result_tmp.ui64;

	free_result(&result_tmp);

	SET_DBL_RESULT(result, (100.0 * (double)free_val) / (double)tot_val);

        return SYSINFO_RET_OK;
}

static int	SYSTEM_SWAP_PUSED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	AGENT_RESULT	result_tmp;
        zbx_uint64_t  tot_val = 0;
        zbx_uint64_t  free_val = 0;

        assert(result);

        init_result(result);
        init_result(&result_tmp);

	if(SYSTEM_SWAP_TOTAL(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK ||
		!(result_tmp.type & AR_UINT64))
                	return  SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check fot division by zero */
	if(tot_val == 0)
	{
		free_result(&result_tmp);
                return  SYSINFO_RET_FAIL;
	}

	if(SYSTEM_SWAP_FREE(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK ||
		!(result_tmp.type & AR_UINT64))
                	return  SYSINFO_RET_FAIL;
	free_val = result_tmp.ui64;

	free_result(&result_tmp);

	SET_DBL_RESULT(result, 100.0-(100.0 * (double)free_val) / (double)tot_val);

        return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define SWP_FNCLIST struct swp_fnclist_s
SWP_FNCLIST
{
	char *mode;
	int (*function)();
};

	SWP_FNCLIST fl[] = 
	{
		{"total",	SYSTEM_SWAP_TOTAL},
		{"free",	SYSTEM_SWAP_FREE},
		{"pfree",	SYSTEM_SWAP_PFREE},
		{"pused",	SYSTEM_SWAP_PUSED},
		{0,		0}
	};

	char swapdev[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;
	
        assert(result);

        init_result(result);
	
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
	{
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
		{
			return (fl[i].function)(cmd, param, flags, result);
		}
	}
	
	return SYSINFO_RET_FAIL;
}

int     OLD_SWAP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        char    key[MAX_STRING_LEN];
        int     ret;

        assert(result);

        init_result(result);

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, key, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

        if(strcmp(key,"free") == 0)
        {
                ret = SYSTEM_SWAP_FREE(cmd, param, flags, result);
        }
        else if(strcmp(key,"total") == 0)
        {
                ret = SYSTEM_SWAP_TOTAL(cmd, param, flags, result);
        }
        else
        {
                ret = SYSINFO_RET_FAIL;
        }

        return ret;
}

int	get_swap_io(double *swapin, double *pgswapin, double *swapout, double *pgswapout)
{
	int	mib[2];
        size_t len;
        struct uvmexp vm;

	mib[0]=CTL_VM;
	mib[1]=VM_UVMEXP;

	len = sizeof(vm);

	if(sysctl(mib,2,&vm,&len,NULL,0) == -1)
	{
		return SYSINFO_RET_FAIL;
	}
	    
	if(swapin)
	{
		/* int swapins;           swapins */
		(*swapin) = (double) vm.swapins;
	}
	if(pgswapin)
	{
		/* int pgswapin;           pages swapped in  */
		(*pgswapin) = (double) vm.pgswapin;
	}
	if(swapout)
	{
		/* int swapouts;           swapouts */
		(*swapout) = (double) vm.swapouts;
	}
	if(pgswapout)
	{
		/* int pgswapout;          pages swapped out  */
		(*pgswapout) = (double) vm.pgswapout;
	}
	
	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
    int	    ret = SYSINFO_RET_FAIL;
    char    swapdev[MAX_STRING_LEN];
    char    mode[MAX_STRING_LEN];
    double  value = 0;
        
    assert(result);

    init_result(result);
	
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
        return SYSINFO_RET_FAIL;
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
        
    assert(result);

    init_result(result);
	
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
        return SYSINFO_RET_FAIL;
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
