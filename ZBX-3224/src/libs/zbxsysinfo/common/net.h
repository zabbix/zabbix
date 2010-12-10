/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_SYSINFO_COMMON_NET_H
#define ZABBIX_SYSINFO_COMMON_NET_H

#include "sysinfo.h"

extern char	*CONFIG_SOURCE_IP;

int	tcp_expect(const char *host, unsigned short port, const char *request,
		const char *expect, const char *sendtoclose, int *value_int);
int	NET_TCP_PORT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_TCP_DNS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_TCP_DNS_QUERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_NET_H */
