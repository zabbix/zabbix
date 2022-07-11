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

#include "avail_protocol.h"

#include "zbxserialize.h"

void	zbx_availability_serialize(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_interface_availability_t *interface_availability)
{
	zbx_uint32_t	data_len = 0, error_len;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, interface_availability->interfaceid);

	zbx_serialize_prepare_value(data_len, interface_availability->agent.flags);
	zbx_serialize_prepare_value(data_len, interface_availability->agent.available);
	zbx_serialize_prepare_value(data_len, interface_availability->agent.errors_from);
	zbx_serialize_prepare_value(data_len, interface_availability->agent.disable_until);
	zbx_serialize_prepare_str_len(data_len, interface_availability->agent.error, error_len);

	if (NULL == *data)
		*data = (unsigned char *)zbx_malloc(NULL, (*data_alloc = MAX(1024, data_len)));

	while (data_len > *data_alloc - *data_offset)
	{
		*data_alloc *= 2;
		*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
	}
	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, interface_availability->interfaceid);
	ptr += zbx_serialize_value(ptr, interface_availability->agent.flags);
	ptr += zbx_serialize_value(ptr, interface_availability->agent.available);
	ptr += zbx_serialize_value(ptr, interface_availability->agent.errors_from);
	ptr += zbx_serialize_value(ptr, interface_availability->agent.disable_until);
	zbx_serialize_str(ptr, interface_availability->agent.error, error_len);
}

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
}
