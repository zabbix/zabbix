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

#include "vps_tracker.h"
#include "dbconfig.h"
#include "zbxmutexs.h"

static zbx_mutex_t	vps_lock = ZBX_MUTEX_NULL;

#define VPS_FLUSH_PERIOD	10

/******************************************************************************
 *                                                                            *
 * Purpose: create VPS tracker                                                *
 *                                                                            *
 ******************************************************************************/
int	vps_tracker_create(zbx_vps_tracker_t *tracker, char **error)
{
	int	ret = FAIL;

	if (SUCCEED != (ret = zbx_mutex_create(&vps_lock, ZBX_MUTEX_VPS_TRACKER, error)))
		goto out;

	memset(tracker, 0, sizeof(zbx_vps_tracker_t));

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: increment VPS tracker history cursor                              *
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
 * Purpose: decrement VPS tracker history cursor                              *
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
 * Purpose: initialize VPS tracker                                            *
 *                                                                            *
 * Comments: This function is called before processes are spawned -           *
 *           no locking is needed.                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_vps_tracker_init(zbx_uint64_t nvps_limit, zbx_uint64_t overcommit_limit)
{
	zbx_vps_tracker_t	*tracker = &config->vps_tracker;

	tracker->last_flush = time(NULL);

	tracker->values_limit = nvps_limit * VPS_FLUSH_PERIOD;
	tracker->overcommit_limit = overcommit_limit;
	tracker->overcommit_charge = overcommit_limit;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add number of synced values to the tracker                        *
 *                                                                            *
 * Comments: This function is called before processes are spawned -           *
 *           no locking is needed.                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_vps_tracker_add(zbx_uint64_t values_num)
{
	zbx_vps_tracker_t	*tracker = &config->vps_tracker;
	time_t			now;

	now = time(NULL);

	zbx_mutex_lock(vps_lock);

	if (VPS_FLUSH_PERIOD <= now - tracker->last_flush)
	{
		if (tracker->values_limit > tracker->values_num)
		{
			tracker->overcommit_charge += tracker->values_limit - tracker->values_num;

			if (tracker->overcommit_charge > tracker->overcommit_limit)
				tracker->overcommit_charge = tracker->overcommit_limit;

			tracker->values_num = 0;
		}
		else
		{
			if (tracker->values_num <= tracker->values_limit + tracker->overcommit_charge)
			{
				tracker->overcommit_charge -= tracker->values_num - tracker->values_limit;
				tracker->values_num = 0;
			}
			else
			{
				tracker->values_num -= tracker->values_limit + tracker->overcommit_charge;
				tracker->overcommit_charge = 0;
			}
		}

		tracker->last_flush = now;
	}

	tracker->values_num += values_num;

	/* update history statistics */

	while (tracker->last_flush != now)
	{
		tracker->history[vps_history_inc(&tracker->history_tail)] = tracker->total_values_num;

		if (tracker->history_tail == tracker->history_head)
			vps_history_inc(&tracker->history_head);

		tracker->last_flush++;
	}

	tracker->total_values_num += values_num;
	tracker->history[tracker->history_tail] += values_num;

	zbx_mutex_unlock(vps_lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the vps limit is reached for current time period        *
 *                                                                            *
 * Return value: SUCCEED - limit is reached                                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_vps_tracker_limit(void)
{
	zbx_vps_tracker_t	*tracker = &config->vps_tracker;
	int			ret;

	zbx_mutex_lock(vps_lock);

	ret = (tracker->values_num <= tracker->values_limit + tracker->overcommit_charge ? FAIL : SUCCEED);

	zbx_mutex_unlock(vps_lock);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the vps limit is reached for current time period        *
 *                                                                            *
 * Return value: SUCCEED - limit is reached                                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
double	zbx_vps_get_avg(void)
{
	zbx_vps_tracker_t	*tracker = &config->vps_tracker;
	double			avg;

	zbx_mutex_lock(vps_lock);

	if (tracker->history_tail != tracker->history_head)
	{
		int		last = tracker->history_tail, period;
		zbx_uint64_t	diff;

		vps_history_dec(&last);
		diff = tracker->history[last] - tracker->history[tracker->history_head];

		if (0 > (period = last - tracker->history_head))
			period += VPS_HISTORY_SIZE;

		avg = (double)diff / period;
	}
	else
		avg = 0.0;

	zbx_mutex_unlock(vps_lock);

	return avg;
}
