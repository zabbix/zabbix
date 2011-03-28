/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
#include "cpustat.h"
#include "mutexs.h"

#include "log.h"

#ifdef _WINDOWS
#	include "perfmon.h"
#else
#	define LOCK_CPUSTATS	zbx_mutex_lock(&cpustats_lock)
#	define UNLOCK_CPUSTATS	zbx_mutex_unlock(&cpustats_lock)
static ZBX_MUTEX	cpustats_lock;
#endif

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
	const char			*__function_name = "init_cpu_collector";
#ifdef _WINDOWS
	PDH_STATUS			status;
	TCHAR				cpu[8], counter_path[PDH_MAX_COUNTER_PATH];
	PDH_COUNTER_PATH_ELEMENTS	cpe;
	int				i;
	DWORD				dwSize;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#ifdef _WINDOWS
	if (ERROR_SUCCESS != (status = PdhOpenQuery(NULL, 0, &pcpus->pdh_query)))
	{
		zabbix_log(LOG_LEVEL_ERR, "call to PdhOpenQuery() failed: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return 1;
	}

	cpe.szMachineName = NULL;
	cpe.szObjectName = GetCounterName(PCI_PROCESSOR);
	cpe.szInstanceName = cpu;
	cpe.szParentInstance = NULL;
	cpe.dwInstanceIndex = -1;
	cpe.szCounterName = GetCounterName(PCI_PROCESSOR_TIME);

	for (i = 0; i <= pcpus->count; i++)
	{
		if (0 == i)
			zbx_wsnprintf(cpu, sizeof(cpu) / sizeof(TCHAR), TEXT("_Total"));
		else
			_itow_s(i - 1, cpu, sizeof(cpu) / sizeof(TCHAR), 10);

		dwSize = PDH_MAX_COUNTER_PATH;
		if (ERROR_SUCCESS != (status = PdhMakeCounterPath(&cpe, counter_path, &dwSize, 0)))
		{
			zabbix_log(LOG_LEVEL_ERR, "call to PdhMakeCounterPath() failed: %s",
					strerror_from_module(status, TEXT("PDH.DLL")));
			return 1;
		}

		if (ERROR_SUCCESS != (status = PdhAddCounter(pcpus->pdh_query, counter_path, 0,
				&pcpus->cpu[i].usage_counter)))
		{
			zabbix_log(LOG_LEVEL_ERR, "unable to add performance counter to query: %s",
					strerror_from_module(status, TEXT("PDH.DLL")));
			return 2;
		}
	}

	if (ERROR_SUCCESS != (status = PdhCollectQueryData(pcpus->pdh_query)))
	{
		zabbix_log(LOG_LEVEL_ERR, "call to PdhCollectQueryData() failed: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return 3;
	}

	for (i = 1; i <= pcpus->count; i++)
		PdhGetRawCounterValue(pcpus->cpu[i].usage_counter, NULL, &pcpus->cpu[i].usage_old);

	cpe.szObjectName = GetCounterName(PCI_SYSTEM);
	cpe.szInstanceName = NULL;
	cpe.szCounterName = GetCounterName(PCI_PROCESSOR_QUEUE_LENGTH);

	dwSize = PDH_MAX_COUNTER_PATH;
	if (ERROR_SUCCESS != (status = PdhMakeCounterPath(&cpe, counter_path, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "call to PdhMakeCounterPath() failed: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return 1;
	}

	/* prepare for CPU execution queue usage collection */
	if (ERROR_SUCCESS != (status = PdhAddCounter(pcpus->pdh_query, counter_path, 0, &pcpus->queue_counter)))
	{
		zabbix_log(LOG_LEVEL_ERR, "unable to add performance counter to query: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return 2;
	}
#else	/* not _WINDOWS */
	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cpustats_lock, ZBX_MUTEX_CPUSTATS))
	{
		zbx_error("unable to create mutex for cpu collector");
		exit(FAIL);
	}
#endif	/* _WINDOWS */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: free_cpu_collector                                               *
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
void	free_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
	const char	*__function_name = "free_cpu_collector";
#ifdef _WINDOWS
	int		i;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#ifdef _WINDOWS
	if (pcpus->queue_counter)
	{
		PdhRemoveCounter(pcpus->queue_counter);
		pcpus->queue_counter = NULL;
	}

	for (i = 0; i <= pcpus->count; i++)
	{
		if (NULL == pcpus->cpu[i].usage_counter)
			continue;

		PdhRemoveCounter(pcpus->cpu[i].usage_counter);
		pcpus->cpu[i].usage_counter = NULL;
	}

	if (pcpus->pdh_query)
	{
		PdhCloseQuery(pcpus->pdh_query);
		pcpus->pdh_query = NULL;
	}
#else	/* not _WINDOWS */
	zbx_mutex_destroy(&cpustats_lock);
#endif	/* _WINDOWS */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#ifndef _WINDOWS

static void	update_cpu_counters(ZBX_SINGLE_CPU_STAT_DATA *cpu, zbx_uint64_t *counter)
{
	int	i, index;

	LOCK_CPUSTATS;

	if (MAX_COLLECTOR_HISTORY <= (index = cpu->h_first + cpu->h_count))
		index -= MAX_COLLECTOR_HISTORY;

	if (cpu->h_count < MAX_COLLECTOR_HISTORY)
		cpu->h_count++;
	else if (++cpu->h_first == MAX_COLLECTOR_HISTORY)
		cpu->h_first = 0;

	for (i = 0; i < ZBX_CPU_STATE_COUNT; i++)
		cpu->h_counter[i][index] = counter[i];

	UNLOCK_CPUSTATS;
}

static void	update_cpustats(ZBX_CPUS_STAT_DATA *pcpus)
{
	const char	*__function_name = "update_cpustats";
	int		cpu_num;
	zbx_uint64_t	counter[ZBX_CPU_STATE_COUNT];

#if defined(HAVE_PROC_STAT)

	FILE	*file;
	char	line[1024];

#elif defined(HAVE_SYS_PSTAT_H)

	struct pst_dynamic	psd;
	struct pst_processor	psp;

#elif defined(HAVE_FUNCTION_SYSCTLBYNAME) && defined(CPUSTATES)

	long	cp_time[CPUSTATES];
	size_t	nlen = sizeof(cp_time);

#elif defined(HAVE_KSTAT_H)

	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;
	zbx_uint64_t	total[ZBX_CPU_STATE_COUNT];

#elif defined(HAVE_FUNCTION_SYSCTL_KERN_CPTIME)

	int		mib[3];
	long		all_states[CPUSTATES];
	u_int64_t	one_states[CPUSTATES];
	size_t		sz;

#elif defined(HAVE_LIBPERFSTAT)

	perfstat_cpu_total_t	ps_cpu_total;
	perfstat_cpu_t		ps_cpu;
	perfstat_id_t		ps_id;

#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#if defined(HAVE_PROC_STAT)

	if (NULL == (file = fopen("/proc/stat", "r")))
	{
		zbx_error("cannot open [%s] [%s]", "/proc/stat", strerror(errno));
		return;
	}

	while (NULL != fgets(line, sizeof(line), file))
	{
		if (0 != strncmp(line, "cpu", 3))
			continue;

		if ('0' <= line[3] && line[3] <= '9')
		{
			cpu_num = atoi(line + 3) + 1;
			if (1 > cpu_num || cpu_num > pcpus->count)
				continue;
		}
		else if (' ' == line[3])
			cpu_num = 0;
		else
			continue;

		memset(counter, 0, sizeof(counter));

		sscanf(line, "%*s " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64
				" " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64,
				&counter[ZBX_CPU_STATE_USER], &counter[ZBX_CPU_STATE_NICE],
				&counter[ZBX_CPU_STATE_SYSTEM], &counter[ZBX_CPU_STATE_IDLE],
				&counter[ZBX_CPU_STATE_IOWAIT], &counter[ZBX_CPU_STATE_INTERRUPT],
				&counter[ZBX_CPU_STATE_SOFTIRQ], &counter[ZBX_CPU_STATE_STEAL]);

		update_cpu_counters(&pcpus->cpu[cpu_num], counter);
	}
	zbx_fclose(file);

#elif defined(HAVE_SYS_PSTAT_H)

	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)
	{
		memset(counter, 0, sizeof(counter));

		if (0 == cpu_num)
		{
			if (-1 == pstat_getdynamic(&psd, sizeof(psd), 1, 0))
				return;

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)psd.psd_cpu_time[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)psd.psd_cpu_time[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)psd.psd_cpu_time[CP_SYS];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)psd.psd_cpu_time[CP_IDLE];
		}
		else
		{
			if (-1 == pstat_getprocessor(&psp, sizeof(psp), 1, cpu_num - 1))
				return;

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)psp.psp_cpu_time[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)psp.psp_cpu_time[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)psp.psp_cpu_time[CP_SYS];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)psp.psp_cpu_time[CP_IDLE];
		}

		update_cpu_counters(&pcpus->cpu[cpu_num], counter);
	}

#elif defined(HAVE_FUNCTION_SYSCTLBYNAME) && defined(CPUSTATES)
	/* FreeBSD 7.0 */

	if (-1 == sysctlbyname("kern.cp_time", &cp_time, &nlen, NULL, 0))
		return;

	if (nlen != sizeof(cp_time))
		return;

	memset(counter, 0, sizeof(counter));

	counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)cp_time[CP_USER];
	counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)cp_time[CP_NICE];
	counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)cp_time[CP_SYS];
	counter[ZBX_CPU_STATE_INTERRUPT] = (zbx_uint64_t)cp_time[CP_INTR];
	counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)cp_time[CP_IDLE];

	update_cpu_counters(&pcpus->cpu[0], counter);

#elif defined(HAVE_KSTAT_H)
	/* Solaris */

	if (NULL == (kc = kstat_open()))
		return;

	memset(total, 0, sizeof(total));

	for (cpu_num = 1; cpu_num <= pcpus->count; cpu_num++)
	{
		if (NULL == (k = kstat_lookup(kc, "cpu_stat", cpu_num - 1, NULL)))
		{
			kstat_close(kc);
			return;
		}

		if (-1 == kstat_read(kc, k, NULL))
		{
			kstat_close(kc);
			return;
		}

		cpu = (cpu_stat_t *)k->ks_data;

		memset(counter, 0, sizeof(counter));

		total[ZBX_CPU_STATE_IDLE] += counter[ZBX_CPU_STATE_IDLE] = cpu->cpu_sysinfo.cpu[CPU_IDLE];
		total[ZBX_CPU_STATE_USER] += counter[ZBX_CPU_STATE_USER] = cpu->cpu_sysinfo.cpu[CPU_USER];
		total[ZBX_CPU_STATE_SYSTEM] += counter[ZBX_CPU_STATE_SYSTEM] = cpu->cpu_sysinfo.cpu[CPU_KERNEL];
		total[ZBX_CPU_STATE_IOWAIT] += counter[ZBX_CPU_STATE_IOWAIT] = cpu->cpu_sysinfo.cpu[CPU_WAIT];

		update_cpu_counters(&pcpus->cpu[cpu_num], counter);
	}
	kstat_close(kc);

	update_cpu_counters(&pcpus->cpu[0], total);

#elif defined(HAVE_FUNCTION_SYSCTL_KERN_CPTIME)
	/* OpenBSD 4.3 */

	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)
	{
		memset(counter, 0, sizeof(counter));

		if (0 == cpu_num)
		{
			mib[0] = CTL_KERN;
			mib[1] = KERN_CPTIME;

			sz = sizeof(all_states);
			if (-1 == sysctl(mib, 2, &all_states, &sz, NULL, 0))
				return;

			if (sz != sizeof(all_states))
				return;

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)all_states[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)all_states[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)all_states[CP_SYS];
			counter[ZBX_CPU_STATE_INTERRUPT] = (zbx_uint64_t)all_states[CP_INTR];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)all_states[CP_IDLE];
		}
		else
		{
			mib[0] = CTL_KERN;
			mib[1] = KERN_CPTIME2;
			mib[2] = cpu_num - 1;

			sz = sizeof(one_states);
			if (-1 == sysctl(mib, 3, &one_states, &sz, NULL, 0))
				return;

			if (sz != sizeof(one_states))
				return;

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)one_states[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)one_states[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)one_states[CP_SYS];
			counter[ZBX_CPU_STATE_INTERRUPT] = (zbx_uint64_t)one_states[CP_INTR];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)one_states[CP_IDLE];
		}

		update_cpu_counters(&pcpus->cpu[cpu_num], counter);
	}

#elif defined(HAVE_LIBPERFSTAT)
	/* AIX 6.1 */

	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)
	{
		memset(counter, 0, sizeof(counter));

		if (0 == cpu_num)
		{
			if (-1 == perfstat_cpu_total(NULL, &ps_cpu_total, sizeof(ps_cpu_total), 1))
				return;

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)ps_cpu_total.user;
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)ps_cpu_total.sys;
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)ps_cpu_total.idle;
			counter[ZBX_CPU_STATE_IOWAIT] = (zbx_uint64_t)ps_cpu_total.wait;
		}
		else
		{
			zbx_snprintf(ps_id.name, sizeof(ps_id.name), "cpu%d", cpu_num - 1);

			if (-1 == perfstat_cpu(&ps_id, &ps_cpu, sizeof(ps_cpu), 1))
				return;

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)ps_cpu.user;
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)ps_cpu.sys;
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)ps_cpu.idle;
			counter[ZBX_CPU_STATE_IOWAIT] = (zbx_uint64_t)ps_cpu.wait;
		}

		update_cpu_counters(&pcpus->cpu[cpu_num], counter);
	}

#endif	/* HAVE_LIBPERFSTAT */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#endif	/* not _WINDOWS */

void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus)
{
#ifdef _WINDOWS

	const char		*__function_name = "collect_cpustat";
	PDH_FMT_COUNTERVALUE	value;
	PDH_STATUS		status;
	int			i, n;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (!pcpus->pdh_query)
		return;

	if (ERROR_SUCCESS != (status = PdhCollectQueryData(pcpus->pdh_query)))
	{
		zabbix_log(LOG_LEVEL_ERR, "call to PdhCollectQueryData() failed: %s",
				strerror_from_module(status, TEXT("PDH.DLL")));
		return;
	}

	for (i = 0; i <= pcpus->count; i++)
	{
		ZBX_SINGLE_CPU_STAT_DATA	*curr_cpu = &pcpus->cpu[i];

		if (!curr_cpu->usage_counter)
			continue;

		PdhGetRawCounterValue(curr_cpu->usage_counter, NULL, &curr_cpu->usage);
		PdhCalculateCounterFromRawValue(curr_cpu->usage_counter, PDH_FMT_LONG,
				&curr_cpu->usage, &curr_cpu->usage_old, &value);
			
		curr_cpu->usage_old = curr_cpu->usage;

		/* calculate average CPU usage */

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

		if (MAX_CPU_HISTORY == ++curr_cpu->h_usage_index)
			curr_cpu->h_usage_index = 0;
	}

	if (pcpus->queue_counter)
	{
		/* process CPU queue length data */

		PdhGetRawCounterValue(pcpus->queue_counter, NULL, &pcpus->queue);
		PdhCalculateCounterFromRawValue(pcpus->queue_counter, PDH_FMT_LONG, &pcpus->queue, NULL, &value);

		/* calculate average processor load */

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

		if (MAX_CPU_HISTORY == ++pcpus->h_queue_index)
			pcpus->h_queue_index = 0;
	}
	
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

#else	/* not _WINDOWS */

	update_cpustats(pcpus);

#endif	/* _WINDOWS */
}

#ifndef _WINDOWS

int	get_cpustat(AGENT_RESULT *result, int cpu_num, int state, int mode)
{
	int				i, time, idx_curr, idx_base;
	zbx_uint64_t			counter, total = 0;
	ZBX_SINGLE_CPU_STAT_DATA	*cpu;

	if (0 > state || state >= ZBX_CPU_STATE_COUNT)
		return SYSINFO_RET_FAIL;

	switch (mode)
	{
		case ZBX_AVG1:
			time = SEC_PER_MIN;
			break;
		case ZBX_AVG5:
			time = 5 * SEC_PER_MIN;
			break;
		case ZBX_AVG15:
			time = 15 * SEC_PER_MIN;
			break;
		default:
			return SYSINFO_RET_FAIL;
	}

	if (!CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_FAIL;
	}

	if (0 > cpu_num || cpu_num > collector->cpus.count)
		return SYSINFO_RET_FAIL;

	cpu = &collector->cpus.cpu[cpu_num];

	if (0 == cpu->h_count)
	{
		SET_DBL_RESULT(result, 0);
		return SYSINFO_RET_OK;
	}

	LOCK_CPUSTATS;

	if (MAX_COLLECTOR_HISTORY <= (idx_curr = (cpu->h_first + cpu->h_count - 1)))
		idx_curr -= MAX_COLLECTOR_HISTORY;

	if (0 > (idx_base = idx_curr - MIN(cpu->h_count - 1, time)))
		idx_base += MAX_COLLECTOR_HISTORY;

	if (1 == cpu->h_count)
	{
		for (i = 0; i < ZBX_CPU_STATE_COUNT; i++)
			total += cpu->h_counter[i][idx_curr];
		counter = cpu->h_counter[state][idx_curr];
	}
	else
	{
		for (i = 0; i < ZBX_CPU_STATE_COUNT; i++)
			total += cpu->h_counter[i][idx_curr] - cpu->h_counter[i][idx_base];
		counter = cpu->h_counter[state][idx_curr] - cpu->h_counter[state][idx_base];
	}

	UNLOCK_CPUSTATS;

	SET_DBL_RESULT(result, 0 == total ? 0 : 100. * (double)counter / (double)total);

	return SYSINFO_RET_OK;
}

#endif	/* not _WINDOWS */
