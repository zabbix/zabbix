/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_TRAPPER_H
#define ZABBIX_TRAPPER_H

#include "zbxthreads.h"
#include "zbxcomms.h"
#include "zbxvault.h"

extern int	CONFIG_TRAPPER_TIMEOUT;
extern char	*CONFIG_STATS_ALLOWED_IP;

#define ZBX_IPC_SERVICE_TRAPPER	"trapper"

typedef struct
{
	zbx_config_comms_args_t	*config_comms;
	zbx_config_vault_t	*config_vault;
	zbx_get_program_type_f	zbx_get_program_type_cb_arg;
	zbx_socket_t		*listen_sock;
	int			config_startup_time;
}
zbx_thread_trapper_args;

ZBX_THREAD_ENTRY(trapper_thread, args);

#endif
