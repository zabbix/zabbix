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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "stats.h"
#include "cpustat.h"
#ifdef _WINDOWS
#	include "perfstat.h"
#endif
#include "mutexs.h"
#include "log.h"

#if !defined(_WINDOWS)
#	define LOCK_CPUSTATS	zbx_mutex_lock(&cpustats_lock)
#	define UNLOCK_CPUSTATS	zbx_mutex_unlock(&cpustats_lock)
static ZBX_MUTEX	cpustats_lock;
#endif

int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
	const char			*__function_name = "init_cpu_collector";
	int				cpu_num, ret = FAIL;
#ifdef _WINDOWS
	TCHAR				cpu[8];
	char				counterPath[PDH_MAX_COUNTER_PATH];
	PDH_COUNTER_PATH_ELEMENTS	cpe;
#else
#ifdef HAVE_KSTAT_H
	kstat_ctl_t			*kc;
	kstat_t				*k, *kd;
#endif
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#ifdef _WINDOWS
	cpe.szMachineName = NULL;
	cpe.szObjectName = get_counter_name(PCI_PROCESSOR);
	cpe.szInstanceName = cpu;
	cpe.szParentInstance = NULL;
	cpe.dwInstanceIndex = -1;
	cpe.szCounterName = get_counter_name(PCI_PROCESSOR_TIME);

	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)
	{
		if (0 == cpu_num)
			zbx_wsnprintf(cpu, sizeof(cpu) / sizeof(TCHAR), TEXT("_Total"));
		else
			_itow_s(cpu_num - 1, cpu, sizeof(cpu) / sizeof(TCHAR), 10);

		if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__function_name, &cpe, counterPath))
			goto clean;

		if (NULL == (pcpus->cpu_counter[cpu_num] = add_perf_counter(NULL, counterPath, MAX_CPU_HISTORY)))
			goto clean;
	}

	cpe.szObjectName = get_counter_name(PCI_SYSTEM);
	cpe.szInstanceName = NULL;
	cpe.szCounterName = get_counter_name(PCI_PROCESSOR_QUEUE_LENGTH);

	if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__function_name, &cpe, counterPath))
		goto clean;

	if (NULL == (pcpus->queue_counter = add_perf_counter(NULL, counterPath, MAX_CPU_HISTORY)))
		goto clean;

	ret = SUCCEED;
clean:
#else	/* not _WINDOWS */
	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cpustats_lock, ZBX_MUTEX_CPUSTATS))
	{
		zbx_error("unable to create mutex for cpu collector");
		exit(EXIT_FAILURE);
	}

	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)
		pcpus->cpu[cpu_num].cpu_num = cpu_num;

#ifdef HAVE_KSTAT_H
	/* Solaris */

	if (NULL != (kc = kstat_open()))
	{
		if (NULL != (k = kstat_lookup(kc, "unix", 0, "kstat_headers")) && -1 != kstat_read(kc, k, NULL))
		{
			int     i;

			for (i = 0, cpu_num = 1; i < k->ks_ndata; i++)
			{
				kd = (kstat_t *)k->ks_data;
				if (0 == strcmp(kd[i].ks_module, "cpu_info"))
					pcpus->cpu[cpu_num++].cpu_num = kd[i].ks_instance + 1;
			}
		}

		kstat_close(kc);
	}
#endif	/* HAVE_KSTAT_H */

	ret = SUCCEED;
#endif	/* _WINDOWS */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

void	free_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
	const char	*__function_name = "free_cpu_collector";
#ifdef _WINDOWS
	int		i;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#ifdef _WINDOWS
	remove_perf_counter(pcpus->queue_counter);
	pcpus->queue_counter = NULL;

	for (i = 0; i <= pcpus->count; i++)
	{
		remove_perf_counter(pcpus->cpu_counter[i]);
		pcpus->cpu_counter[i] = NULL;
	}
#else
	zbx_mutex_destroy(&cpustats_lock);
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#if !defined(_WINDOWS)

static void	update_cpu_counters(ZBX_SINGLE_CPU_STAT_DATA *cpu, zbx_uint64_t *counter)
{
	int	i, index;

	LOCK_CPUSTATS;

	if (MAX_COLLECTOR_HISTORY <= (index = cpu->h_first + cpu->h_count))
		index -= MAX_COLLECTOR_HISTORY;

	if (MAX_COLLECTOR_HISTORY > cpu->h_count)
		cpu->h_count++;
	else if (MAX_COLLECTOR_HISTORY == ++cpu->h_first)
		cpu->h_first = 0;

	if (NULL != counter)
	{
		for (i = 0; i < ZBX_CPU_STATE_COUNT; i++)
			cpu->h_counter[i][index] = counter[i];

		cpu->h_status[index] = SYSINFO_RET_OK;
	}
	else
		cpu->h_status[index] = SYSINFO_RET_FAIL;

	UNLOCK_CPUSTATS;
}

static void	update_cpustats(ZBX_CPUS_STAT_DATA *pcpus)
{
	const char	*__function_name = "update_cpustats";
	int		cpu_num;
	zbx_uint64_t	counter[ZBX_CPU_STATE_COUNT];

#if defined(HAVE_PROC_STAT)

	FILE		*file;
	char		line[1024];
	unsigned char	*cpu_status = NULL;
	const char	*filename = "/proc/stat";

#elif defined(HAVE_SYS_PSTAT_H)

	struct pst_dynamic	psd;
	struct pst_processor	psp;

#elif defined(HAVE_FUNCTION_SYSCTLBYNAME) && defined(CPUSTATES)

	long	cp_time[CPUSTATES], *cp_times = NULL;
	size_t	nlen, nlen_alloc;

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

#define ZBX_SET_CPUS_NOTSUPPORTED()				\
	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)	\
		update_cpu_counters(&pcpus->cpu[cpu_num], NULL)

#if defined(HAVE_PROC_STAT)

	if (NULL == (file = fopen(filename, "r")))
	{
		zbx_error("cannot open [%s]: %s", filename, zbx_strerror(errno));
		ZBX_SET_CPUS_NOTSUPPORTED();
		goto exit;
	}

	cpu_status = zbx_malloc(cpu_status, sizeof(unsigned char) * (pcpus->count + 1));

	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)
		cpu_status[cpu_num] = SYSINFO_RET_FAIL;

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
		cpu_status[cpu_num] = SYSINFO_RET_OK;
	}
	zbx_fclose(file);

	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)
		if (SYSINFO_RET_FAIL == cpu_status[cpu_num])
			update_cpu_counters(&pcpus->cpu[cpu_num], NULL);

	zbx_free(cpu_status);

#elif defined(HAVE_SYS_PSTAT_H)

	for (cpu_num = 0; cpu_num <= pcpus->count; cpu_num++)
	{
		memset(counter, 0, sizeof(counter));

		if (0 == cpu_num)
		{
			if (-1 == pstat_getdynamic(&psd, sizeof(psd), 1, 0))
			{
				update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)psd.psd_cpu_time[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)psd.psd_cpu_time[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)psd.psd_cpu_time[CP_SYS];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)psd.psd_cpu_time[CP_IDLE];
		}
		else
		{
			if (-1 == pstat_getprocessor(&psp, sizeof(psp), 1, cpu_num - 1))
			{
				update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)psp.psp_cpu_time[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)psp.psp_cpu_time[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)psp.psp_cpu_time[CP_SYS];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)psp.psp_cpu_time[CP_IDLE];
		}

		update_cpu_counters(&pcpus->cpu[cpu_num], counter);
	}

#elif defined(HAVE_FUNCTION_SYSCTLBYNAME) && defined(CPUSTATES)
	/* FreeBSD 7.0 */

	nlen = sizeof(cp_time);
	if (-1 == sysctlbyname("kern.cp_time", &cp_time, &nlen, NULL, 0) || nlen != sizeof(cp_time))
	{
		ZBX_SET_CPUS_NOTSUPPORTED();
		goto exit;
	}

	memset(counter, 0, sizeof(counter));

	counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)cp_time[CP_USER];
	counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)cp_time[CP_NICE];
	counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)cp_time[CP_SYS];
	counter[ZBX_CPU_STATE_INTERRUPT] = (zbx_uint64_t)cp_time[CP_INTR];
	counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)cp_time[CP_IDLE];

	update_cpu_counters(&pcpus->cpu[0], counter);

	/* get size of result set for CPU statistics */
	if (-1 == sysctlbyname("kern.cp_times", NULL, &nlen_alloc, NULL, 0)) {
		for (cpu_num = 1; cpu_num <= pcpus->count; cpu_num++)
			update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
		goto exit;
	}

	cp_times = zbx_malloc(cp_times, nlen_alloc);

	nlen = nlen_alloc;
	if (0 == sysctlbyname("kern.cp_times", cp_times, &nlen, NULL, 0) && nlen == nlen_alloc)
	{
		for (cpu_num = 1; cpu_num <= pcpus->count; cpu_num++)
		{
			memset(counter, 0, sizeof(counter));

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)*(cp_times + (cpu_num - 1) * CPUSTATES + CP_USER);
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)*(cp_times + (cpu_num - 1) * CPUSTATES + CP_NICE);
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)*(cp_times + (cpu_num - 1) * CPUSTATES + CP_SYS);
			counter[ZBX_CPU_STATE_INTERRUPT] = (zbx_uint64_t)*(cp_times + (cpu_num - 1) * CPUSTATES + CP_INTR);
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)*(cp_times + (cpu_num - 1) * CPUSTATES + CP_IDLE);

			update_cpu_counters(&pcpus->cpu[cpu_num], counter);
		}
	}
	else
	{
		for (cpu_num = 1; cpu_num <= pcpus->count; cpu_num++)
			update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
	}

	zbx_free(cp_times);

#elif defined(HAVE_KSTAT_H)
	/* Solaris */

	if (NULL == (kc = kstat_open()))
	{
		ZBX_SET_CPUS_NOTSUPPORTED();
		goto exit;
	}

	memset(total, 0, sizeof(total));

	for (cpu_num = 1; cpu_num <= pcpus->count; cpu_num++)
	{
		if (NULL == (k = kstat_lookup(kc, "cpu_stat", pcpus->cpu[cpu_num].cpu_num - 1, NULL)) ||
				-1 == kstat_read(kc, k, NULL))
		{
			update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
			continue;
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

			if (-1 == sysctl(mib, 2, &all_states, &sz, NULL, 0) || sz != sizeof(all_states))
			{
				update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
				continue;
			}

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

			if (-1 == sysctl(mib, 3, &one_states, &sz, NULL, 0) || sz != sizeof(one_states))
			{
				update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
				continue;
			}

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
			{
				update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)ps_cpu_total.user;
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)ps_cpu_total.sys;
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)ps_cpu_total.idle;
			counter[ZBX_CPU_STATE_IOWAIT] = (zbx_uint64_t)ps_cpu_total.wait;
		}
		else
		{
			zbx_snprintf(ps_id.name, sizeof(ps_id.name), "cpu%d", cpu_num - 1);

			if (-1 == perfstat_cpu(&ps_id, &ps_cpu, sizeof(ps_cpu), 1))
			{
				update_cpu_counters(&pcpus->cpu[cpu_num], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)ps_cpu.user;
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)ps_cpu.sys;
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)ps_cpu.idle;
			counter[ZBX_CPU_STATE_IOWAIT] = (zbx_uint64_t)ps_cpu.wait;
		}

		update_cpu_counters(&pcpus->cpu[cpu_num], counter);
	}

#endif	/* HAVE_LIBPERFSTAT */

#undef ZBX_SET_CPUS_NOTSUPPORTED

exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus)
{
	update_cpustats(pcpus);
}

static ZBX_SINGLE_CPU_STAT_DATA	*get_cpustat_by_num(ZBX_CPUS_STAT_DATA *pcpus, int cpu_num)
{
	int	i;

	for (i = 0; i <= pcpus->count; i++)
		if (pcpus->cpu[i].cpu_num == cpu_num)
			break;

	if (i <= pcpus->count)
		return &pcpus->cpu[i];

	return NULL;
}

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

	if (NULL == (cpu = get_cpustat_by_num(&collector->cpus, cpu_num)))
		return SYSINFO_RET_FAIL;

	if (0 == cpu->h_count)
	{
		SET_DBL_RESULT(result, 0);
		return SYSINFO_RET_OK;
	}

	LOCK_CPUSTATS;

	if (MAX_COLLECTOR_HISTORY <= (idx_curr = (cpu->h_first + cpu->h_count - 1)))
		idx_curr -= MAX_COLLECTOR_HISTORY;

	if (SYSINFO_RET_FAIL == cpu->h_status[idx_curr])
	{
		UNLOCK_CPUSTATS;
		return SYSINFO_RET_FAIL;
	}

	if (1 == cpu->h_count)
	{
		for (i = 0; i < ZBX_CPU_STATE_COUNT; i++)
			total += cpu->h_counter[i][idx_curr];
		counter = cpu->h_counter[state][idx_curr];
	}
	else
	{
		if (0 > (idx_base = idx_curr - MIN(cpu->h_count - 1, time)))
			idx_base += MAX_COLLECTOR_HISTORY;

		while (SYSINFO_RET_OK != cpu->h_status[idx_base])
			if (MAX_COLLECTOR_HISTORY == ++idx_base)
				idx_base -= MAX_COLLECTOR_HISTORY;

		for (i = 0; i < ZBX_CPU_STATE_COUNT; i++)
			total += cpu->h_counter[i][idx_curr] - cpu->h_counter[i][idx_base];
		counter = cpu->h_counter[state][idx_curr] - cpu->h_counter[state][idx_base];
	}

	UNLOCK_CPUSTATS;

	SET_DBL_RESULT(result, 0 == total ? 0 : 100. * (double)counter / (double)total);

	return SYSINFO_RET_OK;
}

#endif	/* not _WINDOWS */
