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

	zbx_uint64_t	proxy_hostid;
	char		host[HOST_HOST_LEN_MAX], *error = NULL;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = get_active_proxy_id(jp, &proxy_hostid, host, sock, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse host availability data from active proxy at \"%s\": %s",
				sock->peer, error);
		goto out;
	}

	process_host_availability(jp);
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

	struct zbx_json	j;
	int		ret = FAIL, ts;
	char		*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != check_access_passive_proxy(sock, ZBX_DO_NOT_SEND_RESPONSE, "host availability data request"))
	{
		/* do not send any reply to server in this case as the server expects host availability data */
		goto out1;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	/* if there are no host availability changes we still have to send empty data in response */
	if (SUCCEED != get_host_availability_data(&j, &ts))
	{
		zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);
		zbx_json_close(&j);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() [%s]", __function_name, j.buffer);

	if (SUCCEED != zbx_tcp_send_to(sock, j.buffer, CONFIG_TIMEOUT))
	{
		error = zbx_strdup(error, zbx_socket_strerror());
		goto out;
	}

	if (SUCCEED != zbx_recv_response(sock, CONFIG_TIMEOUT, &error))
		goto out;

	zbx_set_availability_diff_ts(ts);

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send host availability data to server at \"%s\": %s",
				sock->peer, error);
	}

	zbx_json_free(&j);
	zbx_free(error);
out1:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
