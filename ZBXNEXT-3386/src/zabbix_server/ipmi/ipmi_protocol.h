/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#ifndef ZABBIX_IPMI_PROTOCOL_H
#define ZABBIX_IPMI_PROTOCOL_H

#define ZBX_IPC_SERVICE_IPMI	"ipmi"

#define ZBX_IPC_IPMI_REGISTER		1
#define ZBX_IPC_IPMI_REQUEST		2
#define ZBX_IPC_IPMI_RESULT		3


zbx_uint32_t	zbx_ipmi_serialize_value_request(unsigned char **data, zbx_uint64_t itemid, const char *addr,
		unsigned short port, signed char authtype, unsigned char privilege, const char *username,
		const char *password, const char *sensor);

void	zbx_ipmi_deserialize_value_request(const unsigned char *data, zbx_uint64_t *itemid, char **addr,
		unsigned short *port, signed char *authtype, unsigned char *privilege, char **username, char **password,
		char **sensor);

zbx_uint32_t	zbx_ipmi_serialize_value_response(unsigned char **data, zbx_uint64_t itemid, const zbx_timespec_t *ts,
		int errcode, const char *value);

void	zbx_ipmi_deserialize_value_response(const unsigned char *data, zbx_uint64_t *itemid, zbx_timespec_t *ts,
		int *errcode, char **value);

#endif
