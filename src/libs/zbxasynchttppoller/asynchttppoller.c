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

#include "zbxlog.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxhttp.h"
#include "zbxcacheconfig.h"
#include <event.h>
#include <event2/thread.h>
#include "zbxserver.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbx_rtc_constants.h"
#include "zbxrtc.h"
#include "zbxtime.h"
#include "zbxtypes.h"
#include "asynchttppoller.h"

static ZBX_THREAD_LOCAL struct event		*curl_timeout;
static ZBX_THREAD_LOCAL CURLM			*curl_handle;

static process_httpagent_result_callback_fn	process_httpagent_result;
static httpagent_action_callback_fn		http_agent_action;
static ZBX_THREAD_LOCAL void			*http_agent_action_arg;

typedef struct
{
	struct event *event;
	curl_socket_t sockfd;
}
zbx_curl_context_t;

static int	start_timeout(CURLM *multi, long timeout_ms, void *userp)
{
	zabbix_log(LOG_LEVEL_DEBUG, "%s() timeout:%ld", __func__, timeout_ms);

	ZBX_UNUSED(multi);
	ZBX_UNUSED(userp);

	if(0 > timeout_ms)
	{
		evtimer_del(curl_timeout);
	}
	else
	{
		struct timeval tv;

		if(0 == timeout_ms)
			timeout_ms = 1;	/* 0 means directly call socket_action, but we will do it in a bit */

		tv.tv_sec = timeout_ms / 1000;
		tv.tv_usec = (timeout_ms % 1000) * 1000;
		evtimer_del(curl_timeout);
		evtimer_add(curl_timeout, &tv);
	}

	return 0;
}

static void	check_multi_info(void)
{
	CURLMsg	*message;
	int	pending;

	while (NULL != (message = curl_multi_info_read(curl_handle, &pending)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "pending cURL messages:%d", pending);

		switch (message->msg)
		{
			case CURLMSG_DONE:
				process_httpagent_result(message->easy_handle, message->data.result);
				break;
			default:
				zabbix_log(LOG_LEVEL_DEBUG, "curl message:%u", message->msg);
				break;
		}
	}
}

static void	on_timeout(evutil_socket_t fd, short events, void *arg)
{
	int	running_handles;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(fd);
	ZBX_UNUSED(events);
	ZBX_UNUSED(arg);

	http_agent_action(http_agent_action_arg);
	curl_multi_socket_action(curl_handle, CURL_SOCKET_TIMEOUT, 0, &running_handles);
	check_multi_info();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	curl_perform(int fd, short event, void *arg)
{
	int			running_handles;
	int			flags = 0;
	zbx_curl_context_t	*context;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(fd);

	if (event & EV_READ)
		flags |= CURL_CSELECT_IN;
	if (event & EV_WRITE)
		flags |= CURL_CSELECT_OUT;

	http_agent_action(http_agent_action_arg);
	context = (zbx_curl_context_t *)arg;
	curl_multi_socket_action(curl_handle, context->sockfd, flags, &running_handles);

	zabbix_log(LOG_LEVEL_DEBUG, "running_handles:%d", running_handles);

	check_multi_info();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_curl_context_t	*create_curl_context(curl_socket_t sockfd, struct event_base *event_base)
{
	zbx_curl_context_t	*context;

	context = (zbx_curl_context_t *) malloc(sizeof(*context));
	context->sockfd = sockfd;
	context->event = event_new(event_base, sockfd, 0, curl_perform, context);

	return context;
}

static void	destroy_curl_context(zbx_curl_context_t *context)
{
	event_del(context->event);
	event_free(context->event);
	free(context);
}

static int	handle_socket(CURL *easy, curl_socket_t s, int action, void *userp, void *socketp)
{
	zbx_curl_context_t	*curl_context;
	int			events = 0;
	struct event_base	*event_base = (struct event_base *)userp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() action:%d", __func__, action);

	ZBX_UNUSED(easy);
	ZBX_UNUSED(userp);

	switch (action)
	{
		case CURL_POLL_IN:
		case CURL_POLL_OUT:
		case CURL_POLL_INOUT:
			curl_context = socketp ? (zbx_curl_context_t *)socketp : create_curl_context(s, event_base);

			curl_multi_assign(curl_handle, s, (void *) curl_context);

			if(action != CURL_POLL_IN)
				events |= EV_WRITE;
			if(action != CURL_POLL_OUT)
				events |= EV_READ;

			events |= EV_PERSIST;

			event_del(curl_context->event);
			event_assign(curl_context->event, event_base, curl_context->sockfd, events, curl_perform,
					curl_context);
			event_add(curl_context->event, NULL);

		break;
		case CURL_POLL_REMOVE:
			if (NULL != socketp)
			{
				event_del(((zbx_curl_context_t*) socketp)->event);
				destroy_curl_context((zbx_curl_context_t*) socketp);
				curl_multi_assign(curl_handle, s, NULL);
			}
			break;
		default:
			break;

	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return 0;
}

CURLM	*zbx_async_httpagent_init(struct event_base *ev,
		process_httpagent_result_callback_fn process_httpagent_result_callback,
		httpagent_action_callback_fn httpagent_action_callback, void *arg)
{
	CURLMcode	merr;
	CURLcode	err;

	process_httpagent_result = process_httpagent_result_callback;
	http_agent_action = httpagent_action_callback;
	http_agent_action_arg = arg;

	if (CURLE_OK != (err = curl_global_init(CURL_GLOBAL_ALL)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL: %s", curl_easy_strerror(err));

		exit(EXIT_FAILURE);
	}

	if (NULL == (curl_handle = curl_multi_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL multi session");
		exit(EXIT_FAILURE);
	}

	if (CURLM_OK != (merr = curl_multi_setopt(curl_handle, CURLMOPT_SOCKETFUNCTION, handle_socket)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set CURLMOPT_SOCKETFUNCTION: %s", curl_multi_strerror(merr));
		exit(EXIT_FAILURE);
	}

	if (CURLM_OK != (merr = curl_multi_setopt(curl_handle, CURLMOPT_SOCKETDATA, ev)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set CURLMOPT_SOCKETDATA: %s", curl_multi_strerror(merr));
		exit(EXIT_FAILURE);
	}

	if (CURLM_OK != (merr = curl_multi_setopt(curl_handle, CURLMOPT_TIMERFUNCTION, start_timeout)))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set CURLMOPT_TIMERFUNCTION: %s", curl_multi_strerror(merr));
		exit(EXIT_FAILURE);
	}

	if (NULL == (curl_timeout = evtimer_new(ev, on_timeout, NULL)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot create timer event");
		exit(EXIT_FAILURE);
	}

	return curl_handle;
}

void	zbx_async_httpagent_destroy(void)
{
	if (NULL != curl_handle)
		curl_multi_cleanup(curl_handle);

	if (NULL != curl_timeout)
		event_free(curl_timeout);
}

