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

	if(SYSTEM_SWAP_TOTAL(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK)
                return  SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check fot division by zero */
	if(tot_val == 0)
	{
		free_result(&result_tmp);
                return  SYSINFO_RET_FAIL;
	}

	if(SYSTEM_SWAP_FREE(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK)
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

	if(SYSTEM_SWAP_TOTAL(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK)
                return  SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check fot division by zero */
	if(tot_val == 0)
	{
		free_result(&result_tmp);
                return  SYSINFO_RET_FAIL;
	}

	if(SYSTEM_SWAP_FREE(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK)
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
		sprintf(swapdev, "all");
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
		sprintf(mode, "free");
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

struct swap_stat_s {
	zbx_uint64_t rio;
	zbx_uint64_t rsect;
	zbx_uint64_t rpag;
	zbx_uint64_t wio;
	zbx_uint64_t wsect;
	zbx_uint64_t wpag;
};

#if defined(KERNEL_2_4)
#	define INFO_FILE_NAME	"/proc/partitions"
#	define PARSE(line)	if(sscanf(line,"%*d %*d %*d %s " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d", \
				name, 			/* name */ \
				&(result->rio), 	/* rio */ \
				&(result->rsect),	/* rsect */ \
				&(result->wio), 	/* rio */ \
				&(result->wsect)	/* wsect */ \
				) != 5) continue
#else
#	define INFO_FILE_NAME	"/proc/diskstats"
#	define PARSE(line)	if(sscanf(line, "%*d %*d %s " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d", \
				name, 			/* name */ \
				&(result->rio), 	/* rio */ \
				&(result->rsect),	/* rsect */ \
				&(result->wio), 	/* wio */ \
				&(result->wsect)	/* wsect */ \
				) != 5)  \
					if(sscanf(line,"%*d %*d %s " \
						ZBX_FS_UI64 " " ZBX_FS_UI64 " " \
						ZBX_FS_UI64 " " ZBX_FS_UI64, \
					name, 			/* name */ \
					&(result->rio), 	/* rio */ \
					&(result->rsect),	/* rsect */ \
					&(result->wio), 	/* wio */ \
					&(result->wsect)	/* wsect */ \
					) != 5) continue
#endif

static int get_swap_dev_stat(const char *interface, struct swap_stat_s *result)
{
	int ret = SYSINFO_RET_FAIL;
	char line[MAX_STRING_LEN];

	char name[MAX_STRING_LEN];

	FILE *f;

	assert(result);

	if(NULL != (f = fopen(INFO_FILE_NAME,"r")))
	{
		while(fgets(line,MAX_STRING_LEN,f) != NULL)
		{
			PARSE(line);
		
			if(strncmp(name, interface, MAX_STRING_LEN) == 0)
			{
				ret = SYSINFO_RET_OK;
				break;
			}
		}
		fclose(f);
	}

	if(ret != SYSINFO_RET_OK)
	{
		result->rio	= 0;
		result->rsect	= 0;
		result->wio	= 0;
		result->wsect	= 0;
	}
	return ret;
}
	
static int	get_swap_pages(struct swap_stat_s *result)
{
	int ret = SYSINFO_RET_FAIL;
	char line[MAX_STRING_LEN];
	char name[MAX_STRING_LEN];

	zbx_uint64_t
		value1,
		value2;
	
	FILE *f;

	assert(result);

	if(NULL != (f = fopen("/proc/stat","r")) )
	{
		while(fgets(line, sizeof(line), f))
		{
			if(sscanf(line, "%10s " ZBX_FS_UI64 " " ZBX_FS_UI64, name, &value1, &value2) != 3)
				continue;
			
			if(strcmp(name, "swap"))
				continue;
			
			result->wpag	= value1;
			result->rpag	= value2;
			
			ret = SYSINFO_RET_OK;
			break;
		};
		fclose(f);
	}
	
	if(ret != SYSINFO_RET_OK)
	{
		result->wpag	= 0;
		result->rpag	= 0;
	}

	return ret;
}

static int 	get_swap_stat(const char *interface, struct swap_stat_s *result)
{
	int ret = SYSINFO_RET_FAIL;
	
	struct swap_stat_s curr;
	
	FILE *f;

	char line[MAX_STRING_LEN], *s;
	
	assert(result);

	memset(result, 0, sizeof(struct swap_stat_s));

	if(0 == strcmp(interface, "all"))
	{
		ret = get_swap_pages(result);
		interface = NULL;
	}

	if(NULL != (f = fopen("/proc/swaps","r")) )
	{
		while (fgets(line, sizeof(line), f))
		{
			s = strchr(line,' ');
			if (s && s != line && strncmp(line, "/dev/", 5) == 0)
			{
				*s = 0;

				if(interface && 0 != strcmp(interface, line+5)) continue;
				
				if(SYSINFO_RET_OK == get_swap_dev_stat(line+5, &curr))
				{
					result->rio	+= curr.rio;
					result->rsect	+= curr.rsect;
					result->wio	+= curr.wio;
					result->wsect	+= curr.wsect;
					
					ret = SYSINFO_RET_OK;
				}
			}
		}
		fclose(f);
	}

	return ret;
}

int	SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char    	swapdev[10];
	char    	mode[20];

	struct swap_stat_s	ss;

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
		sprintf(swapdev, "all");
	}

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}

	if(mode[0] == '\0')
	{
		/* default parameter */
		sprintf(mode, "pages");
	}

	ret = get_swap_stat(swapdev, &ss);

	if(ret == SYSINFO_RET_OK)
	{
		if(strncmp(mode, "sectors", MAX_STRING_LEN)==0)
		{
			SET_UI64_RESULT(result, ss.wsect);
			ret = SYSINFO_RET_OK;
		}
		else if(strncmp(mode, "count", MAX_STRING_LEN)==0)
		{
			SET_UI64_RESULT(result, ss.wio);
			ret = SYSINFO_RET_OK;
		}
		else if(strncmp(mode, "pages", MAX_STRING_LEN)==0 && strcmp(swapdev, "all")==0)
		{
			SET_UI64_RESULT(result, ss.wpag);
			ret = SYSINFO_RET_OK;
		}
		else
		{
			ret = SYSINFO_RET_FAIL;
		}
	}

	return ret;
}

int	SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char    	swapdev[10];
	char    	mode[20];

	struct swap_stat_s	ss;

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
		sprintf(swapdev, "all");
	}

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}

	if(mode[0] == '\0')
	{
		/* default parameter */
		sprintf(mode, "pages");
	}

	ret = get_swap_stat(swapdev, &ss);

	if(ret == SYSINFO_RET_OK)
	{
		if(strncmp(mode, "sectors", MAX_STRING_LEN)==0)
		{
			SET_UI64_RESULT(result, ss.rsect);
			ret = SYSINFO_RET_OK;
		}
		else if(strncmp(mode, "count", MAX_STRING_LEN)==0)
		{
			SET_UI64_RESULT(result, ss.rio);
			ret = SYSINFO_RET_OK;
		}
		else if(strncmp(mode, "pages", MAX_STRING_LEN)==0 && strcmp(swapdev, "all")==0)
		{
			SET_UI64_RESULT(result, ss.rpag);
			ret = SYSINFO_RET_OK;
		}
		else
		{
			ret = SYSINFO_RET_FAIL;
		}
	}

	return ret;
}

