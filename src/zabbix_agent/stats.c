/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
**/

#include "common.h"
#include "stats.h"

#ifndef WIN32
#warning REMOVE sustem includes fron here!
#include <sys/types.h>
#include <sys/ipc.h>
#include <sys/shm.h>
#endif

#include "log.h"
#include "mutexs.h"
#include "zbxconf.h"

#include "interfaces.h"
#include "diskdevices.h"
#include "cpustat.h"
#include "log.h"

ZBX_COLLECTOR_DATA *collector = NULL;

/******************************************************************************
 *                                                                            *
 * Function: init_collector_data                                              *
 *                                                                            *
 * Purpose: Allocate memory for collector                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Linux version allocate memory as shared.                         *
 *                                                                            *
 ******************************************************************************/
void	init_collector_data(void)
{
#if defined (WIN32)

	collector = calloc(1, sizeof(ZBX_COLLECTOR_DATA));

	if(NULL == collector)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't allocate memory for collector.");
		exit(1);

	}

#else /* not WIN32 */

	key_t	shm_key;
	int	shm_id;

	shm_key = ftok("/tmp/zbxshm", (int)'z');

	shm_id = shmget(shm_key, sizeof(ZBX_COLLECTOR_DATA), IPC_CREAT | 0666);

	if (-1 == shm_id)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't allocate shared memory for collector. [%s]",strerror(errno));
		exit(1);
	}

	collector = shmat(shm_id, 0, 0);

	if ((void*)(-1) == collector)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't attache shared memory for collector. [%s]",strerror(errno));
		exit(1);
	}

#endif /* WIN32 */
}

/******************************************************************************
 *                                                                            *
 * Function: free_collector_data                                              *
 *                                                                            *
 * Purpose: Free memory aloccated for collector                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Linux version allocate memory as shared.                         *
 *                                                                            *
 ******************************************************************************/
void	free_collector_data(void)
{
	if(NULL == collector) return;

#if defined (WIN32)

	free(collector);

#else /* not WIN32 */

	key_t	shm_key;
	int	shm_id;

	shm_key = ftok("/tmp/zbxshm", 'z');

	shm_id = shmget(shm_key, sizeof(ZBX_COLLECTOR_DATA), 0);

	if (-1 == shm_id)
	{
		zabbix_log(LOG_LEVEL_ERR, "Can't find shared memory for collector. [%s]",strerror(errno));
		exit(1);
	}

	shmctl(shm_id, IPC_RMID, 0);

#endif /* WIN32 */

	collector = NULL;
}


/******************************************************************************
 *                                                                            *
 * Function: collector_thread                                                 *
 *                                                                            *
 * Purpose: Collect system information                                        *
 *                                                                            *
 * Parameters:  args - skipped                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(collector_thread, args)
{
	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd collector started");

	init_cpu_collector(&(collector->cpus));

	for(;;)
	{
		collect_cpustat(&(collector->cpus));

		collect_stats_interfaces(&(collector->interfaces));
		collect_stats_diskdevices(&(collector->diskdevices));

		zbx_sleep(1);
	}

	close_cpu_collector(&(collector->cpus));

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd collector stopped");

	zbx_tread_exit(0);
}

/* win32 - TODO

User perf counters

//==============================================
	// Add user counters to query
	for(cptr=userCounterList;cptr!=NULL;cptr=cptr->next)
	{

		LOG_DEBUG_INFO("s","counterPath4");
		LOG_DEBUG_INFO("s",counterPath);

		if ((status=PdhAddCounter(query,cptr->counterPath,0,&cptr->handle))!=ERROR_SUCCESS)
		{
			cptr->interval=-1;   // Flag for unsupported counters
			cptr->lastValue=NOTSUPPORTED;
			WriteLog(MSG_USERDEF_COUNTER_FAILED,EVENTLOG_ERROR_TYPE,"sss",
			cptr->name,cptr->counterPath,GetPdhErrorText(status));
		}
	}


	do{
		// Process user-defined counters
		for(cptr=userCounterList; cptr!=NULL; cptr=cptr->next)
		{
			if (cptr->interval>0)      // Active counter?
			{
				PdhGetRawCounterValue(cptr->handle,NULL,&cptr->rawValueArray[cptr->currPos++]);
				if (cptr->currPos==cptr->interval)
					cptr->currPos=0;
				PdhComputeCounterStatistics(cptr->handle,PDH_FMT_DOUBLE,cptr->currPos,
				cptr->interval,cptr->rawValueArray,&statData);
				cptr->lastValue=statData.mean.doubleValue;
			}
		}

		// Calculate time spent on sample processing and issue warning if it exceeds threshold
		dwTicksElapsed=GetTickCount()-dwTicksStart;
		if (dwTicksElapsed>confMaxProcTime)
		{
			LOG_DEBUG_INFO("s","Processing took too many time.");
			LOG_DEBUG_INFO("d",dwTicksElapsed);
		}

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
		dwSleepTime = (dwTicksElapsed>500) ? 500 : (1000-dwTicksElapsed);
	}while(1);

	if(cptr)
		PdhRemoveCounter(cptr->handle);

  */
