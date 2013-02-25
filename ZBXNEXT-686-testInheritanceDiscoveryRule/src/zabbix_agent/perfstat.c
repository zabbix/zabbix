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
#include "stats.h"
#include "perfstat.h"
#include "alias.h"
#include "log.h"
#include "mutexs.h"

ZBX_PERF_STAT_DATA	ppsd;
static ZBX_MUTEX	perfstat_access = ZBX_MUTEX_NULL;

/******************************************************************************
 *                                                                            *
 * Comments: counter failed or disappeared, dismiss all previous values       *
 *                                                                            *
 ******************************************************************************/
static void	deactivate_perf_counter(PERF_COUNTER_DATA *counter)
{
	zabbix_log(LOG_LEVEL_DEBUG, "deactivate_perf_counter() counterpath:'%s'", counter->counterpath);

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

	if (SUCCEED != perf_collector_started())
	{
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: collector is not started!", counterpath);
		return NULL;
	}

	if (1 > interval || 900 < interval)
	{
		zabbix_log(LOG_LEVEL_WARNING, "PerfCounter '%s' FAILED: interval value out of range", counterpath);
		return NULL;
	}

	for (cptr = ppsd.pPerfCounterList; ; cptr = cptr->next)
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
			pdh_status = zbx_PdhAddCounter(__function_name, cptr, ppsd.pdh_query, counterpath, &cptr->handle);

			zbx_mutex_lock(&perfstat_access);
			cptr->next = ppsd.pPerfCounterList;
			ppsd.pPerfCounterList = cptr;
			zbx_mutex_unlock(&perfstat_access);

			if (ERROR_SUCCESS != pdh_status && PDH_CSTATUS_NO_INSTANCE != pdh_status)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot add performance counter \"%s\": invalid format",
						counterpath);
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
		zabbix_log(LOG_LEVEL_DEBUG, "%s() counter '%s' already exists", __function_name, counterpath);
	}
	else if (NULL != name)
	{
		alias_name = zbx_dsprintf(NULL, "__UserPerfCounter[%s]", name);
		add_alias(name, alias_name);
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

	if (NULL == counter || NULL == ppsd.pPerfCounterList)
		return;

	zbx_mutex_lock(&perfstat_access);

	if (counter == ppsd.pPerfCounterList)
	{
		ppsd.pPerfCounterList = counter->next;
	}
	else
	{
		for (cptr = ppsd.pPerfCounterList; ; cptr = cptr->next)
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

	while (NULL != ppsd.pPerfCounterList)
	{
		cptr = ppsd.pPerfCounterList;
		ppsd.pPerfCounterList = cptr->next;

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
double	compute_average_value(PERF_COUNTER_DATA *counter, int interval)
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

int	init_perf_collector(int multithreaded)
{
	const char	*__function_name = "init_perf_collector";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 != multithreaded)
	{
		if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&perfstat_access, ZBX_MUTEX_PERFSTAT))
		{
			zbx_error("cannot create mutex for performance counters");
			exit(EXIT_FAILURE);
		}
	}

	if (ERROR_SUCCESS != zbx_PdhOpenQuery(__function_name, &ppsd.pdh_query))
		goto out;

	ppsd.nextcheck = time(NULL) + UNSUPPORTED_REFRESH_PERIOD;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	perf_collector_started()
{
	return (NULL != ppsd.pdh_query ? SUCCEED : FAIL);
}

void	free_perf_collector()
{
	PERF_COUNTER_DATA	*cptr;

	if (SUCCEED != perf_collector_started())
		return;

	for (cptr = ppsd.pPerfCounterList; cptr != NULL; cptr = cptr->next)
	{
		if (NULL != cptr->handle)
		{
			PdhRemoveCounter(cptr->handle);
			cptr->handle = NULL;
		}
	}

	PdhCloseQuery(ppsd.pdh_query);
	ppsd.pdh_query = NULL;

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

	if (SUCCEED != perf_collector_started())
		return;

	if (NULL == ppsd.pPerfCounterList)	/* no counters */
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	/* refresh unsupported counters */
	if (ppsd.nextcheck <= now)
	{
		for (cptr = ppsd.pPerfCounterList; NULL != cptr; cptr = cptr->next)
 		{
			if (PERF_COUNTER_NOTSUPPORTED != cptr->status)
				continue;

			zbx_PdhAddCounter(__function_name, cptr, ppsd.pdh_query, cptr->counterpath, &cptr->handle);
		}

		ppsd.nextcheck = now + UNSUPPORTED_REFRESH_PERIOD;
	}

	/* query for new data */
	if (ERROR_SUCCESS != (pdh_status = PdhCollectQueryData(ppsd.pdh_query)))
	{
		for (cptr = ppsd.pPerfCounterList; NULL != cptr; cptr = cptr->next)
		{
			if (PERF_COUNTER_NOTSUPPORTED != cptr->status)
				deactivate_perf_counter(cptr);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() call to PdhCollectQueryData() failed: %s",
				__function_name, strerror_from_module(pdh_status, L"PDH.DLL"));

		goto out;
	}

	/* get the raw values */
	for (cptr = ppsd.pPerfCounterList; NULL != cptr; cptr = cptr->next)
	{
		if (PERF_COUNTER_NOTSUPPORTED == cptr->status)
			continue;

		if (ERROR_SUCCESS != zbx_PdhGetRawCounterValue(__function_name, cptr->counterpath,
				cptr->handle, &cptr->rawValues[cptr->olderRawValue]))
		{
			deactivate_perf_counter(cptr);
			continue;
		}

		if (PERF_COUNTER_INITIALIZED < cptr->status)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() counterpath:'%s' old first:%I64d second:%I64d",
					__function_name, cptr->counterpath,
					cptr->rawValues[(cptr->olderRawValue + 1) % 2].FirstValue,
					cptr->rawValues[(cptr->olderRawValue + 1) % 2].SecondValue);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() counterpath:'%s' new first:%I64d second:%I64d",
					__function_name, cptr->counterpath,
					cptr->rawValues[cptr->olderRawValue].FirstValue,
					cptr->rawValues[cptr->olderRawValue].SecondValue);
		}

		cptr->olderRawValue = (cptr->olderRawValue + 1) % 2;

		pdh_status = PdhCalculateCounterFromRawValue(cptr->handle, PDH_FMT_DOUBLE,
				&cptr->rawValues[(cptr->olderRawValue + 1) % 2],						/* the new value */
				(PERF_COUNTER_INITIALIZED < cptr->status ? &cptr->rawValues[cptr->olderRawValue] : NULL),	/* the older value */
				&value);

		if (ERROR_SUCCESS == pdh_status && PDH_CSTATUS_VALID_DATA != value.CStatus && PDH_CSTATUS_NEW_DATA != value.CStatus)
			pdh_status = value.CStatus;

		if (PDH_CSTATUS_INVALID_DATA == pdh_status)
		{
			/* some (e.g., rate) counters require two raw values, MSDN lacks documentation */
			/* about what happens but tests show that PDH_CSTATUS_INVALID_DATA is returned */

			cptr->status = PERF_COUNTER_GET_SECOND_VALUE;
			continue;
		}

		if (PDH_CALC_NEGATIVE_DENOMINATOR == pdh_status)
		{
			/* This counter type shows the average percentage of active time observed during the sample   */
			/* interval. This is an inverse counter. Inverse counters are calculated by monitoring the    */
			/* percentage of time that the service was inactive and then subtracting that value from 100  */
			/* percent. The formula used to calculate the counter value is:                               */
			/* (1 - (inactive time delta) / (total time delta)) x 100                                     */
			/* For some unknown reason sometimes the collected row values indicate that inactive delta is */
			/* greater than total time delta. There must be some kind of bug in how performance counters  */
			/* work. When this happens function PdhCalculateCounterFromRawValue() returns error           */
			/* PDH_CALC_NEGATIVE_DENOMINATOR. Basically this means that an item was inactive all the time */
			/* so we set the return value to 0.0 and change status to indicate success.                   */
			/* More info: technet.microsoft.com/en-us/library/cc757283(WS.10).aspx                        */

			zabbix_log(LOG_LEVEL_DEBUG, "%s() counterpath:'%s'"
					" negative denominator error is treated as 0.0",
					__function_name, cptr->counterpath);

			pdh_status = ERROR_SUCCESS;
			value.doubleValue = 0.0;	/* 100% inactive */
		}

		if (ERROR_SUCCESS == pdh_status)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s' calculated value:" ZBX_FS_DBL, __function_name,
					cptr->counterpath, value.doubleValue);

			cptr->status = PERF_COUNTER_ACTIVE;
			cptr->value_current = (cptr->value_current + 1) % cptr->interval;
			if (cptr->value_count == cptr->interval)
				cptr->sum -= cptr->value_array[cptr->value_current];	/* remove the oldest value, value_count will not increase */
			cptr->value_array[cptr->value_current] = value.doubleValue;
			cptr->sum += cptr->value_array[cptr->value_current];
			if (cptr->value_count < cptr->interval)
				cptr->value_count++;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot calculate performance counter value \"%s\": %s",
					cptr->counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));

			deactivate_perf_counter(cptr);
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
