/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_AUTOREG_SERVER_H
#define ZABBIX_AUTOREG_SERVER_H

#include "zbxautoreg.h"
#include "zbxdbhigh.h"

void	zbx_autoreg_host_free_server(zbx_autoreg_host_t *autoreg_host);

void	zbx_autoreg_update_host_server(const zbx_dc_proxy_t *proxy, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flags,
		int clock, const zbx_events_funcs_t *events_cbs);

void	zbx_autoreg_flush_hosts_server(zbx_vector_autoreg_host_ptr_t *autoreg_hosts, const zbx_dc_proxy_t *proxy,
		const zbx_events_funcs_t *events_cbs);

void	zbx_autoreg_prepare_host_server(zbx_vector_autoreg_host_ptr_t *autoreg_hosts, const char *host, const char *ip,
		const char *dns, unsigned short port, unsigned int connection_type, const char *host_metadata,
		unsigned short flag, int now);

#endif
