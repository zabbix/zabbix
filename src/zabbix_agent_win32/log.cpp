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
** $module: log.cpp
**
**/

#include "zabbixw32.h"
#include <time.h>
#include <stdarg.h>


//
// Static data
//

static HANDLE hLog=INVALID_HANDLE_VALUE;
static HANDLE mutexLogAccess;


//
// Initialize log
//

void InitLog(void)
{
   char tbuf[32],buffer[256];
   struct tm *loc;
   time_t t;
   DWORD size;
   
   hLog=CreateFile(logFile,GENERIC_WRITE,FILE_SHARE_READ,NULL,OPEN_ALWAYS,
                     FILE_ATTRIBUTE_NORMAL,NULL);
   if (hLog==INVALID_HANDLE_VALUE)
      return;
   SetFilePointer(hLog,0,NULL,FILE_END);
   t=time(NULL);
   loc=localtime(&t);
   strftime(tbuf,32,"%d-%b-%Y %H:%M:%S",loc);
   sprintf(buffer,"**************************************************************\r\n[%s] Log file opened\r\n",tbuf);
   WriteFile(hLog,buffer,strlen(buffer),&size,NULL);

   mutexLogAccess=CreateMutex(NULL,FALSE,NULL);
}


//
// Close log
//

void CloseLog(void)
{
   if (hLog!=INVALID_HANDLE_VALUE)
      CloseHandle(hLog);
   if (mutexLogAccess!=INVALID_HANDLE_VALUE)
      CloseHandle(mutexLogAccess);
}


//
// Write log
//

void WriteLog(char *format,...)
{
   va_list args;
   char *prefix,buffer[4096];
   DWORD size;
   time_t t;
   struct tm *loc;

   // Prevent simultaneous write to log file
   WaitForSingleObject(mutexLogAccess,INFINITE);

   t=time(NULL);
   loc=localtime(&t);
   strftime(buffer,32,"[%d-%b-%Y %H:%M:%S] ",loc);
   WriteFile(hLog,buffer,strlen(buffer),&size,NULL);
   if (optStandalone)
      printf("%s",buffer);

   prefix=(char *)TlsGetValue(dwTlsLogPrefix);
   if (prefix!=NULL)    // Thread has it's log prefix
   {
      WriteFile(hLog,prefix,strlen(prefix),&size,NULL);
      if (optStandalone)
         printf("%s",prefix);
   }

   va_start(args,format);
   vsprintf(buffer,format,args);
   va_end(args);

   WriteFile(hLog,buffer,strlen(buffer),&size,NULL);
   if (optStandalone)
      printf("%s",buffer);

   ReleaseMutex(mutexLogAccess);
}
