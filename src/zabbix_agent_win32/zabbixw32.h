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
** $module: zabbixw32.h
**
**/

#ifndef _zabbixw32_h_
#define _zabbixw32_h_

#include <windows.h>
#include <process.h>
#include <stdio.h>
#include "common.h"


//
// Common constants
//

#define AGENT_VERSION         "1.0.0-alpha2"

#define ZABBIX_SERVICE_NAME   "ZabbixAgentdW32"

#define MAX_ZABBIX_CMD_LEN    MAX_STRING_LEN

#define MAX_CPU               16
#define MAX_PARAMETERS        256
#define MAX_PARAM_NAME        64
#define MAX_PROCESSES         4096
#define MAX_MODULES           512


//
// Parameter definition structure
//

typedef struct
{
   char name[MAX_PARAM_NAME];                   // Command's name
   float (* handler_float)(char *,char *);       // Handler if return value is numeric
   char * (* handler_string)(char *,char *);     // Handler if return value is string
   char *arg;                                   // Optional command argument
} AGENT_COMMAND;


//
// Functions
//

BOOL ParseCommandLine(int argc,char *argv[]);
char *GetSystemErrorText(DWORD error);
BOOL MatchString(char *pattern,char *string);
void StrStrip(char *string);

void InitService(void);
void ZabbixCreateService(char *execName);
void ZabbixRemoveService(void);
void ZabbixStartService(void);
void ZabbixStopService(void);

void InitLog(void);
void CloseLog(void);
void WriteLog(char *format,...);

BOOL Initialize(void);
void Shutdown(void);
void Main(void);

void ListenerThread(void *);
void CollectorThread(void *);

void ProcessCommand(char *cmd,char *result);

BOOL ReadConfig(void);


//
// Global variables
//

extern DWORD dwTlsLogPrefix;
extern HANDLE eventShutdown;
extern BOOL optStandalone;

extern char confFile[];
extern char logFile[];
extern DWORD confServerAddr;
extern WORD confListenPort;

extern float statProcUtilization[];
extern float statProcUtilization5[];
extern float statProcUtilization15[];
extern float statProcLoad;
extern float statProcLoad5;
extern float statProcLoad15;

#endif
