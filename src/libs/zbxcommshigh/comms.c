/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
int	zbx_send_response_ext(zbx_socket_t *sock, int result, const char *info, int protocol, int timeout)
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

	if (FAIL == (ret = zbx_tcp_send_ext(sock, json.buffer, strlen(json.buffer), protocol, timeout)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending result back: %s", zbx_socket_strerror());
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
 * Purpose: read a response message (in JSON format) from socket, optionally  *
 *          extract "info" value.                                             *
 *                                                                            *
 * Parameters: sock    - [IN] socket descriptor                               *
 *             timeout - [IN] timeout for this operation                      *
 *             error   - [OUT] pointer to error message                       *
 *                                                                            *
 * Return value: SUCCEED - "response":"success" successfully retrieved        *
 *               FAIL    - otherwise                                          *
 * Comments:                                                                  *
 *     Allocates memory.                                                      *
 *                                                                            *
 *     If an error occurs, the function allocates dynamic memory for an error *
 *     message and writes its address into location pointed to by "error"     *
 *     parameter.                                                             *
 *                                                                            *
 *     When the "info" value is present in the response message then function *
 *     copies the "info" value into the "error" buffer as additional          *
 *     information                                                            *
 *                                                                            *
 *     IMPORTANT: it is a responsibility of the caller to release the         *
 *                "error" memory !                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_recv_response(zbx_socket_t *sock, int timeout, char **error)
{
	const char		*__function_name = "zbx_recv_response";

	struct zbx_json_parse	jp;
	char			value[16];
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != zbx_tcp_recv_to(sock, timeout))
	{
		/* since we have successfully sent data earlier, we assume the other */
		/* side is just too busy processing our data if there is no response */
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'", __function_name, sock->buffer);

	/* deal with empty string here because zbx_json_open() does not produce an error message in this case */
	if ('\0' == *sock->buffer)
	{
		*error = zbx_strdup(*error, "empty string received");
		goto out;
	}

	if (SUCCEED != zbx_json_open(sock->buffer, &jp))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_RESPONSE "\" tag");
		goto out;
	}

	if (0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
	{
		char	*info = NULL;
		size_t	info_alloc = 0;

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_INFO, &info, &info_alloc))
			*error = zbx_strdup(*error, info);
		else
			*error = zbx_dsprintf(*error, "negative response \"%s\"", value);
		zbx_free(info);
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
