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
#include "async_agent.h"
#include "zbxcommon.h"
#include "zbxcomms.h"
#include "zbxip.h"
#include "zbxself.h"
#include "zbxsysinfo.h"
#include "async_poller.h"
#include "zbxpoller.h"

static const char	*get_agent_step_string(zbx_zabbix_agent_step_t step)
{
	switch (step)
	{
		case ZABBIX_AGENT_STEP_CONNECT_WAIT:
			return "connect";
		case ZABBIX_AGENT_STEP_TLS_WAIT:
			return "tls";
		case ZABBIX_AGENT_STEP_SEND:
			return "send";
		case ZABBIX_AGENT_STEP_RECV:
			return "receive";
		default:
			return "unknown";
	}
}

static zbx_async_task_state_t	get_task_state_for_event(short event)
{
	if (POLLIN & event)
		return ZBX_ASYNC_TASK_READ;

	if (POLLOUT & event)
		return ZBX_ASYNC_TASK_WRITE;

	return ZBX_ASYNC_TASK_STOP;
}

static int	agent_task_process(short event, void *data, int *fd, const char *addr, char *dnserr)
{
	zbx_agent_context	*agent_context = (zbx_agent_context *)data;
	ssize_t			received_len;
	short			event_new;
	zbx_async_task_state_t	state;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)agent_context->arg_action;
	int			errnum = 0;
	socklen_t		optlen = sizeof(int);

	ZBX_UNUSED(fd);

	if (NULL != poller_config && ZBX_PROCESS_STATE_IDLE == poller_config->state)
	{
		zbx_update_selfmon_counter(poller_config->info, ZBX_PROCESS_STATE_BUSY);
		poller_config->state = ZBX_PROCESS_STATE_BUSY;
	}

	if (0 == event)
	{
		/* initialization */
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() step '%s' event:%d itemid:" ZBX_FS_UI64, __func__,
				get_agent_step_string(agent_context->step), event, agent_context->item.itemid);

		if (SUCCEED != zbx_socket_connect(&agent_context->s, SOCK_STREAM, agent_context->config_source_ip,
				addr, agent_context->item.interface.port, agent_context->config_timeout))
		{
			agent_context->item.ret = NETWORK_ERROR;
			SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent failed"
					" during %s", get_agent_step_string(agent_context->step)));
			goto stop;
		}

		*fd = agent_context->s.socket;

		return ZBX_ASYNC_TASK_WRITE;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() step '%s' event:%d itemid:" ZBX_FS_UI64, __func__,
				get_agent_step_string(agent_context->step), event, agent_context->item.itemid);
	}


	if (0 != (event & EV_TIMEOUT))
	{
		agent_context->item.ret = TIMEOUT_ERROR;

		if (NULL != dnserr)
		{
			SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
					" failed: Cannot resolve address: %s", dnserr));
			goto stop;
		}

		switch (agent_context->step)
		{
			case ZABBIX_AGENT_STEP_CONNECT_WAIT:
				SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
						" failed: cannot establish TCP connection to [[%s]:%hu]:"
						" timed out", agent_context->item.interface.addr,
						agent_context->item.interface.port));
				break;
			case ZABBIX_AGENT_STEP_TLS_WAIT:
				SET_MSG_RESULT(&agent_context->item.result,
						zbx_dsprintf(NULL, "Get value from agent failed: TCP successful, cannot"
						" establish TLS to [[%s]:%hu]: timed out",
						agent_context->item.interface.addr,
						agent_context->item.interface.port));
				break;
			case ZABBIX_AGENT_STEP_RECV:
				SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
						" failed: cannot read response: timed out"));
				break;
			case ZABBIX_AGENT_STEP_SEND:
				SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
						" failed: cannot send: timed out"));
				break;
		}

		goto stop;
	}

	switch (agent_context->step)
	{
		case ZABBIX_AGENT_STEP_CONNECT_WAIT:
			if (0 == getsockopt(agent_context->s.socket, SOL_SOCKET, SO_ERROR, &errnum, &optlen) &&
					0 != errnum)
			{
				SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
						" failed: Cannot establish TCP connection to [[%s]:%hu]: %s",
						agent_context->item.interface.addr, agent_context->item.interface.port,
						zbx_strerror(errnum)));
				agent_context->item.ret = NETWORK_ERROR;
				break;
			}

			agent_context->step = ZABBIX_AGENT_STEP_TLS_WAIT;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() step '%s' event:%d itemid:" ZBX_FS_UI64, __func__,
					get_agent_step_string(agent_context->step), event,
					agent_context->item.itemid);
			ZBX_FALLTHROUGH;
		case ZABBIX_AGENT_STEP_TLS_WAIT:
			if (ZBX_TCP_SEC_TLS_CERT == agent_context->tls_connect ||
					ZBX_TCP_SEC_TLS_PSK == agent_context->tls_connect)
			{
				char	*error = NULL;

				if (SUCCEED != zbx_socket_tls_connect(&agent_context->s, agent_context->tls_connect,
						agent_context->tls_arg1, agent_context->tls_arg2,
						agent_context->server_name, &event_new, &error))
				{
					if (ZBX_ASYNC_TASK_STOP != (state = get_task_state_for_event(event_new)))
						return state;

					SET_MSG_RESULT(&agent_context->item.result,
							zbx_dsprintf(NULL, "Get value from agent failed:"
							" TCP successful, cannot establish TLS to [[%s]:%hu]: %s",
							agent_context->item.interface.addr,
							agent_context->item.interface.port, error));
					zbx_free(error);
					agent_context->item.ret = NETWORK_ERROR;
					break;
				}
			}

			agent_context->step = ZABBIX_AGENT_STEP_SEND;
			zabbix_log(LOG_LEVEL_DEBUG, "%s() step '%s' event:%d itemid:" ZBX_FS_UI64, __func__,
					get_agent_step_string(agent_context->step), event,
					agent_context->item.itemid);
			ZBX_FALLTHROUGH;
		case ZABBIX_AGENT_STEP_SEND:
			zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s] itemid:" ZBX_FS_UI64, agent_context->item.key,
					agent_context->item.itemid);

			if (SUCCEED != zbx_tcp_send_context(&agent_context->s, &agent_context->tcp_send_context,
					&event_new))
			{
				if (ZBX_ASYNC_TASK_STOP != (state = get_task_state_for_event(event_new)))
					return state;

				SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
						" failed: cannot send: %s", zbx_socket_strerror()));
				agent_context->item.ret = NETWORK_ERROR;
				break;
			}

			agent_context->step = ZABBIX_AGENT_STEP_RECV;
			zbx_tcp_recv_context_init(&agent_context->s, &agent_context->tcp_recv_context,
					agent_context->item.flags);

			return ZBX_ASYNC_TASK_READ;
		case ZABBIX_AGENT_STEP_RECV:
			if (FAIL != (received_len = zbx_tcp_recv_context(&agent_context->s,
					&agent_context->tcp_recv_context, agent_context->item.flags, &event_new)))
			{
				agent_context->item.ret = SUCCEED;
				zbx_agent_handle_response(&agent_context->s, received_len, &agent_context->item.ret,
						agent_context->item.interface.addr, &agent_context->item.result);

				break;
			}

			if (ZBX_ASYNC_TASK_STOP != (state = get_task_state_for_event(event_new)))
				return state;

			SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent failed:"
					" cannot read response: %s", zbx_socket_strerror()));
			agent_context->item.ret = NETWORK_ERROR;
			break;
	}
stop:
	zbx_tcp_send_context_clear(&agent_context->tcp_send_context);
	zbx_tcp_close(&agent_context->s);

	return ZBX_ASYNC_TASK_STOP;
}

void	zbx_async_check_agent_clean(zbx_agent_context *agent_context)
{
	zbx_free(agent_context->item.key_orig);
	zbx_free(agent_context->item.key);
	zbx_free(agent_context->tls_arg1);
	zbx_free(agent_context->tls_arg2);
	zbx_free_agent_result(&agent_context->item.result);
}

int	zbx_async_check_agent(zbx_dc_item_t *item, AGENT_RESULT *result,  zbx_async_task_clear_cb_t clear_cb,
		void *arg, void *arg_action, struct event_base *base, struct evdns_base *dnsbase,
		const char *config_source_ip)
{
	zbx_agent_context	*agent_context = zbx_malloc(NULL, sizeof(zbx_agent_context));
	int			ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s'  conn:'%s'", __func__, item->key,
			item->host.host, item->interface.addr, zbx_tcp_connection_type_name(item->host.tls_connect));

	agent_context->arg = arg;
	agent_context->arg_action = arg_action;
	agent_context->item.itemid = item->itemid;
	agent_context->item.hostid = item->host.hostid;
	agent_context->item.value_type = item->value_type;
	agent_context->item.flags = item->flags;
	agent_context->item.interface = item->interface;
	agent_context->item.interface.addr = (item->interface.addr == item->interface.dns_orig ?
			agent_context->item.interface.dns_orig : agent_context->item.interface.ip_orig);
	agent_context->item.key = item->key;
	agent_context->item.key_orig = zbx_strdup(NULL, item->key_orig);
	item->key = NULL;
	agent_context->tls_connect = item->host.tls_connect;
	zbx_strlcpy(agent_context->item.host, item->host.host, sizeof(agent_context->item.host));

	agent_context->config_source_ip = config_source_ip;

	zbx_init_agent_result(&agent_context->item.result);

	agent_context->config_timeout = item->timeout;

	switch (agent_context->tls_connect)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			agent_context->tls_arg1 = NULL;
			agent_context->tls_arg2 = NULL;
			break;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			agent_context->tls_arg1 = zbx_strdup(NULL, item->host.tls_issuer);
			agent_context->tls_arg2 = zbx_strdup(NULL, item->host.tls_subject);
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			agent_context->tls_arg1 = zbx_strdup(NULL, item->host.tls_psk_identity);
			agent_context->tls_arg2 = zbx_strdup(NULL, item->host.tls_psk);
			break;
#else
		case ZBX_TCP_SEC_TLS_CERT:
		case ZBX_TCP_SEC_TLS_PSK:
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "A TLS connection is configured to be used with agent"
					" but support for TLS was not compiled in"));
			ret = CONFIG_ERROR;
			agent_context->tls_arg1 = NULL;
			agent_context->tls_arg2 = NULL;
			goto out;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid TLS connection parameters."));
			ret = CONFIG_ERROR;
			agent_context->tls_arg1 = NULL;
			agent_context->tls_arg2 = NULL;
			goto out;
	}
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (SUCCEED != zbx_is_ip(agent_context->item.interface.addr))
		agent_context->server_name = agent_context->item.interface.addr;
	else
		agent_context->server_name = NULL;
#endif
	zbx_socket_clean(&agent_context->s);
	zbx_tcp_send_context_init(agent_context->item.key, strlen(agent_context->item.key), (size_t)item->timeout,
		ZBX_TCP_PROTOCOL, &agent_context->tcp_send_context);

	agent_context->step = ZABBIX_AGENT_STEP_CONNECT_WAIT;

	zbx_async_poller_add_task(base, dnsbase, agent_context->item.interface.addr, agent_context, item->timeout + 1,
			agent_task_process, clear_cb);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(SUCCEED));

	return SUCCEED;
out:
	zbx_async_check_agent_clean(agent_context);
	zbx_free(agent_context);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
