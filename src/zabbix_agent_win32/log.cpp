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
   if (optUseEventLog)
   {
      hLog=RegisterEventSource(NULL,ZABBIX_EVENT_SOURCE);
   }
   else
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
}


//
// Close log
//

void CloseLog(void)
{
   if (optUseEventLog)
   {
      DeregisterEventSource(hLog);
   }
   else
   {
      if (hLog!=INVALID_HANDLE_VALUE)
         CloseHandle(hLog);
      if (mutexLogAccess!=INVALID_HANDLE_VALUE)
         CloseHandle(mutexLogAccess);
   }
}


//
// Write record to log file
//

static void WriteLogToFile(char *message)
{
   char *prefix,buffer[64];
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

   WriteFile(hLog,message,strlen(message),&size,NULL);
   if (optStandalone)
      printf("%s",message);

   ReleaseMutex(mutexLogAccess);
}


//
// Write log record
// Parameters:
// msg    - Message ID
// wType  - Message type (see ReportEvent() for details)
// format - Parameter format string, each parameter represented by one character.
//          The following format characters can be used:
//             s - String
//             d - Decimal integer
//             x - Hex integer
//

void WriteLog(DWORD msg,WORD wType,char *format...)
{
   va_list args;
   char *strings[16];
   int numStrings=0;

   memset(strings,0,sizeof(char *)*16);

   if (format!=NULL)
   {
      va_start(args,format);

      for(;(format[numStrings]!=0)&&(numStrings<16);numStrings++)
      {
         switch(format[numStrings])
         {
            case 's':
               strings[numStrings]=strdup(va_arg(args,char *));
               break;
            case 'd':
               strings[numStrings]=(char *)malloc(16);
               sprintf(strings[numStrings],"%d",va_arg(args,LONG));
               break;
            case 'x':
               strings[numStrings]=(char *)malloc(16);
               sprintf(strings[numStrings],"0x%08X",va_arg(args,DWORD));
               break;
            default:
               strings[numStrings]=(char *)malloc(32);
               sprintf(strings[numStrings],"BAD FORMAT (0x%08X)",va_arg(args,DWORD));
               break;
         }
      }
      va_end(args);
   }

   if (optUseEventLog)
   {
      ReportEvent(hLog,wType,0,msg,NULL,numStrings,0,(const char **)strings,NULL);
   }
   else
   {
      LPVOID lpMsgBuf;

      if (FormatMessage(FORMAT_MESSAGE_ALLOCATE_BUFFER | FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_ARGUMENT_ARRAY,
                        NULL,msg,0,(LPTSTR)&lpMsgBuf,0,strings)>0)
      {
         WriteLogToFile((char *)lpMsgBuf);
         LocalFree(lpMsgBuf);
      }
      else
      {
         char message[64];

         sprintf(message,"MSG 0x%08X - Unable to find message text\r\n",msg);
         WriteLogToFile(message);
      }
   }

   while(--numStrings>=0)
      free(strings[numStrings]);
}
