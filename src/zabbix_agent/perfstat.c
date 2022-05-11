/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "perfstat.h"

#include "stats.h"
#include "alias.h"
#include "log.h"
#include "mutexs.h"
#include "sysinfo.h"

#define OBJECT_CACHE_REFRESH_INTERVAL	60
#define NAMES_UPDATE_INTERVAL		60

struct object_name_ref
{
	char		*eng_name;
	wchar_t		*loc_name;
};

typedef struct
{
	zbx_perf_counter_data_t	*pPerfCounterList;
	PDH_HQUERY		pdh_query;
	time_t			lastrefresh_objects;	/* last refresh time of object cache */
	time_t			lastupdate_names;	/* last update time of object names */
}
ZBX_PERF_STAT_DATA;

static ZBX_PERF_STAT_DATA	ppsd;
static zbx_mutex_t		perfstat_access = ZBX_MUTEX_NULL;

static struct object_name_ref	*object_names = NULL;
static int			object_num = 0;

#define LOCK_PERFCOUNTERS	zbx_mutex_lock(perfstat_access)
#define UNLOCK_PERFCOUNTERS	zbx_mutex_unlock(perfstat_access)

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
 * Comments: if the specified counter exists or the new one is successfully   *
 *           added, a pointer to that counter is returned, NULL otherwise     *
 *                                                                            *
 ******************************************************************************/
zbx_perf_counter_data_t	*add_perf_counter(const char *name, const char *counterpath, int interval,
		zbx_perf_counter_lang_t lang, char **error)
{
	zbx_perf_counter_data_t	*cptr = NULL;
	PDH_STATUS		pdh_status;
	int			added = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() counter:'%s' interval:%d", __func__, counterpath, interval);

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
			cptr->lang = lang;
			cptr->value_current = -1;
			cptr->value_array = (double *)zbx_malloc(cptr->value_array, sizeof(double) * interval);

			/* add the counter to the query */
			pdh_status = zbx_PdhAddCounter(__func__, cptr, ppsd.pdh_query, counterpath,
					lang, &cptr->handle);

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

		if (NULL != name)
		{
			if (0 == strcmp(cptr->name, name))
				break;
		}
		else if (0 == strcmp(cptr->counterpath, counterpath) &&
				cptr->interval == interval && cptr->lang == lang)
		{
			break;
		}
	}

	if (FAIL == added)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() counter '%s' already exists", __func__, counterpath);
	}
	else if (NULL != name && NULL != cptr)
	{
		char	*alias_name;

		alias_name = zbx_dsprintf(NULL, "__UserPerfCounter[%s]", name);
		add_alias(name, alias_name);
		zbx_free(alias_name);
	}
out:
	UNLOCK_PERFCOUNTERS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __func__, NULL == cptr ? "FAIL" : "SUCCEED");

	return cptr;
}

/******************************************************************************
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

static void	free_object_names(void)
{
	int	i;

	for (i = 0; i < object_num; i++)
	{
		zbx_free(object_names[i].eng_name);
		zbx_free(object_names[i].loc_name);
	}

	zbx_free(object_names);
	object_num = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: obtains PDH object localized names and associates them with       *
 *          English names, to be used by perf_instance_en.discovery           *
 *                                                                            *
 * Return value: SUCCEED/FAIL                                                 *
 *                                                                            *
 ******************************************************************************/
static int	set_object_names(void)
{
	wchar_t		*names_eng, *names_loc, *eng_name, *loc_name, *objects, *object, *p_eng = NULL, *p_loc = NULL;
	DWORD		sz = 0;
	PDH_STATUS	pdh_status;
	BOOL		refresh;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	LOCK_PERFCOUNTERS;

	if (ppsd.lastupdate_names + NAMES_UPDATE_INTERVAL >= time(NULL))
	{
		ret = SUCCEED;
		goto out;
	}

	if (ppsd.lastrefresh_objects + OBJECT_CACHE_REFRESH_INTERVAL > time(NULL))
		refresh = FALSE;
	else
		refresh = TRUE;

	if (PDH_MORE_DATA != (pdh_status = PdhEnumObjects(NULL, NULL, NULL, &sz, PERF_DETAIL_WIZARD, refresh)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot obtain required buffer size: %s",
				strerror_from_module(pdh_status, L"PDH.DLL"));
		goto out;
	}

	if (TRUE == refresh)
		ppsd.lastrefresh_objects = time(NULL);

	if (NULL == (p_eng = names_eng = get_all_counter_names(HKEY_PERFORMANCE_TEXT, L"Counter")) ||
			NULL == (p_loc = names_loc = get_all_counter_names(HKEY_PERFORMANCE_NLSTEXT, L"Counter")))
	{
		goto out;
	}

	/* skip fields with number of records */
	names_eng += wcslen(names_eng) + 1;
	names_eng += wcslen(names_eng) + 1;
	names_loc += wcslen(names_loc) + 1;
	names_loc += wcslen(names_loc) + 1;

	objects = zbx_malloc(NULL, (++sz) * sizeof(wchar_t));

	if (ERROR_SUCCESS != (pdh_status = PdhEnumObjects(NULL, NULL, objects, &sz, PERF_DETAIL_WIZARD, FALSE)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot obtain objects list: %s",
				strerror_from_module(pdh_status, L"PDH.DLL"));
		zbx_free(objects);
		goto out;
	}

	free_object_names();

	for (object = objects; L'\0' != *object; object += sz)
	{
		DWORD	idx_eng, idx_loc;

		sz = (DWORD)wcslen(object) + 1;
		object_names = zbx_realloc(object_names, sizeof(struct object_name_ref) * (object_num + 1));

		object_names[object_num].eng_name = NULL;
		object_names[object_num].loc_name = zbx_malloc(NULL, sizeof(wchar_t) * sz);
		memcpy(object_names[object_num].loc_name, object, sizeof(wchar_t) * sz);

		/* For some objects the localized name might be missing and PdhEnumObjects() will return English    */
		/* name instead. In that case for localized name use name returned by PdhEnumObjects() if such name */
		/* exists in English names registry (HKEY_PERFORMANCE_TEXT).                                        */

		idx_loc = 0;

		for (loc_name = names_loc; L'\0' != *loc_name; loc_name += wcslen(loc_name) + 1)
		{
			DWORD	idx;

			idx = (DWORD)_wtoi(loc_name);
			loc_name += wcslen(loc_name) + 1;

			if (0 == wcscmp(object, loc_name))
			{
				idx_loc = idx;
				break;
			}
		}

		for (eng_name = names_eng; L'\0' != *eng_name; eng_name += wcslen(eng_name) + 1)
		{
			idx_eng = (DWORD)_wtoi(eng_name);
			eng_name += wcslen(eng_name) + 1;

			if (idx_loc == idx_eng ||
					(0 == idx_loc && 0 == wcscmp(object_names[object_num].loc_name, eng_name)))
			{
				object_names[object_num].eng_name = zbx_unicode_to_utf8(eng_name);
				break;
			}
		}

		object_num++;
	}

	zbx_free(objects);
	ppsd.lastupdate_names = time(NULL);
	ret = SUCCEED;
out:
	zbx_free(p_eng);
	zbx_free(p_loc);

	UNLOCK_PERFCOUNTERS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
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

	while (NULL != ppsd.pPerfCounterList)
	{
		cptr = ppsd.pPerfCounterList;
		ppsd.pPerfCounterList = cptr->next;

		zbx_free(cptr->name);
		zbx_free(cptr->counterpath);
		zbx_free(cptr->value_array);
		zbx_free(cptr);
	}
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
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	if (ERROR_SUCCESS != zbx_PdhOpenQuery(__func__, &ppsd.pdh_query))
	{
		*error = zbx_strdup(*error, "cannot open performance data query");
		goto out;
	}

	ppsd.lastrefresh_objects = 0;
	ppsd.lastupdate_names = 0;

	if (SUCCEED != init_builtin_counter_indexes())
	{
		*error = zbx_strdup(*error, "cannot initialize built-in counter indexes");
		goto out;
	}

	if (SUCCEED != set_object_names())
	{
		*error = zbx_strdup(*error, "cannot initialize object names");
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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

	LOCK_PERFCOUNTERS;

	free_perf_counter_list();
	free_object_names();

	UNLOCK_PERFCOUNTERS;

	zbx_mutex_destroy(&perfstat_access);
}

void	collect_perfstat(void)
{
	zbx_perf_counter_data_t	*cptr;
	PDH_STATUS		pdh_status;
	time_t			now;
	PDH_FMT_COUNTERVALUE	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	LOCK_PERFCOUNTERS;

	if (SUCCEED != perf_collector_started())
		goto out;

	if (NULL == ppsd.pPerfCounterList)	/* no counters */
		goto out;

	now = time(NULL);

	/* refresh unsupported counters */
	for (cptr = ppsd.pPerfCounterList; NULL != cptr; cptr = cptr->next)
	{
		if (PERF_COUNTER_NOTSUPPORTED != cptr->status)
			continue;

		zbx_PdhAddCounter(__func__, cptr, ppsd.pdh_query, cptr->counterpath,
				cptr->lang, &cptr->handle);
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
				__func__, strerror_from_module(pdh_status, L"PDH.DLL"));

		goto out;
	}

	/* get the raw values */
	for (cptr = ppsd.pPerfCounterList; NULL != cptr; cptr = cptr->next)
	{
		if (PERF_COUNTER_NOTSUPPORTED == cptr->status)
			continue;

		if (ERROR_SUCCESS != zbx_PdhGetRawCounterValue(__func__, cptr->counterpath,
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets average named performance counter value                      *
 *                                                                            *
 * Parameters: name  - [IN] the performance counter name                      *
 *             value - [OUT] the calculated value                             *
 *             error - [OUT] the error message, it is not always produced     *
 *                     when FAIL is returned. It is a caller responsibility   *
 *                     to check if the error message is not NULL.             *
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
	int			ret = FAIL;
	zbx_perf_counter_data_t	*perfs = NULL;
	char			*counterpath = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() name:%s", __func__, name);

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
		PDH_STATUS pdh_status = calculate_counter_value(__func__, counterpath, perfs->lang, value);

		if (PDH_NOT_IMPLEMENTED == pdh_status)
			*error = zbx_strdup(*error, "Counter is not supported for this Microsoft Windows version");
		else if (ERROR_SUCCESS == pdh_status)
			ret = SUCCEED;

		zbx_free(counterpath);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets average performance counter value                            *
 *                                                                            *
 * Parameters: counterpath - [IN] the performance counter path                *
 *             interval    - [IN] the data collection interval in seconds     *
 *             lang        - [IN] counterpath language (default or English)   *
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
int	get_perf_counter_value_by_path(const char *counterpath, int interval, zbx_perf_counter_lang_t lang,
		double *value, char **error)
{
	int			ret = FAIL;
	zbx_perf_counter_data_t	*perfs = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s interval:%d lang:%d", __func__, counterpath,
			interval, lang);

	LOCK_PERFCOUNTERS;

	if (SUCCEED != perf_collector_started())
	{
		*error = zbx_strdup(*error, "Performance collector is not started.");
		goto out;
	}

	for (perfs = ppsd.pPerfCounterList; NULL != perfs; perfs = perfs->next)
	{
		if (0 == strcmp(perfs->counterpath, counterpath) && perfs->lang == lang)
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
		perfs = add_perf_counter(NULL, counterpath, interval, lang, error);
out:
	UNLOCK_PERFCOUNTERS;

	if (SUCCEED != ret && NULL != perfs)
	{
		/* request counter value directly from Windows performance counters */
		if (ERROR_SUCCESS == calculate_counter_value(__func__, counterpath, lang, value))
			ret = SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
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
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s interval:%d", __func__, counter->counterpath, interval);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	refresh_object_cache(void)
{
	DWORD	sz = 0;
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	LOCK_PERFCOUNTERS;

	if (ppsd.lastrefresh_objects + OBJECT_CACHE_REFRESH_INTERVAL < time(NULL))
	{
		if (PDH_MORE_DATA != PdhEnumObjects(NULL, NULL, NULL, &sz, PERF_DETAIL_WIZARD, TRUE))
		{
			ret = FAIL;
			goto out;
		}

		ppsd.lastrefresh_objects = time(NULL);
	}
out:
	UNLOCK_PERFCOUNTERS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static wchar_t	*get_object_name(char *eng_name)
{
	wchar_t	*loc_name = NULL;
	int	i;
	size_t	len;

	LOCK_PERFCOUNTERS;

	len = strlen(eng_name);

	for (i = 0; i < object_num; i++)
	{
		if (NULL != object_names[i].eng_name && len == strlen(object_names[i].eng_name) &&
				0 == zbx_strncasecmp(object_names[i].eng_name, eng_name, len))
		{
			size_t	sz;

			sz = (wcslen(object_names[i].loc_name) + 1) * sizeof(wchar_t);
			loc_name = zbx_malloc(NULL, sz);
			memcpy(loc_name, object_names[i].loc_name, sz);
			break;
		}
	}

	UNLOCK_PERFCOUNTERS;

	return loc_name;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get localized name of the object                                  *
 *                                                                            *
 * Parameters: eng_name - [IN] english name                                   *
 *                                                                            *
 * Returns:  localized name of the object                                     *
 *                                                                            *
 ******************************************************************************/
wchar_t	*get_object_name_local(char *eng_name)
{
	wchar_t	*name;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (name = get_object_name(eng_name)) && SUCCEED == set_object_names())
		name = get_object_name(eng_name);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return name;
}
