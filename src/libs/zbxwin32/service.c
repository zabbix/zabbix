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
#include "service.h"

#include "alias.h"
#include "perfmon.c"
#include "cfg.h"
#include "log.h"

static int ZabbixRemoveEventSource(void);
static int ZabbixInstallEventSource(char *path);

//
// Static data
//

static	SERVICE_STATUS		serviceStatus;
static	SERVICE_STATUS_HANDLE	serviceHandle;

static	HANDLE eventShutdown;

//DWORD (__stdcall *imp_GetGuiResources)(HANDLE,DWORD);				// use GetGuiResources
//BOOL (__stdcall *imp_GetProcessIoCounters)(HANDLE,PIO_COUNTERS);		// use GetProcessIoCounters
//BOOL (__stdcall *imp_GetPerformanceInfo)(PPERFORMANCE_INFORMATION,DWORD);	// use GetPerformanceInfo
//BOOL (__stdcall *imp_GlobalMemoryStatusEx)(LPMEMORYSTATUSEX);			// use GlobalMemoryStatusEx


//
// Shutdown routine
//

static void Shutdown(void)
{
   SetEvent(eventShutdown);
   Sleep(2000);      // Allow other threads to terminate
}

//
// ZABBIX service control handler
//

static VOID WINAPI ServiceCtrlHandler(DWORD ctrlCode)
{
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

			Shutdown();

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

//
// Get proc address and write log file
//

static FARPROC GetProcAddressAndLog(HMODULE hModule,LPCSTR procName)
{
	FARPROC ptr;

	ptr=GetProcAddress(hModule,procName);
	if ((ptr==NULL)&&(dwFlags & AF_LOG_UNRESOLVED_SYMBOLS))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unable to resolve symbol \"%s\"", procName);
	}
	return ptr;
}

//
// Import symbols
//

static void ImportSymbols(void)
{
	/* 
	// Unneeded code, i think what this code can be removed
	// libraries already loaded, and prototype is used from "windows.h"
	// (Eugene)

	HMODULE hModule;
	char ModuleName[0x32];

	sprintf(ModuleName,"USER32.DLL");
	hModule = GetModuleHandle(ModuleName);
	if (NULL == hModule)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unable to get handle to \"%s\"", ModuleName);
	}
	else
	{
		imp_GetGuiResources=(DWORD (__stdcall *)(HANDLE,DWORD))GetProcAddressAndLog(hModule,"GetGuiResources");
	}

	sprintf(ModuleName,"KERNEL32.DLL");
	hModule = GetModuleHandle(ModuleName);
	if (NULL == hModule)
	{
		WriteLog(MSG_NO_DLL,EVENTLOG_WARNING_TYPE,"s",ModuleName);
	}
	else
	{
		imp_GetProcessIoCounters=(BOOL (__stdcall *)(HANDLE,PIO_COUNTERS))GetProcAddressAndLog(hModule,"GetProcessIoCounters");
		imp_GlobalMemoryStatusEx=(BOOL (__stdcall *)(LPMEMORYSTATUSEX))GetProcAddressAndLog(hModule,"GlobalMemoryStatusEx");
	}

	sprintf(ModuleName,PSAPI.DLL");
	hModule=GetModuleHandle(ModuleName);
	if (NULL == hModule)
	{
		WriteLog(MSG_NO_DLL,EVENTLOG_WARNING_TYPE,"s",ModuleName);
	}
	else
	{
		imp_GetPerformanceInfo=(BOOL (__stdcall *)(PPERFORMANCE_INFORMATION,DWORD))GetProcAddressAndLog(hModule,"GetPerformanceInfo");
	}
	*/
}


//
// The entry point for a ZABBIX service.
//

static VOID WINAPI ServiceEntry(DWORD argc,LPTSTR *argv)
{
	WSADATA sockInfo;
	char counterPath[MAX_COUNTER_PATH * 2 + 50];

	serviceHandle = RegisterServiceCtrlHandler(ZABBIX_SERVICE_NAME, ServiceCtrlHandler);

	// Now we start service initialization
	serviceStatus.dwServiceType		= SERVICE_WIN32_OWN_PROCESS;
	serviceStatus.dwCurrentState		= SERVICE_START_PENDING;
	serviceStatus.dwControlsAccepted	= SERVICE_ACCEPT_STOP | SERVICE_ACCEPT_SHUTDOWN;
	serviceStatus.dwWin32ExitCode		= 0;
	serviceStatus.dwServiceSpecificExitCode	= 0;
	serviceStatus.dwCheckPoint		= 0;
	serviceStatus.dwWaitHint		= 2000;

	SetServiceStatus(serviceHandle, &serviceStatus);

	// Initialize Windows Sockets API
	WSAStartup(0x0002,&sockInfo);

	// Dynamically import functions that may not be presented in all Windows versions
	ImportSymbols();

#ifdef TODO

	// Load subagents
	if (subagentNameList!=NULL)
	{
		for(i=0; subagentNameList[i].name!=NULL; i++)
		LoadSubAgent(subagentNameList[i].name,subagentNameList[i].cmdLine);

		FreeSubagentNameList();
	}
#endif /* TODO */

	// Create synchronization stuff
	eventShutdown		= CreateEvent(NULL,TRUE,FALSE,NULL);

	// Internal command aliases
	sprintf(counterPath,"perf_counter[\\%s\\%s]",GetCounterName(PCI_SYSTEM),GetCounterName(PCI_SYSTEM_UP_TIME));
	AddAlias("system.uptime",counterPath);

	// Now service is running
	serviceStatus.dwCurrentState	= SERVICE_RUNNING;
	serviceStatus.dwWaitHint	= 0;
	SetServiceStatus(serviceHandle, &serviceStatus);

	MAIN_ZABBIX_ENTRY();
}


//
// Initialize service
//

void init_service(void)
{
	static SERVICE_TABLE_ENTRY serviceTable[] = {
		{ ZABBIX_SERVICE_NAME, ServiceEntry },
		{ NULL,NULL } 
		};

	eventShutdown = CreateEvent(NULL, TRUE, FALSE, NULL);

	if (!StartServiceCtrlDispatcher(serviceTable))
	{
		printf(
		"StartServiceCtrlDispatcher() failed: %s\n",
		GetSystemErrorText(GetLastError())
		);
	}

	CloseHandle(eventShutdown);
}


//
// Create service
//

int ZabbixCreateService(char *execName)
{
	SC_HANDLE mgr,service;
	char cmdLine[MAX_PATH*2];
	int ret = 0;

	INIT_CHECK_MEMORY(main);

	mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
	if (mgr==NULL)
	{
		printf("ERROR: Cannot connect to Service Manager (%s)\n",GetSystemErrorText(GetLastError()));
		return 1;
	}

	sprintf(cmdLine,"\"%s\" --config \"%s\"", execName, CONFIG_FILE);
	service=CreateService(mgr,ZABBIX_SERVICE_NAME,"Zabbix Win32 Agent",GENERIC_READ,SERVICE_WIN32_OWN_PROCESS,
	SERVICE_AUTO_START,SERVICE_ERROR_NORMAL,cmdLine,NULL,NULL,NULL,NULL,NULL);
	if (service==NULL)
		{
		DWORD code=GetLastError();

		if (code==ERROR_SERVICE_EXISTS)
		printf("ERROR: Service named '" ZABBIX_SERVICE_NAME "' already exist\n");
		else
		printf("ERROR: Cannot create service (%s)\n",GetSystemErrorText(code));
		ret = 1;
	}
	else
	{
		printf("Zabbix Win32 Agent service created successfully\n");
		CloseServiceHandle(service);
	}

	CloseServiceHandle(mgr);

	if(ret == 0)
	{
		ret = ZabbixInstallEventSource(execName);
	}

	CHECK_MEMORY(main,"ZabbixCreateService","end");
	return ret;
}


//
// Remove service
//

int ZabbixRemoveService(void)
{
   SC_HANDLE mgr,service;
   int ret = 0;

INIT_CHECK_MEMORY(main);

   mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
   if (mgr==NULL)
   {
      printf("ERROR: Cannot connect to Service Manager (%s)\n",GetSystemErrorText(GetLastError()));
CHECK_MEMORY(main,"ZabbixCreateService","OpenSCManager");
      return 1;
   }

   service=OpenService(mgr,ZABBIX_SERVICE_NAME,DELETE);
   if (service==NULL)
   {
      printf("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
             GetSystemErrorText(GetLastError()));
	  ret = 1;
   }
   else
   {
      if (DeleteService(service))
         printf("Zabbix Win32 Agent service deleted successfully\n");
      else
	  {
         printf("ERROR: Cannot remove service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
                GetSystemErrorText(GetLastError()));
		 ret = 1;
	  }

      CloseServiceHandle(service);
   }

   CloseServiceHandle(mgr);

   if(ret == 0)
	   ret = ZabbixRemoveEventSource();

CHECK_MEMORY(main,"ZabbixCreateService","end");
	return ret;
}


//
// Start service
//

int ZabbixStartService(void)
{
   SC_HANDLE mgr,service;
   int ret = 0;

INIT_CHECK_MEMORY(main);

   mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
   if (mgr==NULL)
   {
      printf("ERROR: Cannot connect to Service Manager (%s)\n",GetSystemErrorText(GetLastError()));
CHECK_MEMORY(main,"ZabbixStartService","OpenSCManager");
      return 1;
   }

   service=OpenService(mgr,ZABBIX_SERVICE_NAME,SERVICE_START);
   if (service==NULL)
   {
      printf("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
             GetSystemErrorText(GetLastError()));
	  ret=1;
   }
   else
   {
      if (StartService(service,0,NULL))
         printf("Zabbix Win32 Agent service started successfully\n");
      else
	  {
         printf("ERROR: Cannot start service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
                GetSystemErrorText(GetLastError()));
		 ret = 1;
	  }

      CloseServiceHandle(service);
   }

   CloseServiceHandle(mgr);
CHECK_MEMORY(main,"ZabbixStartService","end");
	return ret;
}


//
// Stop service
//

int ZabbixStopService(void)
{
   SC_HANDLE mgr,service;
   int ret = 0;

INIT_CHECK_MEMORY(main);

   mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
   if (mgr==NULL)
   {
      printf("ERROR: Cannot connect to Service Manager (%s)\n",GetSystemErrorText(GetLastError()));
CHECK_MEMORY(main,"ZabbixStopService","OpenSCManager");
      return 1;
   }

   service=OpenService(mgr,ZABBIX_SERVICE_NAME,SERVICE_STOP);
   if (service==NULL)
   {
      printf("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
             GetSystemErrorText(GetLastError()));
	  ret = 1;
   }
   else
   {
      SERVICE_STATUS status;

      if (ControlService(service,SERVICE_CONTROL_STOP,&status))
         printf("Zabbix Win32 Agent service stopped successfully\n");
      else
	  {
         printf("ERROR: Cannot stop service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
                GetSystemErrorText(GetLastError()));
		 ret = 1;
	  }

      CloseServiceHandle(service);
   }

   CloseServiceHandle(mgr);
CHECK_MEMORY(main,"ZabbixStopService","end");
	return ret;
}


//
// Install event source
//
static int ZabbixInstallEventSource(char *path)
{
   HKEY hKey;
   DWORD dwTypes=EVENTLOG_ERROR_TYPE | EVENTLOG_WARNING_TYPE | EVENTLOG_INFORMATION_TYPE;

INIT_CHECK_MEMORY(main);

   if (ERROR_SUCCESS != RegCreateKeyEx(HKEY_LOCAL_MACHINE,
         "System\\CurrentControlSet\\Services\\EventLog\\System\\" ZABBIX_EVENT_SOURCE,
         0,NULL,REG_OPTION_NON_VOLATILE,KEY_SET_VALUE,NULL,&hKey,NULL))
   {
      printf("Unable to create registry key: %s\n",GetSystemErrorText(GetLastError()));
CHECK_MEMORY(main,"ZabbixInstallEventSource","RegCreateKeyEx");
      return 1;
   }

   RegSetValueEx(hKey,"TypesSupported",0,REG_DWORD,(BYTE *)&dwTypes,sizeof(DWORD));
   RegSetValueEx(hKey,"EventMessageFile",0,REG_EXPAND_SZ,(BYTE *)path,strlen(path)+1);

   RegCloseKey(hKey);
   printf("Event source \"" ZABBIX_EVENT_SOURCE "\" installed successfully\n");

CHECK_MEMORY(main,"ZabbixInstallEventSource","end");
	return 0;
}


//
// Remove event source
//

static int ZabbixRemoveEventSource(void)
{

INIT_CHECK_MEMORY(main);

   if (ERROR_SUCCESS==RegDeleteKey(HKEY_LOCAL_MACHINE,
         "System\\CurrentControlSet\\Services\\EventLog\\System\\" ZABBIX_EVENT_SOURCE))
   {
      printf("Event source \"" ZABBIX_EVENT_SOURCE "\" uninstalled successfully\n");
   }
   else
   {
      printf("Unable to uninstall event source \"" ZABBIX_EVENT_SOURCE "\": %s\n",
             GetSystemErrorText(GetLastError()));
	  return 1;
   }
CHECK_MEMORY(main,"ZabbixRemoveEventSource","end");
	return 0;
}
