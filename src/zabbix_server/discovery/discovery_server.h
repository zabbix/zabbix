/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_DISCOVERY_SERVER_H
#define ZABBIX_DISCOVERY_SERVER_H

#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxstats.h"

void	*zbx_discovery_open_server(void);
void	zbx_discovery_update_host_server(void *handle, zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip,
		const char *dns, int status, time_t now, zbx_add_event_func_t add_event_cb);
void	zbx_discovery_update_service_server(void *handle, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		zbx_uint64_t unique_dcheckid, zbx_db_dhost *dhost, const char *ip, const char *dns, int port,
		int status, const char *value, time_t now, zbx_add_event_func_t add_event_cb);
void	zbx_discovery_close_server(void *handle);
#endif
