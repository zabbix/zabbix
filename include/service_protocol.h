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

#ifndef ZABBIX_SERVICE_PROTOCOL_H
#define ZABBIX_SERVICE_PROTOCOL_H

#include "zbxservice.h"

typedef struct
{
	zbx_uint64_t	eventid;
	int		severity;
}
zbx_event_severity_t;

void	zbx_service_serialize(unsigned char **data, size_t *data_alloc, size_t *data_offset, zbx_uint64_t eventid,
		int clock, int ns, int value, int severity, const zbx_vector_ptr_t *tags);
void	zbx_service_deserialize(const unsigned char *data, zbx_uint32_t size, zbx_vector_ptr_t *events);
void	zbx_service_serialize_problem_tags(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		zbx_uint64_t eventid, const zbx_vector_tags_t *tags);
void	zbx_service_deserialize_problem_tags(const unsigned char *data, zbx_uint32_t size, zbx_vector_ptr_t *events);
void	zbx_service_serialize_id(unsigned char **data, size_t *data_alloc, size_t *data_offset, zbx_uint64_t id);
void	zbx_service_deserialize_ids(const unsigned char *data, zbx_uint32_t size, zbx_vector_uint64_t *ids);
void	zbx_service_serialize_rootcause(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		zbx_uint64_t serviceid, const zbx_vector_uint64_t *eventids);
void	zbx_service_deserialize_rootcause(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_service_t *services);

zbx_uint32_t	zbx_service_serialize_parentids(unsigned char **data, const zbx_vector_uint64_t *ids);
void	zbx_service_deserialize_parentids(const unsigned char *data, zbx_vector_uint64_t *ids);

zbx_uint32_t	zbx_service_serialize_event_severities(unsigned char **data, const zbx_vector_ptr_t *event_severities);
void	zbx_service_deserialize_event_severities(const unsigned char *data, zbx_vector_ptr_t *event_severities);

#endif
