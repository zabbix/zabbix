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
#include "zbxjson.h"
#include "comms.h"

#include "zabbix_sender.h"

const char	*progname = NULL;
const char	title_message[] = "";
const char	usage_message[] = "";

const char	*help_message[] = {NULL};

int zbx_sender_send_values(const char *address, unsigned short port, const char *source, const zbx_sender_value_t *values,
		int count, char **result)
{
	zbx_sock_t	sock;
	int		tcp_ret = SUCCEED, ret = FAIL, i;
	struct zbx_json	json;
	char		*answer = NULL;

	if (1 > count)
	{
		if (NULL != result)
			*result = zbx_strdup(NULL, "Values array must have at least one item");

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

	if (SUCCEED == (tcp_ret = zbx_tcp_connect(&sock, source, address, port, GET_SENDER_TIMEOUT)))
	{
		if (SUCCEED == (tcp_ret = zbx_tcp_send(&sock,json.buffer)))
		{
			if (SUCCEED == (tcp_ret = zbx_tcp_recv(&sock, &answer)))
			{
				if (NULL != result)
					*result = answer;
				else
					zbx_free(answer);
			}
		}

		zbx_tcp_close(&sock);
	}

out:
	if (FAIL == tcp_ret && NULL != result)
		*result = zbx_strdup(NULL, zbx_tcp_strerror());

	zbx_json_free(&json);

	return ret;
}

void zbx_sender_result_free(void *ptr)
{
	if (NULL != ptr)
		free(ptr);
}
