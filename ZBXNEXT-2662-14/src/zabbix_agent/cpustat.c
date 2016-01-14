/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include "common.h"
#include "stats.h"
#include "cpustat.h"
#ifdef _WINDOWS
#	include "perfstat.h"
#endif
#include "mutexs.h"
#include "log.h"

/* <sys/dkstat.h> removed in OpenBSD 5.7, only <sys/sched.h> with the same CP_* definitions remained */
#if defined(OpenBSD) && defined(HAVE_SYS_SCHED_H) && !defined(HAVE_SYS_DKSTAT_H)
#	include <sys/sched.h>
#endif

#if !defined(_WINDOWS)
#	define LOCK_CPUSTATS	zbx_mutex_lock(&cpustats_lock)
#	define UNLOCK_CPUSTATS	zbx_mutex_unlock(&cpustats_lock)
static ZBX_MUTEX	cpustats_lock = ZBX_MUTEX_NULL;
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
	const char	*__function_name = "refresh_kstat";
	static int	cpu_over_count_prev = 0;
	int		cpu_over_count = 0, i, inserted;
	kid_t		id;
	kstat_t		*k;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
		zabbix_log(LOG_LEVEL_ERR, "%s: kstat_chain_update() failed", __function_name);
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}
#endif

int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
	const char			*__function_name = "init_cpu_collector";
	int				idx, ret = FAIL;
#ifdef _WINDOWS
	wchar_t				cpu[8];
	char				counterPath[PDH_MAX_COUNTER_PATH], *error = NULL;
	PDH_COUNTER_PATH_ELEMENTS	cpe;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#ifdef _WINDOWS
	cpe.szMachineName = NULL;
	cpe.szObjectName = get_counter_name(PCI_PROCESSOR);
	cpe.szInstanceName = cpu;
	cpe.szParentInstance = NULL;
	cpe.dwInstanceIndex = -1;
	cpe.szCounterName = get_counter_name(PCI_PROCESSOR_TIME);

	for (idx = 0; idx <= pcpus->count; idx++)
	{
		if (0 == idx)
			StringCchPrintf(cpu, ARRSIZE(cpu), TEXT("_Total"));
		else
			_itow_s(idx - 1, cpu, ARRSIZE(cpu), 10);

		if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__function_name, &cpe, counterPath))
			goto clean;

		if (NULL == (pcpus->cpu_counter[idx] = add_perf_counter(NULL, counterPath, MAX_COLLECTOR_PERIOD,
				&error)))
		{
			goto clean;
		}
	}

	cpe.szObjectName = get_counter_name(PCI_SYSTEM);
	cpe.szInstanceName = NULL;
	cpe.szCounterName = get_counter_name(PCI_PROCESSOR_QUEUE_LENGTH);

	if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__function_name, &cpe, counterPath))
		goto clean;

	if (NULL == (pcpus->queue_counter = add_perf_counter(NULL, counterPath, MAX_COLLECTOR_PERIOD, &error)))
		goto clean;

	ret = SUCCEED;
clean:
	if (NULL != error)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add performance counter \"%s\": %s", counterPath, error);
		zbx_free(error);
	}

#else	/* not _WINDOWS */
	if (FAIL == zbx_mutex_create_force(&cpustats_lock, ZBX_MUTEX_CPUSTATS))
	{
		zbx_error("unable to create mutex for cpu collector");
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

void	free_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus)
{
	const char	*__function_name = "free_cpu_collector";
#ifdef _WINDOWS
	int		idx;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#ifdef _WINDOWS
	remove_perf_counter(pcpus->queue_counter);
	pcpus->queue_counter = NULL;

	for (idx = 0; idx <= pcpus->count; idx++)
	{
		remove_perf_counter(pcpus->cpu_counter[idx]);
		pcpus->cpu_counter[idx] = NULL;
	}
#else
	zbx_mutex_destroy(&cpustats_lock);
#endif

#ifdef HAVE_KSTAT_H
	kstat_close(kc);
	zbx_free(ksp);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#ifdef _WINDOWS
int	get_cpu_perf_counter_value(int cpu_num, int interval, double *value, char **error)
{
	int	idx;

	/* For Windows we identify CPU by it's index in cpus array, which is CPU ID + 1. */
	/* At index 0 we keep information about all CPUs. */

	if (ZBX_CPUNUM_ALL == cpu_num)
		idx = 0;
	else
		idx = cpu_num + 1;

	return get_perf_counter_value(collector->cpus.cpu_counter[idx], interval, value, error);
}

static int	get_cpu_perf_counter_status(int pc_status)
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
	const char	*__function_name = "update_cpustats";
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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

	cpu_status = zbx_malloc(cpu_status, sizeof(unsigned char) * (pcpus->count + 1));

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

			total[ZBX_CPU_STATE_IDLE] += counter[ZBX_CPU_STATE_IDLE] = cpu->cpu_sysinfo.cpu[CPU_IDLE];
			total[ZBX_CPU_STATE_USER] += counter[ZBX_CPU_STATE_USER] = cpu->cpu_sysinfo.cpu[CPU_USER];
			total[ZBX_CPU_STATE_SYSTEM] += counter[ZBX_CPU_STATE_SYSTEM] = cpu->cpu_sysinfo.cpu[CPU_KERNEL];
			total[ZBX_CPU_STATE_IOWAIT] += counter[ZBX_CPU_STATE_IOWAIT] = cpu->cpu_sysinfo.cpu[CPU_WAIT];

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

			if (-1 == perfstat_cpu(&ps_id, &ps_cpu, sizeof(ps_cpu), 1))
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus)
{
	update_cpustats(pcpus);
}

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
			total += cpu->h_counter[i][idx_curr] - cpu->h_counter[i][idx_base];
		counter = cpu->h_counter[state][idx_curr] - cpu->h_counter[state][idx_base];
	}

	UNLOCK_CPUSTATS;

	SET_DBL_RESULT(result, 0 == total ? 0 : 100. * (double)counter / (double)total);

	return SYSINFO_RET_OK;
}

static int	get_cpu_status(int pc_status)
{
	if (SYSINFO_RET_OK == pc_status)
		return ZBX_CPU_STATUS_ONLINE;

	return ZBX_CPU_STATUS_OFFLINE;
}
#endif	/* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: get_cpus                                                         *
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
