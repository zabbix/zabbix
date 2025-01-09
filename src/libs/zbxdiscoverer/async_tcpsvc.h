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

#ifndef ZABBIX_ASYNC_TCPSVC_H_
#define ZABBIX_ASYNC_TCPSVC_H_

#include "zbxcacheconfig.h"
#include "zbxasyncpoller.h"
#include "zbxcomms.h"

typedef enum
{
	ZABBIX_TCPSVC_STEP_CONNECT_INIT = 0,
	ZABBIX_TCPSVC_STEP_CONNECT_WAIT,
	ZABBIX_TCPSVC_STEP_SEND,
	ZABBIX_TCPSVC_STEP_RECV
}
zbx_zabbix_tcpsvc_step_t;

typedef struct zbx_tcpsvc_context
{
	zbx_dc_item_context_t		item;
	void				*arg;
	void				*arg_action;
	zbx_socket_t			s;
	zbx_tcp_recv_context_t		tcp_recv_context;
	zbx_tcp_send_context_t		tcp_send_context;
	zbx_zabbix_tcpsvc_step_t	step;
	char				*server_name;
	const char			*config_source_ip;
	int				config_timeout;
	unsigned char			svc_type;
	int				(*validate_func)(struct zbx_tcpsvc_context *, const char *);
	zbx_async_resolve_reverse_dns_t	resolve_reverse_dns;
	zbx_async_rdns_step_t		rdns_step;
	char				*reverse_dns;
	char				*send_data;
}
zbx_tcpsvc_context_t;

int	zbx_async_check_tcpsvc(zbx_dc_item_t *item, unsigned char svc_type, AGENT_RESULT *result,
		zbx_async_task_clear_cb_t clear_cb, void *arg, void *arg_action, struct event_base *base,
		struct evdns_base *dnsbase, const char *config_source_ip,
		zbx_async_resolve_reverse_dns_t resolve_reverse_dns);
void	zbx_async_check_tcpsvc_free(zbx_tcpsvc_context_t *agent_context);

#endif /* ZABBIX_ASYNC_TCPSVC_H_ */
