/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include "proxyautoreg.h"

/******************************************************************************
 *                                                                            *
 * Function: recv_areg_data                                                   *
 *                                                                            *
 * Purpose: receive auto-registration data from proxy                         *
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
void	recv_areg_data(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "recv_areg_data";

	int			ret;
	zbx_uint64_t		proxy_hostid;
	char			host[HOST_HOST_LEN_MAX], error[256];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == (ret = get_proxy_id(jp, &proxy_hostid, host, error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Autoregistration data from active proxy on [%s] failed: %s",
				get_ip_by_socket(sock), error);
		goto exit;
	}

	process_areg_data(jp, proxy_hostid);
exit:
	zbx_send_response(sock, ret, NULL, CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Function: send_areg_data                                                   *
 *                                                                            *
 * Purpose: send auto-registration data from proxy to a server                *
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
void	send_areg_data(zbx_sock_t *sock)
{
	const char	*__function_name = "send_areg_data";

	struct zbx_json	j;
	zbx_uint64_t	lastid;
	int		records;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	records = proxy_get_areg_data(&j, &lastid);

	zbx_json_close(&j);

	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (FAIL == zbx_tcp_send_to(sock, j.buffer, CONFIG_TIMEOUT))
		zabbix_log(LOG_LEVEL_WARNING, "Error while sending availability of hosts. %s",
				zbx_tcp_strerror());
	else if (SUCCEED == zbx_recv_response(sock, NULL, 0, CONFIG_TIMEOUT) && 0 != records)
		proxy_set_areg_lastid(lastid);

	zbx_json_free(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
