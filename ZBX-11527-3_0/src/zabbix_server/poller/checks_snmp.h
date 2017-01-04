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

#ifndef ZABBIX_CHECKS_SNMP_H
#define ZABBIX_CHECKS_SNMP_H

#include "common.h"
#include "log.h"
#include "dbcache.h"
#include "sysinfo.h"

extern char	*CONFIG_SOURCE_IP;
extern int	CONFIG_TIMEOUT;

#ifdef HAVE_NETSNMP
void	zbx_init_snmp(void);
int	get_value_snmp(const DC_ITEM *item, AGENT_RESULT *result);
void	get_values_snmp(const DC_ITEM *items, AGENT_RESULT *results, int *errcodes, int num);
#endif

#endif
