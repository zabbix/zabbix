/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	ZBX_ASYNC_TASK_RESOLVE_REVERSE
}
zbx_async_task_state_t;


typedef enum
{
	ZABBIX_ASYNC_STEP_DEFAULT = 0,
	ZABBIX_ASYNC_STEP_REVERSE_DNS,
}
zbx_async_rdns_step_t;

typedef enum
{
	ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_NO = 0,
	ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES,
}
zbx_async_resolve_reverse_dns_t;

typedef int (*zbx_async_task_process_cb_t)(short event, void *data, int *fd, const char *addr, char *dnserr);
typedef void (*zbx_async_task_clear_cb_t)(void *data);

zbx_async_task_state_t	zbx_async_poller_get_task_state_for_event(short event);
void			zbx_async_poller_add_task(struct event_base *ev, struct evdns_base *dnsbase, const char *addr,
		void *data, int timeout, zbx_async_task_process_cb_t process_cb, zbx_async_task_clear_cb_t clear_cb);
#endif
#endif
