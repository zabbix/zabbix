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

DWORD dwTlsLogPrefix;
BOOL optStandalone=FALSE;
HANDLE eventShutdown;

char confFile[MAX_PATH]="C:\\zabbix_agentd.conf";
char logFile[MAX_PATH]="C:\\zabbix_agentd.log";
WORD confListenPort=10000;
DWORD confServerAddr=0;
DWORD confTimeout=3000;    // 3 seconds default timeout
DWORD confMaxProcTime=100; // 100 milliseconds is default acceptable collector sample processing time


//
// Initialization routine
//

BOOL Initialize(void)
{
   WSAData sockInfo;

   dwTlsLogPrefix=TlsAlloc();
   if (dwTlsLogPrefix==TLS_OUT_OF_INDEXES)
      return FALSE;
   TlsSetValue(dwTlsLogPrefix,NULL);   // Set no prefix for main thread

   // Initialize Windows Sockets API
   WSAStartup(0x0002,&sockInfo);

   InitLog();

   eventShutdown=CreateEvent(NULL,TRUE,FALSE,NULL);

   // Internal command aliases
   AddAlias("system[uptime]","perf_counter[\\System\\System Up Time]");

   // Start TCP/IP listener and collector threads
   _beginthread(CollectorThread,0,NULL);
   _beginthread(ListenerThread,0,NULL);

   return TRUE;
}


//
// Shutdown routine
//

void Shutdown(void)
{
   SetEvent(eventShutdown);
   Sleep(1000);      // Allow other threads to terminate
   WriteLog("Zabbix Win32 agent shutdown\r\n");
   CloseLog();
   TlsFree(dwTlsLogPrefix);
}


//
// Common Main()
//

void Main(void)
{
   WriteLog("Zabbix Win32 agent started\r\n");

   if (optStandalone)
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

   if (!optStandalone)
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
