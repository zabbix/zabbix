/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_SERVER_H
#define ZABBIX_SERVER_H

/* normal and recovery operations */
#define ZBX_OPERATION_MODE_NORMAL	0
#define ZBX_OPERATION_MODE_RECOVERY	1
#define ZBX_OPERATION_MODE_UPDATE	2

/* algorithms for service status calculation */
#define ZBX_SERVICE_STATUS_CALC_SET_OK			0
#define ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL	1
#define ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE	2

#endif /*ZABBIX_SERVER_H*/
