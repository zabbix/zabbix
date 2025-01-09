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

#ifndef ZABBIX_ZBXPGSERVICE_H
#define ZABBIX_ZBXPGSERVICE_H

#include "zbxipcservice.h"
#include "zbxalgo.h"

#define ZBX_IPC_SERVICE_PGSERVICE	"pgservice"

#define ZBX_PG_SERVICE_TIMEOUT		SEC_PER_MIN

#define ZBX_IPC_PGM_HOST_PGROUP_UPDATE		1
#define ZBX_IPC_PGM_GET_PROXY_SYNC_DATA		2
#define ZBX_IPC_PGM_PROXY_SYNC_DATA		3
#define ZBX_IPC_PGM_GET_STATS			4
#define ZBX_IPC_PGM_STATS			5
#define ZBX_IPC_PGM_PROXY_RTDATA		6
#define ZBX_IPC_PGM_GET_ALL_PGROUP_RTDATA	7
#define ZBX_IPC_PGM_ALL_PGROUP_RTDATA		8
#define ZBX_IPC_PGM_STOP			100

#define ZBX_PROXY_SYNC_NONE	0
#define ZBX_PROXY_SYNC_FULL	1
#define ZBX_PROXY_SYNC_PARTIAL	2

#define ZBX_PG_DEFAULT_FAILOVER_DELAY		SEC_PER_MIN
#define ZBX_PG_DEFAULT_FAILOVER_DELAY_STR	"1m"

typedef struct
{
	zbx_uint64_t	objid;
	zbx_uint64_t	srcid;
	zbx_uint64_t	dstid;
}
zbx_objmove_t;

ZBX_VECTOR_DECL(objmove, zbx_objmove_t)

typedef struct
{
	int			status;
	int			proxy_online_num;
	zbx_vector_uint64_t	proxyids;
}
zbx_pg_stats_t;

typedef struct
{
	zbx_uint64_t	proxy_groupid;
	int		status;
	int		proxy_online_num;
	int		proxy_num;
}
zbx_pg_rtdata_t;

void	zbx_pg_update_object_relocations(zbx_uint32_t code, zbx_vector_objmove_t *updates);
int	zbx_pg_get_stats(const char *pg_name, zbx_pg_stats_t *pg_stats, char **error);
int	zbx_pg_get_all_rtdata(zbx_hashset_t *pgroups_rtdata, char **error);
void	zbx_pg_update_proxy_rtdata(zbx_uint64_t proxyid, int lastaccess, int version);

#endif
