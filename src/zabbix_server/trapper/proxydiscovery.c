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

#include "proxydiscovery.h"

/******************************************************************************
 *                                                                            *
 * Function: recv_discovery_data                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	recv_discovery_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts)
{
	const char	*__function_name = "recv_discovery_data";

	int		ret = FAIL;
	char		*error = NULL;
	DC_PROXY	proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != get_active_proxy_from_request(jp, &proxy, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse discovery data from active proxy at \"%s\": %s",
				sock->peer, error);
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

	if (SUCCEED != (ret = process_discovery_data(jp, ts, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "received invalid discovery data from proxy \"%s\" at \"%s\": %s",
				proxy.host, sock->peer, error);
	}
out:
	zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Function: send_discovery_data                                              *
 *                                                                            *
 * Purpose: send discovery data from proxy to a server                        *
 *                                                                            *
 ******************************************************************************/
void	send_discovery_data(zbx_socket_t *sock)
{
	const char	*__function_name = "send_discovery_data";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* do not send any reply to server in this case as the server expects discovery data */
	if (SUCCEED == check_access_passive_proxy(sock, ZBX_DO_NOT_SEND_RESPONSE, "discovery data request"))
		zbx_send_proxy_response(sock, FAIL, "Deprecated request", CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
