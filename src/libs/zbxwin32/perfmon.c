/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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

/*
 * Check performance counter path and convert from numeric format
 *
 * counterPath[PDH_MAX_COUNTER_PATH]
 */

int	check_counter_path(char *counterPath)
{
	DWORD				dwSize = 0;
	PDH_COUNTER_PATH_ELEMENTS	*cpe = NULL;
	PDH_STATUS			status;
	int				is_numeric;
	LPTSTR				wcounterPath = NULL;
	int				ret = FAIL;

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

		dwSize = PDH_MAX_COUNTER_PATH;
		wcounterPath = zbx_realloc(wcounterPath, dwSize * sizeof(TCHAR));
		if (ERROR_SUCCESS != (status = PdhMakeCounterPath(cpe, wcounterPath, &dwSize, 0))) {
			zabbix_log(LOG_LEVEL_ERR, "Can't make counter path: %s",
					strerror_from_module(status, L"PDH.DLL"));
			goto clean;
		}

		zbx_unicode_to_utf8_static(wcounterPath, counterPath, PDH_MAX_COUNTER_PATH);

		zabbix_log(LOG_LEVEL_DEBUG, "Counter path converted to \"%s\"",
				counterPath);
	}
	ret = SUCCEED;
clean:
	zbx_free(cpe);
	zbx_free(wcounterPath);

	return ret;
}
