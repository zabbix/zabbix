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
#include <pdh.h>
#include <psapi.h>
#include "../../include/common.h"
#include "md5.h"
#include "messages.h"


//
// Common constants
//

#ifdef _DEBUG
#define DEBUG_SUFFIX          "-debug"
#else
#define DEBUG_SUFFIX
#endif
#define AGENT_VERSION         "1.0.0-beta8(rc5)" DEBUG_SUFFIX

#define ZABBIX_SERVICE_NAME   "ZabbixAgentdW32"
#define ZABBIX_EVENT_SOURCE   "Zabbix Win32 Agent"

#define COMMAND_TIMEOUT       5

#define MAX_SERVERS           32
#define MAX_ZABBIX_CMD_LEN    MAX_STRING_LEN
#define MAX_CPU               16
#define MAX_PARAMETERS        256
#define MAX_PARAM_NAME        64
#define MAX_PROCESSES         4096
#define MAX_MODULES           512
#define MAX_ALIAS_NAME        120
#define MAX_COUNTER_NAME      (MAX_ALIAS_NAME-12)

#define SYSINFO_RC_SUCCESS       0
#define SYSINFO_RC_NOTSUPPORTED  1
#define SYSINFO_RC_ERROR         2


//
// Parameter definition structure
//

struct AGENT_COMMAND
{
   char name[MAX_PARAM_NAME];               // Command's name
   LONG (* handler_float)(char *,char *,double *); // Handler if return value is floating point numeric
   LONG (* handler_string)(char *,char *,char **); // Handler if return value is string
   char *arg;                               // Optional command argument
};


//
// Alias information structure
//

struct ALIAS
{
   ALIAS *next;
   char name[MAX_ALIAS_NAME];
   char *value;
};


//
// User-defined performance counter structure
//

struct USER_COUNTER
{
   USER_COUNTER *next;              // Pointer to next counter in chain
   char name[MAX_PARAM_NAME];
   char counterPath[MAX_PATH];
   LONG interval;                   // Time interval used in calculations
   LONG currPos;                    // Current position in buffer
   HCOUNTER handle;                 // Counter handle (set by collector thread)
   PDH_RAW_COUNTER *rawValueArray;
   double lastValue;                // Last computed average value
};


//
// Functions
//

BOOL ParseCommandLine(int argc,char *argv[]);
char *GetSystemErrorText(DWORD error);
char *GetPdhErrorText(DWORD error);
BOOL MatchString(char *pattern,char *string);
void StrStrip(char *string);
void GetParameterInstance(char *param,char *instance,int maxSize);

void CalculateMD5Hash(const unsigned char *data,int nbytes,unsigned char *hash);
DWORD CalculateCRC32(const unsigned char *data,DWORD nbytes);

void InitService(void);
void ZabbixCreateService(char *execName);
void ZabbixRemoveService(void);
void ZabbixStartService(void);
void ZabbixStopService(void);

void ZabbixInstallEventSource(char *path);
void ZabbixRemoveEventSource(void);

void InitLog(void);
void CloseLog(void);
void WriteLog(DWORD msg,WORD wType,char *format...);

BOOL Initialize(void);
void Shutdown(void);
void Main(void);

void ListenerThread(void *);
void CollectorThread(void *);

void ProcessCommand(char *cmd,char *result);

BOOL ReadConfig(void);

BOOL AddAlias(char *name,char *value);
void ExpandAlias(char *orig,char *expanded);


//
// Global variables
//

extern DWORD dwTlsLogPrefix;
extern HANDLE eventShutdown;

extern BOOL optStandalone;
extern BOOL optUseEventLog;

extern char confFile[];
extern char logFile[];
extern DWORD confServerAddr[];
extern DWORD confServerCount;
extern WORD confListenPort;
extern DWORD confTimeout;
extern DWORD confMaxProcTime;

extern USER_COUNTER *userCounterList;

extern double statProcUtilization[];
extern double statProcUtilization5[];
extern double statProcUtilization15[];
extern double statProcLoad;
extern double statProcLoad5;
extern double statProcLoad15;
extern double statAvgCollectorTime;
extern double statMaxCollectorTime;

extern DWORD (__stdcall *imp_GetGuiResources)(HANDLE,DWORD);
extern BOOL (__stdcall *imp_GetProcessIoCounters)(HANDLE,PIO_COUNTERS);
extern BOOL (__stdcall *imp_GetPerformanceInfo)(PPERFORMANCE_INFORMATION,DWORD);

#endif
