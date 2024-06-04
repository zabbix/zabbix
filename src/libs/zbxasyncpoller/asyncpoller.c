/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxasyncpoller.h"
#include "zbxcommon.h"
#include "zbxcomms.h"

#ifdef HAVE_LIBEVENT
#include "zbxip.h"
#include <event2/util.h>
#include <event2/dns.h>
typedef struct
{
	void				*data;
	zbx_async_task_process_cb_t	process_cb;
	zbx_async_task_clear_cb_t	free_cb;
	struct event			*tx_event;
	struct event			*rx_event;
	struct event			*timeout_event;
	int				timeout;
	char				*error;
	struct evdns_base		*dnsbase;
	struct evutil_addrinfo		*ai;
	char				*address;
}
zbx_async_task_t;

static void	async_reverse_dns_event(int err, char type, int count, int ttl, void *addresses, void *arg);

static void	async_task_remove(zbx_async_task_t *task)
{
	task->free_cb(task->data);

	if (NULL != task->rx_event)
		event_free(task->rx_event);

	if (NULL != task->tx_event)
		event_free(task->tx_event);

	if (NULL != task->timeout_event)
		event_free(task->timeout_event);

	if (NULL != task->ai)
		evutil_freeaddrinfo(task->ai);

	zbx_free(task->address);
	zbx_free(task->error);
	zbx_free(task);
}

static const char	*task_state_to_str(zbx_async_task_state_t task_state)
{
	switch (task_state)
	{
		case ZBX_ASYNC_TASK_WRITE:
			return "ZBX_ASYNC_TASK_WRITE";
		case ZBX_ASYNC_TASK_READ:
			return "ZBX_ASYNC_TASK_READ";
		case ZBX_ASYNC_TASK_STOP:
			return "ZBX_ASYNC_TASK_STOP";
		case ZBX_ASYNC_TASK_RESOLVE_REVERSE:
			return "ZBX_ASYNC_TASK_RESOLVE_REVERSE";
		default:
			return "unknown";
	}
}

static void	async_event(evutil_socket_t fd, short what, void *arg)
{
	zbx_async_task_t	*task = (zbx_async_task_t *)arg;
	int			ret, fd_in = fd;
	struct event_base	*ev;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = task->process_cb(what, task->data, &fd, task->address, task->error);

	switch (ret)
	{
		case ZBX_ASYNC_TASK_STOP:
			async_task_remove(task);
			break;
		case ZBX_ASYNC_TASK_RESOLVE_REVERSE:
			event_free(task->timeout_event);
			task->timeout_event = NULL;

			if (AF_INET == task->ai->ai_addr->sa_family)
			{
				const struct sockaddr_in	*sin = (const struct sockaddr_in *) (void *)task->ai->ai_addr;

				evdns_base_resolve_reverse(task->dnsbase, &sin->sin_addr, 0, async_reverse_dns_event,
						task);
			}
			else if (AF_INET6 == task->ai->ai_addr->sa_family)
			{
				const struct sockaddr_in6	*sin6 = (const struct sockaddr_in6 *) (void *)task->ai->ai_addr;

				evdns_base_resolve_reverse_ipv6(task->dnsbase, &sin6->sin6_addr, 0,
						async_reverse_dns_event, task);
			}
			break;
		case ZBX_ASYNC_TASK_READ:
			if (fd_in != fd && NULL != task->tx_event)
			{
				event_free(task->tx_event);
				task->tx_event = NULL;
			}

			if (fd_in != fd || NULL == task->rx_event)
			{
				ev = event_get_base(task->timeout_event);

				if (NULL != task->rx_event)
					event_free(task->rx_event);

				task->rx_event = event_new(ev, fd, EV_READ, async_event, (void *)task);
			}
			event_add(task->rx_event, NULL);
			break;
		case ZBX_ASYNC_TASK_WRITE:
			if (fd_in != fd && NULL != task->rx_event)
			{
				event_free(task->rx_event);
				task->rx_event = NULL;
			}

			if (fd_in != fd || NULL == task->tx_event)
			{
				ev = event_get_base(task->timeout_event);

				if (NULL != task->tx_event)
					event_free(task->tx_event);

				task->tx_event = event_new(ev, fd, EV_WRITE, async_event, (void *)task);
			}
			event_add(task->tx_event, NULL);
			break;

	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, task_state_to_str(ret));
}

static void	async_reverse_dns_event(int err, char type, int count, int ttl, void *addresses, void *arg)
{
	zbx_async_task_t	*task = (zbx_async_task_t *)arg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() result:%d type:%d count:%d ttl:%d", __func__, err, type, count, ttl);

	if (DNS_ERR_NONE != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot reverse DNS name: %s", evdns_err_to_string(err));
		task->error = zbx_strdup(task->error, evdns_err_to_string(err));
		zbx_free(task->address);
	}
	else
	{
		if (0 != count)
		{
			task->address = zbx_strdup(task->address, *(char **)addresses);
			zabbix_log(LOG_LEVEL_DEBUG, "resolved reverse DNS name: %s", task->address);

		}
		else
			zbx_free(task->address);
	}

	async_event(-1, 0, task);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	async_dns_event(int err, struct evutil_addrinfo *ai, void *arg)
{
	zbx_async_task_t	*task = (zbx_async_task_t *)arg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() result:%d", __func__, err);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot resolve DNS name: %s", evutil_gai_strerror(err));
		task->error = zbx_strdup(task->error, evutil_gai_strerror(err));
		async_event(-1, EV_TIMEOUT, task);
	}
	else
	{
		struct timeval	tv = {task->timeout, 0};
		char		ip[65];

		if (FAIL == zbx_inet_ntop(ai, ip, (socklen_t)sizeof(ip)))
			ip[0] = '\0';

		task->ai = ai;
		task->address = zbx_strdup(task->address, ip);
		evtimer_add(task->timeout_event, &tv);
		async_event(-1, 0, task);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_async_poller_add_task(struct event_base *ev, struct evdns_base *dnsbase, const char *addr,
		void *data, int timeout, zbx_async_task_process_cb_t process_cb, zbx_async_task_clear_cb_t clear_cb)
{
	zbx_async_task_t	*task;
	struct evutil_addrinfo	hints;

	task = (zbx_async_task_t *)zbx_malloc(NULL, sizeof(zbx_async_task_t));
	task->data = data;
	task->process_cb = process_cb;
	task->free_cb = clear_cb;
	task->timeout_event = evtimer_new(ev, async_event, (void *)task);
	task->timeout = timeout;

	task->rx_event = NULL;
	task->tx_event = NULL;
	task->error = NULL;
	task->dnsbase = dnsbase;
	task->ai = NULL;
	task->address = NULL;

	memset(&hints, 0, sizeof(hints));

	if (SUCCEED == zbx_is_ip4(addr))
		hints.ai_flags = AI_NUMERICHOST;
#ifdef HAVE_IPV6
	else if (SUCCEED == zbx_is_ip6(addr))
		hints.ai_flags = AI_NUMERICHOST;
#endif
	else
		hints.ai_flags = 0;

	hints.ai_family = PF_UNSPEC;
	hints.ai_socktype = SOCK_STREAM;

	evdns_getaddrinfo(dnsbase, addr, NULL, &hints, async_dns_event, task);
}

zbx_async_task_state_t	zbx_async_poller_get_task_state_for_event(short event)
{
	if (POLLIN & event)
		return ZBX_ASYNC_TASK_READ;

	if (POLLOUT & event)
		return ZBX_ASYNC_TASK_WRITE;

	return ZBX_ASYNC_TASK_STOP;
}

#endif
