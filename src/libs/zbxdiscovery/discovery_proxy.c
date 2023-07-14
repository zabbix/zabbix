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

#include "zbxdiscovery.h"
#include "zbxproxybuffer.h"

void	*zbx_discovery_open(void)
{
	return zbx_pb_discovery_open();
}

void	zbx_discovery_close(void *handle)
{
	zbx_pb_discovery_close((zbx_pb_discovery_data_t *)handle);
}

void	zbx_discovery_update_host(void *handle, zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip,
		const char *dns, int status, time_t now, zbx_add_event_func_t add_event_cb)
{
	ZBX_UNUSED(dhost);
	ZBX_UNUSED(add_event_cb);

	zbx_pb_discovery_write_host((zbx_pb_discovery_data_t *)handle, druleid, ip, dns, status, (int)now);
}

void	zbx_discovery_update_service(void *handle, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		zbx_uint64_t unique_dcheckid, zbx_db_dhost *dhost, const char *ip, const char *dns, int port,
		int status, const char *value, time_t now, zbx_add_event_func_t add_event_cb)
{
	ZBX_UNUSED(unique_dcheckid);
	ZBX_UNUSED(dhost);
	ZBX_UNUSED(add_event_cb);

	zbx_pb_discovery_write_service((zbx_pb_discovery_data_t *)handle, druleid, dcheckid, ip, dns, port, status,
			value, (int)now);
}



