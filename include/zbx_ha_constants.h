/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
