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

#ifndef ZABBIX_ASYNC_POLLER_H
#define ZABBIX_ASYNC_POLLER_H

#include "zbxalgo.h"
#include "zbxthreads.h"
#include "zbxcacheconfig.h"
#include "async_manager.h"

typedef struct
{
	zbx_async_manager_t	*manager;
	const zbx_thread_info_t	*info;
	int			state;
	int			clear_cache;
	int			process_num;
	unsigned char		poller_type;
	int			processed;
	int			queued;
	int			processing;
	int			config_unavailable_delay;
	int			config_unreachable_delay;
	int			config_unreachable_period;
	int			config_max_concurrent_checks_per_poller;
	int			config_timeout;
	const char		*config_source_ip;
	struct event		*async_wake_timer;
	struct event		*async_timer;
	struct event_base	*base;
	struct evdns_base	*dnsbase;
	zbx_hashset_t		interfaces;
#ifdef HAVE_LIBCURL
	CURLM			*curl_handle;
#endif
}
zbx_poller_config_t;

#endif
