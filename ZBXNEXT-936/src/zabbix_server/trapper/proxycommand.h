/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_PROXYCOMMAND_H
#define ZABBIX_PROXYCOMMAND_H

#include "comms.h"
#include "zbxjson.h"

extern int	CONFIG_TIMEOUT;
extern char	*CONFIG_SOURCE_IP;

int is_monitored_by_proxy(const zbx_uint64_t hostid, zbx_uint64_t *proxy_hostid);
int	send_proxycommand(zbx_sock_t *requester_sock, const zbx_uint64_t proxy_hostid, const char *jbuffer,
		char *error, const int err_len);
int	recv_proxycommand(zbx_sock_t *sock, struct zbx_json_parse *jp);

#endif
