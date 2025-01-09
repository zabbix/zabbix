/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/
#include "zbxself.h"

#include "zbxstats.h"
#include "zbxtimekeeper.h"
#include "zbxshmem.h"

#ifndef _WINDOWS
#	include "zbxmutexs.h"
#	include "zbxnix.h"
#	include "zbxlog.h"
#	include "zbxtime.h"
#	include "zbxthreads.h"

#	define MAX_HISTORY	60

#define ZBX_SELFMON_FLUSH_DELAY		(ZBX_SELFMON_DELAY * 0.5)

typedef struct
{
	zbx_timekeeper_t	*monitor;
	zbx_timekeeper_sync_t	sync;
	int			process_index[ZBX_PROCESS_TYPE_COUNT];
}
zbx_selfmon_collector_t;

static zbx_selfmon_collector_t	collector;
static zbx_get_config_forks_f	get_config_forks_cb = NULL;

static zbx_mutex_t	sm_lock = ZBX_MUTEX_NULL;

static zbx_shmem_info_t	*sm_mem = NULL;
ZBX_SHMEM_FUNC_IMPL(__sm, sm_mem)

#endif

static void	sm_sync_lock(void *data)
{
	zbx_mutex_t	*mutex = (zbx_mutex_t *)data;

	zbx_mutex_lock(*mutex);
}

static void	sm_sync_unlock(void *data)
{
	zbx_mutex_t	*mutex = (zbx_mutex_t *)data;

	zbx_mutex_unlock(*mutex);
}

#ifndef _WINDOWS

static int	selfmon_is_process_monitored(unsigned char proc_type)
{
	switch (proc_type)
	{
		case ZBX_PROCESS_TYPE_PREPROCESSOR:
		case ZBX_PROCESS_TYPE_DISCOVERER:
			return FAIL;
		default:
			return SUCCEED;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Initialize structures and prepare state                           *
 *          for self-monitoring collector                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_init_selfmon_collector(zbx_get_config_forks_f get_config_forks, char **error)
{
	size_t		sz_total;
	unsigned char	proc_type;
	int		units_num = 0, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	get_config_forks_cb = get_config_forks;

	for (proc_type = 0; ZBX_PROCESS_TYPE_COUNT > proc_type; proc_type++)
	{
		if (SUCCEED != selfmon_is_process_monitored(proc_type))
			continue;

		collector.process_index[proc_type] = units_num;
		units_num += get_config_forks_cb(proc_type);
	}

	sz_total = zbx_timekeeper_get_memmalloc_size(units_num);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() size:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)sz_total);

	if (SUCCEED != zbx_mutex_create(&sm_lock, ZBX_MUTEX_SELFMON, error))
		goto out;

	if (SUCCEED != (ret = zbx_shmem_create_min(&sm_mem, sz_total, "self-monitor cache", NULL, 0, error)))
		goto out;

	zbx_timekeeper_sync_init(&collector.sync, sm_sync_lock, sm_sync_unlock, (void *)&sm_lock);
	collector.monitor = zbx_timekeeper_create_ext(units_num, &collector.sync, __sm_shmem_malloc_func,
			__sm_shmem_realloc_func, __sm_shmem_free_func);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() collector.monitor:%p", __func__, (void *)collector.monitor);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Free memory allocated for self-monitoring collector               *
 *                                                                            *
 ******************************************************************************/
void	zbx_free_selfmon_collector(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() collector.monitor:%p", __func__, (void *)collector.monitor);

	if (NULL == collector.monitor)
		return;

	zbx_timekeeper_free(collector.monitor);

	zbx_mutex_destroy(&sm_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters: info  - [IN] caller process info                               *
 *             state - [IN] new process state; ZBX_PROCESS_STATE_*            *
 *                                                                            *
 ******************************************************************************/
void	zbx_update_selfmon_counter(const zbx_thread_info_t *info, unsigned char state)
{
	if (ZBX_PROCESS_TYPE_UNKNOWN == info->process_type)
		return;

	int	unit_index = collector.process_index[info->process_type] + info->process_num - 1;

	zbx_timekeeper_update(collector.monitor, unit_index, state);

}

static void	collect_selfmon_stats(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_timekeeper_collect(collector.monitor);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate statistics for selected process                         *
 *                                                                            *
 * Parameters: proc_type    - [IN] type of process; ZBX_PROCESS_TYPE_*        *
 *             aggr_func    - [IN] one of ZBX_SELFMON_AGGR_FUNC_*             *
 *             proc_num     - [IN] process number; 1 - first process;         *
 *                                 0 - all processes                          *
 *             state        - [IN] process state; ZBX_PROCESS_STATE_*         *
 *             value        - [OUT] a pointer to a variable that receives     *
 *                                  requested statistics                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_get_selfmon_stats(unsigned char proc_type, unsigned char aggr_func, int proc_num, unsigned char state,
		double *value)
{
	char	*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proc_type:%u proc_num:%d", __func__, proc_type, proc_num);

	if (SUCCEED == selfmon_is_process_monitored(proc_type))
	{
		int	unit_count;
		int	unit_index = collector.process_index[proc_type];

		if (0 < proc_num)
		{
			unit_index += proc_num - 1;
			unit_count = 1;
		}
		else
			unit_count = get_config_forks_cb(proc_type);

		if (SUCCEED != zbx_timekeeper_get_stat(collector.monitor, unit_index, unit_count, aggr_func, state, value, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get self monitoring statistics: %s", error);
			*value = 0;
			zbx_free(error);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "process is not monitored by self-monitoring library");
		*value = 0;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves internal metrics of all running processes based on      *
 *          process type                                                      *
 *                                                                            *
 * Parameters: stats - [OUT] process metrics                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_all_process_stats(zbx_process_info_t *stats)
{
	int			ret = FAIL;
	unsigned char		proc_type;
	zbx_timekeeper_state_t	*units;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (units = zbx_timekeeper_get_counters(collector.monitor)))
		goto out;

	for (proc_type = 0; proc_type < ZBX_PROCESS_TYPE_COUNT; proc_type++)
	{
		int		proc_num;
		unsigned int	total_avg = 0, counter_avg_busy = 0, counter_avg_idle = 0,
				total_max = 0, counter_max_busy = 0, counter_max_idle = 0,
				total_min = 0, counter_min_busy = 0, counter_min_idle = 0;

		stats[proc_type].count = get_config_forks_cb(proc_type);

		if (SUCCEED != selfmon_is_process_monitored(proc_type))
			continue;

		for (proc_num = 0; proc_num < stats[proc_type].count; proc_num++)
		{
			unsigned int		one_total = 0, busy_counter, idle_counter;
			unsigned char		s;

			int	unit_index = collector.process_index[proc_type] + proc_num;

			for (s = 0; s < ZBX_PROCESS_STATE_COUNT; s++)
				one_total += units[unit_index].counter[s];

			busy_counter = units[unit_index].counter[ZBX_PROCESS_STATE_BUSY];
			idle_counter = units[unit_index].counter[ZBX_PROCESS_STATE_IDLE];

			total_avg += one_total;
			counter_avg_busy += busy_counter;
			counter_avg_idle += idle_counter;

			if (0 == proc_num || busy_counter > counter_max_busy)
			{
				counter_max_busy = busy_counter;
				total_max = one_total;
			}

			if (0 == proc_num || idle_counter > counter_max_idle)
			{
				counter_max_idle = idle_counter;
				total_max = one_total;
			}

			if (0 == proc_num || busy_counter < counter_min_busy)
			{
				counter_min_busy = busy_counter;
				total_min = one_total;
			}

			if (0 == proc_num || idle_counter < counter_min_idle)
			{
				counter_min_idle = idle_counter;
				total_min = one_total;
			}
		}

		stats[proc_type].busy_avg = (0 == total_avg ? 0 : 100. * (double)counter_avg_busy / (double)total_avg);
		stats[proc_type].busy_max = (0 == total_max ? 0 : 100. * (double)counter_max_busy / (double)total_max);
		stats[proc_type].busy_min = (0 == total_min ? 0 : 100. * (double)counter_min_busy / (double)total_min);

		stats[proc_type].idle_avg = (0 == total_avg ? 0 : 100. * (double)counter_avg_idle / (double)total_avg);
		stats[proc_type].idle_max = (0 == total_max ? 0 : 100. * (double)counter_max_idle / (double)total_max);
		stats[proc_type].idle_min = (0 == total_min ? 0 : 100. * (double)counter_min_idle / (double)total_min);
	}

	zbx_free(units);
	ret = SUCCEED;
out:

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	sleep_remains;

/******************************************************************************
 *                                                                            *
 * Purpose: sleeping process                                                  *
 *                                                                            *
 * Parameters: info      - [IN] caller process info                           *
 *             sleeptime - [IN] required sleeptime, in seconds                *
 *                                                                            *
 ******************************************************************************/
void	zbx_sleep_loop(const zbx_thread_info_t *info, int sleeptime)
{
	if (0 >= sleeptime)
		return;

	sleep_remains = sleeptime;

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

	do
	{
		sleep(1);
	}
	while (0 < --sleep_remains);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
}

ZBX_THREAD_ENTRY(zbx_selfmon_thread, args)
{
	zbx_thread_args_t	*thread_args = (zbx_thread_args_t *)args;
	const zbx_thread_info_t	*info = &thread_args->info;
	int			process_num = info->process_num;
	const char		*program_type_str = get_program_type_string(info->program_type);
	const char		*process_type_str = get_process_type_string(info->process_type);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]",  program_type_str, info->server_num,
			process_type_str, process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	while (ZBX_IS_RUNNING())
	{
		double	sec = zbx_time();

		zbx_update_env(get_process_type_string(info->process_type), sec);

		zbx_setproctitle("%s [processing data]", process_type_str);

		collect_selfmon_stats();
		sec = zbx_time() - sec;

		zbx_setproctitle("%s [processed data in " ZBX_FS_DBL " sec, idle 1 sec]", process_type_str, sec);

		zbx_sleep_loop(info, ZBX_SELFMON_DELAY);
	}

	zbx_setproctitle("%s #%d [terminated]", process_type_str, process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
#endif
