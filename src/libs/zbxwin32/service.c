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
#include "service.h"

#include "cfg.h"
#include "log.h"
#include "alias.h"
#include "zbxconf.h"
#include "perfmon.h"

static int ZabbixRemoveEventSource(void);
static int ZabbixInstallEventSource(char *path);

#define uninit() { zbx_on_exit(); }

/*
 * Static data
 */

static	SERVICE_STATUS		serviceStatus;
static	SERVICE_STATUS_HANDLE	serviceHandle;

int application_status = ZBX_APP_RUNNED;

static void	parent_signal_handler(int sig)
{
	switch(sig)
	{
	case SIGINT:
	case SIGTERM:
		zabbix_log( LOG_LEVEL_INFORMATION, "Got signal. Exiting ...");
		uninit();
		ExitProcess( FAIL );
		break;
	}
}

/*
 * ZABBIX service control handler
 */

static VOID WINAPI ServiceCtrlHandler(DWORD ctrlCode)
{
	int do_exit = 0;

	serviceStatus.dwServiceType		= SERVICE_WIN32_OWN_PROCESS;
	serviceStatus.dwCurrentState		= SERVICE_RUNNING;
	serviceStatus.dwControlsAccepted	= SERVICE_ACCEPT_STOP | SERVICE_ACCEPT_SHUTDOWN;
	serviceStatus.dwWin32ExitCode		= 0;
	serviceStatus.dwServiceSpecificExitCode	= 0;
	serviceStatus.dwCheckPoint		= 0;
	serviceStatus.dwWaitHint		= 0;

	switch(ctrlCode)
	{
		case SERVICE_CONTROL_STOP:
		case SERVICE_CONTROL_SHUTDOWN:
			serviceStatus.dwCurrentState	= SERVICE_STOP_PENDING;
			serviceStatus.dwWaitHint	= 4000;
			SetServiceStatus(serviceHandle,&serviceStatus);

			ZBX_DO_EXIT();

			/* Allow other threads to terminate */
			zbx_sleep(1);

			serviceStatus.dwCurrentState	= SERVICE_STOPPED;
			serviceStatus.dwWaitHint	= 0;
			serviceStatus.dwCheckPoint	= 0; 
			serviceStatus.dwWin32ExitCode	= 0; 
			break;
		default:
			break;
	}

	SetServiceStatus(serviceHandle, &serviceStatus);
}

/*
 * The entry point for a ZABBIX service.
 */

static VOID WINAPI ServiceEntry(DWORD argc,LPTSTR *argv)
{
	serviceHandle = RegisterServiceCtrlHandler(ZABBIX_SERVICE_NAME, ServiceCtrlHandler);

	/* Now we start service initialization */
	serviceStatus.dwServiceType		= SERVICE_WIN32_OWN_PROCESS;
	serviceStatus.dwCurrentState		= SERVICE_START_PENDING;
	serviceStatus.dwControlsAccepted	= SERVICE_ACCEPT_STOP | SERVICE_ACCEPT_SHUTDOWN;
	serviceStatus.dwWin32ExitCode		= 0;
	serviceStatus.dwServiceSpecificExitCode	= 0;
	serviceStatus.dwCheckPoint		= 0;
	serviceStatus.dwWaitHint		= 2000;

	SetServiceStatus(serviceHandle, &serviceStatus);

	/* Now service is running */
	serviceStatus.dwCurrentState	= SERVICE_RUNNING;
	serviceStatus.dwWaitHint	= 0;
	SetServiceStatus(serviceHandle, &serviceStatus);

	MAIN_ZABBIX_ENTRY();
}


/*
 * Initialize service
 */

void service_start(void)
{
	int c = 0;
	static SERVICE_TABLE_ENTRY serviceTable[] = {
		{ ZABBIX_SERVICE_NAME, (LPSERVICE_MAIN_FUNCTION)ServiceEntry },
		{ NULL,NULL } 
		};

	/* Create synchronization stuff */
/*	eventShutdown = CreateEvent(NULL,TRUE,FALSE,NULL); */

	if (!StartServiceCtrlDispatcher(serviceTable))
	{
		if(ERROR_FAILED_SERVICE_CONTROLLER_CONNECT == GetLastError())
		{
			zbx_error("\n\n\t!!!ATTENTION!!! ZABBIX Agent runned as a console application. !!!ATTENTION!!!\n");
			MAIN_ZABBIX_ENTRY();
		}
		else
		{
			zbx_error("StartServiceCtrlDispatcher() failed: %s", strerror_from_system(GetLastError()));
		}

	}

/*	CloseHandle(eventShutdown); */
}


/*
 * Create service
 */

int ZabbixCreateService(char *path)
{
#define MAX_CMD_LEN MAX_PATH*2

	SC_HANDLE mgr,service;
	char	execName[MAX_PATH];
	char	configFile[MAX_PATH];
	char	cmdLine[MAX_CMD_LEN];
	int	ret = SUCCEED;

	_fullpath(execName, path, MAX_PATH);

	if( NULL == strstr(execName, ".exe") )
		zbx_strlcat(execName, ".exe", sizeof(execName));

	mgr = OpenSCManager(NULL,NULL,GENERIC_WRITE);
	if ( NULL == mgr )
	{
		zbx_error("ERROR: Cannot connect to Service Manager [%s]",strerror_from_system(GetLastError()));
		return FAIL;
	}

	if(NULL == CONFIG_FILE)
	{
		zbx_snprintf(cmdLine, sizeof(cmdLine), "\"%s\"", execName);
	}
	else
	{
		_fullpath(configFile, CONFIG_FILE, MAX_PATH);
		zbx_snprintf(cmdLine, sizeof(cmdLine), "\"%s\" --config \"%s\"", execName, configFile);
	}

	service = CreateService(mgr,
		ZABBIX_SERVICE_NAME,
		ZABBIX_EVENT_SOURCE,
		GENERIC_READ,
		SERVICE_WIN32_OWN_PROCESS,
		SERVICE_AUTO_START,
		SERVICE_ERROR_NORMAL,
		cmdLine,NULL,NULL,NULL,NULL,NULL);

	if (service == NULL)
	{
		DWORD code = GetLastError();

		if (ERROR_SERVICE_EXISTS == code)
		{
			zbx_error("ERROR: Service named '" ZABBIX_SERVICE_NAME "' already exist");
		}
		else
		{
			zbx_error("ERROR: Cannot create service [%s]",strerror_from_system(code));
		}
		ret = FAIL;
	}
	else
	{
		zbx_error(ZABBIX_SERVICE_NAME " service created successfully.");
		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);

	if(ret != SUCCEED)
	{
		return FAIL;
	}

	return ZabbixInstallEventSource(execName);
}


/*
 * Remove service
 */

int ZabbixRemoveService(void)
{
	SC_HANDLE mgr,service;
	int ret = SUCCEED;

	mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
	if (mgr==NULL)
	{
		zbx_error("ERROR: Cannot connect to Service Manager [%s]",strerror_from_system(GetLastError()));
		return FAIL;
	}

	service=OpenService(mgr,ZABBIX_SERVICE_NAME,DELETE);
	if (service==NULL)
	{
		zbx_error("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' [%s]", strerror_from_system(GetLastError()));
		ret = FAIL;
	}
	else
	{
		if (DeleteService(service))
		{
			zbx_error(ZABBIX_EVENT_SOURCE " service deleted successfully");
		}
		else
		{
			zbx_error("ERROR: Cannot remove service named '" ZABBIX_SERVICE_NAME "' [%s]", strerror_from_system(GetLastError()));
			ret = FAIL;
		}

		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);

	if(ret == SUCCEED)
	{
		ret = ZabbixRemoveEventSource();
	}

	return ret;
}


/*
 * Start service
 */

int ZabbixStartService(void)
{
	SC_HANDLE mgr,service;
	int ret = SUCCEED;

	mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);

	if (mgr==NULL)
	{
		zbx_error("ERROR: Cannot connect to Service Manager [%s]",strerror_from_system(GetLastError()));
		return FAIL;
	}

	service=OpenService(mgr,ZABBIX_SERVICE_NAME,SERVICE_START);

	if (service==NULL)
	{
		zbx_error("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' [%s]", strerror_from_system(GetLastError()));
		ret = FAIL;
	}
	else
	{
		if (StartService(service,0,NULL))
		{
			zbx_error(ZABBIX_SERVICE_NAME " service started successfully.");
		}
		else
		{
			zbx_error("ERROR: Cannot start service named '" ZABBIX_SERVICE_NAME "' [%s]", strerror_from_system(GetLastError()));
			ret = FAIL;
		}

		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);
	return ret;
}


/*
 * Stop service
 */

int ZabbixStopService(void)
{
	SC_HANDLE mgr,service;
	int ret = SUCCEED;

	mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
	if (mgr==NULL)
	{
		zbx_error("ERROR: Cannot connect to Service Manager [%s]",strerror_from_system(GetLastError()));
		return FAIL;
	}

	service=OpenService(mgr,ZABBIX_SERVICE_NAME,SERVICE_STOP);
	if (service==NULL)
	{
		zbx_error("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' [%s]", strerror_from_system(GetLastError()));
		ret = FAIL;
	}
	else
	{
		SERVICE_STATUS status;

		if (ControlService(service,SERVICE_CONTROL_STOP,&status))
		{
			zbx_error(ZABBIX_SERVICE_NAME " service stopped successfully.");
		}
		else
		{
			zbx_error("ERROR: Cannot stop service named '" ZABBIX_SERVICE_NAME "' [%s]", strerror_from_system(GetLastError()));
			ret = FAIL;
		}

		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);
	return ret;
}


/*
 * Install event source
 */
static int ZabbixInstallEventSource(char *path)
{
   HKEY		hKey;
   DWORD	dwTypes = EVENTLOG_ERROR_TYPE | EVENTLOG_WARNING_TYPE | EVENTLOG_INFORMATION_TYPE;
   char		execName[MAX_PATH];

	_fullpath(execName, path, MAX_PATH);

	if (ERROR_SUCCESS != RegCreateKeyEx(HKEY_LOCAL_MACHINE,
		"System\\CurrentControlSet\\Services\\EventLog\\System\\" ZABBIX_EVENT_SOURCE,
		0,
		NULL,
		REG_OPTION_NON_VOLATILE,
		KEY_SET_VALUE,
		NULL,
		&hKey,
		NULL))
	{
		zbx_error("Unable to create registry key: %s",strerror_from_system(GetLastError()));
		return FAIL;
	}

	RegSetValueEx(hKey,"TypesSupported",0,REG_DWORD,(BYTE *)&dwTypes,sizeof(DWORD));
	RegSetValueEx(hKey,"EventMessageFile",0,REG_EXPAND_SZ,(BYTE *)execName,(DWORD)strlen(execName)+1);

	RegCloseKey(hKey);
	zbx_error("Event source \"" ZABBIX_EVENT_SOURCE "\" installed successfully.");

	return SUCCEED;
}


/*
 * Remove event source
 */

static int ZabbixRemoveEventSource(void)
{

	if (ERROR_SUCCESS == RegDeleteKey(HKEY_LOCAL_MACHINE,
		"System\\CurrentControlSet\\Services\\EventLog\\System\\" ZABBIX_EVENT_SOURCE))
	{
		zbx_error("Event source \"" ZABBIX_EVENT_SOURCE "\" uninstalled successfully.");
	}
	else
	{
		zbx_error("Unable to uninstall event source \"" ZABBIX_EVENT_SOURCE "\": [%s]", strerror_from_system(GetLastError()));
		return FAIL;
	}

	return SUCCEED;
}

void	init_main_process(void)
{
	signal( SIGINT,  parent_signal_handler);
	signal( SIGTERM, parent_signal_handler );
}