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

#ifndef ZABBIX_SYSINFO_COMMON_NET_H
#define ZABBIX_SYSINFO_COMMON_NET_H

#include "module.h"

extern char	*CONFIG_SOURCE_IP;

#define ZBX_TCP_EXPECT_FAIL	-1
#define ZBX_TCP_EXPECT_OK	0
#define ZBX_TCP_EXPECT_IGNORE	1

int	tcp_expect(const char *host, unsigned short port, int timeout, const char *request,
		int(*validate_func)(const char *), const char *sendtoclose, int *value_int);

int	NET_TCP_PORT(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_NET_H */
