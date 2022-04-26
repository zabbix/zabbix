/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_AVAIL_PROTOCOL_H
#define ZABBIX_AVAIL_PROTOCOL_H

#include "db.h"

void	zbx_availability_serialize_interface(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_interface_availability_t *interface_availability);

void	zbx_availability_deserialize(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_availability_ptr_t  *interface_availabilities);

void	zbx_availability_deserialize_active_hb(const unsigned char *data, zbx_host_active_avail_t *avail);

zbx_uint32_t	zbx_availability_serialize_active_heartbeat(unsigned char **data, zbx_uint64_t hostid,
		int heartbeat_freq);

zbx_uint32_t	zbx_availability_serialize_hostdata(unsigned char **data, zbx_hashset_t *queue);
void	zbx_availability_deserialize_hostdata(const unsigned char *data, zbx_vector_ptr_t *hostdata);

zbx_uint32_t	zbx_availability_serialize_active_status_request(unsigned char **data, zbx_uint64_t hostid);
void	zbx_availability_deserialize_active_status_request(const unsigned char *data, zbx_uint64_t *hostid);

zbx_uint32_t	zbx_availability_serialize_active_status_response(unsigned char **data, int status);
void	zbx_availability_deserialize_active_status_response(const unsigned char *data, int *status);

zbx_uint32_t	zbx_availability_serialize_hostdata2(unsigned char **data, zbx_vector_ptr_t *hosts, zbx_uint64_t proxy_hostid);
void	zbx_availability_deserialize_hostdata2(const unsigned char *data, zbx_vector_ptr_t *hostdata, zbx_uint64_t *proxy_hostid);

zbx_uint32_t	zbx_availability_serialize_hostid(unsigned char **data, zbx_uint64_t hostid);
void	zbx_availability_deserialize_hostid(const unsigned char *data, zbx_uint64_t *hostid);

zbx_uint32_t	zbx_availability_serialize_hostids(unsigned char **data, zbx_vector_uint64_t *hostids);
void	zbx_availability_deserialize_hostids(const unsigned char *data, zbx_vector_uint64_t *hostids);

#endif
