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
#include "zbxavailability.h"
#include "log.h"
#include "zbxjson.h"

void	zbx_availability_serialize_interface(unsigned char **data, size_t *data_alloc, size_t *data_offset,
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
		*data = (unsigned char *)zbx_calloc(NULL, (*data_alloc = MAX(1024, data_len)), 1);

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

zbx_uint32_t	zbx_availability_serialize_active_heartbeat(unsigned char **data, zbx_uint64_t hostid, int heartbeat_freq)
{
	zbx_uint32_t	data_len = 0;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, hostid);
	zbx_serialize_prepare_value(data_len, heartbeat_freq);

	ptr = *data = (unsigned char *)zbx_calloc(NULL, data_len, 1);

	ptr += zbx_serialize_value(ptr, hostid);
	(void)zbx_serialize_value(ptr, heartbeat_freq);

	return data_len;
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

void	zbx_availability_deserialize_active_hb(const unsigned char *data, zbx_host_active_avail_t *avail)
{
	data += zbx_deserialize_uint64(data, &avail->hostid);
	(void)zbx_deserialize_int(data, &avail->heartbeat_freq);
}

zbx_uint32_t	zbx_availability_serialize_hostdata(unsigned char **data, zbx_hashset_t *queue)
{
	zbx_hashset_iter_t	iter;
	zbx_host_active_avail_t	*host;
	zbx_uint32_t		data_len = 0;
	unsigned char		*ptr;

	if (0 == queue->num_data)
		return 0;

	zbx_serialize_prepare_value(data_len, queue->num_data);

	data_len += (zbx_uint32_t)(sizeof(zbx_uint64_t) + sizeof(int)) * (zbx_uint32_t)queue->num_data;

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_len);
	ptr += zbx_serialize_value(ptr, queue->num_data);

	zbx_hashset_iter_reset(queue, &iter);

	while (NULL != (host = (zbx_host_active_avail_t *)zbx_hashset_iter_next(&iter)))
	{
		ptr += zbx_serialize_value(ptr, host->hostid);
		ptr += zbx_serialize_value(ptr, host->active_status);
	}

	return data_len;
}

void	zbx_availability_deserialize_hostdata(const unsigned char *data, zbx_vector_proxy_hostdata_ptr_t *hostdata)
{
	int	values_num, i;

	data += zbx_deserialize_value(data, &values_num);

	if (0 == values_num)
		return;

	for (i = 0; i < values_num; i++)
	{
		zbx_proxy_hostdata_t	*h = (zbx_proxy_hostdata_t *)zbx_malloc(NULL, sizeof(zbx_proxy_hostdata_t));

		data += zbx_deserialize_uint64(data, &h->hostid);
		data += zbx_deserialize_int(data, &h->status);

		zbx_vector_proxy_hostdata_ptr_append(hostdata, h);
	}
}

zbx_uint32_t	zbx_availability_serialize_active_status_request(unsigned char **data, zbx_uint64_t hostid)
{
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, hostid);

	*data = (unsigned char *)zbx_calloc(NULL, data_len, 1);

	(void)zbx_serialize_value(*data, hostid);

	return data_len;
}

zbx_uint32_t	zbx_availability_serialize_active_status_response(unsigned char **data, int status)
{
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, status);

	*data = (unsigned char *)zbx_calloc(NULL, data_len, 1);

	(void)zbx_serialize_value(*data, status);

	return data_len;
}

void	zbx_availability_deserialize_active_status_request(const unsigned char *data, zbx_uint64_t *hostid)
{
	(void)zbx_deserialize_uint64(data, hostid);
}

void	zbx_availability_deserialize_active_status_response(const unsigned char *data, int *status)
{
	(void)zbx_deserialize_int(data, status);
}

zbx_uint32_t	zbx_availability_serialize_proxy_hostdata(unsigned char **data, zbx_vector_proxy_hostdata_ptr_t *hosts,
		zbx_uint64_t proxy_hostid)
{
	zbx_uint32_t		data_len = 0;
	unsigned char		*ptr;
	int			i;

	zbx_serialize_prepare_value(data_len, proxy_hostid);
	zbx_serialize_prepare_value(data_len, hosts->values_num);

	data_len += (zbx_uint32_t)(sizeof(zbx_uint64_t) + sizeof(int)) * (zbx_uint32_t)hosts->values_num;

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_len);
	ptr += zbx_serialize_value(ptr, proxy_hostid);
	ptr += zbx_serialize_value(ptr, hosts->values_num);

	for (i = 0; i < hosts->values_num; i++)
	{
		zbx_proxy_hostdata_t	*host;

		host = hosts->values[i];

		ptr += zbx_serialize_value(ptr, host->hostid);
		ptr += zbx_serialize_value(ptr, host->status);
	}

	return data_len;
}

void	zbx_availability_deserialize_proxy_hostdata(const unsigned char *data, zbx_vector_proxy_hostdata_ptr_t *hostdata,
		zbx_uint64_t *proxy_hostid)
{
	int	values_num, i;

	data += zbx_deserialize_value(data, proxy_hostid);
	data += zbx_deserialize_value(data, &values_num);

	if (0 == values_num)
		return;

	for (i = 0; i < values_num; i++)
	{
		zbx_proxy_hostdata_t	*h = (zbx_proxy_hostdata_t *)zbx_malloc(NULL, sizeof(zbx_proxy_hostdata_t));

		data += zbx_deserialize_uint64(data, &h->hostid);
		data += zbx_deserialize_int(data, &h->status);

		zbx_vector_proxy_hostdata_ptr_append(hostdata, h);
	}
}

zbx_uint32_t	zbx_availability_serialize_hostids(unsigned char **data, zbx_vector_uint64_t *hostids)
{
	zbx_uint32_t	data_len = 0;
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, hostids->values_num);

	for (i = 0; i < hostids->values_num; i++)
		zbx_serialize_prepare_value(data_len, hostids->values[i]);

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_len);
	ptr += zbx_serialize_value(ptr, hostids->values_num);

	for (i = 0; i < hostids->values_num; i++)
		ptr += zbx_serialize_value(ptr, hostids->values[i]);

	return data_len;
}

void	zbx_availability_deserialize_hostids(const unsigned char *data, zbx_vector_uint64_t *hostids)
{
	int	values_num, i;

	data += zbx_deserialize_value(data, &values_num);

	if (0 == values_num)
		return;

	zbx_vector_uint64_reserve(hostids, (size_t)values_num);

	for (i = 0; i < values_num; i++)
	{
		zbx_uint64_t	id;

		data += zbx_deserialize_value(data, &id);

		zbx_vector_uint64_append(hostids, id);
	}
}

zbx_uint32_t	zbx_availability_serialize_active_proxy_hb_update(unsigned char **data, zbx_uint64_t hostid)
{
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, hostid);

	*data = (unsigned char *)zbx_calloc(NULL, data_len, 1);

	(void)zbx_serialize_value(*data, hostid);

	return data_len;
}

void	zbx_availability_deserialize_active_proxy_hb_update(const unsigned char *data, zbx_uint64_t *hostid)
{
	(void)zbx_deserialize_uint64(data, hostid);
}
