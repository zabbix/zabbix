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
#include "common.h"
#include "md5.h"
#include "messages.h"
#include "zabbix_subagent.h"


//
// Common constants
//

#ifdef _DEBUG
#define DEBUG_SUFFIX          "-debug"
#else
#define DEBUG_SUFFIX
#endif
#define AGENT_VERSION         "1.1.1" DEBUG_SUFFIX

#ifdef _WIN64
#define PLATFORM "64"
#else /* not WIN64 */
#define PLATFORM "32"
#endif /* _WIN64 */

#define ZABBIX_SERVICE_NAME   "ZabbixAgentdW" PLATFORM
#define ZABBIX_EVENT_SOURCE   "Zabbix Win" PLATFORM " Agent"

#define COMMAND_TIMEOUT       5

#define MAX_SERVERS           32
#define MAX_ZABBIX_CMD_LEN    MAX_STRING_LEN
#define MAX_CPU               16
#define MAX_PARAM_NAME        64
#define MAX_PROCESSES         4096
#define MAX_MODULES           512
#define MAX_ALIAS_NAME        120
#define MAX_COUNTER_NAME      (MAX_ALIAS_NAME-12)


//
// Performance Counter Indexes
//

#define PCI_SYSTEM				      2
#define PCI_PROCESSOR			      238
#define PCI_PROCESSOR_TIME		      6
#define PCI_PROCESSOR_QUEUE_LENGTH	44
#define PCI_SYSTEM_UP_TIME		      674


//
// Application flags
//

#define AF_STANDALONE               0x0001
#define AF_USE_EVENT_LOG            0x0002
#define AF_LOG_UNRESOLVED_SYMBOLS   0x0004

#define IsStandalone() (dwFlags & AF_STANDALONE)

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
// Performance Countername structure
//

struct PERFCOUNTER
{
	PERFCOUNTER *next;
	DWORD pdhIndex;
	char name[MAX_COUNTER_NAME + 1];
};


//
// Subagent information structure
//

struct SUBAGENT
{
   SUBAGENT *next;                  // Pointer to next element in a chain
   HMODULE hModule;                 // DLL module handle
   int (__zabbix_api * init)(char *,SUBAGENT_COMMAND **);
   void (__zabbix_api * shutdown)(void);
   SUBAGENT_COMMAND *cmdList;       // List of subagent's commands
};


//
// Subagent names list
//

struct SUBAGENT_NAME
{
   char *name;
   char *cmdLine;
};

struct REQUEST
{
   char cmd[MAX_ZABBIX_CMD_LEN];
   char result[MAX_STRING_LEN];
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
int ZabbixCreateService(char *execName);
int ZabbixRemoveService(void);
int ZabbixStartService(void);
int ZabbixStopService(void);

int ZabbixInstallEventSource(char *path);
int ZabbixRemoveEventSource(void);

char *GetCounterName(DWORD index);

void InitLog(void);
void CloseLog(void);
void WriteLog(DWORD msg,WORD wType,char *format...);

BOOL Initialize(void);
void Shutdown(void);
void Main(void);

void ListenerThread(void *);
void CollectorThread(void *);
void ActiveChecksThread(void *);

void ProcessCommand(char *cmd,char *result);

extern char *test_cmd;
int TestCommand(void);

BOOL ReadConfig(void);

BOOL AddAlias(char *name,char *value);
void ExpandAlias(char *orig,char *expanded);

unsigned int __stdcall ProcessingThread(void *arg);
int   process_log(char *filename,int *lastlogsize, char *value);
int process_eventlog(char *source,int *lastlogsize, char *timestamp, char *src, char *severity, char *message);

void str_base64_encode(char *p_str, char *p_b64str, int in_size);
void str_base64_decode(char *p_b64str, char *p_str, int *p_out_size);

int	comms_create_request(char *host, char *key, char *data, char *lastlogsize,
						 char *timestamp, char *source, char *severity, char *request,int maxlen);


int xml_get_data(char *xml,char *tag, char *data, int maxlen);

int	num_param(const char *param);
int	get_param(const char *param, int num, char *buf, int maxlen);

//
// Global variables
//

extern HANDLE eventShutdown;
extern HANDLE eventCollectorStarted;

extern DWORD dwFlags;
extern DWORD g_dwLogLevel;

extern char confFile[];
extern char logFile[];
extern char confHostname[];
extern char confServer[];
extern DWORD confServerAddr[];
extern DWORD confServerCount;
extern WORD confListenPort;
extern WORD confServerPort;
extern DWORD confTimeout;
extern DWORD confMaxProcTime;
extern DWORD confEnableRemoteCommands;

extern USER_COUNTER *userCounterList;
void	FreeUserCounterList(void);

extern SUBAGENT *subagentList;

extern SUBAGENT_NAME *subagentNameList;
void	FreeSubagentNameList(void);

extern PERFCOUNTER *perfCounterList;
void	FreeCounterList(void);

void	FreeAliasList(void);

extern double statProcUtilization[];
extern double statProcUtilization5[];
extern double statProcUtilization15[];
extern double statProcLoad;
extern double statProcLoad5;
extern double statProcLoad15;
extern double statAvgCollectorTime;
extern double statMaxCollectorTime;
extern double statAcceptedRequests;
extern double statRejectedRequests;
extern double statTimedOutRequests;
extern double statAcceptErrors;

extern DWORD (__stdcall *imp_GetGuiResources)(HANDLE,DWORD);
extern BOOL (__stdcall *imp_GetProcessIoCounters)(HANDLE,PIO_COUNTERS);
extern BOOL (__stdcall *imp_GetPerformanceInfo)(PPERFORMANCE_INFORMATION,DWORD);
extern BOOL (__stdcall *imp_GlobalMemoryStatusEx)(LPMEMORYSTATUSEX);

#endif
