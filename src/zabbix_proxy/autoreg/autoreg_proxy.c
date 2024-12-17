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

#include "zbxcacheconfig.h"
#include "autoreg_proxy.h"

#include "zbxproxybuffer.h"

void	zbx_autoreg_update_host_proxy(const zbx_dc_proxy_t *proxy, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flags,
		int clock, const zbx_events_funcs_t *events_cbs)
{
	ZBX_UNUSED(proxy);
	ZBX_UNUSED(events_cbs);

	zbx_pb_autoreg_write_host(host, ip, dns, port, connection_type, host_metadata, (int)flags, clock);
}
