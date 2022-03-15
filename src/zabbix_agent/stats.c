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

#include "stats.h"

#include "log.h"

#include "mutexs.h"

#ifdef _WINDOWS
#	include "service.h"
#	include "perfstat.h"
/* defined in sysinfo lib */
extern int get_cpu_num_win32(void);
#else
#	include "daemon.h"
#endif

ZBX_COLLECTOR_DATA	*collector = NULL;

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

#ifndef _WINDOWS
static int		shm_id;
int 			my_diskstat_shmid = ZBX_NONEXISTENT_SHMID;
ZBX_DISKDEVICES_DATA	*diskdevices = NULL;
zbx_mutex_t		diskstats_lock = ZBX_MUTEX_NULL;
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: returns the number of processors which are currently online       *
 *          (i.e., available).                                                *
 *                                                                            *
 * Return value: number of CPUs                                               *
 *                                                                            *
 ******************************************************************************/
static int	zbx_get_cpu_num(void)
{
#if defined(_WINDOWS)
	return get_cpu_num_win32();
#elif defined(HAVE_SYS_PSTAT_H)
	struct pst_dynamic	psd;

	if (-1 == pstat_getdynamic(&psd, sizeof(struct pst_dynamic), 1, 0))
		goto return_one;

	return (int)psd.psd_proc_cnt;
#elif defined(_SC_NPROCESSORS_CONF)
	/* FreeBSD 7.0 x86 */
	/* Solaris 10 x86 */
	/* AIX 6.1 */
	int	ncpu;

	if (-1 == (ncpu = sysconf(_SC_NPROCESSORS_CONF)))
		goto return_one;

	return ncpu;
#elif defined(HAVE_FUNCTION_SYSCTL_HW_NCPU)
	/* FreeBSD 6.2 x86; FreeBSD 7.0 x86 */
	/* NetBSD 3.1 x86; NetBSD 4.0 x86 */
	/* OpenBSD 4.2 x86 */
	size_t	len;
	int	mib[] = {CTL_HW, HW_NCPU}, ncpu;

	len = sizeof(ncpu);

	if (0 != sysctl(mib, 2, &ncpu, &len, NULL, 0))
		goto return_one;

	return ncpu;
#elif defined(HAVE_PROC_CPUINFO)
	FILE	*f = NULL;
	int	ncpu = 0;

	if (NULL == (file = fopen("/proc/cpuinfo", "r")))
		goto return_one;

	while (NULL != fgets(line, 1024, file))
	{
		if (NULL == strstr(line, "processor"))
			continue;
		ncpu++;
	}
	zbx_fclose(file);

	if (0 == ncpu)
		goto return_one;

	return ncpu;
#endif

#ifndef _WINDOWS
return_one:
	zabbix_log(LOG_LEVEL_WARNING, "cannot determine number of CPUs, assuming 1");
	return 1;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: Allocate memory for collector                                     *
 *                                                                            *
 * Comments: Unix version allocates memory as shared.                         *
 *                                                                            *
 ******************************************************************************/
int	init_collector_data(char **error)
{
	int	cpu_count, ret = FAIL;
	size_t	sz, sz_cpu, sz_cpu_phys_util = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	cpu_count = zbx_get_cpu_num();
	sz = ZBX_SIZE_T_ALIGN8(sizeof(ZBX_COLLECTOR_DATA));

#ifdef _WINDOWS
	ZBX_UNUSED(error);

	sz_cpu = sizeof(zbx_perf_counter_data_t *) * (cpu_count + 1);
	collector = zbx_malloc(collector, sz + sz_cpu);
	memset(collector, 0, sz + sz_cpu);

	collector->cpus.cpu_counter = (zbx_perf_counter_data_t **)((char *)collector + sz);
	collector->cpus.count = cpu_count;
#else
	sz_cpu = sizeof(ZBX_SINGLE_CPU_STAT_DATA) * (cpu_count + 1);
#ifdef _AIX
	sz_cpu = ZBX_SIZE_T_ALIGN8(sz_cpu);
	sz_cpu_phys_util = ZBX_SIZE_T_ALIGN8(sizeof(ZBX_CPU_UTIL_PCT_AIX)) * MAX_COLLECTOR_HISTORY * (cpu_count + 1);
#endif

	if (-1 == (shm_id = zbx_shm_create(sz + sz_cpu + sz_cpu_phys_util)))
	{
		*error = zbx_strdup(*error, "cannot allocate shared memory for collector");
		goto out;
	}

	if ((void *)(-1) == (collector = (ZBX_COLLECTOR_DATA *)shmat(shm_id, NULL, 0)))
	{
		*error = zbx_dsprintf(*error, "cannot attach shared memory for collector: %s", zbx_strerror(errno));
		goto out;
	}

	/* Immediately mark the new shared memory for destruction after attaching to it */
	if (-1 == zbx_shm_destroy(shm_id))
	{
		*error = zbx_strdup(*error, "cannot mark the new shared memory for destruction.");
		goto out;
	}

	collector->cpus.cpu = (ZBX_SINGLE_CPU_STAT_DATA *)((char *)collector + sz);
	collector->cpus.count = cpu_count;
	collector->diskstat_shmid = ZBX_NONEXISTENT_SHMID;
#ifdef _AIX
	collector->cpus_phys_util.counters = (ZBX_CPU_UTIL_PCT_AIX *)((char *)collector + sz + sz_cpu);
	collector->cpus_phys_util.row_num = MAX_COLLECTOR_HISTORY;
	collector->cpus_phys_util.column_num = cpu_count + 1;	/* each CPU + total for all CPUs */
	collector->cpus_phys_util.h_latest = 0;
	collector->cpus_phys_util.h_count = 0;
#endif

#ifdef ZBX_PROCSTAT_COLLECTOR
	zbx_procstat_init();
#endif

	if (SUCCEED != zbx_mutex_create(&diskstats_lock, ZBX_MUTEX_DISKSTATS, error))
		goto out;
#endif

#ifdef _AIX
	memset(&collector->vmstat, 0, sizeof(collector->vmstat));
#endif

#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)
	if (SUCCEED != zbx_kstat_init(&collector->kstat, error))
		goto out;
#endif

	ret = SUCCEED;
#ifndef _WINDOWS
out:
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Free memory allocated for collector                               *
 *                                                                            *
 * Comments: Unix version allocated memory as shared.                         *
 *                                                                            *
 ******************************************************************************/
void	free_collector_data(void)
{
#ifdef _WINDOWS
	zbx_free(collector);
#else
	if (NULL == collector)
		return;

#ifdef ZBX_PROCSTAT_COLLECTOR
	zbx_procstat_destroy();
#endif

	if (ZBX_NONEXISTENT_SHMID != collector->diskstat_shmid)
	{
		if (-1 == shmctl(collector->diskstat_shmid, IPC_RMID, 0))
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove shared memory for disk statistics collector: %s",
					zbx_strerror(errno));
		diskdevices = NULL;
		collector->diskstat_shmid = ZBX_NONEXISTENT_SHMID;
	}

	zbx_mutex_destroy(&diskstats_lock);
#endif

#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)
	zbx_kstat_destroy();
#endif

	collector = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Allocate shared memory for collecting disk statistics             *
 *                                                                            *
 ******************************************************************************/
void	diskstat_shm_init(void)
{
#ifndef _WINDOWS
	size_t	shm_size;

	/* initially allocate memory for collecting statistics for only 1 disk */
	shm_size = sizeof(ZBX_DISKDEVICES_DATA);

	if (-1 == (collector->diskstat_shmid = zbx_shm_create(shm_size)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for disk statistics collector");
		exit(EXIT_FAILURE);
	}

	if ((void *)(-1) == (diskdevices = (ZBX_DISKDEVICES_DATA *)shmat(collector->diskstat_shmid, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for disk statistics collector: %s",
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	diskdevices->count = 0;
	diskdevices->max_diskdev = 1;
	my_diskstat_shmid = collector->diskstat_shmid;

	zabbix_log(LOG_LEVEL_DEBUG, "diskstat_shm_init() allocated initial shm segment id:%d"
			" for disk statistics collector", collector->diskstat_shmid);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: If necessary, reattach to disk statistics shared memory segment.  *
 *                                                                            *
 ******************************************************************************/
void	diskstat_shm_reattach(void)
{
#ifndef _WINDOWS
	if (my_diskstat_shmid != collector->diskstat_shmid)
	{
		int old_shmid;

		old_shmid = my_diskstat_shmid;

		if (ZBX_NONEXISTENT_SHMID != my_diskstat_shmid)
		{
			if (-1 == shmdt((void *) diskdevices))
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot detach from disk statistics collector shared"
						" memory: %s", zbx_strerror(errno));
				exit(EXIT_FAILURE);
			}
			diskdevices = NULL;
			my_diskstat_shmid = ZBX_NONEXISTENT_SHMID;
		}

		if ((void *)(-1) == (diskdevices = (ZBX_DISKDEVICES_DATA *)shmat(collector->diskstat_shmid, NULL, 0)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for disk statistics collector: %s",
					zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}
		my_diskstat_shmid = collector->diskstat_shmid;

		zabbix_log(LOG_LEVEL_DEBUG, "diskstat_shm_reattach() switched shm id from %d to %d",
				old_shmid, my_diskstat_shmid);
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a new, larger disk statistics shared memory segment and    *
 *          copy data from the old one.                                       *
 *                                                                            *
 ******************************************************************************/
void	diskstat_shm_extend(void)
{
#ifndef _WINDOWS
	size_t			old_shm_size, new_shm_size;
	int			old_shmid, new_shmid, old_max, new_max;
	ZBX_DISKDEVICES_DATA	*new_diskdevices;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* calculate the size of the new shared memory segment */
	old_max = diskdevices->max_diskdev;

	if (old_max < 4)
		new_max = old_max + 1;
	else if (old_max < 256)
		new_max = old_max * 2;
	else
		new_max = old_max + 256;

	old_shm_size = sizeof(ZBX_DISKDEVICES_DATA) + sizeof(ZBX_SINGLE_DISKDEVICE_DATA) * (old_max - 1);
	new_shm_size = sizeof(ZBX_DISKDEVICES_DATA) + sizeof(ZBX_SINGLE_DISKDEVICE_DATA) * (new_max - 1);

	if (-1 == (new_shmid = zbx_shm_create(new_shm_size)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for extending disk statistics collector");
		exit(EXIT_FAILURE);
	}

	if ((void *)(-1) == (new_diskdevices = (ZBX_DISKDEVICES_DATA *)shmat(new_shmid, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for extending disk statistics collector: %s",
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	/* copy data from the old segment */
	memcpy(new_diskdevices, diskdevices, old_shm_size);
	new_diskdevices->max_diskdev = new_max;

	/* delete the old segment */
	if (-1 == shmdt((void *) diskdevices))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot detach from disk statistics collector shared memory");
		exit(EXIT_FAILURE);
	}

	if (-1 == zbx_shm_destroy(collector->diskstat_shmid))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot destroy old disk statistics collector shared memory");
		exit(EXIT_FAILURE);
	}

	/* switch to the new segment */
	old_shmid = collector->diskstat_shmid;
	collector->diskstat_shmid = new_shmid;
	my_diskstat_shmid = collector->diskstat_shmid;
	diskdevices = new_diskdevices;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() extended diskstat shared memory: old_max:%d new_max:%d old_size:"
			ZBX_FS_SIZE_T " new_size:" ZBX_FS_SIZE_T " old_shmid:%d new_shmid:%d", __func__, old_max,
			new_max, (zbx_fs_size_t)old_shm_size, (zbx_fs_size_t)new_shm_size, old_shmid,
			collector->diskstat_shmid);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: Collect system information                                        *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(collector_thread, args)
{
	assert(args);

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "agent #%d started [collector]", server_num);

	zbx_free(args);
#ifdef _AIX
	/* Initialize collecting of vmstat data early. This helps getting the real values on the */
	/* first request. Also on the first request collector is starting to update vmstat data. */
	collect_vmstat_data(&collector->vmstat);
#endif

	if (SUCCEED != init_cpu_collector(&(collector->cpus)))
		free_cpu_collector(&(collector->cpus));

	while (ZBX_IS_RUNNING())
	{
		zbx_update_env(zbx_time());

		zbx_setproctitle("collector [processing data]");
#ifdef _WINDOWS
		collect_perfstat();
#else
		if (0 != CPU_COLLECTOR_STARTED(collector))
		{
			collect_cpustat(&(collector->cpus));
#ifdef _AIX
			collect_cpustat_physical(&collector->cpus_phys_util);
#endif
		}

		if (0 != DISKDEVICE_COLLECTOR_STARTED(collector))
			collect_stats_diskdevices();

#ifdef ZBX_PROCSTAT_COLLECTOR
		zbx_procstat_collect();
#endif

#endif
#ifdef _AIX
		if (1 == collector->vmstat.enabled)
			collect_vmstat_data(&collector->vmstat);
#endif

#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)
		zbx_kstat_collect(&collector->kstat);
#endif
		zbx_setproctitle("collector [idle 1 sec]");
		zbx_sleep(1);
	}

#ifdef _WINDOWS
	if (0 != CPU_COLLECTOR_STARTED(collector))
		free_cpu_collector(&(collector->cpus));

	ZBX_DO_EXIT();

	zbx_thread_exit(EXIT_SUCCESS);
#else
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#endif
}
