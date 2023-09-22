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

#ifndef ZABBIX_AUTOREG_H
#define ZABBIX_AUTOREG_H

#include "zbxdbhigh.h"

void	zbx_autoreg_host_free(zbx_autoreg_host_t *autoreg_host);

void	zbx_autoreg_update_host(zbx_uint64_t proxyid, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flags,
		int clock, const zbx_events_funcs_t *events_cbs);

void	zbx_autoreg_flush_hosts(zbx_vector_ptr_t *autoreg_hosts, zbx_uint64_t proxyid,
		const zbx_events_funcs_t *events_cbs);

void	zbx_autoreg_prepare_host(zbx_vector_ptr_t *autoreg_hosts, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flag,
		int now);

#endif
