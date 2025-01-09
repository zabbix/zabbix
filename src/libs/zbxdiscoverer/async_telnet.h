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

#ifndef ZABBIX_ASYNC_TELNET_H_
#define ZABBIX_ASYNC_TELNET_H_

#include "zbxcacheconfig.h"
#include "zbxasyncpoller.h"
#include "zbxcomms.h"
#include "zbxalgo.h"

typedef enum
{
	ZABBIX_TELNET_STEP_CONNECT_INIT = 0,
	ZABBIX_TELNET_STEP_CONNECT_WAIT,
	ZABBIX_TELNET_STEP_SEND,
	ZABBIX_TELNET_STEP_RECV
}
zbx_zabbix_telnet_step_t;

typedef enum
{
	ZABBIX_TELNET_PROTOCOL_RECV_FIRST = 0,
	ZABBIX_TELNET_PROTOCOL_RECV_SECOND,
	ZABBIX_TELNET_PROTOCOL_RECV_THIRD,
	ZABBIX_TELNET_PROTOCOL_RECV_FAIL,
	ZABBIX_TELNET_PROTOCOL_SEND
}
zbx_telnet_protocol_step_t;

ZBX_VECTOR_DECL(telnet_recv, unsigned char)

typedef struct zbx_telnet_state
{
	zbx_telnet_protocol_step_t	state;
	unsigned char			c1;
	unsigned char			c2;
	unsigned char			c3;
	unsigned char			response[3];
	zbx_vector_telnet_recv_t	buff;
}
zbx_recv_context_t;

typedef struct zbx_telnet_context
{
	zbx_dc_item_context_t		item;
	void				*arg;
	void				*arg_action;
	zbx_socket_t			s;
	zbx_recv_context_t		recv_context;
	zbx_tcp_send_context_t		tcp_send_context;
	zbx_zabbix_telnet_step_t	step;
	char				*server_name;
	const char			*config_source_ip;
	int				config_timeout;
	zbx_async_resolve_reverse_dns_t	resolve_reverse_dns;
	zbx_async_rdns_step_t		rdns_step;
	char				*reverse_dns;
}
zbx_telnet_context_t;

void	zbx_async_check_telnet(zbx_dc_item_t *item, zbx_async_task_clear_cb_t clear_cb, void *arg,
		void *arg_action, struct event_base *base, struct evdns_base *dnsbase, const char *config_source_ip,
		zbx_async_resolve_reverse_dns_t resolve_reverse_dns);
void	zbx_async_check_telnet_free(zbx_telnet_context_t *agent_context);

#endif /* ZABBIX_ASYNC_TELNET_H_ */
