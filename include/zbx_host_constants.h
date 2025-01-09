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

#ifndef ZABBIX_ZBX_HOST_CONSTANTS_H
#define ZABBIX_ZBX_HOST_CONSTANTS_H

/* host statuses */
#define HOST_STATUS_MONITORED		0
#define HOST_STATUS_NOT_MONITORED	1
/*#define HOST_STATUS_UNREACHABLE	2*/
#define HOST_STATUS_TEMPLATE		3
/*#define HOST_STATUS_DELETED		4*/

/* host group types */
#define HOSTGROUP_TYPE_HOST		0
#define HOSTGROUP_TYPE_TEMPLATE		1

/* host maintenance status */
#define HOST_MAINTENANCE_STATUS_OFF	0
#define HOST_MAINTENANCE_STATUS_ON	1

/* host inventory mode */
#define HOST_INVENTORY_DISABLED		-1	/* the host has no record in host_inventory */

						/* only in server code, never in DB */
#define HOST_INVENTORY_MANUAL		0
#define HOST_INVENTORY_AUTOMATIC	1
#define HOST_INVENTORY_COUNT		2

#define HOST_INVENTORY_FIELD_COUNT	70

#define HOST_MONITORED_BY_SERVER	0
#define HOST_MONITORED_BY_PROXY		1
#define HOST_MONITORED_BY_PROXY_GROUP	2

#endif /*ZABBIX_ZBX_HOST_CONSTANTS_H*/
