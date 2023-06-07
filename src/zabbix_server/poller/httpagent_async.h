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

#ifndef ZABBIX_HTTPAGENT_ASYNC_H
#define ZABBIX_HTTPAGENT_ASYNC_H
#include "zbxalgo.h"
#include "poller.h"
#include "event.h"
#include "zbxhttp.h"

ZBX_VECTOR_DECL(int32, int)


typedef struct
{
	zbx_dc_interface_t	interface;
	int			errcode;
	char			*error;
	zbx_uint64_t		itemid;
	char			host[ZBX_HOSTNAME_BUF_LEN];
	char			*key_orig;
}
zbx_interface_status;
typedef struct
{
	unsigned char		poller_type;
	int			processed;
	int			queued;
	int			processing;
	int			config_unavailable_delay;
	int			config_unreachable_delay;
	int			config_unreachable_period;
	int			config_timeout;
	const char		*config_source_ip;
	struct event		*async_check_items_timer;
	zbx_vector_uint64_t	itemids;
	zbx_vector_int32_t	errcodes;
	zbx_vector_int32_t	lastclocks;
	CURLM			*curl_handle;
	struct event_base	*base;
	zbx_hashset_t		interfaces;
}
zbx_poller_config_t;

typedef void (*process_item_result_callback_fn)(CURL *easy_handle, CURLcode err);

CURLM	*zbx_async_httpagent_init(struct event_base *ev, process_item_result_callback_fn process_item_result_callback);
void	zbx_async_httpagent_destroy(void);

#endif
