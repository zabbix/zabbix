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


#include "async_tcpsvc.h"
#include "zbxcommon.h"
#include "zbxcomms.h"
#include "zbxip.h"
#include "zbxself.h"
#include "zbxsysinfo.h"
#include "../poller/async_poller.h"
#include "zbxpoller.h"
#include "zbx_discoverer_constants.h"

static const char	*get_tcpsvc_step_string(zbx_zabbix_tcpsvc_step_t step)
{
	switch (step)
	{
		case ZABBIX_TCPSVC_STEP_CONNECT_WAIT:
			return "connect";
		case ZABBIX_TCPSVC_STEP_SEND:
			return "send";
		case ZABBIX_TCPSVC_STEP_RECV:
			return "receive";
		default:
			return "unknown";
	}
}

static int	tcpsvc_send_context_init(const unsigned char svc_type, unsigned char flags,
		zbx_tcp_send_context_t *context, AGENT_RESULT *result)
{
	const char	*data;

	switch (svc_type)
	{
	case SVC_SMTP:
	case SVC_FTP:
	case SVC_POP:
	case SVC_NNTP:
		data = "QUIT\r\n";
		break;
	case SVC_IMAP:
		data = "a1 LOGOUT\r\n";
		break;
	case SVC_HTTP:
	case SVC_TCP:
		data = "";
		break;
	default:
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Error of unknown service:%u", svc_type));
		return FAIL;
	}

	context->compressed_data = NULL;
	context->written = 0;
	context->written_header = 0;
	context->header_len = 0;
	*context->header_buf = '\0';

	context->data = data;
	context->send_len = strlen(context->data);

	if (0 == (flags & ZBX_TCP_PROTOCOL))
		return SUCCEED;

	context->header_len = 0;

	return SUCCEED;
}

static ssize_t	tcpsvc_recv_context_raw(zbx_socket_t *s, zbx_tcp_recv_context_t *context, short *events)
{
	ssize_t	nbytes;
	size_t	allocated = 8 * ZBX_STAT_BUF_LEN;

	if (NULL != events)
		*events = 0;

	while (0 != (nbytes = zbx_tcp_read(s, s->buf_stat + context->buf_stat_bytes,
			sizeof(s->buf_stat) - context->buf_stat_bytes, events)))
	{
		if (ZBX_PROTO_ERROR == nbytes)
		{
			if (ZBX_ASYNC_TASK_STOP == zbx_async_poller_get_task_state_for_event(*events))
				return FAIL;
			else
				break;
		}

		if (ZBX_BUF_TYPE_STAT == s->buf_type)
			context->buf_stat_bytes += (size_t)nbytes;
		else
		{
			if (context->buf_dyn_bytes + (size_t)nbytes >= allocated)
			{
				while (context->buf_dyn_bytes + (size_t)nbytes >= allocated)
					allocated *= 2;
				s->buffer = (char *)zbx_realloc(s->buffer, allocated);
			}

			memcpy(s->buffer + context->buf_dyn_bytes, s->buf_stat, (size_t)nbytes);
			context->buf_dyn_bytes += (size_t)nbytes;
		}

		if (context->buf_stat_bytes + context->buf_dyn_bytes >= context->expected_len)
			break;

		if (sizeof(s->buf_stat) == context->buf_stat_bytes)
		{
			s->buf_type = ZBX_BUF_TYPE_DYN;
			s->buffer = (char *)zbx_malloc(NULL, allocated);
			context->buf_dyn_bytes = sizeof(s->buf_stat);
			context->buf_stat_bytes = 0;
			memcpy(s->buffer, s->buf_stat, sizeof(s->buf_stat));
		}
	}

	if (ZBX_BUF_TYPE_DYN == s->buf_type)
	{
		s->read_bytes = context->buf_stat_bytes + context->buf_dyn_bytes;
		s->buffer[s->read_bytes] = '\0';
	}
	else
		s->buf_stat[context->buf_stat_bytes] = '\0';

	return (ssize_t)(context->buf_stat_bytes + context->buf_dyn_bytes);
}

static int	tcpsvc_task_process(short event, void *data, int *fd, const char *addr, char *dnserr)
{
	zbx_tcpsvc_context	*tcpsvc_context = (zbx_tcpsvc_context *)data;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)tcpsvc_context->arg_action;
	int			errnum = 0;
	ssize_t			received_len;
	socklen_t		optlen = sizeof(int);
	short			event_new;
	zbx_async_task_state_t	state;


	ZBX_UNUSED(fd);
	ZBX_UNUSED(dnserr);

	if (NULL != poller_config && ZBX_PROCESS_STATE_IDLE == poller_config->state)
	{
		zbx_update_selfmon_counter(poller_config->info, ZBX_PROCESS_STATE_BUSY);
		poller_config->state = ZBX_PROCESS_STATE_BUSY;
	}

	if (ZABBIX_ASYNC_STEP_REVERSE_DNS == tcpsvc_context->rdns_step)
	{
		if (NULL != addr)
			tcpsvc_context->reverse_dns = zbx_strdup(NULL, addr);

		goto stop;
	}

	if (0 == event)
	{
		/* initialization */
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() step '%s' event:%d itemid:" ZBX_FS_UI64, __func__,
				get_tcpsvc_step_string(tcpsvc_context->step), event, tcpsvc_context->item.itemid);

		if (SUCCEED != zbx_socket_connect(&tcpsvc_context->s, SOCK_STREAM, tcpsvc_context->config_source_ip,
				addr, tcpsvc_context->item.interface.port, tcpsvc_context->config_timeout))
		{
			tcpsvc_context->item.ret = NETWORK_ERROR;
			SET_MSG_RESULT(&tcpsvc_context->item.result, zbx_dsprintf(NULL, "net.tcp.service check failed"
					" during %s", get_tcpsvc_step_string(tcpsvc_context->step)));
			goto stop;
		}

		*fd = tcpsvc_context->s.socket;

		return ZBX_ASYNC_TASK_READ;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() step '%s' event:%d itemid:" ZBX_FS_UI64, __func__,
				get_tcpsvc_step_string(tcpsvc_context->step), event, tcpsvc_context->item.itemid);
	}


	if (0 != (event & EV_TIMEOUT))
	{
		SET_UI64_RESULT(&tcpsvc_context->item.result, 0);
		tcpsvc_context->item.ret = SUCCEED;
		goto stop;
	}

	switch (tcpsvc_context->step)
	{
		case ZABBIX_TCPSVC_STEP_CONNECT_WAIT:
			if (0 == getsockopt(tcpsvc_context->s.socket, SOL_SOCKET, SO_ERROR, &errnum, &optlen) &&
					0 != errnum)
			{
				SET_UI64_RESULT(&tcpsvc_context->item.result, 0);
				tcpsvc_context->item.ret = SUCCEED;
				break;
			}

			if (0 == tcpsvc_context->tcp_send_context.send_len)
			{
				SET_UI64_RESULT(&tcpsvc_context->item.result, 1);
				tcpsvc_context->item.ret = SUCCEED;

				if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == tcpsvc_context->resolve_reverse_dns)
				{
					tcpsvc_context->rdns_step = ZABBIX_ASYNC_STEP_REVERSE_DNS;
					return ZBX_ASYNC_TASK_RESOLVE_REVERSE;
				}

				break;
			}

			tcpsvc_context->step = ZABBIX_TCPSVC_STEP_RECV;

			zbx_tcp_recv_context_init(&tcpsvc_context->s, &tcpsvc_context->tcp_recv_context,
					tcpsvc_context->item.flags);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() step '%s' event:%d key:%s", __func__,
					get_tcpsvc_step_string(tcpsvc_context->step), event, tcpsvc_context->item.key);

			return ZBX_ASYNC_TASK_READ;
		case ZABBIX_TCPSVC_STEP_RECV:
			zabbix_log(LOG_LEVEL_DEBUG, "%s() receiving data for key:%s where item.ret:%s", __func__,
					tcpsvc_context->item.key, zbx_result_string(tcpsvc_context->item.ret));

			if (SUCCEED == tcpsvc_context->item.ret)
			{
				if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == tcpsvc_context->resolve_reverse_dns)
				{
					tcpsvc_context->rdns_step = ZABBIX_ASYNC_STEP_REVERSE_DNS;
					return ZBX_ASYNC_TASK_RESOLVE_REVERSE;
				}

				break;
			}

			if (FAIL == (received_len = tcpsvc_recv_context_raw(&tcpsvc_context->s,
					&tcpsvc_context->tcp_recv_context, &event_new)))
			{
				if (ZBX_ASYNC_TASK_STOP != (
						state = zbx_async_poller_get_task_state_for_event(event_new)))
				{
					return state;
				}

				SET_UI64_RESULT(&tcpsvc_context->item.result, 0);
				tcpsvc_context->item.ret = SUCCEED;
				break;
			}

			if (0 == received_len || SUCCEED != tcpsvc_context->validate_func(
					tcpsvc_context->svc_type, tcpsvc_context->s.buffer))
			{
				SET_UI64_RESULT(&tcpsvc_context->item.result, 0);
				tcpsvc_context->item.ret = SUCCEED;
				break;
			}

			tcpsvc_context->item.ret = SUCCEED;
			SET_UI64_RESULT(&tcpsvc_context->item.result, 1);

			tcpsvc_context->step = ZABBIX_TCPSVC_STEP_SEND;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() step '%s' event:%d key:%s", __func__,
					get_tcpsvc_step_string(tcpsvc_context->step), event, tcpsvc_context->item.key);

			return ZBX_ASYNC_TASK_WRITE;
		case ZABBIX_TCPSVC_STEP_SEND:
			zabbix_log(LOG_LEVEL_DEBUG, "%s() sending data for key:%s len:%d", __func__,
					tcpsvc_context->item.key, (int)tcpsvc_context->tcp_send_context.send_len);

			if (SUCCEED != zbx_tcp_send_context(&tcpsvc_context->s, &tcpsvc_context->tcp_send_context,
					&event_new))
			{
				if (ZBX_ASYNC_TASK_STOP != (
						state = zbx_async_poller_get_task_state_for_event(event_new)))
				{
					return state;
				}

				SET_UI64_RESULT(&tcpsvc_context->item.result, 0);
				tcpsvc_context->item.ret = SUCCEED;
				break;
			}

			tcpsvc_context->step = ZABBIX_TCPSVC_STEP_RECV;

			zbx_tcp_recv_context_init(&tcpsvc_context->s, &tcpsvc_context->tcp_recv_context,
					tcpsvc_context->item.flags);

			return ZBX_ASYNC_TASK_READ;
	}
stop:
	zbx_tcp_send_context_clear(&tcpsvc_context->tcp_send_context);
	zbx_tcp_close(&tcpsvc_context->s);

	return ZBX_ASYNC_TASK_STOP;
}

void	zbx_async_check_tcpsvc_clean(zbx_tcpsvc_context *tcpsvc_context)
{
	zbx_free(tcpsvc_context->item.key_orig);
	zbx_free(tcpsvc_context->item.key);
	zbx_free(tcpsvc_context->reverse_dns);
	zbx_free_agent_result(&tcpsvc_context->item.result);
}

int	zbx_async_check_tcpsvc(zbx_dc_item_t *item, unsigned char svc_type, AGENT_RESULT *result,
		zbx_async_task_clear_cb_t clear_cb, void *arg, void *arg_action, struct event_base *base,
		struct evdns_base *dnsbase, const char *config_source_ip,
		zbx_async_resolve_reverse_dns_t resolve_reverse_dns)
{
	int			ret;
	zbx_tcpsvc_context	*tcpsvc_context = zbx_malloc(NULL, sizeof(zbx_tcpsvc_context));

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s'", __func__, item->key,
			item->host.host, item->interface.addr);

	ZBX_UNUSED(result);

	tcpsvc_context->arg = arg;
	tcpsvc_context->arg_action = arg_action;
	tcpsvc_context->item.itemid = item->itemid;
	tcpsvc_context->item.hostid = item->host.hostid;
	tcpsvc_context->item.value_type = item->value_type;
	tcpsvc_context->item.flags = item->flags;
	tcpsvc_context->svc_type = svc_type;
	tcpsvc_context->validate_func = zbx_check_service_validate;
	zbx_strlcpy(tcpsvc_context->item.host, item->host.host, sizeof(tcpsvc_context->item.host));
	tcpsvc_context->item.interface = item->interface;
	tcpsvc_context->item.interface.addr = (item->interface.addr == item->interface.dns_orig ?
			tcpsvc_context->item.interface.dns_orig : tcpsvc_context->item.interface.ip_orig);
	tcpsvc_context->item.key_orig = zbx_strdup(NULL, item->key_orig);

	if (item->key != item->key_orig)
	{
		tcpsvc_context->item.key = item->key;
		item->key = NULL;
	}
	else
		tcpsvc_context->item.key = zbx_strdup(NULL, item->key);

	tcpsvc_context->resolve_reverse_dns = resolve_reverse_dns;
	tcpsvc_context->rdns_step = ZABBIX_ASYNC_STEP_DEFAULT;
	tcpsvc_context->reverse_dns = NULL;

	tcpsvc_context->config_source_ip = config_source_ip;
	tcpsvc_context->config_timeout = item->timeout;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (SUCCEED != zbx_is_ip(tcpsvc_context->item.interface.addr))
		tcpsvc_context->server_name = tcpsvc_context->item.interface.addr;
	else
		tcpsvc_context->server_name = NULL;
#endif
	zbx_init_agent_result(&tcpsvc_context->item.result);
	zbx_socket_clean(&tcpsvc_context->s);

	tcpsvc_context->step = ZABBIX_TCPSVC_STEP_CONNECT_WAIT;

	if (SUCCEED == (ret = tcpsvc_send_context_init(tcpsvc_context->svc_type, ZBX_TCP_PROTOCOL,
			&tcpsvc_context->tcp_send_context, result)))
	{
		zbx_async_poller_add_task(base, dnsbase, tcpsvc_context->item.interface.addr, tcpsvc_context,
				item->timeout + 1, tcpsvc_task_process, clear_cb);
	}
	else
		zbx_async_check_tcpsvc_clean(tcpsvc_context);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
