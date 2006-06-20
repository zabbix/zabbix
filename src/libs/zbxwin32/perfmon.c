/* 
** ZabbixW32 - Win32 agent for Zabbix
** Copyright (C) 2002 Victor Kirhenshtein
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
**
** $module: service.cpp
**
**/

#include "common.h"
#include "perfmon.h"

#include "log.h"

PERFCOUNTER *PerfCounterList = NULL;

//
// Get performance counter name by index
//

char *GetCounterName(DWORD index)
{
	PERFCOUNTER	*counterName;
	DWORD	dwSize;
	char hostname[MAX_COMPUTERNAME_LENGTH+3];

	counterName=PerfCounterList;
	while(counterName!=NULL)
	{
		if (counterName->pdhIndex == index)
		   break;
		counterName = counterName->next;
	}
	if (counterName == NULL)
	{
		counterName = (PERFCOUNTER *)malloc(sizeof(PERFCOUNTER));
		memset(counterName, 0, sizeof(PERFCOUNTER));
		counterName->pdhIndex = index;
		counterName->next = PerfCounterList;

		sprintf(hostname, "\\\\");
		dwSize = MAX_COMPUTERNAME_LENGTH+1;
		if(GetComputerName((char *) &hostname + 2, &dwSize)==0)
		{
			zabbix_log(LOG_LEVEL_ERR, "GetComputerName failed: %s", system_strerror(GetLastError()));
		}

		dwSize = MAX_PERFCOUNTER_NAME_LEN;
		if(PdhLookupPerfNameByIndex((char *)&hostname, index, (char *)&counterName->name, &dwSize) == ERROR_SUCCESS)
		{
			PerfCounterList = counterName;
		} 
		else 
		{
			zabbix_log(LOG_LEVEL_ERR, "PdhLookupPerfNameByIndex failed: %s", system_strerror(GetLastError()));
			free(counterName);
			return "UnknownPerformanceCounter";
		}
	}

	return (char *)&counterName->name;
}
