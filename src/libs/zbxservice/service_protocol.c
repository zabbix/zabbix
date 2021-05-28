/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "zbxserialize.h"
#include "db.h"
#include "service_protocol.h"
#include "dbcache.h"

void	zbx_service_serialize(unsigned char **data, size_t *data_alloc, size_t *data_offset, const DB_EVENT *event)
{
	zbx_uint32_t	data_len = 0, *len = NULL;
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, event->eventid);
	zbx_serialize_prepare_value(data_len, event->severity);
	zbx_serialize_prepare_value(data_len, event->clock);
	zbx_serialize_prepare_value(data_len, event->tags.values_num);

	if (0 != event->tags.values_num)
	{
		len = (zbx_uint32_t *)zbx_malloc(NULL, sizeof(zbx_uint32_t) * 2 * event->tags.values_num);
		for (i = 0; i < event->tags.values_num; i++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[i];

			zbx_serialize_prepare_str_len(data_len, tag->tag, len[i * 2]);
			zbx_serialize_prepare_str_len(data_len, tag->value, len[i * 2 + 1]);
		}
	}

	if (NULL == *data)
		*data = (unsigned char *)zbx_malloc(NULL, (*data_alloc = MAX(1024, data_len)));

	while (data_len > *data_alloc - *data_offset)
	{
		*data_alloc *= 2;
		*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
	}
	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, event->eventid);
	ptr += zbx_serialize_value(ptr, event->severity);
	ptr += zbx_serialize_value(ptr, event->clock);
	ptr += zbx_serialize_value(ptr, event->tags.values_num);

	for (i = 0; i < event->tags.values_num; i++)
	{
		zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[i];

		ptr += zbx_serialize_str(ptr, tag->tag, len[i * 2]);
		ptr += zbx_serialize_str(ptr, tag->value, len[i * 2 + 1]);
	}

	zbx_free(len);
}
/*
void	zbx_availability_deserialize(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_availability_ptr_t  *interface_availabilities)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_interface_availability_t	*interface_availability;
		zbx_uint32_t			deserialize_error_len;


		interface_availability = (zbx_interface_availability_t *)zbx_malloc(NULL,
				sizeof(zbx_interface_availability_t));

		zbx_vector_availability_ptr_append(interface_availabilities, interface_availability);

		interface_availability->id = interface_availabilities->values_num;

		data += zbx_deserialize_value(data, &interface_availability->interfaceid);
		data += zbx_deserialize_value(data, &interface_availability->agent.flags);
		data += zbx_deserialize_value(data, &interface_availability->agent.available);
		data += zbx_deserialize_value(data, &interface_availability->agent.errors_from);
		data += zbx_deserialize_value(data, &interface_availability->agent.disable_until);
		data += zbx_deserialize_str(data, &interface_availability->agent.error, deserialize_error_len);
	}
}*/

