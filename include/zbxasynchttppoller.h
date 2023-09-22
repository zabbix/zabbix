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

#ifndef ZABBIX_ASYNCHTTPPOLLER_H
#define ZABBIX_ASYNCHTTPPOLLER_H
#include "zbxsysinc.h"

#if defined(HAVE_LIBCURL) && defined(HAVE_LIBEVENT)
#include <event.h>

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

zbx_asynchttppoller_config	*zbx_async_httpagent_create(struct event_base *ev,
		process_httpagent_result_callback_fn process_httpagent_result_callback,
		httpagent_action_callback_fn httpagent_action_callback, void *arg);
void	zbx_async_httpagent_clean(zbx_asynchttppoller_config *asynchttppoller_config);
#endif
#endif
