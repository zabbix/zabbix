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
#include "common.h"
#include "md5.h"


//
// Common constants
//

#define AGENT_VERSION         "1.0.0-alpha4"

#define ZABBIX_SERVICE_NAME   "ZabbixAgentdW32"

#define COMMAND_TIMEOUT       5

#define MAX_ZABBIX_CMD_LEN    MAX_STRING_LEN
#define MAX_CPU               16
#define MAX_PARAMETERS        256
#define MAX_PARAM_NAME        64
#define MAX_PROCESSES         4096
#define MAX_MODULES           512
#define MAX_ALIAS_NAME        120
#define MAX_COUNTER_NAME      (MAX_ALIAS_NAME-12)


//
// Parameter definition structure
//

struct AGENT_COMMAND
{
   char name[MAX_PARAM_NAME];               // Command's name
   double (* handler_float)(char *,char *); // Handler if return value is floating point numeric
   char *(* handler_string)(char *,char *); // Handler if return value is string
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
BOOL MatchString(char *pattern,char *string);
void StrStrip(char *string);

void CalculateMD5Hash(const unsigned char *data,int nbytes,unsigned char *hash);
DWORD CalculateCRC32(const unsigned char *data,DWORD nbytes);

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

BOOL AddAlias(char *name,char *value);
void ExpandAlias(char *orig,char *expanded);


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
extern DWORD confTimeout;
extern DWORD confMaxProcTime;

extern USER_COUNTER *userCounterList;

extern double statProcUtilization[];
extern double statProcUtilization5[];
extern double statProcUtilization15[];
extern double statProcLoad;
extern double statProcLoad5;
extern double statProcLoad15;

#endif
