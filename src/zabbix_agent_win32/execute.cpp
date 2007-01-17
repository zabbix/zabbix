/* 
** ZabbixW32 - Win32 agent for Zabbix
** Copyright (C) 2002,2003 Victor Kirhenshtein
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
** $module: execute.cpp
**
**/

#include "zabbixw32.h"

LONG H_Execute(char *cmd,char *arg,char **value)
{
   char *ptr1,*ptr2;
   STARTUPINFO si = {0};
   PROCESS_INFORMATION pi = {0};
   SECURITY_ATTRIBUTES sa;
   HANDLE hOutput;
   char szTempPath[MAX_PATH],szTempFile[MAX_PATH];
   DWORD dwBytes=0;
   LONG ret = SYSINFO_RC_ERROR;

   // Extract command line
   ptr1=strchr(cmd,'{');
   ptr2=strchr(cmd,'}');
   ptr1++;
   *ptr2=0;

   // Create temporary file to hold process output
   GetTempPath(MAX_PATH-1,szTempPath);
   GetTempFileName(szTempPath,"zbx",0,szTempFile);

   sa.nLength			= sizeof(SECURITY_ATTRIBUTES);
   sa.lpSecurityDescriptor	= NULL;
   sa.bInheritHandle		= TRUE;

   hOutput=CreateFile(szTempFile,GENERIC_READ | GENERIC_WRITE,0,&sa,CREATE_ALWAYS,FILE_ATTRIBUTE_TEMPORARY,NULL);
   if (hOutput==INVALID_HANDLE_VALUE)
   {
      WriteLog(MSG_CREATE_TMP_FILE_FAILED,EVENTLOG_ERROR_TYPE,"e",GetLastError());
      return SYSINFO_RC_ERROR;
   }

   // Fill in process startup info structure
   memset(&si,0,sizeof(STARTUPINFO));
   si.cb	= sizeof(STARTUPINFO);
   si.dwFlags	= STARTF_USESTDHANDLES;
   si.hStdInput	= GetStdHandle(STD_INPUT_HANDLE);
   si.hStdOutput= hOutput;
   si.hStdError	= GetStdHandle(STD_ERROR_HANDLE);

   // Create new process
   if (!CreateProcess(NULL,ptr1,NULL,NULL,TRUE,0,NULL,NULL,&si,&pi))
   {
      WriteLog(MSG_CREATE_PROCESS_FAILED,EVENTLOG_ERROR_TYPE,"se",ptr1,GetLastError());

      ret = SYSINFO_RC_NOTSUPPORTED;
   }
   else
   {
	   // Wait for process termination and close all handles
	   WaitForSingleObject(pi.hProcess,INFINITE);
	   CloseHandle(pi.hThread);
	   CloseHandle(pi.hProcess);

	   // Rewind temporary file for reading
	   SetFilePointer(hOutput,0,NULL,FILE_BEGIN);

	   *value=(char *)malloc(MAX_STRING_LEN);	// Called and freed in function "ProcessCommand", pointer "strResult"

	   // Read process output
	   ReadFile(hOutput,*value,MAX_STRING_LEN-1,&dwBytes,NULL);
	   (*value)[dwBytes]=0;

	   ptr1=strchr(*value,'\r');
	   if (ptr1!=NULL)
	      *ptr1=0;
	   ptr1=strchr(*value,'\n');
	   if (ptr1!=NULL)
	      *ptr1=0;

	   ret = SYSINFO_RC_SUCCESS;
   }

   // Remove temporary file
   CloseHandle(hOutput);
   DeleteFile(szTempFile);

   return ret;
}

LONG H_RunCommand(char *cmd,char *arg,char **value)
{
	STARTUPINFO    si;
	PROCESS_INFORMATION  pi;
	char *ptr1,*ptr2;
	char command[MAX_ZABBIX_CMD_LEN];
	double result = 0;

	if(confEnableRemoteCommands != 1)
	{
		(*value) = NULL;
		return SYSINFO_RC_NOTSUPPORTED;
	}

	ZeroMemory(&si, sizeof(si) );
	si.cb = sizeof(si);
	ZeroMemory(&pi, sizeof(pi) );

	// Extract command line
	ptr1=strchr(cmd,'[');
	ptr2=strchr(cmd,']');
	ptr1++;
	*ptr2=0;

	if(NULL != (ptr2 = strrchr(ptr1,',')))
	{
		*ptr2=0;
		ptr2++;
	}

	if(!ptr2 || (ptr2 && strcmp(ptr2,"wait") == 0))
	{
		sprintf(command,"__exec{%s}",ptr1);
		return H_Execute(command, arg, value);
	}

	sprintf(command,"cmd /C \"%s\"",ptr1);

LOG_DEBUG_INFO("s","H_RunCommand");
LOG_DEBUG_INFO("s",command);

    GetStartupInfo(&si);

    result = (double)CreateProcess(
		NULL,	// No module name (use command line)
		command,// Name of app to launch
		NULL,	// Default process security attributes
		NULL,	// Default thread security attributes
		FALSE,	// Don't inherit handles from the parent
		0,		// Normal priority
		NULL,	// Use the same environment as the parent
		NULL,	// Launch in the current directory
		&si,	// Startup Information
		&pi);	// Process information stored upon return

	if(!result)
	{
LOG_DEBUG_INFO("s","ERROR");
LOG_DEBUG_INFO("e",GetLastError());
		*value = strdup("1");
	}
	else
	{
LOG_DEBUG_INFO("s","H_RunCommand");
		CloseHandle(pi.hProcess);
		CloseHandle(pi.hThread);
		*value = strdup("0");
	}

	return SYSINFO_RC_SUCCESS;
}
