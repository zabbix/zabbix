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

#include "async_tcpsvc.h"

#include "zbxpoller.h"
#include "zbxtimekeeper.h"
#include "zbxcomms.h"
#include "zbxself.h"
#include "zbxsysinfo.h"
#include "zbx_discoverer_constants.h"

static const char	*get_tcpsvc_step_string(zbx_zabbix_tcpsvc_step_t step)
{
	switch (step)
	{
		case ZABBIX_TCPSVC_STEP_CONNECT_INIT:
			return "init";
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
	memset(context, 0, sizeof(zbx_tcp_send_context_t));

	switch (svc_type)
	{
	case SVC_SMTP:
	case SVC_FTP:
	case SVC_POP:
	case SVC_NNTP:
		context->data = "QUIT\r\n";
		break;
	case SVC_IMAP:
		context->data = "a1 LOGOUT\r\n";
		break;
	case SVC_SSH:
	case SVC_HTTP:
	case SVC_TCP:
		break;
	default:
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Error of unknown service:%u", svc_type));
		return FAIL;
	}

	if (NULL != context->data)
		context->send_len = strlen(context->data);

	if (0 == (flags & ZBX_TCP_PROTOCOL))
		return FAIL;

	return SUCCEED;
}

static int	tcpsvc_task_process(short event, void *data, int *fd, struct evutil_addrinfo **current_ai,
			const char *addr, char *dnserr, struct event *timeout_event)
{
#	define	SET_RESULT_SUCCEED								\
		SET_UI64_RESULT(&tcpsvc_context->item.result, 1);				\
		tcpsvc_context->item.ret = SUCCEED;						\
		zabbix_log(LOG_LEVEL_DEBUG, "%s() SUCCEED step '%s' event:%d key:%s", __func__,	\
				get_tcpsvc_step_string(tcpsvc_context->step), event,		\
				tcpsvc_context->item.key);

#	define	SET_RESULT_FAIL(info)								\
		SET_UI64_RESULT(&tcpsvc_context->item.result, 0);				\
		tcpsvc_context->item.ret = FAIL;						\
		zabbix_log(LOG_LEVEL_DEBUG, "%s() FAIL:%s step '%s' event:%d key:%s", __func__,	\
			info, get_tcpsvc_step_string(tcpsvc_context->step), event,		\
			tcpsvc_context->item.key);

	zbx_tcpsvc_context_t	*tcpsvc_context = (zbx_tcpsvc_context_t *)data;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)tcpsvc_context->arg_action;
	int			errnum = 0;
	socklen_t		optlen = sizeof(int);
	short			event_new;
	zbx_async_task_state_t	state;
	const char		*buf;

	ZBX_UNUSED(dnserr);
	ZBX_UNUSED(timeout_event);
	ZBX_UNUSED(current_ai);

	if (NULL != poller_config && ZBX_PROCESS_STATE_IDLE == poller_config->state)
	{
		zbx_update_selfmon_counter(poller_config->info, ZBX_PROCESS_STATE_BUSY);
		poller_config->state = ZBX_PROCESS_STATE_BUSY;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() step '%s' event:%d itemid:" ZBX_FS_UI64 " addr:%s", __func__,
				get_tcpsvc_step_string(tcpsvc_context->step), event, tcpsvc_context->item.itemid, addr);


	if (ZABBIX_ASYNC_STEP_REVERSE_DNS == tcpsvc_context->rdns_step)
	{
		if (NULL != addr)
			tcpsvc_context->reverse_dns = zbx_strdup(NULL, addr);

		goto stop;
	}

	if (0 != (event & EV_TIMEOUT))
	{
		SET_RESULT_FAIL("timeout");
		goto stop;
	}

	switch (tcpsvc_context->step)
	{
		case ZABBIX_TCPSVC_STEP_CONNECT_INIT:
			/* initialization */
			zabbix_log(LOG_LEVEL_DEBUG, "%s() step '%s' event:%d itemid:" ZBX_FS_UI64, __func__,
					get_tcpsvc_step_string(tcpsvc_context->step), event,
					tcpsvc_context->item.itemid);

			if (SUCCEED != zbx_socket_connect(&tcpsvc_context->s, SOCK_STREAM,
					tcpsvc_context->config_source_ip, addr, tcpsvc_context->item.interface.port,
					tcpsvc_context->config_timeout))
			{
				tcpsvc_context->item.ret = NETWORK_ERROR;
				SET_MSG_RESULT(&tcpsvc_context->item.result, zbx_dsprintf(NULL, "net.tcp.service check"
						" failed during %s", get_tcpsvc_step_string(tcpsvc_context->step)));

				goto out;
			}

			tcpsvc_context->step = ZABBIX_TCPSVC_STEP_CONNECT_WAIT;
			*fd = tcpsvc_context->s.socket;

			return ZBX_ASYNC_TASK_WRITE;
		case ZABBIX_TCPSVC_STEP_CONNECT_WAIT:
			/* sometimes error is not reported, so also validate that socket is writable */
			if ((0 == getsockopt(tcpsvc_context->s.socket, SOL_SOCKET, SO_ERROR, &errnum, &optlen) &&
					0 != errnum) || SUCCEED != zbx_socket_pollout(&tcpsvc_context->s, 0, NULL))
			{
				SET_RESULT_FAIL("connect");
				break;
			}

			if (NULL == tcpsvc_context->validate_func)
			{
				SET_RESULT_SUCCEED;

				if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == tcpsvc_context->resolve_reverse_dns)
				{
					tcpsvc_context->rdns_step = ZABBIX_ASYNC_STEP_REVERSE_DNS;
					return ZBX_ASYNC_TASK_RESOLVE_REVERSE;
				}

				break;
			}

			tcpsvc_context->step = ZABBIX_TCPSVC_STEP_RECV;
			tcpsvc_context->item.ret = NOTSUPPORTED;	/* preliminary init for recv loop */

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

			while (NULL != (buf = zbx_tcp_recv_context_line(&tcpsvc_context->s,
					&tcpsvc_context->tcp_recv_context, &event_new)))
			{
				int	val;

				val = tcpsvc_context->validate_func(tcpsvc_context, buf);

				if (SUCCEED == val)
				{
					tcpsvc_context->step = ZABBIX_TCPSVC_STEP_SEND;
					SET_RESULT_SUCCEED;
					return ZBX_ASYNC_TASK_WRITE;
				}

				if (FAIL == val)
				{
					SET_RESULT_FAIL("line_check");
					break;
				}
			}

			if (SUCCEED != tcpsvc_context->item.ret)
			{
				if (ZBX_ASYNC_TASK_STOP != (
						state = zbx_async_poller_get_task_state_for_event(event_new)))
				{
					return (int)state;
				}

				SET_RESULT_FAIL("unknown");
			}

			break;
		case ZABBIX_TCPSVC_STEP_SEND:
			zabbix_log(LOG_LEVEL_DEBUG, "%s() sending data for key:%s len:%d", __func__,
					tcpsvc_context->item.key, (int)tcpsvc_context->tcp_send_context.send_len);

			if (SUCCEED != zbx_tcp_send_context(&tcpsvc_context->s, &tcpsvc_context->tcp_send_context,
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

			tcpsvc_context->step = ZABBIX_TCPSVC_STEP_RECV;

			zbx_tcp_recv_context_init(&tcpsvc_context->s, &tcpsvc_context->tcp_recv_context,
					tcpsvc_context->item.flags);

			return ZBX_ASYNC_TASK_READ;
	}
stop:
	zbx_tcp_close(&tcpsvc_context->s);
out:
	zbx_tcp_send_context_clear(&tcpsvc_context->tcp_send_context);

	return ZBX_ASYNC_TASK_STOP;

#	undef SET_RESULT_SUCCEED
#	undef SET_RESULT_FAIL
}

void	zbx_async_check_tcpsvc_free(zbx_tcpsvc_context_t *tcpsvc_context)
{
	zbx_free(tcpsvc_context->item.key_orig);
	zbx_free(tcpsvc_context->item.key);
	zbx_free(tcpsvc_context->reverse_dns);
	zbx_free(tcpsvc_context->send_data);
	zbx_free_agent_result(&tcpsvc_context->item.result);
	zbx_free(tcpsvc_context);
}

static int	async_check_ssh_validate(const char *data, zbx_tcpsvc_context_t *context)
{
	int	major, minor, ret = FAIL;

	if (2 == sscanf(data, "SSH-%d.%d-%*s", &major, &minor))
	{
		context->send_data = zbx_dsprintf(context->send_data, "SSH-%d.%d-zabbix_agent\r\n", major, minor);
		context->tcp_send_context.data = context->send_data;
		context->tcp_send_context.send_len = strlen(context->send_data);
		ret = SUCCEED;
	}

	return ret;
}

static int	async_check_service_validate(zbx_tcpsvc_context_t *context, const char *data)
{
	if (SVC_SSH == context->svc_type)
		return NULL == data ? FAIL : async_check_ssh_validate(data, context);

	return zbx_check_service_validate(context->svc_type, data);
}

int	zbx_async_check_tcpsvc(zbx_dc_item_t *item, unsigned char svc_type, AGENT_RESULT *result,
		zbx_async_task_clear_cb_t clear_cb, void *arg, void *arg_action, struct event_base *base,
		struct evdns_base *dnsbase, const char *config_source_ip,
		zbx_async_resolve_reverse_dns_t resolve_reverse_dns)
{
	int			ret;
	zbx_tcpsvc_context_t	*tcpsvc_context = zbx_malloc(NULL, sizeof(zbx_tcpsvc_context_t));

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

	if (NOTSUPPORTED != async_check_service_validate(tcpsvc_context, NULL))
		tcpsvc_context->validate_func = async_check_service_validate;
	else
		tcpsvc_context->validate_func = NULL;

	tcpsvc_context->config_source_ip = config_source_ip;
	tcpsvc_context->config_timeout = item->timeout;
	tcpsvc_context->server_name = NULL;
	tcpsvc_context->send_data = NULL;
	zbx_init_agent_result(&tcpsvc_context->item.result);
	zbx_socket_clean(&tcpsvc_context->s);

	tcpsvc_context->step = ZABBIX_TCPSVC_STEP_CONNECT_INIT;

	if (SUCCEED == (ret = tcpsvc_send_context_init(tcpsvc_context->svc_type, ZBX_TCP_PROTOCOL,
			&tcpsvc_context->tcp_send_context, result)))
	{
		zbx_async_poller_add_task(base, dnsbase, tcpsvc_context->item.interface.addr, tcpsvc_context,
				item->timeout + 1, tcpsvc_task_process, clear_cb);
	}
	else
		zbx_async_check_tcpsvc_free(tcpsvc_context);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
