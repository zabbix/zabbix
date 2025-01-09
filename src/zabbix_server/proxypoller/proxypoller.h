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

#ifndef ZABBIX_PROXYPOLLER_H
#define ZABBIX_PROXYPOLLER_H

#include "zbxthreads.h"
#include "zbxcomms.h"
#include "zbxdbhigh.h"
#include "zbxvault.h"

typedef struct
{
	zbx_config_tls_t		*config_tls;
	zbx_config_vault_t		*config_vault;
	zbx_get_program_type_f		zbx_get_program_type_cb_arg;
	int				config_timeout;
	int				config_trapper_timeout;
	const char			*config_source_ip;
	const char			*config_ssl_ca_location;
	const char			*config_ssl_cert_location;
	const char			*config_ssl_key_location;
	const zbx_events_funcs_t	*events_cbs;
	int				proxyconfig_frequency;
	int				proxydata_frequency;
}
zbx_thread_proxy_poller_args;

ZBX_THREAD_ENTRY(proxypoller_thread, args);

#endif
