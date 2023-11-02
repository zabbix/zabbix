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
#ifdef HAVE_LIBEVENT
#include <event.h>

typedef enum
{
	ZBX_ASYNC_TASK_READ,
	ZBX_ASYNC_TASK_WRITE,
	ZBX_ASYNC_TASK_STOP,

}
zbx_async_task_state_t;

typedef int (*zbx_async_task_process_cb_t)(short event, void *data, int *fd, const char *addr, char *dnserr);
typedef void (*zbx_async_task_clear_cb_t)(void *data);

void	zbx_async_poller_add_task(struct event_base *ev, struct evdns_base *dnsbase, const char *addr,
		void *data, int timeout, zbx_async_task_process_cb_t process_cb, zbx_async_task_clear_cb_t clear_cb);
#endif
#endif
