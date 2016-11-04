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

#include "proxydata.h"
#include "../../libs/zbxcrypto/tls_tcp_active.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_recv_proxy_data                                              *
 *                                                                            *
 * Purpose: receive 'proxy data' request from proxy                           *
 *                                                                            *
 * Parameters: sock - [IN] the connection socket                              *
 *             jp   - [IN] the received JSON data                             *
 *             ts   - [IN] the connection timestamp                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_recv_proxy_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts)
{
	const char	*__function_name = "zbx_recv_proxy_data";

	int		ret;
	char		*error = NULL;
	DC_PROXY	proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = get_active_proxy_from_request(jp, sock, &proxy, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy data from active proxy at \"%s\": %s",
				sock->peer, error);
		goto out;
	}

	if (SUCCEED != (ret = zbx_proxy_check_permissions(&proxy, sock, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot accept connection from proxy \"%s\" at \"%s\": %s",
				proxy.host, sock->peer, error);
		goto out;
	}

	zbx_proxy_update_version(&proxy, jp);

	if (SUCCEED != process_proxy_data(jp, proxy.hostid, ts, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "received invalid proxy data from proxy \"%s\" at \"%s\": %s",
				proxy.host, sock->peer, error);
	}

	ret = SUCCEED;
out:
	zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Function: send_data_to_server                                              *
 *                                                                            *
 * Purpose: sends data from proxy to server                                   *
 *                                                                            *
 * Parameters: sock  - [IN] the connection socket                             *
 *             data  - [IN] the data to send                                  *
 *             error - [OUT] the error message                                *
 *                                                                            *
 ******************************************************************************/
static int	send_data_to_server(zbx_socket_t *sock, const char *data, char **error)
{
	if (SUCCEED != zbx_tcp_send_to(sock, data, CONFIG_TIMEOUT))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		return FAIL;
	}

	if (SUCCEED != zbx_recv_response(sock, CONFIG_TIMEOUT, error))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_send_proxy_data                                              *
 *                                                                            *
 * Purpose: sends 'proxy data' request to server                              *
 *                                                                            *
 * Parameters: sock - [IN] the connection socket                              *
 *             ts   - [IN] the connection timestamp                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_send_proxy_data(zbx_socket_t *sock, zbx_timespec_t *ts)
{
	const char	*__function_name = "send_proxydata";

	struct zbx_json	j;
	zbx_uint64_t	areg_lastid, history_lastid, discovery_lastid;
	char		*error = NULL;
	int		availability_ts, more_history, more_discovery, more_areg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != check_access_passive_proxy(sock, ZBX_DO_NOT_SEND_RESPONSE, "proxy data request"))
	{
		/* do not send any reply to server in this case as the server expects proxy data */
		goto out;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_HOST_AVAILABILITY);
	get_host_availability_data(&j, &availability_ts);
	zbx_json_close(&j);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_HISTORY_DATA);
	proxy_get_hist_data(&j, &history_lastid, &more_history);
	zbx_json_close(&j);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DISCOVERY_DATA);
	proxy_get_dhis_data(&j, &discovery_lastid, &more_discovery);
	zbx_json_close(&j);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_AUTO_REGISTRATION);
	proxy_get_dhis_data(&j, &areg_lastid, &more_areg);
	zbx_json_close(&j);

	if (ZBX_PROXY_DATA_MORE == more_history || ZBX_PROXY_DATA_MORE == more_discovery ||
			ZBX_PROXY_DATA_MORE == more_areg)
	{
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_MORE, ZBX_PROXY_DATA_MORE);
	}

	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, ts->sec);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_NS, ts->ns);

	if (SUCCEED == send_data_to_server(sock, j.buffer, &error))
	{
		zbx_set_availability_diff_ts(availability_ts);

		DBbegin();

		if (0 != history_lastid)
			proxy_set_hist_lastid(history_lastid);

		if (0 != discovery_lastid)
			proxy_set_dhis_lastid(discovery_lastid);

		if (0 != areg_lastid)
			proxy_set_areg_lastid(areg_lastid);

		DBcommit();
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send proxy data to server at \"%s\": %s", sock->peer, error);
		zbx_free(error);
	}

	zbx_json_free(&j);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

