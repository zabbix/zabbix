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
** $module: collect.cpp
**
**/

#include "zabbixw32.h"


//
// Global variables
//

USER_COUNTER *userCounterList=NULL;
double statProcUtilization[MAX_CPU+1];
double statProcUtilization5[MAX_CPU+1];
double statProcUtilization15[MAX_CPU+1];
double statProcLoad=0;
double statProcLoad5=0;
double statProcLoad15=0;
double statAvgCollectorTime=0;
double statMaxCollectorTime=0;


//
// Static variables
//

static LONG cpuUsageHistory[MAX_CPU+1][900];
static LONG cpuQueueHistory[900];
static DWORD collectorTimesHistory[60];


//
// Data collection thread
//

void CollectorThread(void *)
{
   HQUERY query;
   HCOUNTER cntCpuUsage[MAX_CPU+1],cntCpuQueue;
   PDH_FMT_COUNTERVALUE value;
   PDH_RAW_COUNTER rawCpuUsage1[MAX_CPU+1],rawCpuUsage2[MAX_CPU+1];
   PDH_RAW_COUNTER rawCounter;      // Generic raw counter for various parameters
   PDH_STATUS status;
   SYSTEM_INFO sysInfo;
   DWORD i,cpuHistoryIdx,cpuQueueHistoryIdx,collectorTimesIdx,dwSleepTime;
   USER_COUNTER *cptr;
   PDH_STATISTICS statData;

   TlsSetValue(dwTlsLogPrefix,"Collector: ");   // Set log prefix for collector thread
   GetSystemInfo(&sysInfo);

   memset(collectorTimesHistory,0,sizeof(DWORD)*60);
   collectorTimesIdx=0;

   // Prepare for CPU utilization collection
   memset(cpuUsageHistory,0,sizeof(LONG)*(MAX_CPU+1)*900);
   memset(statProcUtilization,0,sizeof(double)*(MAX_CPU+1));
   memset(statProcUtilization5,0,sizeof(double)*(MAX_CPU+1));
   memset(statProcUtilization15,0,sizeof(double)*(MAX_CPU+1));

   if (PdhOpenQuery(NULL,0,&query)!=ERROR_SUCCESS)
   {
      WriteLog("PdhOpenQuery failed\r\n");
      return;
   }

   if (PdhAddCounter(query,"\\Processor(_Total)\\% Processor Time",0,&cntCpuUsage[0])!=ERROR_SUCCESS)
   {
      WriteLog("PdhAddCounter(\\Processor(_Total)\\%% Processor Time) failed\r\n");
      PdhCloseQuery(query);
      return;
   }
   for(i=0;i<sysInfo.dwNumberOfProcessors;i++)
   {
      char counterPath[256];

      sprintf(counterPath,"\\Processor(%d)\\%% Processor Time",i);
      if (PdhAddCounter(query,counterPath,0,&cntCpuUsage[i+1])!=ERROR_SUCCESS)
      {
         WriteLog("PdhAddCounter(%s) failed\r\n",counterPath);
         PdhCloseQuery(query);
         return;
      }
   }

   if (PdhCollectQueryData(query)!=ERROR_SUCCESS)
   {
      WriteLog("PdhCollectQueryData failed\r\n");
      PdhCloseQuery(query);
      return;
   }
   for(i=0;i<sysInfo.dwNumberOfProcessors;i++)
      PdhGetRawCounterValue(cntCpuUsage[i],NULL,&rawCpuUsage2[i]);

   cpuHistoryIdx=0;

   // Prepare for CPU execution queue usage collection
   if (PdhAddCounter(query,"\\System\\Processor Queue Length",0,&cntCpuQueue)!=ERROR_SUCCESS)
   {
      WriteLog("PdhAddCounter(\\System\\Processor Queue Length) failed\r\n");
      PdhCloseQuery(query);
      return;
   }

   memset(cpuQueueHistory,0,sizeof(LONG)*900);
   cpuQueueHistoryIdx=0;

   // Add user counters to query
   for(cptr=userCounterList;cptr!=NULL;cptr=cptr->next)
   {
      if (PdhAddCounter(query,cptr->counterPath,0,&cptr->handle)!=ERROR_SUCCESS)
      {
         cptr->interval=-1;   // Flag for unsupported counters
         cptr->lastValue=NOTSUPPORTED;
         WriteLog("Unable to add user-defined counter %s=\"%s\" to query\r\n",
                  cptr->name,cptr->counterPath);
      }
   }

   // Data collection loop
   WriteLog("Initialization complete\r\n");
   do
   {
      LONG sum;
      int j,n;
      DWORD dwTicksStart,dwTicksElapsed;

      dwTicksStart=GetTickCount();
      if ((status=PdhCollectQueryData(query))!=ERROR_SUCCESS)
         WriteLog("PdhCollectQueryData failed (status=%08X)\r\n",status);

      // Process CPU utilization data
      for(i=0;i<=sysInfo.dwNumberOfProcessors;i++)
      {
         PdhGetRawCounterValue(cntCpuUsage[i],NULL,&rawCpuUsage1[i]);
         PdhCalculateCounterFromRawValue(cntCpuUsage[i],PDH_FMT_LONG,
                                         &rawCpuUsage1[i],&rawCpuUsage2[i],&value);
         cpuUsageHistory[i][cpuHistoryIdx]=value.longValue;
         rawCpuUsage2[i]=rawCpuUsage1[i];

         // Calculate average cpu usage for last minute
         for(n=cpuHistoryIdx,j=0,sum=0;j<60;j++)
         {
            sum+=cpuUsageHistory[i][n--];
            if (n==-1)
               n=899;
         }
         statProcUtilization[i]=((double)sum)/(double)60;

         // Calculate average cpu usage for last five minutes
         for(n=cpuHistoryIdx,j=0,sum=0;j<300;j++)
         {
            sum+=cpuUsageHistory[i][n--];
            if (n==-1)
               n=899;
         }
         statProcUtilization5[i]=((double)sum)/(double)300;

         // Calculate average cpu usage for last fifteen minutes
         for(j=0,sum=0;j<900;j++)
            sum+=cpuUsageHistory[i][j];
         statProcUtilization15[i]=((double)sum)/(double)900;
      }
      cpuHistoryIdx++;
      if (cpuHistoryIdx==900)
         cpuHistoryIdx=0;

      // Process CPU queue length data
      PdhGetRawCounterValue(cntCpuQueue,NULL,&rawCounter);
      PdhCalculateCounterFromRawValue(cntCpuQueue,PDH_FMT_LONG,
                                      &rawCounter,NULL,&value);
      cpuQueueHistory[cpuQueueHistoryIdx]=value.longValue;

      // Calculate average processor(s) load for last minute
      for(n=cpuQueueHistoryIdx,j=0,sum=0;j<60;j++)
      {
         sum+=cpuQueueHistory[n--];
         if (n==-1)
            n=899;
      }
      statProcLoad=((double)sum)/(double)60;

      // Calculate average processor(s) load for last five minutes
      for(n=cpuQueueHistoryIdx,j=0,sum=0;j<300;j++)
      {
         sum+=cpuQueueHistory[n--];
         if (n==-1)
            n=899;
      }
      statProcLoad5=((double)sum)/(double)300;

      // Calculate average processor(s) load for last fifteen minutes
      for(j=0,sum=0;j<900;j++)
         sum+=cpuQueueHistory[j];
      statProcLoad15=((double)sum)/(double)900;

      cpuQueueHistoryIdx++;
      if (cpuQueueHistoryIdx==900)
         cpuQueueHistoryIdx=0;

      // Process user-defined counters
      for(cptr=userCounterList;cptr!=NULL;cptr=cptr->next)
         if (cptr->interval>0)      // Active counter?
         {
            PdhGetRawCounterValue(cptr->handle,NULL,&cptr->rawValueArray[cptr->currPos++]);
            if (cptr->currPos==cptr->interval)
               cptr->currPos=0;
            PdhComputeCounterStatistics(cptr->handle,PDH_FMT_DOUBLE,cptr->currPos,
                                        cptr->interval,cptr->rawValueArray,&statData);
            cptr->lastValue=statData.mean.doubleValue;
         }

      // Calculate time spent on sample processing and issue warning if it exceeds threshold
      dwTicksElapsed=GetTickCount()-dwTicksStart;
      if (dwTicksElapsed>confMaxProcTime)
         WriteLog("Processing took more then %d milliseconds (%d milliseconds)\r\n",
                  confMaxProcTime,dwTicksElapsed);

      // Save processing time to history buffer
      collectorTimesHistory[collectorTimesIdx++]=dwTicksElapsed;
      if (collectorTimesIdx==60)
         collectorTimesIdx=0;

      // Calculate average cpu usage for last minute
      for(i=0,sum=0;i<60;i++)
         sum+=collectorTimesHistory[i];
      statAvgCollectorTime=((double)sum)/(double)60;

      // Change maximum processing time if needed
      if ((double)dwTicksElapsed>statMaxCollectorTime)
         statMaxCollectorTime=(double)dwTicksElapsed;

      // Calculate sleeping time. We will sleep not less than 500 milliseconds even
      // if processing takes more than 500 milliseconds
      dwSleepTime=(dwTicksElapsed>500) ? 500 : (1000-dwTicksElapsed);
   }
   while(WaitForSingleObject(eventShutdown,dwSleepTime)==WAIT_TIMEOUT);

   PdhCloseQuery(query);
}
