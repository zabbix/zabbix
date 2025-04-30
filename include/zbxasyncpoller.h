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

#ifndef ZABBIX_ASYNCPOLLER_H
#define ZABBIX_ASYNCPOLLER_H

#include "zbxcommon.h"

#define ZBX_RES_CONF_FILE "/etc/resolv.conf"

#ifdef HAVE_LIBEVENT
#include <event2/dns.h>
#include <event2/event.h>
#ifdef HAVE_ARES
#include <ares.h>
typedef struct ares_channeldata zbx_channel_t;
#else
typedef void zbx_channel_t;
#endif
#include "zbxalgo.h"

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

typedef struct
{
	char	ip[65];
}
zbx_address_t;

ZBX_VECTOR_DECL(address, zbx_address_t)

typedef int (*zbx_async_task_process_task_cb_t)(short event, void *data, int *fd, zbx_vector_address_t *addresses,
		const char *reverse_dns, char *dnserr, struct event *timeout_event);
typedef void (*zbx_async_task_process_result_cb_t)(void *data);

zbx_async_task_state_t	zbx_async_poller_get_task_state_for_event(short event);
void			zbx_async_poller_add_task(struct event_base *ev, zbx_channel_t *channel,
			struct evdns_base *dnsbase, const char *addr, void *data, int timeout,
			zbx_async_task_process_task_cb_t async_task_process_task_cb,
			zbx_async_task_process_result_cb_t async_task_process_result_cb);
const char		*zbx_resolv_conf_errstr(int error);
const char		*zbx_get_event_string(short event);
const char		*zbx_task_state_to_str(zbx_async_task_state_t task_state);
void			zbx_async_dns_update_host_addresses(struct evdns_base *dnsbase, zbx_channel_t *channel);
#endif
#endif
