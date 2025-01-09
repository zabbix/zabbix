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

#ifndef ZABBIX_PG_SERVICE_H
#define ZABBIX_PG_SERVICE_H

#include "pg_cache.h"
#include "zbxipcservice.h"

typedef struct
{
	zbx_pg_cache_t		*cache;
	zbx_ipc_service_t	service;
	pthread_t		thread;
}
zbx_pg_service_t;

int	pg_service_init(zbx_pg_service_t *service, zbx_pg_cache_t *cache, char **error);
void	pg_service_destroy(zbx_pg_service_t *service);

#endif
