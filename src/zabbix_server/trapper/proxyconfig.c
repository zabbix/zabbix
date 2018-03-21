/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"
#include "db.h"
#include "log.h"
#include "proxy.h"

#include "proxyconfig.h"
#include "../../libs/zbxcrypto/tls_tcp_active.h"

/******************************************************************************
 *                                                                            *
 * Function: send_proxyconfig                                                 *
 *                                                                            *
 * Purpose: send configuration tables to the proxy from server                *
 *          (for active proxies)                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	send_proxyconfig(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	const char	*__function_name = "send_proxyconfig";
	char		*error = NULL;
	struct zbx_json	j;
	DC_PROXY	proxy;
	int		flags = ZBX_TCP_PROTOCOL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != get_active_proxy_from_request(jp, &proxy, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy configuration data request from active proxy at"
				" \"%s\": %s", sock->peer, error);
		goto out;
	}

	if (SUCCEED != zbx_proxy_check_permissions(&proxy, sock, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot accept connection from proxy \"%s\" at \"%s\", allowed address:"
				" \"%s\": %s", proxy.host, sock->peer, proxy.proxy_address, error);
		goto out;
	}

	zbx_update_proxy_data(&proxy, zbx_get_protocol_version(jp), time(NULL),
			(0 != (sock->protocol & ZBX_TCP_COMPRESS) ? 1 : 0));

	if (0 != proxy.compress)
		flags |= ZBX_TCP_COMPRESS;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED != get_proxyconfig_data(proxy.hostid, &j, &error))
	{
		zbx_send_response_ext(sock, FAIL, error, NULL, flags, CONFIG_TIMEOUT);
		zabbix_log(LOG_LEVEL_WARNING, "cannot collect configuration data for proxy \"%s\" at \"%s\": %s",
				proxy.host, sock->peer, error);
		goto clean;
	}

	zabbix_log(LOG_LEVEL_WARNING, "sending configuration data to proxy \"%s\" at \"%s\", datalen " ZBX_FS_SIZE_T,
			proxy.host, sock->peer, (zbx_fs_size_t)j.buffer_size);
	zabbix_log(LOG_LEVEL_DEBUG, "%s", j.buffer);

	if (SUCCEED != zbx_tcp_send_ext(sock, j.buffer, strlen(j.buffer), flags, CONFIG_TRAPPER_TIMEOUT))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send configuration data to proxy \"%s\" at \"%s\": %s",
				proxy.host, sock->peer, zbx_socket_strerror());
	}
clean:
	zbx_json_free(&j);
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: recv_proxyconfig                                                 *
 *                                                                            *
 * Purpose: receive configuration tables from server (passive proxies)        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	recv_proxyconfig(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "recv_proxyconfig";
	struct zbx_json_parse	jp_data;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy configuration data received from server at"
				" \"%s\": %s", sock->peer, zbx_json_strerror());
		zbx_send_proxy_response(sock, ret, zbx_json_strerror(), CONFIG_TIMEOUT);
		goto out;
	}

	if (SUCCEED != check_access_passive_proxy(sock, ZBX_SEND_RESPONSE, "configuration update"))
		goto out;

	process_proxyconfig(&jp_data);
	zbx_send_response_ext(sock, ret, NULL, ZABBIX_VERSION, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS, CONFIG_TIMEOUT);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
