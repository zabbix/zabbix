/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "perfmon.h"
#include "log.h"

static PERF_COUNTER_ID	*PerfCounterList = NULL;

PDH_STATUS	zbx_PdhMakeCounterPath(const char *function, PDH_COUNTER_PATH_ELEMENTS *cpe, char *counterpath)
{
	DWORD		dwSize = PDH_MAX_COUNTER_PATH;
	LPTSTR		wcounterPath = NULL;
	PDH_STATUS	pdh_status;

	wcounterPath = zbx_realloc(wcounterPath, dwSize * sizeof(TCHAR));

	if (ERROR_SUCCESS != (pdh_status = PdhMakeCounterPath(cpe, wcounterPath, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): cannot make counterpath '%s': %s",
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	zbx_unicode_to_utf8_static(wcounterPath, counterpath, PDH_MAX_COUNTER_PATH);
	zbx_free(wcounterPath);

	return pdh_status;
}

PDH_STATUS	zbx_PdhOpenQuery(const char *function, PDH_HQUERY query)
{
	PDH_STATUS	pdh_status;

	if (ERROR_SUCCESS != (pdh_status = PdhOpenQuery(NULL, 0, query)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): call to PdhOpenQuery() failed: %s",
				function, strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	return pdh_status;
}

/******************************************************************************
 *                                                                            *
 * Comments: counter is NULL if it is not in the collector,                   *
 *           do not call it for PERF_COUNTER_ACTIVE counters                  *
 *                                                                            *
 ******************************************************************************/
PDH_STATUS	zbx_PdhAddCounter(const char *function, PERF_COUNTER_DATA *counter, PDH_HQUERY query,
		const char *counterpath, PDH_HCOUNTER *handle)
{
	PDH_STATUS	pdh_status = ERROR_SUCCESS;
	LPTSTR		wcounterPath;

	wcounterPath = zbx_utf8_to_unicode(counterpath);

	if (NULL == *handle)
		pdh_status = PdhAddCounter(query, wcounterPath, 0, handle);

	if (ERROR_SUCCESS == pdh_status)
		pdh_status = PdhValidatePath(wcounterPath);

	if (ERROR_SUCCESS != pdh_status && NULL != *handle)
	{
		if (ERROR_SUCCESS == PdhRemoveCounter(*handle))
			*handle = NULL;
	}

	if (ERROR_SUCCESS == pdh_status)
	{
		if (NULL != counter)
			counter->status = PERF_COUNTER_INITIALIZED;

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): PerfCounter '%s' successfully added", function, counterpath);
	}
	else
	{
		if (NULL != counter)
			counter->status = PERF_COUNTER_NOTSUPPORTED;

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): unable to add PerfCounter '%s': %s",
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	zbx_free(wcounterPath);

	return pdh_status;
}

PDH_STATUS	zbx_PdhCollectQueryData(const char *function, const char *counterpath, PDH_HQUERY query)
{
	PDH_STATUS	pdh_status;

	if (ERROR_SUCCESS != (pdh_status = PdhCollectQueryData(query)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot collect data '%s': %s",
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	return pdh_status;
}

PDH_STATUS	zbx_PdhGetRawCounterValue(const char *function, const char *counterpath, PDH_HCOUNTER handle, PPDH_RAW_COUNTER value)
{
	PDH_STATUS	pdh_status;

	if (ERROR_SUCCESS != (pdh_status = PdhGetRawCounterValue(handle, NULL, value)) ||
		(PDH_CSTATUS_VALID_DATA != value->CStatus && PDH_CSTATUS_NEW_DATA != value->CStatus))
	{
		if (ERROR_SUCCESS == pdh_status)
			pdh_status = value->CStatus;

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot get counter value '%s': %s",
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	return pdh_status;
}

/******************************************************************************
 *                                                                            *
 * Comments: Get the value of a counter. If it is a rate counter,             *
 *           sleep 1 second to get the second raw value.                      *
 *                                                                            *
 ******************************************************************************/
PDH_STATUS	calculate_counter_value(const char *function, const char *counterpath, double *value)
{
	PDH_HQUERY		query;
	PDH_HCOUNTER		handle = NULL;
	PDH_STATUS		pdh_status;
	PDH_RAW_COUNTER		rawData, rawData2;
	PDH_FMT_COUNTERVALUE	counterValue;

	if (ERROR_SUCCESS != (pdh_status = zbx_PdhOpenQuery(function, &query)))
		return pdh_status;

	if (ERROR_SUCCESS != (pdh_status = zbx_PdhAddCounter(function, NULL, query, counterpath, &handle)))
		goto close_query;

	if (ERROR_SUCCESS != (pdh_status = zbx_PdhCollectQueryData(function, counterpath, query)))
		goto remove_counter;

	if (ERROR_SUCCESS != (pdh_status = zbx_PdhGetRawCounterValue(function, counterpath, handle, &rawData)))
		goto remove_counter;

	if (PDH_CSTATUS_INVALID_DATA == (pdh_status = PdhCalculateCounterFromRawValue(handle, PDH_FMT_DOUBLE |
			PDH_FMT_NOCAP100, &rawData, NULL, &counterValue)))
	{
		/* some (e.g., rate) counters require two raw values, MSDN lacks documentation */
		/* about what happens but tests show that PDH_CSTATUS_INVALID_DATA is returned */

		zbx_sleep(1);

		if (ERROR_SUCCESS == (pdh_status = zbx_PdhCollectQueryData(function, counterpath, query)) &&
				ERROR_SUCCESS == (pdh_status = zbx_PdhGetRawCounterValue(function, counterpath, handle, &rawData2)))
		{
			pdh_status = PdhCalculateCounterFromRawValue(handle, PDH_FMT_DOUBLE | PDH_FMT_NOCAP100,
					&rawData2, &rawData, &counterValue);
		}
	}

	if (ERROR_SUCCESS != pdh_status || (PDH_CSTATUS_VALID_DATA != counterValue.CStatus && PDH_CSTATUS_NEW_DATA != counterValue.CStatus))
	{
		if (ERROR_SUCCESS == pdh_status)
			pdh_status = counterValue.CStatus;

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot calculate counter value '%s': %s",
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
	}
	else
	{
		*value = counterValue.doubleValue;
	}
remove_counter:
	PdhRemoveCounter(handle);
close_query:
	PdhCloseQuery(query);

	return pdh_status;
}

LPTSTR	get_counter_name(DWORD pdhIndex)
{
	const char	*__function_name = "get_counter_name";
	PERF_COUNTER_ID	*counterName;
	DWORD		dwSize;
	PDH_STATUS	pdh_status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pdhIndex:%u", __function_name, pdhIndex);

	counterName = PerfCounterList;
	while (NULL != counterName)
	{
		if (counterName->pdhIndex == pdhIndex)
			break;
		counterName = counterName->next;
	}

	if (NULL == counterName)
	{
		counterName = (PERF_COUNTER_ID *)zbx_malloc(counterName, sizeof(PERF_COUNTER_ID));

		memset(counterName, 0, sizeof(PERF_COUNTER_ID));
		counterName->pdhIndex = pdhIndex;
		counterName->next = PerfCounterList;

		dwSize = PDH_MAX_COUNTER_NAME;
		if (ERROR_SUCCESS == (pdh_status = PdhLookupPerfNameByIndex(NULL, pdhIndex, counterName->name, &dwSize)))
			PerfCounterList = counterName;
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "PdhLookupPerfNameByIndex() failed: %s",
					strerror_from_module(pdh_status, L"PDH.DLL"));
			zbx_free(counterName);
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s():FAIL", __function_name);
			return L"UnknownPerformanceCounter";
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():SUCCEED", __function_name);

	return counterName->name;
}

int	check_counter_path(char *counterPath)
{
	const char			*__function_name = "check_counter_path";
	PDH_COUNTER_PATH_ELEMENTS	*cpe = NULL;
	PDH_STATUS			status;
	int				is_numeric, ret = FAIL;
	DWORD				dwSize = 0;
	LPTSTR				wcounterPath;

	wcounterPath = zbx_utf8_to_unicode(counterPath);

	status = PdhParseCounterPath(wcounterPath, NULL, &dwSize, 0);
	if (PDH_MORE_DATA == status || ERROR_SUCCESS == status)
	{
		cpe = (PDH_COUNTER_PATH_ELEMENTS *)zbx_malloc(cpe, dwSize);
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get required buffer size for counter path '%s': %s",
				counterPath, strerror_from_module(status, L"PDH.DLL"));
		goto clean;
	}

	if (ERROR_SUCCESS != (status = PdhParseCounterPath(wcounterPath, cpe, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse counter path '%s': %s",
				counterPath, strerror_from_module(status, L"PDH.DLL"));
		goto clean;
	}

	is_numeric = (SUCCEED == _wis_uint(cpe->szObjectName) ? 0x01 : 0);
	is_numeric |= (SUCCEED == _wis_uint(cpe->szCounterName) ? 0x02 : 0);

	if (0 != is_numeric)
	{
		if (0x01 & is_numeric)
			cpe->szObjectName = get_counter_name(_wtoi(cpe->szObjectName));
		if (0x02 & is_numeric)
			cpe->szCounterName = get_counter_name(_wtoi(cpe->szCounterName));

		if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__function_name, cpe, counterPath))
			goto clean;

		zabbix_log(LOG_LEVEL_DEBUG, "counter path converted to '%s'", counterPath);
	}

	ret = SUCCEED;
clean:
	zbx_free(cpe);
	zbx_free(wcounterPath);

	return ret;
}
