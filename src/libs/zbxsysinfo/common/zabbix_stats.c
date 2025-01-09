/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zabbix_stats.h"
#include "zbxsysinfo.h"

#include "../sysinfo.h"
#include "zbxnum.h"
#include "zbxcomms.h"
#include "zbxjson.h"

/******************************************************************************
 *                                                                            *
 * Purpose: checks whether JSON response is "success" or "failed"             *
 *                                                                            *
 * Parameters: response - [IN]                                                *
 *             result   - [OUT]                                               *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - error occurred                                       *
 *                                                                            *
 ******************************************************************************/
static int	check_response(const char *response, AGENT_RESULT *result)
{
	struct zbx_json_parse	jp;
	char			buffer[MAX_STRING_LEN];

	if (SUCCEED != zbx_json_open(response, &jp))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Value should be a JSON object."));
		return FAIL;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, buffer, sizeof(buffer), NULL))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot find tag: %s.", ZBX_PROTO_TAG_RESPONSE));
		return FAIL;
	}

	if (0 != strcmp(buffer, ZBX_PROTO_VALUE_SUCCESS))
	{
		if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, buffer, sizeof(buffer), NULL))
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot find tag: %s.", ZBX_PROTO_TAG_INFO));
		else
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain internal statistics: %s", buffer));

		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends Zabbix stats request and receives result data               *
 *                                                                            *
 * Parameters: json    - [IN] the request                                     *
 *             ip      - [IN] external Zabbix instance hostname               *
 *             port    - [IN] external Zabbix instance port                   *
 *             timeout - [IN] timeout value for comms                         *
 *             result  - [OUT] check result                                   *
 *                                                                            *
 ******************************************************************************/
static void	get_remote_zabbix_stats(const struct zbx_json *json, const char *ip, unsigned short port, int timeout,
		AGENT_RESULT *result)
{
	zbx_socket_t	s;

	if (SUCCEED == zbx_tcp_connect(&s, sysinfo_get_config_source_ip(), ip, port, timeout,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL))
	{
		if (SUCCEED == zbx_tcp_send(&s, json->buffer))
		{
			if (SUCCEED == zbx_tcp_recv(&s) && NULL != s.buffer)
			{
				if ('\0' == *s.buffer)
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL,
							"Cannot obtain internal statistics: received empty response."));
				}
				else if (SUCCEED == check_response(s.buffer, result))
					zbx_set_agent_result_type(result, ITEM_VALUE_TYPE_TEXT, s.buffer);
			}
			else
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain internal statistics: %s",
						zbx_socket_strerror()));
			}
		}
		else
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain internal statistics: %s",
					zbx_socket_strerror()));
		}

		zbx_tcp_close(&s);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain internal statistics: %s",
				zbx_socket_strerror()));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates Zabbix stats request                                      *
 *                                                                            *
 * Parameters: ip      - [IN] external Zabbix instance hostname               *
 *             port    - [IN] external Zabbix instance port                   *
 *             timeout - [IN] timeout value for comms                         *
 *             result  - [OUT] check result                                   *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - error occurred                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_remote_zabbix_stats(const char *ip, unsigned short port, int timeout, AGENT_RESULT *result)
{
	struct zbx_json	json;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_ZABBIX_STATS, ZBX_JSON_TYPE_STRING);

	get_remote_zabbix_stats(&json, ip, port, timeout, result);

	zbx_json_free(&json);

	return 0 == ZBX_ISSET_MSG(result) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates Zabbix stats queue request                                *
 *                                                                            *
 * Parameters: ip      - [IN] external Zabbix instance hostname               *
 *             port    - [IN] external Zabbix instance port                   *
 *             from    - [IN] lower limit for delay                           *
 *             to      - [IN] upper limit for delay                           *
 *             timeout - [IN] timeout value for comms                         *
 *             result  - [OUT] check result                                   *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - error occurred                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_remote_zabbix_stats_queue(const char *ip, unsigned short port, const char *from, const char *to,
		int timeout, AGENT_RESULT *result)
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

	get_remote_zabbix_stats(&json, ip, port, timeout, result);

	zbx_json_free(&json);

	return 0 == ZBX_ISSET_MSG(result) ? SUCCEED : FAIL;
}

int	zabbix_stats(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*ip_str, *port_str, *queue_str;
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
	else if (SUCCEED != zbx_is_ushort(port_str, &port_number))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	queue_str = get_rparam(request, 2);

	if (3 > request->nparam)
	{
		if (SUCCEED != zbx_get_remote_zabbix_stats(ip_str, port_number, request->timeout, result))
			return SYSINFO_RET_FAIL;
	}
	else if (NULL != queue_str && 0 == strcmp(queue_str, ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE))
	{
		if (SUCCEED != zbx_get_remote_zabbix_stats_queue(ip_str, port_number, get_rparam(request, 3),
				get_rparam(request, 4), request->timeout, result))
		{
			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}
