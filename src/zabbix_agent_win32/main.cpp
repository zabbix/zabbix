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
** $module: main.cpp
**
**/

#include "zabbixw32.h"
#include <conio.h>


//
// Global variables
//

HANDLE eventShutdown;
HANDLE eventCollectorStarted;

DWORD dwFlags=AF_USE_EVENT_LOG;
char confFile[MAX_PATH]="C:\\zabbix_agentd.conf";
char logFile[MAX_PATH]="{EventLog}";
WORD confListenPort=10000;
DWORD confServerAddr[MAX_SERVERS];
DWORD confServerCount=0;
DWORD confTimeout=3000;    // 3 seconds default timeout
DWORD confMaxProcTime=100; // 100 milliseconds is default acceptable collector sample processing time

SUBAGENT *subagentList;    // List of loaded subagents
SUBAGENT_NAME *subagentNameList=NULL;
PERFCOUNTER *perfCounterList=NULL;

DWORD (__stdcall *imp_GetGuiResources)(HANDLE,DWORD);
BOOL (__stdcall *imp_GetProcessIoCounters)(HANDLE,PIO_COUNTERS);
BOOL (__stdcall *imp_GetPerformanceInfo)(PPERFORMANCE_INFORMATION,DWORD);
BOOL (__stdcall *imp_GlobalMemoryStatusEx)(LPMEMORYSTATUSEX);


//
// Get proc address and write log file
//

static FARPROC GetProcAddressAndLog(HMODULE hModule,LPCSTR procName)
{
   FARPROC ptr;

   ptr=GetProcAddress(hModule,procName);
   if ((ptr==NULL)&&(dwFlags & AF_LOG_UNRESOLVED_SYMBOLS))
      WriteLog(MSG_NO_FUNCTION,EVENTLOG_WARNING_TYPE,"s",procName);
   return ptr;
}


//
// Import symbols
//

static void ImportSymbols(void)
{
   HMODULE hModule;

   hModule=GetModuleHandle("USER32.DLL");
   if (hModule!=NULL)
   {
      imp_GetGuiResources=(DWORD (__stdcall *)(HANDLE,DWORD))GetProcAddressAndLog(hModule,"GetGuiResources");
   }
   else
   {
      WriteLog(MSG_NO_DLL,EVENTLOG_WARNING_TYPE,"s","USER32.DLL");
   }

   hModule=GetModuleHandle("KERNEL32.DLL");
   if (hModule!=NULL)
   {
      imp_GetProcessIoCounters=(BOOL (__stdcall *)(HANDLE,PIO_COUNTERS))GetProcAddressAndLog(hModule,"GetProcessIoCounters");
      imp_GlobalMemoryStatusEx=(BOOL (__stdcall *)(LPMEMORYSTATUSEX))GetProcAddressAndLog(hModule,"GlobalMemoryStatusEx");
   }
   else
   {
      WriteLog(MSG_NO_DLL,EVENTLOG_WARNING_TYPE,"s","KERNEL32.DLL");
   }

   hModule=GetModuleHandle("PSAPI.DLL");
   if (hModule!=NULL)
   {
      imp_GetPerformanceInfo=(BOOL (__stdcall *)(PPERFORMANCE_INFORMATION,DWORD))GetProcAddressAndLog(hModule,"GetPerformanceInfo");
   }
   else
   {
      WriteLog(MSG_NO_DLL,EVENTLOG_WARNING_TYPE,"s","PSAPI.DLL");
   }
}


//
// Load subagent
//

static BOOL LoadSubAgent(char *name,char *cmdLine)
{
   SUBAGENT *sbi;
   int rc;

   sbi=(SUBAGENT *)malloc(sizeof(SUBAGENT));
   sbi->hModule=LoadLibrary(name);
   if (sbi->hModule==NULL)
   {
      WriteLog(MSG_LOAD_FAILED,EVENTLOG_ERROR_TYPE,"se",name,GetLastError());
      free(sbi);
      return FALSE;
   }

   sbi->init=(int (__zabbix_api *)(char *,SUBAGENT_COMMAND **))GetProcAddress(sbi->hModule,"zabbix_subagent_init");
   sbi->shutdown=(void (__zabbix_api *)(void))GetProcAddress(sbi->hModule,"zabbix_subagent_shutdown");
   if ((sbi->init==NULL)||(sbi->shutdown==NULL))
   {
      WriteLog(MSG_NO_ENTRY_POINTS,EVENTLOG_ERROR_TYPE,"s",name);
      FreeLibrary(sbi->hModule);
      free(sbi);
      return FALSE;
   }

   if ((rc=sbi->init(cmdLine,&sbi->cmdList))!=0)
   {
      WriteLog(MSG_SUBAGENT_INIT_FAILED,EVENTLOG_ERROR_TYPE,"sd",name,rc);
      FreeLibrary(sbi->hModule);
      free(sbi);
      return FALSE;
   }

   // Add new subagent to chain
   sbi->next=subagentList;
   subagentList=sbi;

   WriteLog(MSG_SUBAGENT_LOADED,EVENTLOG_INFORMATION_TYPE,"s",name);

   return TRUE;
}


//
// Initialization routine
//

BOOL Initialize(void)
{
   WSAData sockInfo;
   int i;
   char counterPath[MAX_COUNTER_PATH * 2 + 50];

   // Initialize Windows Sockets API
   WSAStartup(0x0002,&sockInfo);

   InitLog();

   // Dynamically import functions that may not be presented in all Windows versions
   ImportSymbols();

   // Load subagents
   if (subagentNameList!=NULL)
   {
      for(i=0;subagentNameList[i].name!=NULL;i++)
      {
         LoadSubAgent(subagentNameList[i].name,subagentNameList[i].cmdLine);
         free(subagentNameList[i].name);
         if (subagentNameList[i].cmdLine!=NULL)
            free(subagentNameList[i].cmdLine);
      }
      free(subagentNameList);
   }

   // Create synchronization stuff
   eventShutdown=CreateEvent(NULL,TRUE,FALSE,NULL);
   eventCollectorStarted=CreateEvent(NULL,TRUE,FALSE,NULL);

   // Internal command aliases
   sprintf(counterPath,"perf_counter[\\%s\\%s]",GetCounterName(PCI_SYSTEM),GetCounterName(PCI_SYSTEM_UP_TIME));
   AddAlias("system[uptime]",counterPath);

   // Start TCP/IP listener and collector threads
   _beginthread(CollectorThread,0,NULL);
   WaitForSingleObject(eventCollectorStarted,INFINITE);  // Allow collector thread to initialize
   _beginthread(ListenerThread,0,NULL);

   CloseHandle(eventCollectorStarted);

   return TRUE;
}


//
// Shutdown routine
//

void Shutdown(void)
{
   SetEvent(eventShutdown);
   Sleep(1000);      // Allow other threads to terminate
   WriteLog(MSG_AGENT_SHUTDOWN,EVENTLOG_INFORMATION_TYPE,NULL);
   CloseLog();
}


//
// Common Main()
//

void Main(void)
{
   WriteLog(MSG_AGENT_STARTED,EVENTLOG_INFORMATION_TYPE,NULL);

   if (IsStandalone())
   {
      int ch;

      printf("\n*** Zabbix Win32 agent operational. Press ESC to terminate. ***\n");
      while(1)
      {
         ch=getch();
         if (ch==0)
            ch=-getch();

         if (ch==27)
            break;
      }

      Shutdown();
   }
   else
   {
      WaitForSingleObject(eventShutdown,INFINITE);
   }
}


//
// Entry point
//

int main(int argc,char *argv[])
{
   if (!ParseCommandLine(argc,argv))
      return 1;

   if (!ReadConfig())
      return 1;

   if (!IsStandalone())
   {
      InitService();
   }
   else
   {
      if (!Initialize())
      {
         printf("Zabbix Win32 agent initialization failed\n");
         return 1;
      }
      Main();
   }
   return 0;
}
