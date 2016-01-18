/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "service.h"

#include "cfg.h"
#include "log.h"
#include "alias.h"
#include "zbxconf.h"
#include "perfmon.h"

#define EVENTLOG_REG_PATH TEXT("SYSTEM\\CurrentControlSet\\Services\\EventLog\\")

static	SERVICE_STATUS		serviceStatus;
static	SERVICE_STATUS_HANDLE	serviceHandle;

int	application_status = ZBX_APP_RUNNING;

/* free resources allocated by MAIN_ZABBIX_ENTRY() */
void	zbx_free_service_resources();

static void	parent_signal_handler(int sig)
{
	switch (sig)
	{
		case SIGINT:
		case SIGTERM:
			ZBX_DO_EXIT();
			zabbix_log(LOG_LEVEL_INFORMATION, "Got signal. Exiting ...");
			zbx_on_exit();
			break;
	}
}

static VOID WINAPI	ServiceCtrlHandler(DWORD ctrlCode)
{
	serviceStatus.dwServiceType		= SERVICE_WIN32_OWN_PROCESS;
	serviceStatus.dwCurrentState		= SERVICE_RUNNING;
	serviceStatus.dwControlsAccepted	= SERVICE_ACCEPT_STOP | SERVICE_ACCEPT_SHUTDOWN;
	serviceStatus.dwWin32ExitCode		= 0;
	serviceStatus.dwServiceSpecificExitCode	= 0;
	serviceStatus.dwCheckPoint		= 0;
	serviceStatus.dwWaitHint		= 0;

	switch (ctrlCode)
	{
		case SERVICE_CONTROL_STOP:
		case SERVICE_CONTROL_SHUTDOWN:
			serviceStatus.dwCurrentState	= SERVICE_STOP_PENDING;
			serviceStatus.dwWaitHint	= 4000;
			SetServiceStatus(serviceHandle, &serviceStatus);

			/* notify other threads and allow them to terminate */
			ZBX_DO_EXIT();
			zbx_free_service_resources();

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

static VOID WINAPI	ServiceEntry(DWORD argc, wchar_t **argv)
{
	wchar_t	*wservice_name;

	wservice_name = zbx_utf8_to_unicode(ZABBIX_SERVICE_NAME);
	serviceHandle = RegisterServiceCtrlHandler(wservice_name, ServiceCtrlHandler);
	zbx_free(wservice_name);

	/* start service initialization */
	serviceStatus.dwServiceType		= SERVICE_WIN32_OWN_PROCESS;
	serviceStatus.dwCurrentState		= SERVICE_START_PENDING;
	serviceStatus.dwControlsAccepted	= SERVICE_ACCEPT_STOP | SERVICE_ACCEPT_SHUTDOWN;
	serviceStatus.dwWin32ExitCode		= 0;
	serviceStatus.dwServiceSpecificExitCode	= 0;
	serviceStatus.dwCheckPoint		= 0;
	serviceStatus.dwWaitHint		= 2000;

	SetServiceStatus(serviceHandle, &serviceStatus);

	/* service is running */
	serviceStatus.dwCurrentState	= SERVICE_RUNNING;
	serviceStatus.dwWaitHint	= 0;
	SetServiceStatus(serviceHandle, &serviceStatus);

	MAIN_ZABBIX_ENTRY(0);
}

void	service_start(int flags)
{
	int				ret;
	static SERVICE_TABLE_ENTRY	serviceTable[2];

	if (0 != (flags & ZBX_TASK_FLAG_FOREGROUND))
	{
		MAIN_ZABBIX_ENTRY(flags);
		return;
	}

	serviceTable[0].lpServiceName = zbx_utf8_to_unicode(ZABBIX_SERVICE_NAME);
	serviceTable[0].lpServiceProc = (LPSERVICE_MAIN_FUNCTION)ServiceEntry;
	serviceTable[1].lpServiceName = NULL;
	serviceTable[1].lpServiceProc = NULL;

	ret = StartServiceCtrlDispatcher(serviceTable);
	zbx_free(serviceTable[0].lpServiceName);

	if (0 == ret)
	{
		if (ERROR_FAILED_SERVICE_CONTROLLER_CONNECT == GetLastError())
			zbx_error("use foreground option to run Zabbix agent as console application");
		else
			zbx_error("StartServiceCtrlDispatcher() failed: %s", strerror_from_system(GetLastError()));
	}
}

static int	svc_OpenSCManager(SC_HANDLE *mgr)
{
	if (NULL != (*mgr = OpenSCManager(NULL, NULL, GENERIC_WRITE)))
		return SUCCEED;

	zbx_error("ERROR: cannot connect to Service Manager: %s", strerror_from_system(GetLastError()));

	return FAIL;
}

static int	svc_OpenService(SC_HANDLE mgr, SC_HANDLE *service, DWORD desired_access)
{
	wchar_t	*wservice_name;
	int	ret = SUCCEED;

	wservice_name = zbx_utf8_to_unicode(ZABBIX_SERVICE_NAME);

	if (NULL == (*service = OpenService(mgr, wservice_name, desired_access)))
	{
		zbx_error("ERROR: cannot open service [%s]: %s",
				ZABBIX_SERVICE_NAME, strerror_from_system(GetLastError()));
		ret = FAIL;
	}

	zbx_free(wservice_name);

	return ret;
}

static void	svc_get_fullpath(const char *path, wchar_t *fullpath, size_t max_fullpath)
{
	wchar_t	*wpath;

	wpath = zbx_acp_to_unicode(path);
	_wfullpath(fullpath, wpath, max_fullpath);
	zbx_free(wpath);
}

static void	svc_get_command_line(const char *path, int multiple_agents, wchar_t *cmdLine, size_t max_cmdLine)
{
	wchar_t	path1[MAX_PATH], path2[MAX_PATH];

	svc_get_fullpath(path, path2, MAX_PATH);

	if (NULL == wcsstr(path2, TEXT(".exe")))
		StringCchPrintf(path1, MAX_PATH, TEXT("%s.exe"), path2);
	else
		StringCchPrintf(path1, MAX_PATH, path2);

	if (NULL != CONFIG_FILE)
	{
		svc_get_fullpath(CONFIG_FILE, path2, MAX_PATH);
		StringCchPrintf(cmdLine, max_cmdLine, TEXT("\"%s\" %s--config \"%s\""),
				path1,
				(0 == multiple_agents) ? TEXT("") : TEXT("--multiple-agents "),
				path2);
	}
	else
		StringCchPrintf(cmdLine, max_cmdLine, TEXT("\"%s\""), path1);
}

static int	svc_install_event_source(const char *path)
{
	HKEY	hKey;
	DWORD	dwTypes = EVENTLOG_ERROR_TYPE | EVENTLOG_WARNING_TYPE | EVENTLOG_INFORMATION_TYPE;
	wchar_t	execName[MAX_PATH];
	wchar_t	regkey[256], *wevent_source;

	svc_get_fullpath(path, execName, MAX_PATH);

	wevent_source = zbx_utf8_to_unicode(ZABBIX_EVENT_SOURCE);
	StringCchPrintf(regkey, ARRSIZE(regkey), EVENTLOG_REG_PATH TEXT("System\\%s"), wevent_source);
	zbx_free(wevent_source);

	if (ERROR_SUCCESS != RegCreateKeyEx(HKEY_LOCAL_MACHINE, regkey, 0, NULL, REG_OPTION_NON_VOLATILE,
			KEY_SET_VALUE, NULL, &hKey, NULL))
	{
		zbx_error("unable to create registry key: %s", strerror_from_system(GetLastError()));
		return FAIL;
	}

	RegSetValueEx(hKey, TEXT("TypesSupported"), 0, REG_DWORD, (BYTE *)&dwTypes, sizeof(DWORD));
	RegSetValueEx(hKey, TEXT("EventMessageFile"), 0, REG_EXPAND_SZ, (BYTE *)execName,
			(DWORD)(wcslen(execName) + 1) * sizeof(wchar_t));
	RegCloseKey(hKey);

	zbx_error("event source [%s] installed successfully", ZABBIX_EVENT_SOURCE);

	return SUCCEED;
}

int	ZabbixCreateService(const char *path, int multiple_agents)
{
	SC_HANDLE		mgr, service;
	SERVICE_DESCRIPTION	sd;
	wchar_t			cmdLine[MAX_PATH];
	wchar_t			*wservice_name;
	DWORD			code;
	int			ret = FAIL;

	if (FAIL == svc_OpenSCManager(&mgr))
		return ret;

	svc_get_command_line(path, multiple_agents, cmdLine, MAX_PATH);

	wservice_name = zbx_utf8_to_unicode(ZABBIX_SERVICE_NAME);

	if (NULL == (service = CreateService(mgr, wservice_name, wservice_name, GENERIC_READ, SERVICE_WIN32_OWN_PROCESS,
			SERVICE_AUTO_START, SERVICE_ERROR_NORMAL, cmdLine, NULL, NULL, NULL, NULL, NULL)))
	{
		if (ERROR_SERVICE_EXISTS == (code = GetLastError()))
			zbx_error("ERROR: service [%s] already exists", ZABBIX_SERVICE_NAME);
		else
			zbx_error("ERROR: cannot create service [%s]: %s", ZABBIX_SERVICE_NAME, strerror_from_system(code));
	}
	else
	{
		zbx_error("service [%s] installed successfully", ZABBIX_SERVICE_NAME);
		CloseServiceHandle(service);
		ret = SUCCEED;

		/* update the service description */
		if (SUCCEED == svc_OpenService(mgr, &service, SERVICE_CHANGE_CONFIG))
		{
			sd.lpDescription = TEXT("Provides system monitoring");
			if (0 == ChangeServiceConfig2(service, SERVICE_CONFIG_DESCRIPTION, &sd))
				zbx_error("service description update failed: %s", strerror_from_system(GetLastError()));
			CloseServiceHandle(service);
		}
	}

	zbx_free(wservice_name);

	CloseServiceHandle(mgr);

	if (SUCCEED == ret)
		ret = svc_install_event_source(path);

	return ret;
}

static int	svc_RemoveEventSource()
{
	wchar_t	regkey[256];
	wchar_t	*wevent_source;
	int	ret = FAIL;

	wevent_source = zbx_utf8_to_unicode(ZABBIX_EVENT_SOURCE);
	StringCchPrintf(regkey, ARRSIZE(regkey), EVENTLOG_REG_PATH TEXT("System\\%s"), wevent_source);
	zbx_free(wevent_source);

	if (ERROR_SUCCESS == RegDeleteKey(HKEY_LOCAL_MACHINE, regkey))
	{
		zbx_error("event source [%s] uninstalled successfully", ZABBIX_EVENT_SOURCE);
		ret = SUCCEED;
	}
	else
	{
		zbx_error("unable to uninstall event source [%s]: %s",
				ZABBIX_EVENT_SOURCE, strerror_from_system(GetLastError()));
	}

	return SUCCEED;
}

int	ZabbixRemoveService()
{
	SC_HANDLE	mgr, service;
	int		ret = FAIL;

	if (FAIL == svc_OpenSCManager(&mgr))
		return ret;

	if (SUCCEED == svc_OpenService(mgr, &service, DELETE))
	{
		if (0 != DeleteService(service))
		{
			zbx_error("service [%s] uninstalled successfully", ZABBIX_SERVICE_NAME);
			ret = SUCCEED;
		}
		else
		{
			zbx_error("ERROR: cannot remove service [%s]: %s",
					ZABBIX_SERVICE_NAME, strerror_from_system(GetLastError()));
		}

		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);

	if (SUCCEED == ret)
		ret = svc_RemoveEventSource();

	return ret;
}

int	ZabbixStartService()
{
	SC_HANDLE	mgr, service;
	int		ret = FAIL;

	if (FAIL == svc_OpenSCManager(&mgr))
		return ret;

	if (SUCCEED == svc_OpenService(mgr, &service, SERVICE_START))
	{
		if (0 != StartService(service, 0, NULL))
		{
			zbx_error("service [%s] started successfully", ZABBIX_SERVICE_NAME);
			ret = SUCCEED;
		}
		else
		{
			zbx_error("ERROR: cannot start service [%s]: %s",
					ZABBIX_SERVICE_NAME, strerror_from_system(GetLastError()));
		}

		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);

	return ret;
}

int	ZabbixStopService()
{
	SC_HANDLE	mgr, service;
	SERVICE_STATUS	status;
	int		ret = FAIL;

	if (FAIL == svc_OpenSCManager(&mgr))
		return ret;

	if (SUCCEED == svc_OpenService(mgr, &service, SERVICE_STOP))
	{
		if (0 != ControlService(service, SERVICE_CONTROL_STOP, &status))
		{
			zbx_error("service [%s] stopped successfully", ZABBIX_SERVICE_NAME);
			ret = SUCCEED;
		}
		else
		{
			zbx_error("ERROR: cannot stop service [%s]: %s",
					ZABBIX_SERVICE_NAME, strerror_from_system(GetLastError()));
		}

		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);

	return ret;
}

void	set_parent_signal_handler()
{
	signal(SIGINT, parent_signal_handler);
	signal(SIGTERM, parent_signal_handler);
}

void CALLBACK	ZBXEndThread(ULONG_PTR dwParam)
{
	_endthreadex(SUCCEED);
}
