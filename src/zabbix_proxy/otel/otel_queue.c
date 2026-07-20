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

#include "otel_queue.h"
#include "otel_task.h"
#include "zbxmw.h"
#include <bits/time.h>

#define OTEL_THROTTLE_WINDOW	SEC_PER_MIN

static time_t	time_monotonic(void)
{
	struct timespec	ts;

	if (0 == clock_gettime(CLOCK_MONOTONIC, &ts))
		return ts.tv_sec;

	return time(NULL);
}

zbx_otel_queue_t	*otel_queue_create(zbx_uint64_t quota)
{
	zbx_otel_queue_t	*queue;

	queue = (zbx_otel_queue_t *)zbx_calloc(NULL, 1, sizeof(zbx_otel_queue_t));
	queue->quota = quota;
	queue->window_start = time_monotonic();

	return queue;
}

void	otel_queue_set_quota(zbx_otel_queue_t *queue, zbx_uint64_t quota)
{
	queue->quota = quota;
}

int	otel_queue_push_request(zbx_otel_queue_t *queue, zbx_otel_request_t request, zbx_otel_request_type_t type)
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
		zbx_mw_queue_push_completed_direct(&queue->base, otel_task_request_create(request, type));

	zbx_mw_queue_unlock(&queue->base);

	return ret;
}
