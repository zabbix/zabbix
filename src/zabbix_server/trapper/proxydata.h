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

#ifndef ZABBIX_PROXYDATA_H
#define ZABBIX_PROXYDATA_H

#include "zbxcacheconfig.h"
#include "zbxcomms.h"
#include "zbxdbhigh.h"
#include "zbxtime.h"

void	recv_proxy_data(zbx_socket_t *sock, const struct zbx_json_parse *jp, const zbx_timespec_t *ts,
		const zbx_events_funcs_t *events_cbs, int config_timeout, int proxydata_frequency);

int	zbx_send_proxy_data_response(const zbx_dc_proxy_t *proxy, zbx_socket_t *sock, const char *info, int status,
		int upload_status, int config_timeout);

#endif
