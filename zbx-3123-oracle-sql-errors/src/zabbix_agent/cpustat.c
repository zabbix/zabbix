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
 * Return value: If the function succeeds, return 0,                          *
 *               bigger than 0 on an error                                    *
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
	TCHAR				cpu[8], counter_path[PDH_MAX_COUNTER_PATH];
	PDH_COUNTER_PATH_ELEMENTS	cpe;
	int				i;
	DWORD				dwSize;

	zabbix_log(LOG_LEVEL_DEBUG, "In init_cpu_collector()");

	if (ERROR_SUCCESS != (status = PdhOpenQuery(NULL, 0, &pcpus->pdh_query)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Call to PdhOpenQuery() failed: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return 1;
	}

	cpe.szMachineName = NULL;
	cpe.szObjectName = GetCounterName(PCI_PROCESSOR);
	cpe.szInstanceName = cpu;
	cpe.szParentInstance = NULL;
	cpe.dwInstanceIndex = -1;
	cpe.szCounterName = GetCounterName(PCI_PROCESSOR_TIME);

	for(i = 0 /* 0 : _Total; >0 : cpu */; i <= pcpus->count; i++)
	{
		if (i == 0)
			zbx_wsnprintf(cpu, sizeof(cpu)/sizeof(TCHAR), TEXT("_Total"));
		else
			_itow_s(i - 1, cpu, sizeof(cpu)/sizeof(TCHAR), 10);

		dwSize = PDH_MAX_COUNTER_PATH;
		if (ERROR_SUCCESS != (status = PdhMakeCounterPath(&cpe, counter_path, &dwSize, 0)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Call to PdhMakeCounterPath() failed: %s",
					strerror_from_module(status, TEXT("PDH.DLL")));
			return 1;
		}

		if (ERROR_SUCCESS != (status = PdhAddCounter(pcpus->pdh_query, counter_path, 0,
				&pcpus->cpu[i].usage_counter)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Unable to add performance counter to query: %s",
					strerror_from_module(status, TEXT("PDH.DLL")));
			return 2;
		}
	}

	if (ERROR_SUCCESS != (status = PdhCollectQueryData(pcpus->pdh_query)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Call to PdhCollectQueryData() failed: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return 3;
	}

	for(i = 1; i <= pcpus->count; i++)
	{
		PdhGetRawCounterValue(pcpus->cpu[i].usage_counter, NULL, &pcpus->cpu[i].usage_old);
	}

	cpe.szObjectName = GetCounterName(PCI_SYSTEM);
	cpe.szInstanceName = NULL;
	cpe.szCounterName = GetCounterName(PCI_PROCESSOR_QUEUE_LENGTH);

	dwSize = PDH_MAX_COUNTER_PATH;
	if (ERROR_SUCCESS != (status = PdhMakeCounterPath(&cpe, counter_path, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Call to PdhMakeCounterPath() failed: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return 1;
	}

	/* Prepare for CPU execution queue usage collection */
	if (ERROR_SUCCESS != (status = PdhAddCounter(pcpus->pdh_query, counter_path, 0, &pcpus->queue_counter)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Unable to add performance counter to query: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return 2;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of init_cpu_collector()");

#endif /* _WINDOWS */

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: close_cpu_collector                                              *
 *                                                                            *
 * Purpose: Clear state of data calculation                                   *
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
		if(pcpus->cpu[i].usage_counter)
		{
			PdhRemoveCounter(pcpus->cpu[i].usage_counter);
			pcpus->cpu[i].usage_counter = NULL;
		}
	}

	if(pcpus->pdh_query)
	{
		PdhCloseQuery(pcpus->pdh_query);
		pcpus->pdh_query = NULL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of close_cpu_collector()");

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
		zbx_uint64_t *cpu_interrupt,
		zbx_uint64_t *cpu_iowait,
		zbx_uint64_t *cpu_softirq,
		zbx_uint64_t *cpu_steal
	)
{
    #if defined(HAVE_PROC_STAT)

	FILE	*file;
	char	line[1024];
	char	cpu_name[10];

    #elif defined(HAVE_SYS_PSTAT_H)

	struct	pst_dynamic stats;
	struct	pst_processor psp;

    #elif defined(HAVE_FUNCTION_SYSCTLBYNAME) && defined(CPUSTATES)

	static long	cp_time[CPUSTATES];
	size_t		nlen = sizeof(cp_time);

    #elif defined(HAVE_FUNCTION_SYSCTL_KERN_CPTIME)

	int		mib[3];
	long		all_states[CPUSTATES];
	u_int64_t	one_states[CPUSTATES];
	size_t		sz;

    #elif defined(HAVE_LIBPERFSTAT)

	perfstat_cpu_total_t	ps_cpu_total;
	perfstat_cpu_t		ps_cpu;
	perfstat_id_t		ps_id;

    #elif defined(HAVE_KSTAT_H)

	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;

    #else /* not HAVE_KSTAT_H */

	return 1;

    #endif /* HAVE_PROC_STAT */

	*now = time(NULL);
	*cpu_user = *cpu_system = *cpu_nice = *cpu_idle = *cpu_interrupt = *cpu_iowait = *cpu_softirq = *cpu_steal = 0;

    #if defined(HAVE_PROC_STAT)

	if (NULL == (file = fopen("/proc/stat", "r")))
	{
		zbx_error("Cannot open [%s] [%s]\n", "/proc/stat", strerror(errno));
		return 1;
	}

	if (cpuid > 0)
		zbx_snprintf(cpu_name, sizeof(cpu_name), "cpu%d ", cpuid - 1);
	else
		zbx_strlcpy(cpu_name, "cpu  ", sizeof(cpu_name));

	while (NULL != fgets(line, sizeof(line), file))
	{
		if (NULL == strstr(line, cpu_name))
			continue;

		sscanf(line, "%*s " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " "
				ZBX_FS_UI64 " " ZBX_FS_UI64, cpu_user, cpu_nice, cpu_system, cpu_idle, cpu_iowait, cpu_interrupt,
				cpu_softirq, cpu_steal);
		break;
	}
	zbx_fclose(file);

	if (*cpu_user + *cpu_system + *cpu_nice + *cpu_idle + *cpu_interrupt + *cpu_iowait + *cpu_softirq + *cpu_steal == 0)
		return 1;

    #elif defined(HAVE_SYS_PSTAT_H) /* HAVE_PROC_STAT */

	if ( 0 == cpuid )
	{ /* all cpus */
		pstat_getdynamic(&stats, sizeof( struct pst_dynamic ), 1, 0 );
		*cpu_user 	= (zbx_uint64_t)stats.psd_cpu_time[CP_USER];
		*cpu_nice 	= (zbx_uint64_t)stats.psd_cpu_time[CP_NICE];
		*cpu_system 	= (zbx_uint64_t)stats.psd_cpu_time[CP_SYS];
		*cpu_idle 	= (zbx_uint64_t)stats.psd_cpu_time[CP_IDLE];
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
	}
	else
	{
		return 1;
	}

    #elif defined(HAVE_FUNCTION_SYSCTLBYNAME) && defined(CPUSTATES)
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

    #elif defined(HAVE_KSTAT_H)

	/* Solaris */

	*cpu_idle = *cpu_user = *cpu_system = *cpu_nice = *cpu_interrupt = *cpu_iowait = 0;

	if (NULL == (kc = kstat_open()))
		return 1;

	if (cpuid == 0)	/* all cpus */
	{
		k = kc->kc_chain;
		while (k)
		{
			if (0 == strncmp(k->ks_name, "cpu_stat", 8) && -1 != kstat_read(kc, k, NULL))
			{
				cpu = (cpu_stat_t *)k->ks_data;

				*cpu_idle	+= cpu->cpu_sysinfo.cpu[CPU_IDLE];
				*cpu_user	+= cpu->cpu_sysinfo.cpu[CPU_USER];
				*cpu_system	+= cpu->cpu_sysinfo.cpu[CPU_KERNEL];
				*cpu_nice	+= cpu->cpu_sysinfo.cpu[CPU_WAIT];
			}
			k = k->ks_next;
		}
	}
	else	/* single cpu */
	{
		if (NULL == (k = kstat_lookup(kc, "cpu_stat", cpuid - 1, NULL)))
		{
			kstat_close(kc);
			return 1;
		}

		if (-1 == kstat_read(kc, k, NULL))
		{
			kstat_close(kc);
			return 1;
		}

		cpu = (cpu_stat_t *)k->ks_data;

		*cpu_idle	= cpu->cpu_sysinfo.cpu[CPU_IDLE];
		*cpu_user	= cpu->cpu_sysinfo.cpu[CPU_USER];
		*cpu_system	= cpu->cpu_sysinfo.cpu[CPU_KERNEL];
		*cpu_nice	= cpu->cpu_sysinfo.cpu[CPU_WAIT];
	}

	kstat_close(kc);

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
		return 1;

   #elif defined(HAVE_LIBPERFSTAT)
	/* AIX 6.1 */

	if (0 == cpuid)	/* all cpus */
	{
		if (-1 == perfstat_cpu_total(NULL, &ps_cpu_total, sizeof(ps_cpu_total), 1))
			return 1;

		*cpu_user	= (zbx_uint64_t)ps_cpu_total.user;
		*cpu_system	= (zbx_uint64_t)ps_cpu_total.sys;
		*cpu_idle	= (zbx_uint64_t)ps_cpu_total.idle;
		*cpu_iowait	= (zbx_uint64_t)ps_cpu_total.wait;
	}
	else if (cpuid > 0)
	{
		zbx_snprintf(ps_id.name, sizeof(ps_id.name), "cpu%d", cpuid - 1);

		if (-1 == perfstat_cpu(&ps_id, &ps_cpu, sizeof(ps_cpu), 1))
			return 1;

		*cpu_user	= (zbx_uint64_t)ps_cpu.user;
		*cpu_system	= (zbx_uint64_t)ps_cpu.sys;
		*cpu_idle	= (zbx_uint64_t)ps_cpu.idle;
		*cpu_iowait	= (zbx_uint64_t)ps_cpu.wait;
	}
	else
		return 1;

    #endif /* HAVE_LIBPERFSTAT */

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
	zbx_uint64_t cpu_interrupt,
	zbx_uint64_t cpu_iowait,
	zbx_uint64_t cpu_softirq,
	zbx_uint64_t cpu_steal
	)
{
	register int	i = 0;

	int		time = 0, time1 = 0, time5 = 0, time15 = 0;
	zbx_uint64_t	user = 0, user1 = 0, user5 = 0, user15 = 0,
			system = 0, system1 = 0, system5 = 0, system15 = 0,
			nice = 0, nice1 = 0, nice5 = 0, nice15 = 0,
			idle = 0, idle1 = 0, idle5 = 0, idle15 = 0,
			interrupt = 0, interrupt1 = 0, interrupt5 = 0, interrupt15 = 0,
			iowait = 0, iowait1 = 0, iowait5 = 0, iowait15 = 0,
			softirq = 0, softirq1 = 0, softirq5 = 0, softirq15 = 0,
			steal = 0, steal1 = 0, steal5 = 0, steal15 = 0,
			all = 0, all1 = 0, all5 = 0, all15 = 0;

	ZBX_SINGLE_CPU_STAT_DATA	*curr_cpu = &pcpus->cpu[cpuid];

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
		curr_cpu->h_iowait[i] = iowait = cpu_iowait;
		curr_cpu->h_softirq[i] = softirq = cpu_softirq;
		curr_cpu->h_steal[i] = steal = cpu_steal;

		all = cpu_user + cpu_system + cpu_nice + cpu_idle + cpu_interrupt + cpu_iowait + cpu_softirq + cpu_steal;
		break;
	}

	time = time1 = time5 = time15 = now + 1;

	for (i = 0; i < MAX_CPU_HISTORY; i++)
	{
		if (0 == curr_cpu->clock[i])
			continue;

#define SAVE_CPU_CLOCK_FOR(t)										\
		if ((curr_cpu->clock[i] >= (now - (t * 60))) && (time ## t > curr_cpu->clock[i]))	\
		{											\
			time ## t	= curr_cpu->clock[i];						\
			user ## t	= curr_cpu->h_user[i];						\
			system ## t	= curr_cpu->h_system[i];					\
			nice ## t	= curr_cpu->h_nice[i];						\
			idle ## t	= curr_cpu->h_idle[i];						\
			interrupt ## t	= curr_cpu->h_interrupt[i];					\
			iowait ## t	= curr_cpu->h_iowait[i];					\
			softirq ## t	= curr_cpu->h_softirq[i];					\
			steal ## t	= curr_cpu->h_steal[i];						\
			all ## t	= user ## t + system ## t + nice ## t + idle ## t +		\
						interrupt ## t + iowait ## t + softirq ## t +		\
						steal ## t;						\
		}

		SAVE_CPU_CLOCK_FOR(1);
		SAVE_CPU_CLOCK_FOR(5);
		SAVE_CPU_CLOCK_FOR(15);
	}

#define CALC_CPU_UTIL(type, time)								\
	if ((type) - (type ## time) > 0 && (all) - (all ## time) > 0)				\
	{											\
		curr_cpu->type[ZBX_AVG ## time] = 100. * ((double)((type) - (type ## time))) /	\
				((double)((all) - (all ## time)));				\
	}											\
	else											\
	{											\
		curr_cpu->type[ZBX_AVG ## time] = 0.;						\
	}

	CALC_CPU_UTIL(user, 1);
	CALC_CPU_UTIL(user, 5);
	CALC_CPU_UTIL(user, 15);

	CALC_CPU_UTIL(system, 1);
	CALC_CPU_UTIL(system, 5);
	CALC_CPU_UTIL(system, 15);

	CALC_CPU_UTIL(nice, 1);
	CALC_CPU_UTIL(nice, 5);
	CALC_CPU_UTIL(nice, 15);

	CALC_CPU_UTIL(idle, 1);
	CALC_CPU_UTIL(idle, 5);
	CALC_CPU_UTIL(idle, 15);

	CALC_CPU_UTIL(interrupt, 1);
	CALC_CPU_UTIL(interrupt, 5);
	CALC_CPU_UTIL(interrupt, 15);

	CALC_CPU_UTIL(iowait, 1);
	CALC_CPU_UTIL(iowait, 5);
	CALC_CPU_UTIL(iowait, 15);

	CALC_CPU_UTIL(softirq, 1);
	CALC_CPU_UTIL(softirq, 5);
	CALC_CPU_UTIL(softirq, 15);

	CALC_CPU_UTIL(steal, 1);
	CALC_CPU_UTIL(steal, 5);
	CALC_CPU_UTIL(steal, 15);
}

#endif /* not _WINDOWS */

void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus)
{
#ifdef _WINDOWS

	PDH_FMT_COUNTERVALUE	value;
	PDH_STATUS		status;
	int			i, n;

	zabbix_log(LOG_LEVEL_DEBUG, "In collect_cpustat()");

	if (!pcpus->pdh_query)
		return;

	if ((status = PdhCollectQueryData(pcpus->pdh_query)) != ERROR_SUCCESS)
	{
		zabbix_log(LOG_LEVEL_ERR, "Call to PdhCollectQueryData() failed: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return;
	}

	/* Process CPU utilization data */
	for (i = 0; i <= pcpus->count; i++)
	{
		ZBX_SINGLE_CPU_STAT_DATA	*curr_cpu = &pcpus->cpu[i];

		if (!curr_cpu->usage_counter)
			continue;

		PdhGetRawCounterValue(
			curr_cpu->usage_counter,
			NULL,
			&curr_cpu->usage);

		PdhCalculateCounterFromRawValue(
			curr_cpu->usage_counter,
			PDH_FMT_LONG,
			&curr_cpu->usage,
			&curr_cpu->usage_old,
			&value);
			
		curr_cpu->usage_old = curr_cpu->usage;

		/* Calculate average CPU usage */

		curr_cpu->util15sum -= curr_cpu->h_usage[curr_cpu->h_usage_index];
		curr_cpu->h_usage[curr_cpu->h_usage_index] = value.longValue;
		curr_cpu->util15sum += value.longValue;

		n = curr_cpu->h_usage_index - 5 * SEC_PER_MIN;
		curr_cpu->util5sum -= curr_cpu->h_usage[n < 0 ? n + MAX_CPU_HISTORY : n];
		curr_cpu->util5sum += value.longValue;

		n = curr_cpu->h_usage_index - 1 * SEC_PER_MIN;
		curr_cpu->util1sum -= curr_cpu->h_usage[n < 0 ? n + MAX_CPU_HISTORY : n];
		curr_cpu->util1sum += value.longValue;

		curr_cpu->util15 = curr_cpu->util15sum / (double)MAX_CPU_HISTORY;
		curr_cpu->util5 = curr_cpu->util5sum / (double)(5 * SEC_PER_MIN);
		curr_cpu->util1 = curr_cpu->util1sum / (double)(1 * SEC_PER_MIN);

		curr_cpu->h_usage_index++;
		if (curr_cpu->h_usage_index == MAX_CPU_HISTORY)
			curr_cpu->h_usage_index = 0;
	}

	if (pcpus->queue_counter)
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

		/* Calculate average processor load */

		pcpus->load15sum -= pcpus->h_queue[pcpus->h_queue_index];
		pcpus->h_queue[pcpus->h_queue_index] = value.longValue;
		pcpus->load15sum += value.longValue;
		
		n = pcpus->h_queue_index - 5 * SEC_PER_MIN;
		pcpus->load5sum -= pcpus->h_queue[n < 0 ? n + MAX_CPU_HISTORY : n];
		pcpus->load5sum += value.longValue;

		n = pcpus->h_queue_index - 1 * SEC_PER_MIN;
		pcpus->load1sum -= pcpus->h_queue[n < 0 ? n + MAX_CPU_HISTORY : n];
		pcpus->load1sum += value.longValue;

		pcpus->load15 = pcpus->load15sum / (double)MAX_CPU_HISTORY;
		pcpus->load5 = pcpus->load5sum / (double)(5 * SEC_PER_MIN);
		pcpus->load1 = pcpus->load1sum / (double)(1 * SEC_PER_MIN);

		pcpus->h_queue_index++;
		if (pcpus->h_queue_index == MAX_CPU_HISTORY)
			pcpus->h_queue_index = 0;
	}
	
	zabbix_log(LOG_LEVEL_DEBUG, "End of collect_cpustat()");

#else /* not _WINDOWS */

	register int	i = 0;
	int		now = 0;

	zbx_uint64_t cpu_user, cpu_nice, cpu_system, cpu_idle, cpu_interrupt, cpu_iowait, cpu_softirq, cpu_steal;

	for (i= 0; i <= pcpus->count; i++)
	{
		if(0 != get_cpustat(i, &now, &cpu_user, &cpu_system, &cpu_nice, &cpu_idle, &cpu_interrupt, &cpu_iowait, &cpu_softirq, &cpu_steal))
			continue;

		apply_cpustat(pcpus, i, now, cpu_user, cpu_system, cpu_nice, cpu_idle, cpu_interrupt, cpu_iowait, cpu_softirq, cpu_steal);
	}

#endif /* _WINDOWS */
}
