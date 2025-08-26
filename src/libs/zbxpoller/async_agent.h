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

#ifndef ZABBIX_ASYNC_AGENT_H
#define ZABBIX_ASYNC_AGENT_H

#include "zbxcomms.h"
#include "zbxjson.h"
#include "zbxcacheconfig.h"
#include "zbxasyncpoller.h"

typedef enum
{
	ZABBIX_AGENT_STEP_CONNECT_INIT = 0,
	ZABBIX_AGENT_STEP_CONNECT_WAIT,
	ZABBIX_AGENT_STEP_TLS_WAIT,
	ZABBIX_AGENT_STEP_SEND,
	ZABBIX_AGENT_STEP_RECV,
	ZABBIX_AGENT_STEP_RECV_CLOSE
}
zbx_zabbix_agent_step_t;

typedef struct
{
	zbx_dc_item_context_t		item;
	void				*arg;
	void				*arg_action;
	zbx_socket_t			s;
	zbx_tcp_recv_context_t		tcp_recv_context;
	zbx_tcp_send_context_t		tcp_send_context;
	zbx_zabbix_agent_step_t		step;
	char				*server_name;
	char				*tls_arg1;
	char				*tls_arg2;
	unsigned char			tls_connect;
	const char			*config_source_ip;
	int				config_timeout;
	zbx_async_resolve_reverse_dns_t	resolve_reverse_dns;
	zbx_async_rdns_step_t		rdns_step;
	char				*reverse_dns;
	struct zbx_json			j;
}
zbx_agent_context;

int	zbx_async_check_agent(zbx_dc_item_t *item, AGENT_RESULT *result,  zbx_async_task_clear_cb_t clear_cb,
		void *arg, void *arg_action, struct event_base *base, zbx_channel_t *channel,
		struct evdns_base *dnsbase, const char *config_source_ip,
		zbx_async_resolve_reverse_dns_t resolve_reverse_dns);
void	zbx_async_check_agent_clean(zbx_agent_context *agent_context);

#endif
