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
#include "perfmon.h"
#include "log.h"

PERFCOUNTER	*PerfCounterList = NULL;

PDH_STATUS	zbx_PdhMakeCounterPath(const char *function, PDH_COUNTER_PATH_ELEMENTS *cpe, char *counterpath)
{
	DWORD		dwSize = PDH_MAX_COUNTER_PATH;
	LPTSTR		wcounterPath = NULL;
	PDH_STATUS	pdh_status;

	wcounterPath = zbx_realloc(wcounterPath, dwSize * sizeof(TCHAR));
	if (ERROR_SUCCESS != (pdh_status = PdhMakeCounterPath(cpe, wcounterPath, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): Could not make counterpath '%s': %s",
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
		zabbix_log(LOG_LEVEL_ERR, "%s(): Call to PdhOpenQuery() failed: %s",
				function, strerror_from_module(pdh_status, L"PDH.DLL"));
	return pdh_status;
}

/* counter is NULL if the counter is not in collector */
PDH_STATUS	zbx_PdhAddCounter(const char *function, PERF_COUNTERS *counter, PDH_HQUERY query, const char *counterpath, PDH_HCOUNTER *handle)
{
	PDH_STATUS	pdh_status;
	LPTSTR		wcounterPath;

	wcounterPath = zbx_utf8_to_unicode(counterpath);
	if (ERROR_SUCCESS == (pdh_status = PdhAddCounter(query, wcounterPath, 0, handle)) &&
		ERROR_SUCCESS == (pdh_status = PdhValidatePath(wcounterPath)))
	{
		if (NULL != counter)
			counter->status = PERF_COUNTER_INITIALIZED;
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): PerfCounter '%s' successfully added", function, counterpath);
	}
	else
	{
		if (NULL != counter)
			counter->status = PERF_COUNTER_NOTSUPPORTED;
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): Unable to add PerfCounter '%s': %s",
			function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	zbx_free(wcounterPath);
	return pdh_status;
}

PDH_STATUS	zbx_PdhCollectQueryData(const char *function, const char *counterpath, PDH_HQUERY query)
{
	PDH_STATUS pdh_status;
	if (ERROR_SUCCESS != (pdh_status = PdhCollectQueryData(query)))
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): Can't collect data \"%s\": %s",
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
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
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): Can't get counter value \"%s\": %s",
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
	}
	return pdh_status;
}

LPTSTR	GetCounterName(DWORD pdhIndex)
{
	const char	*__function_name = "GetCounterName";
	PERFCOUNTER	*counterName = NULL;
	DWORD		dwSize;

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
		counterName = (PERFCOUNTER *)zbx_malloc(counterName, sizeof(PERFCOUNTER));

		memset(counterName, 0, sizeof(PERFCOUNTER));
		counterName->pdhIndex = pdhIndex;
		counterName->next = PerfCounterList;

		dwSize = PDH_MAX_COUNTER_NAME;
		if (ERROR_SUCCESS == PdhLookupPerfNameByIndex(NULL, pdhIndex, counterName->name, &dwSize))
			PerfCounterList = counterName;
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "PdhLookupPerfNameByIndex failed: %s",
					strerror_from_system(GetLastError()));
			zbx_free(counterName);
			return L"UnknownPerformanceCounter";
		}
	}

	return counterName->name;
}

int	check_counter_path(char *counterPath)
{
	const char			*__function_name = "check_counter_path";
	PDH_COUNTER_PATH_ELEMENTS	*cpe = NULL;
	PDH_STATUS			status;
	int				is_numeric;
	int				ret = FAIL;
	DWORD				dwSize = 0;
	LPTSTR				wcounterPath = NULL;

	wcounterPath = zbx_utf8_to_unicode(counterPath);

	status = PdhParseCounterPath(wcounterPath, NULL, &dwSize, 0);
	if (status == PDH_MORE_DATA || status == ERROR_SUCCESS)
		cpe = (PDH_COUNTER_PATH_ELEMENTS *)zbx_malloc(cpe, dwSize);
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "Can't get required buffer size. Counter path is \"%s\": %s",
				counterPath, strerror_from_module(status, L"PDH.DLL"));
		goto clean;
	}

	if (ERROR_SUCCESS != (status = PdhParseCounterPath(wcounterPath, cpe, &dwSize, 0))) {
		zabbix_log(LOG_LEVEL_ERR, "Can't parse counter path \"%s\": %s",
				counterPath, strerror_from_module(status, L"PDH.DLL"));

		goto clean;
	}

	is_numeric = (SUCCEED == _wis_uint(cpe->szObjectName)) ? 0x01 : 0;
	is_numeric |= (SUCCEED == _wis_uint(cpe->szCounterName)) ? 0x02 : 0;
	if (0 != is_numeric)
	{
		if (0x01 & is_numeric)
			cpe->szObjectName = GetCounterName(_wtoi(cpe->szObjectName));
		if (0x02 & is_numeric)
			cpe->szCounterName = GetCounterName(_wtoi(cpe->szCounterName));

		if (ERROR_SUCCESS != (status = zbx_PdhMakeCounterPath(__function_name, cpe, counterPath)))
			goto clean;

		zabbix_log(LOG_LEVEL_DEBUG, "Counter path converted to \"%s\"", counterPath);
	}
	ret = SUCCEED;
clean:
	zbx_free(cpe);
	return ret;
}
