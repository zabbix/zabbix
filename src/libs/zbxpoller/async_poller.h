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

#ifndef ZABBIX_ASYNC_POLLER_H
#define ZABBIX_ASYNC_POLLER_H

#include "async_manager.h"

#include "zbxalgo.h"
#include "zbxthreads.h"
#include "zbxasyncpoller.h"

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
	const char		*config_ssl_ca_location;
	const char		*config_ssl_cert_location;
	const char		*config_ssl_key_location;
	struct event		*async_wake_timer;
	struct event		*async_timer;
#ifdef HAVE_ARES
	struct event		*async_timeout_timer;
#endif
	struct event_base	*base;
	struct evdns_base	*dnsbase;
	zbx_channel_t		*channel;
	zbx_hashset_t		interfaces;
	zbx_hashset_t		fd_events;
#ifdef HAVE_LIBCURL
	CURLM			*curl_handle;
#endif
}
zbx_poller_config_t;

#endif
