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
#include "zbxjson.h"
#include "comms.h"

#include "zabbix_sender.h"

const char	*progname = NULL;
const char	title_message[] = "";
const char	*usage_message[] = {NULL};

const char	*help_message[] = {NULL};

unsigned char	program_type	= ZBX_PROGRAM_TYPE_SENDER;

int	zabbix_sender_send_values(const char *address, unsigned short port, const char *source,
		const zabbix_sender_value_t *values, int count, char **result)
{
	zbx_socket_t	sock;
	int		ret, i;
	struct zbx_json	json;

	if (1 > count)
	{
		if (NULL != result)
			*result = zbx_strdup(NULL, "values array must have at least one item");

		return FAIL;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < count; i++)
	{
		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, values[i].host, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, values[i].key, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, values[i].value, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);
	}
	zbx_json_close(&json);

	if (SUCCEED == (ret = zbx_tcp_connect(&sock, source, address, port, GET_SENDER_TIMEOUT,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL)))
	{
		if (SUCCEED == (ret = zbx_tcp_send(&sock, json.buffer)))
		{
			if (SUCCEED == (ret = zbx_tcp_recv(&sock)))
			{
				if (NULL != result)
					*result = zbx_strdup(NULL, sock.buffer);
			}
		}

		zbx_tcp_close(&sock);
	}

	if (FAIL == ret && NULL != result)
		*result = zbx_strdup(NULL, zbx_socket_strerror());

	zbx_json_free(&json);

	return ret;
}

int	zabbix_sender_parse_result(const char *result, int *response, zabbix_sender_info_t *info)
{
	int			ret;
	struct zbx_json_parse	jp;
	char			value[MAX_STRING_LEN];

	if (SUCCEED != (ret = zbx_json_open(result, &jp)))
		goto out;

	if (SUCCEED != (ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value))))
		goto out;

	*response = (0 == strcmp(value, ZBX_PROTO_VALUE_SUCCESS)) ? 0 : -1;

	if (NULL == info)
		goto out;

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, value, sizeof(value)) ||
			3 != sscanf(value, "processed: %*d; failed: %d; total: %d; seconds spent: %lf",
				&info->failed, &info->total, &info->time_spent))
	{
		info->total = -1;
	}
out:
	return ret;
}

void	zabbix_sender_free_result(void *ptr)
{
	if (NULL != ptr)
		free(ptr);
}
