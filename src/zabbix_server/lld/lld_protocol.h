/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef ZABBIX_LLD_PROTOCOL_H
#define ZABBIX_LLD_PROTOCOL_H

#include "common.h"

#define ZBX_IPC_SERVICE_LLD	"lld"

/* LLD -> manager */
#define ZBX_IPC_LLD_REGISTER		1000
#define ZBX_IPC_LLD_RESULT		1001


/* manager -> LLD */
#define ZBX_IPC_LLD_TASK		1100

/* manager -> LLD */
#define ZBX_IPC_LLD_REQUEST		1200


#endif
