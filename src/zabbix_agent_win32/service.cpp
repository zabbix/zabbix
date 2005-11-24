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

#include "zabbixw32.h"

//
// Static data
//

static SERVICE_STATUS_HANDLE serviceHandle;


//
// Service control handler
//

static VOID WINAPI ServiceCtrlHandler(DWORD ctrlCode)
{
   SERVICE_STATUS status;

INIT_CHECK_MEMORY(main);

   status.dwServiceType=SERVICE_WIN32_OWN_PROCESS;
   status.dwCurrentState=SERVICE_RUNNING;
   status.dwControlsAccepted=SERVICE_ACCEPT_STOP | SERVICE_ACCEPT_SHUTDOWN;
   status.dwWin32ExitCode=0;
   status.dwServiceSpecificExitCode=0;
   status.dwCheckPoint=0;
   status.dwWaitHint=0;

   switch(ctrlCode)
   {
      case SERVICE_CONTROL_STOP:
      case SERVICE_CONTROL_SHUTDOWN:
         status.dwCurrentState=SERVICE_STOP_PENDING;
         status.dwWaitHint=4000;
         SetServiceStatus(serviceHandle,&status);

//         WriteLog(MSG_SERVICE_STOPPPED,EVENTLOG_INFORMATION_TYPE,NULL);
         Shutdown();

         status.dwCurrentState=SERVICE_STOPPED;
         status.dwWaitHint=0;
         break;
      default:
         break;
   }

   SetServiceStatus(serviceHandle,&status);
CHECK_MEMORY(main,"ServiceCtrlHandler","end");
}


//
// Service main
//

static VOID WINAPI ZabbixServiceMain(DWORD argc,LPTSTR *argv)
{
   SERVICE_STATUS status;

INIT_CHECK_MEMORY(main);

   serviceHandle=RegisterServiceCtrlHandler(ZABBIX_SERVICE_NAME,ServiceCtrlHandler);

   // Now we start service initialization
   status.dwServiceType=SERVICE_WIN32_OWN_PROCESS;
   status.dwCurrentState=SERVICE_START_PENDING;
   status.dwControlsAccepted=SERVICE_ACCEPT_STOP | SERVICE_ACCEPT_SHUTDOWN;
   status.dwWin32ExitCode=0;
   status.dwServiceSpecificExitCode=0;
   status.dwCheckPoint=0;
   status.dwWaitHint=2000;
   SetServiceStatus(serviceHandle,&status);

   // Actual initialization
   if (!Initialize())
   {
      // Now service is stopped
      status.dwCurrentState=SERVICE_STOPPED;
      status.dwWaitHint=0;
      SetServiceStatus(serviceHandle,&status);
   } else {
	   // Now service is running
	   status.dwCurrentState=SERVICE_RUNNING;
	   status.dwWaitHint=0;
	   SetServiceStatus(serviceHandle,&status);

	   Main();
   }
CHECK_MEMORY(main,"ZabbixServiceMain","end");
}


//
// Initialize service
//

void InitService(void)
{
   static SERVICE_TABLE_ENTRY serviceTable[2]={ { ZABBIX_SERVICE_NAME,ZabbixServiceMain },{ NULL,NULL } };

INIT_CHECK_MEMORY(main);

   if (!StartServiceCtrlDispatcher(serviceTable))
      printf("StartServiceCtrlDispatcher() failed: %s\n",GetSystemErrorText(GetLastError()));

CHECK_MEMORY(main,"InitService","end");
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

   sprintf(cmdLine,"\"%s\" --config \"%s\"",execName,confFile);
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
	   ret = ZabbixInstallEventSource(execName);

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

int ZabbixInstallEventSource(char *path)
{
   HKEY hKey;
   DWORD dwTypes=EVENTLOG_ERROR_TYPE | EVENTLOG_WARNING_TYPE | EVENTLOG_INFORMATION_TYPE;

INIT_CHECK_MEMORY(main);

   if (ERROR_SUCCESS!=RegCreateKeyEx(HKEY_LOCAL_MACHINE,
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

int ZabbixRemoveEventSource(void)
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
