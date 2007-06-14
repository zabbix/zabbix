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
#include "cpustat.h"

#include "log.h"

#ifdef _WINDOWS
	#include "perfmon.h"
#endif /* _WINDOWS */


/******************************************************************************
 *                                                                            *
 * Function: init_cpu_collector                                               *
 *                                                                            *
 * Purpose: Initialize statistic structure and prepare state                  *
 *          for data calculation                                              *
 *                                                                            *
 * Parameters:  pcpus - pointer to the structure                              *
 *                      of ZBX_CPUS_STAT_DATA type                            *
 *                                                                            *
 * Return value: If the function succeeds, the return 0,                      *
 *               great than 0 on an error                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
#ifdef _WINDOWS

	SYSTEM_INFO	sysInfo;
	PDH_STATUS	status;
	int	i;

	char counter_path[MAX_COUNTER_PATH];

	GetSystemInfo(&sysInfo);

	pcpus->count = sysInfo.dwNumberOfProcessors;

	memset(pcpus, 0, sizeof(ZBX_CPUS_STAT_DATA));

	if (PdhOpenQuery(NULL,0,&pcpus->pdh_query)!=ERROR_SUCCESS)
	{
		zabbix_log( LOG_LEVEL_ERR, "Call to PdhOpenQuery() failed: %s", strerror_from_system(GetLastError()));
		return 1;
	}

	zbx_snprintf(counter_path, sizeof(counter_path), "\\%s(_Total)\\%s",GetCounterName(PCI_PROCESSOR),GetCounterName(PCI_PROCESSOR_TIME));

	if (ERROR_SUCCESS != (status = PdhAddCounter(
		pcpus->pdh_query, 
		counter_path, 0, 
		&pcpus->cpu[0].usage_couter)))
	{
		zabbix_log( LOG_LEVEL_ERR, "Unable to add performance counter \"%s\" to query: %s", counter_path, strerror_from_module(status,"PDH.DLL"));
		return 2;
	}

	for(i=1 /* 0 - is Total cpus */; i <= pcpus->count /* "<=" instead of  "+ 1" */; i++)
	{
		zbx_snprintf(counter_path, sizeof(counter_path),"\\%s(%d)\\%s", GetCounterName(PCI_PROCESSOR), i-1, GetCounterName(PCI_PROCESSOR_TIME));

		if (ERROR_SUCCESS != (status = PdhAddCounter(
			pcpus->pdh_query, 
			counter_path,0,
			&pcpus->cpu[i].usage_couter)))
		{
			zabbix_log( LOG_LEVEL_ERR, "Unable to add performance counter \"%s\" to query: %s", counter_path, strerror_from_module(status,"PDH.DLL"));
			return 2;
		}
	}

	if (ERROR_SUCCESS != (status = PdhCollectQueryData(pcpus->pdh_query)))
	{
		zabbix_log( LOG_LEVEL_ERR, "Call to PdhCollectQueryData() failed: %s", strerror_from_module(status,"PDH.DLL"));
		return 3;
	}

	for(i = 1; i <= pcpus->count; i++)
	{
		PdhGetRawCounterValue(pcpus->cpu[i].usage_couter, NULL, &pcpus->cpu[i].usage_old);
	}


	zbx_snprintf(counter_path, sizeof(counter_path), "\\%s\\%s", GetCounterName(PCI_SYSTEM), GetCounterName(PCI_PROCESSOR_QUEUE_LENGTH));

	/* Prepare for CPU execution queue usage collection */
	if (ERROR_SUCCESS != (status = PdhAddCounter(pcpus->pdh_query, counter_path, 0, &pcpus->queue_counter)))
	{
		zabbix_log( LOG_LEVEL_ERR, "Unable to add performance counter \"%s\" to query: %s", counter_path, strerror_from_module(status,"PDH.DLL"));
		return 2;
	}

#else /* not _WINDOWS */

	memset(pcpus, 0, sizeof(ZBX_CPUS_STAT_DATA));


#endif /* _WINDOWS */

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: close_cpu_collector                                              *
 *                                                                            *
 * Purpose: Cleare state of data calculation                                  *
 *                                                                            *
 * Parameters:  pcpus - pointer to the structure                              *
 *                      of ZBX_CPUS_STAT_DATA type                            *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

void	close_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
#ifdef _WINDOWS

	int i;

	if(pcpus->queue_counter)
	{
		PdhRemoveCounter(pcpus->queue_counter);
		pcpus->queue_counter = NULL;
	}

	for(i=0; i < MAX_CPU; i++)
	{
		if(pcpus->cpu[i].usage_couter)
		{
			PdhRemoveCounter(pcpus->cpu[i].usage_couter);
			pcpus->cpu[i].usage_couter = NULL;
		}
	}
	
	if(pcpus->pdh_query)
	{
		PdhCloseQuery(pcpus->pdh_query);
		pcpus->pdh_query = NULL;
	}

#endif /* _WINDOWS */

}

#if !defined(_WINDOWS)

static int	get_cpustat(int *now,zbx_uint64_t *cpu_user,zbx_uint64_t *cpu_system,zbx_uint64_t *cpu_nice,zbx_uint64_t *cpu_idle)
{
    #if defined(HAVE_PROC_STAT)
	
	FILE	*file;
	char	line[MAX_STRING_LEN];
	
    #elif defined(HAVE_SYS_PSTAT_H) /* not HAVE_PROC_STAT */
	
	struct pst_dynamic stats;
	
    #else /* not HAVE_SYS_PSTAT_H */

	return 1;
	
    #endif /* HAVE_PROC_STAT */

	*now = time(NULL);

    #if defined(HAVE_PROC_STAT)
	
	if(NULL == (file = fopen("/proc/stat","r") ))
	{
		zbx_error("Cannot open [%s] [%s]\n","/proc/stat", strerror(errno));
		return 1;
	}

	*cpu_user = *cpu_nice = *cpu_system = *cpu_idle = -1;

	while(fgets(line,1024,file) != NULL)
	{
		if(strstr(line,"cpu ") == NULL) continue;

		sscanf(line, "cpu " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64, cpu_user, cpu_nice, cpu_system, cpu_idle);
		break;
	}
	zbx_fclose(file);

	if(*cpu_user < 0) 
		return 1;
	
    #elif defined(HAVE_SYS_PSTAT_H) /* HAVE_PROC_STAT */

	pstat_getdynamic(&stats, sizeof( struct pst_dynamic ), 1, 0 );
	*cpu_user 	= (zbx_uint64_t)stats.psd_cpu_time[CP_USER];
	*cpu_nice 	= (zbx_uint64_t)stats.psd_cpu_time[CP_NICE];
	*cpu_system 	= (zbx_uint64_t)stats.psd_cpu_time[CP_SYS];
	*cpu_idle 	= (zbx_uint64_t)stats.psd_cpu_time[CP_IDLE];
	
    #endif /* HAVE_SYS_PSTAT_H */
	return 0;
}


static void	apply_cpustat(
	ZBX_CPUS_STAT_DATA *pcpus,
	int now, 
	zbx_uint64_t cpu_user, 
	zbx_uint64_t cpu_system,
	zbx_uint64_t cpu_nice,
	zbx_uint64_t cpu_idle
	)
{
	register int	i	= 0;

	int	time	= 0,
		time1	= 0,
		time5	= 0,
		time15	= 0;

	zbx_uint64_t
		idle	= 0,
		idle1	= 0,
		idle5	= 0,
		idle15	= 0,
		user	= 0,
		user1	= 0,
		user5	= 0,
		user15	= 0,
		system	= 0,
		system1	= 0,
		system5	= 0,
		system15= 0,
		nice	= 0,
		nice1	= 0,
		nice5	= 0,
		nice15	= 0,
		all	= 0,
		all1	= 0,
		all5	= 0,
		all15	= 0;


	for(i=0; i < MAX_CPU_HISTORY; i++)
	{
		if(pcpus->clock[i] < now - MAX_CPU_HISTORY)
		{
			pcpus->clock[i]	= now;

			user	= pcpus->h_user[i]	= cpu_user;
			system	= pcpus->h_system[i]	= cpu_system;
			nice	= pcpus->h_nice[i]	= cpu_nice;
			idle	= pcpus->h_idle[i]	= cpu_idle;

			all	= cpu_idle + cpu_user + cpu_nice + cpu_system;
			break;
		}
	}

	time = time1 = time5 = time15 = now+1;

	for(i=0; i < MAX_CPU_HISTORY; i++)
	{
		if(0 == pcpus->clock[i])	continue;

		if(pcpus->clock[i] == now)
		{
			idle	= pcpus->h_idle[i];
			user	= pcpus->h_user[i];
			nice	= pcpus->h_nice[i];
			system	= pcpus->h_system[i];
			all	= idle + user + nice + system;
		}

		if((pcpus->clock[i] >= (now - 60)) && (time1 > pcpus->clock[i]))
		{
			time1	= pcpus->clock[i];
			idle1	= pcpus->h_idle[i];
			user1	= pcpus->h_user[i];
			nice1	= pcpus->h_nice[i];
			system1	= pcpus->h_system[i];
			all1	= idle1 + user1 + nice1 + system1;
		}
		if((pcpus->clock[i] >= (now - (5*60))) && (time5 > pcpus->clock[i]))
		{
			time5	= pcpus->clock[i];
			idle5	= pcpus->h_idle[i];
			user5	= pcpus->h_user[i];
			nice5	= pcpus->h_nice[i];
			system5	= pcpus->h_system[i];
			all5	= idle5 + user5 + nice5 + system5;
		}
		if((pcpus->clock[i] >= (now - (15*60))) && (time15 > pcpus->clock[i]))
		{
			time15		= pcpus->clock[i];
			idle15		= pcpus->h_idle[i];
			user15		= pcpus->h_user[i];
			nice15		= pcpus->h_nice[i];
			system15	= pcpus->h_system[i];
			all15		= idle15 + user15 + nice15 + system15;
		}
	}

#define CALC_CPU_LOAD(type, time)							\
	if((type) - (type ## time) > 0 && (all) - (all ## time) > 0)			\
	{										\
		pcpus->type ## time = 100. * ((double)((type) - (type ## time)))/	\
				((double)((all) - (all ## time)));			\
	}										\
	else										\
	{										\
		pcpus->type ## time = 0.;						\
	}

	CALC_CPU_LOAD(idle, 1);
	CALC_CPU_LOAD(idle, 5);
	CALC_CPU_LOAD(idle, 15);

	CALC_CPU_LOAD(user, 1);
	CALC_CPU_LOAD(user, 5);
	CALC_CPU_LOAD(user, 15);

	CALC_CPU_LOAD(nice, 1);
	CALC_CPU_LOAD(nice, 5);
	CALC_CPU_LOAD(nice, 15);

	CALC_CPU_LOAD(system, 1);
	CALC_CPU_LOAD(system, 5);
	CALC_CPU_LOAD(system, 15);
}

#endif /* not _WINDOWS */

void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus)
{
#ifdef _WINDOWS

	PDH_FMT_COUNTERVALUE 
		value;
	PDH_STATUS	
		status;
	LONG	sum;
	int	i,
		j,
		n;

	if(!pcpus->queue_counter) return;

	if ((status = PdhCollectQueryData(pcpus->pdh_query)) != ERROR_SUCCESS)
	{
		zabbix_log( LOG_LEVEL_ERR, "Call to PdhCollectQueryData() failed: %s", strerror_from_module(status,"PDH.DLL"));
		return;
	}

	/* Process CPU utilization data */
	for(i=0; i <= pcpus->count; i++)
	{
		if(!pcpus->cpu[i].usage_couter)
			continue;

		PdhGetRawCounterValue(
			pcpus->cpu[i].usage_couter, 
			NULL, 
			&pcpus->cpu[i].usage);

		PdhCalculateCounterFromRawValue(
			pcpus->cpu[i].usage_couter,
			PDH_FMT_LONG,
			&pcpus->cpu[i].usage,
			&pcpus->cpu[i].usage_old, 
			&value);

		pcpus->cpu[i].h_usage[pcpus->cpu[i].h_usage_index] = value.longValue;
		pcpus->cpu[i].usage_old = pcpus->cpu[i].usage;

		/* Calculate average cpu usage */
		for(n = pcpus->cpu[i].h_usage_index, j = 0, sum = 0; j < MAX_CPU_HISTORY; j++, n--)
		{
			if(n < 0) n = MAX_CPU_HISTORY - 1;

			sum += pcpus->cpu[i].h_usage[n];

			if(j == 60) /* cpu usage for last minute */
			{
				pcpus->cpu[i].util1 = ((double)sum)/(double)j;
			}
			else if(j == 300) /* cpu usage for last five minutes */
			{
				pcpus->cpu[i].util5 = ((double)sum)/(double)j;
			}
		}

		/* cpu usage for last fifteen minutes */
		pcpus->cpu[i].util15 = ((double)sum)/(double)MAX_CPU_HISTORY;
		
		pcpus->cpu[i].h_usage_index++;
		if (pcpus->cpu[i].h_usage_index == MAX_CPU_HISTORY)
			pcpus->cpu[i].h_usage_index = 0;
	}


	if(pcpus->queue_counter)
	{
		/* Process CPU queue length data */
		PdhGetRawCounterValue(
			pcpus->queue_counter,
			NULL,
			&pcpus->queue);

		PdhCalculateCounterFromRawValue(
			pcpus->queue_counter,
			PDH_FMT_LONG,
			&pcpus->queue,
			NULL,
			&value);

		pcpus->h_queue[pcpus->h_queue_index] = value.longValue;

		/* Calculate average cpu usage */
		for(n = pcpus->h_queue_index, j = 0, sum = 0; j < MAX_CPU_HISTORY; j++, n--)
		{
			if(n < 0) n = MAX_CPU_HISTORY - 1;

			sum += pcpus->h_queue[n];

			if(j == 60) /* processor(s) load for last minute */
			{
				pcpus->load1 = ((double)sum)/(double)j;
			}
			else if(j == 300) /* processor(s) load for last five minutes */
			{
				pcpus->load5 = ((double)sum)/(double)j;
			}
		}

		/* cpu usage for last fifteen minutes */
		pcpus->load15 = ((double)sum)/(double)MAX_CPU_HISTORY;

		pcpus->h_queue_index++;

		if (pcpus->h_queue_index == MAX_CPU_HISTORY)
			pcpus->h_queue_index = 0;
	}
#else /* not _WINDOWS */

	int	now = 0;
	zbx_uint64_t cpu_user, cpu_nice, cpu_system, cpu_idle;


	if(0 != get_cpustat(&now, &cpu_user, &cpu_system, &cpu_nice, &cpu_idle))
		return;

	apply_cpustat(pcpus, now, cpu_user, cpu_system, cpu_nice, cpu_idle);

#endif /* _WINDOWS */
}
