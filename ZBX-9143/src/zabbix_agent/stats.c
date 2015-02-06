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
#include "log.h"
#include "zbxconf.h"
#ifndef _WINDOWS
#	include "diskdevices.h"
#endif
#include "cfg.h"
#include "mutexs.h"

#ifdef _WINDOWS
#	include "service.h"
#	include "perfstat.h"
#else
#	include "daemon.h"
#	include "ipc.h"
#endif

ZBX_COLLECTOR_DATA	*collector = NULL;

#ifndef _WINDOWS
static int		shm_id;
int 			my_diskstat_shmid = NONEXISTENT_SHMID;
ZBX_DISKDEVICES_DATA	*diskdevices = NULL;
ZBX_MUTEX		diskstats_lock;
#endif

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_cpu_num                                                  *
 *                                                                            *
 * Purpose: returns the number of processors which are currently online       *
 *          (i.e., available).                                                *
 *                                                                            *
 * Return value: number of CPUs                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_get_cpu_num()
{
#if defined(_WINDOWS)
	SYSTEM_INFO	sysInfo;

	GetSystemInfo(&sysInfo);

	return (int)sysInfo.dwNumberOfProcessors;
#elif defined(HAVE_SYS_PSTAT_H)
	struct pst_dynamic	psd;

	if (-1 == pstat_getdynamic(&psd, sizeof(struct pst_dynamic), 1, 0))
		goto return_one;

	return (int)psd.psd_proc_cnt;
#elif defined(_SC_NPROCESSORS_CONF)
	/* FreeBSD 7.0 x86 */
	/* Solaris 10 x86 */
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
#elif defined(HAVE_LIBPERFSTAT)
	/* AIX 6.1 */
	perfstat_cpu_total_t	ps_cpu_total;

	if (-1 == perfstat_cpu_total(NULL, &ps_cpu_total, sizeof(ps_cpu_total), 1))
		goto return_one;

	return (int)ps_cpu_total.ncpus;
#endif

#ifndef _WINDOWS
return_one:
	zabbix_log(LOG_LEVEL_WARNING, "cannot determine number of CPUs, assuming 1");
	return 1;
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: init_collector_data                                              *
 *                                                                            *
 * Purpose: Allocate memory for collector                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Unix version allocates memory as shared.                         *
 *                                                                            *
 ******************************************************************************/
void	init_collector_data()
{
	const char	*__function_name = "init_collector_data";
	int		cpu_count;
	size_t		sz, sz_cpu;
#ifndef _WINDOWS
	key_t		shm_key;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	cpu_count = zbx_get_cpu_num();
	sz = sizeof(ZBX_COLLECTOR_DATA);

#ifdef _WINDOWS
	sz_cpu = sizeof(PERF_COUNTER_DATA *) * (cpu_count + 1);

	collector = zbx_malloc(collector, sz + sz_cpu);
	memset(collector, 0, sz + sz_cpu);

	collector->cpus.cpu_counter = (PERF_COUNTER_DATA **)(collector + 1);
	collector->cpus.count = cpu_count;
#else
	sz_cpu = sizeof(ZBX_SINGLE_CPU_STAT_DATA) * (cpu_count + 1);

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_COLLECTOR_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC key for collector");
		exit(EXIT_FAILURE);
	}

	if (-1 == (shm_id = zbx_shmget(shm_key, sz + sz_cpu)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for collector");
		exit(EXIT_FAILURE);
	}

	if ((void *)(-1) == (collector = shmat(shm_id, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for collector: %s", zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	collector->cpus.cpu = (ZBX_SINGLE_CPU_STAT_DATA *)(collector + 1);
	collector->cpus.count = cpu_count;
	collector->diskstat_shmid = NONEXISTENT_SHMID;

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&diskstats_lock, ZBX_MUTEX_DISKSTATS))
	{
		zbx_error("cannot create mutex for disk statistics collector");
		exit(EXIT_FAILURE);
	}
#endif

#ifdef _AIX
	memset(&collector->vmstat, 0, sizeof(collector->vmstat));
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: free_collector_data                                              *
 *                                                                            *
 * Purpose: Free memory allocated for collector                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Unix version allocated memory as shared.                         *
 *                                                                            *
 ******************************************************************************/
void	free_collector_data()
{
#ifdef _WINDOWS
	zbx_free(collector);
#else
	if (NULL == collector)
		return;

	if (NONEXISTENT_SHMID != collector->diskstat_shmid)
	{
		if (-1 == shmctl(collector->diskstat_shmid, IPC_RMID, 0))
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove shared memory for disk statistics collector: %s",
					zbx_strerror(errno));
		diskdevices = NULL;
		collector->diskstat_shmid = NONEXISTENT_SHMID;
	}

	if (-1 == shmctl(shm_id, IPC_RMID, 0))
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove shared memory for collector: %s", zbx_strerror(errno));

	zbx_mutex_destroy(&diskstats_lock);
#endif
	collector = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: diskstat_shm_init                                                *
 *                                                                            *
 * Purpose: Allocate shared memory for collecting disk statistics             *
 *                                                                            *
 ******************************************************************************/
void	diskstat_shm_init()
{
#ifndef _WINDOWS
	key_t	shm_key;
	size_t	shm_size;

	/* initially allocate memory for collecting statistics for only 1 disk */
	shm_size = sizeof(ZBX_DISKDEVICES_DATA);

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_COLLECTOR_DISKSTAT)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC key for disk statistics collector");
		exit(EXIT_FAILURE);
	}

	if (-1 == (collector->diskstat_shmid = zbx_shmget(shm_key, shm_size)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for disk statistics collector");
		exit(EXIT_FAILURE);
	}

	if ((void *)(-1) == (diskdevices = shmat(collector->diskstat_shmid, NULL, 0)))
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
 * Function: diskstat_shm_reattach                                            *
 *                                                                            *
 * Purpose: If necessary, reattach to disk statistics shared memory segment.  *
 *                                                                            *
 ******************************************************************************/
void	diskstat_shm_reattach()
{
#ifndef _WINDOWS
	if (my_diskstat_shmid != collector->diskstat_shmid)
	{
		int old_shmid;

		old_shmid = my_diskstat_shmid;

		if (NONEXISTENT_SHMID != my_diskstat_shmid)
		{
			if (-1 == shmdt((void *) diskdevices))
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot detach from disk statistics collector shared"
						" memory: %s", zbx_strerror(errno));
				exit(EXIT_FAILURE);
			}
			diskdevices = NULL;
			my_diskstat_shmid = NONEXISTENT_SHMID;
		}

		if ((void *)(-1) == (diskdevices = shmat(collector->diskstat_shmid, NULL, 0)))
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
 * Function: diskstat_shm_extend                                              *
 *                                                                            *
 * Purpose: create a new, larger disk statistics shared memory segment and    *
 *          copy data from the old one.                                       *
 *                                                                            *
 ******************************************************************************/
void	diskstat_shm_extend()
{
#ifndef _WINDOWS
	const char		*__function_name = "diskstat_shm_extend";
	key_t			shm_key;
	size_t			old_shm_size, new_shm_size;
	int			old_shmid, new_shmid, old_max, new_max;
	ZBX_DISKDEVICES_DATA	*new_diskdevices;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* caclulate the size of the new shared memory segment */
	old_max = diskdevices->max_diskdev;

	if (old_max < 4)
		new_max = old_max + 1;
	else if (old_max < 256)
		new_max = old_max * 2;
	else
		new_max = old_max + 256;

	old_shm_size = sizeof(ZBX_DISKDEVICES_DATA) + sizeof(ZBX_SINGLE_DISKDEVICE_DATA) * (old_max - 1);
	new_shm_size = sizeof(ZBX_DISKDEVICES_DATA) + sizeof(ZBX_SINGLE_DISKDEVICE_DATA) * (new_max - 1);

	/* Create the new shared memory segment. The same key is used. */
	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_COLLECTOR_DISKSTAT)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC key for extending disk statistics collector");
		exit(EXIT_FAILURE);
	}

	/* zbx_shmget() will:                                                 */
	/*	- see that a shared memory segment with this key exists       */
	/*	- mark it for deletion                                        */
	/*	- create a new segment with this key, but with a different id */

	if (-1 == (new_shmid = zbx_shmget(shm_key, new_shm_size)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for extending disk statistics collector");
		exit(EXIT_FAILURE);
	}

	if ((void *)(-1) == (new_diskdevices = shmat(new_shmid, NULL, 0)))
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

	/* switch to the new segment */
	old_shmid = collector->diskstat_shmid;
	collector->diskstat_shmid = new_shmid;
	my_diskstat_shmid = collector->diskstat_shmid;
	diskdevices = new_diskdevices;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() extended diskstat shared memory: old_max:%d new_max:%d old_size:%d"
			" new_size:%d old_shmid:%d new_shmid:%d", __function_name, old_max, new_max, old_shm_size,
			new_shm_size, old_shmid, collector->diskstat_shmid);
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: collector_thread                                                 *
 *                                                                            *
 * Purpose: Collect system information                                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(collector_thread, args)
{
	assert(args);

	zabbix_log(LOG_LEVEL_INFORMATION, "agent #%d started [collector]", ((zbx_thread_args_t *)args)->thread_num);

	zbx_free(args);

	if (SUCCEED != init_cpu_collector(&(collector->cpus)))
		free_cpu_collector(&(collector->cpus));

	while (ZBX_IS_RUNNING())
	{
		zbx_setproctitle("collector [processing data]");
#ifdef _WINDOWS
		collect_perfstat();
#else
		if (0 != CPU_COLLECTOR_STARTED(collector))
			collect_cpustat(&(collector->cpus));

		if (0 != DISKDEVICE_COLLECTOR_STARTED(collector))
			collect_stats_diskdevices();
#endif
#ifdef _AIX
		if (1 == collector->vmstat.enabled)
			collect_vmstat_data(&collector->vmstat);
#endif
		zbx_setproctitle("collector [idle 1 sec]");
		zbx_sleep(1);
	}

#ifdef _WINDOWS
	if (CPU_COLLECTOR_STARTED(collector))
		free_cpu_collector(&(collector->cpus));

	zabbix_log(LOG_LEVEL_INFORMATION, "zabbix_agentd collector stopped");

	ZBX_DO_EXIT();

	zbx_thread_exit(0);
#endif
}
