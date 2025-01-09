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

#ifndef ZABBIX_ASYNC_HTTPAGENT_H
#define ZABBIX_ASYNC_HTTPAGENT_H

#include "zbxcommon.h"

#ifdef HAVE_LIBCURL

#include "zbxhttp.h"
#include "zbxcacheconfig.h"

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
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, CURLM *curl_handle);
void	zbx_async_check_httpagent_clean(zbx_httpagent_context *httpagent_context);
#endif
#endif
