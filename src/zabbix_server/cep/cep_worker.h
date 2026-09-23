/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_CEP_WORKER_H
#define ZABBIX_CEP_WORKER_H

#include "zbxmw.h"
#include "zbxexport.h"
#include "zbxipcservice.h"
#include "zbxtypes.h"

typedef struct
{
	zbx_mw_worker_t		base;


	zbx_dbconn_pool_t	*dbpool;

	zbx_ipc_async_socket_t	rtc;

	zbx_export_file_t	*problem_export;
}
zbx_cep_worker_t;

zbx_cep_worker_t	*cep_worker_create( zbx_dbconn_pool_t *dbpool);
void	*cep_worker_entry(void *args);

#endif /* ZABBIX_CEP_WORKER_H */
