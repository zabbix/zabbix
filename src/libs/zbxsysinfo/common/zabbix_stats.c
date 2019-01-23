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
 * Function: check_response                                                   *
 *                                                                            *
 * Purpose: Check whether JSON response is "success" or "failed"              *
 *                                                                            *
 * Parameters: response - [IN] the request                                    *
 *             result   - [OUT] check result                                  *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	check_response(char *response, AGENT_RESULT *result)
{
	struct zbx_json_parse	jp;
	char			value[MAX_STRING_LEN];
	char			info[MAX_STRING_LEN];
	int			ret;

	ret = zbx_json_open(response, &jp);

	if (SUCCEED == ret)
		ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value));

	if (SUCCEED == ret && 0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		ret = FAIL;

	if (FAIL == ret && SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, info, sizeof(info)))
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "%s", info));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_remote_zabbix_stats                                          *
 *                                                                            *
 * Purpose: send Zabbix stats request and receive the result data             *
 *                                                                            *
 * Parameters: json   - [IN] the request                                      *
 *             ip     - [IN] external Zabbix instance hostname                *
 *             port   - [IN] external Zabbix instance port                    *
 *             result - [OUT] check result                                    *
 *                                                                            *
 ******************************************************************************/
static void	get_remote_zabbix_stats(const struct zbx_json *json, const char *ip, unsigned short port,
		AGENT_RESULT *result)
{
	zbx_socket_t	s;

	if (SUCCEED == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, ip, port, CONFIG_TIMEOUT, ZBX_TCP_SEC_UNENCRYPTED,
			NULL, NULL))
	{
		if (SUCCEED == zbx_tcp_send(&s, json->buffer))
		{
			if (SUCCEED == zbx_tcp_recv(&s) && NULL != s.buffer)
			{
				if (SUCCEED == check_response(s.buffer, result))
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

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_remote_zabbix_stats                                      *
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
	struct zbx_json	json;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_ZABBIX_STATS, ZBX_JSON_TYPE_STRING);

	get_remote_zabbix_stats(&json, ip, port, result);

	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_remote_zabbix_stats_queue                                *
 *                                                                            *
 * Purpose: create Zabbix stats queue request                                 *
 *                                                                            *
 * Parameters: ip     - [IN] external Zabbix instance hostname                *
 *             port   - [IN] external Zabbix instance port                    *
 *             from   - [IN] lower limit for delay                            *
 *             to     - [IN] upper limit for delay                            *
 *             result - [OUT] check result                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_get_remote_zabbix_stats_queue(const char *ip, unsigned short port, const char *from, const char *to,
		AGENT_RESULT *result)
{
	struct zbx_json	json;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_ZABBIX_STATS, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_TYPE, ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE, ZBX_JSON_TYPE_STRING);

	zbx_json_addobject(&json, ZBX_PROTO_TAG_PARAMS);

	if (NULL != from && '\0' != *from)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_FROM, from, ZBX_JSON_TYPE_STRING);
	if (NULL != to && '\0' != *to)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_TO, to, ZBX_JSON_TYPE_STRING);

	zbx_json_close(&json);

	get_remote_zabbix_stats(&json, ip, port, result);

	zbx_json_free(&json);
}

int	ZABBIX_STATS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*ip_str, *port_str, *tmp;
	unsigned short	port_number;

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (ip_str = get_rparam(request, 0)) || '\0' == *ip_str)
		ip_str = "127.0.0.1";

	if (NULL == (port_str = get_rparam(request, 1)) || '\0' == *port_str)
	{
		port_number = ZBX_DEFAULT_SERVER_PORT;
	}
	else if (SUCCEED != is_ushort(port_str, &port_number))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (3 > request->nparam)
	{
		zbx_get_remote_zabbix_stats(ip_str, port_number, result);
	}
	else if (0 == strcmp((tmp = get_rparam(request, 2)), ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE))
	{
		zbx_get_remote_zabbix_stats_queue(ip_str, port_number, get_rparam(request, 3),
				get_rparam(request, 4), result);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (0 != ISSET_MSG(result))
		return SYSINFO_RET_FAIL;
	else
		return SYSINFO_RET_OK;
}
