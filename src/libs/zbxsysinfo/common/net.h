/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#ifndef ZABBIX_SYSINFO_COMMON_NET_H
#define ZABBIX_SYSINFO_COMMON_NET_H

#include "module.h"

#define ZBX_TCP_EXPECT_FAIL	-1
#define ZBX_TCP_EXPECT_OK	0
#define ZBX_TCP_EXPECT_IGNORE	1

int	tcp_expect(const char *host, unsigned short port, int timeout, const char *request,
		int(*validate_func)(const char *), const char *sendtoclose, int *value_int);

int	net_tcp_port(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_NET_H */
