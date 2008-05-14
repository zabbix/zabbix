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

PERFCOUNTER *PerfCounterList = NULL;

/*
 * Get performance counter name by index
 */

char *GetCounterName(DWORD index)
{
	PERFCOUNTER	*counterName;
	DWORD		dwSize;

	zabbix_log(LOG_LEVEL_DEBUG, "In GetCounterName() [index:%u]", index);

	counterName = PerfCounterList;
	while(counterName!=NULL)
	{
		if (counterName->pdhIndex == index)
		   break;
		counterName = counterName->next;
	}
	if (counterName == NULL)
	{
		counterName = (PERFCOUNTER *)malloc(sizeof(PERFCOUNTER));
		if (NULL == counterName) {
			zabbix_log(LOG_LEVEL_ERR, "GetCounterName failed: Insufficient memory available for malloc");
			return "UnknownPerformanceCounter";
		}
		memset(counterName, 0, sizeof(PERFCOUNTER));
		counterName->pdhIndex = index;
		counterName->next = PerfCounterList;

		dwSize = sizeof(counterName->name);
		if(PdhLookupPerfNameByIndex(NULL, index, counterName->name, &dwSize) == ERROR_SUCCESS)
		{
			PerfCounterList = counterName;
		} 
		else 
		{
			zabbix_log(LOG_LEVEL_ERR, "PdhLookupPerfNameByIndex failed: %s", strerror_from_system(GetLastError()));
			free(counterName);
			return "UnknownPerformanceCounter";
		}
	}

	return counterName->name;
}

/*
 * Check performance counter path and convert from numeric format
 *
 * counterPath[PDH_MAX_COUNTER_PATH]
 */

int check_counter_path(char *counterPath)
{
	DWORD				dwSize;
	PDH_COUNTER_PATH_ELEMENTS	*cpe = NULL;
	PDH_STATUS			status;
	int				is_numeric;

	dwSize = 0;
retry:
	if (ERROR_SUCCESS != (status = PdhParseCounterPath(counterPath, cpe, &dwSize, 0))) {
		if (status == PDH_MORE_DATA) {
			cpe = (PDH_COUNTER_PATH_ELEMENTS *)zbx_malloc(cpe, dwSize);
			goto retry;
		}
		zabbix_log(LOG_LEVEL_DEBUG, "Can't parse counter path \"%s\": %s",
				counterPath, strerror_from_module(status, "PDH.DLL"));

		zbx_free(cpe);
		return FAIL;
	}

	is_numeric = (SUCCEED == is_uint(cpe->szObjectName)) ? 0x01 : 0;
	is_numeric |= (SUCCEED == is_uint(cpe->szCounterName)) ? 0x02 : 0;
	if (0 != is_numeric) {
		if (0x01 & is_numeric)
			cpe->szObjectName = GetCounterName(atoi(cpe->szObjectName));
		if (0x02 & is_numeric)
			cpe->szCounterName = GetCounterName(atoi(cpe->szCounterName));

		dwSize = PDH_MAX_COUNTER_PATH;
		if (ERROR_SUCCESS != (status = PdhMakeCounterPath(cpe, counterPath, &dwSize, 0))) {
			zabbix_log(LOG_LEVEL_ERR, "Can't make counter path: %s",
					strerror_from_module(status, "PDH.DLL"));
			zbx_free(cpe);
			return FAIL;
		}
		zabbix_log(LOG_LEVEL_DEBUG, "Counter path converted to \"%s\"",
				counterPath);
	}
	zbx_free(cpe);

	return SUCCEED;
}