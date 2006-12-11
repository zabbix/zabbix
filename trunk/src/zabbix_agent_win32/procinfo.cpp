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
** $module: sysinfo.cpp
**
**/

#include "zabbixw32.h"


//
// Convert process time from FILETIME structure (100-nanosecond units) to double (milliseconds)
//

static double ConvertProcessTime(FILETIME *lpft)
{
   __int64 i;

   memcpy(&i,lpft,sizeof(__int64));
   i/=10000;      // Convert 100-nanosecond units to milliseconds
   return (double)i;
}


//
// Check if attribute supported or not
//

static BOOL IsAttributeSupported(int attr)
{
   switch(attr)
   {
      case 5:        // gdiobj
      case 6:        // userobj
         if (imp_GetGuiResources==NULL)
            return FALSE;     // No appropriate function available, probably we are running on NT4
         break;
      case 7:        // io_read_b
      case 8:        // io_read_op
      case 9:        // io_write_b
      case 10:       // io_write_op
      case 11:       // io_other_b
      case 12:       // io_other_op
         if (imp_GetProcessIoCounters==NULL)
            return FALSE;     // No appropriate function available, probably we are running on NT4
         break;
      default:
         break;
   }

   return TRUE;
}


//
// Get specific process attribute
//

static double GetProcessAttribute(HANDLE hProcess,int attr,int type,int count,double lastValue)
{
   double value;  
   PROCESS_MEMORY_COUNTERS mc;
   IO_COUNTERS ioCounters;
   FILETIME ftCreate,ftExit,ftKernel,ftUser;

   // Get value for current process instance
   switch(attr)
   {
      case 0:        // vmsize
         GetProcessMemoryInfo(hProcess,&mc,sizeof(PROCESS_MEMORY_COUNTERS));
         value=(double)mc.PagefileUsage/1024;   // Convert to Kbytes
         break;
      case 1:        // wkset
         GetProcessMemoryInfo(hProcess,&mc,sizeof(PROCESS_MEMORY_COUNTERS));
         value=(double)mc.WorkingSetSize/1024;   // Convert to Kbytes
         break;
      case 2:        // pf
         GetProcessMemoryInfo(hProcess,&mc,sizeof(PROCESS_MEMORY_COUNTERS));
         value=(double)mc.PageFaultCount;
         break;
      case 3:        // ktime
      case 4:        // utime
         GetProcessTimes(hProcess,&ftCreate,&ftExit,&ftKernel,&ftUser);
         value=ConvertProcessTime(attr==3 ? &ftKernel : &ftUser);
         break;
      case 5:        // gdiobj
      case 6:        // userobj
         value=(double)imp_GetGuiResources(hProcess,attr==5 ? 0 : 1);
         break;
      case 7:        // io_read_b
         imp_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.ReadTransferCount);
         break;
      case 8:        // io_read_op
         imp_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.ReadOperationCount);
         break;
      case 9:        // io_write_b
         imp_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.WriteTransferCount);
         break;
      case 10:       // io_write_op
         imp_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.WriteOperationCount);
         break;
      case 11:       // io_other_b
         imp_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.OtherTransferCount);
         break;
      case 12:       // io_other_op
         imp_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.OtherOperationCount);
         break;
      default:       // Unknown attribute
         WriteLog(MSG_UNEXPECTED_ATTRIBUTE,EVENTLOG_ERROR_TYPE,"x",attr);
         value=0;
   }

   // Recalculate final value according to selected type
   if (count==1)     // First instance
   {
      return value;
   }

   switch(type)
   {
      case 0:     // min
         return min(lastValue,value);
      case 1:     // max
         return max(lastValue,value);
      case 2:     // avg
         return (lastValue*(count-1)+value)/count;
      case 3:     // sum
         return lastValue+value;
      default:
         WriteLog(MSG_UNEXPECTED_TYPE,EVENTLOG_ERROR_TYPE,"x",type);
         return 0;
   }
}


//
// Get process-specific information
// Parameter has the following syntax:
//    proc_info[<process>:<attribute>:<type>]
// where
//    <process>   - process name (same as in proc_cnt[] parameter)
//    <attribute> - requested process attribute (see documentation for list of valid attributes)
//    <type>      - representation type (meaningful when more than one process with the same
//                  name exists). Valid values are:
//         min - minimal value among all processes named <process>
//         max - maximal value among all processes named <process>
//         avg - average value for all processes named <process>
//         sum - sum of values for all processes named <process>
//

LONG H_ProcInfo(char *cmd,char *arg,double *value)
{
   char buffer[256];
   char *ptr1,*ptr2;
   int attr,type,i,procCount,counter;
   DWORD *procList,dwSize;
   HMODULE *modList;
   static char *attrList[]=
   {
      "vmsize",
      "wkset",
      "pf",
      "ktime",
      "utime",
      "gdiobj",
      "userobj",
      "io_read_b",
      "io_read_op",
      "io_write_b",
      "io_write_op",
      "io_other_b",
      "io_other_op",
      NULL
   };
   static char *typeList[]={ "min","max","avg","sum" };

INIT_CHECK_MEMORY(main);

   // Get parameter arguments
   GetParameterInstance(cmd,buffer,255);
   if (!MatchString("*:*:*",buffer))
   {

CHECK_MEMORY(main,"H_ProcInfo","end");

      return SYSINFO_RC_NOTSUPPORTED;     // Invalid parameter syntax
   }

   // Parse arguments
   ptr1=strchr(buffer,':');
   *ptr1=0;
   ptr1++;
   ptr2=strchr(ptr1,':');
   *ptr2=0;
   ptr2++;  // Now ptr1 points to attribute and ptr2 points to type

   // Get attribute code from string
   for(attr=0;attrList[attr]!=NULL;attr++)
      if (!strcmp(attrList[attr],ptr1))
         break;
   if (attrList[attr]==NULL)
   {
CHECK_MEMORY(main,"H_ProcInfo","typeList");

      return SYSINFO_RC_NOTSUPPORTED;     // Unsupported attribute
   }

   if (!IsAttributeSupported(attr))
   {

CHECK_MEMORY(main,"H_ProcInfo","IsAttributeSupported");

      return SYSINFO_RC_NOTSUPPORTED;     // Unsupported attribute
   }

   // Get type code from string
   for(type=0;typeList[type]!=NULL;type++)
      if (!strcmp(typeList[type],ptr2))
         break;
   if (typeList[type]==NULL)
   {

CHECK_MEMORY(main,"H_ProcInfo","typeList");


      return SYSINFO_RC_NOTSUPPORTED;     // Unsupported type	 
   }

   // Gather information
   *value=0;   // Initialize to zero
   procList=(DWORD *)malloc(MAX_PROCESSES*sizeof(DWORD));
   modList=(HMODULE *)malloc(MAX_MODULES*sizeof(HMODULE));
   EnumProcesses(procList,sizeof(DWORD)*MAX_PROCESSES,&dwSize);
   procCount=dwSize/sizeof(DWORD);
   for(i=0,counter=0;i<procCount;i++)
   {
      HANDLE hProcess;

      hProcess=OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ,FALSE,procList[i]);
      if (hProcess!=NULL)
      {
         if (EnumProcessModules(hProcess,modList,sizeof(HMODULE)*MAX_MODULES,&dwSize))
         {
            if (dwSize>=sizeof(HMODULE))     // At least one module exist
            {
               char baseName[MAX_PATH];

               GetModuleBaseName(hProcess,modList[0],baseName,sizeof(baseName));
               if (!stricmp(baseName,buffer))
               {
                  counter++;  // Number of processes with specific name
                  *value=GetProcessAttribute(hProcess,attr,type,counter,*value);
               }
            }
         }
         CloseHandle(hProcess);
      }
   }

   // Cleanup
   free(procList);
   free(modList);

CHECK_MEMORY(main,"H_ProcInfo","end");

   return SYSINFO_RC_SUCCESS;
}
