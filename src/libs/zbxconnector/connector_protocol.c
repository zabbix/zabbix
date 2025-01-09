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

#include "zbxconnector.h"
#include "zbxserialize.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxtime.h"

void	zbx_connector_serialize_object(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_connector_object_t *connector_object)
{
	zbx_uint32_t	data_len = 0, error_len, vector_uint64_len;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, connector_object->objectid);
	zbx_serialize_prepare_value(data_len, connector_object->ts.sec);
	zbx_serialize_prepare_value(data_len, connector_object->ts.ns);
	zbx_serialize_prepare_str_len(data_len, connector_object->str, error_len);
	zbx_serialize_prepare_vector_uint64_len(data_len, (&connector_object->ids), vector_uint64_len);

	if (NULL == *data)
		*data = (unsigned char *)zbx_calloc(NULL, (*data_alloc = MAX(1024, data_len)), 1);

	while (data_len > *data_alloc - *data_offset)
	{
		*data_alloc *= 2;
		*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
	}
	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, connector_object->objectid);
	ptr += zbx_serialize_value(ptr, connector_object->ts.sec);
	ptr += zbx_serialize_value(ptr, connector_object->ts.ns);
	ptr += zbx_serialize_str(ptr, connector_object->str, error_len);
	zbx_serialize_vector_uint64(ptr, (&connector_object->ids), vector_uint64_len);
}

void	zbx_connector_deserialize_object(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_connector_object_t *connector_objects)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_connector_object_t	connector_object;
		zbx_uint32_t		deserialize_str_len, vector_uint64_len;

		data += zbx_deserialize_value(data, &connector_object.objectid);
		data += zbx_deserialize_value(data, &connector_object.ts.sec);
		data += zbx_deserialize_value(data, &connector_object.ts.ns);
		data += zbx_deserialize_str(data, &connector_object.str, deserialize_str_len);
		zbx_vector_uint64_create(&connector_object.ids);
		data += zbx_deserialize_vector_uint64(data, &connector_object.ids, vector_uint64_len);

		zbx_vector_connector_object_append(connector_objects, connector_object);
	}
}

void	zbx_connector_serialize_data_point(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_connector_data_point_t *connector_data_point)
{
	zbx_uint32_t	data_len = 0, str_len;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, connector_data_point->ts.sec);
	zbx_serialize_prepare_value(data_len, connector_data_point->ts.ns);
	zbx_serialize_prepare_str_len(data_len, connector_data_point->str, str_len);

	if (NULL == *data)
		*data = (unsigned char *)zbx_calloc(NULL, (*data_alloc = MAX(1024, data_len)), 1);

	while (data_len > *data_alloc - *data_offset)
	{
		*data_alloc *= 2;
		*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
	}
	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, connector_data_point->ts.sec);
	ptr += zbx_serialize_value(ptr, connector_data_point->ts.ns);
	(void)zbx_serialize_str(ptr, connector_data_point->str, str_len);
}

static void	zbx_connector_deserialize_data_point(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_connector_data_point_t *connector_data_points)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_connector_data_point_t	connector_data_point;
		zbx_uint32_t			deserialize_str_len;

		data += zbx_deserialize_value(data, &connector_data_point.ts.sec);
		data += zbx_deserialize_value(data, &connector_data_point.ts.ns);
		data += zbx_deserialize_str(data, &connector_data_point.str, deserialize_str_len);

		zbx_vector_connector_data_point_append(connector_data_points, connector_data_point);
	}
}

void	zbx_connector_serialize_connector(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_connector_t *connector)
{
	zbx_uint32_t	data_len = 0, url_len, timeout_len, token_len, http_proxy_len, username_len, password_len,
			ssl_cert_file_len, ssl_key_file_len, ssl_key_password_len, attempt_interval_len;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, connector->protocol);
	zbx_serialize_prepare_value(data_len, connector->data_type);
	zbx_serialize_prepare_str_len(data_len, connector->url, url_len);
	zbx_serialize_prepare_str_len(data_len, connector->timeout, timeout_len);
	zbx_serialize_prepare_value(data_len, connector->max_attempts);
	zbx_serialize_prepare_str_len(data_len, connector->token, token_len);
	zbx_serialize_prepare_str_len(data_len, connector->http_proxy, http_proxy_len);
	zbx_serialize_prepare_value(data_len, connector->authtype);
	zbx_serialize_prepare_str_len(data_len, connector->username, username_len);
	zbx_serialize_prepare_str_len(data_len, connector->password, password_len);
	zbx_serialize_prepare_value(data_len, connector->verify_peer);
	zbx_serialize_prepare_value(data_len, connector->verify_host);
	zbx_serialize_prepare_str_len(data_len, connector->ssl_cert_file, ssl_cert_file_len);
	zbx_serialize_prepare_str_len(data_len, connector->ssl_key_file, ssl_key_file_len);
	zbx_serialize_prepare_str_len(data_len, connector->ssl_key_password, ssl_key_password_len);
	zbx_serialize_prepare_value(data_len, connector->item_value_type);
	zbx_serialize_prepare_str_len(data_len, connector->attempt_interval, attempt_interval_len);

	if (NULL == *data)
		*data = (unsigned char *)zbx_calloc(NULL, (*data_alloc = MAX(1024, data_len)), 1);

	while (data_len > *data_alloc - *data_offset)
	{
		*data_alloc *= 2;
		*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
	}
	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, connector->protocol);
	ptr += zbx_serialize_value(ptr, connector->data_type);
	ptr += zbx_serialize_str(ptr, connector->url, url_len);
	ptr += zbx_serialize_str(ptr, connector->timeout, timeout_len);
	ptr += zbx_serialize_value(ptr, connector->max_attempts);
	ptr += zbx_serialize_str(ptr, connector->token, token_len);
	ptr += zbx_serialize_str(ptr, connector->http_proxy, http_proxy_len);
	ptr += zbx_serialize_value(ptr, connector->authtype);
	ptr += zbx_serialize_str(ptr, connector->username, username_len);
	ptr += zbx_serialize_str(ptr, connector->password, password_len);
	ptr += zbx_serialize_value(ptr, connector->verify_peer);
	ptr += zbx_serialize_value(ptr, connector->verify_host);
	ptr += zbx_serialize_str(ptr, connector->ssl_cert_file, ssl_cert_file_len);
	ptr += zbx_serialize_str(ptr, connector->ssl_key_file, ssl_key_file_len);
	ptr += zbx_serialize_str(ptr, connector->ssl_key_password, ssl_key_password_len);
	ptr += zbx_serialize_value(ptr, connector->item_value_type);
	(void)zbx_serialize_str(ptr, connector->attempt_interval, attempt_interval_len);
}

void	zbx_connector_deserialize_connector_and_data_point(const unsigned char *data, zbx_uint32_t size,
		zbx_connector_t *connector, zbx_vector_connector_data_point_t *connector_data_points)
{
	zbx_uint32_t		url_len, timeout_len, token_len, http_proxy_len, username_len, password_len,
				ssl_cert_file_len, ssl_key_file_len, ssl_key_password_len, attempt_interval_len;
	const unsigned char	*start = data;

	data += zbx_deserialize_value(data, &connector->protocol);
	data += zbx_deserialize_value(data, &connector->data_type);
	data += zbx_deserialize_str(data, &connector->url, url_len);
	data += zbx_deserialize_str(data, &connector->timeout, timeout_len);
	data += zbx_deserialize_value(data, &connector->max_attempts);
	data += zbx_deserialize_str(data, &connector->token, token_len);
	data += zbx_deserialize_str(data, &connector->http_proxy, http_proxy_len);
	data += zbx_deserialize_value(data, &connector->authtype);
	data += zbx_deserialize_str(data, &connector->username, username_len);
	data += zbx_deserialize_str(data, &connector->password, password_len);
	data += zbx_deserialize_value(data, &connector->verify_peer);
	data += zbx_deserialize_value(data, &connector->verify_host);
	data += zbx_deserialize_str(data, &connector->ssl_cert_file, ssl_cert_file_len);
	data += zbx_deserialize_str(data, &connector->ssl_key_file, ssl_key_file_len);
	data += zbx_deserialize_str(data, &connector->ssl_key_password, ssl_key_password_len);
	data += zbx_deserialize_value(data, &connector->item_value_type);
	data += zbx_deserialize_str(data, &connector->attempt_interval, attempt_interval_len);

	zbx_connector_deserialize_data_point(data, (zbx_uint32_t)(size - (data - start)), connector_data_points);
}
