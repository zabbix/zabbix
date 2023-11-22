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

#ifndef ZABBIX_PG_SERVICE_H
#define ZABBIX_PG_SERVICE_H

#include "zbxrtc.h"
#include "zbx_rtc_constants.h"
#include "zbxipcservice.h"

#define ZBX_IPC_SERVICE_PG_MANAGER		"pgmanager"

#define ZBX_IPC_PGM_PGROUP_UPDATE		1
#define ZBX_IPC_PGM_PGROUP_REMOVE		2
#define ZBX_IPC_PGM_HOST_PGROUP_UPDATE		3
#define ZBX_IPC_PGM_PROXY_UPDATE		4
#define ZBX_IPC_PGM_RELOAD_CONF			5
#define ZBX_IPC_PGM_STOP			100

typedef struct
{
	zbx_ipc_service_t	service;
	pthread_t		thread;
}
zbx_pg_service_t;

int	pg_service_init(zbx_pg_service_t *service, char **error);
void	pg_service_destroy(zbx_pg_service_t *service);

#endif
