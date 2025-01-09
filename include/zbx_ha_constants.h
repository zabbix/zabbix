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

#ifndef ZABBIX_HA_CONSTANTS_H
#define ZABBIX_HA_CONSTANTS_H

#define ZBX_HA_DEFAULT_FAILOVER_DELAY		SEC_PER_MIN

#define ZBX_NODE_STATUS_HATIMEOUT		-3
#define ZBX_NODE_STATUS_ERROR			-2
#define ZBX_NODE_STATUS_UNKNOWN			-1
#define ZBX_NODE_STATUS_STANDBY			0
#define ZBX_NODE_STATUS_STOPPED			1
#define ZBX_NODE_STATUS_UNAVAILABLE		2
#define ZBX_NODE_STATUS_ACTIVE			3

#endif
