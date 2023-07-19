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

#include "zbxasyncpoller.h"

#ifdef HAVE_LIBEVENT

typedef struct
{
	void				*data;
	zbx_async_task_process_cb_t	process_cb;
	zbx_async_task_clear_cb_t	free_cb;
	struct event			*tx_event;
	struct event			*rx_event;
	struct event			*timeout_event;
}
zbx_async_task_t;

static void	async_task_remove(zbx_async_task_t *task)
{
	task->free_cb(task->data);

	event_free(task->rx_event);
	event_free(task->tx_event);
	event_free(task->timeout_event);

	zbx_free(task);
}

static const char	*task_state_to_str(zbx_async_task_state_t task_state)
{
	switch (task_state)
	{
		case ZBX_ASYNC_TASK_WRITE:
			return "ZBX_ASYNC_TASK_WRITE";
		case ZBX_ASYNC_TASK_READ:
			return "ZBX_ASYNC_TASK_READ";
		case ZBX_ASYNC_TASK_STOP:
			return "ZBX_ASYNC_TASK_STOP";
		default:
			return "unknown";
	}
}

static void	async_event(evutil_socket_t fd, short what, void *arg)
{
	zbx_async_task_t	*task = (zbx_async_task_t *)arg;
	int			ret;

	ZBX_UNUSED(fd);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = task->process_cb(what, task->data);

	switch (ret)
	{
		case ZBX_ASYNC_TASK_STOP:
			async_task_remove(task);
			break;
		case ZBX_ASYNC_TASK_READ:
			event_add(task->rx_event, NULL);
			break;
		case ZBX_ASYNC_TASK_WRITE:
			event_add(task->tx_event, NULL);
			break;

	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, task_state_to_str(ret));
}

void	zbx_async_poller_add_task(struct event_base *ev, int fd, void *data, int timeout,
		zbx_async_task_process_cb_t process_cb, zbx_async_task_clear_cb_t clear_cb)
{
	zbx_async_task_t	*task;
	struct timeval		tv = {timeout, 0};

	task = (zbx_async_task_t *)zbx_malloc(NULL, sizeof(zbx_async_task_t));
	task->data = data;
	task->process_cb = process_cb;
	task->free_cb = clear_cb;
	task->timeout_event = evtimer_new(ev,  async_event, (void *)task);

	evtimer_add(task->timeout_event, &tv);

	task->rx_event = event_new(ev, fd, EV_READ, async_event, (void *)task);
	task->tx_event = event_new(ev, fd, EV_WRITE, async_event, (void *)task);

	/* call initialization event */
	async_event(fd, 0, task);
}
#endif
