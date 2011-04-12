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
#include "stats.h"
#include "perfstat.h"
#include "alias.h"
#include "log.h"
#include "mutexs.h"

#ifdef _WINDOWS

/* Static data */
static ZBX_PERF_STAT_DATA *ppsd = NULL;
static ZBX_MUTEX perfstat_access;

/* counter failed or disappeared, dismiss all previous values */
static void	deactivate_perf_counter(PERF_COUNTERS *counter)
{
	counter->status = PERF_COUNTER_NOTSUPPORTED;
	counter->CurrentCounter = 0;
	counter->CurrentNum = 0;
}

/* if the specified exists or a new is successfully added, a pointer to that counter is returned, NULL otherwise  */
PERF_COUNTERS	*add_perf_counter(const char *name, const char *counterpath, int interval)
{
	const char	*__function_name = "add_perf_counter";
	PERF_COUNTERS	*cptr;
	char		*alias_name;
	PDH_STATUS	pdh_status;
	int		result = FAIL;

	assert(counterpath);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [counter:%s] [interval:%d]", __function_name, counterpath, interval);

	if (NULL == ppsd->pdh_query)
	{
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: Collector is not started!", counterpath);
		return NULL;
	}

	if (1 > interval || 900 < interval)
	{
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: Interval value out of range", counterpath);
		return NULL;
	}

	for (cptr = ppsd->pPerfCounterList; ; cptr = cptr->next)
	{
		/* Add new parameters */
		if (NULL == cptr)
		{
			cptr = (PERF_COUNTERS *)zbx_malloc(cptr, sizeof(PERF_COUNTERS));

			memset(cptr, 0, sizeof(PERF_COUNTERS));
			if (NULL != name)
				cptr->name = strdup(name);
			cptr->counterpath = strdup(counterpath);
			cptr->interval = interval;
			cptr->rawValueArray = (PDH_RAW_COUNTER *)zbx_malloc(cptr->rawValueArray, sizeof(PDH_RAW_COUNTER) * interval);

			/* Add the counter to the query */
			pdh_status = zbx_PdhAddCounter(__function_name, cptr, ppsd->pdh_query, counterpath, &cptr->handle);

			zbx_mutex_lock(&perfstat_access);
			cptr->next = ppsd->pPerfCounterList;
			ppsd->pPerfCounterList = cptr;
			zbx_mutex_unlock(&perfstat_access);

			if (ERROR_SUCCESS != pdh_status && PDH_CSTATUS_NO_INSTANCE != pdh_status)
				cptr = NULL; /* indicate a failure */

			result = SUCCEED;
			break;
		}

		if (NULL != name && 0 == strcmp(cptr->name, name))
			break;

		if (NULL == name && 0 == strcmp(cptr->counterpath, counterpath) && cptr->interval == interval)
			break;
	}

	if (FAIL == result)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() counter '%s' (interval: %d) already exists", __function_name, counterpath, interval);
	}
	else if (NULL != name)
	{
		alias_name = zbx_dsprintf(NULL, "__UserPerfCounter[%s]", name);
		result = add_alias(name, alias_name);
		zbx_free(alias_name);
	}

	return cptr;
}

int	add_perfs_from_config(const char *line)
{
	char	name[MAX_STRING_LEN], counterpath[PDH_MAX_COUNTER_PATH], interval[MAX_STRING_LEN];
	LPTSTR	wcounterPath = NULL;
	int	ret = FAIL;
	
	assert(line);

	if (3 < num_param(line))
		goto lbl_syntax_error;

        if (0 != get_param(line, 1, name, sizeof(name)))
		goto lbl_syntax_error;

	if (0 != get_param(line, 2, counterpath, sizeof(counterpath)))
		goto lbl_syntax_error;

        if (0 != get_param(line, 3, interval, sizeof(interval)))
		goto lbl_syntax_error;

	wcounterPath = zbx_acp_to_unicode(counterpath);
	zbx_unicode_to_utf8_static(wcounterPath, counterpath, PDH_MAX_COUNTER_PATH);
	zbx_free(wcounterPath);

	if (FAIL == check_counter_path(counterpath))
		goto lbl_syntax_error;

	if (NULL != add_perf_counter(name, counterpath, atoi(interval)))
		ret = SUCCEED;

	return  ret;
lbl_syntax_error:
	zabbix_log(LOG_LEVEL_WARNING, "PerfCounter \"%s\" FAILED: Invalid format.", line);

	return FAIL;
}

/* counter is removed from the collector and the memory is freed - do not use it again */
void	remove_perf_counter(PERF_COUNTERS *counter)
{
	PERF_COUNTERS	*cptr;

	if (NULL == counter || NULL == ppsd->pPerfCounterList)
		return;

	zbx_mutex_lock(&perfstat_access);
	if (counter == ppsd->pPerfCounterList)
	{
		ppsd->pPerfCounterList = counter->next;
	}
	else
	{
		for (cptr = ppsd->pPerfCounterList; ; cptr = cptr->next)
		{
			if (cptr->next == counter)
			{
				cptr->next = counter->next;
				break;
			}
		}
	}
	zbx_mutex_unlock(&perfstat_access);

	PdhRemoveCounter(counter->handle);
	zbx_free(counter->name);
	zbx_free(counter->counterpath);
	zbx_free(counter->rawValueArray);
	zbx_free(counter);
}

void	perfs_list_free()
{
	PERF_COUNTERS	*cptr;

	zbx_mutex_lock(&perfstat_access);

	while (NULL != ppsd->pPerfCounterList)
	{
		cptr = ppsd->pPerfCounterList;
		ppsd->pPerfCounterList = cptr->next;

		zbx_free(cptr->name);
		zbx_free(cptr->counterpath);
		zbx_free(cptr->rawValueArray);
		zbx_free(cptr);
	}
	zbx_mutex_unlock(&perfstat_access);
}

/* if succeeds, counter->status is set to PERF_COUNTER_ACTIVE */
double	compute_counter_statistics(const char *function, PERF_COUNTERS *counter, int interval)
{
	PDH_STATISTICS	statData;
	PDH_STATUS	pdh_status;

	if (PERF_COUNTER_ACTIVE != counter->status)
		return 0;

	if (USE_DEFAULT_INTERVAL == interval)
		interval = counter->interval;

	if (ERROR_SUCCESS != (pdh_status = PdhComputeCounterStatistics(
		counter->handle,
		PDH_FMT_DOUBLE,
		(counter->CurrentNum < interval) ? 0 : (counter->CurrentCounter - interval) % counter->interval,
		(counter->CurrentNum < interval) ? counter->CurrentNum : interval,
		counter->rawValueArray,
		&statData
		)))
	{ /* rate counters need multiple values, deactivate otherwise */
		if (1 == counter->CurrentNum && PDH_CSTATUS_INVALID_DATA == pdh_status)
			counter->status = PERF_COUNTER_INITIALIZED;
		else
			deactivate_perf_counter(counter);

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): Can't calculate counter statistics \"%s\": %s",
				function, counter->counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
		return 0;
	}

	return statData.mean.doubleValue;
}

int	init_perf_collector(ZBX_PERF_STAT_DATA *pperf)
{
	const char	*__function_name = "init_perf_collector";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	ppsd = pperf;

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&perfstat_access, ZBX_MUTEX_PERFSTAT))
	{
		zbx_error("Unable to create mutex for performance counters");
		exit(FAIL);
	}

	if (ERROR_SUCCESS != zbx_PdhOpenQuery(__function_name, &ppsd->pdh_query))
		return FAIL;

	ppsd->nextcheck = time(NULL) + UNSUPPORTED_REFRESH_PERIOD;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	return SUCCEED;
}

void	free_perf_collector()
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

void	collect_perfstat()
{
	const char	*__function_name = "collect_perfstat";
	PERF_COUNTERS	*cptr;
	PDH_STATUS	pdh_status;
	time_t		now;

	if (NULL == ppsd->pdh_query) /* collector is not started */
		return;

	if (NULL == ppsd->pPerfCounterList) /* no counters */
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	now = time(NULL);

	/* refresh unsupported counters */
	if (ppsd->nextcheck <= now)
	{
		for (cptr = ppsd->pPerfCounterList; cptr != NULL; cptr = cptr->next)
 		{
			if (PERF_COUNTER_NOTSUPPORTED != cptr->status)
				continue;

			zbx_PdhAddCounter(__function_name, cptr, ppsd->pdh_query, cptr->counterpath, &cptr->handle);
		}

		ppsd->nextcheck = now + UNSUPPORTED_REFRESH_PERIOD;
	}

	/* query for new data */
	if (ERROR_SUCCESS != (pdh_status = PdhCollectQueryData(ppsd->pdh_query)))
	{
		for (cptr = ppsd->pPerfCounterList; NULL != cptr; cptr = cptr->next)
		{
			if (PERF_COUNTER_NOTSUPPORTED != cptr->status)
				deactivate_perf_counter(cptr);
		}
		zabbix_log(LOG_LEVEL_DEBUG, "Call to PdhCollectQueryData() failed: %s", strerror_from_module(pdh_status, L"PDH.DLL"));
		return;
	}

	/* get the raw values */
	for (cptr = ppsd->pPerfCounterList; NULL != cptr; cptr = cptr->next)
	{
		if (PERF_COUNTER_NOTSUPPORTED == cptr->status)
			continue;

		if (ERROR_SUCCESS != zbx_PdhGetRawCounterValue(__function_name, cptr->counterpath,
				cptr->handle, &cptr->rawValueArray[cptr->CurrentCounter]))
		{
			deactivate_perf_counter(cptr);
			continue;
		}
		cptr->status = PERF_COUNTER_ACTIVE;
		cptr->CurrentCounter = (cptr->CurrentCounter + 1) % cptr->interval;
		if (cptr->CurrentNum < cptr->interval)
			cptr->CurrentNum++;
	}
}
#endif /* _WINDOWS */
