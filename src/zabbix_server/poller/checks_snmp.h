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

#ifndef ZABBIX_CHECKS_SNMP_H
#define ZABBIX_CHECKS_SNMP_H

#include "config.h"
#include "module.h"
#include "dbcache.h"

extern char	*CONFIG_SOURCE_IP;
extern int	CONFIG_TIMEOUT;

#ifdef HAVE_NETSNMP

#define ZBX_SNMP_STR_HEX	1
#define ZBX_SNMP_STR_STRING	2
#define ZBX_SNMP_STR_OID	3
#define ZBX_SNMP_STR_BITS	4
#define ZBX_SNMP_STR_ASCII	5
#define ZBX_SNMP_STR_UNDEFINED	255

int	get_value_snmp(const DC_ITEM *item, AGENT_RESULT *result, unsigned char poller_type);
void	get_values_snmp(const DC_ITEM *items, AGENT_RESULT *results, int *errcodes, int num, unsigned char poller_type);
void	zbx_clear_cache_snmp(unsigned char process_type, int process_num);
#endif

#endif
