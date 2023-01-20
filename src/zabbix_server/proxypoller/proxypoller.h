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

#ifndef ZABBIX_PROXYPOLLER_H
#define ZABBIX_PROXYPOLLER_H

#include "zbxthreads.h"
#include "zbxcomms.h"
#include "zbxvault.h"

extern char	*CONFIG_SOURCE_IP;
extern int	CONFIG_TRAPPER_TIMEOUT;

typedef struct
{
	zbx_config_tls_t	*config_tls;
	zbx_config_vault_t	*config_vault;
	zbx_get_program_type_f	zbx_get_program_type_cb_arg;
	int			config_timeout;
}
zbx_thread_proxy_poller_args;

ZBX_THREAD_ENTRY(proxypoller_thread, args);

#endif
