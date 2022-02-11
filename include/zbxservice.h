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

#ifndef ZABBIX_SERVICE_H
#define ZABBIX_SERVICE_H

#include "zbxtypes.h"
#include "db.h"
#include "zbxipcservice.h"

ZBX_PTR_VECTOR_DECL(service, DB_SERVICE *)

#define ZBX_IPC_SERVICE_SERVICE			"service"
#define ZBX_IPC_SERVICE_SERVICE_PROBLEMS	1
#define ZBX_IPC_SERVICE_SERVICE_PROBLEMS_TAGS	2
#define ZBX_IPC_SERVICE_SERVICE_PROBLEMS_DELETE	3
#define ZBX_IPC_SERVICE_SERVICE_ROOTCAUSE	4
#define ZBX_IPC_SERVICE_SERVICE_PARENT_LIST	5
#define ZBX_IPC_SERVICE_EVENT_SEVERITIES	6
#define ZBX_IPC_SERVICE_RELOAD_CACHE		7

void	zbx_service_flush(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size);
void	zbx_service_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size, zbx_ipc_message_t *response);
void	zbx_service_reload_cache(void);

#endif /* ZABBIX_AVAILABILITY_H */
