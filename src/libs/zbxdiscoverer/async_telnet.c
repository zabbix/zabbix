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

#include "async_telnet.h"

#include "zbxpoller.h"
#include "zbxtimekeeper.h"
#include "zbxcomms.h"
#include "zbxself.h"

ZBX_VECTOR_IMPL(telnet_recv, unsigned char)

static const char	*get_telnet_step_string(zbx_zabbix_telnet_step_t step)
{
	switch (step)
	{
		case ZABBIX_TELNET_STEP_CONNECT_INIT:
			return "init";
		case ZABBIX_TELNET_STEP_CONNECT_WAIT:
			return "connect";
		case ZABBIX_TELNET_STEP_SEND:
			return "send";
		case ZABBIX_TELNET_STEP_RECV:
			return "receive";
		default:
			return "unknown";
	}
}

static char	telnet_lastchar(const char *buf, int offset)
{
	while (0 < offset)
	{
		offset--;
		if (' ' != buf[offset])
			return buf[offset];
	}

	return '\0';
}

static zbx_telnet_protocol_step_t	async_telnet_recv(zbx_telnet_context_t *telnet_context, short *events)
{
#define CMD_IAC		255
#define CMD_WILL	251
#define CMD_WONT	252
#define CMD_DO		253
#define CMD_DONT	254
#define OPT_SGA		3

	ssize_t			nbytes;
	zbx_socket_t		*s = &telnet_context->s;
	zbx_recv_context_t	*r = &telnet_context->recv_context;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s():%d", __func__, r->state);

	if (ZABBIX_TELNET_PROTOCOL_SEND == r->state)
		r->state = ZABBIX_TELNET_PROTOCOL_RECV_FIRST;

	if (0 == r->buff.values_num)
		zbx_vector_telnet_recv_reserve(&r->buff, 255);

	do
	{
		switch (r->state)
		{
			case ZABBIX_TELNET_PROTOCOL_RECV_FIRST:
				if (1 > (nbytes = zbx_tcp_read(s, (char *)&r->c1, 1, events)))
				{
					if (ZBX_PROTO_ERROR == nbytes && 0 == events)
						r->state = ZABBIX_TELNET_PROTOCOL_RECV_FAIL;

					break;
				}

				if (CMD_IAC != r->c1)
				{
					zbx_vector_telnet_recv_append(&r->buff, r->c1);
					break;
				}

				r->state = ZABBIX_TELNET_PROTOCOL_RECV_SECOND;
				ZBX_FALLTHROUGH;
			case ZABBIX_TELNET_PROTOCOL_RECV_SECOND:
				if (1 > (nbytes = zbx_tcp_read(s, (char *)&r->c2, 1, events)))
				{
					if (ZBX_PROTO_ERROR == nbytes && 0 == events)
						r->state = ZABBIX_TELNET_PROTOCOL_RECV_FAIL;

					break;
				}

				if (CMD_IAC == r->c2)
				{
					zbx_vector_telnet_recv_append(&r->buff, r->c2);
					r->state = ZABBIX_TELNET_PROTOCOL_RECV_FIRST;
					break;
				}

				if (CMD_WILL != r->c2 && CMD_WONT != r->c2 && CMD_DO != r->c2 && CMD_DONT != r->c2)
				{
					r->state = ZABBIX_TELNET_PROTOCOL_RECV_FIRST;
					break;
				}

				r->state = ZABBIX_TELNET_PROTOCOL_RECV_THIRD;
				ZBX_FALLTHROUGH;
			case ZABBIX_TELNET_PROTOCOL_RECV_THIRD:
				if (1 > (nbytes = zbx_tcp_read(s, (char *)&r->c3, 1, events)))
				{
					if (ZBX_PROTO_ERROR == nbytes && 0 == events)
						r->state = ZABBIX_TELNET_PROTOCOL_RECV_FAIL;

					break;
				}

				r->response[0] = CMD_IAC;

				if (CMD_WONT == r->c2)
					r->response[1] = CMD_DONT;	/* the only valid response */
				else if (CMD_DONT == r->c2)
					r->response[1] = CMD_WONT;	/* the only valid response */
				else if (OPT_SGA == r->c3)
					r->response[1] = (r->c2 == CMD_DO ? CMD_WILL : CMD_DO);
				else
					r->response[1] = (r->c2 == CMD_DO ? CMD_WONT : CMD_DONT);

				r->response[2] = r->c3;
				nbytes = 0;
				r->state = ZABBIX_TELNET_PROTOCOL_SEND;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				nbytes = 0;
				r->state = ZABBIX_TELNET_PROTOCOL_RECV_FAIL;
		}
	}
	while (0 < nbytes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() state:%d buff:%d", __func__, r->state, r->buff.values_num);

	return r->state;

#undef CMD_IAC
#undef CMD_WILL
#undef CMD_WONT
#undef CMD_DO
#undef CMD_DONT
#undef OPT_SGA
}

static int	telnet_task_process(short event, void *data, int *fd, const char *addr, char *dnserr,
		struct event *timeout_event)
{
#	define	SET_RESULT_SUCCEED								\
		SET_UI64_RESULT(&telnet_context->item.result, 1);				\
		telnet_context->item.ret = SUCCEED;						\
		zabbix_log(LOG_LEVEL_DEBUG, "%s() SUCCEED step '%s' event:%d key:%s", __func__,	\
				get_telnet_step_string(telnet_context->step), event,		\
				telnet_context->item.key);

#	define	SET_RESULT_FAIL(info)								\
		SET_UI64_RESULT(&telnet_context->item.result, 0);				\
		telnet_context->item.ret = FAIL;						\
		zabbix_log(LOG_LEVEL_DEBUG, "%s() FAIL:%s step '%s' event:%d key:%s", __func__,	\
			info, get_telnet_step_string(telnet_context->step), event,		\
			telnet_context->item.key);

	zbx_telnet_context_t		*telnet_context = (zbx_telnet_context_t *)data;
	zbx_poller_config_t		*poller_config = (zbx_poller_config_t *)telnet_context->arg_action;
	int				errnum = 0;
	socklen_t			optlen = sizeof(int);
	short				event_new = 0;
	zbx_async_task_state_t		state;
	zbx_telnet_protocol_step_t	rc;

	ZBX_UNUSED(dnserr);
	ZBX_UNUSED(timeout_event);

	if (NULL != poller_config && ZBX_PROCESS_STATE_IDLE == poller_config->state)
	{
		zbx_update_selfmon_counter(poller_config->info, ZBX_PROCESS_STATE_BUSY);
		poller_config->state = ZBX_PROCESS_STATE_BUSY;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() step '%s' event:%d itemid:" ZBX_FS_UI64 " addr:%s", __func__,
				get_telnet_step_string(telnet_context->step), event, telnet_context->item.itemid, addr);


	if (ZABBIX_ASYNC_STEP_REVERSE_DNS == telnet_context->rdns_step)
	{
		if (NULL != addr)
			telnet_context->reverse_dns = zbx_strdup(NULL, addr);

		goto stop;
	}

	if (0 != (event & EV_TIMEOUT))
	{
		SET_RESULT_FAIL("timeout");
		goto stop;
	}

	switch (telnet_context->step)
	{
		case ZABBIX_TELNET_STEP_CONNECT_INIT:
			/* initialization */
			zabbix_log(LOG_LEVEL_DEBUG, "%s() step '%s' event:%d itemid:" ZBX_FS_UI64 " [%s:%d]", __func__,
					get_telnet_step_string(telnet_context->step), event,
					telnet_context->item.itemid, addr, telnet_context->item.interface.port);

			if (SUCCEED != zbx_socket_connect(&telnet_context->s, SOCK_STREAM,
					telnet_context->config_source_ip, addr, telnet_context->item.interface.port,
					telnet_context->config_timeout))
			{
				telnet_context->item.ret = NETWORK_ERROR;
				SET_MSG_RESULT(&telnet_context->item.result, zbx_dsprintf(NULL, "net.tcp.service check"
						" failed during %s", get_telnet_step_string(telnet_context->step)));

				goto out;
			}

			telnet_context->step = ZABBIX_TELNET_STEP_CONNECT_WAIT;
			*fd = telnet_context->s.socket;

			return ZBX_ASYNC_TASK_WRITE;
		case ZABBIX_TELNET_STEP_CONNECT_WAIT:
			if (0 == getsockopt(telnet_context->s.socket, SOL_SOCKET, SO_ERROR, &errnum, &optlen) &&
					0 != errnum)
			{
				SET_RESULT_FAIL("connect");
				break;
			}

			telnet_context->step = ZABBIX_TELNET_STEP_RECV;
			telnet_context->recv_context.state = ZABBIX_TELNET_PROTOCOL_RECV_FIRST;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() step '%s' event:%d key:%s", __func__,
					get_telnet_step_string(telnet_context->step), event, telnet_context->item.key);

			return ZBX_ASYNC_TASK_READ;
		case ZABBIX_TELNET_STEP_SEND:
			zabbix_log(LOG_LEVEL_DEBUG, "%s() sending data for key:%s len:%d", __func__,
					telnet_context->item.key, (int)telnet_context->tcp_send_context.send_len);

			if (SUCCEED != zbx_tcp_send_context(&telnet_context->s, &telnet_context->tcp_send_context,
					&event_new))
			{
				if (ZBX_ASYNC_TASK_STOP != (
						state = zbx_async_poller_get_task_state_for_event(event_new)))
				{
					return (int)state;
				}

				SET_RESULT_FAIL("send");
				break;
			}

			telnet_context->step = ZABBIX_TELNET_STEP_RECV;
			ZBX_FALLTHROUGH;
		case ZABBIX_TELNET_STEP_RECV:
			zabbix_log(LOG_LEVEL_DEBUG, "%s() receiving data for key:%s", __func__,
					telnet_context->item.key);

			if (ZABBIX_TELNET_PROTOCOL_SEND == (rc = async_telnet_recv(telnet_context, &event_new)))
			{
				telnet_context->step = ZABBIX_TELNET_STEP_SEND;
				zbx_tcp_send_context_init((const char*)telnet_context->recv_context.response,
						sizeof(telnet_context->recv_context.response), 0, 0,
						&telnet_context->tcp_send_context);
				return ZBX_ASYNC_TASK_WRITE;
			}

			if (ZABBIX_TELNET_PROTOCOL_RECV_FAIL == rc)
			{
				SET_RESULT_FAIL("recv_fail");
				break;
			}
			else if (':' == telnet_lastchar((const char*)telnet_context->recv_context.buff.values,
					telnet_context->recv_context.buff.values_num))
			{
				SET_RESULT_SUCCEED;

				if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == telnet_context->resolve_reverse_dns)
				{
					telnet_context->rdns_step = ZABBIX_ASYNC_STEP_REVERSE_DNS;
					return ZBX_ASYNC_TASK_RESOLVE_REVERSE;
				}

				break;
			}

			if (ZBX_ASYNC_TASK_STOP != (
					state = zbx_async_poller_get_task_state_for_event(event_new)))
			{
				return (int)state;
			}

			SET_RESULT_FAIL("unknown");
			break;
	}
stop:
	zbx_tcp_close(&telnet_context->s);
out:
	zbx_tcp_send_context_clear(&telnet_context->tcp_send_context);

	return ZBX_ASYNC_TASK_STOP;

#	undef SET_RESULT_SUCCEED
#	undef SET_RESULT_FAIL
}

void	zbx_async_check_telnet_free(zbx_telnet_context_t *telnet_context)
{
	zbx_free(telnet_context->item.key_orig);
	zbx_free(telnet_context->item.key);
	zbx_free(telnet_context->reverse_dns);
	zbx_free_agent_result(&telnet_context->item.result);
	zbx_vector_telnet_recv_destroy(&telnet_context->recv_context.buff);
	zbx_free(telnet_context);
}

void	zbx_async_check_telnet(zbx_dc_item_t *item, zbx_async_task_clear_cb_t clear_cb, void *arg,
		void *arg_action, struct event_base *base, struct evdns_base *dnsbase, const char *config_source_ip,
		zbx_async_resolve_reverse_dns_t resolve_reverse_dns)
{
	zbx_telnet_context_t	*telnet_context = zbx_malloc(NULL, sizeof(zbx_telnet_context_t));

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s'", __func__, item->key,
			item->host.host, item->interface.addr);

	telnet_context->arg = arg;
	telnet_context->arg_action = arg_action;
	telnet_context->item.itemid = item->itemid;
	telnet_context->item.hostid = item->host.hostid;
	telnet_context->item.value_type = item->value_type;
	telnet_context->item.flags = item->flags;
	zbx_strlcpy(telnet_context->item.host, item->host.host, sizeof(telnet_context->item.host));
	telnet_context->item.interface = item->interface;
	telnet_context->item.interface.addr = (item->interface.addr == item->interface.dns_orig ?
			telnet_context->item.interface.dns_orig : telnet_context->item.interface.ip_orig);
	telnet_context->item.key_orig = zbx_strdup(NULL, item->key_orig);

	if (item->key != item->key_orig)
	{
		telnet_context->item.key = item->key;
		item->key = NULL;
	}
	else
		telnet_context->item.key = zbx_strdup(NULL, item->key);

	telnet_context->resolve_reverse_dns = resolve_reverse_dns;
	telnet_context->rdns_step = ZABBIX_ASYNC_STEP_DEFAULT;
	telnet_context->reverse_dns = NULL;

	telnet_context->config_source_ip = config_source_ip;
	telnet_context->config_timeout = item->timeout;
	telnet_context->server_name = NULL;
	zbx_init_agent_result(&telnet_context->item.result);
	zbx_socket_clean(&telnet_context->s);
	zbx_vector_telnet_recv_create(&telnet_context->recv_context.buff);
	zbx_tcp_send_context_init(NULL, 0, 0, 0, &telnet_context->tcp_send_context);

	telnet_context->step = ZABBIX_TELNET_STEP_CONNECT_INIT;

	zbx_async_poller_add_task(base, dnsbase, telnet_context->item.interface.addr, telnet_context,
			item->timeout + 1, telnet_task_process, clear_cb);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

