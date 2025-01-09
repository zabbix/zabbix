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

#ifndef ZABBIX_LISTENER_H
#define ZABBIX_LISTENER_H

#include "zbxthreads.h"
#include "zbxcomms.h"

typedef struct
{
	zbx_socket_t		*listen_sock;
	zbx_config_tls_t	*zbx_config_tls;
	zbx_get_program_type_f	zbx_get_program_type_cb_arg;
	const char		*config_file;
	int			config_timeout;
	const char		*config_hosts_allowed;
}
zbx_thread_listener_args;

ZBX_THREAD_ENTRY(listener_thread, args);

#endif
