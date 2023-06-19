/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

void	zbx_discoverer_init(void);

void	*zbx_discovery_open(void);
void	zbx_discovery_update_host(void *handle, zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip,
		const char *dns, int status, time_t now, zbx_add_event_func_t add_event_cb);
void	zbx_discovery_update_service(void *handle, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		zbx_uint64_t unique_dcheckid, zbx_db_dhost *dhost, const char *ip, const char *dns, int port,
		int status, const char *value, time_t now, zbx_add_event_func_t add_event_cb);
void	zbx_discovery_close(void *handle);

void	zbx_discovery_dcheck_free(zbx_dc_dcheck_t *dcheck);
void	zbx_discovery_drule_free(zbx_dc_drule_t *drule);
int	zbx_discovery_get_usage_stats(zbx_vector_dbl_t *usage, int *count, char **error);
int	zbx_discovery_get_queue_size(zbx_uint64_t *size, char **error);
zbx_uint32_t	zbx_discovery_pack_usage_stats(unsigned char **data, const zbx_vector_dbl_t *usage, int count);
void	zbx_discovery_stats_ext_get(struct zbx_json *json, const void *arg);
void	zbx_discovery_get_worker_info(zbx_process_info_t *info);
#endif
