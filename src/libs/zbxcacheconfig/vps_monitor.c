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

#include "vps_monitor.h"

#include "dbconfig.h"
#include "zbxmutexs.h"
#include "zbxcacheconfig.h"

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
 * Purpose: destroy VPS monitor                                               *
 *                                                                            *
 ******************************************************************************/
void	vps_monitor_destroy(void)
{
	zbx_mutex_destroy(&vps_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize VPS monitor                                            *
 *                                                                            *
 * Comments: This function is called before processes are spawned -           *
 *           no locking is needed.                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_vps_monitor_init(zbx_uint64_t vps_limit, zbx_uint64_t overcommit_limit)
{
	zbx_vps_monitor_t	*monitor = &(get_dc_config())->vps_monitor;

	monitor->last_flush = time(NULL);

	monitor->values_limit = vps_limit * ZBX_VPS_FLUSH_PERIOD;
	monitor->overcommit_limit = overcommit_limit;
	monitor->overcommit = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add number of collected values to the monitor                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_vps_monitor_add_collected(zbx_uint64_t values_num)
{
	zbx_vps_monitor_t	*monitor = &(get_dc_config())->vps_monitor;
	time_t			now;

	if (0 == monitor->values_limit)
		return;

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
	zbx_vps_monitor_t	*monitor = &(get_dc_config())->vps_monitor;

	zbx_mutex_lock(vps_lock);
	monitor->total_values_num += values_num;
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
	zbx_vps_monitor_t	*monitor = &(get_dc_config())->vps_monitor;

	if (0 == monitor->values_limit || monitor->values_num < monitor->values_limit)
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
	zbx_vps_monitor_t	*monitor = &(get_dc_config())->vps_monitor;

	zbx_mutex_lock(vps_lock);
	stats->overcommit = monitor->overcommit;
	stats->written_num = monitor->total_values_num;
	zbx_mutex_unlock(vps_lock);

	/* preconfigured values, cannot change without restarting server */
	stats->values_limit = monitor->values_limit / ZBX_VPS_FLUSH_PERIOD;
	stats->overcommit_limit = monitor->overcommit_limit;
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
