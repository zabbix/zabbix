/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "apm_queue.h"
#include "apm_task.h"
#include "zbxmw.h"

#define APM_THROTTLE_WINDOW	SEC_PER_MIN

static time_t	time_monotonic(void)
{
	struct timespec	ts;

	if (0 == clock_gettime(CLOCK_MONOTONIC, &ts))
		return ts.tv_sec;

	return time(NULL);
}

zbx_apm_queue_t	*apm_queue_create(zbx_uint64_t quota)
{
	zbx_apm_queue_t	*queue;

	queue = (zbx_apm_queue_t *)zbx_calloc(NULL, 1, sizeof(zbx_apm_queue_t));
	queue->quota = quota;
	queue->window_start = time_monotonic();

	return queue;
}

void	apm_queue_set_quota(zbx_apm_queue_t *queue, zbx_uint64_t quota)
{
	queue->quota = quota * SEC_PER_MIN;
}

int	apm_queue_push_request(zbx_apm_queue_t *queue, zbx_apm_request_t request, zbx_apm_request_type_t type)
{
	int	ret = SUCCEED;

	zbx_mw_queue_lock(&queue->base);
	if (0 != queue->quota)
	{
		if (queue->usage >= queue->quota)
		{
			time_t	now = time_monotonic();
			int	window_num = (now - queue->window_start) / SEC_PER_MIN;

			if (0 < window_num)
			{
				if (queue->usage >= window_num * queue->quota)
					queue->usage -= window_num * queue->quota;
				else
					queue->usage = 0;

				queue->window_start += window_num * SEC_PER_MIN;
			}
		}

		if (queue->usage >= queue->quota)
			ret = FAIL;
		else
			queue->usage++;

	}

	if (SUCCEED == ret)
	{
		zbx_mw_queue_push_completed_direct(&queue->base, apm_task_request_create(request, type));
		queue->accepted_num++;
	}
	else
		queue->dropped_num++;

	zbx_mw_queue_unlock(&queue->base);

	return ret;
}

void	apm_queue_get_stats(zbx_apm_queue_t *queue, zbx_uint64_t *accepted_num, zbx_uint64_t *dropped_num)
{
	zbx_mw_queue_lock(&queue->base);
	*accepted_num = queue->accepted_num;
	*dropped_num = queue->dropped_num;
	zbx_mw_queue_unlock(&queue->base);
}
