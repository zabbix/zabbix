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

#include "zbxautoreg.h"
#include "zbxproxybuffer.h"

void	zbx_autoreg_update_host(zbx_uint64_t proxyid, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flags,
		int clock, const zbx_events_funcs_t *events_cbs)
{
	ZBX_UNUSED(proxyid);
	ZBX_UNUSED(events_cbs);

	zbx_pb_autoreg_write_host(host, ip, dns, port, connection_type, host_metadata, (int)flags, clock);
}

void	zbx_autoreg_host_free(zbx_autoreg_host_t *autoreg_host)
{
	ZBX_UNUSED(autoreg_host);
}

void	zbx_autoreg_flush_hosts(zbx_vector_ptr_t *autoreg_hosts, zbx_uint64_t proxyid,
		const zbx_events_funcs_t *events_cbs)
{
	ZBX_UNUSED(autoreg_hosts);
	ZBX_UNUSED(proxyid);
	ZBX_UNUSED(events_cbs);
}

void	zbx_autoreg_prepare_host(zbx_vector_ptr_t *autoreg_hosts, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flag,
		int now)
{
	ZBX_UNUSED(autoreg_hosts);
	ZBX_UNUSED(host);
	ZBX_UNUSED(ip);
	ZBX_UNUSED(dns);
	ZBX_UNUSED(port);
	ZBX_UNUSED(connection_type);
	ZBX_UNUSED(host_metadata);
	ZBX_UNUSED(flag);
	ZBX_UNUSED(now);
}
