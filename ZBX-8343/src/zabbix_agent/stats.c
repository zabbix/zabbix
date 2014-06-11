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
#include "log.h"
#include "zbxconf.h"
#include "diskdevices.h"
#include "cfg.h"

#if defined(_WINDOWS)
#	include "service.h"
#else
#	include "daemon.h"
#	include "ipc.h"
#endif

ZBX_COLLECTOR_DATA	*collector = NULL;

#if !defined(_WINDOWS)
static int	shm_id;
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

#if !defined(_WINDOWS)
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
	int	cpu_count;
	size_t	sz, sz_cpu;
#if !defined(_WINDOWS)
	key_t	shm_key;
#endif

	cpu_count = zbx_get_cpu_num();
	sz = sizeof(ZBX_COLLECTOR_DATA);

#ifdef _WINDOWS
	sz_cpu = sizeof(PERF_COUNTER_DATA *) * (cpu_count + 1);

	collector = zbx_malloc(collector, sz + sz_cpu);
	memset(collector, 0, sz + sz_cpu);

	collector->cpus.cpu_counter = (PERF_COUNTER_DATA **)(collector + 1);
	collector->cpus.count = cpu_count;

	init_perf_collector(&collector->perfs);
#else
	sz_cpu = sizeof(ZBX_SINGLE_CPU_STAT_DATA) * (cpu_count + 1);

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_COLLECTOR_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC key for collector");
		exit(FAIL);
	}

	if (-1 == (shm_id = zbx_shmget(shm_key, sz + sz_cpu)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for collector");
		exit(FAIL);
	}

	if ((void *)(-1) == (collector = shmat(shm_id, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for collector: %s", zbx_strerror(errno));
		exit(FAIL);
	}

	collector->cpus.cpu = (ZBX_SINGLE_CPU_STAT_DATA *)(collector + 1);
	collector->cpus.count = cpu_count;
#endif	/* _WINDOWS */

#ifdef _AIX
	memset(&collector->vmstat, 0, sizeof(collector->vmstat));
#endif
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

	if (-1 == shmctl(shm_id, IPC_RMID, 0))
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove shared memory for collector: %s", zbx_strerror(errno));
#endif

	collector = NULL;
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

	zabbix_log(LOG_LEVEL_WARNING, "agent #%d started [collector]", ((zbx_thread_args_t *)args)->thread_num);

	zbx_free(args);

	if (SUCCEED != init_cpu_collector(&(collector->cpus)))
		free_cpu_collector(&(collector->cpus));

	collector_diskdevice_add("");

	while (ZBX_IS_RUNNING())
	{
		zbx_setproctitle("collector [processing data]");
#ifdef _WINDOWS
		collect_perfstat();
#else
		if (CPU_COLLECTOR_STARTED(collector))
			collect_cpustat(&(collector->cpus));
#endif
		collect_stats_diskdevices(&(collector->diskdevices));
#ifdef _AIX
		if (1 == collector->vmstat.enabled)
			collect_vmstat_data(&collector->vmstat);
#endif
		zbx_setproctitle("collector [sleeping for 1 seconds]");
		zbx_sleep(1);
	}

	if (CPU_COLLECTOR_STARTED(collector))
		free_cpu_collector(&(collector->cpus));

#ifdef _WINDOWS
	free_perf_collector();	/* cpu_collector must be freed before perf_collector is freed */
#endif

	zabbix_log(LOG_LEVEL_INFORMATION, "zabbix_agentd collector stopped");

	ZBX_DO_EXIT();

	zbx_thread_exit(0);
}
