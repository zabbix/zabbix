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

         WriteLog("Service stopped [Control code %d]\r\n",ctrlCode);
         Shutdown();

         status.dwCurrentState=SERVICE_STOPPED;
         status.dwWaitHint=0;
         break;
      default:
         break;
   }

   SetServiceStatus(serviceHandle,&status);
}


//
// Service main
//

static VOID WINAPI ZabbixServiceMain(DWORD argc,LPTSTR *argv)
{
   SERVICE_STATUS status;

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
      return;
   }

   // Now service is running
   status.dwCurrentState=SERVICE_RUNNING;
   status.dwWaitHint=0;
   SetServiceStatus(serviceHandle,&status);

   Main();
}


//
// Initialize service
//

void InitService(void)
{
   static SERVICE_TABLE_ENTRY serviceTable[2]={ { ZABBIX_SERVICE_NAME,ZabbixServiceMain },{ NULL,NULL } };

   StartServiceCtrlDispatcher(serviceTable);
}


//
// Create service
//

void ZabbixCreateService(char *execName)
{
   SC_HANDLE mgr,service;
   HKEY key;
   DWORD disp;
   char cmdLine[MAX_PATH*2];

   mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
   if (mgr==NULL)
   {
      printf("ERROR: Cannot connect to Service Manager (%s)\n",GetSystemErrorText(GetLastError()));
      return;
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
   }
   else
   {
      printf("Zabbix Win32 Agent service created successfully\n");
      CloseServiceHandle(service);
   }

   CloseServiceHandle(mgr);

   // Create event source in registry
   if (RegCreateKeyEx(HKEY_LOCAL_MACHINE,"SYSTEM\\CurrentControlSet\\Services\\EventLog\\System\\Zabbix Win32 Agent",
                      0,NULL,REG_OPTION_NON_VOLATILE,KEY_ALL_ACCESS,
                      NULL,&key,&disp)==ERROR_SUCCESS)
   {
      disp=EVENTLOG_ERROR_TYPE | EVENTLOG_WARNING_TYPE | EVENTLOG_INFORMATION_TYPE;
      RegSetValueEx(key,"TypesSupported",0,REG_DWORD,(BYTE *)&disp,sizeof(DWORD));
      RegSetValueEx(key,"EventMessageFile",0,REG_EXPAND_SZ,(BYTE *)execName,strlen(execName)+1);
      RegCloseKey(key);
   }
}


//
// Remove service
//

void ZabbixRemoveService(void)
{
   SC_HANDLE mgr,service;

   mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
   if (mgr==NULL)
   {
      printf("ERROR: Cannot connect to Service Manager (%s)\n",GetSystemErrorText(GetLastError()));
      return;
   }

   service=OpenService(mgr,ZABBIX_SERVICE_NAME,DELETE);
   if (service==NULL)
   {
      printf("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
             GetSystemErrorText(GetLastError()));
   }
   else
   {
      if (DeleteService(service))
         printf("Zabbix Win32 Agent service deleted successfully\n");
      else
         printf("ERROR: Cannot remove service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
                GetSystemErrorText(GetLastError()));

      CloseServiceHandle(service);
   }

   CloseServiceHandle(mgr);
}


//
// Start service
//

void ZabbixStartService(void)
{
   SC_HANDLE mgr,service;

   mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
   if (mgr==NULL)
   {
      printf("ERROR: Cannot connect to Service Manager (%s)\n",GetSystemErrorText(GetLastError()));
      return;
   }

   service=OpenService(mgr,ZABBIX_SERVICE_NAME,SERVICE_START);
   if (service==NULL)
   {
      printf("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
             GetSystemErrorText(GetLastError()));
   }
   else
   {
      if (StartService(service,0,NULL))
         printf("Zabbix Win32 Agent service started successfully\n");
      else
         printf("ERROR: Cannot start service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
                GetSystemErrorText(GetLastError()));

      CloseServiceHandle(service);
   }

   CloseServiceHandle(mgr);
}


//
// Stop service
//

void ZabbixStopService(void)
{
   SC_HANDLE mgr,service;

   mgr=OpenSCManager(NULL,NULL,GENERIC_WRITE);
   if (mgr==NULL)
   {
      printf("ERROR: Cannot connect to Service Manager (%s)\n",GetSystemErrorText(GetLastError()));
      return;
   }

   service=OpenService(mgr,ZABBIX_SERVICE_NAME,SERVICE_STOP);
   if (service==NULL)
   {
      printf("ERROR: Cannot open service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
             GetSystemErrorText(GetLastError()));
   }
   else
   {
      SERVICE_STATUS status;

      if (ControlService(service,SERVICE_CONTROL_STOP,&status))
         printf("Zabbix Win32 Agent service stopped successfully\n");
      else
         printf("ERROR: Cannot stop service named '" ZABBIX_SERVICE_NAME "' (%s)\n",
                GetSystemErrorText(GetLastError()));

      CloseServiceHandle(service);
   }

   CloseServiceHandle(mgr);
}
