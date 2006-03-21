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
** $module: sysinfo.cpp
**
**/

#include "zabbixw32.h"


//
// Externals
//

LONG H_ProcInfo(char *cmd,char *arg,double *value);
LONG H_RunCommand(char *cmd,char *arg,char **value);
LONG H_Execute(char *cmd,char *arg,char **value);
LONG H_CheckTcpPort(char *cmd,char *arg,double *value);


//
// Static data
//

static DWORD procList[MAX_PROCESSES];
static HMODULE modList[MAX_MODULES];

static double statProcessedRequests=0;
static double statFailedRequests=0;
static double statUnsupportedRequests=0;


//
// Get instance for parameters like name[instance]
//

void GetParameterInstance(char *param,char *instance,int maxSize)
{
   char *ptr1,*ptr2;

INIT_CHECK_MEMORY(main);

   instance[0]=0;    // Default is empty string
   ptr1=strchr(param,'[');
   ptr2=strchr(ptr1,']');
   if ((ptr1==NULL)||(ptr2==NULL))
      return;

   ptr1++;
   memcpy(instance,ptr1,min(ptr2-ptr1,maxSize-1));
   instance[min(ptr2-ptr1,maxSize-1)]=0;
CHECK_MEMORY(main,"GetParameterInstance","end");
}


//
// Handler for parameters which always returns numeric constant (like ping)
//

static LONG H_NumericConstant(char *cmd,char *arg,double *value)
{
   *value=(double)((long)arg);
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for parameters which always returns string constant (like version[zabbix_agent])
//

static LONG H_StringConstant(char *cmd,char *arg,char **value)
{
   *value=strdup(arg ? arg : "(null)\n");
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for parameters which returns numeric value from specific variable
//

static LONG H_NumericPtr(char *cmd,char *arg,double *value)
{
   *value=*((double *)arg);
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for cpu_load[]
//

static LONG H_ProcUtil(char *cmd,char *arg,double *value)
{
   char proc[16];
   int procnum;


   if ((DWORD)arg & 0x80)  // bit 7 is set if we are called for specific instance
   {
      GetParameterInstance(cmd,proc,15);
      if (proc[0]==0)
      {
         procnum=1;
      }
      else
      {
         procnum=atoi(proc)+1;
         if ((procnum<1)||(procnum>MAX_CPU))
            return SYSINFO_RC_NOTSUPPORTED;
      }
   }
   else
   {
      procnum=0;     // _Total
   }
   switch((DWORD)arg & 0x03)
   {
      case 0:  // procload
         *value=statProcUtilization[procnum];
         break;
      case 1:  // procload5
         *value=statProcUtilization5[procnum];
         break;
      case 2:  // procload15
         *value=statProcUtilization15[procnum];
         break;
      default:
         return SYSINFO_RC_NOTSUPPORTED;
   }
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for system[proccount]
//

static LONG H_ProcCount(char *cmd,char *arg,double *value)
{
   DWORD dwSize;

   EnumProcesses(procList,sizeof(DWORD)*MAX_PROCESSES,&dwSize);
   *value=(double)(dwSize/sizeof(DWORD));
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for proc_cnt[*]
//

static LONG H_ProcCountSpecific(char *cmd,char *arg,double *value)
{
   DWORD dwSize=0;
   int i,counter,procCount;
   char procName[MAX_PATH];
   HANDLE hProcess;


   GetParameterInstance(cmd,procName,MAX_PATH-1);
   EnumProcesses(procList,sizeof(DWORD)*MAX_PROCESSES,&dwSize);
   procCount=dwSize/sizeof(DWORD);
   for(i=0,counter=0;i<procCount;i++)
   {
      hProcess=OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ,FALSE,procList[i]);
      if (hProcess!=NULL)
      {
         if (EnumProcessModules(hProcess,modList,sizeof(HMODULE)*MAX_MODULES,&dwSize))
         {
            if (dwSize>=sizeof(HMODULE))     // At least one module exist
            {
               char baseName[MAX_PATH];

               GetModuleBaseName(hProcess,modList[0],baseName,sizeof(baseName));
               if (!stricmp(baseName,procName))
                  counter++;
            }
         }
         CloseHandle(hProcess);
      }
   }
   *value=(double)counter;
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for memory[*] and swap[*] parameters
//

static LONG H_MemoryInfo(char *cmd,char *arg,double *value)
{



   if (!strcmp(cmd,"memory[cached]"))
   {
      PERFORMANCE_INFORMATION pfi;

      if (imp_GetPerformanceInfo==NULL)
         return SYSINFO_RC_NOTSUPPORTED;

      imp_GetPerformanceInfo(&pfi,sizeof(PERFORMANCE_INFORMATION));
      *value=(double)pfi.SystemCache*(double)pfi.PageSize;
   }
   else
   {
      if (imp_GlobalMemoryStatusEx!=NULL)
      {
         MEMORYSTATUSEX ms;

         ms.dwLength = sizeof(MEMORYSTATUSEX);
         imp_GlobalMemoryStatusEx(&ms);
         if (!strcmp(cmd, "memory[total]"))
            *value = (double)((__int64)ms.ullTotalPhys);
         else if (!strcmp(cmd, "memory[free]"))
            *value = (double)((__int64)ms.ullAvailPhys);
         else if (!strcmp(cmd, "swap[total]"))
            *value = (double)((__int64)ms.ullTotalPageFile);
         else if (!strcmp(cmd, "swap[free]"))
            *value = (double)((__int64)ms.ullAvailPageFile);
         else
            return SYSINFO_RC_NOTSUPPORTED;
      }
      else
      {
         MEMORYSTATUS ms;

         GlobalMemoryStatus(&ms);

         if (!strcmp(cmd,"memory[total]"))
            *value=(double)ms.dwTotalPhys;
         else if (!strcmp(cmd,"memory[free]"))
            *value=(double)ms.dwAvailPhys;
         else if (!strcmp(cmd,"swap[total]"))
            *value=(double)ms.dwTotalPageFile;
         else if (!strcmp(cmd,"swap[free]"))
            *value=(double)ms.dwAvailPageFile;
         else
            return SYSINFO_RC_NOTSUPPORTED;
      }
   }
   
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for system[hostname] parameter
//

static LONG H_HostName(char *cmd,char *arg,char **value)
{
   DWORD dwSize;
   char buffer[MAX_COMPUTERNAME_LENGTH+1];


   dwSize=MAX_COMPUTERNAME_LENGTH+1;
   GetComputerName(buffer,&dwSize);
   *value=strdup(buffer);
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for diskfree[*], diskused[*]  and disktotal[*] parameters
//

static LONG H_DiskInfo(char *cmd,char *arg,double *value)
{
   char path[MAX_PATH];
   ULARGE_INTEGER freeBytes,totalBytes;



   GetParameterInstance(cmd,path,MAX_PATH-1);
   if (!GetDiskFreeSpaceEx(path,&freeBytes,&totalBytes,NULL))
      return SYSINFO_RC_NOTSUPPORTED;

   if (!memcmp(cmd,"diskfree[",9))
      *value=(double)((__int64)freeBytes.QuadPart);
   else if (!memcmp(cmd,"diskused[",9))
      *value=(double)((__int64)totalBytes.QuadPart-(__int64)freeBytes.QuadPart);
   else
      *value=(double)((__int64)totalBytes.QuadPart);
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for service_state[*] parameter
//

static LONG H_ServiceState(char *cmd,char *arg,double *value)
{
   SC_HANDLE mgr,service;
   char serviceName[MAX_PATH];


   GetParameterInstance(cmd,serviceName,MAX_PATH-1);

   mgr=OpenSCManager(NULL,NULL,GENERIC_READ);
   if (mgr==NULL)
   {
      *value=255;    // Unable to retrieve information
      return SYSINFO_RC_SUCCESS;
   }

   service=OpenService(mgr,serviceName,SERVICE_QUERY_STATUS);
   if (service==NULL)
   {
      *value=SYSINFO_RC_NOTSUPPORTED;
   }
   else
   {
      SERVICE_STATUS status;

      if (QueryServiceStatus(service,&status))
      {
         int i;
         static DWORD states[7]={ SERVICE_RUNNING,SERVICE_PAUSED,SERVICE_START_PENDING,
                                  SERVICE_PAUSE_PENDING,SERVICE_CONTINUE_PENDING,
                                  SERVICE_STOP_PENDING,SERVICE_STOPPED };

         for(i=0;i<7;i++)
            if (status.dwCurrentState==states[i])
               break;
         *value=(double)i;
      }
      else
      {
         *value=255;    // Unable to retrieve information
      }

      CloseServiceHandle(service);
   }

   CloseServiceHandle(mgr);
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for perf_counter[*] parameter
//

static LONG H_PerfCounter(char *cmd,char *arg,double *value)
{
   HQUERY query;
   HCOUNTER counter;
   PDH_RAW_COUNTER rawData;
   PDH_FMT_COUNTERVALUE counterValue;
   PDH_STATUS status;
   char counterName[MAX_PATH];


   GetParameterInstance(cmd,counterName,MAX_PATH-1);

   if (PdhOpenQuery(NULL,0,&query)!=ERROR_SUCCESS)
   {
      WriteLog(MSG_PDH_OPEN_QUERY_FAILED,EVENTLOG_ERROR_TYPE,"s",
               GetSystemErrorText(GetLastError()));
      return SYSINFO_RC_ERROR;
   }

   if ((status=PdhAddCounter(query,counterName,0,&counter))!=ERROR_SUCCESS)
   {
      WriteLog(MSG_PDH_ADD_COUNTER_FAILED,EVENTLOG_ERROR_TYPE,"ss",
               counterName,GetPdhErrorText(status));
      PdhCloseQuery(query);
      return SYSINFO_RC_NOTSUPPORTED;
   }

   if (PdhCollectQueryData(query)!=ERROR_SUCCESS)
   {
      WriteLog(MSG_PDH_COLLECT_QUERY_DATA_FAILED,EVENTLOG_ERROR_TYPE,"s",
               GetSystemErrorText(GetLastError()));
	  PdhRemoveCounter(&counter);
      PdhCloseQuery(query);
      return SYSINFO_RC_ERROR;
   }

   PdhGetRawCounterValue(counter,NULL,&rawData);
   PdhCalculateCounterFromRawValue(counter,PDH_FMT_DOUBLE,
                                   &rawData,NULL,&counterValue);
   PdhRemoveCounter(&counter);

   PdhCloseQuery(query);
   *value=counterValue.doubleValue;
   return SYSINFO_RC_SUCCESS;
}


//
// Handler for user counters
//

static LONG H_UserCounter(char *cmd,char *arg,double *value)
{
   USER_COUNTER *counter;
   char *ptr1,*ptr2;


   ptr1=strchr(cmd,'{');
   ptr2=strchr(cmd,'}');
   ptr1++;
   *ptr2=0;
   for(counter=userCounterList;counter!=NULL;counter=counter->next)
      if (!strcmp(counter->name,ptr1))
      {
         *value=counter->lastValue;
         return SYSINFO_RC_SUCCESS;
      }

   return SYSINFO_RC_NOTSUPPORTED;
}


//
// Calculate MD5 hash for file
//

static LONG H_MD5Hash(char *cmd,char *arg,char **value)
{
   char fileName[MAX_PATH], hashText[MD5_DIGEST_SIZE*2+1];
   unsigned char *data=NULL, hash[MD5_DIGEST_SIZE];
   HANDLE hFile=NULL,hFileMapping=NULL;
   DWORD dwSize=0, dwSizeHigh=0;
   int i=0;



   // Get file name from parameter name and open it
   GetParameterInstance(cmd,fileName,MAX_PATH-1);
   hFile=CreateFile(fileName,GENERIC_READ,FILE_SHARE_READ,NULL,OPEN_EXISTING,0,NULL);
   if (hFile==INVALID_HANDLE_VALUE)
      return SYSINFO_RC_NOTSUPPORTED;

   // Get file size
   dwSize=GetFileSize(hFile,&dwSizeHigh);
   if (dwSizeHigh>0 || dwSize>0x4000000)
      return SYSINFO_RC_NOTSUPPORTED;  // We will not work with files larger than 64MB

   if (dwSize>0)     // We will not create mapping for zero-length files
   {
      // Create file mapping object
      hFileMapping=CreateFileMapping(hFile,NULL,PAGE_READONLY,0,0,NULL);
      if (hFileMapping==NULL)
      {
         WriteLog(MSG_FILE_MAP_FAILED,EVENTLOG_ERROR_TYPE,"se",
                  fileName,GetLastError());
         CloseHandle(hFile);
         return SYSINFO_RC_ERROR;
      }

      // Map entire file to process's address space
      data=(unsigned char *)MapViewOfFile(hFileMapping,FILE_MAP_READ,0,0,0);
      if (data==NULL)
      {
         WriteLog(MSG_MAP_VIEW_FAILED,EVENTLOG_ERROR_TYPE,"se",
                  fileName,GetLastError());
         CloseHandle(hFileMapping);
         CloseHandle(hFile);
         return SYSINFO_RC_ERROR;
      }
   }


   CalculateMD5Hash(data,dwSize,hash);

   // Unmap and close file
   if (dwSize>0)
   {
      UnmapViewOfFile(data);
      CloseHandle(hFileMapping);
   }
   CloseHandle(hFile);

   // Convert MD5 hash to text form
   for(i=0;i<MD5_DIGEST_SIZE;i++)
      sprintf(&hashText[i<<1],"%02x",hash[i]);

   *value=strdup(hashText);
   return SYSINFO_RC_SUCCESS;
}


//
// Calculate CRC32 for file
//

static LONG H_CRC32(char *cmd,char *arg,double *value)
{
   char fileName[MAX_PATH];
   HANDLE hFile = NULL, hFileMapping = NULL;
   DWORD dwSize,dwSizeHigh,crc;
   unsigned char *data = NULL;

INIT_CHECK_MEMORY(main);

   // Get file name from parameter name and open it
   GetParameterInstance(cmd,fileName,MAX_PATH-1);
   hFile=CreateFile(fileName,GENERIC_READ,FILE_SHARE_READ,NULL,OPEN_EXISTING,0,NULL);
   if (hFile==INVALID_HANDLE_VALUE)
      return SYSINFO_RC_NOTSUPPORTED;

   // Get file size
   dwSize=GetFileSize(hFile,&dwSizeHigh);
   if (dwSizeHigh>0 || dwSize>0x4000000)
      return SYSINFO_RC_NOTSUPPORTED;  // We will not work with files larger than 64MB

   if (dwSize>0)     // We will not create mapping for zero-length files
   {
      // Create file mapping object
      hFileMapping=CreateFileMapping(hFile,NULL,PAGE_READONLY,0,0,NULL);
      if (hFileMapping==NULL)
      {
         WriteLog(MSG_FILE_MAP_FAILED,EVENTLOG_ERROR_TYPE,"ss",
                  fileName,GetSystemErrorText(GetLastError()));
         CloseHandle(hFile);
         return SYSINFO_RC_ERROR;
      }

      // Map entire file to process's address space
      data=(unsigned char *)MapViewOfFile(hFileMapping,FILE_MAP_READ,0,0,0);
      if (data==NULL)
      {
         WriteLog(MSG_MAP_VIEW_FAILED,EVENTLOG_ERROR_TYPE,"ss",
                  fileName,GetSystemErrorText(GetLastError()));
         CloseHandle(hFileMapping);
         CloseHandle(hFile);

         return SYSINFO_RC_ERROR;
      }
   }

   crc=CalculateCRC32(data,dwSize);

   // Unmap and close file
   if (dwSize>0)
   {
      UnmapViewOfFile(data);
      CloseHandle(hFileMapping);
   }
   CloseHandle(hFile);

   *value=(double)crc;

CHECK_MEMORY(main, "H_FileSize","end");

   return SYSINFO_RC_SUCCESS;
}


//
// Handler for filesize[*] parameter
//

static LONG H_FileSize(char *cmd,char *arg,double *value)
{
   char fileName[MAX_PATH];
   HANDLE hFind;
   WIN32_FIND_DATA findData;

INIT_CHECK_MEMORY(main);

   GetParameterInstance(cmd,fileName,MAX_PATH-1);

   hFind=FindFirstFile(fileName,&findData);
   if (hFind==INVALID_HANDLE_VALUE)
   {
CHECK_MEMORY(main, "H_FileSize","INVALID_HANDLE_VALUE");
      return SYSINFO_RC_NOTSUPPORTED;
   }
   FindClose(hFind);

   *value=(double)findData.nFileSizeLow+(double)(((__int64)findData.nFileSizeHigh) << 32);

CHECK_MEMORY(main, "H_FileSize","end");

   return SYSINFO_RC_SUCCESS;
}


//
// Handler for system[uname] parameter
//

static LONG H_SystemUname(char *cmd,char *arg,char **value)
{
   DWORD dwSize;
   char *cpuType,computerName[MAX_COMPUTERNAME_LENGTH+1],osVersion[256],buffer[1024];
   SYSTEM_INFO sysInfo;
   OSVERSIONINFO versionInfo;

INIT_CHECK_MEMORY(main);

   dwSize=MAX_COMPUTERNAME_LENGTH+1;
   GetComputerName(computerName,&dwSize);

   versionInfo.dwOSVersionInfoSize=sizeof(OSVERSIONINFO);
   GetVersionEx(&versionInfo);
   GetSystemInfo(&sysInfo);

   switch(versionInfo.dwPlatformId)
   {
      case VER_PLATFORM_WIN32_WINDOWS:
         sprintf(osVersion,"Windows %s-%s",versionInfo.dwMinorVersion==0 ? "95" :
            (versionInfo.dwMinorVersion==10 ? "98" :
               (versionInfo.dwMinorVersion==90 ? "Me" : "Unknown")),versionInfo.szCSDVersion);
         break;
      case VER_PLATFORM_WIN32_NT:
         if (versionInfo.dwMajorVersion!=5)
            sprintf(osVersion,"Windows NT %d.%d %s",versionInfo.dwMajorVersion,
                    versionInfo.dwMinorVersion,versionInfo.szCSDVersion);
         else      // Windows 2000, Windows XP or Windows Server 2003
            sprintf(osVersion,"Windows %s%s%s",versionInfo.dwMinorVersion==0 ? "2000" :
                                              (versionInfo.dwMinorVersion==1 ? "XP" : "Server 2003"),
                    versionInfo.szCSDVersion[0]==0 ? "" : " ",versionInfo.szCSDVersion);
         break;
      default:
         strcpy(osVersion,"Windows [Unknown Version]");
         break;
   }

   switch(sysInfo.wProcessorArchitecture)
   {
      case PROCESSOR_ARCHITECTURE_INTEL:
         cpuType="Intel IA-32";
         break;
      case PROCESSOR_ARCHITECTURE_MIPS:
         cpuType="MIPS";
         break;
      case PROCESSOR_ARCHITECTURE_ALPHA:
         cpuType="Alpha";
         break;
      case PROCESSOR_ARCHITECTURE_PPC:
         cpuType="PowerPC";
         break;
      case PROCESSOR_ARCHITECTURE_IA64:
         cpuType="Intel IA-64";
         break;
      case PROCESSOR_ARCHITECTURE_IA32_ON_WIN64:
         cpuType="IA-32 on IA-64";
         break;
      case PROCESSOR_ARCHITECTURE_AMD64:
         cpuType="AMD-64";
         break;
      default:
         cpuType="unknown";
         break;
   }

   sprintf(buffer,"Windows %s %d.%d.%d %s %s",computerName,versionInfo.dwMajorVersion,
           versionInfo.dwMinorVersion,versionInfo.dwBuildNumber,osVersion,cpuType);
   *value=strdup(buffer);

CHECK_MEMORY(main, "H_SystemUname","end");

   return SYSINFO_RC_SUCCESS;
}


//
// Parameters and handlers
//

static AGENT_COMMAND commands[]=
{
   { "__exec{*}",NULL,H_Execute,NULL },
   { "__usercnt{*}",H_UserCounter,NULL,NULL },
   { "system.run[*]",NULL,H_RunCommand,NULL },
   { "agent[avg_collector_time]",H_NumericPtr,NULL,(char *)&statAvgCollectorTime },
   { "agent[max_collector_time]",H_NumericPtr,NULL,(char *)&statMaxCollectorTime },
   { "agent[accepted_requests]",H_NumericPtr,NULL,(char *)&statAcceptedRequests },
   { "agent[rejected_requests]",H_NumericPtr,NULL,(char *)&statRejectedRequests },
   { "agent[timed_out_requests]",H_NumericPtr,NULL,(char *)&statTimedOutRequests },
   { "agent[accept_errors]",H_NumericPtr,NULL,(char *)&statAcceptErrors },
   { "agent[processed_requests]",H_NumericPtr,NULL,(char *)&statProcessedRequests },
   { "agent[failed_requests]",H_NumericPtr,NULL,(char *)&statFailedRequests },
   { "agent[unsupported_requests]",H_NumericPtr,NULL,(char *)&statUnsupportedRequests },
   { "check_port[*]",H_CheckTcpPort,NULL,NULL },
   { "cksum[*]",H_CRC32,NULL,NULL },
   { "cpu_util",H_ProcUtil,NULL,(char *)0x00 },
   { "cpu_util5",H_ProcUtil,NULL,(char *)0x01 },
   { "cpu_util15",H_ProcUtil,NULL,(char *)0x02 },
   { "cpu_util[*]",H_ProcUtil,NULL,(char *)0x80 },
   { "cpu_util5[*]",H_ProcUtil,NULL,(char *)0x81 },
   { "cpu_util15[*]",H_ProcUtil,NULL,(char *)0x82 },
   { "diskfree[*]",H_DiskInfo,NULL,NULL },
   { "disktotal[*]",H_DiskInfo,NULL,NULL },
   { "diskused[*]",H_DiskInfo,NULL,NULL },
   { "filesize[*]",H_FileSize,NULL,NULL },
   { "md5_hash[*]",NULL,H_MD5Hash,NULL },
   { "memory[*]",H_MemoryInfo,NULL,NULL },
   { "perf_counter[*]",H_PerfCounter,NULL,NULL },
   { "ping",H_NumericConstant,NULL,(char *)1 },
   { "proc_cnt[*]",H_ProcCountSpecific,NULL,NULL },
   { "proc_info[*]",H_ProcInfo,NULL,NULL },
   { "service_state[*]",H_ServiceState,NULL,NULL },
   { "swap[*]",H_MemoryInfo,NULL,NULL },
   { "system[hostname]",NULL,H_HostName,NULL },
   { "system[proccount]",H_ProcCount,NULL,NULL },
   { "system[procload]",H_NumericPtr,NULL,(char *)&statProcLoad },
   { "system[procload5]",H_NumericPtr,NULL,(char *)&statProcLoad5 },
   { "system[procload15]",H_NumericPtr,NULL,(char *)&statProcLoad15 },
   { "system[uname]",NULL,H_SystemUname,NULL },
   { "version[zabbix_agent]",NULL,H_StringConstant,AGENT_VERSION },
   { "",NULL,NULL,NULL }
};


//
// Command processing function
//

void ProcessCommand(char *received_cmd,char *result)
{
   int i;
   double fResult=NOTSUPPORTED;
   char *strResult=NULL,cmd[MAX_ZABBIX_CMD_LEN];
   LONG iRC=SYSINFO_RC_NOTSUPPORTED;
   SUBAGENT *sbi;
   BOOL isSubagentCommand=FALSE;

INIT_CHECK_MEMORY(main);

   ExpandAlias(received_cmd,cmd);

   // Find match for command in subagents
   for(sbi=subagentList; sbi!=NULL; sbi=sbi->next)
   {
      for(i=0;;i++)
      {
         if (sbi->cmdList[i].name[0]==0)
            break;

         if (MatchString(sbi->cmdList[i].name,cmd))
         {
            if (sbi->cmdList[i].handler_float!=NULL)
            {
               iRC=sbi->cmdList[i].handler_float(cmd,&fResult);
            }
            else
            {
               if (sbi->cmdList[i].handler_string!=NULL)
                  iRC=sbi->cmdList[i].handler_string(cmd,&strResult);
            }
            isSubagentCommand=TRUE;
            goto finish_cmd_processing;
         }
      }
   }

   // Find match for command in internal command list
   for(i=0; commands[i].name[0]; i++)
   {
      if (MatchString(commands[i].name,cmd))
      {
         if (commands[i].handler_float!=NULL)
         {
            iRC=commands[i].handler_float(cmd,commands[i].arg,&fResult);
         }
         else
         {
            if (commands[i].handler_string!=NULL)
               iRC=commands[i].handler_string(cmd,commands[i].arg,&strResult);
         }
         break;
      }
   }

finish_cmd_processing:;
   switch(iRC)
   {
      case SYSINFO_RC_SUCCESS:
         if (strResult==NULL)
         {
            sprintf(result,"%f",fResult);
         }
         else
         {
            strncpy(result,strResult,MAX_STRING_LEN-1);
            strcat(result,"\n");
         }
         statProcessedRequests++;
         break;
      case SYSINFO_RC_NOTSUPPORTED:
         strcpy(result,"ZBX_NOTSUPPORTED\n");
         statUnsupportedRequests++;
         break;
      case SYSINFO_RC_ERROR:
         strcpy(result,"ZBX_ERROR\n");
         statFailedRequests++;
         break;
      default:
         strcpy(result,"ZBX_ERROR\n");
         WriteLog(MSG_UNEXPECTED_IRC,EVENTLOG_ERROR_TYPE,"ds",iRC,received_cmd);
         statFailedRequests++;
         break;
   }

   if(strResult)
   {
	   if (isSubagentCommand)
		   zfree(strResult);
	   else
		   free(strResult);
   }

   CHECK_MEMORY(main, "ProcessCommand","end");
}
