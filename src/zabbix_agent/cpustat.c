/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "cpustat.h"

#include "common.h"
#include "stats.h"
#ifdef _WINDOWS
#	include "perfstat.h"
/* defined in sysinfo lib */
extern int get_cpu_group_num_win32(void);
extern int get_numa_node_num_win32(void);
#endif
#include "mutexs.h"
#include "log.h"

/* <sys/dkstat.h> removed in OpenBSD 5.7, only <sys/sched.h> with the same CP_* definitions remained */
#if defined(OpenBSD) && defined(HAVE_SYS_SCHED_H) && !defined(HAVE_SYS_DKSTAT_H)
#	include <sys/sched.h>
#endif

#if !defined(_WINDOWS)
#	define LOCK_CPUSTATS	zbx_mutex_lock(cpustats_lock)
#	define UNLOCK_CPUSTATS	zbx_mutex_unlock(cpustats_lock)
static zbx_mutex_t	cpustats_lock = ZBX_MUTEX_NULL;
#else
#	define LOCK_CPUSTATS
#	define UNLOCK_CPUSTATS
#endif

#ifdef HAVE_KSTAT_H
static kstat_ctl_t	*kc = NULL;
static kid_t		kc_id = 0;
static kstat_t		*(*ksp)[] = NULL;	/* array of pointers to "cpu_stat" elements in kstat chain */

static int	refresh_kstat(ZBX_CPUS_STAT_DATA *pcpus)
{
	static int	cpu_over_count_prev = 0;
	int		cpu_over_count = 0, i, inserted;
	kid_t		id;
	kstat_t		*k;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < pcpus->count; i++)
		(*ksp)[i] = NULL;

	/* kstat_chain_update() can return:							*/
	/*   - -1 (error),									*/
	/*   -  a new kstat chain ID (chain successfully updated),				*/
	/*   -  0 (kstat chain was up-to-date). We ignore this case to make refresh_kstat()	*/
	/*        usable for first-time initialization as the kstat chain is up-to-date after	*/
	/*        kstat_open().									*/
	if (-1 == (id = kstat_chain_update(kc)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s: kstat_chain_update() failed", __func__);
		return FAIL;
	}

	if (0 != id)
		kc_id = id;

	for (k = kc->kc_chain; NULL != k; k = k->ks_next)	/* traverse all kstat chain */
	{
		if (0 == strcmp("cpu_stat", k->ks_module))
		{
			inserted = 0;
			for (i = 1; i <= pcpus->count; i++)	/* search in our array of ZBX_SINGLE_CPU_STAT_DATAs */
			{
				if (pcpus->cpu[i].cpu_num == k->ks_instance)	/* CPU instance found */
				{
					(*ksp)[i - 1] = k;
					inserted = 1;

					break;
				}

				if (ZBX_CPUNUM_UNDEF == pcpus->cpu[i].cpu_num)
				{
					/* free slot found, most likely first-time initialization */
					pcpus->cpu[i].cpu_num = k->ks_instance;
					(*ksp)[i - 1] = k;
					inserted = 1;

					break;
				}
			}
			if (0 == inserted)	/* new CPU added, no place to keep its data */
				cpu_over_count++;
		}
	}

	if (0 < cpu_over_count)
	{
		if (cpu_over_count_prev < cpu_over_count)
		{
			zabbix_log(LOG_LEVEL_WARNING, "%d new processor(s) added. Restart Zabbix agentd to enable"
					" collecting new data.", cpu_over_count - cpu_over_count_prev);
			cpu_over_count_prev = cpu_over_count;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return SUCCEED;
}
#endif

int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
	char				*error = NULL;
	int				idx, ret = FAIL;
#ifdef _WINDOWS
	wchar_t				cpu[16]; /* 16 is enough to store instance name string (group and index) */
	char				counterPath[PDH_MAX_COUNTER_PATH];
	PDH_COUNTER_PATH_ELEMENTS	cpe;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

#ifdef _WINDOWS
	cpe.szMachineName = NULL;
	cpe.szObjectName = get_builtin_object_name(PCI_PROCESSOR_TIME);
	cpe.szInstanceName = cpu;
	cpe.szParentInstance = NULL;
	cpe.dwInstanceIndex = (DWORD)-1;
	cpe.szCounterName = get_builtin_counter_name(PCI_PROCESSOR_TIME);

	/* 64 logical CPUs (threads) is a hard limit for 32-bit Windows systems and some old 64-bit versions,  */
	/* such as Windows Vista. Systems with <= 64 threads will always have one processor group, which means */
	/* it's ok to use old performance counter "\Processor(n)\% Processor Time". However, for systems with  */
	/* more than 64 threads Windows distributes them evenly across multiple processor groups with maximum  */
	/* 64 threads per single group. Given that "\Processor(n)" doesn't report values for n >= 64 we need   */
	/* to use "\Processor Information(g, n)" where g is a group number and n is a thread number within     */
	/* the group. So, for 72-thread system there will be two groups with 36 threads each and Windows will  */
	/* report counters "\Processor Information(0, n)" with 0 <= n <= 31 and "\Processor Information(1,n)". */

	if (pcpus->count <= 64)
	{
		for (idx = 0; idx <= pcpus->count; idx++)
		{
			if (0 == idx)
				StringCchPrintf(cpu, ARRSIZE(cpu), L"_Total");
			else
				_itow_s(idx - 1, cpu, ARRSIZE(cpu), 10);

			if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__func__, &cpe, counterPath))
				goto clean;

			if (NULL == (pcpus->cpu_counter[idx] = add_perf_counter(NULL, counterPath, MAX_COLLECTOR_PERIOD,
					PERF_COUNTER_LANG_DEFAULT, &error)))
			{
				goto clean;
			}
		}
	}
	else
	{
		int	gidx, cpu_groups, cpus_per_group, numa_nodes;

		zabbix_log(LOG_LEVEL_DEBUG, "more than 64 CPUs, using \"Processor Information\" counter");

		cpe.szObjectName = get_builtin_object_name(PCI_INFORMATION_PROCESSOR_TIME);
		cpe.szCounterName = get_builtin_counter_name(PCI_INFORMATION_PROCESSOR_TIME);

		/* This doesn't seem to be well documented but it looks like Windows treats Processor Information */
		/* object differently on NUMA-enabled systems. First index for the object may either mean logical */
		/* processor group on non-NUMA systems or NUMA node number when NUMA is available. There may be more */
		/* NUMA nodes than processor groups. */
		numa_nodes = get_numa_node_num_win32();
		cpu_groups = numa_nodes == 1 ? get_cpu_group_num_win32() : numa_nodes;
		cpus_per_group = pcpus->count / cpu_groups;

		zabbix_log(LOG_LEVEL_DEBUG, "cpu_groups = %d, cpus_per_group = %d, cpus = %d", cpu_groups,
				cpus_per_group, pcpus->count);

		for (gidx = 0; gidx < cpu_groups; gidx++)
		{
			for (idx = 0; idx <= cpus_per_group; idx++)
			{
				if (0 == idx)
				{
					if (0 != gidx)
						continue;
					StringCchPrintf(cpu, ARRSIZE(cpu), L"_Total");
				}
				else
				{
					StringCchPrintf(cpu, ARRSIZE(cpu), L"%d,%d", gidx, idx - 1);
				}

				if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__func__, &cpe, counterPath))
					goto clean;

				if (NULL == (pcpus->cpu_counter[gidx * cpus_per_group + idx] =
						add_perf_counter(NULL, counterPath, MAX_COLLECTOR_PERIOD,
								PERF_COUNTER_LANG_DEFAULT, &error)))
				{
					goto clean;
				}
			}
		}
	}

	cpe.szObjectName = get_builtin_object_name(PCI_PROCESSOR_QUEUE_LENGTH);
	cpe.szInstanceName = NULL;
	cpe.szCounterName = get_builtin_counter_name(PCI_PROCESSOR_QUEUE_LENGTH);

	if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__func__, &cpe, counterPath))
		goto clean;

	if (NULL == (pcpus->queue_counter = add_perf_counter(NULL, counterPath, MAX_COLLECTOR_PERIOD,
			PERF_COUNTER_LANG_DEFAULT, &error)))
	{
		goto clean;
	}

	ret = SUCCEED;
clean:
	if (NULL != error)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add performance counter \"%s\": %s", counterPath, error);
		zbx_free(error);
	}

#else	/* not _WINDOWS */
	if (SUCCEED != zbx_mutex_create(&cpustats_lock, ZBX_MUTEX_CPUSTATS, &error))
	{
		zbx_error("unable to create mutex for cpu collector: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	pcpus->cpu[0].cpu_num = ZBX_CPUNUM_ALL;

#ifndef HAVE_KSTAT_H

	for (idx = 1; idx <= pcpus->count; idx++)
		pcpus->cpu[idx].cpu_num = idx - 1;
#else
	/* Solaris */

	/* CPU instance numbers on Solaris can be non-contiguous, we don't know them yet */
	for (idx = 1; idx <= pcpus->count; idx++)
		pcpus->cpu[idx].cpu_num = ZBX_CPUNUM_UNDEF;

	if (NULL == (kc = kstat_open()))
	{
		zbx_error("kstat_open() failed");
		exit(EXIT_FAILURE);
	}

	kc_id = kc->kc_chain_id;

	if (NULL == ksp)
		ksp = zbx_malloc(ksp, sizeof(kstat_t *) * pcpus->count);

	if (SUCCEED != refresh_kstat(pcpus))
	{
		zbx_error("kstat_chain_update() failed");
		exit(EXIT_FAILURE);
	}
#endif	/* HAVE_KSTAT_H */

	ret = SUCCEED;
#endif	/* _WINDOWS */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

void	free_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
#ifdef _WINDOWS
	int	idx;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

#ifdef _WINDOWS
	remove_perf_counter(pcpus->queue_counter);
	pcpus->queue_counter = NULL;

	for (idx = 0; idx <= pcpus->count; idx++)
	{
		remove_perf_counter(pcpus->cpu_counter[idx]);
		pcpus->cpu_counter[idx] = NULL;
	}
#else
	ZBX_UNUSED(pcpus);
	zbx_mutex_destroy(&cpustats_lock);
#endif

#ifdef HAVE_KSTAT_H
	kstat_close(kc);
	zbx_free(ksp);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

#ifdef _WINDOWS
int	get_cpu_perf_counter_value(int cpu_num, int interval, double *value, char **error)
{
	int	idx;

	/* For Windows we identify CPU by its index in cpus array, which is CPU ID + 1. */
	/* At index 0 we keep information about all CPUs. */

	if (ZBX_CPUNUM_ALL == cpu_num)
		idx = 0;
	else
		idx = cpu_num + 1;

	return get_perf_counter_value(collector->cpus.cpu_counter[idx], interval, value, error);
}

static int	get_cpu_perf_counter_status(zbx_perf_counter_status_t pc_status)
{
	switch (pc_status)
	{
		case PERF_COUNTER_ACTIVE:
			return ZBX_CPU_STATUS_ONLINE;
		case PERF_COUNTER_INITIALIZED:
			return ZBX_CPU_STATUS_UNKNOWN;
	}

	return ZBX_CPU_STATUS_OFFLINE;
}
#else	/* not _WINDOWS */
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
	int		idx;
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

	cpu_stat_t	*cpu;
	zbx_uint64_t	total[ZBX_CPU_STATE_COUNT];
	kid_t		id;

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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

#define ZBX_SET_CPUS_NOTSUPPORTED()				\
	for (idx = 0; idx <= pcpus->count; idx++)		\
		update_cpu_counters(&pcpus->cpu[idx], NULL)

#if defined(HAVE_PROC_STAT)

	if (NULL == (file = fopen(filename, "r")))
	{
		zbx_error("cannot open [%s]: %s", filename, zbx_strerror(errno));
		ZBX_SET_CPUS_NOTSUPPORTED();
		goto exit;
	}

	cpu_status = (unsigned char *)zbx_malloc(cpu_status, sizeof(unsigned char) * (pcpus->count + 1));

	for (idx = 0; idx <= pcpus->count; idx++)
		cpu_status[idx] = SYSINFO_RET_FAIL;

	while (NULL != fgets(line, sizeof(line), file))
	{
		if (0 != strncmp(line, "cpu", 3))
			continue;

		if ('0' <= line[3] && line[3] <= '9')
		{
			idx = atoi(line + 3) + 1;
			if (1 > idx || idx > pcpus->count)
				continue;
		}
		else if (' ' == line[3])
			idx = 0;
		else
			continue;

		memset(counter, 0, sizeof(counter));

		sscanf(line, "%*s " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64
				" " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64 " " ZBX_FS_UI64
				" " ZBX_FS_UI64 " " ZBX_FS_UI64,
				&counter[ZBX_CPU_STATE_USER], &counter[ZBX_CPU_STATE_NICE],
				&counter[ZBX_CPU_STATE_SYSTEM], &counter[ZBX_CPU_STATE_IDLE],
				&counter[ZBX_CPU_STATE_IOWAIT], &counter[ZBX_CPU_STATE_INTERRUPT],
				&counter[ZBX_CPU_STATE_SOFTIRQ], &counter[ZBX_CPU_STATE_STEAL],
				&counter[ZBX_CPU_STATE_GCPU], &counter[ZBX_CPU_STATE_GNICE]);

		/* Linux includes guest times in user and nice times */
		counter[ZBX_CPU_STATE_USER] -= counter[ZBX_CPU_STATE_GCPU];
		counter[ZBX_CPU_STATE_NICE] -= counter[ZBX_CPU_STATE_GNICE];

		update_cpu_counters(&pcpus->cpu[idx], counter);
		cpu_status[idx] = SYSINFO_RET_OK;
	}
	zbx_fclose(file);

	for (idx = 0; idx <= pcpus->count; idx++)
	{
		if (SYSINFO_RET_FAIL == cpu_status[idx])
			update_cpu_counters(&pcpus->cpu[idx], NULL);
	}

	zbx_free(cpu_status);

#elif defined(HAVE_SYS_PSTAT_H)

	for (idx = 0; idx <= pcpus->count; idx++)
	{
		memset(counter, 0, sizeof(counter));

		if (0 == idx)
		{
			if (-1 == pstat_getdynamic(&psd, sizeof(psd), 1, 0))
			{
				update_cpu_counters(&pcpus->cpu[idx], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)psd.psd_cpu_time[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)psd.psd_cpu_time[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)psd.psd_cpu_time[CP_SYS];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)psd.psd_cpu_time[CP_IDLE];
		}
		else
		{
			if (-1 == pstat_getprocessor(&psp, sizeof(psp), 1, pcpus->cpu[idx].cpu_num))
			{
				update_cpu_counters(&pcpus->cpu[idx], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)psp.psp_cpu_time[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)psp.psp_cpu_time[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)psp.psp_cpu_time[CP_SYS];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)psp.psp_cpu_time[CP_IDLE];
		}

		update_cpu_counters(&pcpus->cpu[idx], counter);
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
	if (-1 == sysctlbyname("kern.cp_times", NULL, &nlen_alloc, NULL, 0))
	{
		for (idx = 1; idx <= pcpus->count; idx++)
			update_cpu_counters(&pcpus->cpu[idx], NULL);
		goto exit;
	}

	cp_times = zbx_malloc(cp_times, nlen_alloc);

	nlen = nlen_alloc;
	if (0 == sysctlbyname("kern.cp_times", cp_times, &nlen, NULL, 0) && nlen == nlen_alloc)
	{
		for (idx = 1; idx <= pcpus->count; idx++)
		{
			int	cpu_num = pcpus->cpu[idx].cpu_num;

			memset(counter, 0, sizeof(counter));

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)*(cp_times + cpu_num * CPUSTATES + CP_USER);
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)*(cp_times + cpu_num * CPUSTATES + CP_NICE);
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)*(cp_times + cpu_num * CPUSTATES + CP_SYS);
			counter[ZBX_CPU_STATE_INTERRUPT] = (zbx_uint64_t)*(cp_times + cpu_num * CPUSTATES + CP_INTR);
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)*(cp_times + cpu_num * CPUSTATES + CP_IDLE);

			update_cpu_counters(&pcpus->cpu[idx], counter);
		}
	}
	else
	{
		for (idx = 1; idx <= pcpus->count; idx++)
			update_cpu_counters(&pcpus->cpu[idx], NULL);
	}

	zbx_free(cp_times);

#elif defined(HAVE_KSTAT_H)
	/* Solaris */

	if (NULL == kc)
	{
		ZBX_SET_CPUS_NOTSUPPORTED();
		goto exit;
	}

	memset(total, 0, sizeof(total));

	for (idx = 1; idx <= pcpus->count; idx++)
	{
read_again:
		if (NULL != (*ksp)[idx - 1])
		{
			zbx_uint64_t	last_idle, last_user, last_system, last_iowait;

			id = kstat_read(kc, (*ksp)[idx - 1], NULL);
			if (-1 == id || kc_id != id)	/* error or our kstat chain copy is out-of-date */
			{
				if (SUCCEED != refresh_kstat(pcpus))
				{
					update_cpu_counters(&pcpus->cpu[idx], NULL);
					continue;
				}
				else
					goto read_again;
			}

			cpu = (cpu_stat_t *)(*ksp)[idx - 1]->ks_data;

			memset(counter, 0, sizeof(counter));

			/* The cpu counters are stored in 32 bit unsigned integer that can wrap around. */
			/* To account for possible wraparounds instead of storing the counter directly  */
			/* in cache, increment the last stored value by the unsigned 32 bit difference  */
			/* between new value and last value.                                            */
			if (0 != pcpus->cpu[idx].h_count)
			{
				int	index;

				/* only collector can write into cpu history, so for reading */
				/* collector itself can access it without locking            */

				if (MAX_COLLECTOR_HISTORY <= (index = pcpus->cpu[idx].h_first + pcpus->cpu[idx].h_count - 1))
					index -= MAX_COLLECTOR_HISTORY;

				last_idle = pcpus->cpu[idx].h_counter[ZBX_CPU_STATE_IDLE][index];
				last_user = pcpus->cpu[idx].h_counter[ZBX_CPU_STATE_USER][index];
				last_system = pcpus->cpu[idx].h_counter[ZBX_CPU_STATE_SYSTEM][index];
				last_iowait = pcpus->cpu[idx].h_counter[ZBX_CPU_STATE_IOWAIT][index];
			}
			else
			{
				last_idle = 0;
				last_user = 0;
				last_system = 0;
				last_iowait = 0;
			}

			counter[ZBX_CPU_STATE_IDLE] = cpu->cpu_sysinfo.cpu[CPU_IDLE] - (zbx_uint32_t)last_idle +
					last_idle;
			counter[ZBX_CPU_STATE_USER] = cpu->cpu_sysinfo.cpu[CPU_USER] - (zbx_uint32_t)last_user +
					last_user;
			counter[ZBX_CPU_STATE_SYSTEM] = cpu->cpu_sysinfo.cpu[CPU_KERNEL] - (zbx_uint32_t)last_system +
					last_system;
			counter[ZBX_CPU_STATE_IOWAIT] = cpu->cpu_sysinfo.cpu[CPU_WAIT] - (zbx_uint32_t)last_iowait +
					last_iowait;

			total[ZBX_CPU_STATE_IDLE] += counter[ZBX_CPU_STATE_IDLE];
			total[ZBX_CPU_STATE_USER] += counter[ZBX_CPU_STATE_USER];
			total[ZBX_CPU_STATE_SYSTEM] += counter[ZBX_CPU_STATE_SYSTEM];
			total[ZBX_CPU_STATE_IOWAIT] += counter[ZBX_CPU_STATE_IOWAIT];

			update_cpu_counters(&pcpus->cpu[idx], counter);
		}
		else
			update_cpu_counters(&pcpus->cpu[idx], NULL);
	}

	update_cpu_counters(&pcpus->cpu[0], total);

#elif defined(HAVE_FUNCTION_SYSCTL_KERN_CPTIME)
	/* OpenBSD 4.3 */

	for (idx = 0; idx <= pcpus->count; idx++)
	{
		memset(counter, 0, sizeof(counter));

		if (0 == idx)
		{
			mib[0] = CTL_KERN;
			mib[1] = KERN_CPTIME;

			sz = sizeof(all_states);

			if (-1 == sysctl(mib, 2, &all_states, &sz, NULL, 0) || sz != sizeof(all_states))
			{
				update_cpu_counters(&pcpus->cpu[idx], NULL);
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
			mib[2] = pcpus->cpu[idx].cpu_num;

			sz = sizeof(one_states);

			if (-1 == sysctl(mib, 3, &one_states, &sz, NULL, 0) || sz != sizeof(one_states))
			{
				update_cpu_counters(&pcpus->cpu[idx], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)one_states[CP_USER];
			counter[ZBX_CPU_STATE_NICE] = (zbx_uint64_t)one_states[CP_NICE];
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)one_states[CP_SYS];
			counter[ZBX_CPU_STATE_INTERRUPT] = (zbx_uint64_t)one_states[CP_INTR];
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)one_states[CP_IDLE];
		}

		update_cpu_counters(&pcpus->cpu[idx], counter);
	}

#elif defined(HAVE_LIBPERFSTAT)
	/* AIX 6.1 */

	for (idx = 0; idx <= pcpus->count; idx++)
	{
		memset(counter, 0, sizeof(counter));

		if (0 == idx)
		{
			if (-1 == perfstat_cpu_total(NULL, &ps_cpu_total, sizeof(ps_cpu_total), 1))
			{
				update_cpu_counters(&pcpus->cpu[idx], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)ps_cpu_total.user;
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)ps_cpu_total.sys;
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)ps_cpu_total.idle;
			counter[ZBX_CPU_STATE_IOWAIT] = (zbx_uint64_t)ps_cpu_total.wait;
		}
		else
		{
			zbx_snprintf(ps_id.name, sizeof(ps_id.name), "cpu%d", pcpus->cpu[idx].cpu_num);

			/* perfstat_cpu can return -1 for error or 0 when no data is copied */
			if (1 != perfstat_cpu(&ps_id, &ps_cpu, sizeof(ps_cpu), 1))
			{
				update_cpu_counters(&pcpus->cpu[idx], NULL);
				continue;
			}

			counter[ZBX_CPU_STATE_USER] = (zbx_uint64_t)ps_cpu.user;
			counter[ZBX_CPU_STATE_SYSTEM] = (zbx_uint64_t)ps_cpu.sys;
			counter[ZBX_CPU_STATE_IDLE] = (zbx_uint64_t)ps_cpu.idle;
			counter[ZBX_CPU_STATE_IOWAIT] = (zbx_uint64_t)ps_cpu.wait;
		}

		update_cpu_counters(&pcpus->cpu[idx], counter);
	}

#endif	/* HAVE_LIBPERFSTAT */

#undef ZBX_SET_CPUS_NOTSUPPORTED
#if defined(HAVE_PROC_STAT) || (defined(HAVE_FUNCTION_SYSCTLBYNAME) && defined(CPUSTATES)) || defined(HAVE_KSTAT_H)
exit:
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus)
{
	update_cpustats(pcpus);
}

#if defined(HAVE_LIBPERFSTAT)
static ZBX_CPU_UTIL_PCT_AIX	*increment_address_in_collector(ZBX_CPUS_UTIL_DATA_AIX *p)
{
	if (0 != p->h_count && p->row_num == ++p->h_latest)
		p->h_latest = 0;

	if (p->row_num > p->h_count)
		p->h_count++;

	return p->counters + p->h_latest * p->column_num;
}

/* ZBX_PCT_MULTIPLIER value has been chosen to not lose precision (see FLT_EPSILON) and on the other hand */
/* ensure enough time before counter wrap around ( > 500 years of updating with 100% every second) */
#define ZBX_PCT_MULTIPLIER	10000000

static zbx_uint64_t	convert_pct_to_uint64(float pct)
{
	return (zbx_uint64_t)(pct * (float)ZBX_PCT_MULTIPLIER);
}

static double	convert_uint64_to_pct(zbx_uint64_t num)
{
	return (double)num / (double)ZBX_PCT_MULTIPLIER;
}

#undef ZBX_PCT_MULTIPLIER

static void	insert_phys_util_into_collector(ZBX_CPUS_UTIL_DATA_AIX *cpus_phys_util,
		const ZBX_CPU_UTIL_PCT_AIX *util_data, int util_data_count)
{
	ZBX_CPU_UTIL_PCT_AIX	*p;
	int			i;

	LOCK_CPUSTATS;

	p = increment_address_in_collector(cpus_phys_util);

	if (1 == cpus_phys_util->h_count)	/* initial data element */
	{
		for (i = 0; i < util_data_count; i++)
		{
			p->status = util_data[i].status;
			p->user_pct = util_data[i].user_pct;
			p->kern_pct = util_data[i].kern_pct;
			p->idle_pct = util_data[i].idle_pct;
			p->wait_pct = util_data[i].wait_pct;
			p++;
		}

		for (i = util_data_count; i < cpus_phys_util->column_num; i++)
		{
			p->status = SYSINFO_RET_FAIL;
			p++;
		}
	}
	else
	{
		/* index of previous data element */
		int	prev_idx = (cpus_phys_util->h_latest > 0) ?
				cpus_phys_util->h_latest - 1 : cpus_phys_util->row_num - 1;

		/* pointer to previous data element */
		ZBX_CPU_UTIL_PCT_AIX	*prev = cpus_phys_util->counters + prev_idx * cpus_phys_util->column_num;

		for (i = 0; i < util_data_count; i++)
		{
			p->status = util_data[i].status;
			p->user_pct = prev->user_pct + util_data[i].user_pct;
			p->kern_pct = prev->kern_pct + util_data[i].kern_pct;
			p->idle_pct = prev->idle_pct + util_data[i].idle_pct;
			p->wait_pct = prev->wait_pct + util_data[i].wait_pct;
			p++;
			prev++;
		}

		for (i = util_data_count; i < cpus_phys_util->column_num; i++)
		{
			p->status = SYSINFO_RET_FAIL;
			p++;
		}
	}

	UNLOCK_CPUSTATS;
}

static void	insert_error_status_into_collector(ZBX_CPUS_UTIL_DATA_AIX *cpus_phys_util, int cpu_start_nr,
		int cpu_end_nr)
{
	ZBX_CPU_UTIL_PCT_AIX	*p;
	int			i;

	LOCK_CPUSTATS;

	p = increment_address_in_collector(cpus_phys_util);

	for (i = cpu_start_nr; i <= cpu_end_nr; i++)
		(p + i)->status = SYSINFO_RET_FAIL;

	UNLOCK_CPUSTATS;
}

static void	update_cpustats_physical(ZBX_CPUS_UTIL_DATA_AIX *cpus_phys_util)
{
	static int			initialized = 0, old_cpu_count, old_stats_count;
	static perfstat_cpu_total_t	old_cpu_total;
	static perfstat_cpu_t		*old_cpu_stats = NULL, *new_cpu_stats = NULL, *tmp_cpu_stats;
	static perfstat_id_t		cpu_id;
	static perfstat_cpu_util_t	*cpu_util = NULL;
	static ZBX_CPU_UTIL_PCT_AIX	*util_data = NULL;	/* array for passing utilization data into collector */
	/* maximum number of CPUs the collector has been configured to handle */
	int				max_cpu_count = cpus_phys_util->column_num - 1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != initialized)
	{
		perfstat_cpu_total_t	new_cpu_total;
		perfstat_rawdata_t	rawdata;
		int			new_cpu_count, new_stats_count, i, count_changed = 0;

		/* get total utilization for all CPUs */

		if (-1 == perfstat_cpu_total(NULL, &new_cpu_total, sizeof(perfstat_cpu_total_t), 1))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): perfstat_cpu_total() failed: %s", __func__,
					zbx_strerror(errno));
			insert_error_status_into_collector(cpus_phys_util, 0, max_cpu_count);
			goto exit;
		}

		rawdata.type = UTIL_CPU_TOTAL;
		rawdata.prevstat = &old_cpu_total;
		rawdata.curstat = &new_cpu_total;
		rawdata.sizeof_data = sizeof(perfstat_cpu_total_t);
		rawdata.prev_elems = 1;
		rawdata.cur_elems = 1;

		if (-1 == perfstat_cpu_util(&rawdata, cpu_util, sizeof(perfstat_cpu_util_t), 1))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): perfstat_cpu_util() failed: %s", __func__,
					zbx_strerror(errno));
			insert_error_status_into_collector(cpus_phys_util, 0, max_cpu_count);
			goto exit;
		}

		util_data[0].status = SYSINFO_RET_OK;
		util_data[0].user_pct = convert_pct_to_uint64(cpu_util[0].user_pct);
		util_data[0].kern_pct = convert_pct_to_uint64(cpu_util[0].kern_pct);
		util_data[0].idle_pct = convert_pct_to_uint64(cpu_util[0].idle_pct);
		util_data[0].wait_pct = convert_pct_to_uint64(cpu_util[0].wait_pct);

		/* get utilization for individual CPUs in one batch */

		if (-1 == (new_cpu_count = perfstat_cpu(NULL, NULL, sizeof(perfstat_cpu_t), 0)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): perfstat_cpu() failed: %s", __func__,
					zbx_strerror(errno));
			insert_error_status_into_collector(cpus_phys_util, 0, max_cpu_count);
			goto exit;
		}

		if (max_cpu_count < new_cpu_count)
		{
			zbx_error("number of CPUs has increased. Restart agent to adjust configuration.");
			exit(EXIT_FAILURE);
		}

		if (old_cpu_count != new_cpu_count)
		{
			old_cpu_count = new_cpu_count;
			zabbix_log(LOG_LEVEL_WARNING, "number of CPUs has changed from %d to %d,"
					" skipping this measurement.", old_cpu_count, new_cpu_count);
			insert_error_status_into_collector(cpus_phys_util, 0, max_cpu_count);
			count_changed = 1;
		}

		zbx_strlcpy(cpu_id.name, FIRST_CPU, sizeof(cpu_id.name));

		if (-1 == (new_stats_count = perfstat_cpu(&cpu_id, new_cpu_stats, sizeof(perfstat_cpu_t),
				max_cpu_count)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): perfstat_cpu() failed: %s", __func__,
					zbx_strerror(errno));
			insert_error_status_into_collector(cpus_phys_util, 0, max_cpu_count);
			goto exit;
		}

		if (old_stats_count != new_stats_count)
		{
			old_stats_count = new_stats_count;
			zabbix_log(LOG_LEVEL_WARNING, "number of CPU statistics has changed from %d to %d,"
					" skipping this measurement.", old_stats_count, new_stats_count);
			insert_error_status_into_collector(cpus_phys_util, 0, max_cpu_count);
			count_changed = 1;
		}

		if (0 == count_changed)
		{
			rawdata.type = UTIL_CPU;
			rawdata.prevstat = old_cpu_stats;
			rawdata.curstat = new_cpu_stats;
			rawdata.sizeof_data = sizeof(perfstat_cpu_t);
			rawdata.prev_elems = old_stats_count;
			rawdata.cur_elems = new_stats_count;

			if (-1 == perfstat_cpu_util(&rawdata, cpu_util, sizeof(perfstat_cpu_util_t), new_stats_count))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): perfstat_cpu_util() failed: %s", __func__,
						zbx_strerror(errno));
				insert_error_status_into_collector(cpus_phys_util, 0, max_cpu_count);
				goto copy_to_old;
			}

			for (i = 0; i < new_stats_count; i++)
			{
				util_data[i + 1].status = SYSINFO_RET_OK;

				/* It was observed that perfstat_cpu_util() can return 'NaNQ' as percents */
				/* of utilization and physical counters do not change in this case. */

				if (0 == isnan(cpu_util[i].user_pct) && 0 == isnan(cpu_util[i].kern_pct) &&
						0 == isnan(cpu_util[i].idle_pct) && 0 == isnan(cpu_util[i].wait_pct))
				{
					util_data[i + 1].user_pct = convert_pct_to_uint64(cpu_util[i].user_pct);
					util_data[i + 1].kern_pct = convert_pct_to_uint64(cpu_util[i].kern_pct);
					util_data[i + 1].idle_pct = convert_pct_to_uint64(cpu_util[i].idle_pct);
					util_data[i + 1].wait_pct = convert_pct_to_uint64(cpu_util[i].wait_pct);
				}
				else if (old_cpu_stats[i].puser == new_cpu_stats[i].puser &&
						old_cpu_stats[i].psys == new_cpu_stats[i].psys &&
						old_cpu_stats[i].pidle == new_cpu_stats[i].pidle &&
						old_cpu_stats[i].pwait == new_cpu_stats[i].pwait)
				{
					util_data[i + 1].user_pct = convert_pct_to_uint64(0);
					util_data[i + 1].kern_pct = convert_pct_to_uint64(0);
					util_data[i + 1].idle_pct = convert_pct_to_uint64(100);
					util_data[i + 1].wait_pct = convert_pct_to_uint64(0);
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "%s(): unexpected case:"
							" i=%d name=%s puser=%llu psys=%llu pidle=%llu pwait=%llu"
							" user_pct=%f kern_pct=%f idle_pct=%f wait_pct=%f",
							__func__, i, new_cpu_stats[i].name,
							new_cpu_stats[i].puser, new_cpu_stats[i].psys,
							new_cpu_stats[i].pidle, new_cpu_stats[i].pwait,
							cpu_util[i].user_pct, cpu_util[i].kern_pct,
							cpu_util[i].idle_pct, cpu_util[i].wait_pct);
					insert_error_status_into_collector(cpus_phys_util, 0, max_cpu_count);
					goto copy_to_old;
				}
			}

			insert_phys_util_into_collector(cpus_phys_util, util_data, new_stats_count + 1);
		}
copy_to_old:
		old_cpu_total = new_cpu_total;

		/* swap pointers to old and new data to avoid copying from new to old */
		tmp_cpu_stats = old_cpu_stats;
		old_cpu_stats = new_cpu_stats;
		new_cpu_stats = tmp_cpu_stats;
	}
	else	/* the first call */
	{
		if (-1 == perfstat_cpu_total(NULL, &old_cpu_total, sizeof(perfstat_cpu_total_t), 1))
		{
			zbx_error("the first call of perfstat_cpu_total() failed: %s", zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}

		if (-1 == (old_cpu_count = perfstat_cpu(NULL, NULL, sizeof(perfstat_cpu_t), 0)))
		{
			zbx_error("the first call of perfstat_cpu() failed: %s", zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}

		if (max_cpu_count < old_cpu_count)
		{
			zbx_error("number of CPUs has increased. Restart agent to adjust configuration.");
			exit(EXIT_FAILURE);
		}

		old_cpu_stats = (perfstat_cpu_t *)zbx_calloc(old_cpu_stats, max_cpu_count, sizeof(perfstat_cpu_t));
		new_cpu_stats = (perfstat_cpu_t *)zbx_calloc(new_cpu_stats, max_cpu_count, sizeof(perfstat_cpu_t));
		cpu_util = (perfstat_cpu_util_t *)zbx_calloc(cpu_util, max_cpu_count, sizeof(perfstat_cpu_util_t));
		util_data = (ZBX_CPU_UTIL_PCT_AIX *)zbx_malloc(util_data,
				sizeof(ZBX_CPU_UTIL_PCT_AIX) * (max_cpu_count + 1));
		zbx_strlcpy(cpu_id.name, FIRST_CPU, sizeof(cpu_id.name));

		if (-1 == (old_stats_count = perfstat_cpu(&cpu_id, old_cpu_stats, sizeof(perfstat_cpu_t),
				max_cpu_count)))
		{
			zbx_error("perfstat_cpu() for getting all CPU statistics failed: %s", zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}

		initialized = 1;
	}
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	collect_cpustat_physical(ZBX_CPUS_UTIL_DATA_AIX *cpus_phys_util)
{
	update_cpustats_physical(cpus_phys_util);
}
#endif

static ZBX_SINGLE_CPU_STAT_DATA	*get_cpustat_by_num(ZBX_CPUS_STAT_DATA *pcpus, int cpu_num)
{
	int	idx;

	for (idx = 0; idx <= pcpus->count; idx++)
	{
		if (pcpus->cpu[idx].cpu_num == cpu_num)
			return &pcpus->cpu[idx];
	}

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

	if (0 == CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (cpu = get_cpustat_by_num(&collector->cpus, cpu_num)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain CPU information."));
		return SYSINFO_RET_FAIL;
	}

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
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain CPU information."));
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
		{
			if (cpu->h_counter[i][idx_curr] > cpu->h_counter[i][idx_base])
				total += cpu->h_counter[i][idx_curr] - cpu->h_counter[i][idx_base];
		}

		/* current counter might be less than previous due to guest time sometimes not being fully included */
		/* in user time by "/proc/stat" */
		if (cpu->h_counter[state][idx_curr] > cpu->h_counter[state][idx_base])
			counter = cpu->h_counter[state][idx_curr] - cpu->h_counter[state][idx_base];
		else
			counter = 0;
	}

	UNLOCK_CPUSTATS;

	SET_DBL_RESULT(result, 0 == total ? 0 : 100. * (double)counter / (double)total);

	return SYSINFO_RET_OK;
}

#ifdef _AIX
int	get_cpustat_physical(AGENT_RESULT *result, int cpu_num, int state, int mode)
{
	ZBX_CPUS_UTIL_DATA_AIX	*p = &collector->cpus_phys_util;
	int			time_interval, offset;

	if (ZBX_CPUNUM_ALL != cpu_num && p->column_num - 2 < cpu_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain CPU information."));
		return SYSINFO_RET_FAIL;
	}

	switch (mode)
	{
		case ZBX_AVG1:
			time_interval = SEC_PER_MIN;
			break;
		case ZBX_AVG5:
			time_interval = 5 * SEC_PER_MIN;
			break;
		case ZBX_AVG15:
			time_interval = 15 * SEC_PER_MIN;
			break;
		default:
			return SYSINFO_RET_FAIL;
	}

	if (0 == CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	if (0 == p->h_count)
	{
		SET_DBL_RESULT(result, 0);
		return SYSINFO_RET_OK;
	}

	LOCK_CPUSTATS;

	if (ZBX_CPUNUM_ALL == cpu_num)
		offset = p->h_latest * p->column_num;	/* total for all CPUs is in column 0 */
	else
		offset = p->h_latest * p->column_num + cpu_num + 1;

	if (SYSINFO_RET_FAIL == p->counters[offset].status)
	{
		UNLOCK_CPUSTATS;
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain CPU information."));
		return SYSINFO_RET_FAIL;
	}

	if (1 == p->h_count)
	{
		switch (state)
		{
			case ZBX_CPU_STATE_USER:
				SET_DBL_RESULT(result, convert_uint64_to_pct(p->counters[offset].user_pct));
				break;
			case ZBX_CPU_STATE_SYSTEM:
				SET_DBL_RESULT(result, convert_uint64_to_pct(p->counters[offset].kern_pct));
				break;
			case ZBX_CPU_STATE_IDLE:
				SET_DBL_RESULT(result, convert_uint64_to_pct(p->counters[offset].idle_pct));
				break;
			case ZBX_CPU_STATE_IOWAIT:
				SET_DBL_RESULT(result, convert_uint64_to_pct(p->counters[offset].wait_pct));
				break;
			default:
				UNLOCK_CPUSTATS;
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Statistics for invalid CPU state requested."));
				return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		int	prev_idx, prev_offset;

		if (p->h_count - 1 < time_interval)	/* less data than averaging interval */
			time_interval = p->h_count - 1;

		/* index of data element a time interval back */
		prev_idx = (p->h_latest >= time_interval) ? p->h_latest - time_interval :
				p->h_latest - time_interval + p->row_num;

		/* offset to data element a time interval back */
		if (ZBX_CPUNUM_ALL == cpu_num)
			prev_offset = prev_idx * p->column_num;
		else
			prev_offset = prev_idx * p->column_num + cpu_num + 1;

		if (SYSINFO_RET_FAIL == p->counters[prev_offset].status)
		{
			UNLOCK_CPUSTATS;
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain CPU information."));
			return SYSINFO_RET_FAIL;
		}

		switch (state)
		{
			case ZBX_CPU_STATE_USER:
				SET_DBL_RESULT(result, convert_uint64_to_pct(p->counters[offset].user_pct -
						p->counters[prev_offset].user_pct) / time_interval);
				break;
			case ZBX_CPU_STATE_SYSTEM:
				SET_DBL_RESULT(result, convert_uint64_to_pct(p->counters[offset].kern_pct -
						p->counters[prev_offset].kern_pct) / time_interval);
				break;
			case ZBX_CPU_STATE_IDLE:
				SET_DBL_RESULT(result, convert_uint64_to_pct(p->counters[offset].idle_pct -
						p->counters[prev_offset].idle_pct) / time_interval);
				break;
			case ZBX_CPU_STATE_IOWAIT:
				SET_DBL_RESULT(result, convert_uint64_to_pct(p->counters[offset].wait_pct -
						p->counters[prev_offset].wait_pct) / time_interval);
				break;
			default:
				UNLOCK_CPUSTATS;
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Statistics for invalid CPU state requested."));
				return SYSINFO_RET_FAIL;
		}
	}

	UNLOCK_CPUSTATS;

	return SYSINFO_RET_OK;
}
#endif

static int	get_cpu_status(int pc_status)
{
	if (SYSINFO_RET_OK == pc_status)
		return ZBX_CPU_STATUS_ONLINE;

	return ZBX_CPU_STATUS_OFFLINE;
}
#endif	/* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieve list of available CPUs in the collector                  *
 *                                                                            *
 * Parameters: vector [OUT] - vector for CPUNUM/STATUS pairs                  *
 *                                                                            *
 * Return value: SUCCEED if collector started and has at least one CPU        *
 *               FAIL otherwise                                               *
 *                                                                            *
 * Comments: The data returned is designed for item system.cpu.discovery      *
 *                                                                            *
 ******************************************************************************/
int	get_cpus(zbx_vector_uint64_pair_t *vector)
{
	ZBX_CPUS_STAT_DATA	*pcpus;
	int			idx, ret = FAIL;

	if (!CPU_COLLECTOR_STARTED(collector) || NULL == (pcpus = &collector->cpus))
		goto out;

	LOCK_CPUSTATS;

	/* Per-CPU information is stored in the ZBX_SINGLE_CPU_STAT_DATA array */
	/* starting with index 1. Index 0 contains information about all CPUs. */

	for (idx = 1; idx <= pcpus->count; idx++)
	{
		zbx_uint64_pair_t		pair;
#ifndef _WINDOWS
		ZBX_SINGLE_CPU_STAT_DATA	*cpu;
		int				index;

		cpu = &pcpus->cpu[idx];

		if (MAX_COLLECTOR_HISTORY <= (index = cpu->h_first + cpu->h_count - 1))
			index -= MAX_COLLECTOR_HISTORY;

		pair.first = cpu->cpu_num;
		pair.second = get_cpu_status(cpu->h_status[index]);
#else
		pair.first = idx - 1;
		pair.second = get_cpu_perf_counter_status(pcpus->cpu_counter[idx]->status);
#endif
		zbx_vector_uint64_pair_append(vector, pair);
	}

	UNLOCK_CPUSTATS;

	ret = SUCCEED;
out:
	return ret;
}
