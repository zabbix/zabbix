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

#include "config.h"

#include "common.h"
#include "sysinfo.h"

#include "log.h"


int	PERF_MONITOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	HQUERY		query;
	HCOUNTER	counter;
	PDH_STATUS	status;

	PDH_RAW_COUNTER		rawData;
	PDH_FMT_COUNTERVALUE	counterValue;

	char	counter_name[MAX_STRING_LEN];

	int	ret = SYSINFO_RET_FAIL;

	if(num_param(param) > 1)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, counter_name, sizeof(counter_name)) != 0)
	{
		counter_name[0] = '\0';
	}
	if(counter_name[0] == '\0')
	{
		return SYSINFO_RET_FAIL;
	}

	if (ERROR_SUCCESS == PdhOpenQuery(NULL,0,&query))
	{
		if (ERROR_SUCCESS == (status = PdhAddCounter(query,counter_name,0,&counter)))
		{
			if (ERROR_SUCCESS == PdhCollectQueryData(query))
			{
				if(ERROR_SUCCESS == PdhGetRawCounterValue(counter, NULL, &rawData))
				{
					if( ERROR_SUCCESS == PdhCalculateCounterFromRawValue(
						counter, 
						PDH_FMT_DOUBLE, 
						&rawData, 
						NULL, 
						&counterValue
						) )
					{
						SET_DBL_RESULT(result, counterValue.doubleValue);
						ret = SYSINFO_RET_OK;
					}
					else
					{
						zabbix_log(LOG_LEVEL_DEBUG, "Can't format counter value [%s] [%s]", counter_name, "Rate counter is used.");
					}
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "Can't get counter value [%s] [%s]", counter_name, strerror_from_system(GetLastError()));
				}
			}
			PdhRemoveCounter(&counter);
		}

		PdhCloseQuery(query);
	}

	return ret;
}
