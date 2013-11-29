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
int	zbx_send_response_ext(zbx_sock_t *sock, int result, const char *info, int protocol, int timeout)
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
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, info, ZBX_JSON_TYPE_STRING);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'", __function_name, json.buffer);

	if (FAIL == (ret = zbx_tcp_send_ext(sock, json.buffer, protocol, timeout)))
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
 * Function: zbx_recv_response_dyn                                            *
 *                                                                            *
 * Purpose: read a response message (in JSON format) from socket, optionally  *
 *          extract "info" value.                                             *
 *                                                                            *
 * Parameters: sock       - [IN] socket descriptor                            *
 *             info       - [IN/OUT] pointer to "info" value location or NULL *
 *             timeout    - [IN] timeout for this operation                   *
 *                                                                            *
 * Return value: SUCCEED - "response":"success" response successfully         *
 *                         retrieved                                          *
 *               NETWORK_ERROR - network related error occurred               *
 *               FAIL - otherwise                                             *
 * Comments:                                                                  *
 *     Allocates memory.                                                      *
 *                                                                            *
 *     If 'info' parameter is NULL pointer then this function does not        *
 *     examine the response message for "info".                               *
 *                                                                            *
 *     If 'info' parameter is not a NULL pointer and:                         *
 *        - the "info" value is present in the response message then this     *
 *          function allocates a dynamic memory buffer, copies the "info"     *
 *          value into the buffer and writes the buffer address into location *
 *          pointed to by "info" parameter.                                   *
 *                                                                            *
 *          IMPORTANT: it is the responsibility of caller to release the      *
 *          buffer memory !                                                   *
 *                                                                            *
 *        - the "info" value is not present in the response message then this *
 *          function writes NULL into location pointed to by "info" parameter.*
 *                                                                            *
 ******************************************************************************/
int	zbx_recv_response_dyn(zbx_sock_t *sock, char **info, int timeout)
{
	const char		*__function_name = "zbx_recv_response_dyn";

	struct zbx_json_parse	jp;
	char			value[16], *answer, *info_buf = NULL;
	size_t			info_buf_alloc, offset = 0;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = zbx_tcp_recv_to(sock, &answer, timeout)))
	{
		/* since we have successfully sent data earlier, we assume the other */
		/* side is just too busy processing our data if there is no response */
		zabbix_log(LOG_LEVEL_DEBUG, "did not receive response from host");
		ret = NETWORK_ERROR;
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'", __function_name, answer);

	/* deal with empty string here because zbx_json_open() does not produce an error message in this case */
	if ('\0' == *answer)
	{
		if (NULL != info)
		{
			zbx_strcpy_alloc(&info_buf, &info_buf_alloc, &offset, "empty string received");
			*info = info_buf;
		}
		ret = FAIL;
		goto out;
	}

	if (SUCCEED != (ret = zbx_json_open(answer, &jp)) ||
			SUCCEED != (ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value))))
	{
		if (NULL != info)
		{
			zbx_strcpy_alloc(&info_buf, &info_buf_alloc, &offset, zbx_json_strerror());
			*info = info_buf;
		}
		goto out;
	}

	if (0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		ret = FAIL;

	if (NULL != info)
	{
		if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_INFO, &info_buf, &info_buf_alloc))
			*info = info_buf;
		else
			*info = NULL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
