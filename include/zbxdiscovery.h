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

#ifndef ZABBIX_DISCOVERY_H
#define ZABBIX_DISCOVERY_H

#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxstats.h"

#define ZBX_IPC_SERVICE_DISCOVERER	"discoverer"

#define ZBX_IPC_DISCOVERER_QUEUE		10001
#define ZBX_IPC_DISCOVERER_USAGE_STATS		10002
#define ZBX_IPC_DISCOVERER_USAGE_STATS_RESULT	10003

typedef struct
{
	zbx_uint64_t	dcheckid;
	unsigned short	port;
	char		dns[ZBX_INTERFACE_DNS_LEN_MAX];
	char		value[ZBX_MAX_DISCOVERED_VALUE_SIZE];
	int		status;
	time_t		itemtime;
}
zbx_dservice_t;

typedef struct
{
	zbx_uint64_t	druleid;
	char		*error;
}
zbx_discoverer_drule_error_t;
ZBX_PTR_VECTOR_DECL(discoverer_drule_error, zbx_discoverer_drule_error_t)

void	zbx_discoverer_init(void);

typedef	void*(*zbx_discovery_open_func_t)(void);
typedef	void(*zbx_discovery_close_func_t)(void *handle);
typedef	void(*zbx_discovery_find_host_func_t)(const zbx_uint64_t druleid, const char *ip, zbx_db_dhost *dhost);
typedef void(*zbx_discovery_update_host_func_t)(void *handle, zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip,
		const char *dns, int status, time_t now, zbx_add_event_func_t add_event_cb);
typedef	void(*zbx_discovery_update_service_func_t)(void *handle, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		zbx_uint64_t unique_dcheckid, zbx_db_dhost *dhost, const char *ip, const char *dns, int port,
		int status, const char *value, time_t now, zbx_vector_uint64_t *dserviceids,
		zbx_add_event_func_t add_event_cb);
typedef	void(*zbx_discovery_update_service_down_func_t)(const zbx_uint64_t dhostid, const time_t now,
		zbx_vector_uint64_t *dserviceids);
typedef	void(*zbx_discovery_update_drule_func_t)(void *handle, zbx_uint64_t druleid, const char *error, time_t now);

void	zbx_discovery_dcheck_free(zbx_dc_dcheck_t *dcheck);
void	zbx_discovery_drule_free(zbx_dc_drule_t *drule);
int	zbx_discovery_get_usage_stats(zbx_vector_dbl_t *usage, int *count, char **error);
int	zbx_discovery_get_queue_size(zbx_uint64_t *size, char **error);
zbx_uint32_t	zbx_discovery_pack_usage_stats(unsigned char **data, const zbx_vector_dbl_t *usage, int count);
void	zbx_discovery_stats_ext_get(struct zbx_json *json, const void *arg);
void	zbx_discovery_get_worker_info(zbx_process_info_t *info);
void	zbx_discoverer_drule_error_free(zbx_discoverer_drule_error_t value);

#endif
