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

#ifndef ZABBIX_AUTOREG_PROXY_H
#define ZABBIX_AUTOREG_PROXY_H

#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"

void	zbx_autoreg_update_host_proxy(const zbx_dc_proxy_t *proxy, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flags,
		int clock, const zbx_events_funcs_t *events_cbs);

#endif
