/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_send_response                                                *
 *                                                                            *
 * Purpose: send json SUCCEED or FAIL to socket along with an info message    *
 *                                                                            *
 * Parameters: sock    - [IN] socket descriptor                               *
 *             result  - [IN] SUCCEED or FAIL                                 *
 *             info    - [IN] info message                                    *
 *             timeout - [IN] timeout for this operation                      *
 *                                                                            *
 * Return value: SUCCEED - data successfully transmited                       *
 *               NETWORK_ERROR - network related error occurred               *
 *                                                                            *
 * Author: Alexander Vladishev, Alexei Vladishev                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_send_response(zbx_sock_t *sock, int result, const char *info, int timeout)
{
	const char	*__function_name = "zbx_send_response";

	struct zbx_json	json;
	const char	*resp;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	resp = SUCCEED == result ? ZBX_PROTO_VALUE_SUCCESS : ZBX_PROTO_VALUE_FAILED;

	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, resp, ZBX_JSON_TYPE_STRING);

	if (NULL != info && '\0' != *info)
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, info, ZBX_JSON_TYPE_STRING);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'",
			__function_name, json.buffer);

	if (FAIL == (ret = zbx_tcp_send_to(sock, json.buffer, timeout)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending result back: %s", zbx_tcp_strerror());
		ret = NETWORK_ERROR;
	}

	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_recv_response                                                *
 *                                                                            *
 * Purpose: receive json SUCCEED or FAIL from socket                          *
 *                                                                            *
 * Parameters: sock          - [IN] socket descriptor                         *
 *             info          - [OUT] info message                             *
 *             max_info_size - [IN] size of info buffer                       *
 *             timeout       - [IN] timeout for this operation                *
 *                                                                            *
 * Return value: SUCCEED - data with 'succeed' response successfully          *
 *                         retrieved                                          *
 *               NETWORK_ERROR - network related error occurred               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_recv_response(zbx_sock_t *sock, char *info, int max_info_size, int timeout)
{
	const char		*__function_name = "zbx_recv_response";

	struct zbx_json_parse	jp;
	char			value[16], *answer;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == (ret = zbx_tcp_recv_to(sock, &answer, timeout)))
	{
		/* since we have successfully sent data earlier, we assume the other */
		/* side is just too busy processing our data if there is no response */
		zabbix_log(LOG_LEVEL_DEBUG, "Did not receive response from host");
		ret = NETWORK_ERROR;
		goto exit;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'",
			__function_name, answer);

	if (FAIL == (ret = zbx_json_open(answer, &jp)))
		goto exit;

	if (NULL != info)
		zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, info, max_info_size);

	if (FAIL == (ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value))))
		goto exit;

	if (0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		ret = FAIL;
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
