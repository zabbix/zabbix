/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_ASYNC_HTTP_H_
#define ZABBIX_ASYNC_HTTP_H_

#include "zbxasyncpoller.h"
#include "discoverer_async.h"

#ifdef HAVE_LIBCURL

typedef enum
{
	ZBX_ASYNC_HTTP_STEP_INIT = 0,
	ZBX_ASYNC_HTTP_STEP_RDNS
}
zbx_async_http_step_t;

typedef struct
{
	CURL				*easyhandle;
	int				config_timeout;
	unsigned short			port;
	int				res;
	discovery_async_result_t	*async_result;
	char				*reverse_dns;
	zbx_async_resolve_reverse_dns_t	resolve_reverse_dns;
	zbx_async_http_step_t		step;
}
zbx_discovery_async_http_context_t;

void	process_http_result(CURL *easy_handle, CURLcode err, void *arg);
void	zbx_discovery_async_http_context_destroy(zbx_discovery_async_http_context_t *http_ctx);
int	zbx_discovery_async_check_http(CURLM *curl_mhandle, const char *config_source_ip, int timeout, const char *ip,
		unsigned short port, unsigned char type, zbx_discovery_async_http_context_t *http_ctx, char **error);
#endif

#endif /* ZABBIX_ASYNC_HTTP_H_ */
