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
char confHostname[MAX_PATH]="0.0.0.0";
char confServer[MAX_PATH]="0.0.0.0";

WORD confListenPort=10050; // Alexei: New defailt port 10000 -> 10050
WORD confServerPort=10051;
DWORD confServerAddr[MAX_SERVERS];
DWORD confServerCount=0;
DWORD confTimeout=3000;    // 3 seconds default timeout
DWORD confMaxProcTime=1000; // 1000 milliseconds is default acceptable collector sample processing time
DWORD confEnableRemoteCommands=0; // by default disabled
DWORD g_dwLogLevel = EVENTLOG_ERROR_TYPE | EVENTLOG_WARNING_TYPE | EVENTLOG_INFORMATION_TYPE;

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

INIT_CHECK_MEMORY(main);

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
CHECK_MEMORY(main, "ImportSymbols", "end");
}


//
// Load subagent
//

static BOOL LoadSubAgent(char *name,char *cmdLine)
{
   SUBAGENT *sbi;
   BOOL ret = TRUE;

   int rc;

   sbi=(SUBAGENT *)malloc(sizeof(SUBAGENT));

   sbi->hModule=LoadLibrary(name);
   if (sbi->hModule==NULL)
   {
      WriteLog(MSG_LOAD_FAILED,EVENTLOG_ERROR_TYPE,"se",name,GetLastError());
	  ret = FALSE;
	  goto lbl_FreeSbi;
   }

   sbi->init	=(int  (__zabbix_api *)(char *,SUBAGENT_COMMAND **))GetProcAddress(sbi->hModule,"zabbix_subagent_init");
   sbi->shutdown=(void (__zabbix_api *)(void))GetProcAddress(sbi->hModule,"zabbix_subagent_shutdown");
   if ((sbi->init==NULL) || (sbi->shutdown==NULL))
   {
      WriteLog(MSG_NO_ENTRY_POINTS,EVENTLOG_ERROR_TYPE,"s",name);
	  ret = FALSE;
	  goto lbl_CloseLibrary;
   }

   if ((rc=sbi->init(cmdLine, &sbi->cmdList))!=0)
   {
      WriteLog(MSG_SUBAGENT_INIT_FAILED, EVENTLOG_ERROR_TYPE, "sd", name, rc);
	  ret = FALSE;
	  goto lbl_CloseLibrary;
   }

   // Add new subagent to chain
   sbi->next = subagentList;
   subagentList = sbi;

   FreeLibrary(sbi->hModule);

   WriteLog(MSG_SUBAGENT_LOADED,EVENTLOG_INFORMATION_TYPE,"s",name);

lbl_CloseLibrary:
	FreeLibrary(sbi->hModule);

lbl_FreeSbi:
	if(ret == FALSE)
		free(sbi);

	return ret;
}

static void	FreeSubagentList(void)
{
	SUBAGENT	*curr;
	SUBAGENT	*next;
		
	next = subagentList;
	while(next!=NULL)
	{
		curr = next;
		next = curr->next;
		free(curr);
	}
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

   // Dynamically import functions that may not be presented in all Windows versions
   ImportSymbols();

   // Load subagents
   if (subagentNameList!=NULL)
   {
      for(i=0; subagentNameList[i].name!=NULL; i++)
         LoadSubAgent(subagentNameList[i].name,subagentNameList[i].cmdLine);

	  FreeSubagentNameList();
   }

   // Create synchronization stuff
   eventShutdown=CreateEvent(NULL,TRUE,FALSE,NULL);
   eventCollectorStarted=CreateEvent(NULL,TRUE,FALSE,NULL);

   // Internal command aliases
   sprintf(counterPath,"perf_counter[\\%s\\%s]",GetCounterName(PCI_SYSTEM),GetCounterName(PCI_SYSTEM_UP_TIME));
	if(AddAlias("system.uptime",counterPath))
	{
LOG_DEBUG_INFO("s","AddAlias == OK");
	}
	else
	{
LOG_DEBUG_INFO("s","AddAlias == FAIL");
	}

   // Start TCP/IP listener and collector threads

   _beginthread(CollectorThread,0,NULL);
   WaitForSingleObject(eventCollectorStarted,INFINITE);  // Allow collector thread to initialize

   _beginthread(ListenerThread,0,NULL);

   _beginthread(ActiveChecksThread,0,NULL);

   CloseHandle(eventCollectorStarted);

   return TRUE;
}


//
// Shutdown routine
//

void Shutdown(void)
{
   SetEvent(eventShutdown);
   Sleep(2000);      // Allow other threads to terminate
   WriteLog(MSG_AGENT_SHUTDOWN,EVENTLOG_INFORMATION_TYPE,NULL);
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
      for(;;)
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
	int ret = 0;

INIT_CHECK_MEMORY(main);

   if (!ParseCommandLine(argc,argv))
   {
      ret = 1;
	  goto lbl_End;
   }

   if (!ReadConfig())
   {
      ret = 1;
	  goto lbl_End;
   }

   InitLog();

   if (!IsStandalone())
   {
      InitService();
   }
   else
   {
      if (!Initialize())
      {
         printf("Zabbix Win32 agent initialization failed\n");
	     ret = 1;
		 goto lbl_End;
      }
	  if(test_cmd)
	  {
		TestCommand();
	  }
	  else
	  {
		Main();
	  }
   }

lbl_End:
	CloseHandle(eventShutdown);

	FreeSubagentList();
	FreeSubagentNameList();
	FreeCounterList();
	FreeUserCounterList();
	FreeAliasList();

CHECK_MEMORY(main, "main","end")
#if defined(ENABLE_CHECK_MEMOTY)
	else LOG_DEBUG_INFO("s", "main: Memory OK!");
#else
	;
#endif //_DEBUG

   CloseLog();

   return ret;
}
