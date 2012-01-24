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

static int	VM_MEMORY_CACHED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	FILE	*f;
	char	*t;
	char	c[MAX_STRING_LEN];
	zbx_uint64_t	res = 0;

	if(NULL == (f = fopen("/proc/meminfo","r") ))
	{
		return SYSINFO_RET_FAIL;
	}
	while(NULL!=fgets(c,MAX_STRING_LEN,f))
	{
		if(strncmp(c,"Cached:",7) == 0)
		{
			t=(char *)strtok(c," ");
			t=(char *)strtok(NULL," ");
			sscanf(t, ZBX_FS_UI64, &res );
			t=(char *)strtok(NULL," ");

			if(strcasecmp(t,"kb"))		res <<= 10;
			else if(strcasecmp(t, "mb"))	res <<= 20;
			else if(strcasecmp(t, "gb"))	res <<= 30;
			else if(strcasecmp(t, "tb"))	res <<= 40;

			break;
		}
	}
	zbx_fclose(f);

	SET_UI64_RESULT(result, res);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_BUFFERS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.bufferram * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.bufferram);
#endif
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
}

static int	VM_MEMORY_SHARED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.sharedram * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.sharedram);
#endif
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
}

static int	VM_MEMORY_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.totalram * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.totalram);
#endif
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
}

static int	VM_MEMORY_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo info;

	if( 0 == sysinfo(&info))
	{
#ifdef HAVE_SYSINFO_MEM_UNIT
		SET_UI64_RESULT(result, (zbx_uint64_t)info.freeram * (zbx_uint64_t)info.mem_unit);
#else
		SET_UI64_RESULT(result, info.freeram);
#endif
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
}

static int      VM_MEMORY_PFREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	tot_val = 0;
	zbx_uint64_t	free_val = 0;

	init_result(&result_tmp);

	if (VM_MEMORY_TOTAL(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	tot_val = result_tmp.ui64;

	/* Check for division by zero */
	if(tot_val == 0)
	{
		free_result(&result_tmp);
		return SYSINFO_RET_FAIL;
	}

	if (VM_MEMORY_FREE(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	free_val = result_tmp.ui64;

	free_result(&result_tmp);

	SET_DBL_RESULT(result, (100.0 * (double)free_val) / (double)tot_val);

	return SYSINFO_RET_OK;
}

static int      VM_MEMORY_AVAILABLE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	sum = 0;

	init_result(&result_tmp);

	if (VM_MEMORY_FREE(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	sum += result_tmp.ui64;

	if (VM_MEMORY_BUFFERS(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	sum += result_tmp.ui64;

	if (VM_MEMORY_CACHED(cmd, param, flags, &result_tmp) != SYSINFO_RET_OK || !(result_tmp.type & AR_UINT64))
		return SYSINFO_RET_FAIL;
	sum += result_tmp.ui64;

	free_result(&result_tmp);

	SET_UI64_RESULT(result, sum);

	return SYSINFO_RET_OK;
}

int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"free",	VM_MEMORY_FREE},
		{"pfree",	VM_MEMORY_PFREE},
		{"shared",	VM_MEMORY_SHARED},
		{"total",	VM_MEMORY_TOTAL},
		{"buffers",	VM_MEMORY_BUFFERS},
		{"cached",	VM_MEMORY_CACHED},
		{"available",	VM_MEMORY_AVAILABLE},
		{0,		0}
	};

	char	mode[MAX_STRING_LEN];
	int	i;

	if(num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if(get_param(param, 1, mode, sizeof(mode)) != 0)
		mode[0] = '\0';

	if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "total");
	}

	for(i=0; fl[i].mode!=0; i++)
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
			return (fl[i].function)(cmd, param, flags, result);

	return SYSINFO_RET_FAIL;
}
