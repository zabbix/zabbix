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

#ifndef ZABBIX_ZBX_AVAILABILITY_CONSTANTS_H
#define ZABBIX_ZBX_AVAILABILITY_CONSTANTS_H

/* interface availability */
#define ZBX_INTERFACE_AVAILABLE_UNKNOWN		0
#define ZBX_INTERFACE_AVAILABLE_TRUE		1
#define ZBX_INTERFACE_AVAILABLE_FALSE		2

#define ZBX_IPC_SERVICE_AVAILABILITY		"availability"
#define ZBX_IPC_AVAILABILITY_REQUEST		1
#define ZBX_IPC_AVAILMAN_ACTIVE_HB		2
#define ZBX_IPC_AVAILMAN_ACTIVE_HOSTDATA	3
#define ZBX_IPC_AVAILMAN_ACTIVE_STATUS		4
#define ZBX_IPC_AVAILMAN_CONFSYNC_DIFF		5
#define ZBX_IPC_AVAILMAN_PROCESS_PROXY_HOSTDATA	6
#define ZBX_IPC_AVAILMAN_ACTIVE_PROXY_HB_UPDATE	7

#endif
