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
#include "zbxalgo.h"
#include "event.h"
#include "zbxhttp.h"

typedef void (*process_httpagent_result_callback_fn)(CURL *easy_handle, CURLcode err);
typedef void (*httpagent_action_callback_fn)(void *arg);

CURLM	*zbx_async_httpagent_init(struct event_base *ev,
		process_httpagent_result_callback_fn process_httpagent_result_callback,
		httpagent_action_callback_fn httpagent_action_callback, void *arg);
void	zbx_async_httpagent_destroy(void);

#endif
