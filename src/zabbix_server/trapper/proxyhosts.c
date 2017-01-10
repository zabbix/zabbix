/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "dbcache.h"

#include "proxyhosts.h"

/******************************************************************************
 *                                                                            *
 * Function: recv_host_availability                                           *
 *                                                                            *
 * Purpose: update hosts availability, monitored by proxies                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	recv_host_availability(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	const char	*__function_name = "recv_host_availability";

	char		host[HOST_HOST_LEN_MAX], *error = NULL;
	int		ret = FAIL;
	DC_PROXY	proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != get_active_proxy_from_request(jp, sock, &proxy, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse host availability data from active proxy at \"%s\": %s",
				sock->peer, error);
		goto out;
	}

	if (SUCCEED != zbx_proxy_check_permissions(&proxy, sock, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot accept connection from proxy \"%s\" at \"%s\": %s",
				proxy.host, sock->peer, error);
		goto out;
	}

	zbx_proxy_update_version(&proxy, jp);

	if (SUCCEED != (ret = process_host_availability(jp, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "received invalid host availability data from proxy \"%s\" at \"%s\": %s",
				host, sock->peer, error);
	}
out:
	zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: send_host_availability                                           *
 *                                                                            *
 * Purpose: send hosts availability data from proxy                           *
 *                                                                            *
 ******************************************************************************/
void	send_host_availability(zbx_socket_t *sock)
{
	const char	*__function_name = "send_host_availability";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* do not send any reply to server in this case as the server expects host availability data */
	if (SUCCEED == check_access_passive_proxy(sock, ZBX_DO_NOT_SEND_RESPONSE, "host availability data request"))
		zbx_send_proxy_response(sock, FAIL, "Deprecated request", CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
