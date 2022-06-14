/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zabbix_sender.h"

#include "zbxjson.h"
#include "comms.h"
#include "cfg.h"

const char	*progname = NULL;
const char	title_message[] = "";
const char	*usage_message[] = {NULL};

const char	*help_message[] = {NULL};

unsigned char	program_type	= ZBX_PROGRAM_TYPE_SENDER;

int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

static int	sender_add_serveractive_host_cb(const zbx_vector_ptr_t *addrs, zbx_vector_str_t *hostnames, void *data)
{
	ZBX_UNUSED(hostnames);

	zbx_addr_copy((zbx_vector_ptr_t *)data, addrs);

	return SUCCEED;
}

int	zabbix_sender_send_values(const char *address, unsigned short port, const char *source,
		const zabbix_sender_value_t *values, int count, char **result)
{
	zbx_socket_t					sock;
	int						ret, i;
	struct zbx_json					json;
	static ZBX_THREAD_LOCAL zbx_vector_ptr_t	zbx_addrs;
	static ZBX_THREAD_LOCAL char			*last_address;
	static unsigned short				last_port;

	if (NULL == address)
	{
		if (NULL != result)
			*result = zbx_strdup(NULL, "address must not be NULL");

		return FAIL;
	}

	if (1 > count)
	{
		if (NULL != result)
			*result = zbx_strdup(NULL, "values array must have at least one item");

		return FAIL;
	}

	if (NULL == last_address)
		zbx_vector_ptr_create(&zbx_addrs);

	if (0 != zbx_strcmp_null(last_address, address) || port != last_port)
	{
		last_address = zbx_strdup(last_address, address);
		last_port = port;

		zbx_vector_ptr_clear_ext(&zbx_addrs, zbx_addr_free);

		if (FAIL == zbx_set_data_destination_hosts(address, port, "<server>", sender_add_serveractive_host_cb,
				NULL, &zbx_addrs, result))
		{
			zbx_free(last_address);
			last_port = 0;
			zbx_vector_ptr_clear_ext(&zbx_addrs, zbx_addr_free);
			zbx_vector_ptr_destroy(&zbx_addrs);
			return FAIL;
		}
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

	if (SUCCEED == (ret = connect_to_server(&sock, source, &zbx_addrs, GET_SENDER_TIMEOUT, 30,
		ZBX_TCP_SEC_UNENCRYPTED, 0, 0)))
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

	if (SUCCEED != (ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value), NULL)))
		goto out;

	*response = (0 == strcmp(value, ZBX_PROTO_VALUE_SUCCESS)) ? 0 : -1;

	if (NULL == info)
		goto out;

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, value, sizeof(value), NULL) ||
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
