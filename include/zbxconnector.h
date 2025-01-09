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

#ifndef ZABBIX_CONNECTOR_H
#define ZABBIX_CONNECTOR_H

#include "zbxtypes.h"
#include "zbxipcservice.h"

#include "zbxcacheconfig.h"

#define ZBX_IPC_SERVICE_CONNECTOR		"connector"

#define ZBX_IPC_CONNECTOR_WORKER		1
#define ZBX_IPC_CONNECTOR_REQUEST		2
#define ZBX_IPC_CONNECTOR_RESULT		3
#define ZBX_IPC_CONNECTOR_DIAG_STATS		4
#define ZBX_IPC_CONNECTOR_DIAG_STATS_RESULT	5
#define ZBX_IPC_CONNECTOR_TOP_CONNECTORS	6
#define	ZBX_IPC_CONNECTOR_TOP_CONNECTORS_RESULT	7
#define ZBX_IPC_CONNECTOR_QUEUE			8
#define ZBX_IPC_CONNECTOR_QUEUE_RESULT		9

typedef struct
{
	zbx_uint64_t		objectid;
	zbx_timespec_t		ts;
	char			*str;
	zbx_vector_uint64_t	ids;
}
zbx_connector_object_t;

ZBX_PTR_VECTOR_DECL(connector_object, zbx_connector_object_t)

typedef struct
{
	zbx_timespec_t		ts;
	char			*str;
}
zbx_connector_data_point_t;

ZBX_PTR_VECTOR_DECL(connector_data_point, zbx_connector_data_point_t)

typedef struct
{
	zbx_uint64_t	connectorid;
	int		values_num;
	int		links_num;
	int		queued_links_num;
}
zbx_connector_stat_t;

ZBX_PTR_VECTOR_DECL(connector_stat_ptr, zbx_connector_stat_t *)

void	connector_stat_free(zbx_connector_stat_t *connector_stat);

void	zbx_connector_serialize_object(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_connector_object_t *connector_object);
void	zbx_connector_deserialize_object(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_connector_object_t *connector_objects);
void	zbx_connector_object_free(zbx_connector_object_t connector_object);
void	zbx_connector_serialize_connector(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_connector_t *connector);
void	zbx_connector_serialize_data_point(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_connector_data_point_t *connector_data_point);
void	zbx_connector_deserialize_connector_and_data_point(const unsigned char *data, zbx_uint32_t size,
		zbx_connector_t *connector, zbx_vector_connector_data_point_t *connector_data_points);
void	zbx_connector_data_point_free(zbx_connector_data_point_t connector_data_point);

int		zbx_connector_get_diag_stats(zbx_uint64_t *queued, char **error);
zbx_uint32_t	zbx_connector_pack_diag_stats(unsigned char **data, zbx_uint64_t queued);

int	zbx_connector_get_top_connectors(int limit, zbx_vector_connector_stat_ptr_t *items, char **error);
void	zbx_connector_unpack_top_request(int *limit, const unsigned char *data);
zbx_uint32_t	zbx_connector_pack_top_connectors_result(unsigned char **data, zbx_connector_stat_t **connector_stats,
		int connector_stats_num);
int	zbx_connector_get_queue_size(zbx_uint64_t *size, char **error);

void	zbx_connector_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size);
void	zbx_connector_init(void);
int	zbx_connector_initialized(void);
#endif /* ZABBIX_AVAILABILITY_H */
