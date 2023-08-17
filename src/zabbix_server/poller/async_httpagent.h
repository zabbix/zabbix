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

#ifndef ZABBIX_ASYNC_HTTPAGENT_H
#define ZABBIX_ASYNC_HTTPAGENT_H

#include "zbxcacheconfig.h"
#include "module.h"
#include "async_poller.h"
#include "zbxhttp.h"

#ifdef HAVE_LIBCURL
typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	hostid;
	unsigned char	value_type;
	unsigned char	flags;
	unsigned char	state;
	char		*posts;
	char		*status_codes;
}
zbx_dc_httpitem_context_t;

typedef struct
{
	zbx_http_context_t	http_context;
	zbx_dc_httpitem_context_t	item_context;
}
zbx_httpagent_context;

int	zbx_async_check_httpagent(zbx_dc_item_t *item, AGENT_RESULT *result, const char *config_source_ip,
		CURLM *curl_handle);
void	zbx_async_check_httpagent_clean(zbx_httpagent_context *httpagent_context);
#endif
#endif
