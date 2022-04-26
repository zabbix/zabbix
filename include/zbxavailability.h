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

#ifndef ZABBIX_AVAILABILITY_H
#define ZABBIX_AVAILABILITY_H

#include "zbxtypes.h"
#include "db.h"
#include "zbxipcservice.h"

#define ZBX_IPC_SERVICE_AVAILABILITY		"availability"
#define ZBX_IPC_AVAILABILITY_REQUEST		1
#define ZBX_IPC_AVAILMAN_ACTIVE_HB		2
#define ZBX_IPC_AVAILMAN_ACTIVE_HOSTDATA	3
#define ZBX_IPC_AVAILMAN_ACTIVE_STATUS		4
#define ZBX_IPC_AVAILMAN_CONFSYNC_DIFF		5
#define ZBX_IPC_AVAILMAN_PROCESS_PROXY_HOSTDATA	6
#define ZBX_IPC_AVAILMAN_PROXY_FLUSH_ALL_HOSTS	7
#define ZBX_IPC_AVAILMAN_ADD_HOSTS		8
#define ZBX_AVAIL_HOSTDATA_FREQUENCY		5
#define ZBX_AVAIL_SERVER_CONN_TIMEOUT		3600

void	zbx_availability_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size, zbx_ipc_message_t *response);
void	zbx_availabilities_flush(const zbx_vector_availability_ptr_t *interface_availabilities);

typedef struct
{
	zbx_hashset_t		hosts;
	zbx_hashset_t		queue;
	zbx_hashset_t		proxy_avail;
	double			last_status_refresh;
	double			last_proxy_avail_refresh;
}
zbx_avail_active_hb_cache_t;

typedef struct
{
	zbx_uint64_t	hostid;
	int		status;
}
zbx_proxy_hostdata_t;

void	zbx_availability_serialize_json_hostdata(zbx_vector_ptr_t *hostdata, struct zbx_json *j);
int	zbx_get_active_agent_availability(zbx_uint64_t hostid);

#endif /* ZABBIX_AVAILABILITY_H */
