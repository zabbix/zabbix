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

static ZBX_PERF_STAT_DATA	*ppsd = NULL;
static ZBX_MUTEX		perfstat_access;

/******************************************************************************
 *                                                                            *
 * Comments: counter failed or disappeared, dismiss all previous values       *
 *                                                                            *
 ******************************************************************************/
static void	deactivate_perf_counter(PERF_COUNTER_DATA *counter)
{
	counter->status = PERF_COUNTER_NOTSUPPORTED;
	counter->value_count = 0;
	counter->value_current = -1;
	counter->olderRawValue = 0;
	counter->sum = 0;
}

/******************************************************************************
 *                                                                            *
 * Comments: if the specified counter exists or a new is successfully         *
 *           added, a pointer to that counter is returned, NULL otherwise     *
 *                                                                            *
 ******************************************************************************/
PERF_COUNTER_DATA	*add_perf_counter(const char *name, const char *counterpath, int interval)
{
	const char		*__function_name = "add_perf_counter";
	PERF_COUNTER_DATA	*cptr;
	char			*alias_name;
	PDH_STATUS		pdh_status;
	int			result = FAIL;

	assert(counterpath);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() counter:'%s' interval:%d", __function_name, counterpath, interval);

	if (NULL == ppsd->pdh_query)
	{
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: collector is not started!", counterpath);
		return NULL;
	}

	if (1 > interval || 900 < interval)
	{
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: interval value out of range", counterpath);
		return NULL;
	}

	for (cptr = ppsd->pPerfCounterList; ; cptr = cptr->next)
	{
		/* add new parameters */
		if (NULL == cptr)
		{
			cptr = (PERF_COUNTER_DATA *)zbx_malloc(cptr, sizeof(PERF_COUNTER_DATA));

			/* initialize the counter */
			memset(cptr, 0, sizeof(PERF_COUNTER_DATA));
			if (NULL != name)
				cptr->name = strdup(name);
			cptr->counterpath = strdup(counterpath);
			cptr->interval = interval;
			cptr->value_current = -1;
			cptr->value_array = (double *)zbx_malloc(cptr->value_array, sizeof(double) * interval);

			/* add the counter to the query */
			pdh_status = zbx_PdhAddCounter(__function_name, cptr, ppsd->pdh_query, counterpath, &cptr->handle);

			zbx_mutex_lock(&perfstat_access);
			cptr->next = ppsd->pPerfCounterList;
			ppsd->pPerfCounterList = cptr;
			zbx_mutex_unlock(&perfstat_access);

			if (ERROR_SUCCESS != pdh_status && PDH_CSTATUS_NO_INSTANCE != pdh_status)
			{
				zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: invalid format", counterpath);
				cptr = NULL;	/* indicate a failure */
			}

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

/******************************************************************************
 *                                                                            *
 * Comments: counter is removed from the collector and                        *
 *           the memory is freed - do not use it again                        *
 *                                                                            *
 ******************************************************************************/
void	remove_perf_counter(PERF_COUNTER_DATA *counter)
{
	PERF_COUNTER_DATA	*cptr;

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
	zbx_free(counter->value_array);
	zbx_free(counter);
}

static void	free_perf_counter_list()
{
	PERF_COUNTER_DATA	*cptr;

	zbx_mutex_lock(&perfstat_access);

	while (NULL != ppsd->pPerfCounterList)
	{
		cptr = ppsd->pPerfCounterList;
		ppsd->pPerfCounterList = cptr->next;

		zbx_free(cptr->name);
		zbx_free(cptr->counterpath);
		zbx_free(cptr->value_array);
		zbx_free(cptr);
	}

	zbx_mutex_unlock(&perfstat_access);
}

/******************************************************************************
 *                                                                            *
 * Comments: must be called only for PERF_COUNTER_ACTIVE counters,            *
 *           interval must be less than or equal to counter->interval         *
 *                                                                            *
 ******************************************************************************/
double	compute_average_value(const char *function, PERF_COUNTER_DATA *counter, int interval)
{
	double	sum = 0;
	int	i, j, count;

	if (PERF_COUNTER_ACTIVE != counter->status || interval > counter->interval)
		return 0;

	if (USE_DEFAULT_INTERVAL == interval)
		return counter->sum / (double)counter->value_count;

	/* compute the average manually for custom intervals */
	i = counter->value_current;
	count = (counter->value_count < interval ? counter->value_count : interval);

	/* cycle backwards through the circular buffer of values */
	for (j = 0; j < count; j++, i = (0 < i ? i - 1 : counter->interval - 1))
		sum += counter->value_array[i];

	return sum / (double)count;
}

int	init_perf_collector(ZBX_PERF_STAT_DATA *pperf)
{
	const char	*__function_name = "init_perf_collector";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ppsd = pperf;

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&perfstat_access, ZBX_MUTEX_PERFSTAT))
	{
		zbx_error("unable to create mutex for performance counters");
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
	PERF_COUNTER_DATA	*cptr;

	if (NULL == ppsd->pdh_query)
		return;

	for (cptr = ppsd->pPerfCounterList; cptr != NULL; cptr = cptr->next)
	{
		if (NULL != cptr->handle)
		{
			PdhRemoveCounter(cptr->handle);
			cptr->handle = NULL;
		}
	}

	PdhCloseQuery(ppsd->pdh_query);
	ppsd->pdh_query = NULL;

	free_perf_counter_list();

	zbx_mutex_destroy(&perfstat_access);
}

void	collect_perfstat()
{
	const char		*__function_name = "collect_perfstat";
	PERF_COUNTER_DATA	*cptr;
	PDH_STATUS		pdh_status;
	time_t			now;
	PDH_FMT_COUNTERVALUE	value;

	if (NULL == ppsd->pdh_query)	/* collector is not started */
		return;

	if (NULL == ppsd->pPerfCounterList)	/* no counters */
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	/* refresh unsupported counters */
	if (ppsd->nextcheck <= now)
	{
		for (cptr = ppsd->pPerfCounterList; NULL != cptr; cptr = cptr->next)
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

		zabbix_log(LOG_LEVEL_DEBUG, "call to PdhCollectQueryData() failed: %s",
				strerror_from_module(pdh_status, L"PDH.DLL"));

		return;
	}

	/* get the raw values */
	for (cptr = ppsd->pPerfCounterList; NULL != cptr; cptr = cptr->next)
	{
		if (PERF_COUNTER_NOTSUPPORTED == cptr->status)
			continue;

		if (ERROR_SUCCESS != zbx_PdhGetRawCounterValue(__function_name, cptr->counterpath,
				cptr->handle, &cptr->rawValues[cptr->olderRawValue]))
		{
			deactivate_perf_counter(cptr);
			continue;
		}

		cptr->olderRawValue = (cptr->olderRawValue + 1) % 2;

		pdh_status = PdhCalculateCounterFromRawValue(
				cptr->handle,
				PDH_FMT_DOUBLE,
				&cptr->rawValues[(cptr->olderRawValue + 1) % 2],						/* the new value */
				(PERF_COUNTER_INITIALIZED < cptr->status ? &cptr->rawValues[cptr->olderRawValue] : NULL),	/* the older value */
				&value);

		if (ERROR_SUCCESS == pdh_status)
		{
			cptr->status = PERF_COUNTER_ACTIVE;
			cptr->value_current = (cptr->value_current + 1) % cptr->interval;
			if (cptr->value_count == cptr->interval)
				cptr->sum -= cptr->value_array[cptr->value_current];	/* remove the oldest value, value_count will not increase */
			cptr->value_array[cptr->value_current] = value.doubleValue;
			cptr->sum += cptr->value_array[cptr->value_current];
			if (cptr->value_count < cptr->interval)
				cptr->value_count++;
		}
		else if (PDH_CSTATUS_INVALID_DATA == pdh_status)
		{
			/* some (e.g., rate) counters require two raw values, MSDN lacks documentation */
			/* about what happens but tests show that PDH_CSTATUS_INVALID_DATA is returned */

			cptr->status = PERF_COUNTER_GET_SECOND_VALUE;
		}
		else
			deactivate_perf_counter(cptr);
	}
}

#endif	/* _WINDOWS */
