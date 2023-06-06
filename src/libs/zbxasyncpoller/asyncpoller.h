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

#ifndef ZABBIX_ASYNCPOLLER_H
#define ZABBIX_ASYNCPOLLER_H

#include "zbxcommon.h"

#define ZBX_ASYNC_EVENT_CLOSE	0x0001
#define ZBX_ASYNC_EVENT_READ	0x0002
#define ZBX_ASYNC_EVENT_WRITE	0x0004
#define ZBX_ASYNC_EVENT_ERROR	0x0008
#define ZBX_ASYNC_EVENT_TIMEOUT	0x0010

typedef enum
{
	ZBX_ASYNC_TASK_READ,
	ZBX_ASYNC_TASK_WRITE,
	ZBX_ASYNC_TASK_STOP,

}
zbx_async_task_state_t;

typedef int (*zbx_async_task_process_cb_t)(short event, void *data);
typedef void (*zbx_async_task_clear_cb_t)(void *data);

typedef struct zbx_async_poller zbx_async_poller_t;

typedef struct
{
	void		*data;

	zbx_async_task_process_cb_t	process_cb;
	zbx_async_task_clear_cb_t	free_cb;

	struct event	*tx_event;
	struct event	*rx_event;
	struct event	*timeout_event;
}
zbx_async_task_t;


struct zbx_async_poller
{
	struct event_base	*ev;
	struct event		*ev_timer;
};

void	zbx_async_poller_init(zbx_async_poller_t *poller, struct event_base *ev);
void	zbx_async_poller_add_task(struct event_base *ev, int fd, void *data, int timeout,
		zbx_async_task_process_cb_t process_cb, zbx_async_task_clear_cb_t clear_cb);


#endif
