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

#ifndef ZABBIX_ESCALATOR_H
#define ZABBIX_ESCALATOR_H

#include "zbxthreads.h"
#include "zbxcomms.h"

typedef struct
{
	zbx_config_tls_t	*zbx_config_tls;
	zbx_get_program_type_f	zbx_get_program_type_cb_arg;
	int			config_timeout;
	int			config_trapper_timeout;
	const char		*config_source_ip;
	const char		*config_ssh_key_location;
	zbx_get_config_forks_f	get_process_forks_cb_arg;
	int			config_enable_global_scripts;
}
zbx_thread_escalator_args;

ZBX_THREAD_ENTRY(escalator_thread, args);

#endif
