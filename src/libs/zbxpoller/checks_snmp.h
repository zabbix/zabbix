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

#ifndef ZABBIX_CHECKS_SNMP_H
#define ZABBIX_CHECKS_SNMP_H

#include "zbxcommon.h"

#ifdef HAVE_NETSNMP

#include "zbxcacheconfig.h"

#define ZBX_SNMP_STR_HEX	1
#define ZBX_SNMP_STR_STRING	2
#define ZBX_SNMP_STR_OID	3
#define ZBX_SNMP_STR_BITS	4
#define ZBX_SNMP_STR_ASCII	5
#define ZBX_SNMP_STR_UNDEFINED	255

#define ZBX_SNMP_DEFAULT_NUMBER_OF_RETRIES 1

void	get_values_snmp(zbx_dc_item_t *items, AGENT_RESULT *results, int *errcodes, int num,
		unsigned char poller_type, const char *config_source_ip, const int config_timeout,
		const char *progname);


void	zbx_unset_snmp_bulkwalk_options(void);
void	zbx_init_snmp_engineid_cache(void);
void	zbx_clear_snmp_engineid_cache(void);
void	zbx_destroy_snmp_engineid_cache(void);
void	zbx_housekeep_snmp_engineid_cache(void);

#endif

#endif
