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

#include "zbxservice.h"

#include "common.h"
#include "zbxserialize.h"
#include "zbxdbhigh.h"

void	zbx_service_serialize(unsigned char **data, size_t *data_alloc, size_t *data_offset, zbx_uint64_t eventid,
		int clock, int ns, int value, int severity, const zbx_vector_ptr_t *tags)
{
	zbx_uint32_t	data_len = 0, *len = NULL;
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, eventid);
	zbx_serialize_prepare_value(data_len, clock);
	zbx_serialize_prepare_value(data_len, ns);
	zbx_serialize_prepare_value(data_len, value);
	zbx_serialize_prepare_value(data_len, severity);
	zbx_serialize_prepare_value(data_len, tags->values_num);

	if (0 != tags->values_num)
	{
		len = (zbx_uint32_t *)zbx_malloc(NULL, sizeof(zbx_uint32_t) * 2 * (size_t)tags->values_num);
		for (i = 0; i < tags->values_num; i++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)tags->values[i];

			zbx_serialize_prepare_str_len(data_len, tag->tag, len[i * 2]);
			zbx_serialize_prepare_str_len(data_len, tag->value, len[i * 2 + 1]);
		}
	}

	if (NULL != *data)
	{
		while (data_len > *data_alloc - *data_offset)
		{
			*data_alloc *= 2;
			*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
		}
	}
	else
		*data = (unsigned char *)zbx_malloc(NULL, (*data_alloc = MAX(1024, data_len)));

	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, eventid);
	ptr += zbx_serialize_value(ptr, clock);
	ptr += zbx_serialize_value(ptr, ns);
	ptr += zbx_serialize_value(ptr, value);
	ptr += zbx_serialize_value(ptr, severity);
	ptr += zbx_serialize_value(ptr, tags->values_num);

	for (i = 0; i < tags->values_num; i++)
	{
		zbx_tag_t	*tag = (zbx_tag_t *)tags->values[i];

		ptr += zbx_serialize_str(ptr, tag->tag, len[i * 2]);
		ptr += zbx_serialize_str(ptr, tag->value, len[i * 2 + 1]);
	}

	zbx_free(len);
}

void	zbx_service_deserialize(const unsigned char *data, zbx_uint32_t size, zbx_vector_ptr_t *events)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_event_t	*event;
		int		values_num, i;

		event = (zbx_event_t *)zbx_malloc(NULL, sizeof(zbx_event_t));
		zbx_vector_ptr_create(&event->tags);
		zbx_vector_ptr_append(events, event);

		data += zbx_deserialize_value(data, &event->eventid);
		data += zbx_deserialize_value(data, &event->clock);
		data += zbx_deserialize_value(data, &event->ns);
		data += zbx_deserialize_value(data, &event->value);
		data += zbx_deserialize_value(data, &event->severity);
		data += zbx_deserialize_value(data, &values_num);

		if (0 != values_num)
		{
			zbx_vector_ptr_reserve(&event->tags, (size_t)values_num);

			for (i = 0; i < values_num; i++)
			{
				zbx_tag_t	*tag;
				zbx_uint32_t	len;

				tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
				data += zbx_deserialize_str(data, &tag->tag, len);
				data += zbx_deserialize_str(data, &tag->value, len);

				zbx_vector_ptr_append(&event->tags, tag);
			}
		}
	}
}

void	zbx_service_serialize_problem_tags(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		zbx_uint64_t eventid, const zbx_vector_tags_t *tags)
{
	zbx_uint32_t	data_len = 0, *len = NULL;
	int		i;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, eventid);
	zbx_serialize_prepare_value(data_len, tags->values_num);

	if (0 != tags->values_num)
	{
		len = (zbx_uint32_t *)zbx_malloc(NULL, sizeof(zbx_uint32_t) * 2 * (size_t)tags->values_num);
		for (i = 0; i < tags->values_num; i++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)tags->values[i];

			zbx_serialize_prepare_str_len(data_len, tag->tag, len[i * 2]);
			zbx_serialize_prepare_str_len(data_len, tag->value, len[i * 2 + 1]);
		}
	}

	if (NULL != *data)
	{
		while (data_len > *data_alloc - *data_offset)
		{
			*data_alloc *= 2;
			*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
		}
	}
	else
		*data = (unsigned char *)zbx_malloc(NULL, (*data_alloc = MAX(1024, data_len)));

	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, eventid);
	ptr += zbx_serialize_value(ptr, tags->values_num);

	for (i = 0; i < tags->values_num; i++)
	{
		zbx_tag_t	*tag = (zbx_tag_t *)tags->values[i];

		ptr += zbx_serialize_str(ptr, tag->tag, len[i * 2]);
		ptr += zbx_serialize_str(ptr, tag->value, len[i * 2 + 1]);
	}

	zbx_free(len);
}

void	zbx_service_deserialize_problem_tags(const unsigned char *data, zbx_uint32_t size, zbx_vector_ptr_t *events)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		zbx_event_t	*event;
		int		values_num, i;

		event = (zbx_event_t *)zbx_malloc(NULL, sizeof(zbx_event_t));
		zbx_vector_ptr_create(&event->tags);
		zbx_vector_ptr_append(events, event);

		data += zbx_deserialize_value(data, &event->eventid);
		data += zbx_deserialize_value(data, &values_num);

		if (0 != values_num)
		{
			zbx_vector_ptr_reserve(&event->tags, (size_t)values_num);

			for (i = 0; i < values_num; i++)
			{
				zbx_tag_t	*tag;
				zbx_uint32_t	len;

				tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
				data += zbx_deserialize_str(data, &tag->tag, len);
				data += zbx_deserialize_str(data, &tag->value, len);

				zbx_vector_ptr_append(&event->tags, tag);
			}
		}
	}
}

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
		*data = (unsigned char *)zbx_malloc(NULL, (*data_alloc = MAX(1024, data_len)));

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
		*data = (unsigned char *)zbx_malloc(NULL, (*data_alloc = MAX(1024, data_len)));

	ptr = *data + *data_offset;
	*data_offset += data_len;

	ptr += zbx_serialize_value(ptr, serviceid);
	ptr += zbx_serialize_value(ptr, eventids->values_num);

	for (i = 0; i < eventids->values_num; i++)
		ptr += zbx_serialize_value(ptr, eventids->values[i]);
}

void	zbx_service_deserialize_rootcause(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_service_t *services)
{
	const unsigned char	*end = data + size;

	while (data < end)
	{
		ZBX_DB_SERVICE	*service, service_local;
		int		values_num, i;

		data += zbx_deserialize_value(data, &service_local.serviceid);
		data += zbx_deserialize_value(data, &values_num);

		if (FAIL == (i = zbx_vector_service_bsearch(services, &service_local, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			service = NULL;
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

	zbx_vector_uint64_reserve(ids, values_num);

	for (i = 0; i < values_num; i++)
	{
		zbx_uint64_t	id;

		data += zbx_deserialize_value(data, &id);

		zbx_vector_uint64_append(ids, id);
	}
}

zbx_uint32_t	zbx_service_serialize_event_severities(unsigned char **data, const zbx_vector_ptr_t *event_severities)
{
	zbx_uint32_t		size;
	unsigned char		*ptr;
	int			i;
	zbx_event_severity_t	*es;

	size = sizeof(event_severities->values_num);
	size += (zbx_uint32_t)event_severities->values_num * (sizeof(es->eventid) + sizeof(es->severity));
	ptr = *data = (unsigned char *)zbx_malloc(NULL, size);

	ptr += zbx_serialize_value(ptr, event_severities->values_num);
	for (i = 0; i < event_severities->values_num; i++)
	{
		es = (zbx_event_severity_t *)event_severities->values[i];

		ptr += zbx_serialize_value(ptr, es->eventid);
		ptr += zbx_serialize_value(ptr, es->severity);
	}

	return size;
}

void	zbx_service_deserialize_event_severities(const unsigned char *data, zbx_vector_ptr_t *event_severities)
{
	int			i, es_num;
	zbx_event_severity_t	*es;

	data += zbx_deserialize_value(data, &es_num);
	zbx_vector_ptr_reserve(event_severities, (size_t)es_num);

	for (i = 0; i < es_num; i++)
	{
		es = (zbx_event_severity_t *)zbx_malloc(NULL, sizeof(zbx_event_severity_t));
		data += zbx_deserialize_value(data, &es->eventid);
		data += zbx_deserialize_value(data, &es->severity);
		zbx_vector_ptr_append(event_severities, es);
	}
}
