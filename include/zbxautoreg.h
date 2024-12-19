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

#ifndef ZABBIX_AUTOREG_H
#define ZABBIX_AUTOREG_H

#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"

#define ZBX_AUTOREG_FLAGS_SKIP_EVENT		0x01

typedef struct
{
	zbx_uint64_t	autoreg_hostid;
	zbx_uint64_t	hostid;
	char		*host;
	char		*ip;
	char		*dns;
	char		*host_metadata;
	int		now;
	unsigned short	port;
	unsigned short	flag;
	unsigned int	connection_type;
	zbx_uint32_t	autoreg_flags;
}
zbx_autoreg_host_t;

ZBX_PTR_VECTOR_DECL(autoreg_host_ptr, zbx_autoreg_host_t*)

int      zbx_autoreg_host_compare_func(const void *d1, const void *d2);

void	zbx_autoreg_host_invalidate_cache(const zbx_vector_autoreg_host_ptr_t *autoreg_hosts);

typedef void	(*zbx_autoreg_host_free_func_t)(zbx_autoreg_host_t *autoreg_host);

typedef void	(*zbx_autoreg_update_host_func_t)(const zbx_dc_proxy_t *proxy, const char *host, const char *ip,
		const char *dns, unsigned short port, unsigned int connection_type, const char *host_metadata,
		unsigned short flags, int clock, const zbx_events_funcs_t *events_cbs);

typedef void	(*zbx_autoreg_flush_hosts_func_t)(zbx_vector_autoreg_host_ptr_t *autoreg_hosts,
		const zbx_dc_proxy_t *proxy, const zbx_events_funcs_t *events_cbs);

typedef void	(*zbx_autoreg_prepare_host_func_t)(zbx_vector_autoreg_host_ptr_t *autoreg_hosts, const char *host,
		const char *ip, const char *dns, unsigned short port, unsigned int connection_type,
		const char *host_metadata, unsigned short flag, int now);

#endif
