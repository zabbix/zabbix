/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxservice.h"

#include "zbxalgo.h"
#include "zbxserialize.h"
#include "zbxdbhigh.h"

void	zbx_service_serialize_id(unsigned char **data, size_t *data_alloc, size_t *data_offset, zbx_uint64_t id)
{
	zbx_uint32_t	data_len = 0;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, id);

	if (NULL != *data)
	{
		while (data_len > *data_alloc - *data_offset)
		{
			*data_alloc *= 2;
			*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
		}
	}
	else
	{
		*data_alloc = MAX(1024, data_len);
		*data = (unsigned char *)zbx_malloc(NULL, *data_alloc);
	}

	ptr = *data + *data_offset;
	*data_offset += data_len;

	(void)zbx_serialize_value(ptr, id);
}

void	zbx_service_deserialize_ids(const unsigned char *data, zbx_uint32_t size, zbx_vector_uint64_t *ids)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_uint64_t	eventid;

		data += zbx_deserialize_value(data, &eventid);
		zbx_vector_uint64_append(ids, eventid);
	}
}

void	zbx_service_serialize_rootcause(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		zbx_uint64_t serviceid, const zbx_vector_uint64_t *eventids)
{
	zbx_uint32_t	data_len = 0;
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, serviceid);
	zbx_serialize_prepare_value(data_len, eventids->values_num);

	for (i = 0; i < eventids->values_num; i++)
		zbx_serialize_prepare_value(data_len, eventids->values[i]);

	if (NULL != *data)
	{
		while (data_len > *data_alloc - *data_offset)
		{
			*data_alloc *= 2;
			*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
		}
	}
	else
	{
		*data_alloc = MAX(1024, data_len);
		*data = (unsigned char *)zbx_malloc(NULL, *data_alloc);
	}

	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, serviceid);
	ptr += zbx_serialize_value(ptr, eventids->values_num);

	for (i = 0; i < eventids->values_num; i++)
		ptr += zbx_serialize_value(ptr, eventids->values[i]);
}

void	zbx_service_deserialize_rootcause(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_db_service_t *services)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_db_service	*service, service_local;
		int		values_num, i;

		data += zbx_deserialize_value(data, &service_local.serviceid);
		data += zbx_deserialize_value(data, &values_num);

		if (FAIL == (i = zbx_vector_db_service_bsearch(services, &service_local,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			service = NULL;
		}
		else
			service = services->values[i];

		if (0 == values_num)
			continue;

		if (NULL != service)
			zbx_vector_uint64_reserve(&service->eventids, (size_t)values_num);

		for (i = 0; i < values_num; i++)
		{
			zbx_uint64_t	eventid;

			data += zbx_deserialize_value(data, &eventid);

			if (NULL != service)
				zbx_vector_uint64_append(&service->eventids, eventid);
		}
	}
}

zbx_uint32_t	zbx_service_serialize_parentids(unsigned char **data, const zbx_vector_uint64_t *ids)
{
	zbx_uint32_t	data_len = 0;
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, ids->values_num);

	for (i = 0; i < ids->values_num; i++)
		zbx_serialize_prepare_value(data_len, ids->values[i]);

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr += zbx_serialize_value(ptr, ids->values_num);

	for (i = 0; i < ids->values_num; i++)
		ptr += zbx_serialize_value(ptr, ids->values[i]);

	return data_len;
}

void	zbx_service_deserialize_parentids(const unsigned char *data, zbx_vector_uint64_t *ids)
{
	int		values_num, i;

	data += zbx_deserialize_value(data, &values_num);

	if (0 == values_num)
		return;

	zbx_vector_uint64_reserve(ids, (size_t)values_num);

	for (i = 0; i < values_num; i++)
	{
		zbx_uint64_t	id;

		data += zbx_deserialize_value(data, &id);

		zbx_vector_uint64_append(ids, id);
	}
}

