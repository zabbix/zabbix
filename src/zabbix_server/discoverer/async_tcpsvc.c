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
		case ZABBIX_TCPSVC_STEP_TLS_WAIT:
			return "tls";
		case ZABBIX_TCPSVC_STEP_SEND:
			return "send";
		case ZABBIX_TCPSVC_STEP_RECV:
			return "receive";
		default:
			return "unknown";
	}
}

static int	tcpsvc_task_process(short event, void *data, int *fd, const char *addr, char *dnserr)
{
	zbx_tcpsvc_context	*tcpsvc_context = (zbx_tcpsvc_context *)data;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)tcpsvc_context->arg_action;
	int			errnum = 0;
	socklen_t		optlen = sizeof(int);

	ZBX_UNUSED(fd);

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

		return ZBX_ASYNC_TASK_WRITE;
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

	if (ZABBIX_TCPSVC_STEP_CONNECT_WAIT == tcpsvc_context->step)
	{
		if (0 == getsockopt(tcpsvc_context->s.socket, SOL_SOCKET, SO_ERROR, &errnum, &optlen) &&
				0 != errnum)
		{
			SET_UI64_RESULT(&tcpsvc_context->item.result, 0);
			tcpsvc_context->item.ret = SUCCEED;
			goto stop;
		}

		SET_UI64_RESULT(&tcpsvc_context->item.result, 1);
		tcpsvc_context->item.ret = SUCCEED;

		if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == tcpsvc_context->resolve_reverse_dns)
		{
			tcpsvc_context->rdns_step = ZABBIX_ASYNC_STEP_REVERSE_DNS;
			return ZBX_ASYNC_TASK_RESOLVE_REVERSE;
		}
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

	zbx_init_agent_result(&tcpsvc_context->item.result);

	tcpsvc_context->config_timeout = item->timeout;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (SUCCEED != zbx_is_ip(tcpsvc_context->item.interface.addr))
		tcpsvc_context->server_name = tcpsvc_context->item.interface.addr;
	else
		tcpsvc_context->server_name = NULL;
#endif

	zbx_socket_clean(&tcpsvc_context->s);
	zbx_tcp_send_context_init(tcpsvc_context->item.key, strlen(tcpsvc_context->item.key), (size_t)item->timeout,
		ZBX_TCP_PROTOCOL, &tcpsvc_context->tcp_send_context);

	tcpsvc_context->step = ZABBIX_TCPSVC_STEP_CONNECT_WAIT;

	zbx_async_poller_add_task(base, dnsbase, tcpsvc_context->item.interface.addr, tcpsvc_context, item->timeout + 1,
			tcpsvc_task_process, clear_cb);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(SUCCEED));

	return SUCCEED;
}
