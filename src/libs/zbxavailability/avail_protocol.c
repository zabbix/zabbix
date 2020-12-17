/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "avail_protocol.h"
#include "dbcache.h"

void	zbx_availability_serialize(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_host_availability_t *host_availability)
{
	zbx_uint32_t	data_len = 0;
	int		error_lens[ZBX_AGENT_MAX];
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, host_availability->hostid);

	for (i = 0; i < ZBX_AGENT_MAX; i++)
	{
		zbx_serialize_prepare_value(data_len, host_availability->agents[i].flags);
		zbx_serialize_prepare_value(data_len, host_availability->agents[i].available);
		zbx_serialize_prepare_value(data_len, host_availability->agents[i].errors_from);
		zbx_serialize_prepare_value(data_len, host_availability->agents[i].disable_until);
		zbx_serialize_prepare_str_len(data_len, host_availability->agents[i].error, error_lens[i]);
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

	ptr += zbx_serialize_value(ptr, host_availability->hostid);
	for (i = 0; i < ZBX_AGENT_MAX; i++)
	{
		ptr += zbx_serialize_value(ptr, host_availability->agents[i].flags);
		ptr += zbx_serialize_value(ptr, host_availability->agents[i].available);
		ptr += zbx_serialize_value(ptr, host_availability->agents[i].errors_from);
		ptr += zbx_serialize_value(ptr, host_availability->agents[i].disable_until);
		ptr += zbx_serialize_str(ptr, host_availability->agents[i].error, error_lens[i]);
	}
}

void	zbx_availability_deserialize(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_ptr_t *host_availabilities)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_host_availability_t	*host_availability;
		int			i;

		host_availability = (zbx_host_availability_t *)zbx_malloc(NULL, sizeof(zbx_host_availability_t));

		zbx_vector_ptr_append(host_availabilities, host_availability);

		host_availability->id = host_availabilities->values_num;

		data += zbx_deserialize_value(data, &host_availability->hostid);
		for (i = 0; i < ZBX_AGENT_MAX; i++)
		{
			zbx_uint32_t	len;

			data += zbx_deserialize_value(data, &host_availability->agents[i].flags);
			data += zbx_deserialize_value(data, &host_availability->agents[i].available);
			data += zbx_deserialize_value(data, &host_availability->agents[i].errors_from);
			data += zbx_deserialize_value(data, &host_availability->agents[i].disable_until);
			data += zbx_deserialize_str(data, &host_availability->agents[i].error, len);
		}
	}
}

