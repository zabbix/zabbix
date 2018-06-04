/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
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
#include "sysinfo.h"

#define UNSUPPORTED_REFRESH_PERIOD		600

typedef struct
{
	zbx_perf_counter_data_t	*pPerfCounterList;
	PDH_HQUERY		pdh_query;
	time_t			nextcheck;	/* refresh time of not supported counters */
}
ZBX_PERF_STAT_DATA;

static ZBX_PERF_STAT_DATA	ppsd;
static ZBX_MUTEX		perfstat_access = ZBX_MUTEX_NULL;

#define LOCK_PERFCOUNTERS	zbx_mutex_lock(&perfstat_access)
#define UNLOCK_PERFCOUNTERS	zbx_mutex_unlock(&perfstat_access)

static int	perf_collector_started(void)
{
	return (NULL != ppsd.pdh_query ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Comments: counter failed or disappeared, dismiss all previous values       *
 *                                                                            *
 ******************************************************************************/
static void	deactivate_perf_counter(zbx_perf_counter_data_t *counter)
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
zbx_perf_counter_data_t	*add_perf_counter(const char *name, const char *counterpath, int interval, char **error)
{
	const char		*__function_name = "add_perf_counter";
	zbx_perf_counter_data_t	*cptr = NULL;
	PDH_STATUS		pdh_status;
	int			added = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() counter:'%s' interval:%d", __function_name, counterpath, interval);

	LOCK_PERFCOUNTERS;

	if (SUCCEED != perf_collector_started())
	{
		*error = zbx_strdup(*error, "Performance collector is not started.");
		goto out;
	}

	for (cptr = ppsd.pPerfCounterList; ; cptr = cptr->next)
	{
		/* add new parameters */
		if (NULL == cptr)
		{
			cptr = (zbx_perf_counter_data_t *)zbx_malloc(cptr, sizeof(zbx_perf_counter_data_t));

			/* initialize the counter */
			memset(cptr, 0, sizeof(zbx_perf_counter_data_t));
			if (NULL != name)
				cptr->name = zbx_strdup(NULL, name);
			cptr->counterpath = zbx_strdup(NULL, counterpath);
			cptr->interval = interval;
			cptr->value_current = -1;
			cptr->value_array = (double *)zbx_malloc(cptr->value_array, sizeof(double) * interval);

			/* add the counter to the query */
			pdh_status = zbx_PdhAddCounter(__function_name, cptr, ppsd.pdh_query, counterpath,
					&cptr->handle);

			cptr->next = ppsd.pPerfCounterList;
			ppsd.pPerfCounterList = cptr;

			if (ERROR_SUCCESS != pdh_status && PDH_CSTATUS_NO_INSTANCE != pdh_status)
			{
				*error = zbx_dsprintf(*error, "Invalid performance counter format.");
				cptr = NULL;	/* indicate a failure */
			}

			added = SUCCEED;
			break;
		}

		if (NULL != name && 0 == strcmp(cptr->name, name))
			break;

		if (NULL == name && 0 == strcmp(cptr->counterpath, counterpath) && cptr->interval == interval)
			break;
	}

	if (FAIL == added)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() counter '%s' already exists", __function_name, counterpath);
	}
	else if (NULL != name)
	{
		char	*alias_name;

		alias_name = zbx_dsprintf(NULL, "__UserPerfCounter[%s]", name);
		add_alias(name, alias_name);
		zbx_free(alias_name);
	}
out:
	UNLOCK_PERFCOUNTERS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __function_name, NULL == cptr ? "FAIL" : "SUCCEED");

	return cptr;
}

/******************************************************************************
 *                                                                            *
 * Function: extend_perf_counter_interval                                     *
 *                                                                            *
 * Purpose: extends the performance counter buffer to store the new data      *
 *          interval                                                          *
 *                                                                            *
 * Parameters: result    - [IN] the performance counter                       *
 *             interval  - [IN] the new data collection interval in seconds   *
 *                                                                            *
 ******************************************************************************/
static void	extend_perf_counter_interval(zbx_perf_counter_data_t *counter, int interval)
{
	if (interval <= counter->interval)
		return;

	counter->value_array = (double *)zbx_realloc(counter->value_array, sizeof(double) * interval);

	/* move the data to the end to keep the ring buffer intact */
	if (counter->value_current < counter->value_count)
	{
		int	i;
		double	*src, *dst;

		src = &counter->value_array[counter->interval - 1];
		dst = &counter->value_array[interval - 1];

		for (i = 0; i < counter->value_count - counter->value_current; i++)
			*dst-- = *src--;
	}

	counter->interval = interval;
}

/******************************************************************************
 *                                                                            *
 * Comments: counter is removed from the collector and                        *
 *           the memory is freed - do not use it again                        *
 *                                                                            *
 ******************************************************************************/
void	remove_perf_counter(zbx_perf_counter_data_t *counter)
{
	zbx_perf_counter_data_t	*cptr;

	LOCK_PERFCOUNTERS;

	if (NULL == counter || NULL == ppsd.pPerfCounterList)
		goto out;

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

	PdhRemoveCounter(counter->handle);
	zbx_free(counter->name);
	zbx_free(counter->counterpath);
	zbx_free(counter->value_array);
	zbx_free(counter);
out:
	UNLOCK_PERFCOUNTERS;

}

static void	free_perf_counter_list(void)
{
	zbx_perf_counter_data_t	*cptr;

	LOCK_PERFCOUNTERS;

	while (NULL != ppsd.pPerfCounterList)
	{
		cptr = ppsd.pPerfCounterList;
		ppsd.pPerfCounterList = cptr->next;

		zbx_free(cptr->name);
		zbx_free(cptr->counterpath);
		zbx_free(cptr->value_array);
		zbx_free(cptr);
	}

	UNLOCK_PERFCOUNTERS;
}

/******************************************************************************
 *                                                                            *
 * Comments: must be called only for PERF_COUNTER_ACTIVE counters,            *
 *           interval must be less than or equal to counter->interval         *
 *                                                                            *
 ******************************************************************************/
static double	compute_average_value(zbx_perf_counter_data_t *counter, int interval)
{
	double	sum = 0;
	int	i, j, count;

	if (PERF_COUNTER_ACTIVE != counter->status || interval > counter->interval)
		return 0;

	if (counter->interval == interval)
		return counter->sum / (double)counter->value_count;

	/* compute the average manually for custom intervals */
	i = counter->value_current;
	count = (counter->value_count < interval ? counter->value_count : interval);

	/* cycle backwards through the circular buffer of values */
	for (j = 0; j < count; j++, i = (0 < i ? i - 1 : counter->interval - 1))
		sum += counter->value_array[i];

	return sum / (double)count;
}

int	init_perf_collector(zbx_threadedness_t threadedness, char **error)
{
	const char	*__function_name = "init_perf_collector";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	switch (threadedness)
	{
		case ZBX_SINGLE_THREADED:
			break;
		case ZBX_MULTI_THREADED:
			if (SUCCEED != zbx_mutex_create(&perfstat_access, ZBX_MUTEX_PERFSTAT, error))
				goto out;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			*error = zbx_strdup(*error, "internal error");
			goto out;
	}

	if (ERROR_SUCCESS != zbx_PdhOpenQuery(__function_name, &ppsd.pdh_query))
	{
		*error = zbx_strdup(*error, "cannot open performance data query");
		goto out;
	}

	ppsd.nextcheck = time(NULL) + UNSUPPORTED_REFRESH_PERIOD;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

void	free_perf_collector(void)
{
	zbx_perf_counter_data_t	*cptr;

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

void	collect_perfstat(void)
{
	const char		*__function_name = "collect_perfstat";
	zbx_perf_counter_data_t	*cptr;
	PDH_STATUS		pdh_status;
	time_t			now;
	PDH_FMT_COUNTERVALUE	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_PERFCOUNTERS;

	if (SUCCEED != perf_collector_started())
		goto out;

	if (NULL == ppsd.pPerfCounterList)	/* no counters */
		goto out;

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

		cptr->olderRawValue = (cptr->olderRawValue + 1) & 1;

		pdh_status = PdhCalculateCounterFromRawValue(cptr->handle, PDH_FMT_DOUBLE | PDH_FMT_NOCAP100,
				&cptr->rawValues[(cptr->olderRawValue + 1) & 1],
				(PERF_COUNTER_INITIALIZED < cptr->status ?
						&cptr->rawValues[cptr->olderRawValue] : NULL), &value);

		if (ERROR_SUCCESS == pdh_status && PDH_CSTATUS_VALID_DATA != value.CStatus &&
				PDH_CSTATUS_NEW_DATA != value.CStatus)
		{
			pdh_status = value.CStatus;
		}

		if (PDH_CSTATUS_INVALID_DATA == pdh_status)
		{
			/* some (e.g., rate) counters require two raw values, MSDN lacks documentation */
			/* about what happens but tests show that PDH_CSTATUS_INVALID_DATA is returned */

			cptr->status = PERF_COUNTER_GET_SECOND_VALUE;
			continue;
		}

		/* Negative values can occur when a counter rolls over. By default, this value entry does not appear  */
		/* in the registry and Performance Monitor does not log data errors or notify the user that it has    */
		/* received bad data; More info: https://support.microsoft.com/kb/177655/EN-US                        */

		if (PDH_CALC_NEGATIVE_DENOMINATOR == pdh_status)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "PDH_CALC_NEGATIVE_DENOMINATOR error occurred in counterpath '%s'."
					" Value ignored", cptr->counterpath);
			continue;
		}

		if (PDH_CALC_NEGATIVE_VALUE == pdh_status)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "PDH_CALC_NEGATIVE_VALUE error occurred in counterpath '%s'."
					" Value ignored", cptr->counterpath);
			continue;
		}

		if (ERROR_SUCCESS == pdh_status)
		{
			cptr->status = PERF_COUNTER_ACTIVE;
			cptr->value_current = (cptr->value_current + 1) % cptr->interval

			/* remove the oldest value, value_count will not increase */;
			if (cptr->value_count == cptr->interval)
				cptr->sum -= cptr->value_array[cptr->value_current];

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
	UNLOCK_PERFCOUNTERS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_perf_counter_value_by_name                                   *
 *                                                                            *
 * Purpose: gets average named performance counter value                      *
 *                                                                            *
 * Parameters: name  - [IN] the performance counter name                      *
 *             value - [OUT] the calculated value                             *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Returns:  SUCCEED - the value was retrieved successfully                   *
 *           FAIL    - otherwise                                              *
 *                                                                            *
 * Comments: The value is retrieved from collector (if it has been requested  *
 *           before) or directly from Windows performance counters if         *
 *           possible.                                                        *
 *                                                                            *
 ******************************************************************************/
int	get_perf_counter_value_by_name(const char *name, double *value, char **error)
{
	const char		*__function_name = "get_perf_counter_value_by_name";
	int			ret = FAIL;
	zbx_perf_counter_data_t	*perfs = NULL;
	char			*counterpath = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() name:%s", __function_name, name);

	LOCK_PERFCOUNTERS;

	if (SUCCEED != perf_collector_started())
	{
		*error = zbx_strdup(*error, "Performance collector is not started.");
		goto out;
	}

	for (perfs = ppsd.pPerfCounterList; NULL != perfs; perfs = perfs->next)
	{
		if (NULL != perfs->name && 0 == strcmp(perfs->name, name))
		{
			if (PERF_COUNTER_ACTIVE != perfs->status)
				break;

			/* the counter data is already being collected, return it */
			*value = compute_average_value(perfs, perfs->interval);
			ret = SUCCEED;
			goto out;
		}
	}

	/* we can retrieve named counter data only if it has been registered before */
	if (NULL == perfs)
	{
		*error = zbx_dsprintf(*error, "Unknown performance counter name: %s.", name);
		goto out;
	}

	counterpath = zbx_strdup(counterpath, perfs->counterpath);
out:
	UNLOCK_PERFCOUNTERS;

	if (NULL != counterpath)
	{
		/* request counter value directly from Windows performance counters */
		if (ERROR_SUCCESS == calculate_counter_value(__function_name, counterpath, value))
			ret = SUCCEED;

		zbx_free(counterpath);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_perf_counter_value_by_path                                   *
 *                                                                            *
 * Purpose: gets average performance counter value                            *
 *                                                                            *
 * Parameters: counterpath - [IN] the performance counter path                *
 *             interval    - [IN] the data collection interval in seconds     *
 *             value       - [OUT] the calculated value                       *
 *             error       - [OUT] the error message                          *
 *                                                                            *
 * Returns:  SUCCEED - the value was retrieved successfully                   *
 *           FAIL    - otherwise                                              *
 *                                                                            *
 * Comments: The value is retrieved from collector (if it has been requested  *
 *           before) or directly from Windows performance counters if         *
 *           possible.                                                        *
 *                                                                            *
 ******************************************************************************/
int	get_perf_counter_value_by_path(const char *counterpath, int interval, double *value, char **error)
{
	const char		*__function_name = "get_perf_counter_value_by_path";
	int			ret = FAIL;
	zbx_perf_counter_data_t	*perfs = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s interval:%d", __function_name, counterpath, interval);

	LOCK_PERFCOUNTERS;

	if (SUCCEED != perf_collector_started())
	{
		*error = zbx_strdup(*error, "Performance collector is not started.");
		goto out;
	}

	for (perfs = ppsd.pPerfCounterList; NULL != perfs; perfs = perfs->next)
	{
		if (0 == strcmp(perfs->counterpath, counterpath))
		{
			if (perfs->interval < interval)
				extend_perf_counter_interval(perfs, interval);

			if (PERF_COUNTER_ACTIVE != perfs->status)
				break;

			/* the counter data is already being collected, return it */
			*value = compute_average_value(perfs, interval);
			ret = SUCCEED;
			goto out;
		}
	}

	/* if the requested counter is not already being monitored - start monitoring */
	if (NULL == perfs)
		perfs = add_perf_counter(NULL, counterpath, interval, error);
out:
	UNLOCK_PERFCOUNTERS;

	if (SUCCEED != ret && NULL != perfs)
	{
		/* request counter value directly from Windows performance counters */
		if (ERROR_SUCCESS == calculate_counter_value(__function_name, counterpath, value))
			ret = SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_perf_counter_value                                           *
 *                                                                            *
 * Purpose: gets average value of the specified performance counter interval  *
 *                                                                            *
 * Parameters: counter  - [IN] the performance counter                        *
 *             interval - [IN] the data collection interval in seconds        *
 *             value    - [OUT] the calculated value                          *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Returns:  SUCCEED - the value was retrieved successfully                   *
 *           FAIL    - otherwise                                              *
 *                                                                            *
 ******************************************************************************/
int	get_perf_counter_value(zbx_perf_counter_data_t *counter, int interval, double *value, char **error)
{
	const char	*__function_name = "get_perf_counter_value";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s interval:%d", __function_name, counter->counterpath, interval);

	LOCK_PERFCOUNTERS;

	if (SUCCEED != perf_collector_started())
	{
		*error = zbx_strdup(*error, "Performance collector is not started.");
		goto out;
	}

	if (PERF_COUNTER_ACTIVE != counter->status)
	{
		*error = zbx_strdup(*error, "Performance counter is not ready.");
		goto out;
	}

	*value = compute_average_value(counter, interval);
	ret = SUCCEED;
out:
	UNLOCK_PERFCOUNTERS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
