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

#include "zbxserialize.h"
#include "zbxconnector.h"
#include "log.h"
#include "zbxjson.h"

void	zbx_connector_serialize_object(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_connector_object_t *connector_object)
{
	zbx_uint32_t	data_len = 0, error_len;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, connector_object->objectid);
	zbx_serialize_prepare_value(data_len, connector_object->ts.sec);
	zbx_serialize_prepare_value(data_len, connector_object->ts.ns);
	zbx_serialize_prepare_str_len(data_len, connector_object->str, error_len);

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
	zbx_serialize_str(ptr, connector_object->str, error_len);
}

void	zbx_connector_deserialize_object(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_connector_object_t *connector_objects)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_connector_object_t	connector_object;
		zbx_uint32_t		deserialize_str_len;

		data += zbx_deserialize_value(data, &connector_object.objectid);
		data += zbx_deserialize_value(data, &connector_object.ts.sec);
		data += zbx_deserialize_value(data, &connector_object.ts.ns);
		data += zbx_deserialize_str(data, &connector_object.str, deserialize_str_len);

		zbx_vector_connector_object_append(connector_objects, connector_object);
	}
}
