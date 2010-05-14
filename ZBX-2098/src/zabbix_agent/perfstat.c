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
#include "mutexs.h"

#ifdef _WINDOWS
	#include "perfmon.h"

/* Static data */
static ZBX_PERF_STAT_DATA *ppsd = NULL;
static ZBX_MUTEX perfstat_access;

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
int	add_perf_counter(const char *name, const char *counterPath, int interval)
{
	PERF_COUNTERS	*cptr;
	PDH_STATUS	status;
	char		*alias_name;
	LPTSTR		wcounterPath;
	int		result = FAIL;

	assert(counterPath);

	zabbix_log(LOG_LEVEL_DEBUG, "In add_perf_counter() [counter:%s] [interval:%d]",
			counterPath, interval);

	if (NULL == ppsd->pdh_query) {
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: Collector is not started!",
				counterPath);
		return FAIL;
	}

	if (interval < 1 || interval > 900) {
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: Interval value out of range",
				counterPath);
		return FAIL;
	}

	for (cptr = ppsd->pPerfCounterList; ; cptr = cptr->next) {
		/* Add new parameters */
		if (NULL == cptr) {
			cptr = (PERF_COUNTERS *)zbx_malloc(cptr, sizeof(PERF_COUNTERS));

			memset(cptr, 0, sizeof(PERF_COUNTERS));
			if (NULL != name)
				cptr->name	= strdup(name);
			cptr->counterPath	= strdup(counterPath);
			cptr->interval		= interval;
			cptr->rawValueArray	= (PDH_RAW_COUNTER *)zbx_malloc(cptr->rawValueArray,
							sizeof(PDH_RAW_COUNTER) * interval);
			cptr->CurrentNum	= 1;

			wcounterPath = zbx_utf8_to_unicode(cptr->counterPath);

			/* Add user counters to query */
			if (ERROR_SUCCESS != (status = PdhAddCounter(ppsd->pdh_query, wcounterPath, 0, &cptr->handle)))
			{
				cptr->status	= ITEM_STATUS_NOTSUPPORTED;
				cptr->error	= zbx_dsprintf(cptr->error, "%s", strerror_from_module(status, L"PDH.DLL"));
				zbx_rtrim(cptr->error, " \r\n");

				zabbix_log( LOG_LEVEL_ERR, "Unable to add performance counter '%s' to query: %s",
						cptr->counterPath,
						cptr->error);
			}
			else
			{
				cptr->status	= ITEM_STATUS_ACTIVE;
				zabbix_log(LOG_LEVEL_DEBUG, "PerfCounter '%s' successfully added. Interval %d seconds",
						cptr->counterPath, interval);
			}

			zbx_free(wcounterPath);
			zbx_mutex_lock(&perfstat_access);

			cptr->next		= ppsd->pPerfCounterList;
			ppsd->pPerfCounterList	= cptr;

			zbx_mutex_unlock(&perfstat_access);

			result = SUCCEED;
			break;
		}

		if (NULL != name && 0 == strcmp(cptr->name, name))
			break;

		if (NULL == name && NULL == cptr->name && 0 == strcmp(cptr->counterPath, counterPath) && cptr->interval == interval)
			break;
	}

	if (FAIL == result)
	{
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: Counter already exists",
				counterPath);
	}
	else if (NULL != name)
	{
		alias_name = zbx_dsprintf(NULL, "__UserPerfCounter[%s]", name);

		result = add_alias(name, alias_name);

		zbx_free(alias_name);
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
int	add_perfs_from_config(const char *line)
{
	char	name[MAX_STRING_LEN],
		counterPath[PDH_MAX_COUNTER_PATH],
		interval[MAX_STRING_LEN];
	LPTSTR	wcounterPath = NULL;
	
	assert(line);

	if (num_param(line) != 3)
		goto lbl_syntax_error;

        if (0 != get_param(line, 1, name, sizeof(name)))
		goto lbl_syntax_error;

        if (0 != get_param(line, 2, counterPath, sizeof(counterPath)))
		goto lbl_syntax_error;

	if (NULL == (wcounterPath = zbx_acp_to_unicode(counterPath)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "PerfCounter \"%s\" could not be converted to UNICODE.", counterPath);
		return FAIL;
	}
	
	if (FAIL == zbx_unicode_to_utf8_static(wcounterPath, counterPath, PDH_MAX_COUNTER_PATH))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "PerfCounter \"%s\" could not be converted to UTF-8.", counterPath);
		zbx_free(wcounterPath);
		return FAIL;
	}
	
	zbx_free(wcounterPath);

        if (0 != get_param(line, 3, interval, sizeof(interval)))
		goto lbl_syntax_error;

	if (FAIL == check_counter_path(counterPath))
		goto lbl_syntax_error;

	return add_perf_counter(name, counterPath, atoi(interval));
lbl_syntax_error:
	zabbix_log(LOG_LEVEL_WARNING, "PerfCounter \"%s\" FAILED: Invalid format.",
			line);

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
	PERF_COUNTERS	*cptr;

	zbx_mutex_lock(&perfstat_access);

	while (NULL != ppsd->pPerfCounterList) {
		cptr = ppsd->pPerfCounterList;
		ppsd->pPerfCounterList = cptr->next;

		zbx_free(cptr->name);
		zbx_free(cptr->counterPath);
		zbx_free(cptr->rawValueArray);
		zbx_free(cptr->error);
		zbx_free(cptr);
	}
	zbx_mutex_unlock(&perfstat_access);
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
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	init_perf_collector(ZBX_PERF_STAT_DATA *pperf)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In init_perf_collector()");

	ppsd = pperf;

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&perfstat_access, ZBX_MUTEX_PERFSTAT))
	{
		zbx_error("Unable to create mutex for performance counters");
		exit(FAIL);
	}

	if (ERROR_SUCCESS != PdhOpenQuery(NULL, 0, &ppsd->pdh_query)) {
		zabbix_log(LOG_LEVEL_ERR, "Call to PdhOpenQuery() failed: %s", strerror_from_system(GetLastError()));
		return FAIL;
	}

	ppsd->nextcheck = time(NULL) + UNSUPPORTED_REFRESH_PERIOD;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: close_perf_collector                                             *
 *                                                                            *
 * Purpose: Clear state of data calculation                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	close_perf_collector()
{
	PERF_COUNTERS *cptr;

	if (NULL == ppsd->pdh_query)
		return;

	for (cptr = ppsd->pPerfCounterList; cptr != NULL; cptr = cptr->next)
		if (NULL != cptr->handle) {
			PdhRemoveCounter(cptr->handle);
			cptr->handle = NULL;
		}

	PdhCloseQuery(ppsd->pdh_query);
	ppsd->pdh_query = NULL;

	perfs_list_free();

	zbx_mutex_destroy(&perfstat_access);
}

/******************************************************************************
 *                                                                            *
 * Function: collect_perfstat                                                 *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	collect_perfstat()
{
	PERF_COUNTERS	*cptr;
	PDH_STATISTICS	statData;
	PDH_STATUS	status;
	time_t		now;
	LPTSTR		wcounterPath;

	if (NULL == ppsd->pdh_query)	/* collector is not started */
		return;

	if (NULL == ppsd->pPerfCounterList)	/* no counters */
		return;

	now = time(NULL);

	/* refresh unsupported counters */
	if (ppsd->nextcheck <= now)
	{
		for (cptr = ppsd->pPerfCounterList; cptr != NULL; cptr = cptr->next)
 		{
			if (cptr->status != ITEM_STATUS_NOTSUPPORTED)
				continue;

			wcounterPath = zbx_utf8_to_unicode(cptr->counterPath);

			/* Add user counters to query */
			if (ERROR_SUCCESS != (status = PdhAddCounter(ppsd->pdh_query,
					wcounterPath, 0, &cptr->handle)))
			{
				cptr->status    = ITEM_STATUS_NOTSUPPORTED;
				cptr->error     = zbx_dsprintf(cptr->error, "%s",
						strerror_from_module(status, L"PDH.DLL"));
				zbx_rtrim(cptr->error, " \r\n");

				zabbix_log(LOG_LEVEL_ERR, "Unable to add performance"
						" counter '%s' to query: %s",
						cptr->counterPath,
						cptr->error);
			}
			else
			{
				cptr->status    = ITEM_STATUS_ACTIVE;
				zabbix_log(LOG_LEVEL_DEBUG, "PerfCounter '%s' successfully"
						" added. Interval %d seconds",
						cptr->counterPath, cptr->interval);
			}

			zbx_free(wcounterPath);
		}

		ppsd->nextcheck = now + UNSUPPORTED_REFRESH_PERIOD;
	}

	if (ERROR_SUCCESS != (status = PdhCollectQueryData(ppsd->pdh_query)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Call to PdhCollectQueryData() failed: %s",
				strerror_from_module(status, L"PDH.DLL"));
		return;
	}

	/* Process user-defined counters */
	for ( cptr = ppsd->pPerfCounterList; cptr != NULL; cptr = cptr->next )
	{
		if (cptr->status == ITEM_STATUS_NOTSUPPORTED)	/* Inactive counter? */
			continue;

		PdhGetRawCounterValue(
			cptr->handle,
			NULL,
			&cptr->rawValueArray[cptr->CurrentCounter]
			);

		cptr->CurrentCounter++;
		cptr->CurrentCounter = cptr->CurrentCounter % cptr->interval;

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
#endif /* _WINDOWS */
