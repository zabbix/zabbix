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

#ifndef ZABBIX_DBCONFIG_WORKER_H
#define ZABBIX_DBCONFIG_WORKER_H

#include "zbxthreads.h"
#include "zbxalgo.h"

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

ZBX_THREAD_ENTRY(zbx_dbconfig_worker_thread, args);

#endif /* ZABBIX_DBCONFIG_WORKER_H */
