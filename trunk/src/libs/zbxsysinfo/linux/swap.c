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

#include "config.h"

#include "common.h"
#include "sysinfo.h"

#include "md5.h"

static int	SYSTEM_SWAP_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	assert(result);

        init_result(result);

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.freeswap * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.freeswap);
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
}

static int	SYSTEM_SWAP_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	assert(result);

        init_result(result);

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.totalswap * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.totalswap);
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
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

	if(strncmp(swapdev, "all", MAX_STRING_LEN))
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

static int 	get_swap_io(zbx_uint64_t *swapin, zbx_uint64_t *swapout)
{
	FILE	*f = NULL;
	char	line[MAX_STRING_LEN];
	char	name[20];
	zbx_uint64_t
		value1,
		value2;

	if(NULL != (f = fopen("/proc/stat","r")) )
	{
		while(fgets(line, sizeof(line), f))
		{
			if(sscanf(line, "%10s\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\n", name, &value1, &value2) != 3)
				continue;
			
			if(strcmp(name, "swap"))
				continue;
			
			if(swapin)	*swapin  = value1;
			if(swapout)	*swapout = value2;

			fclose(f);
			
			return SYSINFO_RET_OK;
		};
		zbx_fclose(f);
	}
	return SYSINFO_RET_FAIL;
}

int	SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char    	swapdev[10];
	char    	mode[20];
	zbx_uint64_t	value = 0;

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

	if(strcmp(swapdev, "all"))
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
		zbx_snprintf(mode, sizeof(mode), "pages");
	}

	if(strcmp(mode,"pages") != 0)
	{
		return SYSINFO_RET_FAIL;
	}
	
	if( SYSINFO_RET_OK == (ret = get_swap_io(&value, NULL)) )
	{
		SET_UI64_RESULT(result, value);
	}

	return ret;
}

int	SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char    	swapdev[10];
	char    	mode[20];
	zbx_uint64_t	value = 0;

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

	if(strcmp(swapdev, "all"))
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
		zbx_snprintf(mode, sizeof(mode), "pages");
	}

	if(strcmp(mode,"pages") != 0)
	{
		return SYSINFO_RET_FAIL;
	}
	
	if( SYSINFO_RET_OK == (ret = get_swap_io(NULL, &value)) )
	{
		SET_UI64_RESULT(result, value);
	}

	return ret;
}

