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
#include "perfstat.h"
#include "alias.h"

#include "log.h"

#ifdef _WINDOWS
	#include "perfmon.h"
#endif /* _WINDOWS */

/* Static data */

static PERF_COUNTERS *statPerfCounterList=NULL;

/******************************************************************************
 *                                                                            *
 * Function: add_perf_counter                                                 *
 *                                                                            *
 * Purpose: Add PerfCounter in to the list of                                 *
 *                                                                            *
 * Parameters: name - name of perfcounter                                     *
 *             counterPath - system perfcounter name                          *
 *             interval - update interval                                     *
 *                                                                            *
 * Return value: SUCCEED on success or FAIL in other cases                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	add_perf_counter(const char *name, const char *counterPath, int interval)
{
	PERF_COUNTERS *perfs = NULL;
	int	result = FAIL;
	char	*alias_name = NULL;

	assert(name);
	assert(counterPath);

	if( interval < 1 || interval > 1800 )
	{
		zabbix_log( LOG_LEVEL_WARNING, "PerfCounter FAILED. [%s] (Interval value out of range)", name);
		return FAIL;
	}

	for(perfs = statPerfCounterList; ; perfs=perfs->next)
	{
		/* Add new parameters */
		if(perfs == NULL)
		{
			perfs = (PERF_COUNTERS *)zbx_malloc(perfs, sizeof(PERF_COUNTERS));
			if (NULL != perfs)
			{
				memset(perfs,0,sizeof(PERF_COUNTERS));
				perfs->name		= strdup(name);
				perfs->counterPath	= strdup(counterPath);
				perfs->interval		= interval;
				perfs->rawValueArray	= (PDH_RAW_COUNTER *)zbx_malloc(perfs->rawValueArray,
								sizeof(PDH_RAW_COUNTER) * interval);
				perfs->CurrentCounter	= 0;
				perfs->CurrentNum	= 1;

				perfs->next		= statPerfCounterList;
				statPerfCounterList	= perfs;

				zabbix_log( LOG_LEVEL_DEBUG, "PerfCounter added. [%s] [%s] [%i]", name, counterPath, interval);
				result = SUCCEED;
			}
			break;
		}

		/* Replace existing parameters */
		if (strcmp(perfs->name, name) == 0)
		{
			zbx_free(perfs->name);
			zbx_free(perfs->counterPath);
			zbx_free(perfs->rawValueArray);

			memset(perfs,0,sizeof(PERF_COUNTERS));
			perfs->name		= strdup(name);
			perfs->counterPath	= strdup(counterPath);
			perfs->interval		= interval;
			perfs->rawValueArray	= (PDH_RAW_COUNTER *)zbx_malloc(perfs->rawValueArray,
							sizeof(PDH_RAW_COUNTER) * interval);
			perfs->CurrentCounter	= 0;
			perfs->CurrentNum	= 1;

			perfs->next		= statPerfCounterList;
			statPerfCounterList		= perfs;

			zabbix_log( LOG_LEVEL_DEBUG, "PerfCounter replaced. [%s] [%s] [%i]", name, counterPath, interval);
			result = SUCCEED;
		}
	}

	if( SUCCEED == result )
	{
		alias_name = zbx_dsprintf(NULL, "__UserPerfCounter[%s]", name);

		result = add_alias(name, alias_name);

		zbx_free(alias_name);
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "PerfCounter FAILED. [%s] -> [%s]", name, counterPath);
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: add_perfs_from_config                                            *
 *                                                                            *
 * Purpose: parse config parameter 'PerfCounter'                              *
 *                                                                            *
 * Parameters: line - line for parsing                                        *
 *                                                                            *
 * Return value: SUCCEED on success or FAIL in other cases                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: format of input line is - name,"perfcounter name",interval       *
 *                                                                            *
 ******************************************************************************/
int	add_perfs_from_config(char *line)
{
	char 
		*name = NULL,
		*counterPath = NULL,
		*interval = NULL;

	name = line;
	counterPath = strchr(line,',');
	if(NULL == counterPath)
		goto lbl_syntax_error;

	*counterPath = '\0';
	counterPath++;

	if ( *counterPath != '"' )
		goto lbl_syntax_error;

	counterPath++;

	interval = strrchr(counterPath,',');
	if(NULL == interval)
		goto lbl_syntax_error;

	interval--;
	if ( *interval != '"' )
		goto lbl_syntax_error;

	*interval = '\0';
	interval++;

	*interval = '\0';
	interval++;

	return add_perf_counter(name, counterPath, atoi(interval));

lbl_syntax_error:

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: perfs_list_free                                                  *
 *                                                                            *
 * Purpose: free PerfCounter list                                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	perfs_list_free(void)
{
	PERF_COUNTERS	*curr;
	PERF_COUNTERS	*next;
		
	next = statPerfCounterList;
	while(next!=NULL)
	{
		curr = next;
		next = curr->next;
		zbx_free(curr->name);
		zbx_free(curr->counterPath);
		zbx_free(curr->rawValueArray);
		zbx_free(curr);
	}
	statPerfCounterList = NULL;
}




/******************************************************************************
 *                                                                            *
 * Function: init_perf_collector                                              *
 *                                                                            *
 * Purpose: Initialize statistic structure and prepare state                  *
 *          for data calculation                                              *
 *                                                                            *
 * Parameters:  pperf - pointer to the structure                              *
 *                      of ZBX_PERF_STAT_DATA type                            *
 *                                                                            *
 * Return value: If the function succeeds, the return 0,                      *
 *               great than 0 on an error                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int	init_perf_collector(ZBX_PERF_STAT_DATA *pperf)
{
#ifdef _WINDOWS

	PERF_COUNTERS	*cptr = NULL;
	PDH_STATUS	status;
	int		is_empty = 1;

	memset(pperf, 0, sizeof(ZBX_PERF_STAT_DATA));

	pperf->pPerfCounterList = statPerfCounterList;

	if (PdhOpenQuery(NULL,0,&pperf->pdh_query)!=ERROR_SUCCESS)
	{
		zabbix_log( LOG_LEVEL_ERR, "Call to PdhOpenQuery() failed: %s", strerror_from_system(GetLastError()));
		return 1;
	}

	/* Add user counters to query */
	for ( cptr = statPerfCounterList; cptr != NULL; cptr = cptr->next )
	{
		if (ERROR_SUCCESS != (status = PdhAddCounter(
			pperf->pdh_query, 
			cptr->counterPath, 0, 
			&cptr->handle)))
		{
			cptr->interval	= -1;   /* Flag for unsupported counters */
			cptr->lastValue	= NOTSUPPORTED;

			zabbix_log( LOG_LEVEL_ERR, "Unable to add performance counter \"%s\" to query: %s", cptr->counterPath, strerror_from_module(status,"PDH.DLL"));
		}
		else
		{
			is_empty = 0;
		}
	}

	if ( is_empty )
	{
		close_perf_collector(pperf);
	}

#endif /* _WINDOWS */

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: close_perf_collector                                             *
 *                                                                            *
 * Purpose: Clear state of data calculation                                   *
 *                                                                            *
 * Parameters:  pperf - pointer to the structure                              *
 *                      of ZBX_PERF_STAT_DATA type                            *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

void	close_perf_collector(ZBX_PERF_STAT_DATA *pperf)
{
#ifdef _WINDOWS
	PERF_COUNTERS *cptr = NULL;

	for ( cptr = statPerfCounterList; cptr != NULL; cptr = cptr->next )
	{
		if(cptr->handle)
		{
			PdhRemoveCounter(cptr->handle);
			cptr->handle = NULL;
		}
	}
	
	if( pperf->pdh_query )
	{
		PdhCloseQuery(pperf->pdh_query);
		pperf->pdh_query = NULL;
	}

#endif /* _WINDOWS */

}

void	collect_perfstat(ZBX_PERF_STAT_DATA *pperf)
{
#ifdef _WINDOWS
	PERF_COUNTERS	*cptr = NULL;
	PDH_STATISTICS	statData;
	PDH_STATUS	status;

	if ( pperf->pdh_query )
	{
		if ((status = PdhCollectQueryData(pperf->pdh_query)) != ERROR_SUCCESS)
		{
			zabbix_log( LOG_LEVEL_ERR, "Call to PdhCollectQueryData() failed: %s", strerror_from_module(status,"PDH.DLL"));
			return;
		}
		
		/* Process user-defined counters */
		for ( cptr = statPerfCounterList; cptr != NULL; cptr = cptr->next )
		{
			if ( cptr->handle && cptr->interval > 0 )      /* Active counter? */
			{
				PdhGetRawCounterValue(
					cptr->handle, 
					NULL, 
					&cptr->rawValueArray[cptr->CurrentCounter]
					);

				cptr->CurrentCounter++;

				if ( cptr->CurrentCounter >= cptr->interval )
					cptr->CurrentCounter = 0;

				PdhComputeCounterStatistics(
					cptr->handle,
					PDH_FMT_DOUBLE,
					(cptr->CurrentNum < cptr->interval) ? 0 : cptr->CurrentCounter,
					cptr->CurrentNum,
					cptr->rawValueArray,
					&statData
					);

				cptr->lastValue = statData.mean.doubleValue;

				if(cptr->CurrentNum < cptr->interval)
					cptr->CurrentNum++;
			}
		}
	}

#endif /* _WINDOWS */
}
