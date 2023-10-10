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

#ifndef ZABBIX_DATASENDER_H
#define ZABBIX_DATASENDER_H

#include "zbxthreads.h"
#include "cfg.h"
#include "zbxcomms.h"

typedef struct
{
	zbx_config_tls_t	*zbx_config_tls;
	zbx_get_program_type_f	zbx_get_program_type_cb_arg;
	int			config_timeout;
	zbx_vector_addr_ptr_t	*config_server_addrs;
	const char		*config_source_ip;
	const char		*config_hostname;
	int			config_proxydata_frequency;
}
zbx_thread_datasender_args;

ZBX_THREAD_ENTRY(datasender_thread, args);

#endif
