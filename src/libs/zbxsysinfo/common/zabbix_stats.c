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
#include "comms.h"
#include "zbxjson.h"

#include "zabbix_stats.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_zabbix_stats                                             *
 *                                                                            *
 * Purpose: create Zabbix stats request                                       *
 *                                                                            *
 * Parameters: ip     - [IN] external Zabbix instance hostname                *
 *             port   - [IN] external Zabbix instance port                    *
 *             result - [OUT] check result                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_get_remote_zabbix_stats(const char *ip, unsigned short port, AGENT_RESULT *result)
{
	zbx_socket_t	s;
	struct zbx_json	json;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_ZABBIX_STATS, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, ip, port, CONFIG_TIMEOUT, ZBX_TCP_SEC_UNENCRYPTED,
			NULL, NULL))
	{
		if (SUCCEED == zbx_tcp_send(&s, json.buffer))
		{
			if (SUCCEED == zbx_tcp_recv(&s))
			{
				set_result_type(result, ITEM_VALUE_TYPE_TEXT, s.buffer);
			}
			else
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL,
						"Cannot obtain internal statistics from [%s:%hu] (%s)",
						ip, port, zbx_socket_strerror()));
			}
		}
		else
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL,
					"Cannot send request to obtain internal statistics from [%s:%hu] (%s)",
					ip, port, zbx_socket_strerror()));
		}

		zbx_tcp_close(&s);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL,
				"Cannot create connection to obtain internal statistics from [%s:%hu] (%s)",
				ip, port, zbx_socket_strerror()));
	}
}

int	ZABBIX_STATS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*ip_str,*port_str;
	unsigned short	port_number;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	ip_str = get_rparam(request, 0);
	port_str = get_rparam(request, 1);

	if (NULL == ip_str || '\0' == *ip_str)
		ip_str = "127.0.0.1";

	if (NULL == port_str || '\0' == *port_str)
	{
		port_number = ZBX_DEFAULT_SERVER_PORT;
	}
	else if (SUCCEED != is_ushort(port_str, &port_number))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_get_remote_zabbix_stats(ip_str, port_number, result);

	if (0 != ISSET_MSG(result))
		return SYSINFO_RET_FAIL;
	else
		return SYSINFO_RET_OK;
}
