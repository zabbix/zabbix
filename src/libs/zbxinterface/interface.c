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

#include "zbxinterface.h"

#include "zbxcommon.h"

static const int	interface_type_priority[INTERFACE_TYPE_COUNT] =
{
	INTERFACE_TYPE_AGENT,
	INTERFACE_TYPE_SNMP,
	INTERFACE_TYPE_JMX,
	INTERFACE_TYPE_IPMI
};

/******************************************************************************
 *                                                                            *
 * Return value: Interface type                                               *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
unsigned char	zbx_get_interface_type_by_item_type(unsigned char type)
{
	switch (type)
	{
		case ITEM_TYPE_ZABBIX:
			return INTERFACE_TYPE_AGENT;
		case ITEM_TYPE_SNMP:
		case ITEM_TYPE_SNMPTRAP:
			return INTERFACE_TYPE_SNMP;
		case ITEM_TYPE_IPMI:
			return INTERFACE_TYPE_IPMI;
		case ITEM_TYPE_JMX:
			return INTERFACE_TYPE_JMX;
		case ITEM_TYPE_SIMPLE:
		case ITEM_TYPE_EXTERNAL:
		case ITEM_TYPE_SSH:
		case ITEM_TYPE_TELNET:
			return INTERFACE_TYPE_ANY;
		case ITEM_TYPE_HTTPAGENT:
		case ITEM_TYPE_SCRIPT:
		case ITEM_TYPE_BROWSER:
			return INTERFACE_TYPE_OPT;
		default:
			return INTERFACE_TYPE_UNKNOWN;
	}
}

int	zbx_get_interface_type_priority(int n)
{
	return interface_type_priority[n];
}

const char	*zbx_interface_type_string(zbx_interface_type_t type)
{
	switch (type)
	{
		case INTERFACE_TYPE_AGENT:
			return "Zabbix agent";
		case INTERFACE_TYPE_SNMP:
			return "SNMP";
		case INTERFACE_TYPE_IPMI:
			return "IPMI";
		case INTERFACE_TYPE_JMX:
			return "JMX";
		case INTERFACE_TYPE_OPT:
			return "optional";
		case INTERFACE_TYPE_ANY:
			return "any";
		case INTERFACE_TYPE_UNKNOWN:
		default:
			return "unknown";
	}
}
