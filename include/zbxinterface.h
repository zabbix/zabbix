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

#ifndef ZABBIX_INTERFACE_H
#define ZABBIX_INTERFACE_H

#define ZBX_AGENT_ZABBIX	(INTERFACE_TYPE_AGENT - 1)
#define ZBX_AGENT_SNMP		(INTERFACE_TYPE_SNMP - 1)
#define ZBX_AGENT_IPMI		(INTERFACE_TYPE_IPMI - 1)
#define ZBX_AGENT_JMX		(INTERFACE_TYPE_JMX - 1)
#define ZBX_AGENT_UNKNOWN	255
#define ZBX_AGENT_MAX		INTERFACE_TYPE_COUNT

typedef enum
{
	INTERFACE_TYPE_UNKNOWN = 0,
	INTERFACE_TYPE_AGENT,
	INTERFACE_TYPE_SNMP,
	INTERFACE_TYPE_IPMI,
	INTERFACE_TYPE_JMX,
	INTERFACE_TYPE_OPT = 254,
	INTERFACE_TYPE_ANY = 255
}
zbx_interface_type_t;
const char	*zbx_interface_type_string(zbx_interface_type_t type);

#define INTERFACE_TYPE_COUNT	4	/* number of interface types */
int	zbx_get_interface_type_priority(int n);

unsigned char	zbx_get_interface_type_by_item_type(unsigned char type);

#endif
