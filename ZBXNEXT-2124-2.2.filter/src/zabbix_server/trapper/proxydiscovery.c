/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	recv_discovery_data(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "recv_discovery_data";

	int			ret;
	zbx_uint64_t		proxy_hostid;
	char			host[HOST_HOST_LEN_MAX], error[256];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	error[0] = '\0';

	if (SUCCEED != (ret = get_active_proxy_id(jp, &proxy_hostid, host, error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "discovery data from active proxy on \"%s\" failed: %s",
				get_ip_by_socket(sock), error);
		goto out;
	}

	process_dhis_data(jp);
out:
	zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Function: send_discovery_data                                              *
 *                                                                            *
 * Purpose: send discovery data from proxy to a server                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	send_discovery_data(zbx_sock_t *sock)
{
	const char	*__function_name = "send_discovery_data";

	struct zbx_json	j;
	zbx_uint64_t	lastid;
	int		records;
	char		*info = NULL, *error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	records = proxy_get_dhis_data(&j, &lastid);

	zbx_json_close(&j);

	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (SUCCEED != zbx_tcp_send_to(sock, j.buffer, CONFIG_TIMEOUT))
	{
		zabbix_log(LOG_LEVEL_WARNING, "error while sending discovery data to server: %s", zbx_tcp_strerror());
		goto out;
	}

	if (SUCCEED != zbx_recv_response(sock, &info, CONFIG_TIMEOUT, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "sending discovery data to server: error:\"%s\", info:\"%s\"",
				ZBX_NULL2EMPTY_STR(error), ZBX_NULL2EMPTY_STR(info));
		goto out;
	}

	if (0 != records)
		proxy_set_dhis_lastid(lastid);
out:
	zbx_json_free(&j);
	zbx_free(info);
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
