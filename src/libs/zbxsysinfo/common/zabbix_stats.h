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

#ifndef ZABBIX_SYSINFO_COMMON_ZABBIX_STATS_H_
#define ZABBIX_SYSINFO_COMMON_ZABBIX_STATS_H_

#include "module.h"

extern char	*CONFIG_SOURCE_IP;
extern int	CONFIG_TIMEOUT;

int	zbx_get_remote_zabbix_stats(const char *ip, unsigned short port, AGENT_RESULT *result);
int	zbx_get_remote_zabbix_stats_queue(const char *ip, unsigned short port, const char *from, const char *to,
		AGENT_RESULT *result);

int	ZABBIX_STATS(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_ZABBIX_STATS_H_ */
