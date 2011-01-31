/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "comms.h"
#include "log.h"

#include "zbxjson.h"

#include "checks_jmx.h"

static void	parse_response(AGENT_RESULT *result, int value_type, int data_type, char *response)
{
	struct zbx_json_parse	jp, jp_data, jp_row;
	char			value[MAX_STRING_LEN];
	char			error[MAX_STRING_LEN];
	char			*buffer = NULL;
	const char		*p;
	int			ret = FAIL;

	*value = '\0';

	if (SUCCEED == zbx_json_open(response, &jp))
	{
		if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value)))
		{
			zbx_snprintf(error, sizeof(error), "No response tag in received JSON");
			goto exit;
		}

		if (0 == strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		{
			if (NULL == (p = zbx_json_pair_by_name(&jp, ZBX_PROTO_TAG_DATA)))
			{
				zbx_snprintf(error, sizeof(error), "No data tag in received JSON");
				goto exit;
			}

			if (SUCCEED != zbx_json_brackets_open(p, &jp_data))
			{
				zbx_snprintf(error, sizeof(error), "Could not open data array");
				goto exit;
			}

			p = NULL;
			if (NULL != (p = zbx_json_next(&jp_data, p)))
			{
				if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
				{
					zbx_snprintf(error, sizeof(error), "Could not open value object");
					goto exit;
				}

				if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_VALUE, value, sizeof(value)))
				{
					ret = set_result_type(result, value_type, data_type, value);
					goto exit;
				}
				else if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_ERROR, error, sizeof(value)))
					goto exit;
				else
				{
					zbx_snprintf(error, sizeof(error), "Could not get item value or error");
					goto exit;
				}
			}
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_FAILED))
		{
			zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_ERROR, error, sizeof(error));
			goto exit;
		}
		else
		{
			zbx_snprintf(error, sizeof(error), "Bad response tag in received JSON");
			goto exit;
		}
	}
	else
		zbx_snprintf(error, sizeof(error), "Could not open received JSON");
exit:
	if (SUCCEED != ret && NULL == GET_MSG_RESULT(result))
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
}

int	get_value_jmx(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_jmx";

	struct zbx_json	json;
	zbx_sock_t	s;
	char		*buffer = NULL;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' addr:'%s' key:'%s'",
			__function_name, item->host.host, item->interface.addr, item->key_orig);

	if (NULL == CONFIG_JAVA_PROXY || '\0' == *CONFIG_JAVA_PROXY)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "JavaProxy configuration parameter not set"));
		goto exit;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_JAVA_PROXY_JMX_ITEMS, ZBX_JSON_TYPE_STRING);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_CONN, item->interface.addr, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(&json, ZBX_PROTO_TAG_PORT, item->interface.port);
	if ('\0' != item->username)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_USERNAME, item->username, ZBX_JSON_TYPE_STRING);
	if ('\0' != item->password)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_PASSWORD, item->password, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(&json, ZBX_PROTO_TAG_KEYS);
	zbx_json_addstring(&json, NULL, item->key, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&json);

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP,
					CONFIG_JAVA_PROXY, CONFIG_JAVA_PROXY_PORT, CONFIG_TIMEOUT)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "JSON before sending [%s]", json.buffer);

		if (SUCCEED == (ret = zbx_tcp_send(&s, json.buffer)))
		{
			if (SUCCEED == (ret = zbx_tcp_recv(&s, &buffer)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "JSON back [%s]", buffer);

				parse_response(result, item->value_type, item->data_type, buffer);
			}
			else
				zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [recv] %s", zbx_tcp_strerror());
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [send] %s", zbx_tcp_strerror());

		zbx_tcp_close(&s);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Send value error: [connect] %s", zbx_tcp_strerror());

	zbx_json_free(&json);
exit:
	if (FAIL == ret || GET_MSG_RESULT(result))
		ret = AGENT_ERROR;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
