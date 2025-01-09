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

#ifndef ZABBIX_ACTIVE_CHECKS_H
#define ZABBIX_ACTIVE_CHECKS_H

#include "zbxthreads.h"
#include "zbxcomms.h"
#include "zbxcfg.h"

#define HOST_INTERFACE_LEN	255	/* UTF-8 characters, not bytes */

typedef struct
{
	zbx_vector_addr_ptr_t	addrs;
	zbx_config_tls_t	*zbx_config_tls;
	zbx_get_program_type_f	zbx_get_program_type_cb_arg;
	char			*config_file;
	int			config_timeout;
	const char		*config_source_ip;
	const char		*config_listen_ip;
	int			config_listen_port;
	const char		*config_hostname;
	const char		*config_host_metadata;
	const char		*config_host_metadata_item;
	int			config_heartbeat_frequency;
	const char		*config_host_interface;
	const char		*config_host_interface_item;
	int			config_buffer_send;
	int			config_buffer_size;
	int			config_eventlog_max_lines_per_second;
	int			config_max_lines_per_second;
	int			config_refresh_active_checks;
}
zbx_thread_activechk_args;

ZBX_THREAD_ENTRY(active_checks_thread, args);

#endif	/* ZABBIX_ACTIVE_CHECKS_H */
