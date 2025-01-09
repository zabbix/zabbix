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

#ifndef ZABBIX_CONNECTOR_SERVER_H
#define ZABBIX_CONNECTOR_SERVER_H

#include "zbxthreads.h"

typedef struct
{
	const char	*config_source_ip;
	const char	*config_ssl_ca_location;
	const char	*config_ssl_cert_location;
	const char	*config_ssl_key_location;
}
zbx_thread_connector_worker_args;

ZBX_THREAD_ENTRY(connector_worker_thread, args);

typedef struct
{
	zbx_get_config_forks_f	get_process_forks_cb_arg;
}
zbx_thread_connector_manager_args;

ZBX_THREAD_ENTRY(connector_manager_thread, args);


#endif
