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

#ifndef ZABBIX_ZBXSERVICE_H
#define ZABBIX_ZBXSERVICE_H

#include "zbxtypes.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"

ZBX_PTR_VECTOR_DECL(db_service, zbx_db_service *)

#define ZBX_IPC_SERVICE_SERVICE				"service"
#define ZBX_IPC_SERVICE_SERVICE_PROBLEMS		1
#define ZBX_IPC_SERVICE_SERVICE_PROBLEMS_TAGS		2
#define ZBX_IPC_SERVICE_SERVICE_PROBLEMS_DELETE		3
#define ZBX_IPC_SERVICE_SERVICE_ROOTCAUSE		4
#define ZBX_IPC_SERVICE_SERVICE_PARENT_LIST		5
#define ZBX_IPC_SERVICE_EVENT_SEVERITIES		6
#define ZBX_IPC_SERVICE_RELOAD_CACHE			7
#define ZBX_IPC_SERVICE_SERVICE_EVENTS_SUPPRESS		8
#define ZBX_IPC_SERVICE_SERVICE_EVENTS_UNSUPPRESS	9

void	zbx_service_flush(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size);
void	zbx_service_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size, zbx_ipc_message_t *response);
void	zbx_service_reload_cache(void);

typedef struct
{
	zbx_uint64_t	eventid;
	int		severity;
}
zbx_event_severity_t;

void	zbx_event_severity_free(zbx_event_severity_t *event_severity);

void	zbx_service_serialize_event(unsigned char **data, size_t *data_alloc, size_t *data_offset, zbx_uint64_t eventid,
		int clock, int ns, int value, int severity, const zbx_vector_tags_ptr_t *tags,
		zbx_vector_uint64_t *maintenanceids);
void	zbx_service_deserialize_event(const unsigned char *data, zbx_uint32_t size, zbx_vector_events_ptr_t *events);
void	zbx_service_serialize_problem_tags(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		zbx_uint64_t eventid, const zbx_vector_tags_ptr_t *tags);
void	zbx_service_deserialize_problem_tags(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_events_ptr_t *events);
void	zbx_service_serialize_id(unsigned char **data, size_t *data_alloc, size_t *data_offset, zbx_uint64_t id);
void	zbx_service_deserialize_ids(const unsigned char *data, zbx_uint32_t size, zbx_vector_uint64_t *ids);
void	zbx_service_deserialize_id_pairs(const unsigned char *data, zbx_vector_uint64_pair_t *id_pairs);
void	zbx_service_serialize_rootcause(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		zbx_uint64_t serviceid, const zbx_vector_uint64_t *eventids);
void	zbx_service_deserialize_rootcause(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_db_service_t *services);

zbx_uint32_t	zbx_service_serialize_parentids(unsigned char **data, const zbx_vector_uint64_t *ids);
void	zbx_service_deserialize_parentids(const unsigned char *data, zbx_vector_uint64_t *ids);

ZBX_PTR_VECTOR_DECL(event_severity_ptr, zbx_event_severity_t *)

zbx_uint32_t	zbx_service_serialize_event_severities(unsigned char **data,
		const zbx_vector_event_severity_ptr_t *event_severities);
void	zbx_service_deserialize_event_severities(const unsigned char *data,
		zbx_vector_event_severity_ptr_t *event_severities);

#endif /* ZABBIX_ZBXSERVICE_H */
