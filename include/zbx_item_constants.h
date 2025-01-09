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

#ifndef ZABBIX_ZBX_ITEM_CONSTANTS_H
#define ZABBIX_ZBX_ITEM_CONSTANTS_H

/* item statuses */
#define ITEM_STATUS_ACTIVE		0
#define ITEM_STATUS_DISABLED		1

/* item states */
#define ITEM_STATE_NORMAL		0
#define ITEM_STATE_NOTSUPPORTED		1

#define ZBX_HC_ITEM_STATUS_NORMAL	0
#define ZBX_HC_ITEM_STATUS_BUSY		1

/* discovery rule */
#define DRULE_STATUS_MONITORED		0
#define DRULE_STATUS_NOT_MONITORED	1

#define ITEM_LOGTYPE_INFORMATION	1
#define ITEM_LOGTYPE_WARNING		2
#define ITEM_LOGTYPE_ERROR		4
#define ITEM_LOGTYPE_FAILURE_AUDIT	7
#define ITEM_LOGTYPE_SUCCESS_AUDIT	8
#define ITEM_LOGTYPE_CRITICAL		9
#define ITEM_LOGTYPE_VERBOSE		10

#define HTTPTEST_STATUS_MONITORED       0
#define HTTPTEST_STATUS_NOT_MONITORED	1

#endif /*ZABBIX_ZBX_ITEM_CONSTANTS_H*/
