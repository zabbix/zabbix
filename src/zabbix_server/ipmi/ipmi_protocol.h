/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"

#ifdef HAVE_OPENIPMI

#define ZBX_IPC_SERVICE_IPMI	"ipmi"

/* poller -> manager */
#define ZBX_IPC_IPMI_REGISTER		1
#define ZBX_IPC_IPMI_VALUE_RESULT	2
#define ZBX_IPC_IPMI_COMMAND_RESULT	3

/* manager -> poller */
#define ZBX_IPC_IPMI_VALUE_REQUEST	101
#define ZBX_IPC_IPMI_COMMAND_REQUEST	102
#define ZBX_IPC_IPMI_CLEANUP_REQUEST	103

/* client -> manager */
#define ZBX_IPC_IPMI_SCRIPT_REQUEST	201

/* manager -> client */
#define ZBX_IPC_IPMI_SCRIPT_RESULT	301


zbx_uint32_t	zbx_ipmi_serialize_request(unsigned char **data, zbx_uint64_t objectid, const char *addr,
		unsigned short port, signed char authtype, unsigned char privilege, const char *username,
		const char *password, const char *sensor, int command);

void	zbx_ipmi_deserialize_request(const unsigned char *data, zbx_uint64_t *objectid, char **addr,
		unsigned short *port, signed char *authtype, unsigned char *privilege, char **username, char **password,
		char **sensor, int *command);

void	zbx_ipmi_deserialize_request_objectid(const unsigned char *data, zbx_uint64_t *objectid);

zbx_uint32_t	zbx_ipmi_serialize_result(unsigned char **data, const zbx_timespec_t *ts, int errcode,
		const char *value);

void	zbx_ipmi_deserialize_result(const unsigned char *data, zbx_timespec_t *ts, int *errcode, char **value);

#endif

#endif
