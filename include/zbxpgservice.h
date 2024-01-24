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

#ifndef ZABBIX_ZBXPGSERVICE_H
#define ZABBIX_ZBXPGSERVICE_H

#include "zbxipcservice.h"

#define ZBX_IPC_SERVICE_PG_MANAGER		"pgmanager"

#define ZBX_IPC_PGM_HOST_PGROUP_UPDATE		1
#define ZBX_IPC_PGM_GET_PROXY_SYNC_DATA		2
#define ZBX_IPC_PGM_PROXY_SYNC_DATA		3
#define ZBX_IPC_PGM_GET_STATS			4
#define ZBX_IPC_PGM_STATS			5
#define ZBX_IPC_PGM_STOP			100

#define ZBX_PROXY_SYNC_NONE	0
#define ZBX_PROXY_SYNC_FULL	1
#define ZBX_PROXY_SYNC_PARTIAL	2

#define ZBX_PG_DEFAULT_FAILOVER_DELAY		SEC_PER_MIN
#define ZBX_PG_DEFAULT_FAILOVER_DELAY_STR	"1m"

typedef struct
{
	int			status;
	int			proxy_online_num;
	zbx_vector_uint64_t	proxyids;
}
zbx_pg_stats_t;

int	zbx_pg_service_get_stats(const char *pg_name, zbx_pg_stats_t *pg_stats, char **error);

#endif
