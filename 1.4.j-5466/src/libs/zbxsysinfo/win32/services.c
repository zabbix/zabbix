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

int	SERVICE_STATE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	SC_HANDLE
		mgr,
		service;

	char	name[MAX_STRING_LEN];
	char	service_name[MAX_STRING_LEN];

	unsigned long
		max_len_name = MAX_STRING_LEN;

	int	ret = SYSINFO_RET_FAIL;
	int	i;

	SERVICE_STATUS status;

	if(num_param(param) > 1)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, name, sizeof(name)) != 0)
	{
		name[0] = '\0';
	}
	if(name[0] == '\0')
	{
		return SYSINFO_RET_FAIL;
	}

	if(NULL == (mgr = OpenSCManager(NULL,NULL,GENERIC_READ)) )
	{
		return SYSINFO_RET_FAIL;
	}

	service = OpenService(mgr,name,SERVICE_QUERY_STATUS);

	if(NULL == service && 0 != GetServiceKeyName(mgr, name, service_name, &max_len_name))
	{
		service = OpenService(mgr,service_name,SERVICE_QUERY_STATUS);
	}

	if(NULL == service)
	{
		SET_UI64_RESULT(result, 255);
	}
	else
	{
		if (QueryServiceStatus(service, &status))
		{
			static DWORD states[7] = 
			{
				SERVICE_RUNNING,
				SERVICE_PAUSED,
				SERVICE_START_PENDING,
				SERVICE_PAUSE_PENDING,
				SERVICE_CONTINUE_PENDING,
				SERVICE_STOP_PENDING,
				SERVICE_STOPPED 
			};

			for(i=0; i < 7 && status.dwCurrentState != states[i]; i++);
			
			SET_UI64_RESULT(result, i);
		}
		else
		{
			SET_UI64_RESULT(result, 7);
		}

		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);

	return SYSINFO_RET_OK;
}
