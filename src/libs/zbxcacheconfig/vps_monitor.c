/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "vps_monitor.h"

#include "dbconfig.h"
#include "zbxmutexs.h"

static zbx_mutex_t	vps_lock = ZBX_MUTEX_NULL;

#define ZBX_VPS_FLUSH_PERIOD	10

/******************************************************************************
 *                                                                            *
 * Purpose: create VPS monitor                                                *
 *                                                                            *
 ******************************************************************************/
int	vps_monitor_create(zbx_vps_monitor_t *monitor, char **error)
{
	if (SUCCEED != zbx_mutex_create(&vps_lock, ZBX_MUTEX_VPS_MONITOR, error))
		return FAIL;

	memset(monitor, 0, sizeof(zbx_vps_monitor_t));

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: increment VPS monitor history cursor                              *
 *                                                                            *
 ******************************************************************************/
int	vps_history_inc(int *cr)
{
	if (VPS_HISTORY_SIZE == ++(*cr))
		*cr = 0;

	return *cr;
}

/******************************************************************************
 *                                                                            *
 * Purpose: decrement VPS monitor history cursor                              *
 *                                                                            *
 ******************************************************************************/
int	vps_history_dec(int *cr)
{
	if (0 == *cr)
		*cr = VPS_HISTORY_SIZE;

	--(*cr);

	return *cr;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize VPS monitor                                            *
 *                                                                            *
 * Comments: This function is called before processes are spawned -           *
 *           no locking is needed.                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_vps_monitor_init(zbx_uint64_t nvps_limit, zbx_uint64_t overcommit_limit)
{
	zbx_vps_monitor_t	*monitor = &config->vps_monitor;

	monitor->last_flush = time(NULL);
	monitor->last_hist = monitor->last_flush;

	monitor->values_limit = nvps_limit * ZBX_VPS_FLUSH_PERIOD;
	monitor->overcommit_limit = overcommit_limit * nvps_limit / 100;
	monitor->overcommit = overcommit_limit * nvps_limit / 100;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add number of collected values to the monitor                     *
 *                                                                            *
 * Comments: This function is called before processes are spawned -           *
 *           no locking is needed.                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_vps_monitor_add_collected(zbx_uint64_t values_num)
{
	zbx_vps_monitor_t	*monitor = &config->vps_monitor;
	time_t			now;

	now = time(NULL);

	zbx_mutex_lock(vps_lock);

	if (ZBX_VPS_FLUSH_PERIOD <= now - monitor->last_flush)
	{
		if (monitor->values_limit > monitor->values_num)
		{
			if (monitor->overcommit > monitor->values_limit - monitor->values_num)
				monitor->overcommit -= monitor->values_limit - monitor->values_num;
			else
				monitor->overcommit = 0;

			monitor->values_num = 0;
		}
		else
		{
			zbx_uint64_t	overcommit_available = monitor->overcommit_limit - monitor->overcommit;

			if (monitor->values_num <= monitor->values_limit + overcommit_available)
			{
				monitor->overcommit += monitor->values_num - monitor->values_limit;
				monitor->values_num = 0;
			}
			else
			{
				monitor->values_num -= monitor->values_limit + overcommit_available;
				monitor->overcommit = monitor->overcommit_limit;
			}
		}

		monitor->last_flush = now;
	}

	monitor->values_num += values_num;

	zbx_mutex_unlock(vps_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add number of written values to the monitor                       *
 *                                                                            *
 * Comments: This function is called before processes are spawned -           *
 *           no locking is needed.                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_vps_monitor_add_written(zbx_uint64_t values_num)
{
	zbx_vps_monitor_t	*monitor = &config->vps_monitor;
	time_t			now;

	now = time(NULL);

	zbx_mutex_lock(vps_lock);

	while (monitor->last_hist != now)
	{
		monitor->history[vps_history_inc(&monitor->history_tail)] = monitor->total_values_num;

		if (monitor->history_tail == monitor->history_head)
			vps_history_inc(&monitor->history_head);

		monitor->last_hist++;
	}

	monitor->total_values_num += values_num;
	monitor->history[monitor->history_tail] += values_num;

	zbx_mutex_unlock(vps_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the vps limit is reached for current time period         *
 *                                                                            *
 * Return value: SUCCEED - limit is reached                                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_vps_monitor_capped(void)
{
	zbx_vps_monitor_t	*monitor = &config->vps_monitor;

	if (0 == monitor->values_limit)
		return FAIL;

	int	ret;

	zbx_mutex_lock(vps_lock);

	zbx_uint64_t	overcommit_available = monitor->overcommit_limit - monitor->overcommit;
	ret = (monitor->values_num <= monitor->values_limit + overcommit_available ? FAIL : SUCCEED);

	zbx_mutex_unlock(vps_lock);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get available overcommit charge                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_vps_monitor_get_stats(zbx_vps_monitor_stats_t *stats)
{
	zbx_vps_monitor_t	*monitor = &config->vps_monitor;

	zbx_mutex_lock(vps_lock);
	stats->overcommit = monitor->overcommit;
	zbx_mutex_unlock(vps_lock);

	/* preconfigured values, cannot change without restarting server */
	stats->values_limit = monitor->values_limit / ZBX_VPS_FLUSH_PERIOD;
	stats->overcommit_limit = monitor->overcommit_limit;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get average vps fro the last minute                               *
 *                                                                            *
 ******************************************************************************/
double	zbx_vps_get_avg(void)
{
	zbx_vps_monitor_t	*monitor = &config->vps_monitor;
	double			avg;

	zbx_mutex_lock(vps_lock);

	if (monitor->history_tail != monitor->history_head)
	{
		int		last = monitor->history_tail, period;
		zbx_uint64_t	diff;

		vps_history_dec(&last);
		diff = monitor->history[last] - monitor->history[monitor->history_head];

		if (0 > (period = last - monitor->history_head))
			period += VPS_HISTORY_SIZE;

		avg = (double)diff / period;
	}
	else
		avg = 0.0;

	zbx_mutex_unlock(vps_lock);

	return avg;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return data collection status string to append to process title   *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_vps_monitor_status(void)
{
	if (SUCCEED == zbx_vps_monitor_capped())
		return ", data collection paused";
	else
		return "";
}
