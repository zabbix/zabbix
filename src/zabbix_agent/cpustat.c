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

#ifdef _WINDOWS
	#include "log.h"
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
	PDH_STATUS			status;
	char				cpu[16], counter_path[PDH_MAX_COUNTER_PATH];
	PDH_COUNTER_PATH_ELEMENTS	cpe;
	int				i;
	DWORD				dwSize;

	zabbix_log(LOG_LEVEL_DEBUG, "In init_cpu_collector()");

	if (ERROR_SUCCESS != (status = PdhOpenQuery(NULL, 0, &pcpus->pdh_query))) {
		zabbix_log( LOG_LEVEL_ERR, "Call to PdhOpenQuery() failed: %s",
				strerror_from_module(status, "PDH.DLL"));
		return 1;
	}

	cpe.szMachineName = NULL;
	cpe.szObjectName = GetCounterName(PCI_PROCESSOR);
	cpe.szInstanceName = cpu;
	cpe.szParentInstance = NULL;
	cpe.dwInstanceIndex = -1;
	cpe.szCounterName = GetCounterName(PCI_PROCESSOR_TIME);

	for(i = 0 /* 0 : _Total; >0 : cpu */; i <= pcpus->count; i++) {
		if (i == 0)
			zbx_strlcpy(cpu, "_Total", sizeof(cpu));
		else
			_itoa_s(i - 1, cpu, sizeof(cpu), 10);

		dwSize = sizeof(counter_path);
		if (ERROR_SUCCESS != (status = PdhMakeCounterPath(&cpe, counter_path, &dwSize, 0)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Call to PdhMakeCounterPath() failed: %s",
					strerror_from_module(status, "PDH.DLL"));
			return 1;
		}

		if (ERROR_SUCCESS != (status = PdhAddCounter(pcpus->pdh_query, counter_path, 0,
				&pcpus->cpu[i].usage_couter)))
		{
			zabbix_log( LOG_LEVEL_ERR, "Unable to add performance counter \"%s\" to query: %s",
					counter_path, strerror_from_module(status, "PDH.DLL"));
			return 2;
		}
	}

	if (ERROR_SUCCESS != (status = PdhCollectQueryData(pcpus->pdh_query)))
	{
		zabbix_log( LOG_LEVEL_ERR, "Call to PdhCollectQueryData() failed: %s",
				strerror_from_module(status, "PDH.DLL"));
		return 3;
	}

	for(i = 1; i <= pcpus->count; i++)
	{
		PdhGetRawCounterValue(pcpus->cpu[i].usage_couter, NULL, &pcpus->cpu[i].usage_old);
	}

	cpe.szObjectName = GetCounterName(PCI_SYSTEM);
	cpe.szInstanceName = NULL;
	cpe.szCounterName = GetCounterName(PCI_PROCESSOR_QUEUE_LENGTH);

	dwSize = sizeof(counter_path);
	if (ERROR_SUCCESS != (status = PdhMakeCounterPath(&cpe, counter_path, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Call to PdhMakeCounterPath() failed: %s",
				strerror_from_module(status, "PDH.DLL"));
		return 1;
	}

	/* Prepare for CPU execution queue usage collection */
	if (ERROR_SUCCESS != (status = PdhAddCounter(pcpus->pdh_query, counter_path, 0, &pcpus->queue_counter)))
	{
		zabbix_log( LOG_LEVEL_ERR, "Unable to add performance counter \"%s\" to query: %s",
				counter_path, strerror_from_module(status, "PDH.DLL"));
		return 2;
	}
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

	zabbix_log(LOG_LEVEL_DEBUG, "In close_cpu_collector()");

	if(pcpus->queue_counter)
	{
		PdhRemoveCounter(pcpus->queue_counter);
		pcpus->queue_counter = NULL;
	}

	for (i = 0; i < pcpus->count; i++)
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

static int	get_cpustat(
		int cpuid,
		int *now,
		zbx_uint64_t *cpu_user,
		zbx_uint64_t *cpu_system,
		zbx_uint64_t *cpu_nice,
		zbx_uint64_t *cpu_idle,
		zbx_uint64_t *cpu_interrupt
	)
{
    #if defined(HAVE_PROC_STAT)
	
	FILE	*file;
	char	line[1024];
	char	cpu_name[10];
	
    #elif defined(HAVE_SYS_PSTAT_H)
	
	struct	pst_dynamic stats;
	struct	pst_processor psp;

    #elif defined(HAVE_FUNCTION_SYSCTLBYNAME)

	static long	cp_time[CPUSTATES];
	size_t		nlen = sizeof(cp_time);
 	
    #elif defined(HAVE_FUNCTION_SYSCTL_KERN_CPTIME)

	int		mib[3];
	long		all_states[CPUSTATES];
	u_int64_t	one_states[CPUSTATES];
	size_t		sz;
	
    #else /* not HAVE_FUNCTION_SYSCTL_KERN_CPTIME */

	return 1;
	
    #endif /* HAVE_PROC_STAT */

	*now = time(NULL);

    #if defined(HAVE_PROC_STAT)
	
	if(NULL == (file = fopen("/proc/stat","r") ))
	{
		zbx_error("Cannot open [%s] [%s]\n","/proc/stat", strerror(errno));
		return 1;
	}

	*cpu_user = *cpu_system = *cpu_nice = *cpu_idle = -1;
	*cpu_interrupt	= 0;

	zbx_snprintf(cpu_name, sizeof(cpu_name), "cpu%c ", cpuid > 0 ? '0' + (cpuid - 1) : ' ');

	while ( fgets(line, sizeof(line), file) != NULL )
	{
		if(strstr(line, cpu_name) == NULL) continue;

		sscanf(line, "%*s " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64, cpu_user, cpu_nice, cpu_system, cpu_idle);
		break;
	}
	zbx_fclose(file);

	if(*cpu_user < 0) 
		return 1;
	
    #elif defined(HAVE_SYS_PSTAT_H) /* HAVE_PROC_STAT */

	if ( 0 == cpuid )
	{ /* all cpus */
		pstat_getdynamic(&stats, sizeof( struct pst_dynamic ), 1, 0 );
		*cpu_user 	= (zbx_uint64_t)stats.psd_cpu_time[CP_USER];
		*cpu_nice 	= (zbx_uint64_t)stats.psd_cpu_time[CP_NICE];
		*cpu_system 	= (zbx_uint64_t)stats.psd_cpu_time[CP_SYS];
		*cpu_idle 	= (zbx_uint64_t)stats.psd_cpu_time[CP_IDLE];
		*cpu_interrupt	= 0;
	}
	else if( cpuid > 0 )
	{
		if ( -1 == pstat_getprocessor(&psp, sizeof(struct pst_processor), 1, cpuid - 1) )
		{
			return 1;
		}

		*cpu_user 	= (zbx_uint64_t)psp.psp_cpu_time[CP_USER];
		*cpu_nice 	= (zbx_uint64_t)psp.psp_cpu_time[CP_NICE];
		*cpu_system 	= (zbx_uint64_t)psp.psp_cpu_time[CP_SYS];
		*cpu_idle 	= (zbx_uint64_t)psp.psp_cpu_time[CP_IDLE];
		*cpu_interrupt	= 0;
	}
	else
	{
		return 1;
	}

    #elif defined(HAVE_FUNCTION_SYSCTLBYNAME)
	/* FreeBSD 7.0 */

	if (sysctlbyname("kern.cp_time", &cp_time, &nlen, NULL, 0) == -1)
		return 1;

	if (nlen != sizeof(cp_time))
		return 1;

	*cpu_user	= (zbx_uint64_t)cp_time[CP_USER];
	*cpu_nice	= (zbx_uint64_t)cp_time[CP_NICE];
	*cpu_system	= (zbx_uint64_t)cp_time[CP_SYS];
	*cpu_interrupt	= (zbx_uint64_t)cp_time[CP_INTR];
	*cpu_idle	= (zbx_uint64_t)cp_time[CP_IDLE];

    #elif defined(HAVE_FUNCTION_SYSCTL_KERN_CPTIME)
	/* OpenBSD 4.3 */

	if (0 == cpuid)	/* all cpus */
	{
		mib[0] = CTL_KERN;
		mib[1] = KERN_CPTIME;

		sz = sizeof(all_states);
		if (-1 == sysctl(mib, 2, &all_states, &sz, NULL, 0))
			return 1;

		if (sz != sizeof(all_states))
			return 1;

		*cpu_user	= (zbx_uint64_t)all_states[CP_USER];
		*cpu_nice	= (zbx_uint64_t)all_states[CP_NICE];
		*cpu_system	= (zbx_uint64_t)all_states[CP_SYS];
		*cpu_interrupt	= (zbx_uint64_t)all_states[CP_INTR];
		*cpu_idle	= (zbx_uint64_t)all_states[CP_IDLE];
	}
	else if (cpuid > 0)
	{
		mib[0] = CTL_KERN;
		mib[1] = KERN_CPTIME2;
		mib[2] = cpuid - 1;

		sz = sizeof(one_states);
		if (-1 == sysctl(mib, 3, &one_states, &sz, NULL, 0))
			return 1;

		if (sz != sizeof(one_states))
			return 1;

		*cpu_user	= (zbx_uint64_t)one_states[CP_USER];
		*cpu_nice	= (zbx_uint64_t)one_states[CP_NICE];
		*cpu_system	= (zbx_uint64_t)one_states[CP_SYS];
		*cpu_interrupt	= (zbx_uint64_t)one_states[CP_INTR];
		*cpu_idle	= (zbx_uint64_t)one_states[CP_IDLE];
	}
	else
	{
		return 1;
	}

    #endif /* HAVE_FUNCTION_SYSCTL_KERN_CPTIME */

	return 0;
}


static void	apply_cpustat(
	ZBX_CPUS_STAT_DATA *pcpus,
	int cpuid,
	int now, 
	zbx_uint64_t cpu_user, 
	zbx_uint64_t cpu_system,
	zbx_uint64_t cpu_nice,
	zbx_uint64_t cpu_idle,
	zbx_uint64_t cpu_interrupt
	)
{
	register int	i	= 0;

	int		time = 0, time1 = 0, time5 = 0, time15 = 0;
	zbx_uint64_t	user = 0, user1 = 0, user5 = 0, user15 = 0,
			system = 0, system1 = 0, system5 = 0, system15 = 0,
			nice = 0, nice1 = 0, nice5 = 0, nice15 = 0,
			idle = 0, idle1 = 0, idle5 = 0, idle15 = 0,
			interrupt = 0, interrupt1 = 0, interrupt5 = 0, interrupt15 = 0,
			all = 0, all1 = 0, all5 = 0, all15 = 0;

	ZBX_SINGLE_CPU_STAT_DATA
			*curr_cpu = &pcpus->cpu[cpuid];

	for (i = 0; i < MAX_CPU_HISTORY; i++)
	{
		if (curr_cpu->clock[i] >= now - MAX_CPU_HISTORY)
			continue;

		curr_cpu->clock[i] = now;
		curr_cpu->h_user[i] = user = cpu_user;
		curr_cpu->h_system[i] = system = cpu_system;
		curr_cpu->h_nice[i] = nice = cpu_nice;
		curr_cpu->h_idle[i] = idle = cpu_idle;
		curr_cpu->h_interrupt[i] = interrupt = cpu_interrupt;

		all = cpu_user + cpu_system + cpu_nice + cpu_idle + cpu_interrupt;
		break;
	}

	time = time1 = time5 = time15 = now + 1;

	for (i = 0; i < MAX_CPU_HISTORY; i++)
	{
		if (0 == curr_cpu->clock[i])
			continue;

#define SAVE_CPU_CLOCK_FOR(t)											\
		if ((curr_cpu->clock[i] >= (now - (t * 60))) && (time ## t > curr_cpu->clock[i]))		\
		{												\
			time ## t	= curr_cpu->clock[i];							\
			user ## t	= curr_cpu->h_user[i];							\
			system ## t	= curr_cpu->h_system[i];						\
			nice ## t	= curr_cpu->h_nice[i];							\
			idle ## t	= curr_cpu->h_idle[i];							\
			interrupt ## t	= curr_cpu->h_interrupt[i];						\
			all ## t	= user ## t + system ## t + nice ## t + idle ## t + interrupt ## t;	\
		}

		SAVE_CPU_CLOCK_FOR(1);
		SAVE_CPU_CLOCK_FOR(5);
		SAVE_CPU_CLOCK_FOR(15);
	}

#define CALC_CPU_LOAD(type, time)							\
	if ((type) - (type ## time) > 0 && (all) - (all ## time) > 0)			\
	{										\
		curr_cpu->type ## time = 100. * ((double)((type) - (type ## time)))/	\
				((double)((all) - (all ## time)));			\
	}										\
	else										\
	{										\
		curr_cpu->type ## time = 0.;						\
	}

	CALC_CPU_LOAD(user, 1);
	CALC_CPU_LOAD(user, 5);
	CALC_CPU_LOAD(user, 15);

	CALC_CPU_LOAD(system, 1);
	CALC_CPU_LOAD(system, 5);
	CALC_CPU_LOAD(system, 15);

	CALC_CPU_LOAD(nice, 1);
	CALC_CPU_LOAD(nice, 5);
	CALC_CPU_LOAD(nice, 15);

	CALC_CPU_LOAD(idle, 1);
	CALC_CPU_LOAD(idle, 5);
	CALC_CPU_LOAD(idle, 15);

	CALC_CPU_LOAD(interrupt, 1);
	CALC_CPU_LOAD(interrupt, 5);
	CALC_CPU_LOAD(interrupt, 15);
}

#endif /* not _WINDOWS */

void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus)
{
#ifdef _WINDOWS

	PDH_FMT_COUNTERVALUE	value;
	PDH_STATUS		status;
	LONG			sum;
	int			i, j, n;

	if (!pcpus->pdh_query)
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "In collect_cpustat()");

	if ((status = PdhCollectQueryData(pcpus->pdh_query)) != ERROR_SUCCESS)
	{
		zabbix_log( LOG_LEVEL_ERR, "Call to PdhCollectQueryData() failed: %s", strerror_from_module(status,"PDH.DLL"));
		return;
	}

	/* Process CPU utilization data */
	for(i=0; i <= pcpus->count; i++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In collect_cpustat() [0:%d]", i);
		if(!pcpus->cpu[i].usage_couter)
			continue;
		zabbix_log(LOG_LEVEL_DEBUG, "In collect_cpustat() [1:%d]", i);

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
		zabbix_log(LOG_LEVEL_DEBUG, "In collect_cpustat() [2:%d] " ZBX_FS_DBL " " ZBX_FS_DBL " " ZBX_FS_DBL, i, pcpus->cpu[i].util1, pcpus->cpu[i].util5, pcpus->cpu[i].util15);


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

	register int i = 0;
	int	now = 0;

	zbx_uint64_t cpu_user, cpu_nice, cpu_system, cpu_idle, cpu_interrupt;

	for ( i = 0; i <= pcpus->count; i++ )
	{
		if(0 != get_cpustat(i, &now, &cpu_user, &cpu_system, &cpu_nice, &cpu_idle, &cpu_interrupt))
			continue;

		apply_cpustat(pcpus, i, now, cpu_user, cpu_system, cpu_nice, cpu_idle, cpu_interrupt);
	}

#endif /* _WINDOWS */
}
