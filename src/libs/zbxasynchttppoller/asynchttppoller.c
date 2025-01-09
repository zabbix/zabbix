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

#include "zbxcommon.h"

#if defined(HAVE_LIBCURL) && defined(HAVE_LIBEVENT)

#include "zbxasynchttppoller.h"

typedef struct
{
	struct event			*event;
	curl_socket_t			sockfd;
	zbx_asynchttppoller_config	*asynchttppoller_config;
}
zbx_curl_context_t;

static int	start_timeout(CURLM *multi, long timeout_ms, void *userp)
{
	zbx_asynchttppoller_config	*asynchttppoller_config = (zbx_asynchttppoller_config *)userp;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() timeout:%ld", __func__, timeout_ms);

	ZBX_UNUSED(multi);

	if(0 > timeout_ms)
	{
		evtimer_del(asynchttppoller_config->curl_timeout);
	}
	else
	{
		struct timeval tv;

		if(0 == timeout_ms)
			timeout_ms = 1;	/* 0 means directly call socket_action, but we will do it in a bit */

		tv.tv_sec = timeout_ms / 1000;
		tv.tv_usec = (timeout_ms % 1000) * 1000;
		evtimer_del(asynchttppoller_config->curl_timeout);
		evtimer_add(asynchttppoller_config->curl_timeout, &tv);
	}

	return 0;
}

static void	check_multi_info(zbx_asynchttppoller_config *asynchttppoller_config)
{
	CURLMsg	*message;
	int	pending;

	while (NULL != (message = curl_multi_info_read(asynchttppoller_config->curl_handle, &pending)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "pending cURL messages:%d", pending);

		switch (message->msg)
		{
			case CURLMSG_DONE:
				asynchttppoller_config->process_httpagent_result(message->easy_handle,
						message->data.result, asynchttppoller_config->http_agent_arg);
				break;
			case CURLMSG_NONE:
			case CURLMSG_LAST:
			default:
				zabbix_log(LOG_LEVEL_DEBUG, "curl message:%u", message->msg);
				break;
		}
	}
}

static void	on_timeout(evutil_socket_t fd, short events, void *arg)
{
	int				running_handles;
	zbx_asynchttppoller_config	*asynchttppoller_config = (zbx_asynchttppoller_config *)arg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(fd);
	ZBX_UNUSED(events);

	if (NULL != asynchttppoller_config->http_agent_action)
		asynchttppoller_config->http_agent_action(asynchttppoller_config->http_agent_arg);

	curl_multi_socket_action(asynchttppoller_config->curl_handle, CURL_SOCKET_TIMEOUT, 0, &running_handles);
	check_multi_info(asynchttppoller_config);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	curl_perform(int fd, short event, void *arg)
{
	int				running_handles;
	int				flags = 0;
	zbx_curl_context_t		*context;
	zbx_asynchttppoller_config	*asynchttppoller_config;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(fd);

	if (event & EV_READ)
		flags |= CURL_CSELECT_IN;
	if (event & EV_WRITE)
		flags |= CURL_CSELECT_OUT;

	context = (zbx_curl_context_t *)arg;
	asynchttppoller_config = context->asynchttppoller_config;

	if (NULL != asynchttppoller_config->http_agent_action)
		asynchttppoller_config->http_agent_action(asynchttppoller_config->http_agent_arg);

	curl_multi_socket_action(asynchttppoller_config->curl_handle, context->sockfd, flags, &running_handles);

	zabbix_log(LOG_LEVEL_DEBUG, "running_handles:%d", running_handles);

	check_multi_info(asynchttppoller_config);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_curl_context_t	*create_curl_context(curl_socket_t sockfd,
		zbx_asynchttppoller_config *asynchttppoller_config)
{
	zbx_curl_context_t	*context;

	context = (zbx_curl_context_t *) malloc(sizeof(*context));
	context->sockfd = sockfd;
	context->event = event_new(asynchttppoller_config->ev, sockfd, 0, curl_perform, context);
	context->asynchttppoller_config = asynchttppoller_config;

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
	zbx_curl_context_t		*curl_context;
	short				events = 0;
	zbx_asynchttppoller_config	*asynchttppoller_config = (zbx_asynchttppoller_config *)userp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() action:%d", __func__, action);

	ZBX_UNUSED(easy);

	switch (action)
	{
		case CURL_POLL_IN:
		case CURL_POLL_OUT:
		case CURL_POLL_INOUT:
			curl_context = socketp ? (zbx_curl_context_t *)socketp : create_curl_context(s,
					asynchttppoller_config);

			curl_multi_assign(asynchttppoller_config->curl_handle, s, (void *) curl_context);

			if(action != CURL_POLL_IN)
				events |= EV_WRITE;
			if(action != CURL_POLL_OUT)
				events |= EV_READ;

			events |= EV_PERSIST;

			event_del(curl_context->event);
			event_assign(curl_context->event, asynchttppoller_config->ev, curl_context->sockfd, events,
					curl_perform, curl_context);
			event_add(curl_context->event, NULL);

		break;
		case CURL_POLL_REMOVE:
			if (NULL != socketp)
			{
				event_del(((zbx_curl_context_t*) socketp)->event);
				destroy_curl_context((zbx_curl_context_t*) socketp);
				curl_multi_assign(asynchttppoller_config->curl_handle, s, NULL);
			}
			break;
		default:
			break;

	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return 0;
}

void	zbx_async_httpagent_init(void)
{
	CURLcode	err;

	if (CURLE_OK != (err = curl_global_init(CURL_GLOBAL_ALL)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL: %s", curl_easy_strerror(err));

		exit(EXIT_FAILURE);
	}
}

zbx_asynchttppoller_config	*zbx_async_httpagent_create(struct event_base *ev,
		process_httpagent_result_callback_fn process_httpagent_result_callback,
		httpagent_action_callback_fn httpagent_action_callback, void *arg, char **error)
{
	CURLMcode			merr;
	zbx_asynchttppoller_config	*asynchttppoller_config = zbx_malloc(NULL ,sizeof(zbx_asynchttppoller_config));

	asynchttppoller_config->process_httpagent_result = process_httpagent_result_callback;
	asynchttppoller_config->http_agent_action = httpagent_action_callback;
	asynchttppoller_config->http_agent_arg = arg;
	asynchttppoller_config->ev = ev;

	if (NULL == (asynchttppoller_config->curl_handle = curl_multi_init()))
	{
		*error = zbx_strdup(*error, "cannot initialize cURL multi session");
		goto err;
	}

	if (CURLM_OK != (merr = curl_multi_setopt(asynchttppoller_config->curl_handle, CURLMOPT_SOCKETFUNCTION,
			handle_socket)))
	{
		*error = zbx_dsprintf(*error, "cannot set CURLMOPT_SOCKETFUNCTION: %s", curl_multi_strerror(merr));
		goto err;
	}

	if (CURLM_OK != (merr = curl_multi_setopt(asynchttppoller_config->curl_handle, CURLMOPT_SOCKETDATA,
			asynchttppoller_config)))
	{
		*error = zbx_dsprintf(*error, "cannot set CURLMOPT_SOCKETDATA: %s", curl_multi_strerror(merr));
		goto err;
	}

	if (CURLM_OK != (merr = curl_multi_setopt(asynchttppoller_config->curl_handle, CURLMOPT_TIMERFUNCTION,
			start_timeout)))
	{
		*error = zbx_dsprintf(*error, "cannot set CURLMOPT_TIMERFUNCTION: %s", curl_multi_strerror(merr));
		goto err;
	}

	if (CURLM_OK != (merr = curl_multi_setopt(asynchttppoller_config->curl_handle, CURLMOPT_TIMERDATA,
			asynchttppoller_config)))
	{
		*error = zbx_dsprintf(*error, "cannot set CURLMOPT_TIMERDATA: %s", curl_multi_strerror(merr));
		goto err;
	}

	if (NULL == (asynchttppoller_config->curl_timeout = evtimer_new(ev, on_timeout, asynchttppoller_config)))
	{
		*error = zbx_strdup(*error, "cannot create timer event");
		goto err;
	}

	return asynchttppoller_config;
err:
	if (NULL != asynchttppoller_config->curl_handle)
		curl_multi_cleanup(asynchttppoller_config->curl_handle);

	zbx_free(asynchttppoller_config);

	return NULL;
}

void	zbx_async_httpagent_clean(zbx_asynchttppoller_config *asynchttppoller_config)
{
	if (NULL != asynchttppoller_config->curl_handle)
		curl_multi_cleanup(asynchttppoller_config->curl_handle);

	if (NULL != asynchttppoller_config->curl_timeout)
		event_free(asynchttppoller_config->curl_timeout);
}
#endif
