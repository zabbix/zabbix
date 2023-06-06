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

#include "asyncpoller.h"
#include "zbxtime.h"
#include "log.h"

#ifdef HAVE_LIBEVENT
#	include <event.h>
#endif


static void	async_poller_timeout_cb(evutil_socket_t fd, short what, void *arg)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(fd);
	ZBX_UNUSED(what);
	ZBX_UNUSED(arg);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

}

void	zbx_async_poller_init(zbx_async_poller_t *poller, struct event_base *ev)
{
	poller->ev = ev;
	poller->ev_timer = evtimer_new(poller->ev, async_poller_timeout_cb, poller);
}

void	zbx_async_poller_clear(zbx_async_poller_t *poller)
{
	event_free(poller->ev_timer);
	event_base_free(poller->ev);
}

static void	async_task_remove(zbx_async_task_t *task)
{
	printf("remove async task\n");
	task->free_cb(task->data);

	event_free(task->rx_event);
	event_free(task->tx_event);
	event_free(task->timeout_event);

	zbx_free(task);
}

static void	async_event(evutil_socket_t fd, short what, void *arg)
{
	zbx_async_task_t	*task = (zbx_async_task_t *)arg;
	int			ret;

	ZBX_UNUSED(fd);

	if (ZBX_ASYNC_TASK_STOP == (ret = task->process_cb(what, task->data)))
		async_task_remove(task);

	if (ZBX_ASYNC_TASK_READ == ret)
		event_add(task->rx_event, NULL);

	if (ZBX_ASYNC_TASK_WRITE == ret)
		event_add(task->tx_event, NULL);
}

void	zbx_async_poller_add_task(struct event_base *ev, int fd, void *data, int timeout,
		zbx_async_task_process_cb_t process_cb, zbx_async_task_clear_cb_t clear_cb)
{
	zbx_async_task_t	*task;

	task = (zbx_async_task_t *)zbx_malloc(NULL, sizeof(zbx_async_task_t));
	task->data = data;
	task->process_cb = process_cb;
	task->free_cb = clear_cb;

	task->timeout_event = evtimer_new(ev,  async_event, (void *)task);
	struct timeval	tv = {timeout, 0};
	evtimer_add(task->timeout_event, &tv);

	task->rx_event = event_new(ev, fd, EV_READ, async_event, (void *)task);
	task->tx_event = event_new(ev, fd, EV_WRITE, async_event, (void *)task);

	/* call initialization event */
	async_event(fd, 0, task);
}


void	zbx_async_poller_process(zbx_async_poller_t *poller, zbx_timespec_t *ts)
{
	struct timeval	tv = {ts->sec, ts->ns / 1000};

	evtimer_add(poller->ev_timer, &tv);
	event_base_loop(poller->ev, EVLOOP_ONCE);
}

typedef struct
{
	int	fd;
	int	step;
}
chat_task_t;


int	chat_task_process(short event, void *data)
{
	chat_task_t	*task = (chat_task_t *)data;
	char		buffer[1024];

	printf("chat_task_process(%x)\n", event);

	if (0 == event)
	{
		/* initialization */
		struct sockaddr_un	addr;

		memset(&addr, 0, sizeof(addr));
		addr.sun_family = AF_UNIX;
		zbx_strlcpy(addr.sun_path, "/tmp/chat.sock", sizeof(addr.sun_path));

		connect(task->fd, (struct sockaddr*)&addr, sizeof(addr));
		return ZBX_ASYNC_TASK_WRITE;
	}

	if (0 != (event & EV_TIMEOUT))
	{
		printf("timeout\n");
		return ZBX_ASYNC_TASK_STOP;
	}

	if (0 != (event & EV_WRITE))
	{
		zbx_snprintf(buffer, sizeof(buffer), "%d", task->step);
		write(task->fd, buffer, strlen(buffer) + 1);
		printf("write(event): %s\n", buffer);
		return ZBX_ASYNC_TASK_READ;
	}

	if (0 != (event & EV_READ))
	{
		int	n;

		n = read(task->fd, buffer, sizeof(buffer));

		if (0 == n)
		{
			printf("closed\n");
			return ZBX_ASYNC_TASK_STOP;
		}

		if (0 == strncmp(buffer, "quit", 4))
		{
			printf("terminating\n");
			return ZBX_ASYNC_TASK_STOP;
		}

		if (0 == strncmp(buffer, "ok", 2))
		{
			task->step++;
			printf("continue step %d\n", task->step);
			return ZBX_ASYNC_TASK_WRITE;
		}

	}

	return ZBX_ASYNC_TASK_READ;
}

void	chat_task_free(void *data)
{
	chat_task_t	*task = (chat_task_t *)data;

	printf("chat_task_free(%p)\n", data);

	close(task->fd);
	zbx_free(task);
}

/*
static chat_task_t	*create_task(void)
{
	chat_task_t		*task;
	int			flags;

	task = (chat_task_t *)zbx_malloc(NULL, sizeof(chat_task_t));

	task->step = 1;

	if (-1 == (task->fd = socket(AF_UNIX, SOCK_STREAM, 0)))
	{
		printf("Cannot create client socket: %s\n", zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	if (-1 == (flags = fcntl(task->fd, F_GETFL, 0)))
	{
		printf("cannot get IPC client socket flags\n");
		exit(EXIT_FAILURE);
	}

	if (-1 == fcntl(task->fd, F_SETFL, flags | O_NONBLOCK))
	{
		printf("cannot set non-blocking mode for IPC client socket\n");
		exit(EXIT_FAILURE);
	}

	return task;
}
*/


void	test(void)
{
	/*zbx_async_poller_t	poller;
	chat_task_t		*task;
	zbx_timespec_t		ts = {1, 0};


	zbx_async_poller_init(&poller);

	task = create_task();

	zbx_async_poller_add_task(&poller, task->fd, task, 10, chat_task_process, chat_task_free);

	for (int i = 0; i < 10; i++)
	{
		zbx_async_poller_process(&poller, &ts);
		printf(".\n");
	}


	zbx_async_poller_clear(&poller);*/
}
