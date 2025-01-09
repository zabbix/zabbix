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

#ifndef ZABBIX_ASYNCHTTPPOLLER_H
#define ZABBIX_ASYNCHTTPPOLLER_H
#include "zbxsysinc.h"

#if defined(HAVE_LIBCURL) && defined(HAVE_LIBEVENT)
#include <curl/curl.h>
#include <curl/multi.h>

#include <event2/event.h>

typedef void (*process_httpagent_result_callback_fn)(CURL *easy_handle, CURLcode err, void *arg);
typedef void (*httpagent_action_callback_fn)(void *arg);

typedef struct
{
	struct event_base			*ev;
	struct event				*curl_timeout;
	CURLM					*curl_handle;
	process_httpagent_result_callback_fn	process_httpagent_result;
	httpagent_action_callback_fn		http_agent_action;
	void					*http_agent_arg;
}
zbx_asynchttppoller_config;

void	zbx_async_httpagent_init(void);
zbx_asynchttppoller_config	*zbx_async_httpagent_create(struct event_base *ev,
		process_httpagent_result_callback_fn process_httpagent_result_callback,
		httpagent_action_callback_fn httpagent_action_callback, void *arg, char **error);
void	zbx_async_httpagent_clean(zbx_asynchttppoller_config *asynchttppoller_config);
#endif
#endif
