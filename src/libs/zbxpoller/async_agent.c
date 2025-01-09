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

#include "zbxpoller.h"

#include "zbxasyncpoller.h"
#include "zbxtimekeeper.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxcomms.h"
#include "zbxself.h"
#include "zbxagentget.h"
#include "zbxversion.h"
#include "zbxstr.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
#	include "zbxip.h"
#endif

static const char	*get_agent_step_string(zbx_zabbix_agent_step_t step)
{
	switch (step)
	{
		case ZABBIX_AGENT_STEP_CONNECT_INIT:
			return "init";
		case ZABBIX_AGENT_STEP_CONNECT_WAIT:
			return "connect";
		case ZABBIX_AGENT_STEP_TLS_WAIT:
			return "tls";
		case ZABBIX_AGENT_STEP_SEND:
			return "send";
		case ZABBIX_AGENT_STEP_RECV:
			return "receive";
		case ZABBIX_AGENT_STEP_RECV_CLOSE:
			return "receive close notify";
		default:
			return "unknown";
	}
}

static int	agent_task_process(short event, void *data, int *fd, const char *addr, char *dnserr,
		struct event *timeout_event)
{
	zbx_agent_context	*agent_context = (zbx_agent_context *)data;
	short			event_new = 0;
	zbx_async_task_state_t	state = ZBX_ASYNC_TASK_STOP;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)agent_context->arg_action;
	int			errnum = 0;
	socklen_t		optlen = sizeof(int);

	ZBX_UNUSED(fd);
	ZBX_UNUSED(timeout_event);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() step '%s' event:%d itemid:" ZBX_FS_UI64 " addr:%s", __func__,
				get_agent_step_string(agent_context->step), event, agent_context->item.itemid,
				ZBX_NULL2EMPTY_STR(addr));

	if (NULL != poller_config && ZBX_PROCESS_STATE_IDLE == poller_config->state)
	{
		zbx_update_selfmon_counter(poller_config->info, ZBX_PROCESS_STATE_BUSY);
		poller_config->state = ZBX_PROCESS_STATE_BUSY;
	}

	if (ZABBIX_ASYNC_STEP_REVERSE_DNS == agent_context->rdns_step)
	{
		if (NULL != addr)
			agent_context->reverse_dns = zbx_strdup(NULL, addr);

		goto stop;
	}

	if (0 != (event & EV_TIMEOUT))
	{
		agent_context->item.ret = TIMEOUT_ERROR;

		if (NULL != dnserr)
		{
			SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
					" failed: Cannot resolve address: %s", dnserr));
			return ZBX_ASYNC_TASK_STOP;
		}

		switch (agent_context->step)
		{
			case ZABBIX_AGENT_STEP_CONNECT_INIT:
				SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
						" failed: cannot initialize TCP connection to [[%s]:%hu]:"
						" timed out", agent_context->item.interface.addr,
						agent_context->item.interface.port));
				return ZBX_ASYNC_TASK_STOP;
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
			case ZABBIX_AGENT_STEP_RECV_CLOSE:
				SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
						" failed: cannot read close notify: timed out"));
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
		case ZABBIX_AGENT_STEP_CONNECT_INIT:
			/* initialization */
			agent_context->step = ZABBIX_AGENT_STEP_CONNECT_WAIT;

			if (ZBX_COMPONENT_VERSION(7, 0, 0) <= agent_context->item.version)
			{
				zbx_tcp_send_context_init(agent_context->j.buffer, agent_context->j.buffer_size, 0,
						ZBX_TCP_PROTOCOL, &agent_context->tcp_send_context);
			}
			else
			{
				zbx_tcp_send_context_init(agent_context->item.key, strlen(agent_context->item.key), 0,
					ZBX_TCP_PROTOCOL, &agent_context->tcp_send_context);
			}

			if (SUCCEED != zbx_socket_connect(&agent_context->s, SOCK_STREAM,
					agent_context->config_source_ip, addr, agent_context->item.interface.port,
					agent_context->config_timeout))
			{
				agent_context->item.ret = NETWORK_ERROR;
				SET_MSG_RESULT(&agent_context->item.result,
						zbx_dsprintf(NULL, "Get value from agent failed during %s",
						get_agent_step_string(agent_context->step)));
				goto out;
			}

			*fd = agent_context->s.socket;

			return ZBX_ASYNC_TASK_WRITE;
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
					if (ZBX_ASYNC_TASK_STOP != (
							state = zbx_async_poller_get_task_state_for_event(event_new)))
					{
						return state;
					}

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
				if (ZBX_ASYNC_TASK_STOP != (
						state = zbx_async_poller_get_task_state_for_event(event_new)))
				{
					return state;
				}

				SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent"
						" failed: cannot send: %s", zbx_socket_strerror()));
				agent_context->item.ret = NETWORK_ERROR;
				break;
			}

			agent_context->step = ZABBIX_AGENT_STEP_RECV;
			zbx_tcp_recv_context_init(&agent_context->s, &agent_context->tcp_recv_context,
					agent_context->item.flags);

			return ZBX_ASYNC_TASK_READ;
		case ZABBIX_AGENT_STEP_RECV_CLOSE:
			if (ZBX_PROTO_ERROR == zbx_tcp_read_close_notify(&agent_context->s, 0, &event_new))
			{
				if (ZBX_ASYNC_TASK_STOP != (state = zbx_async_poller_get_task_state_for_event(event_new)))
					return state;

				zabbix_log(LOG_LEVEL_DEBUG, "cannot gracefully close connection: %s", zbx_socket_strerror());
			}
			ZBX_FALLTHROUGH;
		case ZABBIX_AGENT_STEP_RECV:
			if (ZABBIX_AGENT_STEP_RECV == agent_context->step)
			{
				if (FAIL == zbx_tcp_recv_context(&agent_context->s, &agent_context->tcp_recv_context,
					agent_context->item.flags, &event_new))
				{
					if (ZBX_ASYNC_TASK_STOP != (state = zbx_async_poller_get_task_state_for_event(event_new)))
						return state;

					SET_MSG_RESULT(&agent_context->item.result, zbx_dsprintf(NULL, "Get value from agent failed:"
							" cannot read response: %s", zbx_socket_strerror()));
					agent_context->item.ret = NETWORK_ERROR;
					break;
				}

				if (SUCCEED == zbx_tls_used(&agent_context->s))
				{
					agent_context->step = ZABBIX_AGENT_STEP_RECV_CLOSE;
					return ZBX_ASYNC_TASK_READ;
				}
			}

			if (FAIL == (agent_context->item.ret = zbx_agent_handle_response(
					agent_context->s.buffer, agent_context->s.read_bytes,
					agent_context->s.read_bytes + agent_context->tcp_recv_context.offset,
					agent_context->item.interface.addr, &agent_context->item.result,
					&agent_context->item.version)))
			{
				/* retry with other protocol */
				agent_context->step = ZABBIX_AGENT_STEP_CONNECT_INIT;
			}

			if (ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES == agent_context->resolve_reverse_dns &&
					SUCCEED == agent_context->item.ret)
			{
				agent_context->rdns_step = ZABBIX_ASYNC_STEP_REVERSE_DNS;
				return ZBX_ASYNC_TASK_RESOLVE_REVERSE;
			}

			break;
	}
stop:
	zbx_tcp_close(&agent_context->s);
out:
	zbx_tcp_send_context_clear(&agent_context->tcp_send_context);
	if (ZABBIX_AGENT_STEP_CONNECT_INIT == agent_context->step)
		return agent_task_process(0, data, fd, addr, dnserr, NULL);

	return ZBX_ASYNC_TASK_STOP;
}

void	zbx_async_check_agent_clean(zbx_agent_context *agent_context)
{
	zbx_json_free(&agent_context->j);
	zbx_free(agent_context->item.key_orig);
	zbx_free(agent_context->item.key);
	zbx_free(agent_context->tls_arg1);
	zbx_free(agent_context->tls_arg2);
	zbx_free(agent_context->reverse_dns);
	zbx_free_agent_result(&agent_context->item.result);
}

int	zbx_async_check_agent(zbx_dc_item_t *item, AGENT_RESULT *result,  zbx_async_task_clear_cb_t clear_cb,
		void *arg, void *arg_action, struct event_base *base, struct evdns_base *dnsbase,
		const char *config_source_ip, zbx_async_resolve_reverse_dns_t resolve_reverse_dns)
{
	zbx_agent_context	*agent_context = zbx_malloc(NULL, sizeof(zbx_agent_context));
	int			ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s'  conn:'%s'", __func__, item->key,
			item->host.host, item->interface.addr, zbx_tcp_connection_type_name(item->host.tls_connect));

	zbx_json_init(&agent_context->j, ZBX_JSON_STAT_BUF_LEN);
	agent_context->arg = arg;
	agent_context->arg_action = arg_action;
	agent_context->item.itemid = item->itemid;
	agent_context->item.hostid = item->host.hostid;
	agent_context->item.value_type = item->value_type;
	agent_context->item.flags = item->flags;
	agent_context->item.interface = item->interface;
	agent_context->item.interface.addr = (item->interface.addr == item->interface.dns_orig ?
			agent_context->item.interface.dns_orig : agent_context->item.interface.ip_orig);
	agent_context->item.key_orig = zbx_strdup(NULL, item->key_orig);

	if (item->key != item->key_orig)
	{
		agent_context->item.key = item->key;
		item->key = NULL;
	}
	else
		agent_context->item.key = zbx_strdup(NULL, item->key);

	agent_context->resolve_reverse_dns = resolve_reverse_dns;
	agent_context->rdns_step = ZABBIX_ASYNC_STEP_DEFAULT;
	agent_context->reverse_dns = NULL;

	agent_context->tls_connect = item->host.tls_connect;
	zbx_strlcpy(agent_context->item.host, item->host.host, sizeof(agent_context->item.host));

	agent_context->config_source_ip = config_source_ip;

	zbx_init_agent_result(&agent_context->item.result);

	agent_context->config_timeout = item->timeout;

	agent_context->item.version = item->interface.version;

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

	if (ZBX_COMPONENT_VERSION(7, 0, 0) <= agent_context->item.version)
		zbx_agent_prepare_request(&agent_context->j, agent_context->item.key, item->timeout);

	agent_context->step = ZABBIX_AGENT_STEP_CONNECT_INIT;

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
