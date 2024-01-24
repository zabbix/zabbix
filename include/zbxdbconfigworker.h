/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_DBCONFIG_WORKER_H
#define ZABBIX_DBCONFIG_WORKER_H

#include "zbxtypes.h"
#include "zbxipcservice.h"

#include "zbxcacheconfig.h"
#include "zbxthreads.h"

#define ZBX_IPC_SERVICE_DBCONFIG_WORKER		"config"
#define ZBX_IPC_DBCONFIG_WORKER_REQUEST		1

void	zbx_dbconfig_worker_serialize_ids(unsigned char **data, size_t *data_offset, const zbx_vector_uint64_t *ids);
void	zbx_dbconfig_worker_deserialize_ids(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_uint64_t *ids);
void	zbx_dbconfig_worker_send_ids(const zbx_vector_uint64_t *hostids);

typedef struct
{
	zbx_get_config_forks_f	get_process_forks_cb_arg;
}
zbx_thread_dbconfig_worker_args;

ZBX_THREAD_ENTRY(dbconfig_worker_thread, args);

#endif /* ZABBIX_DBCONFIG_WORKER_H */
