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

#ifndef ZABBIX_CHECKS_JAVA_H
#define ZABBIX_CHECKS_JAVA_H

#include "dbcache.h"
#include "sysinfo.h"

#define ZBX_JAVA_GATEWAY_REQUEST_INTERNAL	0
#define ZBX_JAVA_GATEWAY_REQUEST_JMX		1

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_JAVA_GATEWAY;
extern int	CONFIG_JAVA_GATEWAY_PORT;

int	get_value_java(unsigned char request, DC_ITEM *item, AGENT_RESULT *result);
void	get_values_java(unsigned char request, DC_ITEM *items, AGENT_RESULT *results, int *errcodes, int num);

#endif
