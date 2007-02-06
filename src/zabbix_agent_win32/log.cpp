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

static HANDLE mutexLogAccess;
static HANDLE hLog=INVALID_HANDLE_VALUE;

//
// Write record to log file
//
static void WriteLogToFile(char *message)
{
	char buffer[64];
	char logFile_old[MAX_STRING_LEN+5];
	DWORD size;
	time_t t;
	struct tm *loc;

	if (mutexLogAccess!=INVALID_HANDLE_VALUE)
		WaitForSingleObject(mutexLogAccess,INFINITE);

	hLog = CreateFile(logFile,GENERIC_WRITE,FILE_SHARE_READ,NULL,OPEN_ALWAYS,
						FILE_ATTRIBUTE_NORMAL,NULL);
	if (hLog==INVALID_HANDLE_VALUE)
		return;

	SetFilePointer(hLog,0,NULL,FILE_END);

	t=time(NULL);
	loc=localtime(&t);
	strftime(buffer,32,"[%d-%b-%Y %H:%M:%S] ",loc);

	// Prevent simultaneous write to log file

	WriteFile(hLog,buffer,(DWORD)strlen(buffer),&size,NULL);
	if (IsStandalone())
		printf("%s",buffer);

	WriteFile(hLog,message,(DWORD)strlen(message),&size,NULL);

	FlushFileBuffers(hLog);

	size = GetFileSize(hLog, NULL);

	if (hLog!=INVALID_HANDLE_VALUE)
	{
		CloseHandle(hLog);
		hLog = INVALID_HANDLE_VALUE;
	}

	if(size > MAX_LOG_FILE_LEN)
	{
		strscpy(logFile_old, logFile);
		strncat(logFile_old, ".old", MAX_STRING_LEN);
		if(MoveFileEx(logFile, logFile_old, MOVEFILE_REPLACE_EXISTING)!=0)
		{
/*			exit(1);*/
		}
	}

	if (mutexLogAccess!=INVALID_HANDLE_VALUE)
		ReleaseMutex(mutexLogAccess);

	if (IsStandalone())
		printf("%s",message);
	
}

//
// Initialize log
//

void InitLog(void)
{
   if (dwFlags & AF_USE_EVENT_LOG)
   {
      hLog=RegisterEventSource(NULL,ZABBIX_EVENT_SOURCE);
   }
   else
   {
      char buffer[256];

	  mutexLogAccess=CreateMutex(NULL,FALSE,NULL);

      sprintf(buffer,"*************** Log file opened ****************\r\n");
	  WriteLogToFile(buffer);
   }
}


//
// Close log
//

void CloseLog(void)
{
	if (dwFlags & AF_USE_EVENT_LOG)
	{
		DeregisterEventSource(hLog);
	}
	else
	{
		if (mutexLogAccess!=INVALID_HANDLE_VALUE)
			CloseHandle(mutexLogAccess);
	}
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
//             e - System error code (will appear in log as textual description)
//

void WriteLog(DWORD msg,WORD wType,char *format...)
{
   va_list args;
   char *strings[16],*msgBuf;
   char *ptmp;
   int numStrings=0;
   DWORD error;

   if (!((DWORD)wType & g_dwLogLevel))
      return;

   memset(strings,0,sizeof(char *)*16);

   if (format!=NULL)
   {
      va_start(args,format);

      for(numStrings=0; (format[numStrings]!=0)&&(numStrings<16); numStrings++)
      {
         switch(format[numStrings])
         {
            case 's':
               ptmp = va_arg(args,char *);

               if(ptmp)
				   strings[numStrings] = strdup(ptmp);
			   else
				   strings[numStrings] = strdup("");
               break;
            case 'd':
               strings[numStrings]=(char *)malloc(16);
               sprintf(strings[numStrings],"%d",va_arg(args,LONG));
               break;
            case 'x':
               strings[numStrings]=(char *)malloc(16);
               sprintf(strings[numStrings],"0x%08X",va_arg(args,DWORD));
               break;
            case 'e':
               error=va_arg(args,DWORD);
               if (FormatMessage(FORMAT_MESSAGE_ALLOCATE_BUFFER | 
                                 FORMAT_MESSAGE_FROM_SYSTEM | 
                                 FORMAT_MESSAGE_IGNORE_INSERTS,
                                 NULL,error,
                                 MAKELANGID(LANG_NEUTRAL,SUBLANG_DEFAULT), // Default language
                                 (LPSTR)&msgBuf,0,NULL)>0)
               {
                  msgBuf[strcspn(msgBuf,"\r\n")]=0;
                  strings[numStrings]=(char *)malloc(strlen(msgBuf)+1);
                  strcpy(strings[numStrings],msgBuf);
                  LocalFree(msgBuf);
               }
               else
               {
                  strings[numStrings]=(char *)malloc(64);
                  sprintf(strings[numStrings],"1. MSG 0x%08X - Unable to find message text",error);
               }
               break;
            default:
               strings[numStrings]=(char *)malloc(32);
               sprintf(strings[numStrings],"BAD FORMAT [%c] (0x%08X)", format[numStrings], va_arg(args,DWORD));
               break;
         }
      }
      va_end(args);
   }

   if (dwFlags & AF_USE_EVENT_LOG)
   {

      ReportEvent(hLog,wType,0,msg,NULL,(unsigned short)(numStrings),0,(const char **)strings,NULL);
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

         sprintf(message,"2. MSG 0x%08X - Unable to find message text [0x%X]\r\n",msg , GetLastError());

         WriteLogToFile(message);
      }
   }

   while(--numStrings>=0)
      if(strings[numStrings]) 
		  free(strings[numStrings]);
}
