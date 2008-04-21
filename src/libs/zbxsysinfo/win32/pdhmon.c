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

#include "sysinfo.h"
#include "threads.h"

#include "stats.h"

#include "log.h"

int	USER_PERFCOUNTER(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	PERF_COUNTERS *perfs = NULL;

	int	ret = SYSINFO_RET_FAIL;

	if ( !PERF_COLLECTOR_STARTED(collector) )
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	for(perfs = collector->perfs.pPerfCounterList; perfs; perfs=perfs->next)
	{
		if ( 0 == strcmp(perfs->name, param) )
		{
			SET_DBL_RESULT(result, perfs->lastValue);
			ret = SYSINFO_RET_OK;
			break;
		}
	}

	return ret;
}

int	PERF_MONITOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	HQUERY				query;
	HCOUNTER			counter;
	PDH_STATUS			status;
	PDH_RAW_COUNTER			rawData, rawData2;
	PDH_FMT_COUNTERVALUE		counterValue;
	char				counter_path[PDH_MAX_COUNTER_PATH];
	PDH_COUNTER_PATH_ELEMENTS	*cpe = NULL;
	int				ret = SYSINFO_RET_FAIL, is_numeric;
	DWORD				dwSize;

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (get_param(param, 1, counter_path, sizeof(counter_path)) != 0)
		*counter_path = '\0';

	if (*counter_path == '\0')
		return SYSINFO_RET_FAIL;

	dwSize = 0;
retry:
	if (ERROR_SUCCESS != (status = PdhParseCounterPath(counter_path, cpe, &dwSize, 0))) {
		if (status == PDH_MORE_DATA) {
			cpe = (PDH_COUNTER_PATH_ELEMENTS *)zbx_malloc(cpe, dwSize);
			goto retry;
		}
		zabbix_log(LOG_LEVEL_DEBUG, "Can't parse counter path \"%s\": %s",
				counter_path, strerror_from_module(status, "PDH.DLL"));

		zbx_free(cpe);
		return SYSINFO_RET_FAIL;
	}

	is_numeric = (SUCCEED == is_uint(cpe->szObjectName)) ? 0x01 : 0;
	is_numeric |= (SUCCEED == is_uint(cpe->szCounterName)) ? 0x02 : 0;
	if (0 != is_numeric) {
		if (0x01 & is_numeric)
			cpe->szObjectName = GetCounterName(atoi(cpe->szObjectName));
		if (0x02 & is_numeric)
			cpe->szCounterName = GetCounterName(atoi(cpe->szCounterName));

		dwSize = sizeof(counter_path);
		if (ERROR_SUCCESS != (status = PdhMakeCounterPath(cpe, counter_path, &dwSize, 0))) {
			zabbix_log(LOG_LEVEL_ERR, "Can't make counter path: %s",
					strerror_from_module(status, "PDH.DLL"));
			zbx_free(cpe);
			return SYSINFO_RET_FAIL;
		}
		zabbix_log(LOG_LEVEL_DEBUG, "Counter path converted to \"%s\"",
				counter_path);
	}
	zbx_free(cpe);

	if (ERROR_SUCCESS == (status = PdhOpenQuery(NULL, 0, &query)))
	{
		if (ERROR_SUCCESS == (status = PdhAddCounter(query,counter_path,0,&counter)))
		{
			if (ERROR_SUCCESS == (status = PdhCollectQueryData(query)))
			{
				if(ERROR_SUCCESS == (status = PdhGetRawCounterValue(counter, NULL, &rawData)) &&
					(rawData.CStatus == PDH_CSTATUS_VALID_DATA || rawData.CStatus == PDH_CSTATUS_NEW_DATA))
				{
					if( PDH_CSTATUS_INVALID_DATA == (status = PdhCalculateCounterFromRawValue(
						counter, 
						PDH_FMT_DOUBLE, 
						&rawData, 
						NULL, 
						&counterValue
						)) )
					{
						zbx_sleep(1);
						PdhCollectQueryData(query);
						PdhGetRawCounterValue(counter, NULL, &rawData2);
						status = PdhCalculateCounterFromRawValue(
							counter, 
							PDH_FMT_DOUBLE, 
							&rawData2, 
							&rawData, 
							&counterValue);

					}

					if(ERROR_SUCCESS == status) {
						SET_DBL_RESULT(result, counterValue.doubleValue);
						ret = SYSINFO_RET_OK;
					} else
						zabbix_log(LOG_LEVEL_DEBUG, "Can't format counter value \"%s\": %s",
								counter_path, strerror_from_module(status, "PDH.DLL"));
				} else {
					if (ERROR_SUCCESS == status)
						status = rawData.CStatus;

					zabbix_log(LOG_LEVEL_DEBUG, "Can't get counter value \"%s\": %s",
							counter_path, strerror_from_module(status, "PDH.DLL"));
				}
			} else
				zabbix_log(LOG_LEVEL_DEBUG, "Can't collect data \"%s\": %s",
						counter_path, strerror_from_module(status, "PDH.DLL"));

			PdhRemoveCounter(&counter);
		} else
			zabbix_log(LOG_LEVEL_DEBUG, "Can't add counter \"%s\": %s",
					counter_path, strerror_from_module(status, "PDH.DLL"));

		PdhCloseQuery(query);
	} else
		zabbix_log(LOG_LEVEL_DEBUG, "Can't initialize performance counters \"%s\": %s",
				counter_path, strerror_from_module(status, "PDH.DLL"));

	return ret;
}
