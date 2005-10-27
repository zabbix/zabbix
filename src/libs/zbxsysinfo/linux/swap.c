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

/* Solaris. */
#ifndef HAVE_SYSINFO_FREESWAP
#ifdef HAVE_SYS_SWAP_SWAPTABLE
void get_swapinfo(double *total, double *fr)
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

	/* walk thru the structs and sum up the fields */
	t = f = 0;
	ste = &(swt->swt_ent[0]);
	i = cnt;
	while (--i >= 0)
	{
		/* dont count slots being deleted */
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

int	SYSTEM_SWAP_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_FREESWAP
	struct sysinfo info;

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	if( 0 == sysinfo(&info))
	{
		result->type |= AR_DOUBLE;
#ifdef HAVE_SYSINFO_MEM_UNIT
		result->dbl = (double)info.freeswap * (double)info.mem_unit;
#else
		result->dbl = (double)info.freeswap;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
/* Solaris */
#else
#ifdef HAVE_SYS_SWAP_SWAPTABLE
	double swaptotal,swapfree;

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	get_swapinfo(&swaptotal,&swapfree);

	result->type |= AR_DOUBLE;
	result->dbl = swapfree;
	return SYSINFO_RET_OK;
#else
	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	return	SYSINFO_RET_FAIL;
#endif
#endif
}

int	SYSTEM_SWAP_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_TOTALSWAP
	struct sysinfo info;

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	if( 0 == sysinfo(&info))
	{
		result->type |= AR_DOUBLE;
#ifdef HAVE_SYSINFO_MEM_UNIT
		result->dbl = (double)info.totalswap * (double)info.mem_unit;
#else
		result->dbl = (double)info.totalswap;
#endif
		return SYSINFO_RET_OK;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}
/* Solaris */
#else
#ifdef HAVE_SYS_SWAP_SWAPTABLE
	double swaptotal,swapfree;

	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	get_swapinfo(&swaptotal,&swapfree);
	
	result->type |= AR_DOUBLE;
	result->dbl = (double)swaptotal;
	return SYSINFO_RET_OK;
#else
	assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

	return	SYSINFO_RET_FAIL;
#endif
#endif
}

int     OLD_SWAP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
        char    key[MAX_STRING_LEN];
        int     ret;

        assert(result);

        memset(result, 0, sizeof(AGENT_RESULT));

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

