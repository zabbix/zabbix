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

#ifndef ZABBIX_DISCOVERER_H
#define ZABBIX_DISCOVERER_H

#include "zbxthreads.h"

#include "zbxdbhigh.h"
#include "zbxcomms.h"
#include "zbxdiscovery.h"

typedef struct
{
	zbx_config_tls_t				*zbx_config_tls;
	zbx_get_program_type_f				zbx_get_program_type_cb_arg;
	zbx_get_progname_f				zbx_get_progname_cb_arg;
	int						config_timeout;
	int						workers_num;
	const char					*config_source_ip;
	const zbx_events_funcs_t			*events_cbs;
	zbx_discovery_open_func_t			discovery_open_cb;
	zbx_discovery_close_func_t			discovery_close_cb;
	zbx_discovery_find_host_func_t			discovery_find_host_cb;
	zbx_discovery_update_host_func_t		discovery_update_host_cb;
	zbx_discovery_update_service_func_t		discovery_update_service_cb;
	zbx_discovery_update_service_down_func_t	discovery_update_service_down_cb;
	zbx_discovery_update_drule_func_t		discovery_update_drule_cb;
}
zbx_thread_discoverer_args;

ZBX_THREAD_ENTRY(zbx_discoverer_thread, args);

#endif
